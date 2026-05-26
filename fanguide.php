<?php
/**
 * fanguide.php — دليل المشجّع/السفر لكأس العالم 2026 (أمريكا · كندا · المكسيك).
 * محتوى عام دقيق + روابط للمصادر الرسمية فقط. لا نختلق متطلبات تأشيرة لأي جنسية —
 * المتطلبات تختلف حسب الجنسية وتتغيّر، فنوجّه دائماً للجهات الرسمية.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');

$page_title = $ar ? 'دليل المشجّع' : 'Fan Guide';
$page_desc  = $ar
    ? 'كل ما يحتاجه المشجّع لحضور كأس العالم 2026: الدخول، التنقّل، المدن، ومناطق المشجّعين — مع روابط رسمية.'
    : 'Everything a fan needs for the 2026 World Cup: entry, getting around, host cities and fan zones — with official links.';

// الدول المستضيفة الثلاث — حقائق ثابتة + برامج الدخول الرسمية + روابط حكومية موثوقة.
$countries = [
    [
        'flag' => 'us', 'name' => $ar ? 'الولايات المتحدة' : 'United States',
        'cities' => $ar ? '11 مدينة مستضيفة' : '11 host cities',
        'currency' => 'USD ($)', 'language' => $ar ? 'الإنجليزية' : 'English',
        'entry' => $ar
            ? 'تأشيرة زيارة (B1/B2)، أو تصريح ESTA الإلكتروني لمواطني دول برنامج الإعفاء من التأشيرة.'
            : 'Visitor visa (B1/B2), or an electronic ESTA for Visa Waiver Program nationals.',
        'links' => [
            ['travel.state.gov', 'https://travel.state.gov'],
            ['ESTA', 'https://esta.cbp.dhs.gov'],
        ],
    ],
    [
        'flag' => 'ca', 'name' => $ar ? 'كندا' : 'Canada',
        'cities' => $ar ? 'مدينتان (تورنتو وفانكوفر)' : '2 cities (Toronto, Vancouver)',
        'currency' => 'CAD ($)', 'language' => $ar ? 'الإنجليزية والفرنسية' : 'English & French',
        'entry' => $ar
            ? 'تأشيرة زيارة (TRV)، أو تصريح eTA الإلكتروني للمعفَين من التأشيرة القادمين جوّاً.'
            : 'Visitor visa (TRV), or an electronic eTA for visa-exempt travellers arriving by air.',
        'links' => [
            ['canada.ca', 'https://www.canada.ca'],
        ],
    ],
    [
        'flag' => 'mx', 'name' => $ar ? 'المكسيك' : 'Mexico',
        'cities' => $ar ? '3 مدن (مكسيكو سيتي، غوادالاخارا، مونتيري)' : '3 cities (Mexico City, Guadalajara, Monterrey)',
        'currency' => 'MXN ($)', 'language' => $ar ? 'الإسبانية' : 'Spanish',
        'entry' => $ar
            ? 'تأشيرة زيارة، مع إعفاءات لبعض الجنسيات أو لحاملي تأشيرة أمريكية سارية. تحقّق من جهة الهجرة (INM).'
            : 'Visitor visa, with exemptions for some nationalities or holders of a valid US visa. Check immigration (INM).',
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
] : [
    ['🛂', 'Check entry rules early', 'Three countries — you may need separate entry permission for each. Start months ahead.'],
    ['✈️', 'Plan by city clusters', 'Distances are huge. Pick matches in nearby cities to cut down on internal flights.'],
    ['🕐', 'Mind the time zones', 'The three nations span several time zones — all match times on this site auto-convert to yours.'],
    ['🌡️', 'Summer weather (Jun–Jul)', 'It gets hot in some cities. Bring water and sunscreen, especially for daytime matches.'],
    ['💳', 'Three currencies', 'US dollar, Canadian dollar, Mexican peso. Cards are widely accepted.'],
    ['🎉', 'Fan zones', 'FIFA typically runs free "Fan Festival" zones in host cities — locations announced closer to kickoff.'],
];

tpl('header');
?>

<div class="page-head">
  <h1>🧭 <?= e($page_title) ?></h1>
  <p class="muted"><?= e($page_desc) ?></p>
</div>

<div class="alert guide-alert">
  ⚠️ <?= e($ar
    ? 'متطلبات الدخول والتأشيرات تختلف حسب جنسيتك وقد تتغيّر. هذه معلومات عامة فقط — تحقّق دائماً من السفارة أو الموقع الرسمي للدولة قبل الحجز.'
    : 'Entry and visa requirements vary by nationality and may change. This is general guidance only — always verify with the official embassy or government site before booking.') ?>
</div>

<section class="section">
  <h2 class="section-title">🌎 <?= e($ar ? 'الدول المستضيفة والدخول' : 'Host countries & entry') ?></h2>
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
          <li><span><?= e($ar ? 'العملة' : 'Currency') ?></span><strong><?= e($c['currency']) ?></strong></li>
          <li><span><?= e($ar ? 'اللغة' : 'Language') ?></span><strong><?= e($c['language']) ?></strong></li>
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
  <h2 class="section-title">🧳 <?= e($ar ? 'نصائح عملية' : 'Practical tips') ?></h2>
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
  <h2 class="section-title">🏟️ <?= e($ar ? 'المدن والملاعب' : 'Cities & stadiums') ?></h2>
  <p class="muted"><?= e($ar
    ? 'استكشف المدن الـ16 المستضيفة وملاعبها، مع الصور وطريقة الوصول.'
    : 'Explore all 16 host cities and their stadiums, with photos and directions.') ?></p>
  <a class="btn-cta" href="<?= e(url('stadiums.php')) ?>"><?= e($ar ? 'تصفّح الملاعب' : 'Browse stadiums') ?> ›</a>
</section>

<?php tpl('footer'); ?>
