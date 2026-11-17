# Plex

Filmlog talks to Plex in two ways. Both are optional and independent.

## Automatic diary entries (webhook)

When you finish a film in Plex, Plex sends a `media.scrobble` event. Plex fires it once playback passes roughly 90 % of the runtime; pausing, short previews or `media.play` events never create an entry. Filmlog turns the scrobble into a viewing with source **Plex** at the time Plex reports (`lastViewedAt`).

- Address: `https://<your-host>/webhooks/plex/<PLEX_WEBHOOK_SECRET>`. The secret path segment is the only protection, so choose a long random string. A wrong or missing secret answers 404.
- Webhooks need **Plex Pass**. Add the address in Plex under *Settings → Webhooks*. Settings in Filmlog show the complete address ready to copy.
- The film is matched by the `tmdb://` or `imdb://` GUID Plex sends (new and legacy agents). Unknown films are fetched from TMDB first.
- A second scrobble of the same film within six hours counts as a duplicate and is ignored.
- Ratings are not part of the scrobble. Rate the viewing afterwards on the film page or in the diary.
- A film watched through Plex is not removed from the watchlist automatically.

### Which accounts count

Every Plex account or Plex Home profile that sends a webhook is remembered and listed in *Settings → Plex*. Tick the accounts whose scrobbles should go into the diary. On first appearance only the account that owns the webhook (Plex marks it with `user: true`) is ticked; managed profiles of family members stay out until you tick them.

If a profile does not show up: start any film with that profile for a few seconds, then reload the settings.

## Library sync

Every night at 03:15, and on demand via **Jetzt abgleichen** in the settings or **Mit Plex abgleichen** on a film page, Filmlog reads all movie libraries of your Plex server (`PLEX_URL`, `PLEX_TOKEN`).

- Films found get `on_plex = true` and their Plex rating key; films no longer on Plex are reset.
- Films Filmlog did not know yet are created with title and year. Their details (age rating, runtime, genres, providers) are fetched by the queue shortly after, so filters may skip them for a few minutes.
- The film page then shows **Auf Plex vorhanden**, and "Heute Abend" can suggest unseen Plex films when you switch the source to the Plex library.

The library sync runs before the nightly TMDB refresh, so new Plex films get their details the same night.

## Configuration

| Variable | Purpose |
|---|---|
| `PLEX_WEBHOOK_SECRET` | Secret path segment of the webhook address |
| `PLEX_URL` | Base URL of your Plex server, reachable from the worker, for example `http://plex.lan:32400` |
| `PLEX_TOKEN` | An `X-Plex-Token` of the server owner |

Without `PLEX_URL` and `PLEX_TOKEN` the nightly sync is skipped and the sync buttons are hidden.
