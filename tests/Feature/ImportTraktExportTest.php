<?php

use App\Actions\ImportTraktExport;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\TraktImport;
use App\Models\Viewing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/603*' => Http::response(tmdbFixture('movie-603')),
        'api.themoviedb.org/3/find/tt0133093*' => Http::response(['movie_results' => [['id' => 603]], 'tv_results' => []]),
        'api.themoviedb.org/3/find/tt2814388*' => Http::response(['movie_results' => [['id' => 603]], 'tv_results' => []]),
        'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => [], 'tv_results' => []]),
        'api.themoviedb.org/3/movie/*' => Http::response(['status_code' => 34], 404),
    ]);
});

/**
 * @param  array<string, mixed>  $files
 */
function runTraktImport(array $files, int $timeBudget = 50): TraktImport
{
    $import = TraktImport::factory()->create(['path' => traktZip($files)]);

    while (! app(ImportTraktExport::class)->handle($import, $timeBudget)) {
        $import->refresh();
    }

    return $import->refresh();
}

/**
 * Ein typischer Export: Matrix zweimal gesehen und bewertet, Heat auf der Watchlist, eine Liste.
 *
 * @return array<string, mixed>
 */
function sampleTraktExport(): array
{
    $matrix = traktMovie('The Matrix', 1999, ['trakt' => 481, 'imdb' => 'tt0133093', 'tmdb' => 603]);
    $heat = traktMovie('Heat', 1995, ['trakt' => 1, 'tmdb' => 949]);
    $alien = traktMovie('Alien', 1979, ['trakt' => 2, 'tmdb' => 348]);

    return [
        'watched-history.json' => [
            ['id' => 9002, 'watched_at' => '2024-05-01T19:30:00.000Z', 'type' => 'movie', 'movie' => $matrix],
            ['id' => 9001, 'watched_at' => '2010-03-01T20:00:00.000Z', 'type' => 'movie', 'movie' => $matrix],
            ['id' => 9003, 'watched_at' => '2024-05-02T19:30:00.000Z', 'type' => 'episode', 'show' => ['title' => 'Dark']],
        ],
        'ratings-movies.json' => [
            ['rated_at' => '2024-05-01T22:00:00Z', 'rating' => 9, 'type' => 'movie', 'movie' => $matrix],
            ['rated_at' => '2023-01-05T21:00:00Z', 'rating' => 7, 'type' => 'movie', 'movie' => $alien],
        ],
        'watchlist.json' => [
            ['rank' => 1, 'listed_at' => '2024-01-01T00:00:00Z', 'type' => 'movie', 'movie' => $heat],
        ],
        'lists-lists.json' => [['name' => 'Ridley Scott', 'ids' => ['slug' => 'ridley-scott']]],
        'lists-ridley-scott.json' => [['rank' => 1, 'type' => 'movie', 'movie' => $alien]],
    ];
}

beforeEach(function () {
    Movie::factory()->create(['tmdb_id' => 949, 'title' => 'Heat']);
    Movie::factory()->create(['tmdb_id' => 348, 'title' => 'Alien']);
});

test('a Trakt export becomes viewings, ratings, watchlist and lists', function () {
    $import = runTraktImport(sampleTraktExport());

    expect($import->status)->toBe(TraktImport::DONE)
        ->and($import->stats)->toMatchArray([
            'viewings_created' => 2,
            'viewings_existing' => 0,
            'ratings_applied' => 2,
            'watchlist_added' => 1,
            'lists_created' => 1,
            'list_items_added' => 1,
            'movies' => 3,
            'movies_unresolved' => 0,
            'skipped_non_movies' => 1,
        ])
        ->and($import->finished_at)->not->toBeNull();

    expect($import->read_files)->toContain('watched-history.json (2)', 'ratings-movies.json (2)', 'watchlist.json (1)');

    $matrixViewings = Viewing::query()->where('tmdb_id', 603)->orderBy('watched_at')->get();

    expect(Movie::find(603)->title)->toBe('Matrix')
        ->and($matrixViewings->pluck('external_id')->all())->toBe(['9001', '9002'])
        ->and($matrixViewings->pluck('source')->unique()->all())->toBe(['trakt'])
        ->and($matrixViewings->last()->watched_at->utc()->format('Y-m-d H:i'))->toBe('2024-05-01 19:30')
        ->and($matrixViewings->pluck('rating')->all())->toBe([null, 9])
        ->and(MovieList::watchlist()->contains(949))->toBeTrue()
        ->and(MovieList::firstWhere('name', 'Ridley Scott')->contains(348))->toBeTrue();
});

test('a rated movie without history counts as seen on the rating day', function () {
    runTraktImport(sampleTraktExport());

    $viewing = Viewing::query()->where('tmdb_id', 348)->sole();

    expect($viewing)
        ->rating->toBe(7)
        ->source->toBe('trakt')
        ->external_id->toBe('rating:348')
        ->and($viewing->watched_at->utc()->format('Y-m-d'))->toBe('2023-01-05');
});

test('importing the same export twice creates nothing twice', function () {
    runTraktImport(sampleTraktExport());
    $second = runTraktImport(sampleTraktExport());

    expect(Viewing::count())->toBe(3)
        ->and(MovieList::watchlist()->movies()->count())->toBe(1)
        ->and(MovieList::where('name', 'Ridley Scott')->count())->toBe(1)
        ->and($second->stats)->toMatchArray([
            'viewings_created' => 0,
            'viewings_existing' => 2,
            'ratings_applied' => 0,
            'watchlist_added' => 0,
            'lists_created' => 0,
            'list_items_added' => 0,
        ]);
});

test('ratings never overwrite a rating made in Filmlog', function () {
    Viewing::factory()->create(['tmdb_id' => 348, 'watched_at' => '2025-06-01 20:00', 'rating' => 4, 'source' => 'manual']);

    runTraktImport(sampleTraktExport());

    expect(Viewing::query()->where('tmdb_id', 348)->sole()->rating)->toBe(4);
});

test('movies without TMDB id are resolved via IMDb', function () {
    runTraktImport([
        'watched-history.json' => [
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('The Matrix', 1999, ['imdb' => 'tt0133093', 'tmdb' => null])],
        ],
    ]);

    expect(Viewing::sole()->tmdb_id)->toBe(603);
});

test('an outdated TMDB id is replaced via the IMDb id', function () {
    $import = runTraktImport([
        'watched-history.json' => [
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Logan', 2013, ['trakt' => 140293, 'imdb' => 'tt2814388', 'tmdb' => 229801])],
        ],
    ]);

    expect(Viewing::sole()->tmdb_id)->toBe(603)
        ->and($import->unresolved)->toBe([]);
});

test('movies that cannot be resolved end up in the report', function () {
    $import = runTraktImport([
        'watched-history.json' => [
            ['id' => 1, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Gelöscht', 2001, ['trakt' => 7, 'tmdb' => 999999])],
            ['id' => 2, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Unbekannt', 1930, ['trakt' => 8, 'imdb' => 'tt9999999'])],
            ['id' => 3, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Ohne IDs', null, ['trakt' => 9])],
        ],
    ]);

    expect(Viewing::count())->toBe(0)
        ->and($import->stats['movies_unresolved'])->toBe(3)
        ->and(collect($import->unresolved)->pluck('reason', 'title')->all())->toBe([
            'Gelöscht' => 'TMDB kennt die ID nicht (mehr)',
            'Unbekannt' => 'TMDB kennt die IMDb-ID nicht',
            'Ohne IDs' => 'Weder TMDB- noch IMDb-ID im Export',
        ])
        ->and($import->unresolved[0]['ids'])->toBe('tmdb 999999, trakt 7');
});

test('a large export is processed in stages and keeps its progress', function () {
    $import = TraktImport::factory()->create(['path' => traktZip(sampleTraktExport())]);

    expect(app(ImportTraktExport::class)->handle($import, timeBudgetSeconds: 0))->toBeFalse();

    $import->refresh();

    expect($import->status)->toBe(TraktImport::RUNNING)
        ->and($import->total_movies)->toBe(3)
        ->and($import->resolvedCount())->toBe(0)
        ->and(Viewing::count())->toBe(0);

    expect(app(ImportTraktExport::class)->handle($import->refresh(), 50))->toBeTrue()
        ->and($import->refresh()->status)->toBe(TraktImport::DONE)
        ->and($import->progress())->toBe(100);
});
