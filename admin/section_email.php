<?php
/** admin/section_email.php — حالة النشرة البريدية: SMTP + إرسال تجريبي + سجلّ النتائج. */
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
$ar = (current_lang() === 'ar'); $L = fn($a, $e) => $ar ? $a : $e;

// ---------- عمليّات POST (موثّق مسبقاً عبر admin.php) ----------
$notice = ''; $noticeOk = false;
$do = $_POST['do'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $do === 'sendtest') {
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
            : $L('فشل الإرسال — تحقّق من بيانات SMTP.', 'Send failed — check SMTP settings.');
    }
}

// 🆕 إرسال للجميع / للمتوقّعين فقط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($do === 'sendall' || $do === 'sendpredictors')) {
    if (!Database::available()) {
        $notice = $L('قاعدة البيانات غير متاحة.', 'Database not available.');
    } else {
        @set_time_limit(300);
        $predOnly = ($do === 'sendpredictors');
        $h        = Digest::highlights();
        $recips   = Digest::recipients($predOnly);
        $stand    = Predictions::standingsByUser();
        $sent = $fail = 0;
        foreach ($recips as $u) {
            $mail = Digest::buildEmail($u, $h, $stand[$u['id']] ?? null);
            $ok = Mailer::send($u['email'], $mail['subject'], $mail['html'], $mail['text']);
            $ok ? $sent++ : $fail++;
            usleep(200000); // ~0.2ث بين الرسائل
        }
        Digest::log($predOnly ? 'digest-predictors' : 'digest', $sent, $fail, count($recips));
        $noticeOk = ($fail === 0 && $sent > 0);
        $notice = sprintf(
            $L('تم الإرسال: %d نجح · %d فشل · %d إجمالي', 'Sent: %d ok · %d failed · %d total'),
            $sent, $fail, count($recips)
        );
    }
}

$smtp     = Mailer::smtpConfigured();
$logRows  = Digest::recentLog(20);
// 🆕 إحصاءات المشتركين
$rcptAll  = Database::available() ? count(Digest::recipients(false)) : 0;
$rcptPred = Database::available() ? count(Digest::recipients(true))  : 0;
$winOpen  = Digest::windowOpen();
?>

<!-- معاينة الرسالة (popup يفتحها زر «معاينة») -->
<?php if (isset($_GET['preview'])):
    $h = Digest::highlights();
    $mail = Digest::buildEmail(
        ['id' => 0, 'email' => 'preview@wcup2026.org', 'name' => $L('صلاح','Salah')],
        $h, ['points' => 12, 'rank' => 7, 'total' => 145, 'played' => 4, 'trivia' => 2]
    );
    header('Content-Type: text/html; charset=utf-8');
    echo $mail['html']; exit;
endif; ?>

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

<!-- ============ 🆕 المشتركون والإرسال الجماعي ============ -->
<div class="admin-card">
  <h2><?= e($L('📊 المشتركون والإرسال', 'Subscribers & sending')) ?></h2>

  <!-- إحصاءات سريعة -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:18px">
    <div style="background:rgba(54,192,143,.1);border:1px solid rgba(54,192,143,.3);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:28px;font-weight:900;color:#36c08f"><?= (int)$rcptAll ?></div>
      <div style="font-size:13px;color:#9fb3d1;margin-top:4px"><?= e($L('كل المشاركين','All players')) ?></div>
    </div>
    <div style="background:rgba(247,224,154,.08);border:1px solid rgba(247,224,154,.25);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:28px;font-weight:900;color:#f7e09a"><?= (int)$rcptPred ?></div>
      <div style="font-size:13px;color:#9fb3d1;margin-top:4px"><?= e($L('متوقّعون نشطون','Active predictors')) ?></div>
    </div>
    <div style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.25);border-radius:10px;padding:14px;text-align:center">
      <div style="font-size:18px;font-weight:800;color:<?= $winOpen ? '#a3a8ff' : '#94a3b8' ?>;line-height:1.4">
        <?= $winOpen ? '🟢 ' . e($L('نافذة مفتوحة','Window open')) : '⛔ ' . e($L('نافذة مغلقة','Window closed')) ?>
      </div>
      <div style="font-size:12px;color:#9fb3d1;margin-top:4px"><?= e($L('حالة النشرة','Newsletter status')) ?></div>
    </div>
  </div>

  <!-- زرّ المعاينة -->
  <div style="margin-bottom:14px">
    <a href="admin.php?tab=email&preview=1" target="_blank" class="admin-btn">
      👁️ <?= e($L('معاينة الرسالة في نافذة جديدة','Preview email in new tab')) ?>
    </a>
  </div>

  <!-- زرّ إرسال للجميع -->
  <p class="admin-muted" style="margin-bottom:10px"><?= e($L(
    '⚠️ الإرسال الجماعي يستهلك حصّة الـSMTP — تأكّد من اختبار المعاينة أوّلاً.',
    '⚠️ Bulk send consumes SMTP quota — preview first.'
  )) ?></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <form method="post" action="admin.php" style="display:inline"
          onsubmit="return confirm('<?= e($L('سيُرسل لـ ' . (int)$rcptAll . ' مشترك. تأكّد؟', 'Send to ' . (int)$rcptAll . ' subscribers. Sure?')) ?>')">
      <input type="hidden" name="tab" value="email">
      <input type="hidden" name="do" value="sendall">
      <?= Admin::csrfField() ?>
      <button type="submit" class="admin-btn admin-btn-primary" <?= $rcptAll === 0 ? 'disabled' : '' ?>>
        📨 <?= e($L('إرسال للجميع','Send to all')) ?> (<?= (int)$rcptAll ?>)
      </button>
    </form>
    <form method="post" action="admin.php" style="display:inline"
          onsubmit="return confirm('<?= e($L('سيُرسل لـ ' . (int)$rcptPred . ' متوقّع نشط. تأكّد؟', 'Send to ' . (int)$rcptPred . ' active predictors. Sure?')) ?>')">
      <input type="hidden" name="tab" value="email">
      <input type="hidden" name="do" value="sendpredictors">
      <?= Admin::csrfField() ?>
      <button type="submit" class="admin-btn" <?= $rcptPred === 0 ? 'disabled' : '' ?>>
        🎯 <?= e($L('إرسال للمتوقّعين فقط','Predictors only')) ?> (<?= (int)$rcptPred ?>)
      </button>
    </form>
  </div>
</div>

<!-- ============ إرسال تجريبي ============ -->
<div class="admin-card">
  <h2><?= e($L('🧪 إرسال رسالة تجريبية', 'Send a test email')) ?></h2>
  <p class="admin-muted"><?= e($L('يرسل نسخة من النشرة إلى بريدك للتأكد أن الإرسال يعمل.',
      'Sends a copy to your inbox to confirm delivery.')) ?></p>
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
