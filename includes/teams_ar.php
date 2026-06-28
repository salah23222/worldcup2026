<?php
/**
 * teams_ar.php
 * ============================================================
 * يربط اسم المنتخب الإنجليزي (كما يأتي من openfootball)
 * بالاسم العربي ورمز الدولة (ISO) لعرض العلم.
 *
 * ملاحظة: openfootball يستخدم الأسماء الإنجليزية. هذا الملف
 * هو طبقة العرض فقط — لا يؤثر على البيانات.
 * أي منتخب غير موجود هنا يُعرض باسمه الإنجليزي تلقائياً.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

/**
 * كل منتخب: [الاسم العربي, رمز ISO ثنائي للعلم]
 * رمز ISO يُستخدم لجلب العلم من CDN (flagcdn.com) — مجاني.
 */
function teams_map(): array {
    return [
        // ===== المضيفون =====
        'United States'   => ['الولايات المتحدة', 'us'],
        'USA'             => ['الولايات المتحدة', 'us'],
        'Canada'          => ['كندا', 'ca'],
        'Mexico'          => ['المكسيك', 'mx'],

        // ===== أوروبا (UEFA) =====
        'Spain'           => ['إسبانيا', 'es'],
        'France'          => ['فرنسا', 'fr'],
        'England'         => ['إنجلترا', 'gb-eng'],
        'Germany'         => ['ألمانيا', 'de'],
        'Portugal'        => ['البرتغال', 'pt'],
        'Netherlands'     => ['هولندا', 'nl'],
        'Belgium'         => ['بلجيكا', 'be'],
        'Croatia'         => ['كرواتيا', 'hr'],
        'Italy'           => ['إيطاليا', 'it'],
        'Switzerland'     => ['سويسرا', 'ch'],
        'Austria'         => ['النمسا', 'at'],
        'Norway'          => ['النرويج', 'no'],
        'Scotland'        => ['اسكتلندا', 'gb-sct'],
        'Czech Republic'  => ['التشيك', 'cz'],
        'Czechia'         => ['التشيك', 'cz'],
        'Denmark'         => ['الدنمارك', 'dk'],
        'Poland'          => ['بولندا', 'pl'],
        'Turkey'          => ['تركيا', 'tr'],
        'Türkiye'         => ['تركيا', 'tr'],
        'Sweden'          => ['السويد', 'se'],
        'Ukraine'         => ['أوكرانيا', 'ua'],
        'Serbia'          => ['صربيا', 'rs'],
        'Wales'           => ['ويلز', 'gb-wls'],
        'Slovakia'        => ['سلوفاكيا', 'sk'],
        'Hungary'         => ['المجر', 'hu'],
        'Bosnia and Herzegovina' => ['البوسنة والهرسك', 'ba'],
        'Bosnia & Herzegovina'   => ['البوسنة والهرسك', 'ba'],
        'Republic of Ireland'    => ['إيرلندا', 'ie'],

        // ===== أمريكا الجنوبية (CONMEBOL) =====
        'Brazil'          => ['البرازيل', 'br'],
        'Argentina'       => ['الأرجنتين', 'ar'],
        'Uruguay'         => ['الأوروغواي', 'uy'],
        'Colombia'        => ['كولومبيا', 'co'],
        'Ecuador'         => ['الإكوادور', 'ec'],
        'Paraguay'        => ['الباراغواي', 'py'],
        'Peru'            => ['بيرو', 'pe'],
        'Chile'           => ['تشيلي', 'cl'],
        'Venezuela'       => ['فنزويلا', 've'],
        'Bolivia'         => ['بوليفيا', 'bo'],

        // ===== أفريقيا (CAF) =====
        'Morocco'         => ['المغرب', 'ma'],
        'Senegal'         => ['السنغال', 'sn'],
        'Egypt'           => ['مصر', 'eg'],
        'Tunisia'         => ['تونس', 'tn'],
        'Algeria'         => ['الجزائر', 'dz'],
        'Ghana'           => ['غانا', 'gh'],
        'Nigeria'         => ['نيجيريا', 'ng'],
        'Cameroon'        => ['الكاميرون', 'cm'],
        'Ivory Coast'     => ['كوت ديفوار', 'ci'],
        "Côte d'Ivoire"   => ['كوت ديفوار', 'ci'],
        'South Africa'    => ['جنوب أفريقيا', 'za'],
        'Cape Verde'      => ['الرأس الأخضر', 'cv'],
        'Mali'            => ['مالي', 'ml'],
        'DR Congo'        => ['الكونغو الديمقراطية', 'cd'],
        'Congo DR'        => ['الكونغو الديمقراطية', 'cd'],

        // ===== آسيا (AFC) =====
        'Japan'           => ['اليابان', 'jp'],
        'South Korea'     => ['كوريا الجنوبية', 'kr'],
        'Korea Republic'  => ['كوريا الجنوبية', 'kr'],
        'Iran'            => ['إيران', 'ir'],
        'IR Iran'         => ['إيران', 'ir'],
        'Saudi Arabia'    => ['السعودية', 'sa'],
        'Australia'       => ['أستراليا', 'au'],
        'Qatar'           => ['قطر', 'qa'],
        'Iraq'            => ['العراق', 'iq'],
        'Jordan'          => ['الأردن', 'jo'],
        'Uzbekistan'      => ['أوزبكستان', 'uz'],
        'United Arab Emirates' => ['الإمارات', 'ae'],

        // ===== أمريكا الشمالية والوسطى (CONCACAF) =====
        'Panama'          => ['بنما', 'pa'],
        'Costa Rica'      => ['كوستاريكا', 'cr'],
        'Honduras'        => ['هندوراس', 'hn'],
        'Jamaica'         => ['جامايكا', 'jm'],
        'Haiti'           => ['هايتي', 'ht'],
        'Curaçao'         => ['كوراساو', 'cw'],
        'Curacao'         => ['كوراساو', 'cw'],

        // ===== أوقيانوسيا (OFC) =====
        'New Zealand'     => ['نيوزيلندا', 'nz'],
    ];
}

/**
 * ko_resolve_pos() — يحلّ عنصر «1X/2X» (أول/ثاني المجموعة X) إلى المنتخب الفعلي
 * من الترتيب النهائي **بعد اكتمال مباريات تلك المجموعة فقط** (وإلّا يبقى نائباً).
 * عناصر الثالث «3X/Y/Z» لا تُحَلّ هنا (تتبع جدول تخصيص FIFA الرسمي — لا نخمّنها).
 * كاش لكل مجموعة → استدعاء team_name/team_flag المتكرّر لا يُعيد الحساب.
 */
function ko_resolve_pos(string $raw): string {
    if (!preg_match('/^([12])([A-L])$/i', trim($raw), $m) || !class_exists('Standings')) return $raw;
    static $cache = [];
    $g = strtoupper($m[2]);
    if (!array_key_exists($g, $cache)) {
        $cache[$g] = null;
        $rows = Standings::forGroup('Group ' . $g);
        $done = !empty($rows);
        foreach ($rows as $r) { if ((int)($r['p'] ?? 0) < 3) { $done = false; break; } }
        if ($done && count($rows) >= 2) {
            $cache[$g] = [1 => (string)$rows[0]['team'], 2 => (string)$rows[1]['team']];
        }
    }
    return ($cache[$g] !== null) ? $cache[$g][(int)$m[1]] : $raw;
}

/**
 * ko_thirds_map() — تعيين أصحاب المركز الثالث الـ8 لخانات دور الـ32 وفق **جدول FIFA
 * الرسمي 2026** (متحقَّق منه مزدوجاً: كل ثالث مجموعته ضمن مجموعات الخانة + يطابق الجدول
 * الرسمي المنشور). يُحدَّث يدويّاً إن غيّرت FIFA التعيين. (مفتاح = رمز الخانة كما في البيانات.)
 */
function ko_thirds_map(): array {
    return [
        '3A/B/C/D/F' => 'Paraguay',
        '3C/D/F/G/H' => 'Sweden',
        '3C/E/F/H/I' => 'Ecuador',
        '3E/H/I/J/K' => 'DR Congo',
        '3B/E/F/I/J' => 'Bosnia & Herzegovina',
        '3A/E/H/I/J' => 'Senegal',
        '3E/F/G/I/J' => 'Algeria',
        '3D/E/I/J/L' => 'Ghana',
    ];
}

/**
 * ko_resolve() — المُحلّل الشامل لعناصر الأدوار الإقصائية النائبة → المنتخب الفعلي:
 *   • «1X/2X» → أول/ثاني المجموعة (من الترتيب النهائي).
 *   • «3X/Y/Z» → الثالث المُعيَّن رسمياً (ko_thirds_map).
 *   • «WN» → الفائز من المباراة N (من نتيجتها؛ ركلات الترجيح تحسم التعادل) — يتقدّم
 *     الجدول تلقائياً دوراً بعد دور كلّما ظهرت النتائج. عودٌ آمن (الفائز قد يكون نائباً).
 * يبقى العنصر نائباً إن لم يُحسَم بعد. كاش (memo) يمنع العَود ويُسرّع.
 */
function ko_resolve(string $raw): string {
    static $memo = [];
    $raw = trim($raw);
    if ($raw === '') return $raw;
    if (isset($memo[$raw])) return $memo[$raw];
    $memo[$raw] = $raw;   // حماية من العَود اللانهائي قبل الحساب

    $r = ko_resolve_pos($raw);
    if ($r === $raw) $r = ko_thirds_map()[strtoupper($raw)] ?? $raw;
    if ($r === $raw && preg_match('/^W(\d+)$/i', $raw, $m) && class_exists('DataService')) {
        $match = DataService::matchByIndex((int)$m[1]);
        $ft = is_array($match) ? ($match['score']['ft'] ?? null) : null;
        if (is_array($ft) && isset($ft[0], $ft[1])) {
            $a = (int)$ft[0]; $b = (int)$ft[1]; $w = null;
            if ($a > $b)      $w = (string)($match['team1'] ?? '');
            elseif ($b > $a)  $w = (string)($match['team2'] ?? '');
            else {            // تعادل → ركلات الترجيح إن وُجدت
                $p = $match['score']['p'] ?? null;
                if (is_array($p) && isset($p[0], $p[1])) {
                    $w = ((int)$p[0] >= (int)$p[1]) ? (string)($match['team1'] ?? '') : (string)($match['team2'] ?? '');
                }
            }
            if ($w !== null && $w !== '') $r = ko_resolve($w);   // الفائز قد يكون نائباً بدوره
        }
    }
    return $memo[$raw] = $r;
}

/**
 * team_name() — يرجّع اسم المنتخب حسب اللغة الحالية.
 * يتعامل بذكاء مع رموز placeholder للأدوار الإقصائية مثل "W73" أو "1A".
 */
function team_name(string $raw): string {
    $raw = ko_resolve(trim($raw));   // «1L»→بطل · «3...»→الثالث الرسمي · «W77»→الفائز
    if ($raw === '') return t('tbd');

    // placeholder للأدوار الإقصائية: W73 = الفائز من المباراة 73
    if (preg_match('/^W(\d+)$/i', $raw, $m)) {
        return (current_lang() === 'ar')
            ? 'الفائز ' . $m[1]
            : 'Winner ' . $m[1];
    }
    if (preg_match('/^(RU|L)(\d+)$/i', $raw, $m)) {
        return (current_lang() === 'ar')
            ? 'الخاسر ' . $m[2]
            : 'Loser ' . $m[2];
    }
    // placeholder ثالث المجموعات: "3A/B/C/D/F" = أحد ثوالث هذه المجموعات
    if (preg_match('#^([0-9])([A-L](?:/[A-L])+)$#i', $raw, $m)) {
        return (current_lang() === 'ar')
            ? 'ثالث (' . strtoupper($m[2]) . ')'
            : '3rd (' . strtoupper($m[2]) . ')';
    }
    // placeholder للمجموعات: 1A = أول المجموعة A
    if (preg_match('/^([0-9])([A-L])$/i', $raw, $m)) {
        return (current_lang() === 'ar')
            ? 'المركز ' . $m[1] . ' - ' . $m[2]
            : $m[1] . $m[2];
    }

    $map = teams_map();
    if (isset($map[$raw])) {
        return (current_lang() === 'ar') ? $map[$raw][0] : $raw;
    }
    return $raw; // غير معروف: نعرضه كما هو
}

/**
 * team_flag() — يرجّع رمز ISO للعلم، أو '' إذا كان placeholder.
 */
function team_flag(string $raw): string {
    $raw = ko_resolve(trim($raw));   // «1L»/«3...»/«W77» → علم المنتخب الفعلي
    $map = teams_map();
    return $map[$raw][1] ?? '';
}

/**
 * flag_url() — رابط صورة العلم من CDN مجاني.
 */
function flag_url(string $raw, string $size = 'w80'): string {
    $code = team_flag($raw);
    if ($code === '') return '';
    return 'https://flagcdn.com/' . $size . '/' . strtolower($code) . '.png';
}

/** هل هذا الاسم منتخب حقيقي (وليس placeholder)؟ */
function is_real_team(string $raw): bool {
    return team_flag(trim($raw)) !== '';
}

/**
 * fifa_iso() — رمز FIFA الثلاثي (MEX, RSA, SUI…) → رمز العلم ISO المستعمل في الموقع.
 * يُستخدم لربط تقارير FIFA (اسم ملفها يحمل رموز الفرق) بالمباراة الصحيحة بدل
 * الترتيب الزمني (الذي يخلط مباريات نفس اليوم). يعيد '' لرمز غير معروف.
 */
function fifa_iso(string $code3): string {
    static $m = [
        'MEX'=>'mx','USA'=>'us','CAN'=>'ca',
        'ESP'=>'es','FRA'=>'fr','ENG'=>'gb-eng','GER'=>'de','POR'=>'pt','NED'=>'nl','BEL'=>'be',
        'CRO'=>'hr','ITA'=>'it','SUI'=>'ch','AUT'=>'at','NOR'=>'no','SCO'=>'gb-sct','CZE'=>'cz',
        'DEN'=>'dk','POL'=>'pl','TUR'=>'tr','SWE'=>'se','UKR'=>'ua','SRB'=>'rs','WAL'=>'gb-wls',
        'SVK'=>'sk','HUN'=>'hu','BIH'=>'ba','IRL'=>'ie',
        'BRA'=>'br','ARG'=>'ar','URU'=>'uy','COL'=>'co','ECU'=>'ec','PAR'=>'py','PER'=>'pe',
        'CHI'=>'cl','VEN'=>'ve','BOL'=>'bo',
        'MAR'=>'ma','SEN'=>'sn','EGY'=>'eg','TUN'=>'tn','ALG'=>'dz','GHA'=>'gh','NGA'=>'ng',
        'CMR'=>'cm','CIV'=>'ci','RSA'=>'za','CPV'=>'cv','MLI'=>'ml','COD'=>'cd',
        'JPN'=>'jp','KOR'=>'kr','IRN'=>'ir','KSA'=>'sa','AUS'=>'au','QAT'=>'qa','IRQ'=>'iq',
        'JOR'=>'jo','UZB'=>'uz','UAE'=>'ae',
        'PAN'=>'pa','CRC'=>'cr','HON'=>'hn','JAM'=>'jm','HAI'=>'ht','CUW'=>'cw',
        'NZL'=>'nz',
    ];
    return $m[strtoupper(trim($code3))] ?? '';
}
