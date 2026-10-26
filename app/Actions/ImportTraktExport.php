<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\MovieList;
use App\Models\TraktImport;
use App\Models\Viewing;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use App\Services\Trakt\TraktExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Übernimmt einen Trakt-Export in Filmlog. Idempotent: ein zweiter Import desselben Exports legt nichts doppelt an.
 *
 * Schritt 1 ordnet jedem Film eine TMDB-ID zu (fehlende Filme werden von TMDB geholt); das dauert bei großen
 * Exporten und läuft deshalb in Etappen mit Zeitbudget. Schritt 2 schreibt Sichtungen, Bewertungen,
 * Watchlist und Listen in einer Transaktion.
 */
class ImportTraktExport
{
    /**
     * Pause zwischen TMDB-Requests, damit der Import unter dem Rate-Limit (~50/s) bleibt.
     */
    private const int TMDB_PAUSE_MICROSECONDS = 30_000;

    public function __construct(
        private TmdbClient $tmdb,
        private SyncMovie $syncMovie,
    ) {}

    /**
     * Arbeitet bis zum Zeitbudget. Gibt true zurück, wenn der Import fertig ist, false, wenn eine weitere Etappe nötig ist.
     */
    public function handle(TraktImport $import, int $timeBudgetSeconds = 50): bool
    {
        $deadline = microtime(true) + $timeBudgetSeconds;
        $export = TraktExport::open(Storage::disk('local')->path($import->path));
        $movies = $export->movies();

        $import->fill([
            'status' => TraktImport::RUNNING,
            'total_movies' => count($movies),
            'ignored_files' => $export->ignoredFiles(),
            'started_at' => $import->started_at ?? now(),
        ])->save();

        if (! $this->resolveMovies($import, $movies, $deadline)) {
            return false;
        }

        $this->write($import, $export, $movies);

        return true;
    }

    /**
     * @param  array<string, array{key: string, title: string, year: ?int, tmdb: ?int, imdb: ?string, trakt: ?int}>  $movies
     */
    private function resolveMovies(TraktImport $import, array $movies, float $deadline): bool
    {
        $resolved = $import->resolved;
        $unresolved = collect($import->unresolved)->keyBy('key')->all();
        $knownTmdbIds = Movie::query()->whereIn('tmdb_id', array_filter(array_column($movies, 'tmdb')))->pluck('tmdb_id')->flip();
        $sinceSave = 0;

        foreach ($movies as $key => $movie) {
            if (array_key_exists($key, $resolved)) {
                continue;
            }

            if (microtime(true) >= $deadline) {
                $import->fill(['resolved' => $resolved, 'unresolved' => array_values($unresolved)])->save();

                return false;
            }

            [$tmdbId, $reason] = $this->resolve($movie, $movie['tmdb'] !== null && $knownTmdbIds->has($movie['tmdb']));
            $resolved[$key] = $tmdbId;

            if ($tmdbId === null) {
                $unresolved[$key] = [
                    'key' => $key,
                    'title' => $movie['title'],
                    'year' => $movie['year'],
                    'ids' => collect(['tmdb' => $movie['tmdb'], 'imdb' => $movie['imdb'], 'trakt' => $movie['trakt']])
                        ->filter()->map(fn ($id, $source): string => "{$source} {$id}")->join(', '),
                    'reason' => $reason,
                ];
            }

            if (++$sinceSave >= 25) {
                $import->fill(['resolved' => $resolved, 'unresolved' => array_values($unresolved)])->save();
                $sinceSave = 0;
            }
        }

        $import->fill(['resolved' => $resolved, 'unresolved' => array_values($unresolved)])->save();

        return true;
    }

    /**
     * @param  array{key: string, title: string, year: ?int, tmdb: ?int, imdb: ?string, trakt: ?int}  $movie
     * @return array{0: ?int, 1: string} TMDB-ID (oder null) und Grund, falls nicht zuordenbar
     */
    private function resolve(array $movie, bool $isCached): array
    {
        if ($isCached) {
            return [$movie['tmdb'], ''];
        }

        $tmdbId = $movie['tmdb'];

        if ($tmdbId === null && $movie['imdb'] !== null) {
            $tmdbId = $this->tmdb->findMovieIdByImdbId($movie['imdb']);
            usleep(self::TMDB_PAUSE_MICROSECONDS);

            if ($tmdbId === null) {
                return [null, 'TMDB kennt die IMDb-ID nicht'];
            }
        }

        if ($tmdbId === null) {
            return [null, 'Weder TMDB- noch IMDb-ID im Export'];
        }

        if (Movie::query()->whereKey($tmdbId)->exists()) {
            return [$tmdbId, ''];
        }

        try {
            $this->syncMovie->handle(MovieDetails::fromTmdb($this->tmdb->movie($tmdbId), $this->tmdb->region()));
        } catch (MovieNotFoundException) {
            return [null, 'TMDB kennt die ID nicht (mehr)'];
        } finally {
            usleep(self::TMDB_PAUSE_MICROSECONDS);
        }

        return [$tmdbId, ''];
    }

    /**
     * @param  array<string, array{key: string, title: string, year: ?int, tmdb: ?int, imdb: ?string, trakt: ?int}>  $movies
     */
    private function write(TraktImport $import, TraktExport $export, array $movies): void
    {
        $resolved = $import->resolved;
        $tmdbIdOf = fn (array $entry): ?int => $resolved[$entry['movie']['key']] ?? null;

        $stats = DB::transaction(function () use ($export, $tmdbIdOf): array {
            $stats = [
                'viewings_created' => 0,
                'viewings_existing' => 0,
                'ratings_applied' => 0,
                'watchlist_added' => 0,
                'lists_created' => 0,
                'list_items_added' => 0,
            ];

            foreach ($export->history() as $entry) {
                if (($tmdbId = $tmdbIdOf($entry)) === null) {
                    continue;
                }

                $viewing = Viewing::firstOrCreate(
                    ['source' => 'trakt', 'external_id' => $entry['id']],
                    ['tmdb_id' => $tmdbId, 'watched_at' => $entry['watched_at']],
                );
                $stats[$viewing->wasRecentlyCreated ? 'viewings_created' : 'viewings_existing']++;
            }

            foreach ($export->ratings() as $entry) {
                if (($tmdbId = $tmdbIdOf($entry)) !== null && $this->applyRating($tmdbId, $entry['rating'], $entry['rated_at'])) {
                    $stats['ratings_applied']++;
                }
            }

            $watchlist = MovieList::watchlist();

            foreach ($export->watchlist() as $entry) {
                if (($tmdbId = $tmdbIdOf($entry)) !== null && ! $watchlist->contains($tmdbId)) {
                    $watchlist->addMovie($tmdbId);
                    $stats['watchlist_added']++;
                }
            }

            foreach ($export->lists() as $list) {
                $movieList = MovieList::query()->where('is_watchlist', false)->firstWhere('name', $list['name']);

                if ($movieList === null) {
                    $movieList = MovieList::create([
                        'name' => $list['name'],
                        'is_watchlist' => false,
                        'sort' => (int) MovieList::query()->max('sort') + 1,
                    ]);
                    $stats['lists_created']++;
                }

                foreach ($list['items'] as $entry) {
                    if (($tmdbId = $tmdbIdOf($entry)) !== null && ! $movieList->contains($tmdbId)) {
                        $movieList->addMovie($tmdbId);
                        $stats['list_items_added']++;
                    }
                }
            }

            return $stats;
        });

        $import->fill([
            'status' => TraktImport::DONE,
            'stats' => $stats + [
                'movies' => count($movies),
                'movies_unresolved' => count($import->unresolved),
                'skipped_non_movies' => $export->skippedNonMovies(),
            ],
            'finished_at' => now(),
            'error' => null,
        ])->save();
    }

    /**
     * Trakt bewertet Filme, Filmlog Sichtungen: Die Bewertung geht an die letzte Sichtung, sofern die noch keine hat.
     * Ein bewerteter Film ohne Sichtung bekommt eine Sichtung zum Bewertungsdatum.
     */
    private function applyRating(int $tmdbId, int $rating, ?string $ratedAt): bool
    {
        $latest = Viewing::query()->where('tmdb_id', $tmdbId)->orderByDesc('watched_at')->orderByDesc('id')->first();

        if ($latest === null) {
            Viewing::firstOrCreate(
                ['source' => 'trakt', 'external_id' => "rating:{$tmdbId}"],
                ['tmdb_id' => $tmdbId, 'watched_at' => $ratedAt ?? now(), 'rating' => $rating],
            );

            return true;
        }

        if ($latest->rating !== null) {
            return false;
        }

        $latest->update(['rating' => $rating]);

        return true;
    }
}
