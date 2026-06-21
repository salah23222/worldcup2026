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

    $W = 1200; $H = (($_GET['mode'] ?? '') === 'groups') ? 1480 : 630;   // كل المجموعات الـ12 → بطاقة طوليّة
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

            // فاصل رفيع تحت رؤوس الأعمدة
            imagefilledrectangle($im, 60, 252, $W - 60, 254, imagecolorallocatealpha($im, 255, 255, 255, 118));

            // ألوان وعناصر الصفّ — هويّة بيضاء + أخضر «متأهّل» (دلالة فقط، غير مهيمن)
            $rowBg  = imagecolorallocatealpha($im, 9, 15, 44, 62);
            $green  = imagecolorallocate($im, 34, 197, 94);
            $grayB  = imagecolorallocatealpha($im, 255, 255, 255, 92);
            $pillTx = imagecolorallocate($im, 10, 20, 48);
            $frameC = imagecolorallocate($im, 36, 66, 104);
            // شارة الترتيب (دائرة): أخضر للمتأهّلَين · رماديّ لغيرهما
            $rankBadge = function (int $cx, int $cy, int $n, $col) use ($im, $font, $white) {
                imagefilledellipse($im, $cx, $cy, 48, 48, $col);
                $s = (string)$n; $bb = imagettfbbox(23, 0, $font, $s);
                imagettftext($im, 23, 0, (int)($cx - ($bb[2] - $bb[0]) / 2), $cy + 11, $white, $font, $s);
            };
            // حبّة النقاط (pill) بيضاء بارزة + رقم كحليّ — الأبيض هو الأبرز (هويّة الموقع)
            $pill = function (int $cx, int $cy, string $txt) use ($im, $font, $white, $pillTx) {
                $bb = imagettfbbox(28, 0, $font, $txt); $tw = $bb[2] - $bb[0];
                $w = max(62, $tw + 36); $h = 48; $rr = (int)($h / 2);
                $x1 = (int)($cx - $w / 2); $x2 = (int)($cx + $w / 2);
                imagefilledrectangle($im, $x1 + $rr, $cy - $rr, $x2 - $rr, $cy + $rr, $white);
                imagefilledellipse($im, $x1 + $rr, $cy, $h, $h, $white);
                imagefilledellipse($im, $x2 - $rr, $cy, $h, $h, $white);
                imagettftext($im, 28, 0, (int)($cx - $tw / 2), $cy + 10, $pillTx, $font, $txt);
            };

            $y = 270;
            foreach (array_slice($rows, 0, 4) as $i => $r) {
                $teamEn = (string)$r['team'];
                $qual = ($i < 2);                       // أوّل منتخبَين يتأهّلان
                $mid  = $y + 34;
                imagefilledrectangle($im, 60, $y, $W - 60, $y + 68, $rowBg);
                if ($qual) imagefilledrectangle($im, $W - 66, $y, $W - 60, $y + 68, $green);   // حافّة «متأهّل» خضراء رفيعة
                $rankBadge(1090, $mid, $i + 1, $qual ? $green : $grayB);
                $fl = $fetch(flag_url($teamEn, 'w160'));
                if ($fl) {
                    imagecopyresampled($im, $fl, 988, $y + 18, 0, 0, 70, 47, imagesx($fl), imagesy($fl));
                    imagedestroy($fl);
                    imagerectangle($im, 988, $y + 18, 1058, $y + 65, $frameC);
                }
                // الاسم: عربي (فوق) + إنجليزي (تحت) — محاذى يميناً لـ968 فلا يدخل العلم
                $nameAr = $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn);
                $bb = imagettfbbox(27, 0, $fontAr, $nameAr);
                imagettftext($im, 27, 0, (int)(968 - ($bb[2] - $bb[0])), $mid - 1, $white, $fontAr, $nameAr);
                $en = strtoupper($teamEn); $bb = imagettfbbox(15, 0, $font, $en);
                imagettftext($im, 15, 0, (int)(968 - ($bb[2] - $bb[0])), $mid + 27, $dim, $font, $en);
                // الأعمدة الرقميّة (عدا النقاط — لها حبّة بارزة)
                $vals = ['gd' => (int)$r['gd'], 'l' => (int)$r['l'], 'd' => (int)$r['d'], 'w' => (int)$r['w'], 'p' => (int)$r['p']];
                foreach ($vals as $k => $v) {
                    $vs = ($k === 'gd') ? (($v > 0 ? '+' : '') . $v) : (string)$v;
                    $bb = imagettfbbox(24, 0, $font, $vs);
                    imagettftext($im, 24, 0, (int)($colX[$k] - ($bb[2] - $bb[0]) / 2), $mid + 9, $white, $font, $vs);
                }
                $pill($colX['pts'], $mid, (string)(int)$r['pts']);
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

    // 🆕 بطاقة الصدارة (?mode=leaderboard) — أفضل المتوقّعين بأسمائهم ونقاطهم
    if (($_GET['mode'] ?? '') === 'leaderboard') {
        $rows   = class_exists('Predictions') ? Predictions::leaderboard(8) : [];
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape  = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $isAr   = fn(string $s): bool => (bool)preg_match('/\p{Arabic}/u', $s);
        if ($hasFont && $rows) {
            $centerText($im, 46, 150, $white, $fontAr, $shape('أفضل المتوقّعين'));
            $centerText($im, 19, 186, $gold,  $font,   'LEADERBOARD · PREDICTIONS · FIFA WORLD CUP 2026');

            $goldC   = imagecolorallocate($im, 255, 200, 70);
            $silverC = imagecolorallocate($im, 203, 213, 225);
            $bronzeC = imagecolorallocate($im, 205, 127, 50);
            $rowBg   = imagecolorallocatealpha($im, 9, 15, 44, 62);
            $grayB   = imagecolorallocatealpha($im, 255, 255, 255, 96);
            $pillTx  = imagecolorallocate($im, 10, 20, 48);

            $rankBadge = function (int $cx, int $cy, int $n, $col) use ($im, $font, $white) {
                imagefilledellipse($im, $cx, $cy, 44, 44, $col);
                $s = (string)$n; $bb = imagettfbbox(22, 0, $font, $s);
                imagettftext($im, 22, 0, (int)($cx - ($bb[2]-$bb[0])/2), $cy + 10, $white, $font, $s);
            };
            $pill = function (int $cx, int $cy, string $txt) use ($im, $font, $white, $pillTx) {
                $bb = imagettfbbox(26, 0, $font, $txt); $tw = $bb[2]-$bb[0];
                $w = max(58, $tw + 34); $h = 44; $rr = (int)($h/2);
                $x1 = (int)($cx - $w/2); $x2 = (int)($cx + $w/2);
                imagefilledrectangle($im, $x1+$rr, $cy-$rr, $x2-$rr, $cy+$rr, $white);
                imagefilledellipse($im, $x1+$rr, $cy, $h, $h, $white);
                imagefilledellipse($im, $x2-$rr, $cy, $h, $h, $white);
                imagettftext($im, 26, 0, (int)($cx - $tw/2), $cy + 9, $pillTx, $font, $txt);
            };

            $y = 214;
            foreach (array_slice($rows, 0, 8) as $i => $r) {
                $rank = $i + 1;
                // أزل الإيموجي (خط GD لا يرسمها → تشوّه) — مثل «🤖 AI» تصبح «AI»
                $nick = trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', (string)($r['nickname'] ?? '')));
                if ($nick === '') $nick = (string)($r['nickname'] ?? '');   // احتياط لو كان الاسم إيموجي فقط
                if ($nick === '') continue;
                $col  = $rank === 1 ? $goldC : ($rank === 2 ? $silverC : ($rank === 3 ? $bronzeC : $grayB));
                imagefilledrectangle($im, 60, $y, $W - 60, $y + 44, $rowBg);
                $mid = $y + 22;
                $rankBadge(1132, $mid, $rank, $col);
                if ($isAr($nick)) { $nm = $shape($nick); $nf = $fontAr; } else { $nm = $nick; $nf = $font; }
                $bb = imagettfbbox(27, 0, $nf, $nm);
                imagettftext($im, 27, 0, (int)(1092 - ($bb[2]-$bb[0])), $mid + 10, $white, $nf, $nm);
                $pill(150, $mid, (string)(int)($r['points'] ?? 0));
                $y += 48;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 20, $white, $font, strtoupper($domain));
        } else {
            $centerBuiltin($im, 5, 290, $white, 'LEADERBOARD');
            $centerBuiltin($im, 4, 340, $dim, 'wcup2026.org');
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
        $GROUP_AR = ['A'=>'الأولى','B'=>'الثانية','C'=>'الثالثة','D'=>'الرابعة','E'=>'الخامسة','F'=>'السادسة','G'=>'السابعة','H'=>'الثامنة','I'=>'التاسعة','J'=>'العاشرة','K'=>'الحادية عشرة','L'=>'الثانية عشرة'];
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
                    $dateStr = '';
                    if ($ts) $dateStr = ($DAYS[date('l', $ts)] ?? '') . ' ' . (int)date('j', $ts) . ' ' . ($MON[(int)date('n', $ts)] ?? '');
                    $grpStr = (preg_match('/Group\s*([A-L])/i', (string)($m['group'] ?? ''), $gmm))
                        ? ('المجموعة ' . ($GROUP_AR[strtoupper($gmm[1])] ?? strtoupper($gmm[1]))) : '';
                    $hdr = trim($dateStr . ($dateStr && $grpStr ? '  ·  ' : '') . $grpStr);
                    if ($hdr !== '') {
                        $hs = $shape($hdr); $bb = imagettfbbox(15, 0, $fontAr, $hs);
                        imagettftext($im, 15, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $y + 14, $gold, $fontAr, $hs);
                    }
                    $by = $y + 24; $bh = 62; $mid = $by + (int)($bh/2);
                    // شريط داكن شفّاف ليبرز اسم المنتخب الأبيض
                    imagefilledrectangle($im, 60, $by, $W - 60, $by + $bh, imagecolorallocatealpha($im, 10, 14, 64, 64));

                    // صندوق الوقت في الوسط (أبيض + نص كحلي) — يبرز على الشريط الداكن
                    $boxW = 186; $bx1 = (int)($W/2 - $boxW/2);
                    imagefilledrectangle($im, $bx1, $by + 6, $bx1 + $boxW, $by + $bh - 6, $white);
                    $time = $ts ? date('H:i', $ts) : '--:--';
                    $bb = imagettfbbox(28, 0, $font, $time);
                    imagettftext($im, 28, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $mid + 4, $navyText, $font, $time);
                    $tl = $shape('بتوقيت الإمارات'); $bb = imagettfbbox(12, 0, $fontAr, $tl);
                    imagettftext($im, 12, 0, (int)($W/2 - ($bb[2]-$bb[0])/2), $mid + 22, $navyText, $fontAr, $tl);

                    // علم + اسم الفريقين (AR أبيض فوق · EN أزرق فاتح تحت)
                    $fw = 54; $fh = 36; $fy = $mid - (int)($fh/2);
                    // يمين: الفريق الأول (محاذاة يمين)
                    $f1x = $W - 60 - 22 - $fw;
                    if ($fl = $fetch(flag_url($t1, 'w160'))) {
                        imagecopyresampled($im, $fl, $f1x, $fy, 0, 0, $fw, $fh, imagesx($fl), imagesy($fl));
                        imagedestroy($fl); imagerectangle($im, $f1x, $fy, $f1x + $fw, $fy + $fh, $frame);
                    }
                    $rEdge = $f1x - 16;
                    $n1 = $shape(function_exists('team_name') ? team_name($t1) : $t1);
                    $bb = imagettfbbox(23, 0, $fontAr, $n1); imagettftext($im, 23, 0, (int)($rEdge - ($bb[2]-$bb[0])), $mid - 1, $white, $fontAr, $n1);
                    $e1 = strtoupper($t1); $bb = imagettfbbox(13, 0, $font, $e1); imagettftext($im, 13, 0, (int)($rEdge - ($bb[2]-$bb[0])), $mid + 21, $dim, $font, $e1);
                    // يسار: الفريق الثاني (محاذاة يسار)
                    $f2x = 60 + 22;
                    if ($fl2 = $fetch(flag_url($t2, 'w160'))) {
                        imagecopyresampled($im, $fl2, $f2x, $fy, 0, 0, $fw, $fh, imagesx($fl2), imagesy($fl2));
                        imagedestroy($fl2); imagerectangle($im, $f2x, $fy, $f2x + $fw, $fy + $fh, $frame);
                    }
                    $lEdge = $f2x + $fw + 16;
                    $n2 = $shape(function_exists('team_name') ? team_name($t2) : $t2);
                    imagettftext($im, 23, 0, $lEdge, $mid - 1, $white, $fontAr, $n2);
                    imagettftext($im, 13, 0, $lEdge, $mid + 21, $dim, $font, strtoupper($t2));

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

    // 🆕 بطاقة لوحة الإحصائيّات (?mode=dashboard) — مؤشّرات البطولة من تقارير FIFA
    if (($_GET['mode'] ?? '') === 'dashboard') {
        $k = class_exists('FifaStats') ? (FifaStats::teamDashboard()['kpi'] ?? []) : [];
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $tn = fn($en) => function_exists('team_name') ? team_name((string)$en) : (string)$en;
        $ctile = function ($im, $size, $cx, $y, $color, $fnt, $text) {
            if ($text === '') return;
            $bb = imagettfbbox($size, 0, $fnt, $text);
            imagettftext($im, $size, 0, (int)($cx - ($bb[2] - $bb[0]) / 2), $y, $color, $fnt, $text);
        };
        if ($hasFont && !empty($k)) {
            $centerText($im, 44, 100, $white, $fontAr, $shape('لوحة الإحصائيّات'));
            $centerText($im, 19, 134, $gold,  $font,   'FIFA STATS DASHBOARD · WORLD CUP 2026');
            $fast = $k['fastest'] ?? []; $td = $k['topDist'] ?? []; $ts = $k['topSprint'] ?? []; $tx = $k['topXg'] ?? [];
            $tiles = [
                ['v' => (string)(int)($k['matches'] ?? 0),                                         'la' => 'مباريات محلَّلة',     'le' => 'Matches analysed',   's' => '',                           'sa' => false],
                ['v' => number_format((float)($k['distance'] ?? 0), 0),                            'la' => 'إجمالي المسافة · كم', 'le' => 'Total distance · km','s' => '',                           'sa' => false],
                ['v' => rtrim(rtrim(number_format((float)($fast['v'] ?? 0), 1, '.', ''), '0'), '.'),'la' => 'أسرع لاعب · كم/س',    'le' => 'Fastest · km/h',     's' => (string)($fast['name'] ?? ''),'sa' => false],
                ['v' => number_format((float)($td['v'] ?? 0) / 1000, 1),                           'la' => 'أكثر مسافة · كم',     'le' => 'Most distance · km', 's' => (string)($td['name'] ?? ''),  'sa' => false],
                ['v' => number_format((float)($ts['v'] ?? 0), 0),                                  'la' => 'أكثر عَدْوات/مباراة', 'le' => 'Most sprints/match', 's' => (string)($ts['name'] ?? ''),  'sa' => false],
                ['v' => number_format((float)($tx['v'] ?? 0), 2),                                  'la' => 'أعلى xG لمباراة',     'le' => 'Highest match xG',   's' => $tn($tx['team'] ?? ''),       'sa' => true],
            ];
            $cols = [240, 600, 960]; $tops = [166, 360]; $hw = 178; $th = 176;
            foreach ($tiles as $i => $t) {
                $cx = $cols[$i % 3]; $top = $tops[intdiv($i, 3)];
                imagefilledrectangle($im, $cx - $hw, $top, $cx + $hw, $top + $th, imagecolorallocatealpha($im, 255, 255, 255, 118));
                $ctile($im, 40, $cx, $top + 54, $gold,  $font,   $t['v']);
                $ctile($im, 16, $cx, $top + 86, $white, $fontAr, $shape($t['la']));
                $ctile($im, 12, $cx, $top + 106, $dim, $font, $t['le']);
                if ($t['s'] !== '') $ctile($im, 14, $cx, $top + 136, $dim, $t['sa'] ? $fontAr : $font, $t['sa'] ? $shape($t['s']) : $t['s']);
            }
            $centerText($im, 22, $H - 22, $white, $font, strtoupper(parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org'));
        } else {
            $centerBuiltin($im, 5, 300, $white, 'FIFA STATS DASHBOARD');
            $centerBuiltin($im, 4, 340, $dim, 'wcup2026.org');
        }
        imagepng($im); imagedestroy($im); exit;
    }

    // 🆕 بطاقة الملفّ الفنّي للاعب (?mode=player&id=.. أو &name=..&team=..) — صورة رسميّة
    // + تقييم + نقاط الفئات. تُستعمل كمعاينة مشاركة لـplayer.php وكصورة في README.
    if (($_GET['mode'] ?? '') === 'player') {
        $fontAr = __DIR__ . '/assets/fonts/Amiri-Bold.ttf';
        if (!is_file($fontAr)) $fontAr = $font;
        $shape = fn(string $s): string => class_exists('ArabicText') ? ArabicText::shape($s) : $s;
        $fetch = function (string $url) {
            if ($url === '') return false;
            $raw = http_get($url, ['timeout' => 9]);
            return $raw ? @imagecreatefromstring($raw) : false;
        };

        $pid = (string)($_GET['id'] ?? '');
        if ($pid === '' && isset($_GET['name']) && class_exists('FifaMetrics')) {
            $pid = (string)(FifaMetrics::findId((string)$_GET['name'], (string)($_GET['team'] ?? '')) ?? '');
        }
        $pl = ($pid !== '' && class_exists('FifaMetrics')) ? FifaMetrics::player($pid) : null;

        if ($pl) {
            // ── الصورة الدائريّة (يمين) مع إطار ذهبي — والنصّ يساراً (مناسب للعربيّة) ──
            $AV = 312; $px = $W - $AV - 96; $py = 168;
            imagefilledellipse($im, $px + $AV / 2, $py + $AV / 2, $AV + 14, $AV + 14, $gold);
            $av = imagecreatetruecolor($AV, $AV);
            imagesavealpha($av, true);
            $tr = imagecolorallocatealpha($av, 0, 0, 0, 127);
            imagefill($av, 0, 0, $tr);
            $pimg = $fetch((string)($pl['photo'] ?? ''));
            if ($pimg) {
                $pw = imagesx($pimg); $ph = imagesy($pimg);
                $side = min($pw, $ph);
                imagecopyresampled($av, $pimg, 0, 0, (int)(($pw - $side) / 2), 0, $AV, $AV, $side, $side);
                $r2 = ($AV / 2) * ($AV / 2);
                for ($yy = 0; $yy < $AV; $yy++) for ($xx = 0; $xx < $AV; $xx++) {
                    $dx = $xx - $AV / 2; $dy = $yy - $AV / 2;
                    if ($dx * $dx + $dy * $dy > $r2) imagesetpixel($av, $xx, $yy, $tr);
                }
                imagedestroy($pimg);
            }
            imagecopy($im, $av, $px, $py, 0, 0, $AV, $AV);
            imagedestroy($av);

            // ── الاسم + الفريق + التقييم (يسار) ──
            $tx = 80;                        // بداية عمود النصّ (يسار)
            imagettftext($im, 18, 0, $tx, 188, $gold, $font, 'FIFA TECHNICAL PROFILE · WORLD CUP 2026');
            $name = strtoupper(trim((string)$pl['name']));
            $nsize = mb_strlen($name) > 18 ? 38 : (mb_strlen($name) > 13 ? 46 : 54);
            imagettftext($im, $nsize, 0, $tx, 240, $white, $font, $name);

            $teamLoc = function_exists('team_name') ? team_name((string)$pl['teamName']) : (string)$pl['teamName'];
            $tline = $shape($teamLoc . (($pl['pos'] ?? '') !== '' ? '  ·  ' . $pl['pos'] : ''));
            imagettftext($im, 24, 0, $tx, 288, $dim, $fontAr, $tline);

            if ($pl['r'] !== null) {
                $lblR = $shape('تقييم'); $rstr = number_format((float)$pl['r'], 1);
                $rb = imagettfbbox(30, 0, $font, $rstr); $rw = $rb[2] - $rb[0];
                imagefilledrectangle($im, $tx, 310, $tx + $rw + 34, 364, $gold);
                imagettftext($im, 30, 0, $tx + 17, 349, $navy, $font, $rstr);
                imagettftext($im, 18, 0, $tx + $rw + 50, 347, $dim, $fontAr, $lblR);
            }

            // ── أشرطة نقاط الفئات الكبرى ──
            $macro = class_exists('FifaMetrics') ? FifaMetrics::macro($pl, 'ar') : [];
            $by = 400; $bw = 372; $bh = 30; $gap = 48;
            $trkX = $tx + 222;
            foreach (array_slice($macro, 0, 4) as $mc) {
                $lab = $shape((string)$mc['label']); $sc = (int)$mc['score'];
                $lb = imagettfbbox(19, 0, $fontAr, $lab); $lw = $lb[2] - $lb[0];
                imagettftext($im, 19, 0, (int)($trkX - 16 - $lw), $by + 22, $white, $fontAr, $lab);
                imagefilledrectangle($im, $trkX, $by, $trkX + $bw, $by + $bh, imagecolorallocatealpha($im, 255, 255, 255, 108));
                $fillW = (int)max(6, $bw * $sc / 100);
                imagefilledrectangle($im, $trkX, $by, $trkX + $fillW, $by + $bh, $gold);
                imagettftext($im, 24, 0, $trkX + $bw + 16, $by + 26, $gold, $font, (string)$sc);
                $by += $gap;
            }

            $centerText($im, 22, $H - 22, $white, $font, strtoupper(parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org'));
        } else {
            $centerBuiltin($im, 5, 300, $white, 'PLAYER PROFILE');
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
            $centerText($im, 48, 128, $white, $fontAr, $shape('ترتيب المجموعات'));
            $centerText($im, 24, 170, $dim,   $fontAr, $shape('كأس العالم 2026 · دور المجموعات'));

            // شبكة 3 أعمدة × 4 صفوف لكل الـ12 مجموعة (RTL: يمين · وسط · يسار)
            $cellW = 376; $cellH = 286;
            $colX  = [804, 412, 20];
            $rowY  = [222, 532, 842, 1152];
            $i = 0;
            foreach (range('A', 'L') as $L) {
                $gk = 'Group ' . $L;
                $cx = $colX[$i % 3]; $cy = $rowY[intdiv($i, 3)];
                imagefilledrectangle($im, $cx, $cy, $cx + $cellW, $cy + $cellH, imagecolorallocatealpha($im, 255, 255, 255, 124));
                $gl = $shape('المجموعة ' . ($i + 1));
                $bb = imagettfbbox(22, 0, $fontAr, $gl);
                imagettftext($im, 22, 0, (int)($cx + $cellW - 16 - ($bb[2] - $bb[0])), $cy + 38, $gold2, $fontAr, $gl);
                $ry = $cy + 80;
                foreach (array_slice($all[$gk] ?? [], 0, 4) as $ri => $r) {
                    $teamEn = (string)($r['team'] ?? '');
                    imagettftext($im, 15, 0, $cx + $cellW - 26, $ry + 3, $dim, $font, (string)($ri + 1));   // الترتيب
                    $fl = $fetch(function_exists('flag_url') ? flag_url($teamEn, 'w160') : '');
                    if ($fl) { imagecopyresampled($im, $fl, $cx + $cellW - 90, $ry - 14, 0, 0, 42, 28, imagesx($fl), imagesy($fl)); imagedestroy($fl); }
                    // الاسم بمحاذاة يمين (بجانب العلم)
                    $nm = $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn);
                    $nbb = imagettfbbox(16, 0, $fontAr, $nm); $nw = $nbb[2] - $nbb[0];
                    imagettftext($im, 16, 0, max($cx + 50, (int)($cx + $cellW - 98 - $nw)), $ry + 4, $white, $fontAr, $nm);
                    imagettftext($im, 19, 0, $cx + 18, $ry + 5, $gold2, $font, (string)($r['pts'] ?? 0));     // النقاط
                    $ry += 50;
                }
                $i++;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 30, $white, $font, strtoupper($domain));
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
            $centerText($im, 44, 108, $white, $fontAr, $shape('البطاقات'));
            $centerText($im, 18, 142, $gold,  $font,   'BOOKINGS · DISCIPLINE · FIFA WORLD CUP 2026');

            // مجاميع البطولة: شارة صفراء (إنذارات) + حمراء (طرد)
            $pY = 168; $pH = 44;
            imagefilledrectangle($im, (int)($W/2 - 232), $pY, (int)($W/2 - 18), $pY + $pH, $yel);
            imagefilledrectangle($im, (int)($W/2 + 18), $pY, (int)($W/2 + 232), $pY + $pH, $redc);
            $tY = $shape('إنذارات: ' . $totY); $bb = imagettfbbox(24, 0, $fontAr, $tY);
            imagettftext($im, 24, 0, (int)($W/2 - 125 - ($bb[2]-$bb[0])/2), $pY + 31, $navy,  $fontAr, $tY);
            $tR = $shape('طرد: ' . $totR);     $bb = imagettfbbox(24, 0, $fontAr, $tR);
            imagettftext($im, 24, 0, (int)($W/2 + 125 - ($bb[2]-$bb[0])/2), $pY + 31, $white, $fontAr, $tR);

            // رؤوس الأعمدة (عربي + إنجليزي) — مطابقة لبطاقة الترتيب
            $colHdr = function (int $x, string $ar, string $en) use ($im, $fontAr, $font, $dim, $shape) {
                $a = $shape($ar); $bb = imagettfbbox(17, 0, $fontAr, $a);
                imagettftext($im, 17, 0, (int)($x - ($bb[2]-$bb[0])/2), 250, $dim, $fontAr, $a);
                $bb = imagettfbbox(13, 0, $font, $en);
                imagettftext($im, 13, 0, (int)($x - ($bb[2]-$bb[0])/2), 270, $dim, $font, $en);
            };
            $colHdr(300, 'طرد',   'RED');
            $colHdr(470, 'إنذار', 'YELLOW');
            $colHdr(885, 'المنتخب', 'TEAM');

            $y = 290; $rank = 0;
            foreach ($agg as $teamEn => $cc) {
                if ($rank >= 4) break;
                imagefilledrectangle($im, 60, $y, $W - 60, $y + 66, imagecolorallocatealpha($im, 255, 255, 255, 120));
                $mid = $y + 33;
                imagettftext($im, 26, 0, 1096, $mid + 9, $gold, $font, (string)($rank + 1));
                if ($fl = $fetch(flag_url($teamEn, 'w160'))) {
                    imagecopyresampled($im, $fl, 1000, $y + 18, 0, 0, 66, 44, imagesx($fl), imagesy($fl));
                    imagedestroy($fl); imagerectangle($im, 1000, $y + 18, 1066, $y + 62, imagecolorallocate($im, 36, 66, 104));
                }
                $nameAr = $shape(function_exists('team_name') ? team_name($teamEn) : $teamEn);
                $bb = imagettfbbox(26, 0, $fontAr, $nameAr);
                imagettftext($im, 26, 0, (int)(982 - ($bb[2]-$bb[0])), $mid - 1, $navy, $fontAr, $nameAr);
                $en = strtoupper($teamEn); $bb = imagettfbbox(14, 0, $font, $en);
                imagettftext($im, 14, 0, (int)(982 - ($bb[2]-$bb[0])), $mid + 24, imagecolorallocate($im, 70, 90, 140), $font, $en);

                // خانة صفراء (إنذارات) برقم كحلي + خانة حمراء (طرد) برقم أبيض
                $sq = 40;
                imagefilledrectangle($im, (int)(470 - $sq/2), $mid - 20, (int)(470 + $sq/2), $mid + 20, $yel);
                $vs = (string)$cc['y']; $bb = imagettfbbox(24, 0, $font, $vs);
                imagettftext($im, 24, 0, (int)(470 - ($bb[2]-$bb[0])/2), $mid + 9, $navy, $font, $vs);
                imagefilledrectangle($im, (int)(300 - $sq/2), $mid - 20, (int)(300 + $sq/2), $mid + 20, $redc);
                $vs = (string)$cc['r']; $bb = imagettfbbox(24, 0, $font, $vs);
                imagettftext($im, 24, 0, (int)(300 - ($bb[2]-$bb[0])/2), $mid + 9, $white, $font, $vs);

                $y += 74; $rank++;
            }
            $domain = parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org';
            $centerText($im, 22, $H - 22, $white, $font, strtoupper($domain));
        } else {
            $centerBuiltin($im, 5, 300, $white, 'CARDS - WORLD CUP 2026');
            $centerBuiltin($im, 4, 340, $dim, 'wcup2026.org');
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
