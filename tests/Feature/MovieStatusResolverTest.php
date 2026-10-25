<?php

use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use App\Services\MovieStatusResolver;
use App\Services\WatchState;
use Illuminate\Support\Facades\DB;

test('a movie without viewings and not on the watchlist was never seen', function () {
    expect(app(MovieStatusResolver::class)->for(123)->state)->toBe(WatchState::NeverSeen);
});

test('a movie on the watchlist is marked as such', function () {
    $movie = Movie::factory()->create();
    MovieList::watchlist()->movies()->attach($movie->tmdb_id, ['rank' => 1]);

    $status = app(MovieStatusResolver::class)->for($movie->tmdb_id);

    expect($status->state)->toBe(WatchState::OnWatchlist)
        ->and($status->onWatchlist)->toBeTrue();
});

test('own lists other than the watchlist do not count', function () {
    $movie = Movie::factory()->create();
    MovieList::factory()->create()->movies()->attach($movie->tmdb_id, ['rank' => 1]);

    expect(app(MovieStatusResolver::class)->for($movie->tmdb_id)->state)->toBe(WatchState::NeverSeen);
});

test('seen wins over watchlist and reports the latest viewing and latest rating', function () {
    $movie = Movie::factory()->create();
    MovieList::watchlist()->movies()->attach($movie->tmdb_id, ['rank' => 1]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2024-01-10 20:00', 'rating' => 6]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2026-10-16 20:00', 'rating' => null]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2025-05-01 20:00', 'rating' => 8]);

    $status = app(MovieStatusResolver::class)->for($movie->tmdb_id);

    expect($status->state)->toBe(WatchState::Seen)
        ->and($status->lastWatchedAt->toDateString())->toBe('2026-10-16')
        ->and($status->lastRating)->toBe(8)
        ->and($status->viewingCount)->toBe(3)
        ->and($status->onWatchlist)->toBeTrue();
});

test('statuses for many movies are resolved with two queries', function () {
    $movies = Movie::factory()->count(5)->create();
    Viewing::factory()->for($movies[0])->create();

    DB::enableQueryLog();
    $statuses = app(MovieStatusResolver::class)->forMany($movies->pluck('tmdb_id')->all());

    expect($statuses)->toHaveCount(5)
        ->and(DB::getQueryLog())->toHaveCount(2)
        ->and($statuses[$movies[0]->tmdb_id]->state)->toBe(WatchState::Seen);
});
