<?php
/**
 * TweetComposer.php — يبني نصّ التغريدة من بيانات الموقع.
 * ============================================================
 * يوفّر تغريدة لكل «فترة» (slot) من جدول النشر اليومي، بالعربيّة أو الإنجليزيّة:
 *
 *   morning   (09:00) — تنبيه باليوم/ما تبقّى + رابط الجدول
 *   countdown (10:00) — العدّ التنازلي + رابط التوقّعات (قبل البطولة فقط)
 *   evening   (21:00) — ملخّص نتائج اليوم + رابط الترتيب
 *   stats     (22:00) — هدّافون + بطاقات + إجمالي أهداف + رابط stats.php
 *   manual                                                — تغريدة افتراضية للاختبار
 *
 * كل فترة تُنشَر مرّتين في اليوم: عربيّة + إنجليزيّة (مفاتيح اللغة منفصلة في
 * claimSlot لمنع التكرار). نهاية كل تغريدة: رابط الموقع + الوسوم الرسمية.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class TweetComposer
{
    /** فترات النشر اليومية وساعاتها (Asia/Dubai). */
    private const SLOT_HOURS = [
        'recap'     => 9,    // نتائج مباريات الليل (تنبيه صباحي للجمهور)
        'news'      => 14,   // 🆕 تذكير بصفحة الأخبار (CTA يوميّ يوجّه للموقع)
        'countdown' => 16,   // عدّ تنازلي عصراً قبل المباريات
        'morning'   => 17,   // معاينة مباريات اليوم (تحضير الجمهور)
        'trivia'    => 18,   // سؤال اليوم في وقت ذروة التفاعل
        'stats'     => 22,   // ملخّص رقمي قبل المباريات الليلية
        'evening'   => 23,   // نتائج آخر مباريات المساء
    ];

    /** رمز فترة النشر للوقت الحالي. null = لا فترة فعّالة. */
    public static function currentSlot(int $now = 0): ?string
    {
        $now = $now ?: time();
        $h   = (int)date('G', $now);
        $started = DataService::tournamentStarted();

        // قبل البطولة: news + countdown + trivia (3 فترات للتسويق)
        if (!$started) {
            if ($h === self::SLOT_HOURS['news'])      return 'news';
            if ($h === self::SLOT_HOURS['countdown']) return 'countdown';
            if ($h === self::SLOT_HOURS['trivia'])    return 'trivia';
            return null;
        }
        // ⭐ أثناء البطولة: 4 فترات أساسيّة فقط (المستخدم يكمل بتغريدات يدويّة)
        //    recap = نتائج الليل · news = تذكير الأخبار
        //    morning = مباريات اليوم · evening = نتائج المساء
        foreach (['recap', 'news', 'morning', 'evening'] as $s) {
            if ($h === self::SLOT_HOURS[$s]) return $s;
        }
        return null;
    }

    /** يبني نصّ تغريدة لفترة معيّنة باللغة المطلوبة (ar/en). */
    public static function build(string $slot, ?string $lang = null): string
    {
        $lang = ($lang === 'ar' || $lang === 'en') ? $lang : current_lang();
        $ar = ($lang === 'ar');
        switch ($slot) {
            case 'countdown': return self::countdown($ar);
            case 'morning':   return self::morning($ar);
            case 'evening':   return self::evening($ar);
            case 'stats':     return self::stats($ar, $lang);
            case 'trivia':    return self::trivia($ar, $lang);
            case 'recap':     return self::recap($ar);
            case 'news':      return self::newsCTA($ar, $lang);
            case 'manual':
            default:          return self::manual($ar);
        }
    }

    // ───────────────────── الفترات ─────────────────────

    private static function countdown(bool $ar): string
    {
        $start = DataService::tournamentStart();
        $days  = $start ? max(0, (int)floor(($start - time()) / 86400)) : null;
        $link  = self::link('predict.php');
        if ($days !== null && $days > 0) {
            $msg = $ar
                ? "⏳ {$days} أيّام ⚽🔥 تفصلنا عن أكبر بطولة في التاريخ\n🇨🇦 🇲🇽 🇺🇸 · 48 منتخب · 104 مباراة · 16 مدينة\nجهّز توقّعاتك الآن 🏆 👇"
                : "⏳ {$days} days ⚽🔥 until the biggest tournament ever\n🇨🇦 🇲🇽 🇺🇸 · 48 teams · 104 matches · 16 cities\nLock your predictions now 🏆 👇";
        } else {
            $msg = $ar
                ? "🚨 الكرة على وشك أن تتحرّك! آخر فرصة لتوقّعاتك 🌍⚽👇"
                : "🚨 The ball is about to roll! Last chance to lock your picks 🌍⚽👇";
        }
        return self::sign($msg, $link, 'countdown');
    }

    private static function morning(bool $ar): string
    {
        $today = DataService::matchesOnDate();
        $n     = count($today);
        $link  = self::link('matches.php');
        if ($n === 0) {
            $msg = $ar
                ? "☕ يوم راحة في المونديال — راجع توقّعاتك وتفقّد ترتيبك 📊"
                : "☕ Quiet day at the World Cup — review your picks and check the standings 📊";
            return self::sign($msg, self::link('leaderboard.php'), 'morning');
        }
        $headline = $ar
            ? "🔥 اليوم {$n} مباراة في المونديال ⚽"
            : "🔥 {$n} World Cup matches today ⚽";
        $lines = [];
        foreach (array_slice($today, 0, 3) as $m) {
            $lines[] = self::matchLine($m, $ar, withTime: true);
        }
        $foot = $ar ? "كل التفاصيل بتوقيتك المحلي 👇" : "All times in your local timezone 👇";
        return self::sign($headline . "\n" . implode("\n", $lines) . "\n" . $foot, $link, 'morning');
    }

    private static function evening(bool $ar): string
    {
        $today = DataService::matchesOnDate();
        $done  = array_values(array_filter($today, fn($m) => ($m['_status'] ?? '') === 'finished'));
        $link  = self::link('leaderboard.php');

        if (!$done) {
            $msg = $ar
                ? "📺 المباريات لا تزال مستعرة — تابعها مباشرة 👇"
                : "📺 Matches still in play — follow them live 👇";
            return self::sign($msg, self::link('matches.php'), 'evening');
        }
        $headline = $ar ? "⚽ نتائج اليوم 🏆" : "⚽ Today's results 🏆";
        $lines    = [];
        foreach (array_slice($done, 0, 3) as $m) {
            $lines[] = self::matchLine($m, $ar, withTime: false);
        }
        $foot = $ar ? "كيف توقّعتها؟ تحقّق من ترتيبك 👇" : "How did your picks fare? Check your rank 👇";
        return self::sign($headline . "\n" . implode("\n", $lines) . "\n" . $foot, $link, 'evening');
    }

    /** stats — هدّافون + بطاقات + إجمالي أهداف (يُلغى إذا 0 مباريات منتهية). */
    private static function stats(bool $ar, string $lang): string
    {
        $s = Stats::compute();
        if ((int)($s['played'] ?? 0) === 0) {
            $msg = $ar
                ? "📊 البطولة بانتظار أوّل صافرة! تابع كل الإحصائيات لحظة بلحظة 👇"
                : "📊 Tournament awaits its first whistle! Follow every stat live 👇";
            return self::sign($msg, self::link("stats.php?lang={$lang}"), 'stats');
        }

        $head = $ar
            ? "📊 إحصائيات البطولة حتى الآن 🔥"
            : "📊 Tournament stats so far 🔥";

        $bullets = [];
        // إجمالي المباريات والأهداف
        $bullets[] = $ar
            ? "⚽ {$s['played']} مباراة · {$s['goals']} هدف · معدّل {$s['avg']}"
            : "⚽ {$s['played']} matches · {$s['goals']} goals · avg {$s['avg']}";

        // أعلى 3 هدّافين
        $scorers = class_exists('Scorers') ? Scorers::current() : [];
        $top = array_slice($scorers, 0, 3);
        if ($top) {
            $names = [];
            $medals = ['🥇', '🥈', '🥉'];
            foreach ($top as $i => $p) {
                $name = $p['name'];
                $flag = self::flagEmoji($p['team']);
                $g    = (int)$p['goals'];
                $names[] = "{$medals[$i]} {$flag} {$name} ({$g})";
            }
            $bullets[] = ($ar ? "🎯 الهدّافون: " : "🎯 Top scorers: ") . implode(' · ', $names);
        }

        // البطاقات
        $y = (int)($s['yellows'] ?? 0);
        $r = (int)($s['reds']    ?? 0);
        if ($y > 0 || $r > 0) {
            $bullets[] = ($ar
                ? "🟨 صفراء: {$y}  ·  🟥 حمراء: {$r}"
                : "🟨 Yellows: {$y}  ·  🟥 Reds: {$r}");
        }

        $foot = $ar ? "كل الأرقام والمتصدّرين 👇" : "Full numbers & leaders 👇";
        $body = $head . "\n" . implode("\n", $bullets) . "\n" . $foot;
        return self::sign($body, self::link("stats.php?lang={$lang}"), 'stats');
    }

    /** news — تذكير يوميّ بصفحة الأخبار (CTA يجلب الجمهور للموقع بدل بثّ كل خبر). */
    private static function newsCTA(bool $ar, string $lang): string
    {
        $link = self::link("news.php?lang={$lang}");
        if ($ar) {
            $variants = [
                "📰 آخر أخبار المونديال 🇨🇦 🇲🇽 🇺🇸 — تحديث لحظيّ من كل المصادر العربيّة والعالميّة 🌍 👇",
                "🚨 أهم عناوين كأس العالم 2026 اليوم — لا يفوتك خبر 🏆⚽ 👇",
                "📰 كل ما يحدث في طريق المونديال 🌎 · تحديثات يوميّة من العالم 👇",
                "🔥 نبض كأس العالم 🌍⚽ — كل الأخبار في مكان واحد 👇",
                "📰 من كندا إلى المكسيك إلى أمريكا 🇨🇦🇲🇽🇺🇸 — تحديثات مستمرّة 🌟 👇",
            ];
        } else {
            $variants = [
                "📰 All World Cup 2026 news 🇨🇦 🇲🇽 🇺🇸 — live updates from across the globe 🌍 👇",
                "🚨 Today's biggest tournament headlines 🏆⚽ — don't miss anything 👇",
                "📰 The road to the World Cup 🌎 · daily global updates 👇",
                "🔥 Heart of the tournament 🌍⚽ — every story, all in one place 👇",
                "📰 From Canada to Mexico to USA 🇨🇦🇲🇽🇺🇸 — continuous updates 🌟 👇",
            ];
        }
        $idx = (int)date('z') % count($variants);
        return self::sign($variants[$idx], $link, 'news');
    }

    /** recap — صباحاً 09:00: نتائج آخر 24 ساعة (تشمل مباريات الليل في النطاق الأمريكي). */
    private static function recap(bool $ar): string
    {
        // آخر النتائج المنتهية (تشمل ما لُعب الليلة الماضية بتوقيت Asia/Dubai)
        $results = DataService::latestResults(3);
        $link    = self::link('matches.php');
        if (!$results) {
            $msg = $ar
                ? "☀️ صباح كرة القدم! لا نتائج جديدة منذ الأمس — استعد لمباريات اليوم 📅"
                : "☀️ Football morning! No fresh results since yesterday — gear up for today's games 📅";
            return self::sign($msg, $link, 'recap');
        }
        $head  = $ar ? "☀️ صباح الكرة — نتائج الليل 🌙⚽" : "☀️ Morning recap — overnight scores 🌙⚽";
        $lines = [];
        foreach (array_slice($results, 0, 3) as $m) {
            $lines[] = self::matchLine($m, $ar, withTime: false);
        }
        $foot = $ar
            ? "كيف توقّعتها؟ ابدأ يومك بالترتيب 👇"
            : "How did your picks fare? Start your day with the standings 👇";
        return self::sign($head . "\n" . implode("\n", $lines) . "\n" . $foot, self::link('leaderboard.php'), 'recap');
    }

    /** trivia — سؤال اليوم + رابط trivia.php (يستخدم الكاش اليومي للذكاء). */
    private static function trivia(bool $ar, string $lang): string
    {
        $q = null;
        if (class_exists('AiContent')) $q = AiContent::dailyTrivia($lang);
        $link = self::link("trivia.php?lang={$lang}");
        // هاشتاكات خاصّة بـ trivia (مرحلة + #تحدّي_المعرفة + أساس)
        $tags = class_exists('Hashtags') ? Hashtags::forDailySlot('trivia')
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26');

        if (!is_array($q) || empty($q['q'])) {
            $msg = $ar
                ? "🧠 سؤال اليوم بانتظارك في تحدّي المعرفة! 3 نقاط للإجابة الصحيحة 👇"
                : "🧠 Today's trivia challenge awaits! 3 points for the correct answer 👇";
            return self::sign($msg, $link, 'trivia');
        }

        $question = trim((string)$q['q']);
        $head     = $ar ? "🧠 سؤال اليوم — تحدّي المعرفة" : "🧠 Daily trivia — test yourself";
        $cta      = $ar ? "أيها الصحيح؟ شارك واربح 3 نقاط ⭐ 👇" : "Which one? Answer & earn 3 points ⭐ 👇";

        // محاولة إضافة الخيارات لو السياق يسمح (حد 280 حرفاً)
        $withOpts = '';
        if (isset($q['options']) && is_array($q['options']) && count($q['options']) === 4) {
            $labels = $ar ? ['أ', 'ب', 'ج', 'د'] : ['A', 'B', 'C', 'D'];
            $optsLines = [];
            foreach ($q['options'] as $i => $opt) {
                $opt = trim((string)$opt);
                $optsLines[] = "{$labels[$i]}) {$opt}";
            }
            $candidate = $head . "\n" . $question . "\n" . implode("\n", $optsLines) . "\n" . $cta . "\n" . $link . "\n" . $tags;
            if (mb_strlen($candidate, 'UTF-8') <= 280) {
                return $candidate;
            }
        }

        // النسخة المختصرة (بدون خيارات)
        $msg = $head . "\n" . $question . "\n" . $cta;
        return self::sign($msg, $link, 'trivia');
    }

    private static function manual(bool $ar): string
    {
        $msg = $ar
            ? "كأس العالم 2026 — كل المباريات، التوقّعات، الترتيب، وملخّصات لحظية في مكان واحد ⚽"
            : "FIFA World Cup 2026 — all matches, predictions, standings & live updates in one place ⚽";
        return self::sign($msg, self::link());
    }

    // ───────────────────── مساعدات ─────────────────────

    private static function matchLine(array $m, bool $ar, bool $withTime): string
    {
        $t1 = self::nameInLang((string)($m['team1'] ?? ''), $ar ? 'ar' : 'en');
        $t2 = self::nameInLang((string)($m['team2'] ?? ''), $ar ? 'ar' : 'en');
        $f1 = self::flagEmoji((string)($m['team1'] ?? ''));
        $f2 = self::flagEmoji((string)($m['team2'] ?? ''));
        $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
        if ($hasScore) {
            $a = (int)$m['score']['ft'][0]; $b = (int)$m['score']['ft'][1];
            return "{$f1} {$t1} {$a}-{$b} {$t2} {$f2}";
        }
        if ($withTime) {
            $ts = DataService::matchTimestamp($m);
            $hm = $ts ? date('H:i', $ts) : '';
            $vs = $ar ? 'ضد' : 'vs';
            return "{$f1} {$t1} {$vs} {$t2} {$f2} · {$hm}";
        }
        $vs = $ar ? 'ضد' : 'vs';
        return "{$f1} {$t1} {$vs} {$t2} {$f2}";
    }

    /** اسم باللغة المطلوبة (مستقل عن current_lang). */
    private static function nameInLang(string $raw, string $lang): string
    {
        $raw = trim($raw);
        if ($raw === '' || $lang !== 'ar') return $raw;
        if (!function_exists('teams_map')) return $raw;
        $map = teams_map();
        return isset($map[$raw][0]) ? $map[$raw][0] : $raw;
    }

    private static function flagEmoji(string $team): string
    {
        if ($team === '' || !function_exists('team_flag')) return '';
        $cc = strtoupper((string)team_flag($team));
        if (strlen($cc) !== 2 || !ctype_alpha($cc)) return '';
        $a = self::cp(ord($cc[0]) - 65 + 0x1F1E6);
        $b = self::cp(ord($cc[1]) - 65 + 0x1F1E6);
        return $a . $b;
    }

    private static function cp(int $cp): string
    {
        return mb_chr($cp, 'UTF-8');
    }

    private static function sign(string $msg, string $link, ?string $slot = null): string
    {
        // هاشتاكات ذكيّة حسب الفترة: مرحلة + slot + أساس قصير
        if ($slot !== null && class_exists('Hashtags')) {
            $tags = Hashtags::forDailySlot($slot);
        } else {
            $tags = defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26';
        }
        $full = $msg . "\n" . $link . "\n" . $tags;
        if (mb_strlen($full, 'UTF-8') <= 280) return $full;
        $budget = 280 - mb_strlen("\n" . $link . "\n" . $tags, 'UTF-8') - 1;
        $msg = mb_substr($msg, 0, max(0, $budget), 'UTF-8') . '…';
        return $msg . "\n" . $link . "\n" . $tags;
    }

    private static function link(string $page = ''): string
    {
        $base = defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : 'https://wcup2026.org';
        return $base . ($page !== '' ? '/' . ltrim($page, '/') : '');
    }

    // ───────────────────── خطّة النشر للوحة الإدارة ─────────────────────

    public static function schedulePlan(bool $ar): array
    {
        return [
            ['time' => '09:00', 'slot' => 'recap',     'title' => $ar ? 'صباح الكرة — نتائج الليل' : 'Morning recap — overnight scores',
             'note'  => $ar ? 'نتائج آخر مباريات الليل + رابط الترتيب' : 'Overnight results + leaderboard link',
             'when'  => $ar ? 'أثناء البطولة' : 'During tournament'],
            ['time' => '14:00', 'slot' => 'news',      'title' => $ar ? 'تذكير الأخبار' : 'News reminder',
             'note'  => $ar ? 'تغريدة واحدة يومياً تدعو لتصفّح أخبار الموقع' : 'One daily CTA to visit news page',
             'when'  => $ar ? 'يومياً' : 'Every day'],
            ['time' => '16:00', 'slot' => 'countdown', 'title' => $ar ? 'العدّ التنازلي' : 'Countdown',
             'note'  => $ar ? 'كم يوم متبقٍ + رابط التوقّعات' : 'Days remaining + predictions link',
             'when'  => $ar ? 'قبل البطولة وأثناءها' : 'Pre + during tournament'],
            ['time' => '17:00', 'slot' => 'morning',   'title' => $ar ? 'مباريات اليوم' : 'Today\'s matches',
             'note'  => $ar ? 'أبرز 3 مباريات اليوم + رابط الجدول' : 'Top 3 matches today + schedule link',
             'when'  => $ar ? 'أثناء البطولة' : 'During tournament'],
            ['time' => '18:00', 'slot' => 'trivia',    'title' => $ar ? 'سؤال اليوم' : 'Daily trivia',
             'note'  => $ar ? 'سؤال معرفة + خيارات + رابط trivia.php (3 نقاط للإجابة)' : 'Question + options + link to trivia.php (3 pts)',
             'when'  => $ar ? 'قبل البطولة فقط · (يدوي أثناء البطولة)' : 'Pre-tournament only · (manual during)'],
            ['time' => '22:00', 'slot' => 'stats',     'title' => $ar ? 'إحصائيات اليوم' : 'Daily stats',
             'note'  => $ar ? 'الهدّافون + البطاقات + إجمالي الأهداف' : 'Top scorers + cards + total goals',
             'when'  => $ar ? 'يدوي فقط (موفّر للميزانيّة)' : 'Manual only (budget saver)'],
            ['time' => '23:00', 'slot' => 'evening',   'title' => $ar ? 'نتائج المساء' : 'Evening results',
             'note'  => $ar ? 'نتائج آخر مباريات المساء + رابط الترتيب' : 'Late-evening results + leaderboard link',
             'when'  => $ar ? 'أثناء البطولة' : 'During tournament'],
        ];
    }
}
