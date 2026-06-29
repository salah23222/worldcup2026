<?php
/**
 * MatchTweets.php — نشر تلقائي لكل مباراة (قبل/بعد) بالعربيّة والإنجليزيّة.
 * ============================================================
 * يُستدعى من cron/tweet.php (كل 15 دقيقة) ويقرّر بنفسه:
 *
 *   PRE-MATCH  → بين 30 و 75 دقيقة قبل ضربة البداية، يُنشَر تنبيهان:
 *                 • تغريدة بالعربيّة (أعلام + ملعب + وقت محلي + رابط)
 *                 • تغريدة بالإنجليزيّة
 *
 *   POST-MATCH → بعد انتهاء المباراة (score.ft موجود)، يُولَّد التقرير
 *                 بالذكاء الاصطناعي (AiContent::forMatch summary) ثم
 *                 يُنشَر تغريدتان:
 *                 • نتيجة + أوّل جملة من تقرير AR + رابط
 *                 • نفس الشيء بالإنجليزيّة
 *
 * الحالة تُحفَظ في cache/x_match_state.json حسب index المباراة:
 *   { "12": { "pre_ar":{"id":"…","at":…}, "pre_en":{…}, "post_ar":{…}, "post_en":{…} } }
 *
 * كل تغريدة تُنشَر مرّة واحدة فقط للأبد (idempotent عبر بوّابة الحالة).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class MatchTweets
{
    /** نافذة النشر القبلي: نُنشر بين هذين الفاصلين قبل ضربة البداية. */
    private const PRE_MIN_SEC = 30 * 60;   // 30 دقيقة
    private const PRE_MAX_SEC = 75 * 60;   // 75 دقيقة (cron 15-min يلتقطها بسهولة)
    // نافذة تغريدة النتيجة: المباريات التي انطلقت خلال آخر 6 ساعات فقط (≈ انتهت خلال
    // آخر ~4 ساعات). تمنع نشر طابور قديم متراكم دفعةً واحدة عند أي انقطاع للكرون،
    // وتضمن أن النتيجة الطازجة تُنشر فور انتهاء المباراة (الكرون كل دقيقتين يلتقطها).
    private const POST_MAX_AGE_SEC = 6 * 3600;

    /** الحدّ الأقصى لتغريدات لكل run cron (حماية من العواصف). */
    public const MAX_PER_RUN = 6;

    // ───────────────────── واجهات الـ cron ─────────────────────

    /** قائمة المباريات التي تستحقّ تغريدة قَبليّة الآن (لم تُنشَر بعد).
     *  مرتّبة زمنياً، أقصى MAX_PER_RUN. */
    public static function pendingPre(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (!self::isRealMatch($m)) continue;
            $ts = DataService::matchTimestamp($m);
            if ($ts === null) continue;
            $diff = $ts - $now;
            if ($diff < self::PRE_MIN_SEC || $diff > self::PRE_MAX_SEC) continue;

            $idx = (int)$m['_index'];
            // تغريدة قَبليّة واحدة ثنائيّة اللغة (bi) — لا تتكرّر (تشمل النظام القديم ar/en)
            if (self::wasSent($idx, 'pre', 'bi')
                || self::wasSent($idx, 'pre', 'ar')
                || self::wasSent($idx, 'pre', 'en')) continue;
            $out[] = ['match' => $m, 'lang' => 'bi'];
        }
        return $out;
    }

    /** قائمة المباريات التي انتهت ولم تُنشَر تغريدتها البعدية بعد. */
    public static function pendingPost(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (!self::isRealMatch($m)) continue;
            if (($m['_status'] ?? '') !== 'finished') continue;
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;

            // نافذة طزاجة: تجاهل المباريات التي مرّ على انطلاقها أكثر من 6 ساعات
            // (لا تُغرِّد طابوراً قديماً متراكماً؛ فقط النتائج الحديثة).
            $ts = DataService::matchTimestamp($m);
            if ($ts !== null && ($now - $ts) > self::POST_MAX_AGE_SEC) continue;

            $idx = (int)$m['_index'];
            // تغريدة نتيجة واحدة ثنائيّة اللغة (bi). تُعتبر مُرسَلة أيضاً لو نُشرت
            // سابقاً بالنظام القديم (ar/en منفصلتين) فلا تتكرّر بعد التحديث.
            if (self::wasSent($idx, 'post', 'bi')
                || self::wasSent($idx, 'post', 'ar')
                || self::wasSent($idx, 'post', 'en')) continue;
            $out[] = ['match' => $m, 'lang' => 'bi', 'ts' => $ts ?? 0];
        }
        // الأحدث أوّلاً (تُنشَر النتيجة الطازجة قبل أي متأخّرة لو تزامنت)
        usort($out, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
        return $out;
    }

    /** ينشر تغريدة قَبليّة (يبني → يرسل → يسجّل). يعيد مصفوفة XPublisher. */
    public static function sendPre(array $m, string $lang, bool $priority = false): array
    {
        // تغريدة واحدة: الرابط داخلها (معها بطاقة صورة → الوصول لا يتأثّر). لا ردّ منفصل
        // (الردّ كان يُحتسب في سقف الـ12 ويظهر «تغريدتين في نفس الوقت» ويضاعف التكلفة).
        $text = self::buildPre($m, $lang, true);
        $img = class_exists('TweetCardImage')
             ? TweetCardImage::generate([$m], ['title' => 'مباراة قادمة', 'subtitle' => 'كأس العالم 2026'])
             : null;
        $r = XPublisher::tweet($text, $img, $priority);
        if ($r['ok']) {
            self::markSent((int)$m['_index'], 'pre', $lang, (string)$r['id']);
        }
        return $r + ['text' => $text];
    }

    /** ينشر تغريدة بعديّة (يولّد التقرير لو لزم → يبني → يرسل → يسجّل). */
    public static function sendPost(array $m, string $lang): array
    {
        // تغريدة واحدة: الرابط داخلها (معها بطاقة النتيجة) — لا ردّ منفصل (يوفّر سقف + تكلفة)
        $text = self::buildPost($m, $lang, true);
        $img = class_exists('TweetCardImage')
             ? TweetCardImage::generate([$m], ['title' => 'نتيجة المباراة', 'subtitle' => 'كأس العالم 2026', 'mode' => 'result'])
             : null;
        // أولويّة: تتجاوز نتيجةُ المباراة السقفَ اليومي المشترك (هي الأهمّ والأعلى تفاعلاً)
        $r = XPublisher::tweet($text, $img, true);
        if ($r['ok']) {
            self::markSent((int)$m['_index'], 'post', $lang, (string)$r['id']);
        }
        return $r + ['text' => $text];
    }

    // ───────────────────── المباراة القادمة للفائز (الأدوار الإقصائيّة) ─────────────────────

    /** ترجمة اسم الدور للعربيّة. */
    private static function roundArName(string $r): string
    {
        $map = ['Round of 32' => 'دور الـ32', 'Round of 16' => 'دور الـ16',
                'Quarter-final' => 'ربع النهائي', 'Semi-final' => 'نصف النهائي',
                'Third place' => 'تحديد المركز الثالث', 'Final' => 'النهائي'];
        foreach ($map as $en => $ar) { if (stripos($r, $en) !== false) return $ar; }
        return $r;
    }

    /**
     * nextMatchFor() — يجد مباراة الدور التالي للفائز من مباراة إقصائيّة منتهية.
     * يبحث عن «W{index}» أو عن اسم الفائز نفسه في بقيّة المباريات (يصمد سواء حلّت
     * openfootball الخانة أم بقيت نائبة). يعيد ['next','winner','opp'] أو null (نهائيّ).
     */
    public static function nextMatchFor(array $m): ?array
    {
        $idx = (int)($m['_index'] ?? -1);
        $ft  = $m['score']['ft'] ?? null;
        if ($idx < 0 || !is_array($ft) || !isset($ft[0], $ft[1])) return null;
        $a = (int)$ft[0]; $b = (int)$ft[1]; $winner = null;
        if ($a > $b)     $winner = (string)($m['team1'] ?? '');
        elseif ($b > $a) $winner = (string)($m['team2'] ?? '');
        else {
            $p = $m['score']['p'] ?? null;   // ركلات الترجيح تحسم
            if (is_array($p) && isset($p[0], $p[1])) {
                $winner = ((int)$p[0] >= (int)$p[1]) ? (string)($m['team1'] ?? '') : (string)($m['team2'] ?? '');
            }
        }
        if ($winner === null || $winner === '') return null;

        $token = 'W' . $idx;
        foreach (DataService::allMatches() as $nm) {
            // المباراة التالية في دور إقصائيّ لاحق (فهرس أكبر) — لا مباريات المجموعات السابقة
            if ((int)($nm['_index'] ?? -1) <= $idx) continue;
            if (!preg_match('/Round of|Quarter|Semi|Final/i', (string)($nm['round'] ?? ''))) continue;
            $t1 = trim((string)($nm['team1'] ?? '')); $t2 = trim((string)($nm['team2'] ?? ''));
            $hit1 = (strcasecmp($t1, $token) === 0 || $t1 === $winner);
            $hit2 = (strcasecmp($t2, $token) === 0 || $t2 === $winner);
            if ($hit1 || $hit2) return ['next' => $nm, 'winner' => $winner, 'opp' => $hit1 ? $t2 : $t1];
        }
        return null;   // النهائي أو لا مباراة تالية
    }

    /** مباريات إقصائيّة منتهية لم تُنشَر لها تغريدة «الفائز القادمة» بعد (نافذة طزاجة). */
    public static function pendingNext(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (($m['_status'] ?? '') !== 'finished') continue;
            if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) continue;
            if (!preg_match('/Round of|Quarter|Semi|Final/i', (string)($m['round'] ?? ''))) continue;
            $ts = DataService::matchTimestamp($m);
            if ($ts !== null && ($now - $ts) > self::POST_MAX_AGE_SEC) continue;
            $idx = (int)$m['_index'];
            if (self::wasSent($idx, 'next', 'bi')) continue;
            $nx = self::nextMatchFor($m);
            if ($nx === null) continue;
            $out[] = ['match' => $m, 'next' => $nx, 'ts' => $ts ?? 0];
        }
        usort($out, fn($x, $y) => ($y['ts']) <=> ($x['ts']));
        return $out;
    }

    /** نصّ تغريدة «تأهّل الفائز ومباراته القادمة» — ثنائيّة اللغة. */
    public static function buildNext(array $m, array $nx): string
    {
        $win = (string)$nx['winner']; $oppRaw = (string)$nx['opp']; $next = $nx['next'];
        $wf = self::flagEmoji($win);
        $wa = self::nameInLang($win, 'ar'); $we = self::nameInLang($win, 'en');
        $oppKnown = function_exists('is_real_team') && is_real_team($oppRaw);   // الخصم محسوم؟
        $oa = $oppKnown ? self::nameInLang($oppRaw, 'ar') : 'يُحدَّد لاحقاً';
        $oe = $oppKnown ? self::nameInLang($oppRaw, 'en') : 'TBD';
        $of = $oppKnown ? self::flagEmoji($oppRaw) : '';
        $roundAr = self::roundArName((string)($next['round'] ?? ''));
        $roundEn = (string)($next['round'] ?? '');
        $ts = DataService::matchTimestamp($next);
        $hm = $ts ? date('H:i', $ts) : '';
        $url  = self::link('match.php?id=' . (int)($next['_index'] ?? 0) . '&lang=ar');
        $tags = class_exists('Hashtags') ? Hashtags::forMatch($next)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup2026');

        $arBlock = "🎉 {$wf} {$wa} يتأهّل!\n🔜 المباراة القادمة" . ($roundAr !== '' ? " · {$roundAr}" : '')
                 . ":\n{$wf} {$wa} ضدّ " . trim("{$oa} {$of}") . ($hm !== '' ? "\n🕐 {$hm}" : '');
        $enBlock = "🎉 {$we} advance!\n🔜 Next" . ($roundEn !== '' ? " · {$roundEn}" : '') . ": {$we} vs {$oe}";
        $msg = $arBlock . "\n\n" . $enBlock . "\n" . $url . "\n" . $tags;
        return self::fitWithin($msg, 280, $url, $tags);
    }

    /** ينشر تغريدة «الفائز القادمة» (مع بطاقة المباراة التالية) ويسجّلها. */
    public static function sendNext(array $m, array $nx): array
    {
        $text = self::buildNext($m, $nx);
        $img  = class_exists('TweetCardImage')
              ? TweetCardImage::generate([$nx['next']], ['title' => 'المباراة القادمة', 'subtitle' => 'كأس العالم 2026'])
              : null;
        $r = XPublisher::tweet($text, $img);
        if (!empty($r['ok'])) self::markSent((int)$m['_index'], 'next', 'bi', (string)$r['id']);
        return $r + ['text' => $text];
    }

    // ───────────────────── استطلاع «من سيفوز؟» (للمباريات الكبرى) ─────────────────────

    /** مباراة كبيرة = أحد الفريقين ضمن أفضل 24 في تصنيف FIFA. */
    public static function isBigMatch(array $m): bool
    {
        if (!class_exists('Rankings')) return false;
        $r1 = (int)(Rankings::of((string)($m['team1'] ?? '')) ?: 999);
        $r2 = (int)(Rankings::of((string)($m['team2'] ?? '')) ?: 999);
        return min($r1, $r2) <= 24;
    }

    /** المباريات الكبرى القادمة (بين ساعة و14 ساعة) التي لم يُنشَر استطلاعها بعد. */
    public static function pendingPolls(int $now = 0): array
    {
        $now = $now ?: time();
        $out = [];
        foreach (DataService::allMatches() as $m) {
            if (!self::isRealMatch($m) || !self::isBigMatch($m)) continue;
            $ts = DataService::matchTimestamp($m);
            if ($ts === null) continue;
            $diff = $ts - $now;
            if ($diff < 3600 || $diff > 14 * 3600) continue;   // مهلة كافية لجمع الأصوات قبل الانطلاق
            if (self::wasSent((int)$m['_index'], 'poll', 'ar')) continue;
            $out[] = $m;
        }
        return $out;
    }

    /** يبني استطلاع المباراة: ['text','options','minutes']. الاستطلاع يُغلق عند الانطلاق. */
    public static function buildPoll(array $m): array
    {
        $t1 = (string)($m['team1'] ?? ''); $t2 = (string)($m['team2'] ?? '');
        $a1 = self::nameInLang($t1, 'ar'); $a2 = self::nameInLang($t2, 'ar');
        $f1 = self::flagEmoji($t1);        $f2 = self::flagEmoji($t2);
        $tags = class_exists('Hashtags') ? Hashtags::forMatch($m)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup2026');
        $text = "🗳️ من سيفوز؟ · Who wins?\n{$f1} {$a1} 🆚 {$a2} {$f2}\n{$tags}";
        $opts = [
            trim("{$f1} {$a1}"),
            '🤝 ' . 'تعادل',
            trim("{$f2} {$a2}"),
        ];
        $ts = DataService::matchTimestamp($m);
        $mins = $ts ? (int)floor(($ts - time()) / 60) : 720;
        $mins = max(30, min(1440, $mins));   // يُغلق عند الانطلاق (30 د .. 24 س)
        return ['text' => self::fitWithin($text, 280, '', $tags), 'options' => $opts, 'minutes' => $mins];
    }

    /** ينشر استطلاع المباراة (تغريدة استطلاع مستقلّة) + الرابط في ردّ. */
    public static function sendPoll(array $m, bool $priority = false): array
    {
        $p = self::buildPoll($m);
        $r = XPublisher::tweet($p['text'], null, $priority, null,
            ['options' => $p['options'], 'minutes' => $p['minutes']]);
        if (!empty($r['ok'])) {
            self::markSent((int)$m['_index'], 'poll', 'ar', (string)$r['id']);
        }
        return $r + ['text' => $p['text']];
    }

    // ───────────────────── بانيات النصّ ─────────────────────

    /** تغريدة قَبل المباراة — ثنائيّة اللغة (عربي فوق، إنجليزي تحت) مثل تغريدة النتيجة. */
    public static function buildPre(array $m, string $lang = 'bi', bool $withLink = true): string
    {
        $t1 = (string)($m['team1'] ?? ''); $t2 = (string)($m['team2'] ?? '');
        $a1 = self::nameInLang($t1, 'ar'); $a2 = self::nameInLang($t2, 'ar');
        $e1 = self::nameInLang($t1, 'en'); $e2 = self::nameInLang($t2, 'en');
        $f1 = self::flagEmoji($t1);        $f2 = self::flagEmoji($t2);
        $ts = DataService::matchTimestamp($m);
        $hm = $ts ? date('H:i', $ts) : '';
        $ground = trim((string)($m['ground'] ?? ''));
        $url  = self::link('match.php?id=' . (int)$m['_index'] . '&lang=ar');
        $tags = class_exists('Hashtags') ? Hashtags::forMatch($m)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup2026');
        $soon = ($ts !== null && ($ts - time()) <= 5400);   // ≤90 دقيقة → «بعد قليل»، وإلّا «اليوم»
        $whenLine = $hm !== '' ? "🕐 {$hm}" . ($ground !== '' ? " · 🏟️ {$ground}" : '') : ($ground !== '' ? "🏟️ {$ground}" : '');

        // كتلة عربيّة (مع الوقت/الملعب) ثمّ كتلة إنجليزيّة
        $arBlock = "⚽ " . ($soon ? "بعد قليل في كأس العالم 2026" : "اليوم في كأس العالم 2026") . "\n{$f1} {$a1} ضدّ {$a2} {$f2}";
        if ($whenLine !== '') $arBlock .= "\n{$whenLine}";
        $enBlock = "⚽ " . ($soon ? "Coming up at the FIFA World Cup" : "Today at the FIFA World Cup") . "\n{$f1} {$e1} vs {$e2} {$f2}";
        $cta = "🔮 توقّعك للنتيجة؟ · Your score prediction? 👇";

        $msg = $arBlock . "\n\n" . $enBlock . "\n\n" . $cta;
        if ($withLink) $msg .= "\n" . $url;          // عند false: الرابط في ردّ (يرفع الوصول)
        $msg .= "\n" . $tags;
        return self::fitWithin($msg, 280, $withLink ? $url : '', $tags);
    }

    /**
     * تغريدة نتيجة المباراة — ثنائيّة اللغة في تغريدة واحدة (عربي فوق، إنجليزي تحت).
     * مبسّطة: «نهاية المباراة» + الفريقان والنتيجة + رابط التفاصيل (بلا تقرير ذكاء).
     */
    public static function buildPost(array $m, string $lang = 'bi', bool $withLink = true): string
    {
        $t1  = (string)($m['team1'] ?? '');
        $t2  = (string)($m['team2'] ?? '');
        $f1  = self::flagEmoji($t1);
        $f2  = self::flagEmoji($t2);
        $g1  = (int)$m['score']['ft'][0];
        $g2  = (int)$m['score']['ft'][1];
        $url = self::link('match.php?id=' . (int)$m['_index'] . '&lang=ar');
        // هاشتاكات ذكيّة: #الفريق1 #الفريق2 #المضيف + الأساس القصير
        $tags = class_exists('Hashtags') ? Hashtags::forMatch($m)
              : (defined('X_HASHTAGS') ? X_HASHTAGS : '#FIFAWorldCup26');

        $ar1 = self::nameInLang($t1, 'ar'); $ar2 = self::nameInLang($t2, 'ar');
        $en1 = self::nameInLang($t1, 'en'); $en2 = self::nameInLang($t2, 'en');

        // ركلات الترجيح إن وُجدت
        $penAr = $penEn = '';
        if (isset($m['score']['p']) && is_array($m['score']['p'])) {
            $p1 = (int)$m['score']['p'][0]; $p2 = (int)$m['score']['p'][1];
            $penAr = " (ركلات الترجيح {$p1}–{$p2})";
            $penEn = " (penalties {$p1}–{$p2})";
        }

        // سياق الجولة: اسم المجموعة (أو الدور الإقصائي) — يظهر بجانب «نهاية المباراة»
        $grp = trim((string)($m['group'] ?? ''));
        $arCtx = $enCtx = '';
        // الحرف بعد «Group» تحديداً — لا أوّل [A-L] (وإلّا التقط «G» من Group نفسها)
        if ($grp !== '' && preg_match('/Group\s*([A-L])/i', $grp, $gm)) {
            $gL = strtoupper($gm[1]); $arCtx = ' · المجموعة ' . $gL; $enCtx = ' · Group ' . $gL;
        } elseif (($m['round'] ?? '') !== '') {
            $arCtx = $enCtx = ' · ' . (string)$m['round'];
        }

        // عربي فوق ثم إنجليزي تحت — كل كتلة: «نهاية المباراة» + المجموعة + الفريقان والنتيجة
        $arBlock = "🏁 نهاية المباراة{$arCtx}\n{$f1} {$ar1} {$g1} - {$g2} {$ar2} {$f2}{$penAr}";
        $enBlock = "🏁 Full time{$enCtx}\n{$f1} {$en1} {$g1} - {$g2} {$en2} {$f2}{$penEn}";
        // سؤال تفاعليّ (يدعو للردّ — أقوى إشارة للخوارزميّة) بدل «اضغط هنا»
        $cta = "🌟 من رجل المباراة عندك؟ · Who's your Player of the Match?";

        $msg = $arBlock . "\n\n" . $enBlock . "\n\n" . $cta;
        if ($withLink) $msg .= "\n" . $url;          // عند false: الرابط يُنشَر في ردّ (يرفع الوصول)
        $msg .= "\n" . $tags;
        return self::fitWithin($msg, 280, $withLink ? $url : '', $tags);
    }

    // ───────────────────── الحالة ─────────────────────

    private static function stateFile(): string
    {
        return rtrim(CACHE_DIR, '/') . '/x_match_state.json';
    }

    private static function state(): array
    {
        $f = self::stateFile();
        if (!is_file($f)) return [];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }

    private static function saveState(array $s): void
    {
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents(self::stateFile(), json_encode($s, JSON_UNESCAPED_UNICODE));
    }

    public static function wasSent(int $idx, string $type, string $lang): bool
    {
        $s = self::state();
        $k = "{$type}_{$lang}";
        return isset($s[(string)$idx][$k]) && !empty($s[(string)$idx][$k]['id']);
    }

    public static function markSent(int $idx, string $type, string $lang, string $tweetId): void
    {
        $s = self::state();
        $k = "{$type}_{$lang}";
        if (!isset($s[(string)$idx])) $s[(string)$idx] = [];
        $s[(string)$idx][$k] = ['id' => $tweetId, 'at' => time()];
        self::saveState($s);
    }

    public static function recentLog(int $n = 12): array
    {
        $s = self::state();
        $rows = [];
        foreach ($s as $idx => $slots) {
            foreach ($slots as $slot => $v) {
                if (empty($v['id'])) continue;
                $rows[] = ['idx' => (int)$idx, 'slot' => $slot, 'id' => $v['id'], 'at' => (int)$v['at']];
            }
        }
        usort($rows, fn($a, $b) => $b['at'] <=> $a['at']);
        return array_slice($rows, 0, $n);
    }

    // ───────────────────── مساعدات ─────────────────────

    private static function isRealMatch(array $m): bool
    {
        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        return $t1 !== '' && $t2 !== ''
            && function_exists('is_real_team')
            && is_real_team($t1) && is_real_team($t2)
            && isset($m['_index']);
    }

    /** اسم المنتخب باللغة المطلوبة (مستقل عن current_lang).
     *  ملاحظة: مفاتيح teams_map case-sensitive، وقيمتها [0]=AR ،[1]=flag. */
    private static function nameInLang(string $raw, string $lang): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        // حلّ عناصر الإقصائيات النائبة («1L»→England · «3..»→الثالث · «W77»→الفائز) احتياطاً
        if (function_exists('ko_resolve')) $raw = ko_resolve($raw);
        if ($lang !== 'ar') return $raw;
        if (!function_exists('teams_map')) return $raw;
        $map = teams_map();
        return isset($map[$raw][0]) ? $map[$raw][0] : $raw;
    }

    /** علم Unicode من ISO-2 المخزَّن في teams_ar.php. */
    private static function flagEmoji(string $team): string
    {
        if ($team === '' || !function_exists('team_flag')) return '';
        $cc = strtoupper((string)team_flag($team));
        if (strlen($cc) !== 2 || !ctype_alpha($cc)) return '';
        $a = mb_chr(ord($cc[0]) - 65 + 0x1F1E6, 'UTF-8');
        $b = mb_chr(ord($cc[1]) - 65 + 0x1F1E6, 'UTF-8');
        return $a . $b;
    }

    /** أوّل جملة من نصّ (يدعم العربيّة والإنجليزيّة). */
    private static function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') return '';
        // علامات نهاية الجملة العربيّة (؟ ؛ .) والإنجليزيّة (. ! ?)
        if (preg_match('/^(.{20,180}?[\.\!\?؟])(\s|$)/u', $text, $mm)) {
            return trim($mm[1]);
        }
        // لا توجد علامة → قطع عند حد معقول
        return self::ellipsize($text, 160);
    }

    /** يحدّ النصّ مع … عند الحاجة (codepoints UTF-8). */
    private static function ellipsize(string $s, int $max): string
    {
        if (mb_strlen($s, 'UTF-8') <= $max) return $s;
        return mb_substr($s, 0, $max - 1, 'UTF-8') . '…';
    }

    /** يضمن أنّ التغريدة ≤ $cap حرفاً مع حفظ الرابط والوسوم. */
    private static function fitWithin(string $msg, int $cap, string $keepUrl = '', string $keepTags = ''): string
    {
        if (mb_strlen($msg, 'UTF-8') <= $cap) return $msg;
        $tail = '';
        if ($keepUrl !== '')  $tail .= "\n" . $keepUrl;
        if ($keepTags !== '') $tail .= "\n" . $keepTags;
        $budget = $cap - mb_strlen($tail, 'UTF-8') - 1;
        $head = mb_substr($msg, 0, max(0, $budget), 'UTF-8');
        return rtrim($head) . '…' . $tail;
    }

    /** رابط مطلق (نفس آلية TweetComposer). */
    private static function link(string $path = ''): string
    {
        $base = defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : 'https://wcup2026.org';
        return $base . '/' . ltrim($path, '/');
    }
}
