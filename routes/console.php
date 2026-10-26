<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Nächtliche Wartung (Container scheduler: schedule:work)
|--------------------------------------------------------------------------
|
| Zeiten in APP_TIMEZONE (Europe/Berlin). Die Jobs selbst laufen im worker.
|
*/

Schedule::command('filmlog:refresh-movies')->dailyAt('03:30')->withoutOverlapping()->onOneServer();

Schedule::command('filmlog:refresh-collections')->dailyAt('03:45')->withoutOverlapping()->onOneServer();

Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->dailyAt('04:00');

Schedule::command('filmlog:prune-imports')->dailyAt('04:15');
