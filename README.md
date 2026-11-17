# Filmlog

**A self-hosted film diary for one person.** Filmlog remembers what you watched, how you rated it and what is next on your watchlist, and it answers the question before every movie night: *have I seen this already?*

[Website](https://filmlog.einsle.app) · [Handbook (wiki)](https://github.com/roberteinsle/filmlog/wiki) · The app's interface is in German.

## Features

- **Status everywhere**: green for seen, amber for watchlist, red for never seen, in search, lists, diary and charts
- **Viewings with ratings**: date, 1 to 10, companions and a note; repeat viewings are counted
- **Watchlist and lists** with drag and drop ranking and combinable filters
- **Tonight**: three suggestions from your watchlist or Plex library, by companion age limits, time, mood and streaming subscription
- **Plex**: finished films land in the diary automatically; the library sync marks what is on your server
- **Trakt import** of history, ratings, watchlist and lists, safe to repeat
- **Film pages** with trailer, collection, streaming offers in Germany and your subscriptions
- **Review** of a year or month, plus the German box office charts since 1970
- **Passkey login**, login links as fallback, optional authenticator codes

## Stack

PHP 8.5 · Laravel 13 · Livewire 4 · Tailwind CSS 4 · PostgreSQL 17 · Docker. Film data from [TMDB](https://www.themoviedb.org/).

## Quick start

Requirements: Docker with Compose, Node.js 22+, a free [TMDB API Read Access Token](https://www.themoviedb.org/settings/api).

```sh
git clone https://github.com/roberteinsle/filmlog.git && cd filmlog
cp .env.example .env              # set TMDB_TOKEN and FILMLOG_ADMIN_EMAIL
docker compose up -d              # app on :8080, worker, scheduler, PostgreSQL
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install && npm run dev
```

Open <http://localhost:8080>, request a login link for `FILMLOG_ADMIN_EMAIL` and copy it from `storage/logs/laravel.log`. Then add a passkey under *Einstellungen → Sicherheit*.

Run the tests:

```sh
docker compose exec app php artisan test --compact
```

## Configuration

| Variable | Required | Purpose |
|---|---|---|
| `APP_URL` | yes | Public URL with `https://`, passkeys are bound to its host |
| `APP_KEY` | yes | `php artisan key:generate --show` |
| `FILMLOG_ADMIN_EMAIL` | yes | The only account; without it nobody can log in |
| `TMDB_TOKEN` | yes | TMDB API Read Access Token |
| `DB_*` | yes | PostgreSQL connection |
| `MAIL_*` | yes | SMTP for login links |
| `PLEX_WEBHOOK_SECRET`, `PLEX_URL`, `PLEX_TOKEN` | no | Plex webhook and library sync |

All variables are described in [Configuration](https://github.com/roberteinsle/filmlog/wiki/Configuration).

## Deployment

One Docker image runs the web app, the queue worker and the scheduler. GitHub Actions tests every push, builds the image to GHCR and can trigger a Coolify deploy. See [Deployment](https://github.com/roberteinsle/filmlog/wiki/Deployment).

## Documentation

The wiki is generated from [`handbuch/`](handbuch) with `python3 scripts/build-wiki.py`. Edit the handbook, never the wiki. Contributor rules are in [CLAUDE.md](CLAUDE.md).

---

This product uses the TMDB API but is not endorsed or certified by TMDB.
