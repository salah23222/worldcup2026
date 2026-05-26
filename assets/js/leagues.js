/* ============================================================
   leagues.js — تفاعل "المجلس" (الدوريات الخاصّة)
   - leagues.php: إنشاء دوريّة + الانضمام برمز + قائمة دورياتي
   - league.php:  انضمام/مغادرة/إعادة تسمية + نسخ رابط الدعوة
   كل عمليات الكتابة عبر api/league.php مع ترويسة X-CSRF.
   نستخدم textContent دائماً (لا innerHTML مع بيانات المستخدم) لتفادي XSS.
   ============================================================ */
(function () {
  'use strict';

  var I18N = window.WC_LG_I18N || {};

  function postJSON(api, csrf, payload) {
    return fetch(api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      body: JSON.stringify(payload),
      cache: 'no-store'
    }).then(function (r) { return r.json(); });
  }

  function errText(res) {
    var key = (res && res.error) || '';
    return I18N[key] || I18N.err_generic || 'Error';
  }

  /* ============================================================
     صفحة المجلس (قائمة): leaguesApp
     ============================================================ */
  var app = document.getElementById('leaguesApp');
  if (app) {
    var API   = app.getAttribute('data-api') || '/api/league.php';
    var csrf  = app.getAttribute('data-csrf') || '';
    var BASE  = app.getAttribute('data-base') || (location.origin);
    var LANG  = app.getAttribute('data-lang') || 'ar';

    var createForm  = document.getElementById('lgCreateForm');
    var nameInput   = document.getElementById('lgName');
    var createErr   = document.getElementById('lgCreateError');
    var joinForm    = document.getElementById('lgJoinForm');
    var codeInput   = document.getElementById('lgCode');
    var joinErr     = document.getElementById('lgJoinError');
    var list        = document.getElementById('lgList');

    function showErr(el, msg) { if (el) { el.textContent = msg; el.hidden = false; } }
    function hideErr(el) { if (el) el.hidden = true; }

    function leagueHref(code) {
      return BASE + '/league.php?code=' + encodeURIComponent(code) + '&lang=' + encodeURIComponent(LANG);
    }

    /* إضافة عنصر دوريّة للقائمة (textContent فقط) */
    function addToList(lg) {
      var empty = document.getElementById('lgEmpty');
      if (empty && empty.parentNode) empty.parentNode.removeChild(empty);

      var a = document.createElement('a');
      a.className = 'lg-item';
      a.href = leagueHref(lg.code);

      var name = document.createElement('span');
      name.className = 'lg-item-name';
      name.textContent = lg.name;

      var meta = document.createElement('span');
      meta.className = 'lg-item-meta';
      var code = document.createElement('span');
      code.className = 'lg-item-code';
      code.textContent = lg.code;
      meta.appendChild(code);
      meta.appendChild(document.createTextNode(
        ' · ' + (parseInt(lg.members, 10) || 0) + ' ' + (I18N.members || '')
      ));
      if (lg.is_owner) {
        meta.appendChild(document.createTextNode(' · '));
        var badge = document.createElement('span');
        badge.className = 'lg-badge';
        badge.textContent = I18N.owner || '';
        meta.appendChild(badge);
      }

      a.appendChild(name);
      a.appendChild(meta);
      if (list) list.insertBefore(a, list.firstChild);
    }

    if (createForm) {
      createForm.addEventListener('submit', function (e) {
        e.preventDefault();
        hideErr(createErr);
        var name = (nameInput.value || '').trim();
        if (!name) return;
        var btn = createForm.querySelector('button');
        if (btn) btn.disabled = true;
        postJSON(API, csrf, { action: 'create', name: name })
          .then(function (res) {
            if (res && res.ok && res.league) {
              nameInput.value = '';
              addToList(res.league);
              // انتقل مباشرة لصفحة الدوريّة الجديدة
              location.href = leagueHref(res.league.code);
            } else {
              showErr(createErr, errText(res));
            }
          })
          .catch(function () { showErr(createErr, I18N.err_generic || 'Error'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    }

    if (joinForm) {
      joinForm.addEventListener('submit', function (e) {
        e.preventDefault();
        hideErr(joinErr);
        var code = (codeInput.value || '').trim().toUpperCase();
        if (!code) return;
        var btn = joinForm.querySelector('button');
        if (btn) btn.disabled = true;
        postJSON(API, csrf, { action: 'join', code: code })
          .then(function (res) {
            if (res && res.ok && res.league) {
              location.href = leagueHref(res.league.code);
            } else {
              showErr(joinErr, errText(res));
            }
          })
          .catch(function () { showErr(joinErr, I18N.err_generic || 'Error'); })
          .finally(function () { if (btn) btn.disabled = false; });
      });
    }
  }

  /* ============================================================
     صفحة دوريّة واحدة: leagueApp
     ============================================================ */
  var lapp = document.getElementById('leagueApp');
  if (lapp) {
    var lAPI  = lapp.getAttribute('data-api') || '/api/league.php';
    var lcsrf = lapp.getAttribute('data-csrf') || '';
    var lid   = lapp.getAttribute('data-id') || '';

    var statusEl = document.getElementById('lgActionStatus');
    function setStatus(msg) { if (statusEl) statusEl.textContent = msg; }

    /* نسخ رابط الدعوة */
    var copyBtn = document.getElementById('lgCopyBtn');
    var linkInput = document.getElementById('lgInviteLink');
    if (copyBtn && linkInput) {
      copyBtn.addEventListener('click', function () {
        var done = function () {
          var copied = copyBtn.getAttribute('data-copied') || 'Copied';
          var label  = copyBtn.getAttribute('data-label') || 'Copy';
          copyBtn.textContent = copied;
          setTimeout(function () { copyBtn.textContent = '🔗 ' + label; }, 1800);
        };
        try {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkInput.value).then(done, function () {
              linkInput.select(); document.execCommand('copy'); done();
            });
          } else {
            linkInput.select(); document.execCommand('copy'); done();
          }
        } catch (e) { linkInput.select(); }
      });
    }

    /* الانضمام لهذه الدوريّة */
    var joinBtn = document.getElementById('lgJoinBtn');
    if (joinBtn) {
      joinBtn.addEventListener('click', function () {
        joinBtn.disabled = true;
        postJSON(lAPI, lcsrf, { action: 'join', id: lid, code: codeFromTitle() })
          .then(function (res) {
            // الانضمام يحتاج الرمز؛ لكن في هذه الصفحة نعرفه — أعد التحميل لعرض الحالة كعضو.
            if (res && res.ok) { setStatus(I18N.joined || ''); location.reload(); }
            else { setStatus(errText(res)); joinBtn.disabled = false; }
          })
          .catch(function () { setStatus(I18N.err_generic || 'Error'); joinBtn.disabled = false; });
      });
    }

    /* المغادرة */
    var leaveBtn = document.getElementById('lgLeaveBtn');
    if (leaveBtn) {
      leaveBtn.addEventListener('click', function () {
        if (!window.confirm(I18N.confirm_leave || 'Leave?')) return;
        leaveBtn.disabled = true;
        postJSON(lAPI, lcsrf, { action: 'leave', id: lid })
          .then(function (res) {
            if (res && res.ok) { setStatus(I18N.left || ''); location.href = leaguesHref(); }
            else { setStatus(errText(res)); leaveBtn.disabled = false; }
          })
          .catch(function () { setStatus(I18N.err_generic || 'Error'); leaveBtn.disabled = false; });
      });
    }

    /* إعادة التسمية (المالك) */
    var renameBtn = document.getElementById('lgRenameBtn');
    if (renameBtn) {
      renameBtn.addEventListener('click', function () {
        var titleEl = document.getElementById('lgTitle');
        var cur = titleEl ? titleEl.textContent : '';
        var next = window.prompt(I18N.rename_prompt || 'New name:', cur);
        if (next === null) return;
        next = next.trim();
        if (!next || next === cur) return;
        postJSON(lAPI, lcsrf, { action: 'rename', id: lid, name: next })
          .then(function (res) {
            if (res && res.ok && res.league) {
              if (titleEl) titleEl.textContent = res.league.name;
            } else {
              setStatus(errText(res));
            }
          })
          .catch(function () { setStatus(I18N.err_generic || 'Error'); });
      });
    }

    function leaguesHref() {
      var base = location.origin + location.pathname.replace(/[^/]*$/, '');
      var lang = (document.documentElement.lang || 'ar');
      return base + 'leagues.php?lang=' + encodeURIComponent(lang);
    }
    /* الرمز ظاهر في الصفحة (lg-code-inline) — نستخدمه للانضمام من هذه الصفحة. */
    function codeFromTitle() {
      var el = document.querySelector('.lg-code-inline');
      return el ? (el.textContent || '').trim() : '';
    }
  }

})();
