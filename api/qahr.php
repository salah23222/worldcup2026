<?php
/**
 * api/qahr.php
 * ============================================================
 * واجهة «القهر» 😩💔 (JSON).
 *
 *   GET  ?action=roast&id=<matchIndex>&lang=ar  → {ok, roast, share, total}
 *       (قراءة فقط — لا CSRF، Cache-Control: no-store)
 *   POST  action=bump   (ترويسة X-CSRF)         → {ok, total}
 *       (محميّ بـCSRF + حدّ معدّل لكل IP لمنع تضخيم العدّاد)
 *
 * نمط respond() وCSRF مطابق لـ api/predict.php — لا يكسر شيئاً.
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
$action = (string)($input['action'] ?? 'roast');

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

$lang = in_array(($input['lang'] ?? ''), ['ar', 'en'], true) ? (string)$input['lang'] : current_lang();
$dialect = isset($input['dialect']) ? (string)$input['dialect'] : '';

switch ($action) {

    case 'roast':
        // قراءة فقط: يحلّ المباراة عبر الفهرس ويعيد سطر القهر + نصّ المشاركة + العدّاد.
        $id = (int)($input['id'] ?? -1);
        $m  = ($id >= 0) ? DataService::matchByIndex($id) : null;

        if ($m === null) {
            // لا مباراة: نعيد عدّاداً فقط (والمنادي لديه احتياطي).
            respond(['ok' => false, 'error' => 'match_not_found', 'total' => Qahr::total()], 404);
        }

        $team = trim($m['team1'] ?? '');
        $opp  = trim($m['team2'] ?? '');
        $g1 = $g2 = 0;
        if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
            $g1 = (int)$m['score']['ft'][0];
            $g2 = (int)$m['score']['ft'][1];
        }

        // الخاسر هو من نسخر من خسارته: إن خسر team2 نعكس الأطراف.
        $loser = $team; $winner = $opp; $lg = $g1; $wg = $g2;
        if ($g2 < $g1) { $loser = $opp; $winner = $team; $lg = $g2; $wg = $g1; }

        $roast = Qahr::roast($loser, $winner, $lg, $wg, $lang, $dialect);
        $share = Qahr::shareText($loser, $winner, $lg, $wg, $lang, $dialect);

        respond([
            'ok'    => true,
            'roast' => $roast,
            'share' => $share,
            'total' => Qahr::total(),
        ]);
        break;

    case 'bump':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);
        // حدّ معدّل لكل IP لمنع تضخيم العدّاد: 60 / ساعة.
        $rlKey = 'qahr_bump:ip:' . RateLimiter::ip();
        if (RateLimiter::blocked($rlKey, 60, 3600)) {
            respond(['ok' => false, 'error' => 'rate_limited', 'total' => Qahr::total()], 429);
        }
        RateLimiter::hit($rlKey, 3600);
        $total = Qahr::bump();
        respond(['ok' => true, 'total' => $total]);
        break;

    default:
        respond(['ok' => false, 'error' => 'unknown_action'], 400);
}
