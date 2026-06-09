<?php
/**
 * cron/refs.php — تحديث قائمة الحكام من API-Football (مرّة كل 12 ساعة).
 * ============================================================
 * يمسح كاش af-fixtures.json ويُعيد جلبه من API-Football.
 * النتيجة: أيّ حكم أعلنته FIFA يظهر تلقائياً على match.php بعد الجلب.
 *
 * التشغيل:
 *   - من cron-job.org مع جدول "Every 6 hours" مثلاً
 *   - أو عبر المتصفح: /cron/refs.php?token=INSTALL_TOKEN
 *
 * استهلاك API-Football: 1 طلب فقط لكل تشغيل (آمن جداً ضمن الـ100/يوم).
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$cli = (PHP_SAPI === 'cli');
if (!$cli) {
    $tok = (string)($_GET['token'] ?? '');
    if (!defined('INSTALL_TOKEN') || INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $tok)) {
        http_response_code(403); exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

echo "[refs] " . date('Y-m-d H:i:s') . " Asia/Dubai\n";

// تأكّد أن LiveService يستطيع الجلب
if (!class_exists('LiveService') || !method_exists('LiveService', 'refereeFor')) {
    echo "[refs] LiveService::refereeFor not available — upload latest LiveService.php\n";
    exit(1);
}

// ──────────────────────────────────────────────────────────
// (1) جلب Wikipedia 2026 World Cup officials (52 حكم + مساعدين)
// ──────────────────────────────────────────────────────────
if (class_exists('RefereesFetcher')) {
    echo "[refs] fetching Wikipedia officials list...\n";
    $wiki = RefereesFetcher::refresh();
    echo "[refs] Wikipedia → " . count($wiki) . " referees parsed (auto-assistants enabled)\n";
} else {
    echo "[refs] RefereesFetcher class missing — upload latest includes/RefereesFetcher.php\n";
}

// ──────────────────────────────────────────────────────────
// (2) امسح كاش API-Football لإجبار طلب جديد
// ──────────────────────────────────────────────────────────
$cacheFile = rtrim(CACHE_DIR, '/') . '/af-fixtures.json';
@unlink($cacheFile);
echo "[refs] API-Football cache cleared\n";

// أعد بناء الخريطة (طلب API واحد لكل البطولة)
// الجلب يتم تلقائياً عند استدعاء refereeFor() على أيّ مباراة
$all = DataService::allMatches();
$found = 0; $total = 0;
foreach ($all as $m) {
    $total++;
    $t1 = trim($m['team1'] ?? '');
    $t2 = trim($m['team2'] ?? '');
    if ($t1 === '' || $t2 === '' || !is_real_team($t1) || !is_real_team($t2)) continue;
    $r = LiveService::refereeFor($m);
    if ($r !== null) {
        $found++;
        echo "[refs] ✓ {$t1} vs {$t2} → {$r}\n";
    }
}
echo "\n[done] {$found}/{$total} matches have assigned referees.\n";
exit(0);
