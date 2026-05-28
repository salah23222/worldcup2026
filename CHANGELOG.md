# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/); versioning follows
[Semantic Versioning](https://semver.org/).

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
