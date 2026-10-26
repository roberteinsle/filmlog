<?php

namespace App\Models;

use Database\Factories\CompanionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Begleitung für „Mit wem“ (Jasmin, Familie, …). max_fsk = höchste passende Altersfreigabe.
 */
#[Fillable(['name', 'max_fsk', 'sort'])]
class Companion extends Model
{
    /** @use HasFactory<CompanionFactory> */
    use HasFactory;

    /**
     * Die Altersfreigaben, die die FSK vergibt.
     */
    public const array FSK_LEVELS = [0, 6, 12, 16, 18];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_fsk' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    /**
     * @return BelongsToMany<Viewing, $this>
     */
    public function viewings(): BelongsToMany
    {
        return $this->belongsToMany(Viewing::class);
    }
}
