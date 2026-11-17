# Development

## Conventions

- Follow the existing code: class based Livewire components in `app/Livewire` with views in `resources/views/livewire`, actions for domain logic, PHPDoc instead of inline comments, explicit types.
- New features connect through the existing registries (service container, actions, Livewire events, queue, scheduler, config), never through direct access between modules. See [Architecture](Architecture.md).
- UI text is German, code and identifiers are English.
- No personal data in the repository: not in code, docs, the handbook, the wiki, commits or issues. Use neutral examples (`admin@example.com`, `filmlog.example.com`).
- Run `vendor/bin/pint --dirty` before committing.

## Tests

```sh
docker compose exec app php artisan test --compact
docker compose exec app php artisan test --compact --filter=Tonight
```

- Feature tests with Pest against PostgreSQL; factories for all models
- No real HTTP: `Http::preventStrayRequests()` is active for every test, external answers come from `tests/Fixtures` (TMDB, InsideKino)
- Every change ships with tests; CI runs Pint and the full suite on every push and pull request

## Documentation

| Where | What |
|---|---|
| `handbuch/*.md` | All details. Source of the wiki. |
| `README.md` | Short overview and quick start; only changes with a new module or a changed quick start |
| `CLAUDE.md` | Rules and workflows for contributors and coding agents |
| `site/` | Project website on GitHub Pages |

### Building the wiki

The GitHub wiki is generated, never edited by hand:

```sh
python3 scripts/build-wiki.py           # writes the wiki pages to build/wiki
python3 scripts/build-wiki.py --push    # builds and pushes to filmlog.wiki.git
```

The script copies every `handbuch/*.md`, turns links like `Plex.md` into wiki links, and generates `_Sidebar.md` and `_Footer.md`. The order of the sidebar follows the page list in `Home.md`.

## Website

`site/` is plain HTML and CSS without a build step, deployed by `.github/workflows/pages.yml` whenever `site/` changes on `main`.
