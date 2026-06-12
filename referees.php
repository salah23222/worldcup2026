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

// خريطة عدد المباريات لكل حكم (مسح واحد للبيانات بدل استدعاء متكرّر)
$officiated = [];
foreach (DataService::allMatches() as $m) {
    $rn = trim((string)($m['referee'] ?? ''));
    if ($rn !== '') $officiated[$rn] = ($officiated[$rn] ?? 0) + 1;
}

$page_title = $L['title'];
$page_desc  = $L['intro'];
tpl('header');
?>

<div class="page-head">
  <h1>🧑‍⚖️ <?= e($L['title']) ?></h1>
  <p class="muted"><?= e($L['intro']) ?></p>
</div>

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
          $n    = $officiated[$name] ?? 0;
          $href = url('referee.php', ['i' => (int)($r['_index'] ?? 0)]);
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
              <span class="ref-matches"><?= (int)$n ?> <?= e($L['matches']) ?></span>
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
