<?php
/** admin/section_email.php — حالة النشرة البريدية: SMTP + إرسال تجريبي + سجلّ النتائج. */
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
$ar = (current_lang() === 'ar'); $L = fn($a, $e) => $ar ? $a : $e;

// ---------- إرسال رسالة تجريبية (POST موثّق مسبقاً عبر admin.php) ----------
$notice = ''; $noticeOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'sendtest') {
    $to = trim((string)($_POST['email'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $notice = $L('بريد إلكتروني غير صالح.', 'Invalid email address.');
    } else {
        $h    = Digest::highlights();
        $mail = Digest::buildEmail(
            ['id' => 0, 'email' => $to, 'name' => $L('تجربة', 'Test')],
            $h, ['points' => 0, 'rank' => 1, 'total' => 1, 'played' => 0, 'trivia' => 0]
        );
        $ok = Mailer::send($to, $mail['subject'], $mail['html'], $mail['text']);
        Digest::log('test', $ok ? 1 : 0, $ok ? 0 : 1, 1);
        $noticeOk = $ok;
        $notice = $ok
            ? $L('تم إرسال رسالة تجريبية إلى ' . $to . ' بنجاح ✓', 'Test email sent to ' . $to . ' ✓')
            : $L('فشل الإرسال — تحقّق من بيانات SMTP (الخادم/المستخدم/كلمة السر) وأن صندوق البريد موجود.',
                 'Send failed — check SMTP settings (host/user/password) and that the mailbox exists.');
    }
}

$smtp = Mailer::smtpConfigured();
$logRows = Digest::recentLog(20);
?>

<?php if ($notice !== ''): ?>
  <div class="admin-card" style="border-inline-start:4px solid <?= $noticeOk ? '#16a34a' : '#dc2626' ?>;background:<?= $noticeOk ? 'rgba(22,163,74,.08)' : 'rgba(220,38,38,.08)' ?>">
    <strong><?= e($notice) ?></strong>
  </div>
<?php endif; ?>

<!-- ============ حالة SMTP ============ -->
<div class="admin-card">
  <h2><?= e($L('حالة البريد (SMTP)', 'Email status (SMTP)')) ?></h2>
  <div class="admin-check">
    <span class="admin-check-ico"><?= $smtp ? '✅' : '⚠️' ?></span>
    <span><strong><?= e($L('الإرسال عبر SMTP', 'SMTP delivery')) ?></strong> —
      <span class="admin-muted"><?= e($smtp
        ? $L('مُفعّل (أفضل وصول).', 'Enabled (best deliverability).')
        : $L('غير مضبوط — سيُستخدم mail() المدمجة (وصول أضعف). اضبط SMTP_* في config.local.php.',
             'Not set — built-in mail() will be used. Configure SMTP_* in config.local.php.')) ?></span>
    </span>
    <span class="admin-badge <?= $smtp ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e($smtp ? $L('مفعّل','On') : $L('بديل','Fallback')) ?></span>
  </div>
  <?php if ($smtp): ?>
  <div class="admin-table-wrap" style="margin-top:10px">
    <table class="admin-table">
      <tr><th><?= e($L('الخادم', 'Host')) ?></th><td><?= e(SMTP_HOST) ?>:<?= (int)SMTP_PORT ?> (<?= e(SMTP_SECURE) ?>)</td></tr>
      <tr><th><?= e($L('المستخدم', 'User')) ?></th><td><?= e(SMTP_USER) ?></td></tr>
      <tr><th><?= e($L('كلمة السر', 'Password')) ?></th><td>•••••••• (<?= (int)strlen((string)SMTP_PASS) ?> <?= e($L('خانة','chars')) ?>)</td></tr>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ============ إرسال تجريبي ============ -->
<div class="admin-card">
  <h2><?= e($L('إرسال رسالة تجريبية', 'Send a test email')) ?></h2>
  <p class="admin-muted"><?= e($L('يرسل نسخة من النشرة إلى بريدك للتأكد أن الإرسال يعمل — ويُسجّل النتيجة أدناه.',
      'Sends a copy of the newsletter to your inbox to confirm delivery — the result is logged below.')) ?></p>
  <form method="post" action="admin.php" class="admin-toolbar">
    <input type="hidden" name="tab" value="email">
    <input type="hidden" name="do" value="sendtest">
    <?= Admin::csrfField() ?>
    <div class="admin-field">
      <input class="admin-input" type="email" name="email" required
             placeholder="<?= e($L('بريدك للاختبار', 'Your test email')) ?>">
    </div>
    <button type="submit" class="admin-btn admin-btn-primary"><?= e($L('إرسال تجريبي', 'Send test')) ?></button>
  </form>
</div>

<!-- ============ سجلّ الإرسال ============ -->
<div class="admin-card">
  <h2><?= e($L('سجلّ الإرسال', 'Send history')) ?></h2>
  <?php if (!$logRows): ?>
    <p class="admin-muted"><?= e($L('لا توجد عمليات إرسال بعد.', 'No sends yet.')) ?></p>
  <?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e($L('الوقت', 'Time')) ?></th>
          <th><?= e($L('النوع', 'Type')) ?></th>
          <th><?= e($L('نجح', 'Sent')) ?></th>
          <th><?= e($L('فشل', 'Failed')) ?></th>
          <th><?= e($L('المستلِمون', 'Recipients')) ?></th>
          <th><?= e($L('الحالة', 'Status')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logRows as $r):
          $sent = (int)($r['sent'] ?? 0); $fail = (int)($r['fail'] ?? 0);
          if ($fail === 0 && $sent > 0)      { $cls = 'admin-badge-ok';   $txt = $L('نجح', 'OK'); }
          elseif ($sent > 0 && $fail > 0)    { $cls = 'admin-badge-warn'; $txt = $L('جزئي', 'Partial'); }
          else                               { $cls = 'admin-badge-bad';  $txt = $L('فشل', 'Failed'); }
          $typeLabels = ['test' => $L('تجريبي','Test'), 'digest' => $L('نشرة','Digest'), 'digest-predictors' => $L('نشرة (متوقّعون)','Digest (predictors)')];
        ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', (int)($r['t'] ?? 0))) ?></td>
            <td><?= e($typeLabels[$r['type'] ?? ''] ?? (string)($r['type'] ?? '')) ?></td>
            <td><?= $sent ?></td>
            <td><?= $fail ?></td>
            <td><?= (int)($r['rcpt'] ?? 0) ?></td>
            <td><span class="admin-badge <?= $cls ?>"><?= e($txt) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
