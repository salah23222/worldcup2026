# ⚽ World Cup 2026 — Public JSON API

A **free, public, read-only JSON API** for the FIFA World Cup 2026 — matches,
results, live scores, match details (goals, cards, official FIFA stats), top
scorers, group standings and per‑player physical data.

- ✅ **No API key, no signup, no rate sign‑up** — just call it.
- ✅ **CORS enabled** (`Access-Control-Allow-Origin: *`) → use it directly from a browser, mobile app (React Native / Flutter), or any backend.
- ✅ Responses are `application/json; charset=utf-8`, cached ~60s.
- 📊 Data source: **openfootball** (schedule, Public Domain) + **official FIFA** post‑match reports + live results.

> Live site: **[wcup2026.org](https://wcup2026.org/index.php?lang=en)** · Open data also in [football.txt](#-plain-text-footballtxt) format.

---

## Base URL

```
https://wcup2026.org/api/data.php?action=<ACTION>
```

Every response is an envelope:

```json
{ "ok": true, "action": "today", "updated": 1718600000, "matches": [ ... ] }
```

`ok` is `false` with an `error` field on bad requests (`unknown_action`, `not_found`).

---

## Endpoints

| Action | Params | Returns |
|---|---|---|
| `today` | — | matches scheduled today (`matches`) |
| `live` | — | matches in play right now (`matches`) |
| `upcoming` | — | upcoming fixtures (`matches`) |
| `results` | — | finished matches with scores (`matches`) |
| `all` | — | all 104 matches (`matches`) |
| `match` | `id` | **one match, full detail** (`match`) — score, goals, cards, FIFA stats |
| `scorers` | — | top scorers / golden‑boot race (`scorers`) |
| `standings` | — | all 12 group tables (`standings`) |
| `group` | `g` (e.g. `Group A`) | one group's table (`standings`) |
| `physical` | — | per‑player running data from FIFA reports (`players`) |

### Examples

```bash
curl "https://wcup2026.org/api/data.php?action=results"
curl "https://wcup2026.org/api/data.php?action=match&id=12"
curl "https://wcup2026.org/api/data.php?action=scorers"
curl "https://wcup2026.org/api/data.php?action=group&g=Group%20A"
```

```js
// React Native / browser — no backend needed
const res  = await fetch('https://wcup2026.org/api/data.php?action=scorers');
const data = await res.json();
console.log(data.scorers); // [{ name, team, goals }, ...]
```

---

## Response shapes

### Match object (`matches[]` and `match`)

```json
{
  "id": 12,
  "round": "Matchday 7",
  "group": "Group C",
  "team1": "Brazil",          "team2": "Morocco",
  "team1_ar": "البرازيل",      "team2_ar": "المغرب",
  "flag1": "https://flagcdn.com/w80/br.png",
  "flag2": "https://flagcdn.com/w80/ma.png",
  "status": "finished",        // upcoming | live | finished
  "score": [1, 1],             // [team1, team2] or null
  "live_minute": null,
  "date": "2026-06-14", "time": "20:00", "datetime": 1718390000,
  "ground": "Dallas (Arlington)"
}
```

`action=match&id=N` adds richer detail:

```json
{
  "ht": [0, 1],                                   // half-time score
  "goals1": [ { "name": "Vinícius Júnior", "minute": "32", "penalty": true } ],
  "goals2": [ ... ],                              // scorers per team (+ penalty/og flags)
  "cards":  [ { "team": 1, "minute": 61, "name": "...", "type": "yellow" } ],
  "stats":  { "possession": [..], "shots": [..], "xg": [..], "line_breaks": [..], ... }
}
```

### Scorer object (`scorers[]`)

```json
{ "name": "Lionel Messi", "team": "Argentina", "goals": 3 }
```

### Standings row (`standings`, grouped by `Group A` … `Group L`)

```json
{ "team": "Mexico", "pts": 3, "gd": 2, "played": 1, "w": 1, "d": 0, "l": 0, "gf": 2, "ga": 0 }
```

### Physical player (`players[]`)

```json
{ "name": "...", "team": "...", "num": 8, "m": 1,
  "dist": 11700, "sprints": 44, "hsr": 176, "top": 32.8, "photo": "https://..." }
```

---

## 📄 Plain text (football.txt)

The whole tournament is also exported in the **openfootball `football.txt`** format
(human‑ and machine‑readable plain text):

```
https://wcup2026.org/football.php            # schedule + results
https://wcup2026.org/football.php?results    # results only
https://wcup2026.org/football.php?reports    # results + scorers + bookings
```

---

## 🚀 Built with our API

Apps & projects developers have built using this API:

- **[FIFA World Cup Fixtures](https://play.google.com/store/apps/details?id=com.fifaworldcupfixtures)** — Android app on Google Play.

Built something with our API? Tell us and we'll feature it here — **info@wcup2026.org** (or open a discussion / issue on this repo).

---

## Notes & fair use

- Read‑only and free. Please cache where you can (data updates roughly every minute).
- Attribution appreciated: link back to **wcup2026.org**.
- Schedule data is openfootball (Public Domain); FIFA post‑match metrics are FIFA's official data, presented here.
- This is a community project — built with ❤️ for football. Questions / ideas: **info@wcup2026.org**.
