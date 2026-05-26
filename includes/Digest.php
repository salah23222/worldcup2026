<?php
/**
 * Digest.php — نشرة البريد الدورية للمشتركين.
 * ============================================================
 * يبني قائمة المستلِمين (مستخدمو قاعدة البيانات غير الملغين اشتراكهم)،
 * ويُولّد لكل مستخدم رسالة عربية فيها: العدّاد، نقاطه وترتيبه، أبرز المباريات،
 * وروابط للتفاعل + رابط إلغاء الاشتراك. لا يرسل بنفسه — cron/digest.php يرسل.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Digest
{
    /** يضمن وجود جدول إلغاء الاشتراك (DB). */
    public static function ensureTable(): void
    {
        if (!Database::available()) return;
        $pdo = Database::pdo();
        if ($pdo === null) return;
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `email_optout` (
                    `user_id` INT UNSIGNED NOT NULL PRIMARY KEY,
                    `at`      INT UNSIGNED NOT NULL
                 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (Throwable $e) {}
    }

    /**
     * قائمة المستلِمين: [ ['id','email','name'], ... ] (DB فقط).
     * $predictorsOnly = true → فقط من شارك فعلاً في التوقّعات أو سؤال اليوم.
     */
    public static function recipients(bool $predictorsOnly = false): array
    {
        if (!Database::available()) return [];
        self::ensureTable();
        $pdo = Database::pdo();
        if ($pdo === null) return [];
        $out = [];
        $where = 'o.user_id IS NULL';
        if ($predictorsOnly) {
            $where .= ' AND (EXISTS (SELECT 1 FROM predictions p WHERE p.user_id = u.id)'
                    . ' OR EXISTS (SELECT 1 FROM trivia_answers t WHERE t.user_id = u.id))';
        }
        try {
            $sql = "SELECT u.id, u.email, u.display_name
                    FROM users u
                    LEFT JOIN email_optout o ON o.user_id = u.id
                    WHERE {$where}";
            foreach ($pdo->query($sql) as $r) {
                $email = trim((string)$r['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $out[] = ['id' => (int)$r['id'], 'email' => $email, 'name' => (string)$r['display_name']];
            }
        } catch (Throwable $e) {}
        return $out;
    }

    /** رمز توقيع رابط إلغاء الاشتراك لمستخدم. */
    public static function unsubToken(int $userId): string
    {
        $secret = defined('MAIL_SECRET') ? MAIL_SECRET : 'wc26';
        return substr(hash_hmac('sha256', 'unsub:' . $userId, $secret), 0, 32);
    }

    public static function unsubValid(int $userId, string $token): bool
    {
        return hash_equals(self::unsubToken($userId), $token);
    }

    public static function unsubUrl(int $userId): string
    {
        $base = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
        return $base . '/unsubscribe.php?u=' . $userId . '&t=' . self::unsubToken($userId);
    }

    /** يُسجّل إلغاء اشتراك مستخدم. */
    public static function optOut(int $userId): bool
    {
        if (!Database::available()) return false;
        self::ensureTable();
        $pdo = Database::pdo();
        if ($pdo === null) return false;
        try {
            $st = $pdo->prepare('INSERT IGNORE INTO email_optout (user_id, at) VALUES (?, ?)');
            $st->execute([$userId, time()]);
            return true;
        } catch (Throwable $e) { return false; }
    }

    /** يسجّل نتيجة عملية إرسال (لمتابعتها من لوحة التحكم). */
    public static function log(string $type, int $sent, int $fail, int $recipients): void
    {
        $f = rtrim(CACHE_DIR, '/') . '/digest_log.json';
        $list = [];
        if (is_file($f)) {
            $d = json_decode((string)@file_get_contents($f), true);
            if (is_array($d)) $list = $d;
        }
        array_unshift($list, [
            't' => time(), 'type' => $type,
            'sent' => $sent, 'fail' => $fail, 'rcpt' => $recipients,
        ]);
        $list = array_slice($list, 0, 30);
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($f, json_encode($list, JSON_UNESCAPED_UNICODE));
    }

    /** آخر سجلات الإرسال (الأحدث أولاً). */
    public static function recentLog(int $n = 20): array
    {
        $f = rtrim(CACHE_DIR, '/') . '/digest_log.json';
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? array_slice($d, 0, $n) : [];
    }

    /** عناصر مشتركة بين كل الرسائل (تُحسب مرة واحدة). */
    public static function highlights(): array
    {
        $start   = DataService::tournamentStart();
        $started = DataService::tournamentStarted();
        $daysLeft = ($start !== null && !$started) ? (int)ceil(($start - time()) / 86400) : 0;
        return [
            'started'   => $started,
            'days_left' => $daysLeft,
            'today'     => DataService::matchesOnDate(),       // مباريات اليوم
            'results'   => $started ? DataService::latestResults(4) : [],  // نتائج سابقة
            'upcoming'  => DataService::upcomingMatches(4),     // قادمة
        ];
    }

    /** ينهي البطولة؟ (للتوقّف التلقائي بعد النهائي + يومين) */
    public static function windowOpen(): bool
    {
        $end = null;
        foreach (DataService::allMatches() as $m) {
            $ts = DataService::matchTimestamp($m);
            if ($ts !== null && ($end === null || $ts > $end)) $end = $ts;
        }
        if ($end === null) return true;                 // لا بيانات → لا نوقف
        return time() <= ($end + 2 * 86400);
    }

    /** يبني رسالة مستخدم واحد: ['subject','html','text']. */
    public static function buildEmail(array $user, array $h, ?array $standing): array
    {
        $name  = $user['name'] !== '' ? $user['name'] : 'صديقنا';
        $site  = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
        $brand = defined('SITE_NAME_AR') ? SITE_NAME_AR : 'كأس العالم 2026';
        $tzNote = defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'UTC';

        // العنوان والافتتاحية حسب حالة البطولة
        if (!$h['started'] && $h['days_left'] > 0) {
            $subject = "{$brand} — باقٍ {$h['days_left']} يوم على الانطلاق! 🏆";
            $lead = "باقٍ <strong>{$h['days_left']}</strong> يوم على انطلاق المونديال — جهّز توقّعاتك واصعد في الترتيب!";
        } elseif ($h['started']) {
            $subject = "{$brand} — نتائج اليوم وترتيبك ⚽";
            $lead = "البطولة جارية الآن! إليك آخر النتائج ومباريات اليوم.";
        } else {
            $subject = "{$brand} — مستجدّاتك وترتيبك";
            $lead = "إليك أبرز ما في الموقع اليوم.";
        }

        // ===== صفّ مباراة أنيق (أعلام + أسماء + نتيجة/وقت) =====
        $row = function (array $m): string {
            $t1 = e(team_name($m['team1'] ?? '')); $t2 = e(team_name($m['team2'] ?? ''));
            $f1 = flag_url($m['team1'] ?? '', 'w40'); $f2 = flag_url($m['team2'] ?? '', 'w40');
            $img = fn($u) => $u !== '' ? '<img src="' . e($u) . '" width="22" height="16" style="vertical-align:middle;border-radius:3px"> ' : '';
            $ts = DataService::matchTimestamp($m);
            $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
            $mid = $hasScore
                ? '<span style="background:#0a1626;color:#fff;font-weight:800;border-radius:6px;padding:3px 10px">'
                    . (int)$m['score']['ft'][0] . ' - ' . (int)$m['score']['ft'][1] . '</span>'
                : '<span style="color:#9fb0c8;font-weight:700">' . e($ts ? fmt_time($ts) : 'ضد') . '</span>';
            $when = $ts ? fmt_date_short($ts) : '';
            $ground = !empty($m['ground']) ? ' · 📍 ' . e($m['ground']) : '';
            return '<tr><td style="padding:10px 12px;border-top:1px solid #243a68">'
                 . '<table width="100%" style="border-collapse:collapse"><tr>'
                 . '<td style="color:#eef2fb;font-weight:700;font-size:14px">' . $img($f1) . $t1 . '</td>'
                 . '<td style="text-align:center;width:80px">' . $mid . '</td>'
                 . '<td style="color:#eef2fb;font-weight:700;font-size:14px;text-align:left">' . $t2 . ' ' . $img($f2) . '</td>'
                 . '</tr></table>'
                 . '<div style="color:#7e90ad;font-size:12px;margin-top:4px">' . e($when) . $ground . '</div>'
                 . '</td></tr>';
        };
        // ===== قسم مباريات بعنوان =====
        $section = function (string $title, array $list) use ($row): string {
            if (!$list) return '';
            $rows = '';
            foreach ($list as $m) $rows .= $row($m);
            return '<div style="background:#11203a;border:1px solid #243a68;border-radius:14px;margin:14px 0;overflow:hidden">'
                 . '<div style="background:#1b2a45;padding:10px 14px;font-weight:800;font-size:15px">' . $title . '</div>'
                 . '<table width="100%" style="border-collapse:collapse">' . $rows . '</table>'
                 . '</div>';
        };

        // ===== بطاقة الترتيب (ذهبية، بارزة) =====
        if ($standing) {
            $pts = (int)$standing['points']; $rank = (int)$standing['rank']; $tot = (int)$standing['total'];
            $note = !Predictions::pointsActive()
                ? '<div style="font-size:12px;margin-top:6px;font-weight:600">تبدأ احتساب النقاط من انطلاق البطولة — الجميع يبدأ متساوياً ⚖️</div>'
                : '';
            $rankCard =
                '<div style="background:linear-gradient(135deg,#f7e09a,#d9b24a);color:#2a1d00;border-radius:16px;padding:18px;text-align:center;margin:18px 0">'
              . '<div style="font-size:13px;font-weight:700;letter-spacing:.04em">ترتيبك الحالي</div>'
              . '<div style="font-size:38px;font-weight:900;line-height:1.1;margin:2px 0">#' . $rank . '</div>'
              . '<div style="font-size:14px;font-weight:700">من ' . $tot . ' مشارك · نقاطك: ' . $pts . '</div>'
              . $note
              . '</div>';
        } else {
            $rankCard =
                '<div style="background:#11203a;border:1px solid #243a68;border-radius:16px;padding:18px;text-align:center;margin:18px 0">'
              . '<div style="font-weight:700">لم تبدأ اللعب بعد!</div>'
              . '<div style="color:#9fb0c8;font-size:14px;margin-top:4px">انضمّ لمسابقة التوقّعات واجمع النقاط ونافس العالم.</div>'
              . '</div>';
        }

        $today    = $section('⚽ مباريات اليوم',     $h['today']);
        $results  = $section('📊 نتائج سابقة',        $h['results']);
        $upcoming = $section('🗓️ المباريات القادمة', $h['upcoming']);
        if ($today === '' && $results === '' && $upcoming === '') {
            $upcoming = '<p style="color:#9fb0c8;text-align:center">تُعرض المباريات قريباً.</p>';
        }

        $btn = fn($href, $label, $primary) =>
            '<a href="' . e($href) . '" style="text-decoration:none;font-weight:800;border-radius:24px;padding:11px 18px;display:inline-block;margin:4px;'
            . ($primary ? 'background:#fff;color:#0a1626' : 'background:#1b2a45;color:#fff') . '">' . $label . '</a>';

        $unsub = e(self::unsubUrl((int)$user['id']));

        $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
          . '<body style="margin:0;background:#0a1626;font-family:Tahoma,Arial,sans-serif;color:#dfe7f5">'
          . '<div style="max-width:580px;margin:0 auto;padding:24px 18px">'
          . '<div style="text-align:center;margin-bottom:14px">'
          .   '<span style="display:inline-block;background:#fff;color:#0a1626;font-weight:900;border-radius:12px;padding:9px 14px;font-size:22px">26</span>'
          .   '<div style="font-weight:900;margin-top:8px;font-size:19px">' . e($brand) . '</div>'
          .   '<div style="color:#9fb0c8;font-size:12px">كندا · المكسيك · الولايات المتحدة</div>'
          . '</div>'
          . '<p style="font-size:16px">أهلاً <strong>' . e($name) . '</strong> 👋</p>'
          . '<p style="font-size:15px;line-height:1.8;color:#cdd8ec">' . $lead . '</p>'
          . $rankCard
          . $today . $results . $upcoming
          . '<p style="color:#7e90ad;font-size:12px;text-align:center">كل المواعيد بتوقيت ' . e($tzNote) . '.</p>'
          . '<div style="text-align:center;margin:22px 0">'
          .   $btn($site . '/predict.php', '🎯 العب التوقعات', true)
          .   $btn($site . '/bracket.php', '🏆 توقّع المشوار', false)
          .   $btn($site . '/leaderboard.php', '🏅 الصدارة', false)
          . '</div>'
          . '<hr style="border:none;border-top:1px solid #243a68;margin:20px 0">'
          . '<p style="color:#7e90ad;font-size:12px;text-align:center;line-height:1.9">'
          .   'وصلتك هذه الرسالة لأنك مشارك في ' . e($brand) . '.<br>'
          .   '<a href="' . $unsub . '" style="color:#9fb0c8">إلغاء الاشتراك في النشرة</a> · '
          .   '<a href="' . e($site) . '" style="color:#9fb0c8">' . e(parse_url($site, PHP_URL_HOST) ?: $site) . '</a>'
          . '</p>'
          . '</div></body></html>';

        $rankText = $standing
            ? "ترتيبك: #" . (int)$standing['rank'] . " من " . (int)$standing['total'] . " · نقاطك: " . (int)$standing['points']
            : "انضمّ لمسابقة التوقّعات واجمع النقاط!";
        $text = "أهلاً {$name}،\n\n" . strip_tags($lead) . "\n\n{$rankText}\n\n"
              . "العب التوقعات: {$site}/predict.php\n"
              . "توقّع المشوار: {$site}/bracket.php\n"
              . "الصدارة: {$site}/leaderboard.php\n\n"
              . "إلغاء الاشتراك: " . self::unsubUrl((int)$user['id']);

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
