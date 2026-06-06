<?php
/**
 * XPublisher.php — نشر تلقائي على منصّة X (تويتر).
 * ============================================================
 * عميل خفيف يوقّع طلبات OAuth 1.0a User Context ويُرسل تغريدة
 * عبر نقطة v2: POST https://api.twitter.com/2/tweets
 *
 * يحتاج أربعة مفاتيح في config.local.php:
 *   X_API_KEY · X_API_SECRET · X_ACCESS_TOKEN · X_ACCESS_SECRET
 * بدون مفاتيح → الميزة معطّلة بالكامل ولا تتصل بأي شبكة.
 *
 * تسجيل: كل عملية نشر تُضاف لـ cache/x_log.json (آخر 30 عملية).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class XPublisher
{
    private const ENDPOINT = 'https://api.twitter.com/2/tweets';
    private const LIMIT    = 280;   // حدّ X الصارم

    /** هل تمّ ضبط كل المفاتيح الأربعة؟ */
    public static function configured(): bool
    {
        return defined('X_API_KEY')      && X_API_KEY      !== ''
            && defined('X_API_SECRET')   && X_API_SECRET   !== ''
            && defined('X_ACCESS_TOKEN') && X_ACCESS_TOKEN !== ''
            && defined('X_ACCESS_SECRET')&& X_ACCESS_SECRET!== '';
    }

    /**
     * يُرسل تغريدة ويعيد ['ok'=>bool, 'id'=>?string, 'error'=>?string].
     * يقصّ النص تلقائياً إن تجاوز 280 حرفاً (وحدة X هي codepoints UTF-16
     * تقريباً = mb_strlen هنا — كافٍ لتلافي الرفض).
     */
    public static function tweet(string $text): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'id' => null, 'error' => 'x_not_configured'];
        }
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'id' => null, 'error' => 'empty_text'];
        }
        if (mb_strlen($text, 'UTF-8') > self::LIMIT) {
            $text = mb_substr($text, 0, self::LIMIT - 1, 'UTF-8') . '…';
        }

        $auth = self::oauthHeader('POST', self::ENDPOINT, []);
        $body = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $auth,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $j = is_string($resp) ? json_decode($resp, true) : null;
        if ($code >= 200 && $code < 300 && is_array($j) && !empty($j['data']['id'])) {
            $id = (string)$j['data']['id'];
            self::log($id, true, null, $text);
            return ['ok' => true, 'id' => $id, 'error' => null];
        }

        $msg = $err !== '' ? $err
             : (is_array($j) && !empty($j['detail']) ? (string)$j['detail']
             : (is_array($j) && !empty($j['title'])  ? (string)$j['title']
             : ('http_' . $code)));
        self::log(null, false, $msg, $text);
        return ['ok' => false, 'id' => null, 'error' => $msg];
    }

    /** يبني ترويسة Authorization: OAuth ... للطلب. */
    private static function oauthHeader(string $method, string $url, array $extra): string
    {
        $p = array_merge([
            'oauth_consumer_key'     => X_API_KEY,
            'oauth_nonce'            => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'        => (string)time(),
            'oauth_token'            => X_ACCESS_TOKEN,
            'oauth_version'          => '1.0',
        ], $extra);

        // قاعدة التوقيع
        ksort($p);
        $pairs = [];
        foreach ($p as $k => $v) {
            $pairs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        $base = strtoupper($method) . '&'
              . rawurlencode($url) . '&'
              . rawurlencode(implode('&', $pairs));
        $key  = rawurlencode(X_API_SECRET) . '&' . rawurlencode(X_ACCESS_SECRET);
        $p['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));

        // ترويسة Authorization
        $h = [];
        foreach ($p as $k => $v) {
            if (strpos((string)$k, 'oauth_') !== 0) continue;
            $h[] = rawurlencode((string)$k) . '="' . rawurlencode((string)$v) . '"';
        }
        return 'OAuth ' . implode(', ', $h);
    }

    /** يكتب سطراً في cache/x_log.json (آخر 30 عملية، الأحدث أولاً). */
    public static function log(?string $id, bool $ok, ?string $error, string $text): void
    {
        $f = rtrim(CACHE_DIR, '/') . '/x_log.json';
        $list = [];
        if (is_file($f)) {
            $d = json_decode((string)@file_get_contents($f), true);
            if (is_array($d)) $list = $d;
        }
        array_unshift($list, [
            't'     => time(),
            'ok'    => $ok ? 1 : 0,
            'id'    => $id,
            'error' => $error,
            'text'  => mb_substr($text, 0, 280, 'UTF-8'),
        ]);
        $list = array_slice($list, 0, 30);
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($f, json_encode($list, JSON_UNESCAPED_UNICODE));
    }

    /** آخر N عمليات نشر (للوحة التحكم). */
    public static function recentLog(int $n = 20): array
    {
        $f = rtrim(CACHE_DIR, '/') . '/x_log.json';
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? array_slice($d, 0, $n) : [];
    }

    /**
     * بوّابة «مرّة واحدة لكل فترة-في-اليوم» — تمنع الـ cron من نشر نفس
     * الفترة مرتين لو شُغّل أكثر من مرّة في الساعة نفسها.
     * يعيد true إن لم يُنشر اليوم في هذه الفترة (ويسجّل الفترة).
     */
    public static function claimSlot(string $slot): bool
    {
        $f = rtrim(CACHE_DIR, '/') . '/x_slots.json';
        $today = date('Y-m-d');
        $state = [];
        if (is_file($f)) {
            $d = json_decode((string)@file_get_contents($f), true);
            if (is_array($d)) $state = $d;
        }
        // نظّف سجلات الأيام السابقة
        foreach ($state as $k => $_) {
            if (!str_starts_with((string)$k, $today)) unset($state[$k]);
        }
        $key = $today . '|' . $slot;
        if (isset($state[$key])) return false;
        $state[$key] = time();
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($f, json_encode($state, JSON_UNESCAPED_UNICODE));
        return true;
    }
}
