<?php
/**
 * countries.php — قائمة الدول لاختيارها في التسجيل.
 * المفتاح = رمز ISO ثنائي (يُخزّن في القاعدة)، القيمة = [عربي, إنجليزي].
 * تشمل كل الدول العربية + كل منتخبات مونديال 2026 + دولاً رئيسية.
 */
if (!defined('WC2026')) { exit('Access denied'); }

function countries(): array {
    return [
        // ===== الدول العربية =====
        'SA' => ['السعودية', 'Saudi Arabia'],
        'AE' => ['الإمارات', 'United Arab Emirates'],
        'QA' => ['قطر', 'Qatar'],
        'KW' => ['الكويت', 'Kuwait'],
        'BH' => ['البحرين', 'Bahrain'],
        'OM' => ['عُمان', 'Oman'],
        'YE' => ['اليمن', 'Yemen'],
        'IQ' => ['العراق', 'Iraq'],
        'JO' => ['الأردن', 'Jordan'],
        'SY' => ['سوريا', 'Syria'],
        'LB' => ['لبنان', 'Lebanon'],
        'PS' => ['فلسطين', 'Palestine'],
        'EG' => ['مصر', 'Egypt'],
        'SD' => ['السودان', 'Sudan'],
        'LY' => ['ليبيا', 'Libya'],
        'TN' => ['تونس', 'Tunisia'],
        'DZ' => ['الجزائر', 'Algeria'],
        'MA' => ['المغرب', 'Morocco'],
        'MR' => ['موريتانيا', 'Mauritania'],
        'SO' => ['الصومال', 'Somalia'],
        'DJ' => ['جيبوتي', 'Djibouti'],
        'KM' => ['جزر القمر', 'Comoros'],
        // ===== دول أخرى رئيسية / منتخبات مونديالية =====
        'US' => ['الولايات المتحدة', 'United States'],
        'CA' => ['كندا', 'Canada'],
        'MX' => ['المكسيك', 'Mexico'],
        'BR' => ['البرازيل', 'Brazil'],
        'AR' => ['الأرجنتين', 'Argentina'],
        'UY' => ['الأوروغواي', 'Uruguay'],
        'CO' => ['كولومبيا', 'Colombia'],
        'EC' => ['الإكوادور', 'Ecuador'],
        'PY' => ['الباراغواي', 'Paraguay'],
        'CL' => ['تشيلي', 'Chile'],
        'PE' => ['بيرو', 'Peru'],
        'VE' => ['فنزويلا', 'Venezuela'],
        'GB' => ['المملكة المتحدة', 'United Kingdom'],
        'FR' => ['فرنسا', 'France'],
        'ES' => ['إسبانيا', 'Spain'],
        'DE' => ['ألمانيا', 'Germany'],
        'IT' => ['إيطاليا', 'Italy'],
        'PT' => ['البرتغال', 'Portugal'],
        'NL' => ['هولندا', 'Netherlands'],
        'BE' => ['بلجيكا', 'Belgium'],
        'HR' => ['كرواتيا', 'Croatia'],
        'CH' => ['سويسرا', 'Switzerland'],
        'AT' => ['النمسا', 'Austria'],
        'NO' => ['النرويج', 'Norway'],
        'SE' => ['السويد', 'Sweden'],
        'DK' => ['الدنمارك', 'Denmark'],
        'PL' => ['بولندا', 'Poland'],
        'UA' => ['أوكرانيا', 'Ukraine'],
        'RS' => ['صربيا', 'Serbia'],
        'TR' => ['تركيا', 'Türkiye'],
        'RU' => ['روسيا', 'Russia'],
        'GR' => ['اليونان', 'Greece'],
        'IR' => ['إيران', 'Iran'],
        'JP' => ['اليابان', 'Japan'],
        'KR' => ['كوريا الجنوبية', 'South Korea'],
        'CN' => ['الصين', 'China'],
        'IN' => ['الهند', 'India'],
        'PK' => ['باكستان', 'Pakistan'],
        'ID' => ['إندونيسيا', 'Indonesia'],
        'AU' => ['أستراليا', 'Australia'],
        'NZ' => ['نيوزيلندا', 'New Zealand'],
        'UZ' => ['أوزبكستان', 'Uzbekistan'],
        'NG' => ['نيجيريا', 'Nigeria'],
        'GH' => ['غانا', 'Ghana'],
        'SN' => ['السنغال', 'Senegal'],
        'CM' => ['الكاميرون', 'Cameroon'],
        'CI' => ['ساحل العاج', 'Ivory Coast'],
        'ZA' => ['جنوب أفريقيا', 'South Africa'],
        'CV' => ['الرأس الأخضر', 'Cape Verde'],
        'CD' => ['الكونغو الديمقراطية', 'DR Congo'],
        'CR' => ['كوستاريكا', 'Costa Rica'],
        'PA' => ['بنما', 'Panama'],
        'HT' => ['هايتي', 'Haiti'],
        'JM' => ['جامايكا', 'Jamaica'],
        'OT' => ['أخرى', 'Other'],
    ];
}

/** هل رمز الدولة صالح؟ */
function is_valid_country(string $code): bool {
    return isset(countries()[strtoupper($code)]);
}

/** اسم الدولة المعروض حسب اللغة، أو الرمز إن لم يوجد. */
function country_name(string $code): string {
    $code = strtoupper($code);
    $c = countries();
    if (!isset($c[$code])) return $code;
    return (current_lang() === 'ar') ? $c[$code][0] : $c[$code][1];
}
