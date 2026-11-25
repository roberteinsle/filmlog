# Filmlog handbook

Filmlog is a self-hosted film diary for a single person. It remembers what you watched, how you rated it and what is next on your watchlist, and it answers the one question before every movie night: *have I seen this already?*

The app itself is in German. This handbook explains every screen, every integration and how to run Filmlog on your own server.

## Using Filmlog

- [Features](Features.md): every screen explained, from the status banner to the yearly review
- [Plex](Plex.md): automatic diary entries via webhook and the nightly library sync
- [Trakt import](Trakt-Import.md): bring your viewing history, ratings, watchlist and lists
- [TMDB and streaming](TMDB-and-Streaming.md): film data, trailers, collections and your streaming subscriptions
- [Cinema charts](Cinema-Charts.md): the German box office charts per year

## Running Filmlog

- [Installation](Installation.md): local development with Docker Compose or Codespaces
- [Configuration](Configuration.md): every environment variable and setting
- [Deployment](Deployment.md): Docker image, GitHub Actions and Coolify
- [Security](Security.md): passkeys, login links and authenticator codes
- [Troubleshooting](Troubleshooting.md): known pitfalls and how to fix them

## Contributing

Filmlog is free software under the [GNU AGPL-3.0](https://github.com/roberteinsle/filmlog/blob/main/LICENSE).

- [Architecture](Architecture.md): stack, data model, jobs and schedule
- [Development](Development.md): conventions, tests and how this handbook becomes the wiki
