<?php
/**
 * Bracket.php
 * ============================================================
 * ينظّم مباريات الأدوار الإقصائية في مراحل مرتّبة لعرض الشجرة.
 * يتعرّف على المراحل من اسم الـ round في بيانات openfootball.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Bracket
{
    /**
     * تعريف مراحل الإقصائيات وما يطابقها من أسماء الجولات.
     * openfootball قد يسمّيها: "Round of 32", "Round of 16",
     * "Quarter-finals", "Semi-finals", "Match for third place", "Final".
     */
    private static function stageDefs(): array
    {
        return [
            'round_of_32'    => ['Round of 32'],
            'round_of_16'    => ['Round of 16'],
            'quarter_finals' => ['Quarter-finals', 'Quarter-final', 'Quarterfinals'],
            'semi_finals'    => ['Semi-finals', 'Semi-final', 'Semifinals'],
            'third_place'    => ['Match for third place', 'Third place play-off',
                                 'Play-off for third place', 'Third Place'],
            'final'          => ['Final'],
        ];
    }

    /**
     * stages() — يرجّع المراحل الإقصائية مع مبارياتها.
     * النتيجة: [ 'round_of_16' => [matches...], 'final' => [...], ... ]
     * المراحل الفارغة تُحذف.
     */
    public static function stages(): array
    {
        $defs = self::stageDefs();
        $out  = [];

        foreach ($defs as $key => $names) {
            $matches = [];
            foreach (DataService::allMatches() as $m) {
                $round = trim($m['round'] ?? '');
                foreach ($names as $n) {
                    if (strcasecmp($round, $n) === 0) {
                        $matches[] = $m;
                        break;
                    }
                }
            }
            // ترتيب حسب الوقت
            usort($matches, fn($a, $b) =>
                (DataService::matchTimestamp($a) ?? 0) <=>
                (DataService::matchTimestamp($b) ?? 0));
            if ($matches) $out[$key] = $matches;
        }
        return $out;
    }

    /** هل بدأت الأدوار الإقصائية أصلاً؟ */
    public static function hasKnockout(): bool
    {
        return !empty(self::stages());
    }

    /** مباراة النهائي إن وُجدت */
    public static function finalMatch(): ?array
    {
        $s = self::stages();
        return $s['final'][0] ?? null;
    }

    /**
     * predictorData() — بنية شجرة الإقصائيات لميزة «توقّع المشوار».
     * لكل مباراة: رقمها (num) وخانتاها. الخانة إمّا:
     *   - seed: بطاقة دور الـ32 (تُحلّل من رمز المجموعة → المنتخبات المرشّحة)
     *   - win : الفائز من مباراة src    - lose: الخاسر من مباراة src
     * مُستقلّة عن النتائج: تعمل قبل البطولة (القرعة معروفة) وأثناءها.
     */
    public static function predictorData(): array
    {
        // حرف المجموعة → منتخباتها الحقيقية
        $byGroup = [];
        foreach (DataService::allTeams() as $team => $grp) {
            if (preg_match('/Group\s*([A-L])/i', $grp, $mm)) {
                $byGroup[strtoupper($mm[1])][] = $team;
            }
        }

        $teamObj = fn(string $en): array =>
            ['id' => $en, 'name' => team_name($en), 'flag' => flag_url($en, 'w40')];

        // رمز مثل "2A" أو "3A/B/C/D/F" → قائمة المنتخبات المرشّحة لتلك الخانة
        $candsFor = function (string $label) use ($byGroup, $teamObj): array {
            if (!preg_match('/^([1-3])\s*([A-L](?:\/[A-L])*)/i', $label, $m)) return [];
            $teams = [];
            foreach (preg_split('#/#', strtoupper($m[2])) as $L) {
                foreach ($byGroup[$L] ?? [] as $t) $teams[$t] = true;
            }
            $teams = array_keys($teams);
            sort($teams);
            return array_map($teamObj, $teams);
        };

        $slot = function (string $label) use ($candsFor): array {
            $label = trim($label);
            if (preg_match('/^W(\d+)$/i', $label, $m)) return ['type' => 'win',  'src' => (int)$m[1], 'label' => $label];
            if (preg_match('/^L(\d+)$/i', $label, $m)) return ['type' => 'lose', 'src' => (int)$m[1], 'label' => $label];
            return ['type' => 'seed', 'label' => $label, 'cands' => $candsFor($label)];
        };

        $stages = self::stages();
        $order  = ['round_of_32', 'round_of_16', 'quarter_finals', 'semi_finals', 'final', 'third_place'];
        $rounds = [];
        foreach ($order as $key) {
            if (empty($stages[$key])) continue;
            foreach ($stages[$key] as $m) {
                // النهائي وتحديد المركز الثالث بلا رقم في المصدر → أرقام صناعية فريدة
                $num = (int)($m['num'] ?? 0);
                if ($num <= 0) $num = ($key === 'final') ? 999 : 998;
                $rounds[$key][] = [
                    'num' => $num,
                    's1'  => $slot((string)($m['team1'] ?? '')),
                    's2'  => $slot((string)($m['team2'] ?? '')),
                ];
            }
        }
        return ['rounds' => $rounds];
    }
}
