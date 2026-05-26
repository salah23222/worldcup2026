/* ============================================================
   stickers.js — ألبوم الملصقات (يُحفظ في المتصفح)
   - باقة مجانية كل 20 ساعة (5 ملصقات)
   - سحب موزون حسب الندرة + كشف متحرّك
   - حفظ المجموعة في localStorage
   ============================================================ */
(function () {
  'use strict';

  var grid = document.querySelector('.sticker-grid');
  if (!grid) return;

  var I18N = (window.WC_STICKERS && window.WC_STICKERS.i18n) || {};
  var STORE_KEY = 'wc_stickers_owned';
  var PACK_KEY  = 'wc_stickers_lastpack';
  var PACK_SIZE = 5;
  var COOLDOWN  = 20 * 3600 * 1000; // 20 ساعة

  // كل الخانات في الصفحة → قائمة الملصقات مع ندرتها
  var slots = Array.prototype.slice.call(document.querySelectorAll('.sticker'));
  var all = slots.map(function (el) {
    return { id: el.getAttribute('data-id'), rarity: el.getAttribute('data-rarity'), el: el };
  });

  function load() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}') || {}; }
    catch (e) { return {}; }
  }
  function save(o) { try { localStorage.setItem(STORE_KEY, JSON.stringify(o)); } catch (e) {} }

  var owned = load();

  /* -------- عرض الحالة -------- */
  function refresh() {
    var have = 0;
    all.forEach(function (s) {
      if (owned[s.id]) {
        have++;
        s.el.classList.remove('locked');
        s.el.classList.add('owned');
        if (owned[s.id] > 1) s.el.setAttribute('data-dupe', '×' + owned[s.id]);
      }
    });
    var total = all.length;
    var pct = total ? Math.round((have / total) * 100) : 0;
    var fill = document.getElementById('apFill');
    var cnt  = document.getElementById('apCount');
    var pctEl = document.getElementById('apPct');
    if (fill) fill.style.width = pct + '%';
    if (cnt) cnt.textContent = have;
    if (pctEl) pctEl.textContent = pct + '%';
    // عدّاد كل مجموعة
    document.querySelectorAll('[data-set-count]').forEach(function (sc) {
      var sec = sc.closest('.sticker-set');
      var cells = sec.querySelectorAll('.sticker');
      var o = sec.querySelectorAll('.sticker.owned').length;
      sc.textContent = '(' + o + '/' + cells.length + ')';
    });
    if (have >= total && total > 0) {
      var c = document.getElementById('albumComplete');
      if (c) c.hidden = false;
    }
  }

  /* -------- منطق الباقة -------- */
  // أوزان الندرة (كلما قلّ الرقم، قلّ الظهور)
  var WEIGHT = { common: 60, rare: 30, legendary: 10 };

  function drawOne() {
    var pool = [];
    all.forEach(function (s) { for (var i = 0; i < (WEIGHT[s.rarity] || 20); i++) pool.push(s); });
    return pool[Math.floor(Math.random() * pool.length)];
  }

  function cooldownLeft() {
    var last = parseInt(localStorage.getItem(PACK_KEY) || '0', 10);
    return Math.max(0, COOLDOWN - (Date.now() - last));
  }

  function fmtLeft(ms) {
    var s = Math.floor(ms / 1000);
    var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
    return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
  }

  var btn = document.getElementById('openPackBtn');
  var cdEl = document.getElementById('packCooldown');

  function updateBtn() {
    var left = cooldownLeft();
    if (left > 0) {
      btn.disabled = true;
      if (cdEl) { cdEl.hidden = false; cdEl.textContent = (I18N.cooldown || 'Next pack in') + ' ' + fmtLeft(left); }
    } else {
      btn.disabled = false;
      if (cdEl) cdEl.hidden = true;
    }
  }

  function openPack() {
    if (cooldownLeft() > 0) return;
    var drawn = [];
    for (var i = 0; i < PACK_SIZE; i++) {
      var s = drawOne();
      var isNew = !owned[s.id];
      owned[s.id] = (owned[s.id] || 0) + 1;
      drawn.push({ s: s, isNew: isNew });
    }
    save(owned);
    localStorage.setItem(PACK_KEY, String(Date.now()));
    reveal(drawn);
    refresh();
    updateBtn();
  }

  /* -------- كشف الباقة (متحرّك) -------- */
  function reveal(drawn) {
    var overlay = document.getElementById('packOverlay');
    var box = document.getElementById('packReveal');
    if (!overlay || !box) return;
    box.innerHTML = '';
    drawn.forEach(function (d, idx) {
      var src = d.s.el.querySelector('.st-img');
      var emoji = d.s.el.querySelector('.st-emoji');
      var name = d.s.el.querySelector('.st-name');
      var card = document.createElement('div');
      card.className = 'reveal-card rar-' + d.s.rarity;
      card.style.animationDelay = (idx * 0.12) + 's';
      card.innerHTML =
        '<div class="rc-media">' +
          (src ? '<img src="' + src.getAttribute('src') + '" alt="">' :
                 '<span class="rc-emoji">' + (emoji ? emoji.textContent : '⭐') + '</span>') +
        '</div>' +
        '<span class="rc-name">' + (name ? name.textContent : '') + '</span>' +
        '<span class="rc-tag">' + (d.isNew ? (I18N.isNew || 'NEW!') : (I18N.dupe || 'Dup')) + '</span>';
      box.appendChild(card);
    });
    overlay.hidden = false;
  }

  if (btn) btn.addEventListener('click', openPack);
  var close = document.getElementById('packClose');
  if (close) close.addEventListener('click', function () {
    document.getElementById('packOverlay').hidden = true;
  });

  var reset = document.getElementById('resetAlbum');
  if (reset) reset.addEventListener('click', function () {
    if (!window.confirm(I18N.resetConfirm || 'Reset?')) return;
    owned = {};
    save(owned);
    localStorage.removeItem(PACK_KEY);
    all.forEach(function (s) {
      s.el.classList.remove('owned'); s.el.classList.add('locked'); s.el.removeAttribute('data-dupe');
    });
    var c = document.getElementById('albumComplete'); if (c) c.hidden = true;
    refresh(); updateBtn();
  });

  refresh();
  updateBtn();
  setInterval(updateBtn, 30000);
})();
