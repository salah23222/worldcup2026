/* ============================================================
   trivia.js — تحدّي المعرفة اليومي (محتسَب على الخادم)
   - يرسل الإجابة للـAPI الذي يتحقّق ويمنح 3 نقاط للمسجّلين
   - يكشف الصحيح/الشرح من ردّ الخادم
   - سلسلة الأيام المتتالية في localStorage
   ============================================================ */
(function () {
  'use strict';

  var cfg = window.WC_TRIVIA || {};
  var card = document.getElementById('triviaCard');
  var streakEl = document.getElementById('streakNum');
  var KEY = 'wc_trivia';

  function load() { try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; } }
  function save(o) { try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) {} }
  function yesterdayOf(d) { var x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() - 1); return x.toISOString().slice(0, 10); }

  var state = load();
  if (streakEl) streakEl.textContent = state.streak || 0;
  if (!card || cfg.answered) return;   // مُجاب اليوم → الخادم كشفه أصلاً

  var opts = Array.prototype.slice.call(card.querySelectorAll('.trivia-opt'));
  var explain = document.getElementById('triviaExplain');
  var answered = false;

  function reveal(correctIndex, chosen) {
    opts.forEach(function (b) {
      b.disabled = true;
      var i = parseInt(b.getAttribute('data-i'), 10);
      if (i === correctIndex) b.classList.add('opt-correct');
      else if (i === chosen) b.classList.add('opt-wrong');
    });
  }

  function note(cls, text) {
    var p = document.createElement('p');
    p.className = 'trivia-note ' + cls;
    p.textContent = text;
    card.insertBefore(p, explain);
  }

  function bumpStreak() {
    var s = load(), ns;
    if (s.day === yesterdayOf(cfg.day)) ns = (s.streak || 0) + 1;
    else if (s.day === cfg.day) ns = s.streak || 1;
    else ns = 1;
    save({ day: cfg.day, streak: ns });
    if (streakEl) streakEl.textContent = ns;
  }

  opts.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (answered) return;
      answered = true;
      var chosen = parseInt(btn.getAttribute('data-i'), 10);
      opts.forEach(function (b) { b.disabled = true; });

      fetch(cfg.api, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf },
        body: JSON.stringify({ action: 'trivia', index: chosen, lang: cfg.lang }),
        cache: 'no-store'
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || !res.ok) { answered = false; opts.forEach(function (b){ b.disabled = false; }); return; }
        reveal(res.correctIndex, chosen);
        if (explain && res.explain) { explain.textContent = res.explain; explain.hidden = false; }
        if (res.correct) {
          note('tn-correct', cfg.i18n.correct);
          if (res.awarded && res.points > 0) note('tn-correct', '+' + res.points + ' ' + cfg.i18n.added);
          else if (!res.registered) note('tn-wrong', cfg.i18n.guest);
        } else {
          note('tn-wrong', cfg.i18n.wrong);
          if (!res.registered) note('tn-wrong', cfg.i18n.guest);
        }
        bumpStreak();
      })
      .catch(function () { answered = false; opts.forEach(function (b){ b.disabled = false; }); });
    });
  });
})();
