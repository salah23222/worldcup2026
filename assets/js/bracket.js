/* ============================================================
   bracket.js — «توقّع المشوار»
   شجرة إقصائيات تفاعلية: اختيار متأهّلي دور الـ32 ثم تصعيد
   الفائزين حتى النهائي. الحالة محفوظة في localStorage.
   ============================================================ */
(function () {
  'use strict';

  var ROUNDS = window.BRACKET;
  if (!ROUNDS) return;
  var AR = (window.BRACKET_LANG || 'ar') === 'ar';
  var FR = (window.BRACKET_LANG || '') === 'fr';
  var STORE = 'wc_bracket_v1';

  // فهرسة المباريات برقمها + ترتيب الحساب (من الأسفل للأعلى)
  var byNum = {};
  var order = [];
  ['round_of_32','round_of_16','quarter_finals','semi_finals','final','third_place']
    .forEach(function (k) {
      (ROUNDS[k] || []).forEach(function (m) { byNum[m.num] = m; order.push(m.num); });
    });

  // جدول المنتخبات (id → {name, flag}) من كل مرشّحي دور الـ32
  var team = {};
  (ROUNDS.round_of_32 || []).forEach(function (m) {
    [m.s1, m.s2].forEach(function (s) {
      (s.cands || []).forEach(function (c) { team[c.id] = { name: c.name, flag: c.flag }; });
    });
  });

  var finalNum = (ROUNDS.final && ROUNDS.final[0]) ? ROUNDS.final[0].num : null;

  var state = load();

  function load() {
    try {
      var d = JSON.parse(localStorage.getItem(STORE) || '{}');
      return { seeds: d.seeds || {}, winners: d.winners || {} };
    } catch (e) { return { seeds: {}, winners: {} }; }
  }
  function save() {
    try { localStorage.setItem(STORE, JSON.stringify(state)); } catch (e) {}
  }

  function slotOf(num, idx) { var m = byNum[num]; return m ? (idx === 1 ? m.s1 : m.s2) : null; }

  // المنتخب الذي يشغل خانة معيّنة (يحلّ win/lose تتبّعياً)
  function resolve(num, idx) {
    var s = slotOf(num, idx);
    if (!s) return null;
    if (s.type === 'seed') return state.seeds[num + '_' + idx] || null;
    if (s.type === 'win')  return state.winners[s.src] || null;
    if (s.type === 'lose') {
      var w = state.winners[s.src];
      if (!w) return null;
      var t = teamsOf(s.src);
      if (w === t[0]) return t[1];
      if (w === t[1]) return t[0];
      return null;
    }
    return null;
  }
  function teamsOf(num) { return [resolve(num, 1), resolve(num, 2)]; }

  function label(s) {
    if (s.type === 'win')  return (AR ? 'الفائز ' : (FR ? 'Vainqueur ' : 'Winner ')) + s.src;
    if (s.type === 'lose') return (AR ? 'الخاسر ' : (FR ? 'Perdant ' : 'Loser '))  + s.src;
    return s.label || '—';
  }

  function recompute() {
    // نظّف الفائزين الذين لم يعودوا ضمن خانتَي مباراتهم
    order.forEach(function (num) {
      var w = state.winners[num];
      if (!w) return;
      var t = teamsOf(num);
      if (t.indexOf(w) === -1) delete state.winners[num];
    });
    render();
    save();
  }

  function teamHTML(id) {
    var info = team[id] || { name: id, flag: '' };
    var flag = info.flag
      ? '<img class="flag" src="' + info.flag + '" alt="" width="22" height="16"> '
      : '';
    return flag + '<span>' + info.name + '</span>';
  }

  function render() {
    document.querySelectorAll('.bk-slot').forEach(function (el) {
      var num = parseInt(el.getAttribute('data-num'), 10);
      var idx = parseInt(el.getAttribute('data-slot'), 10);
      var s   = slotOf(num, idx);
      var id  = resolve(num, idx);

      if (s && s.type === 'seed') {
        var sel = el.querySelector('.bk-seed');
        if (sel && sel.value !== (state.seeds[num + '_' + idx] || '')) {
          sel.value = state.seeds[num + '_' + idx] || '';
        }
      } else {
        var span = el.querySelector('.bk-team');
        if (span) {
          if (id) { span.innerHTML = teamHTML(id); span.classList.remove('bk-team-empty'); }
          else    { span.textContent = label(s); span.classList.add('bk-team-empty'); }
        }
      }

      var won = id && state.winners[num] === id;
      el.classList.toggle('bk-won', !!won);
      var pick = el.querySelector('.bk-pick');
      if (pick) pick.disabled = !id;
    });

    // البطل
    var champ = finalNum != null ? state.winners[finalNum] : null;
    var box = document.getElementById('bkChampion');
    var name = document.getElementById('bkChampTeam');
    if (box && name) {
      if (champ) { name.innerHTML = teamHTML(champ); box.hidden = false; }
      else { box.hidden = true; }
    }
  }

  // ---- الأحداث ----
  document.querySelectorAll('.bk-slot').forEach(function (el) {
    var num = parseInt(el.getAttribute('data-num'), 10);
    var idx = parseInt(el.getAttribute('data-slot'), 10);

    var sel = el.querySelector('.bk-seed');
    if (sel) {
      sel.addEventListener('change', function () {
        var key = num + '_' + idx;
        if (sel.value) state.seeds[key] = sel.value; else delete state.seeds[key];
        recompute();
      });
    }
    var pick = el.querySelector('.bk-pick');
    if (pick) {
      pick.addEventListener('click', function () {
        var id = resolve(num, idx);
        if (!id) return;
        state.winners[num] = id;
        recompute();
      });
    }
  });

  var resetBtn = document.getElementById('bkReset');
  if (resetBtn) resetBtn.addEventListener('click', function () {
    state = { seeds: {}, winners: {} };
    document.querySelectorAll('.bk-seed').forEach(function (s) { s.value = ''; });
    recompute();
  });

  var shareBtn = document.getElementById('bkShare');
  if (shareBtn) shareBtn.addEventListener('click', function () {
    var champ = finalNum != null ? state.winners[finalNum] : null;
    var champName = champ ? (team[champ] ? team[champ].name : champ) : null;
    var txt = champName
      ? (AR ? 'توقّعي لبطل كأس العالم 2026: ' + champName + ' 🏆' : 'My FIFA World Cup 2026 champion pick: ' + champName + ' 🏆')
      : (AR ? 'املأ توقّعك لمشوار كأس العالم 2026!' : 'Fill your FIFA World Cup 2026 bracket!');
    var url = location.href;
    if (navigator.share) {
      navigator.share({ title: 'World Cup 2026', text: txt, url: url }).catch(function () {});
    } else if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(txt + ' ' + url).then(function () {
        shareBtn.textContent = AR ? 'تم النسخ ✓' : 'Copied ✓';
        setTimeout(function () { shareBtn.textContent = AR ? '📣 شارك توقّعي' : '📣 Share my bracket'; }, 1800);
      });
    }
  });

  recompute();
})();
