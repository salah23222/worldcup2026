/* ============================================================
   pwa.js — تثبيت الموقع كتطبيق على الهاتف + تجربة الأوفلاين/التحديث
   1) تسجيل Service Worker (+ كشف تحديث جاهز)
   2) إظهار شريط «ثبّت التطبيق» (Android/Chrome) أو إرشاد iOS
   3) شريط «أنت غير متصل» + شريط «تحديث متاح»
   ============================================================ */
(function () {
  'use strict';

  var isAr = (document.documentElement.lang || 'ar').toLowerCase().indexOf('ar') === 0;
  function L(ar, en) { return isAr ? ar : en; }

  // -------- شريط صغير غير مزعج (يُعاد استخدامه للأوفلاين والتحديث) --------
  function makeToast(id) {
    var t = document.getElementById(id);
    if (t) return t;
    t = document.createElement('div');
    t.id = id;
    t.setAttribute('role', 'status');
    t.style.cssText =
      'position:fixed;left:50%;transform:translateX(-50%);bottom:14px;z-index:9999;' +
      'max-width:92%;display:none;align-items:center;gap:12px;' +
      'background:#13233d;color:#eef2f7;border:1px solid rgba(255,255,255,.12);' +
      'box-shadow:0 8px 30px rgba(0,0,0,.4);border-radius:12px;' +
      'padding:10px 14px;font:14px/1.5 "Cairo",system-ui,Segoe UI,Tahoma,sans-serif';
    document.body.appendChild(t);
    return t;
  }

  // 1) تسجيل الـ Service Worker (يعمل على HTTPS أو localhost فقط) + كشف التحديث
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').then(function (reg) {
        if (!reg) return;

        // عامل خدمة جديد ينتظر بالفعل (تحديث جاهز من زيارة سابقة)
        if (reg.waiting && navigator.serviceWorker.controller) {
          showUpdate(reg.waiting);
        }

        // عامل جديد قيد التثبيت → راقب حتى يصبح "installed" وينتظر
        reg.addEventListener('updatefound', function () {
          var sw = reg.installing;
          if (!sw) return;
          sw.addEventListener('statechange', function () {
            if (sw.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdate(sw);
            }
          });
        });
      }).catch(function () {});

      // عند تفعيل العامل الجديد بعد SKIP_WAITING → أعد التحميل مرّة واحدة
      var reloaded = false;
      navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (reloaded) return;
        reloaded = true;
        window.location.reload();
      });
    });
  }

  // -------- شريط «تحديث متاح» --------
  function showUpdate(worker) {
    var bar = makeToast('pwaUpdate');
    bar.innerHTML = '';
    var msg = document.createElement('span');
    msg.textContent = L('يتوفّر تحديث جديد', 'A new version is available');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = L('تحديث', 'Update');
    btn.style.cssText =
      'font:inherit;font-weight:700;background:#36c08f;color:#06241a;border:0;' +
      'border-radius:8px;padding:6px 14px;cursor:pointer';
    btn.addEventListener('click', function () {
      btn.disabled = true;
      try { worker.postMessage('SKIP_WAITING'); } catch (e) { window.location.reload(); }
    });
    bar.appendChild(msg);
    bar.appendChild(btn);
    bar.style.display = 'flex';
  }

  // -------- شريط «أنت غير متصل» --------
  var offlineBar = null;
  function renderOffline() {
    if (navigator.onLine) {
      if (offlineBar) offlineBar.style.display = 'none';
      return;
    }
    offlineBar = makeToast('pwaOffline');
    if (!offlineBar.firstChild) {
      var s = document.createElement('span');
      s.textContent = L('أنت غير متصل — تُعرض نسخة محفوظة',
                        "You're offline — showing a saved version");
      offlineBar.appendChild(s);
    }
    offlineBar.style.display = 'flex';
  }
  window.addEventListener('online', renderOffline);
  window.addEventListener('offline', renderOffline);
  if (!navigator.onLine) renderOffline();

  var banner = document.getElementById('pwaBanner');
  if (!banner) return;

  var installBtn = document.getElementById('pwaInstall');
  var closeBtn   = document.getElementById('pwaClose');
  var hintEl     = document.getElementById('pwaHint');
  var DISMISS_KEY  = 'wc_pwa_dismissed';
  var DISMISS_DAYS = 14;   // مدّة التجاهل قبل إعادة عرض شريط التثبيت

  // إذا التطبيق مثبّت أصلاً (يعمل بوضع standalone) → لا تُظهر شيئاً
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                     window.navigator.standalone === true;
  if (isStandalone) return;

  // لا تُزعج الزائر إن أغلق الشريط مؤخراً — تنتهي صلاحية التجاهل بعد DISMISS_DAYS يوماً.
  try {
    var raw = localStorage.getItem(DISMISS_KEY);
    if (raw) {
      var when = parseInt(raw, 10);
      // قيمة قديمة '1' = تجاهل دائم → نُحوّلها لطابع زمني الآن لتنتهي بعد 14 يوماً
      if (!when || when < 1e12) {
        when = Date.now();
        try { localStorage.setItem(DISMISS_KEY, String(when)); } catch (e) {}
      }
      if ((Date.now() - when) < DISMISS_DAYS * 86400000) return;
    }
  } catch (e) {}

  function show() { banner.hidden = false; }
  function dismiss() {
    banner.hidden = true;
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
  }
  if (closeBtn) closeBtn.addEventListener('click', dismiss);

  // -------- Android / Chrome / Edge: زر تثبيت أصلي --------
  var deferred = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    show();
  });

  if (installBtn) installBtn.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.finally(function () { deferred = null; dismiss(); });
  });

  window.addEventListener('appinstalled', function () { dismiss(); });

  // -------- iOS (Safari): لا يدعم beforeinstallprompt → إرشاد يدوي --------
  var ua = window.navigator.userAgent || '';
  var isIOS = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;
  var isSafari = /^((?!chrome|crios|android).)*safari/i.test(ua);
  if (isIOS && isSafari) {
    if (installBtn) installBtn.hidden = true;          // لا زر — إرشاد فقط
    if (hintEl) {
      var ar = document.documentElement.lang === 'ar';
      hintEl.textContent = ar
        ? 'للتثبيت: اضغط زر المشاركة ⬆️ ثم «أضف إلى الشاشة الرئيسية»'
        : 'To install: tap Share ⬆️ then "Add to Home Screen"';
    }
    setTimeout(show, 2500);   // أظهره بعد قليل من التصفّح
  }
})();
