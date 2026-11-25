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
| `README.md` | Overview, quick start and links; only changes with a new module or a changed quick start |
| `SECURITY.md` | How to report vulnerabilities |
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
| `index.html` | The page: hero, status, features, integrations, install, open source and stack, privacy, handbook overview, FAQ |
| `404.html` | Branded not found page |
| `assets/` | Styles, script, favicon, social preview image (`og.png`) and the self hosted Instrument Sans font with its licence |
| `robots.txt`, `sitemap.xml` | Crawling rules and sitemap |
| `llms.txt` | Plain summary with links for AI assistants |
| `<key>.txt` | IndexNow key file (the key is public by design) |
| `.well-known/security.txt` | How to report vulnerabilities (RFC 9116); renew `Expires` once a year |
| `CNAME` | Custom domain `filmlog.einsle.app` |
| `assets/logo.svg`, `assets/social-preview.png` | Logo and the 1280×640 social preview, also used in the README and as the repository's social preview |

Rules for changes:

- Always English, written as an overview; details belong in the handbook and are linked.
- Colours and typeface follow the app (stone, amber, the three status colours, Instrument Sans).
- No cookies, no analytics, nothing loaded from third parties.

### IndexNow

After every successful deploy the workflow reports the public addresses to [IndexNow](https://www.indexnow.org/) (`api.indexnow.org`), which forwards them to Bing, Yandex, Seznam, Naver and Yep. Google does not take part; it reads the sitemap.

- The key is set as `INDEXNOW_KEY` in `.github/workflows/pages.yml` and published as `site/<key>.txt`. Both must match; the key is not a secret.
- The addresses come from `INDEXNOW_PATHS` in the same workflow. Add new public pages there.
- The step waits until the key file is live, accepts HTTP 200 and 202, and never fails the deploy. Its log shows the answer.
- Bing Webmaster Tools list received submissions under *IndexNow*.

To change the key, create a new random key (8 to 128 characters: letters, digits, hyphens), replace the key file and the variable in one commit.

### SEO and GEO checklist

When a feature is added or changed:

1. Update the matching section in `index.html` and, if it is a new area, the handbook overview.
2. Keep `<title>` (about 60 characters) and the meta description (about 155 characters) accurate.
3. Update the JSON-LD graph: `featureList` of the `SoftwareApplication`, and the `FAQPage` questions word for word as on the page.
4. Update `llms.txt` (key facts and links), so AI assistants describe Filmlog correctly.
5. Set `lastmod` in `sitemap.xml` to the date of the change, and add new public pages to `sitemap.xml` and `INDEXNOW_PATHS`.
6. Check the page at 390 px width: no horizontal scrolling.
