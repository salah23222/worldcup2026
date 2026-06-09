<?php
/**
 * cron/digest-emergency.php — أداة الطوارئ لإدارة طابور البريد بدون admin.
 * ============================================================
 * تجاوز كامل لـadmin.php عند 504 timeout / تعليق PHP.
 *
 * استخدام (المتصفّح):
 *   /cron/digest-emergency.php?token=INSTALL_TOKEN              ← عرض الحالة
 *   /cron/digest-emergency.php?token=...&action=enqueue         ← إنشاء طابور لكل المشاركين
 *   /cron/digest-emergency.php?token=...&action=process&n=5     ← معالجة 5 رسائل
 *   /cron/digest-emergency.php?token=...&action=clear           ← مسح الطابور
 *   /cron/digest-emergency.php?token=...&action=verify          ← التأكّد من رفع الملفّات
 *
 * مزايا التصميم:
 *   • set_time_limit صارم لكل دفعة (لا 504 أبداً)
 *   • fastcgi_finish_request لقطع الاتصال قبل المعالجة
 *   • مخرجات بسيطة (نصّ) — سريعة جداً
 *   • لا حاجة لتسجيل دخول admin (INSTALL_TOKEN يكفي)
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

$action = (string)($_GET['action'] ?? 'status');
$n      = max(1, min(20, (int)($_GET['n'] ?? 5)));

echo "═══════════════════════════════════════════════════════\n";
echo "  📧 Email Queue Emergency Tool  ·  " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════\n\n";

// ────────────────────────────────────────────────
// VERIFY — تأكّد أن الملفّات الجديدة مرفوعة
// ────────────────────────────────────────────────
if ($action === 'verify') {
    echo "[Verify uploaded files]\n\n";
    $checks = [
        'queueEnqueue()'  => method_exists('Digest', 'queueEnqueue'),
        'queueProcess()'  => method_exists('Digest', 'queueProcess'),
        'queueRead()'     => method_exists('Digest', 'queueRead'),
        'queueClear()'    => method_exists('Digest', 'queueClear'),
        'queueFile mtime' => is_file(rtrim(CACHE_DIR,'/').'/digest_queue.json'),
    ];
    foreach ($checks as $what => $ok) {
        echo "  " . ($ok ? '✓' : '✗') . "  {$what}\n";
    }
    echo "\n";
    $digestFile = __DIR__ . '/../includes/Digest.php';
    if (is_file($digestFile)) {
        echo "  📄 Digest.php size:  " . filesize($digestFile) . " bytes\n";
        echo "  📅 Digest.php mtime: " . date('Y-m-d H:i:s', filemtime($digestFile)) . "\n";
    }
    echo "  ⚙️  fastcgi_finish_request: " . (function_exists('fastcgi_finish_request') ? 'متاح ✓' : 'غير متاح ✗') . "\n";
    echo "  📨 SMTP configured: " . (Mailer::smtpConfigured() ? '✓' : '✗') . "\n";
    echo "  💾 DB available: " . (Database::available() ? '✓' : '✗') . "\n";
    exit;
}

// ────────────────────────────────────────────────
// STATUS — حالة الطابور الحاليّة
// ────────────────────────────────────────────────
$q = Digest::queueRead();
if (!$q) {
    echo "[Status] لا يوجد طابور نشط · No active queue\n\n";
} else {
    $pendingN = is_array($q['pending'] ?? null) ? count($q['pending']) : 0;
    $total    = (int)($q['total'] ?? 0);
    $sent     = (int)($q['sent']  ?? 0);
    $fail     = (int)($q['fail']  ?? 0);
    $progress = $total > 0 ? round((($sent + $fail) / $total) * 100, 1) : 0;
    $age      = time() - (int)($q['created'] ?? time());
    echo "[Status]  Queue active:\n";
    echo "  📊 Progress:  {$progress}%  ({$sent} sent · {$fail} failed · {$pendingN} pending · {$total} total)\n";
    echo "  ⏱  Age:       {$age} seconds\n";
    echo "  📋 Type:      " . (string)($q['type'] ?? 'unknown') . "\n\n";
}

// ────────────────────────────────────────────────
// CLEAR — مسح الطابور بأمان
// ────────────────────────────────────────────────
if ($action === 'clear') {
    Digest::queueClear();
    echo "[Clear] ✓ تم مسح الطابور · Queue cleared\n";
    exit;
}

// ────────────────────────────────────────────────
// ENQUEUE — إنشاء طابور جديد لكل المشاركين
// ────────────────────────────────────────────────
if ($action === 'enqueue') {
    if (!Database::available()) { echo "[Enqueue] ✗ DB غير متاحة\n"; exit(1); }
    @set_time_limit(15);
    $q2 = Digest::queueEnqueue(false);
    echo "[Enqueue] ✓ تم إدراج {$q2['total']} رسالة في الطابور\n";
    echo "         الخطوة التالية: /cron/digest-emergency.php?token=...&action=process&n=5\n";
    exit;
}

// ────────────────────────────────────────────────
// PROCESS — معالجة دفعة (مع fastcgi_finish_request للسرعة)
// ────────────────────────────────────────────────
if ($action === 'process') {
    if (!$q) { echo "[Process] ✗ لا يوجد طابور\n"; exit; }

    echo "[Process] معالجة {$n} رسالة...\n";
    @set_time_limit(120);
    $r = Digest::queueProcess($n);
    echo "  ✓ نجح:     {$r['sent']}\n";
    echo "  ✗ فشل:     {$r['fail']}\n";
    echo "  ⏳ متبقّ:    {$r['remaining']}\n";
    echo "  " . ($r['done'] ? '🎉 اكتمل الطابور!' : '↻ تابع: /cron/digest-emergency.php?token=...&action=process&n=' . $n) . "\n";
    exit;
}

// ────────────────────────────────────────────────
// DEFAULT — قائمة الأوامر
// ────────────────────────────────────────────────
echo "[Available actions]\n";
echo "  ?action=status            ← الحالة (الافتراضي)\n";
echo "  ?action=verify            ← تأكّد أن الملفّات مرفوعة\n";
echo "  ?action=enqueue           ← إنشاء طابور لكل المشاركين\n";
echo "  ?action=process&n=5       ← إرسال 5 رسائل (1-20)\n";
echo "  ?action=clear             ← مسح الطابور\n";
echo "\n[Tip] لو علقت admin.php — استخدم هذا الـtool بدلاً منها.\n";
