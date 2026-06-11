<?php
/**
 * Predictions.php
 * ============================================================
 * مسابقة التوقعات (نقاط افتراضية — بلا مال).
 *
 * نظام النقاط:
 *   - نتيجة مضبوطة تماماً        → 3 نقاط
 *   - الفائز الصحيح (نتيجة خاطئة) → 2 نقطة
 *   - تعادل صحيح (نتيجة خاطئة)    → 1 نقطة
 *   - غير ذلك                     → 0
 *
 * الهوية: اسم مستعار + معرّف عشوائي يُخزّن في كوكي (wc_uid).
 * التخزين: ملفات JSON تحت data/ (محميّة من الوصول المباشر) مع قفل (flock).
 *
 * إجراءات الأمان:
 *   - التحقق الصارم من المدخلات (الاسم، النتائج، رقم المباراة).
 *   - قفل التوقع تلقائياً عند انطلاق المباراة (لا تعديل بعد البداية).
 *   - حماية CSRF بنمط double-submit cookie.
 *   - كتابة ذرّية بقفل ملفات لتفادي تلف البيانات عند التزامن.
 *   - تأمين كل إخراج عبر e() في صفحات العرض.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Predictions
{
    private const COOKIE_UID  = 'wc_uid';
    private const COOKIE_CSRF = 'wc_csrf';
    private const NICK_MIN = 2;
    private const NICK_MAX = 20;
    private const SCORE_MAX = 30;      // أقصى عدد أهداف منطقي لكل فريق
    private const LB_CACHE_TTL = 30;   // كاش لوحة الصدارة بالثواني

    private static function dir(string $sub = ''): string
    {
        return rtrim(CACHE_DIR, '/') . '/../data/' . $sub;
    }

    // ====================================================
    //  وضع التخزين: قاعدة بيانات (حسابات) أو ملفات (اسم مستعار)
    // ====================================================

    /** هل نستخدم قاعدة البيانات؟ (مفعّلة + الاتصال ناجح) */
    private static function useDb(): bool
    {
        return Database::available();
    }

    /** معرّف المستخدم المسجَّل دخوله (في وضع قاعدة البيانات) أو null. */
    private static function dbUid(): ?int
    {
        if (!self::useDb()) {
            return null;
        }
        $u = Auth::user();
        return $u ? (int)$u['id'] : null;
    }

    // ====================================================
    //  الهوية
    // ====================================================

    /** معرّف المستخدم الحالي من الكوكي (أو null) */
    public static function uid(): ?string
    {
        $uid = $_COOKIE[self::COOKIE_UID] ?? '';
        return preg_match('/^[a-f0-9]{32}$/', $uid) ? $uid : null;
    }

    /** اللاعب الحالي. في وضع DB = الحساب المسجَّل؛ غير ذلك = صاحب الاسم المستعار. */
    public static function user(): ?array
    {
        if (self::useDb()) {
            $u = Auth::user();
            // الاسم المعروض في المسابقة = الاسم المعروض للحساب (لا بيانات شخصية).
            return $u ? ['uid' => 'u' . $u['id'], 'nickname' => $u['display_name']] : null;
        }
        $uid = self::uid();
        if ($uid === null) return null;
        $u = self::readJson(self::dir("users/{$uid}.json"));
        return !empty($u['nickname']) ? $u : null;
    }

    /**
     * register() — ينشئ مستخدماً جديداً باسم مستعار، أو يحدّث اسم الحالي.
     * يُرجّع ['ok'=>bool, 'error'=>?string, 'uid'=>?string, 'nickname'=>?string]
     * يجب استدعاؤها قبل إخراج أي HTML (تضبط كوكيز).
     */
    public static function register(string $nickname): array
    {
        $nickname = self::cleanNickname($nickname);
        if ($nickname === null) {
            return ['ok' => false, 'error' => 'invalid_nickname'];
        }

        $uid = self::uid() ?? bin2hex(random_bytes(16));
        $key = self::nameKey($nickname);

        // فهرس الأسماء لضمان عدم التكرار (مع قفل)
        $idxFile = self::dir('_names.json');
        $fp = self::lockFile($idxFile);
        if ($fp === null) {
            return ['ok' => false, 'error' => 'storage'];
        }
        $names = json_decode(stream_get_contents($fp) ?: '[]', true);
        if (!is_array($names)) $names = [];

        if (isset($names[$key]) && $names[$key] !== $uid) {
            self::unlockFile($fp);
            return ['ok' => false, 'error' => 'name_taken'];
        }

        // أزل الاسم القديم لهذا المستخدم إن غيّره
        foreach ($names as $k => $v) {
            if ($v === $uid && $k !== $key) unset($names[$k]);
        }
        $names[$key] = $uid;
        self::writeLocked($fp, $names);
        self::unlockFile($fp);

        // ملف المستخدم
        $existing = self::readJson(self::dir("users/{$uid}.json"));
        self::writeJson(self::dir("users/{$uid}.json"), [
            'uid'      => $uid,
            'nickname' => $nickname,
            'created'  => $existing['created'] ?? time(),
            'updated'  => time(),
        ]);

        // كوكيز: المعرّف (httpOnly) + رمز CSRF (متاح لـJS لإرساله في الترويسة)
        $csrf = self::ensureCsrf();
        self::setCookie(self::COOKIE_UID, $uid, true);

        return ['ok' => true, 'uid' => $uid, 'nickname' => $nickname, 'csrf' => $csrf];
    }

    /** ينظّف ويتحقق من الاسم المستعار. يُرجّع الاسم النظيف أو null */
    public static function cleanNickname(string $raw): ?string
    {
        $n = trim(preg_replace('/\s+/u', ' ', $raw));
        // أحرف عربية/لاتينية وأرقام ومسافة و _ - .
        if (!preg_match('/^[\p{Arabic}\p{L}0-9 _.\-]+$/u', $n)) return null;
        $len = mb_strlen($n, 'UTF-8');
        if ($len < self::NICK_MIN || $len > self::NICK_MAX) return null;
        return $n;
    }

    private static function nameKey(string $nickname): string
    {
        return mb_strtolower($nickname, 'UTF-8');
    }

    // ====================================================
    //  CSRF (double-submit cookie)
    // ====================================================

    public static function ensureCsrf(): string
    {
        $csrf = $_COOKIE[self::COOKIE_CSRF] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $csrf)) {
            $csrf = bin2hex(random_bytes(16));
            self::setCookie(self::COOKIE_CSRF, $csrf, false);
            $_COOKIE[self::COOKIE_CSRF] = $csrf;
        }
        return $csrf;
    }

    public static function checkCsrf(?string $token): bool
    {
        $cookie = $_COOKIE[self::COOKIE_CSRF] ?? '';
        return $token !== null
            && preg_match('/^[a-f0-9]{32}$/', $cookie)
            && hash_equals($cookie, (string)$token);
    }

    // ====================================================
    //  التوقعات
    // ====================================================

    /** كل توقعات المستخدم الحالي: [matchIndex => ['p1'=>int,'p2'=>int]] */
    public static function myPredictions(): array
    {
        if (self::useDb()) {
            $id = self::dbUid();
            if ($id === null) return [];
            try {
                $st = Database::pdo()->prepare(
                    'SELECT match_index, pred1, pred2 FROM predictions WHERE user_id = ?'
                );
                $st->execute([$id]);
                $out = [];
                foreach ($st as $r) {
                    $out[(string)(int)$r['match_index']] = [
                        'p1' => (int)$r['pred1'], 'p2' => (int)$r['pred2'],
                    ];
                }
                return $out;
            } catch (Throwable $e) {
                return [];
            }
        }
        $uid = self::uid();
        if ($uid === null) return [];
        $d = self::readJson(self::dir("preds/{$uid}.json"));
        return is_array($d) ? $d : [];
    }

    /**
     * save() — يحفظ توقّعاً واحداً. يُرجّع ['ok'=>bool,'error'=>?string]
     * يرفض إن: لا مستخدم / مباراة غير موجودة / انطلقت المباراة / مدخلات خاطئة.
     */
    public static function save(int $matchId, int $p1, int $p2): array
    {
        if ($p1 < 0 || $p2 < 0 || $p1 > self::SCORE_MAX || $p2 > self::SCORE_MAX) {
            return ['ok' => false, 'error' => 'invalid_score'];
        }
        $m = DataService::matchByIndex($matchId);
        if ($m === null) {
            return ['ok' => false, 'error' => 'match_not_found'];
        }
        if (self::isLocked($m)) {
            return ['ok' => false, 'error' => 'locked'];
        }

        // وضع قاعدة البيانات: توقّع مربوط بالحساب (توقّع واحد لكل مباراة).
        if (self::useDb()) {
            $id = self::dbUid();
            if ($id === null) {
                return ['ok' => false, 'error' => 'not_registered'];
            }
            $now = time();
            try {
                $st = Database::pdo()->prepare(
                    'INSERT INTO predictions (user_id, match_index, pred1, pred2, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE pred1 = VALUES(pred1), pred2 = VALUES(pred2), updated_at = VALUES(updated_at)'
                );
                $st->execute([$id, $matchId, $p1, $p2, $now, $now]);
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => 'storage'];
            }
            return ['ok' => true];
        }

        // وضع الملفات (اسم مستعار).
        $uid = self::uid();
        if ($uid === null || self::user() === null) {
            return ['ok' => false, 'error' => 'not_registered'];
        }
        $file = self::dir("preds/{$uid}.json");
        $fp = self::lockFile($file);
        if ($fp === null) return ['ok' => false, 'error' => 'storage'];
        $data = json_decode(stream_get_contents($fp) ?: '{}', true);
        if (!is_array($data)) $data = [];
        $data[(string)$matchId] = ['p1' => $p1, 'p2' => $p2, 'ts' => time()];
        self::writeLocked($fp, $data);
        self::unlockFile($fp);

        return ['ok' => true];
    }

    /** هل أُغلق التوقع؟ (انطلقت المباراة أو لها نتيجة) */
    public static function isLocked(array $m): bool
    {
        if (isset($m['score']['ft'])) return true;
        $ts = DataService::matchTimestamp($m);
        return ($ts !== null && time() >= $ts);
    }

    // ====================================================
    //  النقاط ولوحة الصدارة
    // ====================================================

    /** نقاط توقّع واحد مقابل نتيجة فعلية وفق نظام 3/2/1 */
    public static function scoreOne(int $p1, int $p2, int $a1, int $a2): int
    {
        if ($p1 === $a1 && $p2 === $a2) return 3;          // مضبوط
        $pd = $p1 <=> $p2;                                  // اتجاه التوقع
        $ad = $a1 <=> $a2;                                  // اتجاه النتيجة
        if ($pd === $ad) return ($pd === 0) ? 1 : 2;        // تعادل=1 / فائز=2
        return 0;
    }

    /**
     * leaderboard() — لوحة الصدارة العالمية.
     * كل صف: nickname, points, exact, correct, played
     */
    public static function leaderboard(int $limit = 100): array
    {
        // كاش قصير لتقليل المسح المتكرر
        $cacheFile = self::dir('_leaderboard.json');
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < self::LB_CACHE_TTL)) {
            $c = self::readJson($cacheFile);
            if (isset($c['rows'])) return array_slice($c['rows'], 0, $limit);
        }

        // نتائج المباريات المنتهية فقط: index => [a1,a2]
        $results = self::finishedResults();

        // نمرّ على كل المستخدمين (لإدراج من يلعب التوقعات و/أو تحدّي المعرفة)
        $rows = [];
        if (self::useDb()) {
            $pdo = Database::pdo();
            $users = $predsByUser = $trivByUser = [];
            try { $users = $pdo->query('SELECT id, display_name FROM users')->fetchAll(); } catch (Throwable $e) {}
            try {
                foreach ($pdo->query('SELECT user_id, match_index, pred1, pred2 FROM predictions') as $r) {
                    $predsByUser[(int)$r['user_id']][] = $r;
                }
            } catch (Throwable $e) {}
            try {
                foreach ($pdo->query('SELECT user_id, COALESCE(SUM(points),0) total FROM trivia_answers GROUP BY user_id') as $r) {
                    $trivByUser[(int)$r['user_id']] = (int)$r['total'];
                }
            } catch (Throwable $e) {}

            // خريطة نقاط الإحالة لكل user_id (best-effort — لا تكسر اللوحة لو فشل)
            $refByUser = class_exists('Referrals') ? Referrals::pointsByUserMap() : [];

            foreach ($users as $u) {
                $uid = (int)$u['id'];
                $points = $exact = $correct = $played = 0;
                foreach ($predsByUser[$uid] ?? [] as $p) {
                    $idx = (int)$p['match_index'];
                    if (!isset($results[$idx])) continue;
                    $played++;
                    $s = self::scoreOne((int)$p['pred1'], (int)$p['pred2'], $results[$idx][0], $results[$idx][1]);
                    $points += $s;
                    if ($s === 3) $exact++;
                    if ($s >= 2) $correct++;
                }
                $trivia    = $trivByUser[$uid] ?? 0;
                $referral  = $refByUser[$uid]  ?? 0;
                $points += $trivia + $referral;
                $rows[] = [
                    'nickname' => (string)$u['display_name'],
                    'points'   => $points, 'exact' => $exact,
                    'correct'  => $correct, 'played' => $played,
                    'trivia'   => $trivia,  'referral' => $referral,
                ];
            }
        } else {
            foreach (glob(self::dir('users') . '/*.json') ?: [] as $uf) {
                $uid = basename($uf, '.json');
                if (!preg_match('/^[a-f0-9]{32}$/', $uid)) continue;
                $user = self::readJson($uf);
                if (empty($user['nickname'])) continue;

                $preds = self::readJson(self::dir("preds/{$uid}.json"));
                $points = $exact = $correct = $played = 0;
                foreach ($preds as $idx => $p) {
                    $idx = (int)$idx;
                    if (!isset($results[$idx])) continue;   // لم تنتهِ بعد
                    $played++;
                    $s = self::scoreOne(
                        (int)($p['p1'] ?? -1), (int)($p['p2'] ?? -1),
                        $results[$idx][0], $results[$idx][1]
                    );
                    $points += $s;
                    if ($s === 3) $exact++;
                    if ($s >= 2) $correct++;
                }
                $trivia  = self::triviaTotal($uid);
                $points += $trivia;
                $rows[] = [
                    'nickname' => $user['nickname'],
                    'points'   => $points,
                    'exact'    => $exact,
                    'correct'  => $correct,
                    'played'   => $played,
                    'trivia'   => $trivia,
                ];
            }
        }

        // لاعب افتراضي: الذكاء الاصطناعي (نِدّ للمستخدمين)
        $aiRow = self::aiPlayer($results);
        if ($aiRow !== null) $rows[] = $aiRow;

        usort($rows, function ($a, $b) {
            if ($b['points'] !== $a['points']) return $b['points'] <=> $a['points'];
            if ($b['exact']  !== $a['exact'])  return $b['exact']  <=> $a['exact'];
            return strcmp($a['nickname'], $b['nickname']);
        });

        self::writeJson($cacheFile, ['rows' => $rows, 'at' => time()]);
        return array_slice($rows, 0, $limit);
    }

    /** صفّ الذكاء الاصطناعي في لوحة الصدارة (من توقّعاته المخزّنة) */
    private static function aiPlayer(array $results): ?array
    {
        $points = $exact = $correct = $played = 0;
        foreach (glob(self::dir('../cache/aipred_*.json')) ?: [] as $f) {
            if (!preg_match('/aipred_(\d+)\.json$/', $f, $mm)) continue;
            $idx = (int)$mm[1];
            if (!isset($results[$idx])) continue;
            $p = json_decode((string)@file_get_contents($f), true);
            if (!is_array($p) || !isset($p['p1'], $p['p2'])) continue;
            $played++;
            $s = self::scoreOne((int)$p['p1'], (int)$p['p2'], $results[$idx][0], $results[$idx][1]);
            $points += $s;
            if ($s === 3) $exact++;
            if ($s >= 2) $correct++;
        }
        // يظهر دائماً كنِدّ في المسابقة (حتى قبل بدء النتائج)
        return [
            'nickname' => '🤖 AI',
            'points'   => $points, 'exact' => $exact,
            'correct'  => $correct, 'played' => $played,
            'is_ai'    => true,
        ];
    }

    // ====================================================
    //  تحدّي المعرفة اليومي (3 نقاط للإجابة الصحيحة، مرة/يوم)
    // ====================================================

    /** مجموع نقاط تحدّي المعرفة لمستخدم */
    public static function triviaTotal(string $uid): int
    {
        $d = self::readJson(self::dir("triviapts/{$uid}.json"));
        return (int)($d['total'] ?? 0);
    }

    /** حالة تحدّي اليوم للمستخدم الحالي */
    public static function triviaInfo(): array
    {
        $today = date('Y-m-d');
        $out = ['registered' => false, 'answered_today' => false, 'chosen' => null, 'total' => 0];

        if (self::useDb()) {
            $id = self::dbUid();
            if ($id === null) return $out;
            $out['registered'] = true;
            try {
                $pdo = Database::pdo();
                $t = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM trivia_answers WHERE user_id = ?');
                $t->execute([$id]);
                $out['total'] = (int)$t->fetchColumn();
                $d = $pdo->prepare('SELECT chosen, points FROM trivia_answers WHERE user_id = ? AND day = ? LIMIT 1');
                $d->execute([$id, $today]);
                if ($row = $d->fetch()) {
                    $out['answered_today'] = true;
                    $out['chosen'] = (int)$row['chosen'];
                    $out['points'] = (int)$row['points'];
                }
            } catch (Throwable $e) {}
            return $out;
        }

        $uid = self::uid();
        if ($uid === null || self::user() === null) return $out;
        $out['registered'] = true;
        $d = self::readJson(self::dir("triviapts/{$uid}.json"));
        $out['total'] = (int)($d['total'] ?? 0);
        if (isset($d['days'][$today])) {
            $out['answered_today'] = true;
            $out['chosen'] = (int)($d['days'][$today]['chosen'] ?? -1);
            $out['points'] = (int)($d['days'][$today]['points'] ?? 0);
        }
        return $out;
    }

    /**
     * pointsActive() — هل بدأ احتساب نقاط المسابقة؟
     * لا تُحتسب أي نقاط قبل انطلاق البطولة، حتى يبدأ جميع المشاركين متساوين.
     * (التوقّعات أصلاً لا تُسجّل نقاطاً إلا على مباريات منتهية = أثناء البطولة.)
     */
    public static function pointsActive(): bool
    {
        return DataService::tournamentStarted();
    }

    /**
     * standingsByUser() — نقاط وترتيب كل مستخدم (لنشرة البريد). DB فقط.
     * يُرجّع [ user_id => ['points','rank','total','played','trivia'] ].
     */
    /**
     * 🆕 finishedResults() — نتائج المباريات «المنتهية» فقط: index => [a1,a2].
     * مهم: لا تُحتسب نقاط على مباراة جارية — نتيجة ESPN اللحظيّة تُكتب في
     * score.ft أثناء اللعب، والاحتساب على نتيجة جزئيّة يعطي نقاطاً خاطئة
     * مؤقتاً. فور صافرة النهاية (status=finished) تُحتسب تلقائياً خلال ≤30ث.
     */
    private static function finishedResults(): array
    {
        $results = [];
        foreach (DataService::allMatches() as $m) {
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;
            $st = $m['_status'] ?? DataService::matchStatus($m);
            if ($st !== 'finished') continue;
            $results[(int)$m['_index']] = [(int)$m['score']['ft'][0], (int)$m['score']['ft'][1]];
        }
        return $results;
    }

    public static function standingsByUser(): array
    {
        if (!self::useDb()) return [];

        $results = self::finishedResults();

        $pdo = Database::pdo();
        if ($pdo === null) return [];
        $users = $predsByUser = $trivByUser = [];
        try { $users = $pdo->query('SELECT id FROM users')->fetchAll(); } catch (Throwable $e) { return []; }
        try { foreach ($pdo->query('SELECT user_id, match_index, pred1, pred2 FROM predictions') as $r) { $predsByUser[(int)$r['user_id']][] = $r; } } catch (Throwable $e) {}
        try { foreach ($pdo->query('SELECT user_id, COALESCE(SUM(points),0) total FROM trivia_answers GROUP BY user_id') as $r) { $trivByUser[(int)$r['user_id']] = (int)$r['total']; } } catch (Throwable $e) {}

        $rows = [];
        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $points = $played = 0;
            foreach ($predsByUser[$uid] ?? [] as $p) {
                $idx = (int)$p['match_index'];
                if (!isset($results[$idx])) continue;
                $played++;
                $points += self::scoreOne((int)$p['pred1'], (int)$p['pred2'], $results[$idx][0], $results[$idx][1]);
            }
            $trivia = $trivByUser[$uid] ?? 0;
            $rows[] = ['uid' => $uid, 'points' => $points + $trivia, 'played' => $played, 'trivia' => $trivia];
        }

        // صفّ الذكاء الاصطناعي يُحتسب في الترتيب فقط (لا يُخزَّن)
        $ai = self::aiPlayer($results);
        $all = $rows;
        if ($ai !== null) $all[] = ['uid' => 0, 'points' => (int)($ai['points'] ?? 0), 'played' => 0, 'trivia' => 0];
        usort($all, fn($a, $b) => $b['points'] <=> $a['points']);

        $total = count($all);
        $map = [];
        $pos = 0;
        foreach ($all as $r) {
            $pos++;
            if ($r['uid'] === 0) continue;
            $map[$r['uid']] = [
                'points' => $r['points'], 'rank' => $pos, 'total' => $total,
                'played' => $r['played'], 'trivia' => $r['trivia'],
            ];
        }
        return $map;
    }

    /**
     * recordTrivia() — يسجّل إجابة اليوم (مرة واحدة) ويمنح 3 نقاط إن صحّت
     * (شرط أن تكون البطولة قد انطلقت — قبلها يُسجَّل الجواب بلا نقاط لتحقيق المساواة).
     * يُرجّع ['awarded'=>bool,'points'=>int,'total'=>int,'already'=>bool]
     */
    public static function recordTrivia(int $chosen, int $correctIndex): array
    {
        $today = date('Y-m-d');

        if (self::useDb()) {
            $id = self::dbUid();
            if ($id === null) {
                return ['awarded' => false, 'points' => 0, 'total' => 0];
            }
            $pdo = Database::pdo();
            $sumFn = function () use ($pdo, $id): int {
                $s = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM trivia_answers WHERE user_id = ?');
                $s->execute([$id]);
                return (int)$s->fetchColumn();
            };
            try {
                $c = $pdo->prepare('SELECT points FROM trivia_answers WHERE user_id = ? AND day = ? LIMIT 1');
                $c->execute([$id, $today]);
                if ($ex = $c->fetch()) {   // أجاب اليوم بالفعل
                    return ['awarded' => false, 'already' => true,
                            'points' => (int)$ex['points'], 'total' => $sumFn()];
                }
                $pts = ($chosen === $correctIndex && self::pointsActive()) ? 3 : 0;
                $ins = $pdo->prepare(
                    'INSERT INTO trivia_answers (user_id, day, chosen, points, created_at) VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([$id, $today, $chosen, $pts, time()]);
                return ['awarded' => true, 'points' => $pts, 'total' => $sumFn()];
            } catch (Throwable $e) {
                // تسابق على المفتاح الفريد (user_id, day) → عُدّ كأنه أجاب.
                return ['awarded' => false, 'already' => true, 'points' => 0, 'total' => $sumFn()];
            }
        }

        $uid = self::uid();
        if ($uid === null || self::user() === null) {
            return ['awarded' => false, 'points' => 0, 'total' => 0];
        }
        $file  = self::dir("triviapts/{$uid}.json");
        $fp = self::lockFile($file);
        if ($fp === null) return ['awarded' => false, 'points' => 0, 'total' => 0];
        $d = json_decode(stream_get_contents($fp) ?: '{}', true);
        if (!is_array($d)) $d = [];

        if (isset($d['days'][$today])) {  // أجاب اليوم بالفعل
            self::unlockFile($fp);
            return ['awarded' => false, 'already' => true,
                    'points' => (int)$d['days'][$today]['points'], 'total' => (int)($d['total'] ?? 0)];
        }
        $pts = ($chosen === $correctIndex) ? 3 : 0;
        $d['days'][$today] = ['chosen' => $chosen, 'points' => $pts];
        $d['total'] = (int)($d['total'] ?? 0) + $pts;
        self::writeLocked($fp, $d);
        self::unlockFile($fp);
        return ['awarded' => true, 'points' => $pts, 'total' => $d['total']];
    }

    /** عدد المشاركين الكلي (ملفات بمعرّفات صحيحة فقط) */
    public static function playerCount(): int
    {
        if (self::useDb()) {
            try {
                return (int)Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        }
        $n = 0;
        foreach (glob(self::dir('users') . '/*.json') ?: [] as $f) {
            if (preg_match('/^[a-f0-9]{32}$/', basename($f, '.json'))) $n++;
        }
        return $n;
    }

    // ====================================================
    //  أدوات تخزين منخفضة المستوى
    // ====================================================

    private static function ensureDir(string $file): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
    }

    private static function readJson(string $file): array
    {
        if (!is_file($file)) return [];
        $fp = @fopen($file, 'rb');
        if (!$fp) return [];
        @flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
        $d = json_decode((string)$raw, true);
        return is_array($d) ? $d : [];
    }

    private static function writeJson(string $file, array $data): bool
    {
        self::ensureDir($file);
        $fp = @fopen($file, 'c+b');
        if (!$fp) return false;
        @flock($fp, LOCK_EX);
        self::writeLocked($fp, $data);
        @flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    /** يفتح ملفاً ويقفله حصرياً ويعيد المؤشّر (للقراءة-التعديل-الكتابة) */
    private static function lockFile(string $file)
    {
        self::ensureDir($file);
        $fp = @fopen($file, 'c+b');
        if (!$fp) return null;
        if (!@flock($fp, LOCK_EX)) { fclose($fp); return null; }
        rewind($fp);
        return $fp;
    }

    private static function writeLocked($fp, array $data): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }

    private static function unlockFile($fp): void
    {
        @flock($fp, LOCK_UN);
        fclose($fp);
    }

    /** يضبط كوكي بإعدادات أمان مناسبة */
    private static function setCookie(string $name, string $value, bool $httpOnly): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie($name, $value, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => $httpOnly,
            'samesite' => 'Lax',
        ]);
    }
}
