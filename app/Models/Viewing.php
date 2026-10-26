<?php

namespace App\Models;

use Database\Factories\ViewingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
     * watched_at ist timestamptz: immer mit Offset schreiben, sonst deutet Postgres
     * die Ortszeit in der Zeitzone der Session (UTC) und die Sichtung verrutscht.
     *
     * @return Attribute<never, mixed>
     */
    protected function watchedAt(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => $value === null ? null : $this->asDateTime($value)->format('Y-m-d H:i:sP'));
    }

    /**
     * @return BelongsTo<Movie, $this>
     */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'tmdb_id');
    }

    /**
     * Mit wem der Film lief; keine Begleitung = allein.
     *
     * @return BelongsToMany<Companion, $this>
     */
    public function companions(): BelongsToMany
    {
        return $this->belongsToMany(Companion::class)->ordered();
    }
}
