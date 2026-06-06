<?php
/**
 * TweetComposer.php — يبني نصّ التغريدة من بيانات الموقع.
 * ============================================================
 * يوفّر تغريدة لكل «فترة» (slot) من جدول النشر اليومي:
 *
 *   morning   (09:00 توقيت العرض) — تنبيه باليوم/ما تبقّى + رابط الجدول
 *   evening   (21:00 توقيت العرض) — ملخّص نتائج اليوم + رابط الترتيب
 *   countdown (قبل البطولة)        — العدّ التنازلي + رابط التوقّعات
 *   manual / preview               — تغريدة افتراضية للاختبار اليدوي
 *
 * كل تغريدة تنتهي برابط الموقع + الوسوم الرسمية (#WeAre26 #FIFAWorldCup26).
 * عربي إن كانت لغة الموقع 'ar' وإلّا إنجليزي.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class TweetComposer
{
    /** رمز فترة النشر للوقت الحالي حسب توقيت العرض (DISPLAY_TIMEZONE).
     *  يعيد null لو لا توجد فترة فعّالة الآن. */
    public static function currentSlot(int $now = 0): ?string
    {
        $now = $now ?: time();
        $h   = (int)date('G', $now);   // 0..23 — قد تأثرت أعلاه بـdate_default_timezone_set
        $started = DataService::tournamentStarted();
        // قبل البطولة: تنبيه واحد عند 10 صباحاً.
        if (!$started) {
            return ($h === 10) ? 'countdown' : null;
        }
        // أثناء البطولة: morning 09 + evening 21
        if ($h === 9)  return 'morning';
        if ($h === 21) return 'evening';
        return null;
    }

    /** يبني نصّ تغريدة لفترة معيّنة. لغة الموقع تتحكّم باللغة. */
    public static function build(string $slot): string
    {
        $ar = (current_lang() === 'ar');
        switch ($slot) {
            case 'countdown': return self::countdown($ar);
            case 'morning':   return self::morning($ar);
            case 'evening':   return self::evening($ar);
            case 'manual':
            default:          return self::manual($ar);
        }
    }

    // ---------- فترات ----------

    private static function countdown(bool $ar): string
    {
        $start = DataService::tournamentStart();
        $days  = $start ? max(0, (int)floor(($start - time()) / 86400)) : null;
        $link  = self::link('predict.php');
        if ($days !== null && $days > 0) {
            $msg = $ar
                ? "باقٍ {$days} يوم على انطلاق كأس العالم 2026 ⚽\nجهّز توقّعاتك واصعد في الترتيب 👇"
                : "{$days} days until FIFA World Cup 2026 kicks off ⚽\nPick your predictions and climb the leaderboard 👇";
        } else {
            $msg = $ar
                ? "اقترب انطلاق كأس العالم 2026! آخر فرصة لتوقّعاتك 👇"
                : "FIFA World Cup 2026 is almost here — last chance to lock your picks 👇";
        }
        return self::sign($msg, $link);
    }

    private static function morning(bool $ar): string
    {
        $today = DataService::matchesOnDate();
        $n     = count($today);
        $link  = self::link('matches.php');
        if ($n === 0) {
            $msg = $ar
                ? "لا مباريات اليوم — استرح، راجع توقّعاتك، وتفقّد الترتيب 📊"
                : "No matches today — take a breather, review your picks, check the standings 📊";
            return self::sign($msg, self::link('leaderboard.php'));
        }
        $headline = $ar ? "اليوم {$n} مباراة في المونديال ⚽" : "{$n} World Cup matches today ⚽";
        $lines    = [];
        foreach (array_slice($today, 0, 3) as $m) {
            $lines[] = self::matchLine($m, $ar, withTime: true);
        }
        $body = implode("\n", $lines);
        $foot = $ar ? "كل التفاصيل بتوقيت دولتك 👇" : "All times in your local timezone 👇";
        return self::sign($headline . "\n" . $body . "\n" . $foot, $link);
    }

    private static function evening(bool $ar): string
    {
        // نتائج اليوم فقط — نُرشّح من matchesOnDate
        $today = DataService::matchesOnDate();
        $done  = array_values(array_filter($today, fn($m) => ($m['_status'] ?? '') === 'finished'));
        $link  = self::link('leaderboard.php');

        if (!$done) {
            $msg = $ar
                ? "ما زالت مباريات اليوم تجري — تابعها مباشرة 👇"
                : "Today's matches are still in play — follow them live 👇";
            return self::sign($msg, self::link('matches.php'));
        }

        $headline = $ar ? "نتائج اليوم ⚽" : "Today's results ⚽";
        $lines    = [];
        foreach (array_slice($done, 0, 3) as $m) {
            $lines[] = self::matchLine($m, $ar, withTime: false);
        }
        $body = implode("\n", $lines);
        $foot = $ar ? "كيف توقّعتها؟ تحقّق من ترتيبك 👇" : "How did your picks fare? Check your rank 👇";
        return self::sign($headline . "\n" . $body . "\n" . $foot, $link);
    }

    private static function manual(bool $ar): string
    {
        $msg = $ar
            ? "كأس العالم 2026 — كل المباريات، التوقّعات، الترتيب، وملخّصات لحظية في مكان واحد ⚽"
            : "FIFA World Cup 2026 — all matches, predictions, standings & live updates in one place ⚽";
        return self::sign($msg, self::link());
    }

    // ---------- مساعدات ----------

    /** يبني سطر مباراة قصير: 🇲🇽 المكسيك 1-0 جنوب أفريقيا 🇿🇦  أو  🇲🇽 vs 🇿🇦 17:00 */
    private static function matchLine(array $m, bool $ar, bool $withTime): string
    {
        $t1 = team_name((string)($m['team1'] ?? ''));
        $t2 = team_name((string)($m['team2'] ?? ''));
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

    /** علم Unicode من اسم منتخب (ISO-2 من teams_ar). يعيد '' إن تعذّر. */
    private static function flagEmoji(string $team): string
    {
        if ($team === '' || !function_exists('team_flag')) return '';
        $cc = strtoupper((string)team_flag($team));
        if (strlen($cc) !== 2 || !ctype_alpha($cc)) return '';
        // Regional Indicator Symbols: A=🇦 (U+1F1E6) → كل حرف يُرفع بـ 127397
        $a = self::cp(ord($cc[0]) - 65 + 0x1F1E6);
        $b = self::cp(ord($cc[1]) - 65 + 0x1F1E6);
        return $a . $b;
    }

    /** Unicode codepoint → UTF-8. */
    private static function cp(int $cp): string
    {
        return mb_chr($cp, 'UTF-8');
    }

    /** يلصق رابطاً + الوسوم في نهاية التغريدة، ضمن حد 280. */
    private static function sign(string $msg, string $link): string
    {
        $tags = defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26';
        $full = $msg . "\n" . $link . "\n" . $tags;
        if (mb_strlen($full, 'UTF-8') <= 280) return $full;
        // إن تجاوز → قصّ النص (نُبقي الرابط والوسوم سليمة)
        $budget = 280 - mb_strlen("\n" . $link . "\n" . $tags, 'UTF-8') - 1;
        $msg = mb_substr($msg, 0, max(0, $budget), 'UTF-8') . '…';
        return $msg . "\n" . $link . "\n" . $tags;
    }

    /** رابط مطلق لصفحة في الموقع (يفضّل SITE_URL إن وُجد). */
    private static function link(string $page = ''): string
    {
        $base = defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : 'https://wcup2026.org';
        return $base . ($page !== '' ? '/' . ltrim($page, '/') : '');
    }

    // ---------- وصف خطة النشر (للوحة التحكم) ----------

    /** خطّة النشر اليومية المعروضة للأدمن. */
    public static function schedulePlan(bool $ar): array
    {
        return [
            [
                'time'  => '09:00',
                'slot'  => 'morning',
                'title' => $ar ? 'تنبيه الصباح' : 'Morning preview',
                'note'  => $ar ? 'أبرز 3 مباريات اليوم + رابط الجدول' : 'Top 3 matches today + schedule link',
                'when'  => $ar ? 'أثناء البطولة' : 'During tournament',
            ],
            [
                'time'  => '10:00',
                'slot'  => 'countdown',
                'title' => $ar ? 'العدّ التنازلي' : 'Countdown',
                'note'  => $ar ? 'كم يوم متبقٍ + رابط التوقّعات' : 'Days remaining + predictions link',
                'when'  => $ar ? 'قبل البطولة فقط' : 'Pre-tournament only',
            ],
            [
                'time'  => '21:00',
                'slot'  => 'evening',
                'title' => $ar ? 'ملخّص المساء' : 'Evening recap',
                'note'  => $ar ? 'نتائج اليوم + رابط الترتيب' : 'Today\'s results + leaderboard link',
                'when'  => $ar ? 'أثناء البطولة' : 'During tournament',
            ],
        ];
    }
}
