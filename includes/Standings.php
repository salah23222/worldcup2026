<?php
/**
 * Standings.php
 * ============================================================
 * يحسب جداول ترتيب المجموعات من نتائج المباريات الفعلية.
 * القواعد: فوز=3، تعادل=1، خسارة=0.
 *
 * كسر التعادل (وفق لائحة FIFA لمونديال 2026، بالترتيب):
 *   1) النقاط في كل مباريات المجموعة
 *   2) فرق الأهداف الإجمالي
 *   3) الأهداف المسجَّلة الإجمالية
 *   4) عند بقاء التعادل بين منتخبين أو أكثر: نتائج المواجهات المباشرة بينهم فقط
 *      (نقاط ← فرق أهداف ← أهداف مسجَّلة ضمن تلك المباريات)
 *   5) أبجدياً (بديل عن اللعب النظيف/القرعة — لا نملك بيانات بطاقات موثوقة)
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Standings
{
    /**
     * forGroup() — يرجّع جدول ترتيب مجموعة واحدة.
     * كل صف: team, p, w, d, l, gf, ga, gd, pts
     */
    public static function forGroup(string $group): array
    {
        $matches = DataService::matchesInGroup($group);
        return self::tableFromMatches($matches);
    }

    /** جداول كل المجموعات دفعة واحدة */
    public static function all(): array
    {
        $out = [];
        foreach (DataService::groupNames() as $g) {
            $out[$g] = self::forGroup($g);
        }
        return $out;
    }

    /**
     * thirdPlaceRanking() — ترتيب أصحاب المركز الثالث عبر المجموعات الـ12.
     * في نظام 2026 يتأهل أفضل 8 منهم إلى دور الـ32.
     * المقارنة بين المجموعات لا تستخدم المواجهة المباشرة (لم يلتقوا)، بل:
     *   النقاط ← فرق الأهداف ← الأهداف المسجَّلة ← أبجدياً.
     * كل صف يضيف: 'group' (اسم المجموعة) و'qualified' (هل ضمن أفضل $qualifyCount).
     *
     * @param int $qualifyCount عدد المتأهلين (8 افتراضياً).
     * @return array قائمة صفوف الترتيب الثالث مرتّبة تنازلياً.
     */
    public static function thirdPlaceRanking(int $qualifyCount = 8): array
    {
        $thirds = [];
        foreach (DataService::groupNames() as $g) {
            $rows = self::forGroup($g);
            if (count($rows) >= 3) {
                $row          = $rows[2];   // صاحب المركز الثالث
                $row['group'] = $g;
                $thirds[]     = $row;
            }
        }

        usort($thirds, function ($a, $b) {
            if ($b['pts'] !== $a['pts']) return $b['pts'] <=> $a['pts'];
            if ($b['gd']  !== $a['gd'])  return $b['gd']  <=> $a['gd'];
            if ($b['gf']  !== $a['gf'])  return $b['gf']  <=> $a['gf'];
            return strcmp($a['team'], $b['team']);
        });

        foreach ($thirds as $i => &$row) {
            $row['qualified'] = ($i < $qualifyCount);
        }
        unset($row);

        return $thirds;
    }

    // ====================================================
    //  الحساب الداخلي
    // ====================================================

    /** يبني جدول الترتيب من مجموعة مباريات (مع تطبيق كسر التعادل). */
    private static function tableFromMatches(array $matches): array
    {
        $table = [];

        // تهيئة كل منتخب بصفر
        foreach ($matches as $m) {
            foreach ([$m['team1'] ?? '', $m['team2'] ?? ''] as $t) {
                $t = trim($t);
                if ($t !== '' && is_real_team($t) && !isset($table[$t])) {
                    $table[$t] = [
                        'team' => $t, 'p' => 0, 'w' => 0, 'd' => 0,
                        'l' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'pts' => 0,
                    ];
                }
            }
        }

        // معالجة المباريات المنتهية فقط
        foreach ($matches as $m) {
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) {
                continue;
            }
            $t1 = trim($m['team1'] ?? '');
            $t2 = trim($m['team2'] ?? '');
            if (!isset($table[$t1], $table[$t2])) continue;

            [$g1, $g2] = $m['score']['ft'];
            $g1 = (int)$g1; $g2 = (int)$g2;

            $table[$t1]['p']++;  $table[$t2]['p']++;
            $table[$t1]['gf'] += $g1; $table[$t1]['ga'] += $g2;
            $table[$t2]['gf'] += $g2; $table[$t2]['ga'] += $g1;

            if ($g1 > $g2) {
                $table[$t1]['w']++; $table[$t1]['pts'] += 3;
                $table[$t2]['l']++;
            } elseif ($g1 < $g2) {
                $table[$t2]['w']++; $table[$t2]['pts'] += 3;
                $table[$t1]['l']++;
            } else {
                $table[$t1]['d']++; $table[$t1]['pts'] += 1;
                $table[$t2]['d']++; $table[$t2]['pts'] += 1;
            }
        }

        // حساب فرق الأهداف
        foreach ($table as &$row) {
            $row['gd'] = $row['gf'] - $row['ga'];
        }
        unset($row);

        $rows = array_values($table);
        self::sortRows($rows, $matches);
        return $rows;
    }

    /**
     * يرتّب الصفوف: إجمالي (نقاط/فرق/أهداف) أولاً، ثم يكسر أي تعادل متبقٍّ
     * بين منتخبات متساوية تماماً عبر المواجهة المباشرة بينها، ثم أبجدياً.
     */
    private static function sortRows(array &$rows, array $matches): void
    {
        usort($rows, function ($a, $b) {
            if ($b['pts'] !== $a['pts']) return $b['pts'] <=> $a['pts'];
            if ($b['gd']  !== $a['gd'])  return $b['gd']  <=> $a['gd'];
            if ($b['gf']  !== $a['gf'])  return $b['gf']  <=> $a['gf'];
            return 0;   // تعادل تام → يُحسم بالمواجهة المباشرة أدناه
        });

        // اكسر كل عنقود من المنتخبات المتساوية في (نقاط، فرق، أهداف)
        $n = count($rows);
        $i = 0;
        while ($i < $n) {
            $j = $i + 1;
            while ($j < $n
                && $rows[$j]['pts'] === $rows[$i]['pts']
                && $rows[$j]['gd']  === $rows[$i]['gd']
                && $rows[$j]['gf']  === $rows[$i]['gf']) {
                $j++;
            }
            if ($j - $i > 1) {
                $cluster = array_slice($rows, $i, $j - $i);
                self::breakByHeadToHead($cluster, $matches);
                array_splice($rows, $i, $j - $i, $cluster);
            }
            $i = $j;
        }
    }

    /**
     * يرتّب منتخبات متعادلة عبر «دوري مصغّر» من مبارياتها المباشرة فقط:
     * نقاط ← فرق أهداف ← أهداف مسجَّلة (ضمن تلك المباريات) ← أبجدياً.
     */
    private static function breakByHeadToHead(array &$cluster, array $matches): void
    {
        $inCluster = [];
        foreach ($cluster as $r) { $inCluster[$r['team']] = true; }

        $mini = [];
        foreach ($cluster as $r) {
            $mini[$r['team']] = ['pts' => 0, 'gd' => 0, 'gf' => 0];
        }

        foreach ($matches as $m) {
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;
            $t1 = trim($m['team1'] ?? '');
            $t2 = trim($m['team2'] ?? '');
            if (!isset($inCluster[$t1], $inCluster[$t2])) continue;   // كلاهما داخل العنقود

            [$g1, $g2] = $m['score']['ft'];
            $g1 = (int)$g1; $g2 = (int)$g2;

            $mini[$t1]['gf'] += $g1; $mini[$t1]['gd'] += $g1 - $g2;
            $mini[$t2]['gf'] += $g2; $mini[$t2]['gd'] += $g2 - $g1;
            if ($g1 > $g2) {
                $mini[$t1]['pts'] += 3;
            } elseif ($g1 < $g2) {
                $mini[$t2]['pts'] += 3;
            } else {
                $mini[$t1]['pts'] += 1; $mini[$t2]['pts'] += 1;
            }
        }

        usort($cluster, function ($a, $b) use ($mini) {
            $ma = $mini[$a['team']]; $mb = $mini[$b['team']];
            if ($mb['pts'] !== $ma['pts']) return $mb['pts'] <=> $ma['pts'];
            if ($mb['gd']  !== $ma['gd'])  return $mb['gd']  <=> $ma['gd'];
            if ($mb['gf']  !== $ma['gf'])  return $mb['gf']  <=> $ma['gf'];
            return strcmp($a['team'], $b['team']);
        });
    }
}
