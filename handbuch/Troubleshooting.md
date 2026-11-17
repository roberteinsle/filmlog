# Troubleshooting

## "Der Passkey konnte nicht gespeichert werden."

The message now includes the server's reason in brackets.

- **HTTP 500** on any passkey action: `APP_URL` is probably wrong. Use the full URL with `https://` and without trailing slash. Current versions also accept a missing scheme, but other parts of Laravel still rely on a correct `APP_URL`.
- **Origin errors**: the browser address must match `APP_URL`. If you serve Filmlog on several hosts, set `PASSKEYS_ALLOWED_ORIGINS`.
- **HTTP 423 or confirmation required**: confirm with an existing passkey or log in again; changes need a confirmation from the last three hours.
- Plain HTTP only works on `localhost`.

## Nobody can log in

`FILMLOG_ADMIN_EMAIL` is empty or missing. Without it, no login link is sent and no passkey is accepted. Set it on the server and redeploy.

## Login link never arrives

Check the mail settings (`MAIL_*`). Locally the mail is in `storage/logs/laravel.log`. Requests are throttled; wait a few minutes after several attempts.

## Plex scrobbles do not show up

- Webhooks need Plex Pass, and the address must contain the exact `PLEX_WEBHOOK_SECRET`.
- Only finished films count (about 90 % watched).
- The account or profile must be ticked in *Settings → Plex*.
- Check that the worker runs; scrobbles are processed by the queue.

## Plex sync says "nicht erreichbar"

`PLEX_URL` must be reachable from the server's containers, not just from your browser.

## Filters skip new films

Films added by the Plex sync or the charts get their details from TMDB through the queue. Wait a few minutes or check the worker.

## Dates land in the wrong month

All periods use `APP_TIMEZONE`. Make sure it is set identically for app, worker and scheduler.

## Trakt numbers look wrong

Look at the **Gelesen** line of the import report: it lists every file the importer read with its number of film entries. Unknown file names appear under **Nicht erkannte Dateien**.
