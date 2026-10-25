# Filmlog

Merkt sich, was du gesehen hast, und schlägt vor, was du als Nächstes schaust.
Plan und Architektur: [docs/PROJEKTPLAN.md](docs/PROJEKTPLAN.md).

**Stack:** PHP 8.5 · Laravel 13 · Livewire 4 · Tailwind 4 · PostgreSQL 17 · Image `ghcr.io/roberteinsle/filmlog`

## Lokale Entwicklung (Codespaces)

```sh
cp .env.example .env            # TMDB_TOKEN eintragen – .env wird nie committet
docker compose up -d            # app (:8080), worker, scheduler, db
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
npm run dev                     # Vite mit Hot Reload
```

Tests laufen gegen Postgres (Datenbank `filmlog_test`, wird beim ersten Start von `db` angelegt):

```sh
docker compose exec app php artisan test --compact
```

## Deploy

Push auf `main` → GitHub Action testet, baut und pusht nach GHCR → Coolify-Deploy per Webhook (`AUTO_DEPLOY=true`).
Das Coolify-Compose liegt in [deploy/docker-compose.coolify.yml](deploy/docker-compose.coolify.yml); alle Secrets nur in Coolify bzw. GitHub Secrets.

Login per Magic Link an `FILMLOG_ADMIN_EMAIL` (lokal landet die Mail in `storage/logs/laravel.log`).
