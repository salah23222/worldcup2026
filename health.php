<?php
/**
 * health.php — تشخيص سريع للإنتاج (محمي بكلمة سر الأدمن).
 * يقيس زمن كل عملية حسّاسة لمعرفة سبب البطء/الـ504.
 * الاستخدام: health.php?key=ADMIN_PASS   (احذفه بعد التشخيص).
 */
require __DIR__ . '/includes/bootstrap.php';
while (ob_get_level() > 0) { ob_end_clean(); }

// بوّابة: كلمة سر الأدمن (لا يعمل علناً)
$key = (string)($_GET['key'] ?? '');
if (!defined('ADMIN_PASS') || ADMIN_PASS === '' || !hash_equals(ADMIN_PASS, $key)) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: application/json; charset=utf-8');
$out = [];

$out['php_version']   = PHP_VERSION;
$out['is_prod_branch'] = (DIRECTORY_SEPARATOR === '/');      // true على Hostinger
$out['site_url']      = defined('SITE_URL') ? SITE_URL : '';
$out['db_enabled']    = defined('DB_ENABLED') ? DB_ENABLED : null;
$out['ai_enabled']    = class_exists('AiContent') ? AiContent::enabled() : null;
$out['ai_activate_from'] = defined('AI_ACTIVATE_FROM') ? AI_ACTIVATE_FROM : '';
$out['fetch_timeout'] = defined('FETCH_TIMEOUT') ? FETCH_TIMEOUT : null;
$out['ai_timeout']    = defined('AI_TIMEOUT') ? AI_TIMEOUT : null;

// هل إصلاح Auth الجديد موجود؟ (كاش الجلسة wc_user)
$authSrc = @file_get_contents(__DIR__ . '/includes/Auth.php');
$out['auth_fix_present'] = ($authSrc !== false && strpos($authSrc, "wc_user") !== false);

// هل إصلاح DataService الجديد موجود؟ (الجالب الواحد)
$dsSrc = @file_get_contents(__DIR__ . '/includes/DataService.php');
$out['dataservice_fix_present'] = ($dsSrc !== false && strpos($dsSrc, 'iAmFetcher') !== false);

// هل Database فيه مهلة الاتصال؟
$dbSrc = @file_get_contents(__DIR__ . '/includes/Database.php');
$out['db_timeout_present'] = ($dbSrc !== false && strpos($dbSrc, 'ATTR_TIMEOUT') !== false);

// مجلد الكاش قابل للكتابة؟
$cf = rtrim(CACHE_DIR, '/') . '/_health_write_test.tmp';
$out['cache_writable'] = (@file_put_contents($cf, 'x') !== false);
@unlink($cf);
$out['cache_file_exists'] = is_file(rtrim(CACHE_DIR, '/') . '/worldcup2026.json');

// زمن اتصال قاعدة البيانات
$t = microtime(true);
$pdo = class_exists('Database') ? Database::pdo() : null;
$out['db_connect_seconds'] = round(microtime(true) - $t, 3);
$out['db_connected'] = ($pdo instanceof PDO);

// زمن تحميل بيانات المباريات (DataService)
$t = microtime(true);
$data = DataService::load();
$out['data_load_seconds'] = round(microtime(true) - $t, 3);
$out['matches_count'] = count($data['matches'] ?? []);

// زمن الوصول المباشر لـopenfootball من الخادم (مهلة 6ث)
$t = microtime(true);
$ok = false; $http = 0; $bytes = 0;
if (function_exists('curl_init')) {
    $ch = curl_init(DATA_SOURCE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'WC2026Health',
    ]);
    $r = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = ($r !== false); $bytes = strlen((string)$r);
}
$out['openfootball_seconds'] = round(microtime(true) - $t, 3);
$out['openfootball_http'] = $http;
$out['openfootball_bytes'] = $bytes;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
