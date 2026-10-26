<?php

use App\Livewire\AddToList;
use App\Livewire\MovieShow;
use App\Models\Movie;
use App\Models\MovieList;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the movie page and the search offer adding to a list', function () {
    fakeTmdb();

    $this->get(route('movie', 603))->assertSee('Zur Liste');
    $this->get(route('search', ['q' => 'matrix']))->assertSee('Zur Liste hinzufügen');
});

test('opening the dialog fetches a movie that is not cached yet', function () {
    fakeTmdb();

    Livewire::test(AddToList::class, ['tmdbId' => 603])
        ->call('openDialog')
        ->assertSet('open', true)
        ->assertSee('Auf welche Listen?')
        ->assertSee('Watchlist');

    expect(Movie::find(603)?->title)->toBe('Matrix');
});

test('a TMDB outage keeps the dialog closed', function () {
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::test(AddToList::class, ['tmdbId' => 603])
        ->call('openDialog')
        ->assertSet('open', false)
        ->assertSee('TMDB ist gerade nicht erreichbar.');
});

test('toggling a list adds the movie at the end and removes it again', function () {
    $list = MovieList::factory()->create();
    $list->addMovie(Movie::factory()->create()->tmdb_id);
    Movie::factory()->create(['tmdb_id' => 603]);

    $component = Livewire::test(AddToList::class, ['tmdbId' => 603])
        ->call('openDialog')
        ->call('toggleList', $list->id)
        ->assertDispatched('list-membership-changed', tmdbId: 603);

    expect($list->movies()->pluck('movies.tmdb_id')->last())->toBe(603)
        ->and($list->movies()->get()->last()->pivot->rank)->toBe(2);

    $component->call('toggleList', $list->id);

    expect($list->contains(603))->toBeFalse();
});

test('a new list can be created with the movie on it', function () {
    Movie::factory()->create(['tmdb_id' => 603]);

    Livewire::test(AddToList::class, ['tmdbId' => 603])
        ->call('openDialog')
        ->set('newListName', 'Cyberpunk')
        ->call('createList')
        ->assertHasNoErrors()
        ->assertSet('newListName', '')
        ->assertSee('Cyberpunk')
        ->assertDispatched('list-membership-changed');

    expect(MovieList::firstWhere('name', 'Cyberpunk')->contains(603))->toBeTrue();
});

test('the movie page banner follows the watchlist', function () {
    fakeTmdb();

    $page = Livewire::test(MovieShow::class, ['tmdbId' => 603])->assertSee('Nie gesehen');

    MovieList::watchlist()->addMovie(603);

    $page->dispatch('list-membership-changed', tmdbId: 603)->assertSee('Auf deiner Watchlist');
});

test('adding twice keeps a single entry', function () {
    $movie = Movie::factory()->create();
    $watchlist = MovieList::watchlist();

    $watchlist->addMovie($movie->tmdb_id);
    $watchlist->addMovie($movie->tmdb_id);

    expect($watchlist->movies()->count())->toBe(1);
});
