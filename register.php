<?php
/**
 * register.php — إنشاء حساب جديد.
 * يُرسل النموذج إلى نفسه (POST)، ينشئ الحساب عبر Auth، ويوجّه للرئيسية عند النجاح.
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
        $res = Auth::register(
            (string)($_POST['username'] ?? ''),
            (string)($_POST['password'] ?? ''),
            (string)($_POST['display_name'] ?? ''),
            (string)($_POST['email'] ?? ''),
            (string)($_POST['phone'] ?? ''),
            (string)($_POST['country'] ?? '')
        );
        if ($res['ok']) {
            $newId = (int)($res['user']['id'] ?? 0);
            // إيميل ترحيب (best-effort — لا يُعطّل التسجيل عند فشل البريد).
            try {
                if ($newId > 0) {
                    @AccountMail::welcome($newId, current_lang());
                }
            } catch (Throwable $e) { /* تجاهَل */ }
            // تسجيل الإحالة (best-effort) — إن وُجد كوكي ?ref= صالح، يُكافأ المُحيل.
            try {
                if ($newId > 0) {
                    Referrals::recordSignup($newId);
                }
            } catch (Throwable $e) { /* تجاهَل */ }
            header('Location: ' . url('predict.php'));
            exit;
        }
        $error = $res['error'] ?? 'auth_error';
    }
}

$page_title = t('create_account');
$page_desc  = t('create_account');
tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('create_account')) ?></h1>
</div>

<div class="auth-card">
  <?php if (!Database::available()): ?>
    <div class="alert"><?= e(t('accounts_disabled')) ?></div>
  <?php endif; ?>
  <?php if ($error !== null): ?>
    <div class="alert"><?= e(t($error)) ?></div>
  <?php endif; ?>

    <form class="auth-form" method="post" action="<?= e(url('register.php')) ?>" autocomplete="off">
      <?= Auth::csrfField() ?>
      <label class="auth-label" for="username"><?= e(t('username')) ?></label>
      <input class="auth-input" type="text" id="username" name="username"
             maxlength="32" minlength="3" required value="<?= e($_POST['username'] ?? '') ?>"
             pattern="[a-zA-Z0-9_.]{3,32}" autocapitalize="none" spellcheck="false">
      <span class="auth-hint"><?= e(t('username_hint')) ?></span>

      <label class="auth-label" for="display_name"><?= e(t('display_name')) ?></label>
      <input class="auth-input" type="text" id="display_name" name="display_name"
             maxlength="40" value="<?= e($_POST['display_name'] ?? '') ?>">

      <label class="auth-label" for="email"><?= e(t('email')) ?></label>
      <input class="auth-input" type="email" id="email" name="email"
             maxlength="190" required value="<?= e($_POST['email'] ?? '') ?>"
             autocapitalize="none" spellcheck="false">

      <label class="auth-label" for="phone"><?= e(t('phone')) ?></label>
      <input class="auth-input" type="tel" id="phone" name="phone"
             maxlength="20" required value="<?= e($_POST['phone'] ?? '') ?>"
             inputmode="tel" placeholder="<?= e(t('phone_hint')) ?>">

      <label class="auth-label" for="country"><?= e(t('country')) ?></label>
      <select class="auth-input" id="country" name="country" required>
        <option value=""><?= e(t('choose_country')) ?></option>
        <?php
          $cs = countries();
          $sel = strtoupper($_POST['country'] ?? '');
          uasort($cs, fn($a, $b) => strcmp($a[current_lang() === 'ar' ? 0 : 1], $b[current_lang() === 'ar' ? 0 : 1]));
          foreach ($cs as $code => $names):
        ?>
          <option value="<?= e($code) ?>" <?= $sel === $code ? 'selected' : '' ?>>
            <?= e(current_lang() === 'ar' ? $names[0] : $names[1]) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="auth-label" for="password"><?= e(t('password')) ?></label>
      <input class="auth-input" type="password" id="password" name="password"
             minlength="6" required>
      <span class="auth-hint"><?= e(t('password_hint')) ?></span>

      <button type="submit" class="btn btn-accent auth-submit"><?= e(t('sign_up')) ?></button>
    </form>

    <p class="auth-alt">
      <a class="btn-link" href="<?= e(url('login.php')) ?>"><?= e(t('have_account')) ?></a>
    </p>
</div>

<?php tpl('footer'); ?>
