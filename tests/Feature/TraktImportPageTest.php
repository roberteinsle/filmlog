<?php

use App\Actions\ImportTraktExport;
use App\Jobs\ImportTraktZip;
use App\Livewire\TraktImportPage;
use App\Models\TraktImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
    Storage::fake('local');
});

test('the import page is linked from the settings', function () {
    $this->get(route('settings'))->assertSee(route('import'));

    $this->get(route('import'))
        ->assertOk()
        ->assertSee('<title>Trakt-Import · Filmlog</title>', false);
});

test('uploading a ZIP stores it and queues the import', function () {
    Queue::fake();

    Livewire::test(TraktImportPage::class)
        ->set('file', UploadedFile::fake()->createWithContent('trakt-export.zip', file_get_contents(Storage::disk('local')->path(traktZip(['watchlist.json' => []])))))
        ->call('startImport')
        ->assertHasNoErrors()
        ->assertSet('file', null)
        ->assertSee('trakt-export.zip')
        ->assertSee('wartet');

    $import = TraktImport::sole();

    expect($import->status)->toBe(TraktImport::PENDING)
        ->and($import->path)->toStartWith('imports/');
    Storage::disk('local')->assertExists($import->path);
    Queue::assertPushed(ImportTraktZip::class, fn (ImportTraktZip $job): bool => $job->import->is($import));
});

test('only ZIP files are accepted', function () {
    Queue::fake();

    Livewire::test(TraktImportPage::class)
        ->set('file', UploadedFile::fake()->create('export.json', 10, 'application/json'))
        ->call('startImport')
        ->assertHasErrors('file');

    expect(TraktImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a running import blocks new uploads and polls for progress', function () {
    Queue::fake();
    TraktImport::factory()->create(['status' => TraktImport::RUNNING, 'total_movies' => 200, 'resolved' => array_fill_keys(array_map(fn ($i) => "tmdb:{$i}", range(1, 50)), 1)]);

    Livewire::test(TraktImportPage::class)
        ->assertSeeHtml('wire:poll.2s')
        ->assertSee('50 von 200 Filmen zugeordnet')
        ->assertSee('Ein Import läuft gerade.')
        ->set('file', UploadedFile::fake()->create('trakt.zip', 1, 'application/zip'))
        ->call('startImport');

    expect(TraktImport::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('a finished import shows its report', function () {
    TraktImport::factory()->create([
        'status' => TraktImport::DONE,
        'stats' => ['viewings_created' => 812, 'ratings_applied' => 400, 'movies_unresolved' => 1],
        'unresolved' => [['key' => 'trakt:9', 'title' => 'Ohne IDs', 'year' => 1931, 'ids' => 'trakt 9', 'reason' => 'Weder TMDB- noch IMDb-ID im Export']],
        'ignored_files' => ['readme.json'],
        'read_files' => ['watched-history.json (992)', 'ratings-movies.json (0)'],
        'export_errors' => ['{"file":"ratings-movies.json","error":"HTTP 502"}'],
        'finished_at' => now(),
    ]);

    Livewire::test(TraktImportPage::class)
        ->assertDontSeeHtml('wire:poll')
        ->assertSee('fertig')
        ->assertSeeInOrder(['812', 'Sichtungen neu', '400', 'Bewertungen übernommen'])
        ->assertSee('1 Film nicht zuordenbar')
        ->assertSee('Ohne IDs')
        ->assertSee('Gelesen (Film-Einträge): watched-history.json (992), ratings-movies.json (0)')
        ->assertSee('Der Trakt-Export meldet 1 Fehler (_errors.json)')
        ->assertSee('HTTP 502')
        ->assertSee('Nicht erkannte Dateien: readme.json');
});

test('the job marks a broken ZIP as failed', function () {
    Storage::disk('local')->put('imports/kaputt.zip', 'kein zip');
    $import = TraktImport::factory()->create(['path' => 'imports/kaputt.zip']);

    (new ImportTraktZip($import))->handle(app(ImportTraktExport::class));

    expect($import->refresh())
        ->status->toBe(TraktImport::FAILED)
        ->error->toBe('Die Datei ist kein lesbares ZIP-Archiv.');
});

test('the job queues the next stage until the import is done', function () {
    Queue::fake();
    $import = TraktImport::factory()->create(['path' => traktZip(['watchlist.json' => []])]);

    $action = Mockery::mock(ImportTraktExport::class);
    $action->shouldReceive('handle')->once()->andReturnFalse();

    (new ImportTraktZip($import))->handle($action);

    Queue::assertPushed(ImportTraktZip::class, 1);
});

test('the job skips finished imports', function () {
    Queue::fake();
    $import = TraktImport::factory()->create(['status' => TraktImport::DONE]);

    $action = Mockery::mock(ImportTraktExport::class);
    $action->shouldNotReceive('handle');

    (new ImportTraktZip($import))->handle($action);

    Queue::assertNothingPushed();
});
