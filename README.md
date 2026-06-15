# ⚽ World Cup 2026 Companion

> An Arabic‑first, open‑source companion site for the FIFA World Cup 2026 — live‑style
> results, group standings, **official FIFA match stats & physical data**, **automatic
> match highlights**, AI match reports, a free predictions game, private leagues,
> stickers, daily trivia, leaderboards, bilingual auto‑posting to X, and a fast
> installable PWA.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![No build](https://img.shields.io/badge/build-none-brightgreen)
![License](https://img.shields.io/badge/license-MIT-blue)
![i18n](https://img.shields.io/badge/i18n-AR%20%2F%20EN-orange)
[![Live Demo](https://img.shields.io/badge/Live%20Demo-wcup2026.org-2ea44f)](https://wcup2026.org/index.php?lang=en)
[![GitHub stars](https://img.shields.io/github/stars/salah23222/worldcup2026?style=social)](https://github.com/salah23222/worldcup2026/stargazers)

> ⭐ **If you find this useful, please give it a star — it really helps the project grow!**

---

## 🆕 What's New

The platform now goes well beyond scores — **official data, rich media, and full automation**:

- 📊 **Official FIFA match stats (PMSR)** — every finished match shows FIFA's official
  post‑match data in the site's own identity: possession, shots, passing, **phases of
  play** (build‑up, press, blocks), team **physical** output, and a **tap‑to‑expand card
  per player** (distance, sprints, top speed, line‑breaks, crosses). Pulled straight from
  FIFA's official reports — automatically.
- 🏃 **Physical data explorer** — a sortable, searchable leaderboard of every player's
  running numbers across all matches (total or per‑match average).
- 🎬 **Automatic match highlights** — official broadcast highlights are matched to each
  match via the YouTube feed and shown on the match page within ~30 min of upload —
  hands‑free, with a safe single‑name fallback.
- 🖼️ **Unified bilingual share cards** — one royal‑blue identity for every shareable
  card: group standings, final results (with **AR + EN** stats), next‑24h fixtures, and
  discipline (yellow/red) — tuned for X & WhatsApp with Arabic + English hashtags.
- 🤖 **End‑to‑end automation** — FIFA reports auto‑extract hourly → auto‑deploy to the
  live site → result tweets post within ~2 min of full‑time, with a run‑lock that
  guarantees **no duplicate posts**.
- 📱 **App‑like mobile navigation** — a fixed bottom tab bar (Home · Matches · Predictions
  · News · Stats) for a native feel on phones.
- 🥉 **2026‑format accuracy** — best third‑placed teams table + **FIFA‑compliant
  tie‑breakers** (head‑to‑head), plus match‑start reminders and security hardening.

📜 Full history in **[CHANGELOG.md](CHANGELOG.md)**.

---

## 🌐 Live Demo

**Try the site live:** **[https://wcup2026.org/index.php?lang=en](https://wcup2026.org/index.php?lang=en)**

---

## 📸 Screenshots

> Live demo: **[wcup2026.org](https://wcup2026.org/index.php?lang=en)**

<!-- 🎞️ TIP: record a short screen-capture GIF of the site, save it as docs/demo.gif,
     then uncomment the next line to show an animated demo right here:
![World Cup 2026 — demo](docs/demo.gif)
-->

### Home
![World Cup 2026 — homepage with live countdown to kick-off](docs/screenshots/home.png)

### Get in the Game
![Predictions, bracket, stickers, daily quiz, leaderboard, stats and top scorers](docs/screenshots/features.png)

### Prediction Game
![Predict match scores, earn points, and climb the global leaderboard](docs/screenshots/predict.png)

---

## ✨ Features

- **Matches, groups & knockout** — schedule, live‑style status, results, standings, bracket.
- **2026-accurate standings** — 12 groups, **best third-placed teams** table for the
  Round of 32, and **FIFA-compliant tie-breakers** (head-to-head among level teams).
- **Official FIFA match stats (PMSR)** — possession, shots, passing, **phases of play**,
  team **physical** output, and a **per‑player card** (distance, sprints, top speed,
  line‑breaks, crosses) — pulled from FIFA's official reports, auto‑extracted.
- **Physical data explorer** — sortable, searchable per‑player running leaderboard across
  all matches (total or per‑match average).
- **Automatic match highlights** — official broadcast clips matched to each match via the
  YouTube feed and shown on the match page.
- **Bilingual share cards** — one unified royal‑blue identity for standings, results
  (with AR/EN stats), next‑24h fixtures and discipline — tuned for X & WhatsApp.
- **Automated X posting** — pre/post‑match, results, standings & news, fully hands‑free
  with a run‑lock that prevents duplicate posts.
- **Predictions game** — predict scores, earn points, climb a global leaderboard. No login required (cookie identity) or full accounts via MySQL.
- **Private Leagues** — create a league, share a code, compete with friends/family.
- **Quick 1X2 vote** on every match card (community polls).
- **Match-start reminders** — opt-in local notifications before kickoff (no server/push keys).
- **Stickers album, daily trivia, and a heartbreak roast** — gamified engagement.
- **AI match previews/summaries** (Arabic, dialect‑aware) via Claude — *optional*.
- **News** aggregated from public RSS, **stadiums & host‑city map**, **fan guide**.
- **PWA** — installable, full offline mode, works on slow networks.
- **Bilingual AR/EN** with full RTL/LTR, SEO (canonical, hreflang, Open Graph, JSON‑LD), sitemap.
- **Full‑page micro‑cache** + single‑fetcher data layer → handles big traffic on cheap shared hosting.

## 🧱 Tech stack

- **Plain PHP 8.0+** — no framework, **no build step**, no Composer required.
- **No database needed** to run: data comes from the free public **openfootball** dataset and is cached on disk. MySQL is *optional* (only for user accounts).
- Vanilla JS + a single CSS file. Works on any PHP host (Apache / LiteSpeed / Nginx + PHP‑FPM).

---

## 🚀 Quick start

### Option A — Docker (no PHP needed) 🐳

```bash
git clone https://github.com/salah23222/worldcup2026.git
cd worldcup2026
docker compose up        # → open http://localhost:8080
```

### Option B — PHP / XAMPP

```bash
# 1) Get the code
git clone https://github.com/salah23222/worldcup2026.git
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

## 🧑‍💻 For developers — use the data in any language

This project is built in PHP, but its data is **plain JSON over HTTP**, so you can build
on it in **any language** (Python, JavaScript, Java, Go, Rust, C#, …). A public,
**CORS-enabled, key-free** API is included:

```bash
curl "https://wcup2026.org/api/data.php?action=today"
```
```python
import requests
matches = requests.get("https://wcup2026.org/api/data.php?action=today").json()["matches"]
```
```javascript
const { standings } = await (await fetch(
  "https://wcup2026.org/api/data.php?action=standings")).json();
```

👉 Full endpoint reference & response shapes: **[docs/API.md](docs/API.md)**.
Raw datasets live in [`/data`](data) — `worldcup_fallback.json`, `rankings.json`, `referees.json`.

---

## 🔑 Third‑party services & keys

All secrets live **only** in `includes/config.local.php` (git‑ignored — never committed),
or as environment variables of the same name. **You must obtain your own keys** from
these providers; none are bundled.

| Feature | Provider | Where to get the key | Config key |
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

### Enabling optional features

- **AI content** — set `CLAUDE_API_KEY`. It also has a date gate `AI_ACTIVATE_FROM`
  (default `2026-06-08`); AI stays OFF before that date to avoid cost.
- **User accounts** — set `DB_ENABLED => true` + `DB_*`, then open
  `/install.php?token=YOUR_INSTALL_TOKEN` once to create the tables, **then delete `install.php`**.
- **Live scores** — set `APIFOOTBALL_KEY` (during the tournament only).
- **Email digest** — set `SMTP_*`, then schedule `php cron/digest.php` (e.g. daily cron).

---

## 🌍 Deployment

1. Upload all files **except** `config.local.php` (create it on the server) and the
   runtime folders' contents (`data/*`, `cache/*` — they regenerate).
2. Make `cache/` and `data/` writable (e.g. `755`).
3. Force HTTPS at the host/edge. Confirm `/includes/config.local.php`, `/data/`, `/cache/`
   return **403**.
4. (Recommended) add a cron to keep data warm and send the digest.

Works on shared hosting (LiteSpeed/Apache read the bundled `.htaccess`). On plain
**Nginx**, replicate the deny rules for `config.local.php`, `/data/`, `/cache/` and the
`.json/.md/.log` files in your server block (Nginx ignores `.htaccess`).

### 💡 Recommended hosting

This project runs great on inexpensive shared hosting. I personally recommend
**[Hostinger](https://www.hostinger.com/ae?REFERRALCODE=1SALAH83)** — it runs LiteSpeed,
reads the bundled `.htaccess` out of the box, includes free SSL, and supports PHP 8 + MySQL,
so the whole site works with zero extra setup.

> ℹ️ The Hostinger link above is a referral link — using it supports this project at no extra cost to you. 🙏

---

## 📁 Project structure

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

## ⭐ Star History

[![Star History Chart](https://api.star-history.com/svg?repos=salah23222/worldcup2026&type=Date)](https://star-history.com/#salah23222/worldcup2026&Date)

---

## 🤝 Contributing

PRs welcome. Please keep the project dependency‑free (plain PHP/JS), escape all output
with `e()`, protect state‑changing endpoints with CSRF, and never commit secrets.

## 🙏 Acknowledgments

- **[openfootball](https://github.com/openfootball/worldcup.json)** — all match fixtures,
  results and schedules come from this excellent **public‑domain (CC0)** open‑data project.
  Sincere thanks to its maintainers and contributors — this site is built on their work.
- **API‑Football**, **Anthropic Claude**, and public **RSS** sources — optional integrations
  powering live scores, AI previews, and the news feed.

> 🛠️ **Built in PHP — but usable from any language.** The data is plain JSON over HTTP,
> so you can build on it in **Python, JavaScript, Java, Go, Rust, C#, …** —
> see **[For developers](#-for-developers--use-the-data-in-any-language)**.

---

## 📄 License

MIT — see [LICENSE](LICENSE).

**Disclaimer:** Unofficial fan project, not affiliated with or endorsed by FIFA.
“FIFA” and “World Cup” are trademarks of their respective owners. Match data from the
public‑domain openfootball dataset.
