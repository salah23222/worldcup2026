<?php
/**
 * widget.php — ودجت نتائج قابل للتضمين في أي موقع عبر <iframe>.
 * صفحة مستقلّة بتصميمها (CSS مضمّن) — تعرض مباريات اليوم أو القادمة.
 *   widget.php?lang=ar&limit=5
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

// السماح بالتضمين في مواقع أخرى (تجاوز X-Frame-Options)
header_remove('X-Frame-Options');
header('Content-Security-Policy: frame-ancestors *');

$lang  = current_lang();
$dir   = lang_dir();
$limit = max(1, min(10, (int)($_GET['limit'] ?? 5)));

$today = DataService::matchesOnDate();
$list  = $today ?: DataService::upcomingMatches($limit);
$list  = array_slice($list, 0, $limit);
$site  = ($lang === 'ar') ? SITE_NAME_AR : SITE_NAME_EN;
$home  = base_url();
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($site) ?></title>
<style>
  :root { color-scheme: dark; }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',Tahoma,Arial,sans-serif; background:#0a1626; color:#eef4ff; padding:10px; }
  .wgt { max-width:520px; margin:0 auto; }
  .wgt-head { display:flex; align-items:center; gap:8px; font-weight:800; font-size:.95rem;
    padding-bottom:8px; margin-bottom:8px; border-bottom:1px solid #244268; }
  .wgt-mark { background:#00d563; color:#06231a; font-weight:900; border-radius:7px;
    width:28px; height:28px; display:grid; place-items:center; }
  .wgt-row { display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:8px;
    padding:8px 6px; border-bottom:1px solid #18304f; font-size:.85rem; }
  .wgt-t { display:flex; align-items:center; gap:6px; min-width:0; }
  .wgt-t.r { justify-content:flex-end; text-align:end; }
  .wgt-t img { width:22px; height:16px; border-radius:2px; }
  .wgt-t span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .wgt-sc { font-weight:800; color:#ffc233; font-variant-numeric:tabular-nums; min-width:54px; text-align:center; }
  .wgt-foot { display:block; text-align:center; margin-top:8px; color:#00d563;
    font-size:.78rem; font-weight:700; text-decoration:none; }
</style>
</head>
<body>
<div class="wgt">
  <div class="wgt-head"><span class="wgt-mark">26</span> <?= e($site) ?></div>
  <?php foreach ($list as $m):
    $t1 = trim($m['team1'] ?? ''); $t2 = trim($m['team2'] ?? '');
    $ts = DataService::matchTimestamp($m);
    $hasScore = isset($m['score']['ft']) && is_array($m['score']['ft']);
  ?>
    <div class="wgt-row">
      <div class="wgt-t r"><span><?= e(team_name($t1)) ?></span><?php if(flag_url($t1)): ?><img src="<?= e(flag_url($t1,'w40')) ?>" alt=""><?php endif; ?></div>
      <div class="wgt-sc">
        <?php if ($hasScore): ?>
          <?= (int)$m['score']['ft'][0] ?>-<?= (int)$m['score']['ft'][1] ?>
        <?php else: ?>
          <time data-ts="<?= (int)$ts ?>"><?= e(fmt_time($ts)) ?></time>
        <?php endif; ?>
      </div>
      <div class="wgt-t"><?php if(flag_url($t2)): ?><img src="<?= e(flag_url($t2,'w40')) ?>" alt=""><?php endif; ?><span><?= e(team_name($t2)) ?></span></div>
    </div>
  <?php endforeach; ?>
  <a class="wgt-foot" href="<?= e($home) ?>" target="_top" rel="noopener"><?= e($site) ?> — <?= e(parse_url(SITE_URL, PHP_URL_HOST) ?: 'wcup2026.org') ?> ›</a>
</div>
<script>
  // حوّل الأوقات لتوقيت زائر الموقع المُضيف
  document.querySelectorAll('time[data-ts]').forEach(function(t){
    var ts = parseInt(t.getAttribute('data-ts'),10); if(!ts) return;
    try { t.textContent = new Intl.DateTimeFormat(undefined,{hour:'numeric',minute:'2-digit'}).format(new Date(ts*1000)); } catch(e){}
  });
</script>
</body>
</html>
