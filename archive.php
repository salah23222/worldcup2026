<?php
/**
 * archive.php — أرشيف كؤوس العالم السابقة (نتائج وأبطال).
 * ?year=2022 | 2018  (الافتراضي: الأحدث)
 */
require __DIR__ . '/includes/bootstrap.php';

$years = ArchiveService::years();
$year  = isset($_GET['year']) ? (int)$_GET['year'] : $years[0];
if (!ArchiveService::isValidYear($year)) {
    $year = $years[0];
}

$byRound  = ArchiveService::byRound($year);
$champion = ArchiveService::champion($year);

$page_title = t('archive') . ' ' . $year;
$page_desc  = t('archive_intro');
tpl('header');
?>

<div class="page-head">
  <h1>📚 <?= e(t('archive')) ?></h1>
  <p class="muted"><?= e(t('archive_intro')) ?></p>
</div>

<?php
$lang = current_lang();
$cflag = fn(string $code) => $code === ''
    ? ''
    : '<img class="flag" src="https://flagcdn.com/w40/' . e($code) . '.png" alt="" loading="lazy" width="28" height="21">';
?>

<!-- ============ سجل كل الأبطال (1930 → 2022) ============ -->
<section class="champions-roll">
  <h2 class="day-title">👑 <?= e(t('champions_roll')) ?> (1930 – 2022)</h2>

  <div class="titles-summary">
    <?php foreach (ArchiveService::titleCounts() as $tc): ?>
      <span class="title-chip">
        <?= $cflag($tc['flag']) ?>
        <strong><?= e($tc['ar']) ?></strong>
        <em><?= (int)$tc['titles'] ?>×</em>
      </span>
    <?php endforeach; ?>
  </div>

  <div class="lb-wrap">
    <table class="champions-table">
      <thead>
        <tr>
          <th><?= e(t('year_col')) ?></th>
          <th class="ch-win"><?= e(t('winner_col')) ?></th>
          <th><?= e(t('final_result')) ?></th>
          <th class="ch-run"><?= e(t('runner_up')) ?></th>
          <th class="ch-host"><?= e(t('host_col')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (ArchiveService::allChampions() as $c): ?>
          <tr>
            <td class="ch-year"><strong><?= (int)$c['year'] ?></strong></td>
            <td class="ch-win">
              <?= $cflag($c['winner']['flag']) ?>
              <span><?= e(match($lang) { 'ar' => $c['winner']['ar'], 'fr' => $c['winner']['fr'] ?? $c['winner']['en'], default => $c['winner']['en'] }) ?></span>
            </td>
            <td class="ch-score"><?= e($c['score']) ?></td>
            <td class="ch-run">
              <?= $cflag($c['runner']['flag']) ?>
              <span><?= e(match($lang) { 'ar' => $c['runner']['ar'], 'fr' => $c['runner']['fr'] ?? $c['runner']['en'], default => $c['runner']['en'] }) ?></span>
            </td>
            <td class="ch-host"><?= e(match($lang) { 'ar' => $c['host'], 'fr' => $c['host_fr'] ?? $c['host_en'], default => $c['host_en'] }) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<hr class="arch-sep">
<h2 class="day-title" style="margin-top:6px">🗂️ <?= e(t('final_result')) ?> — <?= e(t('archive')) ?></h2>

<!-- اختيار السنة -->
<div class="year-tabs">
  <?php foreach ($years as $y): ?>
    <a class="year-tab <?= $y === $year ? 'active' : '' ?>"
       href="<?= e(url('archive.php', ['year' => $y])) ?>"><?= (int)$y ?></a>
  <?php endforeach; ?>
</div>

<?php if ($champion && is_real_team($champion)): ?>
<section class="champion-banner champion-sm">
  <span class="champion-trophy">🏆</span>
  <p class="champion-label"><?= e(t('final_winner')) ?> <?= (int)$year ?></p>
  <div class="champion-name">
    <?= flag_img($champion, 'w80') ?>
    <h2><?= e(team_name($champion)) ?></h2>
  </div>
</section>
<?php endif; ?>

<?php if (!$byRound): ?>
  <p class="empty-note"><?= e(t('no_data')) ?></p>
<?php else: ?>
  <?php foreach ($byRound as $round => $matches): ?>
    <section class="day-block">
      <h2 class="day-title"><?= e(round_label($round)) ?></h2>
      <div class="arch-list">
        <?php foreach ($matches as $m):
          $t1 = trim($m['team1'] ?? '');
          $t2 = trim($m['team2'] ?? '');
          $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
          ?>
          <div class="arch-row">
            <div class="arch-team arch-team-1">
              <span class="arch-name"><?= e(team_name($t1)) ?></span>
              <?= flag_img($t1, 'w40') ?>
            </div>
            <div class="arch-score">
              <?php if ($hasScore): ?>
                <span><?= (int)$m['score']['ft'][0] ?></span>
                <span class="arch-colon">:</span>
                <span><?= (int)$m['score']['ft'][1] ?></span>
                <?php if (isset($m['score']['p'])): ?>
                  <small class="arch-pens">(<?= (int)$m['score']['p'][0] ?>:<?= (int)$m['score']['p'][1] ?>)</small>
                <?php endif; ?>
              <?php else: ?>
                <span class="arch-vs"><?= e(t('vs')) ?></span>
              <?php endif; ?>
            </div>
            <div class="arch-team arch-team-2">
              <?= flag_img($t2, 'w40') ?>
              <span class="arch-name"><?= e(team_name($t2)) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php tpl('footer'); ?>
