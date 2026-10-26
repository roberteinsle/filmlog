<?php

use App\Models\Movie;
use App\Models\Viewing;
use App\Services\ReviewStats;

beforeEach(function () {
    $this->heat = Movie::factory()->create(['title' => 'Heat', 'year' => 1995, 'runtime' => 170, 'genre_ids' => [28, 80], 'directors' => [['id' => 638, 'name' => 'Michael Mann']], 'vote_average' => 7.9, 'vote_count' => 7000]);
    $this->collateral = Movie::factory()->create(['title' => 'Collateral', 'year' => 2004, 'runtime' => 120, 'genre_ids' => [80, 53], 'directors' => [['id' => 638, 'name' => 'Michael Mann']], 'vote_average' => 7.5, 'vote_count' => 6000]);
    $this->alien = Movie::factory()->create(['title' => 'Alien', 'year' => 1979, 'runtime' => 117, 'genre_ids' => [27, 878], 'directors' => [['id' => 578, 'name' => 'Ridley Scott']], 'vote_average' => 8.2, 'vote_count' => 15000]);
    $this->niche = Movie::factory()->create(['title' => 'Nische', 'runtime' => 90, 'genre_ids' => [18], 'directors' => [], 'vote_average' => 2.0, 'vote_count' => 30]);

    Viewing::factory()->for($this->heat)->create(['watched_at' => '2026-03-05 20:00', 'rating' => 9]);
    Viewing::factory()->for($this->heat)->create(['watched_at' => '2026-10-01 20:00', 'rating' => 10]);
    Viewing::factory()->for($this->collateral)->create(['watched_at' => '2026-10-10 20:00', 'rating' => 6]);
    Viewing::factory()->for($this->alien)->create(['watched_at' => '2026-10-20 20:00', 'rating' => null]);
    Viewing::factory()->for($this->niche)->create(['watched_at' => '2026-10-21 20:00', 'rating' => 10]);
    Viewing::factory()->for($this->alien)->create(['watched_at' => '2025-12-31 20:00', 'rating' => 8]);
});

test('the years with viewings are listed newest first', function () {
    expect(ReviewStats::years())->toBe([2026, 2025]);
});

test('the summary counts viewings, movies, minutes and the average rating', function () {
    expect((new ReviewStats(2026))->summary())->toBe(['viewings' => 5, 'movies' => 4, 'minutes' => 667, 'average_rating' => 8.8])
        ->and((new ReviewStats(2026, 10))->summary())->toBe(['viewings' => 4, 'movies' => 4, 'minutes' => 497, 'average_rating' => 8.7]);
});

test('periods follow the local time zone', function () {
    Viewing::factory()->for($this->alien)->create(['watched_at' => '2026-10-31 23:30:00+00:00']);

    expect((new ReviewStats(2026, 10))->summary()['viewings'])->toBe(4)
        ->and((new ReviewStats(2026, 11))->summary()['viewings'])->toBe(1);
});

test('viewings are counted per month', function () {
    expect((new ReviewStats(2026))->viewingsPerMonth())->toBe([1 => 0, 2 => 0, 3 => 1, 4 => 0, 5 => 0, 6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 4, 11 => 0, 12 => 0]);
});

test('top genres are counted per viewing', function () {
    expect((new ReviewStats(2026))->topGenres(3)->all())->toBe([
        ['name' => 'Krimi', 'viewings' => 3],
        ['name' => 'Action', 'viewings' => 2],
        ['name' => 'Drama', 'viewings' => 1],
    ]);
});

test('favorite directors by count and by average rating', function () {
    $directors = (new ReviewStats(2026))->favoriteDirectors();

    expect($directors['by_count']->all())->toBe([
        ['name' => 'Michael Mann', 'viewings' => 3, 'average_rating' => 8.3],
        ['name' => 'Ridley Scott', 'viewings' => 1, 'average_rating' => null],
    ])->and($directors['by_rating']->pluck('name')->all())->toBe(['Michael Mann']);
});

test('against the grain uses the latest own rating and only well-known movies', function () {
    expect((new ReviewStats(2026))->againstTheGrain()->all())->toBe([
        ['tmdb_id' => $this->heat->tmdb_id, 'title' => 'Heat', 'year' => 1995, 'rating' => 10, 'tmdb_rating' => 7.9, 'difference' => 2.1],
        ['tmdb_id' => $this->collateral->tmdb_id, 'title' => 'Collateral', 'year' => 2004, 'rating' => 6, 'tmdb_rating' => 7.5, 'difference' => -1.5],
    ]);
});
