<?php
/**
 * Moderation.php — مخزن إيقاف المستخدمين (banned_users).
 * ============================================================
 * يعتمد على نظام الحسابات (Database). معطّل بهدوء إذا كانت القاعدة
 * غير متاحة: كل دالة تُرجّع قيمة آمنة ([] أو false) ولا ترمي أبداً.
 * كل الوصول عبر استعلامات مُجهّزة (prepared)، والجدول يُنشأ عند الحاجة.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Moderation
{
    /** ينشئ جدول الإيقاف إن لم يكن موجوداً (آمن للتكرار). يُستدعى داخل كل دالة. */
    private static function ensureTable(): void
    {
        $pdo = Database::pdo();
        if (!Database::available() || $pdo === null) { return; }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `banned_users` (
                    `user_id` INT UNSIGNED PRIMARY KEY,
                    `reason`  VARCHAR(190) NOT NULL DEFAULT '',
                    `at`      INT UNSIGNED NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {
            // تجاهل بهدوء — المستدعي يحصل على القيمة الآمنة.
        }
    }

    /** هل المستخدم محظور حالياً؟ */
    public static function isBanned(int $id): bool
    {
        $pdo = Database::pdo();
        if (!Database::available() || $pdo === null) { return false; }
        try {
            self::ensureTable();
            $st = $pdo->prepare('SELECT 1 FROM `banned_users` WHERE `user_id` = ? LIMIT 1');
            $st->execute([$id]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** يحظر مستخدماً (أو يحدّث سبب حظر قائم). */
    public static function ban(int $id, string $reason = ''): bool
    {
        $pdo = Database::pdo();
        if (!Database::available() || $pdo === null) { return false; }
        try {
            self::ensureTable();
            $st = $pdo->prepare(
                'INSERT INTO `banned_users` (`user_id`, `reason`, `at`)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`)'
            );
            return $st->execute([$id, $reason, time()]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** يرفع الحظر عن مستخدم. */
    public static function unban(int $id): bool
    {
        $pdo = Database::pdo();
        if (!Database::available() || $pdo === null) { return false; }
        try {
            self::ensureTable();
            $st = $pdo->prepare('DELETE FROM `banned_users` WHERE `user_id` = ?');
            return $st->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * كل المستخدمين المحظورين، الأحدث أولاً.
     * كل صف: ['user_id','reason','at','username','display_name'].
     */
    public static function all(): array
    {
        $pdo = Database::pdo();
        if (!Database::available() || $pdo === null) { return []; }
        try {
            self::ensureTable();
            $st = $pdo->query(
                'SELECT b.`user_id`, b.`reason`, b.`at`,
                        u.`username`, u.`display_name`
                 FROM `banned_users` b
                 LEFT JOIN `users` u ON u.`id` = b.`user_id`
                 ORDER BY b.`at` DESC'
            );
            $rows = $st->fetchAll();
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
