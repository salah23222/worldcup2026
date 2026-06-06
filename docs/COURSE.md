# 🎓 دورة wcup2026.org — من الصفر إلى الإطلاق
### دليل YouTube الشامل · 12 حلقة · ~3 ساعات إجمالاً

> هذا الدليل سكريبت تصوير + فهرس تقني. كل حلقة فيها: hook، أهداف،
> ملفات تُعرض على الشاشة، خطوات demo، ونقاط حديث لتقولها أمام الكاميرا.

---

## 🎬 الفكرة العامّة للسلسلة

> **«كيف بنيت موقع كأس العالم 2026 لوحدي بـ PHP خام، بدون framework،
> بدون قاعدة بيانات في البداية، وحوّلته إلى منصّة كاملة بـ AI ونشر تلقائي
> على X بميزانية أقل من 10 دولار.»**

**الجمهور المستهدف**: مطوّرو PHP المبتدئون والمتوسّطون، أصحاب المشاريع
الشخصيّة، المهتمّون بكرة القدم، أيّ شخص يحلم ببناء منصّته الخاصة.

**النمط**: عملي 100% — كل خطوة تُكتب أمام المشاهد، كل ملف يُفتح ويُشرح.

---

## 📺 خريطة الحلقات

| # | العنوان | المدة | المستوى |
|---|---|---|---|
| 1 | الفكرة، المعمارية، والـ Stack | 12د | مقدّمة |
| 2 | تهيئة XAMPP + هيكلة الملفات | 15د | مبتدئ |
| 3 | DataService — قلب الموقع | 20د | مبتدئ |
| 4 | الواجهة العربيّة + RTL + كاش | 18د | مبتدئ |
| 5 | المباريات والمجموعات والإقصائيّات | 22د | متوسّط |
| 6 | نظام التوقّعات + MySQL + الحسابات | 25د | متوسّط |
| 7 | الذكاء الاصطناعي — Claude للتقارير | 18د | متوسّط |
| 8 | متعدّد اللغات (AR/EN/FR) + كشف تلقائي | 15د | متوسّط |
| 9 | النشرة البريديّة (SMTP + Cron) | 18د | متوسّط |
| 10 | النشر التلقائي على X — 4 طبقات | 30د | متقدّم |
| 11 | حماية الحساب + RateGuard | 12د | متقدّم |
| 12 | النشر على Hostinger + المراقبة | 18د | متقدّم |

**المجموع**: ~3 ساعات و 15 دقيقة من المحتوى المركّز.

---

# الحلقة 1: الفكرة، المعمارية، والـ Stack

**الهدف**: تشويق المشاهد + شرح القرارات الكبرى قبل أي كود.

## 🎤 Hook الافتتاح (30 ثانية)

> «بعد شهر من العمل، صار عندي موقع كأس عالم 2026 كامل: 104 مباراة،
> 48 منتخب، توقّعات، تقارير AI، نشرة بريديّة، نشر تلقائي على X بـ AR
> و EN و FR، كل شي مجاني تقريباً ولوحدي. بدون framework، بدون قاعدة
> بيانات في البداية، فقط PHP خام. اليوم أعلّمك كيف.»

## 🎯 ما سيتعلّمه المشاهد

- لماذا اخترت PHP خام بدون Laravel/Symfony
- كيف وفّرت قاعدة البيانات لمدّة طويلة
- مصدر البيانات المجاني الذي يخدم كل شيء

## 🎬 على الشاشة

افتح المتصفّح وأظهر:
- `wcup2026.org` (الصفحة الرئيسية)
- `matches.php` (104 مباراة)
- `predict.php` (التوقّعات)
- `x.com/salah1alhammadi` (التغريدات التلقائيّة)

## 🗣️ نقاط الحديث

**Tech Stack النهائي**:
```
Backend  : PHP 8.x (بدون framework)
Database : MySQL (مرحلة لاحقة، الإنتاج فقط)
Frontend : HTML + CSS Grid + Vanilla JS (بدون React/Vue)
Hosting  : Hostinger ($3/شهر)
Data     : openfootball/worldcup.json (Public Domain، مجاني)
Live     : API-Football (مجاني — 100 طلب/يوم)
AI       : Claude Haiku 4.5 (~$0.50 للبطولة كاملة)
X Posts  : X API Pay-Per-Use (~$0.12/شهر)
Email    : Hostinger SMTP (مع الاستضافة)
```

**القرار التصميمي الأهم**:
> «لماذا PHP خام؟ لأنني أردت سيطرة كاملة، نشر بسيط على أي استضافة،
> صفر تبعيّات، تكلفة صفر بطاقات.»

## 📂 ملفات لتُرى على الشاشة

- `CLAUDE.md` (دليل المشروع)
- `includes/config.php` (كل الإعدادات)
- `includes/bootstrap.php` (نقطة التحميل)

## 📝 Outro

> «الحلقة القادمة: نبدأ من الصفر — XAMPP، هيكلة الملفات،
> ونرى أوّل صفحة شغّالة.»

---

# الحلقة 2: تهيئة XAMPP + هيكلة الملفات

**الهدف**: المشاهد ينسخ المشروع ويشغّله محلياً خلال 15 دقيقة.

## 🎤 Hook

> «لو ركّبت XAMPP قبل، تعرف كم هذا أسهل من Docker و Composer.
> هاي 5 دقائق من البداية للنهاية.»

## 🎯 الأهداف

- تثبيت XAMPP وتشغيل Apache + PHP
- استنساخ المشروع من GitHub
- فتح الموقع محلياً بدون أيّ خطأ
- فهم هيكلة المجلّدات

## 🎬 على الشاشة

### 1. تنزيل XAMPP

```
https://www.apachefriends.org/download.html
```
ثبّت في `C:\xampp` (Windows) أو `/Applications/XAMPP` (macOS).

### 2. استنساخ المشروع

```bash
cd C:/xampp/htdocs
git clone https://github.com/salah23222/worldcup2026.git
cd worldcup2026
```

### 3. تشغيل XAMPP

شغّل **Apache** من XAMPP Control Panel.

افتح: `http://localhost/worldcup2026`

### 4. هيكل الملفات

```
worldcup2026/
├── includes/        ← الخدمات (DataService, Predictions, ...)
│   ├── config.php
│   ├── bootstrap.php
│   └── helpers.php
├── templates/       ← header + footer مشترك
├── assets/          ← CSS + JS + images
├── api/             ← endpoints (data.php, predict.php, …)
├── cron/            ← مهام مجدولة (digest, tweet)
├── cache/           ← يُملأ تلقائياً (لا تلمسه)
└── *.php            ← صفحات الموقع (index, matches, predict, …)
```

## 🗣️ نقاط الحديث

**القاعدة الذهبيّة**:
> «كل صفحة تبدأ بسطر واحد: `require __DIR__ . '/includes/bootstrap.php';`
> وهذا السطر يُحمّل كل شيء بترتيب صحيح.»

## 📝 Outro

> «الحلقة القادمة: DataService — كيف نقرأ 104 مباراة من ملف JSON
> واحد ونحوّلها لمنصّة كاملة.»

---

# الحلقة 3: DataService — قلب الموقع

**الهدف**: شرح كيف ملف JSON واحد يخدّم كل شيء، مع كاش 3 طبقات.

## 🎤 Hook

> «هذا المشروع كان أصعب قرار فيه: 'هل أستخدم قاعدة بيانات؟'
> قرّرت لا، واستخدمت ملف JSON واحد. والآن أوريك كيف يعمل.»

## 🎬 على الشاشة

افتح `includes/DataService.php` واعرض:

### الدوال الجوهريّة

```php
DataService::allMatches();      // كل الـ 104
DataService::matchByIndex(42);  // المباراة 42
DataService::matchesOnDate();   // مباريات اليوم
DataService::upcomingMatches(4);
DataService::latestResults(4);
DataService::matchesInGroup('Group A');
DataService::matchStatus($m);   // live/finished/upcoming
DataService::matchTimestamp($m);
```

### كاش 3 طبقات (شرح بصري)

```
1) كاش حديث (< 5 دقائق)
   ↓ موجود؟ → استخدمه ✓
   ↓ لا
2) جلب من openfootball
   ↓ نجح؟ → خزّن واستخدم ✓
   ↓ فشل
3) كاش قديم (مهما كان عمره)
   ↓ موجود؟ → استخدمه ✓
   ↓ لا
4) ملف احتياطي (worldcup_fallback.json)
```

> «هذا يعني: لو الإنترنت انقطع، الموقع يستمرّ بالعمل. لو المصدر
> توقّف، الموقع يستمرّ بالعمل. زيرو فشل.»

## 🎬 Demo

افتح `includes/DataService.php` وأظهر:
- السطر 25: `load()`
- السطر 247: `allMatches()` مع `_index` و `_status`
- السطر 275: `matchStatus()`

## 📝 Outro

> «الحلقة القادمة: نأخذ هذه البيانات ونعرضها بواجهة عربيّة جميلة.»

---

# الحلقة 4: الواجهة العربيّة + RTL + كاش الصفحات

**الهدف**: شرح كيف يصير الموقع كله RTL بسطر واحد، وكيف يتحمّل 10x ضغط.

## 🎬 على الشاشة

### 1. i18n.php — نظام الترجمة

```php
// كل نص في الموقع يمرّ بـ t()
echo t('matches');  // → "المباريات" أو "Matches" حسب اللغة
```

افتح `includes/i18n.php`:
- 230 سطر فيها كل ترجمات الموقع
- `current_lang()` يكشف اللغة من URL → كوكي → header → افتراضي

### 2. PageCache — كاش الصفحة الكاملة

افتح `includes/PageCache.php`:
- يخزّن HTML كامل في `cache/page/*.html`
- يخدّم الزوّار غير المسجّلين من الكاش (بدون PHP أصلاً)
- المستخدمون المسجّلون يحصلون على نسخة جديدة دائماً

> «هذا يعني: 10,000 زائر في الساعة لا يضربون السيرفر — يقرأون من
> ملفات HTML جاهزة.»

## 📝 Outro

> «الحلقة القادمة: المجموعات، الجداول، وحساب الترتيب بقواعد FIFA.»

---

# الحلقة 5: المباريات والمجموعات والإقصائيّات

**الهدف**: شرح كيف نحسب جدول مجموعة بـ tie-breaker FIFA الكامل.

## 🎬 على الشاشة

### Standings.php — جدول مجموعة

```php
$rows = Standings::forGroup('Group A');
// كل صف: ['team', 'p', 'w', 'd', 'l', 'gf', 'ga', 'gd', 'pts']
```

### قواعد كسر التعادل (وفق FIFA)

1. النقاط
2. فرق الأهداف الإجمالي
3. الأهداف المسجَّلة
4. **المواجهة المباشرة** بين المتعادلين فقط
5. أبجدياً

> «هذا الجزء كان أصعب جزء في المنطق. النقطة الـ 4 خاصّة: لو 3 منتخبات
> متعادلة، نُنشئ 'دوري مصغّر' بين هؤلاء الثلاثة فقط ونحسب نقاطهم.»

### Bracket.php — شجرة الإقصائيّات

افتح وأظهر:
- كيف يُملأ W73 تلقائياً عند معرفة الفائز من المباراة 73

## 📝 Outro

> «الحلقة القادمة: التوقّعات — الميزة التي تحوّل الموقع من
> صفحة معلومات إلى منصّة تفاعليّة.»

---

# الحلقة 6: نظام التوقّعات + MySQL + الحسابات

**الهدف**: شرح كيف انتقلنا من ملفات JSON محلية إلى MySQL.

## 🎬 على الشاشة

### المرحلة 1: التوقّعات بدون قاعدة بيانات

```
data/preds/
├── _leaderboard.json
└── users/
    ├── alice.json
    └── bob.json
```

### المرحلة 2: MySQL لاحقاً

```sql
CREATE TABLE wc_users (id, username, email, password_hash, ...);
CREATE TABLE wc_preds (uid, match_idx, p1, p2, ts);
CREATE TABLE wc_lboard (uid, points, rank);
```

### bcrypt للأمان

```php
$hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
password_verify($plain, $hash);  // true / false
```

> «لم أحفظ كلمة سر في صورتها الأصليّة أبداً. حتى لو سُرّب الـ DB،
> كلمات السر مشفّرة بطريقة لا يمكن عكسها.»

### Rate Limiting

افتح `RateLimiter.php`:
- يمنع محاولات الدخول الكثيرة (brute force)
- يمنع spam التسجيل

## 📝 Outro

> «الحلقة القادمة: نُضيف الذكاء الاصطناعي — Claude يكتب تقرير
> لكل مباراة تلقائياً.»

---

# الحلقة 7: الذكاء الاصطناعي — Claude للتقارير

**الهدف**: عرض كيف API بسيط واحد يحوّل بيانات نتيجة لتقرير احترافي.

## 🎬 على الشاشة

### مفتاح Claude

في `config.local.php`:
```php
'CLAUDE_API_KEY' => 'sk-ant-api03-...',
```

### AiContent::forMatch

افتح `includes/AiContent.php` السطر 42:

```php
public static function forMatch(array $m, string $type, ?string $forceLang = null): ?string {
    // type: 'preview' (قبل) أو 'summary' (بعد)
    // يُخزّن النتيجة في cache/ai_{type}_{idx}_{lang}.txt
    // لا يستدعي API مرّة ثانية لنفس المباراة + نفس اللغة
}
```

### Prompt Engineering

```php
$system = "You are a professional football writer for FIFA World Cup 2026. "
        . "Write strictly in {$langWord}. Produce {$kind}: 80-120 words. "
        . "Use ONLY facts provided plus general team background. "
        . "Do NOT invent lineups, injuries, quotes.";
```

> «المفتاح: أقول للنموذج بالضبط ماذا لا يفعل. ولا أعطيه إلا
> الحقائق التي أعرفها. النتيجة: تقارير دقيقة بدون اختراع.»

### التكلفة الفعليّة

```
Haiku 4.5: $1/M input + $5/M output
لكل تقرير: ~500 input + 1000 output = ~3000 tokens
لـ 208 تقرير (104 مباراة × 2 لغة): ~$0.65
البطولة كاملة: < $1
```

## 📝 Outro

> «الحلقة القادمة: متعدّد اللغات — كيف الموقع يكشف لغة الزائر فوراً.»

---

# الحلقة 8: متعدّد اللغات (AR/EN/FR)

**الهدف**: شرح كشف لغة المتصفّح + الانتقال الفوريّ.

## 🎬 على الشاشة

افتح `includes/i18n.php` السطر 10:

```php
function current_lang(): string {
    // 1) ?lang= في الرابط
    // 2) كوكي wc_lang
    // 3) ⚡ Accept-Language من المتصفح ← الفرق الكبير
    // 4) DEFAULT_LANG
}
```

### detect_accept_lang()

```php
"fr-FR,fr;q=0.9,en;q=0.8" → 'fr'
"ar-SA,en;q=0.5"          → 'ar'
"de-DE"                   → null → fallback
```

### تجربة حيّة

افتح أدوات المطوّر → Network → أرسل request مع:
```
Accept-Language: fr-FR
```
وشاهد الصفحة تظهر بالفرنسيّة فوراً.

> «أهم نقطة: الكوكي يُحفظ بعد الكشف. الزيارة التالية لا تحتاج كشف.»

### الفرنسيّة بفضل المتطوّع Lassana 🇸🇳

> «هذي صديق سنغالي راسلني على X وعرض الترجمة الفرنسيّة مجاناً.
> سلسلة 42 ملف ضمن PR واحد. هذا ما يجعل المشاريع المفتوحة سحريّة.»

## 📝 Outro

> «الحلقة القادمة: النشرة البريديّة — كيف ترسل لـ 1000 شخص كل
> يومين تلقائياً.»

---

# الحلقة 9: النشرة البريديّة (SMTP + Cron)

**الهدف**: عرض bootstrap كامل لنظام email يعمل من Hostinger.

## 🎬 على الشاشة

### Mailer.php

```php
Mailer::send($to, $subject, $html, $text);
```

التكوين في `config.local.php`:
```php
'SMTP_HOST'   => 'smtp.hostinger.com',
'SMTP_PORT'   => 465,
'SMTP_USER'   => 'info@wcup2026.org',
'SMTP_PASS'   => '...',
'SMTP_SECURE' => 'ssl',
```

### Digest::buildEmail

افتح `includes/Digest.php` السطر 148:
- يبني رسالة HTML احترافيّة لكل مستخدم
- تحتوي ترتيبه + نقاطه + مباريات اليوم
- زر «إلغاء الاشتراك» موقّع بـ HMAC

### cron/digest.php

```bash
# يُشغّل مرّة يومياً
php /home/USER/.../cron/digest.php
```

السكربت يقرّر بنفسه:
- هل اليوم يوم إرسال؟ (كل DIGEST_EVERY_DAYS)
- هل البطولة لم تنتهِ؟
- يرسل للمشتركين النشطين فقط

## 📝 Outro

> «الحلقة القادمة — الأطول والأهم: النشر التلقائي على X.»

---

# الحلقة 10: النشر التلقائي على X — 4 طبقات

**الهدف**: الحلقة الفلاجشيب — تشرح كل المنظومة بدقّة.

## 🎤 Hook

> «هذي الحلقة ستوريك كيف تبني bot احترافي على X يدير حسابك،
> ينشر بـ AR و EN، يربط بمباريات، أخبار، إحصائيات، وأنت نائم.»

## 🎬 على الشاشة

### الطبقة 1: XPublisher

افتح `includes/XPublisher.php`:
- OAuth 1.0a signing — 80 سطر فقط
- POST `/2/tweets`
- معالجة أخطاء + تسجيل

### الطبقة 2: TweetComposer (محتوى يومي)

5 فترات × 2 لغة = 10 تغريدات/يوم:
- 09:00 morning — مباريات اليوم
- 10:00 countdown — عدّ تنازلي
- 16:00 trivia — سؤال اليوم
- 21:00 evening — نتائج اليوم
- 22:00 stats — هدّافون + بطاقات

### الطبقة 3: MatchTweets (لكل مباراة)

افتح `includes/MatchTweets.php`:
- قبل المباراة بـ 30-75 دقيقة → 2 تغريدة (AR+EN)
- بعد النتيجة + تقرير AI → 2 تغريدة

### الطبقة 4: GroupTweets + NewsTweets

- ترتيب المجموعات بعد كل جولة
- أيّ خبر جديد من RSS

### cron/tweet.php

```cron
*/15 * * * * php /home/USER/.../cron/tweet.php
```

السكربت كل 15 دقيقة يفحص 5 طوابير ويُرسل ما يستحقّ.

## 🎬 Demo: لوحة الإدارة

افتح `admin.php?tab=x`:
- حالة المفاتيح
- خطّة النشر
- زر «انشر الآن» تجريبي
- سجلّ آخر النشر

## 📝 Outro

> «الحلقة القادمة: كيف نحمي الحساب من الإيقاف — RateGuard.»

---

# الحلقة 11: حماية الحساب + RateGuard

**الهدف**: شرح circuit breaker لمنع إيقاف الحساب.

## 🎤 Hook

> «X يوقف الحسابات الـ bot. أنا بنيت 5 طبقات دفاع لتجنّب هذا.»

## 🎬 على الشاشة

افتح `includes/RateGuard.php`:

### الـ 5 طبقات

```
1) سقف ساعة     (8 افتراضي)
2) سقف يوم      (30 افتراضي)
3) فاصل أدنى    (60 ثانية)
4) 429/403      → ⏸️ 1 ساعة
5) 3 فشل متتالٍ  → ⏸️ 24 ساعة
```

### كيف تعمل في الكود

```php
$g = RateGuard::check();
if (!$g['ok']) {
    return ['error' => 'rate_guard:' . $g['reason']];
}
// ... فعل
RateGuard::record($success, $slot, $error);
```

### لوحة الإدارة

افتح `admin.php?tab=x`:
- أشرطة تقدّم بالألوان (أخضر → أصفر → أحمر)
- إيقاف يدوي 1/6/24/72 ساعة
- استئناف فوريّ

## 📝 Outro

> «الحلقة الأخيرة: نشر الموقع على Hostinger ومراقبته.»

---

# الحلقة 12: النشر على Hostinger + المراقبة

**الهدف**: المشاهد ينقل موقعه للإنتاج اليوم.

## 🎬 على الشاشة

### الخطوات (نموذج خطوة-بخطوة)

#### 1. شراء استضافة Hostinger ($3/شهر)
- اختر domain (.org / .com)
- اختر خطة Premium (تدعم Cron)

#### 2. رفع الملفات
- File Manager → `public_html`
- استثناء `cache/` و `data/` (يُنشآن تلقائياً)
- استثناء `config.local.php` (احتفظ به يدوياً)

#### 3. إنشاء قاعدة البيانات (MySQL)
- Hostinger → Databases → Create
- ينشئ المستخدم + كلمة سر
- أضِفها في `config.local.php`

#### 4. تشغيل installer
- زر `wcup2026.org/install.php?token=XXX`
- يُنشئ كل الجداول

#### 5. Cron Jobs
```cron
0 9 * * *     php /home/.../cron/digest.php
*/15 * * * *  php /home/.../cron/tweet.php
```

#### 6. SSL + Domain
- Hostinger → SSL → Free Let's Encrypt
- Force HTTPS

#### 7. مراقبة
- Analytics الـ Hostinger
- `admin.php` لوحة التحكم
- Health endpoint: `wcup2026.org/health.php`

### قائمة فحص قبل الإطلاق

- [ ] الـ keys في `config.local.php` صحيحة
- [ ] ADMIN_PASS_HASH مضبوط
- [ ] DB_ENABLED = true
- [ ] SITE_URL = `https://wcup2026.org`
- [ ] Cron Jobs مُفعّلة
- [ ] SSL مفعّل
- [ ] OG image يعمل (افتح Twitter Card Validator)
- [ ] sitemap.xml يُولّد
- [ ] رسالة test email تصل
- [ ] أوّل تغريدة تجريبيّة من admin تنجح

## 🎤 Outro السلسلة

> «اللي عملته اليوم: منصّة احترافيّة تنافس مواقع شركات بميزانية
> أقل من 10 دولار. كل الكود مفتوح المصدر على GitHub.
> ابدأ مشروعك. خلّي شغفك يتحوّل لمنتج حقيقي.»

---

## 📚 موارد إضافيّة لوصف الفيديوهات

### روابط لتُرفق في كل وصف

```
🌐 الموقع الحيّ:       https://wcup2026.org
💻 GitHub:            https://github.com/salah23222/worldcup2026
📊 Hostinger الإحالة:  [رابط affiliate]
📖 openfootball:      https://github.com/openfootball/worldcup.json
🤖 Claude API:        https://console.anthropic.com
🐦 X Developer:       https://developer.x.com

📑 Timestamps:
0:00 المقدّمة
0:45 الـ Tech Stack
...
```

### Hashtags للتسويق

```
#PHP #كود #تطوير_الويب #كأس_العالم #2026 #FIFAWorldCup26
#OpenSource #برمجة #WebDevelopment #SelfHosted #ClaudeAI
```

---

## 🎬 نصائح إنتاج (لكاميرا واحدة + شاشة)

### الإعداد التقني

| العنصر | التوصية |
|---|---|
| **كاميرا** | ويب كام 1080p (Logitech C920 ممتاز) |
| **مايك** | USB condenser ($30 يكفي) |
| **شاشة** | OBS Studio (مجاني) |
| **محرّر** | DaVinci Resolve (مجاني) |
| **إضاءة** | LED panel أمامي + خلفي خفيف |

### إعداد OBS

- Scene 1: Camera Full
- Scene 2: Screen + Camera (PIP صغير زاوية)
- Scene 3: Screen Full Only
- Scene 4: Camera + Code Editor Split

### قواعد الإلقاء

- **في أوّل 15 ثانية**: عرض النتيجة النهائيّة (hook visual)
- **معدّل القطع**: كل 3-5 ثوانٍ تغيير على الشاشة
- **اللغة**: عربيّة فصحى معاصرة + كلمات تقنيّة إنجليزيّة
- **الإيقاع**: سريع لكن واضح، لا تكرار
- **الـ B-roll**: لقطات من الموقع نفسه أثناء الشرح

### مدّة الحلقة المثلى

- **YouTube**: 12-20 دقيقة (أفضل وقت لـ algorithm)
- **Shorts**: 60 ثانية كملخّصات لكل حلقة (للترويج)

---

## 🎯 خطّة تسويق السلسلة

### مرحلة ما قبل الإطلاق

1. اصنع **trailer** 60 ثانية للحلقات كلّها
2. غرّد عنها على @salah1alhammadi
3. شارك في مجموعات developers على Discord/Telegram

### مرحلة الإطلاق

- **حلقة في الأسبوع** (12 أسبوع متتالٍ)
- **Shorts يومية** من مقاطع كل حلقة
- **Live coding** أسبوعي على Twitch لتعديلات الموقع

### مرحلة ما بعد

- جمع الحلقات في **playlist** واحدة
- أنشئ موقع `course.wcup2026.org` يجمع كل المواد
- بيع PDF dossier ($5) للسكريبتات الكاملة

---

## 🔧 ملحق: مرجع سريع للأوامر

### تشغيل محلياً

```bash
cd C:/xampp/htdocs/worldcup2026
php -S 127.0.0.1:8000
```

### Lint كل الملفات

```bash
for f in includes/*.php; do php -l "$f"; done
```

### تشغيل cron يدوياً للاختبار

```bash
php cron/tweet.php --dry-run --force --slot=manual
php cron/digest.php --dry-run
```

### تنظيف الكاش

```bash
rm -rf cache/page/*.html cache/*.json
```

### نسخ احتياطي

```bash
mysqldump -u USER -p DB_NAME > backup_$(date +%Y%m%d).sql
tar czf code_$(date +%Y%m%d).tar.gz includes/ templates/ assets/ *.php
```

---

## 🏁 الخلاصة

هذي السلسلة تخدم:

| المشاهد | الفائدة |
|---|---|
| **مبتدئ PHP** | يتعلّم بناء مشروع كامل من الصفر |
| **مطوّر متوسّط** | يرى أنماط حقيقيّة (caching, OAuth, AI) |
| **صاحب فكرة** | يفهم كيف يُطلق منتجه دون استثمار ضخم |
| **مهتم بـ X bots** | يحصل على blueprint جاهز للنشر التلقائي |
| **عاشق كرة قدم** | يستخدم الموقع حصّة من تجربته للبطولة |

**الرسالة المركزيّة**:
> «المنتج العظيم ليس عن أحدث إطار. المنتج العظيم يحلّ مشكلة حقيقيّة
> بأبسط الأدوات. PHP خام + بضع ساعات يوميّاً + شغف = منصّة كاملة.»

---

**هذا هو السكريبت الكامل. تستطيع تصوير الحلقات بالترتيب، أو تخصيص
أيّ حلقة لتكون فيديو مستقلّ.**

🎬 **في صحّة الإطلاق!**
