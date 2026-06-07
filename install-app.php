<?php
/**
 * install-app.php — صفحة هبوط لتثبيت التطبيق (PWA) على الجوال.
 * يعرض دليل بصريّ خطوة بخطوة (آيفون + أندرويد) — يتعرّف على الجهاز تلقائياً.
 */
require __DIR__ . '/includes/bootstrap.php';

$lang = current_lang();
$ar   = ($lang === 'ar');

$page_title = $ar ? '📱 احصل على التطبيق' : '📱 Get the App';
$page_desc  = $ar
    ? 'تطبيق كأس العالم 2026 — مجاناً، بدون متجر، يعمل بدون إنترنت'
    : 'FIFA World Cup 2026 app — free, no store, works offline';

tpl('header');
?>

<link rel="stylesheet" href="<?= e(rtrim(SITE_URL,'/')) ?>/assets/css/install-app.css?v=<?= @filemtime(__DIR__ . '/assets/css/install-app.css') ?: 1 ?>">

<!-- ════════════════ Hero ════════════════ -->
<section class="ia-hero">
  <div class="ia-hero-mark" aria-hidden="true">26</div>
  <h1 class="ia-hero-title">
    <?= e($ar ? 'تطبيق كأس العالم 2026' : 'FIFA World Cup 2026 App') ?>
  </h1>
  <p class="ia-hero-sub">
    <?= e($ar
      ? 'مجاناً · بدون App Store · بدون انتظار · يعمل بدون إنترنت'
      : 'Free · No App Store · No waiting · Works offline') ?>
  </p>
  <div class="ia-hero-badges">
    <span class="ia-badge">⚡ <?= e($ar ? 'تثبيت في 10 ثوانٍ' : '10-second install') ?></span>
    <span class="ia-badge">📱 <?= e($ar ? 'آيفون + أندرويد' : 'iPhone + Android') ?></span>
    <span class="ia-badge">🆓 <?= e($ar ? 'بدون اشتراكات' : 'No subscriptions') ?></span>
  </div>
</section>

<!-- ════════════════ Benefits ════════════════ -->
<section class="ia-benefits">
  <h2 class="ia-section-h"><?= e($ar ? 'لماذا التطبيق؟' : 'Why the app?') ?></h2>
  <div class="ia-benefits-grid">
    <div class="ia-benefit"><span class="ia-b-ico">🚀</span>
      <strong><?= e($ar ? 'فتح فوريّ' : 'Instant launch') ?></strong>
      <span><?= e($ar ? 'دون انتظار تحميل المتصفّح' : 'No browser load delay') ?></span>
    </div>
    <div class="ia-benefit"><span class="ia-b-ico">📶</span>
      <strong><?= e($ar ? 'يعمل بدون إنترنت' : 'Works offline') ?></strong>
      <span><?= e($ar ? 'الجدول والمجموعات متاحة دائماً' : 'Schedule & groups always available') ?></span>
    </div>
    <div class="ia-benefit"><span class="ia-b-ico">🔔</span>
      <strong><?= e($ar ? 'إشعارات المباريات' : 'Match alerts') ?></strong>
      <span><?= e($ar ? 'تنبيه قبل بداية كل مباراة' : 'Alerts before each kickoff') ?></span>
    </div>
    <div class="ia-benefit"><span class="ia-b-ico">🎨</span>
      <strong><?= e($ar ? 'شاشة كاملة' : 'Full screen') ?></strong>
      <span><?= e($ar ? 'بدون شريط متصفّح' : 'No browser bar') ?></span>
    </div>
    <div class="ia-benefit"><span class="ia-b-ico">🆓</span>
      <strong><?= e($ar ? 'لا App Store' : 'No App Store') ?></strong>
      <span><?= e($ar ? 'لا تنتظر موافقة، لا تدفع لأحد' : 'No review, no fees') ?></span>
    </div>
    <div class="ia-benefit"><span class="ia-b-ico">💾</span>
      <strong><?= e($ar ? 'حجم صغير' : 'Tiny size') ?></strong>
      <span><?= e($ar ? '~500 KB فقط' : '~500 KB only') ?></span>
    </div>
  </div>
</section>

<!-- ════════════════ Step-by-step (iOS) ════════════════ -->
<section class="ia-steps ia-ios">
  <h2 class="ia-section-h">
    <span class="ia-platform-pill"></span>
    <?= e($ar ? '🍎 طريقة التثبيت على آيفون' : '🍎 How to install on iPhone') ?>
  </h2>

  <div class="ia-steps-grid">
    <!-- خطوة 1 -->
    <article class="ia-step">
      <div class="ia-step-num">1</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen">
          <div class="ia-mock-bar"><span class="ia-mock-url">wcup2026.org</span></div>
          <div class="ia-mock-content">
            <div class="ia-mock-26">26</div>
            <p><?= e($ar ? 'كأس العالم 2026' : 'World Cup 2026') ?></p>
          </div>
          <div class="ia-mock-share-arrow">↑</div>
        </div>
      </div>
      <h3><?= e($ar ? 'افتح في Safari' : 'Open in Safari') ?></h3>
      <p><?= e($ar
        ? 'ادخل wcup2026.org في متصفّح Safari (مهم: Chrome على iOS لا يدعم التثبيت)'
        : 'Visit wcup2026.org in Safari (Chrome on iOS does NOT support install)') ?></p>
    </article>

    <!-- خطوة 2 -->
    <article class="ia-step">
      <div class="ia-step-num">2</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen">
          <div class="ia-mock-content ia-mock-share-menu">
            <div class="ia-share-icon">⬆️</div>
            <p class="ia-share-label"><?= e($ar ? 'شارك' : 'Share') ?></p>
            <div class="ia-share-tip">
              <?= e($ar ? '👇 اضغط زر المشاركة في أسفل المتصفّح' : '👇 Tap the Share button at the bottom') ?>
            </div>
          </div>
        </div>
      </div>
      <h3><?= e($ar ? 'اضغط زر المشاركة' : 'Tap the Share button') ?></h3>
      <p><?= e($ar
        ? 'الزرّ في وسط الشريط السفلي (مستطيل فيه سهم لأعلى ⬆️)'
        : 'It\'s the square with an arrow pointing up (⬆️) in the bottom toolbar') ?></p>
    </article>

    <!-- خطوة 3 -->
    <article class="ia-step ia-step-highlight">
      <div class="ia-step-num">3</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen">
          <div class="ia-mock-content ia-mock-add">
            <div class="ia-add-row">
              <span>📋 <?= e($ar ? 'نسخ' : 'Copy') ?></span><span>›</span>
            </div>
            <div class="ia-add-row">
              <span>🔖 <?= e($ar ? 'مفضّلة' : 'Favorites') ?></span><span>›</span>
            </div>
            <div class="ia-add-row ia-add-highlight">
              <span>➕ <?= e($ar ? 'إضافة إلى الشاشة الرئيسية' : 'Add to Home Screen') ?></span>
              <span>›</span>
            </div>
          </div>
        </div>
      </div>
      <h3><?= e($ar ? '«إضافة إلى الشاشة الرئيسية»' : '"Add to Home Screen"') ?></h3>
      <p><?= e($ar
        ? 'انزل في القائمة حتى تجد هذا الخيار (أيقونة +) واضغطه. ثم اضغط «إضافة»'
        : 'Scroll down in the menu to find this option (+ icon), tap it, then tap "Add"') ?></p>
    </article>
  </div>

  <div class="ia-step-result">
    🎉 <strong><?= e($ar ? 'انتهى!' : 'Done!') ?></strong>
    <?= e($ar
      ? 'ستظهر أيقونة "26" في شاشتك الرئيسيّة. افتحها واستمتع بتطبيق كامل.'
      : 'The "26" icon appears on your Home Screen. Tap it to enjoy the full app.') ?>
  </div>
</section>

<!-- ════════════════ Step-by-step (Android) ════════════════ -->
<section class="ia-steps ia-android">
  <h2 class="ia-section-h">
    <?= e($ar ? '🤖 طريقة التثبيت على أندرويد' : '🤖 How to install on Android') ?>
  </h2>

  <div class="ia-steps-grid">
    <!-- خطوة 1 -->
    <article class="ia-step">
      <div class="ia-step-num">1</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen">
          <div class="ia-mock-bar"><span class="ia-mock-url">wcup2026.org</span></div>
          <div class="ia-mock-content">
            <div class="ia-mock-26">26</div>
            <p><?= e($ar ? 'كأس العالم 2026' : 'World Cup 2026') ?></p>
          </div>
        </div>
      </div>
      <h3><?= e($ar ? 'افتح في Chrome' : 'Open in Chrome') ?></h3>
      <p><?= e($ar ? 'ادخل wcup2026.org من متصفّح Chrome' : 'Visit wcup2026.org in Chrome') ?></p>
    </article>

    <!-- خطوة 2 -->
    <article class="ia-step ia-step-highlight">
      <div class="ia-step-num">2</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen">
          <div class="ia-mock-content ia-mock-add">
            <div class="ia-banner-prompt">
              <span class="ia-mock-26-small">26</span>
              <div>
                <strong><?= e($ar ? 'تثبيت التطبيق' : 'Install app') ?></strong>
                <small><?= e($ar ? 'كأس العالم 2026' : 'World Cup 2026') ?></small>
              </div>
              <button class="ia-mini-btn"><?= e($ar ? 'تثبيت' : 'Install') ?></button>
            </div>
          </div>
        </div>
      </div>
      <h3><?= e($ar ? 'اضغط «تثبيت»' : 'Tap "Install"') ?></h3>
      <p><?= e($ar
        ? 'سيظهر شريط تلقائي يقترح التثبيت — اضغطه. أو من قائمة Chrome (⋮) → «تثبيت التطبيق»'
        : 'A prompt appears automatically — tap it. Or from Chrome menu (⋮) → "Install app"') ?></p>
    </article>

    <!-- خطوة 3 -->
    <article class="ia-step">
      <div class="ia-step-num">3</div>
      <div class="ia-phone-mock">
        <div class="ia-phone-screen ia-mock-homescreen">
          <div class="ia-mock-content">
            <div class="ia-mock-26"><span>26</span></div>
            <p><?= e($ar ? 'WC 2026' : 'WC 2026') ?></p>
          </div>
        </div>
      </div>
      <h3><?= e($ar ? 'يصبح أيقونة على شاشتك' : 'It becomes an icon') ?></h3>
      <p><?= e($ar
        ? 'افتحه كأيّ تطبيق ثاني — يعمل بشاشة كاملة وبدون إنترنت'
        : 'Open it like any other app — full-screen and works offline') ?></p>
    </article>
  </div>

  <div class="ia-step-result">
    🎉 <strong><?= e($ar ? 'مبروك!' : 'Congrats!') ?></strong>
    <?= e($ar
      ? 'تطبيقك جاهز. كل التحديثات وصلت لك آلياً بدون اشتراك.'
      : 'Your app is ready. All updates land automatically without subscription.') ?>
  </div>
</section>

<!-- ════════════════ FAQ ════════════════ -->
<section class="ia-faq">
  <h2 class="ia-section-h"><?= e($ar ? '❓ أسئلة متكرّرة' : '❓ FAQ') ?></h2>

  <details class="ia-faq-item">
    <summary><?= e($ar ? 'هل التطبيق آمن؟' : 'Is the app safe?') ?></summary>
    <p><?= e($ar
      ? '100% آمن — لا يصل لجهات اتصالك ولا صورك. هو فعليّاً موقع الويب نفسه يعمل في شاشة كاملة.'
      : '100% safe — no access to contacts or photos. It\'s literally the website running full-screen.') ?></p>
  </details>

  <details class="ia-faq-item">
    <summary><?= e($ar ? 'هل يأخذ مساحة من جهازي؟' : 'Does it take phone storage?') ?></summary>
    <p><?= e($ar
      ? 'أقل من 500 كيلوبايت — أقل من صورة واحدة. وأنت تختار حذفه متى ما شئت من إعدادات الجهاز.'
      : 'Less than 500KB — less than a single photo. You can remove it anytime from device settings.') ?></p>
  </details>

  <details class="ia-faq-item">
    <summary><?= e($ar ? 'لماذا لا أجده في App Store؟' : 'Why not on the App Store?') ?></summary>
    <p><?= e($ar
      ? 'تقنية PWA تتيح تثبيت موقع كتطبيق بدون متجر — أحدث تقنيّات Apple و Google. تجربة أسرع، ولا تنتظر مراجعة.'
      : 'PWA tech lets you install a website as an app without a store — latest Apple/Google standard. Faster, no review wait.') ?></p>
  </details>

  <details class="ia-faq-item">
    <summary><?= e($ar ? 'هل أتلقّى تنبيهات المباريات؟' : 'Do I get match notifications?') ?></summary>
    <p><?= e($ar
      ? 'نعم — عند فتح التطبيق أوّل مرّة سيطلب الإذن للتنبيهات. وافق وتنبّه قبل كل مباراة بـ10 دقائق.'
      : 'Yes — first time you open it, allow notifications. You\'ll be alerted 10 min before each match.') ?></p>
  </details>

  <details class="ia-faq-item">
    <summary><?= e($ar ? 'كيف أحذفه؟' : 'How do I uninstall?') ?></summary>
    <p><?= e($ar
      ? 'اضغط طويلاً على أيقونته في الشاشة الرئيسيّة → اختر «حذف»/«إزالة». تماماً مثل أيّ تطبيق آخر.'
      : 'Long-press the icon on your Home Screen → tap "Remove"/"Delete". Just like any other app.') ?></p>
  </details>
</section>

<!-- ════════════════ Final CTA ════════════════ -->
<section class="ia-final-cta">
  <h2><?= e($ar ? '⚽ جاهز للبدء؟' : '⚽ Ready to start?') ?></h2>
  <p><?= e($ar
    ? 'افتح هذي الصفحة على جوالك Safari (iPhone) أو Chrome (Android) واتبع 3 خطوات.'
    : 'Open this page on your phone\'s Safari (iPhone) or Chrome (Android) and follow 3 steps.') ?></p>
  <div class="ia-cta-buttons">
    <a href="<?= e(url('matches.php')) ?>" class="btn btn-cta">⚽ <?= e($ar ? 'المباريات' : 'Matches') ?></a>
    <a href="<?= e(url('predict.php')) ?>" class="btn btn-cta">🎯 <?= e($ar ? 'التوقّعات' : 'Predict') ?></a>
    <a href="<?= e(url('index.php')) ?>" class="btn">🏠 <?= e($ar ? 'الرئيسية' : 'Home') ?></a>
  </div>
</section>

<?php tpl('footer'); ?>
