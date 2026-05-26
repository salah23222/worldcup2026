<?php
/**
 * AiContent.php
 * ============================================================
 * محتوى ذكي (Claude API) لكل مباراة: معاينة قبلها وملخّص بعدها،
 * بالعربية أو الإنجليزية حسب لغة الزائر.
 *
 * مبادئ:
 *   - معطّل كلياً إن كان CLAUDE_API_KEY فارغاً → الدوال تُرجّع null بهدوء.
 *   - يُولَّد النص مرة واحدة لكل (مباراة + نوع + لغة) ويُخزَّن في cache/ —
 *     فلا تتكرّر تكلفة الـAPI، والزوّار التاليون يقرؤون المخزَّن فوراً.
 *   - لا يُخترع وقائع (تشكيلات/إصابات/أرقام) — يلتزم بمعطيات المباراة فقط.
 *   - أي فشل (شبكة/مفتاح خاطئ) → null، فلا تنكسر الصفحة.
 *
 * طلب الـAPI: cURL إلى /v1/messages، موديل Haiku (الأرخص)، مع
 * prompt caching على نظام التعليمات.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class AiContent
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /** هل الميزة مفعّلة (يوجد مفتاح)؟ */
    public static function enabled(): bool
    {
        if (!defined('CLAUDE_API_KEY') || CLAUDE_API_KEY === '') {
            return false;
        }
        // بوّابة التفعيل الزمنية: لا يعمل (ولا يصرف) قبل التاريخ المحدّد.
        if (defined('AI_ACTIVATE_FROM') && AI_ACTIVATE_FROM !== '' && date('Y-m-d') < AI_ACTIVATE_FROM) {
            return false;
        }
        return true;
    }

    /**
     * forMatch() — يُرجّع نص المعاينة/الملخّص لمباراة (مخزَّن أو مُولَّد)، أو null.
     * $type: 'preview' | 'summary'
     */
    public static function forMatch(array $m, string $type): ?string
    {
        if (!self::enabled()) return null;
        $type = ($type === 'summary') ? 'summary' : 'preview';

        $idx = (int)($m['_index'] ?? -1);
        if ($idx < 0) return null;

        // يحتاج فريقين محدّدين (لا placeholders إقصائية)
        $t1 = trim($m['team1'] ?? '');
        $t2 = trim($m['team2'] ?? '');
        if (!is_real_team($t1) || !is_real_team($t2)) return null;

        $finished = isset($m['score']['ft']) && is_array($m['score']['ft']);
        if ($type === 'summary' && !$finished) return null;

        $lang = current_lang();
        $file = rtrim(CACHE_DIR, '/') . "/ai_{$type}_{$idx}_{$lang}.txt";

        // مخزَّن؟ أعِده فوراً
        if (is_file($file)) {
            $c = @file_get_contents($file);
            if ($c !== false && trim($c) !== '') return $c;
        }

        // ولّد ثم خزّن
        $text = self::generate($m, $type, $lang);
        if ($text !== null && trim($text) !== '') {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($file, $text);
            return $text;
        }
        return null;
    }

    /**
     * matchPrediction() — توقّع الذكاء الاصطناعي لنتيجة مباراة ['p1'=>int,'p2'=>int].
     * يُولَّد مرة واحدة لكل مباراة (مستقل عن اللغة) ويُخزَّن. أو null.
     */
    public static function matchPrediction(array $m): ?array
    {
        if (!self::enabled()) return null;
        $idx = (int)($m['_index'] ?? -1);
        if ($idx < 0) return null;
        $t1 = trim($m['team1'] ?? '');
        $t2 = trim($m['team2'] ?? '');
        if (!is_real_team($t1) || !is_real_team($t2)) return null;

        $file = rtrim(CACHE_DIR, '/') . "/aipred_{$idx}.json";
        if (is_file($file)) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (is_array($d) && isset($d['p1'], $d['p2'])) return $d;
        }

        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => 20,
            'system'     => [[
                'type' => 'text',
                'text' => 'You are a football analyst predicting FIFA World Cup 2026 results. '
                    . 'Reply with ONLY the predicted final score as two numbers like 2-1 (home-away). No words.',
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => "Predict the final score for: {$t1} (home) vs {$t2} (away). Format: N-N",
            ]],
        ];
        $resp = self::call($payload);
        if ($resp === null) return null;
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
        }
        if (!preg_match('/(\d{1,2})\s*[-:]\s*(\d{1,2})/', $text, $mm)) return null;
        $d = ['p1' => min(30, (int)$mm[1]), 'p2' => min(30, (int)$mm[2])];
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($file, json_encode($d));
        return $d;
    }

    /** يبني الطلب ويستدعي الـAPI ويستخرج النص */
    private static function generate(array $m, string $type, string $lang): ?string
    {
        $isAr = ($lang === 'ar');
        $t1   = team_name(trim($m['team1'] ?? ''));
        $t2   = team_name(trim($m['team2'] ?? ''));
        $round  = trim($m['round'] ?? '');
        $group  = trim($m['group'] ?? '');
        $ground = trim($m['ground'] ?? '');
        $ts     = DataService::matchTimestamp($m);
        $date   = $ts ? gmdate('Y-m-d', $ts) : '';

        // حقائق المباراة (تُعطى للنموذج — لا يخترع غيرها)
        $facts = "Match: {$t1} vs {$t2}\n";
        if ($round)  $facts .= "Stage: {$round}\n";
        if ($group)  $facts .= "Group: {$group}\n";
        if ($ground) $facts .= "Venue: {$ground}\n";
        if ($date)   $facts .= "Date: {$date}\n";
        $facts .= "Tournament: FIFA World Cup 2026 (Canada, Mexico, USA)\n";
        if ($type === 'summary') {
            $g1 = (int)$m['score']['ft'][0];
            $g2 = (int)$m['score']['ft'][1];
            $facts .= "Final score: {$t1} {$g1} - {$g2} {$t2}\n";
            if (isset($m['score']['p']) && is_array($m['score']['p'])) {
                $facts .= "Penalty shootout: {$m['score']['p'][0]} - {$m['score']['p'][1]}\n";
            }
        }

        $langWord = $isAr ? 'Arabic' : 'English';
        $kind = ($type === 'summary')
            ? ($isAr ? 'ملخّصاً قصيراً بعد المباراة' : 'a short post-match summary')
            : ($isAr ? 'معاينة قصيرة قبل المباراة' : 'a short pre-match preview');

        $system = "You are a professional football writer for a FIFA World Cup 2026 website. "
            . "Write strictly in {$langWord}. Produce {$kind}: engaging, factual, 80-120 words. "
            . "Output ONLY the body text as 1-2 plain paragraphs. "
            . "Do NOT add any title, heading, label, bullet, or markdown symbols (no #, *, etc.) — "
            . "start directly with the first sentence. "
            . "Use ONLY the facts provided plus widely-known general background about the teams. "
            . "Do NOT invent specific lineups, injuries, quotes, dates, or statistics that are not given.";

        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => defined('AI_MAX_TOKENS') ? AI_MAX_TOKENS : 700,
            'system'     => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => "Write the text now based on these facts:\n\n" . $facts,
            ]],
        ];

        $resp = self::call($payload);
        if ($resp === null) return null;
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                return self::clean($block['text']);
            }
        }
        return null;
    }

    /**
     * dailyTrivia() — سؤال معرفة يومي (اختيار من متعدد) عن كأس العالم.
     * يُولَّد مرة واحدة لكل (يوم + لغة) ويُخزَّن.
     * يُرجّع ['q'=>..., 'options'=>[4], 'correct'=>0..3, 'explain'=>...] أو null.
     */
    public static function dailyTrivia(?string $lang = null): ?array
    {
        $lang = $lang ?: current_lang();
        // الذكاء الاصطناعي معطّل (أو قبل تاريخ التفعيل) → بنك أسئلة منسَّق يدور يومياً.
        if (!self::enabled()) return self::fallbackTrivia($lang);
        $day  = date('Y-m-d');
        $file = rtrim(CACHE_DIR, '/') . "/trivia_{$day}_{$lang}.json";
        if (is_file($file)) {
            $d = json_decode((string)@file_get_contents($file), true);
            if (self::validTrivia($d)) return $d;
        }

        $langWord = ($lang === 'ar') ? 'Arabic' : 'English';
        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => 500,
            'system'     => [[
                'type' => 'text',
                'text' => "You write fun, factual multiple-choice trivia about FIFA World Cup history "
                    . "(teams, players, records, hosts, finals). Difficulty: medium. "
                    . "Write everything in {$langWord}. "
                    . 'Reply with ONLY valid minified JSON, no markdown, in this exact shape: '
                    . '{"q":"question","options":["a","b","c","d"],"correct":0,"explain":"one short sentence"}. '
                    . '"correct" is the 0-based index of the right option. Exactly 4 options.',
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => 'Give today\'s World Cup trivia question as JSON.',
            ]],
        ];
        $resp = self::call($payload);
        if ($resp === null) return self::fallbackTrivia($lang);
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
        }
        // أزل أي أسوار ```json
        $text = trim(preg_replace('/^```[a-z]*|```$/m', '', $text));
        $d = json_decode($text, true);
        if (!self::validTrivia($d)) return self::fallbackTrivia($lang);
        $d['correct'] = (int)$d['correct'];
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($file, json_encode($d, JSON_UNESCAPED_UNICODE));
        return $d;
    }

    private static function validTrivia($d): bool
    {
        return is_array($d)
            && !empty($d['q'])
            && isset($d['options']) && is_array($d['options']) && count($d['options']) === 4
            && isset($d['correct']) && (int)$d['correct'] >= 0 && (int)$d['correct'] <= 3;
    }

    /**
     * fallbackTrivia() — سؤال اليوم من بنك حقائق منسَّق (يعمل بلا ذكاء اصطناعي).
     * يدور تلقائياً كل يوم (حسب رقم اليوم في السنة) فيتغيّر يومياً، وثابت داخل اليوم
     * الواحد (مهم: الخادم يعيد اشتقاقه نفسه للتحقّق من الإجابة).
     */
    public static function fallbackTrivia(string $lang): array
    {
        $bank = self::triviaBank();
        $row  = $bank[(int)date('z') % count($bank)];
        $L    = $row[$lang] ?? $row['en'];
        return [
            'q'       => $L['q'],
            'options' => $L['options'],
            'correct' => (int)$row['correct'],
            'explain' => $L['explain'],
        ];
    }

    /** بنك أسئلة كأس العالم (حقائق عامة موثّقة) — عربي/إنجليزي، خيار صحيح موحّد الترتيب. */
    private static function triviaBank(): array
    {
        return [
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب فاز بكأس العالم 2018؟', 'options' => ['البرازيل','فرنسا','ألمانيا','كرواتيا'], 'explain' => 'فازت فرنسا على كرواتيا 4–2 في نهائي روسيا 2018.'],
             'en' => ['q' => 'Which team won the 2018 World Cup?', 'options' => ['Brazil','France','Germany','Croatia'], 'explain' => 'France beat Croatia 4–2 in the 2018 final in Russia.']],
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب فاز بكأس العالم 2022؟', 'options' => ['فرنسا','الأرجنتين','البرازيل','المغرب'], 'explain' => 'فازت الأرجنتين على فرنسا بركلات الترجيح في نهائي قطر 2022.'],
             'en' => ['q' => 'Which team won the 2022 World Cup?', 'options' => ['France','Argentina','Brazil','Morocco'], 'explain' => 'Argentina beat France on penalties in the 2022 final in Qatar.']],
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب توّج بكأس العالم أكثر من غيره؟', 'options' => ['ألمانيا','البرازيل','إيطاليا','الأرجنتين'], 'explain' => 'البرازيل الأكثر تتويجاً بخمسة ألقاب.'],
             'en' => ['q' => 'Which nation has won the most World Cups?', 'options' => ['Germany','Brazil','Italy','Argentina'], 'explain' => 'Brazil are the most successful with five titles.']],
            ['correct' => 1,
             'ar' => ['q' => 'كم عدد ألقاب البرازيل في كأس العالم؟', 'options' => ['4','5','3','6'], 'explain' => 'فازت البرازيل بخمسة ألقاب: 1958، 1962، 1970، 1994، 2002.'],
             'en' => ['q' => 'How many World Cup titles has Brazil won?', 'options' => ['4','5','3','6'], 'explain' => 'Brazil won five: 1958, 1962, 1970, 1994, 2002.']],
            ['correct' => 0,
             'ar' => ['q' => 'أين أُقيمت أول نسخة من كأس العالم عام 1930؟', 'options' => ['الأوروغواي','البرازيل','إيطاليا','الأرجنتين'], 'explain' => 'استضافت الأوروغواي أول بطولة عام 1930 وفازت بها.'],
             'en' => ['q' => 'Where was the first World Cup held in 1930?', 'options' => ['Uruguay','Brazil','Italy','Argentina'], 'explain' => 'Uruguay hosted and won the first tournament in 1930.']],
            ['correct' => 2,
             'ar' => ['q' => 'كم عدد المنتخبات المشاركة في كأس العالم 2026؟', 'options' => ['32','40','48','24'], 'explain' => 'نسخة 2026 هي الأولى بـ48 منتخباً.'],
             'en' => ['q' => 'How many teams play in the 2026 World Cup?', 'options' => ['32','40','48','24'], 'explain' => '2026 is the first edition with 48 teams.']],
            ['correct' => 2,
             'ar' => ['q' => 'كم دولة تستضيف كأس العالم 2026؟', 'options' => ['دولة واحدة','دولتان','ثلاث دول','أربع دول'], 'explain' => 'تستضيفها ثلاث دول: كندا والمكسيك والولايات المتحدة.'],
             'en' => ['q' => 'How many countries host the 2026 World Cup?', 'options' => ['One','Two','Three','Four'], 'explain' => 'Three hosts: Canada, Mexico and the United States.']],
            ['correct' => 2,
             'ar' => ['q' => 'أي دولة ليست من مستضيفي كأس العالم 2026؟', 'options' => ['الولايات المتحدة','كندا','البرازيل','المكسيك'], 'explain' => 'المستضيفون هم كندا والمكسيك والولايات المتحدة — وليست البرازيل.'],
             'en' => ['q' => 'Which country is NOT a 2026 host?', 'options' => ['United States','Canada','Brazil','Mexico'], 'explain' => 'The hosts are Canada, Mexico and the USA — not Brazil.']],
            ['correct' => 1,
             'ar' => ['q' => 'من هو الهدّاف التاريخي لكأس العالم؟', 'options' => ['رونالدو','ميروسلاف كلوزه','ليونيل ميسي','توماس مولر'], 'explain' => 'الألماني ميروسلاف كلوزه برصيد 16 هدفاً.'],
             'en' => ['q' => 'Who is the all-time top scorer in World Cup history?', 'options' => ['Ronaldo','Miroslav Klose','Lionel Messi','Thomas Müller'], 'explain' => "Germany's Miroslav Klose with 16 goals."]],
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب فاز بكأس العالم 2014؟', 'options' => ['الأرجنتين','ألمانيا','البرازيل','هولندا'], 'explain' => 'فازت ألمانيا على الأرجنتين 1–0 في نهائي البرازيل 2014.'],
             'en' => ['q' => 'Which team won the 2014 World Cup?', 'options' => ['Argentina','Germany','Brazil','Netherlands'], 'explain' => 'Germany beat Argentina 1–0 in the 2014 final in Brazil.']],
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب فاز بكأس العالم 2010؟', 'options' => ['هولندا','إسبانيا','ألمانيا','الأوروغواي'], 'explain' => 'فازت إسبانيا على هولندا 1–0 في نهائي جنوب أفريقيا 2010.'],
             'en' => ['q' => 'Which team won the 2010 World Cup?', 'options' => ['Netherlands','Spain','Germany','Uruguay'], 'explain' => 'Spain beat the Netherlands 1–0 in the 2010 final in South Africa.']],
            ['correct' => 0,
             'ar' => ['q' => 'أي دولة استضافت كأس العالم 2022؟', 'options' => ['قطر','الإمارات','السعودية','روسيا'], 'explain' => 'استضافت قطر النسخة الأولى في الشرق الأوسط عام 2022.'],
             'en' => ['q' => 'Which country hosted the 2022 World Cup?', 'options' => ['Qatar','UAE','Saudi Arabia','Russia'], 'explain' => 'Qatar hosted the first Middle East World Cup in 2022.']],
            ['correct' => 0,
             'ar' => ['q' => 'أي دولة استضافت كأس العالم 2014؟', 'options' => ['البرازيل','جنوب أفريقيا','روسيا','قطر'], 'explain' => 'استضافت البرازيل بطولة 2014.'],
             'en' => ['q' => 'Which country hosted the 2014 World Cup?', 'options' => ['Brazil','South Africa','Russia','Qatar'], 'explain' => 'Brazil hosted the 2014 tournament.']],
            ['correct' => 1,
             'ar' => ['q' => 'أي دولة استضافت كأس العالم 2010؟', 'options' => ['نيجيريا','جنوب أفريقيا','المغرب','مصر'], 'explain' => 'جنوب أفريقيا أول دولة أفريقية تستضيف البطولة عام 2010.'],
             'en' => ['q' => 'Which country hosted the 2010 World Cup?', 'options' => ['Nigeria','South Africa','Morocco','Egypt'], 'explain' => 'South Africa was the first African host, in 2010.']],
            ['correct' => 1,
             'ar' => ['q' => 'من فاز بالحذاء الذهبي (الهدّاف) في كأس العالم 2022؟', 'options' => ['ميسي','كيليان مبابي','جيرو','الفاريز'], 'explain' => 'كيليان مبابي برصيد 8 أهداف.'],
             'en' => ['q' => 'Who won the Golden Boot at the 2022 World Cup?', 'options' => ['Messi','Kylian Mbappé','Giroud','Álvarez'], 'explain' => 'Kylian Mbappé with 8 goals.']],
            ['correct' => 1,
             'ar' => ['q' => 'أي منتخب عربي بلغ نصف نهائي كأس العالم 2022؟', 'options' => ['السنغال','المغرب','تونس','السعودية'], 'explain' => 'المغرب أول منتخب عربي وأفريقي يبلغ نصف النهائي.'],
             'en' => ['q' => 'Which Arab team reached the 2022 World Cup semi-finals?', 'options' => ['Senegal','Morocco','Tunisia','Saudi Arabia'], 'explain' => 'Morocco were the first Arab and African team to reach the semi-finals.']],
            ['correct' => 2,
             'ar' => ['q' => 'كم مرة تُقام بطولة كأس العالم؟', 'options' => ['كل عامين','كل ثلاثة أعوام','كل أربعة أعوام','كل خمسة أعوام'], 'explain' => 'تُقام كل أربع سنوات منذ 1930 (عدا 1942 و1946).'],
             'en' => ['q' => 'How often is the World Cup held?', 'options' => ['Every 2 years','Every 3 years','Every 4 years','Every 5 years'], 'explain' => 'Every four years since 1930 (except 1942 and 1946).']],
            ['correct' => 1,
             'ar' => ['q' => 'من قاد الأرجنتين كقائد للتتويج بلقب 2022؟', 'options' => ['دي ماريا','ليونيل ميسي','أوتاميندي','إميليانو مارتينيز'], 'explain' => 'رفع ليونيل ميسي الكأس كقائد للأرجنتين عام 2022.'],
             'en' => ['q' => 'Who captained Argentina to the 2022 title?', 'options' => ['Di María','Lionel Messi','Otamendi','Emiliano Martínez'], 'explain' => 'Lionel Messi lifted the trophy as Argentina captain in 2022.']],
        ];
    }

    /**
     * lossAnalysis() — تحليل فنّي بحت لسبب خسارة الفريق في مباراة منتهية فعلاً.
     * يُولَّد مرة واحدة لكل (مباراة + لغة) ويُخزَّن. جادّ — لا مزاح.
     */
    public static function lossAnalysis(array $m, string $loserEn, ?string $lang = null): ?string
    {
        if (!self::enabled()) return null;
        if (!isset($m['score']['ft']) || !is_array($m['score']['ft'])) return null;  // غير منتهية
        $idx = (int)($m['_index'] ?? -1);
        if ($idx < 0) return null;

        $t1 = trim($m['team1'] ?? '');
        $t2 = trim($m['team2'] ?? '');
        if (!in_array($loserEn, [$t1, $t2], true)) return null;
        $winnerEn = ($loserEn === $t1) ? $t2 : $t1;

        $lang = $lang ?: current_lang();
        $file = rtrim(CACHE_DIR, '/') . "/loss_{$idx}_{$lang}.txt";
        if (is_file($file)) {
            $c = @file_get_contents($file);
            if ($c !== false && trim($c) !== '') return $c;
        }

        [$g1, $g2] = $m['score']['ft'];
        $loserGoals  = ($loserEn === $t1) ? (int)$g1 : (int)$g2;
        $winnerGoals = ($loserEn === $t1) ? (int)$g2 : (int)$g1;

        $map  = teams_map();
        $loserName  = ($lang === 'ar') ? ($map[$loserEn][0]  ?? $loserEn)  : $loserEn;
        $winnerName = ($lang === 'ar') ? ($map[$winnerEn][0] ?? $winnerEn) : $winnerEn;
        $langWord = ($lang === 'ar') ? 'Arabic' : 'English';

        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => 220,
            'system'     => [[
                'type' => 'text',
                'text' => "You are a professional football analyst. Write in {$langWord}. "
                    . "Give a concise, PURELY TACTICAL/TECHNICAL analysis (2-3 sentences) of why a team lost a match. "
                    . "Focus only on football factors: defensive organization, midfield control, chance creation, "
                    . "finishing, pressing, game management, substitutions. "
                    . "Do NOT mention luck, fate, refereeing, weather, and do NOT make jokes. Serious analysis only.",
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => "{$loserName} lost {$loserGoals}-{$winnerGoals} to {$winnerName} at the FIFA World Cup 2026. "
                    . "Explain technically why {$loserName} lost.",
            ]],
        ];
        $resp = self::call($payload);
        if ($resp === null) return null;
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
        }
        $text = self::clean($text);
        if ($text === '') return null;
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($file, $text);
        return $text;
    }

    /**
     * persona() — شخصية «المعلّق العربي»: محلّل كرة ودود، عارف، مرح وعائلي.
     * تُعاد استخدامها في الروست والقصة اليومية لتوحيد النبرة.
     */
    private static function persona(string $langWord): string
    {
        return "You are a witty, knowledgeable Arabic football pundit covering FIFA World Cup 2026. "
            . "Your humor is warm, clever, and strictly FAMILY-FRIENDLY. "
            . "Write in {$langWord}. "
            . "NEVER insult people, nations, religions, or fans — joke only about the loss/the match itself. "
            . "No profanity, no politics, no slurs. Keep it light and respectful.";
    }

    /** يحوّل مفتاح لهجة Qahr إلى وصف يفهمه النموذج. */
    private static function dialectLabel(string $dialect): string
    {
        switch ($dialect) {
            case 'khaleeji': return 'Gulf Arabic dialect (خليجي)';
            case 'masri':    return 'Egyptian Arabic dialect (مصري)';
            case 'shami':    return 'Levantine Arabic dialect (شامي)';
            case 'maghrebi': return 'Maghrebi Arabic dialect (مغاربي/دارجة)';
            case 'fusha':    return 'Modern Standard Arabic (فصحى)';
            default:         return 'natural everyday Arabic';
        }
    }

    /**
     * roast() — سطر قهر ساخر (لطيف) بالعربية حسب اللهجة، مولّد بـClaude.
     * مخزَّن لكل (مباراة تقريبية + لغة + لهجة). يُرجّع null عند تعطيل الذكاء الاصطناعي.
     *
     * ملاحظة: هذه الدالة هي «التحسين» فقط — Qahr::roast() يستعملها داخل try/catch
     * ويسقط إلى بنك العبارات إن رجعت null أو فشلت.
     *
     * @param string $team الاسم الإنجليزي الخام للفريق الخاسر (مثل "Mexico").
     * @param string $opp  الاسم الإنجليزي الخام للخصم.
     */
    public static function roast(string $team, string $opp, int $g1, int $g2, string $lang, string $dialect = ''): ?string
    {
        if (!self::enabled()) return null;

        $lang = ($lang === 'en') ? 'en' : 'ar';
        $team = trim($team);
        $opp  = trim($opp);
        if ($team === '' || $opp === '') return null;

        $g1 = max(0, min(30, $g1));
        $g2 = max(0, min(30, $g2));

        // مفتاح كاش ثابت لكل (فريقين + نتيجة + لغة + لهجة).
        $keyRaw = strtolower($team) . '_' . strtolower($opp) . '_' . $g1 . '-' . $g2;
        $key    = substr(preg_replace('/[^a-z0-9_-]+/', '', $keyRaw), 0, 60);
        $dkey   = $dialect !== '' ? preg_replace('/[^a-z]+/', '', $dialect) : 'auto';
        $file   = rtrim(CACHE_DIR, '/') . "/roast_{$key}_{$lang}_{$dkey}.txt";

        if (is_file($file)) {
            $c = @file_get_contents($file);
            if ($c !== false && trim($c) !== '') return $c;
        }

        // أسماء العرض (عربي عبر الخريطة، إنجليزي = الخام).
        $map = function_exists('teams_map') ? teams_map() : [];
        if ($lang === 'ar') {
            $teamName = $map[$team][0] ?? $team;
            $oppName  = $map[$opp][0]  ?? $opp;
            $langWord = 'Arabic';
        } else {
            $teamName = $team;
            $oppName  = $opp;
            $langWord = 'English';
        }

        $dialectInstr = ($lang === 'ar')
            ? ' Use the ' . self::dialectLabel($dialect) . '.'
            : '';

        $system = self::persona($langWord)
            . " Produce EXACTLY ONE short, punchy roast line (max ~18 words) about a team's loss. "
            . "Output ONLY that single line — no quotes, no title, no markdown, no extra sentences."
            . $dialectInstr;

        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => 80,
            'system'     => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => "{$teamName} lost {$g1}-{$g2} to {$oppName} at the FIFA World Cup 2026. "
                    . "Write one funny, gentle, family-friendly roast line about this loss. "
                    . "You may add one emoji like 😩 or 💔.",
            ]],
        ];

        $resp = self::call($payload);
        if ($resp === null) return null;
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
        }
        $text = self::clean($text);
        // سطر واحد فقط.
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') return null;

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($file, $text);
        return $text;
    }

    /**
     * dailyStory() — «قصة اليوم»: 2–3 جُمل قصيرة عن البطولة/مباريات اليوم.
     * مخزَّنة لكل (يوم + لغة). يُرجّع null عند التعطيل (today.php ينادي بحذر).
     */
    public static function dailyStory(string $lang): ?string
    {
        if (!self::enabled()) return null;
        $lang = ($lang === 'en') ? 'en' : 'ar';

        $day  = date('Y-m-d');
        $file = rtrim(CACHE_DIR, '/') . "/story_{$day}_{$lang}.txt";
        if (is_file($file)) {
            $c = @file_get_contents($file);
            if ($c !== false && trim($c) !== '') return $c;
        }

        $langWord = ($lang === 'ar') ? 'Arabic' : 'English';

        // حقائق اليوم: مباريات اليوم (إن وُجد DataService) لتأطير القصة دون اختراع وقائع.
        $facts = "Date: {$day}\nTournament: FIFA World Cup 2026 (Canada, Mexico, USA)\n";
        if (class_exists('DataService') && method_exists('DataService', 'allMatches')) {
            $today = [];
            try {
                foreach (DataService::allMatches() as $m) {
                    $ts = DataService::matchTimestamp($m);
                    if ($ts !== null && gmdate('Y-m-d', $ts) === $day) {
                        $t1 = trim($m['team1'] ?? '');
                        $t2 = trim($m['team2'] ?? '');
                        if ($t1 !== '' && $t2 !== '') $today[] = "{$t1} vs {$t2}";
                    }
                    if (count($today) >= 8) break;
                }
            } catch (\Throwable $e) { $today = []; }
            if ($today) {
                $facts .= "Today's matches: " . implode('; ', $today) . "\n";
            } else {
                $facts .= "No matches scheduled today (or schedule not yet known).\n";
            }
        }

        $system = self::persona($langWord)
            . " Write a SHORT 'story of the day' (2-3 sentences only) that sets the mood for the tournament/today. "
            . "Engaging and fun but factual. Output ONLY the body text — no title, no markdown, no bullets. "
            . "Use ONLY the facts provided plus widely-known general background; do NOT invent results, lineups, or stats.";

        $payload = [
            'model'      => defined('AI_MODEL') ? AI_MODEL : 'claude-haiku-4-5',
            'max_tokens' => 200,
            'system'     => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages'   => [[
                'role'    => 'user',
                'content' => "Write today's short story now based on these facts:\n\n" . $facts,
            ]],
        ];

        $resp = self::call($payload);
        if ($resp === null) return null;
        $text = '';
        foreach ($resp['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') { $text = $block['text']; break; }
        }
        $text = self::clean($text);
        if ($text === '') return null;

        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($file, $text);
        return $text;
    }

    /** ينظّف النص من العناوين/رموز الماركداون التي قد يضيفها النموذج */
    private static function clean(string $text): string
    {
        // أزل أسطر العناوين (# ...) ورموز الماركداون
        $text = preg_replace('/^\s{0,3}#{1,6}\s*.*$/m', '', $text);
        $text = str_replace(['**', '__', '`', '#'], '', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /** نداء HTTP إلى Claude Messages API */
    private static function call(array $payload): ?array
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . CLAUDE_API_KEY,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => defined('AI_TIMEOUT') ? AI_TIMEOUT : 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code !== 200) return null;
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }
}
