<?php
/**
 * seo.php — أدوات تحسين الظهور في محركات البحث.
 * ------------------------------------------------------------
 *  base_url()        : عنوان الموقع المطلق (من SITE_URL أو من الطلب الحالي)
 *  page_url_lang()   : رابط الصفحة الحالية بلغة محددة (لبدائل hreflang)
 *  seo_head()        : يطبع canonical + hreflang + Open Graph/Twitter + JSON-LD عام
 *  seo_sportsevent() : JSON-LD لمباراة واحدة (SportsEvent)
 * ------------------------------------------------------------
 */
if (!defined('WC2026')) { exit('Access denied'); }

/** عنوان الموقع المطلق بلا شرطة في النهاية */
function base_url(): string {
    if (defined('SITE_URL') && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/** رابط الصفحة الحالية بلغة محددة مع الحفاظ على باقي المعطيات */
function page_url_lang(string $lang): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $params = $_GET;
    $params['lang'] = $lang;
    $qs = http_build_query($params);
    return base_url() . $script . ($qs ? '?' . $qs : '');
}

/** الرابط القانوني للصفحة الحالية (باللغة الحالية) */
function canonical_url(): string {
    return page_url_lang(current_lang());
}

/**
 * seo_head() — يُطبع كل وسوم الـSEO داخل <head>.
 * $opts: ['title'=>..., 'description'=>..., 'image'=>..., 'type'=>'website|article']
 */
function seo_head(array $opts = []): void {
    $lang  = current_lang();
    $title = $opts['title'] ?? t('site_desc');
    $desc  = $opts['description'] ?? t('site_desc');
    // og:image الافتراضي = البطاقة الديناميكية بالهوية الحالية (تتحدّث تلقائياً عند تغيير التصميم).
    // الـcache-buster مبنيٌّ على mtime ملف المولّد → URL جديد عند كل تعديل → تويتر/فيسبوك يُعيدان الجلب.
    $defaultOg = base_url() . '/card_img.php?v=' . (@filemtime(__DIR__ . '/../card_img.php') ?: 1);
    $image = $opts['image'] ?? $defaultOg;
    $type  = $opts['type'] ?? 'website';
    $canon = canonical_url();
    $siteName = ($lang === 'ar') ? SITE_NAME_AR : SITE_NAME_EN;

    echo '<link rel="canonical" href="' . e($canon) . '">' . "\n";
    echo '<link rel="alternate" hreflang="ar" href="' . e(page_url_lang('ar')) . '">' . "\n";
    echo '<link rel="alternate" hreflang="en" href="' . e(page_url_lang('en')) . '">' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . e(page_url_lang('ar')) . '">' . "\n";

    // Open Graph
    echo '<meta property="og:site_name" content="' . e($siteName) . '">' . "\n";
    echo '<meta property="og:locale" content="' . ($lang === 'ar' ? 'ar_AR' : 'en_US') . '">' . "\n";
    echo '<meta property="og:type" content="' . e($type) . '">' . "\n";
    echo '<meta property="og:title" content="' . e($title) . '">' . "\n";
    echo '<meta property="og:description" content="' . e($desc) . '">' . "\n";
    echo '<meta property="og:url" content="' . e($canon) . '">' . "\n";
    echo '<meta property="og:image" content="' . e($image) . '">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";

    // Twitter
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . e($desc) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . e($image) . '">' . "\n";

    // JSON-LD: WebSite + Organization (في كل الصفحات)
    $ld = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            [
                '@type' => 'WebSite',
                '@id'   => base_url() . '/#website',
                'url'   => base_url() . '/',
                'name'  => $siteName,
                'description' => $desc,
                'inLanguage'  => $lang,
            ],
            [
                '@type' => 'Organization',
                '@id'   => base_url() . '/#org',
                'name'  => $siteName,
                'url'   => base_url() . '/',
                'logo'  => base_url() . '/assets/img/og.png',
            ],
        ],
    ];
    // JSON_HEX_TAG/AMP يهرّبان < > & (إلى \u00XX) فيستحيل الخروج من وسم <script> مهما كانت البيانات.
    echo '<script type="application/ld+json">'
       . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
       . '</script>' . "\n";
}

/**
 * seo_sportsevent() — JSON-LD لمباراة (نوع SportsEvent) لتحسين ظهورها.
 */
function seo_sportsevent(array $m): void {
    $ts = DataService::matchTimestamp($m);
    $t1 = team_name(trim($m['team1'] ?? ''));
    $t2 = team_name(trim($m['team2'] ?? ''));
    $name = $t1 . ' ' . t('vs') . ' ' . $t2;
    $ar  = (current_lang() === 'ar');

    // صورة المباراة: نفس بطاقة المشاركة المُولّدة التي تستخدمها صفحة match.php
    $idx   = (int)($m['_index'] ?? 0);
    $image = base_url() . '/card.php?id=' . $idx . '&mode=match&v=3';

    // وصف موجز ودقيق للمباراة (الحقل description المطلوب من Google)
    $bits = [];
    if (!empty($m['round']))  { $bits[] = round_label($m['round']); }
    if (!empty($m['group']))  { $bits[] = group_label($m['group']); }
    if (!empty($m['ground'])) { $bits[] = $m['ground']; }
    $desc = $name . ($bits ? ' — ' . implode(' · ', $bits) : '')
          . ' — ' . ($ar ? 'كأس العالم 2026' : 'FIFA World Cup 2026');

    // الفريقان (يُستخدمان في competitor الدقيق + performer الذي يطلبه Google)
    $teams = [
        ['@type' => 'SportsTeam', 'name' => $t1],
        ['@type' => 'SportsTeam', 'name' => $t2],
    ];

    $ld = [
        '@context'    => 'https://schema.org',
        '@type'       => 'SportsEvent',
        'name'        => $name,
        'description' => $desc,                 // ← description
        'sport'       => 'Football',
        'url'         => canonical_url(),
        'image'       => [$image],              // ← image
        // إشارات معياريّة: تغطية الموقع لهذه المباراة مجانية ومتاحة للعموم.
        // تُخبر Google أن الحدث «مجاني المشاهدة عبر موقعنا» فلا يُطالب بـoffers.
        'isAccessibleForFree' => true,
        'publicAccess'        => true,
    ];
    if ($ts !== null) {
        $ld['startDate']   = gmdate('c', $ts);
        $ld['endDate']     = gmdate('c', $ts + 7200);   // ← endDate (~ساعتان مدّة المباراة)
        $ld['eventStatus'] = 'https://schema.org/EventScheduled';
    }
    if (!empty($m['ground'])) {
        $ld['location'] = [
            '@type'   => 'Place',
            'name'    => $m['ground'],
            'address' => [
                '@type'           => 'PostalAddress',
                'addressLocality' => $m['ground'],
            ],
        ];
    }
    $ld['competitor'] = $teams;                 // الدقيق دلالياً لرياضة
    $ld['performer']  = $teams;                 // ← performer (يطلبه Google لنتائج Event)
    $ld['organizer'] = [
        '@type' => 'Organization',
        'name'  => 'FIFA',
        'url'   => 'https://www.fifa.com/',
    ];
    // offers — حقل يوصي به Google لأحداث Event. الموقع لا يبيع تذاكر، فلا نختلق
    // سعراً؛ نوجّه للمصدر الرسمي (FIFA) مع حالة التوفّر حسب كون المباراة قادمة.
    $ld['offers'] = [
        '@type'        => 'Offer',
        'url'          => 'https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/tickets',
        'availability' => ($ts !== null && $ts > time())
                          ? 'https://schema.org/InStock'
                          : 'https://schema.org/SoldOut',
    ];

    // JSON_HEX_TAG/AMP يهرّبان < > & (إلى \u00XX) فيستحيل الخروج من وسم <script> مهما كانت البيانات.
    echo '<script type="application/ld+json">'
       . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)
       . '</script>' . "\n";
}
