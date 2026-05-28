# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/); versioning follows
[Semantic Versioning](https://semver.org/).

## [1.1.5] — 2026-05-29
### Security
- **Hide PHP version.** `security.php` now calls `header_remove('X-Powered-By')`,
  so responses no longer advertise the exact PHP version (reduces information
  available for version-targeted attacks). Works regardless of `expose_php`.

## [1.1.4] — 2026-05-29
### Security
- **Rate-limit IP spoofing fixed.** `RateLimiter::ip()` previously trusted the
  client-supplied `X-Forwarded-For` / `CF-Connecting-IP` headers, so an attacker
  could rotate a fake IP per request and bypass every IP-based limit (register,
  contact, poll vote, qahr bump, league join/create). It now uses the
  server-set `REMOTE_ADDR` by default and only consults forwarding headers when
  the request comes through an internal proxy (private `REMOTE_ADDR`) or when
  `RATE_LIMIT_TRUST_FORWARDED` is explicitly enabled for a trusted edge that
  strips inbound headers. Secure by default; no behavior change for deployments
  where `REMOTE_ADDR` already carries the real visitor IP.

## [1.1.3] — 2026-05-28
### Added
- **Local match-start reminders** (`assets/js/reminders.js`) — a "Remind me"
  bell on every upcoming match card. The user grants notification permission
  once, and the browser fires a local notification ~10 minutes before kickoff
  and again at kickoff. Choices persist in `localStorage`, reschedule on each
  visit, and de-duplicate so a reminder never fires twice. Notifications open
  the match page on click via a new service-worker `notificationclick` handler.
  No server, no push keys, no personal data leaves the device.
  - Limitation: local timers are reliable while the site/PWA has run recently;
    this is not closed-app server push (a future enhancement).
### Changed
- Bumped the service-worker cache to `wc2026-v9`.

## [1.1.2] — 2026-05-28
### Added
- **Best third-placed teams table** (`Standings::thirdPlaceRanking()`) — the
  2026 format qualifies the 8 best third-placed teams across the 12 groups
  into the Round of 32. The groups page now renders a dedicated cross-group
  ranking table that highlights the 8 qualifiers.
### Fixed
- **FIFA-compliant group tie-breakers.** Group standings now resolve ties by
  overall points → goal difference → goals scored, then by **head-to-head**
  results among the teams still level (their direct-match points → GD → goals),
  falling back to alphabetical order. Previously ties fell straight to
  alphabetical order after goals scored, which could mis-order level teams.
### Changed
- Bumped the service-worker cache to `wc2026-v8`.

## [1.1.1] — 2026-05-28
### Security
- **Telegram webhook now requires a secret.** When `TELEGRAM_BOT_TOKEN` is set,
  the webhook (`api/telegram.php`) refuses every request unless
  `TELEGRAM_WEBHOOK_SECRET` is configured and matches the
  `X-Telegram-Bot-Api-Secret-Token` header. Previously the secret check was
  skipped when no secret was set, leaving an enabled bot's webhook open to
  unauthenticated POSTs (spam / sending messages to arbitrary chat IDs).
  The bot remains fully disabled by default (no token → 503).
### Changed
- Bumped the service-worker cache to `wc2026-v7`.

## [1.1.0] — 2026-05-28
### Added
- **Redesigned match detail page** — a single continuous narrative
  (hero score, info, AI preview/summary, unified events timeline,
  statistics, lineup, officials, share). Inspired by professional
  league fixture pages.
- **Unified events timeline** — goals and cards merged into one
  chronological stream with team-side indicators and per-event icons.
- **Comparative statistics** — possession, shots, on-target, corners,
  fouls, offsides rendered as proportional team1/team2 bars.
- **Tactical pitch board** (`templates/pitch.php`) — both teams facing
  each other with real grid positions; falls back to a textual lineup
  when grid data is missing.
- **Officials block** — main referee plus assistant referees and VAR
  placeholders that resolve before kickoff.
- **Local-only match preview** — a tightly-scoped demo for match `#0`
  that only activates on `localhost` / `127.0.0.1` so authors can
  preview how the page looks during a real match. Never ships to any
  public deploy.

### Changed
- **JSON-LD hardening** (`seo.php`) — switched to
  `JSON_HEX_TAG | JSON_HEX_AMP` so the structured-data script tag
  cannot be broken out of regardless of data source.
- **JSON-LD enrichment** — added the recommended `offers` field
  (links to the official FIFA ticketing page; no fabricated price).
- Bumped the service-worker cache to `wc2026-v6` so PWA clients pick
  up the new layout and CSS.

## [1.0.1] — 2026-05-27
### Security
- **Unsubscribe link signing:** removed the public default secret (`wc26-mail-secret`).
  The signing key is now derived from a per-install secret (`INSTALL_TOKEN`, then
  `DB_PASS`), so no shared secret ships in the repository. Set `MAIL_SECRET`
  explicitly in `config.local.php` for the strongest guarantee.
- **HTTP security headers:** added `Content-Security-Policy` and `Permissions-Policy`
  (defense-in-depth against XSS and resource injection) in `.htaccess`.
- Documented a full security audit (before/after) in `docs/SECURITY-AUDIT.md`.
### Changed
- Bumped the service-worker cache to `wc2026-v5` so PWA clients pick up the changes.

## [1.0.0] — 2026-05-26
### Added
- Initial open‑source release. 🎉
- Matches, group standings, knockout bracket, results.
- Predictions game with a global leaderboard (cookie identity or optional MySQL accounts).
- **Private Leagues (المجلس)** with shareable invite codes.
- **1X2 quick community vote** on every match card.
- Stickers album, daily trivia, and the heartbreak (القهر) roast.
- Optional **AI match previews/summaries** (Arabic, dialect‑aware) via Claude.
- News aggregation, stadiums, host‑city map, and fan guide.
- **PWA** with full offline mode.
- Bilingual **AR/EN** with full RTL/LTR, SEO (canonical, hreflang, Open Graph, JSON‑LD), and sitemap.
- Cache‑first data layer + full‑page micro‑cache for high‑traffic resilience.
