<?php
/**
 * News.php
 * ============================================================
 * أخبار كأس العالم من خلاصة RSS مجانية (Google News) — تتحدّث تلقائياً.
 * تُخزَّن مؤقتاً (NEWS_CACHE_TTL) لتقليل الطلبات، مع fallback للكاش القديم.
 * كل الروابط خارجية تُفتح بأمان (nofollow/noopener).
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class News
{
    /** أحدث الأخبار حسب اللغة الحالية، مدموجة من Bing + Google بلا تكرار */
    public static function latest(int $limit = 0): array
    {
        $limit = $limit ?: (defined('NEWS_MAX_ITEMS') ? NEWS_MAX_ITEMS : 18);
        $lang  = current_lang();
        $urls  = ($lang === 'ar')
            ? [NEWS_RSS_AR, defined('NEWS_RSS_AR2') ? NEWS_RSS_AR2 : '']
            : [NEWS_RSS_EN, defined('NEWS_RSS_EN2') ? NEWS_RSS_EN2 : ''];
        $cache = rtrim(CACHE_DIR, '/') . "/news_{$lang}.json";

        // كاش حديث
        if (is_file($cache) && (time() - filemtime($cache) < NEWS_CACHE_TTL)) {
            $d = json_decode((string)@file_get_contents($cache), true);
            if (is_array($d)) return array_slice($d, 0, $limit);
        }

        // اجلب من المصدرين وادمج (Bing أولاً ليحتفظ بصوره عند التكرار)
        $items = [];
        foreach ($urls as $u) {
            if ($u === '') continue;
            foreach (self::fetchParse($u) as $it) $items[] = $it;
        }
        $items = self::dedupe($items);
        // رتّب زمنياً، ثم وزّع الأخبار المصوّرة بين الباقي حتى لا تُدفن أسفل القائمة
        usort($items, fn($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
        $withImg = array_values(array_filter($items, fn($x) => !empty($x['image'])));
        $noImg   = array_values(array_filter($items, fn($x) => empty($x['image'])));
        if ($withImg && $noImg) {
            $merged = [];
            $i = $j = 0;
            while ($i < count($withImg) || $j < count($noImg)) {
                if ($i < count($withImg)) $merged[] = $withImg[$i++];   // مصوّر
                if ($j < count($noImg))   $merged[] = $noImg[$j++];     // + خبران بلا صورة
                if ($j < count($noImg))   $merged[] = $noImg[$j++];
            }
            $items = $merged;
        }

        if ($items) {
            if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
            @file_put_contents($cache, json_encode($items, JSON_UNESCAPED_UNICODE));
            return array_slice($items, 0, $limit);
        }

        // فشل → كاش قديم إن وُجد
        if (is_file($cache)) {
            $d = json_decode((string)@file_get_contents($cache), true);
            if (is_array($d)) return array_slice($d, 0, $limit);
        }
        return [];
    }

    /** يكشف عناوين السبام (مجمّعات بث غير قانونية / رموز دعائية) */
    private static function isSpam(string $title): bool
    {
        // رموز تجارية أو تكتّل رموز = إشارة سبام قوية
        if (preg_match('/[™®]/u', $title)) return true;
        if (preg_match('/[\[\]@*]{2,}|!{3,}|>{2,}|<{2,}/u', $title)) return true;
        // عبارات بث/مشاهدة الشائعة في السبام
        $bad = ['watch live', 'live stream', 'livestream', 'streaming', 'reddit',
                'crackstream', 'مشاهدة مباشرة', 'بث مباشر مجان', 'شاهد بالبث'];
        $t = mb_strtolower($title, 'UTF-8');
        foreach ($bad as $b) {
            if (mb_stripos($t, $b) !== false) return true;
        }
        return false;
    }

    /** يزيل الأخبار المكرّرة بمطابقة العنوان المبسّط (يبقي الأول = الأغنى بالصورة) */
    private static function dedupe(array $items): array
    {
        $seen = [];
        $out  = [];
        foreach ($items as $it) {
            $key = mb_strtolower(trim((string)($it['title'] ?? '')), 'UTF-8');
            $key = preg_replace('/[^\p{L}\p{N} ]+/u', '', $key);
            $key = trim(preg_replace('/\s+/u', ' ', $key));
            $key = mb_substr($key, 0, 55, 'UTF-8');
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $it;
        }
        return $out;
    }

    /**
     * enrich() — يجلب من صفحة المصدر المباشرة صورة أعلى دقة (og:image)
     * ونصاً تمهيدياً (og:description / meta description). best-effort + كاش 6 ساعات.
     * لا يعيد نشر نص المقال كاملاً (حقوق الناشر) — وصف المشاركة فقط.
     */
    public static function enrich(string $url): array
    {
        $res = ['image' => '', 'desc' => ''];
        if (!preg_match('#^https?://#i', $url)) return $res;

        $key = rtrim(CACHE_DIR, '/') . '/article_' . md5($url) . '.json';
        if (is_file($key) && (time() - filemtime($key) < 21600)) {
            $d = json_decode((string)@file_get_contents($key), true);
            if (is_array($d)) return $d + $res;
        }

        $html = self::fetch($url);
        if ($html !== null) {
            // og:image (يدعم ترتيب السمات بالاتجاهين)
            if (preg_match('#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)#i', $html, $m)
             || preg_match('#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']#i', $html, $m)) {
                $img = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
                if (preg_match('#^https?://#i', $img)) $res['image'] = $img;
            }
            // og:description ثم meta description
            if (preg_match('#<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']*)#i', $html, $m)
             || preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)#i', $html, $m)) {
                $res['desc'] = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            }
        }
        if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
        @file_put_contents($key, json_encode($res, JSON_UNESCAPED_UNICODE));
        return $res;
    }

    /** يجد خبراً واحداً بمعرّفه (من القائمة المخزّنة) أو null */
    public static function find(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{12}$/', $id)) return null;
        foreach (self::latest(100) as $item) {
            if (($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    private static function fetchParse(string $url): array
    {
        $raw = self::fetch($url);
        if ($raw === null) return [];

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml === false || !isset($xml->channel->item)) return [];

        $out = [];
        foreach ($xml->channel->item as $it) {
            $title = trim(html_entity_decode((string)$it->title, ENT_QUOTES, 'UTF-8'));
            $rawLink = trim((string)$it->link);
            $pub   = trim((string)$it->pubDate);
            if ($title === '' || $rawLink === '') continue;
            if (self::isSpam($title)) continue;   // تجاهل سبام البث/المجمّعات

            // رابط Bing تحويلي يحوي الرابط الحقيقي في معطى url= → نفكّه للوصول للمصدر مباشرة
            $link = $rawLink;
            if (preg_match('/[?&]url=([^&]+)/', $rawLink, $mm)) {
                $dec = urldecode($mm[1]);
                if (preg_match('#^https?://#i', $dec)) $link = $dec;
            }
            if (!preg_match('#^https?://#i', $link)) continue;

            // الصورة + المصدر: Bing عبر مساحة الأسماء News، وGoogle عبر <source>
            $news   = $it->children('News', true);
            $image  = ($news && isset($news->Image))  ? trim((string)$news->Image)  : '';
            $src    = ($news && isset($news->Source)) ? trim((string)$news->Source) : '';
            if ($src === '' && isset($it->source)) $src = trim((string)$it->source);
            if ($image !== '') {
                $image = preg_replace('#^http://#i', 'https://', $image);  // https لتفادي المحتوى المختلط
                // اطلب دقة أعلى من خادم صور Bing (الافتراضي ~100px)
                if (stripos($image, 'bing.com/th') !== false && stripos($image, '&w=') === false) {
                    $image .= '&w=640&h=360&c=7&rs=1&qlt=90';
                }
            }

            // ملخّص نظيف من الوصف
            $summary = trim(html_entity_decode(strip_tags((string)$it->description), ENT_QUOTES, 'UTF-8'));
            if (mb_strlen($summary) > 240) $summary = mb_substr($summary, 0, 237) . '…';

            $host = parse_url($link, PHP_URL_HOST) ?: '';
            // نطاق الناشر للشعار: من <source url> إن وُجد، وإلا من رابط المقال (نتفادى نطاق google)
            $srcUrl   = (isset($it->source) && isset($it->source['url'])) ? trim((string)$it->source['url']) : '';
            $logoHost = $srcUrl ? (parse_url($srcUrl, PHP_URL_HOST) ?: '') : '';
            if ($logoHost === '' && stripos($host, 'google') === false) $logoHost = $host;
            $logo = $logoHost ? 'https://www.google.com/s2/favicons?sz=128&domain=' . rawurlencode($logoHost) : '';

            $out[] = [
                'id'      => substr(md5($link), 0, 12),
                'title'   => $title,
                'link'    => $link,        // رابط الناشر المباشر
                'source'  => $src,
                'host'    => $host,
                'image'   => $image,       // صورة المقال الحقيقية (قد تكون فارغة)
                'logo'    => $logo,        // شعار الناشر (احتياطي)
                'summary' => $summary,
                'ts'      => $pub ? (strtotime($pub) ?: 0) : 0,
            ];
        }
        return $out;
    }

    private static function fetch(string $url): ?string
    {
        // جلب موحّد بمهلة صارمة بلا تراكم (راجع http_get في helpers.php).
        return http_get($url, ['ua' => 'Mozilla/5.0 WorldCup2026Site/1.0']);
    }
}
