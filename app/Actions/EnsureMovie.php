<?php

namespace App\Actions;

use App\Models\Movie;
use App\Services\Tmdb\MovieDetails;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;

/**
 * Sorgt dafür, dass ein Film im lokalen Cache steht: über die TMDB-ID oder, falls nur die IMDb-ID
 * bekannt ist, über TMDB /find. Liefert die TMDB-ID oder null, wenn TMDB den Film nicht kennt.
 */
class EnsureMovie
{
    public function __construct(
        private TmdbClient $tmdb,
        private SyncMovie $syncMovie,
    ) {}

    public function handle(?int $tmdbId, ?string $imdbId = null): ?int
    {
        $tmdbId ??= $imdbId !== null ? $this->tmdb->findMovieIdByImdbId($imdbId) : null;

        if ($tmdbId === null) {
            return null;
        }

        if (Movie::query()->whereKey($tmdbId)->exists()) {
            return $tmdbId;
        }

        try {
            $this->syncMovie->handle(MovieDetails::fromTmdb($this->tmdb->movie($tmdbId), $this->tmdb->region()));
        } catch (MovieNotFoundException) {
            return null;
        }

        return $tmdbId;
    }
}
