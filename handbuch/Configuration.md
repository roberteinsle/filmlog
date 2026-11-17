# Configuration

All settings come from environment variables. Locally they live in `.env` (never committed), on the server in your hosting panel, for example Coolify. Secrets never belong in the repository.

## Required

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel encryption key, `php artisan key:generate --show` |
| `APP_URL` | Public URL including scheme, for example `https://filmlog.example.com`, without trailing slash. Passkeys are bound to its host. |
| `FILMLOG_ADMIN_EMAIL` | The only account. Login links are sent only to this address. Without it nobody can log in. |
| `TMDB_TOKEN` | TMDB API Read Access Token (v4) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection |
| `MAIL_*` | SMTP for login links, for example Amazon SES (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`) |

## Optional

| Variable | Default | Description |
|---|---|---|
| `APP_TIMEZONE` | `Europe/Berlin` | Time zone for dates, months and the schedule |
| `FILMLOG_LANGUAGE` | `de-DE` | TMDB language for titles and overviews |
| `FILMLOG_REGION` | `DE` | Region for age ratings and streaming offers |
| `PLEX_WEBHOOK_SECRET` | – | Secret path segment of the Plex webhook, see [Plex](Plex.md) |
| `PLEX_URL`, `PLEX_TOKEN` | – | Plex server for the library sync |
| `PASSKEYS_RELYING_PARTY_ID` | host of `APP_URL` | Override the passkey domain |
| `PASSKEYS_ALLOWED_ORIGINS` | origin of `APP_URL` | Comma separated list of allowed origins |
| `AUTH_PASSWORD_TIMEOUT` | `10800` | Seconds a login or passkey confirmation stays valid for security changes |
| `LOG_LEVEL` | `warning` in production | Log verbosity |

Queue, cache and sessions use the database (`QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` = `database`); no Redis is needed. Sessions last seven days (`SESSION_LIFETIME=10080`); independently of that, a login is valid for seven days at most.

## Application settings

`config/filmlog.php` holds settings that rarely change:

| Key | Default | Description |
|---|---|---|
| `login_link_minutes` | 15 | Lifetime of a login link |
| `login_lifetime_days` | 7 | Absolute lifetime of a login |
| `tmdb_min_votes` | 500 | Minimum TMDB votes for the TMDB rating filter and "Gegen den Strom" |
| `movie_cache_days` | 30 | Age after which the nightly job refreshes a film |
| `genres` | TMDB genres | German genre names by TMDB id |
| `moods` | six moods | Moods for "Heute Abend", each mapped to TMDB genre ids |

Example of adding a mood:

```php
'moods' => [
    // …
    'dokus' => ['label' => 'Wissen', 'genres' => [99]], // Documentary
],
```
