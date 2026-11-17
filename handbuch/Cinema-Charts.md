# Cinema charts

The **Kinocharts** page lists the most successful films in German cinemas for any year from 1970 until today, based on the yearly charts published by [InsideKino](https://www.insidekino.de/) (`https://www.insidekino.de/DJahr/D{year}.htm`).

Each row shows rank, poster, title (linked to the film page), your status, year, TMDB rating, visitors, Plex availability and your streaming subscriptions. The source page is linked at the bottom.

## How it works

1. Opening a year without data queues the job `ImportCinemaChart`.
2. The job downloads the page once, reads the chart table and stores the rows (`cinema_chart_entries`).
3. Each row is matched to TMDB: by IMDb id when the page contains one, otherwise by title search in the chart year, then the previous year (films often start in December), then without a year. Rows TMDB cannot match are shown as "bei TMDB nicht gefunden".
4. Matching runs in stages of 50 seconds; the page polls and fills up while you watch.

Past years are fetched only once. The current year is refreshed at most once a day. A year InsideKino does not have shows the reason.

## Parsing notes

The pages are hand edited HTML in Windows-1252 with columns that changed over the decades. The parser therefore relies on what is stable:

- A chart row starts with rank and visitor count.
- The title is the cell right after the start date.
- The real chart table is the table with the most chart rows; weekend and distributor tables are ignored.

Non ASCII characters are converted to HTML entities before parsing, so the result does not depend on how the installed libxml version interprets the page's charset declaration.
