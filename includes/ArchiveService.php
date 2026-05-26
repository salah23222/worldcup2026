<?php
/**
 * ArchiveService.php
 * ============================================================
 * يجلب نتائج كؤوس العالم السابقة من openfootball (نفس المصدر المجاني)
 * ويخزّنها مؤقتاً. البطولات السابقة لا تتغيّر، فالكاش طويل المدى.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class ArchiveService
{
    /** كاش طويل (30 يوماً) — البطولات المنتهية ثابتة */
    private const TTL = 2592000;

    /** ذاكرة الطلب الواحد */
    private static array $mem = [];

    /** السنوات المتاحة (الأحدث أولاً) */
    public static function years(): array
    {
        return [2022, 2018];
    }

    public static function isValidYear($year): bool
    {
        return in_array((int)$year, self::years(), true);
    }

    /** يحمّل بيانات سنة معيّنة (كاش → شبكة → كاش قديم → فارغ) */
    public static function load(int $year): array
    {
        if (!self::isValidYear($year)) {
            return ['matches' => []];
        }
        if (isset(self::$mem[$year])) {
            return self::$mem[$year];
        }

        $file  = rtrim(CACHE_DIR, '/') . "/worldcup{$year}.json";
        $fresh = is_file($file) && (time() - filemtime($file) < self::TTL);

        if ($fresh) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (is_array($d)) return self::$mem[$year] = $d;
        }

        $url = "https://raw.githubusercontent.com/openfootball/worldcup.json/master/{$year}/worldcup.json";
        $raw = self::fetch($url);
        if ($raw !== null) {
            $d = json_decode($raw, true);
            if (is_array($d) && isset($d['matches'])) {
                if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
                @file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE));
                return self::$mem[$year] = $d;
            }
        }

        // فشل الجلب → كاش قديم إن وُجد
        if (is_file($file)) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (is_array($d)) return self::$mem[$year] = $d;
        }

        return self::$mem[$year] = ['matches' => []];
    }

    /** كل مباريات سنة، مع _index و_status */
    public static function matches(int $year): array
    {
        $d   = self::load($year);
        $out = [];
        $i   = 0;
        foreach ($d['matches'] ?? [] as $m) {
            $m['_index']  = $i++;
            $m['_status'] = isset($m['score']['ft']) ? 'finished' : 'upcoming';
            $out[] = $m;
        }
        return $out;
    }

    /** المباريات مجمّعة حسب الجولة (بالترتيب الأصلي) */
    public static function byRound(int $year): array
    {
        $rounds = [];
        foreach (self::matches($year) as $m) {
            $r = $m['round'] ?? '—';
            $rounds[$r][] = $m;
        }
        return $rounds;
    }

    /** بطل تلك النسخة (الفائز بالمباراة النهائية) أو null */
    public static function champion(int $year): ?string
    {
        foreach (self::matches($year) as $m) {
            if (strcasecmp(trim($m['round'] ?? ''), 'Final') !== 0) continue;
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;
            [$g1, $g2] = $m['score']['ft'];
            // عند التعادل نحتكم لركلات الترجيح إن وُجدت
            if ($g1 === $g2 && isset($m['score']['p']) && is_array($m['score']['p'])) {
                [$p1, $p2] = $m['score']['p'];
                return ($p1 >= $p2) ? trim($m['team1'] ?? '') : trim($m['team2'] ?? '');
            }
            return ($g1 >= $g2) ? trim($m['team1'] ?? '') : trim($m['team2'] ?? '');
        }
        return null;
    }

    /**
     * allChampions() — سجلّ كل أبطال كأس العالم (1930 → 2022).
     * بيانات تاريخية عامة. كل صف:
     *   year, host, winner[en, ar, flag], runner[en, ar, flag], score
     */
    public static function allChampions(): array
    {
        $C = fn($en, $ar, $flag) => ['en' => $en, 'ar' => $ar, 'flag' => $flag];
        return [
            ['year'=>2022,'host'=>'قطر','host_en'=>'Qatar','winner'=>$C('Argentina','الأرجنتين','ar'),'runner'=>$C('France','فرنسا','fr'),'score'=>'3–3 (4–2 ركلات)'],
            ['year'=>2018,'host'=>'روسيا','host_en'=>'Russia','winner'=>$C('France','فرنسا','fr'),'runner'=>$C('Croatia','كرواتيا','hr'),'score'=>'4–2'],
            ['year'=>2014,'host'=>'البرازيل','host_en'=>'Brazil','winner'=>$C('Germany','ألمانيا','de'),'runner'=>$C('Argentina','الأرجنتين','ar'),'score'=>'1–0'],
            ['year'=>2010,'host'=>'جنوب أفريقيا','host_en'=>'South Africa','winner'=>$C('Spain','إسبانيا','es'),'runner'=>$C('Netherlands','هولندا','nl'),'score'=>'1–0'],
            ['year'=>2006,'host'=>'ألمانيا','host_en'=>'Germany','winner'=>$C('Italy','إيطاليا','it'),'runner'=>$C('France','فرنسا','fr'),'score'=>'1–1 (5–3 ركلات)'],
            ['year'=>2002,'host'=>'كوريا واليابان','host_en'=>'Korea/Japan','winner'=>$C('Brazil','البرازيل','br'),'runner'=>$C('Germany','ألمانيا','de'),'score'=>'2–0'],
            ['year'=>1998,'host'=>'فرنسا','host_en'=>'France','winner'=>$C('France','فرنسا','fr'),'runner'=>$C('Brazil','البرازيل','br'),'score'=>'3–0'],
            ['year'=>1994,'host'=>'الولايات المتحدة','host_en'=>'USA','winner'=>$C('Brazil','البرازيل','br'),'runner'=>$C('Italy','إيطاليا','it'),'score'=>'0–0 (3–2 ركلات)'],
            ['year'=>1990,'host'=>'إيطاليا','host_en'=>'Italy','winner'=>$C('West Germany','ألمانيا الغربية','de'),'runner'=>$C('Argentina','الأرجنتين','ar'),'score'=>'1–0'],
            ['year'=>1986,'host'=>'المكسيك','host_en'=>'Mexico','winner'=>$C('Argentina','الأرجنتين','ar'),'runner'=>$C('West Germany','ألمانيا الغربية','de'),'score'=>'3–2'],
            ['year'=>1982,'host'=>'إسبانيا','host_en'=>'Spain','winner'=>$C('Italy','إيطاليا','it'),'runner'=>$C('West Germany','ألمانيا الغربية','de'),'score'=>'3–1'],
            ['year'=>1978,'host'=>'الأرجنتين','host_en'=>'Argentina','winner'=>$C('Argentina','الأرجنتين','ar'),'runner'=>$C('Netherlands','هولندا','nl'),'score'=>'3–1'],
            ['year'=>1974,'host'=>'ألمانيا الغربية','host_en'=>'West Germany','winner'=>$C('West Germany','ألمانيا الغربية','de'),'runner'=>$C('Netherlands','هولندا','nl'),'score'=>'2–1'],
            ['year'=>1970,'host'=>'المكسيك','host_en'=>'Mexico','winner'=>$C('Brazil','البرازيل','br'),'runner'=>$C('Italy','إيطاليا','it'),'score'=>'4–1'],
            ['year'=>1966,'host'=>'إنجلترا','host_en'=>'England','winner'=>$C('England','إنجلترا','gb-eng'),'runner'=>$C('West Germany','ألمانيا الغربية','de'),'score'=>'4–2'],
            ['year'=>1962,'host'=>'تشيلي','host_en'=>'Chile','winner'=>$C('Brazil','البرازيل','br'),'runner'=>$C('Czechoslovakia','تشيكوسلوفاكيا','cz'),'score'=>'3–1'],
            ['year'=>1958,'host'=>'السويد','host_en'=>'Sweden','winner'=>$C('Brazil','البرازيل','br'),'runner'=>$C('Sweden','السويد','se'),'score'=>'5–2'],
            ['year'=>1954,'host'=>'سويسرا','host_en'=>'Switzerland','winner'=>$C('West Germany','ألمانيا الغربية','de'),'runner'=>$C('Hungary','المجر','hu'),'score'=>'3–2'],
            ['year'=>1950,'host'=>'البرازيل','host_en'=>'Brazil','winner'=>$C('Uruguay','الأوروغواي','uy'),'runner'=>$C('Brazil','البرازيل','br'),'score'=>'2–1'],
            ['year'=>1938,'host'=>'فرنسا','host_en'=>'France','winner'=>$C('Italy','إيطاليا','it'),'runner'=>$C('Hungary','المجر','hu'),'score'=>'4–2'],
            ['year'=>1934,'host'=>'إيطاليا','host_en'=>'Italy','winner'=>$C('Italy','إيطاليا','it'),'runner'=>$C('Czechoslovakia','تشيكوسلوفاكيا','cz'),'score'=>'2–1'],
            ['year'=>1930,'host'=>'الأوروغواي','host_en'=>'Uruguay','winner'=>$C('Uruguay','الأوروغواي','uy'),'runner'=>$C('Argentina','الأرجنتين','ar'),'score'=>'4–2'],
        ];
    }

    /** عدد الألقاب لكل منتخب (الأكثر تتويجاً أولاً).
     *  ملاحظة: الفيفا يحتسب ألمانيا الغربية ضمن ألمانيا (4 ألقاب). */
    public static function titleCounts(): array
    {
        $counts = [];
        foreach (self::allChampions() as $c) {
            $w = $c['winner'];
            // دمج ألمانيا الغربية مع ألمانيا (مطابقة للفيفا)
            $isGermany = in_array($w['en'], ['Germany', 'West Germany'], true);
            $key = $isGermany ? 'Germany' : $w['en'];
            $ar  = $isGermany ? 'ألمانيا' : $w['ar'];
            if (!isset($counts[$key])) {
                $counts[$key] = ['ar' => $ar, 'flag' => $w['flag'], 'titles' => 0];
            }
            $counts[$key]['titles']++;
        }
        uasort($counts, fn($a, $b) => $b['titles'] <=> $a['titles']);
        return array_values($counts);
    }

    /** جلب بسيط بمهلة صارمة بلا تراكم (راجع http_get في helpers.php). */
    private static function fetch(string $url): ?string
    {
        return http_get($url);
    }
}
