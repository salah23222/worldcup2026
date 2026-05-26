<?php
/**
 * stats.php — إحصائيات البطولة (محسوبة من النتائج الحقيقية).
 * كل الأرقام من Stats::compute() — تظهر تلقائياً عند انطلاق المباريات.
 */
require __DIR__ . '/includes/bootstrap.php';

$s = Stats::compute();

$page_title = t('stats');
$page_desc  = t('stats_intro');
tpl('header');

/** صفّ منتخب داخل جدول (مركز + علم + اسم + قيمة) */
function stat_team_row(int $rank, array $row, int $value): void { ?>
  <tr>
    <td class="lb-rank"><?= (int)$rank ?></td>
    <td class="lb-name">
      <?= flag_img($row['team'], 'w40') ?>
      <?= e(team_name($row['team'])) ?>
    </td>
    <td><strong><?= (int)$value ?></strong></td>
  </tr>
<?php }

/** صفّ مباراة (نتيجة + المنتخبان) مع رابط لصفحة المباراة */
function stat_match_row(array $m, int $metric): void {
    $matchUrl = url('match.php', ['id' => (int)$m['index']]); ?>
  <tr>
    <td class="lb-rank"><?= (int)$metric ?></td>
    <td class="lb-name">
      <a class="section-link" href="<?= e($matchUrl) ?>">
        <?= flag_img($m['t1'], 'w40') ?> <?= e(team_name($m['t1'])) ?>
        <strong><?= (int)$m['g1'] ?> - <?= (int)$m['g2'] ?></strong>
        <?= e(team_name($m['t2'])) ?> <?= flag_img($m['t2'], 'w40') ?>
      </a>
    </td>
  </tr>
<?php }
?>

<div class="page-head">
  <h1>📊 <?= e(t('stats')) ?></h1>
  <p class="muted"><?= e(t('stats_intro')) ?></p>
</div>

<?php if ($s['played'] === 0): ?>
  <p class="empty-note"><?= e(t('stats_empty')) ?></p>
  <div class="scoring-card">
    <span class="sc-pill"><?= e(t('matches_count')) ?></span>
    <span class="sc-pill"><?= e(t('teams_count')) ?></span>
    <span class="sc-pill"><?= e(t('cities_count')) ?></span>
  </div>
<?php else: ?>

  <div class="scoring-card">
    <span class="sc-pill"><?= e(t('matches_played')) ?>: <strong><?= (int)$s['played'] ?></strong></span>
    <span class="sc-pill">⚽ <?= e(t('total_goals')) ?>: <strong><?= (int)$s['goals'] ?></strong></span>
    <span class="sc-pill"><?= e(t('avg_goals')) ?>: <strong><?= e((string)$s['avg']) ?></strong></span>
    <span class="sc-pill sc-yellow">🟨 <?= (int)$s['yellows'] ?></span>
    <span class="sc-pill sc-red">🟥 <?= (int)$s['reds'] ?></span>
  </div>

  <div class="groups-grid">

    <?php if ($s['attack']): ?>
    <section class="section">
      <h2 class="section-title">⚽ <?= e(t('most_goals_for')) ?></h2>
      <div class="lb-wrap">
        <table class="leaderboard">
          <thead><tr><th><?= e(t('pos')) ?></th><th class="lb-name"><?= e(t('team')) ?></th><th><?= e(t('gf')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($s['attack'] as $i => $row) stat_team_row($i + 1, $row, $row['gf']); ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($s['defense']): ?>
    <section class="section">
      <h2 class="section-title">🛡️ <?= e(t('best_defense')) ?></h2>
      <div class="lb-wrap">
        <table class="leaderboard">
          <thead><tr><th><?= e(t('pos')) ?></th><th class="lb-name"><?= e(t('team')) ?></th><th><?= e(t('ga')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($s['defense'] as $i => $row) stat_team_row($i + 1, $row, $row['ga']); ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($s['high_scoring']): ?>
    <section class="section">
      <h2 class="section-title">🔥 <?= e(t('highest_scoring')) ?></h2>
      <div class="lb-wrap">
        <table class="leaderboard">
          <thead><tr><th><?= e(t('goals')) ?></th><th class="lb-name"><?= e(t('match')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($s['high_scoring'] as $m) stat_match_row($m, (int)$m['total']); ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($s['big_wins']): ?>
    <section class="section">
      <h2 class="section-title">💥 <?= e(t('biggest_wins')) ?></h2>
      <div class="lb-wrap">
        <table class="leaderboard">
          <thead><tr><th><?= e(t('gd')) ?></th><th class="lb-name"><?= e(t('match')) ?></th></tr></thead>
          <tbody>
            <?php foreach ($s['big_wins'] as $m) stat_match_row($m, (int)$m['margin']); ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </div>

  <?php if ($s['by_city']): $maxCity = max($s['by_city']); ?>
  <section class="section">
    <h2 class="section-title">🏟️ <?= e(t('goals_by_city')) ?></h2>
    <div class="stat-bars">
      <?php foreach ($s['by_city'] as $ground => $goals):
        $pct = $maxCity > 0 ? round($goals / $maxCity * 100) : 0; ?>
        <div class="stat-bar-row">
          <span class="stat-bar-label"><?= e($ground) ?></span>
          <span class="stat-bar-track"><span class="stat-bar-fill" style="width:<?= (int)$pct ?>%"></span></span>
          <span class="stat-bar-val"><?= (int)$goals ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

<?php endif; ?>

<?php tpl('footer'); ?>
