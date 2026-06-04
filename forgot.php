<?php
/**
 * forgot.php — طلب استعادة كلمة السر.
 * يُرسل رابطاً للبريد (إن وُجد المستخدم). لا يكشف وجود/عدم وجود البريد.
 */
require __DIR__ . '/includes/bootstrap.php';

$sent  = false;
$error = null;

// لو مُسجّل دخول، وجّهه للرئيسية.
if (Database::available() && Auth::check()) {
    header('Location: ' . url('predict.php'));
    exit;
}

if (Database::available() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'csrf_error';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        // حدّ معدّل: لكل IP (10 طلبات/ساعة)، ولكل بريد (3 طلبات/ساعة)
        $ipKey   = 'forgot:ip:' . RateLimiter::ip();
        $mailKey = 'forgot:mail:' . mb_strtolower($email);
        if (RateLimiter::blocked($ipKey, 10, 3600) || RateLimiter::blocked($mailKey, 3, 3600)) {
            $error = 'rate_limited';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'invalid_email';
        } else {
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($mailKey, 3600);

            // ابحث عن المستخدم بالبريد — لكن لا تكشف وجوده.
            $userId = PasswordReset::findUserByEmail($email);
            if ($userId !== null) {
                $token = PasswordReset::create($userId, RateLimiter::ip());
                if ($token !== null) {
                    @AccountMail::passwordReset($userId, $token, current_lang());
                }
            }
            // دائماً نُظهر نفس رسالة النجاح (يمنع تعداد البريد).
            $sent = true;
        }
    }
}

$page_title = t('forgot_title');
$page_desc  = t('forgot_title');
tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('forgot_title')) ?></h1>
</div>

<div class="auth-card">
  <?php if (!Database::available()): ?>
    <div class="alert"><?= e(t('accounts_disabled')) ?></div>

  <?php elseif ($sent): ?>
    <div class="alert alert-ok" style="background:rgba(0,213,99,.10);border:1px solid #00d563;color:#cfe0f7">
      ✅ <?= e(t('forgot_sent')) ?>
    </div>
    <p class="auth-alt">
      <a class="btn-link" href="<?= e(url('login.php')) ?>">← <?= e(t('back_to_login')) ?></a>
    </p>

  <?php else: ?>
    <?php if ($error !== null): ?>
      <div class="alert"><?= e(t($error)) ?></div>
    <?php endif; ?>
    <p class="auth-hint" style="margin-bottom:14px"><?= e(t('forgot_intro')) ?></p>
    <form class="auth-form" method="post" action="<?= e(url('forgot.php')) ?>" autocomplete="off">
      <?= Auth::csrfField() ?>
      <label class="auth-label" for="email"><?= e(t('email')) ?></label>
      <input class="auth-input" type="email" id="email" name="email"
             maxlength="190" required value="<?= e($_POST['email'] ?? '') ?>"
             autocapitalize="none" spellcheck="false" autofocus>
      <button type="submit" class="btn btn-accent auth-submit"><?= e(t('forgot_send')) ?></button>
    </form>
    <p class="auth-alt">
      <a class="btn-link" href="<?= e(url('login.php')) ?>">← <?= e(t('back_to_login')) ?></a>
    </p>
  <?php endif; ?>
</div>

<?php tpl('footer'); ?>
