<?php
/**
 * group_table.php — جدول ترتيب مجموعة واحدة.
 * يُستدعى عبر render_group_table($groupName, $rows).
 */
if (!defined('WC2026')) { exit('Access denied'); }

function render_group_table(string $group, array $rows): void {
    ?>
    <div class="group-block">
      <h3 class="group-title">
        <span class="group-letter"><?= e(preg_replace('/[^A-L]/i', '', $group)) ?></span>
        <?= e(group_label($group)) ?>
      </h3>
      <div class="table-scroll">
        <table class="standings">
          <thead>
            <tr>
              <th class="t-pos"><?= e(t('pos')) ?></th>
              <th class="t-team"><?= e(t('team')) ?></th>
              <th><?= e(t('played')) ?></th>
              <th><?= e(t('won')) ?></th>
              <th><?= e(t('draw')) ?></th>
              <th><?= e(t('lost')) ?></th>
              <th><?= e(t('gd')) ?></th>
              <th class="t-pts"><?= e(t('points')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $rank = $i + 1;
              // أول منتخبين يتأهلان مباشرة
              $qualClass = ($rank <= 2) ? ' qualified' : '';
            ?>
            <tr class="<?= e(ltrim($qualClass)) ?>">
              <td class="t-pos"><span class="rank"><?= $rank ?></span></td>
              <td class="t-team">
                <a href="<?= e(url('team.php', ['team' => $r['team']])) ?>">
                  <?= flag_img($r['team'], 'w40') ?>
                  <span><?= e(team_name($r['team'])) ?></span>
                </a>
              </td>
              <td><?= (int)$r['p'] ?></td>
              <td><?= (int)$r['w'] ?></td>
              <td><?= (int)$r['d'] ?></td>
              <td><?= (int)$r['l'] ?></td>
              <td class="<?= $r['gd'] > 0 ? 'pos' : ($r['gd'] < 0 ? 'neg' : '') ?>">
                <?= ($r['gd'] > 0 ? '+' : '') . (int)$r['gd'] ?>
              </td>
              <td class="t-pts"><strong><?= (int)$r['pts'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
        // مشاركة هذه المجموعة: الرابط يحمل ?g=الحرف → معاينة بطاقة المجموعة،
        // والهاشتاكات تضمّ منتخبات المجموعة (AR + EN).
        $gL = preg_replace('/[^A-L]/i', '', $group);
        render_share(
            url('groups.php', ['g' => $gL, 'd' => card_rev()]),
            group_label($group) . ' — ' . t('standings') . ' — ' . SITE_NAME_AR,
            ['teams' => array_map(fn($r) => (string)$r['team'], $rows)]
        );
      ?>
    </div>
    <?php
}

/**
 * render_third_place_table() — جدول ترتيب أصحاب المركز الثالث عبر المجموعات.
 * يُستدعى عبر render_third_place_table($rows) حيث $rows من Standings::thirdPlaceRanking().
 * كل صف يحوي إضافةً: 'group' و'qualified'.
 */
function render_third_place_table(array $rows): void {
    if (!$rows) return;
    ?>
    <div class="group-block third-place-block">
      <h3 class="group-title">
        <span class="group-letter">3</span>
        <?= e(t('best_thirds')) ?>
      </h3>
      <p class="muted third-place-note"><?= e(t('best_thirds_note')) ?></p>
      <div class="table-scroll">
        <table class="standings">
          <thead>
            <tr>
              <th class="t-pos"><?= e(t('pos')) ?></th>
              <th><?= e(t('col_group')) ?></th>
              <th class="t-team"><?= e(t('team')) ?></th>
              <th><?= e(t('played')) ?></th>
              <th><?= e(t('gf')) ?></th>
              <th><?= e(t('gd')) ?></th>
              <th class="t-pts"><?= e(t('points')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $r):
              $rank      = $i + 1;
              $qualClass = !empty($r['qualified']) ? ' qualified' : '';
            ?>
            <tr class="<?= e(ltrim($qualClass)) ?>">
              <td class="t-pos"><span class="rank"><?= $rank ?></span></td>
              <td><strong><?= e(preg_replace('/[^A-L]/i', '', (string)($r['group'] ?? ''))) ?></strong></td>
              <td class="t-team">
                <a href="<?= e(url('team.php', ['team' => $r['team']])) ?>">
                  <?= flag_img($r['team'], 'w40') ?>
                  <span><?= e(team_name($r['team'])) ?></span>
                </a>
              </td>
              <td><?= (int)$r['p'] ?></td>
              <td><?= (int)$r['gf'] ?></td>
              <td class="<?= $r['gd'] > 0 ? 'pos' : ($r['gd'] < 0 ? 'neg' : '') ?>">
                <?= ($r['gd'] > 0 ? '+' : '') . (int)$r['gd'] ?>
              </td>
              <td class="t-pts"><strong><?= (int)$r['pts'] ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
}
