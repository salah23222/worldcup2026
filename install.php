<?php
/**
 * install.php — إعداد قاعدة البيانات لمرة واحدة (محمي برمز سرّي).
 * ============================================================
 * 1) في config.local.php: اضبط DB_PASS و DB_ENABLED=true و INSTALL_TOKEN (رمز عشوائي).
 * 2) افتح:  /install.php?token=<INSTALL_TOKEN>
 *    أو من سطر الأوامر:  php install.php
 * 3) ينشئ قاعدة البيانات والجداول. احذف هذا الملف بعد النجاح.
 * ============================================================
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$isCli = (PHP_SAPI === 'cli');

// حماية: على الويب يجب تمرير رمز سرّي صحيح (CLI مسموح دائماً).
if (!$isCli) {
    $token = (string)($_GET['token'] ?? '');
    if (INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $token)) {
        http_response_code(403);
        echo "Forbidden.\n";
        echo "Set INSTALL_TOKEN in config.local.php and open install.php?token=YOUR_TOKEN\n";
        exit;
    }
}

if (!defined('DB_ENABLED') || !DB_ENABLED) {
    echo "DB_ENABLED is false.\n";
    echo "In includes/config.local.php set: DB_ENABLED => true, and DB_PASS.\n";
    exit;
}

$result = Database::install();

if ($result['ok']) {
    echo "OK — database setup completed.\n";
    echo "Steps: " . implode(', ', $result['steps']) . "\n";
    echo "\nDelete install.php now for safety.\n";
} else {
    // لا نكشف تفاصيل الاستثناء للعميل — نسجّلها في سجلّ الخادم فقط.
    error_log('[install.php] setup failed: ' . ($result['error'] ?? 'unknown'));
    http_response_code(500);
    echo "FAILED — setup did not complete.\n";
    echo "Steps done: " . (empty($result['steps']) ? '(none)' : implode(', ', $result['steps'])) . "\n";
    echo "Check DB settings in config.local.php and that MySQL is running.\n";
    echo "(Details were written to the server error log.)\n";
}
