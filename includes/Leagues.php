<?php
/**
 * Leagues.php — "المجلس": دوريات توقّع خاصة بين الأصدقاء/العائلة/الزملاء.
 * ============================================================
 * ينشئ المستخدم دوريّة خاصّة فيحصل على رمز مشاركة + رابط دعوة. ينضمّ الآخرون
 * بالرمز. لكل دوريّة لوحة صدارة خاصّة تُحتسب من توقّعات أعضائها الموجودة أصلاً
 * (نعيد استخدام نظام نقاط Predictions — لا نخترع نقاطاً جديدة).
 *
 * التخزين: ملفات JSON تحت data/leagues/<id>.json (id = bin2hex(16 hex))
 * مع فهرس code→id في data/leagues/_index.json. كل كتابة ذرّية بقفل flock
 * (نمط RateLimiter/Predictions) لتفادي التلف عند التزامن.
 *
 * الأمان: تحقّق صارم من المدخلات، حدّ للأعضاء والدوريات لكل مستخدم،
 * حدّ معدّل لإنشاء/انضمام عبر RateLimiter، ولا نكشف بيانات شخصية —
 * فقط الاسم المستعار + إحصاءات اللوحة.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Leagues
{
    private const NAME_MIN        = 2;
    private const NAME_MAX        = 40;
    private const MAX_MEMBERS     = 5000;   // سقف أعضاء الدوريّة الواحدة
    private const MAX_PER_USER    = 20;     // سقف الدوريات التي يملك/يشارك بها المستخدم
    private const CODE_LEN        = 6;
    private const LB_CACHE_TTL    = 30;     // كاش لوحة الدوريّة بالثواني (كما في Predictions)
    // أحرف الرمز: بلا 0/O/1/I لتفادي الالتباس عند المشاركة.
    private const CODE_ALPHABET   = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    // ====================================================
    //  المسارات
    // ====================================================

    private static function baseDir(): string
    {
        // نفس جذر بيانات Predictions: cache/../data/leagues
        return rtrim(CACHE_DIR, '/') . '/../data/leagues';
    }

    private static function leagueFile(string $id): string
    {
        return self::baseDir() . '/' . $id . '.json';
    }

    private static function indexFile(): string
    {
        return self::baseDir() . '/_index.json';
    }

    private static function ensureDir(): void
    {
        $d = self::baseDir();
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        // ملف حماية احتياطي في حال لم يرث مجلد data/ قاعدة المنع.
        $ht = $d . '/.htaccess';
        if (!is_file($ht)) { @file_put_contents($ht, "Require all denied\n"); }
    }

    // ====================================================
    //  الهوية + التحقق من المدخلات
    // ====================================================

    /** معرّف المستخدم الحالي (كوكي أو حساب DB) أو null إن لا هوية بعد. */
    private static function uid(): ?string
    {
        $u = Predictions::user();
        if ($u === null || empty($u['uid'])) return null;
        return (string)$u['uid'];
    }

    /** الاسم المعروض للمستخدم الحالي (كما يستخدمه باقي الموقع). */
    private static function myNickname(): string
    {
        $u = Predictions::user();
        return $u['nickname'] ?? '';
    }

    /** ينظّف ويتحقق من اسم الدوريّة (مثل قاعدة الاسم المستعار: عربي/لاتيني/أرقام/مسافة/_-.). */
    public static function cleanName(string $raw): ?string
    {
        $n = trim(preg_replace('/\s+/u', ' ', $raw));
        if ($n === '' || !preg_match('/^[\p{Arabic}\p{L}0-9 _.\-]+$/u', $n)) return null;
        $len = mb_strlen($n, 'UTF-8');
        if ($len < self::NAME_MIN || $len > self::NAME_MAX) return null;
        return $n;
    }

    /** ينظّف نصّ الراعي الاختياري (للمنتجة لاحقاً). يُرجّع نصّاً قصيراً أو null. */
    private static function cleanSponsor(?string $raw): ?string
    {
        if ($raw === null) return null;
        $s = trim(preg_replace('/\s+/u', ' ', $raw));
        if ($s === '') return null;
        if (mb_strlen($s, 'UTF-8') > 60) $s = mb_substr($s, 0, 60, 'UTF-8');
        return $s;
    }

    /** هل الرمز بالصيغة الصحيحة؟ (6 أحرف A–Z0–9) */
    public static function validCode(string $code): bool
    {
        return (bool)preg_match('/^[A-Z0-9]{' . self::CODE_LEN . '}$/', $code);
    }

    /** يطبّع رمزاً مُدخلاً: قصّ + كبيرة. */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private static function validId(string $id): bool
    {
        return (bool)preg_match('/^[a-f0-9]{16}$/', $id);
    }

    // ====================================================
    //  العمليات العامّة
    // ====================================================

    /**
     * create() — ينشئ دوريّة جديدة. المالك = المستخدم الحالي.
     * يُرجّع ['ok'=>true,'league'=>..] أو ['ok'=>false,'error'=>..].
     */
    public static function create(string $name, ?string $sponsor = null): array
    {
        $uid = self::uid();
        if ($uid === null) {
            return ['ok' => false, 'error' => 'login_required'];
        }

        $name = self::cleanName($name);
        if ($name === null) {
            return ['ok' => false, 'error' => 'invalid_name'];
        }

        // حدّ معدّل الإنشاء لكل IP (10 / ساعة) لمنع الإساءة.
        $rlKey = 'league_create:ip:' . RateLimiter::ip();
        if (RateLimiter::blocked($rlKey, 10, 3600)) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }

        // سقف الدوريات لكل مستخدم.
        if (count(self::myLeagues()) >= self::MAX_PER_USER) {
            return ['ok' => false, 'error' => 'too_many'];
        }

        self::ensureDir();

        $id = bin2hex(random_bytes(8));               // 16 hex
        // رمز فريد عبر الفهرس (مع قفل). نحاول عدّة مرّات لتفادي التصادم.
        $idxFp = self::lock(self::indexFile());
        if ($idxFp === null) return ['ok' => false, 'error' => 'storage'];
        $index = self::readLocked($idxFp);

        $code = null;
        for ($i = 0; $i < 12; $i++) {
            $cand = self::randomCode();
            if (!isset($index[$cand])) { $code = $cand; break; }
        }
        if ($code === null) {
            self::unlock($idxFp);
            return ['ok' => false, 'error' => 'storage'];
        }

        $now = time();
        $league = [
            'id'      => $id,
            'name'    => $name,
            'code'    => $code,
            'owner'   => $uid,
            'sponsor' => self::cleanSponsor($sponsor),
            'members' => [$uid => $now],
            'created' => $now,
        ];

        // اكتب ملف الدوريّة أولاً، ثم أضِف الفهرس (وإلا الفهرس يشير إلى ملف ناقص).
        if (!self::writeJson(self::leagueFile($id), $league)) {
            self::unlock($idxFp);
            return ['ok' => false, 'error' => 'storage'];
        }
        $index[$code] = $id;
        self::writeLocked($idxFp, $index);
        self::unlock($idxFp);

        RateLimiter::hit($rlKey, 3600);

        return ['ok' => true, 'league' => self::publicView($league)];
    }

    /**
     * join() — يضيف المستخدم الحالي لعضوية دوريّة عبر رمزها.
     * يُرجّع ['ok'=>true,'league'=>..] أو ['ok'=>false,'error'=>..].
     */
    public static function join(string $code): array
    {
        $uid = self::uid();
        if ($uid === null) {
            return ['ok' => false, 'error' => 'login_required'];
        }
        $code = self::normalizeCode($code);
        if (!self::validCode($code)) {
            return ['ok' => false, 'error' => 'invalid_code'];
        }

        $id = self::idForCode($code);
        if ($id === null) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        // سقف الدوريات لكل مستخدم (يُحتسب الانضمام).
        if (count(self::myLeagues()) >= self::MAX_PER_USER) {
            return ['ok' => false, 'error' => 'too_many'];
        }

        $file = self::leagueFile($id);
        $fp = self::lock($file);
        if ($fp === null) return ['ok' => false, 'error' => 'storage'];
        $league = self::readLocked($fp);
        if (empty($league['id'])) {
            self::unlock($fp);
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (!isset($league['members']) || !is_array($league['members'])) {
            $league['members'] = [];
        }
        if (!isset($league['members'][$uid])) {
            if (count($league['members']) >= self::MAX_MEMBERS) {
                self::unlock($fp);
                return ['ok' => false, 'error' => 'full'];
            }
            $league['members'][$uid] = time();
            self::writeLocked($fp, $league);
        }
        self::unlock($fp);

        return ['ok' => true, 'league' => self::publicView($league)];
    }

    /**
     * leave() — يزيل المستخدم الحالي من عضوية دوريّة.
     * المالك لا يستطيع المغادرة (لتبقى الدوريّة بمالك) — يُرجّع خطأ owner_leave.
     */
    public static function leave(string $id): array
    {
        $uid = self::uid();
        if ($uid === null) return ['ok' => false, 'error' => 'login_required'];
        if (!self::validId($id)) return ['ok' => false, 'error' => 'not_found'];

        $file = self::leagueFile($id);
        $fp = self::lock($file);
        if ($fp === null) return ['ok' => false, 'error' => 'storage'];
        $league = self::readLocked($fp);
        if (empty($league['id'])) {
            self::unlock($fp);
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (($league['owner'] ?? '') === $uid) {
            self::unlock($fp);
            return ['ok' => false, 'error' => 'owner_leave'];
        }
        if (isset($league['members'][$uid])) {
            unset($league['members'][$uid]);
            self::writeLocked($fp, $league);
        }
        self::unlock($fp);
        return ['ok' => true];
    }

    /**
     * rename() — يغيّر اسم الدوريّة (المالك فقط).
     */
    public static function rename(string $id, string $name): array
    {
        $uid = self::uid();
        if ($uid === null) return ['ok' => false, 'error' => 'login_required'];
        if (!self::validId($id)) return ['ok' => false, 'error' => 'not_found'];
        $name = self::cleanName($name);
        if ($name === null) return ['ok' => false, 'error' => 'invalid_name'];

        $file = self::leagueFile($id);
        $fp = self::lock($file);
        if ($fp === null) return ['ok' => false, 'error' => 'storage'];
        $league = self::readLocked($fp);
        if (empty($league['id'])) {
            self::unlock($fp);
            return ['ok' => false, 'error' => 'not_found'];
        }
        if (($league['owner'] ?? '') !== $uid) {
            self::unlock($fp);
            return ['ok' => false, 'error' => 'forbidden'];
        }
        $league['name'] = $name;
        self::writeLocked($fp, $league);
        self::unlock($fp);
        return ['ok' => true, 'league' => self::publicView($league)];
    }

    // ====================================================
    //  القراءة
    // ====================================================

    /** يُرجّع الدوريّة بالرمز (نسخة عرض عامّة) أو null. */
    public static function byCode(string $code): ?array
    {
        $code = self::normalizeCode($code);
        if (!self::validCode($code)) return null;
        $id = self::idForCode($code);
        if ($id === null) return null;
        return self::byId($id);
    }

    /** يُرجّع الدوريّة بالمعرّف (نسخة عرض عامّة) أو null. */
    public static function byId(string $id): ?array
    {
        if (!self::validId($id)) return null;
        $league = self::readJson(self::leagueFile($id));
        if (empty($league['id'])) return null;
        return self::publicView($league);
    }

    /** الدوريّة الخام (بقائمة الأعضاء الكاملة) — للاستخدام الداخلي فقط. */
    private static function rawById(string $id): ?array
    {
        if (!self::validId($id)) return null;
        $league = self::readJson(self::leagueFile($id));
        return empty($league['id']) ? null : $league;
    }

    /** هل المستخدم الحالي عضو في الدوريّة المعطاة (بالمعرّف)؟ */
    public static function isMember(string $id): bool
    {
        $uid = self::uid();
        if ($uid === null) return false;
        $raw = self::rawById($id);
        return $raw !== null && isset($raw['members'][$uid]);
    }

    /** هل المستخدم الحالي مالك الدوريّة؟ */
    public static function isOwner(string $id): bool
    {
        $uid = self::uid();
        if ($uid === null) return false;
        $raw = self::rawById($id);
        return $raw !== null && ($raw['owner'] ?? '') === $uid;
    }

    /** كل دوريات المستخدم الحالي (نُسخ عرض عامّة)، الأحدث أولاً. */
    public static function myLeagues(): array
    {
        $uid = self::uid();
        if ($uid === null) return [];
        $out = [];
        foreach (glob(self::baseDir() . '/*.json') ?: [] as $f) {
            $base = basename($f, '.json');
            if (!self::validId($base)) continue;       // يتخطّى _index.json
            $league = self::readJson($f);
            if (empty($league['id'])) continue;
            if (isset($league['members'][$uid])) {
                $out[] = self::publicView($league);
            }
        }
        usort($out, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));
        return $out;
    }

    /**
     * standings() — لوحة صدارة الدوريّة: نقاط كل عضو مُحتسبة بإعادة استخدام
     * منطق نقاط Predictions، مرتّبة تنازلياً. صفّ: [nickname, points, exact, correct, played].
     * كاش قصير لكل دوريّة (~30ث) عبر ملف _standings_<id>.json حسب mtime.
     */
    public static function standings(string $id): array
    {
        if (!self::validId($id)) return [];

        $cacheFile = self::baseDir() . '/_standings_' . $id . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile) < self::LB_CACHE_TTL)) {
            $c = self::readJson($cacheFile);
            if (isset($c['rows']) && is_array($c['rows'])) return $c['rows'];
        }

        $raw = self::rawById($id);
        if ($raw === null) return [];
        $memberUids = array_keys($raw['members'] ?? []);
        if (!$memberUids) return [];

        // نتائج المباريات المنتهية: index => [a1,a2] (نفس مصدر Predictions::leaderboard).
        $results = [];
        foreach (DataService::allMatches() as $m) {
            if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
                $results[(int)$m['_index']] = [
                    (int)$m['score']['ft'][0], (int)$m['score']['ft'][1],
                ];
            }
        }

        // نأخذ اللوحة العالمية كاملة (مكشّفة عبر كاش Predictions) ونرشّحها لأعضائنا
        // عبر مطابقة الاسم المعروض — لكن لتفادي التصادم في الأسماء نحسب مباشرةً
        // من توقّعات كل عضو حسب وضع التخزين (DB أو ملفات)، مع إعادة استخدام scoreOne.
        $rows = [];
        foreach ($memberUids as $uid) {
            $nick = self::nicknameForUid((string)$uid);
            if ($nick === null) continue;     // عضو بلا اسم/حذف حسابه → نتخطّاه
            $preds = self::predsForUid((string)$uid);
            $points = $exact = $correct = $played = 0;
            foreach ($preds as $idx => $p) {
                $idx = (int)$idx;
                if (!isset($results[$idx])) continue;
                $played++;
                $s = Predictions::scoreOne(
                    (int)($p['p1'] ?? -1), (int)($p['p2'] ?? -1),
                    $results[$idx][0], $results[$idx][1]
                );
                $points += $s;
                if ($s === 3) $exact++;
                if ($s >= 2) $correct++;
            }
            $rows[] = [
                'nickname' => $nick,
                'points'   => $points,
                'exact'    => $exact,
                'correct'  => $correct,
                'played'   => $played,
            ];
        }

        usort($rows, function ($a, $b) {
            if ($b['points'] !== $a['points']) return $b['points'] <=> $a['points'];
            if ($b['exact']  !== $a['exact'])  return $b['exact']  <=> $a['exact'];
            return strcmp($a['nickname'], $b['nickname']);
        });

        self::writeJson($cacheFile, ['rows' => $rows, 'at' => time()]);
        return $rows;
    }

    // ====================================================
    //  جسور قراءة توقّعات/أسماء الأعضاء (يدعم وضع DB ووضع الملفات)
    // ====================================================

    /**
     * uid في وضع DB يكون بصيغة "u<id>" (انظر Predictions::user)، وفي وضع الملفات
     * يكون 32-hex. هذه الدوال تقرأ التوقّعات/الاسم المناسبين دون لمس ملفات Predictions.
     */
    private static function predsForUid(string $uid): array
    {
        // وضع قاعدة البيانات: المعرّف "u<id>".
        if (preg_match('/^u(\d+)$/', $uid, $m)) {
            if (!Database::available()) return [];
            try {
                $st = Database::pdo()->prepare(
                    'SELECT match_index, pred1, pred2 FROM predictions WHERE user_id = ?'
                );
                $st->execute([(int)$m[1]]);
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
        // وضع الملفات: 32-hex.
        if (!preg_match('/^[a-f0-9]{32}$/', $uid)) return [];
        $file = self::baseDir() . '/../preds/' . $uid . '.json';
        $d = self::readJson($file);
        return is_array($d) ? $d : [];
    }

    /** الاسم المعروض لعضو (DB display_name أو nickname الملف)؛ null إن غير موجود. */
    private static function nicknameForUid(string $uid): ?string
    {
        if (preg_match('/^u(\d+)$/', $uid, $m)) {
            if (!Database::available()) return null;
            try {
                $st = Database::pdo()->prepare('SELECT display_name FROM users WHERE id = ? LIMIT 1');
                $st->execute([(int)$m[1]]);
                $name = $st->fetchColumn();
                return ($name === false || $name === null || $name === '') ? null : (string)$name;
            } catch (Throwable $e) {
                return null;
            }
        }
        if (!preg_match('/^[a-f0-9]{32}$/', $uid)) return null;
        $u = self::readJson(self::baseDir() . '/../users/' . $uid . '.json');
        return !empty($u['nickname']) ? (string)$u['nickname'] : null;
    }

    // ====================================================
    //  نسخة العرض العامّة (لا تكشف معرّفات الأعضاء)
    // ====================================================

    private static function publicView(array $league): array
    {
        $uid = self::uid();
        return [
            'id'       => (string)($league['id'] ?? ''),
            'name'     => (string)($league['name'] ?? ''),
            'code'     => (string)($league['code'] ?? ''),
            'sponsor'  => $league['sponsor'] ?? null,
            'members'  => is_array($league['members'] ?? null) ? count($league['members']) : 0,
            'created'  => (int)($league['created'] ?? 0),
            'is_owner' => $uid !== null && ($league['owner'] ?? '') === $uid,
            'is_member'=> $uid !== null && isset($league['members'][$uid]),
        ];
    }

    private static function idForCode(string $code): ?string
    {
        $index = self::readJson(self::indexFile());
        $id = $index[$code] ?? null;
        return (is_string($id) && self::validId($id)) ? $id : null;
    }

    private static function randomCode(): string
    {
        $alpha = self::CODE_ALPHABET;
        $max = strlen($alpha) - 1;
        $out = '';
        for ($i = 0; $i < self::CODE_LEN; $i++) {
            $out .= $alpha[random_int(0, $max)];
        }
        return $out;
    }

    // ====================================================
    //  أدوات تخزين منخفضة المستوى (نمط Predictions/RateLimiter)
    // ====================================================

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
        self::ensureDir();
        $fp = @fopen($file, 'c+b');
        if (!$fp) return false;
        @flock($fp, LOCK_EX);
        self::writeLocked($fp, $data);
        @flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    /** يفتح ملفاً ويقفله حصرياً ويعيد المؤشّر (قراءة-تعديل-كتابة). */
    private static function lock(string $file)
    {
        self::ensureDir();
        $fp = @fopen($file, 'c+b');
        if (!$fp) return null;
        if (!@flock($fp, LOCK_EX)) { fclose($fp); return null; }
        rewind($fp);
        return $fp;
    }

    private static function readLocked($fp): array
    {
        rewind($fp);
        $d = json_decode((string)stream_get_contents($fp), true);
        return is_array($d) ? $d : [];
    }

    private static function writeLocked($fp, array $data): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
        fflush($fp);
    }

    private static function unlock($fp): void
    {
        @flock($fp, LOCK_UN);
        fclose($fp);
    }
}
