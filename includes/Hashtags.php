<?php
/**
 * Hashtags.php — مولّد هاشتاكات ذكي لكل أنواع التغريدات.
 * ============================================================
 * يجمع 4 أبعاد:
 *   1) أساس قصير  : #كأس_العالم_2026 #FIFAWorldCup26 (يبقى دائماً)
 *   2) فريق محدّد : #الأرجنتين #فرنسا (لمباريات بعينها)
 *   3) مضيف الملعب: #كندا أو #المكسيك أو #USA حسب الملعب
 *   4) مرحلة/فترة: #دور_المجموعات / #مباريات_اليوم / #تحدّي_المعرفة …
 *
 * النتيجة: تغريدات تتصدّر ترندات متعدّدة (محلية + دولية + لكل منتخب).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Hashtags
{
    /** الأساس القصير (هاشتاكان فقط) لتوفير مساحة للتفاصيل. */
    public static function core(): string
    {
        return defined('X_HASHTAGS_CORE') && X_HASHTAGS_CORE !== ''
            ? X_HASHTAGS_CORE
            : '#كأس_العالم_2026 #FIFAWorldCup26';
    }

    /** الأساس الكامل (4 هاشتاكات) — للتغريدات اليوميّة. */
    public static function full(): string
    {
        return defined('X_HASHTAGS') && X_HASHTAGS !== ''
            ? X_HASHTAGS
            : '#كأس_العالم_2026 #المونديال #WeAre26 #FIFAWorldCup26';
    }

    /** هاشتاك منتخب: 'Argentina' → '#الأرجنتين'. null لو placeholder. */
    public static function team(string $teamEn): ?string
    {
        $teamEn = trim($teamEn);
        if ($teamEn === '') return null;
        if (function_exists('is_real_team') && !is_real_team($teamEn)) return null;
        if (!function_exists('teams_map')) return null;
        $map = teams_map();
        if (!isset($map[$teamEn][0])) return null;
        $ar = (string)$map[$teamEn][0];
        // استبدل المسافات بـ _ ، احذف أيّ أحرف غير مقبولة
        $clean = preg_replace('/\s+/u', '_', trim($ar));
        $clean = preg_replace('/[^\p{L}\p{N}_]/u', '', $clean);
        return $clean !== '' ? '#' . $clean : null;
    }

    /** هاشتاك البلد المضيف من اسم الملعب/المدينة. */
    public static function hostCountry(string $ground): ?string
    {
        $g = strtolower(trim($ground));
        if ($g === '') return null;

        // المدن المستضيفة (FIFA 2026 الرسميّة)
        $mexico = ['mexico city', 'guadalajara', 'monterrey', 'azteca', 'akron'];
        $canada = ['toronto', 'vancouver', 'edmonton'];
        $usa    = [
            'new york', 'new jersey', 'east rutherford', 'metlife',
            'los angeles', 'inglewood', 'sofi',
            'dallas', 'arlington', 'at&t',
            'houston', 'nrg',
            'atlanta', 'mercedes-benz',
            'boston', 'foxborough', 'gillette',
            'kansas city', 'arrowhead',
            'miami', 'hard rock',
            'philadelphia', 'lincoln',
            'san francisco', 'bay area', 'levi',
            'seattle', 'lumen',
        ];
        foreach ($mexico as $c) if (strpos($g, $c) !== false) return '#المكسيك';
        foreach ($canada as $c) if (strpos($g, $c) !== false) return '#كندا';
        foreach ($usa    as $c) if (strpos($g, $c) !== false) return '#USA';
        return null;
    }

    /** هاشتاك خاصّ بكل فترة يوميّة. */
    public static function dailySlot(string $slot): ?string
    {
        // هاشتاكان لكل فترة: عربيّ (وصول محلي) + إنجليزيّ (وصول عالمي)
        static $map = [
            'recap'     => '#صباح_المونديال #WorldCupMorning',
            'news'      => '#أخبار_المونديال #WorldCupNews',
            'countdown' => '#عدّ_تنازلي_المونديال #WorldCupCountdown',
            'morning'   => '#مباريات_اليوم #MatchDay',
            'trivia'    => '#تحدّي_المعرفة #WorldCupTrivia',
            'stats'     => '#إحصائيات_المونديال #WorldCupStats',
            'evening'   => '#نتائج_اليوم #WorldCupResults',
        ];
        return $map[$slot] ?? null;
    }

    /** هاشتاك مرحلة البطولة الحاليّة. */
    public static function phase(): ?string
    {
        if (!class_exists('DataService')) return null;
        if (!DataService::tournamentStarted()) return '#قبل_انطلاق_المونديال';
        $finished = 0;
        foreach (DataService::allMatches() as $m) {
            if (($m['_status'] ?? '') === 'finished') $finished++;
        }
        if ($finished < 72)  return '#دور_المجموعات';
        if ($finished < 88)  return '#دور_الـ32';
        if ($finished < 96)  return '#دور_الـ16';
        if ($finished < 100) return '#ربع_النهائي';
        if ($finished < 102) return '#نصف_النهائي';
        return '#نهائي_المونديال';
    }

    /** هاشتاك حرف المجموعة: 'Group A' → '#مجموعة_A'. */
    public static function group(string $group): ?string
    {
        $letter = preg_replace('/^Group\s+/', '', trim($group));
        if ($letter === '') return null;
        return '#مجموعة_' . strtoupper($letter);
    }

    // ════════════════════ مُجمِّعات جاهزة ════════════════════

    /** هاشتاكات تغريدة مباراة (قبل/بعد): فريقَين + مضيف + أساس قصير. */
    public static function forMatch(array $m): string
    {
        $tags = [];
        if ($t1 = self::team((string)($m['team1'] ?? '')))         $tags[] = $t1;
        if ($t2 = self::team((string)($m['team2'] ?? '')))         $tags[] = $t2;
        if ($host = self::hostCountry((string)($m['ground'] ?? ''))) {
            // تجنّب التكرار: لو المضيف هو نفسه أحد الفريقَين، تخطّه
            if (!in_array($host, $tags, true)) $tags[] = $host;
        }
        // أساس قصير (هاشتاكان) — يترك مساحة للنتيجة والاسم
        $tags[] = '#كأس_العالم_2026';
        $tags[] = '#FIFAWorldCup26';
        return implode(' ', array_unique($tags));
    }

    /** هاشتاكات فترة يوميّة: مرحلة + فترة + أساس عالمي (5-6 هاشتاكات). */
    public static function forDailySlot(string $slot): string
    {
        $tags = [];
        if ($ph = self::phase())          $tags[] = $ph;
        if ($st = self::dailySlot($slot)) $tags[] = $st;
        // أساس مختلط — وصول عربي + عالمي
        $tags[] = '#كأس_العالم_2026';
        $tags[] = '#FIFAWorldCup26';
        $tags[] = '#WorldCup2026';   // 🌍 ترند عالمي قويّ
        return implode(' ', array_unique($tags));
    }

    /** هاشتاكات ترتيب مجموعة: حرف المجموعة + أساس كامل. */
    public static function forGroup(string $group): string
    {
        $tags = [];
        if ($g = self::group($group)) $tags[] = $g;
        $tags[] = '#كأس_العالم_2026';
        $tags[] = '#المونديال';
        $tags[] = '#FIFAWorldCup26';
        return implode(' ', array_unique($tags));
    }
}
