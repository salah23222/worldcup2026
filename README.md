# ⚽ World Cup 2026 Companion — كأس العالم 2026

> An Arabic‑first, open‑source companion site for the FIFA World Cup 2026 — live‑style
> results, group standings, a free predictions game, private leagues, stickers, daily
> trivia, leaderboards, AI match previews, and a fast installable PWA.
> موقع تفاعلي عربي مفتوح المصدر لكأس العالم 2026: النتائج والترتيب ولعبة التوقعات
> والدوريات الخاصة والملصقات وتحدّي المعرفة والصدارة ومعاينات الذكاء الاصطناعي.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![No build](https://img.shields.io/badge/build-none-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)
![i18n](https://img.shields.io/badge/i18n-AR%20%2F%20EN-orange)

---

## 📸 Screenshots / لقطات الشاشة

> Drop your images into `docs/screenshots/` and uncomment the lines below.
> ضع صورك في `docs/screenshots/` ثم أزِل التعليق عن الأسطر التالية.

<!--
![Home](docs/screenshots/home.png)
![Matches](docs/screenshots/matches.png)
![Predictions](docs/screenshots/predict.png)
-->

---

## ✨ Features / الميزات

- **Matches, groups & knockout** — schedule, live‑style status, results, standings, bracket.
- **Predictions game** — predict scores, earn points, climb a global leaderboard. No login required (cookie identity) or full accounts via MySQL.
- **Private Leagues (المجلس)** — create a league, share a code, compete with friends/family.
- **Quick 1X2 vote** on every match card (community polls).
- **Stickers album, daily trivia, heartbreak (القهر) roast** — gamified engagement.
- **AI match previews/summaries** (Arabic, dialect‑aware) via Claude — *optional*.
- **News** aggregated from public RSS, **stadiums & host‑city map**, **fan guide**.
- **PWA** — installable, full offline mode, works on slow networks.
- **Bilingual AR/EN** with full RTL/LTR, SEO (canonical, hreflang, Open Graph, JSON‑LD), sitemap.
- **Full‑page micro‑cache** + single‑fetcher data layer → handles big traffic on cheap shared hosting.

## 🧱 Tech stack / التقنيات

- **Plain PHP 8.0+** — no framework, **no build step**, no Composer required.
- **No database needed** to run: data comes from the free public **openfootball** dataset and is cached on disk. MySQL is *optional* (only for user accounts).
- Vanilla JS + a single CSS file. Works on any PHP host (Apache / LiteSpeed / Nginx + PHP‑FPM).

---

## 🚀 Quick start / تشغيل سريع

```bash
# 1) Get the code
git clone https://github.com/<your-user>/worldcup2026.git
cd worldcup2026

# 2) Create your local config from the template
cp includes/config.local.example.php includes/config.local.php
#    (edit it only if you want optional features — it runs fine empty)

# 3) Run it (built‑in PHP server, served from the project root)
php -S 127.0.0.1:8000
#    → open http://127.0.0.1:8000
```

**XAMPP / served from a subfolder?** Put the folder in `htdocs/` and set
`'SITE_URL' => 'http://localhost/worldcup2026'` in `config.local.php` so the
CSS/JS load with correct paths.

> The site works **immediately with zero keys** using the bundled fallback data
> (`data/worldcup_fallback.json`) and the public openfootball feed. Everything
> below is **optional**.

---

## 🔑 Third‑party services & keys / المفاتيح من الجهات الخارجية

All secrets live **only** in `includes/config.local.php` (git‑ignored — never committed),
or as environment variables of the same name. **You must obtain your own keys** from
these providers; none are bundled.

| Feature / الميزة | Provider / الجهة | Where to get the key / من أين | Config key |
|---|---|---|---|
| Match data (required, free) | **openfootball** | No key — public domain · https://github.com/openfootball | — |
| Live scores & cards | **API‑Football** | Free 100/day · https://dashboard.api-football.com | `APIFOOTBALL_KEY` |
| AI previews / summaries / roast | **Anthropic Claude** | https://console.anthropic.com | `CLAUDE_API_KEY` |
| User accounts & predictions | **MySQL** | Your host / local | `DB_*`, `DB_ENABLED` |
| Newsletter emails | **SMTP** (any) | e.g. Hostinger, Gmail app‑password, Mailgun | `SMTP_*` |
| Telegram bot | **Telegram BotFather** | https://t.me/BotFather | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` |
| Admin panel | — | Set your own password | `ADMIN_PASS` |
| Search verification | Google / Bing | Search Console / Webmaster | `GOOGLE_SITE_VERIFICATION`, `BING_SITE_VERIFICATION` |

> 🔒 **Security:** never put keys anywhere except `config.local.php` (or ENV).
> If a key ever leaks, **rotate it** at the provider. Make sure your server returns
> **403** for `/includes/config.local.php` and the `/data/` & `/cache/` folders.

### Enabling optional features / تفعيل الميزات

- **AI content** — set `CLAUDE_API_KEY`. It also has a date gate `AI_ACTIVATE_FROM`
  (default `2026-06-08`); AI stays OFF before that date to avoid cost.
- **User accounts** — set `DB_ENABLED => true` + `DB_*`, then open
  `/install.php?token=YOUR_INSTALL_TOKEN` once to create the tables, **then delete `install.php`**.
- **Live scores** — set `APIFOOTBALL_KEY` (during the tournament only).
- **Email digest** — set `SMTP_*`, then schedule `php cron/digest.php` (e.g. daily cron).

---

## 🌍 Deployment / النشر

1. Upload all files **except** `config.local.php` (create it on the server) and the
   runtime folders' contents (`data/*`, `cache/*` — they regenerate).
2. Make `cache/` and `data/` writable (e.g. `755`).
3. Force HTTPS at the host/edge. Confirm `/includes/config.local.php`, `/data/`, `/cache/`
   return **403**.
4. (Recommended) add a cron to keep data warm and send the digest.

Works on shared hosting (LiteSpeed/Apache read the bundled `.htaccess`). On plain
**Nginx**, replicate the deny rules for `config.local.php`, `/data/`, `/cache/` and the
`.json/.md/.log` files in your server block (Nginx ignores `.htaccess`).

---

## 📁 Project structure / البنية

```
includes/      core: bootstrap, config, DataService, Auth, Predictions, ... (service classes)
templates/     header / footer / match_card  (shared UI)
assets/        css, js, images, vendor
api/           JSON endpoints (predict, poll, league, contact, ...)
cron/          scheduled scripts (digest)
data/          seed data (fallback fixtures, rankings, referees) + runtime user data
cache/         runtime cache (page cache, fetched data) — auto‑generated
*.php          pages (index, matches, match, groups, knockout, teams, predict, leagues, ...)
```

Architecture in one line:
`page.php → includes/bootstrap.php → DataService (cache + fetch) → openfootball JSON`.

---

## 🤝 Contributing / المساهمة

PRs welcome. Please keep the project dependency‑free (plain PHP/JS), escape all output
with `e()`, protect state‑changing endpoints with CSRF, and never commit secrets.

## 📄 License / الرخصة

MIT — see [LICENSE](LICENSE).

**Disclaimer:** Unofficial fan project, not affiliated with or endorsed by FIFA.
“FIFA” and “World Cup” are trademarks of their respective owners. Match data from the
public‑domain openfootball dataset.
