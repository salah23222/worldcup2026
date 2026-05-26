<?php
/**
 * Standings.php
 * ============================================================
 * يحسب جداول ترتيب المجموعات من نتائج المباريات الفعلية.
 * القواعد: فوز=3، تعادل=1، خسارة=0.
 * الترتيب: النقاط ← فرق الأهداف ← الأهداف المسجلة ← الاسم.
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
        $table   = [];

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

        // الترتيب
        $rows = array_values($table);
        usort($rows, function ($a, $b) {
            if ($b['pts'] !== $a['pts']) return $b['pts'] <=> $a['pts'];
            if ($b['gd']  !== $a['gd'])  return $b['gd']  <=> $a['gd'];
            if ($b['gf']  !== $a['gf'])  return $b['gf']  <=> $a['gf'];
            return strcmp($a['team'], $b['team']);
        });

        return $rows;
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
}
