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

The project website <https://filmlog.einsle.app> is the overview of all features. It lives in `site/` as plain HTML, CSS and a little JavaScript without a build step, and `.github/workflows/pages.yml` deploys it to GitHub Pages whenever `site/` changes on `main`.

| File | Purpose |
|---|---|
| `index.html` | The page: hero, status, features, integrations, how it works, stack, privacy, handbook overview, FAQ |
| `404.html` | Branded not found page |
| `assets/` | Styles, script, favicon, social preview image (`og.png`) and the self hosted Instrument Sans font with its licence |
| `robots.txt`, `sitemap.xml` | Crawling rules and sitemap |
| `llms.txt` | Plain summary with links for AI assistants |
| `CNAME` | Custom domain `filmlog.einsle.app` |

Rules for changes:

- Always English, written as an overview; details belong in the handbook and are linked.
- Colours and typeface follow the app (stone, amber, the three status colours, Instrument Sans).
- No cookies, no analytics, nothing loaded from third parties.

### SEO and GEO checklist

When a feature is added or changed:

1. Update the matching section in `index.html` and, if it is a new area, the handbook overview.
2. Keep `<title>` (about 60 characters) and the meta description (about 155 characters) accurate.
3. Update the JSON-LD graph: `featureList` of the `SoftwareApplication`, and the `FAQPage` questions word for word as on the page.
4. Update `llms.txt` (key facts and links), so AI assistants describe Filmlog correctly.
5. Set `lastmod` in `sitemap.xml` to the date of the change.
6. Check the page at 390 px width: no horizontal scrolling.
