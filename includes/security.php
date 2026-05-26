<?php
/**
 * security.php — تقوية أمنية على مستوى PHP (لا تعتمد على Apache وحده).
 *   - إعدادات كوكي الجلسة (HttpOnly / SameSite / Secure) قبل أي session_start.
 *   - فرض HTTPS + HSTS في الإنتاج.
 *   - ترويسات أمان: CSP، nosniff، X-Frame-Options، Referrer-Policy.
 * تُستدعى مرّة واحدة من bootstrap.php بعد تحميل config مباشرةً.
 */
if (!defined('WC2026')) { exit('Access denied'); }

function security_init(): void
{
    if (PHP_SAPI === 'cli') { return; }

    // --- كشف الـ HTTPS مع مراعاة الوكلاء/شبكات التوصيل (CDN) التي تُنهي TLS عند الحافة ---
    $fwdProto  = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $fwdSsl    = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $fwdScheme = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '')));
    $fwd       = strtolower((string)($_SERVER['HTTP_FORWARDED'] ?? ''));
    $cfVisitor = (string)($_SERVER['HTTP_CF_VISITOR'] ?? '');

    // هل نحن خلف وكيل/CDN أصلاً؟ (وجود أي ترويسة توجيه = نعم). على Hostinger hcdn
    // تُنهى TLS عند الحافة ويصل الطلب للأصل أحياناً كـ http بلا X-Forwarded-Proto.
    $behindProxy = ($fwdProto !== '' || $fwdSsl !== '' || $fwdScheme !== '' || $fwd !== ''
                 || $cfVisitor !== '' || isset($_SERVER['HTTP_X_FORWARDED_FOR']));

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443)
          || ($fwdProto === 'https')
          || ($fwdSsl === 'on')
          || ($fwdScheme === 'https')
          || (strpos($fwd, 'proto=https') !== false)
          || (stripos($cfVisitor, 'https') !== false);

    // الزائر آمن فعلياً متى كان على https مباشرةً، أو خلف CDN يفرض https عند الحافة
    // (والموقع كله يُقدَّم عبر https — SITE_URL يبدأ بـ https).
    $siteIsHttps  = (stripos((string)SITE_URL, 'https://') === 0);
    $effectiveTls = $https || ($behindProxy && $siteIsHttps);

    // 1) كوكي الجلسة — قبل بدء أي جلسة.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        if ($effectiveTls) { @ini_set('session.cookie_secure', '1'); }
    }

    // 2) فرض HTTPS — تتكفّل به الحافة (Hostinger Force HTTPS) + ترويسة HSTS أدناه.
    //    تحذير حاسم: خلف Hostinger hcdn تُنهى TLS عند الحافة، فيصل الطلب للأصل كـ http
    //    *دائماً* حتى والمتصفّح على https. أي تحويل http→https من PHP هنا يخزّنه الـCDN
    //    ويعيد تقديمه للمتصفّح → حلقة تحويل لا نهائية → 504 Gateway Time-out.
    //    لذلك لا نُصدر تحويلاً من PHP إطلاقاً — الحافة وHSTS يضمنان https دون مخاطرة بالحلقة.

    if (headers_sent()) { return; }

    // 3) ترويسات الأمان.
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // الصفحات القابلة للتضمين (الودجت) يُسمح بتأطيرها في أي موقع.
    $page       = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $embeddable = in_array($page, ['widget.php', 'embed.php'], true);
    if (!$embeddable) {
        header('X-Frame-Options: SAMEORIGIN');
    }
    $frameAncestors = $embeddable ? '*' : "'self'";

    // CSP: يسمح بالخطوط (Google Fonts) والصور الخارجية (أعلام/ويكيبيديا/أخبار).
    // 'unsafe-inline' للنصوص البرمجية المضمّنة (الإخراج كله مُهرَّب أصلاً).
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src 'self' https://fonts.gstatic.com; "
         . "img-src 'self' data: https:; "
         . "connect-src 'self'; "
         . "object-src 'none'; "
         . "base-uri 'self'; "
         . "form-action 'self'; "
         . "frame-ancestors {$frameAncestors}";
    header('Content-Security-Policy: ' . $csp);

    if ($effectiveTls) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
