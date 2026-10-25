<?php

namespace App\Services;

enum WatchState: string
{
    case NeverSeen = 'never_seen';
    case OnWatchlist = 'on_watchlist';
    case Seen = 'seen';
}
