<?php
/**
 * match.php — تفاصيل مباراة واحدة (?id=).
 *
 * سرد واحد متتابع (بلا تبويبات): Hero → معلومات → معاينة/ملخّص الذكاء →
 *   أحداث (خط زمني موحّد) → إحصائيات → التشكيلة → معلومات الحكام →
 *   تحليل خسارة الذكاء → مشاركة.
 *
 * محاكاة محلية لمباراة #0 فقط (المكسيك ضد جنوب أفريقيا): تظهر «كأن المباراة لُعبت»
 * لمعاينة الشكل قبل البطولة. تنطفئ تلقائياً فور توفّر نتيجة حقيقية.
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
$ar       = (current_lang() === 'ar');
$L        = fn(string $a, string $e) => $ar ? $a : $e;

// ============================================================
//  محاكاة محلية لمباراة #0 (المكسيك 3-1 جنوب أفريقيا) — معاينة فقط.
//  تشتغل في التطوير المحلي فقط (localhost / 127.0.0.1) — لا تظهر على الإنتاج
//  الحيّ (wcup2026.org) ولا على أي نشر عام للنسخة المفتوحة. كما تختفي
//  تلقائياً فور وصول نتيجة حقيقية من openfootball/API-Football.
// ============================================================
$demoMode = false;
$isLocalDev = (
    (defined('SITE_URL') && (SITE_URL === '' || strpos(SITE_URL, 'localhost') !== false || strpos(SITE_URL, '127.0.0.1') !== false))
    || (isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') === 0 ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === 0
    ))
);
if ($id === 0 && !$hasScore && $isLocalDev) {
    $demoMode = true;
    $m['score']['ft'] = [3, 1];
    $m['score']['ht'] = [2, 0];
    $m['_status']    = 'finished';
    $status          = 'finished';
    $m['referee']    = 'Szymon Marciniak';
    $m['goals1'] = [
        ['minute'=>12, 'name'=>'Hirving Lozano'],
        ['minute'=>34, 'name'=>'Santiago Giménez', 'penalty'=>true],
        ['minute'=>78, 'name'=>'Edson Álvarez'],
    ];
    $m['goals2'] = [
        ['minute'=>52, 'name'=>'Lyle Foster'],
    ];
    $m['cards'] = [
        ['minute'=>24, 'team'=>1, 'name'=>'Jorge Sánchez',   'type'=>'yellow'],
        ['minute'=>41, 'team'=>2, 'name'=>'Teboho Mokoena',  'type'=>'yellow'],
        ['minute'=>67, 'team'=>2, 'name'=>'Mothobi Mvala',   'type'=>'yellow'],
        ['minute'=>88, 'team'=>2, 'name'=>'Khuliso Mudau',   'type'=>'red'],
    ];
    $m['stats'] = [
        ['k'=>'الاستحواذ',  'k_en'=>'Possession',  'v'=>[62, 38], 'unit'=>'%'],
        ['k'=>'التسديدات',  'k_en'=>'Shots',       'v'=>[18, 9],  'unit'=>''],
        ['k'=>'على المرمى', 'k_en'=>'On target',   'v'=>[8, 3],   'unit'=>''],
        ['k'=>'ركلات ركنية','k_en'=>'Corners',     'v'=>[7, 3],   'unit'=>''],
        ['k'=>'الأخطاء',    'k_en'=>'Fouls',       'v'=>[11, 14], 'unit'=>''],
        ['k'=>'تسلّل',       'k_en'=>'Offsides',    'v'=>[3, 1],   'unit'=>''],
    ];
    $hasScore = true;
}

$page_title = team_name($t1) . ' ' . t('vs') . ' ' . team_name($t2);
$page_desc  = $page_title . ' — ' . round_label($m['round'] ?? '')
            . (!empty($m['ground']) ? ' · ' . $m['ground'] : '');
$seo_type   = 'article';
$page_image = base_url() . '/card.php?id=' . (int)$id . '&mode=match&v=3';

// ----- خط زمني موحّد: أهداف + بطاقات مرتّبة بالدقيقة -----
$events = [];
foreach (($m['goals1'] ?? []) as $g) {
    $events[] = ['min'=>(int)($g['minute']??0), 'side'=>1, 'type'=>'goal',
                 'name'=>(string)($g['name']??''), 'pen'=>!empty($g['penalty']), 'og'=>!empty($g['owngoal']),
                 'assist'=>(string)($g['assist']??'')];
}
foreach (($m['goals2'] ?? []) as $g) {
    $events[] = ['min'=>(int)($g['minute']??0), 'side'=>2, 'type'=>'goal',
                 'name'=>(string)($g['name']??''), 'pen'=>!empty($g['penalty']), 'og'=>!empty($g['owngoal']),
                 'assist'=>(string)($g['assist']??'')];
}
foreach (($m['cards'] ?? []) as $c) {
    $isRed = (($c['type'] ?? 'yellow') === 'red');
    $events[] = ['min'=>(int)($c['minute']??0),
                 'side'=>(((int)($c['team']??1))===2 ? 2 : 1),
                 'type'=>$isRed ? 'red' : 'yellow',
                 'name'=>(string)($c['name']??''),
                 // تفسير الحالة التحكيميّة (من ESPN، معرّب عند توفّر المقابل)
                 'reason'=>(string)(current_lang() === 'ar'
                     ? ($c['reason_ar'] ?? ($c['reason_en'] ?? ''))
                     : ($c['reason_en'] ?? ($c['reason_ar'] ?? '')))];
}
usort($events, fn($a,$b)=>$a['min'] <=> $b['min']);

tpl('header');
seo_sportsevent($m);
?>

<a class="back-link" href="<?= e(url('matches.php')) ?>">‹ <?= e(t('matches')) ?></a>

<article class="match-detail md2 status-<?= e($status) ?>">

  <!-- ============ Hero بطولي ============ -->
  <header class="md-hero">
    <div class="md-meta">
      <span><?= e(round_label($m['round'] ?? '')) ?></span>
      <?php if (!empty($m['group'])): ?><span class="md-dot">·</span><span><?= e(group_label($m['group'])) ?></span><?php endif; ?>
      <span class="md-dot">·</span>
      <?php if ($status === 'live' && !empty($m['_live_minute'])): ?>
        <span class="badge badge-live"><span class="live-dot"></span><?= (int)$m['_live_minute'] ?>' <?= e(t('live')) ?></span>
      <?php else: ?>
        <?= status_badge($status) ?>
      <?php endif; ?>
    </div>

    <div class="md-scoreline">
      <div class="md-team md-team-home">
        <?= flag_img($t1, 'w160') ?>
        <h2><?= e(team_name($t1)) ?></h2>
        <?php if ($mr1 = Rankings::of($t1)): ?><span class="md-rank"><?= e(t('fifa_rank')) ?> #<?= (int)$mr1 ?></span><?php endif; ?>
      </div>

      <div class="md-center">
        <?php if ($hasScore): ?>
          <div class="md-score">
            <span class="md-score-n"><?= (int)$m['score']['ft'][0] ?></span>
            <span class="md-score-sep">:</span>
            <span class="md-score-n"><?= (int)$m['score']['ft'][1] ?></span>
          </div>
          <?php if (isset($m['score']['ht'])): ?>
            <p class="md-ht">(<?= (int)$m['score']['ht'][0] ?> : <?= (int)$m['score']['ht'][1] ?>)</p>
          <?php endif; ?>
          <?php if (isset($m['score']['et']) && !isset($m['score']['p'])): ?>
            <p class="md-aet"><?= e($L('بعد الوقت الإضافي','After extra time')) ?><?php if (isset($m['score']['reg'])): ?> · <?= (int)$m['score']['reg'][0] ?> : <?= (int)$m['score']['reg'][1] ?> <?= e($L('في الزمن الأصلي','in normal time')) ?><?php endif; ?></p>
          <?php endif; ?>
          <?php if (isset($m['score']['p'])): ?>
            <p class="md-pens"><?= e($L('ركلات الترجيح','Penalties')) ?>: <?= (int)$m['score']['p'][0] ?> : <?= (int)$m['score']['p'][1] ?></p>
          <?php endif; ?>
        <?php else: ?>
          <div class="md-time"><strong><?= local_dt($ts, 'time') ?></strong></div>
          <p class="md-time-date"><?= local_dt($ts, 'date') ?></p>
          <?php if (AiContent::enabled()):
            $aiPick = AiContent::matchPrediction($m);
            if ($aiPick !== null): ?>
            <p class="md-aipick">🤖 <?= e(t('ai_pick')) ?>: <strong><?= (int)$aiPick['p1'] ?>-<?= (int)$aiPick['p2'] ?></strong></p>
          <?php endif; endif; ?>
        <?php endif; ?>
      </div>

      <div class="md-team md-team-away">
        <?= flag_img($t2, 'w160') ?>
        <h2><?= e(team_name($t2)) ?></h2>
        <?php if ($mr2 = Rankings::of($t2)): ?><span class="md-rank"><?= e(t('fifa_rank')) ?> #<?= (int)$mr2 ?></span><?php endif; ?>
      </div>
    </div>

    <div class="md-hero-foot">
      <?php if (!empty($m['ground'])): ?><span class="md-hf">🏟 <?= e($m['ground']) ?></span><?php endif; ?>
      <?php if ($ts !== null): ?><span class="md-hf">📅 <?= local_dt($ts, 'datetime') ?></span><?php endif; ?>
    </div>
  </header>

  <?php if ($demoMode): ?>
    <p class="md-demo-banner">🧪
      <?= e($L('هذه بطاقة عرض تجريبية لشكل الصفحة وقت المباراة. تُستبدل تلقائياً ببيانات المباراة الحقيقية فور توفّرها.',
              'Demo preview of how the page looks during a match. Replaced automatically by real match data.')) ?>
    </p>
  <?php endif; ?>

  <!-- ============ بطاقات معلومات سريعة ============ -->
  <section class="md-section">
    <ul class="md-info md-info-grid">
      <li><span class="md-info-k">📅 <?= e(t('date')) ?></span><span class="md-info-v"><?= local_dt($ts, 'date') ?></span></li>
      <li><span class="md-info-k">🕐 <?= e(t('time')) ?></span><span class="md-info-v"><?= local_dt($ts, 'time') ?></span></li>
      <?php if (!empty($m['ground'])): $st = Stadiums::byGround($m['ground']); ?>
        <li>
          <span class="md-info-k">🏟 <?= e(t('stadium')) ?></span>
          <span class="md-info-v">
            <?php if ($st): ?><a class="section-link" href="<?= e(url('stadium.php', ['id' => $st['id']])) ?>"><?= e($m['ground']) ?></a>
            <?php else: ?><?= e($m['ground']) ?><?php endif; ?>
          </span>
        </li>
      <?php endif; ?>
      <li>
        <span class="md-info-k">🧑‍⚖️ <?= e(t('referee')) ?></span>
        <span class="md-info-v"><?= !empty($m['referee']) ? e($m['referee']) : '<span class="ref-tbd">'.e($L('يُعلَن قبل المباراة','TBA before kickoff')).'</span>' ?></span>
      </li>
    </ul>
    <div class="md-cta-row">
      <a class="btn btn-sm" href="<?= e(url('calendar.php', ['id' => $id])) ?>">📅 <?= e(t('add_match_calendar')) ?></a>
    </div>
  </section>

  <!-- ============ معاينة/ملخّص الذكاء (إن وُجد) ============ -->
  <?php if (AiContent::enabled()):
      $aiType = $hasScore ? 'summary' : 'preview';
      $aiText = AiContent::forMatch($m, $aiType);
      if ($aiText !== null): ?>
    <section class="ai-content md-section">
      <h3>🧠 <?= e($hasScore ? t('ai_summary') : t('ai_preview')) ?></h3>
      <div class="ai-flags"><?= flag_img($t1, 'w40') ?><span class="ai-vs"><?= e(t('vs')) ?></span><?= flag_img($t2, 'w40') ?></div>
      <?php foreach (preg_split('/\n+/', $aiText) as $para): if (trim($para) === '') continue; ?>
        <p><?= e($para) ?></p>
      <?php endforeach; ?>
      <p class="ai-note"><?= e(t('ai_note')) ?></p>
    </section>
  <?php endif; endif; ?>

  <!-- ============ 🎬 ملخّص الفيديو الرسمي (beIN عبر يوتيوب) ============ -->
  <?php
  $video = $hasScore ? Highlights::forMatch($m) : null;
  if ($video): ?>
    <section class="md-section video-box">
      <h3 class="section-head">🎬 <?= e($L('ملخّص المباراة (فيديو)','Match highlights (video)')) ?></h3>
      <?php
      // beIN يحظر تضمين فيديوهاته (سياسة الشريك على يوتيوب) — لذا نعرض
      // بطاقة بصورة الفيديو الرسمية وزرّ تشغيل يفتح يوتيوب في تبويب جديد.
      $watchUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($video['id']);
      $thumbUrl = 'https://i.ytimg.com/vi/' . rawurlencode($video['id']) . '/hqdefault.jpg';
      ?>
      <a class="video-thumb" href="<?= e($watchUrl) ?>" target="_blank" rel="noopener"
         title="<?= e($video['title']) ?>">
        <img src="<?= e($thumbUrl) ?>" alt="<?= e($video['title']) ?>" loading="lazy">
        <span class="video-play" aria-hidden="true">▶</span>
        <span class="video-cta"><?= e($L('شاهد الملخّص على YouTube ↗','Watch highlights on YouTube ↗')) ?></span>
      </a>
      <p class="video-credit"><?= e($L('المصدر: beIN SPORTS — القناة الرسمية ليوتيوب','Source: beIN SPORTS — official YouTube channel')) ?></p>
    </section>
  <?php endif; ?>

  <!-- ============ إحصائيات FIFA الرسميّة + رابط التقرير الكامل ============ -->
  <?php
  $fifaStatsHtml = ($hasScore && class_exists('FifaStats'))  ? FifaStats::render($m, $ar) : '';
  $fifaUrl       = ($hasScore && class_exists('FifaReports')) ? FifaReports::forMatch($m) : null;
  echo $fifaStatsHtml;
  if ($fifaUrl): ?>
    <a class="fifa-rep" href="<?= e($fifaUrl) ?>" target="_blank" rel="noopener">
      <span class="fifa-rep-ic">📄</span>
      <span class="fifa-rep-t">
        <b><?= e($L('التقرير الرسمي من FIFA', 'Official FIFA report')) ?></b>
        <small><?= e($fifaStatsHtml === ''
          ? $L('إحصائيّات ما بعد المباراة الكاملة (PDF) ↗', 'Full post-match statistics (PDF) ↗')
          : $L('افتح التقرير الكامل (PDF) ↗', 'Open the full report (PDF) ↗')) ?></small>
      </span>
      <span class="fifa-rep-go" aria-hidden="true">›</span>
    </a>
    <style>
    .fifa-rep{display:flex;align-items:center;gap:13px;margin:6px 0 14px;padding:13px 16px;
      background:linear-gradient(135deg,rgba(255,200,70,.14),rgba(255,200,70,.05));
      border:1px solid rgba(255,200,70,.34);border-radius:14px;text-decoration:none;color:inherit;transition:.15s}
    .fifa-rep:hover{background:linear-gradient(135deg,rgba(255,200,70,.22),rgba(255,200,70,.08));border-color:rgba(255,200,70,.55)}
    .fifa-rep-ic{font-size:1.7rem;flex:0 0 auto}
    .fifa-rep-t{display:flex;flex-direction:column;line-height:1.25;min-width:0}
    .fifa-rep-t b{font-size:1rem}
    .fifa-rep-t small{font-size:.8rem;opacity:.75}
    .fifa-rep-go{margin-inline-start:auto;font-size:1.5rem;color:#ffc846;font-weight:800}
    </style>
  <?php elseif ($hasScore && $fifaStatsHtml === ''): /* انتهت لكن تقرير FIFA لم يُنشَر بعد → ملاحظة بدل فراغ */ ?>
    <div class="fifa-pending">
      <span class="fifa-pending-ic">📄</span>
      <span class="fifa-pending-t">
        <b><?= e($L('تقرير FIFA الرسمي لم يُنشَر بعد', 'Official FIFA report not published yet')) ?></b>
        <small><?= e($L('يصدر عادةً خلال يوم من المباراة، ويظهر هنا تلقائياً فور نشره.', 'Usually published within a day of the match — it will appear here automatically once released.')) ?></small>
      </span>
    </div>
    <style>
    .fifa-pending{display:flex;align-items:center;gap:13px;margin:6px 0 14px;padding:13px 16px;
      background:rgba(255,255,255,.04);border:1px dashed rgba(255,255,255,.2);border-radius:14px;color:inherit}
    .fifa-pending-ic{font-size:1.5rem;flex:0 0 auto;opacity:.7}
    .fifa-pending-t{display:flex;flex-direction:column;line-height:1.3;min-width:0}
    .fifa-pending-t b{font-size:.95rem}
    .fifa-pending-t small{font-size:.8rem;opacity:.72}
    </style>
  <?php endif; ?>

  <!-- ============ رجل المباراة (أعلى تقييم) ============ -->
  <?php
  $motm = ($hasScore && class_exists('FifaMetrics'))
        ? FifaMetrics::motmFor((string)($m['team1'] ?? ''), (string)($m['team2'] ?? '')) : null;
  if ($motm):
      $motmTeamEn = FifaMetrics::motmTeamEn($motm, (string)($m['team1'] ?? ''), (string)($m['team2'] ?? ''));
      $motmPhoto  = (string)($motm['photo'] ?? '');
      $motmUrl    = url('player.php', ['id' => (string)($motm['pid'] ?? '')]);   // pid دقيق (الاسم قد يختلف)
  ?>
    <a class="motm-card" href="<?= e($motmUrl) ?>">
      <span class="motm-badge">🌟 <?= e($L('رجل المباراة', 'Player of the Match')) ?></span>
      <?php if ($motmPhoto !== ''): ?>
        <img class="motm-photo" src="<?= e($motmPhoto) ?>" alt="" loading="lazy" onerror="this.classList.add('off')">
      <?php endif; ?>
      <span class="motm-info">
        <b class="motm-name"><?= e($motm['name'] ?? '') ?></b>
        <span class="motm-team"><?= flag_img($motmTeamEn, 'w40') ?> <?= e(team_name($motmTeamEn)) ?></span>
      </span>
      <span class="motm-rating">★ <?= number_format((float)($motm['rating'] ?? 0), 1) ?></span>
    </a>
    <style>
    .motm-card{display:flex;align-items:center;gap:14px;margin:6px 0 14px;padding:14px 18px;position:relative;
      background:linear-gradient(135deg,rgba(255,200,70,.16),rgba(38,206,168,.06));
      border:1px solid rgba(255,200,70,.40);border-radius:16px;text-decoration:none;color:inherit;transition:.15s}
    .motm-card:hover{border-color:rgba(255,200,70,.7);background:linear-gradient(135deg,rgba(255,200,70,.24),rgba(38,206,168,.10))}
    .motm-badge{position:absolute;top:-10px;inset-inline-start:16px;background:#ffc846;color:#0a1626;font-weight:800;
      font-size:.74rem;padding:3px 11px;border-radius:20px}
    .motm-photo{width:72px;height:72px;border-radius:50%;object-fit:cover;object-position:top center;
      background:#0e1b34;border:2px solid #ffc846;flex:0 0 auto;margin-top:4px}
    .motm-photo.off{display:none}
    .motm-info{display:flex;flex-direction:column;gap:4px;min-width:0;margin-top:4px}
    .motm-name{font-size:1.15rem;line-height:1.1}
    .motm-team{display:flex;align-items:center;gap:7px;opacity:.9;font-size:.9rem}
    .motm-team .flag{width:24px;height:auto;border-radius:3px}
    .motm-rating{margin-inline-start:auto;background:#ffc846;color:#0a1626;font-weight:800;font-size:1.15rem;
      padding:6px 14px;border-radius:14px;margin-top:4px}
    </style>
  <?php endif; ?>

  <!-- ============ الأحداث (خط زمني موحّد) ============ -->
  <?php if (!empty($events)): ?>
    <section class="md-section">
      <h3 class="section-head">⚡ <?= e($L('أحداث المباراة','Match events')) ?></h3>
      <ol class="md-timeline">
        <?php foreach ($events as $ev):
          $home  = ($ev['side'] === 1);
          $tnEn  = $home ? $t1 : $t2;
          $icon  = ['goal'=>'⚽','yellow'=>'🟨','red'=>'🟥'][$ev['type']] ?? '•';
          $tag   = '';
          if ($ev['type']==='goal') {
              if (!empty($ev['pen'])) $tag = $L('ركلة جزاء','Penalty');
              if (!empty($ev['og']))  $tag = $L('هدف عكسي','Own goal');
          }
        ?>
          <li class="md-tl-row md-tl-<?= $home ? 'home' : 'away' ?> md-tl-<?= e($ev['type']) ?>">
            <span class="md-tl-min"><?= (int)$ev['min'] ?>'</span>
            <span class="md-tl-icon" aria-hidden="true"><?= $icon ?></span>
            <span class="md-tl-flag"><?= flag_img($tnEn, 'w40') ?></span>
            <span class="md-tl-name"><?= e($ev['name']) ?></span>
            <?php if ($tag !== ''): ?><span class="md-tl-tag">(<?= e($tag) ?>)</span><?php endif; ?>
            <?php if (!empty($ev['assist'])): ?>
              <span class="md-tl-note">🅰️ <?= e($L('صناعة','Assist')) ?>: <?= e($ev['assist']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ev['reason'])): ?>
              <span class="md-tl-note">📋 <?= e($ev['reason']) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

  <!-- ============ الإحصائيات (ESPN) — تُخفى عند توفّر إحصائيات FIFA الرسميّة ============ -->
  <?php if ($fifaStatsHtml === ''): ?>
  <?php if (!empty($m['stats']) && is_array($m['stats'])): ?>
    <section class="md-section">
      <h3 class="section-head">📊 <?= e($L('الإحصائيات','Statistics')) ?></h3>
      <div class="md-stats-head">
        <span><?= e(team_name($t1)) ?></span>
        <span><?= e(team_name($t2)) ?></span>
      </div>
      <div class="md-stats-grid">
        <?php foreach ($m['stats'] as $s):
          $v1 = (int)$s['v'][0]; $v2 = (int)$s['v'][1];
          $sum = $v1 + $v2; if ($sum <= 0) continue;
          $p1 = round($v1 / $sum * 100, 1); $p2 = 100 - $p1;
          $unit = (string)($s['unit'] ?? '');
        ?>
          <div class="md-stat">
            <div class="md-stat-vals">
              <span class="md-stat-v1"><?= $v1 ?><?= e($unit) ?></span>
              <span class="md-stat-k"><?= e($ar ? $s['k'] : $s['k_en']) ?></span>
              <span class="md-stat-v2"><?= $v2 ?><?= e($unit) ?></span>
            </div>
            <div class="md-stat-bar">
              <span class="md-stat-bar-1" style="flex:<?= $p1 ?>"></span>
              <span class="md-stat-bar-2" style="flex:<?= $p2 ?>"></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <section class="md-section">
      <h3 class="section-head">📊 <?= e($L('الإحصائيات','Statistics')) ?></h3>
      <p class="md-stat-note">
        <?= e($L('إحصائيات الاستحواذ والتسديدات والأخطاء تظهر هنا تلقائياً وقت المباراة.',
                'Possession, shots and fouls stats appear here automatically during the match.')) ?>
      </p>
    </section>
  <?php endif; ?>
  <?php endif; /* fifaStatsHtml === '' */ ?>

  <!-- ============ التشكيلة ============ -->
  <?php
  require_once __DIR__ . '/templates/pitch.php';
  $lineup       = LiveService::lineupForMatch($m);
  $lineupSample = false;
  if (!$lineup) { $lineup = pitch_sample_lineup(); $lineupSample = true; }
  ?>
  <?php
    // عند توفّر تقرير FIFA (إحصائيات غنيّة) نطوي التشكيلة في <details> — لا تُفقَد أي بيانات،
    // فقط تُفتح بضغطة. وإلا فقسم عادي ظاهر.
    $foldLineup = ($fifaStatsHtml !== '');
  ?>
  <?php if ($foldLineup): ?>
  <details class="lineup-box md-section lineup-fold">
    <summary class="section-head">👕 <?= e(t('lineup')) ?> <span class="fold-hint">· <?= e($ar ? 'اضغط للعرض' : 'tap to view') ?></span></summary>
  <?php else: ?>
  <section class="lineup-box md-section">
    <h3 class="section-head">👕 <?= e(t('lineup')) ?></h3>
  <?php endif; ?>
    <?php
    // تشكيلة يدوية متوقعة (قبل الإعلان الرسمي) → وسم توضيحي فوق اللوحة
    $lineupTag = (!$lineupSample && !empty($lineup['probable']))
        ? (current_lang() === 'ar'
            ? '📋 تشكيلة متوقعة — تُستبدل بالرسمية فور إعلانها'
            : '📋 Probable lineup — updated once the official XI is announced')
        : '';
    $drewPitch = render_tactical_pitch($lineup, $t1, $t2, ['sample' => $lineupSample, 'tag' => $lineupTag]);
    if (!$drewPitch): ?>
      <div class="lineup-cols">
        <?php foreach (['team1' => $t1, 'team2' => $t2] as $side => $tn):
          $LU = $lineup[$side] ?? null; if (!$LU) continue; ?>
          <div class="lineup-col">
            <h3><?= flag_img($tn, 'w40') ?> <?= e(team_name($tn)) ?>
              <?php if (!empty($LU['formation'])): ?><span class="lineup-formation"><?= e($LU['formation']) ?></span><?php endif; ?>
            </h3>
            <p class="lineup-label"><?= e(t('starters')) ?></p>
            <ol class="lineup-list"><?php foreach (($LU['start'] ?? []) as $p): ?><li><?= e($p['name']) ?></li><?php endforeach; ?></ol>
            <?php if (!empty($LU['coach'])): ?><p class="lineup-coach"><?= e(t('coach')) ?>: <?= e($LU['coach']) ?></p><?php endif; ?>
            <?php if (!empty($LU['subs'])): ?>
              <p class="lineup-label"><?= e(t('subs')) ?></p>
              <ul class="lineup-list lineup-subs"><?php foreach ($LU['subs'] as $p): ?><li><?= e($p['name']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$lineupSample): ?>
      <div class="lineup-extra">
        <?php foreach (['team1' => $t1, 'team2' => $t2] as $side => $tn):
          $LU = $lineup[$side] ?? null; if (!$LU) continue; ?>
          <div class="lineup-extra-col">
            <h4><?= flag_img($tn, 'w40') ?> <?= e(team_name($tn)) ?></h4>
            <?php if (!empty($LU['coach'])): ?><p class="lineup-coach"><?= e(t('coach')) ?>: <?= e($LU['coach']) ?></p><?php endif; ?>
            <?php if (!empty($LU['subs'])): ?>
              <p class="lineup-label"><?= e(t('subs')) ?></p>
              <ul class="lineup-list lineup-subs"><?php foreach ($LU['subs'] as $p): ?><li><?= e($p['name']) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php echo $foldLineup ? '</details>' : '</section>'; ?>

  <!-- ============ تفاصيل الحكام والمعلومات الموسّعة ============ -->
  <?php
  $officials = is_array($m['officials'] ?? null) ? $m['officials'] : [];
  $offMain   = $officials['main']       ?? null;
  $offAsst   = is_array($officials['assistants'] ?? null) ? $officials['assistants'] : [];
  $offVar    = $officials['var']        ?? null;
  $offFourth = $officials['fourth']     ?? null;

  // حالة المباراة: قبل / جارية / منتهية
  $matchStatus = $m['_status'] ?? DataService::matchStatus($m);
  $kickoffTs   = DataService::matchTimestamp($m);
  $countdown   = null;
  if ($matchStatus === 'upcoming' && $kickoffTs) {
      $diff = $kickoffTs - time();
      if ($diff > 0) {
          $d = floor($diff/86400); $h = floor(($diff%86400)/3600);
          $countdown = $d > 0 ? ($ar ? "{$d} يوم · {$h} ساعة" : "{$d}d · {$h}h")
                              : ($ar ? "{$h} ساعة"           : "{$h}h");
      }
  }

  $renderOff = function(?array $o) use ($ar): string {
      if (!$o || empty($o['name'])) return '';
      $flag = !empty($o['flag']) ? '<img src="https://flagcdn.com/w20/'.e($o['flag']).'.png" alt="" class="ref-flag" loading="lazy"> ' : '';
      $cn   = !empty($o['country_ar']) ? ' <small class="ref-country">('.e($o['country_ar']).')</small>' : '';
      return $flag . e($o['name']) . $cn;
  };
  $tbaSpan = '<span class="ref-tbd">'.e($L('يُعلَن قبل المباراة','TBA before kickoff')).'</span>';

  // ملخّص البطاقات
  $y1 = $y2 = $r1 = $r2 = 0;
  if (!empty($m['cards']) && is_array($m['cards'])) {
      foreach ($m['cards'] as $c) {
          $isT1 = ((int)($c['team'] ?? 1) === 1);
          if (($c['type'] ?? '') === 'red') { $isT1 ? $r1++ : $r2++; }
          else                              { $isT1 ? $y1++ : $y2++; }
      }
  }
  $hasCards = ($y1+$y2+$r1+$r2) > 0;

  // مراجعات VAR من events
  $varCount = 0;
  if (!empty($m['events']) && is_array($m['events'])) {
      foreach ($m['events'] as $ev) {
          if (stripos((string)($ev['type'] ?? ''), 'var') !== false) $varCount++;
      }
  }

  // معلومات إضافيّة عامّة
  $matchNo  = (int)($m['_index'] ?? 0) + 1;
  $stadium  = trim((string)($m['ground'] ?? ''));
  $groupTxt = trim((string)($m['group']  ?? $m['round'] ?? ''));
  ?>
  <section class="md-section">
    <h3 class="section-head">📋 <?= e($L('طاقم التحكيم والمعلومات الموسّعة','Match officials & info')) ?></h3>

    <?php if ($matchStatus === 'upcoming' && $countdown): ?>
    <div class="md-countdown-banner">
      ⏳ <?= e($L('الانطلاق خلال','Kickoff in')) ?> <strong><?= e($countdown) ?></strong>
      — <?= e($L('الإحصائيات والبطاقات تظهر تلقائياً وقت المباراة','Stats and cards appear automatically during the match')) ?>
    </div>
    <?php endif; ?>

    <ul class="md-info md-info-stacked">
      <!-- معلومات أساسيّة سريعة -->
      <?php if ($matchNo > 0): ?>
      <li>
        <span class="md-info-k">🌐 <?= e($L('رقم المباراة','Match number')) ?></span>
        <span class="md-info-v"><?= e($L("$matchNo من 104","$matchNo of 104")) ?></span>
      </li>
      <?php endif; ?>
      <?php if ($groupTxt !== ''): ?>
      <li>
        <span class="md-info-k">🏆 <?= e($L('المرحلة','Stage')) ?></span>
        <span class="md-info-v"><?= e($groupTxt) ?></span>
      </li>
      <?php endif; ?>

      <!-- طاقم التحكيم -->
      <li>
        <span class="md-info-k">🧑‍⚖️ <?= e($L('الحكم الرئيسي','Main referee')) ?></span>
        <span class="md-info-v">
          <?php if ($offMain): ?>
            <?= $renderOff($offMain) ?>
          <?php elseif (!empty($m['referee'])): ?>
            <?= e($m['referee']) ?>
          <?php else: ?>
            <?= $tbaSpan ?>
          <?php endif; ?>
        </span>
      </li>
      <li>
        <span class="md-info-k">🚩 <?= e($L('الحكام المساعدون','Assistant referees')) ?></span>
        <span class="md-info-v">
          <?php if ($offAsst): ?>
            <?php foreach ($offAsst as $i => $a): ?><?= $renderOff($a) ?><?= ($i < count($offAsst)-1) ? ' · ' : '' ?><?php endforeach; ?>
          <?php else: ?>
            <?= $tbaSpan ?>
          <?php endif; ?>
        </span>
      </li>

      <?php
      // VAR + الحكم الرابع — لو الاثنان فارغان، اعرض سطراً واحداً مدمجاً
      $varEmpty    = !$offVar;
      $fourthEmpty = !$offFourth;
      if ($varEmpty && $fourthEmpty): ?>
      <li>
        <span class="md-info-k">📺 <?= e($L('حكم الفيديو + الحكم الرابع','VAR + 4th official')) ?></span>
        <span class="md-info-v">
          <span class="ref-tbd"><?= e($L('يُعلَنان قبل المباراة','To be announced')) ?></span>
        </span>
      </li>
      <?php else: ?>
      <li>
        <span class="md-info-k">📺 <?= e($L('حكم الفيديو (VAR)','Video referee (VAR)')) ?></span>
        <span class="md-info-v"><?= $offVar ? $renderOff($offVar) : $tbaSpan ?></span>
      </li>
      <li>
        <span class="md-info-k">⏱ <?= e($L('الحكم الرابع','Fourth official')) ?></span>
        <span class="md-info-v"><?= $offFourth ? $renderOff($offFourth) : $tbaSpan ?></span>
      </li>
      <?php endif; ?>

      <!-- البطاقات (فقط لو المباراة بدأت) -->
      <?php if ($hasCards): ?>
      <li>
        <span class="md-info-k">🟨 <?= e($L('بطاقات صفراء','Yellow cards')) ?></span>
        <span class="md-info-v"><strong><?= $y1 ?></strong> — <strong><?= $y2 ?></strong></span>
      </li>
      <?php if ($r1 || $r2): ?>
      <li>
        <span class="md-info-k">🟥 <?= e($L('بطاقات حمراء','Red cards')) ?></span>
        <span class="md-info-v"><strong><?= $r1 ?></strong> — <strong><?= $r2 ?></strong></span>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($varCount > 0): ?>
      <li>
        <span class="md-info-k">🎥 <?= e($L('مراجعات الفيديو','VAR reviews')) ?></span>
        <span class="md-info-v"><strong><?= $varCount ?></strong> <?= e($L('مراجعة','reviews')) ?></span>
      </li>
      <?php endif; ?>
    </ul>

    <?php if ($matchStatus !== 'upcoming' && !$hasCards): ?>
    <p class="md-stat-note"><?= e($L('ملاحظة: الإحصائيات التفصيليّة تتدفّق من API-Football خلال المباراة وتظل محفوظة بعدها.','Note: detailed stats stream from API-Football during the match and persist afterwards.')) ?></p>
    <?php endif; ?>
  </section>

  <!-- ============ تحليل خسارة الذكاء (للمباريات المنتهية) ============ -->
  <?php
  if (AiContent::enabled() && $hasScore):
    $fg1 = (int)$m['score']['ft'][0]; $fg2 = (int)$m['score']['ft'][1];
    if ($fg1 !== $fg2):
      $loserEn = ($fg1 < $fg2) ? $t1 : $t2;
      if (is_real_team($loserEn)): ?>
    <section class="excuse-box md-section">
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

  <!-- ============ مشاركة ============ -->
  <?php render_share(
    canonical_url(),
    team_name($t1) . ' ' . t('vs') . ' ' . team_name($t2) . ' — ' . SITE_NAME_AR,
    ['teams' => [$t1, $t2]]
  ); ?>

</article>

<?php tpl('footer'); ?>
