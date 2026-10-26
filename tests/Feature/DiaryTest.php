<?php

use App\Livewire\Diary;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    $this->travelTo(now('Europe/Berlin')->setDate(2026, 10, 26)->setTime(12, 0));
});

test('an empty diary points to the search', function () {
    $this->get(route('diary'))
        ->assertOk()
        ->assertSee('Noch keine Sichtungen.')
        ->assertSee(route('search'));
});

test('viewings are listed newest first and grouped by month', function () {
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Alien']))->create(['watched_at' => '2026-09-12 20:00']);
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Heat']))->create(['watched_at' => '2026-10-20 20:00']);
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Fargo']))->create(['watched_at' => '2026-10-03 20:00']);

    $this->get(route('diary'))
        ->assertOk()
        ->assertSeeInOrder(['Oktober 2026', '2 Filme', 'Heat', 'Fargo', 'September 2026', '1 Film', 'Alien']);
});

test('the month follows the local time zone', function () {
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Silvesterfilm']))->create(['watched_at' => '2025-12-31 23:30:00+00:00']);

    $this->get(route('diary'))->assertSee('Januar 2026')->assertDontSee('Dezember 2025');
});

test('repeat viewings are numbered chronologically', function () {
    $movie = Movie::factory()->create(['title' => 'Matrix']);
    Viewing::factory()->for($movie)->create(['watched_at' => '2020-05-01 20:00']);
    Viewing::factory()->for($movie)->create(['watched_at' => '2026-10-16 20:00']);
    Viewing::factory()->for($movie)->create(['watched_at' => '2023-02-01 20:00']);

    $this->get(route('diary'))
        ->assertSeeInOrder(['3. Sichtung', '2. Sichtung'])
        ->assertDontSee('1. Sichtung');
});

test('an entry shows rating, companions and note', function () {
    $viewing = Viewing::factory()->for(Movie::factory()->create(['title' => 'Heat']))
        ->create(['watched_at' => '2026-10-20 20:00', 'rating' => 8, 'note' => 'Die Banküberfall-Szene!']);
    $viewing->companions()->attach([
        Companion::factory()->create(['name' => 'Jasmin', 'sort' => 1])->id,
        Companion::factory()->create(['name' => 'Tom', 'sort' => 2])->id,
    ]);
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Alien']))->create(['watched_at' => '2026-10-10 20:00', 'rating' => null]);

    $this->get(route('diary'))
        ->assertSeeInOrder(['Heat', '8', '/10', 'mit Jasmin und Tom', '„Die Banküberfall-Szene!“', 'Alien', 'allein']);
});

test('imported viewings show their source', function () {
    Viewing::factory()->for(Movie::factory()->create())->create(['source' => 'trakt', 'external_id' => '123']);

    $this->get(route('diary'))->assertSee('Trakt');
});

test('a viewing can be edited', function () {
    $jasmin = Companion::factory()->create(['name' => 'Jasmin']);
    $viewing = Viewing::factory()->for(Movie::factory()->create(['title' => 'Heat']))
        ->create(['watched_at' => '2026-10-20 18:15:00+00:00', 'rating' => 6, 'note' => null]);

    Livewire::test(Diary::class)
        ->call('editViewing', $viewing->id)
        ->assertSet('showViewingDialog', true)
        ->assertSet('form.date', '2026-10-20')
        ->assertSet('form.rating', 6)
        ->assertSee('Sichtung von „Heat“ bearbeiten')
        ->set('form.rating', 7)
        ->set('form.companionIds', [(string) $jasmin->id])
        ->set('form.note', 'Beim zweiten Mal besser.')
        ->call('updateViewing')
        ->assertHasNoErrors()
        ->assertSet('showViewingDialog', false)
        ->assertSee('mit Jasmin');

    $viewing->refresh();

    expect($viewing)
        ->rating->toBe(7)
        ->note->toBe('Beim zweiten Mal besser.')
        ->and($viewing->watched_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-20 18:15')
        ->and($viewing->companions->pluck('id')->all())->toBe([$jasmin->id]);
});

test('changing the date of a viewing moves it to 8 pm on that day', function () {
    $viewing = Viewing::factory()->for(Movie::factory()->create())->create(['watched_at' => '2026-10-20 18:15:00+00:00']);

    Livewire::test(Diary::class)
        ->call('editViewing', $viewing->id)
        ->set('form.date', '2026-10-18')
        ->call('updateViewing');

    expect($viewing->refresh()->watched_at->timezone('Europe/Berlin')->format('Y-m-d H:i'))->toBe('2026-10-18 20:00');
});

test('editing validates the input', function () {
    $viewing = Viewing::factory()->for(Movie::factory()->create())->create(['rating' => 5]);

    Livewire::test(Diary::class)
        ->call('editViewing', $viewing->id)
        ->set('form.date', '2027-01-01')
        ->call('updateViewing')
        ->assertHasErrors('form.date')
        ->assertSee('Das Datum darf nicht in der Zukunft liegen.');
});

test('a viewing can be deleted', function () {
    $viewing = Viewing::factory()->for(Movie::factory()->create(['title' => 'Heat']))->create();
    $viewing->companions()->attach(Companion::factory()->create());

    Livewire::test(Diary::class)
        ->call('deleteViewing', $viewing->id)
        ->assertDontSee('Heat');

    expect(Viewing::count())->toBe(0)
        ->and(Companion::count())->toBe(1);
});

test('older viewings load on demand', function () {
    $movie = Movie::factory()->create();
    Viewing::factory()->for($movie)->count(50)->create(['watched_at' => '2026-10-01 20:00']);
    Viewing::factory()->for(Movie::factory()->create(['title' => 'Ganz alter Film']))->create(['watched_at' => '2001-01-01 20:00']);

    Livewire::test(Diary::class)
        ->assertSee('Ältere Sichtungen anzeigen')
        ->assertDontSee('Ganz alter Film')
        ->call('showMore')
        ->assertSee('Ganz alter Film')
        ->assertDontSee('Ältere Sichtungen anzeigen');
});
