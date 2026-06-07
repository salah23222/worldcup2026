<?php
/**
 * GroupTweets.php — تغريدات ترتيب المجموعات بعد كل جولة (AR + EN).
 * ============================================================
 * بعد كل جولة في مجموعة (كل منتخب لعب مباراة) يُنشَر جدول الترتيب
 * بطريقة حماسيّة قابلة للانتشار:
 *
 *   Milestone:
 *     • round1 = كل منتخب لعب مباراة واحدة (2 مباريات منتهية في المجموعة)
 *     • round2 = كل منتخب لعب مباراتين (4 مباريات منتهية)
 *     • final  = كل منتخب لعب 3 مباريات (6 مباريات = المجموعة اكتملت)
 *
 * الحالة محفوظة في cache/x_group_state.json — كل مجموعة × milestone × لغة
 * تُنشَر مرّة واحدة فقط للأبد.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class GroupTweets
{
    /** عدد المباريات المنتهية لكل ميل-ستون. */
    private const THRESHOLDS = ['round1' => 2, 'round2' => 4, 'final' => 6];

    // ───────────────────── واجهات الـ cron ─────────────────────

    /** كل (مجموعة × milestone × لغة) جاهز للنشر ولم يُنشَر بعد. */
    public static function pending(): array
    {
        $jobs = [];
        foreach (DataService::groupNames() as $group) {
            $matches  = DataService::matchesInGroup($group);
            $finished = self::countFinished($matches);

            foreach (self::THRESHOLDS as $ms => $need) {
                if ($finished < $need) continue;          // الجولة لم تكتمل
                foreach (['ar', 'en'] as $lang) {
                    if (self::wasSent($group, $ms, $lang)) continue;
                    $jobs[] = ['group' => $group, 'milestone' => $ms, 'lang' => $lang];
                }
            }
        }
        return $jobs;
    }

    /** ينشر تغريدة ترتيب لمرحلة معيّنة من مجموعة. */
    public static function sendStandings(string $group, string $milestone, string $lang): array
    {
        $text = self::buildStandings($group, $milestone, $lang);
        $r = XPublisher::tweet($text);
        if ($r['ok']) self::markSent($group, $milestone, $lang, (string)$r['id']);
        return $r + ['text' => $text];
    }

    // ───────────────────── بانية النصّ ─────────────────────

    /** يبني تغريدة ترتيب مجموعة (حماسيّة، أعلام، رابط، وسوم). */
    public static function buildStandings(string $group, string $milestone, string $lang): string
    {
        $ar = ($lang === 'ar');
        $rows = Standings::forGroup($group);
        $gLetter = preg_replace('/^Group\s+/', '', $group);

        // العنوان حسب المرحلة
        if ($ar) {
            $heads = [
                'round1' => "📊 الجولة الأولى ولّت — مجموعة {$gLetter}",
                'round2' => "🔥 الجولة الثانية انتهت — مجموعة {$gLetter}",
                'final'  => "🏁 المجموعة {$gLetter} اكتملت — المتأهّلون 🎉",
            ];
        } else {
            $heads = [
                'round1' => "📊 Matchday 1 done — Group {$gLetter}",
                'round2' => "🔥 Matchday 2 done — Group {$gLetter}",
                'final'  => "🏁 Group {$gLetter} complete — Qualifiers 🎉",
            ];
        }
        $head = $heads[$milestone] ?? '';

        // الترتيب (أوّل 4)
        $lines = [];
        $medals = ['🥇', '🥈', '🥉', '4️⃣'];
        foreach (array_slice($rows, 0, 4) as $i => $r) {
            $name = self::nameInLang((string)$r['team'], $lang);
            $flag = self::flagEmoji((string)$r['team']);
            $pts  = (int)$r['pts'];
            $gd   = (int)$r['gd'];
            $gdStr = ($gd > 0 ? '+' : '') . $gd;
            $medal = $medals[$i] ?? (string)($i + 1);
            $lines[] = "{$medal} {$flag} {$name} · {$pts} " . ($ar ? "نقطة" : "pts") . " · {$gdStr}";
        }

        // الـ CTA + الرابط + الوسوم — مع هاشتاك المجموعة #مجموعة_A
        $url  = self::link("groups.php?lang={$lang}");
        $tags = class_exists('Hashtags') ? Hashtags::forGroup($group)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26');
        $cta  = $ar ? "كل المجموعات والتفاصيل 👇" : "Full standings & details 👇";

        // إضافة حماس للمرحلة النهائيّة (المتأهّلون)
        if ($milestone === 'final') {
            $top2 = array_slice($rows, 0, 2);
            if (count($top2) === 2) {
                $q1 = self::flagEmoji((string)$top2[0]['team']) . ' ' . self::nameInLang((string)$top2[0]['team'], $lang);
                $q2 = self::flagEmoji((string)$top2[1]['team']) . ' ' . self::nameInLang((string)$top2[1]['team'], $lang);
                $cta = $ar
                    ? "تأهّل: {$q1} و {$q2} 🎉\n{$cta}"
                    : "Qualified: {$q1} & {$q2} 🎉\n{$cta}";
            }
        }

        $msg = $head . "\n" . implode("\n", $lines) . "\n" . $cta . "\n" . $url . "\n" . $tags;
        return self::fitWithin($msg, 280, $url, $tags);
    }

    // ───────────────────── الحالة ─────────────────────

    private static function stateFile(): string
    {
        return rtrim(CACHE_DIR, '/') . '/x_group_state.json';
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

    public static function wasSent(string $group, string $milestone, string $lang): bool
    {
        $s = self::state();
        $k = "{$milestone}_{$lang}";
        return isset($s[$group][$k]) && !empty($s[$group][$k]['id']);
    }

    public static function markSent(string $group, string $milestone, string $lang, string $tweetId): void
    {
        $s = self::state();
        $k = "{$milestone}_{$lang}";
        if (!isset($s[$group])) $s[$group] = [];
        $s[$group][$k] = ['id' => $tweetId, 'at' => time()];
        self::saveState($s);
    }

    public static function recentLog(int $n = 12): array
    {
        $s = self::state();
        $rows = [];
        foreach ($s as $group => $slots) {
            foreach ($slots as $slot => $v) {
                if (empty($v['id'])) continue;
                $rows[] = ['group' => $group, 'slot' => $slot, 'id' => $v['id'], 'at' => (int)$v['at']];
            }
        }
        usort($rows, fn($a, $b) => $b['at'] <=> $a['at']);
        return array_slice($rows, 0, $n);
    }

    // ───────────────────── مساعدات ─────────────────────

    private static function countFinished(array $matches): int
    {
        $n = 0;
        foreach ($matches as $m) {
            if (isset($m['score']['ft']) && is_array($m['score']['ft'])) $n++;
        }
        return $n;
    }

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
        $a = mb_chr(ord($cc[0]) - 65 + 0x1F1E6, 'UTF-8');
        $b = mb_chr(ord($cc[1]) - 65 + 0x1F1E6, 'UTF-8');
        return $a . $b;
    }

    private static function fitWithin(string $msg, int $cap, string $keepUrl = '', string $keepTags = ''): string
    {
        if (mb_strlen($msg, 'UTF-8') <= $cap) return $msg;
        $tail = '';
        if ($keepUrl !== '')  $tail .= "\n" . $keepUrl;
        if ($keepTags !== '') $tail .= "\n" . $keepTags;
        $budget = $cap - mb_strlen($tail, 'UTF-8') - 1;
        return rtrim(mb_substr($msg, 0, max(0, $budget), 'UTF-8')) . '…' . $tail;
    }

    private static function link(string $path = ''): string
    {
        $base = defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : 'https://wcup2026.org';
        return $base . '/' . ltrim($path, '/');
    }
}
