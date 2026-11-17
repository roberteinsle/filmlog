# Architecture

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.5 |
| Framework | Laravel 13 |
| UI | Livewire 4 with Blade, Alpine.js (bundled with Livewire), Tailwind CSS 4, built with Vite |
| Database | PostgreSQL 17 (JSONB with GIN indexes for genres and providers) |
| Queue, cache, sessions | Database drivers, no Redis |
| Auth | Laravel Passkeys, login links, TOTP (Google2FA) |
| Tests | Pest against PostgreSQL |
| Runtime | `serversideup/php` FPM and Nginx image, Coolify |

The UI is German and mobile first.

## Code structure

| Path | Content |
|---|---|
| `app/Livewire/` | One component per screen: `Tonight`, `Search`, `MovieShow`, `Lists`, `ListShow`, `CinemaCharts`, `Diary`, `Review`, `Settings`, `TraktImportPage`, `SecuritySettings`, `AddToList`, `Auth/*` |
| `app/Livewire/Forms/ViewingForm.php` | Shared viewing dialog for film page, diary and "Heute Abend" |
| `app/Actions/` | Domain operations: `SyncMovie`, `EnsureMovie`, `LogViewing`, `ImportTraktExport`, `SuggestForTonight` |
| `app/Jobs/` | `RefreshMovie`, `RefreshCollection`, `ImportTraktZip`, `LogPlexScrobble`, `SyncPlexLibrary`, `ImportCinemaChart` |
| `app/Services/` | `Tmdb/TmdbClient`, `Plex/PlexClient`, `Trakt/TraktExport`, `InsideKino/InsideKinoCharts`, `MovieStatusResolver`, `MovieFilter`, `ReviewStats`, `CollectionGaps`, login services |
| `app/Console/Commands/` | `filmlog:refresh-movies`, `filmlog:refresh-collections`, `filmlog:prune-imports` |
| `routes/console.php` | Schedule |
| `config/filmlog.php` | Application settings, genres and moods |

### How modules connect

Modules never reach into each other directly. External services are registered in the service container (`AppServiceProvider`), domain logic lives in actions, components talk through Livewire events (for example `list-membership-changed`), and background work goes through the queue and the scheduler. A new feature plugs into these registries.

## Data model

```
movies                 tmdb_id PK, title, original_title, year, runtime, fsk, poster_path,
                       vote_average, vote_count, genre_ids jsonb, directors jsonb,
                       provider_ids jsonb, collection_id, imdb_id, on_plex, plex_rating_key, cached_at
viewings               id, tmdb_id → movies, watched_at timestamptz, rating 1–10, note,
                       source (manual | trakt | plex), external_id   UNIQUE(source, external_id)
companions             id, name, max_fsk, sort
companion_viewing      viewing_id, companion_id
lists                  id, name, is_watchlist (exactly one), sort
list_items             list_id, tmdb_id, rank, added_at
collections            id (TMDB), name, parts jsonb, cached_at
streaming_subscriptions provider_id (TMDB), name, logo_path
plex_accounts          id (Plex account), title, counts_viewings, last_seen_at
trakt_imports          path, status, progress, report (stats, unresolved, files, errors)
cinema_chart_years     year, status, fetched_at
cinema_chart_entries   year, rank, title, visitors, imdb_id, tmdb_id → movies, resolved_at
users, passkeys, sessions, cache, jobs, failed_jobs
```

Derived values have no columns: a film's state (never seen, watchlist, seen with last date and latest rating) is computed by `MovieStatusResolver` with two queries for any number of films. The repeat count of a viewing ("2. Sichtung") comes from a window function.

### Time zones

`watched_at` is a `timestamptz`. Values are always written with their offset, and period boundaries are passed with offset, so dates near midnight land in the right month regardless of the database session time zone.

## Background work

| Job | Trigger |
|---|---|
| `RefreshMovie` | Nightly for listed and stale films, after a Plex sync for new films, when filtering by subscription |
| `RefreshCollection` | Nightly for collections of seen films, on demand in the review |
| `ImportTraktZip` | Upload; runs in 50 second stages and re-queues itself |
| `LogPlexScrobble` | Plex webhook |
| `SyncPlexLibrary` | Nightly, on demand in settings and on the film page |
| `ImportCinemaChart` | Opening a chart year; runs in 50 second stages |

Long running jobs work in stages so they stay below the worker timeout and the database queue's `retry_after` of 90 seconds.
