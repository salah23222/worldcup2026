<?php
/**
 * LiveService.php
 * ============================================================
 * طبقة التحديث اللحظي الاختيارية (API-Football).
 *
 * المبدأ: openfootball يبقى المصدر الأساسي للجدول الكامل.
 * هذه الطبقة تجلب فقط *نتائج المباريات الجارية اليوم* وتدمجها
 * فوق بيانات openfootball — لتوفير حصة الـ 100 طلب/يوم.
 *
 * أمان كامل: إذا لم يُضبط المفتاح، أو فشل الاتصال، أو نفدت
 * الحصة — كل الدوال تُرجع نتائج فارغة والموقع يعمل بالكامل
 * على openfootball دون أي انكسار.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class LiveService
{
    /** نتائج لحظية محمّلة في الذاكرة لهذا الطلب */
    private static ?array $live = null;

    /** هل التحديث اللحظي مفعّل أصلاً؟ */
    public static function isEnabled(): bool
    {
        return defined('APIFOOTBALL_KEY') && APIFOOTBALL_KEY !== '';
    }

    /**
     * liveScores() — يرجّع مصفوفة النتائج اللحظية.
     * المفتاح: "Team1|Team2" (أسماء إنجليزية) → ['ft'=>[g1,g2], 'status'=>...]
     */
    public static function liveScores(): array
    {
        if (self::$live !== null) {
            return self::$live;
        }
        if (!self::isEnabled()) {
            self::$live = [];
            return self::$live;
        }

        // 1) جرّب الكاش اللحظي أولاً
        $cacheFile = rtrim(CACHE_DIR, '/') . '/live.json';
        if (is_file($cacheFile)
            && (time() - filemtime($cacheFile) < LIVE_CACHE_TTL)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                self::$live = $cached;
                return self::$live;
            }
        }

        // 2) اجلب من API-Football
        $fetched = self::fetchLive();
        if ($fetched !== null) {
            // كتابة ذرّية
            $tmp = $cacheFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($fetched, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cacheFile);
            }
            self::$live = $fetched;
            return self::$live;
        }

        // 3) فشل → استخدم آخر كاش متاح (حتى لو قديم)
        if (is_file($cacheFile)) {
            $stale = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($stale)) {
                self::$live = $stale;
                return self::$live;
            }
        }

        self::$live = [];
        return self::$live;
    }

    /**
     * fetchLive() — يستدعي API-Football لجلب مباريات اليوم.
     * يطلب فقط مباريات بطولة كأس العالم في تاريخ اليوم.
     */
    private static function fetchLive(): ?array
    {
        $date = date('Y-m-d');
        $url  = 'https://' . APIFOOTBALL_HOST . '/fixtures'
              . '?league=' . APIFOOTBALL_LEAGUE
              . '&season=' . APIFOOTBALL_SEASON
              . '&date='   . $date;

        $raw = self::httpGet($url, [
            'x-apisports-key: ' . APIFOOTBALL_KEY,
        ]);
        if ($raw === null) return null;

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['response'])) return null;

        $out = [];
        foreach ($json['response'] as $fx) {
            $home = $fx['teams']['home']['name'] ?? '';
            $away = $fx['teams']['away']['name'] ?? '';
            if ($home === '' || $away === '') continue;

            $statusShort = $fx['fixture']['status']['short'] ?? 'NS';
            // NS=لم تبدأ, 1H/HT/2H/ET/P=جارية, FT/AET/PEN=انتهت
            $isLive     = in_array($statusShort, ['1H','HT','2H','ET','BT','P','LIVE'], true);
            $isFinished = in_array($statusShort, ['FT','AET','PEN'], true);

            $ref = trim((string)($fx['fixture']['referee'] ?? ''));

            $key = self::normalizeKey($home, $away);
            $out[$key] = [
                'home'    => $home,
                'away'    => $away,
                'ft'      => [
                    (int)($fx['goals']['home'] ?? 0),
                    (int)($fx['goals']['away'] ?? 0),
                ],
                'elapsed' => $fx['fixture']['status']['elapsed'] ?? null,
                'status'  => $isLive ? 'live' : ($isFinished ? 'finished' : 'upcoming'),
                'short'   => $statusShort,
                // معرّف المباراة في API-Football (لجلب البطاقات لاحقاً) + اسم الحكم
                'fixture_id' => isset($fx['fixture']['id']) ? (int)$fx['fixture']['id'] : null,
                'referee'    => ($ref !== '') ? $ref : null,
            ];
        }
        return $out;
    }

    /**
     * cards($fixtureId, $home) — يرجّع بطاقات مباراة واحدة من API-Football.
     * يستخدم كاشاً خاصاً لكل مباراة (live-events-{id}.json) باحترام LIVE_CACHE_TTL.
     * يرجّع مصفوفة عناصر: ['side'=>'home'|'away', 'minute'=>int, 'name'=>string, 'type'=>'yellow'|'red'].
     * آمن تماماً: عند غياب المفتاح أو الفشل يرجّع [] دون أي خطأ.
     */
    public static function cards(?int $fixtureId, string $home): array
    {
        if (!self::isEnabled() || !$fixtureId) return [];

        // 1) كاش خاص بهذه المباراة
        $cacheFile = rtrim(CACHE_DIR, '/') . '/live-events-' . $fixtureId . '.json';
        if (is_file($cacheFile)
            && (time() - filemtime($cacheFile) < LIVE_CACHE_TTL)) {
            $cached = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        // 2) اجلب أحداث المباراة (نوع البطاقات فقط)
        $fetched = self::fetchEvents($fixtureId, $home);
        if ($fetched !== null) {
            $tmp = $cacheFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($fetched, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cacheFile);
            }
            return $fetched;
        }

        // 3) فشل → آخر كاش متاح (حتى لو قديم)
        if (is_file($cacheFile)) {
            $stale = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($stale)) {
                return $stale;
            }
        }

        return [];
    }

    /**
     * fetchEvents() — يستدعي /fixtures/events لجلب أحداث مباراة واحدة،
     * ويرشّح البطاقات (Card) فقط، محوّلاً جانب الفريق إلى home/away.
     */
    private static function fetchEvents(int $fixtureId, string $home): ?array
    {
        $url = 'https://' . APIFOOTBALL_HOST . '/fixtures/events'
             . '?fixture=' . $fixtureId;

        $raw = self::httpGet($url, [
            'x-apisports-key: ' . APIFOOTBALL_KEY,
        ]);
        if ($raw === null) return null;

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['response'])) return null;

        $homeCanon = self::canon($home);
        $out = [];
        foreach ($json['response'] as $ev) {
            if (strtolower((string)($ev['type'] ?? '')) !== 'card') continue;

            $detail = strtolower((string)($ev['detail'] ?? ''));
            // "Yellow Card" / "Red Card" / "Second Yellow card"
            if (strpos($detail, 'red') !== false) {
                $type = 'red';
            } elseif (strpos($detail, 'yellow') !== false) {
                $type = 'yellow';
            } else {
                continue;
            }

            $teamName = (string)($ev['team']['name'] ?? '');
            $side     = (self::canon($teamName) === $homeCanon) ? 'home' : 'away';

            $minute = $ev['time']['elapsed'] ?? null;
            $extra  = $ev['time']['extra'] ?? null;
            if (is_numeric($extra)) $minute = (int)$minute + (int)$extra;

            $out[] = [
                'side'   => $side,
                'minute' => is_numeric($minute) ? (int)$minute : 0,
                'name'   => trim((string)($ev['player']['name'] ?? '')),
                'type'   => $type,
            ];
        }
        return $out;
    }

    /** طلب HTTP GET بسيط مع مهلة */
    private static function httpGet(string $url, array $headers): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => FETCH_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => FETCH_TIMEOUT,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($raw !== false && $code === 200) ? $raw : null;
        }
        $ctx = stream_context_create(['http' => [
            'header'  => implode("\r\n", $headers),
            'timeout' => FETCH_TIMEOUT,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        return ($raw !== false) ? $raw : null;
    }

    /**
     * normalizeKey() — يبني مفتاح موحّد لمطابقة المباريات.
     * يتعامل مع اختلاف تسمية المنتخبات بين المصدرين.
     */
    public static function normalizeKey(string $a, string $b): string
    {
        return self::canon($a) . '|' . self::canon($b);
    }

    /** يبسّط اسم المنتخب للمطابقة (حروف صغيرة، بدون رموز) */
    private static function canon(string $name): string
    {
        $name = strtolower(trim($name));
        // مرادفات شائعة بين openfootball و API-Football
        $aliases = [
            'usa'             => 'united states',
            'korea republic'  => 'south korea',
            'ir iran'         => 'iran',
            'czechia'         => 'czech republic',
            'türkiye'         => 'turkey',
            'congo dr'        => 'dr congo',
        ];
        $name = $aliases[$name] ?? $name;
        return preg_replace('/[^a-z]/', '', $name);
    }

    /**
     * applyTo() — يدمج النتيجة اللحظية فوق مباراة من openfootball.
     * يُستدعى داخل DataService لكل مباراة.
     */
    public static function applyTo(array $match): array
    {
        if (!self::isEnabled()) return $match;

        $live = self::liveScores();
        if (!$live) return $match;

        $key = self::normalizeKey(
            trim($match['team1'] ?? ''),
            trim($match['team2'] ?? '')
        );
        // جرّب أيضاً المفتاح المعكوس (احتياط)
        $rev = self::normalizeKey(
            trim($match['team2'] ?? ''),
            trim($match['team1'] ?? '')
        );

        $hit = $live[$key] ?? null;
        $reversed = false;
        if ($hit === null && isset($live[$rev])) {
            $hit = $live[$rev];
            $reversed = true;
        }
        if ($hit === null) return $match;

        // ادمج النتيجة
        $ft = $hit['ft'];
        if ($reversed) $ft = [$ft[1], $ft[0]];

        if ($hit['status'] === 'live' || $hit['status'] === 'finished') {
            $match['score']['ft'] = $ft;
        }
        $match['_live']        = ($hit['status'] === 'live');
        $match['_live_minute'] = $hit['elapsed'] ?? null;
        $match['_live_source'] = 'api-football';

        // اسم الحكم (إن توفّر فقط) — من النتائج اللحظيّة
        if (!empty($hit['referee'])) {
            $match['referee'] = $hit['referee'];
        }

        // 🆕 احتياط: لو الحكم لم يصل من اللحظي، جرّب الخريطة الكاملة
        if (empty($match['referee'])) {
            $r = self::refereeFor($match);
            if ($r !== null) $match['referee'] = $r;
        }

        // البطاقات (طلب إضافي مُخزَّن لكل مباراة، فقط للمباريات الجارية/المنتهية)
        if ($hit['status'] === 'live' || $hit['status'] === 'finished') {
            $cards = self::cards($hit['fixture_id'] ?? null, $hit['home'] ?? '');
            if ($cards) {
                $out = [];
                foreach ($cards as $c) {
                    // side=home → الفريق المضيف في API. حوّله لرقم فريق openfootball.
                    $isHome = ($c['side'] === 'home');
                    // عند المفتاح المعكوس: مضيف API = team2 عندنا
                    $team   = $reversed ? ($isHome ? 2 : 1) : ($isHome ? 1 : 2);
                    $out[] = [
                        'team'   => $team,
                        'minute' => (int)($c['minute'] ?? 0),
                        'name'   => (string)($c['name'] ?? ''),
                        'type'   => ($c['type'] === 'red') ? 'red' : 'yellow',
                    ];
                }
                if ($out) $match['cards'] = $out;
            }
        }

        return $match;
    }

    // ====================================================
    //  القوائم والتشكيلات (تُفعَّل وقت البطولة بمفتاح API-Football)
    // ====================================================

    /** معرّف المنتخب في API-Football (يخزّن خريطة الأسماء→المعرّفات). */
    private static function teamId(string $name): ?int
    {
        $mapFile = rtrim(CACHE_DIR, '/') . '/af-teamids.json';
        $map = is_file($mapFile) ? (json_decode(@file_get_contents($mapFile), true) ?: []) : [];
        $canon = self::canon($name);
        if (array_key_exists($canon, $map)) return $map[$canon] ?: null;

        $url = 'https://' . APIFOOTBALL_HOST . '/teams?search=' . rawurlencode($name);
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        $id  = null;
        if ($raw !== null) {
            $j = json_decode($raw, true);
            if (isset($j['response'][0]['team']['id'])) $id = (int)$j['response'][0]['team']['id'];
        }
        $map[$canon] = $id ?: 0;   // خزّن حتى الفشل لتفادي تكرار الطلب
        @file_put_contents($mapFile, json_encode($map, JSON_UNESCAPED_UNICODE));
        return $id ?: null;
    }

    /** قائمة لاعبي منتخب (≈26 لاعباً). [] عند غياب المفتاح/البيانات. */
    public static function squad(string $teamEn): array
    {
        if (!self::isEnabled() || trim($teamEn) === '') return [];

        $cacheFile = rtrim(CACHE_DIR, '/') . '/squad-' . self::canon($teamEn) . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $c = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($c)) return $c;
        }

        $id = self::teamId($teamEn);
        if (!$id) return [];
        $url = 'https://' . APIFOOTBALL_HOST . '/players/squads?team=' . $id;
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        if ($raw === null) return [];
        $j = json_decode($raw, true);
        $players = $j['response'][0]['players'] ?? [];

        $out = [];
        foreach ($players as $p) {
            $name = trim((string)($p['name'] ?? ''));
            if ($name === '') continue;
            $out[] = [
                'name'   => $name,
                'number' => isset($p['number']) ? (int)$p['number'] : null,
                'pos'    => (string)($p['position'] ?? ''),
            ];
        }
        if ($out) @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    /**
     * تشكيلة مباراة: أساسيون + احتياط + المدرّب + الخطة، لكل فريق.
     * تُرجع ['team1'=>[...], 'team2'=>[...]] أو null (تصدر عادةً قبل المباراة بساعة).
     */
    public static function lineupForMatch(array $match): ?array
    {
        if (!self::isEnabled()) return null;

        $t1 = trim($match['team1'] ?? '');
        $t2 = trim($match['team2'] ?? '');
        $key = self::normalizeKey($t1, $t2);
        $rev = self::normalizeKey($t2, $t1);

        $fid       = null;
        $homeTeam  = '';
        $reversed  = false;

        // الاستراتيجية 1: مباريات اليوم (liveScores) — أسرع وأخفّ تكلفة
        $live = self::liveScores();
        if ($live) {
            $hit = $live[$key] ?? ($live[$rev] ?? null);
            if ($hit && !empty($hit['fixture_id'])) {
                $fid      = (int)$hit['fixture_id'];
                $homeTeam = (string)($hit['home'] ?? '');
                $reversed = !isset($live[$key]) && isset($live[$rev]);
            }
        }

        // الاستراتيجية 2: خريطة كل مباريات البطولة (لمباريات قادمة قبل يومها بأيام)
        // → التشكيلة تظهر فوراً متى نشرتها API-Football، لا تنتظر يوم المباراة.
        if ($fid === null) {
            $map = self::fixturesMap();
            if (isset($map[$key])) {
                $fid      = (int)$map[$key]['id'];
                $homeTeam = (string)$map[$key]['home'];
                $reversed = false;
            } elseif (isset($map[$rev])) {
                $fid      = (int)$map[$rev]['id'];
                $homeTeam = (string)$map[$rev]['home'];
                $reversed = true;
            }
        }

        if (!$fid) return null;

        $cacheFile = rtrim(CACHE_DIR, '/') . '/lineup-' . $fid . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < LIVE_CACHE_TTL)) {
            $c = json_decode(@file_get_contents($cacheFile), true);
            if (is_array($c)) return $c;
        }

        $url = 'https://' . APIFOOTBALL_HOST . '/fixtures/lineups?fixture=' . $fid;
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        if ($raw === null) return null;
        $j = json_decode($raw, true);
        $resp = $j['response'] ?? [];
        if (count($resp) < 2) return null;   // لم تصدر التشكيلة بعد

        $parse = function (array $e): array {
            $start = $subs = [];
            foreach (($e['startXI'] ?? []) as $s) {
                $p = $s['player'] ?? [];
                $n = trim((string)($p['name'] ?? ''));
                if ($n === '') continue;
                $start[] = [
                    'name'   => $n,
                    'number' => isset($p['number']) ? (int)$p['number'] : null,
                    'grid'   => (string)($p['grid'] ?? ''),   // "row:col" لموضع اللاعب على الملعب
                ];
            }
            foreach (($e['substitutes'] ?? []) as $s) {
                $p = $s['player'] ?? [];
                $n = trim((string)($p['name'] ?? ''));
                if ($n === '') continue;
                $subs[] = ['name' => $n, 'number' => isset($p['number']) ? (int)$p['number'] : null];
            }
            return [
                'formation' => (string)($e['formation'] ?? ''),
                'coach'     => (string)($e['coach']['name'] ?? ''),
                'start'     => $start,
                'subs'      => $subs,
            ];
        };

        $homeCanon = self::canon($homeTeam);
        $byHome = $byAway = null;
        foreach ($resp as $e) {
            if (self::canon($e['team']['name'] ?? '') === $homeCanon) $byHome = $parse($e);
            else $byAway = $parse($e);
        }
        if (!$byHome || !$byAway) { $byHome = $parse($resp[0]); $byAway = $parse($resp[1]); }

        $out = $reversed ? ['team1' => $byAway, 'team2' => $byHome]
                         : ['team1' => $byHome, 'team2' => $byAway];
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    /**
     * refereeFor() — يبحث عن الحكم بالترتيب:
     *   1) data/referees-manual.json  (أولويّة قصوى — تُضاف يدوياً عند إعلان FIFA)
     *   2) API-Football fixturesMap   (تلقائي حين تُحدّث API-Football بياناتها)
     * يعيد اسم الحكم (string) أو null.
     */
    public static function refereeFor(array $match): ?string
    {
        $t1 = trim((string)($match['team1'] ?? ''));
        $t2 = trim((string)($match['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return null;

        // (1) جرّب الملف اليدوي أوّلاً
        $manual = self::manualReferees();
        $key1 = $t1 . '|' . $t2;
        $key2 = $t2 . '|' . $t1;
        if (isset($manual[$key1]) && trim($manual[$key1]) !== '') return trim($manual[$key1]);
        if (isset($manual[$key2]) && trim($manual[$key2]) !== '') return trim($manual[$key2]);

        // (2) جرّب API-Football
        if (!self::isEnabled()) return null;
        $map = self::fixturesMap();
        if (!$map) return null;

        $key = self::normalizeKey($t1, $t2);
        $rev = self::normalizeKey($t2, $t1);
        $hit = $map[$key] ?? ($map[$rev] ?? null);
        if (!is_array($hit)) return null;

        $ref = isset($hit['referee']) ? trim((string)$hit['referee']) : '';
        return $ref !== '' ? $ref : null;
    }

    /** يقرأ ملف التعيينات اليدويّة (يُحدَّث عند إعلان FIFA كل مباراة). */
    private static function manualReferees(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $f = __DIR__ . '/../data/referees-manual.json';
        if (!is_file($f)) { $cache = []; return $cache; }
        $d = json_decode((string)@file_get_contents($f), true);
        if (!is_array($d)) { $cache = []; return $cache; }
        // امسح الحقول الإداريّة (_README, _last_updated, ...)
        foreach (array_keys($d) as $k) {
            if (strpos($k, '_') === 0) unset($d[$k]);
        }
        $cache = $d;
        return $cache;
    }

    /**
     * fixturesMap() — خريطة كل مباريات البطولة → fixture_id (للبحث خارج «مباريات اليوم»).
     * طلب API واحد كل 24 ساعة (الجدول لا يتغيّر بعد ضبطه). يُستهلك ~1 من حصّة 100 يومياً.
     * المفتاح: canon(home)+'|'+canon(away). القيمة: ['id' => int, 'home' => string].
     */
    private static function fixturesMap(): array
    {
        $cacheFile = rtrim(CACHE_DIR, '/') . '/af-fixtures.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c)) return $c;
        }

        $url = 'https://' . APIFOOTBALL_HOST . '/fixtures'
             . '?league=' . APIFOOTBALL_LEAGUE
             . '&season=' . APIFOOTBALL_SEASON;
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        if ($raw === null) {
            // فشل الجلب → خزّن خريطة فارغة مؤقّتاً لمدّة قصيرة لمنع الإلحاح
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            return [];
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['response'])) return [];

        $map = [];
        foreach ($json['response'] as $fx) {
            $home = (string)($fx['teams']['home']['name'] ?? '');
            $away = (string)($fx['teams']['away']['name'] ?? '');
            $fid  = (int)($fx['fixture']['id'] ?? 0);
            $ref  = trim((string)($fx['fixture']['referee'] ?? ''));
            if ($home !== '' && $away !== '' && $fid > 0) {
                $map[self::normalizeKey($home, $away)] = [
                    'id'      => $fid,
                    'home'    => $home,
                    'referee' => $ref !== '' ? $ref : null,   // 🆕 يخزن الحكم لو متاح
                ];
            }
        }

        if ($map) {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            $tmp = $cacheFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($map, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cacheFile);
            }
        }
        return $map;
    }
}
