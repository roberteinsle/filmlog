<?php

namespace App\Actions;

use App\Models\Movie;
use App\Services\Tmdb\MovieDetails;

/**
 * Schreibt die für Filter und Statistik nötigen Felder in den lokalen Cache (movies).
 */
class SyncMovie
{
    public function handle(MovieDetails $details): Movie
    {
        return Movie::updateOrCreate(
            ['tmdb_id' => $details->tmdbId],
            [
                'title' => $details->title,
                'original_title' => $details->originalTitle,
                'year' => $details->year,
                'runtime' => $details->runtime,
                'fsk' => $details->fsk,
                'poster_path' => $details->posterPath,
                'vote_average' => $details->voteAverage,
                'vote_count' => $details->voteCount,
                'genre_ids' => $details->genreIds,
                'directors' => $details->directors,
                'collection_id' => $details->collectionId,
                'imdb_id' => $details->imdbId,
                'cached_at' => now(),
            ],
        );
    }
}
