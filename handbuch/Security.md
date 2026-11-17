# Security

Filmlog is a single user app. There are no passwords and no registration.

## Login methods

### Passkey (default)

The login page offers **Mit Passkey anmelden** first. Passkeys work with Face ID, Touch ID, Windows Hello or a password manager. Only the admin account (`FILMLOG_ADMIN_EMAIL`) may log in with a passkey.

Passkeys are bound to the host of `APP_URL` (the relying party). Filmlog derives it robustly: a missing `https://` is added and a trailing slash is ignored. If needed, set `PASSKEYS_RELYING_PARTY_ID` and `PASSKEYS_ALLOWED_ORIGINS` explicitly.

### Login link (fallback)

Enter the admin address and Filmlog mails a link.

- The link is valid for 15 minutes and works only once.
- Opening it shows a confirmation page; only the explicit confirmation logs in, so mail scanners that open links do not burn them.
- Other addresses receive nothing, but the page looks exactly the same, so the admin address cannot be guessed.
- Requests are throttled per address and IP.
- The account is created on the first successful login.

### Authenticator code (second factor for login links)

With an authenticator app set up under *Settings → Sicherheit*, a login link additionally asks for a six digit TOTP code or one of eight recovery codes.

- Codes cannot be replayed within their time step.
- Five wrong attempts lock the challenge for ten minutes; the challenge itself expires after ten minutes.
- Recovery codes are stored as SHA-256 hashes and work once.

## Session

A login lasts at most seven days, no matter how active you are. Afterwards a new login is required.

Changing passkeys or the authenticator app requires a fresh confirmation: a login within the last three hours (`AUTH_PASSWORD_TIMEOUT`) or a confirmation with an existing passkey.

## Webhook

The Plex webhook is the only route reachable without login. It is protected by the secret path segment `PLEX_WEBHOOK_SECRET`, answers 404 for wrong secrets, needs no CSRF token and is throttled to 60 requests per minute.

## Secrets

Secrets live only in environment variables of the server and in GitHub repository secrets: `APP_KEY`, database password, `TMDB_TOKEN`, mail credentials, Plex token and webhook secret, Coolify token and webhook. `.env` files are ignored by Git and Docker. The test suite blocks all outgoing HTTP requests.

## Reporting a vulnerability

Please open a private security advisory on GitHub (*Security → Report a vulnerability*) instead of a public issue.
