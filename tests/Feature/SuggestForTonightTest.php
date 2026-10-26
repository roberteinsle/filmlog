<?php

use App\Actions\SuggestForTonight;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;

/**
 * @param  array<string, mixed>  $attributes
 */
function watchlistMovie(array $attributes = []): Movie
{
    $movie = Movie::factory()->create($attributes + ['fsk' => 12, 'runtime' => 110, 'genre_ids' => [18]]);
    MovieList::watchlist()->addMovie($movie->tmdb_id);

    return $movie;
}

/**
 * @return list<string>
 */
function suggestedTitles(array $arguments = []): array
{
    return app(SuggestForTonight::class)->handle(...$arguments)['suggestions']->pluck('title')->all();
}

test('by default candidates come only from the watchlist, never already seen', function () {
    watchlistMovie(['title' => 'Watchlist']);
    Movie::factory()->create(['title' => 'Plex', 'on_plex' => true]);
    Movie::factory()->create(['title' => 'Nur im Cache']);
    $seen = watchlistMovie(['title' => 'Schon gesehen']);
    Viewing::factory()->for($seen)->create();

    expect(suggestedTitles())->toBe(['Watchlist']);
});

test('the Plex source suggests unseen Plex movies, watchlist ones on Plex first', function () {
    watchlistMovie(['title' => 'Nur Watchlist']);
    watchlistMovie(['title' => 'Watchlist und Plex', 'on_plex' => true, 'vote_average' => 5.0]);
    Movie::factory()->create(['title' => 'Plex gut', 'on_plex' => true, 'vote_average' => 8.5]);
    Movie::factory()->create(['title' => 'Plex mäßig', 'on_plex' => true, 'vote_average' => 6.0]);
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Plex gesehen', 'on_plex' => true]))->create();

    $result = app(SuggestForTonight::class)->handle(count: 10, source: SuggestForTonight::SOURCE_PLEX);

    expect($result['candidates'])->toBe(3)
        ->and($result['suggestions']->pluck('title')->all())->toEqualCanonicalizing(['Watchlist und Plex', 'Plex gut', 'Plex mäßig'])
        ->and($result['suggestions']->firstWhere('title', 'Watchlist und Plex')->watchlist_rank)->toBe(2)
        ->and($result['suggestions']->firstWhere('title', 'Plex gut')->watchlist_rank)->toBeNull();
});

test('a little randomness varies the order, the seed makes it reproducible', function () {
    foreach (range(1, 12) as $rank) {
        watchlistMovie(['title' => "Film {$rank}"]);
    }

    $orders = collect(range(1, 20))->map(fn (int $seed): array => suggestedTitles(['seed' => $seed]));

    expect(suggestedTitles(['seed' => 7]))->toBe(suggestedTitles(['seed' => 7]))
        ->and($orders->unique(fn (array $titles): string => implode('|', $titles))->count())->toBeGreaterThan(1)
        ->and($orders->flatten()->unique()->diff(['Film 1', 'Film 2', 'Film 3', 'Film 4', 'Film 5', 'Film 6', 'Film 7'])->all())->toBe([]);
});

test('the FSK limit excludes higher and unknown ratings', function () {
    watchlistMovie(['title' => 'FSK 6', 'fsk' => 6]);
    watchlistMovie(['title' => 'FSK 16', 'fsk' => 16]);
    watchlistMovie(['title' => 'Unbekannt', 'fsk' => null]);

    expect(suggestedTitles(['maxFsk' => 12]))->toBe(['FSK 6'])
        ->and(suggestedTitles(['count' => 5]))->toHaveCount(3);
});

test('the time limit is exclusive and drops unknown runtimes', function () {
    watchlistMovie(['title' => '99 Min.', 'runtime' => 99]);
    watchlistMovie(['title' => '100 Min.', 'runtime' => 100]);
    watchlistMovie(['title' => 'Laufzeit unbekannt', 'runtime' => null]);

    expect(suggestedTitles(['maxRuntime' => 100]))->toBe(['99 Min.']);
});

test('a mood matches movies with any of its genres', function () {
    watchlistMovie(['title' => 'Thriller', 'genre_ids' => [53]]);
    watchlistMovie(['title' => 'Krimi-Drama', 'genre_ids' => [18, 80]]);
    watchlistMovie(['title' => 'Komödie', 'genre_ids' => [35]]);

    expect(suggestedTitles(['genreIds' => config('filmlog.moods.spannung.genres')]))->toEqualCanonicalizing(['Thriller', 'Krimi-Drama']);
});

test('shown movies can be excluded', function () {
    $first = watchlistMovie(['title' => 'Eins']);
    watchlistMovie(['title' => 'Zwei']);

    $result = app(SuggestForTonight::class)->handle(excludedTmdbIds: [$first->tmdb_id]);

    expect($result['suggestions']->pluck('title')->all())->toBe(['Zwei'])
        ->and($result['candidates'])->toBe(2)
        ->and($result['remaining'])->toBe(1);
});
