<?php

use App\Console\Commands\RefreshCollections;
use App\Jobs\RefreshCollection;
use App\Models\Movie;
use App\Models\MovieCollection;
use App\Models\Viewing;
use App\Services\CollectionGaps;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{id: int, title: string, release_date: ?string, poster_path: ?string}
 */
function collectionPart(int $id, string $title, ?string $releaseDate): array
{
    return ['id' => $id, 'title' => $title, 'release_date' => $releaseDate, 'poster_path' => null];
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 26));
});

test('a collection with some but not all released parts seen is a gap', function () {
    MovieCollection::factory()->create(['id' => 2344, 'name' => 'Matrix – Filmreihe', 'parts' => [
        collectionPart(603, 'Matrix', '1999-03-30'),
        collectionPart(604, 'Matrix Reloaded', '2003-05-15'),
        collectionPart(605, 'Matrix Revolutions', '2003-11-05'),
        collectionPart(624860, 'Matrix Resurrections', '2021-12-16'),
        collectionPart(999, 'Matrix 5', null),
        collectionPart(998, 'Matrix 6', '2027-01-01'),
    ]]);
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 603, 'collection_id' => 2344]))->create();
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 604, 'collection_id' => 2344]))->create();

    $gap = app(CollectionGaps::class)->all()->sole();

    expect($gap['collection']->name)->toBe('Matrix – Filmreihe')
        ->and($gap['seen'])->toBe(2)
        ->and($gap['released'])->toBe(4)
        ->and(array_column($gap['missing'], 'title'))->toBe(['Matrix Revolutions', 'Matrix Resurrections']);
});

test('complete and unseen collections are no gaps', function () {
    MovieCollection::factory()->create(['id' => 1, 'parts' => [collectionPart(10, 'A', '2000-01-01'), collectionPart(11, 'B', '2002-01-01')]]);
    MovieCollection::factory()->create(['id' => 2, 'parts' => [collectionPart(20, 'C', '2000-01-01'), collectionPart(21, 'D', '2002-01-01')]]);
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 10, 'collection_id' => 1]))->create();
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 11, 'collection_id' => 1]))->create();
    Movie::factory()->create(['tmdb_id' => 20, 'collection_id' => 2]);

    expect(app(CollectionGaps::class)->all())->toBeEmpty();
});

test('gaps with fewer missing parts come first', function () {
    MovieCollection::factory()->create(['id' => 1, 'name' => 'Viele fehlen', 'parts' => [collectionPart(10, 'A', '2000-01-01'), collectionPart(11, 'B', '2001-01-01'), collectionPart(12, 'C', '2002-01-01')]]);
    MovieCollection::factory()->create(['id' => 2, 'name' => 'Einer fehlt', 'parts' => [collectionPart(20, 'D', '2000-01-01'), collectionPart(21, 'E', '2001-01-01')]]);
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 10, 'collection_id' => 1]))->create();
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 20, 'collection_id' => 2]))->create();

    expect(app(CollectionGaps::class)->all()->map(fn (array $gap): string => $gap['collection']->name)->all())->toBe(['Einer fehlt', 'Viele fehlen']);
});

test('only collections of seen movies that are missing or stale are queued', function () {
    Queue::fake();
    Viewing::factory()->for(Movie::factory()->create(['collection_id' => 100]))->create();
    Viewing::factory()->for(Movie::factory()->create(['collection_id' => 200]))->create();
    Viewing::factory()->for(Movie::factory()->create(['collection_id' => 300]))->create();
    Movie::factory()->create(['collection_id' => 400]);
    MovieCollection::factory()->create(['id' => 200, 'cached_at' => now()->subDays(40)]);
    MovieCollection::factory()->create(['id' => 300, 'cached_at' => now()->subDay()]);

    $this->artisan('filmlog:refresh-collections')->expectsOutput('2 Filmreihen zum Aktualisieren eingereiht.')->assertSuccessful();

    expect(Queue::pushed(RefreshCollection::class)->pluck('collectionId')->sort()->values()->all())->toBe([100, 200])
        ->and(app(CollectionGaps::class)->pendingCollections())->toBe(1);
});

test('refreshing a collection stores its parts by release date', function () {
    Http::preventStrayRequests();
    Http::fake(['api.themoviedb.org/3/collection/2344*' => Http::response([
        'id' => 2344,
        'name' => 'Matrix – Filmreihe',
        'parts' => [
            ['id' => 604, 'title' => 'Matrix Reloaded', 'release_date' => '2003-05-15', 'poster_path' => '/r.jpg'],
            ['id' => 999, 'title' => 'Matrix 5', 'release_date' => ''],
            ['id' => 603, 'title' => 'Matrix', 'release_date' => '1999-03-30', 'poster_path' => '/m.jpg'],
        ],
    ])]);

    app()->call([new RefreshCollection(2344), 'handle']);

    expect(MovieCollection::find(2344))
        ->name->toBe('Matrix – Filmreihe')
        ->cached_at->not->toBeNull()
        ->and(array_column(MovieCollection::find(2344)->parts, 'title'))->toBe(['Matrix', 'Matrix Reloaded', 'Matrix 5'])
        ->and(MovieCollection::find(2344)->parts[2]['release_date'])->toBeNull();
});

test('the static helper returns the number of queued collections', function () {
    Queue::fake();
    Viewing::factory()->for(Movie::factory()->create(['collection_id' => 100]))->create();

    expect(RefreshCollections::queueStale())->toBe(1);
});
