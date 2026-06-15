<?php
/**
 * cron/deploy.php — مزامنة إحصائيات FIFA من GitHub إلى الإنتاج (بلا git ولا hPanel).
 * ============================================================
 * يكمّل سلسلة التحديث الآلي للتقارير:
 *   جهازك (يستخرج + git push) → GitHub → هذا السكربت (يسحب assets/fifa) → الموقع محدَّث.
 * يعمل على أي استضافة: يجلب ملفّات assets/fifa/*.json عبر HTTP من GitHub (الريبو عام)
 * ويكتب الجديد/المتغيّر فقط. لا يحتاج git ولا exec ولا لوحة Hostinger.
 *
 * أضِفه في cron-job.org كل ساعة (مثل باقي مهامّك):
 *   /cron/deploy.php?token=INSTALL_TOKEN
 * محميّ بالتوكن. أمان: يكتب فقط ملفّات بنمط hash.json داخل assets/fifa (لا تجاوز مسار).
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

if (PHP_SAPI !== 'cli') {
    $tok = (string)($_GET['token'] ?? '');
    if (!defined('INSTALL_TOKEN') || INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $tok)) {
        http_response_code(403); exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

const GH_API = 'https://api.github.com/repos/salah23222/worldcup2026/contents/assets/fifa';

$listJson = http_get(GH_API, ['timeout' => 15, 'ua' => 'wcup2026-deploy', 'redirects' => true]);
if ($listJson === null) { echo "GitHub list unreachable\n"; exit; }
$files = json_decode($listJson, true);
if (!is_array($files)) { echo "GitHub list invalid: " . substr((string)$listJson, 0, 200) . "\n"; exit; }

$dir = __DIR__ . '/../assets/fifa';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$added = 0; $skip = 0;
foreach ($files as $f) {
    if (($f['type'] ?? '') !== 'file') continue;
    $name = basename((string)($f['name'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}\.json$/', $name)) continue;       // أمان: hash.json فقط
    $local = $dir . '/' . $name;
    clearstatcache(true, $local);
    if (is_file($local) && (int)@filesize($local) === (int)($f['size'] ?? -1)) { $skip++; continue; }
    $raw = http_get((string)($f['download_url'] ?? ''), ['timeout' => 15, 'redirects' => true]);
    if ($raw !== null && json_decode($raw) !== null) {
        if (@file_put_contents($local . '.tmp', $raw) !== false) { @rename($local . '.tmp', $local); $added++; }
    }
}
echo "fifa sync — added/updated: $added, up-to-date: $skip\n";

// خريطة صور اللاعبين الرسميّة (assets/fifa-photos.json) — ملفّ مفرد خارج مجلّد
// assets/fifa، فنجلبه على حدة من GitHub raw (لا يدخل في حلقة المجلّد أعلاه).
$photoRaw = http_get('https://raw.githubusercontent.com/salah23222/worldcup2026/main/assets/fifa-photos.json',
                     ['timeout' => 20, 'ua' => 'wcup2026-deploy', 'redirects' => true]);
if ($photoRaw !== null && json_decode($photoRaw) !== null) {
    $pf = __DIR__ . '/../assets/fifa-photos.json';
    clearstatcache(true, $pf);
    if (!is_file($pf) || (int)@filesize($pf) !== strlen($photoRaw)) {
        if (@file_put_contents($pf . '.tmp', $photoRaw) !== false && @rename($pf . '.tmp', $pf)) {
            echo "fifa-photos.json — updated (" . strlen($photoRaw) . " bytes)\n";
        } else {
            echo "fifa-photos.json — write failed\n";
        }
    } else {
        echo "fifa-photos.json — up-to-date\n";
    }
} else {
    echo "fifa-photos.json — fetch failed\n";
}
