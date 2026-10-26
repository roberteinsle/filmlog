<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Services\Plex\PlexClient;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Gleicht die Plex-Filmbibliothek mit movies.on_plex ab. Filme, die Filmlog noch nicht kennt, werden mit
 * Titel und Jahr angelegt; die Details (FSK, Laufzeit, Genres) holt danach RefreshMovie von TMDB.
 */
class SyncPlexLibrary implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const string LAST_SYNC_CACHE_KEY = 'plex:last-sync';

    public int $timeout = 85;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public int $uniqueFor = 3600;

    public function handle(PlexClient $plex, TmdbClient $tmdb): void
    {
        $rows = [];

        foreach ($plex->movies() as $item) {
            $tmdbId = $item['tmdb'] ?? ($item['imdb'] !== null ? $tmdb->findMovieIdByImdbId($item['imdb']) : null);

            if ($tmdbId === null || $item['title'] === '') {
                continue;
            }

            $rows[$tmdbId] = [
                'tmdb_id' => $tmdbId,
                'title' => $item['title'],
                'year' => $item['year'],
                'on_plex' => true,
                'plex_rating_key' => $item['rating_key'],
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                // Bekannte Filme behalten ihre TMDB-Daten; nur der Plex-Stand wird aktualisiert.
                Movie::upsert($chunk, ['tmdb_id'], ['on_plex', 'plex_rating_key']);
            }

            Movie::query()
                ->where('on_plex', true)
                ->whereNotIn('tmdb_id', array_keys($rows))
                ->update(['on_plex' => false, 'plex_rating_key' => null]);
        });

        Movie::query()
            ->where('on_plex', true)
            ->whereNull('cached_at')
            ->pluck('tmdb_id')
            ->each(fn (int $tmdbId) => RefreshMovie::dispatch($tmdbId));

        Cache::forever(self::LAST_SYNC_CACHE_KEY, ['at' => now()->toIso8601String(), 'movies' => count($rows)]);
    }
}
