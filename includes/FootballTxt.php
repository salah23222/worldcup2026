<?php
/**
 * FootballTxt.php — يُصدّر جدول/نتائج/تقارير البطولة بصيغة football.txt
 * (صيغة openfootball النصّيّة المنظّمة — github.com/openfootball).
 * ============================================================
 * البيانات تأتي أصلاً من openfootball، فنعيد تصديرها بنفس صيغتها النصّيّة
 * مُحدّثةً بالنتائج الحيّة — طبقة تصدير موازية لـJSON، تبقى متزامنة تلقائياً
 * لأنّها تُبنى من نفس `DataService::allMatches()` التي يقرأها كلّ الموقع.
 *
 * ثلاث طرق عرض:
 *   schedule() — العنوان + قوائم المجموعات + كلّ الجولات (النتيجة مضمّنة للملعوب)
 *   results()  — المباريات المنتهية فقط
 *   reports()  — المنتهية + الهدّافون والبطاقات (امتداد «تقرير المباراة»)
 *
 * الصيغة القياسيّة (يقرؤها محلّل openfootball):
 *   Group A
 *     Mexico
 *     ...
 *   Matchday 1
 *   [Thu Jun/11]
 *     Mexico            2-1 (1-0)  South Africa       @ Estadio Azteca, Mexico City
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class FootballTxt
{
    private const PAD = 22;   // عرض عمود اسم الفريق
    private const SCW = 11;   // عرض عمود النتيجة (مثل «2-1 (1-0)»)

    public static function schedule(): string { return self::build(false, false); }
    public static function results(): string  { return self::build(false, true); }
    public static function reports(): string  { return self::build(true,  true); }

    /** ترويسة الملفّ مع طابع التوليد + توثيق المصدر. */
    private static function header(string $sub): string
    {
        return "= FIFA World Cup 2026\n\n"
             . "# {$sub}\n"
             . "# Source:  https://wcup2026.org  (auto-generated, kept in sync with live results)\n"
             . "# Data:    openfootball schedule + ESPN/FIFA live results\n"
             . "# Format:  football.txt  (https://github.com/openfootball)\n"
             . "# Updated: " . gmdate('Y-m-d H:i') . " UTC\n\n";
    }

    private static function build(bool $withReport, bool $finishedOnly): string
    {
        if (!class_exists('DataService')) return "= FIFA World Cup 2026\n";
        $all = DataService::allMatches();

        $sub = $withReport ? 'Match reports — results, scorers, bookings & line-ups'
             : ($finishedOnly ? 'Results' : 'Schedule & results');
        $out = self::header($sub);

        // قوائم المجموعات (A–L) — لعرض الجدول الكامل فقط
        if (!$finishedOnly) {
            $out .= self::groupRosters($all);
        }

        // الجولات مرتّبة زمنيّاً، وداخل كلّ جولة المباريات مرتّبة بوقت الانطلاق
        foreach (self::roundsInOrder($all) as $round => $list) {
            if ($finishedOnly) {
                $list = array_values(array_filter($list, [self::class, 'isFinished']));
                if (!$list) continue;
            }
            // ترويسة الجولة تبدأ بعلامة مربّع صغير (▪) حسب صيغة openfootball الحديثة
            $out .= "▪ " . $round . "\n";
            $curDay = '';
            foreach ($list as $m) {
                $day = self::dayLabel($m);
                if ($day !== '' && $day !== $curDay) {
                    // تاريخ بلا أقواس ولا شرطة (الصيغة الحديثة): «Fri Jun 12»
                    $out .= $day . "\n";
                    $curDay = $day;
                }
                $out .= self::matchLine($m);
                if ($withReport) $out .= self::reportLines($m);
            }
            $out .= "\n";
        }

        return rtrim($out) . "\n";
    }

    /** [اسم المجموعة → قائمة الفرق] لمباريات دور المجموعات. */
    private static function groupRosters(array $all): string
    {
        $groups = [];
        foreach ($all as $m) {
            $g = trim((string)($m['group'] ?? ''));
            if ($g === '') continue;
            foreach (['team1', 'team2'] as $k) {
                $t = trim((string)($m[$k] ?? ''));
                if ($t !== '') $groups[$g][$t] = true;
            }
        }
        ksort($groups, SORT_NATURAL);

        // تعريف المجموعة سطر واحد: «Group A:  Team1   Team2   Team3   Team4»
        // (مسافتان فأكثر بين الأسماء حتى تُقسَّم — أسماء مثل «Bosnia & Herzegovina» تبقى موحَّدة)
        $out = '';
        foreach ($groups as $g => $teams) {
            $out .= $g . ':  ' . implode('   ', array_keys($teams)) . "\n";
        }
        return $out . "\n";
    }

    /** [الجولة → مباريات]، الجولات مرتّبة بأبكر وقت انطلاق فيها. */
    private static function roundsInOrder(array $all): array
    {
        $rounds = [];
        foreach ($all as $m) {
            $r = trim((string)($m['round'] ?? '')) ?: 'Matches';
            $rounds[$r][] = $m;
        }
        $min = [];
        foreach ($rounds as $r => $list) {
            usort($rounds[$r], fn($a, $b) => self::ts($a) <=> self::ts($b));
            $min[$r] = self::ts($rounds[$r][0]);
        }
        uksort($rounds, fn($a, $b) => $min[$a] <=> $min[$b]);
        return $rounds;
    }

    /** سطر المباراة: «  Team1   النتيجة|v   Team2   @ الملعب». */
    private static function matchLine(array $m): string
    {
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        $ft = $m['score']['ft'] ?? null;

        if (is_array($ft) && isset($ft[0], $ft[1]) && is_numeric($ft[0]) && is_numeric($ft[1])) {
            $score = sprintf('%d-%d', (int)$ft[0], (int)$ft[1]);
            $ht = $m['score']['ht'] ?? null;
            if (is_array($ht) && isset($ht[0], $ht[1]) && is_numeric($ht[0]) && is_numeric($ht[1])) {
                $score .= sprintf(' (%d-%d)', (int)$ht[0], (int)$ht[1]);
            }
        } else {
            $score = 'v';
        }

        $venue = trim((string)($m['ground'] ?? ''));
        $line = '  ' . str_pad($t1, self::PAD) . str_pad($score, self::SCW);
        // حاذِ علامة @ بحشو team2 فقط حين يتبعه ملعب
        $line .= ($venue !== '') ? str_pad($t2, self::PAD) . '  @ ' . $venue : $t2;
        return rtrim($line) . "\n";
    }

    /** أسطر التقرير (الهدّافون/البطاقات) — للعرض «reports» فقط. */
    private static function reportLines(array $m): string
    {
        $out = '';
        // الأهداف: صيغة openfootball القياسيّة — سطر بين قوسين بلا تسمية «Goals:»
        // مثال:  (Julián Quiñones 9', Raúl Jiménez 67'  —  -)
        $g1 = self::goalStr($m['goals1'] ?? []);
        $g2 = self::goalStr($m['goals2'] ?? []);
        if ($g1 !== '' || $g2 !== '') {
            $out .= '      (' . ($g1 !== '' ? $g1 : '-') . '  —  ' . ($g2 !== '' ? $g2 : '-') . ")\n";
        }

        // البطاقات: خصائص مخصّصة منفصلة (Yellow Cards / Red Cards) — لا «Cards» موحّد بـ(Y)/(R)
        $yH = []; $yA = []; $rH = []; $rA = [];
        foreach (($m['cards'] ?? []) as $c) {
            $name = trim((string)($c['name'] ?? ''));
            if ($name === '') continue;
            $min  = (int)($c['minute'] ?? 0);
            $s    = $name . ($min > 0 ? " {$min}'" : '');
            $away = ((int)($c['team'] ?? 1) === 2);
            if (($c['type'] ?? '') === 'red') {
                if ($away) { $rA[] = $s; } else { $rH[] = $s; }
            } else {
                if ($away) { $yA[] = $s; } else { $yH[] = $s; }
            }
        }
        if ($yH || $yA) {
            $out .= '      Yellow Cards: ' . (implode(', ', $yH) ?: '-') . '  —  ' . (implode(', ', $yA) ?: '-') . "\n";
        }
        if ($rH || $rA) {
            $out .= '      Red Cards: ' . (implode(', ', $rH) ?: '-') . '  —  ' . (implode(', ', $rA) ?: '-') . "\n";
        }

        // التشكيلتان الأساسيّتان (إن صدرت/أُرشِفت) — كلّ فريق سطر «خاصّيّة» باسمه (صيغة openfootball).
        // ESPN المجاني يعطي الأساسيّين فقط (بلا دقائق تبديل/كابتن) → نخرج الـ11 مجمَّعين بالخطة.
        if (class_exists('LiveService')) {
            $lu = LiveService::archivedLineup($m);   // أرشيف/يدويّ فقط — بلا نداء شبكة في الحلقة
            if (is_array($lu)) {
                $l1 = self::lineupStr((string)($m['team1'] ?? ''), $lu['team1'] ?? null);
                $l2 = self::lineupStr((string)($m['team2'] ?? ''), $lu['team2'] ?? null);
                if ($l1 !== '') $out .= '      ' . $l1 . "\n";
                if ($l2 !== '') $out .= '      ' . $l2 . "\n";
            }
        }
        return $out;
    }

    /** «Team: GK - D1, D2, D3, D4 - M1, M2, M3 - F1, F2, F3» من تشكيلة فريق (الأساسيّون مرتّبون
     *  حارس→دفاع→وسط→هجوم). تُجمَّع حسب الخطة (4-3-3...)؛ وإن تعذّر → قائمة مفصولة بفواصل. */
    private static function lineupStr(string $teamEn, ?array $side): string
    {
        $teamEn = trim($teamEn);
        if ($teamEn === '' || !is_array($side)) return '';
        $names = [];
        foreach (($side['start'] ?? []) as $p) {
            $n = trim((string)($p['name'] ?? ''));
            if ($n !== '') $names[] = $n;
        }
        if (count($names) < 11) return '';   // التشكيلة لم تصدر/تكتمل

        $lines = array_values(array_filter(array_map('intval', preg_split('/\D+/', (string)($side['formation'] ?? '')))));
        if ($lines && array_sum($lines) === count($names) - 1) {
            $segs = [array_shift($names)];                       // الحارس وحده
            foreach ($lines as $cnt) $segs[] = implode(', ', array_splice($names, 0, $cnt));
            $body = implode(' - ', $segs);
        } else {
            $body = implode(', ', $names);                       // خطة غير معروفة
        }
        return $teamEn . ': ' . $body;
    }

    /** «Player 51', Player 64' (pen.)» من مصفوفة أهداف فريق. */
    private static function goalStr(array $goals): string
    {
        $parts = [];
        foreach ($goals as $g) {
            $name = trim((string)($g['name'] ?? ''));
            if ($name === '') continue;
            $min = trim((string)($g['minute'] ?? ''));
            $s = $name . ($min !== '' ? " {$min}'" : '');
            if (!empty($g['penalty'])) $s .= ' (pen.)';
            if (!empty($g['og']))      $s .= ' (og)';
            $parts[] = $s;
        }
        return implode(', ', $parts);
    }

    /** تسمية اليوم «Fri Jun 12» (الصيغة الحديثة: بلا أقواس ولا شرطة) من حقل التاريخ. */
    private static function dayLabel(array $m): string
    {
        $d = trim((string)($m['date'] ?? ''));
        if ($d === '') return '';
        $t = strtotime($d . ' 12:00:00 UTC');
        return $t ? gmdate('D M j', $t) : $d;
    }

    private static function ts(array $m): int
    {
        $t = DataService::matchTimestamp($m);
        return $t ?? PHP_INT_MAX;
    }

    private static function isFinished(array $m): bool
    {
        $ft = $m['score']['ft'] ?? null;
        if (is_array($ft) && isset($ft[0], $ft[1]) && is_numeric($ft[0]) && is_numeric($ft[1])) return true;
        return DataService::matchStatus($m) === 'finished';
    }
}
