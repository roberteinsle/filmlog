<?php

use App\Livewire\Settings;
use App\Models\Movie;
use App\Models\StreamingSubscription;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the settings offer the most important providers in Germany', function () {
    $this->get(route('settings'))
        ->assertOk()
        ->assertSee('Streaming-Abos')
        ->assertSeeInOrder(['Netflix', 'Amazon Prime Video', 'Disney Plus', 'WOW', 'Apple TV+']);
});

test('subscriptions can be switched on and off', function () {
    $component = Livewire::test(Settings::class)->call('toggleSubscription', 8);

    expect(StreamingSubscription::sole())
        ->provider_id->toBe(8)
        ->name->toBe('Netflix')
        ->logo_path->toBe('/netflix.jpg');

    $component->assertSeeHtml('aria-pressed="true"')->call('toggleSubscription', 8);

    expect(StreamingSubscription::count())->toBe(0);
});

test('unknown provider ids are ignored', function () {
    Livewire::test(Settings::class)->call('toggleSubscription', 4242);

    expect(StreamingSubscription::count())->toBe(0);
});

test('your subscriptions stay visible when TMDB is down', function () {
    // Frischer Client ohne den globalen Fake der Anbieterliste
    Http::swap(new HttpFactory);
    Http::fake(['api.themoviedb.org/*' => Http::response([], 503)]);
    StreamingSubscription::factory()->create(['provider_id' => 8, 'name' => 'Netflix']);

    $component = Livewire::test(Settings::class);

    expect($component->instance()->streamingProviders['error'])->toBe('Die Anbieterliste von TMDB ist gerade nicht erreichbar.')
        ->and(array_column($component->instance()->streamingProviders['providers'], 'name'))->toBe(['Netflix']);
});

test('the movie page shows whether you can watch it in your subscriptions or on Plex', function () {
    fakeTmdb();
    StreamingSubscription::factory()->create(['provider_id' => 8, 'name' => 'Netflix']);
    Movie::factory()->create(['tmdb_id' => 603, 'on_plex' => true]);

    $this->get(route('movie', 603))
        ->assertSeeInOrder(['Für dich', 'Netflix', 'im Abo', 'Auf Plex vorhanden', 'Streamt bei', 'Netflix', 'WOW'])
        ->assertSeeHtml('#E5A00D');
});

test('the movie page says when it is in none of your subscriptions', function () {
    fakeTmdb();
    StreamingSubscription::factory()->create(['provider_id' => 337, 'name' => 'Disney Plus']);

    $this->get(route('movie', 603))->assertSee('nicht in deinen Abos und nicht auf Plex');
});

test('without subscriptions and Plex the hint is not shown', function () {
    fakeTmdb();

    $this->get(route('movie', 603))->assertDontSee('Für dich');
});
