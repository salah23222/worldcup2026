<?php
/**
 * RateLimiter.php — حدّ المحاولات (ملفّي، بسيط، بلا قاعدة بيانات).
 * يُستخدم لحماية الدخول/التسجيل من التخمين والإساءة.
 *
 * المفهوم: عدّاد لكل مفتاح ضمن نافذة زمنية. إن بلغ الحد → محظور مؤقتاً.
 */
if (!defined('WC2026')) { exit('Access denied'); }

class RateLimiter
{
    private static function dir(): string
    {
        $d = rtrim(CACHE_DIR, '/') . '/ratelimit';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    private static function file(string $key): string
    {
        return self::dir() . '/' . sha1($key) . '.json';
    }

    /**
     * عنوان IP الزائر. خلف CDN/وكيل (Hostinger hcdn/Cloudflare) قد يكون REMOTE_ADDR
     * هو عنوان الحافة لا الزائر — فيُجمَّع كل المستخدمين في عدّاد واحد. لذا نأخذ أوّل
     * عنوان «عام» صالح من ترويسات التوجيه إن وُجد (الزائر الأصلي)، وإلا REMOTE_ADDR.
     * ملاحظة: ترويسات التوجيه قابلة للتزوير نظرياً؛ لكن حدّ اسم المستخدم في Auth يبقى
     * خط الدفاع الأساسي ضد التخمين الموجّه، وهذا تحسين لدقّة حدّ الـIP لا بديل عنه.
     */
    public static function ip(): string
    {
        $candidates = [];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $p) {
                $candidates[] = trim($p);
            }
        }
        foreach ($candidates as $ip) {
            // أوّل عنوان عام صالح = الزائر الأصلي (نتجاوز عناوين الحافة الخاصة/المحجوزة)
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }

    /** هل تجاوز المفتاح الحدّ المسموح ضمن النافذة؟ */
    public static function blocked(string $key, int $max, int $window): bool
    {
        $f = self::file($key);
        if (!is_file($f)) { return false; }
        $d = json_decode((string)@file_get_contents($f), true);
        if (!is_array($d)) { return false; }
        if (($d['exp'] ?? 0) < time()) { return false; }   // انتهت النافذة
        return (int)($d['c'] ?? 0) >= $max;
    }

    /** يسجّل محاولة واحدة على المفتاح (مع قفل لتفادي التسابق). */
    public static function hit(string $key, int $window): void
    {
        $f  = self::file($key);
        $fp = @fopen($f, 'c+b');
        if (!$fp) { return; }
        @flock($fp, LOCK_EX);
        rewind($fp);
        $d = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($d) || ($d['exp'] ?? 0) < time()) {
            $d = ['c' => 0, 'exp' => time() + $window];
        }
        $d['c'] = (int)($d['c'] ?? 0) + 1;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($d));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
    }

    /** يصفّر العدّاد (يُستدعى بعد نجاح الدخول). */
    public static function reset(string $key): void
    {
        $f = self::file($key);
        if (is_file($f)) { @unlink($f); }
    }
}
