<?php
/** admin/section_x.php — لوحة النشر التلقائي على X (تويتر).
 *  حالة المفاتيح + خطة النشر + معاينات الفترات + نشر تجريبي + سجلّ النشر.
 */
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
$ar = (current_lang() === 'ar'); $L = fn($a, $e) => $ar ? $a : $e;

// ---------- إجراءات POST ----------
$notice = ''; $noticeOk = false; $previewText = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = (string)($_POST['do'] ?? '');

    if ($do === 'preview') {
        $slot = (string)($_POST['slot'] ?? 'manual');
        $previewText = TweetComposer::build($slot);
    }

    if ($do === 'sendnow') {
        $slot = (string)($_POST['slot'] ?? 'manual');
        $text = TweetComposer::build($slot);
        if (!XPublisher::configured()) {
            $notice = $L('المفاتيح غير مضبوطة — أضف X_API_KEY / X_API_SECRET / X_ACCESS_TOKEN / X_ACCESS_SECRET في config.local.php.',
                         'Keys not set — add X_API_KEY / X_API_SECRET / X_ACCESS_TOKEN / X_ACCESS_SECRET to config.local.php.');
        } else {
            $r = XPublisher::tweet($text);
            $noticeOk = (bool)$r['ok'];
            $notice = $r['ok']
                ? $L('تم النشر ✓ — معرّف التغريدة: ' . $r['id'], 'Tweet posted ✓ — ID: ' . $r['id'])
                : $L('فشل النشر — ' . (string)$r['error'], 'Tweet failed — ' . (string)$r['error']);
        }
        $previewText = $text;
    }

    if ($do === 'sendcustom') {
        $text = trim((string)($_POST['text'] ?? ''));
        if ($text === '') {
            $notice = $L('النص فارغ.', 'Text is empty.');
        } elseif (!XPublisher::configured()) {
            $notice = $L('المفاتيح غير مضبوطة.', 'Keys not set.');
        } else {
            $r = XPublisher::tweet($text);
            $noticeOk = (bool)$r['ok'];
            $notice = $r['ok']
                ? $L('تم النشر ✓ — معرّف التغريدة: ' . $r['id'], 'Tweet posted ✓ — ID: ' . $r['id'])
                : $L('فشل النشر — ' . (string)$r['error'], 'Tweet failed — ' . (string)$r['error']);
            $previewText = $text;
        }
    }
}

$configured = XPublisher::configured();
$logRows    = XPublisher::recentLog(20);
$plan       = TweetComposer::schedulePlan($ar);
$handle     = defined('X_HANDLE') ? X_HANDLE : 'wcup2026';
?>

<?php if ($notice !== ''): ?>
  <div class="admin-card" style="border-inline-start:4px solid <?= $noticeOk ? '#16a34a' : '#dc2626' ?>;background:<?= $noticeOk ? 'rgba(22,163,74,.08)' : 'rgba(220,38,38,.08)' ?>">
    <strong><?= e($notice) ?></strong>
  </div>
<?php endif; ?>

<!-- ============ حالة الربط ============ -->
<div class="admin-card">
  <h2><?= e($L('حالة الربط مع X', 'X connection status')) ?></h2>
  <div class="admin-check">
    <span class="admin-check-ico"><?= $configured ? '✅' : '⚠️' ?></span>
    <span><strong><?= e($L('النشر التلقائي', 'Auto-publishing')) ?></strong> —
      <span class="admin-muted"><?= e($configured
        ? $L('مفعّل (المفاتيح موجودة).', 'Enabled (keys present).')
        : $L('معطّل — أضف المفاتيح الأربعة في config.local.php.', 'Disabled — add the 4 keys to config.local.php.')) ?></span>
    </span>
    <span class="admin-badge <?= $configured ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= e($configured ? $L('مفعّل','On') : $L('معطّل','Off')) ?></span>
  </div>
  <?php if ($configured): ?>
  <div class="admin-table-wrap" style="margin-top:10px">
    <table class="admin-table">
      <tr><th><?= e($L('الحساب', 'Handle')) ?></th><td>
        <a href="https://x.com/<?= e($handle) ?>" target="_blank" rel="noopener">@<?= e($handle) ?></a>
      </td></tr>
      <tr><th><?= e($L('API Key', 'API Key')) ?></th><td>•••••••• (<?= (int)strlen((string)X_API_KEY) ?> <?= e($L('خانة','chars')) ?>)</td></tr>
      <tr><th><?= e($L('Access Token', 'Access Token')) ?></th><td>•••••••• (<?= (int)strlen((string)X_ACCESS_TOKEN) ?> <?= e($L('خانة','chars')) ?>)</td></tr>
      <tr><th><?= e($L('الوسوم', 'Hashtags')) ?></th><td><code><?= e(X_HASHTAGS) ?></code></td></tr>
    </table>
  </div>
  <?php else: ?>
  <div class="admin-table-wrap" style="margin-top:10px">
    <p class="admin-muted">
      <?= e($L('كيف تحصل على المفاتيح:', 'How to get the keys:')) ?>
    </p>
    <ol class="admin-muted" style="line-height:2;padding-inline-start:1.5em">
      <li><?= e($L('سجّل دخول إلى developer.x.com (مجاناً).', 'Sign in at developer.x.com (free).')) ?></li>
      <li><?= e($L('أنشئ مشروع → App → فعّل Read + Write.', 'Create Project → App → enable Read + Write.')) ?></li>
      <li><?= e($L('من Keys & tokens انسخ: API Key, API Secret, Access Token, Access Token Secret.',
                   'From Keys & tokens copy: API Key, API Secret, Access Token, Access Token Secret.')) ?></li>
      <li><?= e($L('الصقها في config.local.php على الخادم.',
                   'Paste them into config.local.php on the server.')) ?></li>
    </ol>
  </div>
  <?php endif; ?>
</div>

<!-- ============ خطّة النشر اليوميّة ============ -->
<div class="admin-card">
  <h2><?= e($L('خطّة النشر اليومية', 'Daily publishing schedule')) ?></h2>
  <p class="admin-muted">
    <?= e($L('التشغيل عبر Cron مرّة في الساعة. عند بلوغ ساعة الفترة يُنشَر تلقائياً — وضمان «مرّة واحدة في اليوم» لكل فترة.',
             'Runs via Cron once per hour. When the slot hour matches it posts automatically — guaranteed once-per-day per slot.')) ?>
  </p>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th style="width:90px"><?= e($L('الوقت', 'Time')) ?></th>
          <th><?= e($L('الفترة', 'Slot')) ?></th>
          <th><?= e($L('المحتوى', 'Content')) ?></th>
          <th><?= e($L('متى تُفعّل', 'When active')) ?></th>
          <th><?= e($L('معاينة', 'Preview')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plan as $row): ?>
          <tr>
            <td><strong><?= e($row['time']) ?></strong><br><span class="admin-muted"><?= e(DISPLAY_TIMEZONE) ?></span></td>
            <td><code><?= e($row['slot']) ?></code><br><?= e($row['title']) ?></td>
            <td><?= e($row['note']) ?></td>
            <td><span class="admin-muted"><?= e($row['when']) ?></span></td>
            <td>
              <form method="post" action="admin.php?tab=x" style="margin:0">
                <input type="hidden" name="tab" value="x">
                <input type="hidden" name="do" value="preview">
                <input type="hidden" name="slot" value="<?= e($row['slot']) ?>">
                <?= Admin::csrfField() ?>
                <button type="submit" class="admin-btn"><?= e($L('عرض النصّ','Show text')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="admin-muted" style="margin-top:10px">
    <?= e($L('سطر Cron المقترح (كل 15 دقيقة — لالتقاط نوافذ ما-قبل-المباراة بدقّة):',
             'Suggested Cron line (every 15 min — to catch pre-match windows accurately):')) ?>
    <br><code>*/15 * * * * php /home/USER/domains/wcup2026.org/public_html/cron/tweet.php</code>
  </p>
</div>

<!-- ============ تغريدات كل مباراة (قبل + بعد، AR+EN) ============ -->
<?php
$pre  = MatchTweets::pendingPre();
$post = MatchTweets::pendingPost();
$mLog = MatchTweets::recentLog(12);
?>
<div class="admin-card">
  <h2><?= e($L('تغريدات كل مباراة (تلقائي)', 'Per-match tweets (automatic)')) ?></h2>
  <p class="admin-muted">
    <?= e($L('قبل المباراة بـ 30–75 دقيقة → تغريدتان (عربيّ + إنجليزيّ). بعد المباراة → تقرير ذكاء + تغريدتان. كل تغريدة مرّة واحدة فقط.',
             'Between 30–75 min before kickoff → 2 tweets (AR + EN). After full time → AI report + 2 tweets. Each tweet posts exactly once.')) ?>
  </p>

  <h3 style="margin-top:14px"><?= e($L('في الطابور الآن', 'In the queue right now')) ?></h3>
  <?php if (!$pre && !$post): ?>
    <p class="admin-muted">
      <?= e($L('لا شيء في الطابور حالياً — لا مباراة في نافذة الـ75 دقيقة، ولا مباراة منتهية تنتظر التقرير.',
               'Queue empty — no match in the 75-min pre window, and no finished match waiting for its post tweet.')) ?>
    </p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('النوع', 'Type')) ?></th>
          <th><?= e($L('المباراة', 'Match')) ?></th>
          <th><?= e($L('اللغة', 'Lang')) ?></th>
          <th><?= e($L('الموعد', 'When')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($pre as $j):
            $m = $j['match']; $ts = DataService::matchTimestamp($m);
          ?>
          <tr>
            <td><span class="admin-badge admin-badge-warn"><?= e($L('قبل','PRE')) ?></span></td>
            <td><strong>#<?= (int)$m['_index'] ?></strong> · <?= e($m['team1'] . ' vs ' . $m['team2']) ?></td>
            <td><code><?= e($j['lang']) ?></code></td>
            <td class="admin-muted"><?= e($ts ? date('Y-m-d H:i', $ts) : '—') ?></td>
          </tr>
          <?php endforeach; foreach ($post as $j):
            $m = $j['match']; $ts = DataService::matchTimestamp($m);
            $g1 = (int)$m['score']['ft'][0]; $g2 = (int)$m['score']['ft'][1];
          ?>
          <tr>
            <td><span class="admin-badge admin-badge-ok"><?= e($L('بعد','POST')) ?></span></td>
            <td><strong>#<?= (int)$m['_index'] ?></strong> · <?= e($m['team1']) ?> <strong><?= $g1 ?>-<?= $g2 ?></strong> <?= e($m['team2']) ?></td>
            <td><code><?= e($j['lang']) ?></code></td>
            <td class="admin-muted"><?= e($ts ? date('Y-m-d H:i', $ts) : '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 style="margin-top:18px"><?= e($L('آخر تغريدات منشورة لكل مباراة', 'Recent per-match tweets')) ?></h3>
  <?php if (!$mLog): ?>
    <p class="admin-muted"><?= e($L('لا يوجد بعد.', 'None yet.')) ?></p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('الوقت', 'Time')) ?></th>
          <th><?= e($L('المباراة', 'Match #')) ?></th>
          <th><?= e($L('الفترة', 'Slot')) ?></th>
          <th><?= e($L('الرابط', 'Link')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($mLog as $r): ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', (int)$r['at'])) ?></td>
            <td>#<?= (int)$r['idx'] ?></td>
            <td><code><?= e($r['slot']) ?></code></td>
            <td><a href="https://x.com/<?= e($handle) ?>/status/<?= e($r['id']) ?>" target="_blank" rel="noopener">↗ X</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============ تغريدات ترتيب المجموعات (بعد كل جولة) ============ -->
<?php
$gPending = GroupTweets::pending();
$gLog     = GroupTweets::recentLog(12);
?>
<div class="admin-card">
  <h2><?= e($L('تغريدات ترتيب المجموعات (تلقائي)', 'Group standings tweets (automatic)')) ?></h2>
  <p class="admin-muted">
    <?= e($L('بعد كل جولة في مجموعة (كل المنتخبات لعبت نفس عدد المباريات) تُنشَر تغريدة ترتيب — عربيّ + إنجليزيّ.',
             'After each round in a group (all teams played the same number of games) a standings tweet is posted — AR + EN.')) ?>
  </p>

  <h3 style="margin-top:14px"><?= e($L('في الطابور', 'In queue')) ?></h3>
  <?php if (!$gPending): ?>
    <p class="admin-muted">
      <?= e($L('لا شيء في الطابور — لا توجد مجموعة أكملت جولة لم تُنشَر بعد.',
               'Queue empty — no group has completed an unpublished round.')) ?>
    </p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('المجموعة', 'Group')) ?></th>
          <th><?= e($L('المرحلة', 'Milestone')) ?></th>
          <th><?= e($L('اللغة', 'Lang')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($gPending as $j): ?>
          <tr>
            <td><strong><?= e($j['group']) ?></strong></td>
            <td><code><?= e($j['milestone']) ?></code></td>
            <td><code><?= e($j['lang']) ?></code></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 style="margin-top:18px"><?= e($L('آخر تغريدات منشورة', 'Recent group tweets')) ?></h3>
  <?php if (!$gLog): ?>
    <p class="admin-muted"><?= e($L('لا يوجد بعد.', 'None yet.')) ?></p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('الوقت', 'Time')) ?></th>
          <th><?= e($L('المجموعة', 'Group')) ?></th>
          <th><?= e($L('الفترة', 'Slot')) ?></th>
          <th><?= e($L('الرابط', 'Link')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($gLog as $r): ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', (int)$r['at'])) ?></td>
            <td><?= e($r['group']) ?></td>
            <td><code><?= e($r['slot']) ?></code></td>
            <td><a href="https://x.com/<?= e($handle) ?>/status/<?= e($r['id']) ?>" target="_blank" rel="noopener">↗ X</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($previewText !== ''): ?>
<!-- ============ معاينة النصّ ============ -->
<div class="admin-card">
  <h2><?= e($L('معاينة التغريدة', 'Tweet preview')) ?></h2>
  <pre style="white-space:pre-wrap;background:rgba(255,255,255,.04);padding:14px;border-radius:8px;line-height:1.7"><?= e($previewText) ?></pre>
  <p class="admin-muted"><?= e($L('عدد الأحرف:', 'Characters:')) ?> <strong><?= (int)mb_strlen($previewText, 'UTF-8') ?></strong> / 280</p>
</div>
<?php endif; ?>

<!-- ============ نشر تجريبي الآن ============ -->
<div class="admin-card">
  <h2><?= e($L('نشر فوري (للاختبار)', 'Post now (for testing)')) ?></h2>
  <p class="admin-muted"><?= e($L('يخطّط الفترة المختارة ويُرسلها لـ X — للتحقّق أن الربط يعمل.',
      'Composes the chosen slot and sends it to X — to verify the connection works.')) ?></p>
  <form method="post" action="admin.php?tab=x" class="admin-toolbar">
    <input type="hidden" name="tab" value="x">
    <input type="hidden" name="do" value="sendnow">
    <?= Admin::csrfField() ?>
    <div class="admin-field">
      <select name="slot" class="admin-input">
        <option value="manual"><?= e($L('افتراضية (تعريف الموقع)','Default (site intro)')) ?></option>
        <option value="countdown"><?= e($L('عدّ تنازلي','Countdown')) ?></option>
        <option value="morning"><?= e($L('تنبيه الصباح','Morning preview')) ?></option>
        <option value="evening"><?= e($L('ملخّص المساء','Evening recap')) ?></option>
      </select>
    </div>
    <button type="submit" class="admin-btn admin-btn-primary" <?= $configured ? '' : 'disabled' ?>>
      <?= e($L('انشر الآن','Post now')) ?>
    </button>
  </form>

  <h3 style="margin-top:20px"><?= e($L('أو تغريدة بنصّ مخصّص','Or a custom tweet')) ?></h3>
  <form method="post" action="admin.php?tab=x">
    <input type="hidden" name="tab" value="x">
    <input type="hidden" name="do" value="sendcustom">
    <?= Admin::csrfField() ?>
    <textarea name="text" class="admin-input" rows="4" maxlength="280" style="width:100%;font-family:inherit"
              placeholder="<?= e($L('اكتب تغريدتك هنا (280 حرفاً كحدّ أقصى)…','Write your tweet here (max 280 chars)…')) ?>"></textarea>
    <div style="margin-top:10px">
      <button type="submit" class="admin-btn admin-btn-primary" <?= $configured ? '' : 'disabled' ?>>
        <?= e($L('نشر النصّ المخصّص','Post custom text')) ?>
      </button>
    </div>
  </form>
</div>

<!-- ============ سجلّ النشر ============ -->
<div class="admin-card">
  <h2><?= e($L('سجلّ النشر على X', 'X publishing log')) ?></h2>
  <?php if (!$logRows): ?>
    <p class="admin-muted"><?= e($L('لا يوجد نشر بعد.', 'No tweets published yet.')) ?></p>
  <?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?= e($L('الوقت', 'Time')) ?></th>
          <th><?= e($L('الحالة', 'Status')) ?></th>
          <th><?= e($L('النصّ', 'Text')) ?></th>
          <th><?= e($L('الرابط/الخطأ', 'Link/Error')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logRows as $r):
          $ok = !empty($r['ok']);
          $cls = $ok ? 'admin-badge-ok' : 'admin-badge-bad';
          $txt = $ok ? $L('نُشر','Posted') : $L('فشل','Failed');
        ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', (int)($r['t'] ?? 0))) ?></td>
            <td><span class="admin-badge <?= $cls ?>"><?= e($txt) ?></span></td>
            <td style="max-width:380px"><span class="admin-muted"><?= e(mb_substr((string)($r['text'] ?? ''), 0, 120, 'UTF-8')) ?></span></td>
            <td>
              <?php if ($ok && !empty($r['id'])): ?>
                <a href="https://x.com/<?= e($handle) ?>/status/<?= e((string)$r['id']) ?>" target="_blank" rel="noopener">↗ X</a>
              <?php else: ?>
                <code class="admin-muted"><?= e((string)($r['error'] ?? '—')) ?></code>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
