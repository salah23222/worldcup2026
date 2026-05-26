<?php
/**
 * api/league.php
 * ============================================================
 * واجهة "المجلس" (الدوريات الخاصّة). كل عمليات الكتابة محميّة بـCSRF
 * (رمز الترويسة X-CSRF يجب أن يطابق كوكي wc_csrf — نفس نمط api/predict.php).
 *
 *   POST action=create  {name, sponsor?}     → ينشئ دوريّة (يتطلب هوية/اسم)
 *   POST action=join     {code}              → الانضمام برمز
 *   POST action=leave    {id}                → مغادرة دوريّة
 *   POST action=rename   {id, name}          → إعادة تسمية (المالك فقط)
 *   GET  ?action=standings&id=...            → لوحة صدارة الدوريّة (JSON)
 * ============================================================
 *
 * ملاحظة: لا إخراج HTML قبل ضبط الكوكيز — لذا require ثم منطق مباشر.
 */
require __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$body   = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $body = $decoded;
}
$input  = $body + $_POST + $_GET;
$action = (string)($input['action'] ?? '');

/** يرسل JSON ويُنهي */
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// حماية CSRF لكل طلبات الكتابة (POST): الرمز من الترويسة فقط (لا من الجسم/الرابط).
if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? null;
    if (!Predictions::checkCsrf($token)) {
        respond(['ok' => false, 'error' => 'csrf'], 403);
    }
}

switch ($action) {

    case 'create':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        $res = Leagues::create(
            (string)($input['name'] ?? ''),
            isset($input['sponsor']) ? (string)$input['sponsor'] : null
        );
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'join':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        // حدّ معدّل محاولات الانضمام لكل IP (منع تخمين الرموز): 30 / ساعة.
        $rlKey = 'league_join:ip:' . RateLimiter::ip();
        if (RateLimiter::blocked($rlKey, 30, 3600)) {
            respond(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        RateLimiter::hit($rlKey, 3600);
        $res = Leagues::join((string)($input['code'] ?? ''));
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'leave':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        $res = Leagues::leave((string)($input['id'] ?? ''));
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'rename':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        $res = Leagues::rename(
            (string)($input['id'] ?? ''),
            (string)($input['name'] ?? '')
        );
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'standings':
        $id = (string)($input['id'] ?? '');
        $league = Leagues::byId($id);
        if ($league === null) respond(['ok' => false, 'error' => 'not_found'], 404);
        respond([
            'ok'      => true,
            'league'  => $league,
            'rows'    => Leagues::standings($id),
        ]);
        break;

    default:
        respond(['ok' => false, 'error' => 'bad_action'], 400);
}
