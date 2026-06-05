<?php
/**
 * squads.php — قوائم المنتخبات (لاعبو كل فريق).
 * بيانات اللاعبين من API-Football (تُفعَّل وقت البطولة بالمفتاح). قبل ذلك:
 * تُعرض المنتخبات المشاركة + «تُعلن قريباً». لا نختلق أي اسم لاعب.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang     = current_lang();
$allTeams = DataService::allTeams();             // [اسم => المجموعة]
$sel      = isset($_GET['team']) ? trim($_GET['team']) : '';
$selValid = $sel !== '' && isset($allTeams[$sel]);

$L = [
    'title'      => match($lang) { 'ar' => 'قوائم المنتخبات', 'fr' => 'Effectifs', default => 'Team Squads' },
    'intro'      => match($lang) { 'ar' => 'لاعبو كل منتخب في كأس العالم 2026', 'fr' => 'Effectifs des équipes pour la Coupe du Monde FIFA 2026', default => 'Player squads for the FIFA World Cup 2026' },
    'all'        => match($lang) { 'ar' => '← كل المنتخبات', 'fr' => '← Toutes les équipes', default => '← All teams' },
    'squad'      => match($lang) { 'ar' => 'القائمة', 'fr' => 'Effectif', default => 'Squad' },
    'soon'       => match($lang) { 'ar' => 'قائمة اللاعبين تُعلن قبل البطولة بأيام — ستظهر هنا تلقائياً.', 'fr' => 'La liste des 26 joueurs est annoncée quelques jours avant le tournoi — elle apparaîtra ici automatiquement.', default => 'The 26-player squad is announced days before the tournament — it will appear here automatically.' },
    'no'         => match($lang) { 'ar' => 'لا توجد بيانات بعد.', 'fr' => 'Pas encore de données.', default => 'No data yet.' },
    'num'        => '#',
    'player'     => match($lang) { 'ar' => 'اللاعب', 'fr' => 'Joueur', default => 'Player' },
    'pos'        => match($lang) { 'ar' => 'المركز', 'fr' => 'Position', default => 'Position' },
];

$posMap = [
    'Goalkeeper' => match($lang) { 'ar' => 'حراسة', 'fr' => 'Gardien', default => 'Goalkeeper' },
    'Defender'   => match($lang) { 'ar' => 'دفاع', 'fr' => 'Défenseur', default => 'Defender' },
    'Midfielder' => match($lang) { 'ar' => 'وسط', 'fr' => 'Milieu', default => 'Midfielder' },
    'Attacker'   => match($lang) { 'ar' => 'هجوم', 'fr' => 'Attaquant', default => 'Attacker' },
];

$page_title = $selValid ? (team_name($sel) . ' — ' . $L['squad']) : $L['title'];
$page_desc  = $L['intro'];
tpl('header');
?>

<?php if ($selValid):
    $squad = LiveService::squad($sel);
?>
  <p style="margin-bottom:14px">
    <a class="back-link" href="<?= e(url('squads.php')) ?>"><?= e($L['all']) ?></a>
  </p>
  <div class="page-head">
    <h1><?= flag_img($sel, 'w80') ?> <?= e(team_name($sel)) ?></h1>
    <p class="muted"><?= e($L['squad']) ?></p>
  </div>

  <?php if (!$squad): ?>
    <p class="empty-note">⏳ <?= e($L['soon']) ?></p>
  <?php else: ?>
    <div class="lb-wrap">
      <table class="leaderboard">
        <thead>
          <tr><th><?= e($L['num']) ?></th><th class="lb-name"><?= e($L['player']) ?></th><th><?= e($L['pos']) ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($squad as $p): ?>
            <tr>
              <td class="lb-rank"><?= $p['number'] !== null ? (int)$p['number'] : '—' ?></td>
              <td class="lb-name"><?= e($p['name']) ?></td>
              <td><?= e($posMap[$p['pos']] ?? $p['pos']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php else: ?>
  <div class="page-head">
    <h1>👥 <?= e($L['title']) ?></h1>
    <p class="muted"><?= e($L['intro']) ?></p>
  </div>

  <?php if (!$allTeams): ?>
    <p class="empty-note"><?= e($L['no']) ?></p>
  <?php else:
    // تجميع المنتخبات حسب المجموعة
    $byGroup = [];
    foreach ($allTeams as $name => $group) { $byGroup[$group !== '' ? $group : '—'][] = $name; }
    ksort($byGroup);
  ?>
    <?php foreach ($byGroup as $group => $names): ?>
      <section class="ref-section">
        <?php if ($group !== '—'): ?><h2 class="day-title"><?= e(group_label($group)) ?></h2><?php endif; ?>
        <div class="ref-grid">
          <?php foreach ($names as $name): ?>
            <a class="ref-item" href="<?= e(url('squads.php', ['team' => $name])) ?>">
              <?= flag_img($name, 'w40') ?>
              <div class="ref-meta"><span class="ref-name"><?= e(team_name($name)) ?></span></div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>

<?php tpl('footer'); ?>
