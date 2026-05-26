/*!
 * share.js — شريط مشاركة عالمي للبطاقات (واتساب / X / تيليجرام / نسخ الرابط).
 * ------------------------------------------------------------
 * يربط أي عنصر يحمل [data-share-url] (والاختياري [data-share-text]) بأزرار
 * المشاركة. الهاشتاقات تطابق render_share() في includes/helpers.php:
 *   #كأس_العالم_2026 #FIFAWorldCup26 #wcup2026
 * يستخدم textContent فقط (لا innerHTML) — آمن ضد XSS.
 * ------------------------------------------------------------
 * الاستخدام في HTML (داخل عنصر الشريط):
 *   <div class="share-bar" data-share-url="https://..." data-share-text="...">
 *     <a data-share="wa">واتساب</a>
 *     <a data-share="x">X</a>
 *     <a data-share="tg">تيليجرام</a>
 *     <button data-share="copy" data-copied="تم النسخ ✓">نسخ الرابط</button>
 *   </div>
 * أو بزر منفصل يحمل data-share-url/text مباشرةً.
 */
(function (global) {
  'use strict';

  var TAGS = '#كأس_العالم_2026 #FIFAWorldCup26 #wcup2026';

  function enc(s) { return encodeURIComponent(s == null ? '' : String(s)); }

  function waUrl(text, url)  { return 'https://wa.me/?text=' + enc((text ? text + ' ' : '') + TAGS + ' ' + url); }
  function xUrl(text, url)   { return 'https://twitter.com/intent/tweet?text=' + enc((text ? text + '\n\n' : '') + TAGS) + '&url=' + enc(url); }
  function tgUrl(text, url)  { return 'https://t.me/share/url?url=' + enc(url) + '&text=' + enc((text ? text + ' ' : '') + TAGS); }

  function openShare(href) {
    global.open(href, '_blank', 'noopener');
  }

  /* نسخ الرابط مع حالة "تم النسخ ✓" (textContent فقط) */
  function copyLink(url, btn) {
    var done = function () {
      if (!btn) return;
      var original = btn.getAttribute('data-label') || btn.textContent;
      btn.setAttribute('data-label', original);
      btn.textContent = btn.getAttribute('data-copied') || 'تم النسخ ✓';
      setTimeout(function () { btn.textContent = original; }, 1800);
    };
    if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
      global.navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url); done(); });
    } else {
      fallbackCopy(url);
      done();
    }
  }

  /* نسخ احتياطي للمتصفّحات بلا Clipboard API */
  function fallbackCopy(text) {
    try {
      var ta = global.document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'absolute';
      ta.style.left = '-9999px';
      global.document.body.appendChild(ta);
      ta.select();
      global.document.execCommand('copy');
      global.document.body.removeChild(ta);
    } catch (e) { /* تجاهل */ }
  }

  /* يحلّ الرابط/النص لعنصر زر: من نفسه أو من أقرب حاوية تحمل البيانات */
  function resolve(el) {
    var host = el.closest ? (el.closest('[data-share-url]') || el) : el;
    return {
      url:  host.getAttribute('data-share-url') || global.location.href,
      text: host.getAttribute('data-share-text') || ''
    };
  }

  function handle(kind, el, ev) {
    var d = resolve(el);
    switch (kind) {
      case 'wa':   if (ev) ev.preventDefault(); openShare(waUrl(d.text, d.url)); break;
      case 'x':    if (ev) ev.preventDefault(); openShare(xUrl(d.text, d.url));  break;
      case 'tg':   if (ev) ev.preventDefault(); openShare(tgUrl(d.text, d.url)); break;
      case 'copy': if (ev) ev.preventDefault(); copyLink(d.url, el); break;
    }
  }

  /* يربط كل أزرار [data-share] داخل النطاق المعطى (افتراضياً المستند كله) */
  function init(root) {
    var scope = root || global.document;
    var btns = scope.querySelectorAll('[data-share]');
    Array.prototype.forEach.call(btns, function (el) {
      if (el.__shareBound) return;
      el.__shareBound = true;
      el.addEventListener('click', function (ev) {
        handle(el.getAttribute('data-share'), el, ev);
      });
    });
  }

  var Share = { init: init, waUrl: waUrl, xUrl: xUrl, tgUrl: tgUrl, copyLink: copyLink, TAGS: TAGS };
  global.WCShare = Share;

  if (global.document) {
    if (global.document.readyState === 'loading') {
      global.document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
      init();
    }
  }
})(typeof window !== 'undefined' ? window : this);
