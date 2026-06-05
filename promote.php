<?php
/**
 * promote.php — صفحة التسويق:
 *   • للمسجَّلين: رابطك المخصّص + إحصاءات + لوحة شرف.
 *   • للزوّار: تشجيع على التسجيل، لكن صندوق التسويق متاح لهم بالرابط العام.
 *   • صندوق التسويق: 6 قوالب AR + 6 قوالب EN، ديناميكية بالعدّ التنازلي ورابط المُسوّق.
 */
require __DIR__ . '/includes/bootstrap.php';

$me = Database::available() && Auth::check() ? Auth::user() : null;

$page_title = 'سوّق ' . SITE_NAME_AR;
$page_desc  = 'أرسل رابطك المخصّص واكسب نقاطاً إضافية في توقعاتك.';
tpl('header');
?>

<div class="page-head">
  <h1>📣 <?= e(t('promote_title')) ?></h1>
  <p class="muted"><?= e(t('promote_sub')) ?></p>
</div>

<?php if (!Database::available()): ?>
  <div class="alert muted">
    <?= e(t('accounts_disabled')) ?>
    — <?= e(match(current_lang()) { 'ar' => 'صندوق التسويق أسفل يعمل بالرابط العام.', 'fr' => 'Le kit marketing ci-dessous fonctionne avec le lien générique.', default => 'The marketing kit below still works with the general link.' }) ?>
  </div>

<?php else: ?>

  <?php if ($me):
    $uid          = (int)$me['id'];
    $username     = (string)$me['username'];
    $link         = Referrals::linkFor($username);
    $counted      = Referrals::countCounted($uid);
    $total        = Referrals::totalReferrals($uid);
    $today        = Referrals::countToday($uid);
    $pts          = Referrals::pointsFor($uid);
    $recent       = Referrals::recent($uid, 15);
    $top          = Referrals::topPromoters(10);
    $progressLife = min(100, (int)round($counted / Referrals::LIFETIME_CAP * 100));
    $progressDay  = min(100, (int)round($today   / Referrals::DAILY_CAP    * 100));
    $shareText    = ($me['display_name'] ?? $username) . ' دعاك للمنافسة في توقعات كأس العالم 2026 — ' . SITE_NAME_AR;
  ?>

  <!-- ===== لوحة المُسجَّل: رابطك الشخصي ===== -->
  <div class="promote-link-card">
    <p class="promote-label"><?= e(t('your_ref_link')) ?></p>
    <div class="promote-link-row">
      <input id="refLink" type="text" readonly value="<?= e($link) ?>">
      <button type="button" id="refCopy" class="btn btn-accent"
              data-text="<?= e(t('copy_link')) ?>" data-done="<?= e(t('link_copied')) ?>">
        🔗 <?= e(t('copy_link')) ?>
      </button>
    </div>
    <p class="promote-hint"><?= e(t('promote_hint')) ?></p>
    <?php render_share($link, $shareText); ?>
  </div>

  <!-- إحصاءات -->
  <div class="promote-stats">
    <div class="stat-card"><span class="stat-num"><?= (int)$pts ?></span><span class="stat-lbl">⭐ <?= e(t('points_earned')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int)$counted ?></span><span class="stat-lbl">✅ <?= e(t('counted_referrals')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int)$total ?></span><span class="stat-lbl">👥 <?= e(t('total_referrals')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int)$today ?>/<?= Referrals::DAILY_CAP ?></span><span class="stat-lbl">📅 <?= e(t('today_count')) ?></span></div>
  </div>

  <!-- شرائط التقدّم -->
  <div class="promote-progress">
    <div>
      <span class="muted"><?= e(t('daily_cap')) ?>: <?= (int)$today ?>/<?= Referrals::DAILY_CAP ?></span>
      <div class="bar"><div class="bar-fill" style="width:<?= $progressDay ?>%"></div></div>
    </div>
    <div>
      <span class="muted"><?= e(t('lifetime_cap')) ?>: <?= (int)$counted ?>/<?= Referrals::LIFETIME_CAP ?> (= <?= Referrals::LIFETIME_CAP * Referrals::POINTS_PER ?> <?= e(t('max_points')) ?>)</span>
      <div class="bar"><div class="bar-fill" style="width:<?= $progressLife ?>%"></div></div>
    </div>
  </div>

  <!-- قواعد -->
  <div class="promote-rules">
    <h2 class="section-head">📜 <?= e(t('how_it_works')) ?></h2>
    <ul>
      <li>✅ <?= e(sprintf(t('rule_points'),   Referrals::POINTS_PER)) ?></li>
      <li>📅 <?= e(sprintf(t('rule_daily'),    Referrals::DAILY_CAP,    Referrals::DAILY_CAP    * Referrals::POINTS_PER)) ?></li>
      <li>🏁 <?= e(sprintf(t('rule_lifetime'), Referrals::LIFETIME_CAP, Referrals::LIFETIME_CAP * Referrals::POINTS_PER)) ?></li>
      <li>🛡 <?= e(t('rule_antifraud')) ?></li>
    </ul>
  </div>

  <?php if (!empty($recent)): ?>
  <div class="promote-recent">
    <h2 class="section-head">🎯 <?= e(t('your_recent_refs')) ?></h2>
    <ul class="ref-list">
      <?php foreach ($recent as $r): ?>
        <li>
          <strong><?= e($r['display_name'] ?? $r['username']) ?></strong>
          <span class="muted"><?= e(local_dt((int)$r['created_at'], 'date_short')) ?></span>
          <?php if ((int)$r['counted']): ?>
            <span class="badge badge-done">+<?= Referrals::POINTS_PER ?> ⭐</span>
          <?php else: ?>
            <span class="badge"><?= e(t('not_counted')) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (!empty($top)): ?>
  <div class="promote-top">
    <h2 class="section-head">🏆 <?= e(t('top_promoters')) ?></h2>
    <ol class="top-list">
      <?php foreach ($top as $i => $r): ?>
        <li class="<?= ($r['referrer_user_id'] ?? 0) == $uid ? 'is-me' : '' ?>">
          <span class="rank">#<?= $i + 1 ?></span>
          <span class="name"><?= e($r['display_name'] ?? $r['username']) ?></span>
          <span class="pts"><?= (int)$r['counted'] ?> × <?= Referrals::POINTS_PER ?> = <strong><?= (int)$r['counted'] * Referrals::POINTS_PER ?> ⭐</strong></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php endif; ?>

  <script>
  (function () {
    var btn = document.getElementById('refCopy');
    var inp = document.getElementById('refLink');
    if (!btn || !inp) return;
    var done = btn.getAttribute('data-done') || 'Copied';
    var txt  = btn.getAttribute('data-text') || 'Copy';
    btn.addEventListener('click', function () {
      var ok = function () {
        btn.textContent = '✓ ' + done;
        setTimeout(function () { btn.textContent = '🔗 ' + txt; }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(inp.value).then(ok, ok);
      } else {
        inp.select(); try { document.execCommand('copy'); } catch (e) {} ok();
      }
    });
  })();
  </script>

  <?php else: /* زائر — يقدر يسوّق بالرابط العام بدون حساب */ ?>
    <div class="auth-card promote-guest">
      <p class="muted" style="margin:0 0 12px; font-size:.95rem">
        👋 <?= e(t('promote_guest_welcome')) ?>
      </p>
      <p style="margin:0 0 14px">
        <a class="btn btn-accent" href="<?= e(url('register.php')) ?>">⭐ <?= e(t('promote_guest_signup_btn')) ?></a>
        &nbsp;·&nbsp;
        <a class="btn-link" href="<?= e(url('login.php')) ?>"><?= e(t('sign_in')) ?></a>
      </p>
      <p class="muted" style="margin:0; font-size:.85rem">
        <?= e(t('promote_guest_note')) ?>
      </p>
    </div>
  <?php endif; /* end: $me check */ ?>

<?php endif; /* end: Database::available() — لوحة شخصية فقط داخلها */ ?>

<!-- ============ 🧰 صندوق التسويق — للجميع (حتى لو DB معطّلة، الكوبي يعمل) ============ -->
  <?php
  $hasRef       = ($me !== null);
  $kitUsername  = $hasRef ? (string)$me['username'] : '';
  $kitHomeLink  = $hasRef
    ? Referrals::linkFor($kitUsername)
    : (rtrim((string)SITE_URL, '/') . '/');
  $kitBracket   = $hasRef
    ? (rtrim((string)SITE_URL, '/') . '/bracket.php?ref=' . rawurlencode($kitUsername))
    : (rtrim((string)SITE_URL, '/') . '/bracket.php');
  $tStart       = DataService::tournamentStart();
  // floor (وليس ceil) لتطابق عدّاد الرئيسية بالضبط — لو فيه 7 أيام و1 ساعة باقية، نقول 7.
  $daysLeft     = $tStart ? max(0, (int)floor(($tStart - time()) / 86400)) : 0;
  $isAr         = (current_lang() === 'ar');
  $isFr         = (current_lang() === 'fr');

  $templates = $isAr ? [
    ['icon'=>'⏰','title'=>'تغريدة العدّ التنازلي اليومية (X)','platform'=>'X',
     'text'=>"⚽ {$daysLeft} يوماً على انطلاق كأس العالم 2026!\n\nتوقّع نتائج المباريات وانضمّ للمنافسة 👇\n{$kitHomeLink}"],
    ['icon'=>'🎯','title'=>'ادعُ أصدقاءك للتوقعات (X / واتساب)','platform'=>'X',
     'text'=>"🎯 لعبة توقعات كأس العالم 2026 — مجّاناً، بالعربي والإنجليزي\nسجّل، توقّع، نافس العالم على لوحة الصدارة 🏆\n\n{$kitHomeLink}"],
    ['icon'=>'🏆','title'=>'لعبة شجرة الأدوار (X / فيسبوك)','platform'=>'X',
     'text'=>"🏆 من سيرفع كأس العالم 2026؟\nاملأ شجرتك التكتيكية وثبّت رأيك قبل صفّارة البداية ⚽\n\n{$kitBracket}"],
    ['icon'=>'🚨','title'=>'قبل المباراة بساعة (X)','platform'=>'X',
     'text'=>"🚨 المباراة بعد ساعة!\nتوقّع النتيجة الآن واربح نقاطاً قبل صفّارة البداية ⚽\n\n{$kitHomeLink}"],
    ['icon'=>'💬','title'=>'رسالة لقنوات التيليجرام العربية','platform'=>'Telegram',
     'text'=>"شباب، لقيت موقع توقعات لكأس العالم 2026 — مجّاني، بالعربي والإنجليزي، فيه تحدّي معرفة يومي وشجرة أدوار وعدّاد قهر 😅\nعجبني وحبيت أشاركه:\n\n{$kitHomeLink}"],
    ['icon'=>'📱','title'=>'رسالة الواتساب القصيرة','platform'=>'WhatsApp',
     'text'=>"كأس العالم بعد {$daysLeft} يوم 🔥\nانضمّ معاي للتوقعات — مجّاني وممتع\n{$kitHomeLink}"],
  ] : ($isFr ? [
    ['icon'=>'⏰','title'=>'Tweet quotidien du compte à rebours (X)','platform'=>'X',
     'text'=>"⚽ Plus que {$daysLeft} jours avant la Coupe du Monde 2026 !\n\nPrédisez les résultats et rejoignez la compétition 👇\n{$kitHomeLink}"],
    ['icon'=>'🎯','title'=>'Invitez vos amis aux pronostics (X / WhatsApp)','platform'=>'X',
     'text'=>"🎯 Jeu de pronostics Coupe du Monde 2026 — gratuit\nInscrivez-vous, prédisez, rivalisez au classement 🏆\n\n{$kitHomeLink}"],
    ['icon'=>'🏆','title'=>'Jeu du tableau prédictif (X / Facebook)','platform'=>'X',
     'text'=>"🏆 Qui soulèvera la Coupe du Monde 2026 ?\nRemplissez votre tableau et affirmez votre choix ⚽\n\n{$kitBracket}"],
    ['icon'=>'🚨','title'=>'Avant le match (X)','platform'=>'X',
     'text'=>"🚨 Match dans 1 heure !\nFaites votre prédiction et gagnez des points avant le coup d'envoi ⚽\n\n{$kitHomeLink}"],
    ['icon'=>'💬','title'=>'Message Telegram','platform'=>'Telegram',
     'text'=>"Salut les fans — un jeu de pronostics gratuit pour la Coupe du Monde 2026 vient de lancer. Prédisez les matchs, remplissez le tableau, rivalisez au classement. À tester :\n{$kitHomeLink}"],
    ['icon'=>'📱','title'=>'Message WhatsApp court','platform'=>'WhatsApp',
     'text'=>"Coupe du Monde dans {$daysLeft} jours 🔥\nRejoignez-moi pour les pronostics — gratuit et amusant\n{$kitHomeLink}"],
  ] : [
    ['icon'=>'⏰','title'=>'Daily countdown tweet (X)','platform'=>'X',
     'text'=>"⚽ {$daysLeft} days until FIFA World Cup 2026!\n\nPredict match results and join the competition 👇\n{$kitHomeLink}"],
    ['icon'=>'🟢','title'=>'Reddit r/soccer post (English)','platform'=>'Reddit',
     'text'=>"I built a free World Cup 2026 prediction game (Arabic + English)\n\nFeatures:\n• Predict every match (104 fixtures)\n• Bracket predictor for knockout stages\n• Daily trivia for bonus points\n• Public leaderboard, no ads spam\n• No sign-up required to view\n\nTrying it out before kickoff: {$kitHomeLink}"],
    ['icon'=>'🟢','title'=>'Reddit r/UAE / r/saudi post','platform'=>'Reddit',
     'text'=>"Built a World Cup 2026 site — predictions, bracket, live scores, trivia. Arabic + English. Free. Looking for feedback from football fans here: {$kitHomeLink}"],
    ['icon'=>'💬','title'=>'Telegram football channel message','platform'=>'Telegram',
     'text'=>"Hey football folks — a free World Cup 2026 prediction game just launched. AR + EN. Predict matches, fill brackets, compete on the leaderboard. Worth a look before the tournament:\n{$kitHomeLink}"],
    ['icon'=>'🚨','title'=>'Pre-match tweet (1 hour before)','platform'=>'X',
     'text'=>"🚨 Match starting in 1 hour!\nLock in your prediction and earn points before kickoff:\n{$kitHomeLink}"],
    ['icon'=>'🏆','title'=>'Bracket tweet','platform'=>'X',
     'text'=>"🏆 Who lifts the World Cup 2026?\nFill your bracket and stake your claim now:\n{$kitBracket}"],
  ]);

  // الهاشتاقات الرسمية من FIFA + المحلية + الدول المستضيفة — تُضاف في نهاية كل قالب
  // (يضمن أن المستخدم الذي ينسخ النص ويلصقه على إنستجرام/ثردز/أي منصّة يحصل عليها تلقائياً)
  $tags = $isAr
    ? '#كأس_العالم_2026 #WeAre26 #FIFAWorldCup26 #wcup2026 #كندا #المكسيك #أمريكا'
    : ($isFr
        ? '#CoupeDuMonde2026 #WeAre26 #FIFAWorldCup26 #wcup2026 #Canada #Mexique #USA'
        : '#WeAre26 #FIFAWorldCup26 #WorldCup2026 #wcup2026 #Canada #Mexico #USA');
  foreach ($templates as &$t) {
    $t['text'] .= "\n\n" . $tags;
  }
  unset($t);
  ?>
  <div class="marketing-kit">
    <h2 class="section-head">🧰 <?= e($isAr ? 'صندوق التسويق — قوالب جاهزة' : ($isFr ? 'Kit marketing — Modèles prêts à publier' : 'Marketing Kit — Ready-to-Post Templates')) ?></h2>
    <p class="muted" style="margin-bottom:14px">
      <?php if ($hasRef): ?>
        <?= e($isAr
              ? "العدّ التنازلي ورابطك المخصّص محشوّان في كل قالب — انسخ والصق فقط."
              : ($isFr
                  ? "Le compte à rebours et votre lien personnel sont pré-remplis — copiez et collez."
                  : "Countdown and your personal link are pre-filled — just copy and post.")) ?>
      <?php else: ?>
        <?= e($isAr
              ? "القوالب جاهزة بالرابط العام للموقع. سجّل حسابك لتحصل على رابط مخصّص يكسبك نقاطاً عن كل تسجيل عبر رابطك."
              : ($isFr
                  ? "Les modèles utilisent le lien générique du site. Inscrivez-vous pour obtenir un lien personnel qui vous rapporte des points pour chaque inscription."
                  : "Templates use the general site link. Sign up to get a personal link that earns you points for each signup via your link.")) ?>
      <?php endif; ?>
    </p>
    <div class="kit-grid">
      <?php foreach ($templates as $i => $t):
        $encText  = rawurlencode($t['text']);
        // عنوان منشور Reddit = أوّل سطر فعلي من النص (يفصل التايتل عن الجسم)
        $firstLine = trim(strtok($t['text'], "\n"));
        $body      = trim((string)substr($t['text'], strlen($firstLine)));
        $encTitle  = rawurlencode($firstLine !== '' ? $firstLine : 'World Cup 2026');
        $encBody   = rawurlencode($body);
        $encUrl    = rawurlencode($kitHomeLink);
        // روابط المشاركة المباشرة لكل منصّة
        $sX  = "https://twitter.com/intent/tweet?text={$encText}";
        $sWA = "https://wa.me/?text={$encText}";
        $sTG = "https://t.me/share/url?url={$encUrl}&text={$encText}";
        $sFB = "https://www.facebook.com/sharer/sharer.php?u={$encUrl}&quote={$encText}";
        $sRD = "https://www.reddit.com/submit?title={$encTitle}&text={$encBody}";
      ?>
      <div class="kit-card">
        <div class="kit-head">
          <span class="kit-icon" aria-hidden="true"><?= e($t['icon']) ?></span>
          <strong class="kit-title"><?= e($t['title']) ?></strong>
          <span class="kit-platform"><?= e($t['platform']) ?></span>
        </div>
        <textarea class="kit-text" readonly rows="4" id="kit-<?= $i ?>"><?= e($t['text']) ?></textarea>
        <div class="kit-share-row" aria-label="<?= e($isAr ? 'مشاركة على' : ($isFr ? 'Partager sur' : 'Share to')) ?>">
          <a class="kit-share kit-x"  href="<?= e($sX)  ?>" target="_blank" rel="noopener" title="X" aria-label="X">𝕏</a>
          <a class="kit-share kit-wa" href="<?= e($sWA) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp">WA</a>
          <a class="kit-share kit-tg" href="<?= e($sTG) ?>" target="_blank" rel="noopener" title="Telegram" aria-label="Telegram">✈</a>
          <a class="kit-share kit-fb" href="<?= e($sFB) ?>" target="_blank" rel="noopener" title="Facebook" aria-label="Facebook">f</a>
          <a class="kit-share kit-rd" href="<?= e($sRD) ?>" target="_blank" rel="noopener" title="Reddit" aria-label="Reddit">r/</a>
          <button type="button" class="kit-share kit-cp" title="<?= e($isAr ? 'نسخ' : ($isFr ? 'Copier' : 'Copy')) ?>"
                  data-target="kit-<?= $i ?>" data-done="<?= e(t('link_copied')) ?>">📋</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
  (function () {
    // زرّ النسخ الصغير داخل صفّ المشاركة (يُكمل أزرار المنصّات)
    document.querySelectorAll('.kit-cp').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var ta = document.getElementById(btn.getAttribute('data-target'));
        if (!ta) return;
        var orig = btn.textContent;
        var done = btn.getAttribute('data-done') || 'Copied';
        var ok = function () {
          btn.textContent = '✓';
          setTimeout(function () { btn.textContent = orig; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(ta.value).then(ok, ok);
        } else {
          ta.select(); try { document.execCommand('copy'); } catch (e) {} ok();
        }
      });
    });
  })();
  </script>

<?php tpl('footer'); ?>
