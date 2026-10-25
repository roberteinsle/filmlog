<?php

namespace App\Services\Tmdb;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Ausgewertete TMDB-Detailantwort (movie/{id} mit release_dates, credits, watch/providers).
 */
final readonly class MovieDetails
{
    /**
     * Gültige FSK-Stufen; TMDB liefert sie als Strings ("0", "6", …).
     */
    private const array FSK_LEVELS = [0, 6, 12, 16, 18];

    /**
     * Bevorzugte Release-Typen für die FSK: Kino, limitiert, digital, Disc, TV, Premiere.
     */
    private const array RELEASE_TYPE_PREFERENCE = [3, 2, 4, 5, 6, 1];

    /**
     * @param  list<int>  $genreIds
     * @param  list<string>  $genres
     * @param  list<array{id: int, name: string}>  $directors
     * @param  list<array{name: string, character: string, profile_path: ?string}>  $cast
     * @param  list<array{id: int, name: string, logo_path: ?string}>  $streamingProviders
     */
    public function __construct(
        public int $tmdbId,
        public string $title,
        public ?string $originalTitle,
        public ?int $year,
        public ?int $runtime,
        public ?int $fsk,
        public ?string $overview,
        public ?string $tagline,
        public ?string $posterPath,
        public ?string $backdropPath,
        public ?float $voteAverage,
        public int $voteCount,
        public array $genreIds,
        public array $genres,
        public array $directors,
        public array $cast,
        public array $streamingProviders,
        public ?string $providersLink,
        public ?int $collectionId,
        public ?string $collectionName,
        public ?string $imdbId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromTmdb(array $data, string $region): self
    {
        $releaseDate = (string) ($data['release_date'] ?? '');
        $originalTitle = $data['original_title'] ?? null;
        $title = (string) ($data['title'] ?? $originalTitle ?? '');
        $genres = collect($data['genres'] ?? []);

        return new self(
            tmdbId: (int) $data['id'],
            title: $title,
            originalTitle: $originalTitle !== $title ? $originalTitle : null,
            year: preg_match('/^(\d{4})/', $releaseDate, $match) ? (int) $match[1] : null,
            runtime: ($data['runtime'] ?? 0) > 0 ? (int) $data['runtime'] : null,
            fsk: self::extractFsk($data['release_dates']['results'] ?? [], $region),
            overview: filled($data['overview'] ?? null) ? $data['overview'] : null,
            tagline: filled($data['tagline'] ?? null) ? $data['tagline'] : null,
            posterPath: $data['poster_path'] ?? null,
            backdropPath: $data['backdrop_path'] ?? null,
            voteAverage: isset($data['vote_average']) ? round((float) $data['vote_average'], 1) : null,
            voteCount: (int) ($data['vote_count'] ?? 0),
            genreIds: $genres->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            genres: $genres->pluck('name')->values()->all(),
            directors: collect($data['credits']['crew'] ?? [])
                ->where('job', 'Director')
                ->map(fn (array $person): array => ['id' => (int) $person['id'], 'name' => (string) $person['name']])
                ->unique('id')->values()->all(),
            cast: collect($data['credits']['cast'] ?? [])
                ->sortBy('order')
                ->take(10)
                ->map(fn (array $person): array => [
                    'name' => (string) $person['name'],
                    'character' => (string) ($person['character'] ?? ''),
                    'profile_path' => $person['profile_path'] ?? null,
                ])->values()->all(),
            streamingProviders: self::extractStreamingProviders(Arr::get($data, "watch/providers.results.{$region}", [])),
            providersLink: Arr::get($data, "watch/providers.results.{$region}.link"),
            collectionId: isset($data['belongs_to_collection']['id']) ? (int) $data['belongs_to_collection']['id'] : null,
            collectionName: $data['belongs_to_collection']['name'] ?? null,
            imdbId: filled($data['imdb_id'] ?? null) ? $data['imdb_id'] : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private static function extractFsk(array $results, string $region): ?int
    {
        $releases = collect($results)->firstWhere('iso_3166_1', $region)['release_dates'] ?? [];

        $certified = collect($releases)
            ->map(fn (array $release): array => [
                'type' => (int) ($release['type'] ?? 0),
                'fsk' => is_numeric(trim((string) ($release['certification'] ?? ''))) ? (int) trim($release['certification']) : null,
            ])
            ->filter(fn (array $release): bool => in_array($release['fsk'], self::FSK_LEVELS, true));

        foreach (self::RELEASE_TYPE_PREFERENCE as $type) {
            if ($match = $certified->firstWhere('type', $type)) {
                return $match['fsk'];
            }
        }

        return $certified->first()['fsk'] ?? null;
    }

    /**
     * Abo-, kostenlose und werbefinanzierte Angebote; Kauf und Leihe zählen nicht als „streamt bei“.
     *
     * @param  array<string, mixed>  $regionProviders
     * @return list<array{id: int, name: string, logo_path: ?string}>
     */
    private static function extractStreamingProviders(array $regionProviders): array
    {
        /** @var Collection<int, array<string, mixed>> $providers */
        $providers = collect(['flatrate', 'free', 'ads'])
            ->flatMap(fn (string $kind): array => $regionProviders[$kind] ?? []);

        return $providers
            ->sortBy('display_priority')
            ->unique('provider_id')
            ->map(fn (array $provider): array => [
                'id' => (int) $provider['provider_id'],
                'name' => (string) $provider['provider_name'],
                'logo_path' => $provider['logo_path'] ?? null,
            ])->values()->all();
    }
}
