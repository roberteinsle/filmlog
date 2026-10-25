<?php

namespace App\Services;

use App\Models\Viewing;
use Illuminate\Support\Facades\DB;

/**
 * Ermittelt den Status-Banner-Zustand für einen oder viele Filme mit zwei Abfragen.
 * „Gesehen“ hat Vorrang vor „Watchlist“, damit kein Film versehentlich doppelt läuft.
 */
class MovieStatusResolver
{
    public function for(int $tmdbId): MovieStatus
    {
        return $this->forMany([$tmdbId])[$tmdbId];
    }

    /**
     * @param  list<int>  $tmdbIds
     * @return array<int, MovieStatus>
     */
    public function forMany(array $tmdbIds): array
    {
        $tmdbIds = array_values(array_unique(array_map('intval', $tmdbIds)));

        if ($tmdbIds === []) {
            return [];
        }

        $viewingsByMovie = Viewing::query()
            ->whereIn('tmdb_id', $tmdbIds)
            ->orderByDesc('watched_at')
            ->get(['tmdb_id', 'watched_at', 'rating'])
            ->groupBy('tmdb_id');

        $onWatchlist = DB::table('list_items')
            ->join('lists', 'lists.id', '=', 'list_items.list_id')
            ->where('lists.is_watchlist', true)
            ->whereIn('list_items.tmdb_id', $tmdbIds)
            ->pluck('list_items.tmdb_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $statuses = [];

        foreach ($tmdbIds as $tmdbId) {
            $viewings = $viewingsByMovie->get($tmdbId);
            $isOnWatchlist = $onWatchlist->has($tmdbId);

            $statuses[$tmdbId] = match (true) {
                $viewings !== null => new MovieStatus(
                    state: WatchState::Seen,
                    onWatchlist: $isOnWatchlist,
                    lastWatchedAt: $viewings->first()->watched_at,
                    lastRating: $viewings->firstWhere('rating', '!==', null)?->rating,
                    viewingCount: $viewings->count(),
                ),
                $isOnWatchlist => new MovieStatus(state: WatchState::OnWatchlist, onWatchlist: true),
                default => new MovieStatus(state: WatchState::NeverSeen),
            };
        }

        return $statuses;
    }
}
