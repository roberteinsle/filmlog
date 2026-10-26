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

        MovieCollection::storeFromTmdb($this->collectionId, $data);
    }
}
