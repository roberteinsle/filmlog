<?php

namespace App\Http\Controllers;

use App\Jobs\LogPlexScrobble;
use App\Services\Plex\PlexGuids;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Empfängt Plex-Webhooks (multipart, Feld „payload“ mit JSON). Nur „media.scrobble“ für Filme
 * des Webhook-Kontos selbst wird zur Sichtung; alles andere wird quittiert und ignoriert.
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

        // „user“ ist false, wenn jemand anderes auf deinem Server schaut.
        if (($payload['event'] ?? null) !== 'media.scrobble' || ($metadata['type'] ?? null) !== 'movie' || ($payload['user'] ?? true) !== true) {
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
