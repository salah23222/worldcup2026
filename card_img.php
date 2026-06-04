<?php
/**
 * card_img.php — صورة المعاينة الديناميكية (og:image) للبطاقات القابلة للمشاركة.
 * ============================================================
 * PNG بمقاس 1200×630: خلفية كحلية متدرّجة + علمَا الفريقين + النتيجة/العنوان
 * + الإيموجي + نطاق الموقع. تُستهلَك كـ og:image في card.php (وفي match.php
 * كـ ?mode=match للتوافق الخلفي مع سلوكها التاريخي).
 *
 * السلامة:
 *   • لا GD؟ → إعادة توجيه 302 إلى /assets/img/og.png (كثير من الاستضافات فيها GD).
 *   • أي خطأ؟ → نفس الإعادة الاحترازية (لا fatal أبداً).
 *   • كل المعطيات تُقصّ/تُحقّق عبر Cards.
 * ملاحظة: نصوص لاتينية/أرقام (GD لا يشكّل العربية) — أسماء إنجليزية + أعلام،
 *   تماماً كسلوك card.php التاريخي.
 * ============================================================
 */
// قد يُضمَّن من card.php (وضع المباراة) بعد تحميل bootstrap — لا نُعيد تحميله
// (bootstrap يُعرّف WC2026 بلا حارس، فإعادة تحميله تُسبّب خطأً فادحاً).
if (!defined('WC2026')) {
    require __DIR__ . '/includes/bootstrap.php';
}
// قد لا يكون Cards محمّلاً إن استُدعي card_img.php مباشرةً قبل ربطه في bootstrap.
if (!class_exists('Cards')) {
    require __DIR__ . '/includes/Cards.php';
}

/** إعادة توجيه احترازية إلى الصورة الثابتة (عند غياب GD أو أي خطأ). */
function card_img_fallback(): void
{
    if (!headers_sent()) {
        header('Location: ' . base_url() . '/assets/img/og.png', true, 302);
    }
    exit;
}

// لا GD؟ → احتياطي ثابت فوراً.
if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
    card_img_fallback();
}

try {
    // توافق خلفي: brag=1 بلا type= يعني بطاقة «قلتلكم!» (نفس منطق card.php).
    $reqType = $_GET['type'] ?? '';
    if ($reqType === '' && !empty($_GET['brag'])) { $reqType = 'brag'; }
    $type   = Cards::normalizeType($reqType !== '' ? $reqType : 'predict');
    $data   = Cards::build($type, $_GET);
    $m      = $data['match'];                 // قد يكون null
    $accent = $data['accent'];

    // وضع المباراة (fixture) — للتوافق مع match.php (og:image للمباراة): "VS" بلا نتيجة.
    $isMatch = (($_GET['mode'] ?? '') === 'match');

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');

    $W = 1200; $H = 630;
    $im = imagecreatetruecolor($W, $H);
    if (function_exists('imageantialias')) { @imageantialias($im, true); }

    // خلفية متدرّجة (كحلي → أزرق) — مطابقة لهوية الموقع
    $top = [10, 22, 38]; $bot = [27, 55, 96];
    for ($y = 0; $y < $H; $y++) {
        $tt = $y / $H;
        $c = imagecolorallocate($im,
            (int)($top[0] + ($bot[0]-$top[0])*$tt),
            (int)($top[1] + ($bot[1]-$top[1])*$tt),
            (int)($top[2] + ($bot[2]-$top[2])*$tt));
        imageline($im, 0, $y, $W, $y, $c);
    }

    // ألوان الهوية
    [$ar_, $ag_, $ab_] = card_hex_rgb($accent);
    $accentCol = imagecolorallocate($im, $ar_, $ag_, $ab_);
    $gold  = imagecolorallocate($im, 255, 194, 51);
    $white = imagecolorallocate($im, 238, 244, 255);
    $dim   = imagecolorallocate($im, 159, 179, 209);
    $navy  = imagecolorallocate($im, 10, 22, 38);

    imagefilledrectangle($im, 0, 0, $W, 8, $white);          // شريط علوي أبيض
    imagefilledrectangle($im, 0, $H-8, $W, $H, $accentCol);  // شريط سفلي بلون النوع

    $font  = __DIR__ . '/assets/fonts/Cairo-Black.ttf';
    $fontB = __DIR__ . '/assets/fonts/Cairo-Bold.ttf';
    $hasFont = is_file($font);

    // شارة الهوية «26» أعلى اليسار
    if ($hasFont) {
        $bx = 46; $by = 34; $bs = 64;
        imagefilledrectangle($im, $bx, $by, $bx + $bs, $by + $bs, $white);
        $bb26 = imagettfbbox(32, 0, $font, '26');
        $w26  = $bb26[2] - $bb26[0];
        imagettftext($im, 32, 0, (int)($bx + ($bs - $w26) / 2), (int)($by + $bs / 2 + 15), $navy, $font, '26');
    }

    /** نص موسّط أفقياً (TTF) */
    $centerText = function($im, $size, $y, $color, $font, $text) use ($W) {
        if ($text === '') return;
        $bb = imagettfbbox($size, 0, $font, $text);
        $w  = $bb[2] - $bb[0];
        imagettftext($im, $size, 0, (int)(($W - $w)/2), $y, $color, $font, $text);
    };

    /** نص موسّط احتياطي بخط GD المدمج (عند غياب الخط TTF) */
    $centerBuiltin = function($im, $fontIdx, $y, $color, $text) use ($W) {
        if ($text === '') return;
        $w = imagefontwidth($fontIdx) * strlen($text);
        imagestring($im, $fontIdx, (int)(($W - $w)/2), $y, $text, $color);
    };

    // عنوان علوي حسب النوع/الوضع (لاتيني — GD لا يشكّل العربية)
    $titleMap = [
        'predict' => 'MY PREDICTION  .  FIFA WORLD CUP 2026',
        'brag'    => 'I CALLED IT!  .  FIFA WORLD CUP 2026',
        'result'  => 'FINAL RESULT  .  FIFA WORLD CUP 2026',
        'sticker' => 'STICKER UNLOCKED  .  WORLD CUP 2026',
        'qahr'    => 'WORLD CUP 2026',
    ];
    $title = $isMatch ? 'FIFA WORLD CUP 2026' : ($titleMap[$type] ?? $titleMap['predict']);

    // لا مباراة صالحة وليست بطاقة ملصق → بطاقة هوية الموقع (الواجهة الافتراضية للمشاركة)
    if ($m === null && $type !== 'sticker') {
        if ($hasFont) {
            $centerText($im, 64, 290, $white, $font, 'FIFA WORLD CUP 2026');
            $centerText($im, 32, 360, $gold,  $font, 'CANADA  .  MEXICO  .  USA');
            $centerText($im, 24, 440, $dim,   $font, '104 MATCHES  .  48 TEAMS  .  16 CITIES');
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 26, $H-50, $white, $font, $domain);
        } else {
            $centerBuiltin($im, 5, 280, $white, 'FIFA WORLD CUP 2026');
            $centerBuiltin($im, 4, 330, $dim,   'CANADA - MEXICO - USA');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    if ($hasFont) {
        $centerText($im, 26, 90, $gold, $font, $title);
    } else {
        $centerBuiltin($im, 4, 40, $gold, $title);
    }

    // بطاقة الملصق: لا أعلام مباراة بالضرورة — نعرض الإيموجي والاسم.
    if ($type === 'sticker') {
        $stickerName = Cards::cleanText($_GET['name'] ?? '', 40);
        // الاسم قد يكون عربياً؛ GD بلا تشكيل العربية → نعرض نسخة لاتينية إن أمكن،
        // وإلا نترك العنوان العام. (طبقة العرض HTML تُظهر العربي كاملاً.)
        if ($hasFont) {
            $centerText($im, 80, 300, $accentCol, $font, '*');   // نجمة كبيرة كرمز
            $centerText($im, 44, 400, $white, $font, 'STICKER UNLOCKED');
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 24, $H-50, $white, $font, $domain);
        } else {
            $centerBuiltin($im, 5, 300, $white, 'STICKER UNLOCKED');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    // من هنا فصاعداً: لدينا مباراة صالحة ($m).
    // أسماء لاتينية للعرض داخل الصورة (GD لا يشكّل العربية).
    $t1 = $m['team1']; $t2 = $m['team2'];

    /** يجلب صورة العلم من CDN (مع تخزين مؤقت على القرص لتسريع البطاقة) */
    $fetchImg = function(string $url) {
        if ($url === '') return false;
        $cacheFile = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
        if (is_file($cacheFile)) {
            $r = @file_get_contents($cacheFile);
            if ($r !== false) { $img = @imagecreatefromstring($r); if ($img) return $img; }
        }
        $raw = http_get($url, ['timeout' => 8]);
        if (!$raw) return false;
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($cacheFile, $raw);
        return @imagecreatefromstring($raw);
    };

    // أعلام (يسار/يمين)
    $fw = 240; $fh = 160; $fy = 230;
    foreach ([[$m['flag1'], 120], [$m['flag2'], $W-120-$fw]] as [$flagUrl, $fx]) {
        $img = $fetchImg((string)$flagUrl);
        if ($img) {
            imagecopyresampled($im, $img, $fx, $fy, 0, 0, $fw, $fh, imagesx($img), imagesy($img));
            imagedestroy($img);
            $border = imagecolorallocate($im, 36, 66, 104);
            imagerectangle($im, $fx, $fy, $fx+$fw, $fy+$fh, $border);
        }
    }

    // الوسط: "VS" لوضع المباراة، أو النتيجة (مضبوطة/متوقّعة) لبقية الأنواع
    if ($hasFont) {
        if ($isMatch) {
            $centerText($im, 72, $fy + 105, $gold, $font, 'VS');
        } else {
            $score = ($data['score'] !== '') ? str_replace(':', '  :  ', $data['score']) : 'VS';
            $scoreColor = ($type === 'qahr') ? $accentCol : $white;
            $centerText($im, 92, $fy + 110, $scoreColor, $font, $score);
        }
        // أسماء الفرق تحت الأعلام (لاتيني)
        $bb1 = imagettfbbox(28, 0, $font, $t1); $w1 = $bb1[2]-$bb1[0];
        imagettftext($im, 28, 0, (int)(120 + ($fw-$w1)/2), $fy+$fh+50, $dim, $font, $t1);
        $bb2 = imagettfbbox(28, 0, $font, $t2); $w2 = $bb2[2]-$bb2[0];
        imagettftext($im, 28, 0, (int)(($W-120-$fw) + ($fw-$w2)/2), $fy+$fh+50, $dim, $font, $t2);

        // وضع المباراة: تاريخ/وقت أسفل (UTC، لاتيني)
        if ($isMatch && $m['ts'] !== null) {
            $centerText($im, 30, $fy + $fh + 130, $white, $font, gmdate('D, d M Y . H:i', $m['ts']) . ' UTC');
        }

        // تذييل: النطاق الرسمي
        $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
        $centerText($im, 24, $H-50, $white, $font, $domain);
    } else {
        // احتياطي بلا خط TTF: لا يزال يُنتج صورة صالحة
        $centerBuiltin($im, 5, $fy + 90, $white, $isMatch ? 'VS' : ($data['score'] !== '' ? $data['score'] : 'VS'));
        $centerBuiltin($im, 4, $fy + $fh + 40, $dim, $t1 . '  -  ' . $t2);
        $centerBuiltin($im, 3, $H-40, $white, parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org');
    }

    imagepng($im);
    imagedestroy($im);
} catch (\Throwable $e) {
    if (function_exists('error_log')) { @error_log('card_img.php: ' . $e->getMessage()); }
    if (isset($im) && $im instanceof \GdImage) { @imagedestroy($im); }
    card_img_fallback();
}

/** يحوّل لون hex (#rrggbb) إلى [r,g,b]. */
function card_hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return [0, 213, 99];
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}
