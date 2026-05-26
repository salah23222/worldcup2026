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
    <?= e($lang==='ar'
        ? '16 ملعباً في 3 دول مستضيفة'
        : '16 stadiums across 3 host nations') ?>
  </p>
</div>

<div class="stadiums-grid">
  <?php foreach ($stadiums as $i => $s):
    $flag = 'https://flagcdn.com/w40/' . $s['country'] . '.png';
  ?>
  <a class="stadium-card" href="<?= e(url('stadium.php', ['id' => $s['id']])) ?>">
    <div class="st-num"><?= $i + 1 ?></div>
    <div class="st-body">
      <h3><?= e($lang==='ar' ? $s['nameAr'] : $s['nameEn']) ?></h3>
      <p class="st-city">
        <img class="flag" src="<?= e($flag) ?>" alt="" width="20" height="14" loading="lazy">
        <?= e($lang==='ar' ? $s['cityAr'] : $s['cityEn']) ?>
      </p>
      <p class="st-cap">
        <?= number_format($s['cap']) ?>
        <span class="muted"><?= e($lang==='ar'?'مقعد':'seats') ?></span>
      </p>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php tpl('footer'); ?>
