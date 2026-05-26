<?php
/**
 * db_selftest.php — يتحقق أن قاعدة البيانات «تمشي» والترابط صحيح والأمان سليم.
 *
 * التشغيل:
 *   - سطر الأوامر:  php db_selftest.php
 *   - المتصفح:      /db_selftest.php?token=<INSTALL_TOKEN>
 *
 * يُنشئ مستخدماً تجريبياً، يكتب توقّعاً وإجابة سؤال، يقرأها، يتأكد أن حذف
 * المستخدم يحذف توقعاته وإجاباته (ON DELETE CASCADE)، ثم يحذف بيانات الاختبار.
 * احذف هذا الملف قبل النشر النهائي.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli') {
    $token = (string)($_GET['token'] ?? '');
    if (INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $token)) {
        http_response_code(403);
        echo "Forbidden. Use ?token=<INSTALL_TOKEN> or run from CLI.\n";
        exit;
    }
}

$results = [];
$chk = function (bool $cond, string $label) use (&$results): void {
    $results[] = $cond;
    echo ($cond ? 'PASS  ' : 'FAIL  ') . $label . "\n";
};

echo "=== Database self-test ===\n";

if (!Database::available()) {
    echo "FAIL  لا يمكن الاتصال بقاعدة البيانات.\n";
    echo "      تأكّد من DB_ENABLED=true و DB_PASS في includes/config.local.php وأن MySQL يعمل.\n";
    exit(1);
}
echo "PASS  الاتصال بقاعدة البيانات ناجح.\n";

$pdo = Database::pdo();

// 1) التثبيت (إنشاء الجداول) — آمن للتكرار.
$ins = Database::install();
$chk(!empty($ins['ok']), 'install(): ' . implode(', ', $ins['steps']));

// 2) تسجيل مستخدم تجريبي (يدخل الجلسة داخل هذه العملية).
$suffix = bin2hex(random_bytes(3));
RateLimiter::reset('register:ip:' . RateLimiter::ip());
$reg = Auth::register(
    'selftest_' . $suffix,
    'Test@12345',
    'Self Test',
    'selftest_' . $suffix . '@example.invalid',
    '+10000000000',
    'OT'
);
$chk(!empty($reg['ok']), 'register test user (' . ($reg['error'] ?? 'ok') . ')');
$uid = (int)($reg['user']['id'] ?? 0);

// 3) أمان: التحقق أن كلمة السر مجزّأة بـ bcrypt وتكلفة ≥ 12.
$hash = '';
try {
    $h = $pdo->prepare('SELECT pass_hash FROM users WHERE id = ?');
    $h->execute([$uid]);
    $hash = (string)$h->fetchColumn();
} catch (Throwable $e) {}
$hinfo = $hash !== '' ? password_get_info($hash) : ['algo' => 0, 'options' => []];
$chk($hinfo['algo'] !== 0 && (int)($hinfo['options']['cost'] ?? 0) >= 12,
     'password hashed (algo=' . ($hinfo['algoName'] ?? '?') . ', cost=' . ($hinfo['options']['cost'] ?? 0) . ')');
$chk(strpos($hash, 'Test@12345') === false, 'plaintext password NOT stored');

// 4) حفظ توقّع عبر الخدمة (يستخدم حساب الجلسة + المفتاح الأجنبي).
$saveRes = Predictions::save(0, 2, 1);
$chk(!empty($saveRes['ok']), 'Predictions::save() → DB (' . ($saveRes['error'] ?? 'ok') . ')');

// 5) قراءة التوقّع.
$my = Predictions::myPredictions();
$chk(isset($my['0']) && (int)$my['0']['p1'] === 2 && (int)$my['0']['p2'] === 1,
     'Predictions::myPredictions() reads it back');

// 6) تسجيل إجابة سؤال اليوم.
$tr = Predictions::recordTrivia(1, 1);
$chk(!empty($tr['awarded']) && (int)$tr['points'] === 3, 'recordTrivia() awards 3 points');
$info = Predictions::triviaInfo();
$chk(!empty($info['answered_today']) && (int)$info['total'] >= 3, 'triviaInfo() reflects the answer');

// 7) الصدارة تتضمّن المستخدم التجريبي.
$found = false;
foreach (Predictions::leaderboard(200) as $row) {
    if (($row['nickname'] ?? '') === 'Self Test') { $found = true; break; }
}
$chk($found, 'leaderboard() includes the test account');

// 8) سلامة الترابط: حذف المستخدم يحذف توقعاته وإجاباته (CASCADE).
try { $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]); } catch (Throwable $e) {}
$leftP = (int)$pdo->query('SELECT COUNT(*) FROM predictions WHERE user_id = ' . $uid)->fetchColumn();
$leftT = (int)$pdo->query('SELECT COUNT(*) FROM trivia_answers WHERE user_id = ' . $uid)->fetchColumn();
$chk($leftP === 0 && $leftT === 0, 'ON DELETE CASCADE removed predictions & trivia');

$failed = count(array_filter($results, fn($x) => !$x));
echo "\n=== RESULT: " . (count($results) - $failed) . " passed, {$failed} failed ===\n";
echo $failed === 0
    ? "✓ قاعدة البيانات تعمل والترابط صحيح والأمان سليم.\n"
    : "✗ توجد مشاكل — راجع الأسطر أعلاه.\n";
exit($failed === 0 ? 0 : 1);
