<?php
/**
 * DataService.php
 * ============================================================
 * قلب الموقع. مسؤول عن:
 *   1. جلب بيانات البطولة من openfootball (مجاني، بدون مفتاح)
 *   2. تخزينها مؤقتاً (cache) لتحمّل ملايين الزيارات
 *   3. توفير دوال جاهزة لكل صفحات الموقع
 *
 * استراتيجية الكاش (مهمة جداً):
 *   - كاش حديث (< CACHE_TTL)؟  → استخدمه فوراً (لا اتصال بالإنترنت)
 *   - كاش قديم؟                → اجلب جديد، وإن فشل استخدم القديم
 *   - لا كاش إطلاقاً وفشل الجلب؟ → أعد مصفوفة فارغة بأمان
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class DataService
{
    /** البيانات المحمّلة في الذاكرة (تُحمّل مرة واحدة لكل طلب) */
    private static ?array $data = null;

    /**
     * load() — نقطة الدخول الوحيدة. تُرجع كل بيانات البطولة.
     */
    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $cacheFile = self::cachePath();
        $cached    = is_file($cacheFile) ? self::readCache($cacheFile) : null;

        // مدّة الكاش ديناميكية: أقصر تلقائياً أثناء وجود مباراة مباشرة
        // (تحديث أسرع للنتائج بلا أي ضبط يدوي).
        $ttl = CACHE_TTL;
        if ($cached !== null && self::hasLiveMatch($cached['matches'] ?? [])) {
            $ttl = defined('LIVE_CACHE_TTL') ? min(CACHE_TTL, LIVE_CACHE_TTL) : 60;
        }
        $fresh = $cached !== null && (time() - filemtime($cacheFile) < $ttl);

        // 1) كاش حديث → استخدمه مباشرة
        if ($fresh) {
            self::$data = $cached;
            return self::$data;
        }

        // CLI/كرون: لا يوجد زائر ينتظر صفحة → اجلب الآن (متزامن مُقيَّد) لنحصل على
        // أحدث بيانات. الغرض الأساسي من تشغيل الكرون هو تحديث الكاش بنفسه.
        if (PHP_SAPI === 'cli') {
            $remote = self::fetchRemote();
            if ($remote !== null) {
                self::writeCache($cacheFile, $remote);
                self::$data = $remote;
                return self::$data;
            }
            $stale = $cached ?? self::fallbackData();
            self::$data = !empty($stale['matches'])
                ? $stale
                : ['name' => SITE_NAME_EN, 'matches' => [], '_meta' => ['ok' => false]];
            return self::$data;
        }

        // 2) كاش قديم/مفقود (طلب ويب): لا نحجب الزائر على الشبكة إطلاقاً.
        //    نقدّم له النسخة المخزّنة (ولو قديمة) أو النسخة الاحتياطية فوراً،
        //    ثم نحدّث الكاش من الشبكة *بعد* إرسال الرد (جالب واحد فقط).
        //    هذا هو الإصلاح الجذري لأخطاء 504: لا أحد ينتظر جلباً بطيئاً.
        $stale = $cached ?? self::fallbackData();
        if (!empty($stale['matches'])) {
            self::$data = $stale;
            self::refreshInBackground($cacheFile);
            return self::$data;
        }

        // 3) بداية باردة تماماً (لا كاش ولا نسخة احتياطية): مضطرّون لجلب متزامن،
        //    لكنه مُقيَّد بصرامة بمهلة قصيرة (FETCH_TIMEOUT) ولمرة واحدة فقط.
        $remote = self::fetchRemote();
        if ($remote !== null) {
            self::writeCache($cacheFile, $remote);
            self::$data = $remote;
        } else {
            self::$data = ['name' => SITE_NAME_EN, 'matches' => [], '_meta' => ['ok' => false]];
        }
        return self::$data;
    }

    /**
     * refreshInBackground() — يحدّث كاش البيانات من الشبكة دون أن ينتظره الزائر.
     *   • جالب واحد فقط: من يلتقط القفل غير الحاجز يحدّث، والبقية تنصرف فوراً.
     *   • على php-fpm (الإنتاج): يُرسل الرد كاملاً للزائر (fastcgi_finish_request)
     *     ثم يجلب في الخلفية — فلا يرى الزائر أي تأخير شبكي إطلاقاً.
     *   • على الـCLI/الكرون: يجلب فوراً (الغرض من تشغيله هو تحديث الكاش).
     */
    private static function refreshInBackground(string $cacheFile): void
    {
        static $scheduled = false;
        if ($scheduled) return;            // مرّة واحدة لكل طلب
        $scheduled = true;

        // جالب واحد فقط (قفل غير حاجز). نُبقي المقبض مفتوحاً حتى ينتهي الجلب.
        $lock = @fopen($cacheFile . '.lock', 'c');
        if (!$lock || !@flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) @fclose($lock);
            return;                        // طلب آخر يحدّث الآن → لا تفعل شيئاً
        }

        $doFetch = static function () use ($lock, $cacheFile) {
            $remote = self::fetchRemote();
            if ($remote !== null) {
                self::writeCache($cacheFile, $remote);
            }
            @flock($lock, LOCK_UN);
            @fclose($lock);
        };

        // CLI/كرون: جلب متزامن (لا أحد ينتظر صفحة).
        if (PHP_SAPI === 'cli') { $doFetch(); return; }

        // الويب: أكمل إرسال الرد أولاً ثم اجلب بعده حتى لا ينتظر الزائر الشبكة.
        @ignore_user_abort(true);
        register_shutdown_function(static function () use ($doFetch) {
            // فرّغ كل مخازن الإخراج (يكتب PageCache نسخته ويُرسل HTML للزائر)…
            while (ob_get_level() > 0) { @ob_end_flush(); }
            // …ثم اقطع اتصال الزائر إن كان الخادم يدعمه (php-fpm/الإنتاج)…
            if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
            // …وحدّث الكاش في الخلفية بعد أن استلم الزائر صفحته.
            $doFetch();
        });
    }

    /**
     * fallbackData() — نسخة احتياطية مدمجة (data/worldcup_fallback.json).
     * تضمن وجود بيانات المباريات دائماً حتى لو فشل الكاش والشبكة معاً.
     */
    private static function fallbackData(): array
    {
        $f = rtrim(CACHE_DIR, '/') . '/../data/worldcup_fallback.json';
        if (is_file($f)) {
            $d = json_decode((string)@file_get_contents($f), true);
            if (is_array($d) && isset($d['matches'])) return $d;
        }
        return ['name' => SITE_NAME_EN, 'matches' => []];
    }

    /** هل توجد مباراة مباشرة الآن؟ (لتقصير مدّة الكاش تلقائياً) */
    private static function hasLiveMatch(array $matches): bool
    {
        foreach ($matches as $m) {
            if (is_array($m) && self::matchStatus($m) === 'live') {
                return true;
            }
        }
        return false;
    }

    /**
     * نتائج تجريبية اختيارية لمحاكاة سير البطولة (للتجربة فقط).
     * data/demo_results.json مثل: {"0":[2,0]}  — احذف الملف لإيقاف الوضع التجريبي.
     */
    private static function demoResults(): array
    {
        $f = rtrim(CACHE_DIR, '/') . '/../data/demo_results.json';
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    /** مسار ملف الكاش */
    private static function cachePath(): string
    {
        return rtrim(CACHE_DIR, '/') . '/worldcup2026.json';
    }

    /** قراءة الكاش وفك تشفيره */
    private static function readCache(string $file): ?array
    {
        $raw = @file_get_contents($file);
        if ($raw === false) return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /** كتابة الكاش بشكل آمن (atomic write) */
    private static function writeCache(string $file, array $data): void
    {
        if (!is_dir(CACHE_DIR)) {
            @mkdir(CACHE_DIR, 0755, true);
        }
        $data['_meta'] = ['ok' => true, 'cached_at' => time()];
        $tmp = $file . '.tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $file); // rename ذرّي: يمنع قراءة ملف نصف مكتوب
        }
    }

    /**
     * fetchRemote() — يجلب JSON من openfootball.
     * يستخدم cURL إن وُجد، وإلا file_get_contents.
     */
    private static function fetchRemote(): ?array
    {
        $url     = DATA_SOURCE;
        $timeout = defined('FETCH_TIMEOUT') ? max(1, (int)FETCH_TIMEOUT) : 5;

        // cURL متاح؟ استخدمه وحده. (مهم: لا نُتبعه بـ file_get_contents عند الفشل،
        // لأن تراكم المهلتين كان يضاعف زمن التعليق ويسبّب 504 عند تعذّر الوصول للمصدر.)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_NOSIGNAL       => true,   // مهلة موثوقة داخل php-fpm متعدّد الخيوط
                CURLOPT_USERAGENT      => 'WorldCup2026Site/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw  = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $code !== 200) return null;
            $decoded = json_decode($raw, true);
            return (is_array($decoded) && isset($decoded['matches'])) ? $decoded : null;
        }

        // لا cURL: file_get_contents مع تقييد صارم — نضبط default_socket_timeout أيضاً
        // لأن خيار "timeout" في سياق HTTP لا يحدّ زمن إنشاء الاتصال/DNS بمفرده.
        $prev = @ini_set('default_socket_timeout', (string)$timeout);
        $ctx  = stream_context_create(['http' => [
            'timeout'    => $timeout,
            'user_agent' => 'WorldCup2026Site/1.0',
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($prev !== false) { @ini_set('default_socket_timeout', (string)$prev); }

        if ($raw === false) return null;
        $decoded = json_decode($raw, true);
        return (is_array($decoded) && isset($decoded['matches'])) ? $decoded : null;
    }

    // ========================================================
    //  دوال جاهزة للصفحات
    // ========================================================

    /** نتيجة allMatches() محفوظة بالذاكرة — تُبنى مرّة واحدة لكل طلب */
    private static ?array $matchesMemo = null;

    /** كل المباريات (مصفوفة موحّدة) */
    public static function allMatches(): array
    {
        // الصفحات تستدعيها عدّة مرات (اليوم/القادم/النتائج/الترتيب...) وكل
        // استدعاء كان يعيد دمج LiveService لكل الـ104 مباريات → نبنيها مرّة واحدة.
        if (self::$matchesMemo !== null) {
            return self::$matchesMemo;
        }
        $data = self::load();
        $demo = self::demoResults();      // نتائج تجريبية اختيارية (للتجربة فقط)
        $out  = [];
        $i    = 0;
        foreach ($data['matches'] ?? [] as $m) {
            $m['_index'] = $i;            // معرّف داخلي لصفحة match.php
            if (isset($demo[$i]) && is_array($demo[$i])) {
                $dm = $demo[$i];
                if (isset($dm['ft']) && is_array($dm['ft'])) {        // صيغة كائن: {ft, referee, cards}
                    $m['score']['ft'] = [(int)$dm['ft'][0], (int)$dm['ft'][1]];
                    if (!empty($dm['referee'])) $m['referee'] = (string)$dm['referee'];
                    if (!empty($dm['cards']) && is_array($dm['cards'])) $m['cards'] = $dm['cards'];
                } elseif (count($dm) === 2) {                         // صيغة مختصرة: [g1,g2]
                    $m['score']['ft'] = [(int)$dm[0], (int)$dm[1]];
                }
            }
            // ادمج النتيجة اللحظية (إن كان API-Football مفعّلاً)
            $m = LiveService::applyTo($m);
            // خط النهاية للإقصائيّات: النتيجة النهائيّة تشمل الوقت الإضافي.
            // كل المستهلكين (العرض/API/التغريدات/الذكاء/ترقية الفائز في القوسين)
            // يقرؤون score.ft → نجعلها النتيجة النهائيّة (et إن وُجد) ونحفظ
            // نتيجة الـ90 دقيقة في score.reg للتفصيل. وإلّا كل مباراة تُحسم في
            // الوقت الإضافي تظهر بنتيجة الـ90 دقيقة الناقصة (مثال: بلجيكا 2-2 بدل 3-2).
            if (isset($m['score']['et'][0], $m['score']['et'][1]) && is_array($m['score']['et'])) {
                if (isset($m['score']['ft']) && is_array($m['score']['ft']) && !isset($m['score']['reg'])) {
                    $m['score']['reg'] = $m['score']['ft'];   // نتيجة الـ90 دقيقة (للتفصيل)
                }
                $m['score']['ft'] = [(int)$m['score']['et'][0], (int)$m['score']['et'][1]];
            }
            $m['_status'] = self::matchStatus($m);
            $out[] = $m;
            $i++;
        }
        self::$matchesMemo = $out;
        return $out;
    }

    /** حالة المباراة: finished / live / upcoming */
    public static function matchStatus(array $m): string
    {
        // مصدر لحظي يقول إنها جارية → مباشر (له الأولوية)
        if (!empty($m['_live'])) {
            return 'live';
        }
        // إذا فيها نتيجة → انتهت
        if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
            return 'finished';
        }
        // قارن وقت المباراة بالوقت الحالي
        $ts = self::matchTimestamp($m);
        if ($ts === null) return 'upcoming';
        $now = time();
        // نافذة المباراة الحية ~ ساعتين
        if ($now >= $ts && $now <= $ts + 7200) return 'live';
        return ($now > $ts) ? 'finished' : 'upcoming';
    }

    /** يحوّل تاريخ+وقت المباراة إلى timestamp */
    public static function matchTimestamp(array $m): ?int
    {
        if (empty($m['date'])) return null;
        $time = $m['time'] ?? '00:00';
        // openfootball يكتب الوقت هكذا: "13:00 UTC-6"
        // ملاحظة: DateTimeZone لا يقبل "UTC-6" — نحوّلها لإزاحة "-06:00".
        $offset = '+00:00';
        if (preg_match('/UTC\s*([+-])\s*(\d{1,2})(?::?(\d{2}))?/i', $time, $mm)) {
            $offset = $mm[1]
                    . str_pad($mm[2], 2, '0', STR_PAD_LEFT)
                    . ':' . ($mm[3] ?? '00');
        }
        $clean = preg_replace('/\s*UTC.*$/i', '', $time);
        $clean = trim($clean) ?: '00:00';
        try {
            // فسّر الوقت بإزاحته الأصلية → لحظة مطلقة صحيحة (timestamp مستقل عن المنطقة)
            $dt = new DateTime($m['date'] . ' ' . $clean . ' ' . $offset);
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return null;
        }
    }

    /** مباريات يوم معيّن (افتراضياً: اليوم) */
    public static function matchesOnDate(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $out  = [];
        foreach (self::allMatches() as $m) {
            $ts = self::matchTimestamp($m);
            if ($ts !== null && date('Y-m-d', $ts) === $date) {
                $out[] = $m;
            }
        }
        usort($out, fn($a, $b) => self::matchTimestamp($a) <=> self::matchTimestamp($b));
        return $out;
    }

    /** أقرب N مباريات قادمة */
    public static function upcomingMatches(int $limit = 6): array
    {
        $out = [];
        foreach (self::allMatches() as $m) {
            if ($m['_status'] === 'upcoming' || $m['_status'] === 'live') {
                $out[] = $m;
            }
        }
        usort($out, fn($a, $b) => (self::matchTimestamp($a) ?? PHP_INT_MAX)
                               <=> (self::matchTimestamp($b) ?? PHP_INT_MAX));
        return array_slice($out, 0, $limit);
    }

    /** آخر N نتائج */
    public static function latestResults(int $limit = 6): array
    {
        $out = [];
        foreach (self::allMatches() as $m) {
            if ($m['_status'] === 'finished') $out[] = $m;
        }
        usort($out, fn($a, $b) => (self::matchTimestamp($b) ?? 0)
                               <=> (self::matchTimestamp($a) ?? 0));
        return array_slice($out, 0, $limit);
    }

    /** قائمة المجموعات الموجودة (Group A ... Group L) */
    public static function groupNames(): array
    {
        $g = [];
        foreach (self::allMatches() as $m) {
            if (!empty($m['group'])) $g[$m['group']] = true;
        }
        $g = array_keys($g);
        sort($g);
        return $g;
    }

    /** مباريات مجموعة معيّنة */
    public static function matchesInGroup(string $group): array
    {
        $out = [];
        foreach (self::allMatches() as $m) {
            if (($m['group'] ?? '') === $group) $out[] = $m;
        }
        return $out;
    }

    /** مباريات جولة معيّنة (Matchday 1 / Round of 16 / ...) */
    public static function matchesInRound(string $round): array
    {
        $out = [];
        foreach (self::allMatches() as $m) {
            if (($m['round'] ?? '') === $round) $out[] = $m;
        }
        return $out;
    }

    /** كل الجولات بالترتيب */
    public static function roundNames(): array
    {
        $r = [];
        foreach (self::allMatches() as $m) {
            if (!empty($m['round'])) $r[$m['round']] = true;
        }
        return array_keys($r);
    }

    /** مباراة واحدة بالمعرّف الداخلي */
    public static function matchByIndex(int $index): ?array
    {
        foreach (self::allMatches() as $m) {
            if ($m['_index'] === $index) return $m;
        }
        return null;
    }

    /** كل المنتخبات الحقيقية المشاركة */
    public static function allTeams(): array
    {
        $teams = [];
        foreach (self::allMatches() as $m) {
            foreach ([$m['team1'] ?? '', $m['team2'] ?? ''] as $t) {
                $t = trim($t);
                if ($t !== '' && is_real_team($t)) {
                    $teams[$t] = $m['group'] ?? '';
                }
            }
        }
        ksort($teams);
        return $teams; // [اسم المنتخب => المجموعة]
    }

    /** كل مباريات منتخب معيّن */
    public static function matchesForTeam(string $team): array
    {
        $out = [];
        foreach (self::allMatches() as $m) {
            if (($m['team1'] ?? '') === $team || ($m['team2'] ?? '') === $team) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /** هل البيانات حُمّلت بنجاح؟ */
    public static function isOk(): bool
    {
        $d = self::load();
        return ($d['_meta']['ok'] ?? true) && !empty($d['matches']);
    }

    /** متى آخر تحديث للكاش */
    public static function lastUpdate(): ?int
    {
        $f = self::cachePath();
        return is_file($f) ? filemtime($f) : null;
    }

    /** لحظة انطلاق البطولة = توقيت أبكر مباراة (timestamp) أو null. مخزّنة في الذاكرة. */
    public static function tournamentStart(): ?int
    {
        static $cached = false;
        static $val = null;
        if ($cached) return $val;
        $min = null;
        foreach (self::allMatches() as $m) {
            $ts = self::matchTimestamp($m);
            if ($ts !== null && ($min === null || $ts < $min)) $min = $ts;
        }
        $val = $min;
        $cached = true;
        return $val;
    }

    /** هل انطلقت البطولة فعلاً (بدأت أوّل مباراة)؟ */
    public static function tournamentStarted(): bool
    {
        $start = self::tournamentStart();
        return $start === null ? false : (time() >= $start);
    }
}
