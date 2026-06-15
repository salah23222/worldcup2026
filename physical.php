<?php
/**
 * physical.php — مستكشف البيانات البدنيّة للاعبين (بأسلوب FifaPhy) من تقارير FIFA.
 * ترتيب/بحث/تبديل (إجمالي ↔ لكل مباراة) — تفاعلي بالكامل، بهويّة الموقع.
 */
require __DIR__ . '/includes/bootstrap.php';

$ar   = (current_lang() === 'ar');
$L    = fn(string $a, string $e): string => $ar ? $a : $e;
// الصفحة تُرسَم بالمتصفّح من api/data.php?action=physical (قشرة خفيفة + إعادة محاولة)
// → تتجاوز تذبذب الخادم/كاش SW وتُحمّل دائماً. لا حساب ثقيل هنا.

$page_title = $L('البيانات البدنيّة', 'Physical data');
$page_desc  = $L('أرقام اللاعبين البدنيّة في كأس العالم 2026 — المسافة، العَدْوات، السرعة القصوى، رتّب وقارن.',
                 'Player physical numbers at the FIFA World Cup 2026 — distance, sprints, top speed.');
tpl('header');
?>

<div class="page-head">
  <h1>🏃 <?= e($L('البيانات البدنيّة', 'Physical data')) ?></h1>
  <p class="muted"><?= e($L('أرقام كل لاعب من تقارير FIFA الرسميّة — رتّب بأي عمود، ابحث، وبدّل بين الإجمالي والمعدّل لكل مباراة.',
                            'Every player\'s numbers from official FIFA reports — sort by any column, search, toggle total vs per-match.')) ?></p>
</div>

<div class="phys-controls">
  <input type="search" id="physSearch" class="phys-input" placeholder="<?= e($L('ابحث عن لاعب أو منتخب…', 'Search player or team…')) ?>">
  <select id="physTeam" class="phys-input phys-team">
    <option value=""><?= e($L('كل المنتخبات', 'All teams')) ?></option>
  </select>
  <div class="phys-toggle">
    <button type="button" class="phys-mode is-on" data-mode="total"><?= e($L('الإجمالي', 'Total')) ?></button>
    <button type="button" class="phys-mode" data-mode="avg"><?= e($L('لكل مباراة', 'Per match')) ?></button>
  </div>
</div>

<p id="physStatus" class="empty-note"><?= e($L('جارٍ التحميل…', 'Loading…')) ?></p>

<div class="lb-wrap">
  <table class="leaderboard phys-table" id="physTable" hidden>
    <thead>
      <tr>
        <th>#</th>
        <th class="lb-name"><?= e($L('اللاعب', 'Player')) ?></th>
        <th class="ph-sort" data-k="m"><?= e($L('مباريات', 'M')) ?></th>
        <th class="ph-sort is-sort" data-k="dist"><?= e($L('المسافة (كم)', 'Distance (km)')) ?></th>
        <th class="ph-sort" data-k="sprints"><?= e($L('عَدْوات', 'Sprints')) ?></th>
        <th class="ph-sort" data-k="hsr"><?= e($L('ركضات عالية', 'HS runs')) ?></th>
        <th class="ph-sort" data-k="top"><?= e($L('سرعة قصوى', 'Top speed')) ?></th>
      </tr>
    </thead>
    <tbody id="physBody"></tbody>
  </table>
</div>
<p class="video-credit"><?= e($L('المصدر: المركز الفنّي لـFIFA — تقارير ما بعد المباراة. «لكل مباراة» = الإجمالي ÷ عدد المباريات.',
                                  'Source: FIFA Training Centre — Post-Match reports. “Per match” = total ÷ matches played.')) ?></p>

<style>
.phys-controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:10px 0 14px}
.phys-input{flex:1;min-width:200px;padding:10px 14px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.05);color:inherit;font:inherit}
.phys-toggle{display:flex;border:1px solid rgba(255,255,255,.15);border-radius:10px;overflow:hidden}
.phys-mode{padding:9px 18px;background:transparent;color:inherit;border:0;cursor:pointer;font-weight:700;font:inherit}
.phys-mode.is-on{background:#ffc846;color:#0a1626}
.phys-table th.ph-sort{cursor:pointer;white-space:nowrap}
.phys-table th.ph-sort.is-sort{color:#ffc846}
.phys-table .ph-v{font-variant-numeric:tabular-nums;font-weight:700}
.phys-table .ph-rank{font-weight:800;color:#ffc846}
.phys-table .ph-photo{width:30px;height:30px;border-radius:50%;object-fit:cover;object-position:top center;background:#0e1b34;border:1.5px solid rgba(255,255,255,.18);vertical-align:middle;margin-inline-end:6px}
.phys-table .ph-team{display:block;font-size:.78em;opacity:.7}
.phys-team{flex:0 0 auto;cursor:pointer;max-width:220px}
.phys-team option{color:#0a1626;background:#fff}
.phys-table tbody tr.ph-row{cursor:pointer}
.phys-table tbody tr.ph-row:hover{background:rgba(255,255,255,.05)}
.phys-table tbody tr.ph-row.is-open{background:rgba(255,200,70,.10)}
.ph-detail>td{background:rgba(255,255,255,.04);padding:16px}
.ph-detail-grid{display:flex;flex-wrap:wrap;gap:20px;align-items:center;justify-content:center}
.ph-radar{flex:0 0 auto}
.ph-chips{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}
.ph-chip{background:rgba(255,255,255,.06);border-radius:10px;padding:8px 14px;text-align:center;min-width:88px}
.ph-chip b{display:block;font-size:1.15rem;color:#ffc846;font-variant-numeric:tabular-nums}
.ph-chip span{font-size:.72rem;opacity:.75}
.ph-radar-wrap{text-align:center}
.ph-cap{font-size:.72rem;opacity:.7;margin:2px auto 0;max-width:230px}
.ph-detail>td .ph-block{margin:14px auto 0;max-width:560px}
.ph-blk-t{font-size:.85rem;color:#ffc846;margin-bottom:6px;font-weight:700;text-align:center}
.ph-zones{display:flex;height:13px;border-radius:7px;overflow:hidden}
.ph-zones i{display:block;height:100%}
.ph-line{font-size:.8rem;opacity:.85;margin:6px 0 0;text-align:center}
</style>
<script>
(function(){
  var API   = <?= json_encode(url('api/data.php', ['action' => 'physical']), JSON_UNESCAPED_SLASHES) ?>;
  var MSG = {
    empty: <?= json_encode($L('تظهر البيانات بعد لعب المباريات.', 'Data appears once matches are played.'), JSON_UNESCAPED_UNICODE) ?>,
    err:   <?= json_encode($L('تعذّر تحميل البيانات.', 'Could not load data.'), JSON_UNESCAPED_UNICODE) ?>,
    retry: <?= json_encode($L('إعادة المحاولة', 'Retry'), JSON_UNESCAPED_UNICODE) ?>
  };
  var table  = document.getElementById('physTable');
  var tbody  = document.getElementById('physBody');
  var status = document.getElementById('physStatus');
  var rows = [], mode = 'total', sortK = 'dist', wired = false, MAX = {};
  var LBL = {
    dist:    <?= json_encode($L('المسافة', 'Distance'), JSON_UNESCAPED_UNICODE) ?>,
    sprints: <?= json_encode($L('عَدْوات', 'Sprints'), JSON_UNESCAPED_UNICODE) ?>,
    hsr:     <?= json_encode($L('ركضات عالية', 'HS runs'), JSON_UNESCAPED_UNICODE) ?>,
    top:     <?= json_encode($L('سرعة قصوى', 'Top speed'), JSON_UNESCAPED_UNICODE) ?>,
    km:      <?= json_encode($L('كم', 'km'), JSON_UNESCAPED_UNICODE) ?>,
    kmh:     <?= json_encode($L('كم/س', 'km/h'), JSON_UNESCAPED_UNICODE) ?>,
    perM:    <?= json_encode($L('لكل مباراة', 'per match'), JSON_UNESCAPED_UNICODE) ?>,
    share:   <?= json_encode($L('النسبة مقارنةً بأفضل لاعب في البطولة', 'share of the tournament-best player'), JSON_UNESCAPED_UNICODE) ?>,
    zonesT:  <?= json_encode($L('مناطق السرعة (1→5)', 'Speed zones (1→5)'), JSON_UNESCAPED_UNICODE) ?>,
    lbT:     <?= json_encode($L('اختراق الخطوط', 'Line breaks'), JSON_UNESCAPED_UNICODE) ?>,
    att:     <?= json_encode($L('محاولات', 'Att'), JSON_UNESCAPED_UNICODE) ?>,
    comp:    <?= json_encode($L('مكتملة', 'Comp'), JSON_UNESCAPED_UNICODE) ?>,
    acc:     <?= json_encode($L('دقّة', 'Acc'), JSON_UNESCAPED_UNICODE) ?>,
    crossesT:<?= json_encode($L('العرضيّات', 'Crosses'), JSON_UNESCAPED_UNICODE) ?>,
    total:   <?= json_encode($L('إجمالي', 'Total'), JSON_UNESCAPED_UNICODE) ?>,
    dir:     <?= json_encode([$L('عبر','Through'), $L('حول','Around'), $L('فوق','Over')], JSON_UNESCAPED_UNICODE) ?>,
    typ:     <?= json_encode([$L('تمريرة','Pass'), $L('عرضيّة','Cross'), $L('تقدّم','Prog')], JSON_UNESCAPED_UNICODE) ?>
  };

  function val(r, k){ return parseFloat(r.getAttribute('data-' + k)) || 0; }
  function metric(r, k){ var v = val(r, k); if (mode === 'avg' && k !== 'top' && k !== 'm') v = v / (val(r, 'm') || 1); return v; }

  // هيكل الصفّ ثابت (innerHTML بلا أي بيانات) ثم نملؤه عبر textContent/DOM — آمن ضد XSS.
  function build(players){
    var frag = document.createDocumentFragment();
    rows = [];
    players.forEach(function(p){
      var tr = document.createElement('tr');
      tr.className = 'ph-row';
      tr._p = p;                                    // بيانات اللاعب الكاملة (للتفاصيل عند النقر)
      tr.setAttribute('data-name', String(p.name || '').toLowerCase());
      tr.setAttribute('data-team', (String(p.teamAr || '') + ' ' + String(p.team || '')).toLowerCase());
      tr.setAttribute('data-m', p.m); tr.setAttribute('data-dist', p.dist);
      tr.setAttribute('data-sprints', p.sprints); tr.setAttribute('data-hsr', p.hsr); tr.setAttribute('data-top', p.top);
      tr.innerHTML = '<td class="lb-rank ph-rank"></td><td class="lb-name"></td><td></td>'
        + '<td class="ph-v" data-c="dist"></td><td class="ph-v" data-c="sprints"></td>'
        + '<td class="ph-v" data-c="hsr"></td><td class="ph-v" data-c="top"></td>';
      var nameTd = tr.children[1];
      if (p.photo) {
        var pho = document.createElement('img');
        pho.className = 'ph-photo'; pho.src = p.photo; pho.loading = 'lazy'; pho.alt = '';
        pho.onerror = function(){ this.remove(); };
        nameTd.appendChild(pho);
      }
      if (p.flag) {
        var img = document.createElement('img');
        img.className = 'flag'; img.src = p.flag; img.width = 32; img.height = 24; img.loading = 'lazy'; img.alt = '';
        nameTd.appendChild(img); nameTd.appendChild(document.createTextNode(' '));
      }
      nameTd.appendChild(document.createTextNode(String(p.name || '') + ' '));
      var sp = document.createElement('span'); sp.className = 'muted ph-team'; sp.textContent = String(p.teamAr || '');
      nameTd.appendChild(sp);
      tr.children[2].textContent = (p.m | 0);
      tr.children[6].textContent = (Math.round((+p.top) * 10) / 10);
      rows.push(tr); frag.appendChild(tr);
    });
    tbody.innerHTML = ''; tbody.appendChild(frag);
    computeMax(); fillTeams(players);
  }
  function computeMax(){
    MAX = { dist: 0, sprints: 0, hsr: 0, top: 0 };
    rows.forEach(function(r){
      var m = val(r, 'm') || 1;
      MAX.dist = Math.max(MAX.dist, val(r, 'dist') / m);
      MAX.sprints = Math.max(MAX.sprints, val(r, 'sprints') / m);
      MAX.hsr = Math.max(MAX.hsr, val(r, 'hsr') / m);
      MAX.top = Math.max(MAX.top, val(r, 'top'));
    });
  }
  function fillTeams(players){
    var sel = document.getElementById('physTeam'); if (!sel) return;
    var seen = {}, names = [];
    players.forEach(function(p){ var t = String(p.teamAr || ''); if (t && !seen[t]) { seen[t] = 1; names.push(t); } });
    names.sort();
    names.forEach(function(t){ var o = document.createElement('option'); o.value = t; o.textContent = t; sel.appendChild(o); });
  }
  function render(){
    rows.forEach(function(r){
      var m = val(r, 'm') || 1, f = (mode === 'avg') ? m : 1;
      r.querySelector('[data-c=dist]').textContent    = (val(r, 'dist') / 1000 / f).toFixed(1);
      r.querySelector('[data-c=sprints]').textContent = (mode === 'avg') ? (val(r, 'sprints') / m).toFixed(1) : val(r, 'sprints');
      r.querySelector('[data-c=hsr]').textContent     = (mode === 'avg') ? (val(r, 'hsr') / m).toFixed(1) : val(r, 'hsr');
    });
  }
  function closeDetails(){
    [].forEach.call(tbody.querySelectorAll('tr.ph-detail'), function(d){ d.parentNode.removeChild(d); });
    rows.forEach(function(r){ r.classList.remove('is-open'); });
  }
  function applyFilter(){
    var s = document.getElementById('physSearch'), sel = document.getElementById('physTeam');
    var q = s ? s.value.trim().toLowerCase() : '', tm = sel ? sel.value.trim().toLowerCase() : '';
    closeDetails();
    rows.forEach(function(r){
      var name = r.getAttribute('data-name'), team = r.getAttribute('data-team');
      var hit = (!q || name.indexOf(q) >= 0 || team.indexOf(q) >= 0) && (!tm || team.indexOf(tm) >= 0);
      r.style.display = hit ? '' : 'none';
    });
  }
  // شبكة عنكبوتيّة (4 محاور) باسم المعيار + قيمته — كل القيم رقميّة/مسمّيات ثابتة (آمنة).
  function radarSvg(f, vals){
    var cx = 112, cy = 96, R = 62, ang = [-Math.PI/2, 0, Math.PI/2, Math.PI];
    function pt(fr, i){ return [(cx + fr*R*Math.cos(ang[i])).toFixed(1), (cy + fr*R*Math.sin(ang[i])).toFixed(1)]; }
    var grid = '';
    [0.25, 0.5, 0.75, 1].forEach(function(g){
      grid += '<polygon points="' + [0,1,2,3].map(function(i){ return pt(g, i).join(','); }).join(' ') + '" fill="none" stroke="rgba(255,255,255,.13)"/>';
    });
    var axes = '';
    [0,1,2,3].forEach(function(i){ var p = pt(1, i); axes += '<line x1="'+cx+'" y1="'+cy+'" x2="'+p[0]+'" y2="'+p[1]+'" stroke="rgba(255,255,255,.13)"/>'; });
    var poly = [0,1,2,3].map(function(i){ return pt(Math.max(0.04, f[i] || 0), i).join(','); }).join(' ');
    var labels = [LBL.dist, LBL.sprints, LBL.hsr, LBL.top], anc = ['middle','start','middle','end'], dy = [-10, 0, 22, 0], labs = '';
    [0,1,2,3].forEach(function(i){
      var p = pt(1.24, i), x = p[0], y = parseFloat(p[1]) + dy[i];
      labs += '<text x="'+x+'" y="'+y+'" text-anchor="'+anc[i]+'" fill="#cfe0f5" font-size="11">' + labels[i]
            + '<tspan x="'+x+'" dy="13" fill="#ffc846" font-size="11">' + vals[i] + '</tspan></text>';
    });
    return '<svg viewBox="0 0 224 200" width="232" height="200" class="ph-radar" role="img">' + grid + axes
      + '<polygon points="' + poly + '" fill="rgba(255,200,70,.35)" stroke="#ffc846" stroke-width="2"/>' + labs + '</svg>';
  }
  function chip(v, label){ return '<div class="ph-chip"><b>' + v + '</b><span>' + label + '</span></div>'; }
  function bar(zones){
    var sh = ['#26408b','#3a5bbf','#a8cdf5','#ffc846','#ff7a45'], tot = 0, i;
    for (i = 0; i < 5; i++) tot += (+zones[i] || 0);
    if (tot <= 0) return '';
    var seg = '';
    for (i = 0; i < 5; i++) seg += '<i style="width:' + ((+zones[i]||0)/tot*100).toFixed(1) + '%;background:' + sh[i] + '"></i>';
    return '<div class="ph-block"><div class="ph-blk-t">' + LBL.zonesT + '</div><div class="ph-zones">' + seg + '</div></div>';
  }
  function lbBlock(lb, lbv){
    var att = +lb[0] || 0; if (att <= 0) return '';
    var comp = +lb[1] || 0, pct = Math.round(comp / att * 100);
    var line = LBL.dir[0] + ' ' + (+lbv[0]||0) + ' · ' + LBL.dir[1] + ' ' + (+lbv[1]||0) + ' · ' + LBL.dir[2] + ' ' + (+lbv[2]||0)
             + '  —  ' + LBL.typ[0] + ' ' + (+lbv[3]||0) + ' · ' + LBL.typ[1] + ' ' + (+lbv[4]||0) + ' · ' + LBL.typ[2] + ' ' + (+lbv[5]||0);
    return '<div class="ph-block"><div class="ph-blk-t">' + LBL.lbT + '</div><div class="ph-chips">'
      + chip(att, LBL.att) + chip(comp, LBL.comp) + chip(pct + '%', LBL.acc) + '</div><p class="ph-line">' + line + '</p></div>';
  }
  function crossBlock(cross){
    var total = +cross[6] || 0; if (total <= 0) return '';
    return '<div class="ph-block"><div class="ph-blk-t">' + LBL.crossesT + '</div><div class="ph-chips">' + chip(total, LBL.total) + '</div></div>';
  }
  function toggleDetail(tr){
    if (tr.classList.contains('is-open')) { closeDetails(); return; }
    closeDetails();
    tr.classList.add('is-open');
    var p = tr._p || {}, m = val(tr, 'm') || 1;
    var f = [ (val(tr,'dist')/m)/(MAX.dist||1), (val(tr,'sprints')/m)/(MAX.sprints||1), (val(tr,'hsr')/m)/(MAX.hsr||1), val(tr,'top')/(MAX.top||1) ];
    var vals = [ (val(tr,'dist')/1000/m).toFixed(1) + ' ' + LBL.km, (val(tr,'sprints')/m).toFixed(1), (val(tr,'hsr')/m).toFixed(1), (Math.round(val(tr,'top')*10)/10) + ' ' + LBL.kmh ];
    var chips = chip((val(tr,'dist')/1000/m).toFixed(1), LBL.dist + ' ' + LBL.km + ' /' + LBL.perM)
      + chip((val(tr,'sprints')/m).toFixed(1), LBL.sprints + ' /' + LBL.perM)
      + chip((val(tr,'hsr')/m).toFixed(1), LBL.hsr + ' /' + LBL.perM)
      + chip((Math.round(val(tr,'top')*10)/10) + ' ' + LBL.kmh, LBL.top);
    var rich = bar(p.zones || []) + lbBlock(p.lb || [0, 0], p.lbv || []) + crossBlock(p.cross || []);
    var det = document.createElement('tr'); det.className = 'ph-detail';
    var td = document.createElement('td'); td.colSpan = 7;
    td.innerHTML = '<div class="ph-detail-grid"><div class="ph-radar-wrap">' + radarSvg(f, vals)
      + '<p class="ph-cap">' + LBL.share + '</p></div><div class="ph-chips">' + chips + '</div></div>' + rich;
    det.appendChild(td);
    tr.parentNode.insertBefore(det, tr.nextSibling);
  }
  function sortBy(k){
    sortK = k; closeDetails();
    rows.sort(function(a, b){ return metric(b, k) - metric(a, k); });
    rows.forEach(function(r, i){ tbody.appendChild(r); r.querySelector('.ph-rank').textContent = i + 1; });
  }
  function wire(){
    if (wired) return; wired = true;
    [].forEach.call(table.querySelectorAll('.ph-sort'), function(th){
      th.addEventListener('click', function(){
        [].forEach.call(table.querySelectorAll('.ph-sort'), function(x){ x.classList.remove('is-sort'); });
        th.classList.add('is-sort'); sortBy(th.getAttribute('data-k'));
      });
    });
    [].forEach.call(document.querySelectorAll('.phys-mode'), function(btn){
      btn.addEventListener('click', function(){
        [].forEach.call(document.querySelectorAll('.phys-mode'), function(x){ x.classList.remove('is-on'); });
        btn.classList.add('is-on'); mode = btn.getAttribute('data-mode'); render(); sortBy(sortK);
      });
    });
    var s = document.getElementById('physSearch'); if (s) s.addEventListener('input', applyFilter);
    var sel = document.getElementById('physTeam'); if (sel) sel.addEventListener('change', applyFilter);
    tbody.addEventListener('click', function(e){
      var tr = e.target.closest ? e.target.closest('tr.ph-row') : null;
      if (tr) toggleDetail(tr);
    });
  }
  function showError(){
    status.hidden = false;
    status.textContent = MSG.err + ' ';
    var b = document.createElement('button'); b.className = 'btn btn-sm'; b.textContent = MSG.retry;
    b.onclick = function(){ status.textContent = '…'; load(1); };
    status.appendChild(b);
  }
  function load(attempt){
    attempt = attempt || 1;
    fetch(API, { cache: 'no-store' })
      .then(function(r){ if (!r.ok) throw 0; return r.json(); })
      .then(function(d){
        var p = (d && d.players) || [];
        if (!p.length){ status.hidden = false; status.textContent = MSG.empty; return; }
        build(p); render(); sortBy('dist'); wire();
        status.hidden = true; table.hidden = false;
      })
      .catch(function(){
        if (attempt < 4) { setTimeout(function(){ load(attempt + 1); }, 700 * attempt); }
        else { showError(); }
      });
  }
  load();
})();
</script>

<?php tpl('footer'); ?>
