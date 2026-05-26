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

    /**
     * matchesOfficiated() — عدد مباريات البطولة التي أدارها هذا الحكم.
     * يُحسب تلقائياً بمطابقة $m['referee'] حرفياً مع اسم الحكم.
     */
    public static function matchesOfficiated(string $name): int
    {
        if ($name === '') return 0;
        $count = 0;
        foreach (DataService::allMatches() as $m) {
            if (($m['referee'] ?? '') === $name) {
                $count++;
            }
        }
        return $count;
    }

    /** قائمة مباريات البطولة التي أُسنِدت لهذا الحكم (تظهر عند إعلان التعيينات) */
    public static function matchesFor(string $name): array
    {
        if ($name === '') return [];
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (($m['referee'] ?? '') === $name) $out[] = $m;
        }
        return $out;
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
