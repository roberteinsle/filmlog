<?php

use App\Livewire\ListShow;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function movieOnList(MovieList $list, array $attributes): Movie
{
    $movie = Movie::factory()->create($attributes);
    $list->addMovie($movie->tmdb_id);

    return $movie;
}

test('a list shows its movies by rank with details', function () {
    $watchlist = MovieList::watchlist();
    movieOnList($watchlist, ['title' => 'Heat', 'year' => 1995, 'runtime' => 170, 'fsk' => 16, 'vote_average' => 7.9, 'vote_count' => 7000]);
    movieOnList($watchlist, ['title' => 'Alien', 'year' => 1979]);

    $this->get(route('lists.show', $watchlist))
        ->assertOk()
        ->assertSee('<title>Watchlist · Filmlog</title>', false)
        ->assertSee('2 Filme')
        ->assertSeeInOrder(['Heat', '1995 · 2 Std. 50 Min. · FSK 16 · TMDB 7,9', 'Alien']);
});

test('an unknown list returns 404', function () {
    $this->get(route('lists.show', 999))->assertNotFound();
});

test('an empty list points to the search', function () {
    $this->get(route('lists.show', MovieList::factory()->create()))
        ->assertSee('Noch keine Filme auf dieser Liste.');
});

test('movies can be reordered by drag and drop', function () {
    $list = MovieList::factory()->create();
    $a = movieOnList($list, ['title' => 'A']);
    $b = movieOnList($list, ['title' => 'B']);
    $c = movieOnList($list, ['title' => 'C']);

    Livewire::test(ListShow::class, ['list' => $list])
        ->call('moveMovie', (string) $c->tmdb_id, 0)
        ->assertSeeInOrder(['C', 'A', 'B']);

    expect($list->movies()->pluck('movies.tmdb_id')->all())->toBe([$c->tmdb_id, $a->tmdb_id, $b->tmdb_id])
        ->and($list->movies()->get()->pluck('pivot.rank')->all())->toBe([1, 2, 3]);
});

test('sorting is disabled while a filter is active', function () {
    $list = MovieList::factory()->create();
    $a = movieOnList($list, ['title' => 'A', 'fsk' => 12]);
    $b = movieOnList($list, ['title' => 'B', 'fsk' => 12]);

    Livewire::test(ListShow::class, ['list' => $list])
        ->set('maxFsk', '12')
        ->assertSee('Sortieren geht nur ohne Filter.')
        ->assertDontSeeHtml('wire:sort="moveMovie"')
        ->call('moveMovie', $b->tmdb_id, 0);

    expect($list->movies()->pluck('movies.tmdb_id')->all())->toBe([$a->tmdb_id, $b->tmdb_id]);
});

test('a movie can be removed from the list', function () {
    $list = MovieList::factory()->create();
    $movie = movieOnList($list, ['title' => 'Heat']);

    Livewire::test(ListShow::class, ['list' => $list])
        ->call('removeMovie', $movie->tmdb_id)
        ->assertDontSee('Heat');

    expect($list->movies()->count())->toBe(0)
        ->and(Movie::find($movie->tmdb_id))->not->toBeNull();
});

test('filters narrow the list', function (array $filters, array $expected) {
    $list = MovieList::factory()->create();
    $heat = movieOnList($list, ['title' => 'Heat', 'year' => 1995, 'runtime' => 170, 'fsk' => 16, 'genre_ids' => [28, 80], 'vote_average' => 7.9, 'vote_count' => 7000]);
    $up = movieOnList($list, ['title' => 'Oben', 'year' => 2009, 'runtime' => 96, 'fsk' => 0, 'genre_ids' => [16, 10751], 'vote_average' => 7.9, 'vote_count' => 20000]);
    movieOnList($list, ['title' => 'Nischenfilm', 'year' => 2001, 'runtime' => 88, 'fsk' => null, 'genre_ids' => [18], 'vote_average' => 9.5, 'vote_count' => 12]);
    Viewing::factory()->for($heat)->create(['watched_at' => '2020-01-01', 'rating' => 6]);
    Viewing::factory()->for($heat)->create(['watched_at' => '2025-01-01', 'rating' => 9]);
    Viewing::factory()->for($heat)->create(['watched_at' => '2026-01-01', 'rating' => null]);
    Viewing::factory()->for($up)->create(['watched_at' => '2026-01-01', 'rating' => 7]);

    $titles = Livewire::test(ListShow::class, ['list' => $list])
        ->set($filters)
        ->instance()->movies->pluck('title')->all();

    expect($titles)->toBe($expected);
})->with([
    'own rating uses the latest rating' => [['minOwnRating' => '8'], ['Heat']],
    'own rating excludes unrated movies' => [['minOwnRating' => '5'], ['Heat', 'Oben']],
    'TMDB needs enough votes' => [['minTmdbRating' => '7.5'], ['Heat', 'Oben']],
    'genre' => [['genreId' => '10751'], ['Oben']],
    'decade' => [['decade' => '2000'], ['Oben', 'Nischenfilm']],
    'runtime' => [['maxRuntime' => '100'], ['Oben', 'Nischenfilm']],
    'FSK excludes unknown ratings' => [['maxFsk' => '12'], ['Oben']],
    'combined' => [['decade' => '2000', 'maxRuntime' => '90'], ['Nischenfilm']],
    'invalid values are ignored' => [['minOwnRating' => '99', 'genreId' => 'drop table', 'decade' => '1995', 'maxRuntime' => '-1', 'minTmdbRating' => '7.1'], ['Heat', 'Oben', 'Nischenfilm']],
]);

test('filters are read from the URL', function () {
    $list = MovieList::factory()->create();
    movieOnList($list, ['title' => 'Heat', 'year' => 1995]);
    movieOnList($list, ['title' => 'Oben', 'year' => 2009]);

    $this->get(route('lists.show', $list).'?jahrzehnt=2000')
        ->assertOk()
        ->assertSee('1 von 2')
        ->assertSee('Oben')
        ->assertDontSee('Heat');
});

test('filter changes are written to the URL', function () {
    $list = MovieList::factory()->create();

    Livewire::test(ListShow::class, ['list' => $list])
        ->set('genreId', '28')
        ->assertSetStrict('genreId', '28');

    Livewire::withQueryParams(['genre' => '28', 'fsk' => '12'])
        ->test(ListShow::class, ['list' => $list])
        ->assertSet('genreId', '28')
        ->assertSet('maxFsk', '12')
        ->call('resetFilters')
        ->assertSet('genreId', '')
        ->assertSet('maxFsk', '');
});

test('only genres and decades of the list are offered', function () {
    $list = MovieList::factory()->create();
    movieOnList($list, ['year' => 1995, 'genre_ids' => [28, 80]]);
    Movie::factory()->create(['year' => 1960, 'genre_ids' => [37]]);

    $this->get(route('lists.show', $list))
        ->assertSee('Action')
        ->assertSee('Krimi')
        ->assertSee('1990er')
        ->assertDontSee('Western')
        ->assertDontSee('1960er');
});

test('a filter without matches says so', function () {
    $list = MovieList::factory()->create();
    movieOnList($list, ['fsk' => 18]);

    Livewire::test(ListShow::class, ['list' => $list])
        ->set('maxFsk', '0')
        ->assertSee('Kein Film passt zu diesen Filtern.');
});
