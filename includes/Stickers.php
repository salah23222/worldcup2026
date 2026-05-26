<?php
/**
 * Stickers.php
 * ============================================================
 * مجموعة ملصقات ألبوم المونديال (نمط Panini الافتراضي):
 *   - 48 منتخباً (أعلام)            set=teams
 *   - 16 مدينة مضيفة (علم المضيف)   set=cities
 *   - ملصقات خاصة (إيموجي)          set=special
 * كل ملصق له ندرة: common / rare / legendary (تؤثّر في احتمال الظهور).
 * جمع الملصقات يُحفظ في متصفّح الزائر (localStorage) — لا يحتاج حساباً.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Stickers
{
    /** منتخبات أسطورية (ندرة أعلى) */
    private const LEGENDARY = [
        'Brazil', 'Argentina', 'France', 'Germany', 'Spain',
        'England', 'Portugal', 'Netherlands', 'Italy',
    ];

    /** المدن المضيفة الـ16: [اسم عربي, اسم إنجليزي, رمز علم المضيف] */
    private const CITIES = [
        ['فانكوفر', 'Vancouver', 'ca'],
        ['تورنتو', 'Toronto', 'ca'],
        ['سياتل', 'Seattle', 'us'],
        ['خليج سان فرانسيسكو', 'SF Bay Area', 'us'],
        ['لوس أنجلوس', 'Los Angeles', 'us'],
        ['كانساس سيتي', 'Kansas City', 'us'],
        ['دالاس', 'Dallas', 'us'],
        ['أتلانتا', 'Atlanta', 'us'],
        ['هيوستن', 'Houston', 'us'],
        ['بوسطن', 'Boston', 'us'],
        ['فيلادلفيا', 'Philadelphia', 'us'],
        ['ميامي', 'Miami', 'us'],
        ['نيويورك / نيوجيرسي', 'New York/New Jersey', 'us'],
        ['غوادالاخارا', 'Guadalajara', 'mx'],
        ['مكسيكو سيتي', 'Mexico City', 'mx'],
        ['مونتيري', 'Monterrey', 'mx'],
    ];

    /** ملصقات خاصة: [id, عربي, إنجليزي, إيموجي, ندرة] */
    private const SPECIAL = [
        ['sp_trophy', 'كأس العالم', 'World Cup Trophy', '🏆', 'legendary'],
        ['sp_ball',   'الكرة الرسمية', 'Official Ball', '⚽', 'rare'],
        ['sp_mascot', 'تميمة البطولة', 'Mascot', '🦅', 'rare'],
        ['sp_pitch',  'حفل الافتتاح', 'Opening Ceremony', '🎉', 'legendary'],
        ['sp_boot',   'الحذاء الذهبي', 'Golden Boot', '👟', 'rare'],
        ['sp_glove',  'القفاز الذهبي', 'Golden Glove', '🧤', 'common'],
    ];

    /** كل الملصقات: id, name_ar, name_en, img, emoji, rarity, set */
    public static function all(): array
    {
        $out = [];

        // 1) المنتخبات المشاركة (من البيانات الفعلية)
        $map = teams_map();
        foreach (array_keys(DataService::allTeams()) as $team) {
            $code = team_flag($team);
            if ($code === '') continue;
            $out[] = [
                'id'      => 'tm_' . strtolower(preg_replace('/[^a-z0-9]/i', '', $team)),
                'name_ar' => $map[$team][0] ?? $team,
                'name_en' => $team,
                'img'     => flag_url($team, 'w160'),
                'emoji'   => '',
                'rarity'  => in_array($team, self::LEGENDARY, true) ? 'legendary' : 'rare',
                'set'     => 'teams',
            ];
        }

        // 2) المدن المضيفة
        foreach (self::CITIES as $i => [$ar, $en, $host]) {
            $out[] = [
                'id'      => 'ct_' . $i,
                'name_ar' => $ar,
                'name_en' => $en,
                'img'     => 'https://flagcdn.com/w160/' . $host . '.png',
                'emoji'   => '',
                'rarity'  => 'common',
                'set'     => 'cities',
            ];
        }

        // 3) ملصقات خاصة
        foreach (self::SPECIAL as [$id, $ar, $en, $emoji, $rarity]) {
            $out[] = [
                'id'      => $id,
                'name_ar' => $ar,
                'name_en' => $en,
                'img'     => '',
                'emoji'   => $emoji,
                'rarity'  => $rarity,
                'set'     => 'special',
            ];
        }

        return $out;
    }

    /** ملصقات مجموعة محددة */
    public static function inSet(string $set): array
    {
        return array_values(array_filter(self::all(), fn($s) => $s['set'] === $set));
    }

    /** المجموعات بترتيب العرض */
    public static function sets(): array
    {
        return ['teams', 'cities', 'special'];
    }

    /** العدد الكلي للملصقات */
    public static function total(): int
    {
        return count(self::all());
    }
}
