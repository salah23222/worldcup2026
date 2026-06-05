<?php
/**
 * topscorers.php — هدّافو البطولة (الحالية + الأبطال التاريخيون).
 *  (أ) هدّافو نسخة 2026 من بيانات openfootball الفعلية (فارغة قبل الانطلاق).
 *  (ب) الحذاء الذهبي عبر التاريخ — قائمة ثابتة بحقائق عامة.
 */
require __DIR__ . '/includes/bootstrap.php';

$current = Scorers::current();
$history = Scorers::goldenBootHistory();

$page_title = t('top_scorers');
$page_desc  = t('top_scorers_intro');
tpl('header');

/**
 * صورة اللاعب لكرت FUT: Wikimedia أولاً (مجانية CC) ثم علم منتخبه كبديل.
 * $flagUrl رابط علم احتياطي (يُعرض حين لا تتوفّر صورة).
 */
function fut_photo_html(string $name, string $flagUrl): string {
    $photo = Scorers::photo($name);
    if ($photo !== '') {
        return '<img class="fut-img" src="' . e($photo) . '" alt="' . e($name) . '" loading="lazy">';
    }
    if ($flagUrl !== '') {
        return '<span class="fut-img fut-img-flag"><img src="' . e($flagUrl) . '" alt="" loading="lazy"></span>';
    }
    return '<span class="fut-img fut-img-empty">⚽</span>';
}

/**
 * كرت لاعب بأسلوب البطاقات المجمّعة (FUT):
 *   رقم الأهداف (كالتقييم) + شارة (سنة/مركز) + صورة + اسم + علم/بلد.
 * $c: ['name','photo','flag','label','goals','badge','gold'(bool)]
 */
function render_scorer_card(array $c): void {
    $gold = !empty($c['gold']) ? ' fut-gold' : ''; ?>
  <div class="fut-card<?= $gold ?>">
    <div class="fut-top">
      <span class="fut-rating"><?= (int)$c['goals'] ?><small><?= e(t('goals')) ?></small></span>
      <span class="fut-badge"><?= e((string)$c['badge']) ?></span>
    </div>
    <div class="fut-photo"><?= $c['photo'] ?></div>
    <div class="fut-name"><?= e($c['name']) ?></div>
    <div class="fut-foot"><?= $c['flag'] ?><span><?= e($c['label']) ?></span></div>
  </div>
<?php }

$lang = current_lang();
?>

<div class="page-head">
  <h1>⚽ <?= e(t('top_scorers')) ?></h1>
  <p class="muted"><?= e(t('top_scorers_intro')) ?></p>
</div>

<!-- ============ (أ) هدّافو البطولة الحالية ============ -->
<section class="day-block">
  <h2 class="day-title"><?= e(t('current_top_scorers')) ?></h2>

  <?php if (!$current): ?>
    <p class="empty-note"><?= e(t('no_scorers_yet')) ?></p>
  <?php else: ?>
    <div class="fut-grid">
      <?php foreach ($current as $i => $r):
        $rank   = $i + 1;
        $medal  = $rank <= 3 ? ['', '🥇', '🥈', '🥉'][$rank] : '#' . $rank;
        render_scorer_card([
          'name'  => $r['name'],
          'photo' => fut_photo_html($r['name'], flag_url($r['team'], 'w160')),
          'flag'  => flag_img($r['team'], 'w40'),
          'label' => team_name($r['team']),
          'goals' => (int)$r['goals'],
          'badge' => $medal,
          'gold'  => $rank <= 3,
        ]);
      endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<hr class="arch-sep">

<!-- ============ (ب) الهدّافون التاريخيون (الحذاء الذهبي) ============ -->
<section class="day-block">
  <h2 class="day-title">🥇 <?= e(t('all_time_golden_boots')) ?></h2>

  <div class="fut-grid">
    <?php foreach ($history as $h):
      $flagUrl = 'https://flagcdn.com/%s/' . strtolower($h['flag']) . '.png';
      render_scorer_card([
        'name'  => $h['name'],
        'photo' => fut_photo_html($h['name'], sprintf($flagUrl, 'w160')),
        'flag'  => '<img class="flag" src="' . e(sprintf($flagUrl, 'w40')) . '" alt="" loading="lazy" width="28" height="21">',
        'label' => match($lang) { 'ar' => $h['country_ar'], 'fr' => $h['country_fr'] ?? $h['country_en'], default => $h['country_en'] },
        'goals' => (int)$h['goals'],
        'badge' => (int)$h['year'],
        'gold'  => true,
      ]);
    endforeach; ?>
  </div>
</section>

<?php tpl('footer'); ?>
