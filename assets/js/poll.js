/* ============================================================
   poll.js — أداة استطلاع الجمهور (شاشة ثانية)
   - تُركَّب على أول عنصر [data-poll] في الصفحة (today.php).
   - تجلب الاستطلاع النشط، تتيح التصويت مرّة واحدة، ثم تعرض النِّسَب
     كأشرطة. تُحدّث العدّادات كل ~15 ثانية أثناء ظهور الصفحة.
   - آمنة: لا نضع نصوص الخادم عبر innerHTML — نستخدم textContent وعرض الأشرطة.
   - متسامحة مع الأوفلاين: تفشل بهدوء وتُظهر رسالة بسيطة.
   ============================================================ */
(function () {
  'use strict';

  var root = document.querySelector('[data-poll]');
  if (!root) return;

  var API = (root.getAttribute('data-poll-api') || '/api/poll.php');
  var isAr = (document.documentElement.lang || 'ar').toLowerCase().indexOf('ar') === 0;
  function L(ar, en) { return isAr ? ar : en; }

  var csrf = '';
  var state = null;     // آخر استطلاع معروف
  var timer = null;

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function setMsg(text) {
    root.textContent = '';
    var p = el('p', 'poll-msg', text);
    root.appendChild(p);
  }

  function pct(n, total) {
    if (!total || total <= 0) return 0;
    return Math.round((n / total) * 100);
  }

  // يبني/يحدّث واجهة الاستطلاع بالكامل عبر DOM آمن.
  function render(poll) {
    state = poll;
    root.textContent = '';

    var card = el('div', 'poll-card');
    card.appendChild(el('h3', 'poll-q', poll.question || ''));

    var total = poll.total || 0;
    var voted = !!poll.voted;
    var closed = !!poll.closed;
    var list = el('div', 'poll-options');

    (poll.options || []).forEach(function (label, i) {
      var count = (poll.counts && poll.counts[i]) ? poll.counts[i] : 0;
      var p = pct(count, total);

      var row;
      if (voted || closed) {
        // وضع النتائج: شريط نسبة + النص + النسبة المئوية
        row = el('div', 'poll-result' + (poll.choice === i ? ' is-choice' : ''));
        var bar = el('span', 'poll-bar');
        bar.style.width = p + '%';
        var lab = el('span', 'poll-label', label);
        var val = el('span', 'poll-pct', p + '%');
        row.appendChild(bar);
        row.appendChild(lab);
        row.appendChild(val);
      } else {
        // وضع التصويت: أزرار
        row = el('button', 'poll-opt');
        row.type = 'button';
        row.textContent = label;
        (function (idx) {
          row.addEventListener('click', function () { castVote(idx); });
        })(i);
      }
      list.appendChild(row);
    });
    card.appendChild(list);

    var foot = el('div', 'poll-foot');
    var totalTxt = L('صوت', 'votes');
    foot.appendChild(el('span', 'poll-total', total + ' ' + totalTxt));
    if (closed) {
      foot.appendChild(el('span', 'poll-note', L('انتهى التصويت', 'Voting closed')));
    } else if (voted) {
      foot.appendChild(el('span', 'poll-note', L('شكراً لتصويتك', 'Thanks for voting')));
    }
    card.appendChild(foot);

    root.appendChild(card);
  }

  function fetchCurrent() {
    return fetch(API + '?action=current', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) throw new Error('bad');
        csrf = d.csrf || csrf;
        if (!d.poll) {
          setMsg(L('لا يوجد استطلاع نشط الآن.', 'No active poll right now.'));
          state = null;
          return;
        }
        render(d.poll);
      });
  }

  function castVote(optionIndex) {
    if (!state || !state.id) return;
    // تفاؤل بسيط: عطّل الأزرار فوراً
    var btns = root.querySelectorAll('.poll-opt');
    btns.forEach(function (b) { b.disabled = true; });

    fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      body: JSON.stringify({ action: 'vote', pollId: state.id, option: optionIndex })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok) {
          // ادمج الرد في الحالة واعرض النتائج.
          state.voted = true;
          state.choice = (d.choice != null) ? d.choice : optionIndex;
          state.counts = d.counts || state.counts;
          state.total  = d.total != null ? d.total : state.total;
          render(state);
        } else {
          btns.forEach(function (b) { b.disabled = false; });
        }
      })
      .catch(function () {
        // أوفلاين أو خطأ شبكة → أعد تفعيل الأزرار ولا تكسر شيئاً.
        btns.forEach(function (b) { b.disabled = false; });
      });
  }

  // تحديث دوري للعدّادات أثناء ظهور الصفحة فقط (لطيف على البطارية/الشبكة).
  function startPolling() {
    stopPolling();
    timer = setInterval(function () {
      if (document.hidden) return;
      if (!navigator.onLine) return;
      fetchCurrent().catch(function () {});
    }, 15000);
  }
  function stopPolling() { if (timer) { clearInterval(timer); timer = null; } }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stopPolling();
    else { startPolling(); if (navigator.onLine) fetchCurrent().catch(function () {}); }
  });

  // إقلاع
  setMsg(L('جارٍ التحميل…', 'Loading…'));
  fetchCurrent()
    .then(startPolling)
    .catch(function () {
      setMsg(L('تعذّر تحميل الاستطلاع.', 'Could not load the poll.'));
    });
})();
