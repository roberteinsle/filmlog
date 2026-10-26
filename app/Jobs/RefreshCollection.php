<?php

namespace App\Jobs;

use App\Models\MovieCollection;
use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Holt eine Filmreihe frisch von TMDB und speichert ihre Teile in collections.
 */
class RefreshCollection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public int $uniqueFor = 43_200;

    public function __construct(public int $collectionId) {}

    public function uniqueId(): string
    {
        return (string) $this->collectionId;
    }

    public function handle(TmdbClient $tmdb): void
    {
        $tmdb->forgetCollection($this->collectionId);

        try {
            $data = $tmdb->collection($this->collectionId);
        } catch (MovieNotFoundException) {
            Log::warning("RefreshCollection: TMDB kennt Filmreihe {$this->collectionId} nicht mehr.");

            return;
        }

        $parts = collect($data['parts'] ?? [])
            ->filter(fn (mixed $part): bool => is_array($part) && isset($part['id']))
            ->map(fn (array $part): array => [
                'id' => (int) $part['id'],
                'title' => (string) ($part['title'] ?? $part['original_title'] ?? ''),
                'release_date' => filled($part['release_date'] ?? null) ? (string) $part['release_date'] : null,
                'poster_path' => $part['poster_path'] ?? null,
            ])
            // Angekündigte Teile ohne Datum ans Ende
            ->sortBy(fn (array $part): string => $part['release_date'] ?? '9999-99-99')
            ->values()
            ->all();

        MovieCollection::updateOrCreate(
            ['id' => $this->collectionId],
            ['name' => (string) ($data['name'] ?? 'Filmreihe'), 'parts' => $parts, 'cached_at' => now()],
        );
    }
}
