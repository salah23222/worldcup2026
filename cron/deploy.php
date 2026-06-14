<?php
/**
 * cron/deploy.php — نشر تلقائي: يسحب آخر تحديث من GitHub إلى الإنتاج.
 * ============================================================
 * يكمّل سلسلة التحديث الآلي:
 *   جهازك (يستخرج + git push) → GitHub → هذا السكربت (git pull) → الموقع محدَّث.
 * أضِفه في cron-job.org كل ساعة (مثل باقي مهامّك):
 *   /cron/deploy.php?token=INSTALL_TOKEN
 * آمن: git pull --ff-only فقط (لا يكتب فوق أي تعديل محلّي، يفشل بنظافة عند التعارض).
 * الريبو عام → لا يحتاج مصادقة. محميّ بالتوكن.
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

if (!function_exists('exec')) { exit("exec() disabled on this host — auto-pull unavailable.\n"); }

$root = realpath(__DIR__ . '/..');
$run = function (string $cmd) use ($root): array {
    $out = []; $code = 0;
    exec('cd ' . escapeshellarg($root) . ' && ' . $cmd . ' 2>&1', $out, $code);
    return ['out' => trim(implode("\n", $out)), 'code' => $code];
};

$git = $run('git --version');
echo "git: {$git['out']} (code {$git['code']})\n";
if ($git['code'] !== 0) { echo "git not available on this host.\n"; exit; }

$wt = $run('git rev-parse --is-inside-work-tree');
if ($wt['out'] !== 'true') { echo "NOT a git checkout here — auto-pull unavailable.\n"; exit; }

$before = $run('git rev-parse --short HEAD');
$pull   = $run('git pull --ff-only');
$after  = $run('git rev-parse --short HEAD');

echo "before: {$before['out']}\nafter:  {$after['out']}\n";
echo "pull (code {$pull['code']}):\n{$pull['out']}\n";
echo ($before['out'] !== $after['out']) ? "UPDATED\n" : "already up to date\n";
