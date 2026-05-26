/* ============================================================
   cardvote.js — تصويت 1X2 سريع على بطاقات المباريات
   عنصر واحد لكل خيار: الاسم نفسه زر، وعند الضغط يمتلئ بشريط النسبة داخله.
   - لا طلب شبكي عند التحميل (الخيارات جاهزة من الخادم).
   - عند أوّل ضغطة: يجلب رمز CSRF مرّة واحدة ثم يصوّت.
   - textContent فقط (لا innerHTML).
   ============================================================ */
(function () {
  'use strict';

  var API = (window.WC_POLL_API || '/api/poll.php');
  var csrf = '';
  var csrfPromise = null;

  function getCsrf() {
    if (csrf) return Promise.resolve(csrf);
    if (csrfPromise) return csrfPromise;
    csrfPromise = fetch(API + '?action=token', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) { csrf = (d && d.csrf) || ''; return csrf; })
      .catch(function () { return ''; });
    return csrfPromise;
  }

  function pct(counts, i) {
    var total = 0;
    for (var k = 0; k < counts.length; k++) total += (counts[k] || 0);
    return total > 0 ? Math.round(100 * (counts[i] || 0) / total) : 0;
  }

  function fill(box, counts, choice) {
    var els = box.querySelectorAll('.mc-poll-opt');
    Array.prototype.forEach.call(els, function (el) {
      var i  = parseInt(el.getAttribute('data-opt'), 10);
      var p  = pct(counts, i);
      var bar = el.querySelector('.mcp-bar');
      var pe  = el.querySelector('.mcp-pct');
      if (bar) bar.style.width = p + '%';
      if (pe)  pe.textContent = p + '%';
      if (i === choice) el.classList.add('is-mine');
      el.disabled = true;
    });
    box.classList.add('is-voted');
    box.setAttribute('data-voted', '1');
  }

  function setDisabled(box, on) {
    Array.prototype.forEach.call(box.querySelectorAll('.mc-poll-opt'), function (b) { b.disabled = on; });
  }

  function vote(box, option) {
    var pollId = box.getAttribute('data-poll');
    if (!pollId) return;
    setDisabled(box, true);
    getCsrf().then(function (token) {
      return fetch(API, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', 'X-CSRF': token },
        body: JSON.stringify({ action: 'vote', pollId: pollId, option: option })
      });
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok && d.counts) {
        fill(box, d.counts, (typeof d.choice === 'number') ? d.choice : option);
      } else {
        setDisabled(box, false);
      }
    }).catch(function () { setDisabled(box, false); });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.mc-poll[data-voted="0"]'), function (box) {
      Array.prototype.forEach.call(box.querySelectorAll('.mc-poll-opt'), function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault(); e.stopPropagation();
          vote(box, parseInt(btn.getAttribute('data-opt'), 10));
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
