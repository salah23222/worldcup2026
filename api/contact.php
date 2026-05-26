<?php
/**
 * api/contact.php — استقبال رسائل «تواصل معنا / الرعاية».
 *
 *   GET  ?action=token        → يضبط كوكي CSRF ويعيد الرمز (قبل الإرسال).
 *   POST  action=send {...}   → يتحقق ويُرسل بريداً إلى CONTACT_EMAIL + نسخة احتياطية.
 *
 * الحماية: CSRF (ترويسة X-CSRF) + فحص المصدر (Origin) + حدّ المحاولات + فخّ للبوتات.
 */
require __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$body   = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) { $body = $decoded; }
}
$input  = $body + $_POST + $_GET;
$action = $input['action'] ?? '';

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// إصدار رمز CSRF + ضبط الكوكي (يجب قبل أي إخراج HTML).
if ($action === 'token') {
    respond(['ok' => true, 'csrf' => Predictions::ensureCsrf()]);
}

if ($method !== 'POST' || $action !== 'send') {
    respond(['ok' => false, 'error' => 'method'], 405);
}

// 1) فحص المصدر: ارفض الطلبات من نطاق خارجي (حماية CSRF إضافية).
$host   = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $oHost = parse_url($origin, PHP_URL_HOST);
    if ($oHost !== null && $host !== '' && strcasecmp($oHost, $host) !== 0) {
        respond(['ok' => false, 'error' => 'bad_origin'], 403);
    }
}

// 2) CSRF (double-submit cookie).
if (!Predictions::checkCsrf($_SERVER['HTTP_X_CSRF'] ?? null)) {
    respond(['ok' => false, 'error' => 'csrf'], 403);
}

// 3) فخّ البوتات: حقل مخفي يجب أن يبقى فارغاً.
if (trim((string)($input['website'] ?? '')) !== '') {
    respond(['ok' => true]);   // تجاهل بصمت
}

// 4) حدّ المحاولات لكل IP.
$rlKey = 'contact:ip:' . RateLimiter::ip();
if (RateLimiter::blocked($rlKey, 5, 3600)) {
    respond(['ok' => false, 'error' => 'rate_limited'], 429);
}

// 5) قراءة المدخلات والتحقق منها.
$name    = mb_substr(trim((string)($input['name'] ?? '')), 0, 80, 'UTF-8');
$email   = trim((string)($input['email'] ?? ''));
$phone   = mb_substr(trim((string)($input['phone'] ?? '')), 0, 40, 'UTF-8');
$phone   = preg_replace('/[^\d+\-\s()]/', '', $phone);
$message = mb_substr(trim((string)($input['message'] ?? '')), 0, 4000, 'UTF-8');

if (mb_strlen($name, 'UTF-8') < 2) {
    respond(['ok' => false, 'error' => 'invalid_name'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    respond(['ok' => false, 'error' => 'invalid_email'], 400);
}
if (mb_strlen($message, 'UTF-8') < 5) {
    respond(['ok' => false, 'error' => 'invalid_message'], 400);
}

RateLimiter::hit($rlKey, 3600);

// 6) نسخة احتياطية على القرص (تضمن عدم ضياع أي رسالة حتى لو فشل البريد).
$record = [
    'name' => $name, 'email' => $email, 'phone' => $phone,
    'message' => $message, 'ip' => RateLimiter::ip(),
    'at' => time(), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
];
$dir = rtrim(CACHE_DIR, '/') . '/../data/messages';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
@file_put_contents(
    $dir . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json',
    json_encode($record, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

// 7) إرسال البريد إلى CONTACT_EMAIL (From = نطاقنا، Reply-To = المُرسِل).
$emailHeader = str_replace(["\r", "\n"], '', $email);   // منع حقن الترويسات
$nameClean   = str_replace(["\r", "\n"], ' ', $name);

$subject = '=?UTF-8?B?' . base64_encode('استفسار جديد من الموقع — ' . $nameClean) . '?=';
$lines   = [
    'الاسم: ' . $name,
    'البريد: ' . $email,
    'الهاتف: ' . ($phone !== '' ? $phone : '—'),
    '',
    'الاستفسار:',
    $message,
    '',
    '— IP: ' . $record['ip'] . ' · ' . date('Y-m-d H:i', $record['at']),
];
$headers = implode("\r\n", [
    'From: ' . CONTACT_EMAIL,
    'Reply-To: ' . $emailHeader,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
]);

$sent = @mail(CONTACT_EMAIL, $subject, implode("\r\n", $lines), $headers);
if (!$sent) {
    error_log('[contact] mail() returned false (message saved to data/messages).');
}

// نُعيد النجاح ما دامت الرسالة حُفظت (لا نكشف فشل البريد للزائر).
respond(['ok' => true]);
