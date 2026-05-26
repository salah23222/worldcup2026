<?php
/**
 * admin.php — لوحة التحكم (مالك الموقع فقط).
 * ============================================================
 * بوابة بكلمة سر (Admin) + حدّ محاولات + CSRF. تتكوّن من أقسام مستقلّة
 * في مجلد admin/ تُحمَّل حسب التبويب (?tab=). كل قسم يعالج إجراءاته بنفسه
 * بعد أن يتحقّق هذا الملف من الدخول وCSRF.
 * ============================================================
 */
require __DIR__ . '/includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

$ar = (current_lang() === 'ar');
$L  = fn(string $a, string $e) => $ar ? $a : $e;

// ---------- تسجيل الخروج ----------
if (isset($_GET['logout'])) { Admin::logout(); header('Location: admin.php'); exit; }

// ---------- اللوحة معطّلة (لا كلمة سر) ----------
if (!Admin::enabled()) {
    http_response_code(503);
    exit($L('لوحة التحكم غير مفعّلة. اضبط ADMIN_PASS في config.local.php.',
            'Admin panel is disabled. Set ADMIN_PASS in config.local.php.'));
}

// ---------- معالجة الدخول ----------
$loginErr = '';
if (!Admin::authed() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    $ipKey = 'admin_login:' . RateLimiter::ip();
    if (RateLimiter::blocked($ipKey, 8, 600)) {
        $loginErr = $L('محاولات كثيرة. انتظر قليلاً ثم حاول.', 'Too many attempts. Wait a bit and retry.');
    } elseif (Admin::attempt((string)$_POST['admin_pass'])) {
        RateLimiter::reset($ipKey);
        header('Location: admin.php'); exit;
    } else {
        RateLimiter::hit($ipKey, 600);
        $loginErr = $L('كلمة سر غير صحيحة.', 'Incorrect password.');
    }
}

// ---------- شاشة الدخول ----------
if (!Admin::authed()) {
    ?><!DOCTYPE html><html dir="<?= $ar ? 'rtl' : 'ltr' ?>" lang="<?= e(current_lang()) ?>"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($L('دخول لوحة التحكم', 'Admin Login')) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    </head><body class="admin-body">
      <form class="admin-login" method="post" action="admin.php">
        <div class="admin-login-mark">26</div>
        <h1><?= e($L('لوحة التحكم', 'Admin Panel')) ?></h1>
        <?php if ($loginErr !== ''): ?><p class="admin-login-err"><?= e($loginErr) ?></p><?php endif; ?>
        <input type="password" name="admin_pass" autocomplete="current-password"
               placeholder="<?= e($L('كلمة السر', 'Password')) ?>" required autofocus>
        <button type="submit"><?= e($L('دخول', 'Sign in')) ?></button>
      </form>
    </body></html><?php
    exit;
}

// ================= مُصادَق كأدمن =================

// تحقّق CSRF لكل طلب كتابة (الأقسام تعتمد عليه).
$csrfOk = ($_SERVER['REQUEST_METHOD'] !== 'POST') || Admin::checkCsrf();
if (!$csrfOk) { $_POST = []; }   // أبطل أي إجراء غير موثّق

$tabs = [
    'participants' => $L('المشاركون', 'Participants'),
    'security'     => $L('الأمان والإيقاف', 'Security & Moderation'),
    'email'        => $L('النشرة البريدية', 'Email Digest'),
    'sponsors'     => $L('الرعاة', 'Sponsors'),
];
$tab = (string)($_POST['tab'] ?? $_GET['tab'] ?? 'participants');
if (!isset($tabs[$tab])) $tab = 'participants';

$sectionFile = __DIR__ . '/admin/section_' . $tab . '.php';
?><!DOCTYPE html><html dir="<?= $ar ? 'rtl' : 'ltr' ?>" lang="<?= e(current_lang()) ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($tabs[$tab]) ?> — <?= e($L('لوحة التحكم', 'Admin')) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<?php $av = @filemtime(__DIR__ . '/assets/css/admin.css') ?: 1; ?>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= $av ?>">
</head><body class="admin-body">
<header class="admin-header">
  <div class="admin-brand"><span class="admin-mark">26</span> <?= e($L('لوحة التحكم', 'Admin Panel')) ?></div>
  <nav class="admin-nav">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="admin.php?tab=<?= e($k) ?>" class="<?= $k === $tab ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('index.php')) ?>" class="admin-nav-out"><?= e($L('الموقع', 'Site')) ?> ↗</a>
    <a href="admin.php?logout=1" class="admin-nav-out"><?= e($L('خروج', 'Logout')) ?></a>
  </nav>
</header>
<main class="admin-main">
  <?php if (!$csrfOk): ?>
    <div class="admin-alert"><?= e($L('انتهت صلاحية النموذج، أعد المحاولة.', 'Form expired, please retry.')) ?></div>
  <?php endif; ?>
  <h1 class="admin-title"><?= e($tabs[$tab]) ?></h1>
  <?php
  if (is_file($sectionFile)) {
      require $sectionFile;
  } else {
      echo '<p class="admin-muted">' . e($L('هذا القسم قيد الإعداد.', 'This section is being prepared.')) . '</p>';
  }
  ?>
</main>
</body></html>
