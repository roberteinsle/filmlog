<?php

namespace App\Services\Trakt;

use Illuminate\Support\Str;
use ZipArchive;

/**
 * Liest einen Trakt-Export (ZIP mit JSON-Dateien im Format der Trakt-API) und liefert nur die Filme.
 * Dateinamen werden ohne Ordner verglichen; Serien und Episoden werden übersprungen.
 *
 * @phpstan-type MovieRef array{key: string, title: string, year: ?int, tmdb: ?int, imdb: ?string, trakt: ?int}
 * @phpstan-type HistoryEntry array{id: string, watched_at: string, movie: MovieRef}
 * @phpstan-type RatingEntry array{rating: int, rated_at: ?string, movie: MovieRef}
 * @phpstan-type ListEntry array{rank: int, listed_at: ?string, movie: MovieRef}
 */
class TraktExport
{
    /**
     * Größte JSON-Datei, die gelesen wird (Schutz vor ZIP-Bomben).
     */
    private const int MAX_ENTRY_BYTES = 100 * 1024 * 1024;

    /**
     * Dateien, die Trakt mitliefert, für Filmlog aber keine Rolle spielen.
     */
    private const string IGNORED_PATTERN = '/^(watched|collection|ratings|comments|likes|user|followers|following|friends|recommendations|hidden|playback|stats|settings|profile|notes)[-.]/';

    /** @var list<HistoryEntry> */
    private array $history = [];

    /** @var list<RatingEntry> */
    private array $ratings = [];

    /** @var list<ListEntry> */
    private array $watchlist = [];

    /** @var array<string, array{name: string, items: list<ListEntry>}> */
    private array $lists = [];

    /** @var list<string> */
    private array $ignoredFiles = [];

    private int $skippedNonMovies = 0;

    public static function open(string $path): self
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidTraktExportException('Die Datei ist kein lesbares ZIP-Archiv.');
        }

        try {
            $export = new self;
            $export->read($zip);

            return $export;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<HistoryEntry>
     */
    public function history(): array
    {
        return $this->history;
    }

    /**
     * @return list<RatingEntry>
     */
    public function ratings(): array
    {
        return $this->ratings;
    }

    /**
     * Watchlist-Filme nach Trakt-Rang.
     *
     * @return list<ListEntry>
     */
    public function watchlist(): array
    {
        return $this->watchlist;
    }

    /**
     * Eigene Listen (nach Slug), Filme nach Trakt-Rang.
     *
     * @return array<string, array{name: string, items: list<ListEntry>}>
     */
    public function lists(): array
    {
        return $this->lists;
    }

    /**
     * Alle vorkommenden Filme, einmal je Schlüssel, in stabiler Reihenfolge.
     *
     * @return array<string, MovieRef>
     */
    public function movies(): array
    {
        $movies = [];

        foreach ([$this->history, $this->ratings, $this->watchlist, ...array_column($this->lists, 'items')] as $entries) {
            foreach ($entries as $entry) {
                $movies[$entry['movie']['key']] ??= $entry['movie'];
            }
        }

        return $movies;
    }

    /**
     * @return list<string>
     */
    public function ignoredFiles(): array
    {
        return $this->ignoredFiles;
    }

    public function skippedNonMovies(): int
    {
        return $this->skippedNonMovies;
    }

    private function read(ZipArchive $zip): void
    {
        $listFiles = [];
        $listNames = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = strtolower(basename((string) $stat['name']));

            if (str_ends_with((string) $stat['name'], '/') || ! str_ends_with($name, '.json') || str_starts_with($name, '.')) {
                continue;
            }

            if ($stat['size'] > self::MAX_ENTRY_BYTES) {
                $this->ignoredFiles[] = $name.' (zu groß)';

                continue;
            }

            $kind = match (true) {
                (bool) preg_match('/^(watched-)?history(-\d+)?\.json$/', $name) => 'history',
                $name === 'ratings-movies.json' => 'ratings',
                (bool) preg_match('/^(lists-)?watchlist(-movies)?(-\d+)?\.json$/', $name) => 'watchlist',
                in_array($name, ['lists-lists.json', 'lists.json'], true) => 'list-names',
                str_starts_with($name, 'lists-') => 'list',
                default => null,
            };

            if ($kind === null) {
                if (! preg_match(self::IGNORED_PATTERN, $name)) {
                    $this->ignoredFiles[] = $name;
                }

                continue;
            }

            $data = json_decode((string) $zip->getFromIndex($index), true);

            if (! is_array($data) || ! array_is_list($data)) {
                $this->ignoredFiles[] = $name.' (kein JSON-Array)';

                continue;
            }

            match ($kind) {
                'history' => $this->readHistory($data),
                'ratings' => $this->readRatings($data),
                'watchlist' => array_push($this->watchlist, ...$this->listEntries($data)),
                'list-names' => $listNames = $this->readListNames($data),
                'list' => $listFiles[Str::after(Str::beforeLast($name, '.json'), 'lists-')] = $this->listEntries($data),
            };
        }

        usort($this->watchlist, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        foreach ($listFiles as $slug => $items) {
            usort($items, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
            $this->lists[$slug] = ['name' => $listNames[$slug] ?? Str::headline($slug), 'items' => $items];
        }
    }

    /**
     * @param  list<mixed>  $data
     */
    private function readHistory(array $data): void
    {
        foreach ($data as $item) {
            $movie = $this->movieOf($item);

            if ($movie === null || ! isset($item['id'], $item['watched_at'])) {
                continue;
            }

            $this->history[] = ['id' => (string) $item['id'], 'watched_at' => (string) $item['watched_at'], 'movie' => $movie];
        }
    }

    /**
     * @param  list<mixed>  $data
     */
    private function readRatings(array $data): void
    {
        foreach ($data as $item) {
            $movie = $this->movieOf($item);
            $rating = (int) ($item['rating'] ?? 0);

            if ($movie === null || $rating < 1 || $rating > 10) {
                continue;
            }

            $this->ratings[] = ['rating' => $rating, 'rated_at' => $item['rated_at'] ?? null, 'movie' => $movie];
        }
    }

    /**
     * @param  list<mixed>  $data
     * @return list<ListEntry>
     */
    private function listEntries(array $data): array
    {
        $entries = [];

        foreach ($data as $position => $item) {
            $movie = $this->movieOf($item);

            if ($movie === null) {
                continue;
            }

            $entries[] = ['rank' => (int) ($item['rank'] ?? $position + 1), 'listed_at' => $item['listed_at'] ?? null, 'movie' => $movie];
        }

        return $entries;
    }

    /**
     * @param  list<mixed>  $data
     * @return array<string, string> Slug → Name
     */
    private function readListNames(array $data): array
    {
        $names = [];

        foreach ($data as $list) {
            $slug = $list['ids']['slug'] ?? null;

            if (is_string($slug) && is_string($list['name'] ?? null) && trim($list['name']) !== '') {
                $names[strtolower($slug)] = Str::limit(trim($list['name']), 50, '');
            }
        }

        return $names;
    }

    /**
     * Normalisiert einen Film-Eintrag; Serien, Staffeln und Episoden ergeben null.
     *
     * @return MovieRef|null
     */
    private function movieOf(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        if (($item['type'] ?? 'movie') !== 'movie' || ! is_array($item['movie'] ?? null)) {
            $this->skippedNonMovies++;

            return null;
        }

        $movie = $item['movie'];
        $ids = is_array($movie['ids'] ?? null) ? $movie['ids'] : [];
        $tmdb = is_numeric($ids['tmdb'] ?? null) && (int) $ids['tmdb'] > 0 ? (int) $ids['tmdb'] : null;
        $imdb = is_string($ids['imdb'] ?? null) && preg_match('/^tt\d{5,10}$/', $ids['imdb']) ? $ids['imdb'] : null;
        $trakt = is_numeric($ids['trakt'] ?? null) ? (int) $ids['trakt'] : null;

        $key = match (true) {
            $tmdb !== null => "tmdb:{$tmdb}",
            $imdb !== null => "imdb:{$imdb}",
            $trakt !== null => "trakt:{$trakt}",
            default => 'title:'.Str::slug(($movie['title'] ?? '').'-'.($movie['year'] ?? '')),
        };

        return [
            'key' => $key,
            'title' => (string) ($movie['title'] ?? 'Unbekannter Titel'),
            'year' => is_numeric($movie['year'] ?? null) ? (int) $movie['year'] : null,
            'tmdb' => $tmdb,
            'imdb' => $imdb,
            'trakt' => $trakt,
        ];
    }
}
