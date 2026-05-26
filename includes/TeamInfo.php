<?php
/**
 * TeamInfo.php — نبذة وشعار المنتخب من ويكيبيديا (best-effort، مُخزّنة 30 يوماً).
 * يجرّب لغة الزائر أولاً (بالاسم المترجَم) ثم الإنجليزية. يرجّع [] عند أي تعثّر.
 * نفس فلسفة Referees::profile — لا يكسر الصفحة إطلاقاً عند الفشل.
 */
if (!defined('WC2026')) { exit('Access denied'); }

class TeamInfo
{
    /** @return array{bio?:string, crest?:string, url?:string, src?:string} */
    public static function about(string $teamEn, string $lang = 'en'): array
    {
        $teamEn = trim($teamEn);
        if ($teamEn === '') return [];

        $cacheFile = rtrim(CACHE_DIR, '/') . '/team_' . $lang . '_' . md5($teamEn) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 2592000)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d)) return $d;
        }

        $result = [];
        // قائمة المحاولات: (لغة ويكيبيديا, نص البحث)
        $tries = [];
        if ($lang === 'ar') {
            $arName = function_exists('team_name') ? team_name($teamEn) : $teamEn;
            $tries[] = ['ar', 'منتخب ' . $arName . ' لكرة القدم'];
        }
        $tries[] = ['en', $teamEn . ' national football team'];

        foreach ($tries as [$wl, $query]) {
            $title = self::wikiSearch($wl, $query);
            if ($title === '') continue;
            $sum = self::wikiSummary($wl, $title);
            if (!$sum) continue;
            $extract = (string)($sum['extract'] ?? '');
            $ok = ($sum['type'] ?? '') === 'standard'
                && $extract !== ''
                && preg_match('/national (football|soccer) team|منتخب|كرة القدم|football|soccer/iu', $extract);
            if (!$ok) continue;
            $result = [
                'bio'   => $extract,
                'crest' => (string)($sum['thumbnail']['source'] ?? ''),
                'url'   => (string)($sum['content_urls']['desktop']['page'] ?? ''),
                'src'   => $wl,
            ];
            break;
        }

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
        return $result;
    }

    private static function wikiSearch(string $wl, string $query): string
    {
        $endpoint = "https://{$wl}.wikipedia.org/w/api.php?" . http_build_query([
            'action' => 'query', 'list' => 'search', 'srsearch' => $query,
            'srlimit' => 1, 'format' => 'json',
        ]);
        $d = self::httpJson($endpoint);
        return (string)($d['query']['search'][0]['title'] ?? '');
    }

    private static function wikiSummary(string $wl, string $title): ?array
    {
        $endpoint = "https://{$wl}.wikipedia.org/api/rest_v1/page/summary/"
                  . rawurlencode(str_replace(' ', '_', $title));
        $d = self::httpJson($endpoint);
        return is_array($d) ? $d : null;
    }

    private static function httpJson(string $url): array
    {
        $raw = http_get($url, ['ua' => 'WorldCup2026Site/1.0 (teams)', 'timeout' => 3]);
        if ($raw === null) return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
