<?php
/**
 * reset.php — تعيين كلمة سر جديدة بعد ضغط الرابط في الإيميل.
 * يقبل ?token= (64 محرفاً سداسية). صلاحية ساعة، استخدام مرّة واحدة.
 */
require __DIR__ . '/includes/bootstrap.php';

$token  = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error  = null;
$done   = false;
$valid  = false;

// لو مُسجّل دخول، خرّجه أولاً ليُسجّل دخوله بكلمة السر الجديدة.
if (Database::available() && Auth::check()) {
    Auth::logout();
}

if (Database::available()) {
    $userId = PasswordReset::verify($token);
    $valid  = ($userId !== null);

    if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
            $error = 'csrf_error';
        } else {
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if (mb_strlen($p1, 'UTF-8') < 6) {
                $error = 'weak_password';
            } elseif ($p1 !== $p2) {
                $error = 'passwords_mismatch';
            } else {
                // حدّ معدّل للحماية من brute-force على endpoint إعادة التعيين
                $ipKey = 'reset:ip:' . RateLimiter::ip();
                if (RateLimiter::blocked($ipKey, 10, 600)) {
                    $error = 'rate_limited';
                } else {
                    RateLimiter::hit($ipKey, 600);
                    if (PasswordReset::consume($token, $p1)) {
                        // أمن: المستخدم أثبت ملكيّة بريده باستهلاكه الرمز —
                        // ندخله مباشرةً ونحوّله للرئيسية، فلا يُطالَب باسم/كلمة سر مجدّداً.
                        $auto = Auth::loginAsUser($userId);
                        if ($auto['ok']) {
                            header('Location: ' . url('predict.php'));
                            exit;
                        }
                        // لو فشل الدخول لأي سبب (موقوف مثلاً) — أظهر صفحة نجاح وارجاع لـ login.
                        $done = true;
                    } else {
                        $error = 'reset_failed';
                    }
                }
            }
        }
    }
}

$page_title = t('reset_title');
$page_desc  = t('reset_title');
tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('reset_title')) ?></h1>
</div>

<div class="auth-card">
  <?php if (!Database::available()): ?>
    <div class="alert"><?= e(t('accounts_disabled')) ?></div>

  <?php elseif ($done): ?>
    <div class="alert alert-ok" style="background:rgba(0,213,99,.10);border:1px solid #00d563;color:#cfe0f7">
      ✅ <?= e(t('reset_done')) ?>
    </div>
    <p class="auth-alt">
      <a class="btn btn-accent" href="<?= e(url('login.php')) ?>"><?= e(t('sign_in')) ?> ←</a>
    </p>

  <?php elseif (!$valid): ?>
    <div class="alert">⚠️ <?= e(t('reset_invalid')) ?></div>
    <p class="auth-alt">
      <a class="btn-link" href="<?= e(url('forgot.php')) ?>"><?= e(t('forgot_title')) ?> →</a>
    </p>

  <?php else: ?>
    <?php if ($error !== null): ?>
      <div class="alert"><?= e(t($error)) ?></div>
    <?php endif; ?>
    <p class="auth-hint" style="margin-bottom:14px"><?= e(t('reset_intro')) ?></p>
    <form class="auth-form" method="post" action="<?= e(url('reset.php')) ?>" autocomplete="off">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label class="auth-label" for="password"><?= e(t('new_password')) ?></label>
      <input class="auth-input" type="password" id="password" name="password"
             minlength="6" required autofocus>

      <label class="auth-label" for="password2"><?= e(t('new_password_confirm')) ?></label>
      <input class="auth-input" type="password" id="password2" name="password2"
             minlength="6" required>
      <span class="auth-hint"><?= e(t('password_hint')) ?></span>

      <button type="submit" class="btn btn-accent auth-submit"><?= e(t('reset_submit')) ?></button>
    </form>
  <?php endif; ?>
</div>

<?php tpl('footer'); ?>
