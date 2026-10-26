<?php

namespace App\Jobs;

use App\Actions\EnsureMovie;
use App\Actions\LogViewing;
use App\Models\Viewing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Macht aus einem Plex-Scrobble (Film zu Ende geschaut) eine Sichtung mit source=plex.
 */
class LogPlexScrobble implements ShouldQueue
{
    use Queueable;

    /**
     * Ein zweiter Scrobble desselben Films innerhalb dieser Zeit gilt als Doppelmeldung.
     */
    private const int DUPLICATE_WINDOW_HOURS = 6;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public ?int $tmdbId,
        public ?string $imdbId,
        public string $title,
        public string $ratingKey,
        public int $viewedAt,
    ) {}

    public function handle(EnsureMovie $ensureMovie, LogViewing $logViewing): void
    {
        $tmdbId = $ensureMovie->handle($this->tmdbId, $this->imdbId);

        if ($tmdbId === null) {
            Log::warning("Plex-Scrobble ohne TMDB-Zuordnung: {$this->title} (tmdb {$this->tmdbId}, imdb {$this->imdbId}).");

            return;
        }

        $watchedAt = Carbon::createFromTimestamp($this->viewedAt);

        $isDuplicate = Viewing::query()
            ->where('tmdb_id', $tmdbId)
            ->where('source', 'plex')
            ->whereBetween('watched_at', [
                $watchedAt->copy()->subHours(self::DUPLICATE_WINDOW_HOURS)->format('Y-m-d H:i:sP'),
                $watchedAt->copy()->addHours(self::DUPLICATE_WINDOW_HOURS)->format('Y-m-d H:i:sP'),
            ])
            ->exists();

        if ($isDuplicate) {
            return;
        }

        $logViewing->handle(
            tmdbId: $tmdbId,
            watchedAt: $watchedAt,
            source: 'plex',
            externalId: "{$this->ratingKey}:{$this->viewedAt}",
        );
    }
}
