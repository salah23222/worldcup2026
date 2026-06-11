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
        // ملاحظة: لم نعد نتوقف عند غياب مفتاح API-Football —
        // EspnLive (مجاني بلا مفتاح) يتكفّل بالنتائج اللحظية كاحتياط دائم.

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

        // فشل قريب مُسجَّل → لا تعاود الجلب في كل طلب، قدّم آخر نسخة متاحة
        $failMarker = $cacheFile . '.fail';
        if (is_file($failMarker) && (time() - filemtime($failMarker) < 60)) {
            $stale = is_file($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
            self::$live = is_array($stale) ? $stale : [];
            return self::$live;
        }

        // 2) اجلب من API-Football (إن كان مفعّلاً)، وإلا/وعند فشله → ESPN المجاني
        $fetched = self::isEnabled() ? self::fetchLive() : null;
        if ($fetched === null && class_exists('EspnLive')) {
            $fetched = EspnLive::fetchLive();
        }
        if ($fetched !== null) {
            // كتابة ذرّية
            $tmp = $cacheFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($fetched, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cacheFile);
            }
            @unlink($failMarker);
            self::$live = $fetched;
            return self::$live;
        }
        @touch($failMarker);

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
        // API-Football يعيد أخطاء الخطة/المفتاح بحالة HTTP 200 و response فارغ —
        // اعتبرها فشلاً صريحاً حتى يتفعّل احتياط ESPN المجاني بدلاً منه.
        if (!empty($json['errors'])) return null;

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

        // فشل قريب → لا تعاود الجلب الآن، قدّم آخر نسخة متاحة
        $failMarker = $cacheFile . '.fail';
        if (is_file($failMarker) && (time() - filemtime($failMarker) < 120)) {
            $stale = is_file($cacheFile) ? json_decode((string)@file_get_contents($cacheFile), true) : null;
            return is_array($stale) ? $stale : [];
        }

        // 2) اجلب أحداث المباراة (نوع البطاقات فقط)
        $fetched = self::fetchEvents($fixtureId, $home);
        if ($fetched !== null) {
            $tmp = $cacheFile . '.tmp';
            if (@file_put_contents($tmp, json_encode($fetched, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cacheFile);
            }
            @unlink($failMarker);
            return $fetched;
        }
        @touch($failMarker);

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
        // 🆕 officials/referee تُملأ دائماً — حتى للمباريات القادمة وبدون APIFOOTBALL_KEY
        if (empty($match['officials'])) {
            $o = self::officialsFor($match);
            if ($o['main'] || $o['assistants'] || $o['var'] || $o['fourth']) {
                $match['officials'] = $o;
                // ابقَ متوافقاً مع الكود القديم الذي يقرأ $m['referee']
                if (empty($match['referee']) && !empty($o['main']['name'])) {
                    $match['referee'] = $o['main']['name'];
                }
            }
        }

        // 🆕 تقرير ما بعد المباراة (دائم وذاتيّ الشفاء): المباريات المنتهية
        // تستكمل إحصائياتها وأهدافها وبطاقاتها من الأرشيف المحلي، أو تجلبها
        // من ESPN بمعرّف مؤرّخ (scoreboard?dates=) حتى بعد أيّام من اللعب.
        if (isset($match['score']['ft']) && empty($match['_live'])) {
            $match = self::ensureReport($match);
        }

        // النتائج اللحظية تعمل حتى بدون مفتاح API-Football (احتياط ESPN المجاني) —
        // أما الإحصائيات/البطاقات/التشكيلات فتتحقق دوالها من المفتاح بنفسها.
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
        $match['_live_source'] = (string)($hit['_src'] ?? 'api-football');

        // اسم الحكم (إن توفّر فقط) — من النتائج اللحظيّة
        if (!empty($hit['referee'])) {
            $match['referee'] = $hit['referee'];
        }

        // 🆕 احتياط: لو الحكم لم يصل من اللحظي، جرّب الخريطة الكاملة + officials
        if (empty($match['referee'])) {
            $r = self::refereeFor($match);
            if ($r !== null) $match['referee'] = $r;
        }
        if (empty($match['officials'])) {
            $o = self::officialsFor($match);
            if ($o['main'] || $o['assistants'] || $o['var'] || $o['fourth']) {
                $match['officials'] = $o;
            }
        }

        // 🆕 إحصائيات تفصيليّة (للمباريات الجارية/المنتهية)
        if ($hit['status'] === 'live' || $hit['status'] === 'finished') {
            $stats = self::statsFor($match);
            if ($stats) {
                // لو reversed: اقلب القيم بين الفريقين
                if ($reversed) {
                    foreach ($stats as &$s) {
                        $s['v'] = [(int)$s['v'][1], (int)$s['v'][0]];
                    }
                    unset($s);
                }
                $match['stats'] = $stats;
                // 🆕 أرشفة دائمة (باتجاه team1/team2) — المباراة تخرج من لوحة
                // ESPN اليوميّة بعد يومها، والأرشيف يُبقي تقرير ما بعد المباراة للأبد.
                self::writeStatsArchive($match, $stats);
            }

            // 🆕 أهداف (هدّاف+دقيقة) وبطاقات من ESPN keyEvents — تملأ صفحات
            // الهدّافين والبطاقات فوراً دون انتظار تحديث openfootball المجتمعي.
            if (!empty($hit['espn_id'])) {
                $match = self::attachEspnEvents(
                    $match, (string)$hit['espn_id'], $reversed,
                    empty($match['cards']),
                    empty($match['goals1']) && empty($match['goals2'])
                );
            }
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
        // (0) الملف اليدوي data/lineups-manual.json:
        //     - مؤكَّدة (بدون probable) → أولوية قصوى، تنتهي هنا.
        //     - متوقّعة (probable:true) → تُستبدل تلقائياً بالرسمية من ESPN فور صدورها.
        $manual = self::manualLineup($match);
        if ($manual !== null && empty($manual['probable'])) return $manual;

        // (1) 🆕 التشكيلة الرسمية من ESPN (مجاني — تصدر قبل الانطلاق بساعة تقريباً)
        $espn = self::espnLineup($match);
        if ($espn !== null) return $espn;

        // متوقّعة يدويّة كاحتياط حتى صدور الرسمية
        if ($manual !== null) return $manual;

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
     * manualLineup() — تشكيلة من data/lineups-manual.json (أولوية فوق API).
     * صيغة الملف — المفتاح "Team1|Team2" بالأسماء الإنجليزية (كما في openfootball):
     * {
     *   "Mexico|South Africa": {
     *     "team1": { "formation": "4-3-3", "coach": "اسم المدرب",
     *                "start": [ {"name":"...","number":1}, ... 11 لاعباً بالترتيب: حارس ثم دفاع ثم وسط ثم هجوم ],
     *                "subs":  [ {"name":"...","number":12}, ... ] },
     *     "team2": { ... }
     *   }
     * }
     * مواضع الملعب (grid) تُحسب تلقائياً من الخطة إن لم تُذكر.
     */
    /**
     * espnLineup() — التشكيلة الرسمية من ESPN، مُحوَّلة لاتجاه team1/team2.
     * يعيد null لو لم تصدر بعد (ESPN ينشرها قبل الانطلاق بساعة تقريباً).
     * تُؤرشَف دائماً — تقرير ما بعد المباراة يبقى بعد خروجها من لوحة اليوم.
     */
    private static function espnLineup(array $match): ?array
    {
        if (!class_exists('EspnLive')) return null;
        $t1 = trim((string)($match['team1'] ?? ''));
        $t2 = trim((string)($match['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return null;

        $k1 = self::normalizeKey($t1, $t2);
        $archive = rtrim(CACHE_DIR, '/') . '/match-lineup-' . md5($k1) . '.json';

        $live = self::liveScores();
        $hit  = null;
        if (is_array($live) && $live) {
            $k2  = self::normalizeKey($t2, $t1);
            $hit = $live[$k1] ?? ($live[$k2] ?? null);
        }
        if (!is_array($hit) || empty($hit['espn_id'])) {
            // المباراة خرجت من لوحة اليوم → آخر تشكيلة رسمية مؤرشفة
            if (is_file($archive)) {
                $a = json_decode((string)@file_get_contents($archive), true);
                if (is_array($a) && !empty($a['team1'])) return $a;
            }
            return null;
        }

        $lu = EspnLive::lineupFor((string)$hit['espn_id']);
        if ($lu === null) return null;

        // مضيف ESPN = team1 عندنا إذا طابق المفتاح المباشر، وإلا معكوس
        $reversed = !isset($live[$k1]);
        $out = $reversed
            ? ['team1' => $lu['away'], 'team2' => $lu['home']]
            : ['team1' => $lu['home'], 'team2' => $lu['away']];

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($archive, json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    // ════════════════════════════════════════════════════════════
    //  🆕 أرشيف ما بعد المباراة (دائم — لا TTL)
    // ════════════════════════════════════════════════════════════

    /** مسار أرشيف إحصائيات دائم لمباراة (مخزَّنة باتجاه team1/team2). */
    private static function statsArchivePath(array $match): string
    {
        $key = self::normalizeKey(trim((string)($match['team1'] ?? '')), trim((string)($match['team2'] ?? '')));
        return rtrim(CACHE_DIR, '/') . '/match-stats-' . md5($key) . '.json';
    }

    private static function readStatsArchive(array $match): array
    {
        $f = self::statsArchivePath($match);
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    private static function writeStatsArchive(array $match, array $stats): void
    {
        if (!$stats) return;
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::statsArchivePath($match), json_encode($stats, JSON_UNESCAPED_UNICODE));
    }

    /** مسار أرشيف أحداث دائم (أهداف + بطاقات، باتجاه team1/team2). */
    private static function eventsArchivePath(array $match): string
    {
        $key = self::normalizeKey(trim((string)($match['team1'] ?? '')), trim((string)($match['team2'] ?? '')));
        return rtrim(CACHE_DIR, '/') . '/match-events-' . md5($key) . '.json';
    }

    private static function readEventsArchive(array $match): array
    {
        $f = self::eventsArchivePath($match);
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    private static function writeEventsArchive(array $match, array $events): void
    {
        if (empty($events['cards']) && empty($events['goals1']) && empty($events['goals2'])) return;
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::eventsArchivePath($match), json_encode($events, JSON_UNESCAPED_UNICODE));
    }

    /**
     * attachEspnEvents() — يحوّل keyEvents (أهداف/بطاقات) لاتجاه team1/team2
     * ويثبّتها على المباراة + يؤرشفها. لا يستبدل بيانات openfootball الموجودة.
     */
    private static function attachEspnEvents(array $m, string $eid, bool $reversed, bool $needCards, bool $needGoals): array
    {
        if ((!$needCards && !$needGoals) || !class_exists('EspnLive')) return $m;
        $ev = EspnLive::eventsFor($eid);
        $sideToTeam = fn(string $side): int => $reversed
            ? (($side === 'home') ? 2 : 1)
            : (($side === 'home') ? 1 : 2);

        if ($needCards && !empty($ev['cards'])) {
            $out = [];
            foreach ($ev['cards'] as $c) {
                $out[] = [
                    'team'   => $sideToTeam((string)$c['side']),
                    'minute' => (int)($c['minute'] ?? 0),
                    'name'   => (string)($c['name'] ?? ''),
                    'type'   => (($c['type'] ?? '') === 'red') ? 'red' : 'yellow',
                ];
            }
            if ($out) $m['cards'] = $out;
        }

        if ($needGoals && !empty($ev['goals'])) {
            $g1 = $g2 = [];
            foreach ($ev['goals'] as $g) {
                $e = ['name' => (string)($g['name'] ?? ''), 'minute' => (int)($g['minute'] ?? 0)];
                if (!empty($g['offset']))  $e['offset']  = (int)$g['offset'];
                if (!empty($g['penalty'])) $e['penalty'] = true;
                if (!empty($g['owngoal'])) $e['owngoal'] = true;
                ($sideToTeam((string)$g['side']) === 1) ? ($g1[] = $e) : ($g2[] = $e);
            }
            if ($g1) $m['goals1'] = $g1;
            if ($g2) $m['goals2'] = $g2;
        }

        self::writeEventsArchive($m, [
            'cards'  => $m['cards']  ?? [],
            'goals1' => $m['goals1'] ?? [],
            'goals2' => $m['goals2'] ?? [],
        ]);
        return $m;
    }

    /**
     * ensureReport() — يضمن اكتمال تقرير مباراة منتهية:
     *   (أ) من الأرشيف المحلي (كُتب يوم اللعب)
     *   (ب) وإلا من ESPN بمعرّف مؤرّخ — يعمل حتى بعد أيّام من المباراة
     * يغطّي: الإحصائيات + الأهداف بأسماء هدّافيها + البطاقات.
     */
    private static function ensureReport(array $m): array
    {
        $needStats = empty($m['stats']);
        $needCards = empty($m['cards']);
        $needGoals = empty($m['goals1']) && empty($m['goals2']);
        if (!$needStats && !$needCards && !$needGoals) return $m;

        // (أ) الأرشيف المحلي
        if ($needStats) {
            $a = self::readStatsArchive($m);
            if ($a) { $m['stats'] = $a; $needStats = false; }
        }
        if ($needCards || $needGoals) {
            $ea = self::readEventsArchive($m);
            if ($ea) {
                if ($needCards && !empty($ea['cards'])) { $m['cards'] = $ea['cards']; $needCards = false; }
                if ($needGoals && (!empty($ea['goals1']) || !empty($ea['goals2']))) {
                    if (!empty($ea['goals1'])) $m['goals1'] = $ea['goals1'];
                    if (!empty($ea['goals2'])) $m['goals2'] = $ea['goals2'];
                    $needGoals = false;
                }
            }
        }
        if (!$needStats && !$needCards && !$needGoals) return $m;

        // (ب) جلب ذاتي من ESPN بمعرّف مؤرّخ
        if (!class_exists('EspnLive')) return $m;
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return $m;
        $date = preg_replace('/\D/', '', (string)($m['date'] ?? ''));   // YYYY-MM-DD → YYYYMMDD
        if (strlen($date) !== 8) return $m;

        $k1 = self::normalizeKey($t1, $t2);
        $k2 = self::normalizeKey($t2, $t1);
        $eid = EspnLive::idFor($k1, $date);
        $reversed = false;
        if ($eid === '') {
            $eid = EspnLive::idFor($k2, $date);
            $reversed = ($eid !== '');
        }
        if ($eid === '') return $m;

        if ($needStats) {
            $stats = EspnLive::statsFor($eid);
            if ($stats) {
                if ($reversed) {
                    foreach ($stats as &$s) { $s['v'] = [(int)$s['v'][1], (int)$s['v'][0]]; }
                    unset($s);
                }
                $m['stats'] = $stats;
                self::writeStatsArchive($m, $stats);
            }
        }
        if ($needCards || $needGoals) {
            $m = self::attachEspnEvents($m, $eid, $reversed, $needCards, $needGoals);
        }
        return $m;
    }

    private static function manualLineup(array $match): ?array
    {
        static $all = null;
        if ($all === null) {
            $f = __DIR__ . '/../data/lineups-manual.json';
            $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
            $all = is_array($d) ? $d : [];
            foreach (array_keys($all) as $k) {           // تجاهل حقول _README الإدارية
                if (strpos((string)$k, '_') === 0) unset($all[$k]);
            }
        }
        if (!$all) return null;

        $t1 = trim((string)($match['team1'] ?? ''));
        $t2 = trim((string)($match['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return null;

        $entry = $all[$t1 . '|' . $t2] ?? null;
        $rev   = false;
        if ($entry === null && isset($all[$t2 . '|' . $t1])) {
            $entry = $all[$t2 . '|' . $t1];
            $rev   = true;
        }
        if (!is_array($entry)) return null;

        $A = self::normalizeManualTeam($entry['team1'] ?? null);
        $B = self::normalizeManualTeam($entry['team2'] ?? null);
        if ($A === null || $B === null) return null;

        $out = $rev ? ['team1' => $B, 'team2' => $A] : ['team1' => $A, 'team2' => $B];
        if (!empty($entry['probable'])) { $out['probable'] = true; }   // وسم «متوقعة» في العرض
        return $out;
    }

    /** يطبّع فريقاً من الملف اليدوي ويحسب grid تلقائياً من الخطة عند غيابه. */
    private static function normalizeManualTeam($t): ?array
    {
        if (!is_array($t)) return null;
        $formation = trim((string)($t['formation'] ?? ''));

        $start = [];
        foreach ((is_array($t['start'] ?? null) ? $t['start'] : []) as $p) {
            if (is_string($p)) $p = ['name' => $p];
            if (!is_array($p)) continue;
            $n = trim((string)($p['name'] ?? ''));
            if ($n === '') continue;
            $start[] = [
                'name'   => $n,
                'number' => (isset($p['number']) && $p['number'] !== '') ? (int)$p['number'] : null,
                'grid'   => trim((string)($p['grid'] ?? '')),
            ];
        }
        if (count($start) < 7) return null;   // تشكيلة غير مكتملة → تجاهل المدخل

        // أكمل مواضع الملعب من الخطة: GK في الصف 1، ثم صفوف الخطة (4-3-3 → 4 ثم 3 ثم 3)
        $missingGrid = false;
        foreach ($start as $p) { if ($p['grid'] === '') { $missingGrid = true; break; } }
        if ($missingGrid) {
            $rows = array_values(array_filter(array_map('intval', explode('-', $formation)), fn($x) => $x > 0));
            if (!$rows || array_sum($rows) !== count($start) - 1) {
                $rows = [4, 3, 3];   // خطة افتراضية إن غابت/لم تطابق العدد
            }
            $gridList = ['1:1'];
            $row = 2;
            foreach ($rows as $cnt) {
                for ($cCol = 1; $cCol <= $cnt; $cCol++) { $gridList[] = $row . ':' . $cCol; }
                $row++;
            }
            foreach ($start as $i => &$p) {
                if ($p['grid'] === '') { $p['grid'] = $gridList[$i] ?? ''; }
            }
            unset($p);
        }

        $subs = [];
        foreach ((is_array($t['subs'] ?? null) ? $t['subs'] : []) as $p) {
            if (is_string($p)) $p = ['name' => $p];
            if (!is_array($p)) continue;
            $n = trim((string)($p['name'] ?? ''));
            if ($n === '') continue;
            $subs[] = ['name' => $n, 'number' => (isset($p['number']) && $p['number'] !== '') ? (int)$p['number'] : null];
        }

        return [
            'formation' => $formation,
            'coach'     => trim((string)($t['coach'] ?? '')),
            'start'     => $start,
            'subs'      => $subs,
        ];
    }

    /**
     * refereeFor() — يبحث عن الحكم بالترتيب:
     *   1) data/referees-manual.json  (أولويّة قصوى — تُضاف يدوياً عند إعلان FIFA)
     *   2) API-Football fixturesMap   (تلقائي حين تُحدّث API-Football بياناتها)
     * يعيد اسم الحكم (string) أو null.
     */
    public static function refereeFor(array $match): ?string
    {
        $o = self::officialsFor($match);
        $name = (string)($o['main']['name'] ?? '');
        return $name !== '' ? $name : null;
    }

    /**
     * 🆕 BUILTIN_REFEREES — تعيينات FIFA الرسمية مُضمَّنة في الكود.
     * تعمل فوراً دون رفع ملفّ JSON. أيّ ملفّ يدوي يبقى الأولوية الأعلى.
     * عند إعلان FIFA لمباراة جديدة → أضِف سطراً هنا واحفظ → سترفع PHP فقط.
     * المصدر: https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/articles/match-officials-appointed-referees
     */
    private const BUILTIN_REFEREES = [
        'Mexico|South Africa'             => ['main' => ['name' => 'WILTON SAMPAIO', 'country_ar' => 'البرازيل', 'flag' => 'br']],
        'South Korea|Czech Republic'      => ['main' => ['name' => 'MOHAMED Amin',   'country_ar' => 'مصر',      'flag' => 'eg']],
        'Canada|Bosnia and Herzegovina'   => ['main' => ['name' => 'TELLO Facundo',  'country_ar' => 'الأرجنتين','flag' => 'ar']],
        'United States|Paraguay'          => ['main' => ['name' => 'MAKKELIE Danny', 'country_ar' => 'هولندا',   'flag' => 'nl']],
        // ⚽ أضِف هنا أيّ مباراة جديدة عند إعلان FIFA — صيغة: "Team1|Team2"
    ];

    /**
     * officialsFor() — يعيد طاقم التحكيم الكامل لمباراة معيّنة:
     *   ['main' => [name,country_ar,flag], 'assistants' => [...], 'var' => ..., 'fourth' => ...]
     * المصدر بالترتيب:
     *   1) data/referees-manual.json (أولوية قصوى — أنت تتجاوز)
     *   2) BUILTIN_REFEREES const (مُضمَّن — يعمل بدون رفع JSON)
     *   3) API-Football
     * كلّ ما سبق يُثرى تلقائياً بمساعدَين + علم + دولة من Wikipedia.
     */
    /** نتائج officialsFor محفوظة بالذاكرة لكل زوج فريقين (تُستدعى لكل مباراة في كل allMatches) */
    private static array $officialsMemo = [];

    public static function officialsFor(array $match): array
    {
        $empty = ['main' => null, 'assistants' => [], 'var' => null, 'fourth' => null];

        $t1 = trim((string)($match['team1'] ?? ''));
        $t2 = trim((string)($match['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return $empty;

        $memoKey = $t1 . '|' . $t2;
        if (isset(self::$officialsMemo[$memoKey])) {
            return self::$officialsMemo[$memoKey];
        }

        // ────────────────────────────────────────────────
        // (1) الملف اليدوي — أولويّة قصوى
        // ────────────────────────────────────────────────
        $manual = self::manualReferees();
        $entry  = $manual[$t1 . '|' . $t2] ?? ($manual[$t2 . '|' . $t1] ?? null);
        if ($entry !== null) {
            if (is_string($entry) && trim($entry) !== '') {
                return self::$officialsMemo[$memoKey] = self::enrich(['main' => ['name' => trim($entry)]]);
            }
            if (is_array($entry)) {
                return self::$officialsMemo[$memoKey] = self::enrich([
                    'main'       => self::normalizeOfficial($entry['main']       ?? null),
                    'assistants' => self::normalizeOfficials($entry['assistants'] ?? null),
                    'var'        => self::normalizeOfficial($entry['var']        ?? null),
                    'fourth'     => self::normalizeOfficial($entry['fourth']     ?? null),
                ]);
            }
        }

        // ────────────────────────────────────────────────
        // (2) 🆕 BUILTIN — تعيينات FIFA مُضمَّنة في الكود
        // ────────────────────────────────────────────────
        $bi = self::BUILTIN_REFEREES[$t1 . '|' . $t2] ?? (self::BUILTIN_REFEREES[$t2 . '|' . $t1] ?? null);
        if (is_array($bi)) {
            return self::$officialsMemo[$memoKey] = self::enrich([
                'main'       => self::normalizeOfficial($bi['main']       ?? null),
                'assistants' => self::normalizeOfficials($bi['assistants'] ?? null),
                'var'        => self::normalizeOfficial($bi['var']        ?? null),
                'fourth'     => self::normalizeOfficial($bi['fourth']     ?? null),
            ]);
        }

        // ────────────────────────────────────────────────
        // (3) API-Football → اسم الحكم الرئيسي فقط
        // ────────────────────────────────────────────────
        if (self::isEnabled()) {
            $map = self::fixturesMap();
            if ($map) {
                $hit = $map[self::normalizeKey($t1, $t2)] ?? ($map[self::normalizeKey($t2, $t1)] ?? null);
                if (is_array($hit)) {
                    $ref = isset($hit['referee']) ? trim((string)$hit['referee']) : '';
                    if ($ref !== '') {
                        return self::$officialsMemo[$memoKey] = self::enrich(['main' => ['name' => $ref]]);
                    }
                }
            }
        }
        return self::$officialsMemo[$memoKey] = $empty;
    }

    /**
     * enrich() — يُثري طاقم تحكيم بمعلومات Wikipedia:
     *   - يُضيف العَلَم + الدولة بالعربيّة للحكم الرئيسي
     *   - يجلب المساعدَين تلقائياً من قائمة Wikipedia المُحلّلة (لو غير موجودَين)
     * ✨ هذا هو سرّ «الأوتوماتيك» — اسم حكم واحد → طاقم كامل!
     */
    private static function enrich(array $crew): array
    {
        $out = [
            'main'       => $crew['main']       ?? null,
            'assistants' => $crew['assistants'] ?? [],
            'var'        => $crew['var']        ?? null,
            'fourth'     => $crew['fourth']     ?? null,
        ];

        $mainName = (string)($out['main']['name'] ?? '');
        if ($mainName === '' || !class_exists('RefereesFetcher')) return $out;

        $wiki = RefereesFetcher::lookup($mainName);
        if (!$wiki) return $out;

        // أكمل بيانات الحكم الرئيسي (دون استبدال ما هو مُحدَّد يدوياً)
        if (empty($out['main']['country_ar']) && !empty($wiki['country_ar'])) {
            $out['main']['country_ar'] = $wiki['country_ar'];
        }
        if (empty($out['main']['flag']) && !empty($wiki['flag'])) {
            $out['main']['flag'] = $wiki['flag'];
        }

        // أكمل المساعدَين تلقائياً (لو لم يُحدَّدا يدوياً)
        if (empty($out['assistants']) && !empty($wiki['assistants'])) {
            $assts = [];
            foreach ($wiki['assistants'] as $a) {
                $n = trim((string)($a['name'] ?? ''));
                if ($n === '') continue;
                $assts[] = [
                    'name'       => $n,
                    'country_ar' => (string)($a['country_ar'] ?? ''),
                    'flag'       => (string)($a['flag'] ?? ''),
                ];
            }
            if ($assts) $out['assistants'] = $assts;
        }
        return $out;
    }

    /** يطبّع كائن «حكم واحد» — يضمن المفاتيح الثلاثة (name/country_ar/flag) أو null. */
    private static function normalizeOfficial($v): ?array
    {
        if (!is_array($v)) return null;
        $n = trim((string)($v['name'] ?? ''));
        if ($n === '' || strtoupper($n) === 'TBD' || $n === 'TBA') return null;
        return [
            'name'       => $n,
            'country_ar' => trim((string)($v['country_ar'] ?? '')),
            'flag'       => strtolower(trim((string)($v['flag'] ?? ''))),
        ];
    }

    /** يطبّع مصفوفة المساعدَين — يحذف null/فارغ. */
    private static function normalizeOfficials($arr): array
    {
        if (!is_array($arr)) return [];
        $out = [];
        foreach ($arr as $v) {
            $n = self::normalizeOfficial($v);
            if ($n !== null) $out[] = $n;
        }
        return $out;
    }

    /**
     * statsFor() — يجلب إحصائيات مباراة من API-Football (/fixtures/statistics).
     * يعيد مصفوفة بشكل قابل للعرض: [['k_ar', 'k_en', 'v'=>[home,away], 'unit'=>'']].
     * يُخزَّن لـ60 ثانية أثناء المباراة، 24 ساعة بعد انتهائها.
     */
    public static function statsFor(array $match): array
    {
        $t1 = trim((string)($match['team1'] ?? ''));
        $t2 = trim((string)($match['team2'] ?? ''));
        if ($t1 === '' || $t2 === '') return [];

        // ✨ ابحث عن المعرّف بالترتيب:
        // ١) كاش اللحظي (live.json) — fixture_id (API-Football) أو espn_id (ESPN المجاني)
        // ٢) fixturesMap (af-fixtures.json) — جدول الموسم (يتطلّب خطة مدفوعة لموسم 2026)
        $fid = 0;
        $espnId = '';
        $homeName = null;
        $live = self::liveScores();
        if (is_array($live)) {
            $k1 = self::normalizeKey($t1, $t2);
            $k2 = self::normalizeKey($t2, $t1);
            $hit = $live[$k1] ?? ($live[$k2] ?? null);
            if (is_array($hit)) {
                if (!empty($hit['fixture_id'])) $fid = (int)$hit['fixture_id'];
                if (!empty($hit['espn_id']))    $espnId = (string)$hit['espn_id'];
                $homeName = (string)($hit['home'] ?? '');
            }
        }
        if ($fid <= 0 && self::isEnabled()) {
            $map = self::fixturesMap();
            $hit = $map[self::normalizeKey($t1, $t2)] ?? ($map[self::normalizeKey($t2, $t1)] ?? null);
            $fid = (int)($hit['id'] ?? 0);
            $homeName = (string)($hit['home'] ?? $homeName);
        }

        // 🆕 لا fixture_id (الخطة المجانية لا تدعم 2026)؟ → إحصائيات ESPN المجانيّة
        if ($fid <= 0) {
            if ($espnId !== '' && class_exists('EspnLive')) {
                return EspnLive::statsFor($espnId);   // v=[مضيف,ضيف] — applyTo يعكس عند الحاجة
            }
            return [];
        }
        if (!self::isEnabled()) return [];

        // كاش
        $cacheFile = rtrim(CACHE_DIR, '/') . '/af-stats-' . $fid . '.json';
        $ttl = (($match['_status'] ?? '') === 'finished') ? 86400 : 60;
        $stale = null;
        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c)) {
                if (time() - filemtime($cacheFile) < $ttl) return $c;
                $stale = $c;
            }
        }

        // فشل قريب → لا تعاود الجلب الآن (يمنع دفع مهلة 5 ثوانٍ لكل مباراة في كل طلب)
        $failMarker = $cacheFile . '.fail';
        if (is_file($failMarker) && (time() - filemtime($failMarker) < 120)) {
            return $stale ?? [];
        }

        $url = 'https://' . APIFOOTBALL_HOST . '/fixtures/statistics?fixture=' . $fid;
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        if ($raw === null) { @touch($failMarker); return $stale ?? []; }
        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['response'])) { @touch($failMarker); return $stale ?? []; }

        // الاستجابة: مصفوفتان (home, away) لكلٍّ منها قائمة statistics.type/value
        // استخدم homeName المكتشف أعلاه (من live أو fixturesMap) — مع fallback لـ$t1
        $homeName = $homeName ?: $t1;
        $homeData = []; $awayData = [];
        foreach ($json['response'] as $team) {
            $isHome = ((string)($team['team']['name'] ?? '')) === $homeName;
            $stats  = is_array($team['statistics'] ?? null) ? $team['statistics'] : [];
            foreach ($stats as $s) {
                $type = (string)($s['type'] ?? '');
                $val  = $s['value'] ?? null;
                if ($isHome) $homeData[$type] = $val;
                else         $awayData[$type] = $val;
            }
        }

        // قائمة الأنواع المرغوبة + ترجمتها
        $catalog = [
            ['Ball Possession',     'الاستحواذ',        'Possession',          '%'],
            ['Total Shots',         'إجمالي التسديدات', 'Shots',               ''],
            ['Shots on Goal',       'تسديدات على المرمى','Shots on target',    ''],
            ['Shots off Goal',      'تسديدات خارجة',    'Shots off target',    ''],
            ['Blocked Shots',       'تسديدات مصدودة',   'Blocked shots',       ''],
            ['Shots insidebox',     'تسديدات داخل المنطقة','Inside box',       ''],
            ['Shots outsidebox',    'تسديدات خارج المنطقة','Outside box',      ''],
            ['Corner Kicks',        'ركلات ركنية',      'Corners',             ''],
            ['Offsides',            'تسلّل',             'Offsides',            ''],
            ['Fouls',               'الأخطاء',          'Fouls',               ''],
            ['Yellow Cards',        'بطاقات صفراء',     'Yellow cards',        ''],
            ['Red Cards',           'بطاقات حمراء',     'Red cards',           ''],
            ['Goalkeeper Saves',    'تصدّيات الحارس',    'Saves',              ''],
            ['Total passes',        'تمريرات',          'Passes',              ''],
            ['Passes accurate',     'تمريرات دقيقة',    'Accurate passes',     ''],
            ['Passes %',            'دقّة التمرير',     'Pass accuracy',       '%'],
        ];
        $out = [];
        foreach ($catalog as [$type, $kar, $ken, $unit]) {
            $vh = self::statValue($homeData[$type] ?? null, $unit);
            $va = self::statValue($awayData[$type] ?? null, $unit);
            if ($vh === null && $va === null) continue;
            if ($vh === 0 && $va === 0)       continue;
            $out[] = ['k' => $kar, 'k_en' => $ken, 'v' => [(int)$vh, (int)$va], 'unit' => $unit];
        }

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    /** يحوّل قيمة API-Football («57%», 18, null) لعدد صحيح. */
    private static function statValue($v, string $unit): ?int
    {
        if ($v === null) return null;
        if (is_string($v)) {
            $v = trim($v);
            if ($v === '') return null;
            $v = (int)preg_replace('/[^0-9\-]/', '', $v);
        }
        return (int)$v;
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
        static $memo = null;
        if ($memo !== null) return $memo;

        $cacheFile = rtrim(CACHE_DIR, '/') . '/af-fixtures.json';
        $stale     = null;
        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c)) {
                if (time() - filemtime($cacheFile) < 86400) return $memo = $c;
                $stale = $c;   // قديمة لكنها صالحة كاحتياط عند فشل الجلب
            }
        }

        // فشل قريب مُسجَّل → لا تعاود الجلب في كل طلب (كانت كل صفحة تدفع مهلة 5 ثوانٍ)
        $failMarker = $cacheFile . '.fail';
        if (is_file($failMarker) && (time() - filemtime($failMarker) < 300)) {
            return $memo = ($stale ?? []);
        }

        $url = 'https://' . APIFOOTBALL_HOST . '/fixtures'
             . '?league=' . APIFOOTBALL_LEAGUE
             . '&season=' . APIFOOTBALL_SEASON;
        $raw = self::httpGet($url, ['x-apisports-key: ' . APIFOOTBALL_KEY]);
        if ($raw === null) {
            // فشل الجلب → سجّل الفشل (يمنع الإلحاح) وقدّم النسخة القديمة إن وُجدت
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @touch($failMarker);
            return $memo = ($stale ?? []);
        }

        $json = json_decode($raw, true);
        if (!is_array($json) || !isset($json['response'])) {
            @touch($failMarker);
            return $memo = ($stale ?? []);
        }

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
            @unlink($failMarker);
        } else {
            // استجابة سليمة لكن فارغة (مثلاً نفدت الحصّة) → سجّلها فشلاً مؤقّتاً أيضاً
            @touch($failMarker);
        }
        return $memo = ($map ?: ($stale ?? []));
    }
}
