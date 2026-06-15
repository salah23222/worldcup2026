<?php
/**
 * Highlights.php — ملخّصات الفيديو الرسميّة (beIN SPORTS عبر قائمة يوتيوب).
 * ============================================================
 * المصدر: قائمة تشغيل يوتيوب (HIGHLIGHTS_PLAYLIST في config) تُحدَّث بعد
 * كل مباراة بفيديو ملخّص عنوانه عربي.
 *
 * الآليّة — المطابقة بالأسماء (موثوقة ذاتيّاً لكل مباراة):
 *   1) كشط صفحة القائمة (كاش 30 دقيقة) → معرّفات الفيديو
 *   2) عنوان كل فيديو جديد عبر oEmbed (يُخزَّن للأبد — طلب واحد لكل فيديو)
 *   3) مطابقة: عنوان الفيديو يحوي اسمَي الفريقين (توحيد همزات + إسقاط «ال» + مرادفات)
 *   4) أرشيف دائم لكل مباراة — المطابقة تتمّ مرّة واحدة
 *   5) PINNED = تثبيت يدوي يتجاوز كل شيء (مهرب أمان)
 *
 * ⚠️ لا تستخدم الترتيب الزمني للمطابقة: رفع beIN غير مرتّب وقد تغيب مباريات،
 *    فالربط بالترتيب يضع فيديو مباراة على صفحة مباراة أخرى. الأسماء ذاتيّة التصحيح.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Highlights
{
    /** كاش قائمة الفيديوهات بالدقائق (يلتقط الملخّص الجديد خلال ≤30 دقيقة). */
    private const LIST_TTL = 1800;

    /**
     * تثبيت يدوي للملخّصات (مهرب أمان عند خطأ في الترتيب الزمني).
     * المفتاح = md5 لاسمَي الفريقين بالإنجليزية، حروفاً صغيرة بلا رموز (team1team2).
     * القيمة = معرّف فيديو يوتيوب. أضِف سطراً ليتجاوز الترتيب الآليّ والأرشيف نهائياً.
     *   مثال: 'مفتاح' => 'SsNRplytmvI'
     */
    private const PINNED = [
        // 'md5key' => 'YouTubeVideoId',
    ];

    private static function playlistId(): string
    {
        return defined('HIGHLIGHTS_PLAYLIST') && HIGHLIGHTS_PLAYLIST !== ''
            ? HIGHLIGHTS_PLAYLIST
            : 'PLczz3UIGL1Xro9H31oiYmQviSBosVdclk';   // beIN SPORTS — كأس العالم 2026 الرسمية
    }

    // ────────────────────────────────────────────────────────
    //  الواجهة الرئيسية
    // ────────────────────────────────────────────────────────

    /** مرادفات عربيّة لفرق تُكتب بأسماء مختلفة في عناوين beIN. */
    private const ALIAS_AR = [
        'USA'            => ['امريكا', 'اميركا', 'الولايات المتحده'],
        'United States'  => ['امريكا', 'اميركا', 'الولايات المتحده'],
        'South Korea'    => ['كوريا الجنوبيه'],
        'Czech Republic' => ['تشيكيا', 'تشيك'],
        'Ivory Coast'    => ['كوت ديفوار', 'ساحل العاج'],
        'Cape Verde'     => ['كاب فيردي', 'الراس الاخضر'],
    ];

    /**
     * forMatch($m) — فيديو ملخّص مباراة (للمباريات المنتهية)، بالمطابقة بالأسماء.
     * يعيد ['id' => youtubeId, 'title' => string] أو null لو لم يُنشَر بعد.
     */
    public static function forMatch(array $m): ?array
    {
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return null;

        $key = strtolower(preg_replace('/[^a-z]/i', '', $t1 . $t2));

        // (0) تثبيت يدوي — يتجاوز كل شيء (حتى أرشيفاً خاطئاً)
        if (!empty(self::PINNED[$key])) {
            return ['id' => (string)self::PINNED[$key], 'title' => 'ملخص المباراة'];
        }

        // (1) أرشيف دائم — تُطابَق مرّة وتثبت
        $archive = rtrim(CACHE_DIR, '/') . '/match-video-' . md5($key) . '.json';
        if (is_file($archive)) {
            $a = json_decode((string)@file_get_contents($archive), true);
            if (is_array($a) && !empty($a['id'])) return $a;
        }

        // فقط للمباريات المنتهية (الملخّص يُنشَر بعد المباراة)
        $st = $m['_status'] ?? (isset($m['score']['ft']) ? 'finished' : 'upcoming');
        if ($st !== 'finished') return null;

        // (2) المطابقة بالأسماء: عنوان الفيديو يحوي اسمَي الفريقين (ذاتيّة التصحيح —
        //     لا تربط فيديو مباراة بأخرى، وتُظهر «لا فيديو» حتى يرفع beIN ملخّص المباراة).
        $needles1 = self::needles($t1);
        $needles2 = self::needles($t2);
        if (!$needles1 || !$needles2) return null;

        foreach (self::videos() as $v) {
            $title = self::normalize((string)($v['title'] ?? ''));
            if ($title === '') continue;
            if (self::containsAny($title, $needles1) && self::containsAny($title, $needles2)) {
                $hit = ['id' => (string)$v['id'], 'title' => (string)$v['title']];
                @file_put_contents($archive, json_encode($hit, JSON_UNESCAPED_UNICODE));
                return $hit;
            }
        }
        return null;
    }

    // ────────────────────────────────────────────────────────
    //  المطابقة بالأسماء
    // ────────────────────────────────────────────────────────

    /** إبر البحث لفريق: العربي (مع/بدون «ال») + الإنجليزي + المرادفات (طول ≥ 3). */
    private static function needles(string $teamEn): array
    {
        $needles = [];
        if (function_exists('teams_map')) {
            $map = teams_map();
            $ar  = trim((string)($map[$teamEn][0] ?? ''));
            if ($ar !== '') {
                $needles[] = self::normalize($ar);
                $needles[] = self::normalize(self::stripAl($ar));
            }
        }
        $needles[] = self::normalize($teamEn);
        foreach (self::ALIAS_AR[$teamEn] ?? [] as $alias) $needles[] = self::normalize($alias);
        return array_values(array_unique(array_filter($needles, fn($n) => mb_strlen($n, 'UTF-8') >= 3)));
    }

    /** يحذف «ال» من بداية كل كلمة. */
    private static function stripAl(string $s): string
    {
        return preg_replace('/(^|\s)ال/u', '$1', $s);
    }

    /** توحيد عربي/لاتيني: أإآ→ا · ة→ه · ى→ي · حذف التشكيل/الرموز/المسافات + lowercase. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(['أ', 'إ', 'آ'], 'ا', $s);
        $s = str_replace('ة', 'ه', $s);
        $s = str_replace('ى', 'ي', $s);
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $s);   // تشكيل + تطويل
        $s = preg_replace('/[^\p{Arabic}a-z0-9]/u', '', $s);            // إسقاط ما عداهما
        return (string)$s;
    }

    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && mb_strpos($haystack, $n, 0, 'UTF-8') !== false) return true;
        }
        return false;
    }

    // ────────────────────────────────────────────────────────
    //  جلب القائمة + العناوين
    // ────────────────────────────────────────────────────────

    /** قائمة الفيديوهات [['id','title'], ...] — من الكاش أو بكشط جديد (العنوان من HTML). */
    public static function videos(): array
    {
        $cacheFile = rtrim(CACHE_DIR, '/') . '/yt-videos.json';
        $stored = [];
        if (is_file($cacheFile)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d)) $stored = $d;
            if (time() - filemtime($cacheFile) < self::LIST_TTL) return $stored;
        }

        // فشل قريب → لا تعاود الكشط مع كل طلب
        $fail = $cacheFile . '.fail';
        if (is_file($fail) && (time() - filemtime($fail) < 600)) return $stored;

        $list = self::scrapePlaylist();   // [['id','title'], ...] — العنوان مستخرَج من HTML
        if ($list === null) { @touch($fail); return $stored; }

        // ادمج القديم (لو خرج فيديو من الصفحة) — الكشط الطازج أوّلاً بترتيبه
        $byId = [];
        foreach ($stored as $v) if (!empty($v['id'])) $byId[$v['id']] = $v;
        $out = [];
        foreach ($list as $v) { $out[] = $v; unset($byId[$v['id']]); }
        foreach ($byId as $v) $out[] = $v;

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        $tmp = $cacheFile . '.tmp';
        if (@file_put_contents($tmp, json_encode($out, JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $cacheFile);
        }
        @unlink($fail);
        return $out;
    }

    /**
     * يكشط القائمة ويستخرج [['id','title'], ...] من HTML مباشرةً.
     * ⚠️ العنوان من واجهة يوتيوب الجديدة (title.content، خام UTF-8) — لا oEmbed، الذي
     *    يرجّع «Unauthorized» للفيديوهات المعطّلة التضمين (beIN) فتختفي من القائمة.
     * يطابق عناوين عناصر القائمة (تحوي «مباراة») مع المعرّفات بترتيب العرض.
     */
    private static function scrapePlaylist(): ?array
    {
        $pid = self::playlistId();

        // (1) خلاصة RSS الرسميّة — XML مستقرّ، بلا جدار موافقة YouTube (يعمل على خوادم
        //     الاستضافة حيث يفشل كشط HTML أحياناً). تُرجع أحدث ~15 فيديو.
        $rss = self::fetchUrl('https://www.youtube.com/feeds/videos.xml?playlist_id=' . rawurlencode($pid));
        if ($rss !== null && preg_match_all('#<yt:videoId>([A-Za-z0-9_-]{11})</yt:videoId>.*?<title>(.*?)</title>#s', $rss, $mm, PREG_SET_ORDER)) {
            $out = [];
            foreach ($mm as $e) {
                $t = trim(html_entity_decode($e[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($t !== '') $out[] = ['id' => $e[1], 'title' => $t];
            }
            if ($out) return $out;
        }

        // (2) احتياطي: كشط HTML (واجهة yt الجديدة — title.content، خام UTF-8)
        $html = self::fetchUrl('https://www.youtube.com/playlist?list=' . rawurlencode($pid) . '&hl=ar');
        if ($html === null) return null;
        if (!preg_match_all('/"title":\{"content":"([^"]+)"/', $html, $tt)) return [];
        $titles = array_values(array_filter($tt[1], fn($t) => mb_strpos($t, 'مباراة') !== false));
        preg_match_all('/"videoId":"([A-Za-z0-9_-]{11})"/', $html, $vv);
        $ids = array_values(array_unique($vv[1]));
        $out = [];
        foreach ($titles as $i => $t) {
            if (!isset($ids[$i])) break;
            $out[] = ['id' => $ids[$i], 'title' => trim($t)];
        }
        return $out;
    }

    /** جلب HTTP بسيط (User-Agent متصفّح) — يعيد النصّ أو null عند الفشل. */
    private static function fetchUrl(string $url): ?string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
                       . "Accept-Language: ar,en;q=0.8\r\n",
            'timeout' => 14,
        ]]);
        $r = @file_get_contents($url, false, $ctx);
        return ($r === false || $r === '') ? null : $r;
    }
}
