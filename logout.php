<?php
/**
 * logout.php — يُتلف الجلسة ويعيد التوجيه للرئيسية.
 * يعمل بأمان حتى لو كان النظام معطّلاً.
 */
require __DIR__ . '/includes/bootstrap.php';

if (Database::available()) {
    Auth::logout();
}

header('Location: ' . url('index.php'));
exit;
