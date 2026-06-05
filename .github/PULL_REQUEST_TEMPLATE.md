## What & why / ماذا ولماذا

**Add complete French language support (i18n) to the World Cup 2026 Companion site.**

The site previously supported Arabic (`ar`) and English (`en`). This PR adds full French (`fr`) as a third language, making the site accessible to French-speaking fans across Canada, France, Africa, and beyond — particularly relevant for the 2026 World Cup co-hosted in Canada where French is an official language.

### Changes summary

| Category | Details |
|----------|---------|
| **i18n dictionary** | 350+ new French translation keys in `includes/i18n.php` |
| **Config** | Added `SITE_NAME_FR` and `SITE_TAGLINE_FR` constants |
| **Language switcher** | Updated header to support 3 languages (ar/en/fr) with dropdown |
| **SEO** | Added `hreflang="fr"` and `og:locale fr_FR` |
| **Date formatting** | French dates: `mercredi 10 juin 2026`, `21h00` (24h format) in `helpers.php` |
| **JS locale** | `app.js` now returns `'fr'` locale for `Intl.DateTimeFormat` with 24h time |
| **Stadiums** | Added French names, cities, and descriptions for all 16 stadiums |
| **Emails** | Welcome and password reset emails fully translated in French |
| **User-facing pages** | All pages updated: bracket, predictions, fan guide, map, leagues, promote, today, etc. |
| **Share cards** | Prediction, brag, heartbreak, sticker cards now have French text |
| **Marketing kit** | 6 French social media templates + French hashtags |

### Files modified (42 files, +849 / -311)

<details>
<summary>Click to expand full list</summary>

- `.github/PULL_REQUEST_TEMPLATE.md`
- `CONTRIBUTING.md`
- `api/predict.php`
- `api/qahr.php`
- `api/telegram.php`
- `archive.php`
- `assets/js/app.js`
- `assets/js/bracket.js`
- `bracket.php`
- `calendar.php`
- `card.php`
- `fanguide.php`
- `groups.php`
- `includes/AccountMail.php`
- `includes/Cards.php`
- `includes/Polls.php`
- `includes/Referees.php`
- `includes/Stadiums.php`
- `includes/config.php`
- `includes/helpers.php`
- `includes/i18n.php`
- `includes/seo.php`
- `index.php`
- `leagues.php`
- `manifest.php`
- `map.php`
- `predict.php`
- `promote.php`
- `referee.php`
- `referees.php`
- `register.php`
- `squads.php`
- `stadium.php`
- `stadiums.php`
- `stickers.php`
- `team.php`
- `templates/footer.php`
- `templates/header.php`
- `templates/match_card.php`
- `today.php`
- `topscorers.php`
- `unsubscribe.php`

</details>

## Related issue / المشكلة المرتبطة
<!-- e.g. Closes #123 -->

## Checklist / قائمة التحقّق
- [x] No secrets committed (`config.local.php` untouched)
- [x] All output escaped with `e()`
- [x] State‑changing endpoints protected with CSRF
- [x] `php -l` passes on all changed PHP files (108 files, 0 errors)
- [x] Tested in all three languages (`?lang=ar`, `?lang=en` & `?lang=fr`)
- [x] New UI strings added to `includes/i18n.php` (ar + en + fr)
- [x] No new dependencies / no build step introduced
- [x] Date formatting works correctly in all 3 languages
- [x] SEO hreflang tags include `fr`
- [x] Language switcher cycles through all 3 languages

## How to test / كيفية الاختبار

```bash
# Arabic
php -S 127.0.0.1:8000
# Visit: http://localhost:8000/?lang=ar

# English
# Visit: http://localhost:8000/?lang=en

# French
# Visit: http://localhost:8000/?lang=fr
```

### Key pages to verify in French:
1. **Homepage** (`/`) — countdown, stats band, links
2. **Today** (`/today.php`) — greeting, match of the day, trivia
3. **Predictions** (`/predict.php`) — match cards, poll questions
4. **Bracket** (`/bracket.php`) — fill bracket, share button
5. **Fan Guide** (`/fanguide.php`) — countries, tips
6. **Stadium detail** (`/stadium.php?id=0`) — name, city, history in French
7. **Map** (`/map.php`) — all 16 cities/stadiums in French
8. **Leagues** (`/leagues.php`) — create/join flow
9. **Promote** (`/promote.php`) — marketing kit templates in French

## Screenshots / لقطات الشاشة

<!-- Add screenshots showing the site in French if possible -->
