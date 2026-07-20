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
// ===== القارّات (الاتحادات) — ألوان أولمبيّة رسميّة مكيّفة للثيم الداكن =====
//  أزرق=أوروبا · أصفر=آسيا · (الأسود الأولمبي لأفريقيا يختفي على الداكن → أخضر) ·
//  الأحمر الأولمبي للأمريكتَين → الجنوبية أحمر والشمالية برتقالي (نفس العائلة).
$CONTINENTS = [
    'eur'  => ['ar' => 'أوروبا',          'en' => 'Europe',     'color' => '#0081C8'],
    'asia' => ['ar' => 'آسيا',             'en' => 'Asia',       'color' => '#F4C300'],
    'afr'  => ['ar' => 'أفريقيا',          'en' => 'Africa',     'color' => '#00A651'],
    'sam'  => ['ar' => 'أمريكا الجنوبية', 'en' => 'S. America', 'color' => '#EE334E'],
    'nam'  => ['ar' => 'أمريكا الشمالية', 'en' => 'N. America', 'color' => '#EE7A34'],
    'oce'  => ['ar' => 'أوقيانوسيا',       'en' => 'Oceania',    'color' => '#9AA0A6'],
];
$CONT_OF = [
    'ch'=>'eur','de'=>'eur','es'=>'eur','fr'=>'eur','gb-eng'=>'eur','it'=>'eur','nl'=>'eur',
    'no'=>'eur','pl'=>'eur','pt'=>'eur','ro'=>'eur','se'=>'eur','si'=>'eur',
    'ar'=>'sam','br'=>'sam','cl'=>'sam','co'=>'sam','pe'=>'sam','py'=>'sam','uy'=>'sam','ve'=>'sam',
    'ca'=>'nam','cr'=>'nam','hn'=>'nam','jm'=>'nam','mx'=>'nam','sv'=>'nam','us'=>'nam',
    'dz'=>'afr','eg'=>'afr','ga'=>'afr','ma'=>'afr','mr'=>'afr','so'=>'afr','za'=>'afr',
    'ae'=>'asia','au'=>'asia','cn'=>'asia','jo'=>'asia','jp'=>'asia','qa'=>'asia','sa'=>'asia','uz'=>'asia',
    'nz'=>'oce',
];
// قارّة حكم المباراة: علم الطاقم، ثمّ مطابقة الاسم بقائمة الحكّام الرسميّة
$contOfMatch = function (array $mm) use ($CONT_OF) {
    $off = $mm['officials']['main'] ?? null;
    $fg  = strtolower(trim((string)($off['flag'] ?? '')));
    if ($fg === '' && class_exists('Referees')) {
        $rn = trim((string)(($off['name'] ?? '') ?: ($mm['referee'] ?? '')));
        $ix = $rn !== '' ? Referees::indexOf($rn) : null;
        if ($ix !== null) { $rr = Referees::byIndex($ix); $fg = strtolower(trim((string)($rr['flag'] ?? ''))); }
    }
    return $CONT_OF[$fg] ?? '';
};

// ===== المجاميع الكليّة + توزيعها الحقيقي لكل قارّة (من كل مباريات البطولة) =====
$agg     = ['matches'=>0,'refs'=>0,'yellow'=>0,'red'=>0,'fouls'=>0,'offsides'=>0,'goals'=>0,'pens'=>0];
$contAgg = [];                       // مجاميع كل قارّة
$refSets = ['__all' => []];          // مجموعات الحكّام (لعدّهم بلا تكرار)
foreach (DataService::allMatches() as $mm) {
    if (($mm['_status'] ?? '') !== 'finished') continue;
    $yl = 0; $rd = 0;
    foreach ((array)($mm['cards'] ?? []) as $c) { (($c['type'] ?? '') === 'red') ? $rd++ : $yl++; }
    $fo = Referees::matchFouls($mm);
    $os = Referees::matchOffsides($mm);
    $ft = $mm['score']['ft'] ?? null;
    $go = (is_array($ft) && isset($ft[0], $ft[1])) ? ((int)$ft[0] + (int)$ft[1]) : 0;
    $pn = 0;
    foreach ([($mm['goals1'] ?? []), ($mm['goals2'] ?? [])] as $side) {
        foreach ((array)$side as $g) { if (!empty($g['penalty'])) $pn++; }
    }
    $refN = trim((string)(($mm['officials']['main']['name'] ?? '') ?: ($mm['referee'] ?? '')));

    $agg['matches']++; $agg['yellow']+=$yl; $agg['red']+=$rd; $agg['fouls']+=$fo;
    $agg['offsides']+=$os; $agg['goals']+=$go; $agg['pens']+=$pn;
    if ($refN !== '') $refSets['__all'][$refN] = true;

    $ck = $contOfMatch($mm);
    if ($ck !== '') {
        if (!isset($contAgg[$ck])) $contAgg[$ck] = ['matches'=>0,'yellow'=>0,'red'=>0,'fouls'=>0,'offsides'=>0,'goals'=>0,'pens'=>0];
        $contAgg[$ck]['matches']++; $contAgg[$ck]['yellow']+=$yl; $contAgg[$ck]['red']+=$rd; $contAgg[$ck]['fouls']+=$fo;
        $contAgg[$ck]['offsides']+=$os; $contAgg[$ck]['goals']+=$go; $contAgg[$ck]['pens']+=$pn;
        if ($refN !== '') $refSets[$ck][$refN] = true;
    }
}
$agg['refs'] = count($refSets['__all']);
uasort($contAgg, fn($a, $b) => $b['matches'] <=> $a['matches']);
foreach ($contAgg as $ck => &$v) { $v['refs'] = isset($refSets[$ck]) ? count($refSets[$ck]) : 0; }
unset($v);

$aggRefs = $agg['refs'];
$aggCards = $agg['yellow'] + $agg['red'];
$aggCpm   = $agg['matches'] ? round($aggCards   / $agg['matches'], 1) : 0;
$aggFpm   = $agg['matches'] ? round($agg['fouls'] / $agg['matches'], 1) : 0;

// بيانات JS: قيم كل مقياس لكل قارّة + الكل (لتحديث البطاقات العلويّة عند الاختيار)
$mkStat = fn(array $a) => [
    'matches'=>(int)$a['matches'], 'refs'=>(int)($a['refs'] ?? 0),
    'yellow'=>(int)$a['yellow'], 'red'=>(int)$a['red'], 'fouls'=>(int)$a['fouls'],
    'offsides'=>(int)$a['offsides'], 'goals'=>(int)$a['goals'], 'pens'=>(int)$a['pens'],
    'cpm'=> $a['matches'] ? round(($a['yellow']+$a['red'])/$a['matches'],1) : 0,
    'fpm'=> $a['matches'] ? round($a['fouls']/$a['matches'],1) : 0,
];
$contData = ['all' => $mkStat($agg)];
foreach ($contAgg as $ck => $a) $contData[$ck] = $mkStat($a);

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
    // ركلات الجزاء (أهداف من نقطة الجزاء) — لنفس اللوحة عند النقر على بطاقة الجزاء
    foreach ([[$mm['goals1'] ?? [], $t1], [$mm['goals2'] ?? [], $t2]] as $side) {
        [$goals, $teamEn] = $side;
        foreach ((array)$goals as $g) {
            if (empty($g['penalty'])) continue;
            $allCards[] = [
                'idx'    => (int)($mm['_index'] ?? -1),
                'match'  => team_name($t1) . ' × ' . team_name($t2),
                'minute' => isset($g['minute']) ? (int)$g['minute'] : null,
                'player' => (string)($g['name'] ?? ''),
                'teamEn' => $teamEn,
                'ref'    => $refN,
                'type'   => 'pen',
            ];
        }
    }
}
usort($allCards, fn($a, $b) => [$a['idx'], (int)$a['minute']] <=> [$b['idx'], (int)$b['minute']]);
?>
<?php if ($agg['matches'] > 0): ?>
<section class="ref-section" id="aggSection">
  <h2 class="day-title">🌍 <?= e($lang === 'ar' ? 'إحصائيات شاملة —' : 'Overall totals —') ?>
    <span id="aggScope" class="agg-scope"><?= e($lang === 'ar' ? 'بالمجموع' : 'All') ?></span>
  </h2>
  <div class="ref-stats-grid">
    <div class="ref-stat"><div class="ref-stat-v"><span data-metric="matches"><?= $agg['matches'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'مباريات أُديرت' : 'Matches') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v"><span data-metric="refs"><?= $aggRefs ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'حكّام أداروا' : 'Referees') ?></div></div>
    <button type="button" class="ref-stat ref-stat-click" data-cards="yellow"><div class="ref-stat-v" style="color:#f7e09a">🟨 <span data-metric="yellow"><?= $agg['yellow'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات صفراء' : 'Yellow') ?> <span class="ref-stat-hint">👁</span></div></button>
    <button type="button" class="ref-stat ref-stat-click" data-cards="red"><div class="ref-stat-v" style="color:#ef4444">🟥 <span data-metric="red"><?= $agg['red'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات حمراء' : 'Red') ?> <span class="ref-stat-hint">👁</span></div></button>
    <div class="ref-stat"><div class="ref-stat-v">🚫 <span data-metric="fouls"><?= $agg['fouls'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'إجمالي الأخطاء' : 'Fouls') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">🚩 <span data-metric="offsides"><?= $agg['offsides'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'حالات تسلّل' : 'Offsides') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">🥅 <span data-metric="goals"><?= $agg['goals'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'الأهداف' : 'Goals') ?></div></div>
    <button type="button" class="ref-stat ref-stat-click" data-cards="pen"><div class="ref-stat-v">⚽ <span data-metric="pens"><?= $agg['pens'] ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'ركلات جزاء' : 'Penalties') ?> <span class="ref-stat-hint">👁</span></div></button>
    <div class="ref-stat"><div class="ref-stat-v"><span data-metric="cpm"><?= e((string)$aggCpm) ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقة/مباراة' : 'Cards/match') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v"><span data-metric="fpm"><?= e((string)$aggFpm) ?></span></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'خطأ/مباراة' : 'Fouls/match') ?></div></div>
  </div>
  <script>window.REF_CONT_DATA = <?= json_encode($contData, JSON_UNESCAPED_UNICODE) ?>;
  window.REF_CONT_NAMES = <?= json_encode(array_map(fn($c) => $c[$lang === 'ar' ? 'ar' : 'en'], $CONTINENTS), JSON_UNESCAPED_UNICODE) ?>;
  window.REF_ALL_LABEL = <?= json_encode($lang === 'ar' ? 'بالمجموع' : 'All', JSON_UNESCAPED_UNICODE) ?>;</script>
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
          <td class="rst-rank" data-label="<?= e($lang === 'ar' ? 'الدقيقة' : 'Min') ?>"><?= $c['minute'] !== null ? e($c['minute'] . "'") : '—' ?></td>
          <td class="rst-name" data-label="<?= e($lang === 'ar' ? 'اللاعب' : 'Player') ?>">
            <?php if ($fl !== ''): ?><img src="<?= e($fl) ?>" alt="" loading="lazy" width="18" height="13"> <?php endif; ?>
            <?= e($c['player']) ?>
          </td>
          <td class="rst-name" data-label="<?= e($lang === 'ar' ? 'الحكم' : 'Referee') ?>"><?= e($c['ref']) ?></td>
          <td data-label="<?= e($lang === 'ar' ? 'النوع' : 'Type') ?>"><?= $c['type'] === 'red' ? '🟥' : ($c['type'] === 'pen' ? '⚽' : '🟨') ?></td>
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
              red:    '🟥 <?= e($lang === 'ar' ? 'كل البطاقات الحمراء' : 'All red cards') ?>',
              pen:    '⚽ <?= e($lang === 'ar' ? 'كل ركلات الجزاء' : 'All penalties') ?>' };
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
// (القارّات $CONTINENTS/$CONT_OF/$contAgg مُعرّفة أعلاه — نُعيد استخدامها هنا)
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
    $fg = strtolower(trim((string)($r['flag'] ?? '')));
    $statRows[] = [
        'idx'     => $idx,
        'name'    => $nm,
        'flag'    => $fg,
        'cont'    => ($CONT_OF[$fg] ?? ''),
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

  <?php if ($contAgg): ?>
  <div class="ref-cont-filter" id="contFilter">
    <button type="button" class="rcf-btn active" data-cont="all"><?= e($lang === 'ar' ? 'كل القارّات' : 'All continents') ?></button>
    <?php foreach ($contAgg as $ck => $ca): $C = $CONTINENTS[$ck] ?? null; if (!$C) continue; ?>
      <button type="button" class="rcf-btn" data-cont="<?= e($ck) ?>">
        <span class="rcf-dot" style="background:<?= $C['color'] ?>"></span><?= e($C[$lang === 'ar' ? 'ar' : 'en']) ?>
      </button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

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
        <?php
          $cl = $lang === 'ar'
              ? ['m'=>'مباريات','c'=>'بطاقات','f'=>'أخطاء','fpm'=>'خطأ/مباراة','off'=>'تسلّل','g'=>'أهداف','p'=>'جزاء','s'=>'الصرامة']
              : ['m'=>'Matches','c'=>'Cards','f'=>'Fouls','fpm'=>'Fouls/m','off'=>'Offside','g'=>'Goals','p'=>'Pens','s'=>'Strictness'];
        foreach ($statRows as $i => $s):
          [$sLabel, $sColor] = $strictOf((float)$s['avg']);
        ?>
        <tr data-cont="<?= e($s['cont']) ?>">
          <td class="rst-rank"><?= $i + 1 ?></td>
          <td class="rst-name">
            <a href="<?= e(url('referee.php', ['i' => $s['idx']])) ?>">
              <?php if ($s['flag'] !== ''): ?><img src="https://flagcdn.com/w20/<?= e($s['flag']) ?>.png" alt="" loading="lazy" width="20" height="15"> <?php endif; ?>
              <?= e($s['name']) ?>
            </a>
          </td>
          <td data-label="<?= e($cl['m']) ?>"   data-v="<?= $s['matches'] ?>"><?= $s['matches'] ?></td>
          <td data-label="🟨 · 🟥"              data-v="<?= $s['cards'] ?>"><span class="rst-y"><?= $s['yellow'] ?></span> · <span class="rst-r"><?= $s['red'] ?></span></td>
          <td data-label="🚫 <?= e($cl['f']) ?>" data-v="<?= $s['fouls'] ?>"><?= $s['fouls'] ?></td>
          <td data-label="<?= e($cl['fpm']) ?>"  data-v="<?= $s['fpm'] ?>"><?= e((string)$s['fpm']) ?></td>
          <td data-label="🚩 <?= e($cl['off']) ?>" data-v="<?= $s['offsides'] ?>"><?= $s['offsides'] ?></td>
          <td data-label="⚽ <?= e($cl['g']) ?>" data-v="<?= $s['goals'] ?>"><?= $s['goals'] ?></td>
          <td data-label="<?= e($cl['p']) ?>"    data-v="<?= $s['pens'] ?>"><?= $s['pens'] ?></td>
          <td data-label="<?= e($cl['s']) ?>"    data-v="<?= $s['avg'] ?>"><span class="rst-dot" style="background:<?= $sColor ?>"></span><?= e($sLabel) ?></td>
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
  // فلتر القارّات + تحديث الأرقام العلويّة عند الاختيار
  var filter  = document.getElementById('contFilter');
  var data    = window.REF_CONT_DATA  || {};
  var names   = window.REF_CONT_NAMES || {};
  var scopeEl = document.getElementById('aggScope');
  function updateSummary(cont){
    var d = data[cont] || data.all; if (!d) return;
    document.querySelectorAll('#aggSection [data-metric]').forEach(function(el){
      var m = el.getAttribute('data-metric');
      if (d[m] !== undefined && d[m] !== null) el.textContent = d[m];
    });
    if (scopeEl) scopeEl.textContent = (cont === 'all') ? (window.REF_ALL_LABEL || 'All') : (names[cont] || cont);
  }
  window.refApplyContinent = function(c){
    if (filter) filter.querySelectorAll('.rcf-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-cont') === c); });
    Array.prototype.forEach.call(body.rows, function(r){
      r.style.display = (c === 'all' || r.getAttribute('data-cont') === c) ? '' : 'none';
    });
    updateSummary(c);
  };
  if (filter) {
    filter.querySelectorAll('.rcf-btn').forEach(function(btn){
      btn.addEventListener('click', function(){ window.refApplyContinent(btn.getAttribute('data-cont')); });
    });
  }
})();
</script>

<?php
// ===== رسم الدونت: توزيع الحكّام حسب القارّة (رقم + نسبة) — من كل مباريات البطولة =====
$dTotal = 0; foreach ($contAgg as $ca) $dTotal += (int)($ca['refs'] ?? 0);
if ($dTotal > 0):
  $cum = 0; $segs = '';
  foreach ($contAgg as $ck => $ca) {
      $cn = (int)($ca['refs'] ?? 0); if ($cn <= 0 || !isset($CONTINENTS[$ck])) continue;
      $p  = $cn / $dTotal * 100;
      $segs .= sprintf(
          '<circle cx="21" cy="21" r="15.915" fill="transparent" stroke="%s" stroke-width="5.5" stroke-dasharray="%.3f %.3f" stroke-dashoffset="%.3f"><title>%s: %d (%d%%)</title></circle>',
          $CONTINENTS[$ck]['color'], $p, 100 - $p, 25 - $cum,
          e($CONTINENTS[$ck][$lang === 'ar' ? 'ar' : 'en']), $cn, (int)round($p)
      );
      $cum += $p;
  }
?>
<section class="ref-section">
  <h2 class="day-title">🌍 <?= e($lang === 'ar' ? 'توزيع الحكّام حسب القارّة' : 'Referees by continent') ?></h2>
  <div class="ref-donut-wrap">
    <svg class="ref-donut" viewBox="0 0 42 42" role="img" aria-label="<?= e($lang === 'ar' ? 'توزيع الحكّام حسب القارّة' : 'Referees by continent') ?>">
      <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="var(--surface-2)" stroke-width="5.5"></circle>
      <?= $segs ?>
      <text x="21" y="20.5" text-anchor="middle" class="rd-num"><?= (int)$dTotal ?></text>
      <text x="21" y="25.5" text-anchor="middle" class="rd-lbl"><?= e($lang === 'ar' ? 'حكماً' : 'refs') ?></text>
    </svg>
    <ul class="ref-donut-legend">
      <?php foreach ($contAgg as $ck => $ca): if (!isset($CONTINENTS[$ck])) continue; $C = $CONTINENTS[$ck];
        $cn = (int)($ca['refs'] ?? 0); $mtc = (int)($ca['matches'] ?? 0); $p = (int)round($cn / $dTotal * 100); ?>
      <li class="rdl-item" data-cont="<?= e($ck) ?>" role="button" tabindex="0"
          title="<?= e($lang === 'ar' ? 'اعرض أرقام وحكّام هذه القارّة' : 'Show this continent’s stats & referees') ?>">
        <span class="rdl-dot" style="background:<?= $C['color'] ?>"></span>
        <span class="rdl-name"><?= e($C[$lang === 'ar' ? 'ar' : 'en']) ?></span>
        <span class="rdl-val">
          <strong><?= $cn ?></strong> <?= e($lang === 'ar' ? 'حكّام' : 'refs') ?>
          · <strong><?= $mtc ?></strong> <?= e($lang === 'ar' ? 'مباراة' : 'matches') ?>
          · <?= $p ?>%
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <p class="muted" style="font-size:.78rem;margin-top:10px">
    <?= e($lang === 'ar'
        ? 'توزيع الحكّام الذين أداروا مباريات كأس العالم 2026 وعدد مبارياتهم حسب قارّة (اتحاد) كل حكم — اضغط أي قارّة: تتغيّر الأرقام أعلاه ويُفلتَر الجدول لحكّامها.'
        : 'Referees who officiated FIFA World Cup 2026 and their match counts, by each referee\'s continent (confederation) — tap a continent: the totals above update and the table filters to its referees.') ?>
  </p>
</section>
<script>
(function(){
  var items = document.querySelectorAll('.rdl-item');
  if (!items.length) return;
  function go(c){
    if (window.refApplyContinent) window.refApplyContinent(c);
    var t = document.getElementById('aggSection'); if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  items.forEach(function(li){
    li.addEventListener('click', function(){ go(li.getAttribute('data-cont')); });
    li.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); go(li.getAttribute('data-cont')); } });
  });
})();
</script>
<?php endif; ?>
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
