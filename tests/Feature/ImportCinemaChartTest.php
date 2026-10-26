<?php

use App\Jobs\ImportCinemaChart;
use App\Models\CinemaChartEntry;
use App\Models\CinemaChartYear;
use App\Models\Movie;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(now('Europe/Berlin')->setDate(2026, 10, 26)->setTime(12, 0));
    Http::preventStrayRequests();
    Http::fake([
        'www.insidekino.de/DJahr/D2025.htm' => Http::response(file_get_contents(__DIR__.'/../Fixtures/insidekino/D2025.htm')),
        'www.insidekino.de/DJahr/D2010.htm' => Http::response(file_get_contents(__DIR__.'/../Fixtures/insidekino/D2010.htm')),
        'www.insidekino.de/*' => Http::response('', 404),
        'api.themoviedb.org/3/find/tt1757678*' => Http::response(['movie_results' => [['id' => 83533]]]),
        // Kanu im Chart-Jahr, Männer im Schnee erst im Vorjahr, Jerry Cotton gar nicht
        'api.themoviedb.org/3/search/movie*' => fn (Request $request) => Http::response(['results' => match (true) {
            $request['query'] === 'Das Kanu des Manitu' => [['id' => 1111]],
            $request['query'] === 'Männer im Schnee' && ($request['year'] ?? null) == 2024 => [['id' => 2222]],
            default => [],
        }]),
    ]);
    Movie::factory()->create(['tmdb_id' => 83533, 'title' => 'Avatar: Fire and Ash']);
    Movie::factory()->create(['tmdb_id' => 1111, 'title' => 'Das Kanu des Manitu']);
    Movie::factory()->create(['tmdb_id' => 2222, 'title' => 'Männer im Schnee']);
});

function runChartImport(int $year): void
{
    app()->call([new ImportCinemaChart($year), 'handle']);
}

test('a chart year is stored and its movies are matched to TMDB', function () {
    runChartImport(2025);

    $chart = CinemaChartYear::find(2025);

    expect($chart)
        ->status->toBe(CinemaChartYear::DONE)
        ->fetched_at->not->toBeNull()
        ->and($chart->entries->map->only('rank', 'title', 'tmdb_id')->all())->toBe([
            ['rank' => 1, 'title' => 'Avatar - Fire and Ash', 'tmdb_id' => 83533],
            ['rank' => 2, 'title' => 'Das Kanu des Manitu', 'tmdb_id' => 1111],
            ['rank' => 3, 'title' => 'Männer im Schnee', 'tmdb_id' => 2222],
            ['rank' => 4, 'title' => 'Jerry Cotton', 'tmdb_id' => null],
        ])
        ->and($chart->entries->every(fn (CinemaChartEntry $entry): bool => $entry->resolved_at !== null))->toBeTrue();
});

test('a finished year is not fetched again, the current one once a day', function () {
    runChartImport(2025);
    runChartImport(2025);

    expect(Http::recorded(fn (Request $request): bool => str_contains($request->url(), 'insidekino.de'))->count())->toBe(1);

    CinemaChartYear::factory()->create(['year' => 2026, 'fetched_at' => now()->subHours(2)]);
    expect(CinemaChartYear::find(2026)->needsFetch())->toBeFalse();

    $this->travel(1)->day();
    expect(CinemaChartYear::find(2026)->needsFetch())->toBeTrue()
        ->and(CinemaChartYear::find(2025)->needsFetch())->toBeFalse();
});

test('a refetch keeps matches of unchanged ranks', function () {
    runChartImport(2025);
    CinemaChartEntry::query()->where('rank', 2)->update(['tmdb_id' => 83533]);
    CinemaChartYear::query()->whereKey(2025)->update(['fetched_at' => null]);

    runChartImport(2025);

    expect(CinemaChartEntry::query()->where('rank', 2)->value('tmdb_id'))->toBe(83533);
});

test('a year without charts is marked as failed', function () {
    runChartImport(1960);

    expect(CinemaChartYear::find(1960))
        ->status->toBe(CinemaChartYear::FAILED)
        ->error->toBe('Für 1960 gibt es bei InsideKino keine Jahrescharts.');
});
