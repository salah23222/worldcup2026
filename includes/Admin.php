<?php
/**
 * Admin.php — بوابة لوحة التحكم (admin.php).
 * ============================================================
 * دخول بكلمة سر واحدة (ADMIN_PASS من config.local.php) عبر جلسة.
 * لا تعتمد على نظام الحسابات — تعمل حتى لو كانت قاعدة البيانات معطّلة.
 * كل الإجراءات الكاتبة محميّة بـCSRF، والدخول محدود المحاولات.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Admin
{
    private const SESS = 'wc_admin_ok';
    private const CSRF = 'wc_admin_csrf';

    private static function session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    }

    /** هل اللوحة مفعّلة (كلمة سر مضبوطة)؟ */
    public static function enabled(): bool
    {
        return defined('ADMIN_PASS') && ADMIN_PASS !== '';
    }

    /** هل المستخدم الحالي مُسجّل دخول كأدمن؟ */
    public static function authed(): bool
    {
        self::session();
        return !empty($_SESSION[self::SESS]);
    }

    /** محاولة دخول بكلمة السر (يقارن بثبات زمني). */
    public static function attempt(string $pass): bool
    {
        if (!self::enabled()) return false;
        self::session();
        if (hash_equals(ADMIN_PASS, $pass)) {
            session_regenerate_id(true);
            $_SESSION[self::SESS] = true;
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        self::session();
        unset($_SESSION[self::SESS]);
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
}
