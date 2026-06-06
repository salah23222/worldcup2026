<?php
/**
 * footer.php — تذييل الصفحة المشترك.
 */
if (!defined('WC2026')) { exit('Access denied'); }
$lastUpdate = DataService::lastUpdate();
require_once __DIR__ . '/../includes/SponsorStore.php';
$sponsors   = SponsorStore::all();
$lang       = current_lang();
?>
</main><!-- /.site-main -->

<!-- ============ الرعاة ============ -->
<section class="sponsors">
  <div class="wrap">
    <p class="sponsors-title"><?= e(t('sponsors_title')) ?></p>
    <div class="sponsors-row">
      <?php if (!empty($sponsors)): ?>
        <?php foreach ($sponsors as $sp):
          $name = $sp['name'] ?? '';
          $href = $sp['url']  ?? '';
          $logo = $sp['logo'] ?? '';
          $inner = $logo !== ''
            ? '<img src="' . e($logo) . '" alt="' . e($name) . '" loading="lazy">'
            : '<span>' . e($name) . '</span>';
          if ($href !== ''): ?>
            <a class="sponsor" href="<?= e($href) ?>" target="_blank" rel="noopener nofollow"><?= $inner ?></a>
          <?php else: ?>
            <span class="sponsor"><?= $inner ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 0; $i < (int)(defined('SPONSOR_PLACEHOLDERS') ? SPONSOR_PLACEHOLDERS : 0); $i++): ?>
          <button type="button" class="sponsor sponsor-empty" data-contact-open><?= e($lang === 'ar' ? 'شعارك هنا' : 'Your logo here') ?></button>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<footer class="site-footer">
  <div class="wrap footer-grid">

    <!-- ──────── العمود 1: الهوية + الأرقام ──────── -->
    <div class="footer-col footer-col-brand">
      <div class="footer-brand">
        <span class="footer-mark" aria-hidden="true">
          <span class="fm-top">FIFA</span>
          <span class="fm-bot">26</span>
        </span>
        <div class="footer-brand-text">
          <strong>wcup2026.org</strong>
          <span><?= e($lang === 'ar' ? 'كأس العالم 2026 · كندا · المكسيك · أمريكا' : 'FIFA World Cup 2026 · Canada · Mexico · USA') ?></span>
          <span class="footer-brand-dates">📅 <?= e($lang === 'ar' ? '11 يونيو – 19 يوليو 2026' : 'June 11 – July 19, 2026') ?></span>
        </div>
      </div>
      <div class="footer-numbers">
        <div class="num-pill">
          <span class="num-val">104</span>
          <span class="num-lbl"><?= e($lang === 'ar' ? 'مباراة' : 'matches') ?></span>
        </div>
        <div class="num-pill">
          <span class="num-val">48</span>
          <span class="num-lbl"><?= e($lang === 'ar' ? 'منتخباً' : 'teams') ?></span>
        </div>
        <div class="num-pill">
          <span class="num-val">16</span>
          <span class="num-lbl"><?= e($lang === 'ar' ? 'مدينة' : 'cities') ?></span>
        </div>
      </div>
    </div>

    <!-- ──────── العمود 2: المصدر والبيانات ──────── -->
    <div class="footer-col">
      <h4 class="footer-h"><?= e($lang === 'ar' ? 'البيانات والمصدر' : 'Data & source') ?></h4>
      <ul class="footer-list">
        <li>
          <span class="li-ico">📦</span>
          <span><?= e(t('data_source')) ?>:
            <a href="https://github.com/openfootball/worldcup.json" target="_blank" rel="noopener">openfootball</a>
            <span class="muted">· Public Domain</span>
          </span>
        </li>
        <?php if ($lastUpdate): ?>
        <li>
          <span class="li-ico">🔄</span>
          <span><?= e(t('last_update')) ?>: <strong><?= local_dt($lastUpdate, 'time') ?></strong>
            <span class="auto-refresh-note">· <?= e(t('auto_refresh')) ?></span>
          </span>
        </li>
        <?php endif; ?>
        <li class="tz-note" id="tzNote" data-tz-note="<?= e(t('local_tz_note')) ?>">
          <span class="li-ico">🕒</span>
          <span><?= e(t('local_tz_note')) ?></span>
        </li>
        <li>
          <span class="li-ico">🧩</span>
          <a href="<?= e(url('embed.php')) ?>"><?= e(t('embed_widget')) ?></a>
        </li>
      </ul>
    </div>

    <!-- ──────── العمود 3: المجتمع والتواصل ──────── -->
    <div class="footer-col">
      <h4 class="footer-h"><?= e($lang === 'ar' ? 'المجتمع والتواصل' : 'Community & contact') ?></h4>

      <div class="footer-visitors-card">
        <span class="visitor-ico" aria-hidden="true">👁️</span>
        <span class="visitor-num" id="visitorCount">—</span>
        <span class="visitor-lbl"><?= e(t('visitors')) ?></span>
      </div>

      <div class="footer-rate" id="footerRate"
           data-api="<?= e(rtrim(SITE_URL,'/') . '/api/rate.php') ?>"
           data-thanks="<?= e(t('rate_thanks')) ?>"
           data-satisfied="<?= e(t('rate_satisfied')) ?>">
        <span class="rate-q"><?= e(t('rate_q')) ?></span>
        <div class="rate-faces">
          <button type="button" data-face="happy"   aria-label="<?= e(t('rate_good')) ?>">😊</button>
          <button type="button" data-face="neutral" aria-label="<?= e(t('rate_ok')) ?>">😐</button>
          <button type="button" data-face="sad"     aria-label="<?= e(t('rate_bad')) ?>">😞</button>
        </div>
        <p class="rate-result" id="rateResult" hidden></p>
      </div>

      <div class="footer-actions">
        <a class="footer-action" href="mailto:<?= e(CONTACT_EMAIL) ?>">
          <span class="li-ico">✉️</span> <?= e(CONTACT_EMAIL) ?>
        </a>
        <button type="button" class="footer-action footer-action-cta" data-contact-open>
          <span class="li-ico">💬</span> <?= e(t('contact_title')) ?>
        </button>
      </div>
    </div>

  </div>

  <!-- ──────── شريط حقوق سفلي ──────── -->
  <div class="footer-bottom">
    <div class="wrap footer-bottom-inner">
      <p class="copyright">
        © <?= date('Y') ?> wcup2026.org
        <span class="dot">·</span>
        <?= e($lang === 'ar' ? 'صُنع بشغف لكرة القدم' : 'Built with love for football') ?>
      </p>
      <div class="footer-bottom-links">
        <a href="<?= e(url('matches.php')) ?>"><?= e(t('matches')) ?></a>
        <span class="dot">·</span>
        <a href="<?= e(url('groups.php')) ?>"><?= e(t('groups')) ?></a>
        <span class="dot">·</span>
        <a href="<?= e(url('stats.php')) ?>"><?= e(t('stats') ?: ($lang==='ar'?'الإحصائيات':'Stats')) ?></a>
        <span class="dot">·</span>
        <a href="<?= e(url('predict.php')) ?>"><?= e($lang === 'ar' ? 'التوقّعات' : 'Predict') ?></a>
      </div>
    </div>
  </div>
</footer>

<!-- ============ نافذة التواصل / الرعاية ============ -->
<div class="modal-overlay" id="contactOverlay" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="contactTitle">
    <button type="button" class="modal-close" id="contactClose" aria-label="✕">✕</button>
    <h3 id="contactTitle"><?= e(t('contact_title')) ?></h3>
    <p class="modal-sub"><?= e(t('contact_sub')) ?></p>
    <form id="contactForm" class="contact-form" novalidate>
      <input type="text"  name="name"    placeholder="<?= e(t('contact_name')) ?>"    maxlength="80"  required>
      <input type="email" name="email"   placeholder="<?= e(t('contact_email')) ?>"   maxlength="190" required>
      <input type="text"  name="phone"   placeholder="<?= e(t('contact_phone')) ?>"   maxlength="40" inputmode="tel">
      <textarea name="message" placeholder="<?= e(t('contact_message')) ?>" maxlength="4000" rows="4" required></textarea>
      <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
      <button type="submit" class="btn btn-cta" id="contactSend"><?= e(t('contact_send')) ?></button>
      <p class="contact-result" id="contactResult" hidden></p>
    </form>

    <!-- صندوق التأكيد بعد الإرسال -->
    <div class="contact-success" id="contactSuccess" hidden>
      <div class="cs-icon" aria-hidden="true">✓</div>
      <h3 class="cs-title"><?= e(t('contact_sent_ok')) ?></h3>
      <p class="cs-sub"><?= e(t('contact_followup')) ?></p>
      <?php
        $cPhone = defined('CONTACT_PHONE') ? trim((string)CONTACT_PHONE) : '';
        if ($cPhone !== ''):
          $cTel = preg_replace('/[^\d+]/', '', $cPhone);
          $cWa  = preg_replace('/\D/', '', $cPhone);
      ?>
      <p class="cs-label"><?= e(t('contact_direct')) ?></p>
      <div class="cs-actions">
        <a class="btn btn-cta" href="tel:<?= e($cTel) ?>" dir="ltr">📞 <?= e($cPhone) ?></a>
        <a class="btn btn-sm cs-wa" href="https://wa.me/<?= e($cWa) ?>" target="_blank" rel="noopener"><?= e(current_lang()==='ar' ? 'واتساب' : 'WhatsApp') ?></a>
      </div>
      <?php endif; ?>
      <p class="cs-email">📧 <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></p>
    </div>
  </div>
</div>
<script>
(function () {
  var openers = document.querySelectorAll('[data-contact-open]');
  var overlay = document.getElementById('contactOverlay');
  if (!overlay || !openers.length) return;
  var form = document.getElementById('contactForm');
  var result = document.getElementById('contactResult');
  var success = document.getElementById('contactSuccess');
  var sendBtn = document.getElementById('contactSend');
  var api = <?= json_encode(rtrim(SITE_URL, '/') . '/api/contact.php', JSON_UNESCAPED_SLASHES) ?>;
  var MSG_OK = <?= json_encode(t('contact_sent_ok'), JSON_UNESCAPED_UNICODE) ?>;
  var MSG_ERR = <?= json_encode(t('contact_error'), JSON_UNESCAPED_UNICODE) ?>;
  var csrf = '';
  function val(n) { return form.elements[n] ? form.elements[n].value : ''; }
  function getToken() {
    fetch(api + '?action=token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) { csrf = d.csrf || ''; }).catch(function () {});
  }
  function open() {
    // أعِد الضبط دائماً: أظهر النموذج وأخفِ صندوق التأكيد (في حال فُتحت بعد إرسال سابق).
    if (form) form.hidden = false;
    if (success) success.hidden = true;
    if (result) result.hidden = true;
    overlay.hidden = false;
    getToken();
  }
  function close() { overlay.hidden = true; }
  openers.forEach(function (b) { b.addEventListener('click', open); });
  document.getElementById('contactClose').addEventListener('click', close);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    result.hidden = true; sendBtn.disabled = true;
    fetch(api, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      body: JSON.stringify({
        action: 'send', name: val('name'), email: val('email'),
        phone: val('phone'), message: val('message'), website: val('website')
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        sendBtn.disabled = false;
        if (d && d.ok) {
          // أظهر صندوق التأكيد «سيتم التواصل معك» بدل النموذج.
          form.reset();
          form.hidden = true;
          if (success) success.hidden = false;
        } else {
          result.hidden = false;
          result.textContent = MSG_ERR; result.className = 'contact-result err';
        }
      })
      .catch(function () {
        sendBtn.disabled = false; result.hidden = false;
        result.textContent = MSG_ERR; result.className = 'contact-result err';
      });
  });
})();
</script>

<!-- ============ شريط تثبيت التطبيق (PWA) ============ -->
<div class="pwa-banner" id="pwaBanner" hidden>
  <img class="pwa-icon" src="<?= e(rtrim(SITE_URL,'/')) ?>/assets/img/icon-192.png" alt="" width="44" height="44">
  <div class="pwa-text">
    <strong><?= e(t('install_app')) ?></strong>
    <span id="pwaHint"><?= e(t('install_hint')) ?></span>
  </div>
  <button type="button" class="btn btn-accent btn-sm" id="pwaInstall"><?= e(t('install_btn')) ?></button>
  <button type="button" class="pwa-close" id="pwaClose" aria-label="✕">✕</button>
</div>

<?php $jsBase = e(rtrim(SITE_URL,'/')); $appV = @filemtime(__DIR__ . '/../assets/js/app.js') ?: 1; $pwaV = @filemtime(__DIR__ . '/../assets/js/pwa.js') ?: 1; $cvV = @filemtime(__DIR__ . '/../assets/js/cardvote.js') ?: 1; $rmV = @filemtime(__DIR__ . '/../assets/js/reminders.js') ?: 1; ?>
<script src="<?= $jsBase ?>/assets/js/app.js?v=<?= $appV ?>" defer></script>
<script src="<?= $jsBase ?>/assets/js/pwa.js?v=<?= $pwaV ?>" defer></script>
<script>window.WC_POLL_API=<?= json_encode(rtrim(SITE_URL,'/') . '/api/poll.php', JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= $jsBase ?>/assets/js/cardvote.js?v=<?= $cvV ?>" defer></script>
<script>window.WC_REMIND=<?= json_encode([
  'beforeMin' => 10,
  'icon'      => rtrim(SITE_URL,'/') . '/assets/img/icon-192.png',
  'soon'      => t('remind_soon'),
  'start'     => t('remind_start'),
  'on'        => t('remind_on'),
  'blocked'   => t('remind_blocked'),
  'soonBody'  => t('remind_soon_body'),
  'startBody' => t('remind_start_body'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<?php $rateV = @filemtime(__DIR__ . '/../assets/js/rating.js') ?: 1; ?>
<script src="<?= $jsBase ?>/assets/js/reminders.js?v=<?= $rmV ?>" defer></script>
<script src="<?= $jsBase ?>/assets/js/rating.js?v=<?= $rateV ?>" defer></script>
<script>
(function () {
  var el = document.getElementById('visitorCount');
  if (!el) return;
  fetch(<?= json_encode(rtrim(SITE_URL,'/') . '/api/visit.php', JSON_UNESCAPED_SLASHES) ?>, { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (d) {
      if (d && typeof d.count === 'number') {
        try { el.textContent = d.count.toLocaleString(); }
        catch (e) { el.textContent = String(d.count); }
      }
    })
    .catch(function () {});
})();
</script>
</body>
</html>
