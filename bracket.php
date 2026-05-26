<?php
/**
 * bracket.php — «توقّع المشوار»: شجرة إقصائيات تفاعلية يملؤها المستخدم.
 * يختار المتأهلين في دور الـ32 ثم يصعّد الفائزين حتى النهائي ويتوّج بطله.
 * البنية من Bracket::predictorData()؛ التفاعل والحفظ في المتصفّح عبر bracket.js.
 */
require __DIR__ . '/includes/bootstrap.php';

$data   = Bracket::predictorData();
$rounds = $data['rounds'];
$lang   = current_lang();

$page_title = $lang === 'ar' ? 'توقّع المشوار' : 'Predict the Bracket';
$page_desc  = $lang === 'ar'
    ? 'املأ شجرة الأدوار الإقصائية كاملة من دور الـ32 حتى النهائي، وتوّج بطلك وشاركه.'
    : 'Fill the entire knockout bracket from the Round of 32 to the final, crown your champion and share it.';

$roundTitles = [
    'round_of_32'    => t('round_of_32'),
    'round_of_16'    => t('round_of_16'),
    'quarter_finals' => t('quarter_finals'),
    'semi_finals'    => t('semi_finals'),
    'final'          => t('final'),
];

/** يرسم خانة واحدة (مقعد دور 32 بقائمة منسدلة، أو خانة تُملأ تلقائياً). */
function bk_slot(array $slot, int $num, int $idx): void {
    $isSeed = ($slot['type'] ?? '') === 'seed';
    ?>
    <div class="bk-slot" data-num="<?= (int)$num ?>" data-slot="<?= (int)$idx ?>">
      <?php if ($isSeed): ?>
        <select class="bk-seed" aria-label="<?= e($slot['label']) ?>">
          <option value=""><?= e($slot['label']) ?> …</option>
          <?php foreach ($slot['cands'] as $c): ?>
            <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <span class="bk-team bk-team-empty"
              data-src="<?= (int)($slot['src'] ?? 0) ?>"
              data-kind="<?= e($slot['type']) ?>">—</span>
      <?php endif; ?>
      <button type="button" class="bk-pick" title="<?= e($lang === 'ar' ? 'يتأهّل' : 'advance') ?>" disabled>▲</button>
    </div>
    <?php
}

tpl('header');
?>

<div class="page-head">
  <h1>🏆 <?= e($page_title) ?></h1>
  <p class="muted"><?= e($page_desc) ?></p>
</div>

<div class="bk-champion" id="bkChampion" hidden>
  <span class="bk-champ-label"><?= e(t('final_winner')) ?></span>
  <span class="bk-champ-team" id="bkChampTeam"></span>
</div>

<div class="bk-toolbar">
  <button type="button" class="btn-cta" id="bkShare"><?= e($lang === 'ar' ? '📣 شارك توقّعي' : '📣 Share my bracket') ?></button>
  <button type="button" class="btn-ghost" id="bkReset"><?= e($lang === 'ar' ? '↺ تصفير' : '↺ Reset') ?></button>
  <span class="bk-hint muted"><?= e($lang === 'ar'
      ? 'اختر المتأهّلين في دور الـ32 ثم اضغط ▲ لتصعيد الفائز.'
      : 'Pick the Round-of-32 qualifiers, then press ▲ to advance a winner.') ?></span>
</div>

<div class="bk-board-wrap">
  <div class="bk-board">
    <?php foreach (['round_of_32','round_of_16','quarter_finals','semi_finals','final'] as $key):
      if (empty($rounds[$key])) continue; ?>
      <div class="bk-round bk-<?= e($key) ?>">
        <h2 class="bk-round-title"><?= e($roundTitles[$key]) ?></h2>
        <?php foreach ($rounds[$key] as $mm): ?>
          <div class="bk-match" data-num="<?= (int)$mm['num'] ?>">
            <?php bk_slot($mm['s1'], (int)$mm['num'], 1); ?>
            <?php bk_slot($mm['s2'], (int)$mm['num'], 2); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!empty($rounds['third_place'])): $tp = $rounds['third_place'][0]; ?>
<div class="bk-third">
  <h2 class="bk-round-title"><?= e(t('third_place')) ?></h2>
  <div class="bk-match" data-num="<?= (int)$tp['num'] ?>">
    <?php bk_slot($tp['s1'], (int)$tp['num'], 1); ?>
    <?php bk_slot($tp['s2'], (int)$tp['num'], 2); ?>
  </div>
</div>
<?php endif; ?>

<script>
window.BRACKET = <?= json_encode($rounds, JSON_UNESCAPED_UNICODE) ?>;
window.BRACKET_LANG = <?= json_encode($lang) ?>;
</script>
<?php $bkV = @filemtime(__DIR__ . '/assets/js/bracket.js') ?: 1; ?>
<script src="<?= e(rtrim(SITE_URL, '/')) ?>/assets/js/bracket.js?v=<?= $bkV ?>"></script>

<?php tpl('footer'); ?>
