<?php

use App\Livewire\MovieShow;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    fakeTmdb();
    $this->movie = Movie::factory()->create(['tmdb_id' => 603]);
});

test('the banner of a seen movie unfolds to all viewings', function () {
    $older = Viewing::factory()->for($this->movie)->create(['watched_at' => '2025-03-01 20:00', 'rating' => 6, 'note' => 'Etwas lang.']);
    $older->companions()->attach(Companion::factory()->create(['name' => 'Sam']));
    Viewing::factory()->for($this->movie)->create(['watched_at' => '2026-10-16 20:00', 'rating' => 7, 'source' => 'trakt', 'external_id' => '1']);

    $this->get(route('movie', 603))
        ->assertSeeHtml('x-on:click="open = ! open"')
        ->assertSee('Sichtungen anzeigen')
        ->assertSeeInOrder(['Gesehen am 16.10.2026, deine Bewertung: 7', '2× gesehen', 'Fr., 16.10.2026', '7', 'allein', 'Trakt', 'Sa., 01.03.2025', '6', 'mit Sam', '„Etwas lang.“']);
});

test('banners without viewings do not unfold', function () {
    $this->get(route('movie', 603))->assertDontSee('Sichtungen anzeigen');
});

test('a viewing can be edited from the banner', function () {
    $viewing = Viewing::factory()->for($this->movie)->create(['watched_at' => '2026-10-16 20:00', 'rating' => 7]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('editViewing', $viewing->id)
        ->assertSet('showViewingDialog', true)
        ->assertSee('Sichtung vom 16.10.2026 bearbeiten')
        ->set('form.rating', 9)
        ->call('saveViewing')
        ->assertHasNoErrors()
        ->assertSet('showViewingDialog', false)
        ->assertSet('askRemoveFromWatchlist', false)
        ->assertSee('deine Bewertung: 9');

    expect(Viewing::count())->toBe(1)
        ->and($viewing->refresh()->rating)->toBe(9);
});

test('a viewing can be deleted from the banner', function () {
    $viewing = Viewing::factory()->for($this->movie)->create();

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('deleteViewing', $viewing->id)
        ->assertSee('Nie gesehen');

    expect(Viewing::count())->toBe(0);
});

test('viewings of other movies cannot be touched from here', function (string $method) {
    $foreign = Viewing::factory()->for(Movie::factory()->create())->create();

    Livewire::test(MovieShow::class, ['tmdbId' => 603])->call($method, $foreign->id)->assertNotFound();

    expect(Viewing::find($foreign->id))->not->toBeNull();
})->with(['editViewing', 'deleteViewing']);

test('after editing, a new viewing starts with an empty form again', function () {
    $viewing = Viewing::factory()->for($this->movie)->create(['rating' => 7]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('editViewing', $viewing->id)
        ->call('closeViewingDialog')
        ->call('openViewingDialog')
        ->assertSet('form.viewing', null)
        ->assertSet('form.rating', null)
        ->call('saveViewing');

    expect(Viewing::count())->toBe(2);
});
