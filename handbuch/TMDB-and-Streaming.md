# TMDB and streaming

## Film data

All film data comes from [TMDB](https://www.themoviedb.org/) in German (`FILMLOG_LANGUAGE`, default `de-DE`) for the region Germany (`FILMLOG_REGION`, default `DE`). You need a free TMDB account and an API **Read Access Token** (v4), set as `TMDB_TOKEN`.

What is fetched in one request per film: details, German age rating (FSK, cinema release preferred), credits, watch providers and videos. Responses are cached for six hours, searches for ten minutes.

Filmlog stores only what filters, statistics and lists need in the `movies` table (title, year, runtime, FSK, poster, TMDB rating and votes, genres, directors, provider ids, collection, IMDb id). Overview, cast and offers are always shown live.

### Nightly refresh

At 03:30 every film on a list and every film whose cached data is older than 30 days (`movie_cache_days`) is refreshed, bypassing the HTTP cache. Films TMDB no longer knows keep their stored data.

### Trailers

The film page links the best YouTube trailer: German before English, trailer before teaser, official before unofficial.

### Collections

If a film belongs to a TMDB collection, the film page shows all parts. Collections are also loaded nightly at 03:45 for every seen film and power the **Lücken** section of the review. Parts without a release date or with a future date never count as missing.

## Streaming subscriptions

In *Settings → Streaming-Abos* pick the services you pay for. The list shows the 24 most important providers in Germany according to TMDB (cached for a day); your choices stay visible even when TMDB is unreachable.

Where they show up:

- Film page, **Für dich**: your subscriptions carrying the film, plus Plex
- **Streamt bei**: all offers, yours highlighted
- **Heute Abend**: badges on the suggestions, and with the watchlist as source a filter "Bei welchem Abo?"
- **Kinocharts**: badges per film

Provider data per film (`movies.provider_ids`) is refreshed whenever the film page is opened and nightly for all films on lists. When you filter by subscription and some watchlist films have no provider data yet, Filmlog loads it in the background and tells you how many are pending.

Streaming availability comes from JustWatch through TMDB. Filmlog shows flat rate, free and ad supported offers; rent and buy offers are linked on TMDB.

## Attribution

This product uses the TMDB API but is not endorsed or certified by TMDB.
