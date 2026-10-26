<?php

use App\Livewire\Lists;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the overview shows the watchlist first, then the own lists in order', function () {
    MovieList::factory()->create(['name' => 'Weihnachten', 'sort' => 2]);
    MovieList::factory()->create(['name' => 'Klassiker', 'sort' => 1]);
    MovieList::watchlist()->addMovie(Movie::factory()->create()->tmdb_id);

    $this->get(route('lists'))
        ->assertOk()
        ->assertSeeInOrder(['Watchlist', '1 Film', 'Eigene Listen', 'Klassiker', '0 Filme', 'Weihnachten']);
});

test('creating a list opens it', function () {
    MovieList::factory()->create(['sort' => 7]);

    Livewire::test(Lists::class)
        ->set('name', ' Klassiker nachholen ')
        ->call('createList')
        ->assertHasNoErrors()
        ->assertRedirect(route('lists.show', MovieList::firstWhere('name', 'Klassiker nachholen')));

    expect(MovieList::firstWhere('name', 'Klassiker nachholen'))
        ->is_watchlist->toBeFalse()
        ->sort->toBe(8);
});

test('list names must be present and unique', function (string $name) {
    MovieList::factory()->create(['name' => 'Klassiker']);

    Livewire::test(Lists::class)
        ->set('name', $name)
        ->call('createList')
        ->assertHasErrors('name');
})->with(['empty' => '', 'taken' => 'Klassiker', 'reserved' => 'Watchlist', 'too long' => str_repeat('x', 51)]);

test('a list can be renamed', function () {
    $list = MovieList::factory()->create(['name' => 'Klasiker']);

    Livewire::test(Lists::class)
        ->call('startEditing', $list->id)
        ->assertSet('editName', 'Klasiker')
        ->set('editName', 'Klassiker')
        ->call('saveList')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($list->refresh()->name)->toBe('Klassiker');
});

test('deleting a list keeps its movies and viewings', function () {
    $list = MovieList::factory()->create();
    $movie = Movie::factory()->create();
    Viewing::factory()->for($movie)->create();
    $list->addMovie($movie->tmdb_id);

    Livewire::test(Lists::class)->call('deleteList', $list->id);

    expect(MovieList::find($list->id))->toBeNull()
        ->and(Movie::find($movie->tmdb_id))->not->toBeNull()
        ->and(Viewing::count())->toBe(1);
});

test('the watchlist cannot be renamed or deleted', function (string $method) {
    Livewire::test(Lists::class)->call($method, MovieList::watchlist()->id)->assertNotFound();

    expect(MovieList::watchlist()->name)->toBe('Watchlist');
})->with(['startEditing', 'deleteList']);

test('own lists can be reordered', function () {
    $a = MovieList::factory()->create(['sort' => 1]);
    $b = MovieList::factory()->create(['sort' => 2]);
    $c = MovieList::factory()->create(['sort' => 3]);

    Livewire::test(Lists::class)->call('moveList', (string) $c->id, 0);

    expect(MovieList::query()->ordered()->pluck('id')->all())->toBe([MovieList::watchlist()->id, $c->id, $a->id, $b->id]);
});

test('reordering ignores the watchlist', function () {
    $own = MovieList::factory()->create(['sort' => 1]);

    Livewire::test(Lists::class)->call('moveList', MovieList::watchlist()->id, 1);

    expect(MovieList::query()->ordered()->pluck('id')->all())->toBe([MovieList::watchlist()->id, $own->id]);
});
