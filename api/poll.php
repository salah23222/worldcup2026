<?php
/**
 * api/poll.php
 * ============================================================
 * واجهة استطلاعات الجمهور (شاشة ثانية).
 *
 *   GET  ?action=current            → الاستطلاع النشط + الخيارات + العدّادات + هل صوّت الزائر
 *   POST  action=vote {pollId,option}→ تسجيل صوت واحد (يتطلب ترويسة X-CSRF + حدّ معدّل)
 *
 * كل عمليات الكتابة محميّة بـCSRF (نفس نمط api/predict.php) وبحدّ معدّل لكل IP.
 * لا إخراج HTML قبل ضبط الكوكيز → require ثم منطق مباشر.
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';

// قد لا يكون Polls مُحمّلاً من bootstrap بعد (يُضاف لاحقاً عبر التوصيل) → حمّله بأمان.
if (!class_exists('Polls')) {
    require_once __DIR__ . '/../includes/Polls.php';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$raw    = file_get_contents('php://input');
$body   = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $body = $decoded;
}
$input  = $body + $_POST + $_GET;
$action = $input['action'] ?? 'current';

/** يرسل JSON ويُنهي */
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// حماية CSRF لكل طلب كتابة: الرمز من الترويسة فقط (X-CSRF) = كوكي wc_csrf.
if ($method === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF'] ?? null;
    if (!Predictions::checkCsrf($token)) {
        respond(['ok' => false, 'error' => 'csrf'], 403);
    }
}

switch ($action) {

    case 'token':
        // رمز CSRF خفيف (لتصويت بطاقات المباريات) — بلا اختيار مباراة.
        respond(['ok' => true, 'csrf' => Predictions::ensureCsrf()]);
        break;

    case 'vote':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'method'], 405);

        // حدّ معدّل لكل IP (يمنع الإغراق الآلي): 30 تصويتاً كل 10 دقائق.
        $ipKey = 'poll_vote:' . RateLimiter::ip();
        if (RateLimiter::blocked($ipKey, 30, 600)) {
            respond(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $pollId = (string)($input['pollId'] ?? '');
        $option = (int)($input['option'] ?? -1);
        if (!Polls::validId($pollId)) {
            respond(['ok' => false, 'error' => 'bad_poll'], 400);
        }

        // نحلّ المعرّف عبر مباراته الحقيقية (يقبل أي بطاقة، لا «النشط» فقط)؛
        // يمنع التصويت على استطلاع مزوَّر أو مباراة منتهية/مقفلة.
        $built = Polls::resolve($pollId);
        if ($built === null) {
            respond(['ok' => false, 'error' => 'inactive'], 409);
        }
        if (!empty($built['closed'])) {
            respond(['ok' => false, 'error' => 'closed'], 409);
        }

        $res = Polls::vote($pollId, $option, count($built['options']));
        // نحسب المحاولة في حدّ المعدّل سواء نجحت أو لا (نمنع المسح المتكرر).
        RateLimiter::hit($ipKey, 600);

        if (!$res['ok'] && ($res['error'] ?? '') === 'already_voted') {
            // ليست خطأ حقيقياً للواجهة — نعيد العدّادات بنجاح مع علم voted.
            respond([
                'ok'     => true,
                'voted'  => true,
                'choice' => $res['choice'] ?? null,
                'counts' => $res['counts'] ?? [],
                'total'  => $res['total'] ?? 0,
            ]);
        }
        if (!$res['ok']) {
            respond(['ok' => false, 'error' => $res['error'] ?? 'error'], 400);
        }
        respond([
            'ok'     => true,
            'voted'  => true,
            'choice' => $res['choice'] ?? $option,
            'counts' => $res['counts'] ?? [],
            'total'  => $res['total'] ?? 0,
        ]);
        break;

    case 'current':
    default:
        $cur = Polls::current();
        if ($cur === null) {
            respond([
                'ok'   => true,
                'poll' => null,
                'csrf' => Predictions::ensureCsrf(),
            ]);
        }
        respond([
            'ok'   => true,
            'csrf' => Predictions::ensureCsrf(),
            'poll' => [
                'id'       => $cur['id'],
                'question' => $cur['question'],
                'options'  => $cur['options'],
                'counts'   => $cur['counts'],
                'total'    => $cur['total'],
                'voted'    => $cur['voted'],
                'choice'   => $cur['choice'],
                'closed'   => $cur['closed'],
            ],
        ]);
}
