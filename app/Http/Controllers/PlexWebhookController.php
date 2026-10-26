<?php

namespace App\Http\Controllers;

use App\Jobs\LogPlexScrobble;
use App\Models\PlexAccount;
use App\Services\Plex\PlexGuids;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Empfängt Plex-Webhooks (multipart, Feld „payload“ mit JSON). Jedes auslösende Plex-Konto wird gemerkt;
 * „media.scrobble“ eines Films wird zur Sichtung, wenn das Konto in den Einstellungen zählt
 * (Standard: nur das Konto, dem der Webhook gehört). Alles andere wird quittiert und ignoriert.
 */
class PlexWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret): Response
    {
        $expected = (string) config('services.plex.webhook_secret');

        abort_if($expected === '' || ! hash_equals($expected, $secret), 404);

        $payload = json_decode((string) $request->input('payload'), true);

        if (! is_array($payload)) {
            return response('', 400);
        }

        $metadata = is_array($payload['Metadata'] ?? null) ? $payload['Metadata'] : [];

        // „user“ ist false, wenn ein anderes Konto oder ein verwaltetes Plex-Home-Profil schaut.
        $isWebhookOwner = ($payload['user'] ?? true) === true;
        $account = is_numeric($payload['Account']['id'] ?? null)
            ? PlexAccount::remember((int) $payload['Account']['id'], mb_substr((string) ($payload['Account']['title'] ?? 'Unbekannt'), 0, 100), $isWebhookOwner)
            : null;

        if (($payload['event'] ?? null) !== 'media.scrobble' || ($metadata['type'] ?? null) !== 'movie' || ! ($account?->counts_viewings ?? $isWebhookOwner)) {
            return response('', 204);
        }

        $guids = PlexGuids::from($metadata);

        LogPlexScrobble::dispatch(
            $guids['tmdb'],
            $guids['imdb'],
            mb_substr((string) ($metadata['title'] ?? ''), 0, 200),
            (string) ($metadata['ratingKey'] ?? ''),
            is_numeric($metadata['lastViewedAt'] ?? null) ? (int) $metadata['lastViewedAt'] : now()->getTimestamp(),
        );

        return response('', 202);
    }
}
