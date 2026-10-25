<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Was Filmlog über einen Film weiß: nie gesehen, auf der Watchlist oder gesehen
 * (mit letzter Sichtung und letzter Bewertung).
 */
final readonly class MovieStatus
{
    public function __construct(
        public WatchState $state,
        public bool $onWatchlist = false,
        public ?CarbonInterface $lastWatchedAt = null,
        public ?int $lastRating = null,
        public int $viewingCount = 0,
    ) {}
}
