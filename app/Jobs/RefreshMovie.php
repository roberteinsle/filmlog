<?php

namespace App\Jobs;

use App\Actions\SyncMovie;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Holt einen Film frisch von TMDB (am HTTP-Cache vorbei) und aktualisiert den lokalen Cache in movies.
 * Kennt TMDB den Film nicht mehr, bleibt der gespeicherte Stand erhalten.
 */
class RefreshMovie implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    /**
     * Sperre gegen doppelte Jobs, falls die Queue vom Vortag noch nicht leer ist.
     */
    public int $uniqueFor = 43_200;

    public function __construct(public int $tmdbId) {}

    public function uniqueId(): string
    {
        return (string) $this->tmdbId;
    }

    public function handle(TmdbClient $tmdb, SyncMovie $syncMovie): void
    {
        $tmdb->forgetMovie($this->tmdbId);

        try {
            $syncMovie->handle(MovieDetails::fromTmdb($tmdb->movie($this->tmdbId), $tmdb->region()));
        } catch (MovieNotFoundException) {
            Log::warning("RefreshMovie: TMDB kennt Film {$this->tmdbId} nicht mehr, lokaler Stand bleibt.");
        }
    }
}
