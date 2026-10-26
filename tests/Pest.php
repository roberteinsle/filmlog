<?php

use App\Http\Middleware\EnsureLoginIsFresh;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Tests schicken nie echte HTTP-Anfragen; die Anbieterliste (Einstellungen) kommt aus einem Fixture.
    ->beforeEach(function () {
        Http::preventStrayRequests();
        Http::fake(['api.themoviedb.org/3/watch/providers/movie*' => Http::response(tmdbFixture('watch-providers'))]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helfer
|--------------------------------------------------------------------------
*/

/**
 * Meldet einen Nutzer mit frischem Login-Zeitstempel an (siehe EnsureLoginIsFresh).
 */
function actingAsLoggedInUser(): User
{
    $user = User::factory()->create();
    test()->actingAs($user)->withSession([EnsureLoginIsFresh::SESSION_KEY => now()->getTimestamp()]);

    return $user;
}

/**
 * Gespeicherte TMDB-Antwort aus tests/Fixtures/tmdb.
 *
 * @return array<string, mixed>
 */
function tmdbFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/tmdb/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Leitet TMDB-Requests auf Fixtures um: Suche nach „matrix“ und Film 603.
 */
function fakeTmdb(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(tmdbFixture('search-matrix')),
        'api.themoviedb.org/3/movie/603*' => Http::response(tmdbFixture('movie-603')),
        'api.themoviedb.org/3/movie/*' => Http::response(['status_code' => 34, 'status_message' => 'The resource you requested could not be found.'], 404),
    ]);
}

/**
 * Baut einen Trakt-Export als ZIP auf der (gefakten) local-Disk und liefert den Pfad relativ zur Disk.
 *
 * @param  array<string, mixed>  $files  Dateiname im ZIP → Inhalt (Array wird zu JSON)
 */
function traktZip(array $files, string $path = 'imports/test.zip'): string
{
    $absolutePath = Storage::disk('local')->path($path);
    File::ensureDirectoryExists(dirname($absolutePath));

    $zip = new ZipArchive;
    $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $name => $content) {
        $zip->addFromString($name, is_string($content) ? $content : json_encode($content, JSON_THROW_ON_ERROR));
    }

    $zip->close();

    return $path;
}

/**
 * Ein Film-Objekt im Format der Trakt-API.
 *
 * @param  array<string, mixed>  $ids
 * @return array<string, mixed>
 */
function traktMovie(string $title, ?int $year, array $ids): array
{
    return ['title' => $title, 'year' => $year, 'ids' => $ids + ['slug' => Str::slug($title)]];
}
