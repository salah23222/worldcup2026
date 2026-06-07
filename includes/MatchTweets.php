<?php
/**
 * MatchTweets.php — نشر تلقائي لكل مباراة (قبل/بعد) بالعربيّة والإنجليزيّة.
 * ============================================================
 * يُستدعى من cron/tweet.php (كل 15 دقيقة) ويقرّر بنفسه:
 *
 *   PRE-MATCH  → بين 30 و 75 دقيقة قبل ضربة البداية، يُنشَر تنبيهان:
 *                 • تغريدة بالعربيّة (أعلام + ملعب + وقت محلي + رابط)
 *                 • تغريدة بالإنجليزيّة
 *
 *   POST-MATCH → بعد انتهاء المباراة (score.ft موجود)، يُولَّد التقرير
 *                 بالذكاء الاصطناعي (AiContent::forMatch summary) ثم
 *                 يُنشَر تغريدتان:
 *                 • نتيجة + أوّل جملة من تقرير AR + رابط
 *                 • نفس الشيء بالإنجليزيّة
 *
 * الحالة تُحفَظ في cache/x_match_state.json حسب index المباراة:
 *   { "12": { "pre_ar":{"id":"…","at":…}, "pre_en":{…}, "post_ar":{…}, "post_en":{…} } }
 *
 * كل تغريدة تُنشَر مرّة واحدة فقط للأبد (idempotent عبر بوّابة الحالة).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class MatchTweets
{
    /** نافذة النشر القبلي: نُنشر بين هذين الفاصلين قبل ضربة البداية. */
    private const PRE_MIN_SEC = 30 * 60;   // 30 دقيقة
    private const PRE_MAX_SEC = 75 * 60;   // 75 دقيقة (cron 15-min يلتقطها بسهولة)

    /** الحدّ الأقصى لتغريدات لكل run cron (حماية من العواصف). */
    public const MAX_PER_RUN = 6;

    // ───────────────────── واجهات الـ cron ─────────────────────

    /** قائمة المباريات التي تستحقّ تغريدة قَبليّة الآن (لم تُنشَر بعد).
     *  مرتّبة زمنياً، أقصى MAX_PER_RUN. */
    public static function pendingPre(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (!self::isRealMatch($m)) continue;
            $ts = DataService::matchTimestamp($m);
            if ($ts === null) continue;
            $diff = $ts - $now;
            if ($diff < self::PRE_MIN_SEC || $diff > self::PRE_MAX_SEC) continue;

            $idx = (int)$m['_index'];
            $need = [];
            if (!self::wasSent($idx, 'pre', 'ar')) $need[] = 'ar';
            if (!self::wasSent($idx, 'pre', 'en')) $need[] = 'en';
            foreach ($need as $lang) $out[] = ['match' => $m, 'lang' => $lang];
        }
        return $out;
    }

    /** قائمة المباريات التي انتهت ولم تُنشَر تغريدتها البعدية بعد. */
    public static function pendingPost(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (!self::isRealMatch($m)) continue;
            if (($m['_status'] ?? '') !== 'finished') continue;
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;

            $idx = (int)$m['_index'];
            $need = [];
            if (!self::wasSent($idx, 'post', 'ar')) $need[] = 'ar';
            if (!self::wasSent($idx, 'post', 'en')) $need[] = 'en';
            foreach ($need as $lang) $out[] = ['match' => $m, 'lang' => $lang];
        }
        return $out;
    }

    /** ينشر تغريدة قَبليّة (يبني → يرسل → يسجّل). يعيد مصفوفة XPublisher. */
    public static function sendPre(array $m, string $lang): array
    {
        $text = self::buildPre($m, $lang);
        $r = XPublisher::tweet($text);
        if ($r['ok']) self::markSent((int)$m['_index'], 'pre', $lang, (string)$r['id']);
        return $r + ['text' => $text];
    }

    /** ينشر تغريدة بعديّة (يولّد التقرير لو لزم → يبني → يرسل → يسجّل). */
    public static function sendPost(array $m, string $lang): array
    {
        $text = self::buildPost($m, $lang);
        $r = XPublisher::tweet($text);
        if ($r['ok']) self::markSent((int)$m['_index'], 'post', $lang, (string)$r['id']);
        return $r + ['text' => $text];
    }

    // ───────────────────── بانيات النصّ ─────────────────────

    /** تغريدة قَبل المباراة (AR/EN). */
    public static function buildPre(array $m, string $lang): string
    {
        $ar  = ($lang === 'ar');
        $t1  = (string)($m['team1'] ?? '');
        $t2  = (string)($m['team2'] ?? '');
        $n1  = self::nameInLang($t1, $lang);
        $n2  = self::nameInLang($t2, $lang);
        $f1  = self::flagEmoji($t1);
        $f2  = self::flagEmoji($t2);
        $ts  = DataService::matchTimestamp($m);
        $hm  = $ts ? date('H:i', $ts) : '';
        $ground = trim((string)($m['ground'] ?? ''));
        $url    = self::link('match.php?id=' . (int)$m['_index'] . '&lang=' . $lang);
        // هاشتاكات ذكيّة: #الفريق1 #الفريق2 #المضيف + الأساس القصير
        $tags   = class_exists('Hashtags') ? Hashtags::forMatch($m)
                : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26');
        $vs     = $ar ? 'ضدّ' : 'vs';

        if ($ar) {
            $head = "⚽ بعد قليل في كأس العالم 2026";
            $line = "{$f1} {$n1} {$vs} {$n2} {$f2}";
            $when = $hm !== '' ? "🕐 {$hm}" . ($ground !== '' ? " · 🏟️ {$ground}" : '') : ($ground !== '' ? "🏟️ {$ground}" : '');
            $cta  = "تابع التفاصيل والتوقّعات 👇";
        } else {
            $head = "⚽ Coming up at FIFA World Cup 2026";
            $line = "{$f1} {$n1} {$vs} {$n2} {$f2}";
            $when = $hm !== '' ? "🕐 {$hm}" . ($ground !== '' ? " · 🏟️ {$ground}" : '') : ($ground !== '' ? "🏟️ {$ground}" : '');
            $cta  = "Details & predictions 👇";
        }
        $msg = $head . "\n" . $line;
        if ($when !== '') $msg .= "\n" . $when;
        $msg .= "\n" . $cta . "\n" . $url . "\n" . $tags;
        return self::fitWithin($msg, 280, $url, $tags);
    }

    /** تغريدة بعد المباراة (AR/EN) — تضمّ النتيجة + أوّل جملة من تقرير الذكاء. */
    public static function buildPost(array $m, string $lang): string
    {
        $ar  = ($lang === 'ar');
        $t1  = (string)($m['team1'] ?? '');
        $t2  = (string)($m['team2'] ?? '');
        $n1  = self::nameInLang($t1, $lang);
        $n2  = self::nameInLang($t2, $lang);
        $f1  = self::flagEmoji($t1);
        $f2  = self::flagEmoji($t2);
        $g1  = (int)$m['score']['ft'][0];
        $g2  = (int)$m['score']['ft'][1];
        $url = self::link('match.php?id=' . (int)$m['_index'] . '&lang=' . $lang);
        // هاشتاكات ذكيّة: #الفريق1 #الفريق2 #المضيف + الأساس القصير
        $tags = class_exists('Hashtags') ? Hashtags::forMatch($m)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26');

        // النتيجة الأولى — السطر الأهم
        $score = "{$f1} {$n1} {$g1} - {$g2} {$n2} {$f2}";
        $head  = $ar ? "⏱️ نهاية المباراة" : "⏱️ Full time";
        $cta   = $ar ? "اقرأ التقرير الكامل 👇" : "Read the full report 👇";

        // ركلات الترجيح إن وُجدت
        $pen = '';
        if (isset($m['score']['p']) && is_array($m['score']['p'])) {
            $p1 = (int)$m['score']['p'][0]; $p2 = (int)$m['score']['p'][1];
            $pen = $ar ? "(ركلات الترجيح {$p1}–{$p2})" : "(penalties {$p1}–{$p2})";
        }

        // أوّل جملة من تقرير الذكاء (اختياري — يُولَّد لو لم يكن مخزَّناً)
        $sentence = '';
        if (class_exists('AiContent') && AiContent::enabled()) {
            $report = AiContent::forMatch($m, 'summary', $lang);
            if (is_string($report) && trim($report) !== '') {
                $first = self::firstSentence($report);
                if ($first !== '') $sentence = $first;
            }
        }

        $msg = $head . "\n" . $score;
        if ($pen !== '') $msg .= ' ' . $pen;
        if ($sentence !== '') $msg .= "\n" . $sentence;
        $msg .= "\n" . $cta . "\n" . $url . "\n" . $tags;
        return self::fitWithin($msg, 280, $url, $tags);
    }

    // ───────────────────── الحالة ─────────────────────

    private static function stateFile(): string
    {
        return rtrim(CACHE_DIR, '/') . '/x_match_state.json';
    }

    private static function state(): array
    {
        $f = self::stateFile();
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    private static function saveState(array $s): void
    {
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::stateFile(), json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    public static function wasSent(int $idx, string $type, string $lang): bool
    {
        $s = self::state();
        $k = "{$type}_{$lang}";
        return isset($s[(string)$idx][$k]) && !empty($s[(string)$idx][$k]['id']);
    }

    public static function markSent(int $idx, string $type, string $lang, string $tweetId): void
    {
        $s = self::state();
        $k = "{$type}_{$lang}";
        if (!isset($s[(string)$idx])) $s[(string)$idx] = [];
        $s[(string)$idx][$k] = ['id' => $tweetId, 'at' => time()];
        self::saveState($s);
    }

    public static function recentLog(int $n = 12): array
    {
        $s = self::state();
        $rows = [];
        foreach ($s as $idx => $slots) {
            foreach ($slots as $slot => $v) {
                if (empty($v['id'])) continue;
                $rows[] = ['idx' => (int)$idx, 'slot' => $slot, 'id' => $v['id'], 'at' => (int)$v['at']];
            }
        }
        usort($rows, fn($a, $b) => $b['at'] <=> $a['at']);
        return array_slice($rows, 0, $n);
    }

    // ───────────────────── مساعدات ─────────────────────

    private static function isRealMatch(array $m): bool
    {
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        return $t1 !== '' && $t2 !== ''
            && function_exists('is_real_team')
            && is_real_team($t1) && is_real_team($t2)
            && isset($m['_index']);
    }

    /** اسم المنتخب باللغة المطلوبة (مستقل عن current_lang).
     *  ملاحظة: مفاتيح teams_map case-sensitive، وقيمتها [0]=AR ،[1]=flag. */
    private static function nameInLang(string $raw, string $lang): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        if ($lang !== 'ar') return $raw;
        if (!function_exists('teams_map')) return $raw;
        $map = teams_map();
        return isset($map[$raw][0]) ? $map[$raw][0] : $raw;
    }

    /** علم Unicode من ISO-2 المخزَّن في teams_ar.php. */
    private static function flagEmoji(string $team): string
    {
        if ($team === '' || !function_exists('team_flag')) return '';
        $cc = strtoupper((string)team_flag($team));
        if (strlen($cc) !== 2 || !ctype_alpha($cc)) return '';
        $a = mb_chr(ord($cc[0]) - 65 + 0x1F1E6, 'UTF-8');
        $b = mb_chr(ord($cc[1]) - 65 + 0x1F1E6, 'UTF-8');
        return $a . $b;
    }

    /** أوّل جملة من نصّ (يدعم العربيّة والإنجليزيّة). */
    private static function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') return '';
        // علامات نهاية الجملة العربيّة (؟ ؛ .) والإنجليزيّة (. ! ?)
        if (preg_match('/^(.{20,180}?[\.\!\?؟])(\s|$)/u', $text, $mm)) {
            return trim($mm[1]);
        }
        // لا توجد علامة → قطع عند حد معقول
        return self::ellipsize($text, 160);
    }

    /** يحدّ النصّ مع … عند الحاجة (codepoints UTF-8). */
    private static function ellipsize(string $s, int $max): string
    {
        if (mb_strlen($s, 'UTF-8') <= $max) return $s;
        return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
    }

    /** يضمن أنّ التغريدة ≤ $cap حرفاً مع حفظ الرابط والوسوم. */
    private static function fitWithin(string $msg, int $cap, string $keepUrl = '', string $keepTags = ''): string
    {
        if (mb_strlen($msg, 'UTF-8') <= $cap) return $msg;
        $tail = '';
        if ($keepUrl !== '')  $tail .= "\n" . $keepUrl;
        if ($keepTags !== '') $tail .= "\n" . $keepTags;
        $budget = $cap - mb_strlen($tail, 'UTF-8') - 1;
        $head = mb_substr($msg, 0, max(0, $budget), 'UTF-8');
        return rtrim($head) . '…' . $tail;
    }

    /** رابط مطلق (نفس آلية TweetComposer). */
    private static function link(string $path = ''): string
    {
        $base = defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : 'https://wcup2026.org';
        return $base . '/' . ltrim($path, '/');
    }
}
