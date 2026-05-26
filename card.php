<?php
/**
 * card.php — صفحة بطاقة قابلة للمشاركة (هبوط غني بمعاينة og:image).
 * ============================================================
 * مُعمّمة لكل أنواع البطاقات عبر ?type=:
 *   predict (توقّعي) · brag (قلتلكم!) · result (نتيجة) ·
 *   sticker (كشف ملصق) · qahr (قهر 😩).
 *
 * التوافق الخلفي (مهم جداً):
 *   • card.php?id=5&p1=2&p2=1&brag=1  → بطاقة توقّع/تباهٍ كما كانت (أو أجمل).
 *   • card.php?id=5&mode=match&v=2    → تُرجِع PNG كما كانت تاريخياً، لأن
 *     match.php (لا نملكه) يستعملها مباشرةً كـ og:image للمباراة. لا نكسرها:
 *     نمرّر طلب الصورة إلى card_img.php شفافياً.
 *
 * الصورة (og:image) لكل بطاقة HTML = card_img.php بنفس المعطيات (1200×630).
 * نُبقي البطاقات قابلة للتخزين (Cache-Control) — وهي ضمن قائمة PageCache::$noCache.
 * ============================================================
 */
require __DIR__ . '/includes/bootstrap.php';

/* ----------------------------------------------------------------
 * التوافق الخلفي: وضع المباراة (og:image لصفحة match.php) يُرجِع صورة PNG.
 * نُسلّم التوليد لـ card_img.php مع الحفاظ على نفس المعطيات تماماً.
 * ---------------------------------------------------------------- */
if (($_GET['mode'] ?? '') === 'match') {
    require __DIR__ . '/card_img.php';
    exit;
}

/* ----------------------------------------------------------------
 * صفحة هبوط HTML غنية للمشاركة.
 * ---------------------------------------------------------------- */
// توافق خلفي: الرابط القديم card.php?id=&p1=&p2=&brag=1 (من predict.js) لا يحمل
// type=، لكن brag=1 يعني بطاقة «قلتلكم!». نشتقّ النوع منه إن لم يُحدَّد صراحةً.
$reqType = $_GET['type'] ?? '';
if ($reqType === '' && !empty($_GET['brag'])) {
    $reqType = 'brag';
}
$type = Cards::normalizeType($reqType !== '' ? $reqType : 'predict');
$data = Cards::build($type, $_GET);

$lang   = $data['lang'];
$dir    = ($lang === 'ar') ? 'rtl' : 'ltr';
$accent = $data['accent'];
$m      = $data['match'];

// معطيات تُمرَّر للصورة وللروابط (مُطبّعة)
$imgParams = [];
if ($m) $imgParams['id'] = $m['id'];
$imgParams['p1'] = $data['p1'];
$imgParams['p2'] = $data['p2'];
if (!empty($_GET['brag'])) $imgParams['brag'] = 1;
if (isset($_GET['name'])) $imgParams['name'] = $_GET['name'];
if (isset($_GET['team'])) $imgParams['team'] = $_GET['team'];

$ogImage  = Cards::imageUrl($type, $imgParams);   // 1200×630 عبر card_img.php
$shareUrl = Cards::shareUrl($type, $imgParams);   // رابط هذه الصفحة (مطلق)
$shareTxt = $data['share'];

$siteName = ($lang === 'ar') ? SITE_NAME_AR : SITE_NAME_EN;
$ogTitle  = $data['headline'] . ' — ' . $siteName;
$ogDesc   = $shareTxt;

// البطاقة قابلة للتخزين (يوم كامل) — تطابق سلوك card.php التاريخي.
header('Cache-Control: public, max-age=86400');

$jsV = @filemtime(__DIR__ . '/assets/js/share.js') ?: 1;
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ogTitle) ?></title>
<meta name="description" content="<?= e($ogDesc) ?>">
<meta name="theme-color" content="#0a1626">

<!-- Open Graph / Twitter — معاينة غنية عند اللصق في واتساب/X/تيليجرام -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:locale" content="<?= $lang === 'ar' ? 'ar_AR' : 'en_US' ?>">
<meta property="og:title" content="<?= e($ogTitle) ?>">
<meta property="og:description" content="<?= e($ogDesc) ?>">
<meta property="og:url" content="<?= e($shareUrl) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($ogTitle) ?>">
<meta name="twitter:description" content="<?= e($ogDesc) ?>">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<link rel="icon" type="image/png" href="<?= e(base_url()) ?>/assets/img/icon-192.png">
<link rel="preconnect" href="https://flagcdn.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
  :root { --accent: <?= e($accent) ?>; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body {
    font-family: 'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif;
    background: radial-gradient(120% 120% at 50% 0%, #14294a 0%, #0a1626 60%, #060f1c 100%);
    color: #eef4ff;
    min-height: 100%;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 24px 16px;
    -webkit-font-smoothing: antialiased;
  }
  .card {
    width: 100%; max-width: 600px;
    background: linear-gradient(160deg, #0e1d34 0%, #0a1626 100%);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 22px;
    box-shadow: 0 24px 60px rgba(0,0,0,.45);
    overflow: hidden;
    position: relative;
  }
  .card::before {
    content: ""; position: absolute; inset: 0 0 auto 0; height: 6px;
    background: var(--accent);
  }
  .card-head {
    display: flex; align-items: center; gap: 10px;
    padding: 22px 26px 6px;
  }
  .mark {
    width: 40px; height: 40px; border-radius: 10px;
    background: #eef4ff; color: #0a1626;
    font-weight: 900; font-size: 20px;
    display: grid; place-items: center;
    letter-spacing: -1px;
  }
  .head-meta { font-size: 13px; color: #9fb3d1; font-weight: 600; }
  .head-meta strong { color: #eef4ff; display: block; font-size: 15px; }
  .headline {
    padding: 6px 26px 0;
    font-size: 30px; font-weight: 900; line-height: 1.25;
    color: var(--accent);
  }
  .teams {
    display: flex; align-items: center; justify-content: center;
    gap: 18px; padding: 22px 20px 6px;
  }
  .team { flex: 1; text-align: center; min-width: 0; }
  .team img {
    width: 96px; height: 64px; object-fit: cover; border-radius: 8px;
    border: 1px solid rgba(255,255,255,.12);
    box-shadow: 0 6px 18px rgba(0,0,0,.35);
  }
  .team .tn {
    display: block; margin-top: 10px;
    font-size: 16px; font-weight: 700; color: #cfe0f7;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .score {
    flex: 0 0 auto;
    font-size: 52px; font-weight: 900; letter-spacing: 1px;
    color: #fff; min-width: 120px; text-align: center;
  }
  .score.qahr { color: var(--accent); }
  .emoji-hero { font-size: 84px; text-align: center; padding: 14px 0 0; }
  .subline {
    padding: 8px 26px 0;
    text-align: center; font-size: 18px; color: #cfe0f7; line-height: 1.6;
  }
  .meta-line {
    padding: 6px 26px 0; text-align: center;
    font-size: 14px; color: #9fb3d1;
  }
  .card-foot {
    margin-top: 22px; padding: 14px 26px;
    border-top: 1px solid rgba(255,255,255,.07);
    display: flex; align-items: center; justify-content: space-between;
    font-size: 13px; color: #9fb3d1;
  }
  .card-foot .domain { color: #eef4ff; font-weight: 700; letter-spacing: .5px; }

  .share-bar {
    display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;
    margin-top: 22px; width: 100%; max-width: 600px;
  }
  .share-bar a, .share-bar button {
    appearance: none; border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06); color: #eef4ff;
    font-family: inherit; font-size: 15px; font-weight: 700;
    padding: 12px 16px; border-radius: 12px; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
    transition: transform .12s ease, background .12s ease;
  }
  .share-bar a:hover, .share-bar button:hover { background: rgba(255,255,255,.12); transform: translateY(-1px); }
  .s-wa { border-color: rgba(37,211,102,.5) !important; }
  .s-x  { border-color: rgba(255,255,255,.25) !important; }
  .s-tg { border-color: rgba(42,171,238,.5) !important; }
  .home-link {
    margin-top: 20px; color: #9fb3d1; font-size: 14px;
    text-decoration: none; font-weight: 600;
  }
  .home-link:hover { color: #eef4ff; }
  @media (max-width: 480px) {
    .headline { font-size: 24px; }
    .score { font-size: 40px; min-width: 90px; }
    .team img { width: 76px; height: 52px; }
    .emoji-hero { font-size: 64px; }
  }
</style>
</head>
<body>

<div class="card">
  <div class="card-head">
    <span class="mark">26</span>
    <span class="head-meta">
      <strong><?= e($siteName) ?></strong>
      <?= e($lang === 'ar' ? 'بطاقة قابلة للمشاركة' : 'Shareable card') ?>
    </span>
  </div>

  <h1 class="headline"><?= e($data['headline']) ?></h1>

  <?php if ($m !== null): ?>
    <div class="teams">
      <span class="team">
        <?php if ($m['flag1'] !== ''): ?><img src="<?= e($m['flag1']) ?>" alt="<?= e($m['name1']) ?>" loading="lazy"><?php endif; ?>
        <span class="tn"><?= e($m['name1']) ?></span>
      </span>
      <?php if ($data['score'] !== ''): ?>
        <span class="score<?= $type === 'qahr' ? ' qahr' : '' ?>"><?= e($data['score']) ?></span>
      <?php else: ?>
        <span class="score"><?= e($lang === 'ar' ? 'ضد' : 'VS') ?></span>
      <?php endif; ?>
      <span class="team">
        <?php if ($m['flag2'] !== ''): ?><img src="<?= e($m['flag2']) ?>" alt="<?= e($m['name2']) ?>" loading="lazy"><?php endif; ?>
        <span class="tn"><?= e($m['name2']) ?></span>
      </span>
    </div>
    <?php if ($m['ts'] !== null && in_array($type, ['predict', 'brag'], true)): ?>
      <div class="meta-line"><?= local_dt($m['ts'], 'datetime') ?></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="emoji-hero"><?= e($data['emoji']) ?></div>
  <?php endif; ?>

  <?php if ($data['subline'] !== ''): ?>
    <p class="subline"><?= e($data['subline']) ?></p>
  <?php endif; ?>

  <div class="card-foot">
    <span><?= e($data['emoji']) ?> <?= e($lang === 'ar' ? 'كأس العالم 2026' : 'World Cup 2026') ?></span>
    <span class="domain"><?= e(parse_url(base_url(), PHP_URL_HOST) ?: 'wcup2026.org') ?></span>
  </div>
</div>

<div class="share-bar" data-share-url="<?= e($shareUrl) ?>" data-share-text="<?= e($shareTxt) ?>">
  <a class="share-btn s-wa" data-share="wa" href="<?= e('https://wa.me/?text=' . rawurlencode($shareTxt . ' ' . '#كأس_العالم_2026 #FIFAWorldCup26 #wcup2026' . ' ' . $shareUrl)) ?>" target="_blank" rel="noopener"><?= e($lang === 'ar' ? 'واتساب' : 'WhatsApp') ?></a>
  <a class="share-btn s-x"  data-share="x"  href="<?= e('https://twitter.com/intent/tweet?text=' . rawurlencode($shareTxt . "\n\n" . '#كأس_العالم_2026 #FIFAWorldCup26 #wcup2026') . '&url=' . rawurlencode($shareUrl)) ?>" target="_blank" rel="noopener">𝕏</a>
  <a class="share-btn s-tg" data-share="tg" href="<?= e('https://t.me/share/url?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareTxt . ' ' . '#كأس_العالم_2026 #FIFAWorldCup26 #wcup2026')) ?>" target="_blank" rel="noopener"><?= e($lang === 'ar' ? 'تيليجرام' : 'Telegram') ?></a>
  <button type="button" class="share-btn s-copy" data-share="copy" data-copied="<?= e(t('link_copied')) ?>"><?= e(t('copy_link')) ?></button>
</div>

<a class="home-link" href="<?= e(base_url()) ?>/index.php?lang=<?= e($lang) ?>">
  <?= e($lang === 'ar' ? '← اذهب إلى الموقع' : '← Visit the site') ?>
</a>

<script src="<?= e(base_url()) ?>/assets/js/share.js?v=<?= e((string)$jsV) ?>"></script>
</body>
</html>
