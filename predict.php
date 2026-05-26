<?php
/**
 * predict.php — مسابقة التوقعات (نقاط افتراضية).
 * يعرض المباريات المفتوحة للتوقّع، والتفاعل (تسجيل/حفظ) عبر api/predict.php.
 */
require __DIR__ . '/includes/bootstrap.php';

// نضمن وجود رمز CSRF قبل أي إخراج HTML (يضبط كوكي عند اللزوم)
$csrf    = Predictions::ensureCsrf();
$user    = Predictions::user();
$myPreds = Predictions::myPredictions();

/** هل الاسم مجرّد placeholder لدور إقصائي (W73 / 1A ...)؟ */
function pred_is_placeholder(string $raw): bool {
    $raw = trim($raw);
    return $raw === '' || preg_match('/^(W\d+|RU\d+|L\d+|[0-9][A-L])$/i', $raw) === 1;
}

// المباريات المفتوحة للتوقّع: لم تُقفل + الفريقان محدّدان
$open = [];
foreach (DataService::allMatches() as $m) {
    if (Predictions::isLocked($m)) continue;
    if (pred_is_placeholder($m['team1'] ?? '') || pred_is_placeholder($m['team2'] ?? '')) continue;
    $open[] = $m;
}
usort($open, fn($a, $b) => (DataService::matchTimestamp($a) ?? PHP_INT_MAX)
                        <=> (DataService::matchTimestamp($b) ?? PHP_INT_MAX));

// تجميع حسب اليوم
$byDate = [];
foreach ($open as $m) {
    $ts  = DataService::matchTimestamp($m);
    $key = $ts ? date('Y-m-d', $ts) : '0000';
    $byDate[$key][] = $m;
}

$page_title = t('competition');
$page_desc  = t('comp_intro');
tpl('header');
?>

<div class="page-head">
  <h1>🎯 <?= e(t('competition')) ?></h1>
  <p class="muted"><?= e(t('comp_intro')) ?></p>
</div>

<!-- نظام النقاط -->
<div class="scoring-card">
  <strong><?= e(t('scoring')) ?>:</strong>
  <span class="sc-pill sc-3"><?= e(t('scoring_exact')) ?></span>
  <span class="sc-pill sc-2"><?= e(t('scoring_winner')) ?></span>
  <span class="sc-pill sc-1"><?= e(t('scoring_draw')) ?></span>
</div>

<!-- 😩 عدّاد القهر (مرح) -->
<div class="qahr-meter">
  <span>😩 <?= e(t('qahr_meter')) ?>:</span>
  <strong id="qahrTitle">—</strong>
  <span class="qahr-count">(<span id="qahrNum">0</span>)</span>
  <button type="button" class="btn-link" id="qahrBtn"><?= e(t('qahr_btn')) ?></button>
</div>

<!-- لوحة الهوية: انضمام أو ترحيب -->
<div id="predictApp"
     data-csrf="<?= e($csrf) ?>"
     data-registered="<?= $user ? '1' : '0' ?>"
     data-api="<?= e(rtrim(SITE_URL, '/')) ?>/api/predict.php">

  <div class="join-box" id="joinBox" <?= $user ? 'hidden' : '' ?>>
    <?php if (Database::available()): ?>
      <p class="join-hint"><?= e(t('login_to_predict')) ?></p>
      <a class="btn btn-accent" href="<?= e(url('login.php')) ?>"><?= e(t('login')) ?></a>
      <a class="btn btn-sm" href="<?= e(url('register.php')) ?>"><?= e(t('register')) ?></a>
    <?php else: ?>
      <p class="join-hint"><?= e(t('login_to_predict')) ?></p>
      <form id="joinForm" class="join-form" autocomplete="off">
        <input type="text" id="nickInput" name="nickname"
               maxlength="20" placeholder="<?= e(t('nickname_hint')) ?>"
               aria-label="<?= e(t('nickname')) ?>" required>
        <button type="submit" class="btn btn-accent"><?= e(t('join')) ?></button>
      </form>
      <p class="join-error" id="joinError" hidden></p>
    <?php endif; ?>
  </div>

  <div class="welcome-box" id="welcomeBox" <?= $user ? '' : 'hidden' ?>>
    <span><?= e(t('welcome')) ?>, <strong id="welcomeName"><?= e($user['nickname'] ?? '') ?></strong> 👋</span>
    <?php if (!Database::available()): ?>
      <button type="button" class="btn-link" id="changeNameBtn"><?= e(t('change_name')) ?></button> ·
    <?php endif; ?>
    <a class="btn-link" href="<?= e(url('leaderboard.php')) ?>"><?= e(t('leaderboard')) ?> ›</a>
    <?php if (Database::available()): ?>
      · <a class="btn-link" href="<?= e(url('logout.php')) ?>"><?= e(t('logout')) ?></a>
    <?php endif; ?>
  </div>
</div>

<!-- قائمة المباريات المفتوحة -->
<?php if (!$open): ?>
  <p class="empty-note"><?= e(t('no_open_matches')) ?></p>
<?php else: ?>
  <?php foreach ($byDate as $day => $dayMatches):
    $ts = ($day !== '0000') ? strtotime($day . ' 00:00:00 UTC') : null; ?>
    <section class="day-block">
      <h2 class="day-title"><?= $ts ? local_dt($ts, 'date') : '—' ?></h2>
      <div class="pred-list">
        <?php foreach ($dayMatches as $m):
          $t1  = trim($m['team1'] ?? '');
          $t2  = trim($m['team2'] ?? '');
          $idx = (int)($m['_index'] ?? 0);
          $mts = DataService::matchTimestamp($m);
          $pv  = $myPreds[(string)$idx] ?? null;
          ?>
          <div class="pred-row" data-id="<?= $idx ?>">
            <div class="pred-meta">
              <span class="pred-round"><?= e(round_label($m['round'] ?? '')) ?></span>
              <span class="pred-time"><?= local_dt($mts, 'datetime') ?></span>
            </div>
            <div class="pred-body">
              <div class="pred-team pred-team-1">
                <span class="pred-team-name"><?= e(team_name($t1)) ?></span>
                <?= flag_img($t1, 'w40') ?>
              </div>
              <div class="pred-inputs">
                <input type="number" class="pred-in pred-p1" min="0" max="30"
                       inputmode="numeric" value="<?= $pv ? (int)$pv['p1'] : '' ?>"
                       aria-label="<?= e(team_name($t1)) ?>">
                <span class="pred-colon">:</span>
                <input type="number" class="pred-in pred-p2" min="0" max="30"
                       inputmode="numeric" value="<?= $pv ? (int)$pv['p2'] : '' ?>"
                       aria-label="<?= e(team_name($t2)) ?>">
              </div>
              <div class="pred-team pred-team-2">
                <?= flag_img($t2, 'w40') ?>
                <span class="pred-team-name"><?= e(team_name($t2)) ?></span>
              </div>
            </div>
            <div class="pred-actions">
              <button type="button" class="btn btn-sm pred-fate" title="<?= e(t('fate_decide')) ?>">🪙</button>
              <button type="button" class="btn btn-sm pred-save"><?= e(t('save')) ?></button>
              <button type="button" class="btn btn-sm pred-share" title="<?= e(t('share_pred')) ?>">🔗</button>
              <button type="button" class="btn btn-sm pred-brag" title="<?= e(t('brag_card')) ?>">📢</button>
              <span class="pred-status" aria-live="polite"></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<script>
window.WC_I18N = {
  saved:   <?= json_encode(t('saved'), JSON_UNESCAPED_UNICODE) ?>,
  locked:  <?= json_encode(t('locked'), JSON_UNESCAPED_UNICODE) ?>,
  name_taken: <?= json_encode(t('name_taken'), JSON_UNESCAPED_UNICODE) ?>,
  invalid_nickname: <?= json_encode(t('invalid_nickname'), JSON_UNESCAPED_UNICODE) ?>,
  welcome: <?= json_encode(t('welcome'), JSON_UNESCAPED_UNICODE) ?>
};
window.WC_FUN = {
  bragText: <?= json_encode(current_lang()==='ar' ? 'قلتلكم! توقّعي في كأس العالم 2026 🔮 — ' : 'I called it! My FIFA World Cup 2026 prediction 🔮 — ', JSON_UNESCAPED_UNICODE) ?>,
  qahrTitles: <?= json_encode(current_lang()==='ar'
      ? ['بلا قهر بعد 😌','مبتدئ في القهر','محترف خيبات','سفير القهر','أسطورة القهر العالمي 👑']
      : ['No heartbreak yet 😌','Heartbreak rookie','Disappointment pro','Heartbreak envoy','Global Heartbreak Legend 👑'], JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= e(rtrim(SITE_URL,'/')) ?>/assets/js/predict.js" defer></script>

<?php tpl('footer'); ?>
