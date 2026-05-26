/* ============================================================
   sw.js — Service Worker لتطبيق كأس العالم 2026 (PWA)
   ------------------------------------------------------------
   وضع أوفلاين كامل (يعمل على اتصال ضعيف/2G — حافّة الجنوب العالمي):
     • التنقّلات (HTML) → شبكة-أولاً بمهلة قصيرة، ثم الكاش، ثم صفحة
       أوفلاين احتياطية مدمجة.
     • الأصول الثابتة (css/js/img/خطوط) → stale-while-revalidate.
     • نداءات الـAPI (/api/*) → شبكة فقط (لا نخزّن محتوى شخصياً/POST).
   لا نخزّن POST إطلاقاً، ولا أي رد يحمل Set-Cookie. كل شيء داخل try/catch.
   ============================================================ */
'use strict';

/* رفعنا رقم النسخة (v3 → v4) ليُفعَّل التحديث ويُنظَّف الكاش القديم تلقائياً. */
var CACHE = 'wc2026-v4';

/* قشرة التطبيق المُسبقة التخزين (نفس النطاق فقط — الخطوط/الأعلام عبر نطاقات أخرى نتجاهلها). */
var SHELL = [
  '/',
  '/index.php',
  '/today.php',
  '/matches.php',
  '/assets/css/style.css',
  '/assets/js/app.js',
  '/assets/js/pwa.js',
  '/assets/img/icon-192.png'
];

/* صفحة أوفلاين احتياطية مدمجة (لا تعتمد على الشبكة). تُخزَّن تحت هذا العنوان الوهمي
   ويُرجَع منها عند فشل التنقّل وعدم وجود نسخة مخزّنة من الصفحة المطلوبة. */
var OFFLINE_URL = '/__offline';
var OFFLINE_HTML =
  '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">' +
  '<meta name="viewport" content="width=device-width,initial-scale=1">' +
  '<title>غير متصل — كأس العالم 2026</title>' +
  '<style>' +
  'html,body{margin:0;height:100%}' +
  'body{display:flex;align-items:center;justify-content:center;text-align:center;' +
  'font-family:"Cairo",system-ui,Segoe UI,Tahoma,sans-serif;background:#0a1626;color:#eef2f7;padding:24px}' +
  '.box{max-width:420px}' +
  '.mark{display:inline-grid;place-items:center;width:64px;height:64px;border-radius:16px;' +
  'background:#13233d;color:#36c08f;font-weight:900;font-size:26px;margin-bottom:18px}' +
  'h1{font-size:1.3rem;margin:0 0 8px}p{opacity:.8;line-height:1.7;margin:0 0 20px}' +
  'button{font:inherit;background:#36c08f;color:#06241a;border:0;border-radius:10px;' +
  'padding:11px 22px;font-weight:700;cursor:pointer}' +
  '</style></head><body><div class="box">' +
  '<div class="mark">26</div>' +
  '<h1>أنت غير متصل بالإنترنت</h1>' +
  '<p>لا يمكن تحميل هذه الصفحة الآن. يمكنك تصفّح الصفحات التي زرتها سابقاً.<br>' +
  'You are offline — pages you visited before are still available.</p>' +
  '<button onclick="location.reload()">إعادة المحاولة · Retry</button>' +
  '</div></body></html>';

/* مهلة الشبكة للتنقّلات قبل اللجوء للكاش (مناسبة لاتصال 2G المتذبذب). */
var NAV_TIMEOUT = 4500;

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (c) {
      // خزّن قشرة التطبيق (كلٌّ على حدة حتى لا يُفشِل عنصرٌ مفقودٌ الباقي)…
      var jobs = SHELL.map(function (u) {
        return c.add(new Request(u, { cache: 'reload' })).catch(function () {});
      });
      // …وخزّن صفحة الأوفلاين الاحتياطية كاستجابة مُصنَّعة.
      jobs.push(
        c.put(OFFLINE_URL, new Response(OFFLINE_HTML, {
          headers: { 'Content-Type': 'text/html; charset=utf-8' }
        })).catch(function () {})
      );
      return Promise.all(jobs);
    }).catch(function () {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (k) {
        if (k !== CACHE) return caches.delete(k);
      }));
    }).then(function () { return self.clients.claim(); })
  );
});

/* السماح للصفحة بطلب تفعيل التحديث فوراً (يستدعيه pwa.js عند ضغط «تحديث»). */
self.addEventListener('message', function (e) {
  if (e && e.data === 'SKIP_WAITING') { self.skipWaiting(); }
});

/* هل المسار أصل ثابت (css/js/صورة/خط)؟ */
function isStatic(pathname) {
  return /\.(css|js|mjs|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|otf)$/i.test(pathname);
}

/* لا نخزّن أبداً ردّاً يحمل Set-Cookie (محتوى شخصي/جلسة). */
function isCacheableResponse(res) {
  return res && res.status === 200 && res.type === 'basic' && !res.headers.get('Set-Cookie');
}

/* استراتيجية شبكة-أولاً بمهلة، مع رجوع للكاش ثم لصفحة الأوفلاين (للتنقّلات). */
function networkFirstNav(req) {
  return new Promise(function (resolve) {
    var done = false;
    var timer = setTimeout(function () {
      if (done) return;
      done = true;
      fromCacheOrOffline(req).then(resolve);
    }, NAV_TIMEOUT);

    fetch(req).then(function (res) {
      if (done) return;
      done = true; clearTimeout(timer);
      // خزّن نسخة محدّثة من صفحة التنقّل إن كانت آمنة (لا كوكي).
      if (isCacheableResponse(res)) {
        var copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy).catch(function () {}); }).catch(function () {});
      }
      resolve(res);
    }).catch(function () {
      if (done) return;
      done = true; clearTimeout(timer);
      fromCacheOrOffline(req).then(resolve);
    });
  });
}

function fromCacheOrOffline(req) {
  return caches.match(req).then(function (m) {
    if (m) return m;
    return caches.match(OFFLINE_URL).then(function (off) {
      return off || new Response(OFFLINE_HTML, {
        status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' }
      });
    });
  });
}

/* stale-while-revalidate للأصول الثابتة: قدّم المخزّن فوراً وحدّثه في الخلفية. */
function staleWhileRevalidate(req) {
  return caches.open(CACHE).then(function (c) {
    return c.match(req).then(function (cached) {
      var fetching = fetch(req).then(function (res) {
        if (isCacheableResponse(res)) {
          c.put(req, res.clone()).catch(function () {});
        }
        return res;
      }).catch(function () { return null; });
      return cached || fetching.then(function (r) {
        return r || new Response('', { status: 504, statusText: 'offline' });
      });
    });
  });
}

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;                 // لا نتدخّل في POST (توقعات/تصويت/تسجيل)

  // نفس النطاق فقط عبر http/https (نتجاهل الإضافات والموارد الخارجية: الأعلام/الخطوط).
  var url;
  try { url = new URL(req.url); } catch (err) { return; }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
  if (url.origin !== self.location.origin) return;

  // نداءات الـAPI: شبكة فقط — لا نخزّنها إطلاقاً (محتوى شخصي/متغيّر، CSRF).
  if (/^\/api\//i.test(url.pathname)) return;

  var isNav = (req.mode === 'navigate') ||
              (req.headers.get('accept') || '').indexOf('text/html') !== -1;

  if (isNav) {
    e.respondWith(networkFirstNav(req));
    return;
  }

  if (isStatic(url.pathname)) {
    e.respondWith(staleWhileRevalidate(req));
    return;
  }

  // غير ذلك: حاول الشبكة ثم ارجع للكاش بهدوء.
  e.respondWith(
    fetch(req).catch(function () {
      return caches.match(req).then(function (m) {
        return m || new Response('', { status: 504, statusText: 'offline' });
      });
    })
  );
});
