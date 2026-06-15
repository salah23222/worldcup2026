<?php
/**
 * FifaStats.php — الإحصائيات والتكتيك الرسمي من تقرير FIFA (PMSR) بهويّة الموقع.
 * ============================================================
 * القيد: Hostinger بلا pdftotext → الاستخراج محليّ عبر tools/fifa-extract.ps1
 *   (pdftotext -table) ثم ملفّ JSON لكل مباراة في assets/fifa/{hash}.json (يُزامَن).
 *   الإنتاج يقرأ ملفّ المباراة الواحدة فقط (يتوسّع لكل البطولة دون تحميل ضخم).
 *
 * يستخرج:
 *   • إحصائيات الفريق (16) + مراحل اللعب (8 بالاستحواذ + 9 بدونه) + التشكيل.
 *   • المعطيات البدنيّة (مجمّعة) + إبراز الأسرع.
 *   • 🆕 بيانات كل لاعب مدموجة (بدني + عرضيّات + اختراق خطوط) → روستر قابل للنقر:
 *     يضغط الزائر لاعباً فتظهر كل إحصائيّاته (حلّ مشكلة الجداول العريضة).
 * (الحكّام/الحضور/القائد/الخرائط الحراريّة غير موجودة نصّيّاً في التقرير.)
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class FifaStats
{
    private static array $cache = [];

    private static function dataDir(): string { return __DIR__ . '/../assets/fifa'; }
    private static function key(string $t1, string $t2, string $d): string { return md5(trim($t1) . '|' . trim($t2) . '|' . trim($d)); }

    private static function nums(string $s): array
    {
        if (!preg_match_all('/\d+(?:\.\d+)?/', $s, $mm)) return [];
        return array_map('floatval', $mm[0]);
    }

    // ───────────────────── إعداد الصفوف (عرض) ─────────────────────
    /** [key, AR, EN, unit, subAr|null] */
    private const STAT_ROWS = [
        ['possession',      'الاستحواذ',           'Possession',          '%',  null],
        ['shots',           'التسديدات',           'Shots',               '',   'على المرمى'],
        ['xg',              'الأهداف المتوقّعة xG', 'Expected goals (xG)', '',   null],
        ['pass_pct',        'دقّة التمرير',         'Pass accuracy',       '%',  null],
        ['passes',          'إجمالي التمريرات',    'Total passes',        '',   'مكتملة'],
        ['line_breaks',     'اختراق الخطوط',       'Line breaks',         '',   null],
        ['def_line_breaks', 'اختراقات دفاعيّة',    'Defensive line breaks','',  null],
        ['receptions',      'استلامات الثلث الأخير','Final-third receptions','', null],
        ['ball_prog',       'التقدّم بالكرة',       'Ball progressions',   '',   null],
        ['crosses',         'العرضيّات',           'Crosses',             '',   null],
        ['pressures',       'الضغوط الدفاعيّة',     'Defensive pressures', '',   'مباشر'],
        ['forced_turnovers','استخلاص قسري',        'Forced turnovers',    '',   null],
        ['second_balls',    'الكرات الثانية',       'Second balls',        '',   null],
        ['distance',        'المسافة المقطوعة',    'Distance covered',    ' كم',null],
        ['sprint_dist',     'مسافة الركض السريع',  'Sprint distance',     ' كم',null],
    ];
    private const PHASE_IN = [
        ['build_up_unopp', 'البناء دون ضغط',  'Build-up (unopposed)'],
        ['build_up_opp',   'البناء تحت الضغط', 'Build-up (opposed)'],
        ['progression',    'التقدّم',          'Progression'],
        ['final_third',    'الثلث الأخير',     'Final third'],
        ['long_ball',      'الكرة الطويلة',    'Long ball'],
        ['att_transition', 'التحوّل الهجومي',  'Attacking transition'],
        ['counter',        'الهجمة المرتدّة',  'Counter attack'],
        ['set_piece',      'الكرات الثابتة',   'Set piece'],
    ];
    private const PHASE_OUT = [
        ['high_press',    'ضغط عالٍ',        'High press'],
        ['mid_press',     'ضغط متوسط',       'Mid press'],
        ['low_press',     'ضغط منخفض',       'Low press'],
        ['high_block',    'كتلة عالية',      'High block'],
        ['mid_block',     'كتلة متوسطة',     'Mid block'],
        ['low_block',     'كتلة منخفضة',     'Low block'],
        ['recovery',      'الاستخلاص',       'Recovery'],
        ['def_transition','التحوّل الدفاعي', 'Defensive transition'],
        ['counter_press', 'الضغط العكسي',    'Counter-press'],
    ];
    private const PHYS_ROWS = [
        ['sprints',   'العَدْوات السريعة',   'Sprints',         ''],
        ['hsr',       'ركضات عالية السرعة',  'High-speed runs', ''],
        ['top_speed', 'أعلى سرعة',           'Top speed',       ' كم/س'],
    ];
    /** أنواع العرضيّات (بترتيب v؛ الأخير = الإجمالي). */
    private const CROSS_COLS = [
        ['داخليّة','Inswing'], ['خارجيّة','Outswing'], ['أرضيّة','Driven'], ['عالية','Lofted'],
        ['ارتداديّة','Cutback'], ['دفع','Push'], ['الإجمالي','Total'],
    ];

    // ───────────────────── العرض (إنتاج) ─────────────────────
    public static function forMatch(array $m): ?array
    {
        $k = self::key((string)($m['team1'] ?? ''), (string)($m['team2'] ?? ''), (string)($m['date'] ?? ''));
        if (!array_key_exists($k, self::$cache)) {
            clearstatcache(true);   // كاش stat/realpath على Hostinger قد يخفي الملفّ عرضيّاً
            $f = self::dataDir() . '/' . $k . '.json';
            $raw = is_file($f) ? @file_get_contents($f) : false;
            if ($raw === false && is_file($f)) { clearstatcache(true); $raw = @file_get_contents($f); }
            $d = ($raw !== false) ? json_decode((string)$raw, true) : null;
            self::$cache[$k] = (is_array($d) && !empty($d['stats'])) ? $d : null;
        }
        return self::$cache[$k];
    }

    private static function fmt($v): string
    {
        return (floor((float)$v) == (float)$v) ? (string)(int)$v : (string)$v;
    }

    /** صفوف أشرطة المقارنة لقائمة تعريفات. */
    private static function bars(array $defs, array $src, bool $ar): string
    {
        $html = '';
        foreach ($defs as $d) {
            $key = $d[0];
            if (!isset($src[$key]) || !is_array($src[$key])) continue;
            $v1 = $src[$key][0] ?? null; $v2 = $src[$key][1] ?? null;
            if ($v1 === null || $v2 === null) continue;
            $unit = $d[3] ?? ''; $subAr = $d[4] ?? null;
            $sum = (float)$v1 + (float)$v2;
            $p1 = $sum > 0 ? round((float)$v1 / $sum * 100) : 50;
            $s1 = $s2 = '';
            if ($subAr !== null && isset($src[$key . '_sub']) && is_array($src[$key . '_sub'])) {
                $sub = $src[$key . '_sub'];
                if (isset($sub[0])) $s1 = ' (' . self::fmt($sub[0]) . ')';
                if (isset($sub[1])) $s2 = ' (' . self::fmt($sub[1]) . ')';
            }
            $label = $ar ? $d[1] : $d[2];
            $html .= '<div class="fstat-row">'
                . '<span class="fstat-v">' . e(self::fmt($v1) . $unit . $s1) . '</span>'
                . '<span class="fstat-label">' . e($label) . '</span>'
                . '<span class="fstat-v">' . e(self::fmt($v2) . $unit . $s2) . '</span>'
                . '<div class="fstat-bar"><i style="width:' . $p1 . '%"></i><b style="width:' . (100 - $p1) . '%"></b></div>'
                . '</div>';
        }
        return $html;
    }

    public static function render(array $m, bool $ar): string
    {
        $row = self::forMatch($m);
        if (!$row) return '';
        $stats = $row['stats'];

        $t1 = function_exists('team_name') ? team_name((string)($m['team1'] ?? '')) : (string)($m['team1'] ?? '');
        $t2 = function_exists('team_name') ? team_name((string)($m['team2'] ?? '')) : (string)($m['team2'] ?? '');

        // أهداف + بطاقات (الأهداف من النتيجة، البطاقات من بيانات المباراة)
        $extra = []; $preDefs = [];
        if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
            $extra['goals'] = [(int)$m['score']['ft'][0], (int)$m['score']['ft'][1]];
            $preDefs[] = ['goals', '⚽ الأهداف', '⚽ Goals', '', null];
        }
        if (isset($m['cards']) && is_array($m['cards'])) {
            $r = [0, 0]; $y = [0, 0];
            foreach ($m['cards'] as $c) {
                if (!is_array($c)) continue;
                $idx = ((int)($c['team'] ?? 0) === 2) ? 1 : 0;
                if (($c['type'] ?? '') === 'red') $r[$idx]++; else $y[$idx]++;
            }
            $extra['reds'] = $r;    $preDefs[] = ['reds', '🟥 الطرد', '🟥 Sendings-off', '', null];
            $extra['yellows'] = $y; $preDefs[] = ['yellows', '🟨 الإنذارات', '🟨 Cautions', '', null];
        }

        $statsHtml = self::bars(array_merge($preDefs, self::STAT_ROWS), array_merge($extra, $stats), $ar);
        $inHtml    = !empty($row['phases_in'])  ? self::bars(self::PHASE_IN,  $row['phases_in'],  $ar) : '';
        $outHtml   = !empty($row['phases_out']) ? self::bars(self::PHASE_OUT, $row['phases_out'], $ar) : '';

        $physHtml = ''; $physHl = '';
        if (!empty($row['physical']['t1']) && !empty($row['physical']['t2'])) {
            $p = $row['physical'];
            $physHtml = self::bars(self::PHYS_ROWS, [
                'sprints'   => [$p['t1']['sprints'],   $p['t2']['sprints']],
                'hsr'       => [$p['t1']['hsr'],       $p['t2']['hsr']],
                'top_speed' => [$p['t1']['top_speed'], $p['t2']['top_speed']],
            ], $ar);
            if (!empty($p['t1']['top_player']) && !empty($p['t2']['top_player'])) {
                $physHl = '<p class="fstat-hl">⚡ ' . e($ar ? 'الأسرع' : 'Fastest') . ': '
                    . e($p['t1']['top_player']) . ' ' . self::fmt($p['t1']['top_speed']) . ' · '
                    . e($p['t2']['top_player']) . ' ' . self::fmt($p['t2']['top_speed']) . ' ' . e($ar ? 'كم/س' : 'km/h') . '</p>';
            }
        }
        $playersHtml = self::playersHtml($row, $ar, $t1, $t2);
        if ($statsHtml === '' && $inHtml === '' && $outHtml === '' && $playersHtml === '') return '';

        $form = '';
        if (!empty($row['formation']) && is_array($row['formation'])) {
            $f1 = (string)($row['formation'][0] ?? ''); $f2 = (string)($row['formation'][1] ?? '');
            if ($f1 !== '' && $f2 !== '') $form = ' · ' . e($f1) . ' — ' . e($f2);
        }

        $title  = $ar ? '📊 إحصائيات وتكتيك المباراة (FIFA)' : '📊 Official stats & tactics (FIFA)';
        $credit = $ar ? 'المصدر: المركز الفنّي لـFIFA — تقرير ما بعد المباراة (القيمة بين قوسين = على المرمى/المكتملة/المباشر).'
                      : 'Source: FIFA Training Centre — Post-Match Summary (value in parentheses = on target / completed / direct).';
        $sub = fn(string $h): string => '<h4 class="fstat-sub">' . e($h) . '</h4>';

        $out = '<section class="md-section fifa-stats">' . self::css()
            . '<h3 class="section-head">' . e($title) . '</h3>'
            . '<div class="fstat-head"><span>' . e($t1) . '</span><span class="fstat-form">' . $form . '</span><span>' . e($t2) . '</span></div>';
        if ($statsHtml !== '') $out .= $sub($ar ? 'الإحصائيات' : 'Statistics') . $statsHtml;
        if ($inHtml !== '')    $out .= $sub($ar ? '🎯 مراحل اللعب — بالاستحواذ' : '🎯 Phases of play — in possession') . $inHtml;
        if ($outHtml !== '')   $out .= $sub($ar ? '🛡️ بدون استحواذ (الضغط والكتلة)' : '🛡️ Out of possession') . $outHtml;
        if ($physHtml !== '')  $out .= $sub($ar ? '🏃 المعطيات البدنيّة' : '🏃 Physical data') . $physHtml . $physHl;
        $out .= $playersHtml;
        $out .= '<p class="video-credit" style="margin-top:14px">' . e($credit) . '</p></section>';
        return $out;
    }

    // ── روستر اللاعبين القابل للنقر ──
    private static function chip(string $val, string $label): string
    {
        return '<div class="fpc-chip"><b>' . e($val) . '</b><span>' . e($label) . '</span></div>';
    }

    private static function playersHtml(array $row, bool $ar, string $n1, string $n2): string
    {
        if (empty($row['players'])) return '';
        // عمودان متجاوران: كل منتخب ولاعبوه تحت اسمه (ينطوي لعمود واحد على الجوّال)
        $cols = '';
        foreach (['t1' => $n1, 't2' => $n2] as $tk => $name) {
            $ps = $row['players'][$tk] ?? [];
            if (!$ps) continue;
            $col = '<h4 class="fstat-sub">👥 ' . e(($ar ? 'لاعبو ' : '') . $name) . '</h4>';
            foreach ($ps as $p) $col .= self::playerCard($p, $ar);
            $cols .= '<div class="fstat-pcol">' . $col . '</div>';
        }
        if ($cols === '') return '';
        $hint = $ar ? '👥 لاعبو الفريقين · اضغط لاعباً لكل إحصائيّاته'
                    : '👥 Both squads · tap a player for full stats';
        return '<p class="fstat-phint">' . e($hint) . '</p>'
             . '<div class="fstat-players2">' . $cols . '</div>';
    }

    private static function playerCard(array $p, bool $ar): string
    {
        $phys = (isset($p['phys']) && count($p['phys']) >= 9) ? $p['phys'] : null;
        $head = '<span>#' . (int)$p['num'] . ' · ' . e($p['name']) . '</span>';
        if ($phys) {
            $head .= '<span class="fpc-q">' . round($phys[0] / 1000, 1) . ($ar ? ' كم' : ' km')
                . ' · ' . self::fmt($phys[8]) . ($ar ? ' كم/س' : ' km/h') . '</span>';
        }
        $body = '';
        if ($phys) {
            $body .= '<div class="fpc-cat">' . e($ar ? '🏃 بدني' : '🏃 Physical') . '</div><div class="fpc-grid">'
                . self::chip((string)round($phys[0] / 1000, 1), $ar ? 'مسافة (كم)' : 'Dist (km)')
                . self::chip(self::fmt($phys[8]), $ar ? 'أعلى سرعة' : 'Top speed')
                . self::chip((string)(int)$phys[7], $ar ? 'عَدْوات' : 'Sprints')
                . self::chip((string)(int)$phys[6], $ar ? 'ركضات عالية' : 'HS runs')
                . '</div>';
            $zsum = array_sum(array_slice($phys, 1, 5)) ?: 1;
            $sh = ['#26408b', '#3a5bbf', '#a8cdf5', '#ffc846', '#ff7a45'];
            $zb = '';
            for ($z = 1; $z <= 5; $z++) $zb .= '<i style="width:' . round($phys[$z] / $zsum * 100, 2) . '%;background:' . $sh[$z - 1] . '"></i>';
            $body .= '<div class="fpc-zones" title="' . e($ar ? 'توزيع مناطق السرعة 1→5' : 'speed zones 1→5') . '">' . $zb . '</div>';
        }
        if (isset($p['lb'])) {
            $lb = $p['lb']; $v = $lb['v'] ?? [];
            $body .= '<div class="fpc-cat">' . e($ar ? '🧩 اختراق الخطوط' : '🧩 Line breaks') . '</div><div class="fpc-grid">'
                . self::chip((string)(int)$lb['att'], $ar ? 'محاولة' : 'Att')
                . self::chip((string)(int)$lb['comp'], $ar ? 'مكتملة' : 'Comp')
                . self::chip((int)$lb['pct'] . '%', $ar ? 'دقّة' : 'Acc')
                . '</div>';
            if (count($v) >= 15) {
                $body .= '<p class="fpc-line">' . e($ar ? 'الاتجاه' : 'Dir') . ': '
                    . e($ar ? 'عبر' : 'Through') . ' ' . (int)$v[9] . ' · ' . e($ar ? 'حول' : 'Around') . ' ' . (int)$v[10] . ' · ' . e($ar ? 'فوق' : 'Over') . ' ' . (int)$v[11]
                    . ' — ' . e($ar ? 'النوع' : 'Type') . ': ' . e($ar ? 'تمريرة' : 'Pass') . ' ' . (int)$v[12] . ' · ' . e($ar ? 'عرضيّة' : 'Cross') . ' ' . (int)$v[13] . ' · ' . e($ar ? 'تقدّم' : 'Prog') . ' ' . (int)$v[14] . '</p>';
            }
        }
        if (isset($p['cross']) && array_sum($p['cross']) > 0) {
            $cv = $p['cross'];
            $parts = [];
            for ($ci = 0; $ci < 6; $ci++) if ((int)($cv[$ci] ?? 0) > 0) $parts[] = ($ar ? self::CROSS_COLS[$ci][0] : self::CROSS_COLS[$ci][1]) . ' ' . (int)$cv[$ci];
            $body .= '<div class="fpc-cat">' . e($ar ? '🎯 العرضيّات' : '🎯 Crosses') . '</div><div class="fpc-grid">'
                . self::chip((string)(int)($cv[6] ?? 0), $ar ? 'إجمالي' : 'Total') . '</div>';
            if ($parts) $body .= '<p class="fpc-line">' . e(implode(' · ', $parts)) . '</p>';
        }
        if ($body === '') return '';
        return '<details class="fpc"><summary>' . $head . '</summary><div class="fpc-body">' . $body . '</div></details>';
    }

    private static function css(): string
    {
        return '<style>'
            . '.fifa-stats .fstat-head{display:flex;justify-content:space-between;align-items:center;font-weight:800;margin:6px 0;color:var(--accent,#fff)}'
            . '.fifa-stats .fstat-form{font-size:.8em;font-weight:700;opacity:.85;color:#ffc846}'
            . '.fifa-stats .fstat-sub{margin:18px 0 4px;font-size:.95em;opacity:.9;border-bottom:1px solid rgba(255,255,255,.1);padding-bottom:6px}'
            . '.fifa-stats .fstat-phint{margin:18px 0 8px;font-size:.9em;opacity:.85;color:#ffc846}'
            . '.fifa-stats .fstat-players2{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;align-items:start}'
            . '.fifa-stats .fstat-pcol{min-width:0}'
            . '.fifa-stats .fstat-pcol .fstat-sub{margin-top:0}'
            . '@media(max-width:560px){.fifa-stats .fstat-players2{grid-template-columns:1fr}}'
            . '.fifa-stats .fstat-row{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:8px;margin:9px 0}'
            . '.fifa-stats .fstat-v{font-weight:800;font-variant-numeric:tabular-nums}'
            . '.fifa-stats .fstat-v:first-child{text-align:right}.fifa-stats .fstat-v:nth-child(3){text-align:left}'
            . '.fifa-stats .fstat-label{font-size:.82em;opacity:.8;text-align:center;white-space:nowrap}'
            . '.fifa-stats .fstat-bar{grid-column:1/-1;display:flex;height:6px;border-radius:6px;overflow:hidden;background:rgba(255,255,255,.08)}'
            . '.fifa-stats .fstat-bar i{background:#ffc846}.fifa-stats .fstat-bar b{background:#a8cdf5}'
            . '.fifa-stats .fstat-hl{text-align:center;font-size:.85em;color:#ffc846;margin:8px 0 0}'
            . '.fifa-stats .fpc{margin:6px 0;border:1px solid rgba(255,255,255,.09);border-radius:10px;overflow:hidden}'
            . '.fifa-stats .fpc>summary{cursor:pointer;padding:10px 12px;font-weight:700;display:flex;justify-content:space-between;gap:8px;align-items:center;list-style:none}'
            . '.fifa-stats .fpc>summary::-webkit-details-marker{display:none}'
            . '.fifa-stats .fpc-q{font-weight:700;font-size:.78em;color:#ffc846;white-space:nowrap}'
            . '.fifa-stats .fpc-body{padding:2px 12px 12px}'
            . '.fifa-stats .fpc-cat{font-size:.78em;opacity:.7;margin:12px 0 6px}'
            . '.fifa-stats .fpc-grid{display:flex;flex-wrap:wrap;gap:8px}'
            . '.fifa-stats .fpc-chip{background:rgba(255,255,255,.06);border-radius:8px;padding:6px 12px;text-align:center;min-width:62px}'
            . '.fifa-stats .fpc-chip b{display:block;font-size:1.05em;font-variant-numeric:tabular-nums}'
            . '.fifa-stats .fpc-chip span{display:block;font-size:.68em;opacity:.7}'
            . '.fifa-stats .fpc-line{font-size:.8em;opacity:.85;margin:7px 0 0}'
            . '.fifa-stats .fpc-zones{display:flex;height:8px;border-radius:5px;overflow:hidden;margin:8px 0 2px}'
            . '.fifa-stats .fpc-zones i{display:block;height:100%}'
            . '</style>';
    }

    // ───────────────────── الاستخلاص (محليّ فقط) ─────────────────────
    private static function block(array $lines, string $anchor, int $len): array
    {
        foreach ($lines as $i => $l) if (mb_stripos($l, $anchor) !== false) return array_slice($lines, $i, $len);
        return [];
    }

    private static function pair(array $block, string $label): ?array
    {
        foreach ($block as $l) {
            $pos = mb_strpos($l, $label);
            if ($pos === false) continue;
            $lv = self::nums(mb_substr($l, 0, $pos));
            $rv = self::nums(mb_substr($l, $pos + mb_strlen($label)));
            if ($lv && $rv) return [$lv, $rv];
        }
        return null;
    }

    public static function parseTable(string $txt): array
    {
        $lines = preg_split('/\R/', $txt) ?: [];
        $out = [];

        $ks = self::block($lines, 'Key  Statistics', 60) ?: self::block($lines, 'Key Statistics', 60);
        if ($ks) {
            foreach ($ks as $l) {
                if (preg_match('/Total\s+[\d.]+\s*%/u', $l) && preg_match('/%\s+Total\s*$/u', rtrim($l))) {
                    preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $l, $pp);
                    if (count($pp[1]) >= 2) $out['possession'] = [(float)$pp[1][0], (float)end($pp[1])];
                    break;
                }
            }
            $map = [
                'xG (Expected Goals)'                            => ['xg', false],
                'Attempts at Goal (On Target)'                   => ['shots', true],
                'Total Passes (Complete)'                        => ['passes', true],
                'Pass Completion %'                              => ['pass_pct', false],
                'Completed Line Breaks'                          => ['line_breaks', false],
                'Defensive Line Breaks'                          => ['def_line_breaks', false],
                'Receptions in the Final Third'                  => ['receptions', false],
                'Ball Progressions'                              => ['ball_prog', false],
                'Crosses'                                        => ['crosses', false],
                'Defensive Pressures Applied (Direct Pressures)' => ['pressures', true],
                'Forced Turnovers'                               => ['forced_turnovers', false],
                'Second Balls'                                   => ['second_balls', false],
                'Total Distance Covered'                         => ['distance', false],
            ];
            foreach ($map as $label => [$key, $hasParen]) {
                $p = self::pair($ks, $label);
                if (!$p) continue;
                $out[$key] = [$p[0][0], $p[1][0]];
                if ($hasParen && isset($p[0][1], $p[1][1])) $out[$key . '_sub'] = [$p[0][1], $p[1][1]];
            }
            foreach ($ks as $l) {
                if (mb_stripos($l, 'Low Speed Sprinting') === false) continue;
                if (preg_match_all('/(\d+(?:\.\d+)?)\s*km(?!\/)/', $l, $kk) && count($kk[1]) >= 2) {
                    $out['sprint_dist'] = [(float)$kk[1][0], (float)end($kk[1])];
                }
                break;
            }
        }

        $result = ['stats' => $out];

        // مراحل اللعب
        $ph = self::block($lines, 'Phases of Play', 60);
        if ($ph) {
            $grab = function (array $defs) use ($ph) {
                $r = [];
                $alias = [
                    'Build-up (unopposed)' => 'Build Up Unopposed', 'Build-up (opposed)' => 'Build Up Opposed',
                    'Counter attack' => 'Counter Attack', 'Defensive transition' => 'Defensive Transition',
                    'Attacking transition' => 'Attacking Transition',
                ];
                foreach ($defs as $d) {
                    $label = $alias[$d[2]] ?? $d[2];
                    foreach ($ph as $l) {
                        if (mb_stripos($l, $label) === false) continue;
                        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $l, $mm) && count($mm[1]) >= 2) {
                            $r[$d[0]] = [(float)$mm[1][0], (float)end($mm[1])];
                        }
                        break;
                    }
                }
                return $r;
            };
            $in = $grab(self::PHASE_IN); $op = $grab(self::PHASE_OUT);
            if ($in) $result['phases_in'] = $in;
            if ($op) $result['phases_out'] = $op;
        }

        // التشكيل
        $st = self::block($lines, 'STARTING', 30);
        if ($st) {
            $forms = [];
            foreach ($st as $l) {
                if (preg_match_all('/\d(?:\s*-\s*\d){2,4}/', $l, $ff)) {
                    foreach ($ff[0] as $f) $forms[] = preg_replace('/\s+/', '', $f);
                }
            }
            if (count($forms) >= 2) $result['formation'] = [$forms[0], $forms[1]];
        }

        // بيانات اللاعبين (بدني + عرضيّات + اختراق خطوط) + تجميع بدني للفريق
        $players = self::parsePlayers($lines);
        if ($players['t1'] || $players['t2']) {
            $result['players'] = $players;
            $result['physical'] = self::physAgg($players);
        }

        return $result;
    }

    /** صفوف جدول لكل لاعب (مرسى نهاية السطر). يعيد {t1:{num=>row}, t2:{num=>row}}. */
    private static function sectionRows(array $lines, string $anchor, string $kind): array
    {
        $heads = [];
        foreach ($lines as $i => $l) if (mb_stripos($l, $anchor) !== false) $heads[] = $i;
        if (!$heads) return ['t1' => [], 't2' => []];
        $startAt = $heads[0]; $t1End = $heads[1] ?? PHP_INT_MAX;
        if ($kind === 'phys')      $re = '/(\d+)\s+([A-Z][A-Za-z .\'\-]+?)\s+((?:[\d.]+\s+){8}[\d.]+)\s*$/';
        elseif ($kind === 'cross') $re = '/(\d+)\s+([A-Z][A-Za-z .\'\-]+?)\s+((?:\d+\s+){6}\d+)\s*$/';
        else                       $re = '/(\d+)\s+([A-Z][A-Za-z .\'\-]+?)\s+(\d+)\s+(\d+)\s+(\d+)%\s+((?:\d+\s+){14}\d+)\s*$/';
        $t1 = []; $t2 = [];
        foreach ($lines as $i => $l) {
            if ($i < $startAt) continue;
            if (!preg_match($re, $l, $m)) continue;
            $num = (int)$m[1]; $name = trim($m[2]);
            if ($kind === 'lb') {
                $row = ['name' => $name, 'att' => (int)$m[3], 'comp' => (int)$m[4], 'pct' => (int)$m[5],
                        'v' => array_map('intval', preg_split('/\s+/', trim($m[6])))];
            } else {
                $row = ['name' => $name, 'v' => array_map($kind === 'phys' ? 'floatval' : 'intval', preg_split('/\s+/', trim($m[3])))];
            }
            if ($i < $t1End) $t1[$num] = $row; else $t2[$num] = $row;
        }
        return ['t1' => $t1, 't2' => $t2];
    }

    /** يدمج جداول اللاعب الثلاثة (بدني/عرضيّات/اختراق خطوط) بمفتاح الرقم لكل فريق. */
    private static function parsePlayers(array $lines): array
    {
        $phys  = self::sectionRows($lines, 'Physical Data', 'phys');
        $cross = self::sectionRows($lines, 'Crosses (Open Play)', 'cross');
        $lb    = self::sectionRows($lines, 'Completed  %', 'lb');
        $teams = ['t1' => [], 't2' => []];
        foreach (['t1', 't2'] as $tk) {
            foreach (($phys[$tk] ?? []) as $num => $pr) {
                $p = ['num' => $num, 'name' => $pr['name'], 'phys' => $pr['v']];
                if (isset($cross[$tk][$num])) $p['cross'] = $cross[$tk][$num]['v'];
                if (isset($lb[$tk][$num]))    $p['lb']    = $lb[$tk][$num];
                $teams[$tk][] = $p;
            }
        }
        return $teams;
    }

    /** تجميع بدني للفريق من بيانات اللاعبين: إجمالي العَدْوات/الركضات + أعلى سرعة. */
    private static function physAgg(array $players): array
    {
        $agg = [];
        foreach (['t1', 't2'] as $tk) {
            $sp = 0; $hsr = 0; $top = 0.0; $tp = '';
            foreach ($players[$tk] as $p) {
                $v = $p['phys']; if (count($v) < 9) continue;
                $hsr += (int)$v[6]; $sp += (int)$v[7];
                if ($v[8] > $top) { $top = $v[8]; $tp = $p['name']; }
            }
            $agg[$tk] = ['sprints' => $sp, 'hsr' => $hsr, 'top_speed' => $top, 'top_player' => $tp];
        }
        return $agg;
    }

    /**
     * يبني ملفّاً لكل مباراة في assets/fifa/{hash}.json من مجلّد نصوص باسم رقم
     * المباراة (1.txt..). FIFA M(n) = المباراة رقم n زمنيّاً. محليّ فقط. يعيد العدد.
     */
    public static function build(string $txtDir): int
    {
        if (!class_exists('DataService')) return 0;
        $codeMap = class_exists('FifaReports') ? FifaReports::codeMap() : [];
        $byPair  = self::matchesByPair();

        $dir = self::dataDir();
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $count = 0;
        foreach (glob(rtrim($txtDir, '/') . '/*.txt') as $f) {
            $n     = (int)pathinfo($f, PATHINFO_FILENAME);
            $codes = $codeMap[(string)$n] ?? null;
            if (!$codes) continue;                                   // رقم بلا رموز معروفة → تخطٍّ آمن
            $k = implode('|', self::sortPair($codes[0], $codes[1]));
            if (!isset($byPair[$k])) continue;                       // لا مباراة مطابقة → تخطٍّ
            $m = $byPair[$k];
            $parsed = self::parseTable((string)@file_get_contents($f));
            if (empty($parsed['stats'])) continue;
            $t1 = (string)($m['team1'] ?? ''); $t2 = (string)($m['team2'] ?? ''); $dt = (string)($m['date'] ?? '');
            // محاذاة home/away: إن كان «home» التقرير = الفريق الثاني للمباراة → اقلب الفريقين
            if (function_exists('team_flag') && strtolower((string)$codes[0]) === strtolower((string)team_flag($t2))) {
                $parsed = self::flipTeams($parsed);
            }
            $rec = ['n' => $n, 'team1' => $t1, 'team2' => $t2, 'date' => $dt] + $parsed;
            @file_put_contents($dir . '/' . self::key($t1, $t2, $dt) . '.json', json_encode($rec, JSON_UNESCAPED_UNICODE));
            $count++;
        }
        return $count;
    }

    /** فهرسة كل المباريات بزوج رموز الأعلام (غير مرتّب) → المباراة. */
    private static function matchesByPair(): array
    {
        $byPair = [];
        if (!class_exists('DataService')) return $byPair;
        foreach (DataService::allMatches() as $mm) {
            $a = function_exists('team_flag') ? team_flag((string)($mm['team1'] ?? '')) : '';
            $b = function_exists('team_flag') ? team_flag((string)($mm['team2'] ?? '')) : '';
            if ($a === '' || $b === '') continue;
            $k = implode('|', self::sortPair($a, $b));
            if (!isset($byPair[$k])) $byPair[$k] = $mm;               // دور المجموعات: كل زوج فريد
        }
        return $byPair;
    }

    /** زوج رموز مُرتّب (للمقارنة غير المرتّبة). */
    private static function sortPair(string $a, string $b): array
    {
        $p = [strtolower($a), strtolower($b)]; sort($p); return $p;
    }

    /** قلب بيانات الفريقين (home↔away) حين يختلف ترتيب التقرير عن ترتيب المباراة. */
    private static function flipTeams(array $p): array
    {
        foreach (['stats', 'phases_in', 'phases_out'] as $sec) {
            if (empty($p[$sec]) || !is_array($p[$sec])) continue;
            foreach ($p[$sec] as $k => $v) {
                if (is_array($v) && array_key_exists(0, $v) && array_key_exists(1, $v)) {
                    $t = $v[0]; $p[$sec][$k][0] = $v[1]; $p[$sec][$k][1] = $t;
                }
            }
        }
        if (!empty($p['formation']) && is_array($p['formation'])
            && array_key_exists(0, $p['formation']) && array_key_exists(1, $p['formation'])) {
            $t = $p['formation'][0]; $p['formation'][0] = $p['formation'][1]; $p['formation'][1] = $t;
        }
        foreach (['players', 'physical'] as $sec) {
            if (isset($p[$sec]) && is_array($p[$sec]) && (isset($p[$sec]['t1']) || isset($p[$sec]['t2']))) {
                $t = $p[$sec]['t1'] ?? null; $p[$sec]['t1'] = $p[$sec]['t2'] ?? null; $p[$sec]['t2'] = $t;
            }
        }
        return $p;
    }

    /**
     * pendingReports() — من خريطة {رقم: رابط}: يعيد فقط التقارير غير المستخرَجة بعد
     * (ملفّها assets/fifa/*.json غير موجود). للتشغيل التلقائي المتكرّر بكفاءة.
     * الربط برموز الفرق (اسم ملف التقرير) لا بالترتيب الزمني.
     */
    public static function pendingReports(array $reportsMap): array
    {
        $codeMap = class_exists('FifaReports') ? FifaReports::codeMap() : [];
        $byPair  = self::matchesByPair();
        $dir = self::dataDir();
        $out = [];
        foreach ($reportsMap as $n => $url) {
            $codes = $codeMap[(string)$n] ?? null;
            if (!$codes) continue;                          // رموز غير معروفة → لا يمكن وضعه
            $k = implode('|', self::sortPair($codes[0], $codes[1]));
            if (!isset($byPair[$k])) continue;              // لا مباراة مطابقة بعد
            $m   = $byPair[$k];
            $key = self::key((string)($m['team1'] ?? ''), (string)($m['team2'] ?? ''), (string)($m['date'] ?? ''));
            if (!is_file($dir . '/' . $key . '.json')) $out[$n] = $url;
        }
        return $out;
    }

    /**
     * تجميع بدني لكل لاعب عبر كل المباريات (للمستكشف بأسلوب FifaPhy).
     * يعيد مصفوفة: [name, team(En), m(عدد المباريات), dist(م إجمالي), sprints, hsr, top(أقصى)].
     * مرتّبة بالمسافة تنازليّاً. (المعدّل لكل مباراة يُحسب في الواجهة: ÷ m.)
     */
    public static function physicalLeaderboard(): array
    {
        // مضيفات Hostinger (hcdn) تُبقي أحياناً كاش stat/realpath قديماً فيرجع glob
        // فارغاً عرضيّاً رغم وجود الملفّات → جدول فارغ متذبذب. نمسح الكاش ونعيد المحاولة.
        $cacheFile = rtrim(CACHE_DIR, '/') . '/physical-leaderboard.json';
        clearstatcache(true);
        $files = glob(self::dataDir() . '/*.json') ?: [];
        if (!$files) { clearstatcache(true); $files = glob(self::dataDir() . '/*.json') ?: []; }

        $players = [];
        foreach ($files as $f) {
            $raw = @file_get_contents($f);
            if ($raw === false) { clearstatcache(true); $raw = @file_get_contents($f); }
            $rec = json_decode((string)$raw, true);
            if (!is_array($rec) || empty($rec['players'])) continue;
            $teams = ['t1' => (string)($rec['team1'] ?? ''), 't2' => (string)($rec['team2'] ?? '')];
            foreach ($teams as $tk => $teamEn) {
                foreach (($rec['players'][$tk] ?? []) as $p) {
                    $v = $p['phys'] ?? null;
                    if (!is_array($v) || count($v) < 9) continue;
                    $key = $teamEn . '|' . ($p['num'] ?? '') . '|' . ($p['name'] ?? '');
                    if (!isset($players[$key])) {
                        $players[$key] = ['name' => (string)($p['name'] ?? ''), 'team' => $teamEn, 'num' => (int)($p['num'] ?? 0),
                            'm' => 0, 'dist' => 0.0, 'sprints' => 0, 'hsr' => 0, 'top' => 0.0,
                            'zones' => [0, 0, 0, 0, 0], 'lb' => [0, 0], 'lbv' => [0, 0, 0, 0, 0, 0], 'cross' => [0, 0, 0, 0, 0, 0, 0]];
                    }
                    $pr = &$players[$key];
                    $pr['m']++;
                    $pr['dist']    += (float)$v[0];
                    $pr['sprints'] += (int)$v[7];
                    $pr['hsr']     += (int)$v[6];
                    if ((float)$v[8] > $pr['top']) $pr['top'] = (float)$v[8];
                    for ($z = 0; $z < 5; $z++) $pr['zones'][$z] += (float)($v[$z + 1] ?? 0);   // مناطق السرعة 1→5
                    if (isset($p['lb']) && is_array($p['lb'])) {                                 // اختراق الخطوط
                        $pr['lb'][0] += (int)($p['lb']['att'] ?? 0);
                        $pr['lb'][1] += (int)($p['lb']['comp'] ?? 0);
                        $lv = $p['lb']['v'] ?? [];
                        for ($z = 0; $z < 6; $z++) $pr['lbv'][$z] += (int)($lv[9 + $z] ?? 0);     // اتجاه(3)+نوع(3)
                    }
                    if (isset($p['cross']) && is_array($p['cross'])) {                           // العرضيّات
                        for ($z = 0; $z < 7; $z++) $pr['cross'][$z] += (int)($p['cross'][$z] ?? 0);
                    }
                    unset($pr);
                }
            }
        }
        $players = array_values($players);
        usort($players, fn($a, $b) => $b['dist'] <=> $a['dist']);

        if ($players) {
            // احفظ آخر نسخة ناجحة → تصمد أمام تذبذب glob الفارغ العرضي على Hostinger
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($cacheFile, json_encode($players, JSON_UNESCAPED_UNICODE));
            return $players;
        }
        // فارغ عرضيّاً (تذبذب الخادم) → أعِد آخر نسخة محفوظة بدل صفحة فارغة
        if (is_file($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && $cached) return $cached;
        }
        return [];
    }

    /**
     * teamDashboard() — تجميع على مستوى المنتخب من تقارير FIFA: مؤشّرات البطولة (KPI)
     * + جدول مقارنة المنتخبات (استحواذ/xG/تسديدات/تمرير/مسافة…). للوحة الإحصائيّة.
     * كاش «آخر نسخة ناجحة» مثل physicalLeaderboard (يصمد أمام تذبذب glob).
     */
    public static function teamDashboard(): array
    {
        $cacheFile = rtrim(CACHE_DIR, '/') . '/fifa-dashboard.json';
        clearstatcache(true);
        $files = glob(self::dataDir() . '/*.json') ?: [];
        if (!$files) { clearstatcache(true); $files = glob(self::dataDir() . '/*.json') ?: []; }

        $sumKeys = ['shots', 'shots_sub', 'xg', 'line_breaks', 'crosses'];
        $avgKeys = ['possession', 'pass_pct', 'sprint_dist', 'distance'];
        $teams = []; $kpi = ['matches' => 0, 'distance' => 0.0, 'goals' => 0,
            'topXg' => ['v' => 0.0, 'team' => '']];

        foreach ($files as $f) {
            $rec = json_decode((string)@file_get_contents($f), true);
            if (!is_array($rec) || empty($rec['stats'])) continue;
            $kpi['matches']++;
            $sides = [(string)($rec['team1'] ?? ''), (string)($rec['team2'] ?? '')];
            foreach ($sides as $i => $en) {
                if ($en === '') continue;
                if (!isset($teams[$en])) {
                    $teams[$en] = ['team' => $en, 'm' => 0, 'shots' => 0, 'shots_sub' => 0, 'xg' => 0.0,
                        'line_breaks' => 0, 'crosses' => 0, 'possession' => 0.0, 'pass_pct' => 0.0,
                        'sprint_dist' => 0.0, 'distance' => 0.0];
                }
                $t = &$teams[$en]; $t['m']++;
                foreach ($sumKeys as $k) $t[$k] += (float)($rec['stats'][$k][$i] ?? 0);
                foreach ($avgKeys as $k) $t[$k] += (float)($rec['stats'][$k][$i] ?? 0);
                unset($t);
                $kpi['distance'] += (float)($rec['stats']['distance'][$i] ?? 0);
                $xg = (float)($rec['stats']['xg'][$i] ?? 0);
                if ($xg > $kpi['topXg']['v']) $kpi['topXg'] = ['v' => $xg, 'team' => $en];
            }
        }
        foreach ($teams as &$t) {
            if ($t['m'] > 0) foreach ($avgKeys as $k) $t[$k] = $t[$k] / $t['m'];
        }
        unset($t);
        $teams = array_values($teams);
        usort($teams, fn($a, $b) => $b['distance'] <=> $a['distance']);

        // مؤشّرات اللاعبين من جدول البدنيّات (أسرع/أكثر مسافة/أكثر عَدْوات لكل مباراة)
        $kpi['fastest'] = ['v' => 0.0, 'name' => '', 'team' => ''];
        $kpi['topDist'] = ['v' => 0.0, 'name' => '', 'team' => ''];
        $kpi['topSprint'] = ['v' => 0.0, 'name' => '', 'team' => ''];
        foreach (self::physicalLeaderboard() as $p) {
            $m = max(1, (int)($p['m'] ?? 1));
            if ((float)$p['top'] > $kpi['fastest']['v']) $kpi['fastest'] = ['v' => (float)$p['top'], 'name' => $p['name'], 'team' => $p['team']];
            $dpm = (float)$p['dist'] / $m;
            if ($dpm > $kpi['topDist']['v']) $kpi['topDist'] = ['v' => $dpm, 'name' => $p['name'], 'team' => $p['team']];
            $spm = (float)$p['sprints'] / $m;
            if ($spm > $kpi['topSprint']['v']) $kpi['topSprint'] = ['v' => $spm, 'name' => $p['name'], 'team' => $p['team']];
        }

        $out = ['teams' => $teams, 'kpi' => $kpi];
        if ($teams) {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
            return $out;
        }
        if (is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c) && !empty($c['teams'])) return $c;
        }
        return $out;
    }
}
