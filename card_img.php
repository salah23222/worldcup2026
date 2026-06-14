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

    // خلفية متدرّجة (أزرق ملكي) — موحّدة مع بطاقات التغريدات (TweetCardImage)
    $top = [45, 49, 175]; $bot = [25, 27, 115];
    for ($y = 0; $y < $H; $y++) {
        $tt = $y / $H;
        $c = imagecolorallocate($im,
            (int)($top[0] + ($bot[0]-$top[0])*$tt),
            (int)($top[1] + ($bot[1]-$top[1])*$tt),
            (int)($top[2] + ($bot[2]-$top[2])*$tt));
        imageline($im, 0, $y, $W, $y, $c);
    }

    // أقواس زخرفيّة شفّافة (نفس روح بطاقة النتائج)
    $arc = imagecolorallocatealpha($im, 150, 200, 245, 110);
    imagefilledellipse($im, 0, 150, 360, 360, $arc);
    imagefilledellipse($im, $W, (int)($H * 0.42), 320, 320, $arc);
    imagefilledellipse($im, 90, $H - 30, 340, 340, $arc);

    // ألوان الهوية (موحّدة)
    [$ar_, $ag_, $ab_] = card_hex_rgb($accent);
    $accentCol = imagecolorallocate($im, $ar_, $ag_, $ab_);
    $gold  = imagecolorallocate($im, 255, 200, 70);
    $white = imagecolorallocate($im, 255, 255, 255);
    $dim   = imagecolorallocate($im, 168, 205, 245);
    $navy  = imagecolorallocate($im, 26, 31, 100);

    $font  = __DIR__ . '/assets/fonts/Cairo-Black.ttf';
    $fontB = __DIR__ . '/assets/fonts/Cairo-Bold.ttf';
    $hasFont = is_file($font);

    // علامة «26» مائيّة كبيرة + شارة الهوية أعلى اليسار (موحّدة مع بطاقات التغريدات)
    if ($hasFont) {
        $wm = imagecolorallocatealpha($im, 255, 255, 255, 118);
        imagettftext($im, 300, 0, (int)($W * 0.70), (int)($H * 0.95), $wm, $font, '26');
        $bx = 46; $by = 34; $bs = 64;
        imagefilledrectangle($im, $bx, $by, $bx + $bs, $by + $bs, $white);
        $bb26 = imagettfbbox(32, 0, $font, '26'); $w26 = $bb26[2] - $bb26[0];
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

    // 🆕 بطاقة ترتيب مجموعة (?mode=group&g=A) — بيانات حقيقيّة من Standings بنفس الهوية
    if (($_GET['mode'] ?? '') === 'group') {
        $gL = strtoupper(preg_replace('/[^A-La-l]/', '', (string)($_GET['g'] ?? '')));
        // آخر حرف: يتحمّل «GA» القديم (G من Group) → A، و«A» المفرد → A
        $gL = $gL !== '' ? substr($gL, -1) : 'A';
        $rows = class_exists('Standings') ? Standings::forGroup('Group ' . $gL) : [];

        // جالب أعلام محلي (نسخة مصغّرة من جالب وضع المباراة)
        $fetch = function (string $url) {
            if ($url === '') return false;
            $cf = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
            if (is_file($cf)) { $r = @file_get_contents($cf); if ($r !== false) { $i = @imagecreatefromstring($r); if ($i) return $i; } }
            $raw = http_get($url, ['timeout' => 8]);
            if (!$raw) return false;
            @file_put_contents($cf, $raw);
            return @imagecreatefromstring($raw);
        };

        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        if ($hasFont && $rows) {
            $centerText($im, 44, 148, $white, $fontAr, $shape('المجموعة ' . $gL . ' — الترتيب'));
            $centerText($im, 20, 184, $gold,  $font,   'GROUP ' . $gL . ' · STANDINGS · FIFA WORLD CUP 2026');

            // أعمدة (مركز x) برؤوس عربيّة + إنجليزيّة تحتها
            $colX  = ['pts' => 250, 'gd' => 350, 'l' => 440, 'd' => 520, 'w' => 600, 'p' => 680];
            $colAr = ['pts' => 'نقاط', 'gd' => 'فارق', 'l' => 'خسر', 'd' => 'تعادل', 'w' => 'فوز', 'p' => 'لعب'];
            $colEn = ['pts' => 'PTS', 'gd' => 'GD', 'l' => 'L', 'd' => 'D', 'w' => 'W', 'p' => 'PLD'];
            $colHdr = function (int $x, string $ar, string $en) use ($im, $fontAr, $font, $dim, $shape) {
                $a = $shape($ar); $bb = imagettfbbox(17, 0, $fontAr, $a);
                imagettftext($im, 17, 0, (int)($x - ($bb[2] - $bb[0]) / 2), 222, $dim, $fontAr, $a);
                $bb = imagettfbbox(13, 0, $font, $en);
                imagettftext($im, 13, 0, (int)($x - ($bb[2] - $bb[0]) / 2), 242, $dim, $font, $en);
            };
            foreach ($colX as $k => $x) $colHdr($x, $colAr[$k], $colEn[$k]);
            $colHdr(885, 'المنتخب', 'TEAM');

            $y = 270;
            foreach (array_slice($rows, 0, 4) as $i => $r) {
                $teamEn = (string)$r['team'];
                imagefilledrectangle($im, 60, $y, $W - 60, $y + 68, imagecolorallocatealpha($im, 255, 255, 255, 120));
                $mid = $y + 34;
                imagettftext($im, 28, 0, 1092, $mid + 10, $gold, $font, (string)($i + 1));
                $fl = $fetch(flag_url($teamEn, 'w160'));
                if ($fl) {
                    imagecopyresampled($im, $fl, 1000, $y + 20, 0, 0, 66, 44, imagesx($fl), imagesy($fl));
                    imagedestroy($fl);
                    imagerectangle($im, 1000, $y + 20, 1066, $y + 64, imagecolorallocate($im, 36, 66, 104));
                }
                // الاسم: عربي (فوق) + إنجليزي (تحت) — كلاهما محاذى يميناً لـ982 فلا يدخل العلم
                $nameAr = $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn);
                $bb = imagettfbbox(27, 0, $fontAr, $nameAr);
                imagettftext($im, 27, 0, (int)(982 - ($bb[2] - $bb[0])), $mid - 1, $white, $fontAr, $nameAr);
                $en = strtoupper($teamEn); $bb = imagettfbbox(15, 0, $font, $en);
                imagettftext($im, 15, 0, (int)(982 - ($bb[2] - $bb[0])), $mid + 27, $dim, $font, $en);
                $vals = ['pts' => (int)$r['pts'], 'gd' => (int)$r['gd'], 'l' => (int)$r['l'], 'd' => (int)$r['d'], 'w' => (int)$r['w'], 'p' => (int)$r['p']];
                foreach ($vals as $k => $v) {
                    $vs = ($k === 'gd') ? (($v > 0 ? '+' : '') . $v) : (string)$v;
                    $size = ($k === 'pts') ? 30 : 24;
                    $col  = ($k === 'pts') ? $gold : $white;
                    $bb = imagettfbbox($size, 0, $font, $vs);
                    imagettftext($im, $size, 0, (int)($colX[$k] - ($bb[2] - $bb[0]) / 2), $mid + 9, $col, $font, $vs);
                }
                $y += 78;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 22, $white, $font, strtoupper($domain));
        } else {
            $centerBuiltin($im, 5, 280, $white, 'GROUP ' . $gL . ' STANDINGS');
            $centerBuiltin($im, 4, 330, $dim, 'wcup2026.org');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    // 🆕 بطاقة «المباريات القادمة خلال 24 ساعة» (?mode=upcoming) — لزر مشاركة صفحة المباريات
    if (($_GET['mode'] ?? '') === 'upcoming') {
        $now  = time();
        $list = [];
        if (class_exists('TweetComposer')) $list = TweetComposer::next24Matches(4);
        if (!$list && class_exists('DataService')) {
            foreach (DataService::allMatches() as $mm) {
                $ts = DataService::matchTimestamp($mm);
                if ($ts === null || $ts < $now - 900 || $ts > $now + 86400) continue;
                if (($mm['_status'] ?? '') !== 'upcoming') continue;
                $list[] = $mm;
            }
            usort($list, fn($a, $b) => (DataService::matchTimestamp($a) ?? 0) <=> (DataService::matchTimestamp($b) ?? 0));
            $list = array_slice($list, 0, 4);
        }

        $fetch = function (string $url) {
            if ($url === '') return false;
            $cf = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
            if (is_file($cf)) { $r = @file_get_contents($cf); if ($r !== false) { $i = @imagecreatefromstring($r); if ($i) return $i; } }
            $raw = http_get($url, ['timeout' => 8]);
            if (!$raw) return false;
            @file_put_contents($cf, $raw);
            return @imagecreatefromstring($raw);
        };
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $DAYS = ['Sunday'=>'الأحد','Monday'=>'الإثنين','Tuesday'=>'الثلاثاء','Wednesday'=>'الأربعاء','Thursday'=>'الخميس','Friday'=>'الجمعة','Saturday'=>'السبت'];
        $MON  = [1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'];
        $navyText = imagecolorallocate($im, 26, 31, 100);
        $enBlue   = imagecolorallocate($im, 70, 90, 140);
        $frame    = imagecolorallocate($im, 36, 66, 104);

        if ($hasFont) {
            $centerText($im, 44, 122, $white, $fontAr, $shape('المباريات القادمة'));
            $centerText($im, 19, 156, $gold,  $font,   'UPCOMING · NEXT 24H · FIFA WORLD CUP 2026');

            if ($list) {
                $y = 188;
                foreach ($list as $m) {
                    $t1 = (string)($m['team1'] ?? ''); $t2 = (string)($m['team2'] ?? '');
                    $ts = class_exists('DataService') ? DataService::matchTimestamp($m) : null;
                    if ($ts) {
                        $d = $shape(($DAYS[date('l', $ts)] ?? '') . ' ' . (int)date('j', $ts) . ' ' . ($MON[(int)date('n', $ts)] ?? ''));
                        $bb = imagettfbbox(15, 0, $fontAr, $d);
                        imagettftext($im, 15, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $y + 14, $gold, $fontAr, $d);
                    }
                    $by = $y + 24; $bh = 62; $mid = $by + (int)($bh/2);
                    imagefilledrectangle($im, 60, $by, $W - 60, $by + $bh, imagecolorallocatealpha($im, 255, 255, 255, 120));

                    // صندوق الوقت في الوسط (كحلي + نص أبيض)
                    $boxW = 186; $bx1 = (int)($W/2 - $boxW/2);
                    imagefilledrectangle($im, $bx1, $by + 6, $bx1 + $boxW, $by + $bh - 6, $navyText);
                    $time = $ts ? date('H:i', $ts) : '--:--';
                    $bb = imagettfbbox(28, 0, $font, $time);
                    imagettftext($im, 28, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $mid + 4, $white, $font, $time);
                    $tl = $shape('بتوقيت الإمارات'); $bb = imagettfbbox(12, 0, $fontAr, $tl);
                    imagettftext($im, 12, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $mid + 22, $dim, $fontAr, $tl);

                    // علم + اسم الفريقين (AR فوق · EN تحت)
                    $fw = 54; $fh = 36; $fy = $mid - (int)($fh/2);
                    // يمين: الفريق الأول (محاذاة يمين)
                    $f1x = $W - 60 - 22 - $fw;
                    if ($fl = $fetch(flag_url($t1, 'w160'))) {
                        imagecopyresampled($im, $fl, $f1x, $fy, 0, 0, $fw, $fh, imagesx($fl), imagesy($fl));
                        imagedestroy($fl); imagerectangle($im, $f1x, $fy, $f1x + $fw, $fy + $fh, $frame);
                    }
                    $rEdge = $f1x - 16;
                    $n1 = $shape(function_exists('team_name') ? team_name($t1) : $t1);
                    $bb = imagettfbbox(23, 0, $fontAr, $n1); imagettftext($im, 23, 0, (int)($rEdge - ($bb[2]-$bb[0])), $mid - 1, $navyText, $fontAr, $n1);
                    $e1 = strtoupper($t1); $bb = imagettfbbox(13, 0, $font, $e1); imagettftext($im, 13, 0, (int)($rEdge - ($bb[2]-$bb[0])), $mid + 21, $enBlue, $font, $e1);
                    // يسار: الفريق الثاني (محاذاة يسار)
                    $f2x = 60 + 22;
                    if ($fl2 = $fetch(flag_url($t2, 'w160'))) {
                        imagecopyresampled($im, $fl2, $f2x, $fy, 0, 0, $fw, $fh, imagesx($fl2), imagesy($fl2));
                        imagedestroy($fl2); imagerectangle($im, $f2x, $fy, $f2x + $fw, $fy + $fh, $frame);
                    }
                    $lEdge = $f2x + $fw + 16;
                    $n2 = $shape(function_exists('team_name') ? team_name($t2) : $t2);
                    imagettftext($im, 23, 0, $lEdge, $mid - 1, $navyText, $fontAr, $n2);
                    imagettftext($im, 13, 0, $lEdge, $mid + 21, $enBlue, $font, strtoupper($t2));

                    $y += 95;
                }
            } else {
                $centerText($im, 30, 330, $white, $fontAr, $shape('لا مباريات خلال الـ24 ساعة القادمة'));
                $centerText($im, 22, 372, $dim,   $font,   'NO MATCHES IN THE NEXT 24 HOURS');
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 22, $white, $font, strtoupper($domain));
        } else {
            $centerBuiltin($im, 5, 300, $white, 'UPCOMING MATCHES');
            $centerBuiltin($im, 4, 340, $dim, 'wcup2026.org');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    // 🆕 بطاقة كل المجموعات (?mode=groups) — 4 جداول مصغّرة بهويّة الموقع
    if (($_GET['mode'] ?? '') === 'groups') {
        $all = class_exists('Standings') ? Standings::all() : [];
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $fetch = function (string $url) {
            if ($url === '') return false;
            $cf = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
            if (is_file($cf)) { $r = @file_get_contents($cf); if ($r !== false) { $i = @imagecreatefromstring($r); if ($i) return $i; } }
            $raw = http_get($url, ['timeout' => 8]);
            if (!$raw) return false;
            @file_put_contents($cf, $raw);
            return @imagecreatefromstring($raw);
        };
        if ($hasFont && $all) {
            $gold2 = imagecolorallocate($im, 255, 194, 51);
            $centerText($im, 44, 156, $white, $fontAr, $shape('ترتيب المجموعات'));
            $centerText($im, 24, 196, $dim,   $fontAr, $shape('كأس العالم 2026'));

            $cellW = 540; $cellH = 176;
            $colX  = [640, 60];     // RTL: يمين ثم يسار
            $rowY  = [222, 408];
            $order = ['Group A', 'Group B', 'Group C', 'Group D'];
            $i = 0;
            foreach ($order as $gk) {
                if (!isset($all[$gk])) { $i++; continue; }
                $cx = $colX[$i % 2]; $cy = $rowY[intdiv($i, 2)];
                imagefilledrectangle($im, $cx, $cy, $cx + $cellW, $cy + $cellH, imagecolorallocatealpha($im, 255, 255, 255, 122));
                $gl = $shape('المجموعة ' . substr($gk, -1));
                $bb = imagettfbbox(22, 0, $fontAr, $gl);
                imagettftext($im, 22, 0, (int)($cx + $cellW - 16 - ($bb[2] - $bb[0])), $cy + 32, $gold2, $fontAr, $gl);
                $ry = $cy + 64;
                foreach (array_slice($all[$gk], 0, 4) as $ri => $r) {
                    $teamEn = (string)($r['team'] ?? '');
                    imagettftext($im, 17, 0, $cx + $cellW - 38, $ry + 4, $dim,   $font, (string)($ri + 1));
                    $fl = $fetch(flag_url($teamEn, 'w160'));
                    if ($fl) { imagecopyresampled($im, $fl, $cx + $cellW - 104, $ry - 13, 0, 0, 42, 28, imagesx($fl), imagesy($fl)); imagedestroy($fl); }
                    imagettftext($im, 19, 0, $cx + 64, $ry + 5, $white, $fontAr, $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn));
                    imagettftext($im, 20, 0, $cx + 20, $ry + 6, $gold2, $font, (string)($r['pts'] ?? 0));
                    $ry += 30;
                }
                $i++;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 24, $white, $font, strtoupper($domain));
        } else {
            $centerBuiltin($im, 5, 280, $white, 'GROUP STANDINGS - WORLD CUP 2026');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    // 🆕 بطاقة ملخّص البطاقات (?mode=cards) — مجاميع البطولة + الأكثر بطاقات لكل منتخب
    if (($_GET['mode'] ?? '') === 'cards') {
        $agg = [];
        foreach (DataService::allMatches() as $mm) {
            if (empty($mm['cards']) || !is_array($mm['cards'])) continue;
            foreach ($mm['cards'] as $cc) {
                if (!is_array($cc)) continue;
                $teamEn = ((int)($cc['team'] ?? 0) === 2) ? (string)($mm['team2'] ?? '') : (string)($mm['team1'] ?? '');
                if ($teamEn === '') continue;
                if (!isset($agg[$teamEn])) $agg[$teamEn] = ['y' => 0, 'r' => 0];
                if (($cc['type'] ?? '') === 'red') $agg[$teamEn]['r']++; else $agg[$teamEn]['y']++;
            }
        }
        $totY = array_sum(array_column($agg, 'y'));
        $totR = array_sum(array_column($agg, 'r'));
        uasort($agg, fn($a, $b) => ($b['r'] * 100 + $b['y']) <=> ($a['r'] * 100 + $a['y']));

        $yel   = imagecolorallocate($im, 255, 194, 51);
        $redc  = imagecolorallocate($im, 235, 70, 90);
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $fetch = function (string $url) {
            if ($url === '') return false;
            $cf = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
            if (is_file($cf)) { $r = @file_get_contents($cf); if ($r !== false) { $i = @imagecreatefromstring($r); if ($i) return $i; } }
            $raw = http_get($url, ['timeout' => 8]);
            if (!$raw) return false;
            @file_put_contents($cf, $raw);
            return @imagecreatefromstring($raw);
        };

        // Cairo-Black ينقص بعض حروف العربية → استخدم Amiri-Bold للنصّ العربي
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        if ($hasFont && $agg) {
            $centerText($im, 48, 182, $white, $fontAr, $shape('البطاقات'));
            $centerText($im, 26, 226, $dim,   $fontAr, $shape('كأس العالم 2026'));

            // مجاميع البطولة: مستطيل أصفر (إنذارات) + أحمر (طرد)
            $byY = 258; $bh = 58;
            imagefilledrectangle($im, (int)($W / 2 - 250), $byY, (int)($W / 2 - 20), $byY + $bh, $yel);
            imagefilledrectangle($im, (int)($W / 2 + 20), $byY, (int)($W / 2 + 250), $byY + $bh, $redc);
            $tY = $shape('إنذارات ' . $totY); $bb = imagettfbbox(26, 0, $fontAr, $tY);
            imagettftext($im, 26, 0, (int)($W / 2 - 135 - ($bb[2] - $bb[0]) / 2), $byY + 39, $navy,  $fontAr, $tY);
            $tR = $shape('طرد ' . $totR);     $bb = imagettfbbox(26, 0, $fontAr, $tR);
            imagettftext($im, 26, 0, (int)($W / 2 + 135 - ($bb[2] - $bb[0]) / 2), $byY + 39, $white, $fontAr, $tR);

            // الأكثر حصولاً على البطاقات (مجموع كل منتخب)
            $centerText($im, 24, 360, $gold, $fontAr, $shape('الأكثر حصولاً على البطاقات'));
            $y = 390; $rank = 0;
            foreach ($agg as $teamEn => $cc) {
                if ($rank >= 4) break;
                imagefilledrectangle($im, 130, $y, $W - 130, $y + 50, imagecolorallocatealpha($im, 255, 255, 255, 119));
                $fl = $fetch(flag_url($teamEn, 'w160'));
                if ($fl) { imagecopyresampled($im, $fl, 150, $y + 12, 0, 0, 39, 26, imagesx($fl), imagesy($fl)); imagedestroy($fl); }
                imagettftext($im, 26, 0, 210, $y + 36, $white, $fontAr, $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn));
                imagefilledrectangle($im, $W - 250, $y + 14, $W - 228, $y + 36, $yel);
                imagettftext($im, 24, 0, $W - 222, $y + 35, $white, $font, (string)$cc['y']);
                imagefilledrectangle($im, $W - 165, $y + 14, $W - 143, $y + 36, $redc);
                imagettftext($im, 24, 0, $W - 137, $y + 35, $white, $font, (string)$cc['r']);
                $y += 58; $rank++;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 24, $H - 46, $white, $font, $domain);
        } else {
            $centerBuiltin($im, 5, 280, $white, 'CARDS - WORLD CUP 2026');
        }
        imagepng($im); imagedestroy($im); exit;
    }

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
            // علامة «26» مائيّة كبيرة (عمق بصري بهوية البانر الجديدة)
            $wm = imagecolorallocatealpha($im, 150, 200, 245, 116);
            imagettftext($im, 300, 0, (int)($W * 0.66), (int)($H * 0.98), $wm, $font, '26');

            $centerText($im, 58, 158, $white, $font, 'FIFA WORLD CUP 2026');

            // أعلام الدول الثلاث المستضيفة (كندا · المكسيك · أمريكا)
            $fetchFlag = function (string $url) {
                if ($url === '') return false;
                $cf = rtrim(CACHE_DIR, '/') . '/flag_' . md5($url) . '.png';
                if (is_file($cf)) { $r = @file_get_contents($cf); if ($r !== false) { $i = @imagecreatefromstring($r); if ($i) return $i; } }
                $raw = http_get($url, ['timeout' => 8]);
                if (!$raw) return false;
                if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
                @file_put_contents($cf, $raw);
                return @imagecreatefromstring($raw);
            };
            $hosts  = [['Canada', 'CANADA'], ['Mexico', 'MEXICO'], ['USA', 'USA']];
            $fwf = 160; $fhf = 106; $gap = 60; $fyf = 220;
            $rowW   = count($hosts) * $fwf + (count($hosts) - 1) * $gap;
            $startX = (int)(($W - $rowW) / 2);
            $border = imagecolorallocate($im, 90, 130, 180);
            foreach ($hosts as $i => [$teamEn, $label]) {
                $fx = $startX + $i * ($fwf + $gap);
                $fl = $fetchFlag(flag_url($teamEn, 'w320'));
                if ($fl) {
                    imagecopyresampled($im, $fl, $fx, $fyf, 0, 0, $fwf, $fhf, imagesx($fl), imagesy($fl));
                    imagedestroy($fl);
                    imagerectangle($im, $fx, $fyf, $fx + $fwf, $fyf + $fhf, $border);
                }
                $bb = imagettfbbox(26, 0, $font, $label); $lw = $bb[2] - $bb[0];
                imagettftext($im, 26, 0, (int)($fx + ($fwf - $lw) / 2), $fyf + $fhf + 42, $gold, $font, $label);
            }

            $centerText($im, 26, 458, $dim, $font, '104 MATCHES  .  48 TEAMS  .  16 CITIES');
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 28, $H - 44, $white, $font, strtoupper($domain));
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
