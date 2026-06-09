<?php
/**
 * cron/refs-check.php — صفحة تشخيص سريع للتأكّد أن نظام الحكام يعمل على الإنتاج.
 * ============================================================
 * الاستخدام:
 *   https://wcup2026.org/cron/refs-check.php?token=INSTALL_TOKEN
 *
 * تعرض:
 *   ✓/✗ هل RefereesFetcher محمّل؟
 *   ✓/✗ هل BUILTIN_REFEREES يحوي 4 تعيينات؟
 *   ✓/✗ هل Wikipedia cache موجود؟
 *   ✓/✗ ما الذي تُعيده officialsFor() للمباريات الأربعة؟
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

echo "═══════════════════════════════════════════════════\n";
echo "  🔍 فحص نظام الحكام — " . date('Y-m-d H:i:s') . " Asia/Dubai\n";
echo "═══════════════════════════════════════════════════\n\n";

// (1) فحص الفئات
echo "[1] الفئات المحمّلة:\n";
$checks = [
    'LiveService'      => class_exists('LiveService'),
    'RefereesFetcher'  => class_exists('RefereesFetcher'),
    'DataService'      => class_exists('DataService'),
];
foreach ($checks as $cls => $ok) {
    echo "    " . ($ok ? '✓' : '✗') . " {$cls}\n";
}

// (2) فحص BUILTIN
echo "\n[2] BUILTIN_REFEREES (مُضمَّن في الكود):\n";
$ref = new ReflectionClass('LiveService');
if ($ref->hasConstant('BUILTIN_REFEREES')) {
    $builtin = $ref->getConstant('BUILTIN_REFEREES');
    echo "    ✓ موجود — " . count($builtin) . " تعيينات\n";
    foreach ($builtin as $k => $v) {
        echo "      • {$k} → " . ($v['main']['name'] ?? '?') . "\n";
    }
} else {
    echo "    ✗ غير موجود — ارفع آخر LiveService.php\n";
}

// (3) فحص ملفّ يدوي
echo "\n[3] data/referees-manual.json:\n";
$mf = __DIR__ . '/../data/referees-manual.json';
echo "    " . (is_file($mf) ? '✓ موجود (' . filesize($mf) . ' بايت)' : '○ غير موجود (هذا OK — BUILTIN يكفي)') . "\n";

// (4) فحص Wikipedia cache
echo "\n[4] data/cache/wiki-referees.json:\n";
$wf = (defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../data/cache') . '/wiki-referees.json';
if (is_file($wf)) {
    $age = time() - filemtime($wf);
    $hrs = round($age / 3600, 1);
    echo "    ✓ موجود — عمره {$hrs} ساعة\n";
} else {
    echo "    ○ غير موجود — سيُجلب عند أوّل طلب\n";
}

// (5) اختبار حقيقي
echo "\n[5] اختبار officialsFor() للمباريات الأربعة:\n";
$cases = [
    ['Mexico',        'South Africa'],
    ['South Korea',   'Czech Republic'],
    ['Canada',        'Bosnia and Herzegovina'],
    ['United States', 'Paraguay'],
    ['Argentina',     'France'],  // ضابط — لا حكم له
];
foreach ($cases as [$t1, $t2]) {
    $o = LiveService::officialsFor(['team1' => $t1, 'team2' => $t2]);
    $main = $o['main']['name']       ?? '—';
    $flag = $o['main']['flag']       ?? '';
    $cn   = $o['main']['country_ar'] ?? '';
    $na   = count($o['assistants']);
    printf("    %s %s vs %s\n",
        $main !== '—' ? '✓' : '○', $t1, $t2);
    printf("        🧑‍⚖️ %s%s%s\n",
        $main, $cn ? " ({$cn})" : '', $flag ? " [{$flag}]" : '');
    if ($na > 0) {
        foreach ($o['assistants'] as $a) {
            printf("        🚩 %s%s\n", $a['name'], !empty($a['country_ar']) ? " ({$a['country_ar']})" : '');
        }
    }
}

echo "\n═══════════════════════════════════════════════════\n";
echo "  ✅ انتهى الفحص\n";
echo "═══════════════════════════════════════════════════\n";
