<?php
/**
 * article.php — صفحة عرض الخبر داخل الموقع (?i=معرّف الخبر).
 * تعرض العنوان وصورة المصدر والوقت، وزرّاً بارزاً لقراءة المقال كاملاً على المصدر.
 */
require __DIR__ . '/includes/bootstrap.php';

$id   = isset($_GET['i']) ? trim($_GET['i']) : '';
$item = News::find($id);

if ($item === null) {
    $page_title = t('news');
    tpl('header');
    echo '<div class="alert">' . e(t('news_not_found')) . '</div>';
    echo '<p><a class="btn" href="' . e(url('news.php')) . '">‹ ' . e(t('back_to_news')) . '</a></p>';
    tpl('footer');
    exit;
}

// إثراء من صفحة المصدر المباشرة (صورة أوضح + نص تمهيدي)
$rich    = News::enrich($item['link']);
$heroImg = !empty($rich['image']) ? $rich['image'] : ($item['image'] ?? '');
$summary = (!empty($rich['desc']) && mb_strlen($rich['desc']) > mb_strlen($item['summary'] ?? ''))
         ? $rich['desc'] : ($item['summary'] ?? '');

$page_title = $item['title'];
$page_desc  = $summary !== '' ? mb_substr($summary, 0, 160) : ($item['title']);
$seo_type   = 'article';
tpl('header');
?>

<a class="back-link" href="<?= e(url('news.php')) ?>">‹ <?= e(t('back_to_news')) ?></a>

<article class="article-view">
  <?php if ($heroImg !== ''): ?>
    <div class="article-hero"><img src="<?= e($heroImg) ?>" alt="<?= e($item['title']) ?>" loading="lazy"></div>
  <?php endif; ?>

  <div class="article-head">
    <?php if (!empty($item['logo'])): ?>
      <span class="article-logo"><img src="<?= e($item['logo']) ?>" alt="<?= e($item['source'] ?? '') ?>" width="64" height="64"></span>
    <?php endif; ?>
    <div class="article-headmeta">
      <?php if (!empty($item['source'])): ?>
        <span class="article-source"><?= e($item['source']) ?></span>
      <?php endif; ?>
      <?php if (!empty($item['ts'])): ?>
        <span class="article-time"><?= e(t('published')) ?>: <?= local_dt((int)$item['ts'], 'datetime') ?></span>
      <?php endif; ?>
    </div>
  </div>

  <h1 class="article-title"><?= e($item['title']) ?></h1>

  <?php if ($summary !== ''): ?>
    <p class="article-summary"><?= e($summary) ?></p>
  <?php endif; ?>

  <div class="article-cta">
    <a class="btn btn-accent" href="<?= e($item['link']) ?>" target="_blank" rel="noopener nofollow">
      <?= e(t('read_full')) ?> <?= e($item['source'] ?: ($item['host'] ?? '')) ?> ↗
    </a>
  </div>

  <?php render_share(canonical_url(), $item['title']); ?>
</article>

<!-- أخبار أخرى -->
<?php $more = array_slice(array_filter(News::latest(7), fn($n) => ($n['id'] ?? '') !== $id), 0, 5); ?>
<?php if ($more): ?>
<section class="section" style="margin-top:30px">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(t('latest_news')) ?></h2>
    <a class="section-link" href="<?= e(url('news.php')) ?>"><?= e(t('news_more')) ?> ›</a>
  </div>
  <div class="news-list">
    <?php foreach ($more as $it) render_news_item($it); ?>
  </div>
</section>
<?php endif; ?>

<?php tpl('footer'); ?>
