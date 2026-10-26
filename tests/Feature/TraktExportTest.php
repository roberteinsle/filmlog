<?php

use App\Services\Trakt\InvalidTraktExportException;
use App\Services\Trakt\TraktExport;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function openTraktExport(array $files): TraktExport
{
    return TraktExport::open(Storage::disk('local')->path(traktZip($files)));
}

test('the history keeps movies and skips episodes', function () {
    $export = openTraktExport([
        'export/watched-history.json' => [
            ['id' => 101, 'watched_at' => '2024-05-01T19:30:00.000Z', 'action' => 'watch', 'type' => 'movie', 'movie' => traktMovie('The Matrix', 1999, ['trakt' => 481, 'imdb' => 'tt0133093', 'tmdb' => 603])],
            ['id' => 102, 'watched_at' => '2024-05-02T19:30:00.000Z', 'action' => 'watch', 'type' => 'episode', 'episode' => ['season' => 1], 'show' => ['title' => 'Dark']],
            ['id' => 103, 'watched_at' => '2024-05-03T19:30:00.000Z', 'action' => 'scrobble', 'type' => 'movie', 'movie' => traktMovie('Old Movie', 1950, ['trakt' => 9, 'imdb' => 'tt0012345', 'tmdb' => null])],
        ],
    ]);

    expect($export->history())->toHaveCount(2)
        ->and($export->history()[0])->toMatchArray(['id' => '101', 'watched_at' => '2024-05-01T19:30:00.000Z'])
        ->and($export->history()[0]['movie'])->toMatchArray(['key' => 'tmdb:603', 'title' => 'The Matrix', 'year' => 1999, 'tmdb' => 603, 'imdb' => 'tt0133093'])
        ->and($export->history()[1]['movie']['key'])->toBe('imdb:tt0012345')
        ->and($export->skippedNonMovies())->toBe(1);
});

test('paginated history files are combined', function () {
    $export = openTraktExport([
        'history-1.json' => [['id' => 1, 'watched_at' => '2024-01-01T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('A', 2000, ['tmdb' => 1])]],
        'history-2.json' => [['id' => 2, 'watched_at' => '2024-01-02T20:00:00Z', 'type' => 'movie', 'movie' => traktMovie('B', 2001, ['tmdb' => 2])]],
    ]);

    expect(array_column($export->history(), 'id'))->toEqualCanonicalizing(['1', '2']);
});

test('ratings, watchlist and lists are read', function () {
    $export = openTraktExport([
        'ratings-movies.json' => [
            ['rated_at' => '2024-05-01T22:00:00Z', 'rating' => 9, 'type' => 'movie', 'movie' => traktMovie('The Matrix', 1999, ['tmdb' => 603])],
            ['rated_at' => '2024-05-01T22:00:00Z', 'rating' => 11, 'type' => 'movie', 'movie' => traktMovie('Invalid', 1999, ['tmdb' => 1])],
        ],
        'watchlist.json' => [
            ['rank' => 2, 'listed_at' => '2024-01-01T00:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Heat', 1995, ['tmdb' => 949])],
            ['rank' => 1, 'listed_at' => '2024-01-02T00:00:00Z', 'type' => 'movie', 'movie' => traktMovie('Alien', 1979, ['tmdb' => 348])],
            ['rank' => 3, 'listed_at' => '2024-01-03T00:00:00Z', 'type' => 'show', 'show' => ['title' => 'Dark']],
        ],
        'lists-lists.json' => [['name' => 'Beste Sci-Fi', 'ids' => ['trakt' => 5, 'slug' => 'beste-sci-fi']]],
        'lists-beste-sci-fi.json' => [['rank' => 1, 'type' => 'movie', 'movie' => traktMovie('Alien', 1979, ['tmdb' => 348])]],
        'lists-weihnachten-mit-familie.json' => [['rank' => 1, 'type' => 'movie', 'movie' => traktMovie('Kevin', 1990, ['tmdb' => 771])]],
    ]);

    expect($export->ratings())->toHaveCount(1)
        ->and($export->ratings()[0]['rating'])->toBe(9)
        ->and(array_column(array_column($export->watchlist(), 'movie'), 'title'))->toBe(['Alien', 'Heat'])
        ->and($export->lists())->toHaveKeys(['beste-sci-fi', 'weihnachten-mit-familie'])
        ->and($export->lists()['beste-sci-fi']['name'])->toBe('Beste Sci-Fi')
        ->and($export->lists()['weihnachten-mit-familie']['name'])->toBe('Weihnachten Mit Familie')
        ->and(array_keys($export->movies()))->toEqualCanonicalizing(['tmdb:603', 'tmdb:949', 'tmdb:348', 'tmdb:771']);
});

test('irrelevant Trakt files are ignored silently, unknown ones are reported', function () {
    $export = openTraktExport([
        'watched-movies.json' => [],
        'ratings-shows.json' => [],
        'collection-movies.json' => [],
        'user-profile.json' => [],
        'readme.json' => [],
        'notes.txt' => 'hallo',
        'history.json' => ['kein' => 'Array'],
    ]);

    expect($export->ignoredFiles())->toBe(['readme.json', 'history.json (kein JSON-Array)']);
});

test('a file that is not a ZIP is rejected', function () {
    Storage::disk('local')->put('imports/kaputt.zip', 'das ist kein zip');

    TraktExport::open(Storage::disk('local')->path('imports/kaputt.zip'));
})->throws(InvalidTraktExportException::class, 'Die Datei ist kein lesbares ZIP-Archiv.');
