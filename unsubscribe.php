<?php
/**
 * unsubscribe.php — إلغاء الاشتراك في النشرة بنقرة واحدة (رابط موقّع في كل إيميل).
 * ?u=<user_id>&t=<token>  — التوكن = توقيع HMAC يمنع إلغاء اشتراك الآخرين.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');
$uid  = isset($_GET['u']) ? (int)$_GET['u'] : 0;
$tok  = (string)($_GET['t'] ?? '');

$ok = ($uid > 0 && Digest::unsubValid($uid, $tok) && Digest::optOut($uid));

$page_title = match($lang) { 'ar' => 'إلغاء الاشتراك', 'fr' => 'Désabonnement', default => 'Unsubscribe' };
tpl('header');
?>
<div class="page-head">
  <h1><?= $ok ? '✅' : '⚠️' ?> <?= e($page_title) ?></h1>
</div>
<div class="auth-card" style="text-align:center">
  <?php if ($ok): ?>
    <p style="line-height:1.9"><?= e(match($lang) {
      'ar' => 'تم إلغاء اشتراكك في النشرة الدورية. لن تصلك رسائل بعد الآن. يمكنك دائماً متابعة كل جديد عبر الموقع.',
      'fr' => 'Vous avez été désabonné de la newsletter. Vous pouvez toujours suivre l\'actualité via le site.',
      default => 'You have been unsubscribed from the newsletter. You can always keep up via the site.',
    }) ?></p>
  <?php else: ?>
    <p style="line-height:1.9"><?= e(match($lang) {
      'ar' => 'تعذّر تنفيذ الطلب — الرابط غير صالح أو منتهٍ. إن أردت إلغاء الاشتراك، استخدم الرابط الموجود في آخر رسالة وصلتك.',
      'fr' => 'Impossible de traiter la demande — le lien est invalide ou expiré. Utilisez le lien de votre dernier e-mail.',
      default => 'Could not process this request — the link is invalid or expired. Use the link from your latest email.',
    }) ?></p>
  <?php endif; ?>
  <p style="margin-top:16px">
    <a class="btn-cta" href="<?= e(url('index.php')) ?>"><?= e(match($lang) { 'ar' => 'العودة للموقع', 'fr' => 'Retour au site', default => 'Back to site' }) ?></a>
  </p>
</div>
<?php tpl('footer'); ?>
