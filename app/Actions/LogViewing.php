<?php

namespace App\Actions;

use App\Models\Viewing;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Legt eine Sichtung samt Begleitungen an (Gesehen-Dialog, später Trakt-Import und Plex).
 */
class LogViewing
{
    /**
     * @param  list<int>  $companionIds
     */
    public function handle(
        int $tmdbId,
        CarbonInterface $watchedAt,
        ?int $rating = null,
        ?string $note = null,
        array $companionIds = [],
        string $source = 'manual',
        ?string $externalId = null,
    ): Viewing {
        return DB::transaction(function () use ($tmdbId, $watchedAt, $rating, $note, $companionIds, $source, $externalId): Viewing {
            $viewing = Viewing::create([
                'tmdb_id' => $tmdbId,
                'watched_at' => $watchedAt,
                'rating' => $rating,
                'note' => $note,
                'source' => $source,
                'external_id' => $externalId,
            ]);

            $viewing->companions()->sync($companionIds);

            return $viewing;
        });
    }
}
