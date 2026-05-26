<?php
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
require_once __DIR__ . '/../includes/SponsorStore.php';
$ar = (current_lang() === 'ar'); $L = fn($a,$e)=>$ar?$a:$e;

/* ---- معالجة الإجراءات (بعد تحقّق CSRF في admin.php) ---- */
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do'])) {
    $do = (string)$_POST['do'];
    if ($do === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $url  = trim((string)($_POST['url']  ?? ''));
        $logo = trim((string)($_POST['logo'] ?? ''));
        if ($url  !== '' && !filter_var($url,  FILTER_VALIDATE_URL)) { $url  = ''; }
        if ($logo !== '' && !filter_var($logo, FILTER_VALIDATE_URL)) { $logo = ''; }
        if ($name !== '') {
            if (SponsorStore::add($name, $url, $logo)) {
                $notice = $L('تمت إضافة الراعي بنجاح.', 'Sponsor added successfully.');
            } else {
                $notice = $L('تعذّر حفظ الراعي. تحقّق من صلاحيات مجلد data.', 'Could not save sponsor. Check the data folder permissions.');
            }
        } else {
            $notice = $L('اسم الراعي مطلوب.', 'Sponsor name is required.');
        }
    } elseif ($do === 'del') {
        if (SponsorStore::removeAt((int)($_POST['idx'] ?? -1))) {
            $notice = $L('تم حذف الراعي.', 'Sponsor removed.');
        } else {
            $notice = $L('تعذّر حذف الراعي.', 'Could not remove sponsor.');
        }
    }
}

$sponsors = SponsorStore::all();
?>
<?php if ($notice !== ''): ?>
  <div class="admin-card"><?= e($notice) ?></div>
<?php endif; ?>

<div class="admin-card">
  <h2><?= e($L('إضافة راعٍ', 'Add sponsor')) ?></h2>
  <form method="post" action="admin.php">
    <input type="hidden" name="tab" value="sponsors">
    <input type="hidden" name="do" value="add">
    <?= Admin::csrfField() ?>
    <div class="admin-row">
      <div class="admin-field">
        <label><?= e($L('الاسم', 'Name')) ?></label>
        <input class="admin-input" type="text" name="name" required maxlength="120">
      </div>
      <div class="admin-field">
        <label><?= e($L('الرابط (اختياري)', 'URL (optional)')) ?></label>
        <input class="admin-input" type="url" name="url" placeholder="https://example.com">
      </div>
      <div class="admin-field">
        <label><?= e($L('رابط الشعار (اختياري)', 'Logo URL (optional)')) ?></label>
        <input class="admin-input" type="url" name="logo" placeholder="https://.../logo.png">
      </div>
    </div>
    <button type="submit" class="admin-btn admin-btn-primary"><?= e($L('إضافة', 'Add')) ?></button>
  </form>
</div>

<div class="admin-card">
  <h2><?= e($L('الرعاة الحاليون', 'Current sponsors')) ?></h2>
  <?php if (!$sponsors): ?>
    <div class="admin-muted"><?= e($L('لا يوجد رعاة بعد.', 'No sponsors yet.')) ?></div>
  <?php else: ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= e($L('الشعار', 'Logo')) ?></th>
          <th><?= e($L('الاسم', 'Name')) ?></th>
          <th><?= e($L('الرابط', 'URL')) ?></th>
          <th><?= e($L('حذف', 'Delete')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sponsors as $i => $sp):
            $name = (string)($sp['name'] ?? '');
            $url  = (string)($sp['url']  ?? '');
            $logo = (string)($sp['logo'] ?? '');
        ?>
        <tr>
          <td><?= e((string)($i + 1)) ?></td>
          <td>
            <?php if ($logo !== ''): ?>
              <img src="<?= e($logo) ?>" style="height:28px;border-radius:4px" alt="">
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
          <td><?= e($name) ?></td>
          <td>
            <?php if ($url !== ''): ?>
              <a href="<?= e($url) ?>" target="_blank" rel="noopener nofollow"><?= e($url) ?></a>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="admin.php">
              <input type="hidden" name="tab" value="sponsors">
              <input type="hidden" name="do" value="del">
              <input type="hidden" name="idx" value="<?= e((string)$i) ?>">
              <?= Admin::csrfField() ?>
              <button type="submit" class="admin-btn admin-btn-danger"><?= e($L('حذف', 'Delete')) ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <p class="admin-muted"><?= e($L('عندما تكون القائمة فارغة يعرض الموقع خانات «شعارك هنا».', 'When the list is empty the site shows "your logo here" placeholders.')) ?></p>
</div>
