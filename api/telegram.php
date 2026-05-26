<?php
/**
 * api/telegram.php — بوّابة (webhook) بوت تيليجرام — هيكل آمن ومعطّل افتراضياً.
 * ============================================================
 * الحالة: هيكل (SCAFFOLD). يبقى خاملاً 100% ما لم يُضبط رمز البوت.
 *   • بلا رمز  → 503 + {ok:false,error:'disabled'} ويخرج فوراً (لا اتصال خارجي،
 *               لا معالجة، لا خطر).
 *   • مع رمز   → webhook بسيط وآمن يردّ على أوامر قليلة.
 *
 * ------------------------------------------------------------
 *  خطوات التشغيل (عند الرغبة في تفعيل البوت لاحقاً):
 *  1) أنشئ بوتاً عبر @BotFather واحصل على الرمز (token).
 *  2) ضع في config.local.php (أو متغيّرات البيئة):
 *        'TELEGRAM_BOT_TOKEN'      => '123456:ABC...'
 *        'TELEGRAM_WEBHOOK_SECRET' => 'سرّ-عشوائي-طويل'
 *     (راجع قسم «التوصيل المطلوب» — تُضاف هذه الثوابت إلى includes/config.php.)
 *  3) سجّل الـwebhook لدى تيليجرام (مرّة واحدة) مع ترويسة السرّ:
 *        https://api.telegram.org/bot<token>/setWebhook
 *           ?url=https://YOURDOMAIN/api/telegram.php
 *           &secret_token=<TELEGRAM_WEBHOOK_SECRET>
 *     سيرسل تيليجرام بعدها الترويسة: X-Telegram-Bot-Api-Secret-Token
 *  4) للإيقاف: deleteWebhook، أو ببساطة أفرغ TELEGRAM_BOT_TOKEN.
 * ------------------------------------------------------------
 *  أوامر مدعومة: /start ، /today (مباراة اليوم) ، /trivia (رابط التحدّي).
 *  ملاحظة أمان: لا شيء فادح هنا أبداً — كل خطوة داخل تحقّق/حراسة، وأي فشل صامت.
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** رمز البوت (دفاعي): من ثابت معرّف، ثم من البيئة، وإلا فارغ = معطّل. */
$token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : (getenv('TELEGRAM_BOT_TOKEN') ?: '');
$token = is_string($token) ? trim($token) : '';

// ---------- معطّل: لا رمز → اخرج فوراً، خاملاً وآمناً ----------
if ($token === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'disabled'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- مفعّل: webhook صغير وآمن ----------

// 1) تيليجرام يرسل تحديثاته عبر POST فقط.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2) تحقّق من ترويسة السرّ (إن ضُبط سرّ) — يمنع استدعاء العنوان من جهات غير تيليجرام.
$secret = defined('TELEGRAM_WEBHOOK_SECRET') ? TELEGRAM_WEBHOOK_SECRET
        : (getenv('TELEGRAM_WEBHOOK_SECRET') ?: '');
$secret = is_string($secret) ? trim($secret) : '';
if ($secret !== '') {
    $got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!is_string($got) || !hash_equals($secret, $got)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 3) اقرأ التحديث (JSON) بأمان.
$raw    = file_get_contents('php://input');
$update = ($raw !== '' && $raw !== false) ? json_decode($raw, true) : null;
if (!is_array($update)) {
    // ردّ 200 حتى لا يعيد تيليجرام المحاولة على إدخال تالف.
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4) استخرج الرسالة والأمر.
$message = $update['message'] ?? $update['edited_message'] ?? [];
$chatId  = isset($message['chat']['id']) ? $message['chat']['id'] : null;
$text    = isset($message['text']) ? trim((string)$message['text']) : '';

if ($chatId === null || $text === '') {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// لغة الردّ: حسب لغة تيليجرام للمستخدم إن توفّرت، وإلا الافتراضية.
$tgLang = $message['from']['language_code'] ?? DEFAULT_LANG;
$ar = (strpos((string)$tgLang, 'ar') === 0);
$Lg = fn(string $a, string $e) => $ar ? $a : $e;

// أوّل كلمة = الأمر (نتجاهل لاحقة @BotName والمعطيات).
$cmd = strtolower(preg_replace('/[@\s].*$/', '', $text));

$base  = rtrim(SITE_URL, '/');
$reply = '';

switch ($cmd) {
    case '/start':
        $reply = $Lg(
            "أهلاً بك في بوت كأس العالم 2026! 🏆\n"
            . "الأوامر:\n/today — مباراة اليوم\n/trivia — تحدّي المعرفة اليومي",
            "Welcome to the World Cup 2026 bot! 🏆\n"
            . "Commands:\n/today — match of the day\n/trivia — daily trivia"
        );
        break;

    case '/today':
        $reply = tg_today_summary($Lg, $base, $ar);
        break;

    case '/trivia':
        $url = $base !== '' ? ($base . '/trivia.php') : '/trivia.php';
        $reply = $Lg("🧠 تحدّي المعرفة اليومي:\n", "🧠 Daily trivia:\n") . $url;
        break;

    default:
        $reply = $Lg("لم أفهم الأمر. جرّب: /today أو /trivia",
                     "Unknown command. Try: /today or /trivia");
}

if ($reply !== '') {
    tg_send($token, $chatId, $reply);
}

// ردّ 200 لتيليجرام (أنهينا المعالجة بنجاح).
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
exit;

// ============================================================
//  دوال مساعدة (محليّة لهذا الملف)
// ============================================================

/** يبني ملخّص مباراة اليوم/أقرب مباراة قادمة كنصّ للبوت. */
function tg_today_summary(callable $Lg, string $base, bool $ar): string
{
    $m = null;
    $todays = DataService::matchesOnDate(date('Y-m-d'));
    foreach ($todays as $cand) {
        if (($cand['_status'] ?? '') === 'live') { $m = $cand; break; }
    }
    if ($m === null && !empty($todays)) $m = $todays[0];
    if ($m === null) {
        $up = DataService::upcomingMatches(1);
        $m = $up[0] ?? null;
    }
    if ($m === null) {
        return $Lg("لا توجد مباراة مبرمجة الآن.", "No match scheduled right now.");
    }

    $t1 = team_name(trim($m['team1'] ?? ''));
    $t2 = team_name(trim($m['team2'] ?? ''));
    $ts = DataService::matchTimestamp($m);
    $when = $ts ? (fmt_date($ts) . ' · ' . fmt_time($ts)) : '';
    $ground = trim($m['ground'] ?? '');
    $link = $base !== '' ? ($base . '/match.php?id=' . (int)($m['_index'] ?? 0)) : '';

    $live = ($m['_status'] ?? '') === 'live';
    $head = $live ? $Lg('🔴 مباشر الآن:', '🔴 Live now:') : $Lg('⚽ مباراة اليوم:', '⚽ Match of the day:');

    $out  = $head . "\n" . $t1 . ' — ' . $t2;
    if ($when !== '')   { $out .= "\n🕒 " . $when; }
    if ($ground !== '') { $out .= "\n📍 " . $ground; }
    if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
        $out .= "\n" . $Lg('النتيجة: ', 'Score: ') . (int)$m['score']['ft'][0] . '-' . (int)$m['score']['ft'][1];
    }
    if ($link !== '') { $out .= "\n" . $link; }
    return $out;
}

/** يرسل رسالة عبر Bot API (آمن: لا يكسر شيئاً عند الفشل). */
function tg_send(string $token, $chatId, string $text): void
{
    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'disable_web_page_preview' => true,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => defined('FETCH_TIMEOUT') ? max(2, (int)FETCH_TIMEOUT) : 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        @curl_exec($ch);
        @curl_close($ch);
        return;
    }

    // لا cURL: محاولة بسيطة عبر stream (مقيّدة بمهلة).
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout'       => defined('FETCH_TIMEOUT') ? max(2, (int)FETCH_TIMEOUT) : 5,
        'ignore_errors' => true,
    ]]);
    @file_get_contents($url, false, $ctx);
}
