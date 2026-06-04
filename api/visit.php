<?php
/**
 * api/visit.php — عدّاد زوّار قويّ ضدّ رفع الملفات.
 * ============================================================
 * GET → يزيد العدّاد مرّة واحدة لكل زائر (كوكي 6 ساعات)، ويُرجّع الإجمالي.
 *        يبدأ من قاعدة مبدئية VISIT_BASE.
 *
 * التخزين (بالأولوية):
 *   1) MySQL: جدول site_counters → لا يُمسح عند رفع/مزامنة الملفات. مُفضَّل.
 *   2) ملف:  data/_visits.json (احتياطي — يستخدم لو DB معطّلة).
 *
 * الرد بلا تخزين (no-store) ليُحدَّث في كل تحميل حتى لو الـHTML من PageCache.
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const VISIT_BASE   = 41000;          // القاعدة المبدئية للعدّاد
const COUNTER_NAME = 'site_visits';

$alreadyCounted = isset($_COOKIE['wc_seen']);

// ============================================================
//  مسار 1) قاعدة البيانات (مُفضَّل — لا يُمسح برفع الملفات)
// ============================================================
function visit_db_init(PDO $pdo): bool
{
    static $ok = false;
    if ($ok) return true;
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `site_counters` (
              `name`       VARCHAR(40) PRIMARY KEY,
              `value`      BIGINT UNSIGNED NOT NULL DEFAULT 0,
              `updated_at` INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $ok = true;
        return true;
    } catch (Throwable $e) { return false; }
}

function visit_db_read(PDO $pdo): int
{
    if (!visit_db_init($pdo)) return VISIT_BASE;
    try {
        $st = $pdo->prepare('SELECT value FROM site_counters WHERE name = ? LIMIT 1');
        $st->execute([COUNTER_NAME]);
        $r = $st->fetch();
        return $r ? max((int)$r['value'], VISIT_BASE) : VISIT_BASE;
    } catch (Throwable $e) { return VISIT_BASE; }
}

function visit_db_increment(PDO $pdo): int
{
    if (!visit_db_init($pdo)) return VISIT_BASE;
    try {
        // INSERT-ON-DUPLICATE-KEY-UPDATE: ذرّيّ وسريع.
        // GREATEST(value+1, BASE+1) يضمن أنّنا فوق القاعدة ولا نسمح بتراجع.
        $st = $pdo->prepare(
            'INSERT INTO site_counters (name, value, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
               value      = GREATEST(value + 1, ?),
               updated_at = VALUES(updated_at)'
        );
        $now = time();
        $st->execute([COUNTER_NAME, VISIT_BASE + 1, $now, VISIT_BASE + 1]);
        return visit_db_read($pdo);
    } catch (Throwable $e) { return VISIT_BASE; }
}

// ============================================================
//  مسار 2) ملف (احتياط)
// ============================================================
function visit_file_read(string $file): int
{
    $d = json_decode((string)@file_get_contents($file), true);
    $c = (is_array($d) && isset($d['count'])) ? (int)$d['count'] : 0;
    return max($c, VISIT_BASE);
}

function visit_file_increment(string $file): int
{
    $dir = dirname($file);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $fp = @fopen($file, 'c+b');
    if (!$fp) return visit_file_read($file);
    @flock($fp, LOCK_EX);
    rewind($fp);
    $d   = json_decode((string)stream_get_contents($fp), true);
    $cur = (is_array($d) && isset($d['count'])) ? (int)$d['count'] : 0;
    if ($cur < VISIT_BASE) { $cur = VISIT_BASE; }
    $cur++;
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode(['count' => $cur]));
    fflush($fp);
    @flock($fp, LOCK_UN);
    fclose($fp);
    return $cur;
}

// ============================================================
//  التنفيذ: DB أولاً، ملف لو فشل
// ============================================================
$pdo   = class_exists('Database') ? Database::pdo() : null;
$file  = rtrim(CACHE_DIR, '/') . '/../data/_visits.json';
$count = 0;

if ($pdo instanceof PDO) {
    $count = $alreadyCounted ? visit_db_read($pdo) : visit_db_increment($pdo);
} else {
    $count = $alreadyCounted ? visit_file_read($file) : visit_file_increment($file);
}

// كوكي عدم العدّ المتكرّر (6 ساعات)
if (!$alreadyCounted && !headers_sent()) {
    setcookie('wc_seen', '1', [
        'expires'  => time() + 6 * 3600,
        'path'     => '/',
        'secure'   => (stripos((string)SITE_URL, 'https://') === 0),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE);
