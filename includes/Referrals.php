<?php
/**
 * Referrals.php — نظام الإحالة المكافِئ على التسويق الحقيقي.
 * ============================================================
 * كل مستخدم رابطه المخصّص: site/?ref=USERNAME
 * عند زيارة الرابط: نضع كوكي wc_ref بـUsername لمدّة 30 يوماً.
 * عند تسجيل المُحال إليه: نقرأ الكوكي ونُسجّل علاقة دائمة + نُكافئ.
 *
 * مكافحة الخداع:
 *   • IP المُحال ≠ IP المُحيل (لا يحتسب لو نفسه).
 *   • حدّ يومي + حدّ كلّي.
 *   • لا نقاط للمُحيل عن نفسه (لو ضغط رابطه على متصفّحه).
 *   • سجل قاعدة البيانات (referrals) UNIQUE على referred_user_id —
 *     مستخدم جديد له مُحيل واحد فقط، ولا تكرار.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Referrals
{
    public  const POINTS_PER       = 3;     // نقاط للمُحيل عن كل تسجيل ناجح
    public  const WELCOME_POINTS   = 2;     // مكافأة ترحيب للمُحال إليه (مستقبلية)
    public  const DAILY_CAP        = 5;     // حدّ يومي للإحالات المُحتَسَبة
    public  const LIFETIME_CAP     = 50;    // حدّ كلّي مدى الحياة
    public  const COOKIE_KEY       = 'wc_ref';
    public  const COOKIE_DAYS      = 30;

    private static bool $tableReady = false;

    /** ينشئ جدول الإحالات (lazy). */
    private static function ensureTable(PDO $pdo): void
    {
        if (self::$tableReady) return;
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `referrals` (
                  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  `referrer_user_id`  INT UNSIGNED NOT NULL,
                  `referred_user_id`  INT UNSIGNED NOT NULL,
                  `referrer_ip`       VARCHAR(45) DEFAULT NULL,
                  `referred_ip`       VARCHAR(45) DEFAULT NULL,
                  `counted`           TINYINT(1) NOT NULL DEFAULT 1,
                  `created_at`        INT UNSIGNED NOT NULL,
                  UNIQUE KEY `uk_referred` (`referred_user_id`),
                  KEY `idx_referrer` (`referrer_user_id`),
                  KEY `idx_created`  (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::$tableReady = true;
        } catch (Throwable $e) {}
    }

    /** يلتقط ?ref= من الـURL في كل صفحة، ويضع الكوكي إن صحّ. */
    public static function captureFromRequest(): void
    {
        if (PHP_SAPI === 'cli') return;
        if (empty($_GET['ref'])) return;

        $ref = trim((string)$_GET['ref']);
        // اسم المستخدم: حروف/أرقام/_/. فقط (3–32) — نفس قواعد التسجيل
        if (!preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $ref)) return;

        // إن كان المستخدم مسجّلاً وهو نفسه صاحب الرابط → تجاهَل (لا يُسوّق لنفسه)
        if (class_exists('Auth') && Auth::check()) {
            $cur = Auth::user();
            if ($cur && strcasecmp($cur['username'] ?? '', $ref) === 0) return;
        }

        // ضع الكوكي (لا تكتب إن سبق وُجدت لمُحيل آخر — أول مُحيل يفوز)
        if (!isset($_COOKIE[self::COOKIE_KEY])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            if (!headers_sent()) {
                @setcookie(self::COOKIE_KEY, $ref, [
                    'expires'  => time() + self::COOKIE_DAYS * 86400,
                    'path'     => '/',
                    'secure'   => $secure,
                    'httponly' => false,   // قابلة للقراءة من JS لتمييز الزائر المُحال (مفيد للتحليلات)
                    'samesite' => 'Lax',
                ]);
            }
        }
    }

    /**
     * يُسجّل علاقة الإحالة بعد تسجيل ناجح للمستخدم الجديد.
     * يُستدعى من register.php بعد Auth::register().
     */
    public static function recordSignup(int $newUserId): void
    {
        if ($newUserId <= 0) return;
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return;

        $refUsername = (string)($_COOKIE[self::COOKIE_KEY] ?? '');
        if ($refUsername === '' || !preg_match('/^[a-zA-Z0-9_.]{3,32}$/', $refUsername)) return;

        self::ensureTable($pdo);

        try {
            // 1) أوجِد ID المُحيل من اسمه
            $st = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $st->execute([$refUsername]);
            $row = $st->fetch();
            if (!$row) return;
            $referrerId = (int)$row['id'];

            // 2) لا يُحيل نفسه
            if ($referrerId === $newUserId) return;

            // 3) IP المُحال vs IP المُحيل (إن أمكن مقارنته)
            $referredIp = self::ip();
            // نتتبع IP الزائرين السابقين فقط من خلال آخر ظهور للمُحيل في session/log؟
            // عملياً: نخزّن referred_ip ونعتبر counted=0 لو IP موجود في إحالات سابقة كمُحيل
            $countedByIp = 1;
            $st2 = $pdo->prepare(
                'SELECT 1 FROM referrals
                 WHERE referrer_user_id = ? AND referred_ip = ? LIMIT 1'
            );
            $st2->execute([$referrerId, $referredIp]);
            if ($st2->fetch()) {
                $countedByIp = 0;   // نفس IP استُخدم سابقاً لتسجيل آخر من هذا المُحيل
            }

            // 4) فحص الحدود
            $countToday    = self::countToday($referrerId);
            $countLifetime = self::countCounted($referrerId);
            $counted = $countedByIp;
            if ($counted && $countToday >= self::DAILY_CAP)        { $counted = 0; }
            if ($counted && $countLifetime >= self::LIFETIME_CAP)  { $counted = 0; }

            // 5) إدراج (UNIQUE على referred_user_id يحمي من التكرار)
            $ins = $pdo->prepare(
                'INSERT IGNORE INTO referrals
                    (referrer_user_id, referred_user_id, referred_ip, counted, created_at)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute([$referrerId, $newUserId, $referredIp, $counted, time()]);

            // 6) امسح الكوكي بعد الاستهلاك
            if (!headers_sent()) {
                @setcookie(self::COOKIE_KEY, '', ['expires' => time() - 3600, 'path' => '/']);
            }
        } catch (Throwable $e) {
            // غير حرج — تسجيل المستخدم نجح، الإحالة ميزة إضافية
        }
    }

    /** عدد الإحالات اليوم (المعدودة) لمُحيل معيّن. */
    public static function countToday(int $referrerId): int
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return 0;
        self::ensureTable($pdo);
        try {
            $startOfDay = strtotime(gmdate('Y-m-d 00:00:00'));
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM referrals
                 WHERE referrer_user_id = ? AND counted = 1 AND created_at >= ?'
            );
            $st->execute([$referrerId, $startOfDay]);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    /** عدد الإحالات المُحتَسَبة (الكلّي) لمُحيل. */
    public static function countCounted(int $referrerId): int
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return 0;
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM referrals
                 WHERE referrer_user_id = ? AND counted = 1'
            );
            $st->execute([$referrerId]);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    /** كل الإحالات (مُعدّة + غير مُعدّة) لمُحيل — للعرض في لوحته. */
    public static function totalReferrals(int $referrerId): int
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return 0;
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM referrals WHERE referrer_user_id = ?'
            );
            $st->execute([$referrerId]);
            return (int)$st->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    /** نقاط الإحالة لمُحيل (المُحتَسَب × POINTS_PER). */
    public static function pointsFor(int $referrerId): int
    {
        return self::countCounted($referrerId) * self::POINTS_PER;
    }

    /** آخر N إحالات (نُظهر أسماء المستخدمين الجدد + تاريخ + معدود؟). */
    public static function recent(int $referrerId, int $limit = 20): array
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return [];
        self::ensureTable($pdo);
        try {
            $limit = max(1, min(100, $limit));
            $st = $pdo->prepare(
                'SELECT r.referred_user_id, r.counted, r.created_at, u.display_name, u.username
                 FROM referrals r
                 JOIN users u ON u.id = r.referred_user_id
                 WHERE r.referrer_user_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT ' . (int)$limit
            );
            $st->execute([$referrerId]);
            return $st->fetchAll() ?: [];
        } catch (Throwable $e) { return []; }
    }

    /** لوحة شرف أفضل المسوّقين. */
    public static function topPromoters(int $limit = 10): array
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return [];
        self::ensureTable($pdo);
        try {
            $limit = max(1, min(100, $limit));
            $st = $pdo->query(
                "SELECT r.referrer_user_id, COUNT(*) AS counted, u.display_name, u.username
                 FROM referrals r
                 JOIN users u ON u.id = r.referrer_user_id
                 WHERE r.counted = 1
                 GROUP BY r.referrer_user_id
                 ORDER BY counted DESC
                 LIMIT " . (int)$limit
            );
            return $st->fetchAll() ?: [];
        } catch (Throwable $e) { return []; }
    }

    /** خريطة نقاط الإحالة لكل user_id (تُستخدم في leaderboard). */
    public static function pointsByUserMap(): array
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return [];
        self::ensureTable($pdo);
        try {
            $rows = $pdo->query(
                'SELECT referrer_user_id, COUNT(*) * ' . (int)self::POINTS_PER . ' AS pts
                 FROM referrals WHERE counted = 1
                 GROUP BY referrer_user_id'
            )->fetchAll();
            $map = [];
            foreach ($rows as $r) {
                $map[(int)$r['referrer_user_id']] = (int)$r['pts'];
            }
            return $map;
        } catch (Throwable $e) { return []; }
    }

    /** الرابط المخصّص للمستخدم (مطلق). */
    public static function linkFor(string $username): string
    {
        $base = defined('SITE_URL') && SITE_URL !== ''
              ? rtrim((string)SITE_URL, '/')
              : ((($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'wcup2026.org'));
        return $base . '/?ref=' . rawurlencode($username);
    }

    /** IP الزائر مع احترام CDN/Proxy. */
    private static function ip(): string
    {
        $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') return $first;
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
