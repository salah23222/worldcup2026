<?php
/**
 * Polls.php
 * ============================================================
 * استطلاعات الجمهور الخفيفة (شاشة ثانية): «من يسجّل التالي؟ / من يفوز؟».
 *
 * مبادئ:
 *   - تخزين ملفّي بسيط تحت data/polls/<id>.json (لا قاعدة بيانات).
 *   - معرّف ثابت لكل (مباراة + نوع السؤال) → نفس الاستطلاع لكل الزوّار.
 *   - صوت واحد لكل زائر لكل استطلاع: كوكي wc_polls (قائمة المعرّفات المُصوَّت
 *     عليها) + حارس بصمة IP مجزّأة داخل الملف (طبقة ثانية ضد التكرار).
 *   - كتابة ذرّية بقفل (flock) لتفادي تلف العدّادات عند التزامن.
 *   - يعمل فقط على مباراة قادمة/مباشرة فيها فريقان حقيقيّان.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Polls
{
    private const COOKIE_VOTED = 'wc_polls';   // قائمة معرّفات الاستطلاعات المُصوَّت عليها
    private const MAX_OPTIONS   = 4;

    /** مجلد تخزين الاستطلاعات (يُنشأ عند اللزوم). */
    private static function dir(): string
    {
        $d = rtrim(CACHE_DIR, '/') . '/../data/polls';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    private static function file(string $pollId): string
    {
        return self::dir() . '/' . $pollId . '.json';
    }

    /** يتحقّق من صيغة معرّف الاستطلاع (نمنع أي مسار/حروف غريبة). */
    public static function validId(string $pollId): bool
    {
        return (bool)preg_match('/^[a-z0-9_]{4,64}$/', $pollId);
    }

    // ====================================================
    //  بناء الاستطلاع الحالي من بيانات البطولة
    // ====================================================

    /**
     * current() — الاستطلاع النشط الآن (مرتبط بأقرب مباراة قادمة/مباشرة)، أو null.
     * يُرجّع: ['id','match_index','question','options'(نصوص),'counts'(أرقام),
     *          'total','voted'(bool),'choice'(?int),'closed'(bool)]
     */
    public static function current(?string $lang = null): ?array
    {
        $lang = in_array($lang, ['ar', 'en', 'fr'], true) ? $lang : current_lang();
        $m = self::pickMatch();

        $built = self::forMatch($m, $lang);
        if ($built === null) return null;

        return self::hydrate($built, $lang);
    }

    /**
     * card() — استطلاع مباراة واحدة مُهيّأ بالكامل (للعرض على بطاقة المباراة).
     * يُرجّع مصفوفة مُهيّأة (نفس شكل current) أو null إن لم تكن المباراة قابلة للاستطلاع.
     */
    public static function card(array $m, ?string $lang = null): ?array
    {
        $lang  = in_array($lang, ['ar', 'en', 'fr'], true) ? $lang : current_lang();
        $built = self::forMatch($m, $lang);
        if ($built === null) return null;
        return self::hydrate($built, $lang);
    }

    /**
     * resolve() — يحوّل معرّف استطلاع (m<idx>_winner|next) إلى تعريفه عبر المباراة،
     * للتحقّق من صحّته عند التصويت (يقبل أي بطاقة مباراة، لا «النشط» فقط). أو null.
     */
    public static function resolve(string $pollId): ?array
    {
        if (!self::validId($pollId)) return null;
        if (!preg_match('/^m(\d+)_(winner|next)$/', $pollId, $mm)) return null;
        $m = DataService::matchByIndex((int)$mm[1]);
        if ($m === null) return null;
        $built = self::forMatch($m);
        return ($built !== null && $built['id'] === $pollId) ? $built : null;
    }

    /** يختار المباراة المستهدفة: أقرب مباراة (مباشرة لها الأولوية) بفريقين حقيقيّين. */
    private static function pickMatch(): ?array
    {
        $candidates = DataService::upcomingMatches(12);
        // قدّم المباريات المباشرة على القادمة.
        usort($candidates, function ($a, $b) {
            $la = ($a['_status'] ?? '') === 'live' ? 0 : 1;
            $lb = ($b['_status'] ?? '') === 'live' ? 0 : 1;
            if ($la !== $lb) return $la <=> $lb;
            return (DataService::matchTimestamp($a) ?? PHP_INT_MAX)
                <=> (DataService::matchTimestamp($b) ?? PHP_INT_MAX);
        });
        foreach ($candidates as $m) {
            $t1 = trim($m['team1'] ?? '');
            $t2 = trim($m['team2'] ?? '');
            if (is_real_team($t1) && is_real_team($t2)) return $m;
        }
        return null;
    }

    /**
     * forMatch() — يبني تعريف الاستطلاع (المعرّف + السؤال + الخيارات) لمباراة.
     * مباشرة → «من يسجّل التالي؟»، غير ذلك → «من يفوز؟».
     * يُرجّع ['id','match_index','question','options','closed'] أو null.
     */
    public static function forMatch(array $m, ?string $lang = null): ?array
    {
        $lang = in_array($lang, ['ar', 'en', 'fr'], true) ? $lang : current_lang();
        $idx  = (int)($m['_index'] ?? -1);
        if ($idx < 0) return null;

        $t1 = trim($m['team1'] ?? '');
        $t2 = trim($m['team2'] ?? '');
        if (!is_real_team($t1) || !is_real_team($t2)) return null;

        $n1 = team_name($t1);
        $n2 = team_name($t2);
        $live = ($m['_status'] ?? DataService::matchStatus($m)) === 'live';
        $kind = $live ? 'next' : 'winner';

        if ($kind === 'next') {
            $question = match($lang) {
                'ar' => "من يسجّل التالي؟ {$n1} و {$n2}",
                'fr' => "Qui marque le prochain ? {$n1} vs {$n2}",
                default => "Who scores next? {$n1} vs {$n2}",
            };
            $options = [
                $n1,
                match($lang) { 'ar' => 'لا أحد (يبقى كما هو)', 'fr' => 'Personne (reste comme ça)', default => 'No one (stays as is)' },
                $n2,
            ];
        } else {
            $question = match($lang) {
                'ar' => "من يفوز؟ {$n1} و {$n2}",
                'fr' => "Qui gagne ? {$n1} vs {$n2}",
                default => "Who wins? {$n1} vs {$n2}",
            };
            $options = [
                $n1,
                match($lang) { 'ar' => 'تعادل', 'fr' => 'Nul', default => 'Draw' },
                $n2,
            ];
        }

        $id = 'm' . $idx . '_' . $kind;   // معرّف ثابت لكل (مباراة + نوع)
        return [
            'id'          => $id,
            'match_index' => $idx,
            'question'    => $question,
            'options'     => array_slice($options, 0, self::MAX_OPTIONS),
            'closed'      => Predictions::isLocked($m) && $kind === 'winner',
        ];
    }

    /** يدمج التعريف مع العدّادات وحالة تصويت الزائر الحالي. */
    private static function hydrate(array $built, string $lang): array
    {
        $counts = self::counts($built['id'], count($built['options']));
        return [
            'id'          => $built['id'],
            'match_index' => $built['match_index'],
            'question'    => $built['question'],
            'options'     => $built['options'],
            'counts'      => $counts,
            'total'       => array_sum($counts),
            'voted'       => self::hasVoted($built['id']),
            'choice'      => self::choiceOf($built['id']),
            'closed'      => (bool)($built['closed'] ?? false),
        ];
    }

    // ====================================================
    //  العدّادات
    // ====================================================

    /** counts() — مصفوفة أعداد الأصوات لكل خيار (مضمونة الطول). */
    public static function counts(string $pollId, int $optionCount = self::MAX_OPTIONS): array
    {
        $optionCount = max(1, min(self::MAX_OPTIONS, $optionCount));
        $out = array_fill(0, $optionCount, 0);
        if (!self::validId($pollId)) return $out;
        $d = self::read($pollId);
        $votes = isset($d['votes']) && is_array($d['votes']) ? $d['votes'] : [];
        foreach ($votes as $i => $c) {
            $i = (int)$i;
            if ($i >= 0 && $i < $optionCount) $out[$i] = max(0, (int)$c);
        }
        return $out;
    }

    // ====================================================
    //  التصويت
    // ====================================================

    /**
     * vote() — يسجّل صوتاً واحداً لزائر واحد على استطلاع.
     * يُرجّع ['ok'=>bool, 'error'=>?string, 'counts'=>int[], 'total'=>int, 'choice'=>?int]
     * يجب استدعاؤها قبل أي إخراج HTML (تضبط كوكي).
     */
    public static function vote(string $pollId, int $optionIndex, int $optionCount = self::MAX_OPTIONS): array
    {
        if (!self::validId($pollId)) {
            return ['ok' => false, 'error' => 'bad_poll'];
        }
        $optionCount = max(1, min(self::MAX_OPTIONS, $optionCount));
        if ($optionIndex < 0 || $optionIndex >= $optionCount) {
            return ['ok' => false, 'error' => 'bad_option'];
        }
        // صوّت بالفعل (حسب الكوكي)؟ أعِد العدّادات الحالية بلا تغيير.
        if (self::hasVoted($pollId)) {
            $counts = self::counts($pollId, $optionCount);
            return ['ok' => false, 'error' => 'already_voted',
                    'counts' => $counts, 'total' => array_sum($counts),
                    'choice' => self::choiceOf($pollId)];
        }

        $file = self::file($pollId);
        $fp = @fopen($file, 'c+b');
        if (!$fp) return ['ok' => false, 'error' => 'storage'];
        if (!@flock($fp, LOCK_EX)) { fclose($fp); return ['ok' => false, 'error' => 'storage']; }
        rewind($fp);
        $d = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($d)) $d = [];
        if (!isset($d['votes']) || !is_array($d['votes'])) $d['votes'] = [];
        if (!isset($d['ips'])   || !is_array($d['ips']))   $d['ips']   = [];

        // طبقة ثانية: حارس بصمة IP مجزّأة (يمنع التكرار لمن مسح الكوكي على نفس الشبكة).
        $ipHash = self::ipHash();
        if (isset($d['ips'][$ipHash])) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            self::markVoted($pollId, (int)$d['ips'][$ipHash]);   // وحّد الكوكي مع السجل
            $counts = self::counts($pollId, $optionCount);
            return ['ok' => false, 'error' => 'already_voted',
                    'counts' => $counts, 'total' => array_sum($counts),
                    'choice' => (int)$d['ips'][$ipHash]];
        }

        $cur = (int)($d['votes'][(string)$optionIndex] ?? 0);
        $d['votes'][(string)$optionIndex] = $cur + 1;
        $d['ips'][$ipHash] = $optionIndex;
        $d['updated'] = time();

        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);

        self::markVoted($pollId, $optionIndex);

        $counts = self::counts($pollId, $optionCount);
        return ['ok' => true, 'counts' => $counts, 'total' => array_sum($counts),
                'choice' => $optionIndex];
    }

    // ====================================================
    //  حالة الزائر (كوكي)
    // ====================================================

    /** هل صوّت الزائر الحالي على هذا الاستطلاع (حسب الكوكي)؟ */
    public static function hasVoted(string $pollId): bool
    {
        return self::choiceOf($pollId) !== null;
    }

    /** اختيار الزائر الحالي على استطلاع (من الكوكي) أو null. */
    public static function choiceOf(string $pollId): ?int
    {
        $map = self::votedMap();
        return array_key_exists($pollId, $map) ? (int)$map[$pollId] : null;
    }

    /** يقرأ خريطة {pollId: optionIndex} من كوكي الزائر. */
    private static function votedMap(): array
    {
        $raw = $_COOKIE[self::COOKIE_VOTED] ?? '';
        if ($raw === '') return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    /** يسجّل تصويت الزائر في الكوكي (يحتفظ بآخر 50 استطلاعاً فقط). */
    private static function markVoted(string $pollId, int $optionIndex): void
    {
        $map = self::votedMap();
        $map[$pollId] = $optionIndex;
        if (count($map) > 50) {
            $map = array_slice($map, -50, null, true);
        }
        $_COOKIE[self::COOKIE_VOTED] = json_encode($map, JSON_UNESCAPED_UNICODE);
        if (headers_sent()) return;
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_VOTED, $_COOKIE[self::COOKIE_VOTED], [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    // ====================================================
    //  أدوات
    // ====================================================

    private static function read(string $pollId): array
    {
        $file = self::file($pollId);
        if (!is_file($file)) return [];
        $d = json_decode((string)@file_get_contents($file), true);
        return is_array($d) ? $d : [];
    }

    /** بصمة IP مجزّأة (لا نخزّن الـIP نفسه — خصوصية). */
    private static function ipHash(): string
    {
        $seed = defined('INSTALL_TOKEN') && INSTALL_TOKEN !== '' ? INSTALL_TOKEN : 'wc26-polls';
        return substr(hash('sha256', RateLimiter::ip() . '|' . $seed), 0, 16);
    }
}
