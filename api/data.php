<?php
/**
 * api/data.php
 * ============================================================
 * API داخلي خاص بالموقع. يُرجّع JSON تستهلكه واجهة JavaScript
 * للتحديث التلقائي بدون إعادة تحميل الصفحة.
 *
 * أمثلة:
 *   api/data.php?action=today      → مباريات اليوم
 *   api/data.php?action=live       → المباريات المباشرة فقط
 *   api/data.php?action=match&id=5 → مباراة واحدة
 *   api/data.php?action=group&g=Group+A → ترتيب مجموعة
 * ============================================================
 */
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');     // المتصفح يخزّن دقيقة
header('Access-Control-Allow-Origin: *');         // للاستخدام كـ API عام

$action = $_GET['action'] ?? 'today';

/** يبسّط مصفوفة مباراة لإرسالها كـ JSON خفيف */
function slim_match(array $m): array {
    $ts = DataService::matchTimestamp($m);
    return [
        'id'       => $m['_index'] ?? null,
        'round'    => $m['round'] ?? '',
        'group'    => $m['group'] ?? '',
        'team1'    => $m['team1'] ?? '',
        'team2'    => $m['team2'] ?? '',
        'team1_ar' => team_name(trim($m['team1'] ?? '')),
        'team2_ar' => team_name(trim($m['team2'] ?? '')),
        'flag1'    => flag_url(trim($m['team1'] ?? ''), 'w80'),
        'flag2'    => flag_url(trim($m['team2'] ?? ''), 'w80'),
        'status'   => $m['_status'] ?? DataService::matchStatus($m),
        'score'    => $m['score']['ft'] ?? null,
        'live_minute' => $m['_live_minute'] ?? null,
        'date'     => $ts ? date('Y-m-d', $ts) : null,
        'time'     => fmt_time($ts),
        'datetime' => $ts,
        'ground'   => $m['ground'] ?? '',
    ];
}

$response = ['ok' => true, 'action' => $action, 'updated' => time()];

switch ($action) {

    case 'today':
        $response['matches'] = array_map('slim_match', DataService::matchesOnDate());
        break;

    case 'live':
        $live = array_filter(DataService::allMatches(),
                             fn($m) => $m['_status'] === 'live');
        $response['matches'] = array_map('slim_match', array_values($live));
        break;

    case 'upcoming':
        $response['matches'] = array_map('slim_match',
                               DataService::upcomingMatches((int)($_GET['limit'] ?? 10)));
        break;

    case 'results':
        $response['matches'] = array_map('slim_match',
                               DataService::latestResults((int)($_GET['limit'] ?? 10)));
        break;

    case 'all':
        $response['matches'] = array_map('slim_match', DataService::allMatches());
        break;

    case 'match':
        $m = DataService::matchByIndex((int)($_GET['id'] ?? -1));
        if ($m) {
            $response['match'] = slim_match($m);
        } else {
            $response['ok'] = false;
            $response['error'] = 'not_found';
        }
        break;

    case 'group':
        $g = $_GET['g'] ?? '';
        $response['group']     = $g;
        $response['standings'] = Standings::forGroup($g);
        break;

    case 'standings':
        $response['standings'] = Standings::all();
        break;

    case 'loss':
        $lm = DataService::matchByIndex((int)($_GET['id'] ?? -1));
        $response['analysis'] = null;
        if ($lm && isset($lm['score']['ft']) && is_array($lm['score']['ft'])) {
            $a1 = (int)$lm['score']['ft'][0]; $a2 = (int)$lm['score']['ft'][1];
            if ($a1 !== $a2) {
                $loser = ($a1 < $a2) ? trim($lm['team1'] ?? '') : trim($lm['team2'] ?? '');
                $response['analysis'] = AiContent::lossAnalysis($lm, $loser);
            }
        }
        $response['ok'] = ($response['analysis'] !== null);
        break;

    case 'physical':   // مستكشف البيانات البدنيّة — يُرسَم بالمتصفّح (أخفّ + أصمد من جدول 251 صفّاً)
        $players = class_exists('FifaStats') ? FifaStats::physicalLeaderboard() : [];
        $out = [];
        foreach ($players as $r) {
            $en = (string)($r['team'] ?? '');
            $out[] = [
                'name'   => (string)($r['name'] ?? ''),
                'team'   => $en,
                'teamAr' => function_exists('team_name') ? team_name($en) : $en,
                'flag'   => function_exists('flag_url') ? flag_url($en, 'w40') : '',
                'photo'  => function_exists('player_photo') ? player_photo((string)($r['name'] ?? '')) : '',
                'num'    => (int)($r['num'] ?? 0),
                'm'      => (int)($r['m'] ?? 0),
                'dist'   => (float)($r['dist'] ?? 0),
                'sprints'=> (int)($r['sprints'] ?? 0),
                'hsr'    => (int)($r['hsr'] ?? 0),
                'top'    => (float)($r['top'] ?? 0),
                'zones'  => array_map('floatval', (array)($r['zones'] ?? [])),
                'lb'     => array_map('intval', (array)($r['lb'] ?? [])),
                'lbv'    => array_map('intval', (array)($r['lbv'] ?? [])),
                'cross'  => array_map('intval', (array)($r['cross'] ?? [])),
            ];
        }
        $response['players'] = $out;
        break;

    default:
        $response['ok'] = false;
        $response['error'] = 'unknown_action';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
