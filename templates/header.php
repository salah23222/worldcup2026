<?php
/**
 * header.php — رأس الصفحة المشترك (قبل المحتوى).
 * يتوقع متغيّر $page_title اختيارياً.
 */
if (!defined('WC2026')) { exit('Access denied'); }
$lang  = current_lang();
$dir   = lang_dir();
$title = isset($page_title) ? ($page_title . ' — ' . t('site_desc')) : t('site_desc');
// تحديد الصفحة النشطة من اسم الملف الحالي
$current = basename($_SERVER['SCRIPT_NAME']);
function nav_active(string $file): string {
    return (basename($_SERVER['SCRIPT_NAME']) === $file) ? ' class="active"' : '';
}
/** هل الصفحة الحالية ضمن مجموعة قائمة منسدلة؟ (لتمييز زر المجموعة) */
function nav_group_active(array $files): string {
    return in_array(basename($_SERVER['SCRIPT_NAME']), $files, true) ? ' nav-group-on' : '';
}
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($dir) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- أول زيارة فقط: يفعّل حركة الدخول (يُضبط قبل الرسم لتفادي الوميض) -->
<script>try{if(!localStorage.getItem('wc_seen')){document.documentElement.classList.add('intro');localStorage.setItem('wc_seen','1');}}catch(e){}</script>
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($page_desc ?? t('site_desc')) ?>">
<?php
// كلمات مفتاحية للبحث (Bing/محركات أخرى تستخدمها؛ Google يتجاهلها غالباً — القيمة الأكبر
// في العنوان والوصف والمحتوى والبيانات المنظّمة، وكلها مضبوطة). تُترجَم حسب اللغة.
$kw = ($lang === 'ar')
    ? 'كأس العالم 2026, مونديال 2026, كأس العالم, نتائج كأس العالم 2026, مباريات كأس العالم 2026, '
      . 'جدول مباريات المونديال, ترتيب المجموعات, مواعيد المباريات بتوقيتك, توقعات كأس العالم, '
      . 'الأدوار الإقصائية, المنتخبات, الملاعب, كأس العالم كندا المكسيك أمريكا, هدافو كأس العالم, دليل المشجع'
    : 'FIFA World Cup 2026, World Cup 2026, World Cup 2026 schedule, World Cup 2026 results, '
      . 'World Cup 2026 fixtures, group standings, knockout bracket, match predictions, World Cup 2026 teams, '
      . 'World Cup stadiums, Canada Mexico USA World Cup, live scores, top scorers, fan guide';
?>
<meta name="keywords" content="<?= e($page_keywords ?? $kw) ?>">
<?php if (defined('GOOGLE_SITE_VERIFICATION') && GOOGLE_SITE_VERIFICATION !== ''): ?>
<meta name="google-site-verification" content="<?= e(GOOGLE_SITE_VERIFICATION) ?>">
<?php endif; ?>
<?php if (defined('BING_SITE_VERIFICATION') && BING_SITE_VERIFICATION !== ''): ?>
<meta name="msvalidate.01" content="<?= e(BING_SITE_VERIFICATION) ?>">
<?php endif; ?>
<meta name="theme-color" content="#0a1626">
<?php $b = e(rtrim(SITE_URL, '/')); ?>
<link rel="manifest" href="<?= $b ?>/manifest.php">
<link rel="icon" type="image/png" href="<?= $b ?>/assets/img/icon-192.png">
<link rel="apple-touch-icon" href="<?= $b ?>/assets/img/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($lang === 'ar' ? 'مونديال 2026' : 'WC 2026') ?>">
<?php
// كل وسوم SEO: canonical + hreflang + Open Graph/Twitter + JSON-LD
seo_head([
    'title'       => isset($page_title) ? $page_title : t('site_desc'),
    'description' => $page_desc ?? t('site_desc'),
    'type'        => $seo_type ?? 'website',
    'image'       => (isset($page_image) && $page_image !== '') ? $page_image : (base_url() . '/assets/img/og.png'),
]);
?>
<link rel="preconnect" href="https://flagcdn.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<?php $cssV = @filemtime(__DIR__ . '/../assets/css/style.css') ?: 1; ?>
<link rel="stylesheet" href="<?= $b ?>/assets/css/style.css?v=<?= $cssV ?>">
</head>
<body>

<header class="site-header">
  <div class="wrap header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-mark">26</span>
      <span class="brand-text">
        <strong><?= e($lang === 'ar' ? SITE_NAME_AR : SITE_NAME_EN) ?></strong>
        <small><?= e($lang === 'ar' ? SITE_TAGLINE_AR : SITE_TAGLINE_EN) ?></small>
      </span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="menu">
      <span></span><span></span><span></span>
    </button>

    <nav class="main-nav" id="mainNav">
      <a href="<?= e(url('index.php')) ?>"<?= nav_active('index.php') ?>><?= e(t('home')) ?></a>
      <a href="<?= e(url('today.php')) ?>"<?= nav_active('today.php') ?>><?= e($lang === 'ar' ? 'اليوم' : 'Today') ?></a>
      <a href="<?= e(url('matches.php')) ?>"<?= nav_active('matches.php') ?>><?= e(t('matches')) ?></a>
      <a href="<?= e(url('news.php')) ?>"<?= nav_active('news.php') ?>><?= e(t('news')) ?></a>

      <div class="nav-group">
        <button type="button" class="nav-group-btn<?= nav_group_active(['groups.php','knockout.php','teams.php','squads.php','stadiums.php','map.php','fanguide.php','archive.php']) ?>">
          <?= e(t('nav_tournament')) ?><i class="nav-caret">▾</i>
        </button>
        <div class="nav-drop">
          <a href="<?= e(url('groups.php')) ?>"<?= nav_active('groups.php') ?>><?= e(t('groups')) ?></a>
          <a href="<?= e(url('knockout.php')) ?>"<?= nav_active('knockout.php') ?>><?= e(t('knockout')) ?></a>
          <a href="<?= e(url('teams.php')) ?>"<?= nav_active('teams.php') ?>><?= e(t('teams')) ?></a>
          <a href="<?= e(url('squads.php')) ?>"<?= nav_active('squads.php') ?>><?= e(t('squads')) ?></a>
          <a href="<?= e(url('stadiums.php')) ?>"<?= nav_active('stadiums.php') ?>><?= e(t('stadiums')) ?></a>
          <a href="<?= e(url('map.php')) ?>"<?= nav_active('map.php') ?>><?= e(t('host_map')) ?></a>
          <a href="<?= e(url('fanguide.php')) ?>"<?= nav_active('fanguide.php') ?>><?= e(t('fan_guide')) ?></a>
          <a href="<?= e(url('archive.php')) ?>"<?= nav_active('archive.php') ?>><?= e(t('archive')) ?></a>
        </div>
      </div>

      <div class="nav-group">
        <button type="button" class="nav-group-btn<?= nav_group_active(['stats.php','topscorers.php','bookings.php','referees.php']) ?>">
          <?= e(t('nav_numbers')) ?><i class="nav-caret">▾</i>
        </button>
        <div class="nav-drop">
          <a href="<?= e(url('stats.php')) ?>"<?= nav_active('stats.php') ?>><?= e(t('stats')) ?></a>
          <a href="<?= e(url('topscorers.php')) ?>"<?= nav_active('topscorers.php') ?>><?= e(t('top_scorers')) ?></a>
          <a href="<?= e(url('bookings.php')) ?>"<?= nav_active('bookings.php') ?>><?= e(t('bookings')) ?></a>
          <a href="<?= e(url('referees.php')) ?>"<?= nav_active('referees.php') ?>><?= e(t('referees')) ?></a>
        </div>
      </div>

      <div class="nav-group">
        <button type="button" class="nav-group-btn<?= nav_group_active(['predict.php','bracket.php','leaderboard.php','leagues.php','league.php','stickers.php','trivia.php']) ?>">
          <?= e(t('nav_play')) ?><i class="nav-caret">▾</i>
        </button>
        <div class="nav-drop">
          <a href="<?= e(url('predict.php')) ?>"<?= nav_active('predict.php') ?>><?= e(t('predict')) ?></a>
          <a href="<?= e(url('bracket.php')) ?>"<?= nav_active('bracket.php') ?>><?= e(t('bracket')) ?></a>
          <a href="<?= e(url('leaderboard.php')) ?>"<?= nav_active('leaderboard.php') ?>><?= e(t('leaderboard')) ?></a>
          <a href="<?= e(url('leagues.php')) ?>"<?= nav_active('leagues.php') ?>><?= e($lang === 'ar' ? 'المجلس' : 'My Leagues') ?></a>
          <a href="<?= e(url('stickers.php')) ?>"<?= nav_active('stickers.php') ?>><?= e(t('stickers')) ?></a>
          <a href="<?= e(url('trivia.php')) ?>"<?= nav_active('trivia.php') ?>><?= e(t('trivia')) ?></a>
        </div>
      </div>
<?php
      // رابط الحساب — يظهر فقط عندما يكون نظام الحسابات مفعّلاً.
      if (defined('DB_ENABLED') && DB_ENABLED):
          $authUser = class_exists('Auth') ? Auth::user() : null;
          if ($authUser !== null): ?>
      <a class="nav-user" href="<?= e(url('logout.php')) ?>">
        <span class="nav-user-name"><?= e($authUser['display_name']) ?></span>
        <span class="nav-user-sep">·</span>
        <span class="nav-user-action"><?= e(t('logout')) ?></span>
      </a>
<?php     else: ?>
      <a href="<?= e(url('login.php')) ?>"<?= nav_active('login.php') ?>><?= e(t('login')) ?></a>
<?php     endif;
      endif; ?>
      <a class="lang-switch" href="<?= e(url($current, ['lang' => $lang === 'ar' ? 'en' : 'ar'])) ?>">
        <?= $lang === 'ar' ? 'English' : 'العربية' ?>
      </a>
    </nav>
  </div>
</header>

<main class="wrap site-main">
