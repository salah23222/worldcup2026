<?php
/**
 * map.php — خريطة المدن المستضيفة الـ16 (خريطة حقيقية تفاعلية).
 * تستخدم Leaflet (مستضافة محلياً) مع بلاطات CARTO الداكنة — كل مدينة علامة
 * في موقعها الجغرافي الحقيقي تنقلك لملعبها. لا scripts خارجية (آمنة CSP).
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');
$st   = Stadiums::all();

$page_title = $ar ? 'خريطة المدن المستضيفة' : 'Host Cities Map';
$page_desc  = $ar ? 'المدن الـ16 المستضيفة لكأس العالم 2026 عبر كندا والمكسيك والولايات المتحدة على خريطة تفاعلية — اضغط مدينة لتفتح ملعبها.'
                  : 'The 16 host cities of the 2026 World Cup across Canada, Mexico and the USA on an interactive map — tap a city for its stadium.';

$countries = [
    'ca' => $ar ? 'كندا' : 'Canada',
    'us' => $ar ? 'الولايات المتحدة' : 'United States',
    'mx' => $ar ? 'المكسيك' : 'Mexico',
];
$byCountry = ['ca' => [], 'us' => [], 'mx' => []];
$points = [];
foreach ($st as $s) {
    $byCountry[$s['country']][] = $s;
    $points[] = [
        'lat'  => (float)$s['lat'], 'lng' => (float)$s['lng'],
        'city' => $ar ? $s['cityAr'] : $s['cityEn'],
        'name' => $ar ? $s['nameAr'] : $s['nameEn'],
        'cc'   => $s['country'],
        'url'  => url('stadium.php', ['id' => (int)$s['id']]),
    ];
}
$viewLabel = $ar ? 'عرض الملعب' : 'View stadium';

tpl('header');
?>
<link rel="stylesheet" href="<?= e(rtrim(SITE_URL, '/')) ?>/assets/vendor/leaflet/leaflet.css">

<div class="page-head">
  <h1>🗺️ <?= e($page_title) ?></h1>
  <p class="muted"><?= e($page_desc) ?></p>
</div>

<div class="hostmap-legend">
  <span class="hm-leg hm-ca"><?= e($countries['ca']) ?> (<?= count($byCountry['ca']) ?>)</span>
  <span class="hm-leg hm-us"><?= e($countries['us']) ?> (<?= count($byCountry['us']) ?>)</span>
  <span class="hm-leg hm-mx"><?= e($countries['mx']) ?> (<?= count($byCountry['mx']) ?>)</span>
</div>

<div id="hostmap" role="application" aria-label="<?= e($page_desc) ?>"></div>

<!-- قائمة المدن مجمّعة حسب الدولة (تعمل على الجوال وقارئات الشاشة) -->
<div class="hostmap-cities">
  <?php foreach ($countries as $cc => $cname): if (!$byCountry[$cc]) continue; ?>
    <section class="hm-city-col">
      <h2 class="hm-city-head">
        <img class="flag" src="https://flagcdn.com/w40/<?= e($cc) ?>.png" alt="" width="28" height="21">
        <?= e($cname) ?>
      </h2>
      <ul class="hm-city-list">
        <?php foreach ($byCountry[$cc] as $s):
          $city = $ar ? $s['cityAr'] : $s['cityEn'];
          $name = $ar ? $s['nameAr'] : $s['nameEn'];
        ?>
          <li>
            <a href="<?= e(url('stadium.php', ['id' => (int)$s['id']])) ?>">
              <strong><?= e($city) ?></strong>
              <span class="hm-city-stadium"><?= e($name) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>

<div class="more-wrap">
  <a class="btn-ghost" href="<?= e(url('fanguide.php')) ?>"><?= e(t('fan_guide')) ?> ›</a>
  <a class="btn-cta" href="<?= e(url('stadiums.php')) ?>"><?= e(t('stadiums')) ?> ›</a>
</div>

<script>
window.HOSTMAP = <?= json_encode($points, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.HOSTMAP_VIEW = <?= json_encode($viewLabel, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= e(rtrim(SITE_URL, '/')) ?>/assets/vendor/leaflet/leaflet.js"></script>
<script>
(function () {
  if (typeof L === 'undefined' || !document.getElementById('hostmap')) return;
  var pts = window.HOSTMAP || [];
  var colors = { ca: '#ff5a5f', us: '#4aa3ff', mx: '#2bd576' };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

  var map = L.map('hostmap', { scrollWheelZoom: false, minZoom: 3, maxZoom: 9 });
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', {
    subdomains: 'abcd', maxZoom: 9,
    attribution: '&copy; OpenStreetMap &copy; CARTO'
  }).addTo(map);

  var latlngs = [];
  pts.forEach(function (p) {
    var mk = L.circleMarker([p.lat, p.lng], {
      radius: 8, color: '#fff', weight: 2,
      fillColor: colors[p.cc] || '#fff', fillOpacity: 1
    }).addTo(map);
    mk.bindTooltip(esc(p.city), { direction: 'top' });
    mk.bindPopup('<strong>' + esc(p.city) + '</strong><br>' + esc(p.name) +
      '<br><a href="' + p.url + '">' + esc(window.HOSTMAP_VIEW) + '</a>');
    latlngs.push([p.lat, p.lng]);
  });
  if (latlngs.length) map.fitBounds(latlngs, { padding: [30, 30] });
})();
</script>

<?php tpl('footer'); ?>
