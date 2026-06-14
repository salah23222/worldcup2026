<?php
/**
 * matches.php — كل المباريات مع فلترة (جولة / مجموعة / حالة).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

$page_title = t('matches');
// معاينة المشاركة: بطاقة «المباريات القادمة خلال 24 ساعة» (أفقيّة 1200×630 — لا تُقصّ على X)
$page_image = url('card_img.php', ['mode' => 'upcoming', 'd' => card_rev()]);
$page_desc  = current_lang() === 'ar'
    ? 'كل مباريات كأس العالم 2026 ومواعيدها بتوقيتك — والمباريات القادمة خلال 24 ساعة.'
    : 'All FIFA World Cup 2026 matches and kickoff times in your timezone — plus what is coming in the next 24 hours.';

// قراءة الفلاتر من الرابط
$fRound  = isset($_GET['round'])  ? trim($_GET['round'])  : '';
$fGroup  = isset($_GET['group'])  ? trim($_GET['group'])  : '';
$fStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

$all = DataService::allMatches();

// تطبيق الفلاتر
$matches = array_filter($all, function ($m) use ($fRound, $fGroup, $fStatus) {
    if ($fRound  !== '' && ($m['round'] ?? '') !== $fRound)  return false;
    if ($fGroup  !== '' && ($m['group'] ?? '') !== $fGroup)  return false;
    if ($fStatus !== '' && ($m['_status'] ?? '') !== $fStatus) return false;
    return true;
});

// ترتيب زمني
usort($matches, fn($a, $b) =>
    (DataService::matchTimestamp($a) ?? 0) <=> (DataService::matchTimestamp($b) ?? 0));

// تجميع حسب اليوم لعرض أنيق
$byDate = [];
foreach ($matches as $m) {
    $ts  = DataService::matchTimestamp($m);
    $key = $ts ? date('Y-m-d', $ts) : '0000';
    $byDate[$key][] = $m;
}

tpl('header');
?>

<div class="page-head">
  <h1><?= e(t('all_matches')) ?></h1>
  <p class="muted"><?= count($matches) ?> / <?= count($all) ?></p>
</div>

<?php
  $icsUrl   = e(url('calendar.php'));
  $calBase  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
  $webcalUrl = e('webcal://' . ($_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST))
             . $calBase . '/calendar.php?lang=' . current_lang());
?>
<div class="cal-bar">
  <a class="btn btn-cta" href="<?= $icsUrl ?>">📅 <?= e(t('add_to_calendar')) ?></a>
  <button type="button" class="btn btn-sm cal-sub" data-share="native"
          data-share-url="<?= e(url('matches.php', ['d' => card_rev()])) ?>"
          data-share-text="<?= e(current_lang()==='ar' ? 'المباريات القادمة خلال 24 ساعة — كأس العالم 2026' : 'Upcoming matches in the next 24h — FIFA World Cup 2026') ?>">📢 <?= e(current_lang()==='ar' ? 'شارك المباريات' : 'Share matches') ?></button>
  <a class="btn btn-sm cal-sub" href="<?= $webcalUrl ?>">🔔 <?= e(t('subscribe_calendar')) ?></a>
  <a class="btn btn-sm cal-sub" href="<?= e(url('print.php')) ?>" target="_blank">🖨️ <?= e(current_lang()==='ar' ? 'طباعة جدول البطولة' : 'Print schedule poster') ?></a>
  <span class="cal-hint muted"><?= e(t('calendar_hint')) ?></span>
</div>

<!-- ============ شريط الفلاتر ============ -->
<form class="filters" method="get" id="matchFilters">
  <input type="hidden" name="lang" value="<?= e(current_lang()) ?>">

  <select name="round" onchange="this.form.submit()">
    <option value=""><?= e(t('all')) ?> — <?= e(t('matchday')) ?></option>
    <?php foreach (DataService::roundNames() as $r): ?>
      <option value="<?= e($r) ?>" <?= $fRound === $r ? 'selected' : '' ?>>
        <?= e(round_label($r)) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="group" onchange="this.form.submit()">
    <option value=""><?= e(t('all')) ?> — <?= e(t('groups')) ?></option>
    <?php foreach (DataService::groupNames() as $g): ?>
      <option value="<?= e($g) ?>" <?= $fGroup === $g ? 'selected' : '' ?>>
        <?= e(group_label($g)) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="status" onchange="this.form.submit()">
    <option value=""><?= e(t('all')) ?></option>
    <option value="live"     <?= $fStatus==='live'?'selected':'' ?>><?= e(t('live')) ?></option>
    <option value="finished" <?= $fStatus==='finished'?'selected':'' ?>><?= e(t('finished')) ?></option>
    <option value="upcoming" <?= $fStatus==='upcoming'?'selected':'' ?>><?= e(t('upcoming_short')) ?></option>
  </select>

  <?php if ($fRound || $fGroup || $fStatus): ?>
    <a class="filter-clear" href="<?= e(url('matches.php')) ?>">✕ <?= e(t('all')) ?></a>
  <?php endif; ?>
</form>

<!-- ============ المباريات مجمّعة حسب اليوم ============ -->
<?php if (!$matches): ?>
  <p class="empty-note"><?= e(t('no_data')) ?></p>
<?php else: ?>
  <?php foreach ($byDate as $day => $dayMatches):
    $ts = ($day !== '0000') ? strtotime($day) : null; ?>
    <section class="day-block" data-autorefresh="1">
      <h2 class="day-title"><?= local_dt($ts, 'date') ?></h2>
      <div class="match-grid">
        <?php foreach ($dayMatches as $m) render_match_card($m); ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<!-- ============ مشاركة المباريات (نفس شريط بقيّة الصفحات) — المعاينة = بطاقة الـ24 ساعة ============ -->
<?php
  // هاشتاكات فرق مباريات الـ24 ساعة القادمة (AR + EN) — تُضاف لوسوم render_share الأساسيّة
  $shareTeams = [];
  if (class_exists('TweetComposer')) {
      foreach (TweetComposer::next24Matches(4) as $um) {
          foreach (['team1', 'team2'] as $k) {
              $tn = trim((string)($um[$k] ?? ''));
              if ($tn !== '' && (!function_exists('is_real_team') || is_real_team($tn))) $shareTeams[] = $tn;
          }
      }
  }
  $shareTeams = array_slice(array_values(array_unique($shareTeams)), 0, 4);
  render_share(
      url('matches.php', ['d' => card_rev()]),
      current_lang() === 'ar'
          ? 'المباريات القادمة خلال 24 ساعة — كأس العالم 2026'
          : 'Upcoming matches in the next 24h — FIFA World Cup 2026',
      ['teams' => $shareTeams]
  );
?>

<?php tpl('footer'); ?>
