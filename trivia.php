<?php
/**
 * trivia.php — تحدّي المعرفة اليومي.
 * إجابة صحيحة = 3 نقاط تُضاف لرصيد المستخدم في الصدارة (تُحتسب على الخادم).
 * سؤال واحد لكل يوم، تُسجَّل مرة واحدة لكل مستخدم مسجّل.
 */
require __DIR__ . '/includes/bootstrap.php';

$csrf = Predictions::ensureCsrf();      // قبل أي إخراج (يضبط كوكي)
$info = Predictions::triviaInfo();
$q    = AiContent::dailyTrivia(current_lang());
$day  = date('Y-m-d');
$pointsLive = Predictions::pointsActive();   // النقاط تُحتسب فقط بعد انطلاق البطولة

$page_title = t('daily_trivia');
$page_desc  = t('trivia_intro');
tpl('header');
?>

<div class="page-head">
  <h1>🧠 <?= e(t('daily_trivia')) ?></h1>
  <p class="muted"><?= e(t('trivia_intro')) ?></p>
</div>

<div class="trivia-topbar">
  <span class="trivia-streak">🔥 <span id="streakNum">0</span> <?= e(t('day_streak')) ?></span>
  <span class="trivia-points-note">⭐ <?= e($pointsLive ? t('trivia_points_note') : t('trivia_points_soon')) ?></span>
</div>

<?php if (!$info['registered']): ?>
  <p class="muted" style="margin-bottom:14px">
    <?= e(t('trivia_login_hint')) ?> ·
    <a class="section-link" href="<?= e(url('predict.php')) ?>"><?= e(t('join')) ?> ›</a>
  </p>
<?php endif; ?>

<?php if (!$q): ?>
  <p class="empty-note"><?= e(t('no_trivia')) ?></p>
<?php else:
  $answered = $info['answered_today'];
?>
  <div class="trivia-card" id="triviaCard">
    <p class="trivia-q"><?= e($q['q']) ?></p>
    <div class="trivia-options">
      <?php foreach ($q['options'] as $i => $opt):
        $cls = '';
        if ($answered) {
            if ($i === (int)$q['correct'])      $cls = 'opt-correct';
            elseif ($i === (int)$info['chosen']) $cls = 'opt-wrong';
        }
      ?>
        <button type="button" class="trivia-opt <?= $cls ?>" data-i="<?= (int)$i ?>" <?= $answered ? 'disabled' : '' ?>>
          <?= e($opt) ?>
        </button>
      <?php endforeach; ?>
    </div>
    <?php if ($answered): ?>
      <p class="trivia-explain"><?= e($q['explain'] ?? '') ?></p>
      <p class="trivia-note"><?= e(t('answered_today')) ?></p>
    <?php else: ?>
      <p class="trivia-explain" id="triviaExplain" hidden></p>
    <?php endif; ?>
  </div>

  <script>
  window.WC_TRIVIA = {
    api: <?= json_encode(rtrim(SITE_URL,'/') . '/api/predict.php', JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode($csrf) ?>,
    lang: <?= json_encode(current_lang()) ?>,
    day: <?= json_encode($day) ?>,
    answered: <?= $answered ? 'true' : 'false' ?>,
    registered: <?= $info['registered'] ? 'true' : 'false' ?>,
    i18n: {
      correct:  <?= json_encode(t('correct_answer'), JSON_UNESCAPED_UNICODE) ?>,
      wrong:    <?= json_encode(t('wrong_answer'), JSON_UNESCAPED_UNICODE) ?>,
      added:    <?= json_encode(t('points_added'), JSON_UNESCAPED_UNICODE) ?>,
      guest:    <?= json_encode(t('no_points_guest'), JSON_UNESCAPED_UNICODE) ?>
    }
  };
  </script>
  <script src="<?= e(rtrim(SITE_URL,'/')) ?>/assets/js/trivia.js" defer></script>
<?php endif; ?>

<?php tpl('footer'); ?>
