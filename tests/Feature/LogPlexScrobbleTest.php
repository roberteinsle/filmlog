<?php

use App\Jobs\LogPlexScrobble;
use App\Models\Movie;
use App\Models\Viewing;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/movie/603*' => Http::response(tmdbFixture('movie-603')),
        'api.themoviedb.org/3/find/tt0133093*' => Http::response(['movie_results' => [['id' => 603]]]),
        'api.themoviedb.org/3/find/*' => Http::response(['movie_results' => []]),
    ]);
});

function runPlexScrobble(?int $tmdbId = 603, ?string $imdbId = null, int $viewedAt = 1792958400): void
{
    app()->call([new LogPlexScrobble($tmdbId, $imdbId, 'Matrix', '4711', $viewedAt), 'handle']);
}

test('a scrobble becomes a Plex viewing at the time it was watched', function () {
    runPlexScrobble();

    $viewing = Viewing::sole();

    expect($viewing)
        ->tmdb_id->toBe(603)
        ->source->toBe('plex')
        ->external_id->toBe('4711:1792958400')
        ->rating->toBeNull()
        ->and($viewing->watched_at->getTimestamp())->toBe(1792958400)
        ->and(Movie::find(603)->title)->toBe('Matrix');
});

test('a movie known only by its IMDb id is resolved', function () {
    runPlexScrobble(tmdbId: null, imdbId: 'tt0133093');

    expect(Viewing::sole()->tmdb_id)->toBe(603);
});

test('a duplicate scrobble within a few hours is ignored, a later one counts', function () {
    runPlexScrobble(viewedAt: 1792958400);
    runPlexScrobble(viewedAt: 1792958400 + 60);
    runPlexScrobble(viewedAt: 1792958400 + 2 * 86400);

    expect(Viewing::count())->toBe(2);
});

test('an unknown movie is logged and skipped', function () {
    runPlexScrobble(tmdbId: null, imdbId: 'tt9999999');

    expect(Viewing::count())->toBe(0);
});
