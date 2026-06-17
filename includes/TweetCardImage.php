<?php
/**
 * TweetCardImage.php — بطاقة مباريات مصوّرة للنشر على X (بأسلوب القنوات الرياضية).
 * ============================================================
 * يولّد PNG عمودي (1000×متغيّر) بهوية الموقع:
 *   خلفية زرقاء ملكية متدرّجة + أقواس زاوية فاتحة + علامة «26» مائية
 *   عنوان عربي كبير + لكل مباراة: شريط أبيض (علم + اسم عربي × صندوق توقيت/نتيجة)
 *   + سطر التاريخ، وتذييل بهوية الموقع.
 *
 * العربية تُشكَّل عبر ArabicText (خط Amiri-Bold يغطي كل أشكال العرض)،
 * والأرقام/اللاتيني بخط Cairo. عند غياب GD/الخط → null (تغريدة نصية فقط).
 *
 * الاستخدام:
 *   $png = TweetCardImage::generate($matches, ['title' => 'مباريات اليوم']);
 *   // يعيد مسار ملف PNG في cache/cards/ أو null
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class TweetCardImage
{
    private const W = 1000;

    /** أسماء الأيام/الشهور بالعربية (مستقلة عن locale الخادم). */
    private const DAYS   = ['Sunday'=>'الأحد','Monday'=>'الاثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
    private const MONTHS = [1=>'يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];

    /** «Group A» → «المجموعة الأولى» */
    private const GROUP_AR = [
        'A'=>'الأولى','B'=>'الثانية','C'=>'الثالثة','D'=>'الرابعة','E'=>'الخامسة','F'=>'السادسة',
        'G'=>'السابعة','H'=>'الثامنة','I'=>'التاسعة','J'=>'العاشرة','K'=>'الحادية عشرة','L'=>'الثانية عشرة',
    ];

    /**
     * generate() — يبني البطاقة ويعيد مسار PNG أو null.
     * $opt: title (عربي), subtitle (عربي اختياري), mode: 'fixture'|'result'
     */
    public static function generate(array $matches, array $opt = []): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagettftext')) return null;
        $fontAr = __DIR__ . '/../assets/fonts/Amiri-Bold.ttf';
        $fontEn = __DIR__ . '/../assets/fonts/Cairo-Black.ttf';
        if (!is_file($fontAr) || !is_file($fontEn)) return null;
        if (!class_exists('ArabicText')) return null;

        $matches = array_slice(array_values($matches), 0, 4);
        if (!$matches) return null;

        $title    = (string)($opt['title'] ?? 'مباريات اليوم');
        $subtitle = (string)($opt['subtitle'] ?? '');
        $subEn    = (string)($opt['subtitle_en'] ?? '');   // 🆕 سطر إنجليزي (بطاقة ثنائيّة)
        $mode     = (($opt['mode'] ?? 'fixture') === 'result') ? 'result' : 'fixture';

        // كاش: نفس المحتوى = نفس الملف (لا إعادة توليد لكل تشغيل cron)
        $key = sha1('v4|' . $title . '|' . $subtitle . '|' . $subEn . '|' . $mode . '|' . json_encode(array_map(
            fn($m) => [$m['team1'] ?? '', $m['team2'] ?? '', $m['date'] ?? '', $m['time'] ?? '', $m['score']['ft'] ?? null, count($m['stats'] ?? []), count($m['cards'] ?? [])],
            $matches
        ), JSON_UNESCAPED_UNICODE));
        $dir  = rtrim(CACHE_DIR, '/') . '/cards';
        $file = $dir . '/x-' . substr($key, 0, 20) . '.png';
        if (is_file($file) && (time() - filemtime($file) < 6 * 3600)) return $file;

        try {
            $n = count($matches);
            $W = self::W;
            $headH = ($subtitle !== '') ? 330 : 290;
            if ($subEn !== '') $headH += 42;
            $rowH  = 262;   // +27 لسطر أسماء الفريقين بالإنجليزية تحت كل شريط
            $footH = 130;
            // 🆕 شريط إحصائيات لبطاقة نتيجة مباراة واحدة (إحصائيات حيّة + الإنذارات/الطرد)
            $statsRows = []; $motm = null;
            if ($n === 1 && $mode === 'result') {
                $m0 = $matches[0];
                if (!empty($m0['stats']) && is_array($m0['stats'])) {
                    $statsRows = array_slice(array_values($m0['stats']), 0, 6);   // حتى 6 محاور للرادار
                }
                // رجل المباراة (صورته + اسمه) — يُضاف أسفل البطاقة إن وُجد
                if ($statsRows && class_exists('FifaMetrics')) {
                    $motm = FifaMetrics::motmFor((string)($m0['team1'] ?? ''), (string)($m0['team2'] ?? ''));
                    if ($motm && (string)($motm['photo'] ?? '') === '') $motm = null;
                }
            }
            $statsH = $statsRows ? (520 + ($motm ? 168 : 0)) : 0;   // رادار + (شريط رجل المباراة)
            $H = max(780, $headH + $n * $rowH + $statsH + $footH);

            $im = imagecreatetruecolor($W, $H);

            // ── الخلفية: تدرّج أزرق ملكي ──
            $top = [45, 49, 175]; $bot = [25, 27, 115];
            for ($y = 0; $y < $H; $y++) {
                $t = $y / $H;
                imageline($im, 0, $y, $W, $y, imagecolorallocate($im,
                    (int)($top[0] + ($bot[0]-$top[0])*$t),
                    (int)($top[1] + ($bot[1]-$top[1])*$t),
                    (int)($top[2] + ($bot[2]-$top[2])*$t)));
            }

            // أقواس زاوية فاتحة (كالتصميم المرجعي) — شفافة
            $arc = imagecolorallocatealpha($im, 150, 200, 245, 96);
            imagefilledellipse($im, 0, 130, 320, 320, $arc);
            imagefilledellipse($im, $W, (int)($H*0.45), 280, 280, $arc);
            imagefilledellipse($im, 60, $H - 60, 300, 300, $arc);

            // علامة «26» مائية ضخمة
            $wm = imagecolorallocatealpha($im, 255, 255, 255, 112);
            imagettftext($im, 360, 0, (int)($W*0.18), (int)($H*0.82), $wm, $fontEn, '26');

            // ألوان
            $white = imagecolorallocate($im, 255, 255, 255);
            $light = imagecolorallocate($im, 168, 205, 245);
            $navy  = imagecolorallocate($im, 26, 31, 100);
            $boxBg = imagecolorallocate($im, 30, 36, 110);
            $gold  = imagecolorallocate($im, 255, 200, 70);
            $dimW  = imagecolorallocatealpha($im, 255, 255, 255, 40);

            // ── الترويسة: شارة 26 + النطاق ──
            self::roundedRect($im, $W/2 - 38, 36, $W/2 + 38, 112, 18, $white);
            self::centerText($im, $fontEn, 34, $W/2, 92, $navy, '26');
            self::centerText($im, $fontEn, 17, $W/2, 145, $light, 'WCUP2026.ORG');

            // ── العنوان والعنوان الفرعي ──
            self::centerText($im, $fontAr, 52, $W/2, 225, $white, ArabicText::shape($title));
            if ($subtitle !== '') {
                self::centerText($im, $fontAr, 30, $W/2, 285, $light, ArabicText::shape($subtitle));
            }
            if ($subEn !== '') {
                self::centerText($im, $fontEn, 22, $W/2, ($subtitle !== '' ? 327 : 285), $gold, $subEn);
            }

            // ── صفوف المباريات ──
            $y = $headH;
            foreach ($matches as $m) {
                self::matchRow($im, $m, $y, $mode, $fontAr, $fontEn, [
                    'white'=>$white, 'light'=>$light, 'navy'=>$navy, 'boxBg'=>$boxBg, 'gold'=>$gold,
                ]);
                $y += $rowH;
            }

            // ── 🆕 شريط إحصائيات المباراة (بطاقة نتيجة مفردة) ──
            if ($statsRows) {
                $m0  = $matches[0];
                $t1n = function_exists('team_name') ? team_name((string)($m0['team1'] ?? '')) : (string)($m0['team1'] ?? '');
                $t2n = function_exists('team_name') ? team_name((string)($m0['team2'] ?? '')) : (string)($m0['team2'] ?? '');
                $sy  = $headH + $n * $rowH + 6;
                self::centerText($im, $fontAr, 30, $W / 2, $sy + 22, $gold, ArabicText::shape('إحصائيات المباراة'));

                // ── الشبكة العنكبوتيّة (رادار) — تيل لفريق1 · سماوي لفريق2 (بلا أصفر) ──
                $teal  = imagecolorallocate($im, 38, 206, 168);
                $sky   = imagecolorallocate($im, 96, 165, 250);
                $tealF = imagecolorallocatealpha($im, 38, 206, 168, 96);
                $skyF  = imagecolorallocatealpha($im, 96, 165, 250, 112);
                $grid  = imagecolorallocatealpha($im, 255, 255, 255, 114);
                $axes  = array_slice($statsRows, 0, 6); $nA = count($axes);
                $cx = (int)($W / 2); $R = 150; $cyR = $sy + 92 + $R;
                imagealphablending($im, true);
                $ptF = function (int $i, float $r) use ($cx, $cyR, $nA) {
                    $a = deg2rad(-90 + $i * 360 / $nA);
                    return [(int)round($cx + $r * cos($a)), (int)round($cyR + $r * sin($a))];
                };
                foreach ([0.34, 0.67, 1.0] as $ring) {
                    $pts = []; for ($i = 0; $i < $nA; $i++) { [$x, $y] = $ptF($i, $R * $ring); $pts[] = $x; $pts[] = $y; }
                    self::poly($im, $pts, $grid, false);
                }
                for ($i = 0; $i < $nA; $i++) {
                    [$x, $y] = $ptF($i, $R); imageline($im, $cx, $cyR, $x, $y, $grid);
                    [$lx, $ly] = $ptF($i, $R + 30); $s = $axes[$i];
                    $nm = ArabicText::shape((string)($s['k'] ?? '')); $bb = imagettfbbox(15, 0, $fontAr, $nm); $tw = $bb[2] - $bb[0];
                    $ax = abs($lx - $cx) < 8 ? (int)($lx - $tw / 2) : ($lx > $cx ? $lx : (int)($lx - $tw));
                    imagettftext($im, 15, 0, $ax, $ly, $white, $fontAr, $nm);
                    $vs = ((string)($s['v'][0] ?? 0)) . ' : ' . ((string)($s['v'][1] ?? 0));
                    $bb2 = imagettfbbox(14, 0, $fontEn, $vs); $vw = $bb2[2] - $bb2[0];
                    $vx = abs($lx - $cx) < 8 ? (int)($lx - $vw / 2) : ($lx > $cx ? $lx : (int)($lx - $vw));
                    imagettftext($im, 14, 0, $vx, $ly + 19, $gold, $fontEn, $vs);
                }
                $p1 = []; $p2 = [];
                for ($i = 0; $i < $nA; $i++) {
                    $v1 = (float)($axes[$i]['v'][0] ?? 0); $v2 = (float)($axes[$i]['v'][1] ?? 0); $mx = max($v1, $v2, 1);
                    [$x1, $y1] = $ptF($i, $R * max(0.05, $v1 / $mx)); $p1[] = $x1; $p1[] = $y1;
                    [$x2, $y2] = $ptF($i, $R * max(0.05, $v2 / $mx)); $p2[] = $x2; $p2[] = $y2;
                }
                self::poly($im, $p2, $skyF, true);  self::poly($im, $p2, $sky, false);
                self::poly($im, $p1, $tealF, true); self::poly($im, $p1, $teal, false);

                // وسيلة إيضاح (أيّ لون لأيّ فريق) + سطر البطاقات
                $ly2 = $cyR + $R + 56;
                imagefilledellipse($im, (int)($W / 2 - 150), $ly2 - 6, 16, 16, $teal);
                imagettftext($im, 18, 0, (int)($W / 2 - 132), $ly2, $white, $fontAr, ArabicText::shape($t1n));
                imagefilledellipse($im, (int)($W / 2 + 60), $ly2 - 6, 16, 16, $sky);
                imagettftext($im, 18, 0, (int)($W / 2 + 78), $ly2, $white, $fontAr, ArabicText::shape($t2n));

                // ── شريط رجل المباراة (صورته + اسمه + تقييمه) ──
                if ($motm) {
                    $msy = $ly2 + 44;
                    // سطران منفصلان (لا خلط عربي/إنجليزي في تشكيل واحد → لا انعكاس · بلا إيموجي)
                    self::centerText($im, $fontAr, 24, $W / 2, $msy, $gold, ArabicText::shape('رجل المباراة'));
                    self::centerText($im, $fontEn, 13, $W / 2, $msy + 20, $light, 'PLAYER OF THE MATCH');
                    $d = 92;
                    $nm = function_exists('mb_strtoupper') ? mb_strtoupper(trim((string)($motm['name'] ?? '')), 'UTF-8') : strtoupper(trim((string)($motm['name'] ?? '')));
                    $rt = $motm['r'] ?? ($motm['rating'] ?? null);
                    $rstr = $rt !== null ? number_format((float)$rt, 1) : '';
                    $bbN = imagettfbbox(26, 0, $fontEn, $nm); $nmW = $bbN[2] - $bbN[0];
                    $gap = 20; $total = $d + $gap + max($nmW, 80);
                    $startX = (int)(($W - $total) / 2);
                    $pcy = $msy + 84;
                    if (!self::drawCirclePhoto($im, (string)$motm['photo'], $startX + (int)($d / 2), $pcy, $d, $gold)) {
                        imagefilledellipse($im, $startX + (int)($d / 2), $pcy, $d, $d, imagecolorallocatealpha($im, 255, 255, 255, 110));
                    }
                    $txX = $startX + $d + $gap;
                    imagettftext($im, 26, 0, $txX, $pcy - 4, $white, $fontEn, $nm);
                    if ($rstr !== '') {
                        imagefilledrectangle($im, $txX, $pcy + 12, $txX + 70, $pcy + 44, $gold);
                        imagettftext($im, 22, 0, $txX + 10, $pcy + 38, $navy, $fontEn, $rstr);
                    }
                }
            }

            // ── التذييل ──
            imageline($im, 120, $H - 95, $W - 120, $H - 95, $dimW);
            self::centerText($im, $fontAr, 24, $W/2, $H - 52, $light,
                ArabicText::shape('كل المباريات بتوقيتك على wcup2026.org'));

            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            imagepng($im, $file);
            imagedestroy($im);
            return is_file($file) ? $file : null;
        } catch (\Throwable $e) {
            @error_log('TweetCardImage: ' . $e->getMessage());
            if (isset($im) && $im instanceof \GdImage) @imagedestroy($im);
            return null;
        }
    }

    /** صفّ مباراة واحد: شارة المجموعة + الشريط الأبيض + سطر التاريخ. */
    private static function matchRow($im, array $m, int $y, string $mode, string $fontAr, string $fontEn, array $c): void
    {
        $W = self::W;
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        $ts = class_exists('DataService') ? DataService::matchTimestamp($m) : null;

        // 🆕 التاريخ فوق الصفّ (ذهبي) — انتقل من تحت الشريط للأعلى
        $contentTop = $y;
        if ($ts !== null) {
            $dateAr = (self::DAYS[date('l', $ts)] ?? '') . ' ' . (int)date('j', $ts) . ' ' . (self::MONTHS[(int)date('n', $ts)] ?? '');
            self::centerText($im, $fontAr, 20, $W/2, $y + 24, $c['gold'], ArabicText::shape(trim($dateAr)));
            $contentTop = $y + 38;
        }

        // شارة المجموعة/الجولة فوق الشريط
        $label = self::roundLabelAr($m);
        if ($label !== '') {
            $shaped = ArabicText::shape($label);
            $bb = imagettfbbox(19, 0, $fontAr, $shaped);
            $lw = $bb[2] - $bb[0];
            self::roundedRect($im, (int)($W/2 - $lw/2 - 24), $contentTop, (int)($W/2 + $lw/2 + 24), $contentTop + 42, 21, $c['boxBg']);
            self::centerText($im, $fontAr, 19, $W/2, $contentTop + 30, $c['white'], $shaped);
        }

        // الشريط الأبيض
        $barY = $contentTop + 54; $barH = 116;
        self::roundedRect($im, 50, $barY, $W - 50, $barY + $barH, 26, $c['white']);

        // صندوق الوسط (التوقيت أو النتيجة)
        $boxW = 200; $boxH = 96;
        $bx1 = (int)($W/2 - $boxW/2); $by1 = (int)($barY + ($barH - $boxH)/2);
        self::roundedRect($im, $bx1, $by1, $bx1 + $boxW, $by1 + $boxH, 18, $c['boxBg']);

        $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
        // أظهر النتيجة لأي مباراة منتهية — لا في وضع «result» فقط. بطاقة «مباريات اليوم»
        // قد تضمّ مباريات لُعبت فعلاً، فعرض توقيت انطلاقها بدل نتيجتها يُفقد المصداقيّة.
        $showScore = $hasScore && ($mode === 'result' || ($m['_status'] ?? '') === 'finished');
        if ($showScore) {
            // النتيجة قطعاً منفصلة (رقم - شرطة - رقم): ترتيب حتمي لا تعبث به أي
            // معالجة BiDi داخلية في libgd، وبعرض RTL صحيح — أهداف فريق اليمين يمينه.
            $gTeam1 = (string)(int)$m['score']['ft'][0];   // الفريق الأول = جهة اليمين
            $gTeam2 = (string)(int)$m['score']['ft'][1];   // الفريق الثاني = جهة اليسار
            self::centerText($im, $fontEn, 38, $W/2,      $by1 + 62, $c['white'], '-');
            self::centerText($im, $fontEn, 38, $W/2 + 48, $by1 + 62, $c['white'], $gTeam1);
            self::centerText($im, $fontEn, 38, $W/2 - 48, $by1 + 62, $c['white'], $gTeam2);
            self::centerText($im, $fontAr, 13, $W/2, $by1 + 86, $c['light'], ArabicText::shape('النتيجة النهائية'));
        } else {
            $time = $ts !== null ? date('H:i', $ts) : '--:--';
            self::centerText($im, $fontEn, 34, $W/2, $by1 + 52, $c['white'], $time);
            self::centerText($im, $fontAr, 14, $W/2, $by1 + 82, $c['light'], ArabicText::shape('بتوقيت الإمارات'));
        }

        // الفريقان: علم + اسم عربي (فوق) + إنجليزي (تحته مباشرةً) داخل الشريط الأبيض
        $name1 = function_exists('team_name') ? team_name($t1) : $t1;
        $name2 = function_exists('team_name') ? team_name($t2) : $t2;
        $fw = 86; $fh = 58;
        $fy = (int)($barY + ($barH - $fh)/2);

        // يمين: علم الفريق الأول ثم اسمه (عربي فوق · إنجليزي تحت)
        $f1x = $W - 50 - 26 - $fw;
        self::drawFlag($im, $t1, $f1x, $fy, $fw, $fh);
        self::fitText($im, $fontAr, 26, 16, $bx1 + $boxW + 18, $f1x - 14, $barY + 50, $c['navy'], ArabicText::shape($name1));
        self::fitText($im, $fontEn, 16, 11, $bx1 + $boxW + 18, $f1x - 14, $barY + 82, $c['navy'], strtoupper($t1));

        // يسار: علم الفريق الثاني ثم اسمه (عربي فوق · إنجليزي تحت)
        $f2x = 50 + 26;
        self::drawFlag($im, $t2, $f2x, $fy, $fw, $fh);
        self::fitText($im, $fontAr, 26, 16, $f2x + $fw + 14, $bx1 - 18, $barY + 50, $c['navy'], ArabicText::shape($name2));
        self::fitText($im, $fontEn, 16, 11, $f2x + $fw + 14, $bx1 - 18, $barY + 82, $c['navy'], strtoupper($t2));
    }

    /** «Group A» → «المجموعة الأولى»، وإلا اسم الجولة بالعربية. */
    private static function roundLabelAr(array $m): string
    {
        $g = trim((string)($m['group'] ?? ''));
        if ($g !== '' && preg_match('/Group\s+([A-L])/i', $g, $mm)) {
            $ord = self::GROUP_AR[strtoupper($mm[1])] ?? strtoupper($mm[1]);
            return 'المجموعة ' . $ord;
        }
        $roundsAr = [
            'Round of 32' => 'دور الـ32', 'Round of 16' => 'دور الـ16',
            'Quarter-finals' => 'ربع النهائي', 'Semi-finals' => 'نصف النهائي',
            'Third-place' => 'المركز الثالث', 'Final' => 'النهائي',
        ];
        $r = trim((string)($m['round'] ?? ''));
        foreach ($roundsAr as $en => $ar) {
            if (stripos($r, $en) !== false) return $ar;
        }
        return $r !== '' && preg_match('/Matchday\s+(\d+)/i', $r, $mm) ? ('الجولة ' . $mm[1]) : '';
    }

    /** يرسم علم منتخب (من flagcdn مع كاش قرص) بإطار رفيع. */
    private static function drawFlag($im, string $team, int $x, int $y, int $w, int $h): void
    {
        $url = function_exists('flag_url') ? flag_url($team, 'w160') : '';
        if ($url === '') return;
        $cacheFile = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
        $raw = is_file($cacheFile) ? @file_get_contents($cacheFile) : null;
        if (!$raw) {
            $raw = function_exists('http_get') ? http_get($url, ['timeout' => 6]) : null;
            if ($raw) @file_put_contents($cacheFile, $raw);
        }
        if (!$raw) return;
        $flag = @imagecreatefromstring($raw);
        if (!$flag) return;
        imagecopyresampled($im, $flag, $x, $y, 0, 0, $w, $h, imagesx($flag), imagesy($flag));
        imagedestroy($flag);
        $border = imagecolorallocatealpha($im, 26, 31, 100, 80);
        imagerectangle($im, $x, $y, $x + $w, $y + $h, $border);
    }

    /** صورة لاعب دائريّة بإطار (تُحمَّل من الرابط مع كاش قرص). يعيد true عند النجاح. */
    private static function drawCirclePhoto($im, string $url, int $cx, int $cy, int $d, $ring): bool
    {
        if ($url === '') return false;
        $cf = rtrim(CACHE_DIR, '/') . '/pp_' . md5($url) . '.img';
        $raw = is_file($cf) ? @file_get_contents($cf) : null;
        if (!$raw) {
            $raw = function_exists('http_get') ? http_get($url, ['timeout' => 9]) : null;
            if ($raw) @file_put_contents($cf, $raw);
        }
        if (!$raw) return false;
        $src = @imagecreatefromstring($raw);
        if (!$src) return false;
        imagefilledellipse($im, $cx, $cy, $d + 8, $d + 8, $ring);   // إطار ذهبي
        $av = imagecreatetruecolor($d, $d);
        imagesavealpha($av, true);
        $trans = imagecolorallocatealpha($av, 0, 0, 0, 127);
        imagefill($av, 0, 0, $trans);
        $pw = imagesx($src); $ph = imagesy($src); $side = min($pw, $ph);
        imagecopyresampled($av, $src, 0, 0, (int)(($pw - $side) / 2), 0, $d, $d, $side, $side);
        $r2 = ($d / 2) * ($d / 2);
        for ($yy = 0; $yy < $d; $yy++) for ($xx = 0; $xx < $d; $xx++) {
            $dx = $xx - $d / 2; $dy = $yy - $d / 2;
            if ($dx * $dx + $dy * $dy > $r2) imagesetpixel($av, $xx, $yy, $trans);
        }
        imagecopy($im, $av, $cx - (int)($d / 2), $cy - (int)($d / 2), 0, 0, $d, $d);
        imagedestroy($src); imagedestroy($av);
        return true;
    }

    /** مضلّع (مملوء أو حدّ) — متوافق مع PHP 8.0 (4 وسائط) و8.1+ (3 وسائط). */
    private static function poly($im, array $pts, $color, bool $fill): void
    {
        if (count($pts) < 6) return;
        if (PHP_VERSION_ID >= 80100) {
            $fill ? imagefilledpolygon($im, $pts, $color) : imagepolygon($im, $pts, $color);
        } else {
            $nn = intdiv(count($pts), 2);
            $fill ? imagefilledpolygon($im, $pts, $nn, $color) : imagepolygon($im, $pts, $nn, $color);
        }
    }

    private static function roundedRect($im, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    /** نص موسّط أفقياً حول نقطة x. */
    private static function centerText($im, string $font, float $size, int $cx, int $y, $color, string $text): void
    {
        if ($text === '') return;
        $bb = imagettfbbox($size, 0, $font, $text);
        $w = $bb[2] - $bb[0];
        imagettftext($im, $size, 0, (int)($cx - $w / 2), $y, $color, $font, $text);
    }

    /** نص موسّط داخل نطاق [x1,x2] مع تصغير تلقائي حتى يتّسع. */
    private static function fitText($im, string $font, float $size, float $minSize, int $x1, int $x2, int $y, $color, string $text): void
    {
        if ($text === '' || $x2 <= $x1) return;
        $max = $x2 - $x1;
        while ($size >= $minSize) {
            $bb = imagettfbbox($size, 0, $font, $text);
            $w = $bb[2] - $bb[0];
            if ($w <= $max) break;
            $size -= 1.5;
        }
        $bb = imagettfbbox($size, 0, $font, $text);
        $w = $bb[2] - $bb[0];
        imagettftext($im, $size, 0, (int)($x1 + ($max - $w) / 2), $y, $color, $font, $text);
    }
}
