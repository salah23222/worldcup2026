/* ============================================================
   predict.js — تفاعل مسابقة التوقعات
   - الانضمام باسم مستعار (تسجيل)
   - حفظ توقّع كل مباراة (محميّ بـCSRF)
   ============================================================ */
(function () {
  'use strict';

  var app = document.getElementById('predictApp');
  if (!app) return;

  var API  = app.getAttribute('data-api') || '/api/predict.php';
  var csrf = app.getAttribute('data-csrf') || '';
  var I18N = window.WC_I18N || {};

  var joinBox    = document.getElementById('joinBox');
  var welcomeBox = document.getElementById('welcomeBox');
  var joinForm   = document.getElementById('joinForm');
  var nickInput  = document.getElementById('nickInput');
  var joinError  = document.getElementById('joinError');
  var welcomeName= document.getElementById('welcomeName');
  var changeBtn  = document.getElementById('changeNameBtn');

  function postJSON(payload) {
    return fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      body: JSON.stringify(payload),
      cache: 'no-store'
    }).then(function (r) { return r.json(); });
  }

  function showError(msg) {
    if (!joinError) return;
    joinError.textContent = msg;
    joinError.hidden = false;
  }

  /* -------- الانضمام / تغيير الاسم -------- */
  if (joinForm) {
    joinForm.addEventListener('submit', function (e) {
      e.preventDefault();
      joinError.hidden = true;
      var name = (nickInput.value || '').trim();
      postJSON({ action: 'register', nickname: name })
        .then(function (res) {
          if (res && res.ok) {
            if (res.csrf) { csrf = res.csrf; app.setAttribute('data-csrf', csrf); }
            app.setAttribute('data-registered', '1');
            if (welcomeName) welcomeName.textContent = res.nickname || name;
            if (joinBox) joinBox.hidden = true;
            if (welcomeBox) welcomeBox.hidden = false;
          } else {
            var key = (res && res.error) || '';
            showError(I18N[key] || I18N.invalid_nickname || 'Error');
          }
        })
        .catch(function () { showError(I18N.invalid_nickname || 'Error'); });
    });
  }

  if (changeBtn) {
    changeBtn.addEventListener('click', function () {
      if (welcomeBox) welcomeBox.hidden = true;
      if (joinBox) { joinBox.hidden = false; nickInput.focus(); }
    });
  }

  var FUN = window.WC_FUN || {};
  function base() { return location.origin + location.pathname.replace(/[^/]*$/, ''); }
  function cardUrl(id, p1, p2, brag) {
    return base() + 'card.php?id=' + id + '&p1=' + parseInt(p1,10) + '&p2=' + parseInt(p2,10) + (brag ? '&brag=1' : '');
  }

  /* -------- 🪙 دع القدر يقرّر / 🔗 شارك / 📢 عاير -------- */
  document.querySelectorAll('.pred-row').forEach(function (row) {
    var id = parseInt(row.getAttribute('data-id'), 10);
    var in1 = row.querySelector('.pred-p1'), in2 = row.querySelector('.pred-p2');

    var fate = row.querySelector('.pred-fate');
    if (fate) fate.addEventListener('click', function () {
      // درجات واقعية موزونة (منخفضة أكثر)
      var pool = [0,0,1,1,1,1,2,2,2,3,3,4];
      var pick = function(){ return pool[Math.floor(Math.random()*pool.length)]; };
      var n = 0, spin = setInterval(function(){
        in1.value = Math.floor(Math.random()*5); in2.value = Math.floor(Math.random()*5);
        if (++n > 8) { clearInterval(spin); in1.value = pick(); in2.value = pick();
          row.classList.add('fate-flash'); setTimeout(function(){ row.classList.remove('fate-flash'); }, 600); }
      }, 70);
    });

    var sh = row.querySelector('.pred-share');
    if (sh) sh.addEventListener('click', function () {
      if (in1.value === '' || in2.value === '') { in1.focus(); return; }
      var ar = document.documentElement.lang === 'ar';
      var msg = (ar ? 'توقّعي في كأس العالم 2026 🔮 — ' : 'My FIFA World Cup 2026 prediction 🔮 — ') + cardUrl(id, in1.value, in2.value, false);
      window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
    });

    var brag = row.querySelector('.pred-brag');
    if (brag) brag.addEventListener('click', function () {
      if (in1.value === '' || in2.value === '') { in1.focus(); return; }
      var msg = (FUN.bragText || 'I called it! — ') + cardUrl(id, in1.value, in2.value, true);
      window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
    });
  });

  /* -------- 😩 عدّاد القهر -------- */
  (function () {
    var btn = document.getElementById('qahrBtn');
    var numEl = document.getElementById('qahrNum');
    var titleEl = document.getElementById('qahrTitle');
    if (!btn || !numEl || !titleEl) return;
    var titles = FUN.qahrTitles || ['', '', '', '', ''];
    function titleFor(n) {
      if (n === 0) return titles[0];
      if (n <= 3) return titles[1];
      if (n <= 9) return titles[2];
      if (n <= 19) return titles[3];
      return titles[4];
    }
    function get() { return parseInt(localStorage.getItem('wc_qahr') || '0', 10) || 0; }
    function render(n) { numEl.textContent = n; titleEl.textContent = titleFor(n); }
    render(get());
    btn.addEventListener('click', function () {
      var n = get() + 1;
      try { localStorage.setItem('wc_qahr', String(n)); } catch (e) {}
      render(n);
      titleEl.classList.remove('qahr-pop'); void titleEl.offsetWidth; titleEl.classList.add('qahr-pop');
    });
  })();

  /* -------- حفظ التوقعات -------- */
  document.querySelectorAll('.pred-row').forEach(function (row) {
    var btn = row.querySelector('.pred-save');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var registered = app.getAttribute('data-registered') === '1';
      var status = row.querySelector('.pred-status');
      if (!registered) {
        if (joinBox) { joinBox.hidden = false; }
        if (nickInput) nickInput.focus();
        return;
      }
      var id = parseInt(row.getAttribute('data-id'), 10);
      var p1 = row.querySelector('.pred-p1').value;
      var p2 = row.querySelector('.pred-p2').value;
      if (p1 === '' || p2 === '') return;

      btn.disabled = true;
      postJSON({ action: 'save', id: id, p1: parseInt(p1, 10), p2: parseInt(p2, 10) })
        .then(function (res) {
          if (res && res.ok) {
            row.classList.add('pred-saved');
            if (status) status.textContent = I18N.saved || 'Saved';
          } else {
            var key = (res && res.error) || '';
            if (key === 'locked') {
              row.classList.add('pred-locked');
              if (status) status.textContent = I18N.locked || 'Locked';
            } else if (status) {
              status.textContent = '—';
            }
          }
        })
        .catch(function () {})
        .finally(function () { btn.disabled = false; });
    });
  });

})();
