<?php

use App\Services\Tmdb\MovieNotFoundException;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

test('movie details are requested in German with all appended data and the bearer token', function () {
    fakeTmdb();

    app(TmdbClient::class)->movie(603);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://api.themoviedb.org/3/movie/603?')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['language'] === 'de-DE'
        && $request['append_to_response'] === 'release_dates,credits,watch/providers,videos'
        && $request['include_video_language'] === 'de,en');
});

test('search sends the query with German language and region and excludes adult titles', function () {
    fakeTmdb();

    $result = app(TmdbClient::class)->searchMovies('matrix');

    expect($result['results'])->toHaveCount(3);
    Http::assertSent(fn (Request $request): bool => $request['query'] === 'matrix'
        && $request['language'] === 'de-DE'
        && $request['region'] === 'DE'
        && $request['include_adult'] === 'false');
});

test('responses are cached', function () {
    fakeTmdb();
    $client = app(TmdbClient::class);

    $client->movie(603);
    $client->movie(603);
    $client->searchMovies('matrix');
    $client->searchMovies('matrix');

    Http::assertSentCount(2);
});

test('an unknown movie raises a not-found exception', function () {
    fakeTmdb();

    app(TmdbClient::class)->movie(999_999_999);
})->throws(MovieNotFoundException::class);

test('server errors are retried and then reported', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response(['status_message' => 'Fehler'], 503)]);

    expect(fn () => app(TmdbClient::class)->movie(603))->toThrow(TmdbException::class, 'HTTP 503');
    Http::assertSentCount(3);
});

test('a missing token fails without sending a request', function () {
    Http::fake();
    config(['services.tmdb.token' => null]);
    app()->forgetInstance(TmdbClient::class);

    expect(fn () => app(TmdbClient::class)->searchMovies('matrix'))->toThrow(TmdbException::class, 'TMDB_TOKEN');
    Http::assertNothingSent();
});
