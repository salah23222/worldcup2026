<?php
/**
 * Rankings.php — تصنيف الفيفا للمنتخبات.
 * المصدر: data/rankings.json (قابل للتحديث يدوياً من fifa.com).
 * حقائق عامة — لا كشط لموقع الفيفا.
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Rankings
{
    private static ?array $map = null;

    private static function load(): array
    {
        if (self::$map !== null) return self::$map;
        $f = rtrim(CACHE_DIR, '/') . '/../data/rankings.json';
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : [];
        self::$map = is_array($d) ? $d : [];
        return self::$map;
    }

    /** ترتيب المنتخب في تصنيف الفيفا، أو null إن لم يكن معروفاً */
    public static function of(string $team): ?int
    {
        $team = trim($team);
        $m = self::load();
        return (isset($m[$team]) && is_int($m[$team]) && $m[$team] > 0) ? $m[$team] : null;
    }
}
