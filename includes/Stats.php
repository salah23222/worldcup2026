<?php
/**
 * Stats.php — إحصائيات البطولة محسوبة من النتائج الحقيقية فقط.
 * ============================================================
 * يمسح كل المباريات المنتهية (لها score.ft) ويستخرج منها:
 *   - ملخّص: عدد المباريات، إجمالي الأهداف، المعدّل، البطاقات
 *   - أكثر المنتخبات تسجيلاً / أفضل الدفاعات
 *   - أكثر المباريات أهدافاً / أكبر الانتصارات
 *   - الأهداف حسب المدينة المستضيفة
 * كل الأرقام من بيانات openfootball/API-Football الحقيقية — لا شيء وهمي.
 * قبل انطلاق البطولة لا توجد مباريات منتهية → كل القوائم فارغة بأمان.
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Stats
{
    /** يحسب كل الإحصائيات دفعة واحدة ويرجّعها كمصفوفة جاهزة للعرض. */
    public static function compute(): array
    {
        $finished = [];
        foreach (DataService::allMatches() as $m) {
            if (($m['_status'] ?? '') !== 'finished') continue;
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;
            $finished[] = $m;
        }

        $out = [
            'played'       => count($finished),
            'goals'        => 0,
            'avg'          => 0.0,
            'yellows'      => 0,
            'reds'         => 0,
            'attack'       => [],
            'defense'      => [],
            'high_scoring' => [],
            'big_wins'     => [],
            'by_city'      => [],
        ];

        if (!$finished) return $out;

        $team    = [];   // اسم إنجليزي => ['gf','ga','p']
        $city    = [];   // اسم الملعب/المدينة => مجموع الأهداف
        $scoring = [];   // لقائمتَي "أكثر أهدافاً" و"أكبر انتصار"

        foreach ($finished as $m) {
            $g1 = (int)$m['score']['ft'][0];
            $g2 = (int)$m['score']['ft'][1];
            $out['goals'] += $g1 + $g2;

            $t1 = trim($m['team1'] ?? '');
            $t2 = trim($m['team2'] ?? '');

            if (is_real_team($t1)) {
                $team[$t1] ??= ['gf' => 0, 'ga' => 0, 'p' => 0];
                $team[$t1]['gf'] += $g1;
                $team[$t1]['ga'] += $g2;
                $team[$t1]['p']++;
            }
            if (is_real_team($t2)) {
                $team[$t2] ??= ['gf' => 0, 'ga' => 0, 'p' => 0];
                $team[$t2]['gf'] += $g2;
                $team[$t2]['ga'] += $g1;
                $team[$t2]['p']++;
            }

            $ground = trim($m['ground'] ?? '');
            if ($ground !== '') {
                $city[$ground] = ($city[$ground] ?? 0) + $g1 + $g2;
            }

            if (!empty($m['cards']) && is_array($m['cards'])) {
                foreach ($m['cards'] as $c) {
                    if (!is_array($c)) continue;
                    if (($c['type'] ?? '') === 'red') { $out['reds']++; }
                    else { $out['yellows']++; }
                }
            }

            $scoring[] = [
                'index'  => (int)($m['_index'] ?? 0),
                't1'     => $t1,
                't2'     => $t2,
                'g1'     => $g1,
                'g2'     => $g2,
                'total'  => $g1 + $g2,
                'margin' => abs($g1 - $g2),
            ];
        }

        $out['avg'] = round($out['goals'] / max(1, $out['played']), 2);

        // المنتخبات كقائمة موحّدة
        $teams = [];
        foreach ($team as $en => $s) {
            $teams[] = ['team' => $en] + $s;
        }

        // الأكثر تسجيلاً: الأهداف (له) تنازلياً، ثم فارق الأهداف
        $attack = $teams;
        usort($attack, fn($a, $b) =>
            [$b['gf'], $b['gf'] - $b['ga']] <=> [$a['gf'], $a['gf'] - $a['ga']]);
        $out['attack'] = array_slice($attack, 0, 8);

        // أفضل دفاع: الأهداف (عليه) تصاعدياً، ثم الأكثر تسجيلاً
        $defense = $teams;
        usort($defense, fn($a, $b) =>
            [$a['ga'], $b['gf']] <=> [$b['ga'], $a['gf']]);
        $out['defense'] = array_slice($defense, 0, 8);

        // أكثر المباريات أهدافاً
        $hs = array_values(array_filter($scoring, fn($x) => $x['total'] > 0));
        usort($hs, fn($a, $b) => $b['total'] <=> $a['total']);
        $out['high_scoring'] = array_slice($hs, 0, 5);

        // أكبر الانتصارات (فارق الأهداف)
        $bw = array_values(array_filter($scoring, fn($x) => $x['margin'] > 0));
        usort($bw, fn($a, $b) => $b['margin'] <=> $a['margin']);
        $out['big_wins'] = array_slice($bw, 0, 5);

        // الأهداف حسب المدينة
        arsort($city);
        $out['by_city'] = array_slice($city, 0, 8, true);

        return $out;
    }
}
