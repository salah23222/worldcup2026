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

    // ════════════════════════════════════════════════════════════
    //  🆕 نظام الطابور (async) — يحلّ مشكلة timeout على Hostinger
    // ════════════════════════════════════════════════════════════

    /** مسار ملفّ الطابور. */
    private static function queueFile(): string
    {
        return rtrim(CACHE_DIR, '/') . '/digest_queue.json';
    }

    /** يقرأ الطابور الحالي. الشكل: ['type'=>..., 'created'=>ts, 'pending'=>[users], 'done'=>[ids], 'failed'=>[ids]]. */
    public static function queueRead(): ?array
    {
        $f = self::queueFile();
        if (!is_file($f)) return null;
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : null;
    }

    /** يحفظ الطابور (atomic). */
    private static function queueWrite(array $q): bool
    {
        $f = self::queueFile();
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0755, true);
        $tmp = $f . '.tmp';
        if (@file_put_contents($tmp, json_encode($q, JSON_UNESCAPED_UNICODE)) === false) return false;
        return @rename($tmp, $f);
    }

    /** يحذف الطابور (عند الانتهاء). */
    public static function queueClear(): bool
    {
        $f = self::queueFile();
        return !is_file($f) || @unlink($f);
    }

    /**
     * queueEnqueue() — يبني طابور إرسال جديد لكل المستلِمين (أو المتوقّعين فقط).
     * يعود فوراً — لا إرسال هنا. الإرسال الفعلي عبر queueProcess() دفعةً دفعة.
     */
    public static function queueEnqueue(bool $predictorsOnly = false): array
    {
        $recips = self::recipients($predictorsOnly);
        $q = [
            'type'    => $predictorsOnly ? 'digest-predictors' : 'digest',
            'created' => time(),
            'pending' => $recips,
            'total'   => count($recips),
            'sent'    => 0,
            'fail'    => 0,
        ];
        self::queueWrite($q);
        return $q;
    }

    /**
     * queueProcess() — يُرسل دفعة من الطابور (افتراضياً 10 رسائل) ثم يحفظ.
     * يُستدعى من cron (للأتمتة) أو من admin (للمعالجة الفوريّة).
     * يعود: ['sent','fail','remaining','done']
     */
    public static function queueProcess(int $batch = 10): array
    {
        $q = self::queueRead();
        if (!$q || empty($q['pending'])) {
            return ['sent' => 0, 'fail' => 0, 'remaining' => 0, 'done' => true];
        }

        @set_time_limit(180);
        $h     = self::highlights();
        $stand = Predictions::standingsByUser();
        $sent  = 0; $fail = 0;
        $batch = max(1, min(50, $batch));

        for ($i = 0; $i < $batch && !empty($q['pending']); $i++) {
            $u = array_shift($q['pending']);
            if (!is_array($u) || empty($u['email'])) continue;
            $mail = self::buildEmail($u, $h, $stand[$u['id']] ?? null);
            $ok   = Mailer::send($u['email'], $mail['subject'], $mail['html'], $mail['text']);
            $ok ? $sent++ : $fail++;
            usleep(150000); // 0.15ث بين الرسائل (ضمن نفس الدفعة فقط)
        }

        $q['sent'] += $sent;
        $q['fail'] += $fail;
        $remaining = count($q['pending']);

        if ($remaining === 0) {
            // اكتمل الطابور — سجّل النتيجة + احذفه
            self::log($q['type'], (int)$q['sent'], (int)$q['fail'], (int)$q['total']);
            self::queueClear();
            return ['sent' => $sent, 'fail' => $fail, 'remaining' => 0, 'done' => true];
        }

        self::queueWrite($q);
        return ['sent' => $sent, 'fail' => $fail, 'remaining' => $remaining, 'done' => false];
    }

    /** عناصر مشتركة بين كل الرسائل (تُحسب مرة واحدة). */
    public static function highlights(): array
    {
        $start   = DataService::tournamentStart();
        $started = DataService::tournamentStarted();
        // floor (لا ceil) ليطابق عدّاد الرئيسية: 7d + ساعات = "7 يوم"، لا 8.
        $daysLeft = ($start !== null && !$started) ? (int)floor(($start - time()) / 86400) : 0;
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

    /** يبني رسالة مستخدم واحد ثنائيّة اللغة (AR+EN): ['subject','html','text']. */
    public static function buildEmail(array $user, array $h, ?array $standing): array
    {
        $name  = $user['name'] !== '' ? $user['name'] : 'صديقنا';
        $nameEn = $user['name'] !== '' ? $user['name'] : 'Friend';
        $site  = rtrim(defined('SITE_URL') ? SITE_URL : '', '/');
        $brand = defined('SITE_NAME_AR') ? SITE_NAME_AR : 'كأس العالم 2026';
        $brandEn = 'FIFA World Cup 2026';
        $tzNote = defined('DISPLAY_TIMEZONE') ? DISPLAY_TIMEZONE : 'UTC';

        // ✨ عنوان ثنائي اللغة + افتتاحية لكل لغة
        if (!$h['started'] && $h['days_left'] > 0) {
            $subject = "{$brand} — باقٍ {$h['days_left']} يوم 🏆 · {$h['days_left']} days to {$brandEn}!";
            $lead   = "باقٍ <strong>{$h['days_left']}</strong> يوم على انطلاق المونديال — جهّز توقّعاتك واصعد في الترتيب!";
            $leadEn = "Only <strong>{$h['days_left']}</strong> days until kickoff — lock your predictions and climb the leaderboard!";
        } elseif ($h['started']) {
            $subject = "{$brand} — نتائج اليوم ⚽ · {$brandEn} — Today's Results";
            $lead   = "البطولة جارية الآن! إليك آخر النتائج ومباريات اليوم.";
            $leadEn = "The tournament is live! Here are the latest results and today's fixtures.";
        } else {
            $subject = "{$brand} — مستجدّاتك · Your {$brandEn} Update";
            $lead   = "إليك أبرز ما في الموقع اليوم.";
            $leadEn = "Here are today's tournament highlights.";
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

        // ===== بطاقة الترتيب الذهبيّة (AR + EN معاً) =====
        if ($standing) {
            $pts = (int)$standing['points']; $rank = (int)$standing['rank']; $tot = (int)$standing['total'];
            $note   = !Predictions::pointsActive()
                ? '<div style="font-size:12px;margin-top:6px;font-weight:600">احتساب النقاط يبدأ مع انطلاق البطولة — الجميع متساوون الآن ⚖️</div>'
                : '';
            $noteEn = !Predictions::pointsActive()
                ? '<div style="font-size:12px;margin-top:4px;font-weight:600">Scoring begins at kickoff — everyone starts equal ⚖️</div>'
                : '';
            $rankCard =
                '<div style="background:linear-gradient(135deg,#f7e09a,#d9b24a);color:#2a1d00;border-radius:16px;padding:18px;text-align:center;margin:18px 0">'
              . '<div style="font-size:13px;font-weight:700;letter-spacing:.04em">ترتيبك الحالي · Your Rank</div>'
              . '<div style="font-size:42px;font-weight:900;line-height:1.1;margin:4px 0">#' . $rank . '</div>'
              . '<div style="font-size:14px;font-weight:700">من ' . $tot . ' مشارك · نقاطك: ' . $pts . '</div>'
              . '<div style="font-size:13px;font-weight:600;margin-top:2px">Out of ' . $tot . ' players · ' . $pts . ' pts</div>'
              . $note . $noteEn
              . '</div>';
        } else {
            $rankCard =
                '<div style="background:#11203a;border:1px solid #243a68;border-radius:16px;padding:18px;text-align:center;margin:18px 0">'
              . '<div style="font-weight:700">لم تبدأ اللعب بعد! · You haven\'t played yet!</div>'
              . '<div style="color:#9fb0c8;font-size:13px;margin-top:6px;line-height:1.7">انضمّ لمسابقة التوقّعات واجمع النقاط ونافس العالم.<br>'
              . 'Join the predictions game, earn points, compete worldwide.</div>'
              . '</div>';
        }

        // 🆕 عناوين الأقسام ثنائيّة
        $today    = $section('⚽ مباريات اليوم · Today\'s Matches',   $h['today']);
        $results  = $section('📊 نتائج سابقة · Recent Results',       $h['results']);
        $upcoming = $section('🗓️ المباريات القادمة · Upcoming',       $h['upcoming']);
        if ($today === '' && $results === '' && $upcoming === '') {
            $upcoming = '<p style="color:#9fb0c8;text-align:center">تُعرض المباريات قريباً.<br>Matches will appear soon.</p>';
        }

        $btn = fn($href, $label, $primary) =>
            '<a href="' . e($href) . '" style="text-decoration:none;font-weight:800;border-radius:24px;padding:11px 18px;display:inline-block;margin:4px;font-size:14px;'
            . ($primary ? 'background:#fff;color:#0a1626' : 'background:#1b2a45;color:#fff') . '">' . $label . '</a>';

        $unsub = e(self::unsubUrl((int)$user['id']));

        // 🆕 قسم إنجليزي LTR منفصل أسفل العربي
        $englishBlock =
            '<div dir="ltr" lang="en" style="text-align:left;direction:ltr;padding:18px 0;border-top:1px dashed #243a68;margin-top:24px">'
          . '<p style="font-size:15px;line-height:1.8;color:#cdd8ec;margin:0 0 14px"><strong>Hello ' . e($nameEn) . '</strong> 👋<br>' . $leadEn . '</p>'
          . '<p style="color:#7e90ad;font-size:12px;margin:8px 0">All times in ' . e($tzNote) . '.</p>'
          . '<div style="text-align:center;margin:18px 0">'
          .   $btn($site . '/predict.php?lang=en', '🎯 Play Predictions', true)
          .   $btn($site . '/bracket.php?lang=en', '🏆 Bracket',          false)
          .   $btn($site . '/leaderboard.php?lang=en', '🏅 Leaderboard',  false)
          . '</div>'
          . '</div>';

        $html = '<!DOCTYPE html><html dir="rtl" lang="ar"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
          . '<body style="margin:0;background:#0a1626;font-family:Tahoma,Arial,sans-serif;color:#dfe7f5">'
          . '<div style="max-width:580px;margin:0 auto;padding:24px 18px">'
          // header
          . '<div style="text-align:center;margin-bottom:14px">'
          .   '<span style="display:inline-block;background:#fff;color:#0a1626;font-weight:900;border-radius:12px;padding:9px 14px;font-size:22px">26</span>'
          .   '<div style="font-weight:900;margin-top:8px;font-size:19px">' . e($brand) . '</div>'
          .   '<div style="color:#9fb0c8;font-size:12px;direction:ltr;display:inline-block">' . e($brandEn) . ' · Canada · Mexico · USA</div>'
          . '</div>'
          // greeting AR
          . '<p style="font-size:16px">أهلاً <strong>' . e($name) . '</strong> 👋</p>'
          . '<p style="font-size:15px;line-height:1.8;color:#cdd8ec">' . $lead . '</p>'
          // ranking card (bilingual)
          . $rankCard
          // match sections
          . $today . $results . $upcoming
          . '<p style="color:#7e90ad;font-size:12px;text-align:center">كل المواعيد بتوقيت ' . e($tzNote) . '.</p>'
          // AR buttons
          . '<div style="text-align:center;margin:22px 0">'
          .   $btn($site . '/predict.php', '🎯 العب التوقعات', true)
          .   $btn($site . '/bracket.php', '🏆 توقّع المشوار', false)
          .   $btn($site . '/leaderboard.php', '🏅 الصدارة', false)
          . '</div>'
          // 🆕 English block
          . $englishBlock
          // footer (bilingual)
          . '<hr style="border:none;border-top:1px solid #243a68;margin:20px 0">'
          . '<p style="color:#7e90ad;font-size:11px;text-align:center;line-height:1.9">'
          .   'وصلتك هذه الرسالة لأنك مشارك في ' . e($brand) . '.<br>'
          .   '<span dir="ltr" style="display:inline-block">You received this email as a registered ' . e($brandEn) . ' player.</span><br><br>'
          .   '<a href="' . $unsub . '" style="color:#9fb0c8">إلغاء الاشتراك · Unsubscribe</a> · '
          .   '<a href="' . e($site) . '" style="color:#9fb0c8">' . e(parse_url($site, PHP_URL_HOST) ?: $site) . '</a>'
          . '</p>'
          . '</div></body></html>';

        // نص عادي (Plain) — ثنائي اللغة
        $rankText = $standing
            ? "ترتيبك: #" . (int)$standing['rank'] . " من " . (int)$standing['total'] . " · نقاطك: " . (int)$standing['points']
            : "انضمّ لمسابقة التوقّعات واجمع النقاط!";
        $rankTextEn = $standing
            ? "Your rank: #" . (int)$standing['rank'] . " of " . (int)$standing['total'] . " · " . (int)$standing['points'] . " pts"
            : "Join the predictions game and earn points!";
        $text = "أهلاً {$name}،\n\n" . strip_tags($lead) . "\n\n{$rankText}\n\n"
              . "العب التوقعات: {$site}/predict.php\n"
              . "توقّع المشوار: {$site}/bracket.php\n"
              . "الصدارة: {$site}/leaderboard.php\n\n"
              . "────────────────────\n\n"
              . "Hello {$nameEn},\n\n" . strip_tags($leadEn) . "\n\n{$rankTextEn}\n\n"
              . "Predictions: {$site}/predict.php?lang=en\n"
              . "Bracket:     {$site}/bracket.php?lang=en\n"
              . "Leaderboard: {$site}/leaderboard.php?lang=en\n\n"
              . "إلغاء الاشتراك · Unsubscribe: " . self::unsubUrl((int)$user['id']);

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
