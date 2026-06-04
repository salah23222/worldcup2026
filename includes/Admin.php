<?php
/**
 * Admin.php — بوابة لوحة التحكم (admin.php).
 * ============================================================
 * دخول بكلمة سر واحدة عبر جلسة. تعمل حتى لو كانت قاعدة البيانات معطّلة.
 *
 * تحصينات الأمن:
 *   1) تخزين الهاش (bcrypt) بدل النص العادي عبر ADMIN_PASS_HASH (مُفضَّل).
 *      توافق رجعي: لو ضُبط ADMIN_PASS كنص عادي يُقبل أيضاً.
 *   2) مقارنة بثبات زمني (password_verify / hash_equals).
 *   3) تجديد معرّف الجلسة عند الدخول/الخروج (يمنع تثبيت الجلسة).
 *   4) مهلة خمول للجلسة (60 دقيقة افتراضياً) → تسجيل خروج تلقائي.
 *   5) CSRF لكل إجراءات الكتابة.
 *   6) سِجلّ تدقيق لمحاولات الدخول (نجاح/فشل) في logs/admin-login.log.
 *   7) إشعار بريدي عند نجاح أيّ دخول (best-effort — لا يكسر شيئاً).
 *
 * RateLimiter محدّد المحاولات والـ no-cache مُطبَّقَين في admin.php.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Admin
{
    private const SESS         = 'wc_admin_ok';
    private const CSRF         = 'wc_admin_csrf';
    private const SEEN         = 'wc_admin_seen';
    private const IDLE_SECONDS = 3600;   // 60 دقيقة خمول → خروج تلقائي

    private static function session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    }

    /** هل اللوحة مفعّلة؟ (هاش أو نص عادي مضبوط) */
    public static function enabled(): bool
    {
        $hash  = defined('ADMIN_PASS_HASH') ? (string)ADMIN_PASS_HASH : '';
        $plain = defined('ADMIN_PASS')      ? (string)ADMIN_PASS      : '';
        return ($hash !== '' || $plain !== '');
    }

    /** هل المستخدم مُسجّل دخول كأدمن؟ (مع فحص مهلة الخمول) */
    public static function authed(): bool
    {
        self::session();
        if (empty($_SESSION[self::SESS])) return false;
        // مهلة خمول: لو تجاوز آخر نشاط الحدّ → اخرج وأعِد false.
        $seen = (int)($_SESSION[self::SEEN] ?? 0);
        if ($seen > 0 && (time() - $seen) > self::IDLE_SECONDS) {
            self::logout();
            return false;
        }
        $_SESSION[self::SEEN] = time();
        return true;
    }

    /** محاولة دخول. يُفضَّل الهاش، ويقبل النص العادي كتوافق رجعي. */
    public static function attempt(string $pass): bool
    {
        if (!self::enabled() || $pass === '') {
            self::audit(false, 'no-password-or-disabled');
            return false;
        }
        self::session();

        $hash  = defined('ADMIN_PASS_HASH') ? (string)ADMIN_PASS_HASH : '';
        $plain = defined('ADMIN_PASS')      ? (string)ADMIN_PASS      : '';

        $ok = false;
        if ($hash !== '') {
            // الهاش هو المسار الآمن المُفضَّل
            $ok = password_verify($pass, $hash);
        } elseif ($plain !== '') {
            // توافق رجعي: مقارنة نص عادي بثبات زمني
            $ok = hash_equals($plain, $pass);
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION[self::SESS] = true;
            $_SESSION[self::SEEN] = time();
            self::audit(true, 'success');
            self::notifyLogin();   // best-effort (لا يكسر إن فشل البريد)
            return true;
        }

        self::audit(false, 'wrong-password');
        return false;
    }

    public static function logout(): void
    {
        self::session();
        unset($_SESSION[self::SESS], $_SESSION[self::SEEN]);
        session_regenerate_id(true);
    }

    /** رمز CSRF للجلسة (يُولَّد مرة). */
    public static function csrfToken(): string
    {
        self::session();
        if (empty($_SESSION[self::CSRF])) {
            $_SESSION[self::CSRF] = bin2hex(random_bytes(16));
        }
        return $_SESSION[self::CSRF];
    }

    /** حقل CSRF مخفي للنماذج. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . e(self::csrfToken()) . '">';
    }

    /** يتحقّق من رمز CSRF في طلب POST. */
    public static function checkCsrf(): bool
    {
        self::session();
        return isset($_POST['csrf'])
            && is_string($_POST['csrf'])
            && hash_equals((string)($_SESSION[self::CSRF] ?? ''), $_POST['csrf']);
    }

    // ====================================================
    //  سِجلّ التدقيق + إشعار البريد
    // ====================================================

    /** يكتب سطراً في logs/admin-login.log (لا يفشل أبداً). */
    private static function audit(bool $ok, string $reason): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $line = sprintf(
            "[%s] %s ip=%s ua=%s reason=%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $ok ? 'OK   ' : 'FAIL ',
            self::ip(),
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 160),
            $reason
        );
        @file_put_contents($logDir . '/admin-login.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** إشعار بريدي قصير عند نجاح الدخول (best-effort). */
    private static function notifyLogin(): void
    {
        try {
            if (!class_exists('Mailer') || !defined('CONTACT_EMAIL') || CONTACT_EMAIL === '') return;
            $to   = (string)CONTACT_EMAIL;
            $when = gmdate('Y-m-d H:i:s') . ' UTC';
            $ip   = self::ip();
            $ua   = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? '-'), 0, 200);
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'wcup2026.org');
            $subj = '[' . $host . '] Admin login at ' . $when;
            $body = "Successful admin login on {$host}\n\n"
                  . "Time:  {$when}\n"
                  . "IP:    {$ip}\n"
                  . "Agent: {$ua}\n\n"
                  . "If this was NOT you, change ADMIN_PASS_HASH in includes/config.local.php immediately.";
            @Mailer::send($to, $subj, $body);
        } catch (\Throwable $e) {
            // تجاهَل بصمت — تسجيل الدخول الناجح أهمّ من إشعار البريد.
        }
    }

    /** عنوان IP الفعلي للزائر (يحترم وكيل/CDN). */
    public static function ip(): string
    {
        $fwd = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($fwd !== '') {
            $first = trim(explode(',', $fwd)[0]);
            if ($first !== '') return $first;
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
