<?php

namespace App\Services\Tmdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Dünner Client für die TMDB-API v3 (Auth per v4 Read Access Token).
 * Antworten werden kurz gecacht, damit Suche und Filmseite TMDB schonen.
 */
class TmdbClient
{
    private const int SEARCH_CACHE_SECONDS = 600;

    private const int MOVIE_CACHE_SECONDS = 21_600;

    private const int PROVIDER_LIST_CACHE_SECONDS = 86_400;

    public function __construct(
        private ?string $token,
        private string $baseUrl,
        private string $language,
        private string $region,
    ) {}

    /**
     * @return array{results: list<array<string, mixed>>, total_results: int, total_pages: int}
     */
    public function searchMovies(string $query, int $page = 1, ?int $year = null): array
    {
        $params = array_filter([
            'query' => $query,
            'page' => $page,
            'include_adult' => 'false',
            'language' => $this->language,
            'region' => $this->region,
            'year' => $year,
        ], fn (mixed $value): bool => $value !== null);

        return Cache::remember(
            'tmdb:search:'.hash('sha256', json_encode($params)),
            self::SEARCH_CACHE_SECONDS,
            fn (): array => $this->get('search/movie', $params)->json() + ['results' => [], 'total_results' => 0, 'total_pages' => 0],
        );
    }

    /**
     * Details inkl. Freigaben, Cast/Crew, Streaming-Anbietern und Trailern in einem Request.
     *
     * @return array<string, mixed>
     */
    public function movie(int $tmdbId): array
    {
        return Cache::remember(
            "tmdb:movie:v2:{$tmdbId}:{$this->language}",
            self::MOVIE_CACHE_SECONDS,
            function () use ($tmdbId): array {
                $response = $this->get("movie/{$tmdbId}", [
                    'language' => $this->language,
                    'append_to_response' => 'release_dates,credits,watch/providers,videos',
                    'include_video_language' => substr($this->language, 0, 2).',en',
                ]);

                return $response->json();
            },
        );
    }

    /**
     * Streaming-Anbieter für Filme in der Region, nach ihrer Bedeutung dort sortiert.
     *
     * @return list<array{id: int, name: string, logo_path: ?string}>
     */
    public function watchProviders(): array
    {
        return Cache::remember(
            "tmdb:watch-providers:{$this->region}:{$this->language}",
            self::PROVIDER_LIST_CACHE_SECONDS,
            fn (): array => collect($this->get('watch/providers/movie', ['watch_region' => $this->region, 'language' => $this->language])->json('results') ?? [])
                ->sortBy(fn (array $provider): int => (int) ($provider['display_priorities'][$this->region] ?? $provider['display_priority'] ?? PHP_INT_MAX))
                ->map(fn (array $provider): array => [
                    'id' => (int) $provider['provider_id'],
                    'name' => (string) $provider['provider_name'],
                    'logo_path' => $provider['logo_path'] ?? null,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * Filmreihe mit allen Teilen (/collection/{id}).
     *
     * @return array<string, mixed>
     */
    public function collection(int $collectionId): array
    {
        return Cache::remember(
            "tmdb:collection:{$collectionId}:{$this->language}",
            self::MOVIE_CACHE_SECONDS,
            fn (): array => $this->get("collection/{$collectionId}", ['language' => $this->language])->json(),
        );
    }

    public function forgetCollection(int $collectionId): void
    {
        Cache::forget("tmdb:collection:{$collectionId}:{$this->language}");
    }

    /**
     * TMDB-ID zu einer IMDb-ID (tt…) über /find; null, wenn TMDB den Film nicht kennt.
     */
    public function findMovieIdByImdbId(string $imdbId): ?int
    {
        $tmdbId = Cache::remember(
            "tmdb:find:imdb:{$imdbId}",
            self::MOVIE_CACHE_SECONDS,
            fn (): int => (int) ($this->get("find/{$imdbId}", ['external_source' => 'imdb_id'])->json('movie_results.0.id') ?? 0),
        );

        return $tmdbId > 0 ? $tmdbId : null;
    }

    public function forgetMovie(int $tmdbId): void
    {
        Cache::forget("tmdb:movie:v2:{$tmdbId}:{$this->language}");
    }

    public function region(): string
    {
        return $this->region;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query): Response
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new TmdbException('TMDB ist nicht erreichbar.', previous: $exception);
        }

        if ($response->notFound()) {
            throw new MovieNotFoundException("TMDB: {$path} nicht gefunden.");
        }

        if ($response->failed()) {
            throw new TmdbException("TMDB antwortet mit HTTP {$response->status()}.");
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        if (blank($this->token)) {
            throw TmdbException::notConfigured();
        }

        return Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(10)
            ->retry([200, 800], when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && in_array($exception->response->status(), [429, 500, 502, 503, 504], true)), throw: false);
    }
}
