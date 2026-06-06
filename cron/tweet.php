<?php
/**
 * cron/tweet.php — النشر التلقائي على X (تويتر) — يُشغَّل عبر Cron.
 * ============================================================
 * شغّله مرّة في الساعة. السكربت يقرّر بنفسه:
 *   - هل هذه ساعة فترة معروفة (morning/evening/countdown)؟
 *   - هل نُشرت اليوم؟ (claimSlot يمنع التكرار)
 *   - هل المفاتيح موجودة؟
 * إن تحقّقت الشروط: يبني النصّ وينشر، ويسجّل النتيجة.
 *
 * التشغيل (Hostinger Cron) — مرّة في الساعة على رأس الساعة:
 *   0 * * * * php /home/USER/domains/wcup2026.org/public_html/cron/tweet.php
 *
 * خيارات (CLI):
 *   --slot=morning|evening|countdown|manual   (تجاوز الفترة الحالية)
 *   --force                                   (تجاوز bot bucket «مرّة في اليوم»)
 *   --dry-run                                 (يطبع النصّ فقط، لا ينشر)
 * عبر المتصفّح (إن لزم):  cron/tweet.php?token=INSTALL_TOKEN
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

// --- قراءة الخيارات (CLI أو متصفّح بـtoken) ---
$cli = (PHP_SAPI === 'cli');
$args = [];
if ($cli) {
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([^=]+)(?:=(.*))?$/', $a, $mm)) $args[$mm[1]] = $mm[2] ?? '1';
    }
} else {
    $tok = (string)($_GET['token'] ?? '');
    if (!defined('INSTALL_TOKEN') || INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $tok)) {
        http_response_code(403); exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
    $args = $_GET;
}

$dry   = isset($args['dry-run']) || isset($args['dry']);
$force = isset($args['force']);
$slot  = (string)($args['slot'] ?? '');

$log = function (string $m) { echo $m . "\n"; };

// ---- 1) تحديد الفترة ----
if ($slot === '') {
    $auto = TweetComposer::currentSlot();
    if ($auto === null) {
        $log('[tweet] no slot active at this hour (' . date('H:i') . '). exit.');
        exit(0);
    }
    $slot = $auto;
}
$log('[tweet] slot=' . $slot);

// ---- 2) المفاتيح ----
if (!XPublisher::configured()) {
    $log('[tweet] X keys not configured. exit.');
    exit(0);
}

// ---- 3) بوّابة «مرّة واحدة في اليوم» ----
if (!$force && !XPublisher::claimSlot($slot)) {
    $log('[tweet] slot already posted today. exit.');
    exit(0);
}

// ---- 4) البناء + النشر ----
$text = TweetComposer::build($slot);
$log('[tweet] text:');
$log('---');
$log($text);
$log('--- (' . mb_strlen($text, 'UTF-8') . ' chars)');

if ($dry) {
    $log('[tweet] dry-run, not posting.');
    exit(0);
}

$r = XPublisher::tweet($text);
if ($r['ok']) {
    $log('[tweet] OK — id=' . (string)$r['id']);
    exit(0);
}
$log('[tweet] FAIL — ' . (string)$r['error']);
exit(1);
