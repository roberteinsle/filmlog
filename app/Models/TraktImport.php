<?php

namespace App\Models;

use Database\Factories\TraktImportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Trakt-Import: hochgeladenes ZIP, Fortschritt und Bericht (Zahlen, nicht zuordenbare Filme, ignorierte Dateien).
 *
 * @property array<string, int|null> $resolved Film-Schlüssel (tmdb:…, imdb:…, trakt:…) → TMDB-ID oder null
 * @property array<string, int> $stats
 * @property list<array{key: string, title: string, year: ?int, ids: string, reason: string}> $unresolved
 * @property list<string> $ignored_files
 */
#[Fillable(['original_name', 'path', 'status', 'total_movies', 'resolved', 'stats', 'unresolved', 'ignored_files', 'error', 'started_at', 'finished_at'])]
class TraktImport extends Model
{
    /** @use HasFactory<TraktImportFactory> */
    use HasFactory;

    public const string PENDING = 'pending';

    public const string RUNNING = 'running';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    /**
     * Gleiche Defaults wie in der Migration, damit ein frisch angelegter Import sofort nutzbar ist.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
        'total_movies' => 0,
        'resolved' => '{}',
        'stats' => '{}',
        'unresolved' => '[]',
        'ignored_files' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_movies' => 'integer',
            'resolved' => 'array',
            'stats' => 'array',
            'unresolved' => 'array',
            'ignored_files' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::DONE, self::FAILED], true);
    }

    public function resolvedCount(): int
    {
        return count($this->resolved);
    }

    /**
     * Fortschritt der TMDB-Zuordnung in Prozent (0–100).
     */
    public function progress(): int
    {
        if ($this->status === self::DONE) {
            return 100;
        }

        return $this->total_movies > 0 ? (int) floor($this->resolvedCount() / $this->total_movies * 100) : 0;
    }
}
