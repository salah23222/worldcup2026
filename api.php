<?php
/**
 * api.php — صفحة مرجع الـAPI العامّة (للمطوّرين).
 * توثّق api/data.php وكل الـactions + football.txt. صفحة قائمة بذاتها
 * على wcup2026.org (نطاقنا → سيو لنا)، بنمط الموقع وثلاثيّة اللغة.
 */
require __DIR__ . '/includes/bootstrap.php';

$L  = current_lang();
$tx = fn(string $ar, string $en, ?string $fr = null) => $L === 'ar' ? $ar : ($L === 'fr' ? ($fr ?? $en) : $en);

$page_title = $tx('واجهة برمجة التطبيقات (API)', 'Developer API', 'API développeur');
$page_desc  = $tx(
    'واجهة JSON مجانيّة وعامّة لكأس العالم 2026: المباريات، النتائج المباشرة، أهداف وبطاقات المباراة، إحصائيات الفيفا الرسميّة، الهدّافون، وترتيب المجموعات. بلا مفتاح وبلا تسجيل.',
    'Free, public JSON API for the FIFA World Cup 2026: matches, live scores, goals & cards, official FIFA stats, top scorers and group standings. No key, no signup.',
    'API JSON gratuite et publique pour la Coupe du Monde 2026 : matchs, scores en direct, buts, statistiques officielles FIFA, buteurs et classements. Sans clé.'
);
$page_keywords = $tx(
    'API كأس العالم 2026, واجهة برمجة كأس العالم, JSON API كرة قدم, نتائج مباشرة API, هدافون API, ترتيب المجموعات API, wcup2026 API',
    'World Cup 2026 API, FIFA World Cup JSON API, free football API, soccer API, live scores API, top scorers API, standings API, no key football api, wcup2026 API'
);

// رابط الـAPI على نفس النطاق (آمن مع CSP connect-src 'self' → العرض الحيّ يعمل)
$apiBase = rtrim(SITE_URL, '/') . '/api/data.php';

tpl('header');
?>

<!-- بيانات منظّمة: WebAPI (تساعد محركات البحث على فهم الصفحة كواجهة برمجيّة) -->
<script type="application/ld+json">
<?= json_encode([
  '@context'    => 'https://schema.org',
  '@type'       => 'WebAPI',
  'name'        => 'World Cup 2026 Public JSON API',
  'description' => 'Free, public, CORS-enabled JSON API for the FIFA World Cup 2026: matches, live scores, match details, top scorers, standings and per-player physical data.',
  'url'         => canonical_url(),
  'documentation' => $apiBase,
  'provider'    => ['@type' => 'Organization', 'name' => 'wcup2026.org', 'url' => rtrim(SITE_URL, '/')],
  'isAccessibleForFree' => true,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<style>
/* ===== صفحة الـAPI — أنماط مُنطاقة (api-*) ===== */
.api-hero{text-align:center;padding:10px 0 6px}
.api-badge{display:inline-flex;gap:8px;align-items:center;background:rgba(255,255,255,.06);
  border:1px solid rgba(255,255,255,.14);padding:6px 14px;border-radius:999px;font-size:13px;color:var(--muted,#9aa6c4);margin-bottom:16px}
.api-badge .dot{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18)}
.api-hero h1{font-size:clamp(26px,5vw,40px);margin:.2em 0 .3em;line-height:1.18}
.api-hero p.sub{color:var(--muted,#9aa6c4);max-width:680px;margin:0 auto;font-size:17px}
.api-pills{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:18px}
.api-pill{font-size:13px;color:var(--muted,#9aa6c4);background:rgba(255,255,255,.05);
  border:1px solid rgba(255,255,255,.12);padding:6px 12px;border-radius:999px}
.api-sec{margin:34px 0;padding-top:26px;border-top:1px solid rgba(255,255,255,.10)}
.api-sec h2{font-size:22px;margin:0 0 4px}
.api-sec .lead{color:var(--muted,#9aa6c4);margin:0 0 16px}
.api-codebar{display:flex;align-items:center;gap:10px;background:#0b1120;border:1px solid rgba(255,255,255,.14);
  border-radius:12px;padding:13px 16px;overflow:auto}
.api-codebar code{color:#7dd3fc;font-size:15px;white-space:nowrap;font-family:ui-monospace,Menlo,Consolas,monospace}
.api-table{width:100%;border-collapse:collapse;background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.12);border-radius:12px;overflow:hidden}
.api-table th,.api-table td{text-align:start;padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.10);font-size:14.5px;vertical-align:top}
.api-table th{color:var(--muted,#9aa6c4);font-weight:700;background:rgba(255,255,255,.04);font-size:12.5px;text-transform:uppercase;letter-spacing:.04em}
.api-table tr:last-child td{border-bottom:0}
.api-table td code{color:#7dd3fc;background:#0b1120;padding:2px 7px;border-radius:6px;font-size:13px;white-space:nowrap;font-family:ui-monospace,Menlo,Consolas,monospace}
.api-req{color:#f59e0b}
.api-pre{background:#0b1120;border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:15px 18px;
  overflow:auto;font-size:13.5px;margin:12px 0;direction:ltr;text-align:left;
  font-family:ui-monospace,Menlo,Consolas,monospace;line-height:1.7;color:#e2e8f0}
.api-pre .k{color:#7dd3fc}.api-pre .s{color:#86efac}.api-pre .n{color:#fca5a5}.api-pre .c{color:#64748b}
.api-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.api-grid2{grid-template-columns:1fr}.api-codebar code{font-size:13px}}
.api-demo{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:16px}
.api-demo h3{margin:0 0 4px;font-size:15px;font-family:ui-monospace,Menlo,Consolas,monospace}
.api-demo .out{margin-top:10px;background:#0b1120;border:1px solid rgba(255,255,255,.12);border-radius:10px;
  padding:12px;min-height:54px;font-size:13px;color:#86efac;white-space:pre-wrap;direction:ltr;text-align:left;
  font-family:ui-monospace,Menlo,Consolas,monospace}
.api-h3{font-size:16px;margin:16px 0 0}
</style>

<div class="api-hero">
  <span class="api-badge"><span class="dot"></span> <?= e($tx('حيّ · مجاني · بلا مفتاح', 'Live · Free · No API key', 'En direct · Gratuit · Sans clé')) ?></span>
  <h1><?= e($tx('واجهة كأس العالم 2026 — JSON API', 'World Cup 2026 — JSON API', 'Coupe du Monde 2026 — API JSON')) ?></h1>
  <p class="sub"><?= e($page_desc) ?></p>
  <div class="api-pills">
    <span class="api-pill">⚡ CORS: <code>*</code></span>
    <span class="api-pill">📦 application/json</span>
    <span class="api-pill">🕐 <?= e($tx('كاش ~60 ثانية', '~60s cache', 'cache ~60s')) ?></span>
    <span class="api-pill">🆓 <?= e($tx('بلا تسجيل', 'No signup', 'Sans inscription')) ?></span>
  </div>
</div>

<section class="api-sec">
  <h2><?= e($tx('الرابط الأساسي', 'Base URL', 'URL de base')) ?></h2>
  <p class="lead"><?= e($tx('كل طلب هو GET بسيط — بلا ترويسات وبلا توكن.', 'Every call is a simple GET. No headers, no token.', 'Chaque appel est un simple GET. Sans en-tête, sans jeton.')) ?></p>
  <div class="api-codebar"><code><?= e($apiBase) ?>?action=&lt;ACTION&gt;</code></div>
  <pre class="api-pre"><span class="c">// <?= e($tx('كل رد ضمن غلاف موحّد', 'Every response is an envelope', 'Chaque réponse est une enveloppe')) ?></span>
{ <span class="k">"ok"</span>: <span class="n">true</span>, <span class="k">"action"</span>: <span class="s">"today"</span>, <span class="k">"updated"</span>: <span class="n">1718600000</span>, <span class="k">"matches"</span>: [ … ] }</pre>
</section>

<section class="api-sec">
  <h2><?= e($tx('نقاط النهاية (Endpoints)', 'Endpoints', 'Points de terminaison')) ?></h2>
  <p class="lead"><?= e($tx('مرّر معامل', 'Pass an', 'Passez un')) ?> <code style="color:#7dd3fc">action</code>. <span class="api-req"><?= e($tx('المعاملات بالبرتقالي إلزاميّة.', 'Amber params are required.', 'Les paramètres oranges sont requis.')) ?></span></p>
  <table class="api-table">
    <tr>
      <th><?= e($tx('الإجراء', 'Action', 'Action')) ?></th>
      <th><?= e($tx('المعاملات', 'Params', 'Paramètres')) ?></th>
      <th><?= e($tx('يُرجِع', 'Returns', 'Retourne')) ?></th>
    </tr>
    <tr><td><code>today</code></td><td>—</td><td><?= e($tx('مباريات اليوم', 'Matches scheduled today', "Matchs du jour")) ?></td></tr>
    <tr><td><code>live</code></td><td>—</td><td><?= e($tx('المباريات المباشرة الآن', 'Matches in play right now', 'Matchs en cours')) ?></td></tr>
    <tr><td><code>upcoming</code></td><td>limit</td><td><?= e($tx('المباريات القادمة', 'Upcoming fixtures', 'Prochains matchs')) ?></td></tr>
    <tr><td><code>results</code></td><td>limit</td><td><?= e($tx('المباريات المنتهية بنتائجها', 'Finished matches with scores', 'Matchs terminés avec scores')) ?></td></tr>
    <tr><td><code>all</code></td><td>—</td><td><?= e($tx('كل الـ104 مباراة', 'All 104 matches', 'Les 104 matchs')) ?></td></tr>
    <tr><td><code>match</code></td><td><span class="api-req">id</span></td><td><strong><?= e($tx('مباراة واحدة بكامل التفاصيل', 'One match, full detail', 'Un match, détail complet')) ?></strong> — <?= e($tx('أهداف، بطاقات، إحصائيات الفيفا', 'goals, cards, FIFA stats', 'buts, cartons, stats FIFA')) ?></td></tr>
    <tr><td><code>scorers</code></td><td>—</td><td><?= e($tx('الهدّافون (سباق الحذاء الذهبي)', 'Top scorers / golden-boot race', 'Buteurs / course au Soulier d’or')) ?></td></tr>
    <tr><td><code>standings</code></td><td>—</td><td><?= e($tx('ترتيب المجموعات الـ12', 'All 12 group tables', 'Les 12 classements de groupes')) ?></td></tr>
    <tr><td><code>group</code></td><td><span class="api-req">g</span></td><td><?= e($tx('ترتيب مجموعة واحدة', "One group's table", 'Classement d’un groupe')) ?> (<code>Group A</code>)</td></tr>
    <tr><td><code>physical</code></td><td>—</td><td><?= e($tx('بيانات الجري لكل لاعب من تقارير الفيفا', 'Per-player running data from FIFA reports', 'Données de course par joueur (rapports FIFA)')) ?></td></tr>
  </table>
</section>

<section class="api-sec">
  <h2><?= e($tx('بداية سريعة', 'Quick start', 'Démarrage rapide')) ?></h2>
  <p class="lead"><?= e($tx('استخدمها مباشرةً من المتصفّح أو تطبيق جوّال (React Native / Flutter) أو أي خادم.', 'Use it straight from a browser, mobile app (React Native / Flutter) or any backend.', 'Utilisez-la depuis un navigateur, une app mobile ou tout backend.')) ?></p>
  <div class="api-grid2">
    <pre class="api-pre"><span class="c"># curl</span>
curl <span class="s">"<?= e($apiBase) ?>?action=results"</span>
curl <span class="s">"<?= e($apiBase) ?>?action=match&id=12"</span>
curl <span class="s">"<?= e($apiBase) ?>?action=scorers"</span></pre>
    <pre class="api-pre"><span class="c">// JavaScript</span>
<span class="k">const</span> res  = <span class="k">await</span> fetch(
  <span class="s">'<?= e($apiBase) ?>?action=scorers'</span>);
<span class="k">const</span> data = <span class="k">await</span> res.json();
console.log(data.scorers);
<span class="c">// [{ name, team, goals }, …]</span></pre>
  </div>
</section>

<section class="api-sec">
  <h2><?= e($tx('جرّبها الآن', 'Try it live', 'Essayez en direct')) ?></h2>
  <p class="lead"><?= e($tx('هذا الصندوق يستدعي الـAPI الحقيقي من متصفّحك الآن.', 'This box calls the real API from your browser right now.', 'Cette boîte appelle l’API réelle depuis votre navigateur.')) ?></p>
  <div class="api-demo">
    <h3>GET <code style="color:#7dd3fc">?action=scorers</code> → <?= e($tx('أعلى 5', 'top 5', 'top 5')) ?></h3>
    <div class="out" id="apiOut"><?= e($tx('جارٍ التحميل…', 'Loading…', 'Chargement…')) ?></div>
  </div>
</section>

<section class="api-sec">
  <h2><?= e($tx('بنية الردود', 'Response shapes', 'Structure des réponses')) ?></h2>

  <h3 class="api-h3"><?= e($tx('كائن المباراة', 'Match object', 'Objet match')) ?></h3>
  <pre class="api-pre">{
  <span class="k">"id"</span>: <span class="n">12</span>, <span class="k">"round"</span>: <span class="s">"Matchday 7"</span>, <span class="k">"group"</span>: <span class="s">"Group C"</span>,
  <span class="k">"team1"</span>: <span class="s">"Brazil"</span>,   <span class="k">"team2"</span>: <span class="s">"Morocco"</span>,
  <span class="k">"team1_ar"</span>: <span class="s">"البرازيل"</span>, <span class="k">"team2_ar"</span>: <span class="s">"المغرب"</span>,
  <span class="k">"flag1"</span>: <span class="s">"https://flagcdn.com/w80/br.png"</span>,
  <span class="k">"status"</span>: <span class="s">"finished"</span>,   <span class="c">// upcoming | live | finished</span>
  <span class="k">"score"</span>: [<span class="n">1</span>, <span class="n">1</span>],       <span class="c">// [team1, team2] or null</span>
  <span class="k">"live_minute"</span>: <span class="n">null</span>,
  <span class="k">"date"</span>: <span class="s">"2026-06-14"</span>, <span class="k">"time"</span>: <span class="s">"20:00"</span>, <span class="k">"datetime"</span>: <span class="n">1718390000</span>,
  <span class="k">"ground"</span>: <span class="s">"Dallas (Arlington)"</span>
}</pre>

  <h3 class="api-h3"><code style="color:#7dd3fc">?action=match&id=N</code> <?= e($tx('يضيف تفاصيل', 'adds detail', 'ajoute des détails')) ?></h3>
  <pre class="api-pre">{
  <span class="k">"ht"</span>: [<span class="n">0</span>, <span class="n">1</span>],                              <span class="c">// half-time</span>
  <span class="k">"goals1"</span>: [{ <span class="k">"name"</span>:<span class="s">"Vinícius Júnior"</span>, <span class="k">"minute"</span>:<span class="s">"32"</span>, <span class="k">"penalty"</span>:<span class="n">true</span> }],
  <span class="k">"goals2"</span>: [ … ],
  <span class="k">"cards"</span>:  [{ <span class="k">"team"</span>:<span class="n">1</span>, <span class="k">"minute"</span>:<span class="n">61</span>, <span class="k">"name"</span>:<span class="s">"…"</span>, <span class="k">"type"</span>:<span class="s">"yellow"</span> }],
  <span class="k">"stats"</span>:  { <span class="k">"possession"</span>:[…], <span class="k">"shots"</span>:[…], <span class="k">"xg"</span>:[…] }
}</pre>

  <div class="api-grid2">
    <div>
      <h3 class="api-h3"><?= e($tx('هدّاف', 'Scorer', 'Buteur')) ?></h3>
      <pre class="api-pre">{ <span class="k">"name"</span>:<span class="s">"Lionel Messi"</span>,
  <span class="k">"team"</span>:<span class="s">"Argentina"</span>,
  <span class="k">"goals"</span>:<span class="n">3</span> }</pre>
    </div>
    <div>
      <h3 class="api-h3"><?= e($tx('صفّ ترتيب', 'Standings row', 'Ligne de classement')) ?></h3>
      <pre class="api-pre">{ <span class="k">"team"</span>:<span class="s">"Mexico"</span>, <span class="k">"pts"</span>:<span class="n">3</span>,
  <span class="k">"played"</span>:<span class="n">1</span>, <span class="k">"w"</span>:<span class="n">1</span>, <span class="k">"d"</span>:<span class="n">0</span>, <span class="k">"l"</span>:<span class="n">0</span>,
  <span class="k">"gf"</span>:<span class="n">2</span>, <span class="k">"ga"</span>:<span class="n">0</span>, <span class="k">"gd"</span>:<span class="n">2</span> }</pre>
    </div>
  </div>
</section>

<section class="api-sec">
  <h2><?= e($tx('نصّ صِرف (football.txt)', 'Plain text (football.txt)', 'Texte brut (football.txt)')) ?></h2>
  <p class="lead"><?= e($tx('البطولة كاملةً مُصدَّرة أيضاً بصيغة', 'The whole tournament is also exported in the', 'Tout le tournoi est aussi exporté au format')) ?>
    <a href="https://github.com/openfootball" target="_blank" rel="noopener">openfootball</a> <code style="color:#7dd3fc">football.txt</code>.</p>
  <pre class="api-pre"><?= e(rtrim(SITE_URL,'/')) ?>/football.php            <span class="c"># <?= e($tx('الجدول + النتائج', 'schedule + results', 'calendrier + résultats')) ?></span>
<?= e(rtrim(SITE_URL,'/')) ?>/football.php?results    <span class="c"># <?= e($tx('النتائج فقط', 'results only', 'résultats seuls')) ?></span>
<?= e(rtrim(SITE_URL,'/')) ?>/football.php?reports    <span class="c"># + <?= e($tx('هدّافون + بطاقات', 'scorers + bookings', 'buteurs + cartons')) ?></span></pre>
</section>

<section class="api-sec">
  <h2>🚀 <?= e($tx('بُنيت بواجهتنا', 'Built with our API', 'Réalisé avec notre API')) ?></h2>
  <p class="lead"><?= e($tx('تطبيقات ومشاريع يبنيها مطوّرون باستخدام هذا الـAPI:', 'Apps & projects developers built using this API:', 'Applications créées avec cette API :')) ?></p>
  <ul style="margin:0;padding-inline-start:20px;line-height:2">
    <li>
      <a href="https://play.google.com/store/apps/details?id=com.fifaworldcupfixtures" target="_blank" rel="noopener"><strong>FIFA World Cup Fixtures</strong></a>
      — <?= e($tx('تطبيق أندرويد على Google Play', 'Android app on Google Play', 'App Android sur Google Play')) ?>
    </li>
  </ul>
  <p class="lead" style="margin:14px 0 0">
    <?= e($tx('بنيت شيئاً بواجهتنا؟ أخبرنا لنعرضه هنا:', 'Built something with our API? Tell us and we\'ll feature it:', 'Vous avez créé quelque chose ? Dites-le-nous :')) ?>
    <a href="mailto:info@wcup2026.org">info@wcup2026.org</a>
  </p>
</section>

<section class="api-sec">
  <h2><?= e($tx('الاستخدام العادل', 'Fair use', 'Usage équitable')) ?></h2>
  <p class="lead" style="margin:0"><?= e($tx(
    'للقراءة فقط ومجّاني. خزّن مؤقّتاً حيثما أمكن (البيانات تتحدّث كل دقيقة تقريباً). الإسناد مُقدَّر — ضع رابطاً إلى wcup2026.org. بيانات الجدول من openfootball (ملك عام)؛ إحصائيات المباراة من بيانات الفيفا الرسميّة.',
    'Read-only and free. Please cache where you can (data refreshes ~every minute). Attribution appreciated — link back to wcup2026.org. Schedule data is openfootball (Public Domain); match metrics are FIFA official data.',
    'Lecture seule et gratuit. Mettez en cache si possible. Attribution appréciée — un lien vers wcup2026.org. Calendrier : openfootball (domaine public) ; statistiques : données officielles FIFA.'
  )) ?></p>
</section>

<script>
// عرض حيّ — يُثبت أنّ الـAPI حقيقي (نفس النطاق → متوافق مع CSP)
fetch('api/data.php?action=scorers')
  .then(function (r) { return r.json(); })
  .then(function (d) {
    var rows = (d.scorers || []).slice(0, 5).map(function (s, i) {
      return (i + 1) + '. ' + s.name + ' (' + s.team + ') — ' + s.goals + ' ⚽';
    }).join('\n');
    document.getElementById('apiOut').textContent = rows || <?= json_encode($tx('لا هدّافون بعد.', 'No scorers yet.', 'Aucun buteur.'), JSON_UNESCAPED_UNICODE) ?>;
  })
  .catch(function (e) { document.getElementById('apiOut').textContent = 'Error: ' + e; });
</script>

<?php tpl('footer'); ?>
