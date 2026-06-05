<?php
/**
 * referee.php — البطاقة التفصيلية لحكم واحد.
 * يُعرّف الحكم بترتيبه في القائمة (?i=). يعرض صورة + نبذة من ويكيبيديا
 * (best-effort مُخزّنة) + بياناته الثابتة + مبارياته في البطولة (تظهر عند التعيين).
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';

$lang = current_lang();
$i    = isset($_GET['i']) ? (int)$_GET['i'] : -1;
$ref  = $i >= 0 ? Referees::byIndex($i) : null;

// حكم غير موجود → ارجع لقائمة الحكّام
if ($ref === null || trim((string)($ref['name'] ?? '')) === '') {
    header('Location: ' . url('referees.php'));
    exit;
}

$name    = trim((string)$ref['name']);
$country = $lang === 'ar'
         ? ($ref['country_ar'] ?? $ref['country_en'] ?? '')
         : ($ref['country_en'] ?? $ref['country_ar'] ?? '');
$flag    = strtolower(trim((string)($ref['flag'] ?? '')));
$role    = $ref['role'] ?? 'referee';

$roleLabels = [
    'referee'   => match($lang) { 'ar' => 'حكم ساحة', 'fr' => 'Arbitre principal', default => 'Referee' },
    'assistant' => match($lang) { 'ar' => 'حكم مساعد', 'fr' => 'Arbitre assistant', default => 'Assistant Referee' },
    'var'       => match($lang) { 'ar' => 'حكم فيديو (VAR)', 'fr' => 'Arbitre vidéo (VAR)', default => 'Video Match Official (VAR)' },
];
$roleLabel = $roleLabels[$role] ?? $roleLabels['referee'];

$profile = Referees::profile($name, $lang);
$matches = Referees::matchesFor($name);

$L = [
    'about'      => t('about_stadium'),
    'no_profile' => match($lang) { 'ar' => 'لا تتوفّر نبذة تفصيلية لهذا الحكم بعد — تُضاف عند توفّرها.', 'fr' => "Un profil détaillé n'est pas encore disponible — il sera ajouté dès qu'il sera disponible.", default => "A detailed profile isn't available yet — added as it becomes available." },
    'source'     => match($lang) { 'ar' => 'المصدر: ويكيبيديا', 'fr' => 'Source : Wikipédia', default => 'Source: Wikipedia' },
    'read_more'  => t('read_more'),
    'role'       => match($lang) { 'ar' => 'الاختصاص', 'fr' => 'Spécialité', default => 'Role' },
    'his_matches'=> match($lang) { 'ar' => 'مبارياته في البطولة', 'fr' => 'Ses matchs dans le tournoi', default => 'Tournament matches' },
    'no_matches' => match($lang) { 'ar' => 'لم تُسنَد إليه مباريات بعد — تظهر بمجرّد إعلان التعيينات.', 'fr' => "Aucun match assigné pour l'instant — affiché une fois les nominations annoncées.", default => 'No matches assigned yet — shown once appointments are announced.' },
    'back'       => match($lang) { 'ar' => 'كل الحكّام', 'fr' => 'Tous les arbitres', default => 'All referees' },
];

$page_title = $name;
$page_desc  = $name . ' — ' . $roleLabel . ' · ' . $country;
if (!empty($profile['photo'])) $page_image = $profile['photo'];
tpl('header');
?>

<a class="back-link" href="<?= e(url('referees.php')) ?>">&larr; <?= e($L['back']) ?></a>

<div class="ref-profile">
  <div class="ref-profile-photo">
    <?php if (!empty($profile['photo'])): ?>
      <img src="<?= e($profile['photo']) ?>" alt="<?= e($name) ?>" loading="lazy">
    <?php elseif ($flag !== ''): ?>
      <span class="ref-profile-flag"><img src="https://flagcdn.com/w160/<?= e($flag) ?>.png" alt="" loading="lazy"></span>
    <?php else: ?>
      <span class="ref-profile-empty">🧑‍⚖️</span>
    <?php endif; ?>
  </div>
  <div class="ref-profile-info">
    <h1><?= e($name) ?></h1>
    <p class="ref-profile-country">
      <?php if ($flag !== ''): ?>
        <img class="flag" src="https://flagcdn.com/w40/<?= e($flag) ?>.png" alt="" loading="lazy" width="28" height="21">
      <?php endif; ?>
      <span><?= e($country) ?></span>
    </p>
    <span class="ref-role-badge ref-role-<?= e($role) ?>"><?= e($roleLabel) ?></span>
  </div>
</div>

<section class="section">
  <h2 class="section-title"><?= e($L['about']) ?></h2>
  <?php if (!empty($profile['bio'])): ?>
    <p class="ref-bio"><?= e($profile['bio']) ?></p>
    <?php if (!empty($profile['url'])): ?>
      <p class="ref-bio-src">
        <a href="<?= e($profile['url']) ?>" target="_blank" rel="noopener">
          <?= e($L['read_more']) ?> ↗
        </a>
        <span class="muted"> · <?= e($L['source']) ?></span>
      </p>
    <?php endif; ?>
  <?php else: ?>
    <p class="empty-note"><?= e($L['no_profile']) ?></p>
  <?php endif; ?>
</section>

<section class="section">
  <h2 class="section-title"><?= e($L['his_matches']) ?></h2>
  <?php if ($matches): ?>
    <div class="match-grid">
      <?php foreach ($matches as $m) render_match_card($m); ?>
    </div>
  <?php else: ?>
    <p class="empty-note"><?= e($L['no_matches']) ?></p>
  <?php endif; ?>
</section>

<?php tpl('footer'); ?>
