<?php
/**
 * team.php — صفحة منتخب غنيّة: نبذة + الترتيب + ترتيب المجموعة + المباريات + روابط.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';
require __DIR__ . '/templates/group_table.php';

$teamRaw  = isset($_GET['team']) ? trim($_GET['team']) : '';
$allTeams = DataService::allTeams();

if ($teamRaw === '' || !isset($allTeams[$teamRaw])) {
    $page_title = t('teams');
    tpl('header');
    echo '<div class="alert">' . e(t('no_data')) . '</div>';
    echo '<p><a class="btn" href="' . e(url('teams.php')) . '">‹ ' . e(t('teams')) . '</a></p>';
    tpl('footer');
    exit;
}

$lang  = current_lang();
$ar    = ($lang === 'ar');
$group = $allTeams[$teamRaw];
$rank  = Rankings::of($teamRaw);
$about = TeamInfo::about($teamRaw, $lang);

$matches = DataService::matchesForTeam($teamRaw);
usort($matches, fn($a, $b) =>
    (DataService::matchTimestamp($a) ?? 0) <=> (DataService::matchTimestamp($b) ?? 0));

// ترتيب المنتخب في مجموعته
$standRow = null; $standRank = null; $groupRows = [];
if ($group) {
    $groupRows = Standings::forGroup($group);
    foreach ($groupRows as $i => $r) {
        if ($r['team'] === $teamRaw) { $standRow = $r; $standRank = $i + 1; break; }
    }
}

$page_title = team_name($teamRaw);
$page_desc  = team_name($teamRaw) . ' — ' . ($group ? group_label($group) . ' · ' : '')
            . t('fifa_rank') . ($rank ? ' #' . (int)$rank : '');
$seo_type   = 'article';
if (!empty($about['crest'])) { $page_image = $about['crest']; }

tpl('header');
?>

<a class="back-link" href="<?= e(url('teams.php')) ?>">‹ <?= e(t('teams')) ?></a>

<section class="team-hero">
  <?= flag_img($teamRaw, 'w320') ?>
  <div class="team-hero-info">
    <h1><?= e(team_name($teamRaw)) ?></h1>
    <?php if ($ar && $teamRaw !== team_name($teamRaw)): ?>
      <p class="team-hero-en"><?= e($teamRaw) ?></p>
    <?php endif; ?>
    <div class="team-hero-badges">
      <?php if ($group): ?><span class="team-badge"><?= e(group_label($group)) ?></span><?php endif; ?>
      <?php if ($rank): ?><span class="team-badge team-badge-rank"><?= e(t('fifa_rank')) ?> #<?= (int)$rank ?></span><?php endif; ?>
    </div>
  </div>
</section>

<!-- ============ نبذة (ويكيبيديا) ============ -->
<?php if (!empty($about['bio'])): ?>
<section class="section">
  <h2 class="section-title"><?= e($ar ? 'نبذة' : 'About') ?></h2>
  <p class="ref-bio"><?= e($about['bio']) ?></p>
  <?php if (!empty($about['url'])): ?>
    <p class="ref-bio-src">
      <a href="<?= e($about['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($ar ? 'اقرأ المزيد' : 'Read more') ?> ↗</a>
      <span class="muted"> · <?= e($ar ? 'المصدر: ويكيبيديا' : 'Source: Wikipedia') ?></span>
    </p>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ============ بطاقة الترتيب ============ -->
<?php if ($standRow): ?>
<div class="team-stat-strip">
  <div><strong><?= (int)$standRank ?></strong><span><?= e(t('pos')) ?></span></div>
  <div><strong><?= (int)$standRow['pts'] ?></strong><span><?= e(t('points')) ?></span></div>
  <div><strong><?= (int)$standRow['p'] ?></strong><span><?= e(t('played')) ?></span></div>
  <div><strong><?= (int)$standRow['w'] ?></strong><span><?= e(t('won')) ?></span></div>
  <div><strong><?= (int)$standRow['d'] ?></strong><span><?= e(t('draw')) ?></span></div>
  <div><strong><?= (int)$standRow['l'] ?></strong><span><?= e(t('lost')) ?></span></div>
  <div><strong><?= ($standRow['gd']>0?'+':'').(int)$standRow['gd'] ?></strong><span><?= e(t('gd')) ?></span></div>
</div>
<?php endif; ?>

<!-- ============ ترتيب المجموعة ============ -->
<?php if ($group && $groupRows): ?>
<section class="section">
  <h2 class="section-title"><?= e($ar ? 'ترتيب المجموعة' : 'Group standing') ?></h2>
  <?php render_group_table($group, $groupRows); ?>
</section>
<?php endif; ?>

<!-- ============ مباريات المنتخب ============ -->
<section class="section">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(t('matches')) ?></h2>
  </div>
  <?php if ($matches): ?>
    <div class="match-grid">
      <?php foreach ($matches as $m) render_match_card($m); ?>
    </div>
  <?php else: ?>
    <p class="empty-note"><?= e(t('no_data')) ?></p>
  <?php endif; ?>
</section>

<!-- ============ روابط ============ -->
<div class="team-cta">
  <a class="btn-ghost" href="<?= e(url('squads.php')) ?>"><?= e(t('squads')) ?> ›</a>
  <a class="btn-cta" href="<?= e(url('predict.php')) ?>"><?= e(t('play_predict')) ?></a>
</div>

<?php render_share(canonical_url(), team_name($teamRaw) . ' — ' . SITE_NAME_AR); ?>

<?php tpl('footer'); ?>
