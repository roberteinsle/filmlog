<?php

namespace App\Actions;

use App\Models\Movie;
use App\Models\MovieList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Vorschläge für heute Abend: ungesehene Filme von der Watchlist oder (umschaltbar) aus dem Plex-Bestand, passend zu
 * Altersfreigabe, Zeit und Stimmung. Reihenfolge: Watchlist-Rang, dann TMDB-Bewertung – mit leichtem Zufall,
 * damit nicht jeden Abend dieselben drei oben stehen.
 */
class SuggestForTonight
{
    /**
     * Um wie viele Plätze ein Film durch den Zufall höchstens nach vorn rutschen kann.
     */
    private const float JITTER = 4.0;

    public const string SOURCE_WATCHLIST = 'watchlist';

    public const string SOURCE_PLEX = 'plex';

    /**
     * @param  list<int>  $genreIds  Film passt, wenn er mindestens eines der Genres hat; leer = egal
     * @param  list<int>  $excludedTmdbIds  bereits gezeigte Vorschläge
     * @param  list<int>  $providerIds  nur Filme, die bei einem dieser Anbieter streamen; leer = egal
     * @return array{suggestions: Collection<int, Movie>, candidates: int, remaining: int}
     */
    public function handle(
        ?int $maxFsk = null,
        ?int $maxRuntime = null,
        array $genreIds = [],
        array $excludedTmdbIds = [],
        int $seed = 0,
        int $count = 3,
        string $source = self::SOURCE_WATCHLIST,
        array $providerIds = [],
    ): array {
        $candidates = $this->candidates($source, $maxFsk, $maxRuntime, $genreIds, $providerIds)->get();
        $remaining = $candidates->reject(fn (Movie $movie): bool => in_array($movie->tmdb_id, $excludedTmdbIds, true))->values();
        $randomizer = new Randomizer(new Mt19937($seed));

        $suggestions = $remaining
            ->map(fn (Movie $movie, int $position): array => ['movie' => $movie, 'score' => $position + $randomizer->getFloat(0, self::JITTER)])
            ->sortBy('score')
            ->take($count)
            ->pluck('movie')
            ->values();

        return ['suggestions' => $suggestions, 'candidates' => $candidates->count(), 'remaining' => $remaining->count()];
    }

    /**
     * Passende ungesehene Filme in Grundreihenfolge; watchlist_rank ist null für Plex-Filme, die nicht auf der Watchlist stehen.
     *
     * @param  list<int>  $genreIds
     * @param  list<int>  $providerIds
     * @return Builder<Movie>
     */
    private function candidates(string $source, ?int $maxFsk, ?int $maxRuntime, array $genreIds, array $providerIds): Builder
    {
        $watchlistId = MovieList::watchlist()->id;

        return Movie::query()
            ->select('movies.*', 'watchlist.rank as watchlist_rank')
            ->leftJoin('list_items as watchlist', fn ($join) => $join
                ->on('watchlist.tmdb_id', '=', 'movies.tmdb_id')
                ->where('watchlist.list_id', $watchlistId))
            ->when(
                $source === self::SOURCE_PLEX,
                fn (Builder $query) => $query->where('movies.on_plex', true),
                fn (Builder $query) => $query->whereNotNull('watchlist.list_id'),
            )
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('viewings')->whereColumn('viewings.tmdb_id', 'movies.tmdb_id'))
            // Mit FSK-Grenze fallen Filme ohne bekannte Freigabe heraus (sicherer für die Familie).
            ->when($maxFsk !== null, fn (Builder $query) => $query->where('movies.fsk', '<=', $maxFsk))
            ->when($maxRuntime !== null, fn (Builder $query) => $query->where('movies.runtime', '<', $maxRuntime))
            ->when($genreIds !== [], fn (Builder $query) => $query->where(function (Builder $query) use ($genreIds): void {
                foreach ($genreIds as $genreId) {
                    $query->orWhereJsonContains('movies.genre_ids', $genreId);
                }
            }))
            ->when($providerIds !== [], fn (Builder $query) => $query->where(function (Builder $query) use ($providerIds): void {
                foreach ($providerIds as $providerId) {
                    $query->orWhereJsonContains('movies.provider_ids', $providerId);
                }
            }))
            ->orderByRaw('watchlist.rank asc nulls last')
            ->orderByRaw('movies.vote_average desc nulls last')
            ->orderBy('movies.tmdb_id');
    }
}
