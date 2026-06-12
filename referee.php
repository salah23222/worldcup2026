<?php
/**
 * referee.php — البطاقة التفصيلية لحكم واحد.
 * يُعرّف الحكم بترتيبه في القائمة (?i=). يعرض صورة + نبذة من ويكيبيديا
 * (best-effort مُخزّنة) + بياناته الثابتة + مبارياته في البطولة (تظهر عند التعيين).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

$lang = current_lang();
$i    = isset($_GET['i']) ? (int)$_GET['i'] : -1;
$ref  = $i >= 0 ? Referees::byIndex($i) : null;

// حكم غير موجود → ارجع لقائمة الحكّام
if ($ref === null || trim((string)($ref['name'] ?? '')) === '') {
    header('Location: ' . url('referees.php'));
    exit;
}

$name    = trim((string)$ref['name']);
$country = $lang === 'ar'
         ? ($ref['country_ar'] ?? $ref['country_en'] ?? '')
         : ($ref['country_en'] ?? $ref['country_ar'] ?? '');
$flag    = strtolower(trim((string)($ref['flag'] ?? '')));
$role    = $ref['role'] ?? 'referee';

$roleLabels = [
    'referee'   => $lang === 'ar' ? 'حكم ساحة'           : 'Referee',
    'assistant' => $lang === 'ar' ? 'حكم مساعد'          : 'Assistant Referee',
    'var'       => $lang === 'ar' ? 'حكم فيديو (VAR)'    : 'Video Match Official (VAR)',
];
$roleLabel = $roleLabels[$role] ?? $roleLabels['referee'];

$profile = Referees::profile($name, $lang);
$matches = Referees::matchesFor($name);

$L = [
    'about'      => $lang === 'ar' ? 'نبذة'                  : 'About',
    'no_profile' => $lang === 'ar' ? 'لا تتوفّر نبذة تفصيلية لهذا الحكم بعد — تُضاف عند توفّرها.'
                                    : "A detailed profile isn't available yet — added as it becomes available.",
    'source'     => $lang === 'ar' ? 'المصدر: ويكيبيديا'    : 'Source: Wikipedia',
    'read_more'  => $lang === 'ar' ? 'اقرأ المزيد'           : 'Read more',
    'role'       => $lang === 'ar' ? 'الاختصاص'             : 'Role',
    'his_matches'=> $lang === 'ar' ? 'مبارياته في البطولة'  : 'Tournament matches',
    'no_matches' => $lang === 'ar' ? 'لم تُسنَد إليه مباريات بعد — تظهر بمجرّد إعلان التعيينات.'
                                    : 'No matches assigned yet — shown once appointments are announced.',
    'back'       => $lang === 'ar' ? 'كل الحكّام'            : 'All referees',
];

$page_title = $name;
$page_desc  = $name . ' — ' . $roleLabel . ' · ' . $country;
if (!empty($profile['photo'])) $page_image = $profile['photo'];
tpl('header');
?>

<a class="back-link" href="<?= e(url('referees.php')) ?>">&larr; <?= e($L['back']) ?></a>

<div class="ref-profile">
  <div class="ref-profile-photo">
    <?php if (!empty($profile['photo'])): ?>
      <img src="<?= e($profile['photo']) ?>" alt="<?= e($name) ?>" loading="lazy">
    <?php elseif ($flag !== ''): ?>
      <span class="ref-profile-flag"><img src="https://flagcdn.com/w160/<?= e($flag) ?>.png" alt="" loading="lazy"></span>
    <?php else: ?>
      <span class="ref-profile-empty">🧑‍⚖️</span>
    <?php endif; ?>
  </div>
  <div class="ref-profile-info">
    <h1><?= e($name) ?></h1>
    <p class="ref-profile-country">
      <?php if ($flag !== ''): ?>
        <img class="flag" src="https://flagcdn.com/w40/<?= e($flag) ?>.png" alt="" loading="lazy" width="28" height="21">
      <?php endif; ?>
      <span><?= e($country) ?></span>
    </p>
    <span class="ref-role-badge ref-role-<?= e($role) ?>"><?= e($roleLabel) ?></span>
  </div>
</div>

<?php
// 🆕 إحصائيات الحكم في البطولة — محسوبة من مبارياتنا الفعليّة
$rs = Referees::statsFor($name);
if ($rs && $rs['matches'] > 0):
    $cardsTotal = $rs['yellow'] + $rs['red'];
    $avg        = round($cardsTotal / $rs['matches'], 1);
    // مؤشّر الصرامة: معدّل بطاقات/مباراة على مقياس 0–8
    $pct = min(100, (int)round($avg / 8 * 100));
    if     ($avg < 2)  { $sLabel = $lang === 'ar' ? 'هادئ'       : 'Lenient';     $sColor = '#36c08f'; }
    elseif ($avg < 4)  { $sLabel = $lang === 'ar' ? 'متوازن'     : 'Balanced';    $sColor = '#f7e09a'; }
    elseif ($avg < 6)  { $sLabel = $lang === 'ar' ? 'صارم'       : 'Strict';      $sColor = '#f59e0b'; }
    else               { $sLabel = $lang === 'ar' ? 'صارم جداً'  : 'Very strict'; $sColor = '#ef4444'; }
?>
<section class="section">
  <h2 class="section-title">📊 <?= e($lang === 'ar' ? 'إحصائياته في البطولة' : 'Tournament record') ?></h2>
  <div class="ref-stats-grid">
    <div class="ref-stat"><div class="ref-stat-v"><?= (int)$rs['matches'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'مباريات أدارها' : 'Matches') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v" style="color:#f7e09a">🟨 <?= (int)$rs['yellow'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات صفراء' : 'Yellow cards') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v" style="color:#ef4444">🟥 <?= (int)$rs['red'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'بطاقات حمراء' : 'Red cards') ?></div></div>
    <div class="ref-stat"><div class="ref-stat-v">⚽ <?= (int)$rs['pens'] ?></div><div class="ref-stat-k"><?= e($lang === 'ar' ? 'ركلات جزاء احتسبها' : 'Penalties awarded') ?></div></div>
  </div>
  <div class="ref-strict">
    <div class="ref-strict-head">
      <span><?= e($lang === 'ar' ? 'مؤشّر الصرامة' : 'Strictness index') ?></span>
      <strong style="color:<?= $sColor ?>"><?= e($sLabel) ?> · <?= e((string)$avg) ?> <?= e($lang === 'ar' ? 'بطاقة/مباراة' : 'cards/match') ?></strong>
    </div>
    <div class="ref-strict-bar"><span style="width:<?= $pct ?>%;background:<?= $sColor ?>"></span></div>
  </div>
  <p class="muted" style="font-size:.78rem;margin-top:8px">
    <?= e($lang === 'ar'
        ? 'تُحسب تلقائياً من المباريات المنتهية التي أدارها في كأس العالم 2026.'
        : 'Computed automatically from his finished FIFA World Cup 2026 matches.') ?>
  </p>
</section>
<?php endif; ?>

<section class="section">
  <h2 class="section-title"><?= e($L['about']) ?></h2>
  <?php if (!empty($profile['bio'])): ?>
    <p class="ref-bio"><?= e($profile['bio']) ?></p>
    <?php if (!empty($profile['url'])): ?>
      <p class="ref-bio-src">
        <a href="<?= e($profile['url']) ?>" target="_blank" rel="noopener">
          <?= e($L['read_more']) ?> ↗
        </a>
        <span class="muted"> · <?= e($L['source']) ?></span>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p class="empty-note"><?= e($L['no_profile']) ?></p>
  <?php endif; ?>
</section>

<section class="section">
  <h2 class="section-title"><?= e($L['his_matches']) ?></h2>
  <?php if ($matches): ?>
    <div class="match-grid">
      <?php foreach ($matches as $m) render_match_card($m); ?>
    </div>
  <?php else: ?>
    <p class="empty-note"><?= e($L['no_matches']) ?></p>
  <?php endif; ?>
</section>

<?php tpl('footer'); ?>
