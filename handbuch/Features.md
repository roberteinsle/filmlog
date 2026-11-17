# Features

Filmlog has six main areas, reachable from the navigation (bottom bar on phones, top bar on larger screens): **Heute Abend**, **Suche**, **Listen**, **Kinocharts**, **Tagebuch** and **Rückblick**. Settings live behind the gear icon.

## Status at a glance

Every film carries one of three states. They appear as a coloured banner on the film page and as a coloured dot in search results, lists, the diary and the charts.

| Colour | State | Meaning |
|---|---|---|
| 🟢 green | Gesehen | You have seen it. The banner shows the last date and your latest rating. |
| 🟡 amber | Watchlist | Not seen yet, but on your watchlist. |
| 🔴 red | Nie gesehen | Never seen and not on the watchlist. |

"Seen" wins over "watchlist", so a film you already watched never looks like an open item.

On the film page the banner of a seen film can be expanded. It lists every viewing with date, rating, companions, note and source (manual, Trakt or Plex), and each viewing can be edited or deleted right there.

## Search (Suche)

Type at least two characters. Results come live from TMDB in German, each with its status dot. Every result has a **+** button to put the film on any list without opening it.

## Film page

- Poster, original title, tagline, runtime, German age rating (FSK), TMDB rating, genres, director and cast
- **Trailer auf YouTube**: German trailer if available, otherwise English
- **Für dich**: which of your streaming subscriptions carries the film and whether it is on your Plex server
- **Streamt bei**: all flat rate, free and ad supported offers in Germany; your subscriptions are highlighted
- **Mit Plex abgleichen**: syncs the Plex library right away instead of waiting for the night
- **Collection**: if the film belongs to a series (for example Jurassic Park), all parts with year and status, the current part highlighted
- **Gesehen / Wieder gesehen** opens the viewing dialog, **Zur Liste** manages list membership

### Viewing dialog

Date (defaults to today, never in the future), rating from 1 to 10 (optional), companions (multiple choice, none means alone) and a one line note of up to 280 characters. A viewing logged for today gets the current time; a past date is stored at 20:00 local time.

If the film is still on the watchlist after saving, Filmlog asks whether to remove it.

## Lists (Listen)

The watchlist always comes first, followed by your own lists. Own lists can be created, renamed, reordered by drag and drop and deleted. Deleting a list never deletes films or viewings.

Inside a list:

- Drag and drop changes the rank. The watchlist rank decides what "Heute Abend" suggests first.
- Combinable filters, kept in the URL: own rating ≥ X, TMDB rating ≥ X (only films with at least 500 votes), genre, decade, maximum runtime and maximum age rating. Genres and decades only offer values that occur in the list. While a filter is active, sorting is disabled.

## Tonight (Heute Abend)

The start page answers "what do we watch tonight?" with three suggestions.

1. **Woher?** Watchlist (default) or the Plex library
2. **Bei welchem Abo?** (watchlist only) limit to films on selected streaming subscriptions
3. **Mit wem?** alone or with companions; the strictest age limit of the chosen companions applies
4. **Wie viel Zeit?** under 100 minutes, under 130 minutes or any
5. **Worauf hast du Lust?** a mood that maps to TMDB genres (configurable)

Seen films are never suggested. Order follows the watchlist rank, then the TMDB rating, with a little randomness so the same three do not show up every evening. **Andere Vorschläge** hides the shown films and draws again; when everything has been shown it starts over. With an age or time limit set, films without a known rating or runtime are left out.

**Läuft gerade** logs a viewing for now, including the chosen companions, opens the rating dialog and afterwards offers to remove the film from the watchlist. All choices are kept in the URL, so a favourite combination can be bookmarked.

## Diary (Tagebuch)

All viewings, newest first, grouped by month in local time. Repeat viewings are marked ("2. Sichtung"), imports show their source. Viewings can be edited and deleted inline; older entries load on demand.

Filters at the top: all, first viewings, repeat viewings, or exactly the 2nd, 3rd … viewing. They only appear once there are repeat viewings and are kept in the URL.

## Cinema charts (Kinocharts)

See [Cinema charts](Cinema-Charts.md).

## Review (Rückblick)

Pick a year and optionally a month.

- Number of viewings, number of distinct films, hours watched and average rating
- Top genres and favourite directors (by count and by average rating from two rated films)
- **Gegen den Strom**: where your latest rating differs most from TMDB (films with at least 500 votes)
- **Lücken**: collections where you have seen some, but not all released parts, with the missing ones

Counting is per viewing: a film watched twice counts twice for viewings, hours, genres and directors, but once for films.

## Settings (Einstellungen)

- **Begleitungen**: companions with an optional maximum age rating, sortable by drag and drop
- **Streaming-Abos**: your subscriptions, chosen from the most important providers in Germany
- **Plex**: webhook address, which Plex accounts write to the diary, library status and manual sync, see [Plex](Plex.md)
- **Sicherheit**: passkeys and authenticator app, see [Security](Security.md)
- **Daten**: the [Trakt import](Trakt-Import.md)
