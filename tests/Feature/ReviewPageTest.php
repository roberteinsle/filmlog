<?php

use App\Jobs\RefreshCollection;
use App\Livewire\Review;
use App\Models\Movie;
use App\Models\MovieCollection;
use App\Models\Viewing;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    $this->travelTo(now('Europe/Berlin')->setDate(2026, 10, 26)->setTime(12, 0));
});

test('without viewings the review explains how to start', function () {
    $this->get(route('review'))->assertOk()->assertSee('Noch keine Sichtungen.');
});

test('the current year is shown by default with all sections', function () {
    $heat = Movie::factory()->create(['title' => 'Heat', 'runtime' => 170, 'genre_ids' => [80], 'directors' => [['id' => 638, 'name' => 'Michael Mann']], 'vote_average' => 7.9, 'vote_count' => 7000]);
    Viewing::factory()->for($heat)->create(['watched_at' => '2026-10-01 20:00', 'rating' => 10]);
    Viewing::factory()->for($heat)->create(['watched_at' => '2025-05-01 20:00', 'rating' => 8]);

    $this->get(route('review'))
        ->assertOk()
        ->assertSeeInOrder(['2026', '2025', 'Okt', '2026', 'Sichtungen', '1', 'Stunden', '2,8', 'Top-Genres', 'Krimi', 'Lieblingsregie', 'Michael Mann', 'Gegen den Strom', 'Heat', '+2,1', 'Lücken']);
});

test('a year and month can be chosen and are kept in the URL', function () {
    $movie = Movie::factory()->create(['runtime' => 100]);
    Viewing::factory()->for($movie)->create(['watched_at' => '2025-05-01 20:00']);
    Viewing::factory()->for($movie)->create(['watched_at' => '2025-06-01 20:00']);
    Viewing::factory()->for($movie)->create(['watched_at' => '2026-01-01 20:00']);

    $component = Livewire::withQueryParams(['jahr' => '2025'])->test(Review::class)->assertSee('Sichtungen');
    expect($component->instance()->stats->summary()['viewings'])->toBe(2);

    $component->call('chooseMonth', 6)->assertSet('month', '6')->assertSee('Juni 2025');
    expect($component->instance()->stats->summary()['viewings'])->toBe(1);

    $component->call('chooseMonth', 6)->assertSet('month', '');
    $component->call('chooseYear', 2026)->assertSet('year', '2026');
    expect($component->instance()->stats->summary()['viewings'])->toBe(1);
});

test('an unknown year in the URL falls back to the current one', function () {
    Viewing::factory()->for(Movie::factory()->create())->create(['watched_at' => '2026-03-01 20:00']);

    expect(Livewire::withQueryParams(['jahr' => '1800', 'monat' => '13'])->test(Review::class)->instance())
        ->selectedYear->toBe(2026)
        ->selectedMonth->toBeNull();
});

test('gaps are listed with the missing parts', function () {
    MovieCollection::factory()->create(['id' => 2344, 'name' => 'Matrix – Filmreihe', 'parts' => [
        ['id' => 603, 'title' => 'Matrix', 'release_date' => '1999-03-30', 'poster_path' => null],
        ['id' => 604, 'title' => 'Matrix Reloaded', 'release_date' => '2003-05-15', 'poster_path' => null],
    ]]);
    Viewing::factory()->for(Movie::factory()->create(['tmdb_id' => 603, 'collection_id' => 2344]))->create(['watched_at' => '2026-01-01 20:00']);

    $this->get(route('review'))
        ->assertSeeInOrder(['Lücken', 'Matrix – Filmreihe', '1 von 2 gesehen', 'Matrix Reloaded', '2003'])
        ->assertSee(route('movie', 604));
});

test('collections that are not loaded yet can be loaded right away', function () {
    Queue::fake();
    Viewing::factory()->for(Movie::factory()->create(['collection_id' => 2344]))->create(['watched_at' => '2026-01-01 20:00']);

    Livewire::test(Review::class)
        ->assertSee('1 Filmreihe ist noch nicht geladen')
        ->call('loadCollections')
        ->assertSee('Filmreihen werden geladen')
        ->assertSeeHtml('wire:poll.5s');

    Queue::assertPushed(RefreshCollection::class, fn (RefreshCollection $job): bool => $job->collectionId === 2344);
});
