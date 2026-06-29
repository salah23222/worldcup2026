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

// ── قفل تشغيل: يمنع تداخل تشغيلَين (مهمّ مع كرون كل دقيقتين) → لا تكرار لنفس البطاقة.
//    لو تشغيل آخر يحمل القفل الآن، اخرج فوراً بهدوء. (يُحرَّر تلقائياً عند انتهاء السكربت.)
$lockFp = @fopen(rtrim(CACHE_DIR, '/') . '/cron-tweet.lock', 'c');
if ($lockFp && !flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "locked: another run in progress — skipping (prevents duplicate posts)\n";
    exit;
}

$log  = function (string $m) { echo $m . "\n"; @flush(); };
$pace = max(1, (defined('X_MIN_SPACING') ? (int)X_MIN_SPACING : 15) + 2);

// بصمة نسخة الكود — تساعد على التحقّق من أن النسخة الصحيحة فعلاً تُشغَّل
$log('[version] tweet.php v2.3-test-schedule (' . date('Y-m-d H:i:s') . ' Asia/Dubai)');

// ═══ مُشغّل يدويّ: انشر تغريدة مباراة محدّدة فوراً (للتجربة بعد النشر على الإنتاج) ═══
//   ?token=...&matchtweet=ID        → ينشر (نتيجة إن انتهت، وإلّا قَبليّة) + الرابط في ردّ
//   أضِف &dry=1 للمعاينة بلا نشر · &force لتكرار تغريدة سبق نشرها · يتجاوز السقف اليومي
if (($mt = (int)($args['matchtweet'] ?? 0)) > 0 || isset($args['matchtweet'])) {
    $target = null;
    foreach (DataService::allMatches() as $mm) { if ((int)($mm['_index'] ?? -1) === $mt) { $target = $mm; break; } }
    if (!$target) { $log("[matchtweet] match #$mt not found"); exit; }
    // &poll → انشر استطلاع «من سيفوز؟» لهذه المباراة
    if (isset($args['poll'])) {
        if (!$force && MatchTweets::wasSent($mt, 'poll', 'ar')) { $log("[matchtweet] #$mt poll already posted — add &force"); exit; }
        if ($dry) { $p = MatchTweets::buildPoll($target); $log('---'); $log($p['text']); $log('[options] ' . implode('  |  ', $p['options']) . '  (' . $p['minutes'] . 'min)'); $log('---'); exit; }
        $r = MatchTweets::sendPoll($target, true);
        $log(!empty($r['ok']) ? "[matchtweet] POLL OK #$mt id=" . (string)$r['id'] : "[matchtweet] POLL FAIL #$mt " . (string)($r['error'] ?? '?'));
        exit;
    }
    $fin  = isset($target['score']['ft']) && is_array($target['score']['ft']);
    $type = $fin ? 'post' : 'pre'; $lg2 = $fin ? 'bi' : 'ar';
    $lbl  = '#' . $mt . ' ' . ($target['team1'] ?? '') . '-' . ($target['team2'] ?? '') . ' [' . $type . ']';
    if (!$force && MatchTweets::wasSent($mt, $type, $lg2)) {
        $log("[matchtweet] $lbl already posted — add &force to repeat"); exit;
    }
    if ($dry) {
        $log("[matchtweet] DRY $lbl");
        $log('---'); $log($fin ? MatchTweets::buildPost($target, 'bi', false) : MatchTweets::buildPre($target, 'ar', false)); $log('---');
        exit;
    }
    $r = $fin ? MatchTweets::sendPost($target, 'bi') : MatchTweets::sendPre($target, 'bi', true);
    $log(!empty($r['ok']) ? "[matchtweet] OK $lbl id=" . (string)$r['id'] : "[matchtweet] FAIL $lbl " . (string)($r['error'] ?? '?'));
    exit;
}

// ═══ بطاقة «المتأهّلون إلى دور الـ32»: بنّاء + ناشر (يدويّ &r32 · وتلقائيّ عند تأهّل جديد) ═══
// لا تُنشَر إلا إذا زاد عدد المتأهّلين المضمونين رياضياً (Standings::qualifiedR32) عن آخر مرّة.
$r32Tweet = function (bool $force, bool $priority) use ($log, $dry) {
    if (!class_exists('Standings') || !class_exists('TweetCardImage')) return ['ok' => false, 'error' => 'no_class'];
    $q = Standings::qualifiedR32();
    $teams = $q['teams']; $cnt = count($teams);
    if ($cnt < 1) { $log('[r32] none clinched yet — skip'); return ['ok' => false, 'error' => 'none']; }
    $stateF = rtrim(CACHE_DIR, '/') . '/x_r32.json';
    $last = 0;
    if (is_file($stateF)) { $d = json_decode((string)@file_get_contents($stateF), true); $last = (int)($d['count'] ?? 0); }
    if (!$force && $cnt <= $last) { $log("[r32] no new qualifier (now={$cnt} posted={$last}) — skip"); return ['ok' => false, 'error' => 'nochange']; }

    $word = ($cnt === 1) ? 'منتخب ضمن مقعده' : 'منتخباً ضمنوا مقاعدهم';
    $ar   = "🎟️ التأهّل إلى دور الـ32\n{$cnt} {$word} في كأس العالم 2026";
    $en   = "🎟️ Through to the Round of 32\n" . ($cnt === 1 ? '1 team has booked its place' : "{$cnt} teams have booked their place");
    $text = $ar . "\n\n" . $en . "\n#WorldCup2026 #FIFAWorldCup #كأس_العالم #المونديال";
    $img  = TweetCardImage::roundOf32($teams, $q['complete']);

    if ($dry) { $log('[r32] DRY (cnt=' . $cnt . ')'); $log('---'); $log($text); $log('[img] ' . ($img ?: 'none')); $log('---'); return ['ok' => false, 'skipped' => 'dry']; }
    $r = XPublisher::tweet($text, $img, $priority);
    if (!empty($r['ok'])) @file_put_contents($stateF, json_encode(['count' => $cnt, 'at' => time()], JSON_UNESCAPED_UNICODE));
    return $r + ['count' => $cnt];
};

// مُشغّل يدويّ: انشر بطاقة المتأهّلين الآن (?token=...&r32 · +&force لإعادة النشر · +&dry للمعاينة)
if (isset($args['r32'])) {
    $r = $r32Tweet(isset($args['force']), true);   // أولويّة: تتجاوز السقف اليومي
    $log(!empty($r['ok']) ? '[r32] OK posted id=' . (string)$r['id'] . ' (cnt=' . (string)($r['count'] ?? '?') . ')'
                          : '[r32] not posted: ' . (string)($r['error'] ?? ($r['skipped'] ?? '?')));
    exit;
}

// ═══════════════════ وضع التنظيف: مسح طوابير الحالة ═══════════════════
// cron/tweet.php?token=...&clear-state=news       → يصمت كل أخبار الـ RSS الحاليّة
// cron/tweet.php?token=...&clear-state=all        → ينظّف news + match + group state
if (isset($args['clear-state'])) {
    $what = strtolower((string)$args['clear-state']);
    $log('');
    $log('╔══════════════════════════════════════════════════════════════╗');
    $log('║  تنظيف حالة الطوابير — ' . $what . str_repeat(' ', max(0, 30 - mb_strlen($what,'UTF-8'))) . '║');
    $log('╚══════════════════════════════════════════════════════════════╝');
    $log('');

    if ($what === 'news' || $what === 'all') {
        // علِّم كل أخبار /news.php الحالية كـ "تم بثّها" → لن تظهر في الطابور
        $marked = 0;
        if (class_exists('News') && class_exists('NewsTweets')) {
            foreach (['ar', 'en'] as $lang) {
                $items = News::latest(50, $lang);
                foreach ($items as $it) {
                    $id = !empty($it['id']) ? (string)$it['id'] : substr(md5((string)($it['link'] ?? '')), 0, 12);
                    if ($id === '') continue;
                    NewsTweets::markSent($id, $lang, (string)($it['title'] ?? ''), 'cleared');
                    $marked++;
                }
            }
        }
        $log('[clear] news: ' . $marked . ' خبر تم تعليمه (لن يُبثّ).');
    }

    if ($what === 'all') {
        // نظّف ملف rate-guard (يستأنف من 0)
        @unlink(rtrim(CACHE_DIR, '/') . '/x_rate.json');
        $log('[clear] x_rate.json: محذوف');
    }

    $log('');
    $log('✅ تم. النظام جاهز يبدأ من الصفر.');
    exit(0);
}

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
// تغريدة واحدة «غير-أولويّة» لكل تشغيل (إلا في وضع drain) → لا تخرج تغريدتان «في نفس
// الوقت»، وتتباعد طبيعياً عبر تشغيلات الكرون (كل 15 دقيقة). النتائج مستثناة (أولويّة قصوى).
$nonPrioDone = false;

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
$send = function (string $tag, string $label, callable $sender, bool $priority = false) use (&$sent, &$failed, &$blocked, &$nonPrioDone, $log, $dry, $pace) {
    if ($dry) {
        $log("[{$tag}] would tweet {$label}");
        return ['ok' => false, 'skipped' => 'dry'];
    }
    // الأولويّة (نتائج) تتجاوز علم blocked إن كان سببه السقف اليومي فقط
    if ($blocked && !$priority) {
        $log("[{$tag}] SKIP {$label} (guard blocked earlier in this run)");
        return ['ok' => false, 'error' => 'guard_blocked'];
    }
    // فحص استباقي للحارس (النتائج تتجاوز السقف اليومي)
    $g = RateGuard::check(0, $priority);
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
        if (!$priority) $nonPrioDone = true;   // استهلكنا «الحصّة» غير-الأولويّة لهذا التشغيل
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

// ═══ أولويّة قصوى: تغريدة نتيجة فور انتهاء المباراة (قبل أي تغريدة أخرى) ═══
// النتائج هي الأهمّ والأعلى تفاعلاً — تُنشَر أوّلاً كي لا تستهلك الفتراتُ اليوميّة
// والمجموعاتُ ميزانيّةَ السقف قبلها. نافذة الطزاجة في pendingPost تمنع الطابور القديم.
if (!$skipMatches) {
    $post = MatchTweets::pendingPost();
    $log('[post] candidates=' . count($post));
    foreach ($post as $job) {
        if ($sent >= $capPerRun) { $log('[post] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) { $log('[post] would tweet ' . $label); $log('---'); $log(MatchTweets::buildPost($m, $lg)); $log('---'); continue; }
        $send('post', $label, fn() => MatchTweets::sendPost($m, $lg), true);   // أولويّة: تتجاوز السقف اليومي
    }
}

// ─────────────────────────────────────────────────────────────────────
//  بقيّة الفئات «غير ذات الأولويّة» — تغريدة واحدة فقط لكل تشغيل (إلا drain).
//  الترتيب حسب الأهميّة التي طلبها صاحب الموقع:
//    1) الفترة اليوميّة (نتائج الليل 9ص · مباريات الـ24 ساعة 6م · ...) — نافذتها ساعة فقط
//    2) قَبل المباراة (مهمّ جداً)   3) المتأهّلون لدور الـ32   4) استطلاع «من سيفوز؟»
//    5) ترتيب المجموعات            6) لوحة الإحصائيّات (الأدنى)
//  العَلَم $nonPrioDone يُرفَع بعد أوّل نجاح غير-أولويّ → تتباعد التغريدات 15 دقيقة
//  ولا تظهر «تغريدتان في نفس الوقت». (drain يتجاوزه لإفراغ الطابور يدوياً.)
// ─────────────────────────────────────────────────────────────────────

// ═══════════════════ A) الفترة اليوميّة — أولويّة عُليا بعد النتائج (تُنشَر أوّلاً في ساعتها) ═══════════════════
//   recap 9ص (نتائج الليل) · news 14 · morning 18 (مباريات الـ24 ساعة) · evening 23.
//   محميّة: نافذتها ساعة واحدة فقط → تُعالَج قبل قَبل-المباراة/الاستطلاع كي لا تُزاحَم، وتتجاوز
//   السقف اليومي (قليلة + محجوزة مرّة/يوم بـclaimSlot فلا تُغرِق) كي لا تُحجَب أبداً — وتبقى
//   «تغريدة واحدة لكل تشغيل» (نرفع $nonPrioDone يدوياً بعد نجاحها فلا تخرج معها أخرى).
if (!$skipDaily && ($drain || !$nonPrioDone)) {
    $dailySlot = $slot !== '' ? $slot : (TweetComposer::currentSlot() ?? '');
    if ($dailySlot === '') {
        $log('[daily] no slot active at ' . date('H:i') . '.');
    } else {
        $langs = defined('X_LANGS') && is_array(X_LANGS) && X_LANGS ? X_LANGS : ['ar', 'en'];
        foreach ($langs as $lg) {
            if (!$drain && $nonPrioDone) break;   // تغريدة واحدة لكل تشغيل
            $slotKey = $dailySlot . '_' . $lg;
            // dry-run لا يحجز الفترة — كان يستهلكها فتُحجَب تغريدة اليوم الفعلية بعده!
            if (!$force && !$dry && !XPublisher::claimSlot($slotKey)) {
                $log('[daily] ' . $slotKey . ' already posted today.');
                continue;
            }
            $text = TweetComposer::build($dailySlot, $lg);
            $log('[daily] slot=' . $slotKey . ' chars=' . mb_strlen($text, 'UTF-8'));

            // بطاقة مصوّرة مرافقة (مباريات اليوم / النتائج) — null = تغريدة نصية
            $img = null;
            if (class_exists('TweetCardImage')) {
                if ($dailySlot === 'morning') {
                    // الـ24 ساعة القادمة (لا التقويم — كانت تعرض مباريات لُعبت فجراً)
                    $list = TweetComposer::next24Matches(4);
                    if ($list) $img = TweetCardImage::generate($list, [
                        'title' => 'المباريات القادمة', 'subtitle' => 'كأس العالم 2026',
                        'subtitle_en' => "UPCOMING MATCHES — FIFA WORLD CUP 2026",
                    ]);
                } elseif ($dailySlot === 'news') {
                    // 🆕 بطاقة «مباريات اليوم» لإثراء تغريدة الأخبار (نفس البطاقة المرئيّة في فترة morning)
                    $list = DataService::matchesOnDate();
                    if ($list) $img = TweetCardImage::generate($list, [
                        'title' => 'مباريات اليوم', 'subtitle' => 'كأس العالم 2026',
                        'subtitle_en' => "TODAY'S MATCHES — FIFA WORLD CUP 2026",
                    ]);
                } elseif ($dailySlot === 'evening' || $dailySlot === 'recap') {
                    $list = DataService::latestResults(3);
                    if ($list) $img = TweetCardImage::generate($list, [
                        'title' => 'نتائج المباريات', 'subtitle' => 'كأس العالم 2026',
                        'subtitle_en' => "RESULTS — FIFA WORLD CUP 2026", 'mode' => 'result',
                    ]);
                }
            }
            if ($img) $log('[daily] card image: ' . basename($img));

            if ($dry) { $log('[daily] dry-run:'); $log('---'); $log($text); $log('---'); continue; }
            // priority=true → تتجاوز السقف اليومي (مهمّة وقليلة)؛ ثمّ نرفع العَلَم يدوياً (واحدة/تشغيل)
            $r = $send('daily', $slotKey, fn() => XPublisher::tweet($text, $img, true), true);
            if (!empty($r['ok'])) { $nonPrioDone = true; }
            else { XPublisher::releaseSlot($slotKey); }   // فشل → حرّر الفترة ليعيد الكرون المحاولة
        }
    }
}

// ═══════════════════ B) قَبل المباراة (مهمّ جداً) ═══════════════════
if (!$skipMatches && ($drain || !$nonPrioDone)) {
    $pre = MatchTweets::pendingPre();
    $log('[pre]  candidates=' . count($pre));
    foreach ($pre as $job) {
        if (!$drain && $nonPrioDone) break;
        if ($sent >= $capPerRun) { $log('[pre] cap reached, stop.'); break; }
        $m = $job['match']; $lg = $job['lang'];
        $label = '#' . (int)$m['_index'] . ' ' . $m['team1'] . '-' . $m['team2'] . ' [' . $lg . ']';
        if ($dry) { $log('[pre] would tweet ' . $label); $log('---'); $log(MatchTweets::buildPre($m, $lg)); $log('---'); continue; }
        $send('pre', $label, fn() => MatchTweets::sendPre($m, $lg));
    }
}

// ═══ بطاقة «المتأهّلون إلى دور الـ32» — النشر التلقائي معطّل بطلب صاحب الموقع (اكتمل التأهّل) ═══
//    المُشغّل اليدوي ?token=...&r32 يبقى متاحاً عند الحاجة فقط (لا ينشر تلقائياً).
$log('[r32] auto disabled (qualification complete).');

// ═══ استطلاع «من سيفوز؟» للمباريات الكبرى القادمة (تفاعل عالٍ) — واحد لكل run ═══
if (!$skipMatches && ($drain || !$nonPrioDone)) {
    $polls = MatchTweets::pendingPolls();
    $log('[poll] candidates=' . count($polls));
    foreach ($polls as $pm) {
        if (!$drain && $nonPrioDone) break;
        if ($sent >= $capPerRun) { $log('[poll] cap reached, stop.'); break; }
        $label = '#' . (int)$pm['_index'] . ' ' . $pm['team1'] . '-' . $pm['team2'];
        if ($dry) { $pp = MatchTweets::buildPoll($pm); $log('[poll] would tweet ' . $label); $log('---'); $log($pp['text']); $log('[options] ' . implode('  |  ', $pp['options'])); $log('---'); continue; }
        $send('poll', $label, fn() => MatchTweets::sendPoll($pm));
        break;   // استطلاع واحد لكل تشغيل (تفادي الإغراق)
    }
}

// ═══ D) ترتيب المجموعات — معطّل (انتهى دور المجموعات بطلب صاحب الموقع) ═══
//    لا نريد تغريدات جداول المجموعات بعد انتهاء الدور. «المباريات القادمة» للأدوار
//    الإقصائيّة تغطّيها فترة morning (معاينة الـ24 ساعة) + قسم القادم أدناه.
$log('[group] disabled — group stage over; upcoming knockout fixtures cover this instead.');

// ═══ لوحة الإحصائيّات (إنجليزيّة، مرّة يوميّاً 21:00 بتوقيت دبي) — أدنى أولويّة ═══
if (!$skipDaily && ($drain || !$nonPrioDone) && class_exists('TweetComposer')) {
    $wantDash = ($slot === 'dashboard') || ((int)date('G') === 12);   // الظهر (9م صارت لتغريدة الصدارة)
    if ($wantDash && ($force || $dry || XPublisher::claimSlot('dashboard_en'))) {
        $text = TweetComposer::dashboardTweet('en');
        $log('[dashboard] chars=' . mb_strlen($text, 'UTF-8'));
        if ($dry) { $log('[dashboard] dry-run:'); $log('---'); $log($text); $log('---'); }
        else {
            $r = $send('dashboard', 'dashboard_en', fn() => XPublisher::tweet($text));
            if (empty($r['ok'])) { XPublisher::releaseSlot('dashboard_en'); }
        }
    }
}

// ═══════════════════ E) أخبار جديدة — معطّل (استبدلناه بـ CTA يوميّ 14:00) ═══════════════════
// بثّ كل خبر فردياً كان يستهلك كثيراً من الرصيد. البديل: تغريدة واحدة يوميّة من
// TweetComposer (slot=news) تدعو لزيارة /news.php. الأخبار التفصيليّة تبقى على الموقع.
$log('[news] feed broadcasting disabled — daily CTA via TweetComposer slot=news handles this.');

if (function_exists('cron_heartbeat')) {
    cron_heartbeat('tweet', "sent={$sent} failed={$failed}" . ($dry ? ' (dry)' : ''));
}
$log("[done] sent={$sent} failed={$failed}");
exit($failed > 0 ? 1 : 0);
