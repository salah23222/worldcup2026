<?php
/**
 * stickers.php — ألبوم الملصقات الافتراضي.
 * يعرض كل خانات الملصقات؛ JavaScript يحدّد المملوكة (localStorage) ويدير الباقات.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang  = current_lang();
$total = Stickers::total();

$page_title = t('sticker_album');
$page_desc  = t('album_intro');
tpl('header');

/** يرسم خانة ملصق واحدة (مقفلة افتراضياً حتى يفتحها JS) */
function render_sticker(array $s, string $lang): void {
    ?>
    <div class="sticker locked rar-<?= e($s['rarity']) ?>" data-id="<?= e($s['id']) ?>" data-rarity="<?= e($s['rarity']) ?>">
      <div class="st-inner">
        <?php if ($s['img'] !== ''): ?>
          <img class="st-img" src="<?= e($s['img']) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="st-emoji"><?= $s['emoji'] ?></span>
        <?php endif; ?>
        <span class="st-name"><?= e(match($lang) { 'ar' => $s['name_ar'], 'fr' => $s['name_fr'] ?? $s['name_en'], default => $s['name_en'] }) ?></span>
      </div>
      <span class="st-lock" aria-hidden="true">?</span>
    </div>
    <?php
}
?>

<div class="page-head">
  <h1>🃏 <?= e(t('sticker_album')) ?></h1>
  <p class="muted"><?= e(t('album_intro')) ?></p>
</div>

<!-- شريط التقدّم + زر الباقة -->
<div class="album-bar">
  <div class="album-progress">
    <div class="ap-track"><div class="ap-fill" id="apFill" style="width:0%"></div></div>
    <span class="ap-text"><span id="apCount">0</span> / <?= (int)$total ?> · <span id="apPct">0%</span></span>
  </div>
  <button type="button" class="btn btn-accent" id="openPackBtn"><?= e(t('open_pack')) ?></button>
  <span class="album-cooldown" id="packCooldown" hidden></span>
</div>
<p class="album-complete-note" id="albumComplete" hidden>🏆 <?= e(t('album_complete')) ?></p>

<!-- المجموعات -->
<?php foreach (Stickers::sets() as $set): ?>
  <section class="sticker-set">
    <h2 class="day-title"><?= e(t('set_' . $set)) ?>
      <span class="set-count" data-set-count="<?= e($set) ?>"></span>
    </h2>
    <div class="sticker-grid">
      <?php foreach (Stickers::inSet($set) as $s) render_sticker($s, $lang); ?>
    </div>
  </section>
<?php endforeach; ?>

<p style="text-align:center;margin-top:20px">
  <button type="button" class="btn-link" id="resetAlbum"><?= e(t('reset_album')) ?></button>
</p>

<!-- نافذة فتح الباقة -->
<div class="pack-overlay" id="packOverlay" hidden>
  <div class="pack-modal">
    <p class="pack-title"><?= e(t('got_stickers')) ?>:</p>
    <div class="pack-reveal" id="packReveal"></div>
    <button type="button" class="btn btn-accent" id="packClose">✓</button>
  </div>
</div>

<script>
window.WC_STICKERS = {
  i18n: {
    cooldown: <?= json_encode(t('pack_cooldown'), JSON_UNESCAPED_UNICODE) ?>,
    isNew:    <?= json_encode(t('sticker_new'), JSON_UNESCAPED_UNICODE) ?>,
    dupe:     <?= json_encode(t('sticker_dupe'), JSON_UNESCAPED_UNICODE) ?>,
    complete: <?= json_encode(t('album_complete'), JSON_UNESCAPED_UNICODE) ?>,
    resetConfirm: <?= json_encode(t('reset_confirm'), JSON_UNESCAPED_UNICODE) ?>
  }
};
</script>
<script src="<?= e(rtrim(SITE_URL,'/')) ?>/assets/js/stickers.js" defer></script>

<?php tpl('footer'); ?>
