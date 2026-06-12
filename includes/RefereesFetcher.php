<?php
/**
 * RefereesFetcher.php — جلب أوتوماتيكي لطاقم التحكيم من Wikipedia.
 * ============================================================
 * استراتيجيّة:
 *   1) Wikipedia tabular data → قائمة 52 حكم + 88 مساعد + جنسيّاتهم
 *   2) خريطة دولة → كود علم (ISO2) + اسم عربي
 *   3) عند معرفة الحكم الرئيسي (من manual/API-Football)، يتمّ
 *      إثراؤه تلقائياً بمساعديه + علمه + دولته بالعربية.
 *
 * نتيجة: عند إعلان FIFA لحكم → اسم واحد فقط في referees-manual.json
 *        ↓
 *        النظام يجلب تلقائياً مساعديه + العَلَم + الدولة ع.
 *
 * كاش: 7 أيّام (القائمة لا تتغيّر — تُعلَن مرّة واحدة).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class RefereesFetcher
{
    const WIKI_URL  = 'https://en.wikipedia.org/w/api.php?action=parse&page=2026_FIFA_World_Cup_officials&format=json&prop=wikitext&redirects=true';
    // 6 ساعات: صفحة ويكيبيديا تُحدَّث يومياً بتعيينات FIFA لكل مباراة
    // (عمودا «Matches assigned» و«Fourth official») — نلتقطها بنفس اليوم.
    const CACHE_TTL = 21600;

    /** مهلة إعادة المحاولة بعد جلب فاشل/فارغ — تمنع إعادة الجلب في كل طلب. */
    const FAIL_RETRY_AFTER = 900; // 15 دقيقة

    /** نسخة الذاكرة لهذا الطلب — lookup() تُستدعى عدّة مرات في الصفحة الواحدة. */
    private static ?array $memo = null;

    /** يقرأ قائمة الحكام المُحلَّلة (من الكاش أو يجلبها من Wikipedia). */
    public static function all(): array
    {
        if (self::$memo !== null) return self::$memo;

        $cacheFile = self::cachePath();
        $cached    = null;
        if (is_file($cacheFile)) {
            $d = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($d)) $cached = $d;
        }

        // كاش حديث → استخدمه مباشرة.
        if ($cached !== null && (time() - filemtime($cacheFile) < self::CACHE_TTL)) {
            return self::$memo = $cached;
        }

        // فشل قريب مُسجَّل؟ لا تعاود الجلب الآن — قدّم القديم (إن وُجد) بدل تعليق الصفحة.
        $failMarker = $cacheFile . '.fail';
        if (is_file($failMarker) && (time() - filemtime($failMarker) < self::FAIL_RETRY_AFTER)) {
            return self::$memo = ($cached ?? []);
        }

        $fresh = self::refresh();
        // فشل الجلب/التحليل → سجّل الفشل (حتى لا يتكرّر مع كل طلب) وقدّم القديم.
        if (!$fresh) {
            if (!is_dir(dirname($failMarker))) @mkdir(dirname($failMarker), 0755, true);
            @touch($failMarker);
            return self::$memo = ($cached ?? []);
        }
        return self::$memo = $fresh;
    }

    /** يفرض جلباً جديداً من Wikipedia + يحفظ النتيجة. */
    public static function refresh(): array
    {
        $raw = self::httpGet(self::WIKI_URL);
        if ($raw === null) return [];
        $json = json_decode($raw, true);
        $wt   = $json['parse']['wikitext']['*'] ?? '';
        if ($wt === '') return [];

        $list = self::parseTable($wt);
        if (!$list) return [];

        $cacheFile = self::cachePath();
        if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @unlink($cacheFile . '.fail');
        return $list;
    }

    /**
     * lookup($name) — يبحث عن حكم بأيّ صيغة اسم:
     *   "WILTON SAMPAIO" / "Wilton Sampaio" / "MOHAMED Amin" / "Amin Mohamed"
     * يعيد ['name', 'country', 'country_ar', 'flag', 'assistants' => [...]] أو null.
     */
    public static function lookup(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') return null;
        $tokens = self::tokens($name);
        if (!$tokens) return null;

        $all = self::all();
        $best = null; $bestScore = 0;
        foreach ($all as $r) {
            $rTokens = self::tokens($r['name'] ?? '');
            $score   = count(array_intersect($tokens, $rTokens));
            if ($score > $bestScore) { $bestScore = $score; $best = $r; }
        }
        if (!$best || $bestScore < 2) return null;  // اشترط مطابقة كلمتَين على الأقل

        $country   = (string)($best['country'] ?? '');
        $countryAr = self::countryArabic($country);
        $flag      = self::countryFlag($country);
        $assistants = [];
        foreach ($best['assistants'] ?? [] as $a) {
            $aCountry = (string)($a['country'] ?? '');
            $assistants[] = [
                'name'       => (string)($a['name'] ?? ''),
                'country_ar' => self::countryArabic($aCountry),
                'flag'       => self::countryFlag($aCountry),
            ];
        }
        return [
            'name'       => (string)$best['name'],
            'country'    => $country,
            'country_ar' => $countryAr,
            'flag'       => $flag,
            'assistants' => $assistants,
        ];
    }

    /**
     * lookupAny($name) — يبحث في «كل» أسماء القائمة: الحكام والمساعدين معاً.
     * يفيد لإثراء مساعدي ESPN وحكّام VAR/الرابع بعلَمهم ودولتهم.
     * يعيد ['country_ar','flag'] أو null.
     */
    public static function lookupAny(string $name): ?array
    {
        $tokens = self::tokens($name);
        if (count($tokens) < 2) return null;

        $best = null; $bestScore = 0;
        foreach (self::all() as $r) {
            // الحكم الرئيسي
            $score = count(array_intersect($tokens, self::tokens($r['name'] ?? '')));
            if ($score > $bestScore) { $bestScore = $score; $best = (string)($r['country'] ?? ''); }
            // مساعدوه
            foreach ($r['assistants'] ?? [] as $a) {
                $score = count(array_intersect($tokens, self::tokens($a['name'] ?? '')));
                if ($score > $bestScore) { $bestScore = $score; $best = (string)($a['country'] ?? ''); }
            }
        }
        if ($best === null || $bestScore < 2) return null;
        return ['country_ar' => self::countryArabic($best), 'flag' => self::countryFlag($best)];
    }

    // ────────────────────────────────────────────────────────
    //  Parser
    // ────────────────────────────────────────────────────────

    private static function parseTable(string $wt): array
    {
        // قصّ قسم الجدول
        if (!preg_match('/==Referees and assistant referees==(.*?)==Video/s', $wt, $m)) return [];
        $tbl = $m[1];

        // قسّم على |- (يفصل الصفوف)
        $parts = preg_split('/\n\|-\n/', $tbl);
        if (!is_array($parts)) return [];

        $referees = [];
        foreach ($parts as $p) {
            // تخطّى صفّ الرأس (يبدأ بـ !)
            if (preg_match('/^\s*!/', $p)) continue;

            // اجمع خلايا الصفّ (كل سطر يبدأ بـ |)
            // ⚠️ الخلايا الفارغة «|» مشروعة ويجب حفظها — إسقاطها يُزيح الأعمدة
            //    فيُقرأ تعيين «الحكم الرابع» وكأنّه تعيين «رئيسي»!
            $cells = []; $cur = null;
            foreach (explode("\n", $p) as $line) {
                if (strlen($line) > 0 && $line[0] === '|') {
                    if ($cur !== null) $cells[] = trim($cur);
                    $cur = ltrim($line, '|');
                } elseif ($cur !== null) {
                    $cur .= "\n" . $line;
                }
            }
            if ($cur !== null) $cells[] = trim($cur);

            // احذف rowspan="x"|
            $cells = array_map(fn($c) => preg_replace('/^rowspan="?\d+"?\s*\|\s*/', '', $c), $cells);

            // نريد آخر 4 خلايا [referee, assistants, matches, fourth]
            if (count($cells) < 2) continue;
            if (count($cells) >= 4) $cells = array_slice($cells, -4);
            elseif (count($cells) === 3) $cells = array_merge($cells, ['']);
            else $cells = array_merge($cells, ['', '']);

            $refRaw  = $cells[0] ?? '';
            $asstRaw = $cells[1] ?? '';
            if ($refRaw === '' || stripos($refRaw, 'Vacant') !== false) continue;

            $refClean = self::clean($refRaw);
            if (!preg_match('/(.+?)\s*\(([^)]+)\)/', str_replace("\n", ' ', $refClean), $rm)) continue;

            $assistants = [];
            foreach (explode("\n", self::clean($asstRaw)) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('/(.+?)\s*\(([^)]+)\)/', $line, $am)) {
                    $assistants[] = ['name' => trim($am[1]), 'country' => trim($am[2])];
                }
            }
            $referees[] = [
                'name'       => trim($rm[1]),
                'country'    => trim($rm[2]),
                'assistants' => $assistants,
                // 🆕 تعيينات FIFA لكل مباراة (تملؤها ويكيبيديا تباعاً)
                'assigned'   => self::parseAssigned($cells[2] ?? ''),   // حكماً رئيسياً
                'fourth_of'  => self::parseAssigned($cells[3] ?? ''),   // حكماً رابعاً
            ];
        }
        return $referees;
    }

    /**
     * parseAssigned() — يحلّل خليّة «Matches assigned»:
     *   "Canada–Bosnia and Herzegovina (Group B)<br/>Ivory Coast–Ecuador (Group E)"
     * يعيد [['Canada','Bosnia and Herzegovina'], ['Ivory Coast','Ecuador']].
     */
    private static function parseAssigned(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        $out = [];
        foreach (explode("\n", self::clean($raw)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $line = preg_replace('/\s*\([^)]*\)\s*$/u', '', $line);   // احذف "(Group B)"
            // الفاصل: شرطة طويلة – أو — أو " v " أو " vs "
            $parts = preg_split('/\s*(?:–|—|\bvs?\.?\b)\s*/u', $line);
            if (is_array($parts) && count($parts) === 2
                && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                $out[] = [trim($parts[0]), trim($parts[1])];
            }
        }
        return $out;
    }

    /**
     * assignmentFor($t1, $t2) — تعيين FIFA الرسمي لمباراة (من ويكيبيديا):
     * يعيد طاقماً جاهزاً ['main','assistants','var'=>null,'fourth'] أو null.
     * المطابقة عبر LiveService::normalizeKey (تتكفّل بمرادفات الأسماء).
     */
    public static function assignmentFor(string $t1, string $t2): ?array
    {
        if (!class_exists('LiveService')) return null;
        $want = [LiveService::normalizeKey($t1, $t2), LiveService::normalizeKey($t2, $t1)];

        $pairMatches = function (array $pair) use ($want): bool {
            $k = LiveService::normalizeKey($pair[0], $pair[1]);
            return in_array($k, $want, true);
        };

        $crew = null;
        $fourth = null;
        foreach (self::all() as $r) {
            foreach (($r['assigned'] ?? []) as $pair) {
                if ($pairMatches($pair)) {
                    $assts = [];
                    foreach (($r['assistants'] ?? []) as $a) {
                        $assts[] = [
                            'name'       => (string)($a['name'] ?? ''),
                            'country_ar' => self::countryArabic((string)($a['country'] ?? '')),
                            'flag'       => self::countryFlag((string)($a['country'] ?? '')),
                        ];
                    }
                    $crew = [
                        'main' => [
                            'name'       => (string)$r['name'],
                            'country_ar' => self::countryArabic((string)($r['country'] ?? '')),
                            'flag'       => self::countryFlag((string)($r['country'] ?? '')),
                        ],
                        'assistants' => $assts,
                        'var'        => null,
                        'fourth'     => null,
                    ];
                    break;
                }
            }
            if ($fourth === null) {
                foreach (($r['fourth_of'] ?? []) as $pair) {
                    if ($pairMatches($pair)) {
                        $fourth = [
                            'name'       => (string)$r['name'],
                            'country_ar' => self::countryArabic((string)($r['country'] ?? '')),
                            'flag'       => self::countryFlag((string)($r['country'] ?? '')),
                        ];
                        break;
                    }
                }
            }
            if ($crew !== null && $fourth !== null) break;
        }

        if ($crew === null && $fourth === null) return null;
        if ($crew === null) {
            // لم يُملأ الحكم الرئيسي بعد، لكنّ الرابع معروف — أعِد الرابع فقط
            $crew = ['main' => null, 'assistants' => [], 'var' => null, 'fourth' => $fourth];
        } else {
            $crew['fourth'] = $fourth;
        }
        return $crew;
    }

    /** يطبّع وسوم Wikipedia: [[a|b]]→b, {{ill|x|...}}→x, <br>→\n */
    private static function clean(string $s): string
    {
        $s = preg_replace('/\[\[[^|\]]+\|([^\]]+)\]\]/u', '$1', $s);
        $s = preg_replace('/\[\[([^\]]+)\]\]/u', '$1', $s);
        $s = preg_replace('/\{\{ill\|([^|}]+)[^}]*\}\}/u', '$1', $s);
        $s = preg_replace('/\{\{efn[^}]*\}\}/u', '', $s);
        $s = preg_replace("/''([^']+)''/u", '$1', $s);
        $s = preg_replace('/\{\{[^}]+\}\}/u', '', $s);
        $s = str_replace(['<br>', '<br/>', '<br />'], "\n", $s);
        return trim($s);
    }

    /** يحوّل الاسم لقائمة tokens (lowercase, ≥3 أحرف). */
    private static function tokens(string $name): array
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = preg_replace('/[^a-z\s]/', ' ', $name);
        $toks = preg_split('/\s+/', trim($name)) ?: [];
        return array_values(array_filter($toks, fn($t) => strlen($t) >= 3));
    }

    // ────────────────────────────────────────────────────────
    //  دول: ISO2 + الاسم العربي
    // ────────────────────────────────────────────────────────

    private static function countryFlag(string $en): string
    {
        static $map = [
            'United Arab Emirates'=>'ae','Qatar'=>'qa','Saudi Arabia'=>'sa','Australia'=>'au',
            'China'=>'cn','Jordan'=>'jo','Uzbekistan'=>'uz','Japan'=>'jp','Iran'=>'ir',
            'South Korea'=>'kr','Cameroon'=>'cm','Gabon'=>'ga','Mauritania'=>'mr','Angola'=>'ao',
            'Algeria'=>'dz','Morocco'=>'ma','Egypt'=>'eg','South Africa'=>'za','El Salvador'=>'sv',
            'Costa Rica'=>'cr','United States'=>'us','Jamaica'=>'jm','Trinidad and Tobago'=>'tt',
            'Canada'=>'ca','Mexico'=>'mx','Nicaragua'=>'ni','Honduras'=>'hn','Panama'=>'pa',
            'Guatemala'=>'gt','Argentina'=>'ar','Brazil'=>'br','Colombia'=>'co','Chile'=>'cl',
            'Peru'=>'pe','Uruguay'=>'uy','Paraguay'=>'py','Venezuela'=>'ve','Ecuador'=>'ec',
            'Bolivia'=>'bo','Germany'=>'de','France'=>'fr','Italy'=>'it','Spain'=>'es',
            'Netherlands'=>'nl','Portugal'=>'pt','England'=>'gb-eng','Scotland'=>'gb-sct',
            'Wales'=>'gb-wls','Romania'=>'ro','Poland'=>'pl','Slovenia'=>'si','Turkey'=>'tr',
            'Norway'=>'no','Sweden'=>'se','Denmark'=>'dk','Belgium'=>'be','Switzerland'=>'ch',
            'Austria'=>'at','Greece'=>'gr','Croatia'=>'hr','Serbia'=>'rs','Bulgaria'=>'bg',
            'Russia'=>'ru','New Zealand'=>'nz','Fiji'=>'fj','Tahiti'=>'pf','Solomon Islands'=>'sb',
            'Czech Republic'=>'cz','Slovakia'=>'sk','Ukraine'=>'ua','Israel'=>'il','Hungary'=>'hu',
        ];
        return $map[$en] ?? '';
    }

    private static function countryArabic(string $en): string
    {
        static $map = [
            'United Arab Emirates'=>'الإمارات','Qatar'=>'قطر','Saudi Arabia'=>'السعودية',
            'Australia'=>'أستراليا','China'=>'الصين','Jordan'=>'الأردن','Uzbekistan'=>'أوزبكستان',
            'Japan'=>'اليابان','Iran'=>'إيران','South Korea'=>'كوريا الجنوبية','Cameroon'=>'الكاميرون',
            'Gabon'=>'الغابون','Mauritania'=>'موريتانيا','Angola'=>'أنغولا','Algeria'=>'الجزائر',
            'Morocco'=>'المغرب','Egypt'=>'مصر','South Africa'=>'جنوب أفريقيا','El Salvador'=>'السلفادور',
            'Costa Rica'=>'كوستاريكا','United States'=>'الولايات المتحدة','Jamaica'=>'جامايكا',
            'Trinidad and Tobago'=>'ترينيداد وتوباغو','Canada'=>'كندا','Mexico'=>'المكسيك',
            'Nicaragua'=>'نيكاراغوا','Honduras'=>'هندوراس','Panama'=>'بنما','Guatemala'=>'غواتيمالا',
            'Argentina'=>'الأرجنتين','Brazil'=>'البرازيل','Colombia'=>'كولومبيا','Chile'=>'تشيلي',
            'Peru'=>'بيرو','Uruguay'=>'الأوروغواي','Paraguay'=>'باراغواي','Venezuela'=>'فنزويلا',
            'Ecuador'=>'الإكوادور','Bolivia'=>'بوليفيا','Germany'=>'ألمانيا','France'=>'فرنسا',
            'Italy'=>'إيطاليا','Spain'=>'إسبانيا','Netherlands'=>'هولندا','Portugal'=>'البرتغال',
            'England'=>'إنجلترا','Scotland'=>'اسكتلندا','Wales'=>'ويلز','Romania'=>'رومانيا',
            'Poland'=>'بولندا','Slovenia'=>'سلوفينيا','Turkey'=>'تركيا','Norway'=>'النرويج',
            'Sweden'=>'السويد','Denmark'=>'الدنمارك','Belgium'=>'بلجيكا','Switzerland'=>'سويسرا',
            'Austria'=>'النمسا','Greece'=>'اليونان','Croatia'=>'كرواتيا','Serbia'=>'صربيا',
            'Bulgaria'=>'بلغاريا','Russia'=>'روسيا','New Zealand'=>'نيوزيلندا','Fiji'=>'فيجي',
            'Czech Republic'=>'التشيك','Slovakia'=>'سلوفاكيا','Ukraine'=>'أوكرانيا','Israel'=>'إسرائيل',
            'Hungary'=>'المجر',
        ];
        return $map[$en] ?? $en;
    }

    // ────────────────────────────────────────────────────────
    //  HTTP + كاش
    // ────────────────────────────────────────────────────────

    private static function cachePath(): string
    {
        return rtrim(CACHE_DIR, '/\\') . '/wiki-referees.json';
    }

    private static function httpGet(string $url): ?string
    {
        // الجالب الموحّد (cURL بمهلة صارمة 6 ثوانٍ، بلا تراكم مهلات) — كانت
        // file_get_contents هنا بمهلة 20 ثانية تعلّق عمّال php-fpm وتسبّب 504.
        if (function_exists('http_get')) {
            return http_get($url, [
                'timeout' => 6,
                'ua'      => 'wcup2026.org/1.0 (contact: salah232@gmail.com)',
            ]);
        }
        $prev = @ini_set('default_socket_timeout', '6');
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: wcup2026.org/1.0 (contact: salah232@gmail.com)\r\n",
                'timeout' => 6,
            ],
        ]);
        $r = @file_get_contents($url, false, $ctx);
        if ($prev !== false) { @ini_set('default_socket_timeout', (string)$prev); }
        return ($r === false) ? null : $r;
    }
}
