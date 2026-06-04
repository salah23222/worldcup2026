<?php
/**
 * PasswordReset.php — رموز استعادة كلمة السر (آمنة، صالحة مرة واحدة، 1 ساعة).
 * ============================================================
 * نُخزّن hash(SHA-256) للرمز لا الرمز نفسه → لو سُرّبت القاعدة لا تصلح الروابط.
 * مرّة واحدة فقط (used_at)، صلاحية ساعة (expires_at).
 * عند الاستهلاك: نُحدّث pass_hash ونُبطل بقية رموز نفس المستخدم.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class PasswordReset
{
    private const TTL_SECONDS = 3600;        // ساعة واحدة
    private static bool $tableReady = false;

    /** ينشئ الجدول مرّة واحدة (lazy). */
    private static function ensureTable(PDO $pdo): void
    {
        if (self::$tableReady) return;
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `password_resets` (
                  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  `user_id` INT UNSIGNED NOT NULL,
                  `token_hash` CHAR(64) NOT NULL,
                  `expires_at` INT UNSIGNED NOT NULL,
                  `used_at` INT UNSIGNED DEFAULT NULL,
                  `created_at` INT UNSIGNED NOT NULL,
                  `ip` VARCHAR(45) DEFAULT NULL,
                  UNIQUE KEY `uk_token` (`token_hash`),
                  KEY `idx_user` (`user_id`),
                  KEY `idx_exp` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::$tableReady = true;
        } catch (Throwable $e) {
            // غير حرج — إن فشل، الدوال أدناه ترجع false بأمان.
        }
    }

    /** يبحث عن مستخدم بالبريد. يرجّع id أو null (لا يكشف وجود/عدم وجود البريد). */
    public static function findUserByEmail(string $email): ?int
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return null;
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        try {
            $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $row = $st->fetch();
            return $row ? (int)$row['id'] : null;
        } catch (Throwable $e) { return null; }
    }

    /**
     * يُولّد رمزاً جديداً للمستخدم، يخزّن hashه، ويُرجع الرمز الصافي (مرّة واحدة فقط).
     * يُبطِل أيّ رموز سابقة نشطة لنفس المستخدم.
     */
    public static function create(int $userId, ?string $ip = null): ?string
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO || $userId <= 0) return null;
        self::ensureTable($pdo);

        $token = bin2hex(random_bytes(32));            // 64 محرفاً سداسية
        $hash  = hash('sha256', $token);
        $now   = time();
        $exp   = $now + self::TTL_SECONDS;

        try {
            // أبطِل أيّ رموز نشطة سابقة لنفس المستخدم (single active token policy)
            $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL')
                ->execute([$now, $userId]);

            $st = $pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at, ip)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([$userId, $hash, $exp, $now, $ip]);
            return $token;
        } catch (Throwable $e) { return null; }
    }

    /** يتحقّق من صلاحية الرمز ويُرجع user_id أو null. */
    public static function verify(string $token): ?int
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return null;
        if (strlen($token) !== 64 || !ctype_xdigit($token)) return null;
        self::ensureTable($pdo);

        $hash = hash('sha256', $token);
        try {
            $st = $pdo->prepare(
                'SELECT user_id, expires_at, used_at FROM password_resets WHERE token_hash = ? LIMIT 1'
            );
            $st->execute([$hash]);
            $row = $st->fetch();
        } catch (Throwable $e) { return null; }

        if (!$row) return null;
        if (!empty($row['used_at'])) return null;
        if (time() > (int)$row['expires_at']) return null;
        return (int)$row['user_id'];
    }

    /**
     * يستهلك الرمز ويُحدّث كلمة سر المستخدم في معاملة واحدة (ذرّية).
     * يرجّع true عند النجاح.
     */
    public static function consume(string $token, string $newPassword): bool
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) return false;
        if (mb_strlen($newPassword, 'UTF-8') < 6) return false;

        $userId = self::verify($token);
        if (!$userId) return false;

        $hash    = hash('sha256', $token);
        $passNew = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 12]);
        $now     = time();

        try {
            $pdo->beginTransaction();
            // علِّم الرمز مستهلكاً (شرطياً لمنع السباق الزمني race condition)
            $st = $pdo->prepare(
                'UPDATE password_resets SET used_at = ? WHERE token_hash = ? AND used_at IS NULL'
            );
            $st->execute([$now, $hash]);
            if ($st->rowCount() !== 1) { $pdo->rollBack(); return false; }
            // حدّث كلمة السر
            $pdo->prepare('UPDATE users SET pass_hash = ?, updated_at = ? WHERE id = ?')
                ->execute([$passNew, $now, $userId]);
            // أبطِل أيّ رموز معلّقة أخرى لنفس المستخدم
            $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL')
                ->execute([$now, $userId]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return false;
        }
    }
}
