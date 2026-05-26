<?php
/**
 * helpers.php — دوال مساعدة عامة تُستخدم في كل الموقع.
 */
if (!defined('WC2026')) { exit('Access denied'); }

/** تأمين أي نص قبل طباعته في HTML (يمنع XSS) */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** رابط داخلي مع الحفاظ على اللغة الحالية (إلا إذا مُرّرت لغة صريحة) */
function url(string $page, array $params = []): string {
    // لا نطمس لغة مُرّرت صراحةً (مثل زر تبديل اللغة)
    if (!isset($params['lang'])) {
        $params['lang'] = current_lang();
    }
    $qs = http_build_query($params);
    return rtrim(SITE_URL, '/') . '/' . ltrim($page, '/') . ($qs ? '?' . $qs : '');
}

/** تحويل اسم الجولة (إنجليزي) إلى نص معروض حسب اللغة */
function round_label(string $round): string {
    $round = trim($round);
    // Matchday N
    if (preg_match('/Matchday\s*(\d+)/i', $round, $m)) {
        return t('matchday') . ' ' . $m[1];
    }
    $map = [
        'Round of 32'           => 'round_of_32',
        'Round of 16'           => 'round_of_16',
        'Quarter-finals'        => 'quarter_finals',
        'Quarter-final'         => 'quarter_finals',
        'Semi-finals'           => 'semi_finals',
        'Semi-final'            => 'semi_finals',
        'Match for third place' => 'third_place',
        'Third Place'           => 'third_place',
        'Final'                 => 'final',
    ];
    foreach ($map as $en => $key) {
        if (strcasecmp($round, $en) === 0) return t($key);
    }
    return $round;
}

/** اسم المجموعة المعروض: "Group A" → "المجموعة A" */
function group_label(string $group): string {
    if (preg_match('/Group\s*([A-L])/i', $group, $m)) {
        return t('group') . ' ' . strtoupper($m[1]);
    }
    return $group;
}

/** تنسيق التاريخ حسب اللغة */
function fmt_date(?int $ts): string {
    if ($ts === null) return '—';
    if (current_lang() === 'ar') {
        $days   = ['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];
        $months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو',
                   'يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        $d = $days[(int)date('w', $ts)];
        $mn = $months[(int)date('n', $ts)];
        return $d . ' ' . date('j', $ts) . ' ' . $mn . ' ' . date('Y', $ts);
    }
    return date('D, j M Y', $ts);
}

/** تنسيق الوقت 12 ساعة */
function fmt_time(?int $ts): string {
    if ($ts === null) return '—';
    if (current_lang() === 'ar') {
        $h = (int)date('g', $ts);
        $min = date('i', $ts);
        $ampm = (date('a', $ts) === 'am') ? 'ص' : 'م';
        return $h . ':' . $min . ' ' . $ampm;
    }
    return date('g:i A', $ts);
}

/** تنسيق مختصر للتاريخ: "15 يونيو" */
function fmt_date_short(?int $ts): string {
    if ($ts === null) return '—';
    if (current_lang() === 'ar') {
        $months = ['','يناير','فبراير','مارس','أبريل','مايو','يونيو',
                   'يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'];
        return date('j', $ts) . ' ' . $months[(int)date('n', $ts)];
    }
    return date('j M', $ts);
}

/**
 * يُخرج تاريخ/وقت داخل وسم <time> يحمل لحظة المباراة المطلقة (UTC).
 * النص الظاهر = تنسيق الخادم (يظهر فوراً)، ثم يستبدله app.js بتوقيت بلد الزائر.
 * $mode: time | date | date_short | datetime
 * يُطبع كـ HTML موثوق (بدون e()) لأنه يُولّد الوسم بنفسه ويؤمّن النص داخلياً.
 */
function local_dt(?int $ts, string $mode = 'time'): string {
    if ($ts === null) return '—';
    switch ($mode) {
        case 'date':       $fallback = fmt_date($ts); break;
        case 'date_short': $fallback = fmt_date_short($ts); break;
        case 'datetime':   $fallback = fmt_date($ts) . ' · ' . fmt_time($ts); break;
        case 'time':
        default:           $fallback = fmt_time($ts); $mode = 'time'; break;
    }
    return '<time class="js-local" datetime="' . e(gmdate('c', $ts)) . '"'
         . ' data-ts="' . (int)$ts . '" data-mode="' . e($mode) . '">'
         . e($fallback) . '</time>';
}

/** شارة حالة المباراة (HTML) */
function status_badge(string $status): string {
    switch ($status) {
        case 'live':
            return '<span class="badge badge-live"><span class="live-dot"></span>'
                 . e(t('live')) . '</span>';
        case 'finished':
            return '<span class="badge badge-done">' . e(t('finished')) . '</span>';
        default:
            return '<span class="badge badge-soon">' . e(t('upcoming_short')) . '</span>';
    }
}

/** يرجّع النتيجة النهائية كنص "2 - 1" أو علامة "-" */
function score_text(array $m): string {
    if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
        return (int)$m['score']['ft'][0] . ' - ' . (int)$m['score']['ft'][1];
    }
    return '–';
}

/** كرت خبر واحد: صورة المصدر + العنوان + المصدر/الوقت. الضغط يفتح صفحة الخبر الداخلية. */
function render_news_item(array $it): void {
    $href = url('article.php', ['i' => $it['id'] ?? '']);
    $thumb = !empty($it['image']) ? $it['image'] : ($it['logo'] ?? '');
    $isPhoto = !empty($it['image']);
    ?>
    <a class="news-item" href="<?= e($href) ?>">
      <?php if ($thumb !== ''): ?>
        <span class="news-thumb <?= $isPhoto ? 'news-thumb-photo' : '' ?>"><img src="<?= e($thumb) ?>" alt="<?= e($it['source'] ?? '') ?>" loading="lazy"></span>
      <?php else: ?>
        <span class="news-thumb news-thumb-empty" aria-hidden="true">📰</span>
      <?php endif; ?>
      <span class="news-body">
        <span class="news-title"><?= e($it['title'] ?? '') ?></span>
        <span class="news-meta">
          <?php if (!empty($it['source'])): ?><span class="news-source"><?= e($it['source']) ?></span><?php endif; ?>
          <?php if (!empty($it['ts'])): ?><span class="news-time"><?= local_dt((int)$it['ts'], 'datetime') ?></span><?php endif; ?>
        </span>
      </span>
    </a>
    <?php
}

/** شريط أزرار مشاركة (X / واتساب / فيسبوك / تيليجرام / نسخ) لرابط ونصّ معيّنين. */
function render_share(string $url, string $text): void {
    $u = rawurlencode($url);
    // هاشتاقات: اسم الموقع (wcup2026) + كأس العالم 2026 عربي/إنجليزي + الرسمي (FIFAWorldCup26/WeAre26)
    // تركيز إقليمي: X للسعودية، فيسبوك لمصر — لزيادة الانتشار.
    $tagsX   = '#كأس_العالم_2026 #FIFAWorldCup26 #WeAre26 #السعودية #wcup2026';
    $tagsFB  = '#WorldCup2026 #FIFAWorldCup26 #مصر #wcup2026';
    $tagsGen = '#كأس_العالم_2026 #FIFAWorldCup26 #wcup2026';

    $tX = rawurlencode($text . "\n\n" . $tagsX);
    $tF = rawurlencode($text . "\n" . $tagsFB);
    $tG = rawurlencode($text . ' ' . $tagsGen);

    $x  = "https://twitter.com/intent/tweet?text={$tX}&url={$u}";
    $wa = "https://wa.me/?text={$tG}%20{$u}";
    $fb = "https://www.facebook.com/sharer/sharer.php?u={$u}&quote={$tF}";
    $tg = "https://t.me/share/url?url={$u}&text={$tG}";
    ?>
    <div class="share-bar">
      <span class="share-label"><?= e(t('share')) ?>:</span>
      <a class="share-btn s-x"  href="<?= e($x) ?>"  target="_blank" rel="noopener" aria-label="X">𝕏</a>
      <a class="share-btn s-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp">واتساب</a>
      <a class="share-btn s-fb" href="<?= e($fb) ?>" target="_blank" rel="noopener" aria-label="Facebook">f</a>
      <a class="share-btn s-tg" href="<?= e($tg) ?>" target="_blank" rel="noopener" aria-label="Telegram">✈</a>
      <button type="button" class="share-btn s-copy" data-url="<?= e($url) ?>"
              data-copied="<?= e(t('link_copied')) ?>">🔗 <?= e(t('copy_link')) ?></button>
    </div>
    <?php
}

/** صورة العلم كـ <img> أو دائرة فارغة للـ placeholder */
function flag_img(string $team, string $size = 'w40'): string {
    $u = flag_url($team, $size);
    if ($u === '') {
        return '<span class="flag flag-tbd" aria-hidden="true">?</span>';
    }
    return '<img class="flag" src="' . e($u) . '" alt="" loading="lazy" '
         . 'width="32" height="24">';
}

/**
 * http_get() — جلب HTTP موحّد وآمن من حيث المهلة (يُستخدم لكل الجلب الخارجي البسيط).
 *
 * لماذا هذه الدالة: المُجلبات القديمة كانت تجرّب cURL ثم تُتبعه بـ file_get_contents
 * عند الفشل، فتتراكم المهلتان (السبب الجذري لتعليق الصفحة و504). هنا:
 *   • نستخدم cURL وحده عند توفّره (لا تراكم)، بمهلة صارمة على الاتصال والقراءة معاً.
 *   • نلجأ لـ file_get_contents فقط إن كان cURL غير مثبَّت أصلاً — مع تقييد
 *     default_socket_timeout حتى لا يَعلَق إنشاء الاتصال/DNS.
 *
 * @param array $opts ['timeout'=>int ثوانٍ, 'ua'=>string, 'redirects'=>bool]
 * @return string|null جسم الرد عند نجاح 200، أو null عند أي فشل.
 */
function http_get(string $url, array $opts = []): ?string {
    if (!preg_match('#^https?://#i', $url)) return null;

    // حماية SSRF: ارفض المضيفات الداخلية/المحجوزة (localhost، الشبكة المحلية،
    // عنوان بيانات السحابة 169.254.169.254 ...) قبل أي اتصال.
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === null || $host === '' || !http_host_is_public((string)$host)) return null;

    $timeout   = (int)($opts['timeout'] ?? (defined('FETCH_TIMEOUT') ? FETCH_TIMEOUT : 5));
    if ($timeout < 1) $timeout = 1;
    $ua        = (string)($opts['ua'] ?? 'WorldCup2026Site/1.0');
    $redirects = array_key_exists('redirects', $opts) ? (bool)$opts['redirects'] : true;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $redirects,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_NOSIGNAL       => true,   // مهلة موثوقة داخل php-fpm متعدّد الخيوط
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_SSL_VERIFYPEER => true,
            // اقصر البروتوكولات على http/https فقط (يمنع file:// وgopher:// حتى عبر إعادة التوجيه)
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($raw === false || $code !== 200) ? null : $raw;
    }

    // لا cURL: file_get_contents مع تقييد زمن الاتصال أيضاً (لا تراكم — لا cURL أصلاً).
    $prev = @ini_set('default_socket_timeout', (string)$timeout);
    $ctx  = stream_context_create(['http' => [
        'timeout'         => $timeout,
        'user_agent'      => $ua,
        'follow_location' => $redirects ? 1 : 0,
        'max_redirects'   => 3,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($prev !== false) { @ini_set('default_socket_timeout', (string)$prev); }
    return ($raw === false) ? null : $raw;
}

/**
 * http_host_is_public() — هل المضيف عام (ليس داخلياً/محجوزاً)؟ حماية ضد SSRF.
 * يرفض: localhost، 127.0.0.0/8، 10/8، 172.16/12، 192.168/16، 169.254/16 (بيانات السحابة)،
 *        ::1، fc00::/7 ... إلخ. يرجّع true إن تعذّر الحلّ (فيفشل الاتصال لاحقاً بأمان).
 */
function http_host_is_public(string $host): bool {
    $host = trim($host, "[]");           // إزالة أقواس IPv6
    if ($host === '' || strcasecmp($host, 'localhost') === 0) return false;

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;                  // المضيف عنوان IP مباشر
    } else {
        $recs = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($recs)) {
            foreach ($recs as $r) {
                if (!empty($r['ip']))   $ips[] = $r['ip'];
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
        }
        if (!$ips) { $r = @gethostbyname($host); if ($r && $r !== $host) $ips[] = $r; }
    }
    if (!$ips) return true;              // تعذّر الحلّ → دع الاتصال يفشل لاحقاً (لا نكسر مضيفاً عامّاً)

    foreach ($ips as $ip) {
        // يرفض النطاقات الخاصة والمحجوزة (private + reserved)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}
