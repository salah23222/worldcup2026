<?php
/**
 * login.php — تسجيل الدخول إلى حساب موجود.
 * يُرسل النموذج إلى نفسه (POST)، يتحقّق عبر Auth، ويوجّه للرئيسية عند النجاح.
 */
require __DIR__ . '/includes/bootstrap.php';

$error = null;

// إن كان المستخدم مسجّلاً بالفعل، وجّهه للرئيسية مباشرةً.
if (Database::available() && Auth::check()) {
    header('Location: ' . url('predict.php'));
    exit;
}

if (Database::available() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Auth::checkCsrf($_POST['csrf'] ?? null)) {
        $error = 'csrf_error';
    } else {
        $res = Auth::login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($res['ok']) {
            header('Location: ' . url('predict.php'));
            exit;
        }
        $error = $res['error'] ?? 'auth_error';
    }
}

$page_title = t('login');
$page_desc  = t('login');
tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('login')) ?></h1>
</div>

<div class="auth-card">
  <?php if (!Database::available()): ?>
    <div class="alert"><?= e(t('accounts_disabled')) ?></div>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <div class="alert"><?= e(t($error)) ?></div>
  <?php endif; ?>

    <form class="auth-form" method="post" action="<?= e(url('login.php')) ?>" autocomplete="off">
      <?= Auth::csrfField() ?>
      <label class="auth-label" for="username"><?= e(t('username')) ?></label>
      <input class="auth-input" type="text" id="username" name="username"
             maxlength="32" required value="<?= e($_POST['username'] ?? '') ?>"
             autocapitalize="none" spellcheck="false">

      <label class="auth-label" for="password"><?= e(t('password')) ?></label>
      <input class="auth-input" type="password" id="password" name="password"
             minlength="6" required>

      <button type="submit" class="btn btn-accent auth-submit"><?= e(t('sign_in')) ?></button>
    </form>

    <p class="auth-alt">
      <a class="btn-link" href="<?= e(url('register.php')) ?>"><?= e(t('no_account')) ?></a>
    </p>
</div>

<?php tpl('footer'); ?>
