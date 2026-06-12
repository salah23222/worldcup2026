<?php
/**
 * Referees.php
 * ============================================================
 * قائمة حكام كأس العالم 2026 (تحرير يدوي من مالك الموقع).
 *
 * لماذا يدوي؟
 *   لا يوجد API مجاني موثوق يعطي قائمة حكام المونديال الرسمية
 *   أو تقييماتهم. لذلك يُملأ data/referees.json يدوياً عندما
 *   يُعلن FIFA الأسماء — ولا نختلق أي اسم إطلاقاً.
 *
 * المصدر: data/referees.json — مصفوفة من الكائنات:
 *   {"name":"...","country_ar":"...","country_en":"...","flag":"iso2"}
 *   (راجع data/referees.README.txt لشرح الشكل)
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Referees
{
    /** مسار ملف البيانات اليدوي */
    private static function dataPath(): string
    {
        return __DIR__ . '/../data/referees.json';
    }

    /**
     * all() — يقرأ قائمة الحكام من data/referees.json.
     * يُرجع [] بأمان إذا كان الملف غير موجود أو فارغاً أو غير صالح.
     */
    public static function all(): array
    {
        $f = self::dataPath();
        if (!is_file($f)) return [];
        $raw = @file_get_contents($f);
        if ($raw === false || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** حكم واحد بترتيبه في القائمة (للصفحة التفصيلية referee.php) */
    public static function byIndex(int $i): ?array
    {
        $all = self::all();
        return $all[$i] ?? null;
    }

    /** tokens لاتينية صغيرة (≥3 أحرف) — للمطابقة عبر اختلاف صيَغ الأسماء. */
    private static function nameTokens(string $name): array
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[^a-z\s]/', ' ', $name);
        $toks = preg_split('/\s+/', trim((string)$name)) ?: [];
        return array_values(array_filter($toks, fn($t) => strlen($t) >= 3));
    }

    /**
     * sameRef() — هل الاسمان لنفس الحكم؟
     * «WILTON SAMPAIO» تطابق «Wilton Pereira Sampaio» (تقاطع كلمتَين)،
     * و«Ma Ning» تطابق «Ning Ma» (لقب واحد لكلَيهما).
     */
    public static function sameRef(string $a, string $b): bool
    {
        $ta = self::nameTokens($a);
        $tb = self::nameTokens($b);
        if (!$ta || !$tb) return false;
        $i = count(array_intersect($ta, $tb));
        return $i >= 2 || ($i >= 1 && count($ta) === 1 && count($tb) === 1);
    }

    /**
     * matchesOfficiated() — عدد مباريات البطولة التي أدارها هذا الحكم.
     * مطابقة ذكيّة عبر sameRef (الأسماء تأتي بصيَغ مختلفة من المصادر).
     */
    public static function matchesOfficiated(string $name): int
    {
        return count(self::matchesFor($name));
    }

    /** قائمة مباريات البطولة التي أُسنِدت لهذا الحكم (تظهر عند إعلان التعيينات) */
    public static function matchesFor(string $name): array
    {
        $name = trim($name);
        if ($name === '') return [];
        $out = [];
        foreach (DataService::allMatches() as $m) {
            $r = trim((string)($m['referee'] ?? ''));
            if ($r !== '' && self::sameRef($r, $name)) $out[] = $m;
        }
        return $out;
    }

    /**
     * tournamentStats() — إحصائيات تحكيميّة محسوبة من بيانات مبارياتنا
     * (لا يوجد مصدر مجاني موثوق لتقييم الحكام — نحسبها بأنفسنا من الواقع):
     *   لكل حكم: مباريات أدارها · 🟨 · 🟥 · ركلات جزاء احتسبها · معدّل البطاقات.
     */
    public static function tournamentStats(): array
    {
        static $memo = null;
        if ($memo !== null) return $memo;

        $rows = [];
        foreach (DataService::allMatches() as $m) {
            if (($m['_status'] ?? '') !== 'finished') continue;
            $ref = trim((string)($m['referee'] ?? ''));
            if ($ref === '') continue;

            $idx = null;
            foreach ($rows as $k => $r) {
                if (self::sameRef($ref, $r['name'])) { $idx = $k; break; }
            }
            if ($idx === null) {
                $rows[] = ['name' => $ref, 'matches' => 0, 'yellow' => 0, 'red' => 0, 'pens' => 0];
                $idx = array_key_last($rows);
            } elseif (mb_strlen($ref, 'UTF-8') > mb_strlen($rows[$idx]['name'], 'UTF-8')) {
                $rows[$idx]['name'] = $ref;   // فضّل الصيغة الأكمل للعرض
            }

            $rows[$idx]['matches']++;
            foreach (($m['cards'] ?? []) as $c) {
                (($c['type'] ?? '') === 'red') ? $rows[$idx]['red']++ : $rows[$idx]['yellow']++;
            }
            foreach ([($m['goals1'] ?? []), ($m['goals2'] ?? [])] as $side) {
                foreach ((array)$side as $g) {
                    if (!empty($g['penalty'])) $rows[$idx]['pens']++;
                }
            }
        }

        usort($rows, fn($a, $b) =>
            [$b['matches'], $b['yellow'] + $b['red']] <=> [$a['matches'], $a['yellow'] + $a['red']]);
        return $memo = $rows;
    }

    /** إحصائيات حكم واحد (بمطابقة ذكيّة) أو null لو لم يُدِر مباراة منتهية بعد. */
    public static function statsFor(string $name): ?array
    {
        foreach (self::tournamentStats() as $r) {
            if (self::sameRef($name, $r['name'])) return $r;
        }
        return null;
    }

    /**
     * يحوّل اسم الحكم بصيغة "SURNAME Given" إلى عنوان ويكيبيديا "Given Surname".
     * (اللقب بأحرف كبيرة يأتي أولاً في القائمة الرسمية، والاسم الأول بعده.)
     */
    public static function wikiTitleGuess(string $name): string
    {
        $tokens = preg_split('/\s+/', trim($name)) ?: [];
        $surname = [];
        $given   = [];
        foreach ($tokens as $tk) {
            if ($tk === '') continue;
            // اللقب = الأجزاء المكتوبة بأحرف كبيرة بالكامل
            if (mb_strtoupper($tk, 'UTF-8') === $tk && mb_strlen($tk, 'UTF-8') > 1) {
                $surname[] = mb_convert_case($tk, MB_CASE_TITLE, 'UTF-8');
            } else {
                $given[] = $tk;
            }
        }
        $ordered = array_merge($given, $surname);
        if (!$ordered) $ordered = array_map(
            fn($t) => mb_convert_case($t, MB_CASE_TITLE, 'UTF-8'), $tokens);
        return trim(implode(' ', $ordered));
    }

    /**
     * profile() — نبذة وصورة الحكم من ويكيبيديا (best-effort، مُخزّنة 30 يوماً).
     * يجرّب لغة الزائر أولاً ثم الإنجليزية. يرجّع [] عند أي تعثّر (الصفحة تعمل بدونها).
     *
     * @return array{photo?:string, bio?:string, url?:string, src?:string}
     */
    public static function profile(string $name, string $lang = 'en'): array
    {
        $name = trim($name);
        if ($name === '') return [];

        $cacheFile = rtrim(CACHE_DIR, '/') . '/ref_' . $lang . '_' . md5($name) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d)) return $d;
        }

        $guess  = self::wikiTitleGuess($name);
        $result = [];
        $tryLangs = $lang === 'ar' ? ['ar', 'en'] : ['en'];
        foreach ($tryLangs as $wl) {
            $title = self::wikiSearch($wl, $guess);
            if ($title === '') continue;
            $sum = self::wikiSummary($wl, $title);
            if (!$sum) continue;
            $extract = (string)($sum['extract'] ?? '');
            // تحقّق بسيط أنّ الصفحة عن حكم/كرة قدم (يتجنّب المطابقة الخاطئة)
            $ok = ($sum['type'] ?? '') === 'standard'
                && $extract !== ''
                && preg_match('/referee|football|soccer|حكم|كرة القدم/iu', $extract);
            if (!$ok) continue;
            $result = [
                'bio'   => $extract,
                'photo' => (string)($sum['thumbnail']['source'] ?? ($sum['originalimage']['source'] ?? '')),
                'url'   => (string)($sum['content_urls']['desktop']['page'] ?? ''),
                'src'   => $wl,
            ];
            break;
        }

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    /** يبحث في ويكيبيديا عن أفضل عنوان صفحة مطابق للاسم. */
    private static function wikiSearch(string $wl, string $query): string
    {
        $endpoint = "https://{$wl}.wikipedia.org/w/api.php?" . http_build_query([
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $query . ' referee',
            'srlimit'  => 1,
            'format'   => 'json',
        ]);
        $data = self::httpJson($endpoint);
        return (string)($data['query']['search'][0]['title'] ?? '');
    }

    /** يجلب ملخّص صفحة ويكيبيديا (REST summary: نبذة + صورة). */
    private static function wikiSummary(string $wl, string $title): ?array
    {
        $endpoint = "https://{$wl}.wikipedia.org/api/rest_v1/page/summary/"
                  . rawurlencode(str_replace(' ', '_', $title));
        $data = self::httpJson($endpoint);
        return is_array($data) ? $data : null;
    }

    /** طلب HTTP قصير المهلة يُرجِع JSON مفكوكاً أو [] عند الفشل. */
    private static function httpJson(string $url): array
    {
        $raw = http_get($url, ['ua' => 'WorldCup2026Site/1.0 (referees)', 'timeout' => 3]);
        if ($raw === null) return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
