<?php
/**
 * config.php
 * ============================================================
 * كل إعدادات الموقع في مكان واحد.
 * عدّل هنا فقط — لا تعدّل في الملفات الأخرى.
 * ============================================================
 */

// منع الوصول المباشر للملفات الداخلية
if (!defined('WC2026')) {
    define('WC2026', true);
}

// ============================================================
//  الأسرار وإعدادات البيئة
//  لا تضع أي سرّ (مفاتيح API / كلمات سر) في هذا الملف — فهو متتبَّع في Git.
//  ضعها في config.local.php (غير متتبَّع) أو في متغيّرات البيئة.
//  انسخ config.local.example.php إلى config.local.php واملأه.
// ============================================================
$__local_file = __DIR__ . '/config.local.php';
$__local = is_file($__local_file) ? require $__local_file : [];
if (!is_array($__local)) { $__local = []; }

if (!function_exists('cfg_secret')) {
    /** يقرأ إعداداً من متغيّر البيئة، ثم من config.local.php، ثم الافتراضي. */
    function cfg_secret(string $key, $default, array $local) {
        $env = getenv($key);
        if ($env !== false && $env !== '') { return $env; }
        return array_key_exists($key, $local) ? $local[$key] : $default;
    }
}

// ---------- معلومات الموقع ----------
define('SITE_NAME_AR', 'كأس العالم 2026');
define('SITE_NAME_EN', 'World Cup 2026');
define('SITE_NAME_FR', 'Coupe du Monde 2026');
define('SITE_TAGLINE_AR', 'كندا · المكسيك · الولايات المتحدة');
define('SITE_TAGLINE_EN', 'Canada · Mexico · USA');
define('SITE_TAGLINE_FR', 'Canada · Mexique · États-Unis');
// من config.local.php / البيئة. فارغ = روابط نسبية. للإنتاج: https://wcup2026.org
define('SITE_URL', (string)cfg_secret('SITE_URL', '', $__local));

// بريد التواصل (يستقبل رسائل نموذج «تواصل معنا/الرعاية»).
define('CONTACT_EMAIL', (string)cfg_secret('CONTACT_EMAIL', 'info@wcup2026.org', $__local));
// رقم التواصل المباشر (اتصال/واتساب) — يظهر في صندوق التأكيد بعد الإرسال.
// بصيغة دولية مثل +9665XXXXXXXX. فارغ = لا يُعرض رقم. ضعه في config.local.php.
define('CONTACT_PHONE', (string)cfg_secret('CONTACT_PHONE', '', $__local));

// رموز توثيق ملكية الموقع (من Google Search Console و Bing Webmaster).
define('GOOGLE_SITE_VERIFICATION', (string)cfg_secret('GOOGLE_SITE_VERIFICATION', '', $__local));
define('BING_SITE_VERIFICATION', (string)cfg_secret('BING_SITE_VERIFICATION', '', $__local));

// ---------- مصدر البيانات (مجاني 100% — بدون مفتاح API) ----------
// openfootball: بيانات ملكية عامة، بدون حد طلبات، بدون تسجيل
define('DATA_SOURCE', 'https://raw.githubusercontent.com/openfootball/worldcup.json/master/2026/worldcup.json');

// ---------- إعدادات الـ Cache (التخزين المؤقت) ----------
// المسار الذي يكتب فيه PHP ملفات الكاش — تأكد أن صلاحياته 755
define('CACHE_DIR', __DIR__ . '/../cache/');
// مدة صلاحية الكاش بالثواني (300 = 5 دقائق). أثناء المباريات قللها لـ 60.
define('CACHE_TTL', 300);
// مهلة الاتصال بالمصدر بالثواني (قصيرة: طلب واحد فقط يجلب، والبقية تُقدَّم لها النسخة المخزّنة)
define('FETCH_TIMEOUT', 5);
// كاش الصفحات الكامل (ثواني): يخدم الزوّار/الزواحف من HTML جاهز بلا تشغيل PHP →
// يتحمّل ضغطاً هائلاً على الاستضافة المشتركة. 0 = تعطيل. المستخدمون المسجّلون لا يُخزَّنون أبداً.
define('PAGE_CACHE_TTL', 60);

// ---------- التوقيت ----------
// التوقيت الذي تُعرض به مواعيد المباريات للزائر
date_default_timezone_set('Asia/Dubai');   // توقيت الإمارات (UTC+4)
define('DISPLAY_TIMEZONE', 'Asia/Dubai');

// ---------- اللغة ----------
// اللغة الافتراضية: 'ar' أو 'en'
define('DEFAULT_LANG', 'ar');

// ---------- (اختياري) API-Football للنتائج اللحظية ----------
// مجاني محدود: 100 طلب/يوم. سجّل في dashboard.api-football.com واحصل على مفتاح.
// اتركه فارغاً = الموقع يعمل بالكامل على openfootball فقط (ولا ينكسر إطلاقاً).
// ضعه وقت البطولة فقط، واحذفه بعدها.
define('APIFOOTBALL_KEY', (string)cfg_secret('APIFOOTBALL_KEY', '', $__local));
define('APIFOOTBALL_HOST', 'v3.football.api-sports.io');
// معرّف بطولة كأس العالم في API-Football (ثابت = 1)
define('APIFOOTBALL_LEAGUE', 1);
define('APIFOOTBALL_SEASON', 2026);
// كاش النتائج اللحظية بالثواني (قصير لأنها تتغيّر بسرعة)
define('LIVE_CACHE_TTL', 60);

// ---------- قاعدة البيانات (للحسابات: اسم مستخدم + كلمة سر) ----------
// ضع بيانات MySQL هنا. على XAMPP عادةً المستخدم root.
// إن كان لـroot كلمة سر، ضعها في DB_PASS. سيُنشئ النظام الجداول تلقائياً.
// بيانات MySQL — من config.local.php / البيئة (لا تكتب كلمة السر هنا).
define('DB_HOST', (string)cfg_secret('DB_HOST', '127.0.0.1', $__local));
define('DB_NAME', (string)cfg_secret('DB_NAME', 'worldcup2026', $__local));
define('DB_USER', (string)cfg_secret('DB_USER', 'root', $__local));
define('DB_PASS', (string)cfg_secret('DB_PASS', '', $__local));
define('DB_CHARSET', 'utf8mb4');
// true لتفعيل نظام الحسابات والدخول (يحتاج اتصال DB صحيحاً أعلاه)
define('DB_ENABLED', filter_var(cfg_secret('DB_ENABLED', false, $__local), FILTER_VALIDATE_BOOLEAN));
// رمز سرّي يحمي أداة التثبيت install.php من التشغيل العلني.
define('INSTALL_TOKEN', (string)cfg_secret('INSTALL_TOKEN', '', $__local));

// ---------- البريد (SMTP لإرسال النشرة الدورية للمشتركين) ----------
// ضع بيانات صندوق بريدك (info@wcup2026.org) في config.local.php / البيئة.
// فارغ = يُستخدم mail() المدمجة بدل SMTP (وصول أضعف). للإنتاج استخدم SMTP.
define('SMTP_HOST',   (string)cfg_secret('SMTP_HOST', '', $__local));        // مثل smtp.hostinger.com
define('SMTP_PORT',   (int)   cfg_secret('SMTP_PORT', 465, $__local));        // 465=ssl أو 587=tls
define('SMTP_USER',   (string)cfg_secret('SMTP_USER', '', $__local));         // = البريد الكامل
define('SMTP_PASS',   (string)cfg_secret('SMTP_PASS', '', $__local));         // كلمة سر صندوق البريد
define('SMTP_SECURE', (string)cfg_secret('SMTP_SECURE', 'ssl', $__local));    // 'ssl' أو 'tls'
// سرّ توقيع روابط إلغاء الاشتراك (لا يُكشف للمستخدم). الأفضل ضبطه صراحةً في config.local.php.
// إن لم يُضبط: نشتقّه من INSTALL_TOKEN ثم DB_PASS (قيمة خاصّة بكل تثبيت) — لا سرّ عام مكتوب
// في الكود (كان سابقاً 'wc26-mail-secret' وهو متوقَّع علناً ويسمح بتزوير روابط إلغاء الاشتراك).
define('MAIL_SECRET', (string)cfg_secret('MAIL_SECRET',
    (INSTALL_TOKEN !== '' ? INSTALL_TOKEN
        : (DB_PASS !== '' ? hash('sha256', 'wc26-mail|' . DB_PASS) : '')),
    $__local));
// كل كم يوم تُرسَل النشرة (افتراضي 2 = كل يومين). تتوقّف تلقائياً بعد النهائي.
define('DIGEST_EVERY_DAYS', (int)cfg_secret('DIGEST_EVERY_DAYS', 2, $__local));

// حدّ المعدّل: مصدر IP الزائر. افتراضياً false = نعتمد REMOTE_ADDR فقط (لا يُزوّره العميل)،
// وهو الصحيح حين يحمل REMOTE_ADDR عنوان الزائر الحقيقي (Hostinger/LiteSpeed). فعّله (true)
// فقط إن كانت حافتُك تفرض X-Forwarded-For/CF-Connecting-IP موثوقاً وتمسح أي ترويسة من العميل،
// وإلّا فتفعيله يفتح ثغرة تجاوز الحدّ عبر تزوير الترويسة.
define('RATE_LIMIT_TRUST_FORWARDED', (bool)cfg_secret('RATE_LIMIT_TRUST_FORWARDED', false, $__local));

// ---------- لوحة التحكم (admin.php) ----------
// المُفضَّل: ADMIN_PASS_HASH (بصمة bcrypt) — حتى لو سُرّب ملف الإعدادات لا تُكشف كلمة السر.
// لتوليد هاش جديد من كلمة سرّ تختارها:
//   php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_BCRYPT, ['cost'=>12]).PHP_EOL;"
// التوافق الرجعي: ADMIN_PASS كنص عادي يُقبل أيضاً (لكنه أقلّ أماناً).
// فارغ كلاهما = اللوحة معطّلة تماماً.
define('ADMIN_PASS_HASH', (string)cfg_secret('ADMIN_PASS_HASH', '', $__local));
define('ADMIN_PASS',      (string)cfg_secret('ADMIN_PASS',      '', $__local));

// ---------- المحتوى الذكي (Claude API — اختياري) ----------
// ضع مفتاح sk-ant-... لتفعيل المعاينات/الملخصات بالذكاء الاصطناعي (عربي + إنجليزي).
// فارغ = الميزة معطّلة والموقع يعمل كالمعتاد. تُولّد مرة واحدة لكل مباراة وتُخزّن.
define('CLAUDE_API_KEY', (string)cfg_secret('CLAUDE_API_KEY', '', $__local));
define('AI_MODEL', 'claude-haiku-4-5');       // الأرخص؛ يكفي تماماً للمعاينات
define('AI_MAX_TOKENS', 700);
define('AI_TIMEOUT', 10);   // مهلة قصيرة: تمنع تعليق عمّال php-fpm وتفادي 504 على الاستضافة المشتركة
// بوّابة التفعيل: لا يعمل الذكاء الاصطناعي (ولا يصرف أي مبلغ) قبل هذا التاريخ
// حتى لو وُضع المفتاح أبكر. الافتراضي = 3 أيام قبل افتتاح البطولة (11 يونيو 2026).
define('AI_ACTIVATE_FROM', (string)cfg_secret('AI_ACTIVATE_FROM', '2026-06-08', $__local));

// ---------- (اختياري) بوت تيليجرام ----------
// فارغ = البوت معطّل تماماً (نقطة الويبهوك ترد 503 ولا تتصل بأي شيء).
// لتفعيله: أنشئ بوتاً عبر @BotFather، ضع التوكن هنا، واضبط ويبهوك يشير إلى
// https://wcup2026.org/api/telegram.php مع secret_token = TELEGRAM_WEBHOOK_SECRET.
define('TELEGRAM_BOT_TOKEN',      (string)cfg_secret('TELEGRAM_BOT_TOKEN', '', $__local));
define('TELEGRAM_WEBHOOK_SECRET', (string)cfg_secret('TELEGRAM_WEBHOOK_SECRET', '', $__local));

// ---------- الرعاة (اختياري) ----------
// أضِف رعاتك هنا. كل راعٍ: ['name'=>'الاسم', 'url'=>'https://...', 'logo'=>'/assets/img/sponsors/x.png']
// 'logo' اختياري؛ إن غاب يُعرض الاسم نصّاً. اترك المصفوفة فارغة لإظهار خانات «شعارك هنا».
define('SPONSORS', [
    // ['name' => 'شريك ذهبي', 'url' => 'https://example.com', 'logo' => ''],
]);
// عدد الخانات الفارغة التي تظهر كدعوة للرعاية عندما لا يوجد رعاة
define('SPONSOR_PLACEHOLDERS', 4);

// ---------- الأخبار (RSS مجاني — مصدران مدموجان) ----------
// 1) Bing News: صور حقيقية + رابط مباشر + ملخّص.  2) Google News: تغطية أوسع.
// يُدمج المصدران وتُزال الأخبار المكرّرة.
define('NEWS_RSS_AR', 'https://www.bing.com/news/search?q=%D9%83%D8%A3%D8%B3+%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%85+2026&format=RSS&setlang=ar&cc=SA');
define('NEWS_RSS_EN', 'https://www.bing.com/news/search?q=FIFA+World+Cup+2026&format=RSS&setlang=en&cc=US');
define('NEWS_RSS_AR2', 'https://news.google.com/rss/search?q=%D9%83%D8%A3%D8%B3+%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%85+2026&hl=ar&gl=SA&ceid=SA:ar');
define('NEWS_RSS_EN2', 'https://news.google.com/rss/search?q=FIFA+World+Cup+2026&hl=en-US&gl=US&ceid=US:en');
define('NEWS_CACHE_TTL', 900);   // كاش الأخبار 15 دقيقة
define('NEWS_MAX_ITEMS', 30);

// ---------- الوضع ----------
// true أثناء التطوير لإظهار الأخطاء، false عند النشر
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // لا تُظهر الأخطاء للزوّار، لكن سجّلها في ملف داخلي للتشخيص.
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    @ini_set('error_log', __DIR__ . '/../logs/php-error.log');
}
