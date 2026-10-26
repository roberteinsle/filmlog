<?php

namespace App\Services\InsideKino;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Liest die deutschen Jahres-Kinocharts von insidekino.de (https://www.insidekino.de/DJahr/D{Jahr}.htm).
 *
 * Die Seiten sind über Jahrzehnte gewachsenes FrontPage-HTML (Windows-1252) mit wechselnden Spalten.
 * Verlässlich ist: Eine Filmzeile beginnt mit Rang und Besucherzahl, und der Titel steht direkt hinter
 * dem Startdatum. Die eigentliche Chart-Tabelle ist die mit den meisten solcher Zeilen.
 */
class InsideKinoCharts
{
    public const int FIRST_YEAR = 1970;

    private const string VISITORS_PATTERN = '/\d{1,3}(?:\.\d{3})+/';

    private const string LINE_BREAK = "\u{2028}";

    private const string DATE_PATTERN = '/^\d{1,2}\.\d{1,2}\.(\d{2,4})?/';

    public static function url(int $year): string
    {
        return "https://www.insidekino.de/DJahr/D{$year}.htm";
    }

    /**
     * @return list<array{rank: int, title: string, subtitle: ?string, visitors: ?int, imdb_id: ?string}>
     */
    public function fetch(int $year): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Filmlog (privates Filmtagebuch)'])->timeout(20)->retry([1000, 3000], throw: false)->get(self::url($year));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('insidekino.de ist nicht erreichbar.', previous: $exception);
        }

        if ($response->notFound()) {
            throw new ChartNotFoundException("Für {$year} gibt es bei InsideKino keine Jahrescharts.");
        }

        if ($response->failed()) {
            throw new RuntimeException("insidekino.de antwortet mit HTTP {$response->status()}.");
        }

        return $this->parse(mb_convert_encoding($response->body(), 'UTF-8', 'Windows-1252'));
    }

    /**
     * @return list<array{rank: int, title: string, subtitle: ?string, visitors: ?int, imdb_id: ?string}>
     */
    public function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $rowsByTable = [];

        foreach ((new DOMXPath($document))->query('//tr') as $row) {
            $entry = $this->parseRow($row);
            $table = $this->closestTable($row);

            if ($entry !== null && $table !== null) {
                $rowsByTable[spl_object_id($table)][] = $entry;
            }
        }

        if ($rowsByTable === []) {
            return [];
        }

        usort($rowsByTable, fn (array $a, array $b): int => count($b) <=> count($a));

        return collect($rowsByTable[0])->unique('rank')->sortBy('rank')->values()->all();
    }

    /**
     * @return array{rank: int, title: string, subtitle: ?string, visitors: ?int, imdb_id: ?string}|null
     */
    private function parseRow(DOMElement $row): ?array
    {
        $cells = [];

        foreach ($row->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'], true)) {
                $cells[] = $this->lines($child);
            }
        }

        if (count($cells) < 6 || ! preg_match('/^(\d{1,3})\.?$/', $cells[0][0] ?? '', $rank)) {
            return null;
        }

        if (! preg_match(self::VISITORS_PATTERN, implode(' ', $cells[1]), $visitors)) {
            return null;
        }

        $titleLines = null;

        foreach (array_slice($cells, 2, null, true) as $index => $lines) {
            if (preg_match(self::DATE_PATTERN, $lines[0] ?? '') && isset($cells[$index + 1][0])) {
                $titleLines = $cells[$index + 1];

                break;
            }
        }

        if ($titleLines === null || ! preg_match('/\p{L}/u', $titleLines[0])) {
            return null;
        }

        preg_match('/\b(tt\d{5,10})\b/', $row->ownerDocument->saveHTML($row), $imdb);

        return [
            'rank' => (int) $rank[1],
            'title' => $this->cleanTitle($titleLines[0]),
            'subtitle' => isset($titleLines[1]) ? Str::limit($titleLines[1], 250, '') : null,
            'visitors' => (int) str_replace('.', '', $visitors[0]),
            'imdb_id' => $imdb[1] ?? null,
        ];
    }

    /**
     * Sichtbarer Text einer Zelle, aufgeteilt an <br> und Absätzen.
     *
     * @return list<string>
     */
    private function lines(DOMNode $cell): array
    {
        $text = '';
        $walk = function (DOMNode $node) use (&$walk, &$text): void {
            foreach ($node->childNodes as $child) {
                $name = strtolower($child->nodeName);

                if (in_array($name, ['script', 'style'], true)) {
                    continue;
                }

                // Zeilenumbrüche im Quelltext sind nur Leerraum; Zeilen trennen <br> und Absätze.
                if ($name === 'br') {
                    $text .= self::LINE_BREAK;
                } elseif ($child->nodeType === XML_TEXT_NODE) {
                    $text .= str_replace(["\r", "\n"], ' ', $child->textContent);
                } else {
                    $walk($child);

                    if (in_array($name, ['p', 'div'], true)) {
                        $text .= self::LINE_BREAK;
                    }
                }
            }
        };
        $walk($cell);

        return array_values(array_filter(array_map(
            fn (string $line): string => trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $line))),
            explode(self::LINE_BREAK, $text),
        ), fn (string $line): bool => $line !== ''));
    }

    private function cleanTitle(string $title): string
    {
        $title = trim(preg_replace('/\s*\(?3D\)?$/u', '', $title));

        // Manche Jahrgänge schreiben Titel in Versalien („JERRY COTTON“)
        if (mb_strlen($title) > 3 && mb_strtoupper($title) === $title) {
            $title = mb_convert_case(mb_strtolower($title), MB_CASE_TITLE);
        }

        return Str::limit($title, 250, '');
    }

    private function closestTable(DOMNode $node): ?DOMNode
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if (strtolower($parent->nodeName) === 'table') {
                return $parent;
            }
        }

        return null;
    }
}
