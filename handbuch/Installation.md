# Installation

This page covers running Filmlog on your machine for development. For a server see [Deployment](Deployment.md).

## Requirements

- Docker with Docker Compose
- Node.js 22 or newer for the frontend build
- A free [TMDB](https://www.themoviedb.org/settings/api) API Read Access Token

PHP itself runs inside the containers (PHP 8.5, image `serversideup/php`). A GitHub Codespace works the same way; the repository contains a `.devcontainer`.

## First start

```sh
git clone https://github.com/roberteinsle/filmlog.git
cd filmlog
cp .env.example .env               # set TMDB_TOKEN and FILMLOG_ADMIN_EMAIL
docker compose up -d               # app (:8080), worker, scheduler, db
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm install
npm run dev                        # Vite with hot reload
```

Open <http://localhost:8080>. The compose stack contains:

| Service | Purpose |
|---|---|
| `app` | PHP-FPM and Nginx on port 8080 |
| `worker` | `php artisan queue:work` for imports, refreshes and syncs |
| `scheduler` | `php artisan schedule:work` for the nightly tasks |
| `db` | PostgreSQL 17; also creates the test database `filmlog_test` |

## Logging in

There is exactly one account: the address in `FILMLOG_ADMIN_EMAIL`. Request a login link on the login page. Locally `MAIL_MAILER=log` writes the mail to `storage/logs/laravel.log`; copy the link from there. The account is created on the first login. Afterwards add a passkey under *Settings → Sicherheit*. Passkeys need a secure context; `http://localhost` qualifies, other plain HTTP hosts do not.

## Tests

Tests run against PostgreSQL:

```sh
docker compose exec app php artisan test --compact
```

Tests never send real HTTP requests; external services (TMDB, Plex, InsideKino) are faked with fixtures in `tests/Fixtures`.

## Useful commands

```sh
docker compose exec app php artisan filmlog:refresh-movies       # queue TMDB refresh for stale and listed films
docker compose exec app php artisan filmlog:refresh-collections  # queue collection refresh
docker compose exec app php artisan filmlog:prune-imports        # delete old Trakt ZIPs
docker compose exec app vendor/bin/pint --dirty                  # code style
```
