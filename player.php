<?php
/**
 * player.php — الملفّ الفنّي الكامل للاعب من خلاصة FIFA المنظّمة.
 *   /player.php?id={playerId}            أو   ?name=...&team=...
 * يعرض: صورة + تقييم + رادار عامّ (هجوم/إبداع/اختراق/دفاع) + أقسام بكلّ فئة
 * (رادار + أشرطة مئويّة مقابل المركز). القيم لكلّ 90 دقيقة. تصميم بهويّة الموقع.
 */
require __DIR__ . '/includes/bootstrap.php';

$ar = (current_lang() === 'ar');
$lg = current_lang();
$L  = fn(string $a, string $e): string => $ar ? $a : $e;

$pid = (string)($_GET['id'] ?? '');
if ($pid === '' && isset($_GET['name'])) {
    $pid = (string)(FifaMetrics::findId((string)$_GET['name'], (string)($_GET['team'] ?? '')) ?? '');
}
$pl = $pid !== '' ? FifaMetrics::player($pid) : null;

if (!$pl) {
    $page_title = $L('اللاعب غير موجود', 'Player not found');
    tpl('header');
    echo '<div class="page-head"><h1>' . e($L('لا توجد بيانات لهذا اللاعب', 'No data for this player')) . '</h1>'
       . '<p class="muted"><a href="' . e(url('physical.php')) . '">' . e($L('عُد إلى مستكشف اللاعبين', 'Back to the players explorer')) . '</a></p></div>';
    tpl('footer');
    return;
}

$teamEn  = function_exists('fifa_iso') && function_exists('team_name')
    ? (string)$pl['teamName'] : (string)$pl['teamName'];
$teamLoc = function_exists('team_name') ? team_name((string)$pl['teamName']) : (string)$pl['teamName'];
$photo   = (string)($pl['photo'] ?? '');
$rating  = $pl['r'] ?? null;
$macro   = FifaMetrics::macro($pl, $lg);
$cats    = FifaMetrics::activeCats($pl);

$page_title = $pl['name'] . ' — ' . $L('إحصائيّات', 'Stats');
$page_desc  = $L('الملفّ الفنّي الكامل لـ' . $pl['name'] . ' في كأس العالم 2026 — من بيانات FIFA الرسميّة.',
                 'Full technical profile of ' . $pl['name'] . ' at the FIFA World Cup 2026 — official FIFA data.');
// معاينة المشاركة: بطاقة الملفّ المُولّدة (صورة + تقييم + نقاط الفئات) لا الصورة الخام
$page_image = url('card_img.php', ['mode' => 'player', 'id' => $pid, 'd' => function_exists('card_rev') ? card_rev() : '1']);
tpl('header');

/** رادار SVG: محاور [['label','pct'],…] بقيم 0-100. */
function fm_radar(array $axes, int $size = 300): string {
    $n = count($axes);
    if ($n < 3) return '';
    $cx = $size / 2; $cy = $size / 2 + 6; $R = $size * 0.30;
    $pt = function (float $ang, float $r) use ($cx, $cy) {
        return [$cx + $r * cos($ang), $cy + $r * sin($ang)];
    };
    $ang = fn(int $i) => deg2rad(-90 + $i * 360 / $n);
    $svg = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" class="fm-radar" role="img" aria-hidden="true">';
    // حلقات الشبكة
    foreach ([0.25, 0.5, 0.75, 1.0] as $ring) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) { [$x, $y] = $pt($ang($i), $R * $ring); $pts[] = round($x, 1) . ',' . round($y, 1); }
        $svg .= '<polygon points="' . implode(' ', $pts) . '" fill="none" stroke="rgba(255,255,255,.10)" stroke-width="1"/>';
    }
    // الأشعّة + التسميات
    for ($i = 0; $i < $n; $i++) {
        [$x, $y] = $pt($ang($i), $R);
        $svg .= '<line x1="' . round($cx, 1) . '" y1="' . round($cy, 1) . '" x2="' . round($x, 1) . '" y2="' . round($y, 1) . '" stroke="rgba(255,255,255,.10)"/>';
        [$lx, $ly] = $pt($ang($i), $R + 26);
        $anchor = abs($lx - $cx) < 6 ? 'middle' : ($lx > $cx ? 'start' : 'end');
        $svg .= '<text x="' . round($lx, 1) . '" y="' . round($ly, 1) . '" text-anchor="' . $anchor . '" class="fm-axislbl">'
             . e($axes[$i]['label']) . '</text>';
        $svg .= '<text x="' . round($lx, 1) . '" y="' . round($ly + 13, 1) . '" text-anchor="' . $anchor . '" class="fm-axispct">'
             . (int)$axes[$i]['pct'] . '%</text>';
    }
    // مضلّع البيانات
    $dp = [];
    for ($i = 0; $i < $n; $i++) { [$x, $y] = $pt($ang($i), $R * max(2, (int)$axes[$i]['pct']) / 100); $dp[] = round($x, 1) . ',' . round($y, 1); }
    $svg .= '<polygon points="' . implode(' ', $dp) . '" fill="rgba(38,206,168,.22)" stroke="#26cea8" stroke-width="2"/>';
    for ($i = 0; $i < $n; $i++) { [$x, $y] = $pt($ang($i), $R * max(2, (int)$axes[$i]['pct']) / 100); $svg .= '<circle cx="' . round($x, 1) . '" cy="' . round($y, 1) . '" r="2.6" fill="#26cea8"/>'; }
    return $svg . '</svg>';
}
/** لون الشريط حسب المئويّة. */
function fm_barclass(int $p): string { return $p >= 66 ? 'hi' : ($p >= 33 ? 'mid' : 'lo'); }
?>

<article class="fm-wrap">

  <!-- ─── رأس اللاعب ─── -->
  <header class="fm-head">
    <?php if ($photo !== ''): ?>
      <img class="fm-photo" src="<?= e($photo) ?>" alt="" loading="lazy" onerror="this.classList.add('off')">
    <?php endif; ?>
    <div class="fm-id">
      <h1 class="fm-name"><?= e($pl['name']) ?></h1>
      <p class="fm-team"><?= flag_img((string)$pl['teamName'], 'w40') ?> <?= e($teamLoc) ?>
        <?php if (!empty($pl['pos'])): ?><span class="fm-pos"><?= e($pl['pos']) ?></span><?php endif; ?>
      </p>
      <div class="fm-badges">
        <?php if ($rating !== null): ?><span class="fm-rating">★ <?= number_format((float)$rating, 1) ?></span><?php endif; ?>
        <span class="fm-badge"><?= (int)$pl['min'] ?>′ <?= e($L('لُعبت', 'played')) ?></span>
        <span class="fm-badge"><?= (int)($pl['mp'] ?? 1) ?> <?= e($L('مباراة', 'MP')) ?></span>
      </div>
    </div>
  </header>

  <!-- ─── الرادار العامّ ─── -->
  <section class="fm-card fm-overall">
    <div class="fm-radarbox"><?= fm_radar(array_map(fn($m) => ['label' => $m['label'], 'pct' => $m['score']], $macro), 300) ?></div>
    <div class="fm-macro-chips">
      <?php foreach ($macro as $m): ?>
        <span class="fm-chip fm-<?= fm_barclass((int)$m['score']) ?>"><b><?= (int)$m['score'] ?></b> <?= e($m['label']) ?></span>
      <?php endforeach; ?>
    </div>
    <p class="fm-note"><?= e($L('0-100 مقابل لاعبي نفس المركز · تقريبيّ', '0-100 vs same-position players · approximate')) ?></p>
  </section>

  <!-- ─── أقسام الفئات ─── -->
  <?php foreach ($cats as $cid):
      $cat   = FifaMetrics::CATS[$cid];
      $rows  = FifaMetrics::radar($pl, $cid, $lg);
      if (!$rows) continue;
      $axes  = array_filter($rows, fn($r) => true);
  ?>
  <section class="fm-card">
    <h2 class="fm-cat-h"><span class="fm-cat-ico"><?= $cat['icon'] ?></span> <?= e($cat[$lg] ?? $cat['en']) ?></h2>
    <div class="fm-cat-body">
      <?php if (count($rows) >= 3): ?>
        <div class="fm-radarbox sm"><?= fm_radar(array_map(fn($r) => ['label' => $r['label'], 'pct' => $r['pct']], array_slice($rows, 0, 6)), 280) ?></div>
      <?php endif; ?>
      <div class="fm-bars">
        <?php foreach ($rows as $r): ?>
          <div class="fm-bar">
            <span class="fm-bar-lbl"><?= e($r['label']) ?></span>
            <span class="fm-bar-track"><i class="fm-bar-fill <?= fm_barclass((int)$r['pct']) ?>" style="width:<?= max(3, (int)$r['pct']) ?>%"></i></span>
            <span class="fm-bar-val"><b><?= e($r['val']) ?></b><small><?= (int)$r['pct'] ?>%</small></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <p class="fm-source"><?= e($L('المصدر: بيانات FIFA الرسميّة (المركز الفنّي) · القيم لكلّ 90 دقيقة.',
                               'Source: official FIFA data (Training Centre) · values per 90 minutes.')) ?></p>

  <?php render_share(url('player.php', ['id' => $pid]),
        $L($pl['name'] . ' — إحصائيّات كأس العالم 2026', $pl['name'] . ' — FIFA World Cup 2026 stats')); ?>
</article>

<style>
.fm-wrap{max-width:760px;margin:0 auto}
.fm-head{display:flex;align-items:center;gap:18px;background:linear-gradient(135deg,#1b2a52,#12203f);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:18px 20px;margin-bottom:14px}
.fm-photo{width:104px;height:104px;border-radius:50%;object-fit:cover;object-position:top center;background:#0e1b34;border:3px solid rgba(255,255,255,.20);flex:0 0 auto}
.fm-photo.off{display:none}
.fm-id{min-width:0}
.fm-name{font-size:1.5rem;margin:0 0 4px;line-height:1.1}
.fm-team{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:0 0 8px;opacity:.92}
.fm-team .flag{width:26px;height:auto;border-radius:3px}
.fm-pos{font-size:.74rem;background:rgba(255,255,255,.10);padding:2px 9px;border-radius:20px;font-weight:700}
.fm-badges{display:flex;gap:7px;flex-wrap:wrap}
.fm-rating{background:#ffc846;color:#0a1626;font-weight:800;padding:3px 11px;border-radius:20px}
.fm-badge{background:rgba(255,255,255,.07);padding:3px 11px;border-radius:20px;font-size:.82rem;font-weight:600}
.fm-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px 18px;margin-bottom:14px}
.fm-overall{text-align:center}
.fm-radarbox{display:flex;justify-content:center}
.fm-radar{width:300px;max-width:100%;height:auto}
.fm-radarbox.sm .fm-radar{width:280px}
.fm-axislbl{fill:rgba(255,255,255,.85);font-size:11px;font-weight:700}
.fm-axispct{fill:#26cea8;font-size:10px;font-weight:800}
.fm-macro-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:6px}
.fm-chip{font-size:.84rem;font-weight:700;padding:5px 12px;border-radius:20px;background:rgba(255,255,255,.06)}
.fm-chip b{font-variant-numeric:tabular-nums}
.fm-chip.hi b{color:#26cea8}.fm-chip.mid b{color:#ffc846}.fm-chip.lo b{color:#ff7a45}
.fm-note,.fm-source{font-size:.76rem;opacity:.6;text-align:center;margin:8px 0 0}
.fm-cat-h{display:flex;align-items:center;gap:9px;font-size:1.12rem;margin:0 0 10px;color:var(--accent,#fff)}
.fm-cat-ico{font-size:1.2rem}
.fm-cat-body{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.fm-bars{flex:1 1 300px;min-width:260px;display:flex;flex-direction:column;gap:9px}
.fm-bar{display:grid;grid-template-columns:1fr 90px auto;align-items:center;gap:10px}
.fm-bar-lbl{font-size:.86rem;font-weight:600}
.fm-bar-track{height:8px;border-radius:6px;background:rgba(255,255,255,.08);overflow:hidden}
.fm-bar-fill{display:block;height:100%;border-radius:6px}
.fm-bar-fill.hi{background:linear-gradient(90deg,#1f9c8a,#26cea8)}
.fm-bar-fill.mid{background:linear-gradient(90deg,#c79324,#ffc846)}
.fm-bar-fill.lo{background:linear-gradient(90deg,#c1502a,#ff7a45)}
.fm-bar-val{display:flex;flex-direction:column;align-items:flex-end;line-height:1.05;min-width:46px}
.fm-bar-val b{font-variant-numeric:tabular-nums;font-size:.95rem}
.fm-bar-val small{font-size:.68rem;opacity:.6}
@media(max-width:560px){.fm-head{flex-direction:column;text-align:center}.fm-team{justify-content:center}.fm-badges{justify-content:center}.fm-bar{grid-template-columns:1fr 70px auto}}
</style>

<?php tpl('footer'); ?>
