<?php
/**
 * embed.php — صفحة كود التضمين: ينسخها صاحب أي موقع لعرض نتائجنا (ودجت).
 * تتضمّن رابطاً ظاهراً للموقع (backlink حقيقي مفيد للسيو).
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$base = base_url();
$site = ($lang === 'ar') ? SITE_NAME_AR : SITE_NAME_EN;

$code = '<iframe src="' . $base . '/widget.php?lang=' . $lang . '" '
      . 'width="100%" height="360" style="border:0;max-width:540px" '
      . 'loading="lazy" title="' . htmlspecialchars($site, ENT_QUOTES) . '"></iframe>' . "\n"
      . '<p><a href="' . $base . '" target="_blank" rel="noopener">' . htmlspecialchars($site, ENT_QUOTES) . ' — ' . htmlspecialchars(parse_url(SITE_URL, PHP_URL_HOST) ?: 'wcup2026.org', ENT_QUOTES) . '</a></p>';

$page_title = t('embed_widget');
$page_desc  = t('embed_intro');
tpl('header');
?>

<div class="page-head">
  <h1>🔌 <?= e(t('embed_widget')) ?></h1>
  <p class="muted"><?= e(t('embed_intro')) ?></p>
</div>

<div class="embed-wrap">
  <div class="embed-code">
    <textarea id="embedCode" rows="5" readonly><?= e($code) ?></textarea>
    <button type="button" class="btn btn-accent" id="copyBtn"
            data-copied="<?= e(t('code_copied')) ?>"><?= e(t('copy_code')) ?></button>
  </div>

  <h2 class="day-title"><?= e(t('embed_preview')) ?></h2>
  <div class="embed-preview">
    <iframe src="<?= e(url('widget.php')) ?>" width="100%" height="360"
            style="border:0;max-width:540px" loading="lazy" title="<?= e($site) ?>"></iframe>
  </div>
</div>

<script>
(function(){
  var btn = document.getElementById('copyBtn');
  var ta  = document.getElementById('embedCode');
  if(!btn||!ta) return;
  btn.addEventListener('click', function(){
    ta.select();
    try { navigator.clipboard.writeText(ta.value); } catch(e){ document.execCommand('copy'); }
    var t = btn.textContent; btn.textContent = btn.getAttribute('data-copied');
    setTimeout(function(){ btn.textContent = t; }, 1800);
  });
})();
</script>

<?php tpl('footer'); ?>
