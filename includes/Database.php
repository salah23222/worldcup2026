<?php
/**
 * Database.php
 * ============================================================
 * اتصال PDO وحيد (singleton) بقاعدة بيانات MySQL لنظام الحسابات.
 *
 * مبادئ السلامة:
 *   - معطّل كلياً إذا كان DB_ENABLED = false → pdo() تُرجّع null.
 *   - عند فشل الاتصال لا يُرمى استثناء للخارج → تُرجّع null بدلاً منه،
 *     فيتعامل المستدعي مع الحالة بهدوء ولا تنكسر أي صفحة.
 *   - كل الوصول للبيانات عبر استعلامات مُجهّزة (prepared) في طبقات أعلى.
 *
 * الاستخدام:
 *   if (Database::available()) { $pdo = Database::pdo(); ... }
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Database
{
    /** مؤشّر الاتصال المُخزّن مؤقتاً (cache). false = حاولنا وفشلنا. */
    private static ?PDO $pdo = null;
    private static bool $tried = false;

    /**
     * يُرجّع اتصال PDO جاهزاً، أو null إن كان النظام معطّلاً أو فشل الاتصال.
     * لا يرمي أبداً — المستدعي يجب أن يفحص null.
     */
    public static function pdo(): ?PDO
    {
        if (self::$tried) {
            return self::$pdo;
        }
        self::$tried = true;

        if (!defined('DB_ENABLED') || !DB_ENABLED) {
            return self::$pdo = null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // مهلة اتصال قصيرة: لو كانت MySQL بطيئة/ممتلئة، يفشل سريعاً (وضع الملفات)
                // بدل أن يعلّق الصفحة 60 ثانية ويسبّب 504.
                PDO::ATTR_TIMEOUT            => 4,
            ]);
        } catch (Throwable $e) {
            // فشل الاتصال (مفتاح/كلمة سر خاطئة، أو DB غير موجودة، أو الخادم متوقّف).
            self::$pdo = null;
        }

        return self::$pdo;
    }

    /** هل نظام الحسابات جاهز للاستخدام (مفعّل + الاتصال ناجح)؟ */
    public static function available(): bool
    {
        return self::pdo() instanceof PDO;
    }

    /**
     * install() — إعداد لمرة واحدة:
     *   1) ينشئ قاعدة البيانات إن لم تكن موجودة.
     *   2) ينشئ جدول users إن لم يكن موجوداً.
     * يُرجّع مصفوفة حالة: ['ok'=>bool, 'steps'=>[...], 'error'=>?string]
     * آمن للتكرار (idempotent) — لا يحذف أو يعدّل بيانات قائمة.
     */
    public static function install(): array
    {
        $steps = [];

        if (!defined('DB_ENABLED') || !DB_ENABLED) {
            return ['ok' => false, 'steps' => $steps, 'error' => 'db_disabled'];
        }

        // الخطوة 1: الاتصال بالخادم (بدون تحديد قاعدة) وإنشاء قاعدة البيانات.
        try {
            $serverDsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
            $server = new PDO($serverDsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', DB_NAME);
            $server->exec(
                "CREATE DATABASE IF NOT EXISTS `{$dbName}` "
                . "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            $steps[] = 'database_ready';
        } catch (Throwable $e) {
            return [
                'ok'    => false,
                'steps' => $steps,
                'error' => 'connect_failed: ' . $e->getMessage(),
            ];
        }

        // الخطوة 2: الاتصال بالقاعدة وإنشاء جدول users.
        $pdo = self::pdo();
        if (!$pdo instanceof PDO) {
            return [
                'ok'    => false,
                'steps' => $steps,
                'error' => 'db_connect_failed',
            ];
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `users` (
                    `id`           INT AUTO_INCREMENT PRIMARY KEY,
                    `username`     VARCHAR(32)  NOT NULL UNIQUE,
                    `display_name` VARCHAR(40)  NOT NULL,
                    `email`        VARCHAR(190) NOT NULL UNIQUE,
                    `phone`        VARCHAR(32)  NOT NULL,
                    `country`      VARCHAR(2)   NOT NULL,
                    `pass_hash`    VARCHAR(255) NOT NULL,
                    `created_at`   INT          NOT NULL,
                    `updated_at`   INT          NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $steps[] = 'users_table_ready';

            // ترقية الجداول القديمة: أضف الأعمدة الناقصة (آمن للتكرار)
            $cols = [
                'email'   => "ADD COLUMN `email` VARCHAR(190) NOT NULL DEFAULT '' ",
                'phone'   => "ADD COLUMN `phone` VARCHAR(32) NOT NULL DEFAULT '' ",
                'country' => "ADD COLUMN `country` VARCHAR(2) NOT NULL DEFAULT '' ",
            ];
            $have = [];
            foreach ($pdo->query('SHOW COLUMNS FROM `users`') as $c) {
                $have[strtolower($c['Field'])] = true;
            }
            foreach ($cols as $name => $ddl) {
                if (!isset($have[$name])) {
                    try { $pdo->exec('ALTER TABLE `users` ' . $ddl); } catch (Throwable $e) {}
                }
            }

            // جدول التوقعات: توقّع واحد لكل (مستخدم، مباراة).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `predictions` (
                    `id`          INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`     INT             NOT NULL,
                    `match_index` INT             NOT NULL,
                    `pred1`       TINYINT UNSIGNED NOT NULL,
                    `pred2`       TINYINT UNSIGNED NOT NULL,
                    `created_at`  INT             NOT NULL,
                    `updated_at`  INT             NOT NULL,
                    UNIQUE KEY `uniq_user_match` (`user_id`, `match_index`),
                    KEY `idx_match` (`match_index`),
                    CONSTRAINT `fk_pred_user` FOREIGN KEY (`user_id`)
                        REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $steps[] = 'predictions_table_ready';

            // جدول إجابات سؤال اليوم: إجابة واحدة لكل (مستخدم، يوم).
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `trivia_answers` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`    INT             NOT NULL,
                    `day`        CHAR(10)        NOT NULL,
                    `chosen`     TINYINT         NOT NULL,
                    `points`     TINYINT UNSIGNED NOT NULL,
                    `created_at` INT             NOT NULL,
                    UNIQUE KEY `uniq_user_day` (`user_id`, `day`),
                    CONSTRAINT `fk_trivia_user` FOREIGN KEY (`user_id`)
                        REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $steps[] = 'trivia_table_ready';
        } catch (Throwable $e) {
            return [
                'ok'    => false,
                'steps' => $steps,
                'error' => 'create_table_failed: ' . $e->getMessage(),
            ];
        }

        return ['ok' => true, 'steps' => $steps, 'error' => null];
    }
}
