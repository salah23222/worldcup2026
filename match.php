<?php
/**
 * match.php — تفاصيل مباراة واحدة (?id=).
 */
require __DIR__ . '/includes/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
$m  = DataService::matchByIndex($id);

if ($m === null) {
    $page_title = t('matches');
    tpl('header');
    echo '<div class="alert">' . e(t('no_data')) . '</div>';
    echo '<p><a class="btn" href="' . e(url('matches.php')) . '">‹ ' . e(t('matches')) . '</a></p>';
    tpl('footer');
    exit;
}

$t1       = trim($m['team1'] ?? '');
$t2       = trim($m['team2'] ?? '');
$status   = $m['_status'];
$ts       = DataService::matchTimestamp($m);
$hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
$page_title = team_name($t1) . ' ' . t('vs') . ' ' . team_name($t2);
$page_desc  = $page_title . ' — ' . round_label($m['round'] ?? '')
            . (!empty($m['ground']) ? ' · ' . $m['ground'] : '');
$seo_type   = 'article';
$page_image = base_url() . '/card.php?id=' . (int)$id . '&mode=match&v=2';

tpl('header');
seo_sportsevent($m);
?>

<a class="back-link" href="<?= e(url('matches.php')) ?>">‹ <?= e(t('matches')) ?></a>

<article class="match-detail status-<?= e($status) ?>">
  <div class="md-meta">
    <span><?= e(round_label($m['round'] ?? '')) ?></span>
    <?php if (!empty($m['group'])): ?>
      · <span><?= e(group_label($m['group'])) ?></span>
    <?php endif; ?>
    ·
    <?php if ($status === 'live' && !empty($m['_live_minute'])): ?>
      <span class="badge badge-live">
        <span class="live-dot"></span><?= (int)$m['_live_minute'] ?>' <?= e(t('live')) ?>
      </span>
    <?php else: ?>
      <?= status_badge($status) ?>
    <?php endif; ?>
  </div>

  <div class="md-scoreline">
    <div class="md-team">
      <?= flag_img($t1, 'w160') ?>
      <h2><?= e(team_name($t1)) ?></h2>
      <?php if ($mr1 = Rankings::of($t1)): ?><span class="md-rank"><?= e(t('fifa_rank')) ?> #<?= (int)$mr1 ?></span><?php endif; ?>
    </div>

    <div class="md-center">
      <?php if ($hasScore): ?>
        <div class="md-score">
          <?= (int)$m['score']['ft'][0] ?>
          <span>:</span>
          <?= (int)$m['score']['ft'][1] ?>
        </div>
        <?php if (isset($m['score']['ht'])): ?>
          <p class="md-ht">(<?= (int)$m['score']['ht'][0] ?> : <?= (int)$m['score']['ht'][1] ?>)</p>
        <?php endif; ?>
        <?php if (isset($m['score']['p'])): ?>
          <p class="md-pens">
            <?= e(current_lang()==='ar' ? 'ركلات الترجيح' : 'Penalties') ?>:
            <?= (int)$m['score']['p'][0] ?> : <?= (int)$m['score']['p'][1] ?>
          </p>
        <?php endif; ?>
      <?php else: ?>
        <div class="md-time">
          <strong><?= local_dt($ts, 'time') ?></strong>
        </div>
        <?php
        if (AiContent::enabled()) {
            $aiPick = AiContent::matchPrediction($m);
            if ($aiPick !== null):
        ?>
          <p class="md-aipick">🤖 <?= e(t('ai_pick')) ?>: <strong><?= (int)$aiPick['p1'] ?>-<?= (int)$aiPick['p2'] ?></strong></p>
        <?php endif; } ?>
      <?php endif; ?>
    </div>

    <div class="md-team">
      <?= flag_img($t2, 'w160') ?>
      <h2><?= e(team_name($t2)) ?></h2>
      <?php if ($mr2 = Rankings::of($t2)): ?><span class="md-rank"><?= e(t('fifa_rank')) ?> #<?= (int)$mr2 ?></span><?php endif; ?>
    </div>
  </div>

  <!-- ============ معلومات المباراة ============ -->
  <ul class="md-info">
    <li>
      <span class="md-info-k">📅 <?= e(t('date')) ?></span>
      <span class="md-info-v"><?= local_dt($ts, 'date') ?></span>
    </li>
    <li>
      <span class="md-info-k">🕐 <?= e(t('time')) ?></span>
      <span class="md-info-v"><?= local_dt($ts, 'time') ?></span>
    </li>
    <?php if (!empty($m['ground'])): $st = Stadiums::byGround($m['ground']); ?>
    <li>
      <span class="md-info-k">🏟 <?= e(t('stadium')) ?></span>
      <span class="md-info-v">
        <?php if ($st): ?>
          <a class="section-link" href="<?= e(url('stadium.php', ['id' => $st['id']])) ?>"><?= e($m['ground']) ?></a>
        <?php else: ?>
          <?= e($m['ground']) ?>
        <?php endif; ?>
      </span>
    </li>
    <?php endif; ?>
    <li>
      <span class="md-info-k">🧑‍⚖️ <?= e(t('referee')) ?></span>
      <span class="md-info-v"><?= !empty($m['referee']) ? e($m['referee']) : '<span class="ref-tbd">• • • • • •</span>' ?></span>
    </li>
  </ul>

  <p class="md-cal">
    <a class="btn btn-sm" href="<?= e(url('calendar.php', ['id' => $id])) ?>">📅 <?= e(t('add_match_calendar')) ?></a>
  </p>

  <!-- ============ 👕 التشكيلة على الملعب (أساسيون/احتياط) — وقت المباراة ============ -->
  <?php
  $lineup = LiveService::lineupForMatch($m);
  $lastName = function (string $n): string { $a = preg_split('/\s+/', trim($n)); return end($a) ?: $n; };
  ?>
  <section class="lineup-box">
    <h2 class="section-head">👕 <?= e(t('lineup')) ?></h2>
    <?php if (!$lineup): ?>
      <p class="muted">⏳ <?= e(t('lineup_soon')) ?></p>
    <?php else: ?>
      <div class="lineup-cols">
        <?php foreach (['team1' => $t1, 'team2' => $t2] as $side => $tn):
          $LU = $lineup[$side] ?? null; if (!$LU) continue;
          // هل لكل اللاعبين موضع شبكي (row:col)؟ → ارسم ملعباً، وإلا قائمة نصّية
          $start   = $LU['start'] ?? [];
          $hasGrid = $start && !array_filter($start, fn($p) => ($p['grid'] ?? '') === '');
          $rows = [];
          if ($hasGrid) {
              foreach ($start as $p) {
                  $g = array_map('intval', explode(':', $p['grid']));
                  $rows[$g[0] ?? 0][] = ['p' => $p, 'c' => $g[1] ?? 0];
              }
              ksort($rows);
              foreach ($rows as &$ln) { usort($ln, fn($a, $b) => $a['c'] <=> $b['c']); }
              unset($ln);
              $maxRow = max(array_keys($rows));
          }
        ?>
          <div class="lineup-col">
            <h3><?= flag_img($tn, 'w40') ?> <?= e(team_name($tn)) ?>
              <?php if (!empty($LU['formation'])): ?><span class="lineup-formation"><?= e($LU['formation']) ?></span><?php endif; ?>
            </h3>

            <?php if ($hasGrid): ?>
              <div class="pitch" role="img" aria-label="<?= e(team_name($tn)) ?>">
                <?php foreach ($rows as $r => $ln):
                    $cnt = count($ln);
                    $y   = ($maxRow > 1) ? (92 - ($r - 1) / ($maxRow - 1) * 84) : 50;
                    foreach ($ln as $i => $cell):
                        $x = ($i + 1) / ($cnt + 1) * 100;
                        $p = $cell['p'];
                ?>
                  <span class="pitch-player" style="left:<?= round($x, 1) ?>%;top:<?= round($y, 1) ?>%">
                    <span class="pp-dot"><?= $p['number'] !== null ? (int)$p['number'] : '' ?></span>
                    <span class="pp-name"><?= e($lastName($p['name'])) ?></span>
                  </span>
                <?php endforeach; endforeach; ?>
              </div>
            <?php else: ?>
              <p class="lineup-label"><?= e(t('starters')) ?></p>
              <ol class="lineup-list"><?php foreach ($start as $p): ?><li><?= e($p['name']) ?></li><?php endforeach; ?></ol>
            <?php endif; ?>

            <?php if (!empty($LU['coach'])): ?><p class="lineup-coach"><?= e(t('coach')) ?>: <?= e($LU['coach']) ?></p><?php endif; ?>
            <?php if (!empty($LU['subs'])): ?>
              <p class="lineup-label"><?= e(t('subs')) ?></p>
              <ul class="lineup-list lineup-subs"><?php foreach ($LU['subs'] as $p): ?><li><?= e($p['name']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php render_share(canonical_url(), team_name($t1) . ' ' . t('vs') . ' ' . team_name($t2) . ' — ' . SITE_NAME_AR); ?>

  <!-- ============ 🔎 تحليل الخسارة الفنّي (للمباريات المنتهية فقط) ============ -->
  <?php
  if (AiContent::enabled() && $hasScore):
      $fg1 = (int)$m['score']['ft'][0]; $fg2 = (int)$m['score']['ft'][1];
      if ($fg1 !== $fg2):
          $loserEn = ($fg1 < $fg2) ? $t1 : $t2;
          if (is_real_team($loserEn)):
  ?>
  <section class="excuse-box">
    <button type="button" class="btn btn-sm excuse-btn" data-id="<?= (int)$id ?>">🔎 <?= e(t('excuse_btn')) ?> · <?= e(team_name($loserEn)) ?></button>
    <p class="excuse-out" id="excuseOut" hidden></p>
  </section>
  <script>
  (function(){
    var api = '<?= e(rtrim(SITE_URL,"/")) ?>/api/data.php';
    var out = document.getElementById('excuseOut');
    var loading = <?= json_encode(t('excuse_loading'), JSON_UNESCAPED_UNICODE) ?>;
    var btn = document.querySelector('.excuse-btn');
    if (btn) btn.addEventListener('click', function(){
      out.hidden = false; out.textContent = loading;
      fetch(api + '?action=loss&lang=<?= e(current_lang()) ?>&id=' + btn.getAttribute('data-id'), {cache:'no-store'})
        .then(function(r){return r.json();})
        .then(function(d){ out.textContent = (d && d.analysis) ? ('🔎 ' + d.analysis) : '—'; })
        .catch(function(){ out.textContent = '—'; });
    });
  })();
  </script>
  <?php endif; endif; endif; ?>

  <!-- ============ المحتوى الذكي (معاينة/ملخّص) ============ -->
  <?php
  if (AiContent::enabled()):
      $aiType = $hasScore ? 'summary' : 'preview';
      $aiText = AiContent::forMatch($m, $aiType);
      if ($aiText !== null):
  ?>
  <section class="ai-content">
    <h3>🧠 <?= e($hasScore ? t('ai_summary') : t('ai_preview')) ?></h3>
    <div class="ai-flags">
      <?= flag_img($t1, 'w40') ?><span class="ai-vs"><?= e(t('vs')) ?></span><?= flag_img($t2, 'w40') ?>
    </div>
    <?php foreach (preg_split('/\n+/', $aiText) as $para): if (trim($para) === '') continue; ?>
      <p><?= e($para) ?></p>
    <?php endforeach; ?>
    <p class="ai-note"><?= e(t('ai_note')) ?></p>
  </section>
  <?php endif; endif; ?>

  <!-- ============ الأهداف ============ -->
  <?php
  $goals1 = $m['goals1'] ?? [];
  $goals2 = $m['goals2'] ?? [];
  if ($goals1 || $goals2): ?>
  <div class="md-goals">
    <h3>⚽ <?= e(t('goals')) ?></h3>
    <div class="md-goals-cols">
      <ul class="goals-col">
        <?php foreach ($goals1 as $g): ?>
          <li>
            <span class="g-min"><?= (int)($g['minute'] ?? 0) ?><?= e(t('minute')) ?></span>
            <span class="g-name"><?= e($g['name'] ?? '') ?></span>
            <?php if (!empty($g['penalty'])): ?>
              <span class="g-tag">(<?= e(t('penalty')) ?>)</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <ul class="goals-col goals-col-2">
        <?php foreach ($goals2 as $g): ?>
          <li>
            <span class="g-min"><?= (int)($g['minute'] ?? 0) ?><?= e(t('minute')) ?></span>
            <span class="g-name"><?= e($g['name'] ?? '') ?></span>
            <?php if (!empty($g['penalty'])): ?>
              <span class="g-tag">(<?= e(t('penalty')) ?>)</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>

  <!-- ============ البطاقات (لحظي عبر API-Football فقط) ============ -->
  <?php if (!empty($m['cards']) && is_array($m['cards'])): ?>
  <div class="md-cards">
    <h3>🟨 <?= e(t('cards')) ?></h3>
    <ul class="cards-list">
      <?php foreach ($m['cards'] as $c):
        $cTeamEn = (((int)($c['team'] ?? 1)) === 2) ? $t2 : $t1;
        $isRed   = (($c['type'] ?? 'yellow') === 'red');
      ?>
      <li class="card-row card-<?= $isRed ? 'red' : 'yellow' ?>">
        <span class="card-min"><?= (int)($c['minute'] ?? 0) ?><?= e(t('minute')) ?></span>
        <span class="card-mark" aria-hidden="true"><?= $isRed ? '🟥' : '🟨' ?></span>
        <?= flag_img($cTeamEn, 'w40') ?>
        <span class="card-name"><?= e($c['name'] ?? '') ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</article>

<?php tpl('footer'); ?>
