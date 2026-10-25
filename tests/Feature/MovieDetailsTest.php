<?php

use App\Services\Tmdb\MovieDetails;

test('it extracts all fields from a TMDB detail response', function () {
    $details = MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'DE');

    expect($details)
        ->tmdbId->toBe(603)
        ->title->toBe('Matrix')
        ->originalTitle->toBe('The Matrix')
        ->year->toBe(1999)
        ->runtime->toBe(136)
        ->voteAverage->toBe(8.2)
        ->voteCount->toBe(27154)
        ->genreIds->toBe([28, 878])
        ->genres->toBe(['Action', 'Science Fiction'])
        ->collectionId->toBe(2344)
        ->imdbId->toBe('tt0133093')
        ->providersLink->toBe('https://www.themoviedb.org/movie/603-the-matrix/watch?locale=DE');
});

test('the FSK prefers the German theatrical release', function () {
    expect(MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'DE')->fsk)->toBe(16);
});

test('the FSK falls back to other German releases and ignores invalid values', function () {
    $data = tmdbFixture('movie-603');
    $data['release_dates']['results'] = [[
        'iso_3166_1' => 'DE',
        'release_dates' => [
            ['certification' => 'unbekannt', 'type' => 3],
            ['certification' => '7', 'type' => 3],
            ['certification' => ' 12 ', 'type' => 4],
        ],
    ]];

    expect(MovieDetails::fromTmdb($data, 'DE')->fsk)->toBe(12);
});

test('the FSK is unknown without a German release', function () {
    $data = tmdbFixture('movie-603');
    $data['release_dates']['results'] = [['iso_3166_1' => 'US', 'release_dates' => [['certification' => 'R', 'type' => 3]]]];

    expect(MovieDetails::fromTmdb($data, 'DE')->fsk)->toBeNull();
});

test('directors are unique and only taken from the crew job Director', function () {
    expect(MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'DE')->directors)->toBe([
        ['id' => 9339, 'name' => 'Lilly Wachowski'],
        ['id' => 9340, 'name' => 'Lana Wachowski'],
    ]);
});

test('the cast is ordered by billing', function () {
    $cast = MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'DE')->cast;

    expect(array_column($cast, 'name'))->toBe(['Keanu Reeves', 'Laurence Fishburne', 'Carrie-Anne Moss'])
        ->and($cast[0]['character'])->toBe('Neo');
});

test('only subscription providers of the region count as streaming', function () {
    expect(array_column(MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'DE')->streamingProviders, 'name'))
        ->toBe(['Netflix', 'WOW'])
        ->and(array_column(MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'AT')->streamingProviders, 'name'))
        ->toBe(['Disney Plus'])
        ->and(MovieDetails::fromTmdb(tmdbFixture('movie-603'), 'CH')->streamingProviders)
        ->toBe([]);
});

test('sparse responses produce empty values instead of errors', function () {
    $details = MovieDetails::fromTmdb(['id' => 1, 'title' => 'Ohne alles', 'original_title' => 'Ohne alles', 'runtime' => 0, 'release_date' => ''], 'DE');

    expect($details)
        ->originalTitle->toBeNull()
        ->year->toBeNull()
        ->runtime->toBeNull()
        ->fsk->toBeNull()
        ->directors->toBe([])
        ->streamingProviders->toBe([]);
});
