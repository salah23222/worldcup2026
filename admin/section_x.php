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

    if ($do === 'pause') {
        $hours = max(1, min(168, (int)($_POST['hours'] ?? 24)));
        RateGuard::pauseFor($hours);
        $notice = $L("تم إيقاف النشر التلقائي لمدّة {$hours} ساعة.", "Auto-publishing paused for {$hours} hours.");
        $noticeOk = true;
    }

    if ($do === 'resume') {
        RateGuard::resume();
        $notice = $L('تم استئناف النشر التلقائي.', 'Auto-publishing resumed.');
        $noticeOk = true;
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

<!-- ============ 🛡️ حماية الحساب (RateGuard) ============ -->
<?php
$g = RateGuard::stats();
$hPct = $g['hourly_cap'] > 0 ? min(100, (int)round(100 * $g['hourly_used'] / $g['hourly_cap'])) : 0;
$dPct = $g['daily_cap']  > 0 ? min(100, (int)round(100 * $g['daily_used']  / $g['daily_cap']))  : 0;
$hColor = $hPct >= 90 ? '#dc2626' : ($hPct >= 70 ? '#f59e0b' : '#16a34a');
$dColor = $dPct >= 90 ? '#dc2626' : ($dPct >= 70 ? '#f59e0b' : '#16a34a');
?>
<div class="admin-card" style="border-inline-start:4px solid <?= $g['paused'] ? '#dc2626' : '#16a34a' ?>">
  <h2>🛡️ <?= e($L('حماية الحساب من الإيقاف', 'Account-safety guard')) ?></h2>

  <?php if ($g['paused']): ?>
    <div class="admin-check" style="background:rgba(220,38,38,.1);padding:12px;border-radius:10px;margin-bottom:14px">
      <span class="admin-check-ico">⏸️</span>
      <span><strong><?= e($L('النشر التلقائي موقوف', 'Auto-publishing PAUSED')) ?></strong>
        — <span class="admin-muted"><?= e($L('حتى', 'until')) ?> <?= e(date('Y-m-d H:i', $g['pause_until'])) ?>
          (<?= e($L('بعد', 'in')) ?> <?= e(human_remaining($g['pause_until'] - time())) ?>)</span></span>
    </div>
  <?php else: ?>
    <div class="admin-check" style="background:rgba(22,163,74,.08);padding:12px;border-radius:10px;margin-bottom:14px">
      <span class="admin-check-ico">✅</span>
      <span><strong><?= e($L('الحساب آمن — النشر فعّال', 'Account safe — publishing active')) ?></strong>
        <span class="admin-muted">· <?= e($L('فاصل أدنى','min spacing')) ?>: <?= (int)$g['min_spacing'] ?>s</span>
      </span>
    </div>
  <?php endif; ?>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <tr>
        <th style="width:30%"><?= e($L('في آخر ساعة', 'Last hour')) ?></th>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <strong style="color:<?= $hColor ?>"><?= (int)$g['hourly_used'] ?> / <?= (int)$g['hourly_cap'] ?></strong>
            <div style="flex:1;height:8px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden">
              <div style="width:<?= $hPct ?>%;height:100%;background:<?= $hColor ?>;transition:width .3s"></div>
            </div>
            <span class="admin-muted"><?= $hPct ?>%</span>
          </div>
        </td>
      </tr>
      <tr>
        <th><?= e($L('في آخر 24 ساعة', 'Last 24h')) ?></th>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <strong style="color:<?= $dColor ?>"><?= (int)$g['daily_used'] ?> / <?= (int)$g['daily_cap'] ?></strong>
            <div style="flex:1;height:8px;background:rgba(255,255,255,.08);border-radius:4px;overflow:hidden">
              <div style="width:<?= $dPct ?>%;height:100%;background:<?= $dColor ?>;transition:width .3s"></div>
            </div>
            <span class="admin-muted"><?= $dPct ?>%</span>
          </div>
        </td>
      </tr>
      <?php if ((int)$g['fails_streak'] > 0): ?>
      <tr>
        <th><?= e($L('فشل متتالٍ', 'Consecutive failures')) ?></th>
        <td><strong style="color:#f59e0b"><?= (int)$g['fails_streak'] ?> / 3</strong>
          <span class="admin-muted">(<?= e($L('عند 3 → إيقاف 24 ساعة تلقائياً', 'at 3 → auto-pause 24h')) ?>)</span></td>
      </tr>
      <?php endif; ?>
      <?php if (!empty($g['last_error'])): ?>
      <tr>
        <th><?= e($L('آخر خطأ', 'Last error')) ?></th>
        <td><span class="admin-muted"><?= e(date('Y-m-d H:i', (int)$g['last_error']['ts'])) ?> · </span>
          <code><?= e((string)($g['last_error']['err'] ?? '—')) ?></code></td>
      </tr>
      <?php endif; ?>
    </table>
  </div>

  <div class="admin-toolbar" style="margin-top:14px;gap:8px">
    <?php if ($g['paused']): ?>
      <form method="post" action="admin.php?tab=x" style="display:inline">
        <input type="hidden" name="tab" value="x">
        <input type="hidden" name="do" value="resume">
        <?= Admin::csrfField() ?>
        <button type="submit" class="admin-btn admin-btn-primary"><?= e($L('▶ استئناف الآن', '▶ Resume now')) ?></button>
      </form>
    <?php else: ?>
      <form method="post" action="admin.php?tab=x" style="display:inline-flex;gap:6px;align-items:center">
        <input type="hidden" name="tab" value="x">
        <input type="hidden" name="do" value="pause">
        <?= Admin::csrfField() ?>
        <select name="hours" class="admin-input">
          <option value="1">1 <?= e($L('ساعة','hour')) ?></option>
          <option value="6">6 <?= e($L('ساعات','hours')) ?></option>
          <option value="24" selected>24 <?= e($L('ساعة','hours')) ?></option>
          <option value="72">72 <?= e($L('ساعة','hours')) ?></option>
          <option value="168">7 <?= e($L('أيام','days')) ?></option>
        </select>
        <button type="submit" class="admin-btn"><?= e($L('⏸ إيقاف يدوي', '⏸ Pause manually')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <p class="admin-muted" style="margin-top:12px;font-size:.85em">
    <?= e($L('💡 الحدود الافتراضية محافظة جداً (8/ساعة · 30/يوم · 60ث فاصل). الحارس يكشف 429/403 تلقائياً ويوقف ساعة. الفشل المتراكم 3 مرّات يوقف يوماً كاملاً — كل ذلك لحماية حسابك من الإيقاف.',
             '💡 Defaults are conservative (8/h · 30/day · 60s spacing). Guard auto-pauses on 429/403 for 1 hour. 3 consecutive failures trigger 24h pause — all to keep your account safe.')) ?>
  </p>
</div>

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

  <!-- زر إفراغ الطابور الكامل (يتجاوز سقف الـ run، لكن RateGuard ساعة/يوم يظلّان فعّالَين) -->
  <?php
    $drainUrl = rtrim(SITE_URL, '/') . '/cron/tweet.php?token=' . urlencode(INSTALL_TOKEN) . '&drain=1';
  ?>
  <div style="margin-top:16px;padding:14px;background:rgba(255,194,51,.07);border:1px solid rgba(255,194,51,.25);border-radius:10px">
    <strong>⚡ <?= e($L('إفراغ الطابور بالكامل', 'Drain queue now')) ?></strong>
    <p class="admin-muted" style="margin:6px 0 10px">
      <?= e($L('ينشر كل التغريدات المعلّقة (يومية + أخبار + مباريات) دفعة واحدة، مع احترام فاصل 17ث بين كل تغريدة وسقفَي حماية الحساب (12/ساعة · 50/يوم).',
               'Publishes every pending tweet (daily + news + matches) in one go, respecting 17s spacing and account-safety caps (12/h · 50/day).')) ?>
    </p>
    <a class="admin-btn admin-btn-primary" href="<?= e($drainUrl) ?>" target="_blank" rel="noopener">
      🚀 <?= e($L('افتح صفحة الإفراغ في تبويب جديد', 'Open drain page in new tab')) ?>
    </a>
    <p class="admin-muted" style="font-size:.85em;margin-top:8px">
      <?= e($L('سيستغرق ~85ث لكل 6 تغريدات. حافظ على التبويب مفتوحاً حتى يكتمل.',
               '~85s per 6 tweets. Keep the tab open until done.')) ?>
    </p>
  </div>
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

<!-- ============ تغريدات الأخبار (تلقائي عند جلب أخبار جديدة) ============ -->
<?php
$nPending = NewsTweets::pending();
$nLog     = NewsTweets::recentLog(12);
$nWindow  = NewsTweets::inWindow();
?>
<div class="admin-card">
  <h2><?= e($L('تغريدات الأخبار (تلقائي)', 'News tweets (automatic)')) ?></h2>
  <p class="admin-muted">
    <?= e($L('حين يجلب الموقع أيّ خبر جديد، يُنشَر تغريدة تلقائياً (حتى 2 خبر لكل run · 1 عربي + 1 إنجليزي). الرابط يقود إلى /news.php — التراكتيك يبقى على موقعك.',
             'When a new news item appears, it is auto-tweeted (up to 2 per run · 1 AR + 1 EN). Link points to /news.php — traffic stays on your site.')) ?>
  </p>
  <p class="admin-muted">
    <?= e($L('نافذة النشر: ', 'Publish window: ')) ?>
    <strong>08:00–23:00 (<?= e(DISPLAY_TIMEZONE) ?>)</strong> ·
    <?= e($L('الآن:', 'Now:')) ?>
    <span class="admin-badge <?= $nWindow ? 'admin-badge-ok' : 'admin-badge-warn' ?>">
      <?= e($nWindow ? $L('داخل النافذة','In window') : $L('خارج النافذة','Outside')) ?>
    </span>
  </p>

  <h3 style="margin-top:14px"><?= e($L('في الطابور', 'Queue')) ?></h3>
  <?php if (!$nPending): ?>
    <p class="admin-muted">
      <?= e($L('لا أخبار جديدة بانتظار النشر.', 'No fresh news waiting.')) ?>
    </p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('اللغة','Lang')) ?></th>
          <th><?= e($L('العنوان','Title')) ?></th>
          <th><?= e($L('المصدر','Source')) ?></th>
          <th><?= e($L('النشر','Published')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach (array_slice($nPending, 0, 8) as $j):
            $it = $j['item'];
          ?>
          <tr>
            <td><code><?= e($j['lang']) ?></code></td>
            <td style="max-width:380px"><?= e(mb_substr((string)($it['title'] ?? ''), 0, 110, 'UTF-8')) ?></td>
            <td><span class="admin-muted"><?= e((string)($it['source'] ?? ($it['host'] ?? ''))) ?></span></td>
            <td class="admin-muted"><?= e($j['ts'] > 0 ? date('Y-m-d H:i', $j['ts']) : '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3 style="margin-top:18px"><?= e($L('آخر أخبار منشورة','Recently tweeted news')) ?></h3>
  <?php if (!$nLog): ?>
    <p class="admin-muted"><?= e($L('لا يوجد بعد.','None yet.')) ?></p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr>
          <th><?= e($L('الوقت','Time')) ?></th>
          <th><?= e($L('اللغة','Lang')) ?></th>
          <th><?= e($L('العنوان','Title')) ?></th>
          <th><?= e($L('الرابط','Link')) ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($nLog as $r): ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', (int)$r['at'])) ?></td>
            <td><code><?= e($r['lang']) ?></code></td>
            <td style="max-width:380px"><span class="admin-muted"><?= e(mb_substr((string)$r['title'], 0, 100, 'UTF-8')) ?></span></td>
            <td>
              <?php if (!empty($r['tweet'])): ?>
                <a href="https://x.com/<?= e($handle) ?>/status/<?= e($r['tweet']) ?>" target="_blank" rel="noopener">↗ X</a>
              <?php else: ?>—<?php endif; ?>
            </td>
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
        <option value="recap"><?= e($L('صباح الكرة (نتائج الليل)','Morning recap')) ?></option>
        <option value="morning"><?= e($L('مباريات اليوم','Today\'s matches')) ?></option>
        <option value="trivia"><?= e($L('سؤال اليوم (تحدّي المعرفة)','Daily trivia')) ?></option>
        <option value="stats"><?= e($L('إحصائيات اليوم','Daily stats')) ?></option>
        <option value="evening"><?= e($L('نتائج المساء','Evening results')) ?></option>
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
