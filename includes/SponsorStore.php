<?php
if (!defined('WC2026')) { exit('Access denied'); }

/**
 * SponsorStore — تخزين الرعاة في data/sponsors.json (بدون قاعدة بيانات).
 * دفاعي بالكامل: لا يرمي استثناءات، ويرجع قيماً آمنة عند أي فشل.
 */
class SponsorStore
{
    /** المسار الكامل لملف الرعاة. */
    private static function path(): string
    {
        return rtrim(CACHE_DIR, '/') . '/../data/sponsors.json';
    }

    /** يطبّع عنصراً واحداً إلى المفاتيح name/url/logo كنصوص. */
    private static function normalize($item): array
    {
        if (!is_array($item)) { $item = []; }
        return [
            'name' => (string)($item['name'] ?? ''),
            'url'  => (string)($item['url']  ?? ''),
            'logo' => (string)($item['logo'] ?? ''),
        ];
    }

    /** القائمة المخزّنة في الملف، أو [] إذا غاب الملف أو كان غير صالح. */
    public static function file(): array
    {
        $path = self::path();
        if (!is_file($path)) { return []; }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') { return []; }
        $data = json_decode($raw, true);
        if (!is_array($data)) { return []; }
        $out = [];
        foreach ($data as $item) {
            $out[] = self::normalize($item);
        }
        return $out;
    }

    /** كل الرعاة: من الملف إن وُجدوا، وإلا من ثابت SPONSORS في الإعدادات. */
    public static function all(): array
    {
        $list = self::file();
        if (!is_array($list) || count($list) === 0) {
            $list = defined('SPONSORS') ? SPONSORS : [];
        }
        if (!is_array($list)) { $list = []; }
        $out = [];
        foreach ($list as $item) {
            $out[] = self::normalize($item);
        }
        return $out;
    }

    /** يحفظ القائمة في الملف بشكل ذرّي (path.tmp ثم rename). */
    public static function save(array $list): bool
    {
        $clean = [];
        foreach ($list as $item) {
            if (!is_array($item)) { $item = []; }
            $clean[] = [
                'name' => (string)trim((string)($item['name'] ?? '')),
                'url'  => (string)trim((string)($item['url']  ?? '')),
                'logo' => (string)trim((string)($item['logo'] ?? '')),
            ];
        }

        $path = self::path();
        $dir  = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

        $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) { return false; }

        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json) === false) { return false; }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    /** يضيف راعياً جديداً. الاسم مطلوب. */
    public static function add(string $name, string $url, string $logo): bool
    {
        $name = trim($name);
        if ($name === '') { return false; }

        $list = self::file();
        if (count($list) === 0) { $list = self::all(); }

        $list[] = ['name' => $name, 'url' => $url, 'logo' => $logo];
        return self::save($list);
    }

    /** يحذف الراعي عند الفهرس المحدّد. */
    public static function removeAt(int $i): bool
    {
        $list = self::file();
        if (count($list) === 0) { $list = self::all(); }

        if (!array_key_exists($i, $list)) { return false; }
        array_splice($list, $i, 1);
        return self::save($list);
    }
}
