<?php

namespace App\Models;

use Database\Factories\CinemaChartEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zeile der Kinocharts: Rang, Titel laut insidekino.de, Besucher und – wenn zugeordnet – der TMDB-Film.
 */
#[Fillable(['year', 'rank', 'title', 'subtitle', 'visitors', 'imdb_id', 'tmdb_id', 'resolved_at'])]
class CinemaChartEntry extends Model
{
    /** @use HasFactory<CinemaChartEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'rank' => 'integer',
            'visitors' => 'integer',
            'tmdb_id' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Movie, $this>
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'tmdb_id');
    }
}
