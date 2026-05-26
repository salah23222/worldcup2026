<?php
/**
 * news.php — أخبار كأس العالم (RSS مجاني، يتحدّث تلقائياً).
 */
require __DIR__ . '/includes/bootstrap.php';

$items = News::latest();

$page_title = t('latest_news');
$page_desc  = t('latest_news') . ' — ' . t('site_desc');
tpl('header');
?>

<div class="page-head">
  <h1>📰 <?= e(t('latest_news')) ?></h1>
  <p class="muted"><?= e(t('site_desc')) ?></p>
</div>

<?php if (!$items): ?>
  <p class="empty-note"><?= e(t('no_news')) ?></p>
<?php else: ?>
  <div class="news-list">
    <?php foreach ($items as $it) render_news_item($it); ?>
  </div>
<?php endif; ?>

<?php tpl('footer'); ?>
