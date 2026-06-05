<?php
/**
 * api/predict.php
 * ============================================================
 * واجهة مسابقة التوقعات. كل عمليات الكتابة محميّة بـCSRF،
 * ومقفولة تلقائياً عند انطلاق المباراة.
 *
 *   GET  ?action=me                         → بيانات المستخدم + توقعاته + رمز CSRF
 *   POST  action=register  {nickname}       → تسجيل/تحديث الاسم المستعار
 *   POST  action=save      {id,p1,p2}       → حفظ توقّع (يتطلب ترويسة X-CSRF)
 *   GET  ?action=leaderboard&limit=N        → لوحة الصدارة
 * ============================================================
 *
 * ملاحظة: لا إخراج HTML قبل ضبط الكوكيز — لذا require ثم منطق مباشر.
 */
require __DIR__ . '/../includes/bootstrap.php';

// نقرأ مدخلات JSON أو نموذج عادي
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$body   = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $body = $decoded;
}
$input  = $body + $_POST + $_GET;
$action = $input['action'] ?? 'me';

/** يرسل JSON ويُنهي */
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// حماية CSRF لكل طلبات الكتابة (POST): رمز الترويسة (X-CSRF) يجب أن يطابق كوكي wc_csrf.
// نقبل الرمز من الترويسة فقط (لا من الجسم/الرابط) لمنع التزوير عبر نموذج خارجي.
if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? null;
    if (!Predictions::checkCsrf($token)) {
        respond(['ok' => false, 'error' => 'csrf'], 403);
    }
}

switch ($action) {

    case 'register':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        // في وضع الحسابات (قاعدة البيانات) التسجيل يتم عبر register.php/login.php.
        if (Database::available()) respond(['ok' => false, 'error' => 'login_required'], 403);
        $res = Predictions::register((string)($input['nickname'] ?? ''));
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'save':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        $res = Predictions::save(
            (int)($input['id'] ?? -1),
            (int)($input['p1'] ?? -1),
            (int)($input['p2'] ?? -1)
        );
        respond($res, $res['ok'] ? 200 : 400);
        break;

    case 'trivia':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        $lang = in_array(($input['lang'] ?? ''), ['ar', 'en', 'fr'], true) ? $input['lang'] : current_lang();
        $q = AiContent::dailyTrivia($lang);
        if (!$q) respond(['ok' => false, 'error' => 'no_trivia'], 400);
        $chosen = (int)($input['index'] ?? -1);
        if ($chosen < 0 || $chosen > 3) respond(['ok' => false, 'error' => 'bad_index'], 400);
        $correct = (int)$q['correct'];
        $rec = Predictions::recordTrivia($chosen, $correct);
        respond([
            'ok'           => true,
            'correct'      => ($chosen === $correct),
            'correctIndex' => $correct,
            'explain'      => $q['explain'] ?? '',
            'registered'   => (Predictions::user() !== null),
            'awarded'      => $rec['awarded'] ?? false,
            'points'       => $rec['points'] ?? 0,
            'total'        => $rec['total'] ?? 0,
        ]);
        break;

    case 'leaderboard':
        $limit = max(1, min(200, (int)($input['limit'] ?? 100)));
        respond([
            'ok'      => true,
            'players' => Predictions::playerCount(),
            'rows'    => Predictions::leaderboard($limit),
        ]);
        break;

    case 'me':
    default:
        $u = Predictions::user();
        respond([
            'ok'          => true,
            'registered'  => $u !== null,
            'nickname'    => $u['nickname'] ?? null,
            'csrf'        => Predictions::ensureCsrf(),
            'predictions' => Predictions::myPredictions(),
        ]);
}
