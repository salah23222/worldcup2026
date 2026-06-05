<?php
/**
 * today.php — «اليوم» — مركز الطقس اليومي.
 * ============================================================
 * صفحة واحدة تجمع طقساً يوميّاً للزائر:
 *   • تحية + تاريخ اليوم
 *   • مباراة اليوم (أبرز مباراة اليوم أو أقرب قادمة) مع رابط لتفاصيلها
 *   • تشويق تحدّي المعرفة (نقاط + حالة اليوم) ودعوة لصفحة الأسئلة
 *   • دعوة «توقّع اليوم» لصفحة التوقعات
 *   • استطلاع الجمهور (شاشة ثانية)
 *   • (اختياري) «قصة اليوم» بالذكاء الاصطناعي — تظهر فقط إن توفّرت
 *
 * صفحة شخصية/ديناميكية → يجب استثناؤها من كاش الصفحات (PageCache $noCache).
 * ============================================================
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/templates/match_card.php';   // يوفّر render_match_card()

// قد لا يكون Polls مُحمّلاً من bootstrap بعد (يُضاف لاحقاً عبر التوصيل) → حمّله بأمان.
if (!class_exists('Polls')) {
    require_once __DIR__ . '/includes/Polls.php';
}

$lang = current_lang();
$ar   = ($lang === 'ar');
$L    = fn(string $a, string $e, string $f = '') => $ar ? $a : (($lang === 'fr') ? ($f ?: $e) : $e);

// نضمن رمز CSRF قبل أي إخراج HTML (يستخدمه استطلاع الجمهور للتصويت).
$csrf = Predictions::ensureCsrf();

// ---------- تحية حسب وقت اليوم ----------
$hour = (int)date('G');
if ($hour < 5)        { $greet = $L('سهرة سعيدة', 'Good night', 'Bonne nuit'); }
elseif ($hour < 12)   { $greet = $L('صباح الخير', 'Good morning', 'Bonjour'); }
elseif ($hour < 17)   { $greet = $L('نهارك سعيد', 'Good afternoon', 'Bon après-midi'); }
else                  { $greet = $L('مساء الخير', 'Good evening', 'Bonsoir'); }

$todayTs = time();

// ---------- مباراة اليوم ----------
// أبرز مباراة اليوم (مباشرة لها الأولوية، ثم الأقرب)، وإلا أقرب مباراة قادمة عموماً.
$matchOfDay = null;
$todays = DataService::matchesOnDate(date('Y-m-d'));
if (!empty($todays)) {
    foreach ($todays as $m) {
        if (($m['_status'] ?? '') === 'live') { $matchOfDay = $m; break; }
    }
    if ($matchOfDay === null) {
        // أقرب مباراة لم تنتهِ اليوم، وإلا أوّل مباراة اليوم.
        foreach ($todays as $m) {
            if (($m['_status'] ?? '') !== 'finished') { $matchOfDay = $m; break; }
        }
        if ($matchOfDay === null) $matchOfDay = $todays[0];
    }
}
if ($matchOfDay === null) {
    $up = DataService::upcomingMatches(1);
    $matchOfDay = $up[0] ?? null;
}

// ---------- حالة تحدّي المعرفة ----------
$triv = Predictions::triviaInfo();

// ---------- قصة اليوم (اختيارية، دفاعية تماماً) ----------
$story = (class_exists('AiContent') && method_exists('AiContent', 'dailyStory'))
       ? @AiContent::dailyStory(current_lang())
       : null;
if (!is_string($story) || trim($story) === '') { $story = null; }

$page_title = $L('اليوم', 'Today', "Aujourd'hui");
$page_desc  = $L('جولتك اليومية: مباراة اليوم، توقّع، تحدّي معرفة، واستطلاع الجمهور.',
                 'Your daily ritual: match of the day, a prediction, trivia and the crowd poll.',
                 'Votre rituel quotidien : match du jour, prédiction, quiz et sondage du public.');
tpl('header');
?>

<style>
/* تنسيق محلّي خفيف يتناغم مع ثيم الموقع الكحلي (لا يلمس style.css) */
.today-hero{background:linear-gradient(135deg,#0e1d36,#13233d);border:1px solid rgba(255,255,255,.08);
  border-radius:16px;padding:22px 20px;margin:6px 0 18px}
.today-hero .greet{font-size:1.5rem;font-weight:900;margin:0 0 4px}
.today-hero .date{opacity:.78;margin:0;font-size:.98rem}
.today-grid{display:grid;gap:16px;grid-template-columns:1fr}
@media(min-width:760px){.today-grid{grid-template-columns:1fr 1fr}}
.today-card{background:#0e1d36;border:1px solid rgba(255,255,255,.08);border-radius:14px;
  padding:18px;display:flex;flex-direction:column}
.today-card h2{font-size:1.05rem;margin:0 0 12px;display:flex;align-items:center;gap:8px}
.today-card .tc-cta{margin-top:auto;padding-top:12px}
.today-span2{grid-column:1/-1}
.today-stat{display:flex;align-items:baseline;gap:8px;margin:0 0 6px}
.today-stat b{font-size:1.7rem;font-weight:900;color:#36c08f}
.today-muted{opacity:.78;line-height:1.7;margin:0 0 10px}
.today-tag{display:inline-block;font-size:.8rem;padding:2px 10px;border-radius:999px;
  background:rgba(54,192,143,.15);color:#36c08f;font-weight:700}
.today-tag.live{background:rgba(231,76,60,.18);color:#ff7a6b}
.today-story{white-space:pre-line;line-height:1.9}
.today-mod-link{align-self:flex-start}
/* أداة الاستطلاع */
[data-poll] .poll-card{margin:0}
.poll-q{font-size:1.02rem;margin:0 0 14px}
.poll-options{display:flex;flex-direction:column;gap:8px}
.poll-opt{font:inherit;text-align:start;background:#13233d;color:#eef2f7;border:1px solid rgba(255,255,255,.12);
  border-radius:10px;padding:11px 14px;cursor:pointer;font-weight:600}
.poll-opt:hover{border-color:#36c08f}
.poll-opt:disabled{opacity:.55;cursor:default}
.poll-result{position:relative;border-radius:10px;overflow:hidden;background:#13233d;
  border:1px solid rgba(255,255,255,.10);padding:11px 14px;display:flex;align-items:center;gap:10px}
.poll-result.is-choice{border-color:#36c08f}
.poll-bar{position:absolute;inset-inline-start:0;top:0;bottom:0;background:rgba(54,192,143,.22);width:0;z-index:0}
.poll-label,.poll-pct{position:relative;z-index:1}
.poll-label{flex:1}
.poll-pct{font-weight:800;color:#36c08f}
.poll-foot{display:flex;justify-content:space-between;align-items:center;margin-top:12px;
  font-size:.85rem;opacity:.85}
.poll-note{color:#36c08f;font-weight:700}
.poll-msg{opacity:.75;margin:0}
</style>

<div class="today-hero">
  <p class="greet"><?= e($greet) ?> 👋</p>
  <p class="date"><?= e(fmt_date($todayTs)) ?></p>
</div>

<div class="today-grid">

  <!-- ============ مباراة اليوم ============ -->
  <section class="today-card today-span2">
    <h2>⚽ <?= e($L('مباراة اليوم', 'Match of the day', 'Match du jour')) ?></h2>
    <?php if ($matchOfDay !== null):
      $isLive = ($matchOfDay['_status'] ?? '') === 'live';
      $mTs    = DataService::matchTimestamp($matchOfDay);
      $detail = url('match.php', ['id' => (int)($matchOfDay['_index'] ?? 0)]);
    ?>
      <p>
        <span class="today-tag <?= $isLive ? 'live' : '' ?>">
          <?= e($isLive ? $L('مباشر الآن', 'Live now', 'En direct') : $L('قادمة', 'Upcoming', 'À venir')) ?>
        </span>
      </p>
      <div style="margin:6px 0 4px"><?php render_match_card($matchOfDay); ?></div>
    <?php else: ?>
      <p class="today-muted"><?= e($L('لا توجد مباراة مبرمجة لليوم — تابع المباريات القادمة.',
                                        'No match scheduled today — check the upcoming fixtures.',
                                        'Aucun match prévu aujourd\'hui — consultez les prochains matchs.')) ?></p>
      <p class="tc-cta">
        <a class="btn btn-cta" href="<?= e(url('matches.php')) ?>"><?= e($L('كل المباريات', 'All matches', 'Tous les matchs')) ?></a>
      </p>
    <?php endif; ?>
  </section>

  <!-- ============ تحدّي المعرفة ============ -->
  <section class="today-card">
    <h2>🧠 <?= e($L('تحدّي اليوم', "Today's trivia", 'Quiz du jour')) ?></h2>
    <div class="today-stat">
      <b><?= (int)($triv['total'] ?? 0) ?></b>
      <span class="today-muted" style="margin:0"><?= e($L('نقطة في رصيدك', 'points in your bank', 'points dans votre réserve')) ?></span>
    </div>
    <?php if (!empty($triv['answered_today'])): ?>
      <p class="today-muted"><?= e($L('أجبت تحدّي اليوم ✅ — عُد غداً لسؤال جديد.',
                                      'Answered today ✅ — come back tomorrow for a new one.',
                                      'Défi répondu aujourd\'hui ✅ — revenez demain pour une nouvelle question.')) ?></p>
    <?php elseif (empty($triv['registered'])): ?>
      <p class="today-muted"><?= e($L('سجّل اسمك لتجمع النقاط وتظهر في لوحة الصدارة.',
                                      'Register a nickname to earn points and join the leaderboard.',
                                      'Enregistrez un pseudo pour gagner des points et apparaître au classement.')) ?></p>
    <?php else: ?>
      <p class="today-muted"><?= e($L('سؤال اليوم بانتظارك — أجب واكسب حتى 3 نقاط.',
                                      "Today's question is waiting — answer for up to 3 points.",
                                      'La question du jour vous attend — répondez pour gagner jusqu\'à 3 points.')) ?></p>
    <?php endif; ?>
    <p class="tc-cta">
      <a class="btn btn-cta" href="<?= e(url('trivia.php')) ?>"><?= e($L('العب تحدّي اليوم', "Play today's trivia", 'Jouer au quiz du jour')) ?></a>
    </p>
  </section>

  <!-- ============ توقّع اليوم ============ -->
  <section class="today-card">
    <h2>🎯 <?= e($L('توقّع اليوم', "Today's prediction", 'Pronostic du jour')) ?></h2>
    <p class="today-muted">
      <?php if ($matchOfDay !== null && !Predictions::isLocked($matchOfDay)
                && is_real_team($matchOfDay['team1'] ?? '') && is_real_team($matchOfDay['team2'] ?? '')): ?>
        <?= e($L('توقّع نتيجة ', 'Predict the score for ', 'Prédisez le score de ')
              . team_name($matchOfDay['team1']) . ' ' . $L('و', 'vs', ' vs ') . ' ' . team_name($matchOfDay['team2'])
              . $L(' قبل انطلاقها.', ' before kick-off.', ' avant le coup d\'envoi.')) ?>
      <?php else: ?>
        <?= e($L('توقّع نتائج المباريات القادمة وتسلّق لوحة الصدارة.',
                 'Predict upcoming results and climb the leaderboard.',
                 'Prédisez les résultats et grimpez au classement.')) ?>
      <?php endif; ?>
    </p>
    <p class="tc-cta">
      <a class="btn btn-cta" href="<?= e(url('predict.php')) ?>"><?= e($L('سجّل توقّعك', 'Make your prediction', 'Faites votre prédiction')) ?></a>
    </p>
  </section>

  <!-- ============ استطلاع الجمهور ============ -->
  <section class="today-card today-span2">
    <h2>📊 <?= e($L('رأي الجمهور', 'The crowd says', 'L\'avis du public')) ?></h2>
    <div data-poll data-poll-api="<?= e(rtrim(SITE_URL, '/') . '/api/poll.php') ?>"></div>
  </section>

  <?php if ($story !== null): ?>
  <!-- ============ قصة اليوم (ذكاء اصطناعي، اختيارية) ============ -->
  <section class="today-card today-span2">
    <h2>📰 <?= e($L('قصة اليوم', 'Story of the day', 'Histoire du jour')) ?></h2>
    <p class="today-story today-muted"><?= e($story) ?></p>
  </section>
  <?php endif; ?>

</div>

<?php
$pollV = @filemtime(__DIR__ . '/assets/js/poll.js') ?: 1;
$jsBase = e(rtrim(SITE_URL, '/'));
?>
<script src="<?= $jsBase ?>/assets/js/poll.js?v=<?= $pollV ?>" defer></script>

<?php tpl('footer'); ?>
