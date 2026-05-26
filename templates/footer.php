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
  <div class="wrap footer-inner">
    <div class="footer-stats">
      <span><?= e(t('matches_count')) ?></span>
      <span><?= e(t('teams_count')) ?></span>
      <span><?= e(t('cities_count')) ?></span>
    </div>
    <div class="footer-meta">
      <p>
        <?= e(t('data_source')) ?>:
        <a href="https://github.com/openfootball/worldcup.json" target="_blank" rel="noopener">openfootball</a>
        · Public Domain ·
        <a href="<?= e(url('embed.php')) ?>"><?= e(t('embed_widget')) ?></a>
      </p>
      <?php if ($lastUpdate): ?>
        <p class="muted">
          <?= e(t('last_update')) ?>: <?= local_dt($lastUpdate, 'time') ?> ·
          <span class="auto-refresh-note"><?= e(t('auto_refresh')) ?></span>
        </p>
      <?php endif; ?>
      <p class="muted tz-note" id="tzNote" data-tz-note="<?= e(t('local_tz_note')) ?>">
        🕒 <?= e(t('local_tz_note')) ?>
      </p>
      <p class="footer-contact">
        📧 <?= e(t('contact_label')) ?>:
        <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
        · <button type="button" class="link-btn" data-contact-open><?= e(t('contact_title')) ?></button>
      </p>
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

<?php $jsBase = e(rtrim(SITE_URL,'/')); $appV = @filemtime(__DIR__ . '/../assets/js/app.js') ?: 1; $pwaV = @filemtime(__DIR__ . '/../assets/js/pwa.js') ?: 1; $cvV = @filemtime(__DIR__ . '/../assets/js/cardvote.js') ?: 1; ?>
<script src="<?= $jsBase ?>/assets/js/app.js?v=<?= $appV ?>" defer></script>
<script src="<?= $jsBase ?>/assets/js/pwa.js?v=<?= $pwaV ?>" defer></script>
<script>window.WC_POLL_API=<?= json_encode(rtrim(SITE_URL,'/') . '/api/poll.php', JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= $jsBase ?>/assets/js/cardvote.js?v=<?= $cvV ?>" defer></script>
</body>
</html>
