<?php

namespace App\Services\Plex;

/**
 * Liest TMDB- und IMDb-ID aus Plex-Metadaten: neuer Agent (Guid: [{id: "tmdb://603"}, …])
 * oder Legacy-Agent (guid: "com.plexapp.agents.themoviedb://603?lang=de" bzw. „…imdb://tt0133093…“).
 */
final class PlexGuids
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return array{tmdb: ?int, imdb: ?string}
     */
    public static function from(array $metadata): array
    {
        $guids = array_filter([
            ...array_map(fn (mixed $guid): mixed => is_array($guid) ? ($guid['id'] ?? null) : null, is_array($metadata['Guid'] ?? null) ? $metadata['Guid'] : []),
            $metadata['guid'] ?? null,
        ], 'is_string');

        $tmdb = null;
        $imdb = null;

        foreach ($guids as $guid) {
            if ($tmdb === null && preg_match('~(?:^tmdb|themoviedb)://(\d+)~', $guid, $match)) {
                $tmdb = (int) $match[1];
            }

            if ($imdb === null && preg_match('~imdb://(tt\d{5,10})~', $guid, $match)) {
                $imdb = $match[1];
            }
        }

        return ['tmdb' => $tmdb, 'imdb' => $imdb];
    }
}
