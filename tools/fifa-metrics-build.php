<?php
/**
 * tools/fifa-metrics-build.php — يبني لقطة المقاييس الفنّيّة الكاملة للاعبين
 * (هجوم/إبداع/اختراق خطوط/تمرير/ضغط/دفاع/كرات ثابتة/انضباط/حراسة) من خلاصة FIFA
 * المنظّمة (التي يوفّرها مشروع fifaphy عاماً) + التقييمات + المراكز.
 *
 * مصدر FIFA الوحيد لهذه المقاييس الغنيّة هو الخلاصة المنظّمة (لا يحويها PDF التقرير
 * الذي نحلّله محليّاً — هو بدنيّ فقط). نأخذ لقطة دائمة فنعتمد عليها كبيانات خاصّة بنا.
 *
 *   php tools/fifa-metrics-build.php <dir_with data.js ratings.js posreal.js>
 *   → يكتب assets/fifa-metrics.json (مجاميع لكل لاعب + دقائق + تقييم + مركز).
 * يُشغَّل محليّاً فقط (مثل fifa-build). per-90/المئويّات/الرادار تُحسب وقت العرض في FifaMetrics.
 */
chdir(dirname(__DIR__));
$dir = rtrim($argv[1] ?? (__DIR__ . '/_fp'), '/\\');

$dataJs    = @file_get_contents("$dir/data.js");
$ratingsJs = @file_get_contents("$dir/ratings.js");
$posrealJs = @file_get_contents("$dir/posreal.js");
if (!$dataJs)    { fwrite(STDERR, "missing $dir/data.js\n");    exit(1); }
if (!$ratingsJs) { fwrite(STDERR, "missing $dir/ratings.js\n"); exit(1); }

// ── 1) التقييمات + الدقائق لكل لاعب (من ratings.js) ─────────────────────────
//    RATINGS.matches[mid].players[pid] = {r, pos, min, name, team}
$minSum = []; $rSum = []; $rCnt = []; $posStr = [];
if (preg_match('/const RATINGS\s*=\s*(\{.*\})\s*;?\s*$/s', trim($ratingsJs), $rm)) {
    $R = json_decode($rm[1], true);
    foreach (($R['matches'] ?? []) as $match) {
        foreach (($match['players'] ?? []) as $pid => $pr) {
            $min = (int)($pr['min'] ?? 0);
            $minSum[$pid] = ($minSum[$pid] ?? 0) + $min;
            if (isset($pr['r'])) { $rSum[$pid] = ($rSum[$pid] ?? 0) + (float)$pr['r']; $rCnt[$pid] = ($rCnt[$pid] ?? 0) + 1; }
            $p = (string)($pr['pos'] ?? '');
            if ($p !== '' && $p !== 'Substitute') $posStr[$pid] = $p;   // مركز فعلي لا «بديل»
        }
    }
}

// ── 2) المركز الحقيقي + الخطّ (من posreal.js) ───────────────────────────────
$posMain = []; $posLine = [];
if ($posrealJs && preg_match('/const POSREAL\s*=\s*(\{.*\})\s*;?\s*$/s', trim($posrealJs), $pm)) {
    $P = json_decode($pm[1], true);
    foreach (($P['players'] ?? []) as $pid => $pp) {
        $posMain[$pid] = (string)($pp['main'] ?? '');
        $posLine[$pid] = (int)($pp['line'] ?? -1);
    }
}
$LINE_GROUP = [0 => 'GK', 1 => 'DEF', 2 => 'MID', 3 => 'FWD'];
$groupFromPosStr = function (string $s): string {
    if (stripos($s, 'Goalkeeper') !== false) return 'GK';
    if (stripos($s, 'Defender') !== false)   return 'DEF';
    if (stripos($s, 'Striker') !== false || stripos($s, 'Forward') !== false) return 'FWD';
    return 'MID';
};

// ── 3) تجميع المقاييس لكل لاعب (من data.js — كائنات لاعب/مباراة) ─────────────
$re = '/\{"playerId":"(\d+)","name":"([^"]*)","teamId":"[^"]*","teamName":"([^"]*)","teamCode":"([^"]*)","photo":"([^"]*)"[^{]*"metrics":\{([^{}]*)\}/u';
preg_match_all($re, $dataJs, $rows, PREG_SET_ORDER);

$players = [];
foreach ($rows as $r) {
    $pid = $r[1];
    if (!isset($players[$pid])) {
        $players[$pid] = [
            'name' => $r[2], 'teamName' => $r[3], 'team' => strtoupper($r[4]), 'photo' => $r[5],
            'm' => [],
        ];
    }
    // أزواج "Key":number داخل metrics
    if (preg_match_all('/"([A-Za-z0-9]+)":(-?[0-9.]+)/', $r[6], $kv, PREG_SET_ORDER)) {
        foreach ($kv as $p) {
            $val = (float)$p[2];
            if ($val == 0.0) continue;                       // أهمل الأصفار لتصغير الملفّ
            $players[$pid]['m'][$p[1]] = ($players[$pid]['m'][$p[1]] ?? 0) + $val;
        }
    }
}

// ── 4) أرفِق الدقائق/التقييم/المركز + نظّف ───────────────────────────────────
$out = [];
foreach ($players as $pid => $pl) {
    $min = (int)($minSum[$pid] ?? 0);
    if ($min <= 0) continue;                                  // بلا دقائق لا per-90 موثوق
    $line = $posLine[$pid] ?? -1;
    $group = $LINE_GROUP[$line] ?? $groupFromPosStr($posStr[$pid] ?? '');
    $m = $pl['m'];
    foreach ($m as $k => $v) { $m[$k] = round($v, 2); }       // تقليم
    $out[$pid] = [
        'name'     => $pl['name'],
        'team'     => $pl['team'],
        'teamName' => $pl['teamName'],
        'photo'    => $pl['photo'],
        'pos'      => $posMain[$pid] ?? ($posStr[$pid] ?? ''),
        'grp'      => $group,
        'min'      => $min,
        'mp'       => $rCnt[$pid] ?? 1,
        'r'        => isset($rCnt[$pid]) && $rCnt[$pid] > 0 ? round($rSum[$pid] / $rCnt[$pid], 2) : null,
        'm'        => $m,
    ];
}

$payload = [
    '_source'    => 'FIFA structured feed (via public fifaphy dataset) — technical metrics + ratings',
    '_generated' => gmdate('Y-m-d'),
    '_count'     => count($out),
    'players'    => $out,
];
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/../assets/fifa-metrics.json', $json);
echo 'built ' . count($out) . ' players · ' . round(strlen($json) / 1024) . " KB\n";

// ── 5) خريطة صور اللاعبين (assets/fifa-photos.json) — من نفس الكائنات ──────────
//    تُحدَّث تلقائياً فيلتقط اللاعبين الجدد/البدلاء فور ظهورهم في الخلاصة.
$norm = function (string $s): string {
    $n = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($n === false) $n = $s;
    return preg_replace('/\s+/', ' ', trim(preg_replace('/[^A-Z0-9 ]/', ' ', strtoupper($n))));
};
$byName = []; $dup = []; $seenP = [];
foreach ($rows as $r) {
    if (isset($seenP[$r[1]])) continue;
    $seenP[$r[1]] = 1;
    $n = $norm($r[2]); $photo = $r[5];
    if ($n === '' || $photo === '') continue;
    if (isset($byName[$n]) && $byName[$n] !== $photo) $dup[$n] = 1;
    elseif (!isset($byName[$n])) $byName[$n] = $photo;
}
foreach (array_keys($dup) as $k) unset($byName[$k]);
ksort($byName);
$photoJson = json_encode([
    '_source'    => 'FIFA Digital Hub (digitalhub.fifa.com) — official WC2026 player photos',
    '_generated' => gmdate('Y-m-d'),
    '_count'     => count($byName),
    'byName'     => $byName,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/../assets/fifa-photos.json', $photoJson);
echo 'photos ' . count($byName) . " players\n";
