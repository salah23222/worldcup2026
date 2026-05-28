# Launch & Promotion Drafts

Ready-to-post copy for sharing the project. Keep it honest — no inflated claims,
no asking for upvotes/stars on Hacker News (against the rules there).

Live demo: https://wcup2026.org/index.php?lang=en
Repo: https://github.com/salah23222/worldcup2026

---

## 1) Show HN (Hacker News)

**Title:**

```
Show HN: Open-source World Cup 2026 site in plain PHP – no framework, no build
```

**First comment (post immediately after submitting):**

```
I built an open-source companion site for the 2026 World Cup. Live demo:
https://wcup2026.org/index.php?lang=en — Code: https://github.com/salah23222/worldcup2026

A few things that made it interesting to build:

- Plain PHP 8, no framework, no build step, no Composer. `git clone` and run.
- Runs with zero API keys and no database — match data comes from the
  public-domain openfootball dataset and is cached on disk. MySQL is optional
  (only for user accounts).
- Designed to survive traffic spikes on cheap shared hosting: a full-page
  micro-cache + a single-fetcher data layer with stale-while-revalidate, so a
  cold cache never blocks a request on the network.
- Arabic-first with full RTL, but fully bilingual (AR/EN).
- Implements the new 2026 format correctly: 12 groups, the "best 8
  third-placed teams" table, and FIFA head-to-head tie-breakers.
- Installable PWA with a real offline mode, opt-in local match-start
  reminders (no server push / no keys), and a key-free CORS JSON API so you
  can build on the data in any language.

The data layer and security (CSRF, prepared statements, an SSRF guard, output
escaping) were the parts I cared most about. Happy to answer questions or take
feedback — especially on the caching approach and the no-framework tradeoffs.
```

**Timing:** Tue–Thu morning US time. Reply quickly to comments. Do not ask for upvotes.

---

## 2) dev.to article

**Title:** `I built a World Cup 2026 site in plain PHP — no framework, no build, no database`

**Tags:** `php`, `webdev`, `opensource`, `pwa`

```markdown
The 2026 World Cup runs across 48 teams and three countries. I wanted a fast,
Arabic-first companion site — and I wanted it to run on the cheapest shared
hosting without falling over. So I built it in **plain PHP 8: no framework, no
build step, no Composer, and no database required.**

🔗 Live: https://wcup2026.org/index.php?lang=en
⭐ Code (MIT): https://github.com/salah23222/worldcup2026

## Why no framework?

Frameworks are great, but for a read-heavy public site that mostly renders
cached data, they add weight I didn't need. The whole thing is service classes +
templates. `git clone` and `php -S localhost:8000` — that's the dev setup.

## Surviving traffic on cheap hosting

Two ideas do the heavy lifting:
1. **Full-page micro-cache** — rendered HTML is cached per URL+language.
2. **Single-fetcher data layer with stale-while-revalidate** — a request never
   blocks on the network. If the cache is cold, it serves the last good copy and
   refreshes in the background.

Match data comes from the public-domain **openfootball** dataset, cached on disk.
No DB needed to run; MySQL is optional, only for user accounts.

## Getting the 2026 format right

The new format qualifies the **8 best third-placed teams** across 12 groups, so
I had to rank third-placed teams cross-group and implement **FIFA head-to-head
tie-breakers** (points → GD → goals → head-to-head among level teams).

## The fun extras

- Installable **PWA** with a real offline mode.
- **Local match-start reminders** — opt-in notifications before kickoff, with no
  server push and no keys (nothing leaves your device).
- A **key-free, CORS-enabled JSON API** so you can build on the data in any
  language.
- Bilingual **AR/EN** with full RTL/LTR.

## Security I cared about

Prepared statements everywhere, CSRF on every state-changing endpoint, an SSRF
guard on outbound fetches, strict output escaping, and security headers
(CSP/HSTS). I keep auditing it openly — the CHANGELOG lists each hardening pass.

---

It's MIT-licensed and built to be forked for any tournament. If the no-framework,
no-build approach interests you, the repo is a compact real-world example.
Feedback (and stars ⭐) welcome: https://github.com/salah23222/worldcup2026
```

---

## Other channels

- **Reddit:** r/PHP, r/webdev, r/opensource, r/coolgithubprojects (read each sub's
  self-promotion rules first). r/soccer only if framed as a free fan tool, not a plug.
- **X/Twitter + LinkedIn:** lead with the built-in shareable match cards (visual).
- **Arabic dev communities:** Telegram/Facebook groups — the Arabic-first angle is rare.
- **Product Hunt:** optional, closer to the tournament kickoff (June 11).
