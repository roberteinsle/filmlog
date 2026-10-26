<?php

use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the movie page shows the details from TMDB', function () {
    fakeTmdb();

    $this->get(route('movie', 603))
        ->assertOk()
        ->assertSee('<title>Matrix · Filmlog</title>', false)
        ->assertSee('Originaltitel: The Matrix')
        ->assertSee('Der Hacker Neo')
        ->assertSee('2 Std. 16 Min.')
        ->assertSee('FSK 16')
        ->assertSee('TMDB 8,2')
        ->assertSee('Lilly Wachowski, Lana Wachowski')
        ->assertSee('Keanu Reeves')
        ->assertSee('Morpheus')
        ->assertSeeInOrder(['Streamt bei', 'Netflix', 'WOW'])
        ->assertDontSee('Apple TV');
});

test('opening a movie stores it in the local cache', function () {
    fakeTmdb();

    $this->get(route('movie', 603))->assertOk();

    expect(Movie::find(603))
        ->title->toBe('Matrix')
        ->year->toBe(1999)
        ->runtime->toBe(136)
        ->fsk->toBe(16)
        ->genre_ids->toBe([28, 878])
        ->provider_ids->toBe([8, 30])
        ->collection_id->toBe(2344)
        ->cached_at->not->toBeNull();
});

test('the banner says never seen for an unknown movie', function () {
    fakeTmdb();

    $this->get(route('movie', 603))->assertSee('🔴')->assertSee('Nie gesehen');
});

test('the banner says on watchlist', function () {
    fakeTmdb();
    MovieList::watchlist()->movies()->attach(Movie::factory()->create(['tmdb_id' => 603])->tmdb_id, ['rank' => 1]);

    $this->get(route('movie', 603))->assertSee('🟡')->assertSee('Auf deiner Watchlist');
});

test('the banner shows the last viewing date and rating', function () {
    fakeTmdb();
    $movie = Movie::factory()->create(['tmdb_id' => 603]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2025-03-01 20:00', 'rating' => 6]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2026-10-16 20:15', 'rating' => 8]);

    $this->get(route('movie', 603))
        ->assertSee('🟢')
        ->assertSee('Gesehen am 16.10.2026, deine Bewertung: 8')
        ->assertSee('2× gesehen');
});

test('an unknown TMDB id returns 404', function () {
    fakeTmdb();

    $this->get(route('movie', 999_999_999))->assertNotFound();
});

test('non-numeric ids do not match the route', function () {
    $this->get('/film/abc')->assertNotFound();
});

test('a TMDB outage still shows the banner and the cached title', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $movie = Movie::factory()->create(['tmdb_id' => 603, 'title' => 'Matrix', 'year' => 1999]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2026-10-16 20:15', 'rating' => 9]);

    $this->get(route('movie', 603))
        ->assertOk()
        ->assertSee('TMDB ist gerade nicht erreichbar.')
        ->assertSee('Gesehen am 16.10.2026, deine Bewertung: 9')
        ->assertSee('Matrix');
});

test('guests cannot open movie pages', function () {
    auth()->logout();

    $this->get(route('movie', 603))->assertRedirect(route('login'));
});
