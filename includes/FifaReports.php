<?php
/**
 * FifaReports.php — التقرير الرسمي بعد المباراة من المركز الفنّي لـFIFA (PMSR).
 * ============================================================
 * المصدر: صفحة hub رسميّة تسرد روابط PDF لكل مباراة:
 *   https://www.fifatrainingcentre.com/en/fifa-world-cup-2026/match-report-hub.php
 *   كل رابط: .../2026/PMSR-M{رقم} {رمز1} V {رمز2}.pdf  (التسمية غير متّسقة:
 *   مسافات أحياناً، شُرَط أحياناً، ومسافة زائدة أحياناً) → لذا نكشط الروابط
 *   الفعليّة ولا نبنيها بنمط ثابت.
 *
 * الآليّة (بلا مفتاح):
 *   1) كشط صفحة الـhub (كاش 6 ساعات) → خريطة [رقم المباراة → رابط PDF مطلق]
 *   2) الربط: FIFA M(n) ↔ فهرس مباراتنا (n-1)  [مؤكّد لمباريات دور المجموعات]
 *   3) أرشيف دائم لكل مباراة — الرابط يثبت فور إيجاده
 *
 * نعرض التقرير الرسمي مضمّناً (iframe) من رابط FIFA مباشرةً (بلا إعادة استضافة).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class FifaReports
{
    private const HUB_URL    = 'https://www.fifatrainingcentre.com/en/fifa-world-cup-2026/match-report-hub.php';
    // 🆕 لوحة الأدوار الإقصائيّة — أنشأتها FIFA منفصلة عن المجموعات (تقارير M73 فما فوق)
    private const HUB_URL_KO  = 'https://www.fifatrainingcentre.com/en/fifa-world-cup-2026/match-report-hub-knockout-stage.php';
    private const MEDIA_BASE = 'https://www.fifatrainingcentre.com';
    private const LIST_TTL   = 21600;   // 6 ساعات

    /**
     * forMatch($m) — رابط التقرير الرسمي للمباراة المنتهية، أو null إن لم يُنشَر بعد.
     */
    public static function forMatch(array $m): ?string
    {
        $finished = ($m['_status'] ?? '') === 'finished'
                  || (isset($m['score']['ft']) && is_array($m['score']['ft']));
        if (!$finished) return null;

        $key = md5(trim((string)($m['team1'] ?? '')) . '|' . trim((string)($m['team2'] ?? '')) . '|' . (string)($m['date'] ?? ''));
        $archive = rtrim(CACHE_DIR, '/') . '/fifa-report-' . $key . '.txt';

        // المصدر الموثوق أولاً: الربط برموز الفرق من اسم ملف التقرير (حتمي وصحيح).
        // يكتب النتيجة في الأرشيف فيُداوي أي رابط قديم خاطئ مثبَّت من زمن الترتيب الزمني.
        $n = self::numberForTeams((string)($m['team1'] ?? ''), (string)($m['team2'] ?? ''));
        if ($n > 0) {
            $url = self::reports()[(string)$n] ?? '';
            if ($url !== '') {
                if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
                @file_put_contents($archive, $url);
                return $url;
            }
        }

        // احتياطي فقط عند فشل الربط/الكشط: أرشيف سابق
        if (is_file($archive)) {
            $u = trim((string)@file_get_contents($archive));
            if ($u !== '') return $u;
        }
        return null;
    }

    /**
     * رقم المباراة عند FIFA = ترتيبها الزمني (1‑based) بين كل المباريات حسب وقت
     * الانطلاق. (فهرس openfootball ليس زمنيّاً، فلا يصلح.) تعيد -1 إن لم تُطابَق.
     * ملاحظة: المباريات المتزامنة (الجولة الأخيرة للمجموعات) قد تتبادل الترتيب —
     * عندها يمكن تثبيت الرابط يدويّاً عبر أرشيف fifa-report-*.txt.
     */
    private static function matchNumber(array $m): int
    {
        if (!class_exists('DataService')) return -1;
        $list = [];
        foreach (DataService::allMatches() as $mm) {
            $ts = DataService::matchTimestamp($mm);
            $list[] = [
                'ts'   => $ts ?? PHP_INT_MAX,
                't1'   => trim((string)($mm['team1'] ?? '')),
                't2'   => trim((string)($mm['team2'] ?? '')),
                'date' => (string)($mm['date'] ?? ''),
            ];
        }
        usort($list, fn($a, $b) => $a['ts'] <=> $b['ts']);

        $mt1 = trim((string)($m['team1'] ?? ''));
        $mt2 = trim((string)($m['team2'] ?? ''));
        $md  = (string)($m['date'] ?? '');
        foreach ($list as $i => $r) {
            if ($r['t1'] === $mt1 && $r['t2'] === $mt2 && $r['date'] === $md) return $i + 1;
        }
        return -1;
    }

    /**
     * codeMap() — [رقم التقرير → [iso1, iso2]] مستخرجة من اسم ملف الـPDF
     * (مثل «PMSR-M07-BRA-V-MAR» → [br, ma]). هذا الربط الصحيح بالفرق بدل الترتيب.
     */
    public static function codeMap(): array
    {
        $out = [];
        foreach (self::reports() as $n => $url) {
            $name = urldecode(basename((string)$url));
            if (preg_match('/PMSR-M\d+[\s_\-]+([A-Za-z]{3})[\s_\-]+V[\s_\-]+([A-Za-z]{3})/', $name, $mm)) {
                $i1 = function_exists('fifa_iso') ? fifa_iso($mm[1]) : '';
                $i2 = function_exists('fifa_iso') ? fifa_iso($mm[2]) : '';
                if ($i1 !== '' && $i2 !== '') $out[(string)$n] = [$i1, $i2];
            }
        }
        return $out;
    }

    /** رقم التقرير المطابق لفريقَي المباراة (مقارنة رموز غير مرتّبة)، أو -1. */
    public static function numberForTeams(string $t1, string $t2): int
    {
        $a = function_exists('team_flag') ? team_flag($t1) : '';
        $b = function_exists('team_flag') ? team_flag($t2) : '';
        if ($a === '' || $b === '') return -1;
        $want = [strtolower($a), strtolower($b)]; sort($want);
        foreach (self::codeMap() as $n => $pair) {
            $have = [strtolower($pair[0]), strtolower($pair[1])]; sort($have);
            if ($have === $want) return (int)$n;
        }
        return -1;
    }

    /** خريطة [رقم المباراة (string) → رابط PDF مطلق] — من الكاش أو بكشط جديد. */
    public static function reports(): array
    {
        $cacheFile = rtrim(CACHE_DIR, '/') . '/fifa-reports.json';
        $stored = [];
        if (is_file($cacheFile)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d)) $stored = $d;
            if (time() - filemtime($cacheFile) < self::LIST_TTL) return $stored;
        }

        // فشل قريب → لا تعاود الكشط مع كل طلب
        $fail = $cacheFile . '.fail';
        if (is_file($fail) && (time() - filemtime($fail) < 1800)) return $stored;

        // 🆕 اكشط اللوحتين: دور المجموعات + دور الإقصائيّات (لوحة منفصلة)
        $out = $stored;   // القائمة تكبر فقط — أبقِ ما سبق
        $reached = false; $found = false;
        foreach ([self::HUB_URL, self::HUB_URL_KO] as $hub) {
            $html = self::httpGet($hub);
            if ($html === '') continue;
            $reached = true;
            if (!preg_match_all('/href="([^"]*PMSR-M(\d+)[^"]*\.pdf)"/i', $html, $mm, PREG_SET_ORDER)) continue;
            foreach ($mm as $row) {
                $path = trim($row[1]);
                $n    = (int)$row[2];
                if ($n <= 0 || $path === '') continue;
                if (stripos($path, 'http') !== 0) $path = self::MEDIA_BASE . '/' . ltrim($path, '/');
                $out[(string)$n] = str_replace(' ', '%20', $path);   // ترميز المسافات
                $found = true;
            }
        }
        if (!$reached || !$found) {
            @touch($fail);
            return $stored;
        }
        ksort($out, SORT_NUMERIC);

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        $tmp = $cacheFile . '.tmp';
        if (@file_put_contents($tmp, json_encode($out, JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $cacheFile);
        }
        @unlink($fail);
        return $out;
    }

    /** جلب HTML بسيط (User-Agent متصفّح — صفحة الـhub تتطلّبه). */
    private static function httpGet(string $url): string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                       . "Accept-Language: en,ar;q=0.8\r\n",
            'timeout' => 14,
        ]]);
        $html = @file_get_contents($url, false, $ctx);
        return is_string($html) ? $html : '';
    }
}
