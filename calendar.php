<?php
/**
 * calendar.php — يولّد ملف تقويم iCalendar (.ics) لكل مباريات كأس العالم 2026.
 *
 * يُبنى من نفس بيانات openfootball المخزّنة لدينا (لا اعتماد على طرف ثالث).
 * يصلح للاشتراك المباشر (webcal://) أو التنزيل، ويعمل مع Google/Apple/Outlook.
 *
 * المعاملات:
 *   ?id=N   مباراة واحدة فقط (اختياري)
 *   ?lang=  لغة أسماء المنتخبات (ar|en) — افتراضياً لغة الموقع
 */
require __DIR__ . '/includes/bootstrap.php';

/** تهريب نص حسب RFC 5545. */
function ics_esc(string $s): string {
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    $s = str_replace(',', '\\,', $s);
    $s = str_replace(';', '\\;', $s);
    return $s;
}

/** طيّ السطور الطويلة (الحد 75 ثُماني) على حدود الأحرف (آمن مع UTF-8/العربية). */
function ics_fold(string $line): string {
    if (strlen($line) <= 73) return $line;
    $out = ''; $cur = ''; $len = 0;
    $n = mb_strlen($line, 'UTF-8');
    for ($i = 0; $i < $n; $i++) {
        $ch = mb_substr($line, $i, 1, 'UTF-8');
        $b  = strlen($ch);
        if ($len + $b > 73) { $out .= $cur . "\r\n "; $cur = $ch; $len = $b; }
        else                { $cur .= $ch; $len += $b; }
    }
    return $out . $cur;
}

$lang   = current_lang();
$single = isset($_GET['id']) ? (int)$_GET['id'] : null;

$matches = DataService::allMatches();
$host    = parse_url(SITE_URL, PHP_URL_HOST) ?: 'worldcup2026';
$now     = gmdate('Ymd\THis\Z');
$brand   = $lang === 'ar' ? SITE_NAME_AR : SITE_NAME_EN;     // اسم الموقع في كل حدث
$siteBase = rtrim(SITE_URL, '/');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//WorldCup2026//Matches//' . strtoupper($lang),
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:' . ics_esc($lang === 'ar' ? 'كأس العالم 2026' : 'World Cup 2026'),
    'X-WR-TIMEZONE:UTC',
    'REFRESH-INTERVAL;VALUE=DURATION:PT12H',
    'X-PUBLISHED-TTL:PT12H',
];

foreach ($matches as $m) {
    $idx = (int)($m['_index'] ?? 0);
    if ($single !== null && $idx !== $single) continue;

    $ts = DataService::matchTimestamp($m);
    if (!$ts) continue;   // تخطّى ما لا موعد له

    $t1 = team_name(trim($m['team1'] ?? '')) ?: 'TBD';
    $t2 = team_name(trim($m['team2'] ?? '')) ?: 'TBD';

    $summary = $t1 . ' vs ' . $t2;
    if (isset($m['score']['ft']) && is_array($m['score']['ft'])) {
        $summary .= ' (' . (int)$m['score']['ft'][0] . '-' . (int)$m['score']['ft'][1] . ')';
    }

    $matchUrl = $siteBase . '/match.php?id=' . $idx;
    // الوصف يبدأ باسم الموقع، ثم الدور/المجموعة، ثم رابط المباراة.
    $descParts = [$brand];
    if (!empty($m['round'])) $descParts[] = round_label($m['round']);
    if (!empty($m['group'])) $descParts[] = group_label($m['group']);
    $desc = implode(' · ', $descParts) . "\n" . $matchUrl;

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:wc2026-match-' . $idx . '@' . $host;
    $lines[] = 'DTSTAMP:' . $now;
    $lines[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $ts);
    $lines[] = 'DTEND:'   . gmdate('Ymd\THis\Z', $ts + 5400); // +90 دقيقة
    $lines[] = 'SUMMARY:' . ics_esc($summary . ' — ' . $brand);
    $lines[] = 'DESCRIPTION:' . ics_esc($desc);
    if (!empty($m['ground']))         $lines[] = 'LOCATION:'    . ics_esc((string)$m['ground']);
    if (defined('CONTACT_EMAIL') && CONTACT_EMAIL !== '') {
        $lines[] = 'ORGANIZER;CN=' . ics_esc($brand) . ':mailto:' . CONTACT_EMAIL;
    }
    $lines[] = 'URL:' . $matchUrl;
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';

// إخراج الملف
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="worldcup2026.ics"');
header('Cache-Control: public, max-age=3600');

echo implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
