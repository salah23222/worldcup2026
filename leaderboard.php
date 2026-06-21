<?php
/**
 * leaderboard.php — لوحة الصدارة العالمية لمسابقة التوقعات.
 */
require __DIR__ . '/includes/bootstrap.php';

$rows    = Predictions::leaderboard(100);
$players = Predictions::playerCount();
$me      = Predictions::user();

$page_title = t('leaderboard');
$page_desc  = t('comp_intro');
// بطاقة مشاركة بأسماء المتصدّرين ونقاطهم (بدل البطاقة الافتراضيّة)
$page_image = url('card_img.php', ['mode' => 'leaderboard', 'd' => card_rev()]);
tpl('header');
?>

<div class="page-head">
  <h1>🏆 <?= e(t('top_players')) ?></h1>
  <p class="muted">
    <?= (int)$players ?> <?= e(t('players_count')) ?> ·
    <a class="section-link" href="<?= e(url('predict.php')) ?>"><?= e(t('competition')) ?> ›</a>
  </p>
</div>

<div class="scoring-card">
  <span class="sc-pill sc-3"><?= e(t('scoring_exact')) ?></span>
  <span class="sc-pill sc-2"><?= e(t('scoring_winner')) ?></span>
  <span class="sc-pill sc-1"><?= e(t('scoring_draw')) ?></span>
</div>

<?php if ($players === 0): ?>
  <p class="empty-note"><?= e(t('no_players')) ?> <?= e(t('beat_ai')) ?></p>
<?php endif; ?>

<div class="lb-wrap" data-autorefresh="1">
  <table class="leaderboard">
    <thead>
      <tr>
        <th><?= e(t('rank')) ?></th>
        <th class="lb-name"><?= e(t('player')) ?></th>
        <th><?= e(t('points')) ?></th>
        <th><?= e(t('exact')) ?></th>
        <th><?= e(t('correct_w')) ?></th>
        <th><?= e(t('predicted')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
        $isMe = $me && $me['nickname'] === $r['nickname'];
        $isAi = !empty($r['is_ai']);
        $rank = $i + 1; ?>
        <tr class="<?= $isMe ? 'lb-me' : '' ?> <?= $isAi ? 'lb-ai' : '' ?> <?= $rank <= 3 ? 'lb-top lb-top-' . $rank : '' ?>">
          <td class="lb-rank"><?= $rank <= 3 ? ['','🥇','🥈','🥉'][$rank] : $rank ?></td>
          <td class="lb-name"><?php if ($isAi): ?><?= e($r['nickname']) ?><?php else: ?><a class="lb-link" href="<?= e(url('predictor.php', ['u' => $r['nickname']])) ?>"><?= e($r['nickname']) ?></a><?php endif; ?></td>
          <td class="lb-pts"><strong><?= (int)$r['points'] ?></strong></td>
          <td><?= (int)$r['exact'] ?></td>
          <td><?= (int)$r['correct'] ?></td>
          <td><?= (int)$r['played'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<p class="muted" style="text-align:center;font-size:.85rem;margin:10px 0 0">
  <?= e(current_lang()==='ar' ? 'اضغط على اسم أيّ لاعب لرؤية توقّعاته' : (current_lang()==='fr' ? "Cliquez sur un nom pour voir ses pronostics" : "Tap any player's name to see their predictions")) ?>
</p>
<style>.lb-link{color:inherit;text-decoration:none;border-bottom:1px dotted rgba(255,255,255,.35)}.lb-link:hover{border-bottom-style:solid}</style>

<?php render_share(canonical_url(), t('leaderboard') . ' — ' . SITE_NAME_AR); ?>

<?php if ($players === 0): ?>
  <p style="text-align:center;margin-top:16px">
    <a class="btn btn-accent" href="<?= e(url('predict.php')) ?>"><?= e(t('join')) ?> ›</a>
  </p>
<?php endif; ?>

<?php tpl('footer'); ?>
