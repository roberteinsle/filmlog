<?php

use App\Livewire\MovieShow;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    fakeTmdb();
    $this->travelTo(now('Europe/Berlin')->setDate(2026, 10, 26)->setTime(21, 30));
});

test('the movie page offers the seen button', function () {
    $this->get(route('movie', 603))->assertSee('Gesehen');
});

test('the dialog defaults to today without rating or companions', function () {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->assertSet('showViewingDialog', true)
        ->assertSet('form.date', '2026-10-26')
        ->assertSet('form.rating', null)
        ->assertSet('form.companionIds', [])
        ->assertSee('Matrix gesehen');
});

test('saving logs a viewing with rating, companions and note', function () {
    $sam = Companion::factory()->create(['name' => 'Sam']);
    $family = Companion::factory()->create(['name' => 'Familie']);
    Companion::factory()->create(['name' => 'Kollegen']);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->set('form.rating', 9)
        ->set('form.companionIds', [(string) $sam->id, (string) $family->id])
        ->set('form.note', '  Immer noch großartig.  ')
        ->call('saveViewing')
        ->assertHasNoErrors()
        ->assertSet('showViewingDialog', false)
        ->assertSee('Gesehen am 26.10.2026, deine Bewertung: 9');

    $viewing = Viewing::sole();

    expect($viewing)
        ->tmdb_id->toBe(603)
        ->rating->toBe(9)
        ->note->toBe('Immer noch großartig.')
        ->source->toBe('manual')
        ->and($viewing->watched_at->equalTo(now()))->toBeTrue()
        ->and($viewing->companions->pluck('name')->all())->toEqualCanonicalizing(['Sam', 'Familie']);
});

test('a viewing without rating and companions is allowed', function () {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->call('saveViewing')
        ->assertHasNoErrors();

    expect(Viewing::sole())
        ->rating->toBeNull()
        ->note->toBeNull()
        ->companions->toBeEmpty();
});

test('a viewing on an earlier day is stored at 8 pm local time', function () {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->set('form.date', '2026-10-03')
        ->call('saveViewing');

    expect(Viewing::sole()->watched_at->timezone('Europe/Berlin')->format('Y-m-d H:i'))->toBe('2026-10-03 20:00');
});

test('invalid input is rejected', function (string $field, mixed $value) {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->set($field, $value)
        ->call('saveViewing')
        ->assertHasErrors()
        ->assertSet('showViewingDialog', true);

    expect(Viewing::count())->toBe(0);
})->with([
    'date in the future' => ['form.date', '2026-10-27'],
    'malformed date' => ['form.date', '26.10.2026'],
    'empty date' => ['form.date', ''],
    'rating above 10' => ['form.rating', 11],
    'rating below 1' => ['form.rating', 0],
    'unknown companion' => ['form.companionIds', ['999']],
    'note too long' => ['form.note', str_repeat('a', 281)],
]);

test('a movie on the watchlist triggers the removal question', function () {
    MovieList::watchlist()->movies()->attach(Movie::factory()->create(['tmdb_id' => 603])->tmdb_id, ['rank' => 1]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->call('saveViewing')
        ->assertSet('askRemoveFromWatchlist', true)
        ->assertSee('Von der Watchlist nehmen?')
        ->call('removeFromWatchlist')
        ->assertSet('askRemoveFromWatchlist', false)
        ->assertDontSee('steht außerdem auf der Watchlist');

    expect(MovieList::watchlist()->movies()->count())->toBe(0);
});

test('the movie can stay on the watchlist', function () {
    MovieList::watchlist()->movies()->attach(Movie::factory()->create(['tmdb_id' => 603])->tmdb_id, ['rank' => 1]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->call('saveViewing')
        ->call('keepOnWatchlist')
        ->assertSet('askRemoveFromWatchlist', false)
        ->assertSee('steht außerdem auf der Watchlist');

    expect(MovieList::watchlist()->movies()->count())->toBe(1);
});

test('no watchlist question for a movie that is not on it', function () {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->call('saveViewing')
        ->assertSet('askRemoveFromWatchlist', false);
});

test('a seen movie offers to log another viewing', function () {
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 603]))->create();

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->assertSee('Wieder gesehen')
        ->call('openViewingDialog')
        ->call('saveViewing')
        ->assertSee('2× gesehen');
});

test('the dialog links to the settings when no companions exist', function () {
    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('openViewingDialog')
        ->assertSee('Noch keine Begleitungen angelegt.')
        ->assertSee(route('settings'));
});
