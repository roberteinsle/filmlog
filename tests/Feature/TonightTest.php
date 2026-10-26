<?php

use App\Livewire\Tonight;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\MovieList;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function tonightCandidate(array $attributes = []): Movie
{
    $movie = Movie::factory()->create($attributes + ['fsk' => 12, 'runtime' => 110, 'genre_ids' => [18]]);
    MovieList::watchlist()->addMovie($movie->tmdb_id);

    return $movie;
}

test('the start page offers the three questions and suggestions', function () {
    Companion::factory()->create(['name' => 'Familie', 'max_fsk' => 12]);
    tonightCandidate(['title' => 'Heat']);

    $this->get(route('tonight'))
        ->assertOk()
        ->assertSeeInOrder(['Mit wem?', 'Allein', 'Familie', 'Wie viel Zeit?', 'unter 100 Min.', 'Worauf hast du Lust?', 'Spannung', 'Vorschläge', 'Heat', 'Läuft gerade']);
});

test('at most three suggestions are shown', function () {
    foreach (range(1, 5) as $rank) {
        tonightCandidate(['title' => "Film {$rank}"]);
    }

    expect(Livewire::test(Tonight::class)->instance()->result['suggestions'])->toHaveCount(3);
});

test('companions set the FSK limit, the strictest one wins', function () {
    $jasmin = Companion::factory()->create(['name' => 'Jasmin', 'max_fsk' => null]);
    $family = Companion::factory()->create(['name' => 'Familie', 'max_fsk' => 12]);
    $kids = Companion::factory()->create(['name' => 'Kinder', 'max_fsk' => 6]);
    tonightCandidate(['title' => 'FSK 0', 'fsk' => 0]);
    tonightCandidate(['title' => 'FSK 12', 'fsk' => 12]);
    tonightCandidate(['title' => 'FSK 18', 'fsk' => 18]);

    $component = Livewire::test(Tonight::class)
        ->call('toggleCompanion', $jasmin->id)
        ->assertSet('companionIds', [$jasmin->id])
        ->assertDontSee('bis FSK');
    expect($component->instance()->result['candidates'])->toBe(3);

    $component->call('toggleCompanion', $family->id)->assertSee('bis FSK 12');
    expect($component->instance()->result['suggestions']->pluck('title')->sort()->values()->all())->toBe(['FSK 0', 'FSK 12']);

    $component->call('toggleCompanion', $kids->id)->assertSee('bis FSK 6');
    expect($component->instance()->result['suggestions']->pluck('title')->all())->toBe(['FSK 0']);

    $component->call('chooseAlone')->assertSet('companionIds', []);
    expect($component->instance()->result['candidates'])->toBe(3);
});

test('time and mood narrow the suggestions and are kept in the URL', function () {
    tonightCandidate(['title' => 'Kurzer Thriller', 'runtime' => 95, 'genre_ids' => [53]]);
    tonightCandidate(['title' => 'Langer Thriller', 'runtime' => 160, 'genre_ids' => [53]]);
    tonightCandidate(['title' => 'Kurze Komödie', 'runtime' => 90, 'genre_ids' => [35]]);

    $component = Livewire::withQueryParams(['zeit' => '100', 'stimmung' => 'spannung'])->test(Tonight::class)
        ->assertSet('time', '100')
        ->assertSet('mood', 'spannung');

    expect($component->instance()->result['suggestions']->pluck('title')->all())->toBe(['Kurzer Thriller']);

    $component->call('chooseTime', '')->call('chooseMood', 'lachen');
    expect($component->instance()->result['suggestions']->pluck('title')->all())->toBe(['Kurze Komödie']);
});

test('unknown choices fall back to egal', function () {
    Livewire::test(Tonight::class)
        ->call('chooseTime', '45')
        ->assertSet('time', '')
        ->call('chooseMood', 'drop table')
        ->assertSet('mood', '');
});

test('other suggestions skip the shown ones and start over when all were shown', function () {
    foreach (range(1, 4) as $rank) {
        tonightCandidate(['title' => "Film {$rank}"]);
    }

    $component = Livewire::test(Tonight::class);
    $first = $component->instance()->result['suggestions']->pluck('title');

    $component->call('otherSuggestions');
    $second = $component->instance()->result['suggestions']->pluck('title');

    expect($second->intersect($first)->all())->toBe([])
        ->and($second)->toHaveCount(1);

    $component->call('otherSuggestions')
        ->assertSee('Das waren alle passenden Filme')
        ->assertSet('shownTmdbIds', []);
    expect($component->instance()->result['suggestions'])->toHaveCount(3);
});

test('changing a choice starts the suggestions over', function () {
    foreach (range(1, 4) as $rank) {
        tonightCandidate(['title' => "Film {$rank}"]);
    }

    Livewire::test(Tonight::class)
        ->call('otherSuggestions')
        ->call('chooseTime', '130')
        ->assertSet('shownTmdbIds', []);
});

test('an empty result explains what to do', function () {
    Livewire::test(Tonight::class)->assertSee('Kein ungesehener Film passt dazu.');
});

test('now playing logs a viewing for today with the chosen companions and opens the rating', function () {
    $this->travelTo(now()->setTime(20, 15));
    $jasmin = Companion::factory()->create(['name' => 'Jasmin']);
    $heat = tonightCandidate(['title' => 'Heat']);

    $component = Livewire::test(Tonight::class)
        ->call('toggleCompanion', $jasmin->id)
        ->call('nowPlaying', $heat->tmdb_id)
        ->assertSet('showViewingDialog', true)
        ->assertSee('„Heat“ läuft');

    $viewing = Viewing::sole();

    expect($viewing->tmdb_id)->toBe($heat->tmdb_id)
        ->and($viewing->watched_at->equalTo(now()))->toBeTrue()
        ->and($viewing->rating)->toBeNull()
        ->and($viewing->companions->pluck('name')->all())->toBe(['Jasmin']);

    $component->set('form.rating', 8)
        ->call('saveViewing')
        ->assertHasNoErrors()
        ->assertSet('showViewingDialog', false)
        ->assertSet('askRemoveFromWatchlist', true)
        ->call('removeFromWatchlist')
        ->assertSet('askRemoveFromWatchlist', false);

    expect($viewing->refresh()->rating)->toBe(8)
        ->and(MovieList::watchlist()->contains($heat->tmdb_id))->toBeFalse()
        ->and($component->instance()->result['candidates'])->toBe(0);
});

test('closing the rating keeps the viewing and the movie can stay on the watchlist', function () {
    $heat = tonightCandidate(['title' => 'Heat']);

    Livewire::test(Tonight::class)
        ->call('nowPlaying', $heat->tmdb_id)
        ->call('closeViewingDialog')
        ->assertSet('askRemoveFromWatchlist', true)
        ->call('keepOnWatchlist');

    expect(Viewing::count())->toBe(1)
        ->and(MovieList::watchlist()->contains($heat->tmdb_id))->toBeTrue();
});
