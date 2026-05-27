# تقرير المراجعة الأمنية — Security Audit

**المشروع / Project:** World Cup 2026 (open-source)
**التاريخ / Date:** 2026-05-27
**النطاق / Scope:** فحص ثابت كامل للكود (99 ملف PHP + 12 ملف JS) — Full static review.
**المنهجية / Methodology:** فئات ثغرات معيارية: حقن، XSS/DOM، تنفيذ كود، deserialization، LFI، SSRF، CSRF، IDOR، المصادقة، تسريب الأسرار.

---

## 1) الخلاصة — Verdict

> **لا توجد ثغرات حرجة أو عالية أو متوسطة. المستودع آمن للنشر العلني على GitHub.**
> No critical/high/medium vulnerabilities. The repository is safe for public release.

تأكيدات مهمة قبل النشر — Pre-publish checks:
- ✅ **لا أسرار في تاريخ Git** (لا مفاتيح API، لا كلمات سر، لا مفاتيح خاصة). No secrets anywhere in git history.
- ✅ `includes/config.local.php` ضمن `.gitignore` ولم يُرفع أبداً. Never tracked.
- ✅ ملف المثال `config.local.example.php` لا يحوي إلا قيماً افتراضية غير حسّاسة.

---

## 2) نتائج الفحص حسب الفئة — Findings by category

| # | الفئة / Category | الحالة / Status | الدليل / Evidence |
|---|---|---|---|
| 1 | SQL Injection | ✅ آمن | Prepared statements في كل الوصول للقاعدة (`Auth.php`, `Predictions.php`). |
| 2 | XSS (stored/reflected) | ✅ آمن | `e()` = `htmlspecialchars(ENT_QUOTES)`؛ أسماء العرض تُنقّى عند التسجيل (`Auth.php:93`). |
| 3 | DOM XSS (JS) | ✅ آمن | `innerHTML` تُستعمل لاستعادة محتوى محفوظ/تفريغ/HTML من نفس الأصل مُهرَّب بالخادم. |
| 4 | تنفيذ كود/أوامر | ✅ غير موجود | لا `eval`/`system`/`exec`؛ `->exec()` هي PDO فقط. |
| 5 | Deserialization | ✅ غير موجود | صفر استخدام لـ `unserialize`. |
| 6 | LFI / تضمين ملفات | ✅ آمن | `require $sectionFile` في `admin.php` مقيّد بقائمة بيضاء `$tabs`. |
| 7 | SSRF | ✅ محميّ | `http_host_is_public()` يحجب localhost/الشبكات الخاصة/`169.254`؛ cURL مقيّد بـ HTTP/HTTPS. |
| 8 | CSRF | ✅ آمن | session token + double-submit cookie مع `hash_equals`؛ فحص Origin في `api/contact.php`. |
| 9 | IDOR / صلاحيات | ✅ آمن | `Predictions::save()` يأخذ `user_id` من الجلسة لا من الطلب. |
| 10 | المصادقة/كلمات السر | ✅ آمن | `password_hash` bcrypt cost 12، تجديد الجلسة، rate limiting. |
| 11 | تسريب أسرار | ✅ آمن | الأسرار في `config.local.php` (gitignored) أو متغيّرات البيئة فقط. |
| 12 | أدوات التثبيت | ✅ Fail-closed | `install.php`/`db_selftest.php` ترفض 403 عند `INSTALL_TOKEN` فارغ. |
| 13 | GitHub Workflows | ✅ لا يوجد | لا مجلد workflows. |

---

## 3) إصلاحات طُبّقت — Applied fixes (Before / After)

### A) سرّ توقيع روابط إلغاء الاشتراك — Predictable mail-signing secret  · خطورة: منخفضة

كان السرّ يرجع لقيمة عامة معروفة عند غياب الإعداد، ما يسمح نظرياً بتزوير روابط إلغاء اشتراك الآخرين.

**قبل — Before** (`includes/config.php`):
```php
define('MAIL_SECRET', (string)cfg_secret('MAIL_SECRET',
    (INSTALL_TOKEN !== '' ? INSTALL_TOKEN : 'wc26-mail-secret'), $__local));
```

**بعد — After:**
```php
define('MAIL_SECRET', (string)cfg_secret('MAIL_SECRET',
    (INSTALL_TOKEN !== '' ? INSTALL_TOKEN
        : (DB_PASS !== '' ? hash('sha256', 'wc26-mail|' . DB_PASS) : '')),
    $__local));
```
لا قيمة سرّية عامة في المستودع؛ المفتاح يُشتقّ من سرّ خاصّ بكل تثبيت. (الأفضل ضبط `MAIL_SECRET` صراحةً في `config.local.php`.)

### B) ترويسات أمان HTTP — CSP / Permissions-Policy  · خطورة: منخفضة (دفاع في العمق)

لم تكن هناك سياسة `Content-Security-Policy` للصفحات (فقط `frame-ancestors` للودجت).

**قبل — Before** (`.htaccess`): ترويسات `X-Content-Type-Options` + `X-Frame-Options` + `Referrer-Policy` فقط.

**بعد — After:** أُضيفت `Permissions-Policy` و `Content-Security-Policy`:
```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline';
  style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;
  font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:;
  connect-src 'self'; object-src 'none'; base-uri 'self';
  form-action 'self'; frame-ancestors 'self'
```
تحقّقنا أنها **لا تكسر الموقع**: لا iframe خارجي، كل طلبات JS نفس الأصل، الموارد الخارجية الوحيدة هي Google Fonts و`flagcdn`/صور الأخبار (مغطّاة). الودجت يحتفظ بـ `frame-ancestors *`.

---

## 4) توصيات (لم تُطبَّق تلقائياً) — Recommendations (not auto-applied)

| البند | الوصف | السبب في عدم التطبيق الآن |
|---|---|---|
| **C. حذف أدوات التثبيت بعد الإعداد** | احذف `install.php` و `db_selftest.php` في الإنتاج بعد نجاح التثبيت. | محميّة بتوكن ومطلوبة لتجربة الإعداد في مشروع مفتوح المصدر. |
| **D. نافذة DNS-rebinding في `http_get()`** | ثبّت الـ IP الذي تحقّق منه `http_host_is_public()` عبر `CURLOPT_RESOLVE`، أو أعد التحقق بعد كل redirect. | تغيير في مسار الجلب الخارجي؛ يُفضَّل اختباره بعناية قبل النشر. خطورته نظرية ومنخفضة جداً. |
| **E. تشديد CSP** | استبدال `'unsafe-inline'` بـ nonce لكل سكربت/نمط inline. | يتطلّب إعادة هيكلة طبقة العرض؛ مرحلة لاحقة. |
| **F. حماية nginx** | على الإنتاج (nginx) أضف قواعد تكافئ `.htaccess` لحجب الملفات الحساسة (`config*.php`, dotfiles, `cache/`, `logs/`). | `.htaccess` يعمل على Apache فقط (مناسب لـ XAMPP). |

---

## 5) الملفات المعدّلة — Changed files

- `includes/config.php` — إصلاح A.
- `.htaccess` — إصلاح B.
- `sw.js` — رفع كاش PWA إلى `wc2026-v5`.
- `CHANGELOG.md` — إصدار `1.0.1`.
- `docs/SECURITY-AUDIT.md` — هذا التقرير.
