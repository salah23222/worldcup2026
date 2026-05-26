<?php
/**
 * match_card.php — كرت مباراة واحد.
 * يتوقّع متغيّر $m (مصفوفة المباراة من DataService).
 * يُستدعى عبر: include بعد ضبط $m، أو render_match_card($m).
 */
if (!defined('WC2026')) { exit('Access denied'); }

function render_match_card(array $m): void {
    $t1     = trim($m['team1'] ?? '');
    $t2     = trim($m['team2'] ?? '');
    $status = $m['_status'] ?? DataService::matchStatus($m);
    $ts     = DataService::matchTimestamp($m);
    $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
    $detailUrl = url('match.php', ['id' => $m['_index'] ?? 0]);
    // استطلاع 1X2 سريع (فوز/تعادل/فوز) — للمباريات القادمة فقط (يُخفى للمقفلة/المنتهية).
    $poll = class_exists('Polls') ? Polls::card($m) : null;
    if ($poll !== null && !empty($poll['closed'])) { $poll = null; }
    ?>
    <div class="mc-wrap">
    <a class="match-card status-<?= e($status) ?>" href="<?= e($detailUrl) ?>">
      <div class="mc-top">
        <span class="mc-round"><?= e(round_label($m['round'] ?? '')) ?></span>
        <?php if ($status === 'live' && !empty($m['_live_minute'])): ?>
          <span class="badge badge-live">
            <span class="live-dot"></span><?= (int)$m['_live_minute'] ?>'
          </span>
        <?php else: ?>
          <?= status_badge($status) ?>
        <?php endif; ?>
      </div>

      <div class="mc-body">
        <div class="mc-team mc-team-1">
          <?= flag_img($t1, 'w80') ?>
          <span class="mc-team-name"><?= e(team_name($t1)) ?></span>
          <?php if ($r1 = Rankings::of($t1)): ?><span class="mc-rank">#<?= (int)$r1 ?></span><?php endif; ?>
        </div>

        <div class="mc-score">
          <?php if ($hasScore): ?>
            <span class="mc-score-num"><?= (int)$m['score']['ft'][0] ?></span>
            <span class="mc-score-sep">:</span>
            <span class="mc-score-num"><?= (int)$m['score']['ft'][1] ?></span>
          <?php else: ?>
            <span class="mc-time"><?= local_dt($ts, 'time') ?></span>
          <?php endif; ?>
        </div>

        <div class="mc-team mc-team-2">
          <?= flag_img($t2, 'w80') ?>
          <span class="mc-team-name"><?= e(team_name($t2)) ?></span>
          <?php if ($r2 = Rankings::of($t2)): ?><span class="mc-rank">#<?= (int)$r2 ?></span><?php endif; ?>
        </div>
      </div>

      <div class="mc-foot">
        <span class="mc-date"><?= local_dt($ts, 'date_short') ?></span>
        <?php if (!empty($m['ground'])): ?>
          <span class="mc-ground">📍 <?= e($m['ground']) ?></span>
        <?php endif; ?>
      </div>
    </a>
    <?php if ($poll !== null) render_match_poll($poll); ?>
    </div><!-- /.mc-wrap -->
    <?php
}

/**
 * render_match_poll() — ودجت تصويت 1X2 سريع أسفل بطاقة المباراة.
 * يعرض أزرار الاختيار (فوز/تعادل/فوز)، وبعد التصويت نسب الجمهور (شريط لكل خيار).
 * يُستدعى فقط لمباراة قابلة للاستطلاع (ليست مقفلة). كل النصوص مُهرَّبة بـe().
 */
function render_match_poll(array $p): void {
    $opts   = $p['options'] ?? [];
    if (!$opts) return;
    $counts = $p['counts'] ?? array_fill(0, count($opts), 0);
    $total  = (int)($p['total'] ?? array_sum($counts));
    $voted  = !empty($p['voted']);
    $choice = $p['choice'];
    $ar     = (current_lang() === 'ar');
    ?>
    <div class="mc-poll<?= $voted ? ' is-voted' : '' ?>" data-poll="<?= e($p['id']) ?>" data-opts="<?= count($opts) ?>"
         data-voted="<?= $voted ? '1' : '0' ?>" data-choice="<?= ($choice === null) ? '-1' : (int)$choice ?>">
      <div class="mc-poll-q"><?= e($ar ? 'توقّعك؟' : 'Your call?') ?></div>
      <div class="mc-poll-list">
        <?php foreach ($opts as $i => $label):
          $pct  = ($voted && $total > 0) ? (int)round(100 * $counts[$i] / $total) : 0;
          $mine = ($choice !== null && (int)$choice === (int)$i); ?>
          <button type="button" class="mc-poll-opt<?= $mine ? ' is-mine' : '' ?>" data-opt="<?= (int)$i ?>"<?= $voted ? ' disabled' : '' ?>>
            <i class="mcp-bar" style="width:<?= $pct ?>%"></i>
            <span class="mcp-lbl"><?= e($label) ?></span>
            <span class="mcp-pct"><?= $voted ? $pct . '%' : '' ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}
