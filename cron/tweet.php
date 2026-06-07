<?php
/**
 * cron/tweet.php — النشر التلقائي على X (تويتر) — يُشغَّل عبر Cron كل 15 دقيقة.
 * ============================================================
 *   A) فترات يوميّة (morning/countdown/trivia/evening/stats) — AR + EN
 *   B) قبل المباراة بـ 30–75 دقيقة                            — AR + EN
 *   C) بعد المباراة + تقرير AI                                 — AR + EN
 *   D) ترتيب المجموعات بعد كل جولة                              — AR + EN
 *   E) أخبار جديدة من /news.php                                — AR + EN
 *
 * بين كل تغريدتَين: نوم X_MIN_SPACING+2 ثانية (افتراضي 17ث) لاحترام الفاصل.
 * تشغيل (Hostinger):
 *   *​/15 * * * *  /usr/bin/php /home/USER/domains/wcup2026.org/public_html/cron/tweet.php
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }
@set_time_limit(0);

// --- خيارات (CLI أو متصفّح بـtoken) ---
$cli = (PHP_SAPI === 'cli');
$args = [];
if ($cli) {
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--([^=]+)(?:=(.*))?$/', $a, $mm)) $args[$mm[1]] = $mm[2] ?? '1';
    }
} else {
    $tok = (string)($_GET['token'] ?? '');
    if (!defined('INSTALL_TOKEN') || INSTALL_TOKEN === '' || !hash_equals(INSTALL_TOKEN, $tok)) {
        http_response_code(403); exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
    $args = $_GET;
}

$dry         = isset($args['dry-run']) || isset($args['dry']);
$force       = isset($args['force']);
$slot        = (string)($args['slot'] ?? '');
$skipDaily   = isset($args['no-daily']);
$skipMatches = isset($args['no-matches']);
// drain = أفرغ الطابور (يرفع MAX_PER_RUN — لكن RateGuard ساعة/يوم يظلّان فعّالَين)
$drain       = isset($args['drain']);
$capPerRun   = $drain ? 999 : (defined('MAX_PER_RUN') ? MAX_PER_RUN : MatchTweets::MAX_PER_RUN);
$capNews     = $drain ? 999 : NewsTweets::MAX_PER_RUN;

$log  = function (string $m) { echo $m . "\n"; @flush(); };
$pace = max(1, (defined('X_MIN_SPACING') ? (int)X_MIN_SPACING : 15) + 2);

// بصمة نسخة الكود — تساعد على التحقّق من أن النسخة الصحيحة فعلاً تُشغَّل
$log('[version] tweet.php v2.3-test-schedule (' . date('Y-m-d H:i:s') . ' Asia/Dubai)');

// ═══════════════════ وضع الاختبار: معاينة كل الجدول دفعة واحدة ═══════════════════
// استخدام: cron/tweet.php?token=...&test-schedule=1
// يعرض كل التغريدات الست × لغتَين = 12 معاينة بدون أي نشر، ثم يخرج.
if (isset($args['test-schedule'])) {
    $log('');
    $log('╔══════════════════════════════════════════════════════════════╗');
    $log('║  اختبار شامل لجدول النشر — 6 فترات × 2 لغة = 12 تغريدة      ║');
    $log('╚══════════════════════════════════════════════════════════════╝');
    $log('');

    $plan = TweetComposer::schedulePlan(true);
    foreach ($plan as $row) {
        $log('');
        $log('╔══════════════════════════════════════════════════════════════');
        $log('║  ⏰ ' . $row['time'] . '  ·  ' . strtoupper($row['slot']) . '  ·  ' . $row['title']);
        $log('║  📋 ' . $row['note']);
        $log('║  📅 ' . $row['when']);
        $log('╚══════════════════════════════════════════════════════════════');

        foreach (['ar', 'en'] as $lang) {
            $text = TweetComposer::build($row['slot'], $lang);
            $chars = mb_strlen($text, 'UTF-8');
            $status = $chars <= 280 ? '✓' : '⚠️ تجاوز 280!';
            $log('');
            $log('─── [' . $lang . '] · ' . $chars . ' حرف ' . $status . ' ───');
            $log($text);
        }
        $log('');
    }

    $log('');
    $log('═════════════════════════ النتيجة ═════════════════════════');
    $log('  ✅ كل المعاينات أُنجزت — لم تُنشَر أي تغريدة فعلاً');
    $log('  📊 المجموع: 12 تغريدة (6 فترات × عربيّ وإنجليزيّ)');
    $log('  🕒 المُولِّد: ' . date('Y-m-d H:i:s') . ' Asia/Dubai');
    $log('═══════════════════════════════════════════════════════════');
    exit(0);
}

// (0) الحارس: المفاتيح
if (!XPublisher::configured()) { $log('[tweet] X keys not configured. exit.'); exit(0); }

// (0.5) RateGuard: لو الحساب موقوف → خروج فوري
$gStats = RateGuard::stats();
if ($gStats['paused']) {
    $log('[guard] account paused until ' . date('Y-m-d H:i:s', $gStats['pause_until'])
       . ' (fails=' . $gStats['fails_streak'] . '). exit.');
    exit(0);
}
$log('[guard] hourly=' . $gStats['hourly_used'] . '/' . $gStats['hourly_cap']
   . ' daily=' . $gStats['daily_used'] . '/' . $gStats['daily_cap']
   . ' spacing=' . $pace . 's');

$sent = 0; $failed = 0;

/**
 * Helper موحَّد للنشر مع احترام الفاصل الأدنى.
 *   - قبل كل محاولة: يفحص RateGuard مسبقاً.
 *     • لو spacing ≤ 30s: ينام بقدر wait+1 ثم يحاول (ضمان نجاح).
 *     • لو hourly/daily cap أو موقوف: يطبع BLOCKED ويخرج فوراً (تجنّب الفشل المتراكم).
 *   - يحدّث $sent/$failed ويطبع نتيجة كل محاولة.
 *
 * هذا يُصلح حالة «أوّل تغريدة فشلت → $sent ظلّ 0 → لا نوم → الكل يفشل».
 */
$blocked = false;   // عند رفعها = توقّف عن المحاولة لبقيّة الـ run
$send = function (string $tag, string $label, callable $sender) use (&$sent, &$failed, &$blocked, $log, $dry, $pace) {
    if ($dry) {
        $log("[{$tag}] would tweet {$label}");
        return ['ok' => false, 'skipped' => 'dry'];
    }
    if ($blocked) {
        $log("[{$tag}] SKIP {$label} (guard blocked earlier in this run)");
        return ['ok' => false, 'error' => 'guard_blocked'];
    }
    // فحص استباقي للحارس
    $g = RateGuard::check();
    if (!$g['ok']) {
        if ($g['reason'] === 'spacing' && (int)$g['wait'] <= max(30, $pace + 5)) {
            $sleepFor = max(1, (int)$g['wait'] + 1);
            $log("[{$tag}] wait {$sleepFor}s (spacing) before {$label}");
            sleep($sleepFor);
        } else {
            // hourly_cap / daily_cap / paused → لا فائدة من المحاولة
            $log("[{$tag}] BLOCKED {$label} guard:" . $g['reason'] . " wait=" . $g['wait'] . 's');
            $blocked = true;
            $failed++;
            return ['ok' => false, 'error' => 'guard:' . $g['reason']];
        }
    }
    $r = $sender();
    if (!empty($r['ok'])) {
        $log("[{$tag}] OK {$label} id=" . (string)$r['id']);
        $sent++;
    } else {
        $log("[{$tag}] FAIL {$label} " . (string)($r['error'] ?? '?'));
        $failed++;
        // لو الفشل من الحارس نفسه → ارفع علم blocked لتفادي 24 محاولة فاشلة
        if (!empty($r['error']) && strpos((string)$r['error'], 'rate_guard:') === 0) {
            $blocked = true;
        }
    }
    return $r;
};

// ═══════════════════ A) الفترات اليوميّة (AR + EN) ═══════════════════
if (!$skipDaily) {
    $dailySlot = $slot !== '' ? $slot : (TweetComposer::currentSlot() ?? '');
    if ($dailySlot === '') {
        $log('[daily] no slot active at ' . date('H:i') . '.');
    } else {
        foreach (['ar', 'en'] as $lg) {
            $slotKey = $dailySlot . '_' . $lg;
            if (!$force && !XPublisher::claimSlot($slotKey)) {
                $log('[daily] ' . $slotKey . ' already posted today.');
                continue;
            }
            $text = TweetComposer::build($dailySlot, $lg);
            $log('[daily] slot=' . $slotKey . ' chars=' . mb_strlen($text, 'UTF-8'));
            if ($dry) { $log('[daily] dry-run:'); $log('---'); $log($text); $log('---'); continue; }
            $send('daily', $slotKey, fn() => XPublisher::tweet($text));
        }
    }
}

// ═══════════════════ B) قَبل المباراة ═══════════════════
if (!$skipMatches) {
    $pre = MatchTweets::pendingPre();
    $log('[pre]  candidates=' . count($pre));
    foreach ($pre as $job) {
        if ($sent >= $capPerRun) { $log('[pre] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) { $log('[pre] would tweet ' . $label); $log('---'); $log(MatchTweets::buildPre($m, $lg)); $log('---'); continue; }
        $send('pre', $label, fn() => MatchTweets::sendPre($m, $lg));
    }
}

// ═══════════════════ C) بعد المباراة + تقرير AI ═══════════════════
if (!$skipMatches) {
    $post = MatchTweets::pendingPost();
    $log('[post] candidates=' . count($post));
    foreach ($post as $job) {
        if ($sent >= $capPerRun) { $log('[post] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) { $log('[post] would tweet ' . $label); $log('---'); $log(MatchTweets::buildPost($m, $lg)); $log('---'); continue; }
        $send('post', $label, fn() => MatchTweets::sendPost($m, $lg));
    }
}

// ═══════════════════ D) ترتيب المجموعات ═══════════════════
if (!$skipMatches) {
    $gq = GroupTweets::pending();
    $log('[group] candidates=' . count($gq));
    foreach ($gq as $job) {
        if ($sent >= $capPerRun) { $log('[group] cap reached, stop.'); break; }
        $label = $job['group'] . ' · ' . $job['milestone'] . ' [' . $job['lang'] . ']';
        if ($dry) { $log('[group] would tweet ' . $label); $log('---'); $log(GroupTweets::buildStandings($job['group'], $job['milestone'], $job['lang'])); $log('---'); continue; }
        $send('group', $label, fn() => GroupTweets::sendStandings($job['group'], $job['milestone'], $job['lang']));
    }
}

// ═══════════════════ E) أخبار جديدة ═══════════════════
if (!$skipMatches) {
    if (!NewsTweets::inWindow()) {
        $log('[news] outside publish window (08:00–23:00) — skip.');
    } else {
        $nq = NewsTweets::pending();
        $log('[news] candidates=' . count($nq));
        $nsent = 0;
        foreach ($nq as $job) {
            if ($nsent >= $capNews) break;
            $label = '[' . $job['lang'] . '] ' . mb_substr($job['item']['title'] ?? '', 0, 60, 'UTF-8');
            if ($dry) {
                $log('[news] would tweet ' . $label);
                $log('---'); $log(NewsTweets::buildTweet($job['item'], $job['lang'])); $log('---');
                $nsent++;
                continue;
            }
            $r = $send('news', $label, fn() => NewsTweets::sendOne($job['item'], $job['lang'], $job['id']));
            if (!empty($r['ok'])) $nsent++;
        }
    }
}

$log("[done] sent={$sent} failed={$failed}");
exit($failed > 0 ? 1 : 0);
