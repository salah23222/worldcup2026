<?php
/**
 * EspnLive.php — مصدر نتائج لحظية مجاني (واجهة ESPN العامة غير الموثّقة).
 * ============================================================
 * بديل تلقائي عندما يفشل API-Football أو لا تدعم خطته الموسم
 * (الخطة المجانية لا تدعم 2026). بلا مفتاح، بلا تسجيل، بلا حدود معلنة.
 *
 * يعيد نفس صيغة LiveService::fetchLive تماماً:
 *   key = normalizeKey(home, away) →
 *   ['home','away','ft'=>[g1,g2],'elapsed','status','short','fixture_id','referee']
 *
 * يوفّر أيضاً (عبر summary endpoint):
 *   statsFor()  — إحصائيات المباراة (استحواذ/تسديدات/أخطاء/بطاقات...)
 *   lineupFor() — التشكيلة الرسمية (تظهر قبل الانطلاق بساعة تقريباً)
 *
 * ملاحظة أسماء: ESPN تكتب "Czechia"/"USA" — أسماء openfootball تختلف،
 * والمطابقة تتم عبر LiveService::normalizeKey التي تتكفّل بالمرادفات.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class EspnLive
{
    private const URL = 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard';

    /**
     * fetchLive() — يجلب مباريات اليوم من ESPN ويحوّلها لصيغة LiveService.
     * null = فشل الجلب/التحليل. [] = نجاح بلا مباريات اليوم.
     */
    public static function fetchLive(): ?array
    {
        $timeout = defined('FETCH_TIMEOUT') ? max(1, (int)FETCH_TIMEOUT) : 5;
        $raw = function_exists('http_get') ? http_get(self::URL, ['timeout' => $timeout]) : null;
        if ($raw === null) return null;

        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['events']) || !is_array($j['events'])) return null;

        $out = [];
        foreach ($j['events'] as $ev) {
            $comp = $ev['competitions'][0] ?? null;
            if (!is_array($comp)) continue;

            $home = $away = null;
            foreach (($comp['competitors'] ?? []) as $c) {
                if (!is_array($c)) continue;
                if (($c['homeAway'] ?? '') === 'home') $home = $c;
                elseif (($c['homeAway'] ?? '') === 'away') $away = $c;
            }
            if (!$home || !$away) continue;

            $hn = trim((string)($home['team']['name'] ?? ''));
            $an = trim((string)($away['team']['name'] ?? ''));
            if ($hn === '' || $an === '') continue;

            // الحالة: pre = لم تبدأ · in = جارية · post = انتهت
            $state  = strtolower((string)($ev['status']['type']['state'] ?? 'pre'));
            $status = ($state === 'in') ? 'live' : (($state === 'post') ? 'finished' : 'upcoming');

            // دقيقة اللعب من displayClock مثل "67'"
            $elapsed = null;
            if (preg_match('/(\d+)/', (string)($ev['status']['displayClock'] ?? ''), $mm)) {
                $elapsed = (int)$mm[1];
            }

            $key = LiveService::normalizeKey($hn, $an);
            $out[$key] = [
                'home'       => $hn,
                'away'       => $an,
                'ft'         => [(int)($home['score'] ?? 0), (int)($away['score'] ?? 0)],
                'elapsed'    => $elapsed,
                'status'     => $status,
                'short'      => (string)($ev['status']['type']['shortDetail'] ?? ''),
                'fixture_id' => null,    // خاص بـ API-Football — غير متاح هنا
                'espn_id'    => (string)($ev['id'] ?? ''),   // 🆕 لجلب الإحصائيات/التشكيلة
                'referee'    => null,
                '_src'       => 'espn',
            ];
        }

        // 🆕 راكم خريطة key→espn_id الدائمة (تتيح جلب تقارير الأيّام الماضية لاحقاً)
        $ids = [];
        foreach ($out as $k => $v) {
            if (!empty($v['espn_id'])) $ids[$k] = $v['espn_id'];
        }
        self::rememberIds($ids);

        return $out;
    }

    // ════════════════════════════════════════════════════════════
    //  🆕 خريطة معرّفات دائمة — key → espn_id (تتراكم يوماً بعد يوم)
    // ════════════════════════════════════════════════════════════

    private static function idsMapFile(): string
    {
        return rtrim(CACHE_DIR, '/') . '/espn-ids.json';
    }

    private static function rememberIds(array $pairs): void
    {
        if (!$pairs) return;
        $f = self::idsMapFile();
        $map = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
        $dirty = false;
        foreach ($pairs as $k => $id) {
            if (($map[$k] ?? '') !== $id) { $map[$k] = $id; $dirty = true; }
        }
        if ($dirty) {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($f, json_encode($map, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * idFor($key, $dateYmd) — معرّف ESPN لمباراة بأيّ تاريخ (ماضٍ أو حاضر).
     * يقرأ الخريطة المتراكمة؛ ولو ناقصة يجلب لوحة ذلك اليوم
     * (scoreboard?dates=YYYYMMDD — يعمل للأيّام الماضية) ويكمّلها ذاتياً.
     */
    public static function idFor(string $key, string $dateYmd = ''): string
    {
        $f = self::idsMapFile();
        $map = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
        if (!empty($map[$key])) return (string)$map[$key];
        if (!preg_match('/^\d{8}$/', $dateYmd)) return '';

        // كاش لوحة اليوم المؤرّخ (6 ساعات — لوحات الماضي لا تتغيّر)
        $dayFile = rtrim(CACHE_DIR, '/') . '/espn-day-' . $dateYmd . '.json';
        $j = null;
        if (is_file($dayFile) && (time() - filemtime($dayFile) < 21600)) {
            $j = json_decode((string)@file_get_contents($dayFile), true);
        }
        if (!is_array($j)) {
            $fail = $dayFile . '.fail';
            if (is_file($fail) && (time() - filemtime($fail) < 600)) return '';
            $timeout = defined('FETCH_TIMEOUT') ? max(1, (int)FETCH_TIMEOUT) : 5;
            $raw = function_exists('http_get') ? http_get(self::URL . '?dates=' . $dateYmd, ['timeout' => $timeout]) : null;
            $j = ($raw !== null) ? json_decode($raw, true) : null;
            if (!is_array($j)) { @touch($fail); return ''; }
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($dayFile, json_encode($j, JSON_UNESCAPED_UNICODE));
            @unlink($fail);
        }

        // استخرج key→id من لوحة ذلك اليوم وادمجها في الخريطة
        $ids = [];
        foreach (($j['events'] ?? []) as $ev) {
            $comp = $ev['competitions'][0] ?? null;
            if (!is_array($comp)) continue;
            $h = $a = null;
            foreach (($comp['competitors'] ?? []) as $c) {
                if (($c['homeAway'] ?? '') === 'home') $h = $c;
                elseif (($c['homeAway'] ?? '') === 'away') $a = $c;
            }
            if (!$h || !$a) continue;
            $hn = trim((string)($h['team']['name'] ?? ''));
            $an = trim((string)($a['team']['name'] ?? ''));
            $id = (string)($ev['id'] ?? '');
            if ($hn === '' || $an === '' || $id === '') continue;
            $ids[LiveService::normalizeKey($hn, $an)] = $id;
        }
        self::rememberIds($ids);
        return (string)($ids[$key] ?? '');
    }

    /**
     * officialsFor($eventId) — طاقم التحكيم من gameInfo.officials.
     * ESPN ينشر التعيينات الرسمية (حسب إعلان FIFA) قرب موعد كل مباراة.
     * يعيد ['main','assistants','var','fourth'] بأسماء فقط — الإثراء
     * (علم + دولة) يتمّ في LiveService::enrich عبر قائمة Wikipedia.
     */
    public static function officialsFor(string $eventId): ?array
    {
        $j = self::summary($eventId);
        $offs = $j['gameInfo']['officials'] ?? null;
        if (!is_array($offs) || !$offs) return null;

        $crew = ['main' => null, 'assistants' => [], 'var' => null, 'fourth' => null];
        foreach ($offs as $o) {
            $name = trim((string)($o['fullName'] ?? ($o['displayName'] ?? '')));
            if ($name === '') continue;
            $pos = strtolower((string)($o['position']['name'] ?? ($o['position']['displayName'] ?? '')));
            $entry = ['name' => $name, 'country_ar' => '', 'flag' => ''];
            // الترتيب مهم: "assistant referee" تحوي "referee" — افحص الأخصّ أوّلاً
            if (strpos($pos, 'video') !== false || strpos($pos, 'var') !== false) {
                if (!$crew['var']) $crew['var'] = $entry;
            } elseif (strpos($pos, 'fourth') !== false) {
                if (!$crew['fourth']) $crew['fourth'] = $entry;
            } elseif (strpos($pos, 'assistant') !== false || strpos($pos, 'line') !== false) {
                $crew['assistants'][] = $entry;
            } elseif (strpos($pos, 'referee') !== false && !$crew['main']) {
                $crew['main'] = $entry;
            }
        }
        return !empty($crew['main']['name']) ? $crew : null;
    }

    /**
     * scoreFor($eventId) — النتيجة النهائيّة من رأس الملخّص.
     * يعيد ['home'=>g, 'away'=>g, 'finished'=>bool] أو null.
     * يُستخدم لاستعادة نتيجة مباراة منتهية حين يتأخّر openfootball المجتمعي.
     */
    public static function scoreFor(string $eventId): ?array
    {
        $j = self::summary($eventId);
        if (!is_array($j)) return null;
        $comp = $j['header']['competitions'][0] ?? null;
        if (!is_array($comp)) return null;

        $state = strtolower((string)($comp['status']['type']['state'] ?? ''));
        $home = $away = null;
        foreach (($comp['competitors'] ?? []) as $c) {
            if (($c['homeAway'] ?? '') === 'home') $home = $c;
            elseif (($c['homeAway'] ?? '') === 'away') $away = $c;
        }
        if (!$home || !$away) return null;
        return [
            'home'     => (int)($home['score'] ?? 0),
            'away'     => (int)($away['score'] ?? 0),
            'finished' => ($state === 'post'),
        ];
    }

    /**
     * eventsFor($eventId) — الأهداف والبطاقات من keyEvents:
     *   ['goals' => [['side','name','minute','offset?','penalty?','owngoal?']],
     *    'cards' => [['side','minute','name','type'=>'yellow'|'red']]]
     * side = 'home'|'away' (بالنسبة لمضيف ESPN). تُستثنى ركلات الترجيح.
     */
    public static function eventsFor(string $eventId): array
    {
        $empty = ['goals' => [], 'cards' => []];
        $j = self::summary($eventId);
        if (!is_array($j)) return $empty;
        $homeId = self::homeTeamId($j);
        $goals = $cards = [];

        foreach (($j['keyEvents'] ?? []) as $e) {
            $tt = strtolower((string)($e['type']['type'] ?? ''));
            $teamId = (string)($e['team']['id'] ?? '');
            if ($teamId === '') continue;
            $side = ($teamId === $homeId) ? 'home' : 'away';
            $name = trim((string)($e['participants'][0]['athlete']['displayName'] ?? ''));

            // الدقيقة من "45'+4'" أو "9'"
            $minute = 0; $offset = 0;
            if (preg_match('/(\d+)(?:\D+(\d+))?/', (string)($e['clock']['displayValue'] ?? ''), $mm)) {
                $minute = (int)$mm[1];
                $offset = (int)($mm[2] ?? 0);
            }

            $desc = trim((string)($e['text'] ?? ''));

            if (!empty($e['scoringPlay']) && empty($e['shootout'])) {
                $g = ['side' => $side, 'name' => $name, 'minute' => $minute];
                if ($offset) $g['offset'] = $offset;
                if (strpos($tt, 'pen') !== false) $g['penalty'] = true;
                if (strpos($tt, 'own') !== false) {
                    // الهدف العكسي يُحسب للفريق المنافس (لاعبه سجّل في مرماه)
                    $g['owngoal'] = true;
                    $g['side'] = ($side === 'home') ? 'away' : 'home';
                }
                // 🆕 صانع الهدف من النص الوصفي: "Assisted by Érik Lira."
                if (preg_match('/Assisted by ([^.]+)\./u', $desc, $am)) {
                    $g['assist'] = trim($am[1]);
                }
                $goals[] = $g;
            } elseif ($tt === 'yellow-card' || $tt === 'red-card') {
                $card = ['side' => $side, 'minute' => $minute, 'name' => $name,
                         'type' => ($tt === 'red-card') ? 'red' : 'yellow'];
                // 🆕 تفسير الحالة التحكيميّة (سبب البطاقة) — عربي + إنجليزي
                $ar = self::cardReasonAr($desc);
                if ($ar !== '') $card['reason_ar'] = $ar;
                if (preg_match('/card(?: for (.+?))?\.?\s*$/u', $desc, $rm) && !empty($rm[1])) {
                    $card['reason_en'] = trim($rm[1]);
                }
                $cards[] = $card;
            }
        }
        return ['goals' => $goals, 'cards' => $cards];
    }

    /** ترجمة أسباب البطاقات الشائعة في نصوص ESPN إلى العربيّة. */
    private static function cardReasonAr(string $text): string
    {
        static $map = [
            'second yellow'        => 'الإنذار الثاني',
            'violent conduct'      => 'سلوك عنيف',
            'serious foul play'    => 'تدخّل عنيف خطير',
            'professional foul'    => 'إعاقة هجمة واعدة',
            'denying an obvious'   => 'حرمان من فرصة محقّقة',
            'bad foul'             => 'خطأ قويّ',
            'handball'             => 'لمسة يد',
            'handling the ball'    => 'لمسة يد',
            'dissent'              => 'الاعتراض على الحكم',
            'time wasting'         => 'إضاعة الوقت',
            'delaying the restart' => 'تأخير استئناف اللعب',
            'simulation'           => 'التمثيل',
            'diving'               => 'التمثيل (سقوط متعمّد)',
            'unsporting'           => 'سلوك غير رياضي',
            'off the ball'         => 'حالة بعيدة عن الكرة',
            'altercation'          => 'مشادّة',
        ];
        $lt = strtolower($text);
        foreach ($map as $en => $ar) {
            if (strpos($lt, $en) !== false) return $ar;
        }
        return '';
    }

    // ════════════════════════════════════════════════════════════
    //  🆕 summary — إحصائيات + تشكيلات (مجاني، بدون مفتاح)
    // ════════════════════════════════════════════════════════════

    private const SUMMARY = 'https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/summary?event=';

    /** يجلب summary مباراة (مع كاش LIVE_CACHE_TTL + fail-marker). */
    private static function summary(string $eventId): ?array
    {
        $eventId = trim($eventId);
        if ($eventId === '' || !preg_match('/^\d+$/', $eventId)) return null;

        $cacheFile = rtrim(CACHE_DIR, '/') . '/espn-sum-' . $eventId . '.json';
        $ttl = defined('LIVE_CACHE_TTL') ? max(30, (int)LIVE_CACHE_TTL) : 60;
        $stale = null;
        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c)) {
                if (time() - filemtime($cacheFile) < $ttl) return $c;
                $stale = $c;
            }
        }
        $fail = $cacheFile . '.fail';
        if (is_file($fail) && (time() - filemtime($fail) < 120)) return $stale;

        $timeout = defined('FETCH_TIMEOUT') ? max(1, (int)FETCH_TIMEOUT) : 5;
        $raw = function_exists('http_get') ? http_get(self::SUMMARY . $eventId, ['timeout' => $timeout]) : null;
        if ($raw === null) { @touch($fail); return $stale; }
        $j = json_decode($raw, true);
        if (!is_array($j)) { @touch($fail); return $stale; }

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        $tmp = $cacheFile . '.tmp';
        if (@file_put_contents($tmp, json_encode($j, JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $cacheFile);
        }
        @unlink($fail);
        return $j;
    }

    /** يحدّد أيّ team.id في boxscore هو المضيف (من header.competitions). */
    private static function homeTeamId(array $j): string
    {
        foreach (($j['header']['competitions'][0]['competitors'] ?? []) as $c) {
            if (($c['homeAway'] ?? '') === 'home') return (string)($c['team']['id'] ?? '');
        }
        return '';
    }

    /**
     * statsFor($eventId) — إحصائيات بصيغة LiveService::statsFor نفسها:
     *   [['k'=>عربي, 'k_en'=>EN, 'v'=>[home,away], 'unit'=>''|'%'], ...]
     * ترتيب v دائماً [مضيف, ضيف] — مَن يستدعيها يتكفّل بالعكس عند الحاجة.
     */
    public static function statsFor(string $eventId): array
    {
        $j = self::summary($eventId);
        if (!is_array($j)) return [];
        $teams = $j['boxscore']['teams'] ?? null;
        if (!is_array($teams) || count($teams) < 2) return [];

        $homeId = self::homeTeamId($j);
        $homeData = $awayData = [];
        foreach ($teams as $t) {
            $isHome = ((string)($t['team']['id'] ?? '')) === $homeId;
            foreach (($t['statistics'] ?? []) as $s) {
                $name = (string)($s['name'] ?? '');
                $val  = (string)($s['displayValue'] ?? '');
                if ($name === '') continue;
                if ($isHome) $homeData[$name] = $val;
                else         $awayData[$name] = $val;
            }
        }
        if (!$homeData && !$awayData) return [];

        // أسماء ESPN → التسمية المعروضة (نفس كتالوج LiveService)
        $catalog = [
            ['possessionPct',  'الاستحواذ',          'Possession',       '%'],
            ['totalShots',     'إجمالي التسديدات',   'Shots',            ''],
            ['shotsOnTarget',  'تسديدات على المرمى', 'Shots on target',  ''],
            ['wonCorners',     'ركلات ركنية',        'Corners',          ''],
            ['offsides',       'تسلّل',               'Offsides',         ''],
            ['foulsCommitted', 'الأخطاء',            'Fouls',            ''],
            ['yellowCards',    'بطاقات صفراء',       'Yellow cards',     ''],
            ['redCards',       'بطاقات حمراء',       'Red cards',        ''],
            ['saves',          'تصدّيات الحارس',      'Saves',           ''],
        ];
        $num = function ($v): ?int {
            $v = trim((string)$v);
            if ($v === '') return null;
            return (int)round((float)preg_replace('/[^0-9.\-]/', '', $v));
        };
        $out = [];
        foreach ($catalog as [$key, $kar, $ken, $unit]) {
            $vh = $num($homeData[$key] ?? null);
            $va = $num($awayData[$key] ?? null);
            if ($vh === null && $va === null) continue;
            if ((int)$vh === 0 && (int)$va === 0 && $key !== 'possessionPct') continue;
            $out[] = ['k' => $kar, 'k_en' => $ken, 'v' => [(int)$vh, (int)$va], 'unit' => $unit];
        }
        return $out;
    }

    /**
     * lineupFor($eventId) — التشكيلة الرسمية من ESPN rosters.
     * يعيد ['home'=>[formation,coach,start,subs], 'away'=>...] أو null إن لم تصدر.
     * ترتيب الأساسيين حسب formationPlace (حارس→دفاع→وسط→هجوم) ليتوافق مع رسم الملعب.
     */
    public static function lineupFor(string $eventId): ?array
    {
        $j = self::summary($eventId);
        if (!is_array($j)) return null;
        $rosters = $j['rosters'] ?? null;
        if (!is_array($rosters)) return null;

        $out = ['home' => null, 'away' => null];
        foreach ($rosters as $r) {
            $side = (($r['homeAway'] ?? '') === 'home') ? 'home' : ((($r['homeAway'] ?? '') === 'away') ? 'away' : null);
            if ($side === null) continue;
            $start = $subs = [];
            foreach (($r['roster'] ?? []) as $p) {
                $name = trim((string)($p['athlete']['displayName'] ?? ''));
                if ($name === '') continue;
                $numJ = isset($p['jersey']) ? (int)$p['jersey'] : null;
                if (!empty($p['starter'])) {
                    $start[] = ['name' => $name, 'number' => $numJ, 'grid' => '',
                                '_fp' => (int)($p['formationPlace'] ?? 99)];
                } else {
                    $subs[] = ['name' => $name, 'number' => $numJ];
                }
            }
            if (count($start) < 11) continue;   // التشكيلة لم تصدر بعد
            usort($start, fn($a, $b) => $a['_fp'] <=> $b['_fp']);
            foreach ($start as &$s) unset($s['_fp']);
            unset($s);
            $out[$side] = [
                'formation' => (string)($r['formation'] ?? ''),
                'coach'     => '',
                'start'     => $start,
                'subs'      => $subs,
            ];
        }
        return ($out['home'] && $out['away']) ? $out : null;
    }
}
