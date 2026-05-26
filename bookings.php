<?php
/**
 * bookings.php — الإنذارات والطرد (البطاقات الصفراء/الحمراء على مستوى البطولة).
 *
 * يمسح كل المباريات ويجمع كل بطاقة في $m['cards'] (تأتي من API-Football أثناء
 * البطولة الفعلية؛ غائبة الآن). كل بطاقة:
 *   ['team' => 1|2, 'minute' => int, 'name' => string, 'type' => 'yellow'|'red']
 * فلتر اختياري: ?type=red لعرض حالات الطرد فقط.
 */
require __DIR__ . '/includes/bootstrap.php';

// الفلتر: 'red' أو 'all'
$filter = (isset($_GET['type']) && $_GET['type'] === 'red') ? 'red' : 'all';

// اجمع كل البطاقات من كل المباريات
$cards    = [];
$yellows  = 0;
$reds     = 0;

foreach (DataService::allMatches() as $m) {
    if (empty($m['cards']) || !is_array($m['cards'])) {
        continue;
    }
    foreach ($m['cards'] as $c) {
        if (!is_array($c)) continue;
        $type = ($c['type'] ?? '') === 'red' ? 'red' : 'yellow';
        if ($type === 'red') { $reds++; } else { $yellows++; }

        // المنتخب صاحب البطاقة (team = 1 أو 2 → team1/team2 لتلك المباراة)
        $teamSide = ((int)($c['team'] ?? 0) === 2) ? 2 : 1;
        $teamEn   = $teamSide === 2 ? ($m['team2'] ?? '') : ($m['team1'] ?? '');

        $cards[] = [
            'match_index' => (int)($m['_index'] ?? 0),
            'team1'       => $m['team1'] ?? '',
            'team2'       => $m['team2'] ?? '',
            'team_en'     => $teamEn,
            'minute'      => (int)($c['minute'] ?? 0),
            'name'        => (string)($c['name'] ?? ''),
            'type'        => $type,
        ];
    }
}

// ترتيب: حسب رقم المباراة ثم الدقيقة
usort($cards, function ($a, $b) {
    return [$a['match_index'], $a['minute']] <=> [$b['match_index'], $b['minute']];
});

// طبّق الفلتر على العرض (العدّادات تبقى للبطولة كاملة)
$visible = $cards;
if ($filter === 'red') {
    $visible = array_values(array_filter($cards, fn($c) => $c['type'] === 'red'));
}

$page_title = t('bookings');
$page_desc  = t('bookings_intro');
tpl('header');
?>

<div class="page-head">
  <h1>🟨 <?= e(t('bookings')) ?></h1>
  <p class="muted"><?= e(t('bookings_intro')) ?></p>
</div>

<div class="scoring-card">
  <span class="sc-pill sc-yellow">🟨 <?= e(t('yellow_cards')) ?>: <?= (int)$yellows ?></span>
  <span class="sc-pill sc-red">🟥 <?= e(t('red_cards')) ?>: <?= (int)$reds ?></span>
</div>

<div class="scoring-card">
  <a class="sc-pill <?= $filter === 'all' ? 'sc-on' : 'sc-off' ?>"
     href="<?= e(url('bookings.php')) ?>"><?= e(t('all_cards')) ?></a>
  <a class="sc-pill <?= $filter === 'red' ? 'sc-red' : 'sc-off' ?>"
     href="<?= e(url('bookings.php', ['type' => 'red'])) ?>">🟥 <?= e(t('red_only')) ?></a>
</div>

<?php if (empty($visible)): ?>
  <p class="empty-note"><?= e(t('no_cards')) ?></p>
<?php else: ?>
  <div class="lb-wrap">
    <table class="leaderboard">
      <thead>
        <tr>
          <th><?= e(t('minute')) ?></th>
          <th class="lb-name"><?= e(t('player')) ?></th>
          <th class="lb-name"><?= e(t('team')) ?></th>
          <th><?= e(t('card')) ?></th>
          <th class="lb-name"><?= e(t('match')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($visible as $c):
          $icon = $c['type'] === 'red' ? '🟥' : '🟨';
          $matchUrl = url('match.php', ['id' => $c['match_index']]);
        ?>
          <tr>
            <td class="lb-rank"><?= (int)$c['minute'] ?>'</td>
            <td class="lb-name"><?= $c['name'] !== '' ? e($c['name']) : '—' ?></td>
            <td class="lb-name">
              <?= flag_img($c['team_en'], 'w40') ?>
              <?= e(team_name($c['team_en'])) ?>
            </td>
            <td><?= $icon ?></td>
            <td class="lb-name">
              <a class="section-link" href="<?= e($matchUrl) ?>">
                <?= e(team_name($c['team1'])) ?> <?= e(t('vs')) ?> <?= e(team_name($c['team2'])) ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php tpl('footer'); ?>
