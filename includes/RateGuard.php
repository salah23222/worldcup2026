<?php
/**
 * RateGuard.php — حارس حماية حساب X من الإيقاف.
 * ============================================================
 * منظومة دفاع متعدّدة الطبقات للنشر التلقائي:
 *
 *   1) سقف بالساعة  (X_HOURLY_CAP — افتراضي 8)  — يحدّ كل run cron
 *   2) سقف باليوم    (X_DAILY_CAP  — افتراضي 30)
 *   3) فاصل أدنى    (X_MIN_SPACING — افتراضي 60 ث) بين أي تغريدتَين
 *   4) Circuit-breaker: لو وصلت ردود 429/403 → يوقف ساعة كاملة
 *   5) فشل متتالٍ (≥3) → يوقف يوماً كاملاً + ينبّه
 *   6) سجل آخر 200 تغريدة (وقت + نتيجة) في cache/x_rate.json
 *
 * كل دوال الفحص خفيفة (قراءة JSON صغير) → آمنة للاستدعاء في كل cron run.
 * إن لم تُضبط ثوابت السقوف في config، تُستخدم الافتراضات الآمنة أعلاه.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class RateGuard
{
    private const FILE       = 'x_rate.json';
    private const PAUSE_KEY  = 'pause_until';
    private const FAILS_KEY  = 'consecutive_fails';
    private const HISTORY    = 'history';     // [ [ts, ok, slot, err], ... ]
    private const HIST_MAX   = 200;
    private const DEFAULT_HOURLY  = 8;
    private const DEFAULT_DAILY   = 30;
    private const DEFAULT_SPACING = 60;       // ثوانٍ

    // ──────────────────── الواجهة العامّة ────────────────────

    /**
     * هل يُسمح بنشر تغريدة الآن؟ يعيد ['ok'=>bool,'reason'=>?string,'wait'=>int].
     * $skipDaily=true للتغريدات ذات الأولويّة (نتائج المباريات): تتجاوز السقف اليومي
     * المشترك (عددها محدود ~6/يوم) لكن تبقى محكومةً بالإيقاف + السقف الساعي + التباعد
     * تفادياً لحظر X.
     */
    public static function check(int $now = 0, bool $skipDaily = false): array
    {
        $now = $now ?: time();
        $s = self::state();

        // (1) قطع الدائرة: في فترة إيقاف؟
        $pauseUntil = (int)($s[self::PAUSE_KEY] ?? 0);
        if ($pauseUntil > $now) {
            return [
                'ok'     => false,
                'reason' => 'paused',
                'wait'   => $pauseUntil - $now,
                'until'  => $pauseUntil,
            ];
        }

        $hist = $s[self::HISTORY] ?? [];

        // (2) سقف ساعة
        $hourlyCap = self::hourlyCap();
        $lastHour  = array_filter($hist, fn($r) => ($r['ts'] ?? 0) >= $now - 3600);
        if (count($lastHour) >= $hourlyCap) {
            return ['ok' => false, 'reason' => 'hourly_cap', 'wait' => 600];
        }

        // (3) سقف يوم — تتجاوزه التغريدات ذات الأولويّة (نتائج المباريات)
        if (!$skipDaily) {
            $dailyCap = self::dailyCap();
            $lastDay  = array_filter($hist, fn($r) => ($r['ts'] ?? 0) >= $now - 86400);
            if (count($lastDay) >= $dailyCap) {
                return ['ok' => false, 'reason' => 'daily_cap', 'wait' => 3600];
            }
        }

        // (4) فاصل أدنى
        $spacing = self::minSpacing();
        $last = end($hist);
        if (is_array($last) && isset($last['ts'])) {
            $since = $now - (int)$last['ts'];
            if ($since < $spacing) {
                return ['ok' => false, 'reason' => 'spacing', 'wait' => $spacing - $since];
            }
        }

        return ['ok' => true, 'reason' => null, 'wait' => 0];
    }

    /** يُسجَّل بعد كل محاولة نشر — يحرّك circuit breaker عند الحاجة. */
    public static function record(bool $ok, string $slot = 'manual', ?string $error = null): void
    {
        $s = self::state();
        $hist = $s[self::HISTORY] ?? [];
        $hist[] = [
            'ts'    => time(),
            'ok'    => $ok ? 1 : 0,
            'slot'  => $slot,
            'err'   => $error,
        ];
        // قصّ السجل
        if (count($hist) > self::HIST_MAX) {
            $hist = array_slice($hist, -self::HIST_MAX);
        }
        $s[self::HISTORY] = $hist;

        if ($ok) {
            $s[self::FAILS_KEY] = 0;
        } else {
            // ★ 402 = نفاد الرصيد → إيقاف 24 ساعة فوراً (لا فائدة من إعادة المحاولة)
            if ($error !== null && self::isInsufficientCredits($error)) {
                $s[self::PAUSE_KEY] = time() + 86400;
                $s['last_credit_error'] = ['ts' => time(), 'msg' => mb_substr($error, 0, 200, 'UTF-8')];
            }
            // 429/403/401 → إيقاف ساعة
            elseif ($error !== null && self::isCritical($error)) {
                $s[self::PAUSE_KEY] = time() + 3600;
            }
            // فشل متراكم → إيقاف يوم
            $fails = (int)($s[self::FAILS_KEY] ?? 0) + 1;
            $s[self::FAILS_KEY] = $fails;
            if ($fails >= 3) {
                $s[self::PAUSE_KEY] = max((int)($s[self::PAUSE_KEY] ?? 0), time() + 86400);
            }
        }
        self::save($s);
    }

    /** يدوياً: إيقاف N ساعة (للأدمن). */
    public static function pauseFor(int $hours): void
    {
        $s = self::state();
        $s[self::PAUSE_KEY] = time() + max(1, $hours) * 3600;
        self::save($s);
    }

    /** يدوياً: استئناف فوريّ (للأدمن). */
    public static function resume(): void
    {
        $s = self::state();
        $s[self::PAUSE_KEY]  = 0;
        $s[self::FAILS_KEY]  = 0;
        self::save($s);
    }

    // ──────────────────── إحصاءات للوحة ────────────────────

    public static function stats(int $now = 0): array
    {
        $now  = $now ?: time();
        $s    = self::state();
        $hist = $s[self::HISTORY] ?? [];
        $hour = array_filter($hist, fn($r) => ($r['ts'] ?? 0) >= $now - 3600);
        $day  = array_filter($hist, fn($r) => ($r['ts'] ?? 0) >= $now - 86400);

        $pauseUntil = (int)($s[self::PAUSE_KEY] ?? 0);
        $isPaused   = ($pauseUntil > $now);

        $lastErr = null;
        foreach (array_reverse($hist) as $r) {
            if (empty($r['ok']) && !empty($r['err'])) { $lastErr = $r; break; }
        }

        return [
            'hourly_used'  => count($hour),
            'hourly_cap'   => self::hourlyCap(),
            'daily_used'   => count($day),
            'daily_cap'    => self::dailyCap(),
            'paused'       => $isPaused,
            'pause_until'  => $pauseUntil,
            'fails_streak' => (int)($s[self::FAILS_KEY] ?? 0),
            'last_error'   => $lastErr,
            'min_spacing'  => self::minSpacing(),
        ];
    }

    // ──────────────────── الداخل ────────────────────

    private static function file(): string
    {
        return rtrim(CACHE_DIR, '/') . '/' . self::FILE;
    }

    private static function state(): array
    {
        $f = self::file();
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    private static function save(array $s): void
    {
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::file(), json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    private static function hourlyCap(): int
    {
        return defined('X_HOURLY_CAP') && X_HOURLY_CAP > 0 ? (int)X_HOURLY_CAP : self::DEFAULT_HOURLY;
    }

    private static function dailyCap(): int
    {
        return defined('X_DAILY_CAP') && X_DAILY_CAP > 0 ? (int)X_DAILY_CAP : self::DEFAULT_DAILY;
    }

    private static function minSpacing(): int
    {
        return defined('X_MIN_SPACING') && X_MIN_SPACING >= 0 ? (int)X_MIN_SPACING : self::DEFAULT_SPACING;
    }

    /** هل الخطأ يستحقّ إيقاف ساعة فوراً (لتفادي إيقاف X الحساب)؟ */
    private static function isCritical(string $err): bool
    {
        // 429 = rate limit · 403 = forbidden (suspended?) · 401 = invalid creds
        return (bool)preg_match('/(429|403|401|too many|forbidden|unauthorized|suspended)/i', $err);
    }

    /** نفاد الرصيد (402) — يستحقّ إيقاف 24 ساعة لتفادي رسائل فشل متكرّرة. */
    private static function isInsufficientCredits(string $err): bool
    {
        return (bool)preg_match('/(http_?402|no.{0,10}credits|insufficient.{0,10}credit|does not have any credits|payment required)/i', $err);
    }
}
