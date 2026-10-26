<?php

namespace App\Models;

use Database\Factories\StreamingSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein aktives Streaming-Abo, identifiziert über die TMDB-Anbieter-ID.
 */
#[Table('streaming_subscriptions', key: 'provider_id', incrementing: false)]
#[Fillable(['provider_id', 'name', 'logo_path'])]
class StreamingSubscription extends Model
{
    /** @use HasFactory<StreamingSubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['provider_id' => 'integer'];
    }

    /**
     * @return list<int>
     */
    public static function providerIds(): array
    {
        return static::query()->pluck('provider_id')->map(fn ($id): int => (int) $id)->all();
    }
}
