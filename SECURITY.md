# Security policy

## Reporting a vulnerability

Please report security issues **privately** through GitHub: open the repository's *Security* tab and choose **Report a vulnerability** ([direct link](https://github.com/roberteinsle/filmlog/security/advisories/new)). Do not open a public issue.

Helpful details:

- what is affected (login, Plex webhook, import, …) and the version or commit
- steps to reproduce, or a proof of concept
- the impact you expect

You will get an answer as soon as possible. Fixes are released as a new version; the advisory is published once an update is available.

## Supported versions

Only the latest release and the `main` branch receive security fixes.

## Scope

Filmlog is self hosted. Issues in your own server setup (reverse proxy, database, mail account) are out of scope, but hints that make the defaults safer are welcome. See [Security](https://github.com/roberteinsle/filmlog/wiki/Security) in the handbook for how login and the webhook are protected.
