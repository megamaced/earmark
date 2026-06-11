# Earmark

A self-hosted **scrobble store** for [Nextcloud](https://nextcloud.com). Record what you listen to — from Last.fm-compatible scrobble clients, your imported Last.fm history, or (optionally) Spotify — resolve it to MusicBrainz, and browse your listening stats, all on a server you control.

> **100 % AI-written.** Every line of source, every test, every CI workflow and this README was written by [Claude Code](https://www.anthropic.com/claude-code) under direction from a human reviewer.

Sibling to [Crate](https://github.com/megamaced/crate): *Crate catalogues what you **own**; Earmark records what you **play**.*

## Why it's lightweight

Earmark rides entirely on Nextcloud's runtime, database, auth, cron and UI. There is no TimescaleDB, no Spark and no message broker — the marginal cost over an already-running Nextcloud is roughly one database table plus a background job. That low footprint is the whole point.

## Planned features

- **Inbound scrobble API** — Last.fm-compatible (AudioScrobbler 1.2 + Last.fm 2.0) and ListenBrainz-compatible submission endpoints, so existing scrobble clients can point at your Nextcloud
- **Last.fm full-history import** — pulls your entire scrobble history, resumably
- **Spotify import** (optional) — polls recently-played
- **MusicBrainz resolution** — every listen resolved to recording / artist / release MBIDs
- **Stats** — top artists / albums / tracks, listening clock, calendar heatmap, recent feed
- **Android companion** (planned) — view stats offline-first, optionally scrobble from the phone

## Status

Early scaffold (v0.1.0). See the project Wiki for the requirements, task list, API spec and Android plan.

## Development

```sh
composer install          # PHP dependencies (dev)
npm install               # JS dependencies
npm run build             # build the front-end bundle into js/
vendor/bin/phpcs --standard=PSR12 lib/
vendor/bin/phpunit --testsuite unit
npm run lint
```

## Licence

[AGPL-3.0-or-later](LICENSE).
