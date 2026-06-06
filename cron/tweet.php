<?php
/**
 * cron/tweet.php — النشر التلقائي على X (تويتر) — يُشغَّل عبر Cron.
 * ============================================================
 * شغّله مرّة في الساعة. السكربت يقرّر بنفسه:
 *   - هل هذه ساعة فترة معروفة (morning/evening/countdown)؟
 *   - هل نُشرت اليوم؟ (claimSlot يمنع التكرار)
 *   - هل المفاتيح موجودة؟
 * إن تحقّقت الشروط: يبني النصّ وينشر، ويسجّل النتيجة.
 *
 * التشغيل (Hostinger Cron) — مرّة في الساعة على رأس الساعة:
 *   0 * * * * php /home/USER/domains/wcup2026.org/public_html/cron/tweet.php
 *
 * خيارات (CLI):
 *   --slot=morning|evening|countdown|manual   (تجاوز الفترة الحالية)
 *   --force                                   (تجاوز bot bucket «مرّة في اليوم»)
 *   --dry-run                                 (يطبع النصّ فقط، لا ينشر)
 * عبر المتصفّح (إن لزم):  cron/tweet.php?token=INSTALL_TOKEN
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

// --- قراءة الخيارات (CLI أو متصفّح بـtoken) ---
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

$dry   = isset($args['dry-run']) || isset($args['dry']);
$force = isset($args['force']);
$slot  = (string)($args['slot'] ?? '');
$skipDaily   = isset($args['no-daily']);
$skipMatches = isset($args['no-matches']);

$log = function (string $m) { echo $m . "\n"; };

// ---- 0) الحارس: المفاتيح ----
if (!XPublisher::configured()) {
    $log('[tweet] X keys not configured. exit.');
    exit(0);
}

$sent = 0; $failed = 0;

// ═══════════════════ A) الفترات اليوميّة (09:00/10:00/21:00/22:00) — AR + EN ═══════════════════
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
            if ($dry) {
                $log('[daily] dry-run:'); $log('---'); $log($text); $log('---');
                continue;
            }
            $r = XPublisher::tweet($text);
            if ($r['ok']) { $log('[daily] OK id=' . $r['id']); $sent++; }
            else          { $log('[daily] FAIL ' . $r['error']); $failed++; }
        }
    }
}

// ═══════════════════ B) قَبل المباراة (AR + EN لكل مباراة قادمة) ═══════════════════
if (!$skipMatches) {
    $pre = MatchTweets::pendingPre();
    $log('[pre]  candidates=' . count($pre));
    foreach ($pre as $job) {
        if ($sent >= MatchTweets::MAX_PER_RUN) { $log('[pre] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) {
            $log('[pre] would tweet ' . $label);
            $log('---'); $log(MatchTweets::buildPre($m, $lg)); $log('---');
            continue;
        }
        $r = MatchTweets::sendPre($m, $lg);
        if ($r['ok']) { $log('[pre] OK ' . $label . ' id=' . $r['id']); $sent++; }
        else          { $log('[pre] FAIL ' . $label . ' ' . $r['error']); $failed++; }
    }
}

// ═══════════════════ C) بعد المباراة (تقرير AI + تغريدة AR + EN) ═══════════════════
if (!$skipMatches) {
    $post = MatchTweets::pendingPost();
    $log('[post] candidates=' . count($post));
    foreach ($post as $job) {
        if ($sent >= MatchTweets::MAX_PER_RUN) { $log('[post] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) {
            $log('[post] would tweet ' . $label);
            $log('---'); $log(MatchTweets::buildPost($m, $lg)); $log('---');
            continue;
        }
        $r = MatchTweets::sendPost($m, $lg);
        if ($r['ok']) { $log('[post] OK ' . $label . ' id=' . $r['id']); $sent++; }
        else          { $log('[post] FAIL ' . $label . ' ' . $r['error']); $failed++; }
    }
}

// ═══════════════════ D) ترتيب المجموعات بعد كل جولة (AR + EN) ═══════════════════
if (!$skipMatches) {
    $gq = GroupTweets::pending();
    $log('[group] candidates=' . count($gq));
    foreach ($gq as $job) {
        if ($sent >= MatchTweets::MAX_PER_RUN) { $log('[group] cap reached, stop.'); break; }
        $label = $job['group'] . ' · ' . $job['milestone'] . ' [' . $job['lang'] . ']';
        if ($dry) {
            $log('[group] would tweet ' . $label);
            $log('---'); $log(GroupTweets::buildStandings($job['group'], $job['milestone'], $job['lang'])); $log('---');
            continue;
        }
        $r = GroupTweets::sendStandings($job['group'], $job['milestone'], $job['lang']);
        if ($r['ok']) { $log('[group] OK ' . $label . ' id=' . $r['id']); $sent++; }
        else          { $log('[group] FAIL ' . $label . ' ' . $r['error']); $failed++; }
    }
}

$log("[done] sent={$sent} failed={$failed}");
exit($failed > 0 ? 1 : 0);
