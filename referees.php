<?php
/**
 * referees.php — حكّام كأس العالم 2026 (القائمة الرسمية من FIFA)، مفرزون حسب الاختصاص.
 * البيانات في data/referees.json (تُولَّد من data/make_referees.php).
 * عدد المباريات يُحسب تلقائياً من $m['referee'] (يظهر أثناء البطولة عند تعيين الحكّام).
 */
require __DIR__ . '/includes/bootstrap.php';

$lang     = current_lang();
$referees = Referees::all();

$L = [
    'title' => $lang === 'ar' ? 'الحكّام' : 'Referees',
    'intro' => $lang === 'ar' ? 'القائمة الرسمية لحكّام كأس العالم FIFA 2026 — مفرزون حسب الاختصاص'
                              : 'Official FIFA World Cup 2026 match officials — by role',
    'empty' => $lang === 'ar' ? 'ستُعلَن أسماء الحكام قبل انطلاق البطولة'
                              : 'Referees will be announced before the tournament begins',
    'matches' => $lang === 'ar' ? 'مباراة' : 'matches',
];

$roleLabels = [
    'referee'   => $lang === 'ar' ? 'الحكّام'              : 'Referees',
    'assistant' => $lang === 'ar' ? 'الحكّام المساعدون'    : 'Assistant Referees',
    'var'       => $lang === 'ar' ? 'حكّام الفيديو (VAR)'  : 'Video Match Officials (VAR)',
];

// تجميع حسب الاختصاص (مع الحفاظ على ترتيب القائمة الأصلي كـ index للرابط التفصيلي)
$groups = ['referee' => [], 'assistant' => [], 'var' => []];
foreach ($referees as $idx => $r) {
    $role = $r['role'] ?? 'referee';
    if (!isset($groups[$role])) $role = 'referee';
    $r['_index'] = $idx;          // ترتيبه في القائمة → referee.php?i=
    $groups[$role][] = $r;
}

$page_title = $L['title'];
$page_desc  = $L['intro'];
tpl('header');
?>

<div class="page-head">
  <h1>🧑‍⚖️ <?= e($L['title']) ?></h1>
  <p class="muted"><?= e($L['intro']) ?></p>
</div>

<?php
// ===== إحصائيات شاملة بالمجموع (كل مباريات البطولة المنتهية — ESPN) =====
$agg = ['matches'=>0,'yellow'=>0,'red'=>0,'fouls'=>0,'offsides'=>0,'goals'=>0,'pens'=>0];
$aggRefs = 0;
foreach (Referees::tournamentStats() as $tr) {
    if ((int)($tr['matches'] ?? 0) < 1) continue;
    $aggRefs++;
    foreach (['matches','yellow','red','fouls','offsides','goals','pens'] as $k) {
        $agg[$k] += (int)($tr[$k] ?? 0);
    }
}
$aggCards = $agg['yellow'] + $agg['red'];
$aggCpm   = $agg['matches'] ? round($aggCards   / $agg['matches'], 1) : 0;
$aggFpm   = $agg['matches'] ? round($agg['fouls'] / $agg['matches'], 1) : 0;

// كل بطاقات البطولة (مباراة · دقيقة · لاعب · حكم · نوع) — للوحة التفاصيل عند النقر
$allCards = [];
foreach (DataService::allMatches() as $mm) {
    if (($mm['_status'] ?? '') !== 'finished') continue;
    $t1 = trim((string)($mm['team1'] ?? ''));
    $t2 = trim((string)($mm['team2'] ?? ''));
    $refN = trim((string)(($mm['officials']['main']['name'] ?? '') ?: ($mm['referee'] ?? '')));
    foreach ((array)($mm['cards'] ?? []) as $c) {
        $isT1 = (int)($c['team'] ?? 1) === 1;
        $allCards[] = [
            'idx'    => (int)($mm['_index'] ?? -1),
            'match'  => team_name($t1) . ' × ' . team_name($t2),
            'minute' => isset($c['minute']) ? (int)$c['minute'] : null,
            'player' => (string)($c['name'] ?? ''),
            'teamEn' => $isT1 ? $t1 : $t2,
            'ref'    => $refN,
            'type'   => (($c['type'] ?? '') === 'red') ? 'red' : 'yellow',
        ];
    }
}
usort($allCards, fn($a, $b) => [$a['idx'], (int)$a['minute']] <=> [$b['idx'], (int)$b['minute']]);
?>
<?php if ($agg['matches'] > 0): ?>
<section class="ref-section">
  <h2 class="day-title">🌍 <?= e($lang === 'ar' ? 'إحصائيات شاملة — بالمجموع' : 'Overall totals') ?></h2>
  <div class="ref-stats-grid">
    <div class="ref-stat"><div class="ref-stat-v"><?= $agg['matches'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'مباريات أُديرت' : 'Matches') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v"><?= $aggRefs ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'حكّام أداروا' : 'Referees') ?></div></div>
    <button type="button" class="ref-stat ref-stat-click" data-cards="yellow"><div class="ref-stat-v" style="color:#f7e09a">🟨 <?= $agg['yellow'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات صفراء' : 'Yellow') ?> <span class="ref-stat-hint">👁</span></div></button>
    <button type="button" class="ref-stat ref-stat-click" data-cards="red"><div class="ref-stat-v" style="color:#ef4444">🟥 <?= $agg['red'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات حمراء' : 'Red') ?> <span class="ref-stat-hint">👁</span></div></button>
    <div class="ref-stat"><div class="ref-stat-v">🚫 <?= $agg['fouls'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'إجمالي الأخطاء' : 'Fouls') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">🚩 <?= $agg['offsides'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'حالات تسلّل' : 'Offsides') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">🥅 <?= $agg['goals'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'الأهداف' : 'Goals') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">⚽ <?= $agg['pens'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'ركلات جزاء' : 'Penalties') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v"><?= e((string)$aggCpm) ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقة/مباراة' : 'Cards/match') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v"><?= e((string)$aggFpm) ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'خطأ/مباراة' : 'Fouls/match') ?></div></div>
  </div>
</section>

<?php if ($allCards): ?>
<section class="ref-section" id="cardsPanel" hidden>
  <h2 class="day-title" style="display:flex;align-items:center;gap:10px;justify-content:space-between">
    <span id="cardsPanelTitle"></span>
    <button type="button" class="cards-close" aria-label="<?= e($lang === 'ar' ? 'إغلاق' : 'Close') ?>">✕</button>
  </h2>
  <div class="ref-stats-scroll" style="max-height:62vh">
    <table class="ref-log-tbl">
      <thead>
        <tr>
          <th class="rst-name"><?= e($lang === 'ar' ? 'المباراة' : 'Match') ?></th>
          <th><?= e($lang === 'ar' ? 'الدقيقة' : 'Min') ?></th>
          <th class="rst-name"><?= e($lang === 'ar' ? 'اللاعب' : 'Player') ?></th>
          <th class="rst-name"><?= e($lang === 'ar' ? 'الحكم' : 'Referee') ?></th>
          <th><?= e($lang === 'ar' ? 'النوع' : 'Type') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($allCards as $c):
          $fl = function_exists('flag_url') ? flag_url($c['teamEn'], 'w20') : '';
        ?>
        <tr data-type="<?= $c['type'] ?>">
          <td class="rst-name"><a href="<?= e(url('match.php', ['id' => $c['idx']])) ?>"><?= e($c['match']) ?></a></td>
          <td class="rst-rank"><?= $c['minute'] !== null ? e($c['minute'] . "'") : '—' ?></td>
          <td class="rst-name">
            <?php if ($fl !== ''): ?><img src="<?= e($fl) ?>" alt="" loading="lazy" width="18" height="13"> <?php endif; ?>
            <?= e($c['player']) ?>
          </td>
          <td class="rst-name"><?= e($c['ref']) ?></td>
          <td><?= $c['type'] === 'red' ? '🟥' : '🟨' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
(function(){
  var panel = document.getElementById('cardsPanel');
  if (!panel) return;
  var titleEl = document.getElementById('cardsPanelTitle');
  var rows    = panel.querySelectorAll('tbody tr');
  var LBL = { yellow: '🟨 <?= e($lang === 'ar' ? 'كل البطاقات الصفراء' : 'All yellow cards') ?>',
              red:    '🟥 <?= e($lang === 'ar' ? 'كل البطاقات الحمراء' : 'All red cards') ?>' };
  function show(type){
    var n = 0;
    rows.forEach(function(r){
      var ok = r.getAttribute('data-type') === type;
      r.style.display = ok ? '' : 'none';
      if (ok) n++;
    });
    titleEl.textContent = LBL[type] + ' (' + n + ')';
    panel.hidden = false;
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  document.querySelectorAll('.ref-stat-click').forEach(function(b){
    b.addEventListener('click', function(){ show(b.getAttribute('data-cards')); });
  });
  var close = panel.querySelector('.cards-close');
  if (close) close.addEventListener('click', function(){ panel.hidden = true; });
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php
// ===== قسم إحصائيات الحكّام: من أدار مباريات + أرقامه الحقيقيّة (ESPN) =====
$statRows = [];
foreach ($referees as $idx => $r) {
    if (($r['role'] ?? 'referee') !== 'referee') continue;   // الحكّام الرئيسيون فقط
    $nm = trim((string)($r['name'] ?? ''));
    if ($nm === '') continue;
    $st = Referees::statsFor($nm);
    if (!$st || (int)($st['matches'] ?? 0) < 1) continue;
    $mt = (int)$st['matches'];
    $yl = (int)($st['yellow'] ?? 0);
    $rd = (int)($st['red'] ?? 0);
    $fl = (int)($st['fouls'] ?? 0);
    $statRows[] = [
        'idx'     => $idx,
        'name'    => $nm,
        'flag'    => strtolower(trim((string)($r['flag'] ?? ''))),
        'matches' => $mt,
        'yellow'  => $yl,
        'red'     => $rd,
        'cards'   => $yl + $rd,
        'fouls'   => $fl,
        'offsides'=> (int)($st['offsides'] ?? 0),
        'goals'   => (int)($st['goals'] ?? 0),
        'pens'    => (int)($st['pens'] ?? 0),
        'fpm'     => $mt ? round($fl / $mt, 1) : 0,
        'avg'     => $mt ? round(($yl + $rd) / $mt, 1) : 0,
    ];
}
usort($statRows, fn($a, $b) => [$b['matches'], $b['fouls']] <=> [$a['matches'], $a['fouls']]);

$strictOf = function (float $avg) use ($lang): array {
    if ($avg < 2) return [$lang === 'ar' ? 'هادئ'      : 'Lenient',     '#36c08f'];
    if ($avg < 4) return [$lang === 'ar' ? 'متوازن'    : 'Balanced',    '#f7e09a'];
    if ($avg < 6) return [$lang === 'ar' ? 'صارم'      : 'Strict',      '#f59e0b'];
    return              [$lang === 'ar' ? 'صارم جداً'  : 'Very strict', '#ef4444'];
};
?>

<?php if ($statRows): ?>
<section class="ref-section">
  <h2 class="day-title">📊 <?= e($lang === 'ar' ? 'إحصائيات الحكّام' : 'Referee statistics') ?>
    <span class="set-count">(<?= count($statRows) ?>)</span>
  </h2>
  <p class="muted" style="font-size:.8rem;margin:-4px 0 12px">
    <?= e($lang === 'ar'
        ? 'أرقام حقيقيّة من مباريات البطولة (المصدر: ESPN) — اضغط عنوان أي عمود للترتيب، أو اسم الحكم لصفحته الكاملة.'
        : 'Real figures from tournament matches (source: ESPN) — click any column to sort, or a name for full stats.') ?>
  </p>
  <div class="ref-stats-scroll">
    <table class="ref-stats-tbl" id="refStatsTbl">
      <thead>
        <tr>
          <th>#</th>
          <th class="rst-name"><?= e($lang === 'ar' ? 'الحكم' : 'Referee') ?></th>
          <th data-k><?= e($lang === 'ar' ? 'مباريات' : 'Matches') ?></th>
          <th data-k>🟨 · 🟥</th>
          <th data-k>🚫 <?= e($lang === 'ar' ? 'أخطاء' : 'Fouls') ?></th>
          <th data-k><?= e($lang === 'ar' ? 'خطأ/مباراة' : 'Fouls/m') ?></th>
          <th data-k>🚩 <?= e($lang === 'ar' ? 'تسلّل' : 'Offside') ?></th>
          <th data-k>⚽ <?= e($lang === 'ar' ? 'أهداف' : 'Goals') ?></th>
          <th data-k><?= e($lang === 'ar' ? 'جزاء' : 'Pens') ?></th>
          <th data-k><?= e($lang === 'ar' ? 'الصرامة' : 'Strictness') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($statRows as $i => $s):
          [$sLabel, $sColor] = $strictOf((float)$s['avg']);
        ?>
        <tr>
          <td class="rst-rank"><?= $i + 1 ?></td>
          <td class="rst-name">
            <a href="<?= e(url('referee.php', ['i' => $s['idx']])) ?>">
              <?php if ($s['flag'] !== ''): ?><img src="https://flagcdn.com/w20/<?= e($s['flag']) ?>.png" alt="" loading="lazy" width="20" height="15"> <?php endif; ?>
              <?= e($s['name']) ?>
            </a>
          </td>
          <td data-v="<?= $s['matches'] ?>"><?= $s['matches'] ?></td>
          <td data-v="<?= $s['cards'] ?>"><span class="rst-y"><?= $s['yellow'] ?></span> · <span class="rst-r"><?= $s['red'] ?></span></td>
          <td data-v="<?= $s['fouls'] ?>"><?= $s['fouls'] ?></td>
          <td data-v="<?= $s['fpm'] ?>"><?= e((string)$s['fpm']) ?></td>
          <td data-v="<?= $s['offsides'] ?>"><?= $s['offsides'] ?></td>
          <td data-v="<?= $s['goals'] ?>"><?= $s['goals'] ?></td>
          <td data-v="<?= $s['pens'] ?>"><?= $s['pens'] ?></td>
          <td data-v="<?= $s['avg'] ?>"><span class="rst-dot" style="background:<?= $sColor ?>"></span><?= e($sLabel) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<script>
(function(){
  var tbl = document.getElementById('refStatsTbl');
  if (!tbl) return;
  var body = tbl.tBodies[0];
  tbl.querySelectorAll('th[data-k]').forEach(function(th){
    var desc = true;
    th.addEventListener('click', function(){
      var idx  = Array.prototype.indexOf.call(th.parentNode.children, th);
      var rows = Array.prototype.slice.call(body.rows);
      rows.sort(function(a, b){
        var va = parseFloat(a.cells[idx].getAttribute('data-v')) || 0;
        var vb = parseFloat(b.cells[idx].getAttribute('data-v')) || 0;
        return desc ? vb - va : va - vb;
      });
      desc = !desc;
      rows.forEach(function(r, i){ r.cells[0].textContent = i + 1; body.appendChild(r); });
    });
  });
})();
</script>
<?php endif; ?>

<?php render_share(canonical_url(), $lang === 'ar'
    ? '🧑‍⚖️ إحصائيات حكّام كأس العالم 2026 — البطاقات والأخطاء والتسلّل والصرامة لكل حكم'
    : '🧑‍⚖️ FIFA World Cup 2026 referee stats — cards, fouls, offsides & strictness per referee'); ?>

<h2 class="day-title" style="margin-top:26px">📋 <?= e($lang === 'ar' ? 'القائمة الكاملة' : 'Full list') ?></h2>

<?php if (!$referees): ?>
  <p class="empty-note"><?= e($L['empty']) ?></p>
<?php else: ?>
  <?php foreach ($groups as $role => $list): if (!$list) continue; ?>
    <section class="ref-section">
      <h2 class="day-title">
        <?= e($roleLabels[$role]) ?>
        <span class="set-count">(<?= count($list) ?>)</span>
      </h2>
      <div class="ref-grid">
        <?php foreach ($list as $r):
          $name = trim((string)($r['name'] ?? ''));
          if ($name === '') continue;
          $country = $lang === 'ar'
                   ? ($r['country_ar'] ?? $r['country_en'] ?? '')
                   : ($r['country_en'] ?? $r['country_ar'] ?? '');
          $flag = strtolower(trim((string)($r['flag'] ?? '')));
          // مطابقة ذكيّة (sameRef): أسماء القائمة «SURNAME Given» تختلف عن حقل
          // المباراة «Given Surname» → المطابقة الحرفيّة كانت تُخفي معظم الحكّام.
          $n    = Referees::matchesOfficiated($name);
          $href = url('referee.php', ['i' => (int)($r['_index'] ?? 0)]);
          // فاولات/مباراة الحقيقيّة (من إحصائيات ESPN) — تظهر عند توفّرها
          $rst  = Referees::statsFor($name);
          $fpm  = ($rst && (int)($rst['matches'] ?? 0) > 0 && (int)($rst['fouls'] ?? 0) > 0)
                ? round($rst['fouls'] / $rst['matches'], 1) : null;
        ?>
          <a class="ref-item" href="<?= e($href) ?>">
            <?php if ($flag !== ''): ?>
              <img class="flag" src="https://flagcdn.com/w40/<?= e($flag) ?>.png"
                   alt="" loading="lazy" width="32" height="24">
            <?php endif; ?>
            <div class="ref-meta">
              <span class="ref-name"><?= e($name) ?></span>
              <span class="ref-country"><?= e($country) ?></span>
            </div>
            <?php if ($n > 0): ?>
              <span class="ref-nums">
                <span class="ref-matches"><?= (int)$n ?> <?= e($L['matches']) ?></span>
                <?php if ($fpm !== null): ?>
                  <span class="ref-fpm" title="<?= e($lang === 'ar' ? 'خطأ لكل مباراة (ESPN)' : 'Fouls per match (ESPN)') ?>">🚫 <?= e((string)$fpm) ?></span>
                <?php endif; ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?php $isAr = (current_lang() === 'ar'); ?>
<section class="section">
  <h2 class="section-title">🔭 <?= e($isAr ? 'مصادر تحكيميّة موثوقة' : 'Trusted refereeing sources') ?></h2>
  <div class="ref-sources">
    <a class="ref-item" href="https://x.com/ArbitroInteBlog" target="_blank" rel="noopener">
      <span class="ref-src-ico">𝕏</span>
      <div class="ref-meta">
        <span class="ref-name">Arbitro Internacional</span>
        <span class="ref-country"><?= e($isAr ? 'تعيينات الحكام أوّلاً بأوّل + تحليل الحالات' : 'Appointments first + decision analysis') ?></span>
      </div>
    </a>
    <a class="ref-item" href="https://x.com/DaleJohnsonESPN" target="_blank" rel="noopener">
      <span class="ref-src-ico">𝕏</span>
      <div class="ref-meta">
        <span class="ref-name">Dale Johnson — ESPN</span>
        <span class="ref-country"><?= e($isAr ? 'شرح قرارات الـVAR الجدليّة' : 'VAR decisions explained') ?></span>
      </div>
    </a>
    <a class="ref-item" href="https://www.theifab.com/laws-of-the-game-documents/" target="_blank" rel="noopener">
      <span class="ref-src-ico">⚖️</span>
      <div class="ref-meta">
        <span class="ref-name">IFAB — <?= e($isAr ? 'قانون اللعبة' : 'Laws of the Game') ?></span>
        <span class="ref-country"><?= e($isAr ? 'المرجع الرسمي (نسخة عربيّة متاحة)' : 'Official rules (Arabic available)') ?></span>
      </div>
    </a>
  </div>
</section>

<?php tpl('footer'); ?>
