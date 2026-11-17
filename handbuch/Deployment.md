# Deployment

Filmlog ships as one Docker image that runs three roles: web app, queue worker and scheduler. PostgreSQL runs separately.

## Image

`Dockerfile` builds a production image based on `serversideup/php:8.5-fpm-nginx-alpine`:

1. Composer dependencies without dev packages and an authoritative class map
2. Frontend assets with Vite and Tailwind
3. Runtime with the `intl` extension; on start the container runs migrations and caches config, routes, events and views (`AUTORUN_ENABLED=true`)

The image listens on port 8080 and answers `/up` for health checks.

## GitHub Actions

`.github/workflows/build.yml`:

- **Pull requests**: Pint and the test suite against a PostgreSQL service. Fork PRs get no secrets.
- **Push to `main`**, **tags `v*`**, **weekly schedule**, **manual run**: tests, then build and push to the GitHub Container Registry (`ghcr.io/<owner>/filmlog`). A red test never produces a new `latest`.
- **Deploy**: with the repository variable `AUTO_DEPLOY=true`, a successful build triggers the Coolify deploy webhook (`POST`, secrets `COOLIFY_WEBHOOK` and `COOLIFY_TOKEN`).

The weekly build picks up the newest PHP patch release of the base image.

## Coolify

`deploy/docker-compose.coolify.yml` is pasted into a Coolify "Docker Compose Empty" resource. Coolify does not read it from the repository, so paste it again after it changes.

- Three services from the same image: `app` (with migrations), `worker` (`queue:work --tries=3 --timeout=120`) and `scheduler` (`schedule:work`)
- All three share the volume `app-storage` (uploads such as Trakt ZIPs are written by `app` and read by `worker`)
- PostgreSQL 17 is its own Coolify resource. The services join the external network `coolify` to reach it; set `DB_HOST` to its internal host name
- Set every `${…}` variable under *Environment Variables*, see [Configuration](Configuration.md)
- Point your domain at the `app` service, port 8080
- Coolify must be able to pull from GHCR: `docker login ghcr.io` on the server with a token that has `read:packages`

### Checklist for a new server

1. PostgreSQL resource with daily backups
2. Docker Compose resource with the compose file and all variables, including `APP_URL` with `https://` and `FILMLOG_ADMIN_EMAIL`
3. Domain and TLS in Coolify
4. Repository secrets `COOLIFY_WEBHOOK`, `COOLIFY_TOKEN` and variable `AUTO_DEPLOY=true`
5. First deploy, then log in with a login link and add a passkey

## Scheduled tasks

| Time | Task |
|---|---|
| 03:15 | Plex library sync (only when Plex is configured) |
| 03:30 | TMDB refresh for films on lists and films older than 30 days |
| 03:45 | Collections of seen films |
| 04:00 | Delete failed jobs older than 14 days |
| 04:15 | Delete Trakt ZIPs seven days after the import |

## Releases

Every push to `main` deploys. Versioned releases (`v*` tags) are created deliberately, not automatically.
