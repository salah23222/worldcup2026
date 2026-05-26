<?php
/**
 * make_og.php — يولّد صورة Open Graph (1200x630) للمشاركة الاجتماعية.
 * يُشغّل مرة واحدة: php assets/img/make_og.php
 * يكتب assets/img/og.png
 */
$W = 1200; $H = 630;
$im = imagecreatetruecolor($W, $H);
imageantialias($im, true);

// تدرّج رأسي للخلفية (كحلي غامق → أزرق)
$top = [10, 22, 38];      // #0a1626
$bot = [27, 55, 96];      // #1b3760
for ($y = 0; $y < $H; $y++) {
    $t = $y / $H;
    $r = (int)($top[0] + ($bot[0] - $top[0]) * $t);
    $g = (int)($top[1] + ($bot[1] - $top[1]) * $t);
    $b = (int)($top[2] + ($bot[2] - $top[2]) * $t);
    $c = imagecolorallocate($im, $r, $g, $b);
    imageline($im, 0, $y, $W, $y, $c);
}

// شريط أخضر علوي + ذهبي
$green = imagecolorallocate($im, 0, 213, 99);
$gold  = imagecolorallocate($im, 255, 194, 51);
$white = imagecolorallocate($im, 238, 244, 255);
$dim   = imagecolorallocate($im, 159, 179, 209);
imagefilledrectangle($im, 0, 0, $W, 10, $green);
imagefilledrectangle($im, 0, $H - 8, $W, $H, $gold);

// مربّع العلامة "26"
imagefilledrectangle($im, 90, 230, 290, 430, $green);

// خط Cairo المضمّن داخل المشروع (نفس خط الموقع)
$fontBlack = __DIR__ . '/../fonts/Cairo-Black.ttf';
$fontBold  = __DIR__ . '/../fonts/Cairo-Bold.ttf';
$font  = is_file($fontBlack) ? $fontBlack : (is_file($fontBold) ? $fontBold : '');

if ($font) {
    $navy = imagecolorallocate($im, 10, 22, 38);
    imagettftext($im, 110, 0, 118, 375, $navy, $font, '26');
    imagettftext($im, 32, 0, 340, 275, $gold, $font, 'FIFA WORLD CUP');
    imagettftext($im, 92, 0, 338, 385, $white, $font, '2026');
    imagettftext($im, 26, 0, 342, 445, $dim, $font, 'Canada  .  Mexico  .  USA');
    imagettftext($im, 22, 0, 342, 515, $green, $font, '104 Matches  .  48 Teams  .  16 Cities');
} else {
    imagestring($im, 5, 340, 300, 'FIFA WORLD CUP 2026', $white);
}

imagepng($im, __DIR__ . '/og.png');
imagedestroy($im);
echo "og.png written\n";
