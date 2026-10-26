<?php

use App\Services\InsideKino\ChartNotFoundException;
use App\Services\InsideKino\InsideKinoCharts;
use Illuminate\Support\Facades\Http;

function insideKinoFixture(string $name): string
{
    return file_get_contents(__DIR__."/../Fixtures/insidekino/{$name}.htm");
}

test('the current page format is parsed from the main chart table', function () {
    Http::fake(['www.insidekino.de/DJahr/D2025.htm' => Http::response(insideKinoFixture('D2025'))]);

    expect(app(InsideKinoCharts::class)->fetch(2025))->toBe([
        ['rank' => 1, 'title' => 'Avatar - Fire and Ash', 'subtitle' => 'Avatar: Fire and Ash (USA)', 'visitors' => 5503170, 'imdb_id' => 'tt1757678'],
        ['rank' => 2, 'title' => 'Das Kanu des Manitu', 'subtitle' => '(D)', 'visitors' => 5090142, 'imdb_id' => null],
        ['rank' => 3, 'title' => 'Männer im Schnee', 'subtitle' => null, 'visitors' => 383232, 'imdb_id' => null],
        ['rank' => 4, 'title' => 'Jerry Cotton', 'subtitle' => null, 'visitors' => 152155, 'imdb_id' => null],
    ]);
});

test('older page formats are parsed too', function () {
    Http::fake(['www.insidekino.de/DJahr/D2010.htm' => Http::response(insideKinoFixture('D2010'))]);

    expect(app(InsideKinoCharts::class)->fetch(2010))->toBe([
        ['rank' => 1, 'title' => 'Harry Potter und die Heiligtümer des Todes Teil 1', 'subtitle' => 'David Yates', 'visitors' => 5835503, 'imdb_id' => null],
        ['rank' => 2, 'title' => 'Asterix und Kleopatra', 'subtitle' => null, 'visitors' => 3569000, 'imdb_id' => null],
    ]);
});

test('a year without charts is reported', function () {
    Http::fake(['www.insidekino.de/*' => Http::response('', 404)]);

    app(InsideKinoCharts::class)->fetch(2099);
})->throws(ChartNotFoundException::class, 'Für 2099 gibt es bei InsideKino keine Jahrescharts.');

test('the source url follows the year', function () {
    expect(InsideKinoCharts::url(2025))->toBe('https://www.insidekino.de/DJahr/D2025.htm');
});
