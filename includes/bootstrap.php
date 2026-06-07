<?php
/**
 * bootstrap.php
 * ============================================================
 * ملف واحد يُحمّل كل ما يحتاجه أي صفحة.
 * كل صفحة تبدأ بـ: require __DIR__ . '/includes/bootstrap.php';
 * ============================================================
 */
define('WC2026', true);

// التقط أي إخراج عارض من ملفات الإعداد (BOM/مسافة قبل <?php) في مخزّن مؤقت،
// حتى تبقى الترويسات (Content-Type، CSP، إلخ) قابلة للضبط ولا تنكسر.
ob_start();

require __DIR__ . '/config.php';
require __DIR__ . '/security.php';
security_init();
require __DIR__ . '/i18n.php';
require __DIR__ . '/teams_ar.php';
require __DIR__ . '/countries.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/DataService.php';
require __DIR__ . '/ArchiveService.php';
require __DIR__ . '/LiveService.php';
require __DIR__ . '/Standings.php';
require __DIR__ . '/Stats.php';
require __DIR__ . '/Bracket.php';
require __DIR__ . '/Referees.php';
require __DIR__ . '/TeamInfo.php';
require __DIR__ . '/Scorers.php';
require __DIR__ . '/Rankings.php';
require __DIR__ . '/Predictions.php';
require __DIR__ . '/News.php';
require __DIR__ . '/Stickers.php';
require __DIR__ . '/Stadiums.php';
require __DIR__ . '/AiContent.php';
require __DIR__ . '/RateLimiter.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Auth.php';
// ميزات جديدة (تُحمّل بعد تبعياتها: DataService/Predictions/AiContent/RateLimiter/Database)
require __DIR__ . '/Qahr.php';
require __DIR__ . '/Cards.php';
require __DIR__ . '/Leagues.php';
require __DIR__ . '/Polls.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/AccountMail.php';
require __DIR__ . '/PasswordReset.php';
require __DIR__ . '/Referrals.php';
require __DIR__ . '/Digest.php';
require __DIR__ . '/RateGuard.php';
require __DIR__ . '/Hashtags.php';
require __DIR__ . '/XPublisher.php';
require __DIR__ . '/TweetComposer.php';
require __DIR__ . '/MatchTweets.php';
require __DIR__ . '/GroupTweets.php';
require __DIR__ . '/NewsTweets.php';
require __DIR__ . '/Admin.php';

// التقط ?ref=username من أيّ صفحة وضع الكوكي (قبل أي إخراج)
Referrals::captureFromRequest();

require __DIR__ . '/PageCache.php';

/** اختصار لتضمين قالب من مجلد templates */
function tpl(string $name): void {
    // متغيّرات الصفحة (تُضبَط في الصفحة قبل tpl) يجب أن تكون مرئية داخل القالب.
    // الصفحة هي نقطة الدخول → متغيّراتها عامّة، فنستوردها هنا صراحةً.
    global $page_title, $page_desc, $seo_type, $page_image;
    $f = __DIR__ . '/../templates/' . $name . '.php';
    if (is_file($f)) require $f;
}

// كاش الصفحات: يخدم النسخة المخزّنة فوراً (إصابة) أو يلتقط الإخراج لحفظه (إخفاق).
// آخر سطر في bootstrap → قبل أي إخراج من الصفحة.
PageCache::begin();
