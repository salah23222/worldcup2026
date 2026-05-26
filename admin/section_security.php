<?php
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
require_once __DIR__ . '/../includes/Moderation.php';
$ar = (current_lang() === 'ar'); $L = fn($a,$e)=>$ar?$a:$e;

// ---------- معالجة الإجراءات (POST موثّق مسبقاً عبر admin.php) ----------
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do'])) {
    $do  = (string)$_POST['do'];
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($do === 'ban') {
        $reason = substr(trim((string)($_POST['reason'] ?? '')), 0, 180);
        Moderation::ban($uid, $reason);
        $notice = $L('تم إيقاف المستخدم #' . $uid . '.', 'User #' . $uid . ' has been banned.');
    } elseif ($do === 'unban') {
        Moderation::unban($uid);
        $notice = $L('تم رفع الإيقاف عن المستخدم #' . $uid . '.', 'User #' . $uid . ' has been unbanned.');
    }
}

// ---------- فحوصات الأمان ----------
$checks = [];
$checks[] = (defined('DEBUG_MODE') && DEBUG_MODE === false)
    ? ['ok',  $L('وضع التصحيح مُطفأ', 'Debug mode off'),    $L('الأخطاء مخفية عن الزوار.', 'Errors hidden from visitors.')]
    : ['bad', $L('وضع التصحيح مُفعّل', 'Debug mode on'),     $L('قد يكشف تفاصيل حساسة — أطفئه في الإنتاج.', 'May leak sensitive details — turn off in production.')];

$checks[] = (stripos((string)SITE_URL, 'https://') === 0)
    ? ['ok',   'HTTPS', $L('الموقع يعمل عبر اتصال مشفّر.', 'Site runs over an encrypted connection.')]
    : ['warn', 'HTTPS', $L('فعّل HTTPS لحماية كلمات السر والجلسات.', 'Enable HTTPS to protect passwords and sessions.')];

$checks[] = is_file(__DIR__ . '/../install.php')
    ? ['warn', 'install.php', $L('موجود — احذفه بعد الإعداد.', 'Present — delete after setup.')]
    : ['ok',   'install.php', $L('غير موجود.', 'Not present.')];

$checks[] = is_file(__DIR__ . '/../db_selftest.php')
    ? ['warn', 'db_selftest.php', $L('موجود — احذفه بعد الإعداد.', 'Present — delete after setup.')]
    : ['ok',   'db_selftest.php', $L('غير موجود.', 'Not present.')];

$weakPass = (strlen((string)ADMIN_PASS) < 10
    || ADMIN_PASS === 'localadmin'
    || stripos((string)ADMIN_PASS, 'change-me') !== false);
$checks[] = $weakPass
    ? ['warn', $L('قوة كلمة سر الأدمن', 'Admin password strength'), $L('ضعيفة — استخدم كلمة سر طويلة وفريدة.', 'Weak — use a long, unique password.')]
    : ['ok',   $L('قوة كلمة سر الأدمن', 'Admin password strength'), $L('قوية بما يكفي.', 'Strong enough.')];

$checks[] = Database::available()
    ? ['ok',   $L('قاعدة بيانات الحسابات', 'Accounts database'), $L('متصلة وجاهزة.', 'Connected and ready.')]
    : ['warn', $L('قاعدة بيانات الحسابات', 'Accounts database'), $L('غير متاحة — الإيقاف يحتاج القاعدة.', 'Unavailable — moderation needs the DB.')];

$icoMap   = ['ok' => '✅', 'warn' => '⚠️', 'bad' => '❌'];
$badgeMap = ['ok' => 'admin-badge-ok', 'warn' => 'admin-badge-warn', 'bad' => 'admin-badge-bad'];
$statTxt  = ['ok' => $L('سليم', 'OK'), 'warn' => $L('تنبيه', 'Warning'), 'bad' => $L('خطر', 'Risk')];

// ---------- نشاط حدّ المحاولات ----------
$rlActive = 0; $rlThrottled = 0; $rlHasData = false;
$rlGlob = glob(rtrim(CACHE_DIR, '/') . '/ratelimit/*.json');
if (is_array($rlGlob) && $rlGlob) {
    $now = time();
    foreach ($rlGlob as $f) {
        $d = json_decode((string)@file_get_contents($f), true);
        if (!is_array($d)) { continue; }
        $exp = (int)($d['exp'] ?? 0);
        $c   = (int)($d['c'] ?? 0);
        if ($exp >= $now) {
            $rlHasData = true;
            $rlActive++;
            if ($c >= 8) { $rlThrottled++; }
        }
    }
}

// ---------- قائمة المحظورين ----------
$banned = Moderation::all();
?>

<?php if ($notice !== ''): ?>
  <div class="admin-card" style="border-left:4px solid #16a34a;background:rgba(22,163,74,.08)">
    <strong><?= e($notice) ?></strong>
  </div>
<?php endif; ?>

<div class="admin-card">
  <h2><?= e($L('قائمة فحص الأمان', 'Security checklist')) ?></h2>
  <?php foreach ($checks as $c): [$lvl, $label, $hint] = $c; ?>
    <div class="admin-check">
      <span class="admin-check-ico"><?= e($icoMap[$lvl]) ?></span>
      <span><strong><?= e($label) ?></strong> — <span class="admin-muted"><?= e($hint) ?></span></span>
      <span class="admin-badge <?= e($badgeMap[$lvl]) ?>"><?= e($statTxt[$lvl]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <h2><?= e($L('نشاط حدّ المحاولات', 'Rate-limit activity')) ?></h2>
  <?php if (!$rlHasData): ?>
    <p class="admin-muted"><?= e($L('لا يوجد نشاط حالياً.', 'No activity right now.')) ?></p>
  <?php else: ?>
    <div class="admin-stats">
      <div class="admin-stat">
        <strong><?= e((string)$rlActive) ?></strong>
        <span><?= e($L('عدّادات نشطة', 'Active counters')) ?></span>
      </div>
      <div class="admin-stat">
        <strong><?= e((string)$rlThrottled) ?></strong>
        <span><?= e($L('محظور مؤقتاً الآن', 'Currently throttled')) ?></span>
      </div>
    </div>
  <?php endif; ?>
  <p class="admin-muted"><?= e($L('المفاتيح مُجزّأة (hashed)، لذا لا تُعرض عناوين IP الفردية.', 'Keys are hashed, so individual IPs are not shown.')) ?></p>
</div>

<div class="admin-card">
  <h2><?= e($L('الإيقاف', 'Moderation')) ?></h2>

  <?php if (!Database::available()): ?>
    <p class="admin-muted"><?= e($L('قاعدة بيانات الحسابات غير متاحة — لا يمكن الإيقاف الآن.', 'Accounts database is unavailable — moderation is disabled.')) ?></p>
  <?php endif; ?>

  <form method="post" action="admin.php" class="admin-toolbar">
    <input type="hidden" name="tab" value="security">
    <input type="hidden" name="do" value="ban">
    <?= Admin::csrfField() ?>
    <div class="admin-field">
      <input class="admin-input" type="number" name="user_id" min="1" step="1"
             placeholder="<?= e($L('رقم المستخدم', 'User ID')) ?>" required>
    </div>
    <div class="admin-field">
      <input class="admin-input" type="text" name="reason" maxlength="180"
             placeholder="<?= e($L('السبب (اختياري)', 'Reason (optional)')) ?>">
    </div>
    <button type="submit" class="admin-btn admin-btn-danger"><?= e($L('إيقاف', 'Ban')) ?></button>
  </form>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e($L('المستخدم', 'User ID')) ?></th>
          <th><?= e($L('المعرّف', 'Username')) ?></th>
          <th><?= e($L('الاسم', 'Display name')) ?></th>
          <th><?= e($L('السبب', 'Reason')) ?></th>
          <th><?= e($L('التاريخ', 'Date')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$banned): ?>
          <tr><td colspan="6" class="admin-muted"><?= e($L('لا يوجد مستخدمون محظورون.', 'No banned users.')) ?></td></tr>
        <?php else: foreach ($banned as $b): ?>
          <tr>
            <td><?= e((string)(int)($b['user_id'] ?? 0)) ?></td>
            <td><?= e((string)($b['username'] ?? '')) ?></td>
            <td><?= e((string)($b['display_name'] ?? '')) ?></td>
            <td><?= e((string)($b['reason'] ?? '')) ?></td>
            <td><?= e(date('Y-m-d', (int)($b['at'] ?? 0))) ?></td>
            <td>
              <form method="post" action="admin.php">
                <input type="hidden" name="tab" value="security">
                <input type="hidden" name="do" value="unban">
                <input type="hidden" name="user_id" value="<?= e((string)(int)($b['user_id'] ?? 0)) ?>">
                <?= Admin::csrfField() ?>
                <button type="submit" class="admin-btn"><?= e($L('رفع الإيقاف', 'Unban')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
