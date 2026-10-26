<?php

namespace App\Services\Plex;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Dünner Client für die Plex-Media-Server-API (JSON, Auth per X-Plex-Token).
 */
class PlexClient
{
    public function __construct(
        private ?string $url,
        private ?string $token,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    /**
     * Alle Filme aus allen Film-Bibliotheken des Servers.
     *
     * @return list<array{rating_key: string, title: string, year: ?int, tmdb: ?int, imdb: ?string}>
     */
    public function movies(): array
    {
        $sections = $this->get('library/sections')['MediaContainer']['Directory'] ?? [];
        $movies = [];

        foreach ($sections as $section) {
            if (($section['type'] ?? null) !== 'movie') {
                continue;
            }

            $items = $this->get("library/sections/{$section['key']}/all", ['type' => 1, 'includeGuids' => 1])['MediaContainer']['Metadata'] ?? [];

            foreach ($items as $item) {
                $movies[] = [
                    'rating_key' => (string) ($item['ratingKey'] ?? ''),
                    'title' => (string) ($item['title'] ?? ''),
                    'year' => isset($item['year']) ? (int) $item['year'] : null,
                    ...PlexGuids::from($item),
                ];
            }
        }

        return $movies;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new PlexException('Plex ist nicht erreichbar.', previous: $exception);
        }

        if ($response->failed()) {
            throw new PlexException("Plex antwortet mit HTTP {$response->status()}.");
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new PlexException('Plex ist nicht konfiguriert (PLEX_URL, PLEX_TOKEN).');
        }

        return Http::baseUrl(rtrim((string) $this->url, '/'))
            ->withHeaders(['X-Plex-Token' => $this->token])
            ->acceptJson()
            ->timeout(30)
            ->retry([500, 2000], throw: false);
    }
}
