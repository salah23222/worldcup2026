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
        $key = sha1('v2|' . $title . '|' . $subtitle . '|' . $subEn . '|' . $mode . '|' . json_encode(array_map(
            fn($m) => [$m['team1'] ?? '', $m['team2'] ?? '', $m['date'] ?? '', $m['time'] ?? '', $m['score']['ft'] ?? null],
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
            $H = max(780, $headH + $n * $rowH + $footH);

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

        // شارة المجموعة/الجولة فوق الشريط
        $label = self::roundLabelAr($m);
        if ($label !== '') {
            $shaped = ArabicText::shape($label);
            $bb = imagettfbbox(19, 0, $fontAr, $shaped);
            $lw = $bb[2] - $bb[0];
            self::roundedRect($im, (int)($W/2 - $lw/2 - 24), $y, (int)($W/2 + $lw/2 + 24), $y + 42, 21, $c['boxBg']);
            self::centerText($im, $fontAr, 19, $W/2, $y + 30, $c['white'], $shaped);
        }

        // الشريط الأبيض
        $barY = $y + 54; $barH = 116;
        self::roundedRect($im, 50, $barY, $W - 50, $barY + $barH, 26, $c['white']);

        // صندوق الوسط (التوقيت أو النتيجة)
        $boxW = 200; $boxH = 96;
        $bx1 = (int)($W/2 - $boxW/2); $by1 = (int)($barY + ($barH - $boxH)/2);
        self::roundedRect($im, $bx1, $by1, $bx1 + $boxW, $by1 + $boxH, 18, $c['boxBg']);

        $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
        if ($mode === 'result' && $hasScore) {
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

        // الفريقان: الأول يمين (RTL) والثاني يسار — علم + اسم عربي
        $name1 = function_exists('team_name') ? team_name($t1) : $t1;
        $name2 = function_exists('team_name') ? team_name($t2) : $t2;
        $fw = 86; $fh = 58;
        $fy = (int)($barY + ($barH - $fh)/2);

        // يمين: علم الفريق الأول ثم اسمه (نحو الوسط)
        $f1x = $W - 50 - 26 - $fw;
        self::drawFlag($im, $t1, $f1x, $fy, $fw, $fh);
        self::fitText($im, $fontAr, 27, 17, $bx1 + $boxW + 18, $f1x - 14, $barY + 72, $c['navy'],
            ArabicText::shape($name1));

        // يسار: علم الفريق الثاني ثم اسمه
        $f2x = 50 + 26;
        self::drawFlag($im, $t2, $f2x, $fy, $fw, $fh);
        self::fitText($im, $fontAr, 27, 17, $f2x + $fw + 14, $bx1 - 18, $barY + 72, $c['navy'],
            ArabicText::shape($name2));

        // سطر التاريخ تحت الشريط + 🆕 الاسمان بالإنجليزية (بطاقة ثنائيّة اللغة)
        if ($ts !== null) {
            $dateAr = (self::DAYS[date('l', $ts)] ?? '') . ' ' . (int)date('j', $ts) . ' ' . (self::MONTHS[(int)date('n', $ts)] ?? '');
            self::centerText($im, $fontAr, 21, $W/2, $barY + $barH + 40, $c['gold'], ArabicText::shape(trim($dateAr)));
        }
        self::centerText($im, $fontEn, 18, $W/2, $barY + $barH + ($ts !== null ? 72 : 40), $c['light'],
            strtoupper($t1 . '  vs  ' . $t2));
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

    /** مستطيل بزوايا دائرية. */
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
