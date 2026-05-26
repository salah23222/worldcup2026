<?php
/**
 * leagues.php — "المجلس": إنشاء دوريّة خاصّة + الانضمام برمز + قائمة دورياتي.
 * كل التفاعل عبر api/league.php (AJAX) مع CSRF؛ المستخدم بلا هوية يُوجَّه أولاً
 * لاختيار اسم في predict.php.
 */
require __DIR__ . '/includes/bootstrap.php';

// نضمن رمز CSRF قبل أي إخراج HTML (قد يضبط كوكي).
$csrf = Predictions::ensureCsrf();
$user = Predictions::user();

$ar = (current_lang() === 'ar');
$L  = fn(string $a, string $e) => $ar ? $a : $e;

$myLeagues = $user ? Leagues::myLeagues() : [];

$page_title = $L('المجلس', 'My Leagues');
$page_desc  = $L('أنشئ دوريّة توقّعات خاصّة بأصدقائك وعائلتك وزملائك — بلوحة صدارة خاصّة بكم.',
                 'Create a private predictions league with your friends, family and coworkers — with your own leaderboard.');
tpl('header');
?>

<link rel="stylesheet" href="<?= e(rtrim(SITE_URL, '/')) ?>/assets/css/leagues.css?v=<?= @filemtime(__DIR__ . '/assets/css/leagues.css') ?: 1 ?>">

<div class="page-head">
  <h1>🏆 <?= e($L('المجلس', 'My Leagues')) ?></h1>
  <p class="muted"><?= e($L('دوريّة توقّعات خاصّة بينك وبين من تختار — لوحة صدارتكم وحدكم.',
                            'A private predictions league among you and whoever you invite — your own leaderboard.')) ?></p>
</div>

<?php if (!$user): ?>
  <!-- بلا هوية: ادعُه لاختيار اسم أولاً في صفحة التوقعات -->
  <div class="lg-card lg-needname">
    <p><?= e($L('لإنشاء دوريّة أو الانضمام لواحدة، اختر اسمك أولاً في صفحة التوقعات.',
                'To create or join a league, pick your name first on the predictions page.')) ?></p>
    <a class="btn btn-accent" href="<?= e(url('predict.php')) ?>"><?= e($L('اختر اسمك وابدأ', 'Pick a name to start')) ?> ›</a>
  </div>
<?php else: ?>

  <div id="leaguesApp"
       data-csrf="<?= e($csrf) ?>"
       data-api="<?= e(rtrim(SITE_URL, '/')) ?>/api/league.php"
       data-base="<?= e(rtrim(SITE_URL, '/')) ?>"
       data-lang="<?= e(current_lang()) ?>">

    <div class="lg-grid">
      <!-- إنشاء دوريّة -->
      <section class="lg-card">
        <h2><?= e($L('أنشئ دوريّة', 'Create a league')) ?></h2>
        <form id="lgCreateForm" class="lg-form" autocomplete="off">
          <input type="text" id="lgName" name="name" maxlength="40"
                 placeholder="<?= e($L('اسم الدوريّة (مثل: شلّة العمل)', 'League name (e.g. The Work Crew)')) ?>"
                 aria-label="<?= e($L('اسم الدوريّة', 'League name')) ?>" required>
          <button type="submit" class="btn btn-accent"><?= e($L('إنشاء', 'Create')) ?></button>
        </form>
        <p class="lg-error" id="lgCreateError" hidden></p>
      </section>

      <!-- الانضمام برمز -->
      <section class="lg-card">
        <h2><?= e($L('انضم برمز', 'Join by code')) ?></h2>
        <form id="lgJoinForm" class="lg-form" autocomplete="off">
          <input type="text" id="lgCode" name="code" maxlength="6"
                 placeholder="<?= e($L('الرمز (6 أحرف)', 'Code (6 chars)')) ?>"
                 aria-label="<?= e($L('رمز الدوريّة', 'League code')) ?>"
                 style="text-transform:uppercase" required>
          <button type="submit" class="btn"><?= e($L('انضمام', 'Join')) ?></button>
        </form>
        <p class="lg-error" id="lgJoinError" hidden></p>
      </section>
    </div>

    <!-- قائمة دورياتي -->
    <h2 class="lg-mine-title"><?= e($L('دورياتي', 'My leagues')) ?></h2>
    <div id="lgList" class="lg-list">
      <?php if (!$myLeagues): ?>
        <p class="empty-note" id="lgEmpty"><?= e($L('لا دوريات بعد. أنشئ واحدة وادعُ أصدقاءك!',
                                                    'No leagues yet. Create one and invite your friends!')) ?></p>
      <?php else: ?>
        <?php foreach ($myLeagues as $lg): ?>
          <a class="lg-item" href="<?= e(url('league.php', ['code' => $lg['code']])) ?>">
            <span class="lg-item-name"><?= e($lg['name']) ?></span>
            <span class="lg-item-meta">
              <span class="lg-item-code"><?= e($lg['code']) ?></span>
              · <?= (int)$lg['members'] ?> <?= e($L('عضو', 'members')) ?>
              <?php if (!empty($lg['is_owner'])): ?>· <span class="lg-badge"><?= e($L('مالك', 'owner')) ?></span><?php endif; ?>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <script>
  window.WC_LG_I18N = {
    members:    <?= json_encode($L('عضو', 'members'), JSON_UNESCAPED_UNICODE) ?>,
    owner:      <?= json_encode($L('مالك', 'owner'), JSON_UNESCAPED_UNICODE) ?>,
    err_generic:<?= json_encode($L('حدث خطأ، حاول مجدداً.', 'Something went wrong, try again.'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_name:<?= json_encode($L('اسم غير صالح (٢ إلى ٤٠ حرفاً).', 'Invalid name (2–40 chars).'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_code:<?= json_encode($L('رمز غير صالح.', 'Invalid code.'), JSON_UNESCAPED_UNICODE) ?>,
    not_found:  <?= json_encode($L('لا توجد دوريّة بهذا الرمز.', 'No league with that code.'), JSON_UNESCAPED_UNICODE) ?>,
    too_many:   <?= json_encode($L('بلغت الحد الأقصى للدوريات.', 'You reached the leagues limit.'), JSON_UNESCAPED_UNICODE) ?>,
    full:       <?= json_encode($L('الدوريّة مكتملة العدد.', 'This league is full.'), JSON_UNESCAPED_UNICODE) ?>,
    rate_limited:<?= json_encode($L('محاولات كثيرة، انتظر قليلاً.', 'Too many attempts, wait a bit.'), JSON_UNESCAPED_UNICODE) ?>,
    login_required:<?= json_encode($L('اختر اسمك أولاً.', 'Pick your name first.'), JSON_UNESCAPED_UNICODE) ?>
  };
  </script>
  <script src="<?= e(rtrim(SITE_URL, '/')) ?>/assets/js/leagues.js?v=<?= @filemtime(__DIR__ . '/assets/js/leagues.js') ?: 1 ?>" defer></script>

<?php endif; ?>

<?php tpl('footer'); ?>
