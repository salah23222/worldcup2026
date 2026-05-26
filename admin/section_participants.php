<?php
if (!defined('WC2026') || !Admin::authed()) { exit('Access denied'); }
$ar = (current_lang() === 'ar'); $L = fn($a,$e)=>$ar?$a:$e;

$pdo = Database::pdo();
if ($pdo === null) {
    echo '<div class="admin-alert">' . e($L('قاعدة بيانات الحسابات معطّلة حالياً.', 'The accounts database is currently disabled.')) . '</div>';
    return;
}

/* ---- Overview stats (each defensive) ---- */
$totalUsers = $totalPreds = $totalTrivia = 0;
try { $totalUsers  = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (Throwable $e) {}
try { $totalPreds  = (int)$pdo->query('SELECT COUNT(*) FROM predictions')->fetchColumn(); } catch (Throwable $e) {}
try { $totalTrivia = (int)$pdo->query('SELECT COUNT(*) FROM trivia_answers')->fetchColumn(); } catch (Throwable $e) {}
?>
<div class="admin-stats">
  <div class="admin-stat"><strong><?= e((string)$totalUsers) ?></strong><span><?= e($L('المشاركون', 'Users')) ?></span></div>
  <div class="admin-stat"><strong><?= e((string)$totalPreds) ?></strong><span><?= e($L('التوقعات', 'Predictions')) ?></span></div>
  <div class="admin-stat"><strong><?= e((string)$totalTrivia) ?></strong><span><?= e($L('إجابات الأسئلة', 'Trivia answers')) ?></span></div>
</div>

<div class="admin-toolbar">
  <form method="get" action="admin.php">
    <input type="hidden" name="tab" value="participants">
    <input class="admin-input" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e($L('بحث بالاسم أو البريد', 'Search name or email')) ?>">
    <button class="admin-btn"><?= e($L('بحث', 'Search')) ?></button>
  </form>
</div>
<?php
/* ---- Fetch users ---- */
$q = trim((string)($_GET['q'] ?? ''));
$users = [];
try {
    if ($q !== '') {
        $like = '%' . $q . '%';
        $st = $pdo->prepare('SELECT id, username, display_name, email, country, created_at FROM users WHERE (username LIKE ? OR display_name LIKE ? OR email LIKE ?) ORDER BY created_at DESC, id DESC LIMIT 500');
        $st->execute([$like, $like, $like]);
    } else {
        $st = $pdo->prepare('SELECT id, username, display_name, email, country, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 500');
        $st->execute();
    }
    $users = $st->fetchAll();
} catch (Throwable $e) {
    $users = [];
}

$standings = Predictions::standingsByUser();

if (!$users) {
    echo '<div class="admin-muted">' . e($q !== '' ? $L('لا نتائج مطابقة للبحث.', 'No users match your search.') : $L('لا يوجد مشاركون مسجّلون بعد.', 'No registered users yet.')) . '</div>';
    return;
}
?>
<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th><?= e($L('اسم المستخدم', 'Username')) ?></th>
          <th><?= e($L('الاسم المعروض', 'Display name')) ?></th>
          <th><?= e($L('البريد', 'Email')) ?></th>
          <th><?= e($L('الدولة', 'Country')) ?></th>
          <th><?= e($L('تاريخ التسجيل', 'Registered')) ?></th>
          <th><?= e($L('النقاط', 'Points')) ?></th>
          <th><?= e($L('الترتيب', 'Rank')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 0; foreach ($users as $u):
            $i++;
            $uid     = (int)($u['id'] ?? 0);
            $cc      = (string)($u['country'] ?? '');
            $created = (int)($u['created_at'] ?? 0);
            $st      = $standings[$uid] ?? null;
        ?>
        <tr>
          <td><?= e((string)$i) ?></td>
          <td><?= e((string)($u['username'] ?? '')) ?></td>
          <td><?= e((string)($u['display_name'] ?? '')) ?></td>
          <td><?= e((string)($u['email'] ?? '')) ?></td>
          <td>
            <?php if (preg_match('/^[A-Za-z]{2}$/', $cc)): ?>
              <img src="https://flagcdn.com/w20/<?= e(strtolower($cc)) ?>.png" width="20" height="15" alt=""> <?= e(strtoupper($cc)) ?>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
          <td><?= e($created > 0 ? date('Y-m-d', $created) : '—') ?></td>
          <td><span class="admin-badge admin-badge-ok"><?= e($st !== null ? (string)(int)$st['points'] : '0') ?></span></td>
          <td><?= e($st !== null ? ('#' . (int)$st['rank'] . ' / ' . (int)$st['total']) : '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($users) === 500): ?>
    <div class="admin-muted"><?= e($L('عُرضت أول 500 نتيجة فقط — استخدم البحث لتضييق القائمة.', 'Showing first 500 results only — use search to narrow the list.')) ?></div>
  <?php endif; ?>
</div>
