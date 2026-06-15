/* ============================================================
   sw.js — Service Worker لتطبيق كأس العالم 2026 (PWA)  v11
   ------------------------------------------------------------
   استراتيجيّة أوفلاين متينة — تتجنّب صفحة "غير متصل" الخاطئة:
     1) شبكة-أوّلاً بمهلة 10 ثوانٍ (كانت 4.5 — قصيرة لـHostinger البطيء).
     2) عند الفشل/المهلة → جرّب الكاش بنفس الـURL، ثم تجاهل الـquery-string،
        ثم تجاهل الـpath تماماً وارجع لأي صفحة مخزّنة من نفس الجذر.
     3) قبل عرض "غير متصل" نهائياً → تحقّق navigator.onLine حقيقةً.
   الأصول الثابتة: stale-while-revalidate. /api و /cron: شبكة فقط.
   لا نخزّن POST ولا Set-Cookie أبداً. كل شيء try/catch.
   ============================================================ */
'use strict';

/* v15 — رفع الإصدار يحذف الكاش القديم عند التفعيل (يمسح أي صفحة فارغة مخزّنة).
   + physical.php صار مُستثنى من كاش SW كليّاً (بيانات حيّة → دائماً من الشبكة). */
var CACHE = 'wc2026-v15';

/* قشرة التطبيق — صفحات شائعة بكل لغة (يطابقها SW مع/بدون query). */
var SHELL = [
  '/',
  '/index.php',
  '/today.php',
  '/matches.php',
  '/groups.php',
  '/predict.php',
  '/leaderboard.php',
  '/teams.php',
  '/stadiums.php',
  '/assets/css/style.css',
  '/assets/js/app.js',
  '/assets/js/pwa.js',
  '/assets/img/icon-192.png'
];

/* صفحة أوفلاين احتياطيّة (UI محسّن: شعار + قائمة صفحات سابقة + إعادة محاولة). */
var OFFLINE_URL = '/__offline';
var OFFLINE_HTML =
  '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">' +
  '<meta name="viewport" content="width=device-width,initial-scale=1">' +
  '<title>غير متصل — كأس العالم 2026</title>' +
  '<style>' +
  '*,*::before,*::after{box-sizing:border-box}' +
  'html,body{margin:0;min-height:100%;background:#0a1626;color:#eef2f7;' +
  'font-family:"Cairo","Segoe UI",Tahoma,system-ui,sans-serif}' +
  'body{display:flex;align-items:center;justify-content:center;text-align:center;padding:24px}' +
  '.box{max-width:480px;width:100%}' +
  '.mark{display:inline-grid;place-items:center;width:72px;height:72px;border-radius:18px;' +
  'background:linear-gradient(135deg,#13233d,#1a3050);color:#36c08f;font-weight:900;font-size:30px;' +
  'margin-bottom:22px;box-shadow:0 8px 32px rgba(54,192,143,.15)}' +
  '.dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#ef4444;' +
  'margin-inline-end:8px;animation:pulse 1.4s infinite}' +
  '@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}' +
  'h1{font-size:1.4rem;margin:0 0 10px;font-weight:800}' +
  '.sub{font-size:.9rem;color:#9fb3d1;margin:0 0 6px;letter-spacing:.5px}' +
  'p{opacity:.9;line-height:1.8;margin:0 0 24px;color:#cbd5e1}' +
  '.row{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:18px}' +
  'button,a.btn{font:inherit;font-weight:700;cursor:pointer;border:0;border-radius:12px;' +
  'padding:12px 22px;text-decoration:none;transition:all .15s}' +
  '.btn-pri{background:#36c08f;color:#06241a}' +
  '.btn-pri:hover{background:#28a877;transform:translateY(-1px)}' +
  '.btn-sec{background:#1a2940;color:#cbd5e1;border:1px solid #2a3a55}' +
  '.btn-sec:hover{background:#22344f}' +
  '.tips{margin-top:28px;padding-top:20px;border-top:1px solid #1a2940;font-size:.85rem;color:#9fb3d1;text-align:start}' +
  '.tips li{margin:6px 0;line-height:1.7}' +
  '.tips .ico{color:#36c08f;font-weight:700}' +
  '</style></head><body><div class="box">' +
  '<div class="mark">26</div>' +
  '<p class="sub"><span class="dot"></span>اتصال ضعيف · Slow connection</p>' +
  '<h1>تعذّر تحميل هذه الصفحة</h1>' +
  '<p>قد يكون الاتصال بطيئاً أو منقطعاً. الصفحات التي زرتها سابقاً ما زالت متاحة.<br>' +
  '<small style="color:#7a90af">You can still browse pages you visited earlier.</small></p>' +
  '<div class="row">' +
  '<button class="btn-pri" onclick="location.reload()">🔄 إعادة المحاولة</button>' +
  '<a class="btn btn-sec" href="/">🏠 الرئيسية</a>' +
  '</div>' +
  '<ul class="tips">' +
  '<li><span class="ico">✓</span> صفحاتنا متاحة دون اتصال بعد أوّل زيارة</li>' +
  '<li><span class="ico">✓</span> النتائج تتحدّث تلقائياً عند عودة الإنترنت</li>' +
  '<li><span class="ico">✓</span> ثبّت التطبيق على الشاشة للوصول السريع</li>' +
  '</ul>' +
  '</div></body></html>';

/* بعد إصلاحات الخادم (stale-while-revalidate) الصفحات ترد خلال ~1 ثانية.
   المهلة هنا «تبديل للكاش» فقط — لو تجاوزها الخادم نقدّم النسخة المخزّنة
   فوراً، ويبقى طلب الشبكة جارياً ليُخزَّن ردّه للزيارة القادمة. */
var NAV_TIMEOUT = 6000;

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE).then(function (c) {
      var jobs = SHELL.map(function (u) {
        return c.add(new Request(u, { cache: 'reload' })).catch(function () {});
      });
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

self.addEventListener('message', function (e) {
  if (e && e.data === 'SKIP_WAITING') { self.skipWaiting(); }
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (list[i].url === url && 'focus' in list[i]) return list[i].focus();
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});

function isStatic(pathname) {
  return /\.(css|js|mjs|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf|otf)$/i.test(pathname);
}

function isCacheableResponse(res) {
  return res && res.status === 200 && res.type === 'basic' && !res.headers.get('Set-Cookie');
}

/* يبحث في الكاش فقط (بلا صفحة أوفلاين):
   1) نفس الـURL تماماً → 2) متجاهلاً الـquery-string → 3) الصفحة الرئيسية.
   يُرجع undefined إن لم يوجد شيء. */
function cachedFallback(req) {
  return caches.open(CACHE).then(function (c) {
    return c.match(req).then(function (m1) {
      if (m1) return m1;
      return c.match(req, { ignoreSearch: true }).then(function (m2) {
        if (m2) return m2;
        return c.match('/');
      });
    });
  }).catch(function () { return undefined; });
}

/* صفحة الأوفلاين — آخر حل على الإطلاق. */
function offlineResponse() {
  return caches.open(CACHE)
    .then(function (c) { return c.match(OFFLINE_URL); })
    .catch(function () { return undefined; })
    .then(function (off) {
      return off || new Response(OFFLINE_HTML, {
        status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8' }
      });
    });
}

/* شبكة-أوّلاً:
   - المهلة لا تعرض «غير متصل» أبداً — تقدّم الكاش إن وُجد، وإلا تنتظر الشبكة
     مهما تأخّرت (المستخدم متّصل والخادم بطيء ≠ أوفلاين).
   - الردّ المتأخّر (بعد المهلة) يُخزَّن دائماً ليُفيد الزيارة القادمة.
   - صفحة الأوفلاين تظهر فقط عند فشل الشبكة الفعلي + خلوّ الكاش تماماً. */
function networkFirstNav(req) {
  return new Promise(function (resolve) {
    var done = false;

    var timer = setTimeout(function () {
      cachedFallback(req).then(function (m) {
        if (done || !m) return;   // لا كاش → اترك طلب الشبكة الجاري يكمل
        done = true;
        resolve(m);
      });
    }, NAV_TIMEOUT);

    fetch(req).then(function (res) {
      // خزّن دائماً — حتى الردّ الذي وصل بعد المهلة يفيد الزيارة التالية.
      if (isCacheableResponse(res)) {
        var copy = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, copy).catch(function () {}); }).catch(function () {});
      }
      if (done) return;
      done = true; clearTimeout(timer);
      resolve(res);
    }).catch(function () {
      clearTimeout(timer);
      if (done) return;
      // فشل شبكة فعلي → كاش، ثم صفحة الأوفلاين كآخر حل.
      cachedFallback(req).then(function (m) {
        if (done) return;
        done = true;
        if (m) { resolve(m); return; }
        offlineResponse().then(resolve);
      });
    });
  });
}

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
  if (req.method !== 'GET') return;

  var url;
  try { url = new URL(req.url); } catch (err) { return; }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
  if (url.origin !== self.location.origin) return;

  // ✨ المسارات التي لا يجب أن يلمسها SW أبداً (محتوى شخصي/حسّاس/POST):
  //   /api/*        → JSON محتوى شخصي
  //   /cron/*       → عمليّات خادم تستغرق ثوانٍ
  //   /admin*       → لوحة تحكّم بجلسة + CSRF + معاينات حيّة
  //   /login.php    → نموذج دخول (لا للتخزين)
  //   /unsubscribe* → روابط شخصيّة موقّعة
  //   /install.php  → ثبيت/تهيئة لمرّة واحدة
  if (/^\/api\//i.test(url.pathname))       return;
  if (/^\/cron\//i.test(url.pathname))      return;
  if (/^\/admin/i.test(url.pathname))       return;
  if (/^\/login\.php/i.test(url.pathname))  return;
  if (/^\/unsubscribe/i.test(url.pathname)) return;
  if (/^\/install\.php/i.test(url.pathname))return;
  if (/^\/physical\.php/i.test(url.pathname))return;   // بيانات حيّة متغيّرة — دائماً من الشبكة (لا يُخدَم فراغ مخزّن)

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

  e.respondWith(
    fetch(req).catch(function () {
      return caches.match(req).then(function (m) {
        return m || new Response('', { status: 504, statusText: 'offline' });
      });
    })
  );
});
