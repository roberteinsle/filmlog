<?php

use App\Jobs\LogPlexScrobble;
use App\Services\Plex\PlexGuids;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.plex.webhook_secret' => 'geheim-123']);
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function plexPayload(array $overrides = [], array $metadata = []): array
{
    return array_replace([
        'event' => 'media.scrobble',
        'user' => true,
        'owner' => true,
        'Account' => ['id' => 1, 'title' => 'tyrionschnee'],
        'Metadata' => array_replace([
            'type' => 'movie',
            'ratingKey' => '4711',
            'title' => 'Matrix',
            'year' => 1999,
            'guid' => 'plex://movie/5d7768',
            'Guid' => [['id' => 'imdb://tt0133093'], ['id' => 'tmdb://603']],
            'lastViewedAt' => 1792958400,
        ], $metadata),
    ], $overrides);
}

function postPlexWebhook(array $payload, string $secret = 'geheim-123'): TestResponse
{
    return test()->post('/webhooks/plex/'.$secret, ['payload' => json_encode($payload)]);
}

test('a scrobbled movie is queued as a viewing without login', function () {
    postPlexWebhook(plexPayload())->assertStatus(202);

    Queue::assertPushed(LogPlexScrobble::class, fn (LogPlexScrobble $job): bool => $job->tmdbId === 603
        && $job->imdbId === 'tt0133093'
        && $job->title === 'Matrix'
        && $job->ratingKey === '4711'
        && $job->viewedAt === 1792958400);
});

test('a wrong or missing secret is answered with 404', function () {
    postPlexWebhook(plexPayload(), 'falsch')->assertNotFound();

    config(['services.plex.webhook_secret' => null]);
    postPlexWebhook(plexPayload(), 'geheim-123')->assertNotFound();

    Queue::assertNothingPushed();
});

test('other events, episodes and other users are ignored', function (array $overrides, array $metadata) {
    postPlexWebhook(plexPayload($overrides, $metadata))->assertNoContent();

    Queue::assertNothingPushed();
})->with([
    'play event' => [['event' => 'media.play'], []],
    'episode' => [[], ['type' => 'episode']],
    'someone else on the server' => [['user' => false], []],
]);

test('a broken payload is rejected', function () {
    $this->post('/webhooks/plex/geheim-123', ['payload' => 'kein json'])->assertStatus(400);
});

test('TMDB and IMDb ids are read from new and legacy Plex agents', function (array $metadata, ?int $tmdb, ?string $imdb) {
    expect(PlexGuids::from($metadata))->toBe(['tmdb' => $tmdb, 'imdb' => $imdb]);
})->with([
    'new agent' => [['Guid' => [['id' => 'imdb://tt0133093'], ['id' => 'tmdb://603'], ['id' => 'tvdb://1']]], 603, 'tt0133093'],
    'legacy TMDB agent' => [['guid' => 'com.plexapp.agents.themoviedb://603?lang=de'], 603, null],
    'legacy IMDb agent' => [['guid' => 'com.plexapp.agents.imdb://tt0133093?lang=de'], null, 'tt0133093'],
    'unknown' => [['guid' => 'local://123'], null, null],
]);
