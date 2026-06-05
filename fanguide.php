<?php
/**
 * fanguide.php — دليل المشجّع/السفر لكأس العالم 2026 (أمريكا · كندا · المكسيك).
 * محتوى عام دقيق + روابط للمصادر الرسمية فقط. لا نختلق متطلبات تأشيرة لأي جنسية —
 * المتطلبات تختلف حسب الجنسية وتتغيّر، فنوجّه دائماً للجهات الرسمية.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');
$fr   = ($lang === 'fr');
$Lg   = fn(string $a, string $e, string $f = '') => $ar ? $a : ($fr ? ($f ?: $e) : $e);

$page_title = $Lg('دليل المشجّع', 'Fan Guide', 'Guide du fan');
$page_desc  = $Lg(
    'كل ما يحتاجه المشجّع لحضور كأس العالم 2026: الدخول، التنقّل، المدن، ومناطق المشجّعين — مع روابط رسمية.',
    'Everything a fan needs for the 2026 World Cup: entry, getting around, host cities and fan zones — with official links.',
    'Tout ce dont un fan a besoin pour la Coupe du Monde 2026 : entrée, déplacements, villes hôtes et zones de supporters — avec des liens officiels.'
);

// الدول المستضيفة الثلاث — حقائق ثابتة + برامج الدخول الرسمية + روابط حكومية موثوقة.
$countries = [
    [
        'flag' => 'us', 'name' => $Lg('الولايات المتحدة', 'United States', 'États-Unis'),
        'cities' => $Lg('11 مدينة مستضيفة', '11 host cities', '11 villes hôtes'),
        'currency' => 'USD ($)', 'language' => $Lg('الإنجليزية', 'English', 'Anglais'),
        'entry' => $Lg(
            'تأشيرة زيارة (B1/B2)، أو تصريح ESTA الإلكتروني لمواطني دول برنامج الإعفاء من التأشيرة.',
            'Visitor visa (B1/B2), or an electronic ESTA for Visa Waiver Program nationals.',
            'Visa de visiteur (B1/B2), ou ESTA électronique pour les ressortissants du programme d\'exemption de visa.'
        ),
        'links' => [
            ['travel.state.gov', 'https://travel.state.gov'],
            ['ESTA', 'https://esta.cbp.dhs.gov'],
        ],
    ],
    [
        'flag' => 'ca', 'name' => $Lg('كندا', 'Canada', 'Canada'),
        'cities' => $Lg('مدينتان (تورنتو وفانكوفر)', '2 cities (Toronto, Vancouver)', '2 villes (Toronto, Vancouver)'),
        'currency' => 'CAD ($)', 'language' => $Lg('الإنجليزية والفرنسية', 'English & French', 'Anglais et français'),
        'entry' => $Lg(
            'تأشيرة زيارة (TRV)، أو تصريح eTA الإلكتروني للمعفَين من التأشيرة القادمين جوّاً.',
            'Visitor visa (TRV), or an electronic eTA for visa-exempt travellers arriving by air.',
            'Visa de visiteur (TRV), ou eTA électronique pour les voyageurs arrivant par avion.'
        ),
        'links' => [
            ['canada.ca', 'https://www.canada.ca'],
        ],
    ],
    [
        'flag' => 'mx', 'name' => $Lg('المكسيك', 'Mexico', 'Mexique'),
        'cities' => $Lg('3 مدن (مكسيكو سيتي، غوادالاخارا، مونتيري)', '3 cities (Mexico City, Guadalajara, Monterrey)', '3 villes (Mexico, Guadalajara, Monterrey)'),
        'currency' => 'MXN ($)', 'language' => $Lg('الإسبانية', 'Spanish', 'Espagnol'),
        'entry' => $Lg(
            'تأشيرة زيارة، مع إعفاءات لبعض الجنسيات أو لحاملي تأشيرة أمريكية سارية. تحقّق من جهة الهجرة (INM).',
            'Visitor visa, with exemptions for some nationalities or holders of a valid US visa. Check immigration (INM).',
            'Visa de visiteur, avec exemptions pour certaines nationalités ou titulaires d\'un visa américain valide. Vérifiez auprès de l\'immigration (INM).'
        ),
        'links' => [
            ['gob.mx', 'https://www.gob.mx'],
        ],
    ],
];

$tips = $ar ? [
    ['🛂', 'افحص متطلبات الدخول مبكراً', 'البطولة في ثلاث دول — قد تحتاج إذن دخول لكل دولة تزورها. ابدأ قبل أشهر.'],
    ['✈️', 'خطّط حسب «عناقيد» المدن', 'المسافات شاسعة. اختر مبارياتك في مدن متقاربة لتقليل الطيران الداخلي.'],
    ['🕐', 'انتبه لفروق التوقيت', 'الدول الثلاث تمتدّ عبر عدّة مناطق زمنية — كل مواعيد المباريات في الموقع تتحوّل لتوقيتك تلقائياً.'],
    ['🌡️', 'الطقس صيفي (يونيو–يوليو)', 'حرارة مرتفعة في بعض المدن. خذ ماءً وواقي شمس، خاصةً للمباريات النهارية.'],
    ['💳', 'ثلاث عملات', 'دولار أمريكي، دولار كندي، بيزو مكسيكي. البطاقات مقبولة على نطاق واسع.'],
    ['🎉', 'مناطق المشجّعين', 'تقيم فيفا عادةً «مهرجان المشجّعين» المجاني في المدن المستضيفة — تُعلن أماكنه قرب البطولة.'],
] : ($fr ? [
    ['🛂', 'Vérifiez les conditions d\'entrée tôt', 'Trois pays — vous aurez peut-être besoin d\'une autorisation d\'entrée pour chaque pays visité. Commencez des mois à l\'avance.'],
    ['✈️', 'Planifiez par groupes de villes', 'Les distances sont énormes. Choisissez des matchs dans des villes proches pour réduire les vols intérieurs.'],
    ['🕐', 'Attention aux fuseaux horaires', 'Les trois pays s\'étendent sur plusieurs fuseaux horaires — tous les horaires sur ce site se convertissent automatiquement.'],
    ['🌡️', 'Climat estival (juin–juillet)', 'Il fait chaud dans certaines villes. Apportez de l\'eau et de la crème solaire, surtout pour les matchs de jour.'],
    ['💳', 'Trois devises', 'Dollar américain, dollar canadien, peso mexicain. Les cartes sont largement acceptées.'],
    ['🎉', 'Zones de supporters', 'La FIFA organise généralement des «Fan Festivals» gratuits dans les villes hôtes — les lieux sont annoncés avant le tournoi.'],
] : [
    ['🛂', 'Check entry rules early', 'Three countries — you may need separate entry permission for each. Start months ahead.'],
    ['✈️', 'Plan by city clusters', 'Distances are huge. Pick matches in nearby cities to cut down on internal flights.'],
    ['🕐', 'Mind the time zones', 'The three nations span several time zones — all match times on this site auto-convert to yours.'],
    ['🌡️', 'Summer weather (Jun–Jul)', 'It gets hot in some cities. Bring water and sunscreen, especially for daytime matches.'],
    ['💳', 'Three currencies', 'US dollar, Canadian dollar, Mexican peso. Cards are widely accepted.'],
    ['🎉', 'Fan zones', 'FIFA typically runs free "Fan Festival" zones in host cities — locations announced closer to kickoff.'],
]);

tpl('header');
?>

<div class="page-head">
  <h1>🧭 <?= e($page_title) ?></h1>
  <p class="muted"><?= e($page_desc) ?></p>
</div>

<div class="alert guide-alert">
  ⚠️ <?= e($Lg(
    'متطلبات الدخول والتأشيرات تختلف حسب جنسيتك وقد تتغيّر. هذه معلومات عامة فقط — تحقّق دائماً من السفارة أو الموقع الرسمي للدولة قبل الحجز.',
    'Entry and visa requirements vary by nationality and may change. This is general guidance only — always verify with the official embassy or government site before booking.',
    'Les conditions d\'entrée et de visa varient selon la nationalité et peuvent changer. Ceci est une indication générale — vérifiez toujours auprès de l\'ambassade ou du site officiel avant de réserver.'
  )) ?>
</div>

<section class="section">
  <h2 class="section-title">🌎 <?= e($Lg('الدول المستضيفة والدخول', 'Host countries & entry', 'Pays hôtes & entrée')) ?></h2>
  <div class="guide-grid">
    <?php foreach ($countries as $c): ?>
      <div class="guide-card">
        <div class="guide-card-head">
          <img class="flag" src="https://flagcdn.com/w80/<?= e($c['flag']) ?>.png" alt="" loading="lazy" width="40" height="30">
          <div>
            <span class="guide-name"><?= e($c['name']) ?></span>
            <span class="guide-cities"><?= e($c['cities']) ?></span>
          </div>
        </div>
        <ul class="guide-facts">
          <li><span><?= e($Lg('العملة', 'Currency', 'Devise')) ?></span><strong><?= e($c['currency']) ?></strong></li>
          <li><span><?= e($Lg('اللغة', 'Language', 'Langue')) ?></span><strong><?= e($c['language']) ?></strong></li>
        </ul>
        <p class="guide-entry"><?= e($c['entry']) ?></p>
        <div class="guide-links">
          <?php foreach ($c['links'] as [$lbl, $href]): ?>
            <a href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= e($lbl) ?> ↗</a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section">
  <h2 class="section-title">🧳 <?= e($Lg('نصائح عملية', 'Practical tips', 'Conseils pratiques')) ?></h2>
  <div class="guide-grid">
    <?php foreach ($tips as [$icon, $title, $body]): ?>
      <div class="guide-tip">
        <span class="guide-tip-icon"><?= $icon ?></span>
        <div>
          <span class="guide-tip-title"><?= e($title) ?></span>
          <p class="guide-tip-body"><?= e($body) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section guide-cta-sec">
  <h2 class="section-title">🏟️ <?= e($Lg('المدن والملاعب', 'Cities & stadiums', 'Villes & stades')) ?></h2>
  <p class="muted"><?= e($Lg(
    'استكشف المدن الـ16 المستضيفة وملاعبها، مع الصور وطريقة الوصول.',
    'Explore all 16 host cities and their stadiums, with photos and directions.',
    'Découvrez les 16 villes hôtes et leurs stades, avec photos et itinéraires.'
  )) ?></p>
  <a class="btn-cta" href="<?= e(url('stadiums.php')) ?>"><?= e($Lg('تصفّح الملاعب', 'Browse stadiums', 'Voir les stades')) ?> ›</a>
</section>

<?php tpl('footer'); ?>
