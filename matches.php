<?php
/**
 * matches.php — كل المباريات مع فلترة (جولة / مجموعة / حالة).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

$page_title = t('matches');

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
  <a class="btn btn-sm cal-sub" href="<?= $webcalUrl ?>">🔔 <?= e(t('subscribe_calendar')) ?></a>
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

<?php tpl('footer'); ?>
