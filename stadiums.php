<?php
/**
 * stadiums.php — الملاعب الـ16 والمدن المستضيفة.
 * بيانات الملاعب ثابتة (لا تتغيّر) فمصدرها داخلي.
 */
require __DIR__ . '/includes/bootstrap.php';

$page_title = t('stadiums');
$lang = current_lang();

$stadiums = Stadiums::all();

tpl('header');
?>

<div class="page-head">
  <h1>🏟 <?= e(t('stadiums')) ?></h1>
  <p class="muted">
    <?= e(match($lang) {
        'ar' => '16 ملعباً في 3 دول مستضيفة',
        'fr' => '16 stades dans 3 pays hôtes',
        default => '16 stadiums across 3 host nations',
    }) ?>
  </p>
</div>

<div class="stadiums-grid">
  <?php foreach ($stadiums as $i => $s):
    $flag = 'https://flagcdn.com/w40/' . $s['country'] . '.png';
  ?>
  <a class="stadium-card" href="<?= e(url('stadium.php', ['id' => $s['id']])) ?>">
    <div class="st-num"><?= $i + 1 ?></div>
    <div class="st-body">
      <h3><?= e(match($lang) { 'ar' => $s['nameAr'], 'fr' => $s['nameFr'] ?? $s['nameEn'], default => $s['nameEn'] }) ?></h3>
      <p class="st-city">
        <img class="flag" src="<?= e($flag) ?>" alt="" width="20" height="14" loading="lazy">
        <?= e(match($lang) { 'ar' => $s['cityAr'], 'fr' => $s['cityFr'] ?? $s['cityEn'], default => $s['cityEn'] }) ?>
      </p>
      <p class="st-cap">
        <?= number_format($s['cap']) ?>
        <span class="muted"><?= e(match($lang) { 'ar' => 'مقعد', 'fr' => 'places', default => 'seats' }) ?></span>
      </p>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php tpl('footer'); ?>
