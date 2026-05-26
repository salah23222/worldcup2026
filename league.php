<?php
/**
 * league.php — صفحة دوريّة واحدة: لوحة صدارتها + صندوق دعوة (رابط + واتساب) + مغادرة/تسمية.
 * يُفتح عبر ?code=XXXXXX. الرمز غير الصالح → صفحة "غير موجودة" لطيفة.
 */
require __DIR__ . '/includes/bootstrap.php';

$csrf = Predictions::ensureCsrf();
$user = Predictions::user();

$ar = (current_lang() === 'ar');
$L  = fn(string $a, string $e) => $ar ? $a : $e;

$code   = Leagues::normalizeCode((string)($_GET['code'] ?? ''));
$league = Leagues::validCode($code) ? Leagues::byCode($code) : null;

$page_title = $league ? $league['name'] : $L('المجلس', 'League');
$page_desc  = $L('لوحة صدارة دوريّة التوقّعات الخاصّة.', 'Private predictions league leaderboard.');
tpl('header');
?>

<link rel="stylesheet" href="<?= e(rtrim(SITE_URL, '/')) ?>/assets/css/leagues.css?v=<?= @filemtime(__DIR__ . '/assets/css/leagues.css') ?: 1 ?>">

<?php if ($league === null): ?>

  <div class="page-head">
    <h1>🤔 <?= e($L('لم نجد هذه الدوريّة', 'League not found')) ?></h1>
    <p class="muted"><?= e($L('الرمز غير صحيح أو الدوريّة لم تعد موجودة.',
                              'The code is wrong or the league no longer exists.')) ?></p>
  </div>
  <p style="text-align:center;margin-top:16px">
    <a class="btn btn-accent" href="<?= e(url('leagues.php')) ?>"><?= e($L('إلى المجلس', 'Go to leagues')) ?> ›</a>
  </p>

<?php else:
  $id        = $league['id'];
  $isMember  = !empty($league['is_member']);
  $isOwner   = !empty($league['is_owner']);
  $rows      = Leagues::standings($id);
  // رابط الدعوة المطلق (بصيغة host/league.php?code=XXXXXX) — نفعّله بلغة الزائر الحالية.
  $inviteUrl = url('league.php', ['code' => $league['code']]);
  $waText    = $L('انضم لدوريّة توقّعاتنا في كأس العالم 2026 🏆 «' . $league['name'] . '» — الرمز: ' . $league['code'],
                  'Join our FIFA World Cup 2026 predictions league 🏆 "' . $league['name'] . '" — code: ' . $league['code']);
  $waUrl     = 'https://wa.me/?text=' . rawurlencode($waText . ' ' . $inviteUrl);
?>

  <div id="leagueApp"
       data-csrf="<?= e($csrf) ?>"
       data-api="<?= e(rtrim(SITE_URL, '/')) ?>/api/league.php"
       data-id="<?= e($id) ?>"
       data-owner="<?= $isOwner ? '1' : '0' ?>"
       data-member="<?= $isMember ? '1' : '0' ?>"
       data-registered="<?= $user ? '1' : '0' ?>">

    <div class="page-head">
      <h1>🏆 <span id="lgTitle"><?= e($league['name']) ?></span></h1>
      <p class="muted">
        <span id="lgCount"><?= (int)$league['members'] ?></span> <?= e($L('عضو', 'members')) ?>
        · <?= e($L('الرمز', 'Code')) ?>: <strong class="lg-code-inline"><?= e($league['code']) ?></strong>
        <?php if ($isOwner): ?>
          · <button type="button" class="btn-link" id="lgRenameBtn"><?= e($L('إعادة تسمية', 'Rename')) ?></button>
        <?php endif; ?>
      </p>
      <?php if (!empty($league['sponsor'])): ?>
        <p class="lg-sponsor"><?= e($L('برعاية', 'Sponsored by')) ?>: <?= e($league['sponsor']) ?></p>
      <?php endif; ?>
    </div>

    <!-- صندوق الدعوة -->
    <div class="lg-invite">
      <div class="lg-invite-head">
        <strong><?= e($L('ادعُ أصدقاءك', 'Invite your friends')) ?></strong>
        <span class="muted"><?= e($L('شاركهم الرمز أو الرابط لينضمّوا.', 'Share the code or link so they can join.')) ?></span>
      </div>
      <div class="lg-invite-row">
        <input type="text" id="lgInviteLink" class="lg-invite-input" readonly
               value="<?= e($inviteUrl) ?>" aria-label="<?= e($L('رابط الدعوة', 'Invite link')) ?>">
        <button type="button" class="btn lg-copy" id="lgCopyBtn"
                data-copied="<?= e($L('تم النسخ ✓', 'Copied ✓')) ?>"
                data-label="<?= e($L('نسخ الرابط', 'Copy link')) ?>">🔗 <?= e($L('نسخ الرابط', 'Copy link')) ?></button>
        <a class="btn lg-wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">📱 <?= e($L('واتساب', 'WhatsApp')) ?></a>
      </div>
    </div>

    <!-- انضمام/مغادرة -->
    <div class="lg-actions">
      <?php if (!$isMember && $user): ?>
        <button type="button" class="btn btn-accent" id="lgJoinBtn"><?= e($L('انضم لهذه الدوريّة', 'Join this league')) ?></button>
      <?php elseif (!$user): ?>
        <a class="btn btn-accent" href="<?= e(url('predict.php')) ?>"><?= e($L('اختر اسمك للانضمام', 'Pick a name to join')) ?> ›</a>
      <?php elseif ($isMember && !$isOwner): ?>
        <button type="button" class="btn btn-sm" id="lgLeaveBtn"><?= e($L('مغادرة الدوريّة', 'Leave league')) ?></button>
      <?php endif; ?>
      <span class="lg-action-status" id="lgActionStatus" aria-live="polite"></span>
    </div>

    <!-- لوحة صدارة الدوريّة (نفس بنية leaderboard.php) -->
    <div class="lb-wrap">
      <table class="leaderboard">
        <thead>
          <tr>
            <th><?= e(t('rank')) ?></th>
            <th class="lb-name"><?= e(t('player')) ?></th>
            <th><?= e(t('points')) ?></th>
            <th><?= e(t('exact')) ?></th>
            <th><?= e(t('correct_w')) ?></th>
            <th><?= e(t('predicted')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty-note"><?= e($L('لا نقاط بعد — ستظهر بعد انتهاء المباريات.',
                                                            'No points yet — they appear after matches finish.')) ?></td></tr>
          <?php else: foreach ($rows as $i => $r):
            $isMe = $user && $user['nickname'] === $r['nickname'];
            $rank = $i + 1; ?>
            <tr class="<?= $isMe ? 'lb-me' : '' ?> <?= $rank <= 3 ? 'lb-top lb-top-' . $rank : '' ?>">
              <td class="lb-rank"><?= $rank <= 3 ? ['', '🥇', '🥈', '🥉'][$rank] : $rank ?></td>
              <td class="lb-name"><?= e($r['nickname']) ?></td>
              <td class="lb-pts"><strong><?= (int)$r['points'] ?></strong></td>
              <td><?= (int)$r['exact'] ?></td>
              <td><?= (int)$r['correct'] ?></td>
              <td><?= (int)$r['played'] ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p style="text-align:center;margin-top:16px">
      <a class="section-link" href="<?= e(url('leagues.php')) ?>"><?= e($L('كل دورياتي', 'All my leagues')) ?> ›</a>
      ·
      <a class="section-link" href="<?= e(url('predict.php')) ?>"><?= e(t('competition')) ?> ›</a>
    </p>

  </div>

  <script>
  window.WC_LG_I18N = {
    err_generic:  <?= json_encode($L('حدث خطأ، حاول مجدداً.', 'Something went wrong, try again.'), JSON_UNESCAPED_UNICODE) ?>,
    invalid_name: <?= json_encode($L('اسم غير صالح (٢ إلى ٤٠ حرفاً).', 'Invalid name (2–40 chars).'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_leave:<?= json_encode($L('مغادرة هذه الدوريّة؟', 'Leave this league?'), JSON_UNESCAPED_UNICODE) ?>,
    rename_prompt:<?= json_encode($L('الاسم الجديد للدوريّة:', 'New league name:'), JSON_UNESCAPED_UNICODE) ?>,
    joined:       <?= json_encode($L('انضممت ✓', 'Joined ✓'), JSON_UNESCAPED_UNICODE) ?>,
    left:         <?= json_encode($L('غادرت', 'Left'), JSON_UNESCAPED_UNICODE) ?>
  };
  </script>
  <script src="<?= e(rtrim(SITE_URL, '/')) ?>/assets/js/leagues.js?v=<?= @filemtime(__DIR__ . '/assets/js/leagues.js') ?: 1 ?>" defer></script>

<?php endif; ?>

<?php tpl('footer'); ?>
