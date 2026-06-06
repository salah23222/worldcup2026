<?php
/**
 * PageCache.php — كاش صفحات كامل (full-page micro-cache).
 * ============================================================
 * يخدم الزوّار غير المسجّلين والزواحف من نسخة HTML جاهزة (دون تشغيل PHP/قاعدة
 * بيانات) → يحرّر عمّال الخادم ويمنع 504 تحت الضغط الكثيف.
 *
 * قواعد الأمان:
 *   - GET فقط، ولا يُخزَّن أي مستخدم له كوكي جلسة (محتوى شخصي).
 *   - الصفحات الديناميكية/الشخصية مستثناة (توقعات/دخول/لوحة/...).
 *   - TTL قصير (PAGE_CACHE_TTL) فتبقى النتائج محدّثة.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class PageCache
{
    private static ?string $file = null;

    /** يُستدعى في نهاية bootstrap: يخدم من الكاش أو يبدأ التقاط الإخراج لحفظه. */
    public static function begin(): void
    {
        $ttl = defined('PAGE_CACHE_TTL') ? (int)PAGE_CACHE_TTL : 0;
        if ($ttl <= 0 || !self::cacheable()) return;

        $file = self::file();

        // إصابة: قدّم النسخة المخزّنة فوراً بلا أي تشغيل للصفحة.
        if (is_file($file) && (time() - filemtime($file) < $ttl)) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: text/html; charset=utf-8');
            header('X-Page-Cache: HIT');
            readfile($file);
            exit;
        }

        // إخفاق: التقط إخراج الصفحة واحفظه عند الانتهاء.
        self::$file = $file;
        @header('X-Page-Cache: MISS');
        ob_start([self::class, 'finish']);
    }

    /** رد نداء التخزين: يحفظ صفحة سليمة فقط (200 + HTML كامل). */
    public static function finish(string $buf): string
    {
        $f = self::$file;
        if ($f !== null && strlen($buf) > 300 && stripos($buf, '</html>') !== false) {
            $code = function_exists('http_response_code') ? (int)http_response_code() : 200;
            if ($code === 0 || $code === 200) {
                $dir = dirname($f);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                if (@file_put_contents($f . '.tmp', $buf) !== false) {
                    @rename($f . '.tmp', $f);
                }
            }
        }
        return $buf;   // أرسل للمتصفّح كالمعتاد
    }

    /** هل هذا الطلب قابل للتخزين المؤقت؟ */
    private static function cacheable(): bool
    {
        if (PHP_SAPI === 'cli') return false;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return false;

        // مستخدم له كوكي جلسة → محتوى شخصي (نِك/دخول)، لا تخزين.
        $sess = function_exists('session_name') ? session_name() : 'PHPSESSID';
        if (!empty($_COOKIE[$sess])) return false;

        // صفحات ديناميكية/شخصية/خاصة لا تُخزَّن.
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        static $noCache = [
            'predict.php', 'leaderboard.php', 'trivia.php',
            'login.php', 'register.php', 'logout.php', 'forgot.php', 'reset.php', 'promote.php',
            'admin.php', 'health.php', 'install.php', 'db_selftest.php',
            'card.php', 'card_img.php', 'calendar.php', 'manifest.php', 'sitemap.php', 'print.php',
            'widget.php', 'embed.php', 'stickers.php', 'unsubscribe.php',
            // ميزات جديدة شخصية/ديناميكية — لا تُخزَّن كصفحة كاملة
            'leagues.php', 'league.php', 'today.php',
        ];
        return !in_array($script, $noCache, true);
    }

    private static function file(): string
    {
        $lang = function_exists('current_lang') ? current_lang() : 'ar';
        $key  = ($_SERVER['REQUEST_URI'] ?? '/') . '|' . $lang;
        return rtrim(CACHE_DIR, '/') . '/page/' . sha1($key) . '.html';
    }
}
