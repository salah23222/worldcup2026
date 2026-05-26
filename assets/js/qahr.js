/*!
 * qahr.js — ودجة «القهر» 😩💔 المستقلّة.
 * ------------------------------------------------------------
 * تربط أي عنصر يحمل [data-qahr] بثلاث وظائف:
 *   1) عرض سطر القهر (roast) من api/qahr.php (textContent فقط — آمن ضد XSS).
 *   2) زر «+ سجّل قهرك» يرسل POST action=bump (مع ترويسة X-CSRF) ويحدّث العدّاد العام.
 *   3) زر مشاركة (يعيد استخدام WCShare إن وُجد، وإلا wa.me).
 *
 * بنية HTML المتوقّعة (كل العناصر اختيارية ما عدا الجذر):
 *   <div data-qahr
 *        data-api="/api/qahr.php"
 *        data-csrf="<رمز CSRF>"
 *        data-id="<matchIndex اختياري لجلب الروست من الخادم>"
 *        data-lang="ar"
 *        data-dialect=""
 *        data-share-url="https://...">
 *     <p data-qahr-line>…سطر القهر…</p>     <!-- يُملأ من data-id إن وُجد -->
 *     <button data-qahr-bump>+ سجّل قهرك</button>
 *     <span data-qahr-total>0</span>
 *     <button data-qahr-share>مشاركة</button>
 *   </div>
 *
 * كل النصوص القادمة من الخادم تُكتب عبر textContent فقط. لا innerHTML أبداً.
 */
(function (global) {
  'use strict';

  var doc = global.document;
  if (!doc) return;

  function enc(s) { return encodeURIComponent(s == null ? '' : String(s)); }

  /* قراءة سمة مع قيمة افتراضية */
  function attr(el, name, def) {
    var v = el.getAttribute(name);
    return (v === null || v === '') ? (def || '') : v;
  }

  /* تنسيق رقم العدّاد بفواصل (بدون مكتبات) */
  function fmtNum(n) {
    n = parseInt(n, 10);
    if (!isFinite(n) || n < 0) n = 0;
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function getJSON(url) {
    return global.fetch(url, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function postJSON(url, csrf, payload) {
    return global.fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf || '' },
      body: JSON.stringify(payload || {}),
      cache: 'no-store',
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  /* رابط مشاركة واتساب — يعيد استخدام WCShare إن وُجد */
  function shareWhatsApp(text, url) {
    if (global.WCShare && typeof global.WCShare.waUrl === 'function') {
      return global.WCShare.waUrl(text, url);
    }
    return 'https://wa.me/?text=' + enc((text ? text + ' ' : '') + url);
  }

  function init(root) {
    var scope = root || doc;
    var widgets = scope.querySelectorAll('[data-qahr]');
    Array.prototype.forEach.call(widgets, function (el) {
      if (el.__qahrBound) return;
      el.__qahrBound = true;
      bind(el);
    });
  }

  function bind(el) {
    var api     = attr(el, 'data-api', '/api/qahr.php');
    var csrf    = attr(el, 'data-csrf', '');
    var id      = attr(el, 'data-id', '');
    var lang    = attr(el, 'data-lang', (doc.documentElement.getAttribute('lang') || 'ar'));
    var dialect = attr(el, 'data-dialect', '');

    var lineEl  = el.querySelector('[data-qahr-line]');
    var bumpBtn = el.querySelector('[data-qahr-bump]');
    var totalEl = el.querySelector('[data-qahr-total]');
    var shareBtn= el.querySelector('[data-qahr-share]');

    // نصّ المشاركة المُجمَّع (يُحدَّث من الخادم إن توفّر).
    var shareText = attr(el, 'data-share-text', (lineEl ? lineEl.textContent : '') || '');
    var shareUrl  = attr(el, 'data-share-url', global.location.href);

    /* 1) جلب الروست من الخادم إن وُجد data-id (وإلا يبقى النص المطبوع كما هو) */
    if (id !== '') {
      var q = api + (api.indexOf('?') >= 0 ? '&' : '?') +
              'action=roast&id=' + enc(id) + '&lang=' + enc(lang) +
              (dialect ? '&dialect=' + enc(dialect) : '');
      getJSON(q).then(function (res) {
        if (!res) return;
        if (res.ok && res.roast && lineEl) {
          lineEl.textContent = res.roast;          // textContent فقط
        }
        if (res.ok && res.share) {
          shareText = res.share;
        }
        if (typeof res.total !== 'undefined' && totalEl) {
          totalEl.textContent = fmtNum(res.total);
        }
      }).catch(function () { /* تجاهل — يبقى النص المطبوع من الخادم */ });
    }

    /* 2) زر «+ سجّل قهرك»: POST bump ويحدّث العدّاد */
    if (bumpBtn) {
      bumpBtn.addEventListener('click', function () {
        if (bumpBtn.disabled) return;
        bumpBtn.disabled = true;
        postJSON(api, csrf, { action: 'bump', lang: lang })
          .then(function (res) {
            if (res && typeof res.total !== 'undefined' && totalEl) {
              totalEl.textContent = fmtNum(res.total);
            }
          })
          .catch(function () { /* تجاهل */ })
          .then(function () {
            // إعادة التفعيل بعد لحظة (منع النقر المتكرر السريع).
            setTimeout(function () { bumpBtn.disabled = false; }, 1200);
          });
      });
    }

    /* 3) زر المشاركة */
    if (shareBtn) {
      shareBtn.addEventListener('click', function (ev) {
        if (ev) ev.preventDefault();
        var text = shareText || (lineEl ? lineEl.textContent : '') || '';
        // Web Share API الأصلي إن توفّر (هاتف)، وإلا واتساب.
        if (global.navigator && typeof global.navigator.share === 'function') {
          global.navigator.share({ text: text, url: shareUrl }).catch(function () {
            global.open(shareWhatsApp(text, shareUrl), '_blank', 'noopener');
          });
        } else {
          global.open(shareWhatsApp(text, shareUrl), '_blank', 'noopener');
        }
      });
    }
  }

  var WCQahr = { init: init, bind: bind };
  global.WCQahr = WCQahr;

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : this);
