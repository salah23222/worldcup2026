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
    'title'      => $lang === 'ar' ? 'قوائم المنتخبات' : 'Team Squads',
    'intro'      => $lang === 'ar' ? 'لاعبو كل منتخب في كأس العالم 2026'
                                   : 'Player squads for the FIFA World Cup 2026',
    'all'        => $lang === 'ar' ? '← كل المنتخبات' : '← All teams',
    'squad'      => $lang === 'ar' ? 'القائمة' : 'Squad',
    'soon'       => $lang === 'ar' ? 'قائمة اللاعبين تُعلن قبل البطولة بأيام — ستظهر هنا تلقائياً.'
                                   : 'The 26-player squad is announced days before the tournament — it will appear here automatically.',
    'no'         => $lang === 'ar' ? 'لا توجد بيانات بعد.' : 'No data yet.',
    'num'        => $lang === 'ar' ? '#' : '#',
    'player'     => $lang === 'ar' ? 'اللاعب' : 'Player',
    'pos'        => $lang === 'ar' ? 'المركز' : 'Position',
];

// ترجمة المراكز من API-Football
$posMap = [
    'Goalkeeper' => $lang === 'ar' ? 'حراسة'  : 'Goalkeeper',
    'Defender'   => $lang === 'ar' ? 'دفاع'   : 'Defender',
    'Midfielder' => $lang === 'ar' ? 'وسط'    : 'Midfielder',
    'Attacker'   => $lang === 'ar' ? 'هجوم'   : 'Attacker',
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
