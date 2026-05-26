<?php
/**
 * make_icons.php — مولّد أيقونات تطبيق PWA بشعار «26».
 * شغّله مرة واحدة:  php assets/img/make_icons.php
 * يُنتج: icon-192.png · icon-512.png · icon-maskable-512.png · apple-touch-icon.png
 */
$font = __DIR__ . '/../fonts/Cairo-Black.ttf';

if (!function_exists('imagecreatetruecolor') || !is_file($font)) {
    fwrite(STDERR, "GD أو الخط غير متوفّر.\n");
    exit(1);
}

/** يرسم أيقونة مربّعة: خلفية كحلية متدرّجة + «26» أبيض + شريط ذهبي. */
function draw_icon(string $out, int $size, string $font, float $scale): void
{
    $im = imagecreatetruecolor($size, $size);

    // خلفية متدرّجة (#0a1626 → #142a48)
    [$r1, $g1, $b1] = [10, 22, 38];
    [$r2, $g2, $b2] = [20, 42, 72];
    for ($y = 0; $y < $size; $y++) {
        $t = $y / $size;
        $col = imagecolorallocate(
            $im,
            (int)($r1 + ($r2 - $r1) * $t),
            (int)($g1 + ($g2 - $g1) * $t),
            (int)($b1 + ($b2 - $b1) * $t)
        );
        imagefilledrectangle($im, 0, $y, $size, $y + 1, $col);
    }

    $white = imagecolorallocate($im, 255, 255, 255);
    $gold  = imagecolorallocate($im, 255, 194, 51);

    // «26» في المنتصف
    $fs = (int)($size * $scale);
    $bb = imagettfbbox($fs, 0, $font, '26');
    $x  = (int)($size / 2 - ($bb[0] + $bb[2]) / 2);
    $y  = (int)($size / 2 - ($bb[7] + $bb[1]) / 2);
    imagettftext($im, $fs, 0, $x, $y, $white, $font, '26');

    // شريط ذهبي صغير أسفل الرقم (لمسة هوية)
    $bw = (int)($size * 0.24);
    $bh = max(3, (int)($size * 0.014));
    $by = (int)($size * 0.72);
    imagefilledrectangle($im, (int)(($size - $bw) / 2), $by, (int)(($size + $bw) / 2), $by + $bh, $gold);

    imagepng($im, $out);
    imagedestroy($im);
    echo "✓ " . basename($out) . " ({$size}px)\n";
}

draw_icon(__DIR__ . '/icon-192.png',          192, $font, 0.50);
draw_icon(__DIR__ . '/icon-512.png',          512, $font, 0.50);
draw_icon(__DIR__ . '/icon-maskable-512.png', 512, $font, 0.38); // هامش أمان للأيقونة القابلة للقص
draw_icon(__DIR__ . '/apple-touch-icon.png',  180, $font, 0.50);
echo "تم توليد الأيقونات.\n";
