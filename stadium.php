<?php
/**
 * stadium.php — صفحة تفاصيل ملعب واحد.
 * ?id=N (0..15). صورة من ويكيبيديا + نبذة + زر التوجيه عبر خرائط جوجل.
 */
require __DIR__ . '/includes/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
$s  = Stadiums::get($id);

if (!$s) {
    http_response_code(404);
    $page_title = t('stadium');
    tpl('header');
    echo '<p class="empty-note">' . e(t('not_found')) . '</p>';
    echo '<p style="text-align:center"><a class="back-link" href="' . e(url('stadiums.php')) . '">← ' . e(t('all_stadiums')) . '</a></p>';
    tpl('footer');
    exit;
}

$lang = current_lang();
$name = match($lang) { 'ar' => $s['nameAr'], 'fr' => $s['nameFr'] ?? $s['nameEn'], default => $s['nameEn'] };
$city = match($lang) { 'ar' => $s['cityAr'], 'fr' => $s['cityFr'] ?? $s['cityEn'], default => $s['cityEn'] };
$hist = match($lang) { 'ar' => $s['histAr'], 'fr' => $s['histFr'] ?? $s['histEn'], default => $s['histEn'] };
$flag = 'https://flagcdn.com/w40/' . $s['country'] . '.png';
$img  = Stadiums::image($id);
$maps = Stadiums::mapsUrl($s);

$page_title = $name;
$page_desc  = $hist;
tpl('header');
?>

<p style="margin-bottom:14px">
  <a class="back-link" href="<?= e(url('stadiums.php')) ?>">← <?= e(t('all_stadiums')) ?></a>
</p>

<article class="stadium-detail">
  <div class="sd-hero">
    <span class="sd-hero-emoji">🏟</span>
    <?php if ($img !== ''): ?>
      <img src="<?= e($img) ?>" alt="<?= e($name) ?>" loading="lazy" onerror="this.remove()">
    <?php endif; ?>
  </div>

  <div class="sd-head">
    <h1><?= e($name) ?></h1>
    <p class="sd-city">
      <img class="flag" src="<?= e($flag) ?>" alt="" width="22" height="15" loading="lazy">
      <?= e($city) ?>
    </p>
  </div>

  <div class="sd-stats">
    <div>
      <strong><?= number_format($s['cap']) ?></strong>
      <span><?= e(t('capacity')) ?></span>
    </div>
    <div>
      <strong><?= (int)$s['opened'] ?></strong>
      <span><?= e(t('opened')) ?></span>
    </div>
    <div>
      <strong><?= e(strtoupper($s['country'])) ?></strong>
      <span><?= e(t('country')) ?></span>
    </div>
  </div>

  <?php if ($hist !== ''): ?>
    <section class="sd-history">
      <h2><?= e(t('about_stadium')) ?></h2>
      <p><?= e($hist) ?></p>
    </section>
  <?php endif; ?>

  <a class="btn btn-cta sd-maps" href="<?= e($maps) ?>" target="_blank" rel="noopener noreferrer">
    🧭 <?= e(t('get_directions')) ?>
  </a>
</article>

<?php tpl('footer'); ?>
