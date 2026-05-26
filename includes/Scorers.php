<?php
/**
 * Scorers.php
 * ============================================================
 * هدّافو البطولة:
 *   1. current()           — يجمع أهداف اللاعبين من مباريات openfootball الفعلية
 *   2. goldenBootHistory() — قائمة ثابتة لهدّافي نسخ كأس العالم السابقة (حقائق عامة)
 *   3. photo()             — صورة اللاعب من Wikimedia Commons (مجانية CC) مع كاش
 *
 * قاعدة ذهبية: لا نختلق أي هدّاف. الأهداف تأتي حصراً من حقلي
 * goals1/goals2 في بيانات openfootball، وهي فارغة قبل انطلاق البطولة.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Scorers
{
    /**
     * current() — يجمع الأهداف عبر كل المباريات.
     * لكل لاعب: عدد الأهداف + المنتخب الذي يلعب له (إنجليزي).
     * يتجاهل الأهداف العكسية (owngoal). يرتّب تنازلياً حسب عدد الأهداف.
     *
     * @return array<int, array{name:string, team:string, goals:int}>
     */
    public static function current(): array
    {
        $tally = [];   // "name|team" => ['name'=>, 'team'=>, 'goals'=>]

        foreach (DataService::allMatches() as $m) {
            // goals1 → team1 ، goals2 → team2
            $pairs = [
                [$m['goals1'] ?? null, $m['team1'] ?? ''],
                [$m['goals2'] ?? null, $m['team2'] ?? ''],
            ];
            foreach ($pairs as [$goals, $team]) {
                if (!is_array($goals)) continue;
                $team = trim((string)$team);
                foreach ($goals as $g) {
                    if (!is_array($g)) continue;
                    if (!empty($g['owngoal'])) continue;          // لا نحسب الهدف العكسي
                    $name = trim((string)($g['name'] ?? ''));
                    if ($name === '') continue;
                    $key = $name . '|' . $team;
                    if (!isset($tally[$key])) {
                        $tally[$key] = ['name' => $name, 'team' => $team, 'goals' => 0];
                    }
                    $tally[$key]['goals']++;
                }
            }
        }

        $rows = array_values($tally);
        // الأكثر تهديفاً أولاً، ثم اسم اللاعب أبجدياً عند التساوي
        usort($rows, function ($a, $b) {
            return ($b['goals'] <=> $a['goals'])
                ?: strcasecmp($a['name'], $b['name']);
        });
        return $rows;
    }

    /**
     * goldenBootHistory() — الحذاء الذهبي عبر تاريخ كأس العالم (حقائق عامة موثّقة).
     * عند تساوي الهدّافين في نسخة، نُدرج الأبرز أو نُجمّع الأسماء.
     *
     * @return array<int, array{year:int, name:string, country_ar:string, country_en:string, flag:string, goals:int}>
     */
    public static function goldenBootHistory(): array
    {
        return [
            ['year' => 2022, 'name' => 'Kylian Mbappé',     'country_ar' => 'فرنسا',     'country_en' => 'France',    'flag' => 'fr', 'goals' => 8],
            ['year' => 2018, 'name' => 'Harry Kane',        'country_ar' => 'إنجلترا',   'country_en' => 'England',   'flag' => 'gb-eng', 'goals' => 6],
            ['year' => 2014, 'name' => 'James Rodríguez',   'country_ar' => 'كولومبيا',  'country_en' => 'Colombia',  'flag' => 'co', 'goals' => 6],
            ['year' => 2010, 'name' => 'Thomas Müller',     'country_ar' => 'ألمانيا',   'country_en' => 'Germany',   'flag' => 'de', 'goals' => 5],
            ['year' => 2006, 'name' => 'Miroslav Klose',    'country_ar' => 'ألمانيا',   'country_en' => 'Germany',   'flag' => 'de', 'goals' => 5],
            ['year' => 2002, 'name' => 'Ronaldo',           'country_ar' => 'البرازيل',  'country_en' => 'Brazil',    'flag' => 'br', 'goals' => 8],
            ['year' => 1998, 'name' => 'Davor Šuker',       'country_ar' => 'كرواتيا',   'country_en' => 'Croatia',   'flag' => 'hr', 'goals' => 6],
            ['year' => 1994, 'name' => 'Oleg Salenko',      'country_ar' => 'روسيا',     'country_en' => 'Russia',    'flag' => 'ru', 'goals' => 6],
            ['year' => 1990, 'name' => 'Salvatore Schillaci','country_ar' => 'إيطاليا',  'country_en' => 'Italy',     'flag' => 'it', 'goals' => 6],
            ['year' => 1986, 'name' => 'Gary Lineker',      'country_ar' => 'إنجلترا',   'country_en' => 'England',   'flag' => 'gb-eng', 'goals' => 6],
            ['year' => 1982, 'name' => 'Paolo Rossi',       'country_ar' => 'إيطاليا',   'country_en' => 'Italy',     'flag' => 'it', 'goals' => 6],
            ['year' => 1978, 'name' => 'Mario Kempes',      'country_ar' => 'الأرجنتين', 'country_en' => 'Argentina', 'flag' => 'ar', 'goals' => 6],
        ];
    }

    /**
     * photo() — يحاول جلب صورة مصغّرة للاعب من Wikimedia Commons (مجانية / CC).
     * النتيجة (رابط الصورة أو '' عند الفشل) تُخزّن في cache/ لكل اسم لتفادي تكرار الجلب.
     * best-effort: مهلة قصيرة جداً حتى لا تُبطئ الصفحة؛ يرجّع '' عند أي تعثّر.
     *
     * @return string رابط الصورة أو '' (والمستدعي يستخدم العلم كبديل)
     */
    public static function photo(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';

        $cacheFile = rtrim(CACHE_DIR, '/') . '/scorer_' . md5($name) . '.json';
        // كاش: صالح لمدة 30 يوماً (الصور لا تتغيّر)
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d) && array_key_exists('url', $d)) {
                return (string)$d['url'];
            }
        }

        $url = self::fetchWikimediaThumb($name);

        // خزّن النتيجة حتى لو كانت '' (cache negative) لتفادي إعادة الجلب الفاشل
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($cacheFile, json_encode(['url' => $url], JSON_UNESCAPED_UNICODE));
        return $url;
    }

    /**
     * يستعلم Wikimedia Commons عن صورة مصغّرة عنوانها = اسم اللاعب.
     * يستخدم MediaWiki API: pageimages → thumbnail. مهلة قصيرة (3ث).
     */
    private static function fetchWikimediaThumb(string $name): string
    {
        // ويكيبيديا الإنجليزية أغنى بصور اللاعبين وأكثرها CC
        $endpoint = 'https://en.wikipedia.org/w/api.php?'
            . http_build_query([
                'action'      => 'query',
                'titles'      => $name,
                'prop'        => 'pageimages',
                'piprop'      => 'thumbnail',
                'pithumbsize' => 200,
                'redirects'   => 1,
                'format'      => 'json',
            ]);

        $raw = http_get($endpoint, ['ua' => 'WorldCup2026Site/1.0 (top scorers)', 'timeout' => 3]);
        if ($raw === null) return '';

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) return '';
        $pages = $data['query']['pages'] ?? [];
        if (!is_array($pages)) return '';
        foreach ($pages as $page) {
            $src = $page['thumbnail']['source'] ?? '';
            if (is_string($src) && preg_match('#^https?://#i', $src)) {
                return $src;
            }
        }
        return '';
    }
}
