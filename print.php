<?php
/**
 * print.php — جدول البطولة بتصميم بوستر قابل للطباعة.
 * ============================================================
 * نسخة طباعية على ورقة A4 (Portrait) تشبه البوسترات الرسمية:
 *   • شارة 26 + عنوان البطولة
 *   • شبكة أعلام كل مجموعة (12 × 4)
 *   • جدول كامل: التاريخ · المجموعة · المباراة (علمَا الفريقَين) · الوقت
 *   • التوقيت بتوقيت المستخدم المحلي تلقائياً (عبر JS — Intl.DateTimeFormat)
 *   • تذييل: 72 MATCHES · wcup2026.org
 * ============================================================
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');

$matches = DataService::allMatches();

// نستخرج مباريات دور المجموعات فقط (Matchday 1-3) — كما في البوسترات الرسمية
$groupMatches = array_filter($matches, function ($m) {
    $r = $m['round'] ?? '';
    return strpos($r, 'Matchday') === 0;
});
usort($groupMatches, fn($a, $b) =>
    (DataService::matchTimestamp($a) ?? 0) <=> (DataService::matchTimestamp($b) ?? 0));

// نبني خريطة فِرَق كل مجموعة (للشبكة اليسرى)
$groupTeams = [];
foreach ($matches as $m) {
    $g = $m['group'] ?? '';
    if ($g === '') continue;
    foreach (['team1', 'team2'] as $k) {
        $t = trim($m[$k] ?? '');
        if ($t === '' || !is_real_team($t)) continue;
        if (!isset($groupTeams[$g])) $groupTeams[$g] = [];
        if (!in_array($t, $groupTeams[$g], true)) $groupTeams[$g][] = $t;
    }
}
ksort($groupTeams);

$page_title = $ar ? 'جدول البطولة — للطباعة' : 'Tournament Schedule — Printable';
?>
<!doctype html>
<html lang="<?= e($lang) ?>" dir="<?= e(lang_dir()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($page_title) ?> · <?= e($ar ? SITE_NAME_AR : SITE_NAME_EN) ?></title>
<link rel="preconnect" href="https://flagcdn.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<style>
/* ============================================================
   شاشة (المعاينة) + طباعة (A4) — تصميم بوستر FIFA
   ============================================================ */
* { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    font-family: 'Oswald', 'Cairo', sans-serif;
    background: #e5e5e5;
    color: #000;
}

/* شريط أزرار خارج البوستر (لا يُطبَع) */
.toolbar {
    position: sticky; top: 0; z-index: 100;
    background: #0a1626; color: #fff;
    padding: 12px 20px;
    display: flex; gap: 12px; align-items: center; justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.toolbar h1 {
    font-size: 1rem; font-weight: 700; color: #ffc233;
}
.toolbar button, .toolbar a {
    padding: 8px 18px; font-weight: 700; font-size: .92rem;
    background: #ffc233; color: #0a1626; border: 0; border-radius: 6px;
    cursor: pointer; text-decoration: none; font-family: inherit;
}
.toolbar button:hover, .toolbar a:hover { background: #ffd966; }
.toolbar a.secondary { background: rgba(255,255,255,.15); color: #fff; }

/* البوستر (الورقة A4) */
.poster {
    width: 210mm; min-height: 297mm; margin: 20px auto;
    background: #fff;
    padding: 8mm;
    display: grid;
    grid-template-columns: 70mm 1fr;
    gap: 6mm;
    box-shadow: 0 8px 40px rgba(0,0,0,.25);
    color: #000;
}

/* الجانب الأيسر: الشارة + المجموعات */
.poster-left {
    border-inline-end: 2px solid #000;
    padding-inline-end: 5mm;
    display: flex; flex-direction: column; gap: 5mm;
}

.brand-mark {
    text-align: center; padding: 4mm 0; border: 3px solid #000;
    border-radius: 3mm;
    background: linear-gradient(180deg, #000 0%, #000 50%, #fff 50%, #fff 100%);
    color: #fff;
    font-family: 'Oswald', sans-serif; font-weight: 900;
}
.brand-mark .num { font-size: 24mm; line-height: 1; letter-spacing: -2mm; }
.brand-mark .lbl { font-size: 4mm; color: #000; margin-top: 2mm; font-weight: 700; letter-spacing: 1mm; }
.brand-mark .yr  { color: #000; font-size: 7mm; font-weight: 900; letter-spacing: -.3mm; }

.groups-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5mm; }
.group-card {
    border: 1.5px solid #000; padding: 1.5mm;
    text-align: center;
}
.group-card h3 {
    background: #000; color: #fff;
    font-size: 3mm; padding: .5mm 0; margin: -1.5mm -1.5mm 1.5mm;
    font-family: 'Oswald', sans-serif; font-weight: 700; letter-spacing: 1mm;
}
.group-card .flags {
    display: grid; grid-template-columns: 1fr 1fr; gap: 1.5mm;
    justify-items: center;
}
.group-card .flag {
    width: 9mm; height: 6mm; object-fit: cover;
    border: .5px solid #ccc;
}

/* الجانب الأيمن: جدول المباريات */
.poster-right { padding-inline-start: 0; }
.schedule {
    width: 100%; border-collapse: collapse;
    font-family: 'Oswald', sans-serif; font-weight: 500;
}
.schedule thead th {
    background: #000; color: #fff;
    font-size: 3.2mm; font-weight: 700; letter-spacing: .5mm;
    padding: 2mm 1mm; text-align: center;
    border: 1px solid #000;
}
.schedule tbody td {
    padding: 1mm 1.5mm; border: 1px solid #000;
    font-size: 2.8mm; line-height: 1.2;
    vertical-align: middle;
}
.schedule .col-date  { text-align: center; font-weight: 700; width: 14mm; background: #f5f5f5; }
.schedule .col-group { text-align: center; font-weight: 700; width: 10mm; background: #f5f5f5; }
.schedule .col-time  { text-align: center; font-weight: 700; width: 13mm; background: #f5f5f5; font-family: 'Oswald', monospace; }
.schedule .col-match { padding: 1mm 2mm; }

.match-row {
    display: flex; align-items: center; gap: 2mm;
}
.match-row .flag { width: 5mm; height: 3.5mm; object-fit: cover; border: .3px solid #999; flex: 0 0 auto; }
.match-row .team { font-size: 2.7mm; font-weight: 700; flex: 1; }
.match-row .team-a { text-align: end; }
.match-row .team-b { text-align: start; }
.match-row .vs    { font-weight: 700; font-size: 2.5mm; color: #666; padding: 0 1mm; }

/* تذييل البوستر */
.poster-footer {
    grid-column: 1 / -1;
    margin-top: 4mm; padding-top: 3mm;
    border-top: 2px solid #000;
    display: flex; justify-content: space-between; align-items: center;
    font-family: 'Oswald', sans-serif;
}
.poster-footer .total {
    font-size: 6mm; font-weight: 900; letter-spacing: .5mm;
}
.poster-footer .total small { font-size: 3mm; font-weight: 500; color: #666; }
.poster-footer .site {
    text-align: end;
}
.poster-footer .site-name {
    font-size: 5mm; font-weight: 900; color: #0a1626;
    letter-spacing: -.2mm;
}
.poster-footer .site-tag {
    font-size: 2.5mm; color: #555; font-weight: 500;
}

/* تنسيق الطباعة (A4 portrait) */
@media print {
    @page { size: A4 portrait; margin: 0; }
    html, body { background: #fff; }
    .toolbar { display: none !important; }
    .poster {
        margin: 0; box-shadow: none;
        width: 210mm; min-height: 297mm;
        page-break-after: avoid;
    }
}

/* للشاشات الصغيرة (المعاينة على الجوال) */
@media (max-width: 800px) {
    .poster {
        width: 100%; min-height: auto;
        grid-template-columns: 1fr;
    }
    .poster-left {
        border-inline-end: 0; border-bottom: 2px solid #000;
        padding-inline-end: 0; padding-bottom: 5mm;
    }
}
</style>
</head>
<body>

<!-- شريط أدوات يختفي عند الطباعة -->
<div class="toolbar">
  <h1>📄 <?= e($ar ? 'جدول البطولة — جاهز للطباعة' : 'Tournament Schedule — Print-Ready') ?></h1>
  <button type="button" onclick="window.print()">🖨️ <?= e($ar ? 'طباعة' : 'Print') ?></button>
  <a class="secondary" href="<?= e(url('matches.php')) ?>">← <?= e($ar ? 'رجوع' : 'Back') ?></a>
</div>

<!-- البوستر -->
<article class="poster">

  <!-- الجانب الأيسر -->
  <aside class="poster-left">
    <div class="brand-mark">
      <div class="num">26</div>
      <div class="lbl">FIFA WORLD CUP</div>
      <div class="yr">2026</div>
    </div>

    <div class="groups-wrap">
      <?php foreach ($groupTeams as $g => $teams):
        $gLetter = preg_replace('/^Group /', '', $g);
      ?>
      <div class="group-card">
        <h3>GROUP <?= e($gLetter) ?></h3>
        <div class="flags">
          <?php foreach (array_slice($teams, 0, 4) as $t):
            $url = flag_url($t, 'w40');
          ?>
            <?php if ($url): ?>
              <img class="flag" src="<?= e($url) ?>" alt="<?= e($t) ?>" title="<?= e($t) ?>">
            <?php else: ?>
              <span class="flag" style="background:#eee"></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- الجانب الأيمن: جدول المباريات -->
  <section class="poster-right">
    <table class="schedule">
      <thead>
        <tr>
          <th>DATE</th>
          <th>GROUP</th>
          <th>MATCH</th>
          <th>TIME</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $lastDate = '';
        foreach ($groupMatches as $m):
          $ts    = DataService::matchTimestamp($m);
          $group = preg_replace('/^Group /', '', $m['group'] ?? '—');
          $t1    = $m['team1'] ?? '';
          $t2    = $m['team2'] ?? '';
          $u1    = $t1 !== '' ? flag_url($t1, 'w40') : '';
          $u2    = $t2 !== '' ? flag_url($t2, 'w40') : '';
          // اسم الفريق بالحرف اللاتيني (لقراءة دولية للبوستر)
          $n1    = $t1; // openfootball uses English names
          $n2    = $t2;
          // التاريخ الإنجليزي القصير (مطابق لشكل البوسترات)
          $dateLabel = $ts ? strtoupper(gmdate('d M', $ts)) : '—';
          $showDate  = ($dateLabel !== $lastDate);
          $lastDate  = $dateLabel;
        ?>
        <tr>
          <td class="col-date">
            <?= $showDate ? e($dateLabel) . '<br>' . e(($ts ? gmdate('Y', $ts) : '')) : '' ?>
          </td>
          <td class="col-group"><?= e($group) ?></td>
          <td class="col-match">
            <div class="match-row">
              <span class="team team-a"><?= e($n1) ?></span>
              <?php if ($u1): ?><img class="flag" src="<?= e($u1) ?>" alt=""><?php endif; ?>
              <span class="vs">vs</span>
              <?php if ($u2): ?><img class="flag" src="<?= e($u2) ?>" alt=""><?php endif; ?>
              <span class="team team-b"><?= e($n2) ?></span>
            </div>
          </td>
          <td class="col-time">
            <?php if ($ts): ?>
              <!-- JS سيستبدلها بتوقيت الزائر المحلي -->
              <time class="js-local" data-ts="<?= (int)$ts ?>" data-mode="time"><?= e(gmdate('H:i', $ts)) ?></time>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- تذييل -->
    <div class="poster-footer">
      <div class="total">
        <?= count($groupMatches) ?> MATCHES
        <small style="display:block"><?= e($ar ? 'مرحلة المجموعات' : 'GROUP STAGE') ?></small>
      </div>
      <div class="site">
        <div class="site-name">wcup2026.org</div>
        <div class="site-tag"><?= e($ar ? 'كل المعلومات في مكان واحد' : 'All in one place') ?></div>
      </div>
    </div>
  </section>
</article>

<!-- نفس سكربت تحويل الوقت المحلي المستخدم في الموقع -->
<script>
(function () {
    var locale = '<?= e($ar ? 'ar-u-nu-latn' : 'en') ?>';
    document.querySelectorAll('time.js-local[data-ts]').forEach(function (node) {
        var ts = parseInt(node.getAttribute('data-ts'), 10);
        if (!ts) return;
        try {
            var d = new Date(ts * 1000);
            // 24-hour format لمطابقة شكل البوسترات الرسمية
            node.textContent = new Intl.DateTimeFormat(locale, {
                hour: '2-digit', minute: '2-digit', hour12: false
            }).format(d);
        } catch (e) {}
    });
})();
</script>

</body>
</html>
