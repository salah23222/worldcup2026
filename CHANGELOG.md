# Changelog

All notable changes to this project are documented here.
Format based on [Keep a Changelog](https://keepachangelog.com/); versioning follows
[Semantic Versioning](https://semver.org/).

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
