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
 * team_name() — يرجّع اسم المنتخب حسب اللغة الحالية.
 * يتعامل بذكاء مع رموز placeholder للأدوار الإقصائية مثل "W73" أو "1A".
 */
function team_name(string $raw): string {
    $raw = trim($raw);
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
    $raw = trim($raw);
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
