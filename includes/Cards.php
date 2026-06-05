<?php
/**
 * Cards.php — مُساعِد البطاقات القابلة للمشاركة (WhatsApp/X/Telegram).
 * ============================================================
 * يبني بيانات/عناوين/ألوان كل نوع بطاقة من معطيات الرابط.
 * الأنواع: predict (توقّعي) · brag (قلتلكم!) · result (نتيجة) ·
 *          sticker (كشف ملصق) · qahr (قهر 😩).
 *
 * مبدأ التصميم: دفاعي بالكامل — لا يكسر شيئاً إن غابت بيانات أو غاب
 * صنف Qahr (يبنيه وكيل آخر). كل القيم تُقصّ/تُحقّق، وكل الإخراج يُؤمَّن بـ e()
 * في طبقة العرض (card.php) لا هنا — هذه الطبقة تُرجِع بيانات نقية.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Cards
{
    /** الأنواع المسموح بها (قائمة بيضاء صارمة) */
    public const TYPES = ['predict', 'brag', 'result', 'sticker', 'qahr'];

    /** لون الإبراز لكل نوع (يطابق هوية الموقع: كحلي/أخضر/ذهبي) */
    private const ACCENTS = [
        'predict' => '#00d563',   // أخضر — توقّع
        'brag'    => '#ffc233',   // ذهبي — تباهٍ
        'result'  => '#eef4ff',   // أبيض — نتيجة رسمية
        'sticker' => '#7c5cff',   // بنفسجي — ملصق نادر
        'qahr'    => '#ff5a5a',   // أحمر — قهر
    ];

    /** إيموجي لكل نوع */
    private const EMOJIS = [
        'predict' => '🔮',
        'brag'    => '🏆',
        'result'  => '⚽',
        'sticker' => '✨',
        'qahr'    => '😩',
    ];

    /** يُطبّع/يُحقّق النوع، ويُرجِع 'predict' افتراضياً (توافق خلفي). */
    public static function normalizeType($type): string
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';
        return in_array($type, self::TYPES, true) ? $type : 'predict';
    }

    /** لون الإبراز لنوع البطاقة */
    public static function accent(string $type): string
    {
        return self::ACCENTS[self::normalizeType($type)] ?? '#00d563';
    }

    /** إيموجي نوع البطاقة */
    public static function emoji(string $type): string
    {
        return self::EMOJIS[self::normalizeType($type)] ?? '🔮';
    }

    /**
     * matchData() — يحلّ مباراة بالمعرّف الداخلي (int) عبر DataService،
     * ويُرجِع بيانات عرض جاهزة (أسماء، أعلام، تاريخ/وقت، نتيجة فعلية).
     * يُرجِع null إذا لم تُوجد المباراة أو لم تكن بين منتخبين حقيقيين.
     */
    public static function matchData(int $id): ?array
    {
        if ($id < 0) return null;
        $m = DataService::matchByIndex($id);
        if (!is_array($m)) return null;

        $t1 = trim((string)($m['team1'] ?? ''));
        $t2 = trim((string)($m['team2'] ?? ''));
        if (!is_real_team($t1) || !is_real_team($t2)) return null;

        $ts  = DataService::matchTimestamp($m);
        $ft  = (isset($m['score']['ft']) && is_array($m['score']['ft']))
             ? [(int)$m['score']['ft'][0], (int)$m['score']['ft'][1]]
             : null;

        return [
            'id'        => $id,
            'team1'     => $t1,
            'team2'     => $t2,
            'name1'     => team_name($t1),
            'name2'     => team_name($t2),
            'flag1'     => flag_url($t1, 'w320'),
            'flag2'     => flag_url($t2, 'w320'),
            'ts'        => $ts,
            'round'     => trim((string)($m['round'] ?? '')),
            'ground'    => trim((string)($m['ground'] ?? '')),
            'ft'        => $ft,        // النتيجة الفعلية إن وُجدت [g1,g2] أو null
        ];
    }

    /** يقصّ هدفاً ضمن نطاق منطقي 0..30 */
    public static function clampScore($n): int
    {
        return max(0, min(30, (int)$n));
    }

    /**
     * build() — يبني بيانات بطاقة كاملة جاهزة للعرض من معطيات الرابط.
     * @param string $type نوع البطاقة (يُطبّع)
     * @param array  $params المعطيات الخام من $_GET
     * @return array بنية موحّدة: type, accent, emoji, headline, subline,
     *               score, match (أو null), share (نص المشاركة), lang
     */
    public static function build(string $type, array $params): array
    {
        $type = self::normalizeType($type);
        $lang = current_lang();
        $ar   = ($lang === 'ar');

        $id   = isset($params['id']) ? (int)$params['id'] : -1;
        $match = self::matchData($id);

        $p1 = self::clampScore($params['p1'] ?? 0);
        $p2 = self::clampScore($params['p2'] ?? 0);

        $headline = '';
        $subline  = '';
        $score    = '';

        switch ($type) {
            case 'brag':
                $headline = match($lang) { 'ar' => 'قلتلكم! 🏆', 'fr' => 'JE L\'AVAIS DIT ! 🏆', default => 'I CALLED IT! 🏆' };
                $subline  = match($lang) { 'ar' => 'توقّعت النتيجة بالضبط', 'fr' => 'Score exact prédit', default => 'Called the exact score' };
                $score    = $p1 . ' : ' . $p2;
                break;

            case 'result':
                $headline = match($lang) { 'ar' => 'النتيجة النهائية', 'fr' => 'RÉSULTAT FINAL', default => 'FINAL RESULT' };
                // النتيجة الفعلية من البيانات إن وُجدت، وإلا المُمرَّرة
                if ($match && $match['ft'] !== null) {
                    $score = $match['ft'][0] . ' : ' . $match['ft'][1];
                } else {
                    $score = $p1 . ' : ' . $p2;
                }
                $subline = $match
                    ? ($match['name1'] . ' ' . t('vs') . ' ' . $match['name2'])
                    : t('site_desc');
                break;

            case 'sticker':
                $name = self::cleanText($params['name'] ?? '', 40);
                if ($name === '') $name = match($lang) { 'ar' => 'ملصق نادر', 'fr' => 'Sticker rare', default => 'Rare Sticker' };
                $headline = match($lang) { 'ar' => 'حصلت على ملصق! ✨', 'fr' => 'Sticker obtenu ! ✨', default => 'Got a sticker! ✨' };
                $subline  = $name;
                $score    = '';
                break;

            case 'qahr':
                $teamRaw = self::cleanText($params['team'] ?? '', 40);
                // اسم الفريق: من المباراة (إن وُجد id) أو من معطى team الخام
                $teamDisp = $match ? $match['name1'] : ($teamRaw !== '' ? team_name($teamRaw) : '');
                $oppDisp  = $match ? $match['name2'] : '';
                $g1 = $match && $match['ft'] !== null ? $match['ft'][0] : $p1;
                $g2 = $match && $match['ft'] !== null ? $match['ft'][1] : $p2;

                // ربط دفاعي بـ Qahr::roast() — لا يكسر إن غاب الصنف.
                $roast = '';
                if (class_exists('Qahr') && method_exists('Qahr', 'roast')) {
                    try {
                        $roast = (string)Qahr::roast(
                            $match ? $match['team1'] : $teamRaw,
                            $match ? $match['team2'] : '',
                            (int)$g1, (int)$g2, $lang
                        );
                    } catch (\Throwable $e) {
                        $roast = '';
                    }
                }
                if ($roast === '') {
                    // سطر احتياطي مدمج (إن غاب Qahr أو رجع فارغاً)
                    $roast = match($lang) {
                        'ar' => 'قهر… بس بكرة نرجع أقوى 😩💔',
                        'fr' => 'Frustration… mais on revient plus forts 😩💔',
                        default => 'Heartbreak… but we rise again 😩💔',
                    };
                }

                $headline = match($lang) { 'ar' => 'قهر! 😩', 'fr' => 'FRUSTRATION ! 😩', default => 'HEARTBREAK 😩' };
                $subline  = $roast;
                $score    = ($g1 !== null && $g2 !== null) ? ((int)$g1 . ' : ' . (int)$g2) : '';
                break;

            case 'predict':
            default:
                $type     = 'predict';
                $headline = match($lang) { 'ar' => 'توقّعي 🔮', 'fr' => 'MA PRÉDICTION 🔮', default => 'MY PREDICTION 🔮' };
                $subline  = t('site_desc');
                $score    = $p1 . ' : ' . $p2;
                break;
        }

        $data = [
            'type'     => $type,
            'lang'     => $lang,
            'accent'   => self::accent($type),
            'emoji'    => self::emoji($type),
            'headline' => $headline,
            'subline'  => $subline,
            'score'    => $score,
            'match'    => $match,
            'p1'       => $p1,
            'p2'       => $p2,
        ];
        $data['share'] = self::shareText($type, $data);
        return $data;
    }

    /**
     * shareText() — نص المشاركة (عربي/إنجليزي حسب اللغة الحالية).
     * نصّ ودود مناسب لمجموعات واتساب، بلا روابط (الرابط يُضاف بواسطة المشاركة).
     */
    public static function shareText(string $type, array $data): string
    {
        $lang = current_lang();
        $ar = ($lang === 'ar');
        $m  = $data['match'] ?? null;
        $vs = t('vs');
        $pair = $m ? ($m['name1'] . ' ' . $vs . ' ' . $m['name2']) : '';

        switch (self::normalizeType($type)) {
            case 'brag':
                return match($lang) {
                    'ar' => 'قلتلكم! توقّعت ' . ($pair ?: 'المباراة') . ' بالضبط ' . $data['score'] . ' 🏆🔥',
                    'fr' => 'Je l\'avais dit ! ' . ($pair ?: 'Le match') . ' prédit exactement ' . $data['score'] . ' 🏆🔥',
                    default => 'I called it! Predicted ' . ($pair ?: 'the match') . ' exactly ' . $data['score'] . ' 🏆🔥',
                };
            case 'result':
                return match($lang) {
                    'ar' => 'نتيجة ' . ($pair ?: 'المباراة') . ': ' . $data['score'] . ' ⚽',
                    'fr' => ($pair ?: 'Match') . ' résultat : ' . $data['score'] . ' ⚽',
                    default => ($pair ?: 'Match') . ' result: ' . $data['score'] . ' ⚽',
                };
            case 'sticker':
                return match($lang) {
                    'ar' => 'حصلت على ملصق «' . $data['subline'] . '» في ألبوم المونديال! ✨',
                    'fr' => 'Sticker "' . $data['subline'] . '" obtenu dans l\'album de la Coupe du Monde ! ✨',
                    default => 'Got the "' . $data['subline'] . '" sticker in the World Cup album! ✨',
                };
            case 'qahr':
                return match($lang) {
                    'ar' => ($data['subline'] !== '' ? $data['subline'] . ' 😩' : 'قهر مونديالي 😩'),
                    'fr' => ($data['subline'] !== '' ? $data['subline'] . ' 😩' : 'Frustration de Coupe du Monde 😩'),
                    default => ($data['subline'] !== '' ? $data['subline'] . ' 😩' : 'World Cup heartbreak 😩'),
                };
            case 'predict':
            default:
                return match($lang) {
                    'ar' => 'توقّعي في كأس العالم 2026 🔮 — ' . ($pair ?: '') . ' ' . $data['score'],
                    'fr' => 'Ma prédiction Coupe du Monde 2026 🔮 — ' . ($pair ?: '') . ' ' . $data['score'],
                    default => 'My FIFA World Cup 2026 prediction 🔮 — ' . ($pair ?: '') . ' ' . $data['score'],
                };
        }
    }

    /**
     * shareUrl() — رابط مطلق للبطاقة (صفحة card.php الهبوطية القابلة للمشاركة).
     * @param string $type نوع البطاقة
     * @param array  $params معطيات تُضاف للرابط (id, p1, p2, brag, name, team...)
     */
    public static function shareUrl(string $type, array $params = []): string
    {
        $type = self::normalizeType($type);
        $qs = ['type' => $type] + self::filterParams($params);
        return base_url() . '/card.php?' . http_build_query($qs);
    }

    /**
     * imageUrl() — رابط مطلق لصورة المعاينة (og:image) عبر card_img.php.
     */
    public static function imageUrl(string $type, array $params = []): string
    {
        $type = self::normalizeType($type);
        $qs = ['type' => $type] + self::filterParams($params);
        return base_url() . '/card_img.php?' . http_build_query($qs);
    }

    /** يُبقي فقط المعطيات المعروفة ويُطبّعها (يمنع تضخيم/تلويث الرابط). */
    private static function filterParams(array $params): array
    {
        $out = [];
        if (isset($params['id']))   $out['id'] = (int)$params['id'];
        if (isset($params['p1']))   $out['p1'] = self::clampScore($params['p1']);
        if (isset($params['p2']))   $out['p2'] = self::clampScore($params['p2']);
        if (!empty($params['brag'])) $out['brag'] = 1;
        if (isset($params['name']) && $params['name'] !== '') $out['name'] = self::cleanText($params['name'], 40);
        if (isset($params['team']) && $params['team'] !== '') $out['team'] = self::cleanText($params['team'], 40);
        if (isset($params['lang']) && in_array($params['lang'], ['ar', 'en', 'fr'], true)) $out['lang'] = $params['lang'];
        return $out;
    }

    /** ينظّف نصاً قصيراً: يزيل التحكّم/الأسطر، يقصّ الطول، بلا HTML. */
    public static function cleanText($s, int $max = 60): string
    {
        $s = is_string($s) ? $s : '';
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);   // محارف تحكّم/أسطر
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if ($s === '') return '';
        // قصّ آمن متعدّد البايت
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, $max, 'UTF-8');
        } else {
            $s = substr($s, 0, $max);
        }
        return $s;
    }
}
