<?php

use App\Livewire\Search;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the search page renders with an empty query', function () {
    fakeTmdb();

    $this->get(route('search'))
        ->assertOk()
        ->assertSeeLivewire(Search::class)
        ->assertSee('Gib mindestens 2 Zeichen ein.');

    Http::assertNothingSent();
});

test('it shows TMDB results with year and links to the movie page', function () {
    fakeTmdb();

    Livewire::test(Search::class)
        ->set('query', 'matrix')
        ->assertSee('Matrix Reloaded')
        ->assertSee('2003')
        ->assertSee('Jahr unbekannt')
        ->assertSee(route('movie', 603))
        ->assertSee('kein Poster');
});

test('each result shows its Filmlog status', function () {
    fakeTmdb();
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 603]))->create();
    MovieList::watchlist()->movies()->attach(Movie::factory()->create(['tmdb_id' => 604])->tmdb_id, ['rank' => 1]);

    $html = Livewire::test(Search::class)->set('query', 'matrix')->html();

    expect(substr_count($html, 'aria-label="Gesehen"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="Auf der Watchlist"'))->toBe(1)
        ->and(substr_count($html, 'aria-label="Nie gesehen"'))->toBe(1);
});

test('the query is kept in the URL', function () {
    fakeTmdb();

    Livewire::withQueryParams(['q' => 'matrix'])
        ->test(Search::class)
        ->assertSet('query', 'matrix')
        ->assertSee('Matrix Revolutions');
});

test('queries shorter than two characters do not hit TMDB', function () {
    fakeTmdb();

    Livewire::test(Search::class)->set('query', ' m ')->assertSee('Gib mindestens 2 Zeichen ein.');

    Http::assertNothingSent();
});

test('an empty result set says so', function () {
    Http::fake(['*' => Http::response(['page' => 1, 'results' => [], 'total_results' => 0, 'total_pages' => 0])]);

    Livewire::test(Search::class)
        ->set('query', 'gibtsnicht')
        ->assertSee('Kein Film gefunden für „gibtsnicht“.');
});

test('a TMDB outage shows a friendly message', function () {
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::test(Search::class)
        ->set('query', 'matrix')
        ->assertSee('TMDB ist gerade nicht erreichbar.');
});

test('very long queries are truncated before they are sent', function () {
    fakeTmdb();

    Livewire::test(Search::class)->set('query', str_repeat('a', 500));

    Http::assertSent(fn ($request): bool => mb_strlen($request['query']) === 100);
});
