/* ============================================================
   World Cup 2026 — app.js
   1) قائمة الجوال
   2) التحديث التلقائي للنتائج بدون إعادة تحميل الصفحة
   ============================================================ */
(function () {
  'use strict';

  /* -------- 1) قائمة الجوال -------- */
  var toggle = document.getElementById('navToggle');
  var nav    = document.getElementById('mainNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
      toggle.classList.toggle('open');
    });
    // أغلق القائمة عند الضغط على رابط
    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        nav.classList.remove('open');
        toggle.classList.remove('open');
      });
    });
  }

  /* -------- القوائم المنسدلة (أكورديون على الجوال) -------- */
  document.querySelectorAll('.nav-group-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var group = btn.closest('.nav-group');
      if (!group) return;
      var willOpen = !group.classList.contains('open');
      // أغلق بقية المجموعات (سلوك أكورديون نظيف)
      document.querySelectorAll('.nav-group.open').forEach(function (g) {
        if (g !== group) g.classList.remove('open');
      });
      group.classList.toggle('open', willOpen);
    });
  });

  /* -------- 2) تحويل المواعيد لتوقيت بلد الزائر --------
     كل وسم <time class="js-local"> يحمل data-ts (لحظة المباراة المطلقة).
     نحوّله هنا لتوقيت متصفّح الزائر تلقائياً عبر Intl — بلا أي إعداد منه.
     النص الافتراضي من الخادم يبقى ظاهراً لو كان JS معطّلاً. */
  function localeTag() {
    var l = (document.documentElement.lang || 'en').toLowerCase();
    // ar مع أرقام لاتينية ليتطابق مع بقية الموقع
    if (l.indexOf('ar') === 0) return 'ar-u-nu-latn';
    if (l.indexOf('fr') === 0) return 'fr';
    return 'en';
  }

  function fmtOpts(mode) {
    var isFr = (document.documentElement.lang || '').indexOf('fr') === 0;
    switch (mode) {
      case 'date':
        return { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
      case 'date_short':
        return { day: 'numeric', month: 'short' };
      case 'datetime':
        return { weekday: 'short', day: 'numeric', month: 'short',
                 hour: 'numeric', minute: '2-digit', hour12: !isFr };
      case 'time':
      default:
        return { hour: 'numeric', minute: '2-digit', hour12: !isFr };
    }
  }

  function localizeTimes(root) {
    root = root || document;
    var locale = localeTag();
    var nodes = root.querySelectorAll('time.js-local[data-ts]');
    nodes.forEach(function (node) {
      var ts = parseInt(node.getAttribute('data-ts'), 10);
      if (!ts) return;
      var d = new Date(ts * 1000);
      try {
        // بلا تحديد timeZone → Intl يستخدم توقيت جهاز الزائر تلقائياً
        node.textContent = new Intl.DateTimeFormat(locale, fmtOpts(node.getAttribute('data-mode'))).format(d);
      } catch (e) { /* أبقِ نص الخادم */ }
    });
  }

  /** يكتب اسم منطقة الزائر الزمنية في تذييل الصفحة */
  function showTimezone() {
    var note = document.getElementById('tzNote');
    if (!note) return;
    var base = note.getAttribute('data-tz-note') || '';
    var tz = '';
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
    note.textContent = '🕒 ' + base + (tz ? ' (' + tz + ')' : '');
  }

  localizeTimes();
  showTimezone();

  /* -------- زر نسخ الرابط في شريط المشاركة -------- */
  document.querySelectorAll('.s-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-url') || location.href;
      var done = btn.getAttribute('data-copied') || 'Copied';
      var orig = btn.innerHTML;
      var ok = function () { btn.textContent = done; setTimeout(function () { btn.innerHTML = orig; }, 1800); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(ok, ok);
      } else {
        var ta = document.createElement('textarea'); ta.value = url; document.body.appendChild(ta);
        ta.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(ta); ok();
      }
    });
  });

  /* -------- العدّاد التنازلي لانطلاق البطولة -------- */
  (function () {
    var cd = document.getElementById('countdown');
    if (!cd) return;
    var target = parseInt(cd.getAttribute('data-target'), 10) * 1000;
    if (!target) return;
    var fields = {
      d: cd.querySelector('[data-cd="d"]'),
      h: cd.querySelector('[data-cd="h"]'),
      m: cd.querySelector('[data-cd="m"]'),
      s: cd.querySelector('[data-cd="s"]')
    };
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function tick() {
      var diff = Math.floor((target - Date.now()) / 1000);
      if (diff <= 0) { cd.classList.add('cd-live'); cd.innerHTML =
        '<p class="cd-label cd-live-label">' +
        (document.documentElement.lang === 'ar' ? 'انطلقت البطولة! 🎉' : (document.documentElement.lang === 'fr' ? 'Le tournoi a commencé ! 🎉' : 'The tournament has begun! 🎉')) +
        '</p>'; clearInterval(timer); return; }
      var d = Math.floor(diff / 86400);
      var h = Math.floor((diff % 86400) / 3600);
      var m = Math.floor((diff % 3600) / 60);
      var s = diff % 60;
      if (fields.d) fields.d.textContent = d;
      if (fields.h) fields.h.textContent = pad(h);
      if (fields.m) fields.m.textContent = pad(m);
      if (fields.s) fields.s.textContent = pad(s);
    }
    tick();
    var timer = setInterval(tick, 1000);
  })();

  /* -------- كشف الأقسام تدريجياً عند التمرير -------- */
  (function () {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var els = document.querySelectorAll('.section, .day-block, .hero-banner, .ref-section, .sticker-set, .lineup-box, .lb-wrap');
    if (!els.length) return;
    document.body.classList.add('reveal-on');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
      });
    }, { threshold: 0.06, rootMargin: '0px 0px -40px 0px' });
    els.forEach(function (el) { el.classList.add('reveal'); io.observe(el); });
  })();

  /* -------- الملعب التكتيكي: الضغط على لاعب يُظهر اسمه ورقمه -------- */
  (function () {
    var board = document.querySelector('.pitch-full');
    if (!board) return;
    var info = document.getElementById('pitchInfo');
    var defaultText = info ? info.textContent : '';
    board.addEventListener('click', function (e) {
      var pp = e.target.closest ? e.target.closest('.pp') : null;
      if (!pp) return;
      board.querySelectorAll('.pp.active').forEach(function (x) {
        if (x !== pp) x.classList.remove('active');
      });
      pp.classList.toggle('active');
      if (!info) return;
      if (pp.classList.contains('active')) {
        var num  = pp.getAttribute('data-num');
        var name = pp.getAttribute('data-name') || '';
        var team = pp.getAttribute('data-team') || '';
        info.textContent = (num ? '#' + num + ' · ' : '') + name + (team ? ' — ' + team : '');
        info.classList.add('has-pick');
      } else {
        info.textContent = defaultText;
        info.classList.remove('has-pick');
      }
    });
  })();

  /* -------- 3) التحديث التلقائي -------- */
  // يعمل فقط إذا كانت الصفحة فيها قسم data-autorefresh
  var hasLiveSection = document.querySelector('[data-autorefresh]');
  if (!hasLiveSection) return;

  // مسار الـ API الداخلي (نسبي ليعمل على أي استضافة)
  var API_BASE = (function () {
    var path = window.location.pathname;
    var dir  = path.substring(0, path.lastIndexOf('/') + 1);
    return dir + 'api/data.php';
  })();

  var REFRESH_MS = 90000;   // كل 90 ثانية (بدل 60)
  var liveTimer  = null;
  var idleChecks = 0;

  /**
   * يفحص إن كان هناك مباريات مباشرة:
   *   - وُجدت → يحدّث المحتوى بهدوء (softReload).
   *   - لا توجد → يتوقّف الاستطلاع نهائياً بعد فحصين (لا إرهاق للخادم قبل/بين المباريات).
   * هذا يلغي «نوبات» الطلبات على api/data.php التي ترهق الخادم بلا فائدة.
   */
  function checkUpdates() {
    fetch(API_BASE + '?action=live', { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var count = (data && data.ok && data.matches) ? data.matches.length : 0;
        updateLiveIndicator(count);
        if (count > 0) {
          idleChecks = 0;
          softReload();                       // فقط عند وجود مباراة مباشرة
        } else {
          idleChecks++;
          if (idleChecks >= 2 && liveTimer) { // لا مباريات مباشرة → أوقف الاستطلاع
            clearInterval(liveTimer);
            liveTimer = null;
          }
        }
      })
      .catch(function () { /* تجاهل أخطاء الشبكة بصمت */ });
  }

  /** يحدّث عدّاد المباريات المباشرة في الفوتر */
  function updateLiveIndicator(count) {
    var note = document.querySelector('.auto-refresh-note');
    if (note && count > 0) {
      note.textContent = '🔴 ' + count + ' ' +
        (document.documentElement.lang === 'ar' ? 'مباشر الآن' : 'live now');
    }
  }

  /**
   * إعادة تحميل خفيفة: تجلب الصفحة نفسها وتستبدل شبكات
   * المباريات فقط — بدون وميض الصفحة كاملة.
   */
  function softReload() {
    fetch(window.location.href, { cache: 'no-store' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        // استبدل كل شبكة مباريات بالمحتوى الجديد
        var oldGrids = document.querySelectorAll('.match-grid');
        var newGrids = doc.querySelectorAll('.match-grid');
        oldGrids.forEach(function (grid, i) {
          if (newGrids[i]) grid.innerHTML = newGrids[i].innerHTML;
        });
        // استبدل جداول الترتيب إن وُجدت
        var oldTables = document.querySelectorAll('.standings tbody');
        var newTables = doc.querySelectorAll('.standings tbody');
        oldTables.forEach(function (tb, i) {
          if (newTables[i]) tb.innerHTML = newTables[i].innerHTML;
        });
        // استبدل جدول الصدارة (تنعكس الدرجات لحظياً)
        var oldLb = document.querySelectorAll('.leaderboard tbody');
        var newLb = doc.querySelectorAll('.leaderboard tbody');
        oldLb.forEach(function (tb, i) {
          if (newLb[i]) tb.innerHTML = newLb[i].innerHTML;
        });
        // أعد تحويل المواعيد المُحقونة حديثاً لتوقيت الزائر
        localizeTimes();
      })
      .catch(function () {});
  }

  // ابدأ الدورة (نحتفظ بمرجع المؤقّت لنوقفه عند غياب المباريات المباشرة)
  liveTimer = setInterval(checkUpdates, REFRESH_MS);
  // فحص أوّل بعد 10 ثوانٍ من تحميل الصفحة (لا نزاحم تحميل الصفحة الأوّل)
  setTimeout(checkUpdates, 10000);

})();
