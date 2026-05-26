<?php
/**
 * sitemap.php — خريطة موقع XML ديناميكية لمحركات البحث.
 * تُخرج كل الصفحات + صفحات المباريات، مع بدائل اللغة (hreflang).
 * تُخدّم أيضاً على /sitemap.xml عبر إعادة كتابة في .htaccess.
 */
require __DIR__ . '/includes/bootstrap.php';

// أزل أي إخراج عارض (BOM/مسافة) قبل ضبط الترويسة وإخراج XML نظيف.
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/xml; charset=utf-8');

$base = base_url();

/** صفحات ثابتة: [المسار, الأولوية, معدل التغيّر] */
$pages = [
    ['index.php',     '1.0', 'hourly'],
    ['matches.php',   '0.9', 'hourly'],
    ['news.php',      '0.8', 'hourly'],
    ['groups.php',    '0.8', 'daily'],
    ['knockout.php',  '0.8', 'daily'],
    ['topscorers.php','0.7', 'daily'],
    ['stats.php',     '0.7', 'daily'],
    ['bookings.php',  '0.6', 'daily'],
    ['referees.php',  '0.5', 'monthly'],
    ['teams.php',     '0.7', 'weekly'],
    ['stadiums.php',  '0.6', 'monthly'],
    ['map.php',       '0.6', 'monthly'],
    ['fanguide.php',  '0.7', 'monthly'],
    ['predict.php',   '0.9', 'daily'],
    ['bracket.php',   '0.8', 'weekly'],
    ['leaderboard.php','0.8','hourly'],
    ['stickers.php',  '0.7', 'weekly'],
    ['trivia.php',    '0.7', 'daily'],
    ['archive.php',   '0.6', 'monthly'],
];

// صفحات المباريات
$matchUrls = [];
foreach (DataService::allMatches() as $m) {
    $matchUrls[] = ['match.php?id=' . (int)($m['_index'] ?? 0), '0.6', 'daily'];
}
// صفحات الأرشيف لكل سنة
$archiveUrls = [];
if (class_exists('ArchiveService')) {
    foreach (ArchiveService::years() as $y) {
        $archiveUrls[] = ['archive.php?year=' . (int)$y, '0.6', 'monthly'];
    }
}

// صفحات الحكّام التفصيلية
$refUrls = [];
if (class_exists('Referees')) {
    foreach (Referees::all() as $i => $r) {
        $refUrls[] = ['referee.php?i=' . (int)$i, '0.4', 'monthly'];
    }
}

// صفحات المنتخبات الـ48
$teamUrls = [];
foreach (array_keys(DataService::allTeams()) as $teamEn) {
    $teamUrls[] = ['team.php?team=' . rawurlencode($teamEn), '0.6', 'weekly'];
}

$all = array_merge($pages, $archiveUrls, $matchUrls, $refUrls, $teamUrls);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
   . 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

foreach ($all as [$path, $priority, $freq]) {
    $sep = (strpos($path, '?') !== false) ? '&' : '?';
    $arUrl = $base . '/' . $path . $sep . 'lang=ar';
    $enUrl = $base . '/' . $path . $sep . 'lang=en';
    echo "  <url>\n";
    echo "    <loc>" . e($arUrl) . "</loc>\n";
    echo '    <xhtml:link rel="alternate" hreflang="ar" href="' . e($arUrl) . '"/>' . "\n";
    echo '    <xhtml:link rel="alternate" hreflang="en" href="' . e($enUrl) . '"/>' . "\n";
    echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . e($arUrl) . '"/>' . "\n";
    echo "    <changefreq>" . $freq . "</changefreq>\n";
    echo "    <priority>" . $priority . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
