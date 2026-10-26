<?php

namespace App\Models;

use Database\Factories\PlexAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Plex-Konto oder Plex-Home-Profil, das Webhooks auslöst. Die ID stammt von Plex (Account.id).
 */
#[Table('plex_accounts', incrementing: false)]
#[Fillable(['id', 'title', 'counts_viewings', 'last_seen_at'])]
class PlexAccount extends Model
{
    /** @use HasFactory<PlexAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'counts_viewings' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Merkt sich das Konto aus einem Webhook. Beim ersten Auftauchen zählt es nur,
     * wenn es das Konto ist, dem der Webhook gehört (Plex: user=true).
     */
    public static function remember(int $id, string $title, bool $isWebhookOwner): self
    {
        $account = static::query()->firstOrNew(['id' => $id], ['counts_viewings' => $isWebhookOwner]);
        $account->fill(['title' => $title, 'last_seen_at' => now()])->save();

        return $account;
    }
}
