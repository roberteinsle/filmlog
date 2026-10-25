<?php

namespace App\Livewire;

use App\Services\MovieStatus;
use App\Services\MovieStatusResolver;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Suche')]
class Search extends Component
{
    private const int MIN_QUERY_LENGTH = 2;

    private const int MAX_QUERY_LENGTH = 100;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    public ?string $error = null;

    /**
     * Treffer der ersten TMDB-Ergebnisseite, jeweils mit Filmlog-Status.
     *
     * @return list<array{id: int, title: string, year: ?string, poster_path: ?string, overview: string, status: MovieStatus}>
     */
    #[Computed]
    public function results(): array
    {
        $this->error = null;
        $query = Str::limit(trim($this->query), self::MAX_QUERY_LENGTH, '');

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return [];
        }

        try {
            $hits = app(TmdbClient::class)->searchMovies($query)['results'];
        } catch (TmdbException $exception) {
            report($exception);
            $this->error = 'TMDB ist gerade nicht erreichbar. Bitte versuch es gleich noch einmal.';

            return [];
        }

        $statuses = app(MovieStatusResolver::class)->forMany(array_column($hits, 'id'));

        return array_map(fn (array $hit): array => [
            'id' => (int) $hit['id'],
            'title' => (string) ($hit['title'] ?? $hit['original_title'] ?? ''),
            'year' => preg_match('/^(\d{4})/', (string) ($hit['release_date'] ?? ''), $match) ? $match[1] : null,
            'poster_path' => $hit['poster_path'] ?? null,
            'overview' => (string) ($hit['overview'] ?? ''),
            'status' => $statuses[(int) $hit['id']],
        ], $hits);
    }

    public function render(): View
    {
        return view('livewire.search', ['minQueryLength' => self::MIN_QUERY_LENGTH]);
    }
}
