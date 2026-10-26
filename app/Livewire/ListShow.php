<?php

namespace App\Livewire;

use App\Models\Movie;
use App\Models\MovieList;
use App\Services\MovieFilter;
use App\Services\MovieStatus;
use App\Services\MovieStatusResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Eine Liste (Watchlist oder eigene): Filme nach Rang, per Drag & Drop sortierbar, mit kombinierbaren Filtern in der URL.
 */
class ListShow extends Component
{
    #[Locked]
    public MovieList $list;

    #[Url(as: 'bewertung', except: '')]
    public string $minOwnRating = '';

    #[Url(as: 'tmdb', except: '')]
    public string $minTmdbRating = '';

    #[Url(as: 'genre', except: '')]
    public string $genreId = '';

    #[Url(as: 'jahrzehnt', except: '')]
    public string $decade = '';

    #[Url(as: 'laufzeit', except: '')]
    public string $maxRuntime = '';

    #[Url(as: 'fsk', except: '')]
    public string $maxFsk = '';

    public function mount(MovieList $list): void
    {
        $this->list = $list;
    }

    #[Computed]
    public function filter(): MovieFilter
    {
        return MovieFilter::fromInput($this->minOwnRating, $this->minTmdbRating, $this->genreId, $this->decade, $this->maxRuntime, $this->maxFsk);
    }

    /**
     * Die Filme der Liste in Rang-Reihenfolge, gefiltert.
     *
     * @return Collection<int, Movie>
     */
    #[Computed]
    public function movies(): Collection
    {
        return $this->filter->apply($this->listMovies())
            ->select('movies.*', 'list_items.rank')
            ->orderBy('list_items.rank')
            ->orderBy('list_items.added_at')
            ->get();
    }

    /**
     * @return array<int, MovieStatus>
     */
    #[Computed]
    public function statuses(): array
    {
        return app(MovieStatusResolver::class)->forMany($this->movies->modelKeys());
    }

    /**
     * Nur Genres und Jahrzehnte, die in dieser Liste vorkommen.
     *
     * @return array{genres: array<int, string>, decades: list<int>}
     */
    #[Computed]
    public function filterOptions(): array
    {
        $genreIds = DB::table('list_items')
            ->join('movies', 'movies.tmdb_id', '=', 'list_items.tmdb_id')
            ->crossJoin(DB::raw('jsonb_array_elements_text(movies.genre_ids) as genre(id)'))
            ->where('list_items.list_id', $this->list->id)
            ->distinct()
            ->pluck('genre.id')
            ->map(fn ($id): int => (int) $id);

        $genres = collect(config('filmlog.genres'))->only($genreIds)->sort()->all();

        $decades = $this->listMovies()
            ->whereNotNull('movies.year')
            ->selectRaw('distinct (movies.year / 10) * 10 as decade')
            ->orderByDesc('decade')
            ->pluck('decade')
            ->map(fn ($decade): int => (int) $decade)
            ->all();

        return ['genres' => $genres, 'decades' => $decades];
    }

    /**
     * Handler für wire:sort. Mit aktivem Filter sind nicht alle Filme sichtbar; dann wird nicht sortiert.
     */
    public function moveMovie(int|string $tmdbId, int $position): void
    {
        if ($this->filter->isActive()) {
            return;
        }

        $this->list->moveMovie((int) $tmdbId, $position);
        unset($this->movies);
    }

    public function removeMovie(int $tmdbId): void
    {
        $this->list->removeMovie($tmdbId);
        unset($this->movies, $this->statuses, $this->filterOptions);
    }

    public function resetFilters(): void
    {
        $this->reset('minOwnRating', 'minTmdbRating', 'genreId', 'decade', 'maxRuntime', 'maxFsk');
    }

    public function render(): View
    {
        return view('livewire.list-show', [
            'tmdbLevels' => MovieFilter::TMDB_LEVELS,
            'runtimeLimits' => MovieFilter::RUNTIME_LIMITS,
            'fskLevels' => MovieFilter::FSK_LEVELS,
            'totalCount' => $this->listMovies()->count(),
        ])->title($this->list->name);
    }

    /**
     * @return Builder<Movie>
     */
    private function listMovies(): Builder
    {
        return Movie::query()
            ->join('list_items', 'list_items.tmdb_id', '=', 'movies.tmdb_id')
            ->where('list_items.list_id', $this->list->id);
    }
}
