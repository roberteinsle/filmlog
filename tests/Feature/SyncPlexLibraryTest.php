<?php

use App\Actions\SuggestForTonight;
use App\Jobs\RefreshMovie;
use App\Jobs\SyncPlexLibrary;
use App\Livewire\Settings;
use App\Models\Movie;
use App\Services\Plex\PlexClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.plex.url' => 'http://plex.local:32400', 'services.plex.token' => 'plex-token']);
    app()->forgetInstance(PlexClient::class);
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'plex.local:32400/library/sections/1/all*' => Http::response(['MediaContainer' => ['Metadata' => [
            ['ratingKey' => '11', 'title' => 'Heat', 'year' => 1995, 'Guid' => [['id' => 'tmdb://949']]],
            ['ratingKey' => '12', 'title' => 'Ganz neu', 'year' => 2024, 'Guid' => [['id' => 'imdb://tt0000042'], ['id' => 'tmdb://424242']]],
            ['ratingKey' => '13', 'title' => 'Alter Agent', 'year' => 1999, 'guid' => 'com.plexapp.agents.imdb://tt0133093?lang=de'],
            ['ratingKey' => '14', 'title' => 'Heimvideo', 'guid' => 'local://14'],
        ]]]),
        'plex.local:32400/library/sections*' => Http::response(['MediaContainer' => ['Directory' => [
            ['key' => '1', 'type' => 'movie', 'title' => 'Filme'],
            ['key' => '2', 'type' => 'show', 'title' => 'Serien'],
        ]]]),
        'api.themoviedb.org/3/find/tt0133093*' => Http::response(['movie_results' => [['id' => 603]]]),
    ]);
});

test('the Plex library sets on_plex and adds unknown movies', function () {
    $heat = Movie::factory()->create(['tmdb_id' => 949, 'title' => 'Heat (TMDB)', 'fsk' => 16, 'cached_at' => now()]);
    $gone = Movie::factory()->create(['on_plex' => true, 'plex_rating_key' => '99']);

    app()->call([new SyncPlexLibrary, 'handle']);

    expect($heat->refresh())
        ->on_plex->toBeTrue()
        ->plex_rating_key->toBe('11')
        ->title->toBe('Heat (TMDB)')
        ->fsk->toBe(16)
        ->and($gone->refresh()->on_plex)->toBeFalse()
        ->and(Movie::find(424242))->title->toBe('Ganz neu')->year->toBe(2024)->on_plex->toBeTrue()->cached_at->toBeNull()
        ->and(Movie::find(603))->on_plex->toBeTrue()
        ->and(Movie::where('on_plex', true)->count())->toBe(3)
        ->and(Cache::get(SyncPlexLibrary::LAST_SYNC_CACHE_KEY)['movies'])->toBe(3);

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Plex-Token', 'plex-token'));
    expect(Queue::pushed(RefreshMovie::class)->pluck('tmdbId')->sort()->values()->all())->toBe([603, 424242]);
});

test('Plex movies become suggestions for tonight', function () {
    app()->call([new SyncPlexLibrary, 'handle']);

    expect(app(SuggestForTonight::class)->handle(count: 10, source: SuggestForTonight::SOURCE_PLEX)['candidates'])->toBe(3);
});

test('the settings show the Plex status and start a sync', function () {
    actingAsLoggedInUser();
    config(['services.plex.webhook_secret' => 'geheim-123']);
    Movie::factory()->create(['on_plex' => true]);

    Livewire::test(Settings::class)
        ->assertSee(route('webhooks.plex', 'geheim-123'))
        ->assertSee('1 Film auf Plex')
        ->assertSee('noch nie abgeglichen')
        ->call('syncPlex')
        ->assertSee('Abgleich läuft im Hintergrund');

    Queue::assertPushed(SyncPlexLibrary::class);
});

test('the settings say when Plex is not configured', function () {
    actingAsLoggedInUser();
    config(['services.plex.url' => null, 'services.plex.webhook_secret' => null]);
    app()->forgetInstance(PlexClient::class);

    Livewire::test(Settings::class)
        ->assertSee('PLEX_WEBHOOK_SECRET ist nicht gesetzt.')
        ->assertSee('PLEX_URL und PLEX_TOKEN sind nicht gesetzt.')
        ->call('syncPlex');

    Queue::assertNothingPushed();
});

test('the library sync is scheduled at 03:15', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains((string) $event->description, 'SyncPlexLibrary'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 3 * * *');
});
