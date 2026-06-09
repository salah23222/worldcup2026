<?php
/**
 * cron/digest.php — مُرسِل النشرة الدورية للمشتركين (يُشغَّل عبر Cron).
 * ============================================================
 * يرسل لكل مستخدم مسجّل (لم يُلغِ اشتراكه) رسالة فيها نقاطه وترتيبه وأبرز
 * المباريات وروابط التفاعل. الإرسال كل DIGEST_EVERY_DAYS أيام، ويتوقّف
 * تلقائياً بعد نهاية البطولة.
 *
 * التشغيل (Hostinger Cron) — مرّة يومياً والسكربت يضبط التكرار بنفسه:
 *   php /home/USER/domains/wcup2026.org/public_html/cron/digest.php
 *
 * خيارات (CLI):  --dry-run  (لا يرسل، يعرض فقط)
 *                --test=you@mail.com  (يرسل رسالة تجريبية واحدة)
 *                --force    (يتجاهل بوابة كل-يومين ونافذة البطولة)
 *                --limit=N  (أول N مستلِم فقط — للاختبار)
 * عبر المتصفّح (إن لزم): cron/digest.php?token=INSTALL_TOKEN
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }   // إخراج نصّي نظيف

// --- قراءة الخيارات (CLI أو متصفّح بـtoken) ---
$cli = (PHP_SAPI === 'cli');
$args = [];
if ($cli) {
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([^=]+)(?:=(.*))?$/', $a, $mm)) $args[$mm[1]] = $mm[2] ?? '1';
    }
} else {
    // حماية: لا يُشغَّل علنياً بدون التوكن.
    $tok = (string)($_GET['token'] ?? '');
    if (!defined('INSTALL_TOKEN') || INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $tok)) {
        http_response_code(403); exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
    $args = $_GET;
}

$dry    = isset($args['dry-run']) || isset($args['dry']);
$force  = isset($args['force']);
$test   = (string)($args['test'] ?? '');
$limit  = isset($args['limit']) ? (int)$args['limit'] : 0;
$predOnly = isset($args['predictors']);   // فقط المشاركون في التوقعات/سؤال اليوم

$log = function (string $m) { echo $m . "\n"; };

// --- رسالة تجريبية واحدة ---
if ($test !== '') {
    $h = Digest::highlights();
    $fake = ['id' => 0, 'email' => $test, 'name' => 'تجربة'];
    $mail = Digest::buildEmail($fake, $h, ['points' => 0, 'rank' => 1, 'total' => 1, 'played' => 0, 'trivia' => 0]);
    if ($dry) { $log($mail['subject']); $log(''); $log($mail['text']); exit; }
    $ok = Mailer::send($test, $mail['subject'], $mail['html'], $mail['text']);
    Digest::log('test', $ok ? 1 : 0, $ok ? 0 : 1, 1);
    $log($ok ? "test sent to {$test}" : "test FAILED (تحقّق من بيانات SMTP)");
    exit;
}

// 🆕 معالجة الطابور أوّلاً — لو يوجد إرسال يدوي معلّق من admin
$queue = Digest::queueRead();
if ($queue && !empty($queue['pending'])) {
    $log("[queue] found " . count($queue['pending']) . " pending — processing batch...");
    $r = Digest::queueProcess(20); // 20 رسالة لكل تشغيل cron
    $log("[queue] batch: sent={$r['sent']} fail={$r['fail']} remaining={$r['remaining']}" . ($r['done'] ? ' DONE' : ''));
    if (!$r['done']) {
        // ما زال الطابور قيد التنفيذ — لا تشغّل النشرة الدوريّة هذه الجلسة
        exit;
    }
}

// --- بوّابة نافذة البطولة (تتوقّف بعد النهائي) ---
if (!$force && !Digest::windowOpen()) { $log('window closed (البطولة انتهت)'); exit; }

// --- بوّابة «كل يومين» (السكربت يُشغَّل يومياً ويضبط التكرار بنفسه) ---
$stateFile = rtrim(CACHE_DIR, '/') . '/digest_last.txt';
$every = defined('DIGEST_EVERY_DAYS') ? max(1, (int)DIGEST_EVERY_DAYS) : 2;
if (!$force && !$dry) {
    $last = is_file($stateFile) ? trim((string)@file_get_contents($stateFile)) : '';
    if ($last !== '') {
        $days = (strtotime(date('Y-m-d')) - strtotime($last)) / 86400;
        if ($days < $every) { $log("skip: آخر إرسال {$last} (كل {$every} يوم)"); exit; }
    }
}

// --- المستلِمون يحتاجون قاعدة بيانات (الإيميلات مخزّنة فيها) ---
if (!Database::available()) {
    $log('DB غير متاحة — النشرة تحتاج مستخدمين مسجّلين. تأكّد من DB_ENABLED والاتصال.');
    if (!$dry) exit(1);
}

$h      = Digest::highlights();
$recips = Digest::recipients($predOnly);
$stand  = Predictions::standingsByUser();
if ($limit > 0) $recips = array_slice($recips, 0, $limit);

$sent = $fail = 0;
foreach ($recips as $u) {
    $mail = Digest::buildEmail($u, $h, $stand[$u['id']] ?? null);
    if ($dry) { $log("[DRY] {$u['email']} :: {$mail['subject']}"); $sent++; continue; }
    $ok = Mailer::send($u['email'], $mail['subject'], $mail['html'], $mail['text']);
    $ok ? $sent++ : $fail++;
    usleep(200000);   // ~0.2ث بين الرسائل (تجنّب حدود الاستضافة)
}

if (!$dry) {
    @file_put_contents($stateFile, date('Y-m-d'));
    Digest::log($predOnly ? 'digest-predictors' : 'digest', $sent, $fail, count($recips));
}
$log("done: sent={$sent} fail={$fail} recipients=" . count($recips) . ($dry ? ' (dry-run)' : ''));
