<?php

use App\Jobs\RefreshMovie;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\TraktImport;
use App\Services\Tmdb\TmdbException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('stale movies and movies on lists are queued for a refresh', function () {
    Queue::fake();
    $stale = Movie::factory()->create(['cached_at' => now()->subDays(31)]);
    $neverCached = Movie::factory()->create(['cached_at' => null]);
    $onList = Movie::factory()->create(['cached_at' => now()->subHour()]);
    MovieList::watchlist()->addMovie($onList->tmdb_id);
    Movie::factory()->create(['cached_at' => now()->subDays(29)]);

    $this->artisan('filmlog:refresh-movies')
        ->expectsOutput('3 Filme zum Aktualisieren eingereiht.')
        ->assertSuccessful();

    Queue::assertPushed(RefreshMovie::class, 3);
    expect(Queue::pushed(RefreshMovie::class)->pluck('tmdbId')->sort()->values()->all())
        ->toBe(collect([$stale->tmdb_id, $neverCached->tmdb_id, $onList->tmdb_id])->sort()->values()->all());
});

test('refreshing a movie bypasses the HTTP cache and updates the local copy', function () {
    fakeTmdb();
    Movie::factory()->create(['tmdb_id' => 603, 'title' => 'Alter Titel', 'cached_at' => now()->subDays(40)]);
    Cache::put('tmdb:movie:603:de-DE', ['id' => 603, 'title' => 'Veraltet aus dem Cache'], 3600);

    $this->travelTo(now()->setTime(3, 30));
    app()->call([new RefreshMovie(603), 'handle']);

    expect(Movie::find(603))
        ->title->toBe('Matrix')
        ->cached_at->toDateTimeString()->toBe(now()->toDateTimeString());
});

test('a movie TMDB no longer knows keeps its local copy', function () {
    fakeTmdb();
    $movie = Movie::factory()->create(['tmdb_id' => 4242, 'title' => 'Verschwunden', 'cached_at' => now()->subDays(40)]);

    app()->call([new RefreshMovie(4242), 'handle']);

    expect($movie->refresh())
        ->title->toBe('Verschwunden')
        ->cached_at->lessThan(now()->subDays(39))->toBeTrue();
});

test('a TMDB outage fails the job so the queue retries it', function () {
    Http::fake(['*' => Http::response([], 503)]);
    Movie::factory()->create(['tmdb_id' => 603]);

    app()->call([new RefreshMovie(603), 'handle']);
})->throws(TmdbException::class);

test('ZIPs of finished imports older than a week are deleted, the report stays', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');
    $old = TraktImport::factory()->create(['status' => TraktImport::DONE, 'finished_at' => now()->subDays(8), 'path' => 'imports/alt.zip']);
    $recent = TraktImport::factory()->create(['status' => TraktImport::DONE, 'finished_at' => now()->subDays(2), 'path' => 'imports/neu.zip']);
    $running = TraktImport::factory()->create(['status' => TraktImport::RUNNING, 'path' => 'imports/laeuft.zip']);
    foreach (['imports/alt.zip', 'imports/neu.zip', 'imports/laeuft.zip', 'imports/verwaist.zip', 'imports/frisch-verwaist.zip'] as $path) {
        $disk->put($path, 'zip');
    }
    touch($disk->path('imports/verwaist.zip'), now()->subDays(10)->getTimestamp());

    $this->artisan('filmlog:prune-imports')
        ->expectsOutput('2 Import-Dateien gelöscht.')
        ->assertSuccessful();

    $disk->assertMissing(['imports/alt.zip', 'imports/verwaist.zip']);
    $disk->assertExists(['imports/neu.zip', 'imports/laeuft.zip', 'imports/frisch-verwaist.zip']);
    expect(TraktImport::count())->toBe(3);
});

test('the nightly tasks are scheduled', function (string $command, string $time) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($time);
})->with([
    ['filmlog:refresh-movies', '30 3 * * *'],
    ['filmlog:refresh-collections', '45 3 * * *'],
    ['queue:prune-failed', '0 4 * * *'],
    ['filmlog:prune-imports', '15 4 * * *'],
]);
