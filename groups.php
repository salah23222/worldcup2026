<?php
/**
 * groups.php — جداول ترتيب المجموعات الـ12.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/group_table.php';

$page_title = t('groups');
$tables     = Standings::all();
$thirds     = Standings::thirdPlaceRanking();

tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('groups')) ?> — <?= e(t('standings')) ?></h1>
  <p class="muted">
    <?= e(match(current_lang()) {
        'ar' => 'يتأهل أول منتخبين من كل مجموعة + أفضل 8 منتخبات في المركز الثالث',
        'fr' => 'Les 2 premiers de chaque groupe + les 8 meilleurs troisièmes se qualifient',
        default => 'Top 2 of each group + 8 best third-placed teams advance',
    }) ?>
  </p>
</div>

<?php if (!$tables): ?>
  <p class="empty-note"><?= e(t('no_data')) ?></p>
<?php else: ?>
  <div class="groups-grid">
    <?php foreach ($tables as $group => $rows): ?>
      <?php render_group_table($group, $rows); ?>
    <?php endforeach; ?>
  </div>
  <p class="legend">
    <span class="legend-dot qualified"></span>
    <?= e(t('qualified')) ?>
  </p>

  <?php if ($thirds): ?>
    <div class="groups-grid third-place-grid">
      <?php render_third_place_table($thirds); ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php tpl('footer'); ?>
