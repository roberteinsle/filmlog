# Trakt import

A one time upload brings your Trakt history into Filmlog. You can import the same export again at any time; nothing is created twice.

## How to

1. Export your data from Trakt (the official export or a backup tool that writes Trakt API JSON files).
2. In Filmlog open *Einstellungen → Daten → Trakt-Import öffnen* and upload the ZIP (up to 50 MB).
3. The import runs in the background. The page shows the progress and, when finished, a report.

## What is imported

| Trakt file | Becomes |
|---|---|
| `watched-history.json`, `watched-history-N.json`, `history*.json` | Viewings with date, source **Trakt**, keyed by the Trakt history id |
| `ratings-movies.json`, `ratings-movies-N.json`, `ratings.json` | Rating of the latest viewing of that film |
| `watchlist.json`, `lists-watchlist.json` | Watchlist, in Trakt order |
| `lists-lists.json` + `lists-{slug}.json` or `lists-list-{id}-{slug}.json` | Own lists with their Trakt names, in Trakt order |

Folders inside the ZIP do not matter. Shows, seasons and episodes are skipped and counted in the report.

### Rules

- **Ratings never overwrite** a rating you set in Filmlog. They go to the latest viewing only if it has no rating yet.
- A **rated film without history** gets a viewing on the rating date, because in Filmlog every rating belongs to a viewing.
- Films without a TMDB id are matched through their IMDb id. If TMDB no longer knows a TMDB id, the IMDb id is tried as well.
- Lists named after their file by older imports ("List 25150071 …") are renamed to their Trakt name instead of being duplicated. Empty leftovers such as "Collaborations" or "Favorites" are removed, lists with films never.
- `lists-collaborations.json` (lists of other users) creates no list. Empty Trakt lists are kept if they exist in Trakt.

## The report

- Counts: new and existing viewings, ratings applied, watchlist entries, lists created or renamed, list entries, skipped shows
- **Nicht zuordenbar**: films that could not be matched, with the reason and their ids
- **Gelesen**: every file read with its number of film entries, useful when numbers look wrong
- **Der Trakt-Export meldet … Fehler**: the contents of `_errors.json` written by some export tools
- **Nicht erkannte Dateien**: files the importer does not know

## Large exports

Matching thousands of films against TMDB takes a while. The job works in stages of 50 seconds, stores its progress and queues the next stage itself, so it stays below the worker timeout. A TMDB outage is retried; a broken ZIP fails right away with a message.

Uploaded ZIPs are deleted seven days after the import finished; the report stays.
