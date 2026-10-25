<?php

namespace App\Models;

use Database\Factories\ViewingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Sichtung eines Films; die Bewertung (1–10) gehört zur Sichtung.
 */
#[Fillable(['tmdb_id', 'watched_at', 'rating', 'note', 'source', 'external_id'])]
class Viewing extends Model
{
    /** @use HasFactory<ViewingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'watched_at' => 'datetime',
            'rating' => 'integer',
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
