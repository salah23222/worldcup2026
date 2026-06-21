<?php
/**
 * Auth.php
 * ============================================================
 * نظام الحسابات: تسجيل، دخول، خروج، الجلسة الحالية، وحماية CSRF.
 *
 * يعتمد كلياً على Database::pdo(). إن كان null (النظام معطّل أو فشل
 * الاتصال) فكل الدوال تُرجّع حالة فشل لطيفة دون كسر أي صفحة:
 *   - register/login → ['ok'=>false, 'error'=>'db_unavailable', 'user'=>null]
 *   - user()         → null
 *   - check()        → false
 *
 * كلمات السر تُخزَّن بـ password_hash (bcrypt افتراضياً) ولا تُسترجع أبداً.
 * كل الوصول للقاعدة عبر استعلامات مُجهّزة (prepared statements).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Auth
{
    private const SESSION_KEY = 'wc_user_id';
    private const CSRF_KEY     = 'wc_auth_csrf';
    private const USER_MIN     = 3;
    private const USER_MAX     = 32;
    private const PASS_MIN     = 8;  // NIST 800-63B الحد الأدنى
    private const NAME_MAX     = 40;
    // تكلفة bcrypt مثبّتة (أعلى من الافتراضي 10) لتقوية التجزئة.
    private const PASS_OPTS    = ['cost' => 12];

    /** يبدأ الجلسة بأمان (لا يخطئ إن بدأت مسبقاً أو أُرسلت الترويسات). */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            return; // لا يمكن بدء الجلسة بعد إرسال الترويسات — نتفادى التحذير.
        }
        @session_start();
    }

    // ====================================================
    //  التسجيل والدخول والخروج
    // ====================================================

    /**
     * register() — ينشئ حساباً جديداً.
     * @return array{ok:bool, error:?string, user:?array}
     */
    public static function register(
        string $username,
        string $password,
        string $displayName,
        string $email = '',
        string $phone = '',
        string $country = ''
    ): array {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) {
            return ['ok' => false, 'error' => 'db_unavailable', 'user' => null];
        }

        // حدّ التسجيل لكل IP (يبطّئ الإساءة وحصر الحسابات).
        $regKey = 'register:ip:' . RateLimiter::ip();
        if (RateLimiter::blocked($regKey, 6, 3600)) {
            return ['ok' => false, 'error' => 'rate_limited', 'user' => null];
        }
        RateLimiter::hit($regKey, 3600);

        $username    = trim($username);
        $displayName = trim($displayName);
        $email       = trim($email);
        $country     = strtoupper(trim($country));
        $phone       = self::cleanPhone($phone);

        if (!self::validUsername($username)) {
            return ['ok' => false, 'error' => 'invalid_username', 'user' => null];
        }
        if (mb_strlen($password, 'UTF-8') < self::PASS_MIN) {
            return ['ok' => false, 'error' => 'weak_password', 'user' => null];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            return ['ok' => false, 'error' => 'invalid_email', 'user' => null];
        }
        if ($phone === null) {
            return ['ok' => false, 'error' => 'invalid_phone', 'user' => null];
        }
        if (!function_exists('is_valid_country') || !is_valid_country($country)) {
            return ['ok' => false, 'error' => 'invalid_country', 'user' => null];
        }
        // تنقية الاسم المعروض: أحرف (أي لغة) وأرقام ومسافة و _ . - فقط.
        // يزيل محارف HTML الخطرة (< > " ' &) كدفاع عميق ضد XSS — حتى لو فشل التهريب لاحقاً.
        $displayName = trim((string)preg_replace('/[^\p{L}\p{N} _.\-]/u', '', $displayName));
        $displayName = trim((string)preg_replace('/\s+/u', ' ', $displayName));
        if ($displayName === '') {
            $displayName = $username;
        }
        if (mb_strlen($displayName, 'UTF-8') > self::NAME_MAX) {
            $displayName = mb_substr($displayName, 0, self::NAME_MAX, 'UTF-8');
        }

        try {
            // تفرّد اسم المستخدم.
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                return ['ok' => false, 'error' => 'username_taken', 'user' => null];
            }
            // تفرّد البريد.
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                return ['ok' => false, 'error' => 'email_taken', 'user' => null];
            }

            $now  = time();
            $hash = password_hash($password, PASSWORD_DEFAULT, self::PASS_OPTS);
            $ins  = $pdo->prepare(
                'INSERT INTO users (username, display_name, email, phone, country, pass_hash, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([$username, $displayName, $email, $phone, $country, $hash, $now, $now]);
            $id = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'db_error', 'user' => null];
        }

        $user = [
            'id'           => $id,
            'username'     => $username,
            'display_name' => $displayName,
            'email'        => $email,
            'phone'        => $phone,
            'country'      => $country,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
        self::setLoggedIn($id);

        return ['ok' => true, 'error' => null, 'user' => $user];
    }

    /** ينظّف رقم الهاتف ويتحقق منه (أرقام + اختيارياً +)، أو null إن غير صالح. */
    private static function cleanPhone(string $phone): ?string
    {
        $p = preg_replace('/[\s\-()]+/', '', trim($phone));
        if (!preg_match('/^\+?\d{6,18}$/', (string)$p)) return null;
        return $p;
    }

    /**
     * login() — يتحقق من بيانات الدخول ويفتح جلسة.
     * @return array{ok:bool, error:?string, user:?array}
     */
    public static function login(string $username, string $password): array
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) {
            return ['ok' => false, 'error' => 'db_unavailable', 'user' => null];
        }

        $username = trim($username);

        // حدّ المحاولات: لكل IP ولكل اسم مستخدم (منع التخمين).
        $ipKey   = 'login:ip:' . RateLimiter::ip();
        $userKey = 'login:user:' . mb_strtolower($username);
        if (RateLimiter::blocked($ipKey, 20, 600) || RateLimiter::blocked($userKey, 8, 900)) {
            return ['ok' => false, 'error' => 'rate_limited', 'user' => null];
        }

        try {
            // الدخول باسم المستخدم أو البريد الإلكتروني (المستخدمون يكتبون بريدهم غالباً).
            // البريد يُطابَق بلا حساسيّة لحالة الأحرف (LOWER) مهما كان ترميز الجدول.
            $stmt = $pdo->prepare(
                'SELECT id, username, display_name, email, phone, country, pass_hash, created_at, updated_at
                 FROM users WHERE username = ? OR LOWER(email) = LOWER(?) LIMIT 1'
            );
            $stmt->execute([$username, $username]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'db_error', 'user' => null];
        }

        if (!$row || !password_verify($password, $row['pass_hash'])) {
            // سجّل المحاولة الفاشلة على المفتاحين.
            RateLimiter::hit($ipKey, 600);
            RateLimiter::hit($userKey, 900);
            return ['ok' => false, 'error' => 'invalid_credentials', 'user' => null];
        }

        // نجح الدخول → صفّر عدّاد فشل هذا المستخدم.
        RateLimiter::reset($userKey);

        // ترقية تجزئة كلمة السر تلقائياً إن تغيّر الخوارزم/التكلفة.
        if (password_needs_rehash($row['pass_hash'], PASSWORD_DEFAULT, self::PASS_OPTS)) {
            try {
                $newHash = password_hash($password, PASSWORD_DEFAULT, self::PASS_OPTS);
                $upd = $pdo->prepare('UPDATE users SET pass_hash = ?, updated_at = ? WHERE id = ?');
                $upd->execute([$newHash, time(), (int)$row['id']]);
            } catch (Throwable $e) {
                // غير حرج — نتابع الدخول بالتجزئة القديمة.
            }
        }

        // منع الدخول إن كان الحساب موقوفاً من لوحة التحكم (آمن قبل وجود الكلاس).
        if (class_exists('Moderation') && Moderation::isBanned((int)$row['id'])) {
            return ['ok' => false, 'error' => 'banned', 'user' => null];
        }

        self::setLoggedIn((int)$row['id']);

        return ['ok' => true, 'error' => null, 'user' => self::publicUser($row)];
    }

    /**
     * loginAsUser() — يفتح جلسة للمستخدم مباشرةً (بعد إثبات هويّة آمن مسبق:
     * مثل استهلاك رمز استعادة كلمة السر). يتحقق من وجوده، عدم إيقافه،
     * ثم يفتح الجلسة كما لو سجّل دخولاً عادياً.
     *
     * استخدم بحذر — لا تستدعِها قبل التحقّق من حقّ المستخدم في الدخول.
     * @return array{ok:bool, user:?array}
     */
    public static function loginAsUser(int $userId): array
    {
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO || $userId <= 0) {
            return ['ok' => false, 'user' => null];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, display_name, email, phone, country, created_at, updated_at
                 FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return ['ok' => false, 'user' => null];
        }
        if (!$row) {
            return ['ok' => false, 'user' => null];
        }
        // منع الدخول إن كان موقوفاً (احترام لوحة الإشراف).
        if (class_exists('Moderation') && Moderation::isBanned((int)$row['id'])) {
            return ['ok' => false, 'user' => null];
        }
        self::setLoggedIn((int)$row['id']);
        return ['ok' => true, 'user' => self::publicUser($row)];
    }

    /** يسجّل الخروج ويُتلف الجلسة بالكامل. */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }
    }

    // ====================================================
    //  الجلسة الحالية
    // ====================================================

    /** المستخدم المسجّل حالياً (مصفوفة عامة بلا تجزئة) أو null. */
    public static function user(): ?array
    {
        // 0) زائر بلا كوكي جلسة أصلاً → لا مستخدم حتماً. لا نبدأ جلسة له:
        //    بدء الجلسة كان يرسل Set-Cookie لكل زائر، وكوكي الجلسة يجعل PageCache
        //    يتجاوز كاش الصفحات في كل زياراته اللاحقة → كل زائر متكرّر كان يدفع
        //    كلفة توليد الصفحة كاملةً. (صفحات الدخول/التوقع تبدأ جلستها بنفسها.)
        if (session_status() !== PHP_SESSION_ACTIVE
            && empty($_COOKIE[session_name()])) {
            return null;
        }

        // 1) تحقّق الجلسة أولاً — الزوّار غير المسجّلين (والزواحف) لا يلمسون القاعدة إطلاقاً.
        //    هذا يمنع فتح اتصال MySQL في كل صفحة (السبب الجذري لأخطاء 504 تحت الضغط).
        self::startSession();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_int($id) && !ctype_digit((string)$id)) {
            return null;
        }
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        // 2) كاش في الجلسة — المستخدم المسجّل لا يُستعلَم من القاعدة في كل صفحة.
        if (isset($_SESSION['wc_user']) && is_array($_SESSION['wc_user'])
            && (int)($_SESSION['wc_user']['id'] ?? 0) === $id) {
            return $_SESSION['wc_user'];
        }

        // 3) أوّل مرة فقط (أو بعد تسجيل الدخول): استعلام واحد ثم تخزين في الجلسة.
        $pdo = Database::pdo();
        if (!$pdo instanceof PDO) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, display_name, email, phone, country, created_at, updated_at
                 FROM users WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            return null;
        }

        if (!$row) return null;
        $pub = self::publicUser($row);
        $_SESSION['wc_user'] = $pub;   // كاش الجلسة → لا استعلام في الصفحات التالية
        return $pub;
    }

    /** هل يوجد مستخدم مسجّل الدخول حالياً؟ */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    // ====================================================
    //  CSRF (رمز مخزّن في الجلسة)
    // ====================================================

    /** يُرجّع رمز CSRF للجلسة (ينشئه عند أول استدعاء). */
    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION[self::CSRF_KEY]) || !is_string($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    /** حقل مخفي جاهز للنماذج. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="'
            . htmlspecialchars(self::csrfToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }

    /** يتحقق من رمز CSRF المُرسل مقابل رمز الجلسة (مقارنة ثابتة الزمن). */
    public static function checkCsrf(?string $token): bool
    {
        self::startSession();
        $stored = $_SESSION[self::CSRF_KEY] ?? '';
        return is_string($stored)
            && $stored !== ''
            && is_string($token)
            && hash_equals($stored, $token);
    }

    // ====================================================
    //  أدوات داخلية
    // ====================================================

    private static function setLoggedIn(int $id): void
    {
        self::startSession();
        // تجديد معرّف الجلسة لمنع تثبيت الجلسة (session fixation).
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_regenerate_id(true);
        }
        $_SESSION[self::SESSION_KEY] = $id;
        unset($_SESSION['wc_user']);   // امسح كاش الجلسة → يُعاد جلب المستخدم الصحيح مرّة واحدة
    }

    /** يتحقق من صيغة اسم المستخدم: 3–32 محرفاً [a-zA-Z0-9_.] */
    private static function validUsername(string $username): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_.]{' . self::USER_MIN . ',' . self::USER_MAX . '}$/', $username);
    }

    /** يجرّد صف القاعدة من الحقول الحسّاسة (pass_hash). */
    private static function publicUser(array $row): array
    {
        return [
            'id'           => (int)$row['id'],
            'username'     => (string)$row['username'],
            'display_name' => (string)$row['display_name'],
            'email'        => (string)($row['email'] ?? ''),
            'phone'        => (string)($row['phone'] ?? ''),
            'country'      => (string)($row['country'] ?? ''),
            'created_at'   => (int)($row['created_at'] ?? 0),
            'updated_at'   => (int)($row['updated_at'] ?? 0),
        ];
    }
}
