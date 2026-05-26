<?php
/**
 * Qahr.php
 * ============================================================
 * محرّك «القهر» 😩💔 — سطر ساخر (لطيف ومحترم) عن خسارة منتخب،
 * مع «عدّاد القهر الوطني» ونصّ مشاركة جاهز للواتساب.
 *
 * مبادئ:
 *   - يعمل بالكامل بلا أي مفتاح API: بنك عبارات عربي بعدّة لهجات
 *     (فصحى / خليجي / مصري / شامي / مغاربي) + مجموعة إنجليزية.
 *   - الذكاء الاصطناعي (Claude) يُحسِّن فقط عند توفّر المفتاح وبعد
 *     تاريخ التفعيل — وإلا نستعمل البنك مباشرة.
 *   - لا يكسر أبداً ولا يرمي استثناءً: كل دالة آمنة (try/catch داخلياً).
 *   - السخرية عائلية ومحترمة: نضحك على الخسارة، لا على الناس/الأوطان/الأديان.
 *
 * العقد مع البطاقات (لا تغيّره):
 *   Qahr::roast(string $team, string $opp, int $g1, int $g2, string $lang, string $dialect=''): string
 *   - $team/$opp = الاسم الإنجليزي الخام كما في البيانات (مثل "Mexico").
 *   - داخلياً نترجمه للعرض عبر team_name() عند بناء الإخراج العربي.
 *   - $lang = 'ar' | 'en'. يُرجّع سطراً قصيراً واحداً (قد يكون '' لكن نفضّل دائماً سطراً جيّداً).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Qahr
{
    /** رمز القهر الموحّد (يُلحَق بنصوص المشاركة) */
    public const EMOJI = '😩💔';

    /** أقصى عدّاد منطقي (حماية من الإفراط) */
    private const COUNTER_MAX = 1000000000;

    // ====================================================
    //  اللهجات المدعومة
    // ====================================================

    /** قائمة اللهجات المدعومة: مفتاح => اسم معروض (عربي/إنجليزي). */
    public static function dialects(): array
    {
        return [
            'fusha'   => ['ar' => 'الفصحى',  'en' => 'Standard Arabic'],
            'khaleeji'=> ['ar' => 'خليجي',   'en' => 'Gulf'],
            'masri'   => ['ar' => 'مصري',    'en' => 'Egyptian'],
            'shami'   => ['ar' => 'شامي',    'en' => 'Levantine'],
            'maghrebi'=> ['ar' => 'مغاربي',  'en' => 'Maghrebi'],
        ];
    }

    // ====================================================
    //  العقد الأساسي: roast()
    // ====================================================

    /**
     * roast() — سطر قهر ساخر قصير لخسارة $team أمام $opp بنتيجة $g1-$g2.
     * اختيار شبه حتمي (بذرة = team+opp+score) فيثبت السطر لنفس المباراة.
     * يحاول الذكاء الاصطناعي أولاً إن كان متاحاً، وإلا فالبنك. لا يرمي أبداً.
     *
     * @return string سطر واحد (قد يكون '' في أسوأ الحالات — والمنادي لديه احتياطي).
     */
    public static function roast(string $team, string $opp, int $g1, int $g2, string $lang, string $dialect = ''): string
    {
        try {
            $lang = ($lang === 'en') ? 'en' : 'ar';
            $g1 = self::clampGoals($g1);
            $g2 = self::clampGoals($g2);
            $dialect = self::normalizeDialect($dialect, $team, $opp, $g1, $g2);

            // الأسماء المعروضة (عربي عبر team_name، إنجليزي = الخام أو مترجم خفيف)
            $teamDisp = self::displayName($team, $lang);
            $oppDisp  = self::displayName($opp, $lang);
            $scoreTxt = $g1 . '-' . $g2;

            // 1) جرّب الذكاء الاصطناعي إن كان مفعّلاً (يُحسِّن فقط).
            if (class_exists('AiContent')
                && method_exists('AiContent', 'roast')
                && method_exists('AiContent', 'enabled')
                && AiContent::enabled()) {
                try {
                    $ai = AiContent::roast($team, $opp, $g1, $g2, $lang, $dialect);
                    if (is_string($ai) && trim($ai) !== '') {
                        return self::oneLine($ai);
                    }
                } catch (\Throwable $e) {
                    // تجاهل — ننزل للبنك.
                }
            }

            // 2) بنك العبارات (يعمل دائماً، بلا مفتاح).
            $line = self::fromBank($lang, $dialect, $team, $opp, $g1, $g2, $teamDisp, $oppDisp, $scoreTxt);
            return self::oneLine($line);
        } catch (\Throwable $e) {
            return '';   // لا نكسر أبداً
        }
    }

    /** يبني السطر من بنك العبارات ويملأ القوالب. */
    private static function fromBank(string $lang, string $dialect, string $team, string $opp, int $g1, int $g2, string $teamDisp, string $oppDisp, string $scoreTxt): string
    {
        $bank = self::bank();
        if ($lang === 'en') {
            $set = $bank['en'];
        } else {
            $set = $bank[$dialect] ?? $bank['fusha'];
        }
        if (empty($set)) return '';

        // بذرة ثابتة: نفس المباراة → نفس السطر.
        $seed = self::seed($team, $opp, $g1, $g2, $dialect);
        $tmpl = $set[$seed % count($set)];

        $heavy = ($g1 + $g2 >= 5) || (abs($g1 - $g2) >= 3);
        return self::fill($tmpl, $teamDisp, $oppDisp, $scoreTxt, $g1, $g2, $heavy);
    }

    /** يملأ القالب بالقيم. */
    private static function fill(string $tmpl, string $team, string $opp, string $score, int $g1, int $g2, bool $heavy): string
    {
        return strtr($tmpl, [
            '{team}'  => $team,
            '{opp}'   => $opp,
            '{score}' => $score,
            '{g1}'    => (string)$g1,
            '{g2}'    => (string)$g2,
        ]);
    }

    // ====================================================
    //  نصّ المشاركة
    // ====================================================

    /**
     * shareText() — سطر جاهز للواتساب: روست + النتيجة + رمز القهر.
     */
    public static function shareText(string $team, string $opp, int $g1, int $g2, string $lang, string $dialect = ''): string
    {
        try {
            $lang = ($lang === 'en') ? 'en' : 'ar';
            $g1 = self::clampGoals($g1);
            $g2 = self::clampGoals($g2);
            $line = self::roast($team, $opp, $g1, $g2, $lang, $dialect);
            if ($line === '') {
                $line = ($lang === 'ar') ? 'قهر… بس بكرة نرجع أقوى' : 'Heartbreak… but we rise again';
            }
            $teamDisp = self::displayName($team, $lang);
            $oppDisp  = self::displayName($opp, $lang);
            if ($lang === 'ar') {
                $head = "{$teamDisp} {$g1} - {$g2} {$oppDisp}";
            } else {
                $head = "{$teamDisp} {$g1} - {$g2} {$oppDisp}";
            }
            return $head . ' — ' . $line . ' ' . self::EMOJI;
        } catch (\Throwable $e) {
            return '';
        }
    }

    // ====================================================
    //  عدّاد القهر الوطني (تجميعي عام)
    // ====================================================

    private static function dir(): string
    {
        $d = rtrim(CACHE_DIR, '/') . '/../data/qahr';
        if (!is_dir($d)) { @mkdir($d, 0755, true); }
        return $d;
    }

    private static function file(): string
    {
        return self::dir() . '/global.json';
    }

    /** bump() — يزيد العدّاد العام بواحد ويُرجّع المجموع الجديد (كتابة ذرّية بقفل). */
    public static function bump(): int
    {
        try {
            $f  = self::file();
            $fp = @fopen($f, 'c+b');
            if (!$fp) return self::total();
            if (!@flock($fp, LOCK_EX)) { fclose($fp); return self::total(); }
            rewind($fp);
            $d = json_decode((string)stream_get_contents($fp), true);
            $n = (is_array($d) ? (int)($d['total'] ?? 0) : 0);
            if ($n < 0) $n = 0;
            if ($n < self::COUNTER_MAX) $n++;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode(['total' => $n], JSON_UNESCAPED_UNICODE));
            fflush($fp);
            @flock($fp, LOCK_UN);
            fclose($fp);
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** total() — يقرأ العدّاد العام (مع قفل قراءة). */
    public static function total(): int
    {
        try {
            $f = self::file();
            if (!is_file($f)) return 0;
            $fp = @fopen($f, 'rb');
            if (!$fp) return 0;
            @flock($fp, LOCK_SH);
            $raw = stream_get_contents($fp);
            @flock($fp, LOCK_UN);
            fclose($fp);
            $d = json_decode((string)$raw, true);
            $n = (is_array($d) ? (int)($d['total'] ?? 0) : 0);
            return ($n < 0) ? 0 : $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ====================================================
    //  أدوات داخلية
    // ====================================================

    private static function clampGoals(int $g): int
    {
        if ($g < 0) return 0;
        if ($g > 30) return 30;
        return $g;
    }

    /** يطبّع مفتاح اللهجة؛ فارغ = اختيار شبه حتمي (يثبت لكل مباراة). */
    private static function normalizeDialect(string $dialect, string $team, string $opp, int $g1, int $g2): string
    {
        $dialect = trim($dialect);
        $keys = array_keys(self::dialects());   // [fusha, khaleeji, masri, shami, maghrebi]
        if ($dialect !== '' && in_array($dialect, $keys, true)) {
            return $dialect;
        }
        // اختيار ثابت لكل مباراة (لا يتغيّر بين الطلبات لنفس النتيجة).
        $idx = self::seed($team, $opp, $g1, $g2, 'dialect') % count($keys);
        return $keys[$idx];
    }

    /** بذرة عددية ثابتة من معطيات المباراة. */
    private static function seed(string $team, string $opp, int $g1, int $g2, string $salt): int
    {
        $key = strtolower(trim($team)) . '|' . strtolower(trim($opp)) . '|' . $g1 . '|' . $g2 . '|' . $salt;
        // crc32 كافٍ (لا حاجة لتعمية) ودائماً موجب.
        return abs(crc32($key));
    }

    /** الاسم المعروض: عربي عبر team_name()، إنجليزي = الخام (أو ترجمة خفيفة عبر team_name إن كانت اللغة en تعطي الخام). */
    private static function displayName(string $raw, string $lang): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ($lang === 'ar') ? 'الفريق' : 'the team';
        }
        if (function_exists('team_name')) {
            // team_name يعتمد على current_lang() للعربية؛ لكن قد يُستدعى roast بلغة مختلفة عن لغة الصفحة.
            // للعربية نأخذ الاسم العربي من الخريطة مباشرة لضمان الصواب بصرف النظر عن لغة الصفحة.
            if ($lang === 'ar' && function_exists('teams_map')) {
                $map = teams_map();
                if (isset($map[$raw][0])) return $map[$raw][0];
            }
            if ($lang === 'en') {
                return $raw;   // البيانات إنجليزية أصلاً
            }
            // احتياط: استعمل team_name (لغة الصفحة الحالية).
            $n = team_name($raw);
            if ($n !== '') return $n;
        }
        return $raw;
    }

    /** يحوّل أي نص لسطر واحد نظيف (يزيل الأسطر والمسافات الزائدة). */
    private static function oneLine(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', (string)$s);
        $s = trim((string)$s);
        // قصّ احتياطي لطول معقول (سطر واحد).
        if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > 180) {
            $s = rtrim(mb_substr($s, 0, 180, 'UTF-8')) . '…';
        }
        return $s;
    }

    // ====================================================
    //  بنك العبارات — لطيف، عائلي، يضحك على الخسارة لا على الناس
    // ====================================================

    /**
     * bank() — قوالب الروست لكل مجموعة. القوالب تستخدم {team} {opp} {score}.
     * كل مجموعة ~8–15 سطراً.
     */
    private static function bank(): array
    {
        return [
            // ---------- الفصحى ----------
            'fusha' => [
                'خسارة {team} أمام {opp} بنتيجة {score}… القهر يا قلبي 😩',
                'انتهت المباراة {score} وبقي الحلم في الإنعاش 💔',
                '{team} لم تخسر فحسب، بل أهدتنا ليلةً من القهر بنتيجة {score}.',
                'ودّعنا {opp} ودّعتنا الأعصاب — {score} ولا حول ولا قوة 😩',
                'بنتيجة {score} تعلّمنا أنّ الكرة مدوّرة… ومُرّة أحياناً.',
                'كان الأمل كبيراً، فجاء {opp} بنتيجة {score} ليصغّره.',
                '{team} قاتلت، لكن {score} لها رأيٌ آخر. نرفع رؤوسنا ونعود.',
                'نتيجة {score} تُكتب بالحبر، والقهر يُكتب في القلب 💔',
                'لا بأس يا {team}… {score} اليوم، وغدًا حكاية أخرى.',
                'سقطت {team} {score} أمام {opp}، وسقط معها معادنا مع النوم الليلة.',
            ],
            // ---------- خليجي ----------
            'khaleeji' => [
                'يا قهر… {team} طاحت {score} قدّام {opp} 😩💔',
                '{score} وخلاص، عاد القلب يدق دق! ما عليه، نرجع أقوى.',
                'والله إن {opp} ما رحمتنا، {score} وقهر لين الصبح.',
                'توقّعنا فرحة، طلعت {score}… الله يعين القلوب 💔',
                'يا {team} وش هالنتيجة {score}؟ بس عادي، الجايّات أحلى إن شاء الله.',
                'القهر اللي صار اليوم {score} ما ينعاد، نشد الهمّة ونرجع.',
                'خذينا {score} وخذينا معاها وجع القلب، بس راسنا مرفوع.',
                'صدق يقولون الكورة حظوظ… {team} {score} واحنا قاهرنا الحظ اليوم 😩',
                'ما خسرنا فريق، خسرنا نومة الليلة بسبب {score}.',
                'عقب هالـ{score} نبي قهوة وسوالف، وبكرة نعدّل المركب.',
            ],
            // ---------- مصري ----------
            'masri' => [
                'قهر يا عم… {team} اتغلبت {score} قدام {opp} 😩💔',
                'النتيجة {score} والقلب مالوش لازمة خالص النهارده!',
                'يا {team} انتي عايزانا ندخل مستشفى؟ {score} كده؟ 💔',
                'خلاص بقى، {score} وخدنا على دماغنا، بس هنرجع أد الدنيا.',
                '{opp} مرحمتش، {score} وسهرنا قهر لحد الفجر.',
                'كله كوم و{score} دي كوم تاني… الكورة بتوجع بصحيح.',
                'مفيش حاجة اسمها كده، {team} {score}؟ ربنا يصبّرنا 😩',
                'الماتش خلص {score}، والقهر لسه فاتح شغله جوه القلب.',
                'عادي يا جماعة، النهارده {score}، وبكرة نقلب الطاولة.',
                'احنا اتعودنا، بس برضه {score} دي وجعت 💔',
            ],
            // ---------- شامي ----------
            'shami' => [
                'يا قهر يا قلبي… {team} انهزمت {score} قدّام {opp} 😩',
                'النتيجة {score} وصار القلب عم يوجع متل العادة 💔',
                'شو هالنتيجة {score} يا {team}؟ بتجنني والله!',
                'ما خسرنا بس، {score} ضيّعت علينا نومة الليلة كمان.',
                '{opp} ما رحمتنا أبداً، {score} وقهر لآخر الليل.',
                'منرفع راسنا منقول {score} اليوم وبكرا منرجع أحلى.',
                'تعبنا وقلبنا تعب، و{score} كتبت قصة القهر.',
                'عنجد الكورة بتوجع، {team} {score}… بس منكمّل.',
                'كل شي بوقتو حلو، إلا {score} هاد ما إلو طعمة 😩',
                'لا تزعل كتير، {score} اليوم، وبكرا في يوم تاني.',
            ],
            // ---------- مغاربي ----------
            'maghrebi' => [
                'يا الحݣرة… {team} تغلبات {score} قدّام {opp} 😩💔',
                'النتيجة {score} والقلب راه يضرب بزّاف!',
                'واش هاد {score} يا {team}؟ زعمة درنا فيها مشكل.',
                'ما خسرناش غير الماتش، خسرنا نعاس الليلة على {score}.',
                '{opp} ما رحمتناش، {score} وقهر حتى للصباح.',
                'نرفعو راسنا، {score} اليوم وغدوة نرجعو أقوى بزّاف.',
                'الكورة تجرح بصّح، {team} {score}… بصّح نكمّلو.',
                'كلشي مزيان غير {score} هادي ما عجباتناش 😩',
                'ماشي مشكل، اليوم {score}، وغدوة نقلبو الموازين.',
                'تعب القلب على {score}… بصّح راسنا مرفوع.',
            ],
            // ---------- English ----------
            'en' => [
                "{team} lost {score} to {opp}… pure heartbreak 😩💔",
                "Final score {score} and our hearts need a medic 💔",
                "{team} didn't just lose, they gifted us a sleepless night at {score}.",
                "{opp} showed no mercy — {score} and here come the tears 😩",
                "{score}: the ball is round, and tonight it's bitter.",
                "Big dreams, then {opp} arrived with a {score} reality check.",
                "{team} fought hard, but {score} had other plans. Heads up, we rise again.",
                "{score} is written in ink, the heartbreak in the heart 💔",
                "It's fine, {team}… {score} today, a new story tomorrow.",
                "{team} went down {score} to {opp}, and so did our good mood 😩",
            ],
        ];
    }
}
