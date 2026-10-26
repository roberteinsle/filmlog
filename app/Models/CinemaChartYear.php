<?php

namespace App\Models;

use Database\Factories\CinemaChartYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kinocharts eines Jahres (Quelle: insidekino.de). Abgeschlossene Jahre werden einmal geladen,
 * das laufende Jahr höchstens einmal am Tag.
 */
#[Table('cinema_chart_years', key: 'year', incrementing: false)]
#[Fillable(['year', 'status', 'error', 'fetched_at'])]
class CinemaChartYear extends Model
{
    /** @use HasFactory<CinemaChartYearFactory> */
    use HasFactory;

    public const string PENDING = 'pending';

    public const string LOADING = 'loading';

    public const string DONE = 'done';

    public const string FAILED = 'failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => self::PENDING];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CinemaChartEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CinemaChartEntry::class, 'year')->orderBy('rank');
    }

    /**
     * Muss (neu) von insidekino.de geholt werden?
     */
    public function needsFetch(): bool
    {
        if ($this->fetched_at === null) {
            return true;
        }

        $isCurrentYear = $this->year >= (int) now(config('app.timezone'))->format('Y');

        return $isCurrentYear && $this->fetched_at->lt(now()->subDay());
    }
}
