<?php

use App\Livewire\MovieShow;
use App\Models\Movie;
use App\Models\MovieCollection;
use App\Models\Viewing;
use App\Services\Plex\PlexClient;
use App\Services\Tmdb\MovieDetails;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the movie page links to the German YouTube trailer', function () {
    fakeTmdb();

    $this->get(route('movie', 603))
        ->assertSee('Trailer auf YouTube')
        ->assertSee('https://www.youtube.com/watch?v=m8e-FF8MsqU', false);
});

test('the trailer prefers German, then trailers over teasers, then official ones', function (array $videos, ?string $expected) {
    $details = MovieDetails::fromTmdb(['id' => 1, 'title' => 'X', 'videos' => ['results' => $videos]], 'DE');

    expect($details->trailerKey)->toBe($expected);
})->with([
    'German teaser beats English trailer' => [[
        ['iso_639_1' => 'en', 'key' => 'enTrailer01', 'site' => 'YouTube', 'type' => 'Trailer', 'official' => true],
        ['iso_639_1' => 'de', 'key' => 'deTeaser001', 'site' => 'YouTube', 'type' => 'Teaser', 'official' => true],
    ], 'deTeaser001'],
    'only YouTube counts' => [[['iso_639_1' => 'de', 'key' => 'vimeo00001', 'site' => 'Vimeo', 'type' => 'Trailer']], null],
    'featurettes are no trailers' => [[['iso_639_1' => 'de', 'key' => 'making00001', 'site' => 'YouTube', 'type' => 'Featurette']], null],
    'no videos' => [[], null],
]);

test('the movie page shows the other parts of its collection', function () {
    fakeTmdb();
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 604]))->create();

    $this->get(route('movie', 603))
        ->assertSeeInOrder(['Matrix Filmreihe', 'Matrix', '1999', 'Gesehen', 'Matrix Reloaded', '2003', 'Matrix Revolutions', 'Matrix Resurrections', '2021'])
        ->assertSeeHtml('aria-current="page"')
        ->assertSee(route('movie', 624860));

    expect(MovieCollection::find(2344)->name)->toBe('Matrix Filmreihe');
});

test('the Plex library can be synced from the movie page', function () {
    fakeTmdb();
    Queue::fake();
    config(['services.plex.url' => 'http://plex.local:32400', 'services.plex.token' => 'plex-token']);
    app()->forgetInstance(PlexClient::class);
    Http::fake([
        'plex.local:32400/library/sections/1/all*' => Http::response(['MediaContainer' => ['Metadata' => [
            ['ratingKey' => '7', 'title' => 'Matrix', 'year' => 1999, 'Guid' => [['id' => 'tmdb://603']]],
        ]]]),
        'plex.local:32400/library/sections*' => Http::response(['MediaContainer' => ['Directory' => [['key' => '1', 'type' => 'movie']]]]),
    ]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->assertSee('Mit Plex abgleichen')
        ->assertDontSee('Auf Plex vorhanden')
        ->call('syncWithPlex')
        ->assertSee('Abgeglichen: Der Film liegt auf Plex.')
        ->assertSee('Auf Plex vorhanden');
});

test('an unreachable Plex is reported on the movie page', function () {
    fakeTmdb();
    config(['services.plex.url' => 'http://plex.local:32400', 'services.plex.token' => 'plex-token']);
    app()->forgetInstance(PlexClient::class);
    Http::fake(['plex.local:32400/*' => Http::response('', 500)]);

    Livewire::test(MovieShow::class, ['tmdbId' => 603])
        ->call('syncWithPlex')
        ->assertSee('Plex ist gerade nicht erreichbar.');
});

test('without Plex configuration there is no sync button', function () {
    fakeTmdb();
    config(['services.plex.url' => null]);
    app()->forgetInstance(PlexClient::class);

    $this->get(route('movie', 603))->assertDontSee('Mit Plex abgleichen');
});
