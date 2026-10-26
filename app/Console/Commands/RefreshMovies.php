<?php

namespace App\Console\Commands;

use App\Jobs\RefreshMovie;
use App\Models\Movie;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('filmlog:refresh-movies')]
#[Description('Stellt RefreshMovie für veraltete Filme und alle Filme auf Listen in die Queue')]
class RefreshMovies extends Command
{
    public function handle(): int
    {
        $staleBefore = now()->subDays(config('filmlog.movie_cache_days'));

        $tmdbIds = Movie::query()
            ->where(fn ($query) => $query
                ->whereNull('cached_at')
                ->orWhere('cached_at', '<', $staleBefore)
                // Filme auf Listen täglich: Streaming-Anbieter und Bewertungen ändern sich.
                ->orWhereExists(fn ($query) => $query->selectRaw('1')->from('list_items')->whereColumn('list_items.tmdb_id', 'movies.tmdb_id')))
            ->orderBy('tmdb_id')
            ->pluck('tmdb_id');

        $tmdbIds->each(fn (int $tmdbId) => RefreshMovie::dispatch($tmdbId));

        $this->info("{$tmdbIds->count()} Filme zum Aktualisieren eingereiht.");

        return self::SUCCESS;
    }
}
