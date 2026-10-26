<?php

use App\Jobs\ImportCinemaChart;
use App\Livewire\CinemaCharts;
use App\Models\CinemaChartEntry;
use App\Models\CinemaChartYear;
use App\Models\Movie;
use App\Models\StreamingSubscription;
use App\Models\Viewing;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    Queue::fake();
    $this->travelTo(now('Europe/Berlin')->setDate(2026, 10, 26)->setTime(12, 0));
});

test('the charts are in the navigation between lists and diary', function () {
    $this->get(route('tonight'))->assertSeeInOrder([route('lists'), route('charts'), route('diary')]);
});

test('opening a year without data starts the import', function () {
    Livewire::withQueryParams(['jahr' => '2025'])->test(CinemaCharts::class)
        ->assertSee('Die Kinocharts 2025 werden von InsideKino geladen')
        ->assertSeeHtml('wire:poll.3s');

    Queue::assertPushed(ImportCinemaChart::class, fn (ImportCinemaChart $job): bool => $job->year === 2025);
    expect(CinemaChartYear::find(2025)->status)->toBe(CinemaChartYear::LOADING);
});

test('a running import is not started twice', function () {
    CinemaChartYear::factory()->create(['year' => 2025, 'status' => CinemaChartYear::LOADING, 'fetched_at' => null]);

    Livewire::withQueryParams(['jahr' => '2025'])->test(CinemaCharts::class);

    Queue::assertNothingPushed();
});

test('the list shows poster, title, TMDB rating, Plex and your subscriptions', function () {
    StreamingSubscription::factory()->create(['provider_id' => 337, 'name' => 'Disney Plus']);
    StreamingSubscription::factory()->create(['provider_id' => 8, 'name' => 'Netflix']);
    $chart = CinemaChartYear::factory()->create(['year' => 2025]);
    $avatar = Movie::factory()->create(['title' => 'Avatar: Fire and Ash', 'year' => 2025, 'vote_average' => 7.4, 'vote_count' => 900, 'on_plex' => true, 'provider_ids' => [337]]);
    $kanu = Movie::factory()->create(['title' => 'Das Kanu des Manitu', 'year' => 2025, 'vote_average' => 6.1, 'vote_count' => 300, 'provider_ids' => []]);
    Viewing::factory()->for($kanu)->create();
    CinemaChartEntry::factory()->create(['year' => 2025, 'rank' => 1, 'title' => 'Avatar - Fire and Ash', 'visitors' => 5503170, 'tmdb_id' => $avatar->tmdb_id, 'resolved_at' => now()]);
    CinemaChartEntry::factory()->create(['year' => 2025, 'rank' => 2, 'title' => 'Das Kanu des Manitu', 'visitors' => 383232, 'tmdb_id' => $kanu->tmdb_id, 'resolved_at' => now()]);
    CinemaChartEntry::factory()->create(['year' => 2025, 'rank' => 3, 'title' => 'Jerry Cotton', 'visitors' => 152155, 'tmdb_id' => null, 'resolved_at' => now()]);

    $this->get(route('charts', ['jahr' => 2025]))
        ->assertOk()
        ->assertSeeInOrder([
            '1', 'Avatar: Fire and Ash', '2025 · TMDB 7,4 · 5,5 Mio. Besucher', 'Plex', 'Disney Plus',
            '2', 'Gesehen', 'Das Kanu des Manitu', 'TMDB 6,1 · 383.232 Besucher',
            '3', 'Jerry Cotton', 'bei TMDB nicht gefunden',
            'Quelle', 'https://www.insidekino.de/DJahr/D2025.htm',
        ])
        ->assertDontSeeHtml('wire:poll');

    Queue::assertNothingPushed();
});

test('the year can be chosen and defaults to the current one', function () {
    $component = Livewire::test(CinemaCharts::class);
    expect($component->instance()->selectedYear)->toBe(2026);

    $component->call('chooseYear', 1999)->assertSet('year', '1999');
    expect($component->instance()->selectedYear)->toBe(1999);

    $component->call('chooseYear', 1800)->assertSet('year', '');
});

test('a year without charts shows the reason', function () {
    CinemaChartYear::factory()->create(['year' => 1975, 'status' => CinemaChartYear::FAILED, 'error' => 'Für 1975 gibt es bei InsideKino keine Jahrescharts.', 'fetched_at' => now()]);

    Livewire::withQueryParams(['jahr' => '1975'])->test(CinemaCharts::class)
        ->assertSee('Für 1975 gibt es bei InsideKino keine Jahrescharts.');

    Queue::assertNothingPushed();
});
