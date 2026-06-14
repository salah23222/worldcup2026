<?php
/**
 * groups.php — جداول ترتيب المجموعات الـ12.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/group_table.php';

$page_title = t('groups');
$tables     = Standings::all();
$thirds     = Standings::thirdPlaceRanking();

// معاينة الرابط (تويتر/واتساب): ?g=A → بطاقة تلك المجموعة · بلا ?g → كل المجموعات
$gParam = strtoupper(preg_replace('/[^A-La-l]/', '', (string)($_GET['g'] ?? '')));
$page_image = ($gParam !== '')
    ? url('card_img.php', ['mode' => 'group',  'g' => $gParam[0], 'd' => card_rev()])
    : url('card_img.php', ['mode' => 'groups', 'd' => card_rev()]);

// عند ?g → عنوان/وصف خاصّان بالمجموعة فتظهر بطاقة المشاركة ذات معنى (لا «المجموعات» العامّة)
if ($gParam !== '') {
    $gL = $gParam[0];
    $page_title = t('group') . ' ' . $gL . ' — ' . t('standings');
    $page_desc  = current_lang() === 'ar'
        ? ('ترتيب المجموعة ' . $gL . ' في كأس العالم 2026 — النقاط والفارق والنتائج محدّثة.')
        : ('Group ' . $gL . ' standings at the FIFA World Cup 2026 — live points, goal difference and results.');
}

tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('groups')) ?> — <?= e(t('standings')) ?></h1>
  <p class="muted">
    <?= e(current_lang()==='ar'
        ? 'يتأهل أول منتخبين من كل مجموعة + أفضل 8 منتخبات في المركز الثالث'
        : 'Top 2 of each group + 8 best third-placed teams advance') ?>
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

  <!-- ============ مشاركة الجدول ============ -->
  <?php render_share(canonical_url(), t('groups') . ' — ' . t('standings') . ' — ' . SITE_NAME_AR); ?>
<?php endif; ?>

<?php tpl('footer'); ?>
