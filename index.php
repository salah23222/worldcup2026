<?php
/**
 * index.php — الصفحة الرئيسية.
 * تعرض: بطل العالم (إن انتهت البطولة) / مباريات اليوم / النتائج / القادم.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

$page_title = t('home');
$today      = DataService::matchesOnDate();        // مباريات اليوم
$upcoming   = DataService::upcomingMatches(6);
$results    = DataService::latestResults(6);
$finalM     = Bracket::finalMatch();
$dataOk     = DataService::isOk();

// لحظة انطلاق البطولة = أبكر توقيت مباراة (للعدّاد التنازلي)
$kickoff = null;
foreach (DataService::allMatches() as $m) {
    $ts = DataService::matchTimestamp($m);
    if ($ts !== null && ($kickoff === null || $ts < $kickoff)) $kickoff = $ts;
}

tpl('header');
?>

<!-- ============ البطل (يظهر فقط بعد نهاية البطولة) ============ -->
<?php
$champion = null;
if ($finalM && isset($finalM['score']['ft'])) {
    [$fg1, $fg2] = $finalM['score']['ft'];
    $champion = ($fg1 >= $fg2) ? ($finalM['team1'] ?? '') : ($finalM['team2'] ?? '');
}
?>
<?php if ($champion && is_real_team($champion)): ?>
<section class="champion-banner">
  <div class="champion-glow"></div>
  <span class="champion-trophy">🏆</span>
  <p class="champion-label"><?= e(t('final_winner')) ?></p>
  <div class="champion-name">
    <?= flag_img($champion, 'w160') ?>
    <h2><?= e(team_name($champion)) ?></h2>
  </div>
</section>
<?php else: ?>
<!-- ============ الواجهة البطولية (Hero) ============ -->
<section class="hero">
  <div class="hero-bg"></div>
  <div class="hero-content">
    <p class="hero-kicker">FIFA WORLD CUP</p>
    <h1 class="hero-title">2026</h1>
    <div class="hero-hosts" aria-hidden="true">
      <img src="https://flagcdn.com/w80/ca.png" alt="" loading="eager" width="40" height="30">
      <img src="https://flagcdn.com/w80/mx.png" alt="" loading="eager" width="40" height="30">
      <img src="https://flagcdn.com/w80/us.png" alt="" loading="eager" width="40" height="30">
    </div>
    <p class="hero-sub"><?= e(t('hero_tagline')) ?></p>

    <div class="hero-cta">
      <a class="btn-cta" href="<?= e(url('predict.php')) ?>"><?= e(t('play_predict')) ?></a>
      <a class="btn-ghost" href="<?= e(url('matches.php')) ?>"><?= e(t('explore_matches')) ?> ›</a>
    </div>

    <?php if ($kickoff && time() < $kickoff): ?>
    <div class="countdown" id="countdown" data-target="<?= (int)$kickoff ?>">
      <p class="cd-label"><?= e(t('kickoff_in')) ?></p>
      <div class="cd-boxes">
        <div class="cd-box"><span class="cd-num" data-cd="d">—</span><span class="cd-lbl"><?= e(t('cd_days')) ?></span></div>
        <div class="cd-box"><span class="cd-num" data-cd="h">—</span><span class="cd-lbl"><?= e(t('cd_hours')) ?></span></div>
        <div class="cd-box"><span class="cd-num" data-cd="m">—</span><span class="cd-lbl"><?= e(t('cd_mins')) ?></span></div>
        <div class="cd-box"><span class="cd-num" data-cd="s">—</span><span class="cd-lbl"><?= e(t('cd_secs')) ?></span></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- ============ البطولة في أرقام (شريط بارز) ============ -->
<?php $lang = current_lang(); $ar = ($lang === 'ar'); ?>
<section class="stats-band" aria-label="<?= e(match($lang) { 'ar' => 'البطولة في أرقام', 'fr' => 'Le tournoi en chiffres', default => 'Tournament in numbers' }) ?>">
  <div class="sb-item"><span class="sb-num">48</span><span class="sb-lbl"><?= e(t('teams')) ?></span></div>
  <div class="sb-item"><span class="sb-num">104</span><span class="sb-lbl"><?= e(t('matches')) ?></span></div>
  <div class="sb-item"><span class="sb-num">16</span><span class="sb-lbl"><?= e(t('host_cities')) ?></span></div>
  <div class="sb-item"><span class="sb-num">3</span><span class="sb-lbl"><?= e(match($lang) { 'ar' => 'دول مضيفة', 'fr' => 'Pays hôtes', default => 'Host nations' }) ?></span></div>
</section>

<!-- ============ تفاعل مع المونديال (إبراز الميزات) ============ -->
<section class="engage">
  <div class="engage-head">
    <h2><?= e(t('engage_title')) ?></h2>
    <p class="muted"><?= e(t('engage_sub')) ?></p>
  </div>
  <div class="engage-grid">
    <?php
    $features = [
        ['predict.php',     '🎯', t('predict'),     t('f_predict')],
        ['bracket.php',     '🏆', t('bracket'),     t('f_bracket')],
        ['stickers.php',    '🃏', t('stickers'),    t('f_stickers')],
        ['trivia.php',      '❓', t('trivia'),      t('f_trivia')],
        ['leaderboard.php', '🏅', t('leaderboard'), t('f_leaderboard')],
        ['stats.php',       '📊', t('stats'),       t('f_stats')],
        ['topscorers.php',  '⚽', t('top_scorers'), t('f_scorers')],
    ];
    foreach ($features as [$page, $icon, $title, $desc]): ?>
      <a class="engage-card" href="<?= e(url($page)) ?>">
        <span class="engage-icon"><?= $icon ?></span>
        <span class="engage-body">
          <span class="engage-title"><?= e($title) ?></span>
          <span class="engage-desc"><?= e($desc) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!$dataOk): ?>
  <div class="alert"><?= e(t('no_data')) ?></div>
<?php endif; ?>

<!-- ============ مباريات اليوم ============ -->
<section class="section" id="today" data-autorefresh="1">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(t('today_matches')) ?></h2>
    <span class="section-date"><?= local_dt(time(), 'date') ?></span>
  </div>
  <?php if ($today): ?>
    <div class="match-grid">
      <?php foreach ($today as $m) render_match_card($m); ?>
    </div>
  <?php else: ?>
    <p class="empty-note"><?= e(t('no_matches_today')) ?></p>
  <?php endif; ?>
</section>

<!-- ============ آخر النتائج ============ -->
<?php if ($results): ?>
<section class="section">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(t('latest_results')) ?></h2>
    <a class="section-link" href="<?= e(url('matches.php', ['status' => 'finished'])) ?>">
      <?= e(t('all')) ?> ›
    </a>
  </div>
  <div class="match-grid">
    <?php foreach ($results as $m) render_match_card($m); ?>
  </div>
  <div class="more-wrap">
    <a class="btn-ghost" href="<?= e(url('matches.php', ['status' => 'finished'])) ?>"><?= e(t('more_matches')) ?> ›</a>
  </div>
</section>
<?php endif; ?>

<!-- ============ المباريات القادمة ============ -->
<?php if ($upcoming): ?>
<section class="section">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(t('upcoming')) ?></h2>
    <a class="section-link" href="<?= e(url('matches.php')) ?>"><?= e(t('all')) ?> ›</a>
  </div>
  <div class="match-grid">
    <?php foreach ($upcoming as $m) render_match_card($m); ?>
  </div>
  <div class="more-wrap">
    <a class="btn-ghost" href="<?= e(url('matches.php')) ?>"><?= e(t('more_matches')) ?> ›</a>
  </div>
</section>
<?php endif; ?>

<!-- ============ آخر الأخبار ============ -->
<?php $news = News::latest(5); ?>
<?php if ($news): ?>
<section class="section">
  <div class="section-head">
    <h2><span class="section-bar"></span>📰 <?= e(t('latest_news')) ?></h2>
    <a class="section-link" href="<?= e(url('news.php')) ?>"><?= e(t('news_more')) ?> ›</a>
  </div>
  <div class="news-list">
    <?php foreach ($news as $it) render_news_item($it); ?>
  </div>
</section>
<?php endif; ?>

<!-- ============ روابط سريعة ============ -->
<section class="quick-links">
  <a href="<?= e(url('groups.php')) ?>" class="ql-card">
    <span class="ql-icon">▦</span><span><?= e(t('groups')) ?></span>
  </a>
  <a href="<?= e(url('knockout.php')) ?>" class="ql-card">
    <span class="ql-icon">🏆</span><span><?= e(t('knockout')) ?></span>
  </a>
  <a href="<?= e(url('teams.php')) ?>" class="ql-card">
    <span class="ql-icon">⚑</span><span><?= e(t('teams')) ?></span>
  </a>
  <a href="<?= e(url('stadiums.php')) ?>" class="ql-card">
    <span class="ql-icon">🏟</span><span><?= e(t('stadiums')) ?></span>
  </a>
</section>

<!-- ============ روابط رسمية ============ -->
<?php
$officialLinks = [
  ['🌐', match($lang) { 'ar' => 'الموقع الرسمي (FIFA)', 'fr' => 'Site officiel (FIFA)', default => 'Official FIFA site' }, 'https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026', true],
  ['🎟️', match($lang) { 'ar' => 'التذاكر', 'fr' => 'Billets', default => 'Tickets' }, 'https://www.fifa.com/tickets', true],
  ['🗺️', match($lang) { 'ar' => 'خريطة المدن المضيفة', 'fr' => 'Carte des villes hôtes', default => 'Host cities map' }, url('map.php'), false],
  ['📅', match($lang) { 'ar' => 'جدول المباريات', 'fr' => 'Calendrier des matchs', default => 'Match schedule' }, url('matches.php'), false],
  ['🏟️', match($lang) { 'ar' => 'الملاعب', 'fr' => 'Stades', default => 'Stadiums' }, url('stadiums.php'), false],
  ['🧭', match($lang) { 'ar' => 'دليل المشجّع', 'fr' => 'Guide du fan', default => 'Fan guide' }, url('fanguide.php'), false],
];
?>
<section class="section official-links">
  <div class="section-head">
    <h2><span class="section-bar"></span><?= e(match($lang) { 'ar' => 'روابط رسمية ومفيدة', 'fr' => 'Liens officiels & utiles', default => 'Official & useful links' }) ?></h2>
  </div>
  <div class="official-grid">
    <?php foreach ($officialLinks as [$icon, $label, $href, $external]): ?>
      <a class="official-card" href="<?= e($href) ?>"<?= $external ? ' target="_blank" rel="noopener nofollow"' : '' ?>>
        <span class="official-icon"><?= $icon ?></span>
        <span class="official-label"><?= e($label) ?></span>
        <?php if ($external): ?><span class="official-ext" aria-hidden="true">↗</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php tpl('footer'); ?>
