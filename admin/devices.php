<?php require_once dirname(__DIR__).'/core/bootstrap.php';require_admin();$title='دستگاه‌ها';

$q      = trim($_GET['q'] ?? '');
$filterParam = (string)($_GET['filter'] ?? 'all');
$filter = in_array($filterParam, ['all', 'online', 'blocked'], true) ? $filterParam : 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$returnQs = http_build_query(['q' => $q, 'filter' => $filter, 'page' => $page]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($action === 'block') { $pdo->prepare('UPDATE devices SET blocked=1 WHERE id=?')->execute([$id]); flash('success', 'دستگاه مسدود شد.'); }
        if ($action === 'unblock') { $pdo->prepare('UPDATE devices SET blocked=0 WHERE id=?')->execute([$id]); flash('success', 'دستگاه آزاد شد.'); }
        if ($action === 'delete') { $pdo->prepare('DELETE FROM devices WHERE id=?')->execute([$id]); flash('success', 'دستگاه حذف شد.'); }
    }
    $qs = (string)($_POST['return_qs'] ?? $returnQs);
    redirect('/admin/devices.php' . ($qs !== '' ? '?' . $qs : ''));
}

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(model LIKE ? OR manufacturer LIKE ? OR ip LIKE ? OR device_id LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($filter === 'blocked') $where[] = 'blocked = 1';
if ($filter === 'online') $where[] = 'last_seen_at >= (NOW() - INTERVAL 3 MINUTE)';
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM devices $whereSql");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM devices $whereSql ORDER BY last_seen_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$devices = $stmt->fetchAll();

$totalDevices   = (int)$pdo->query('SELECT COUNT(*) FROM devices')->fetchColumn();
$onlineDevices  = (int)$pdo->query("SELECT COUNT(*) FROM devices WHERE last_seen_at >= (NOW() - INTERVAL 3 MINUTE)")->fetchColumn();
$blockedDevices = (int)$pdo->query('SELECT COUNT(*) FROM devices WHERE blocked=1')->fetchColumn();

require __DIR__ . '/_header.php';
?>
<div class="grid">
  <div class="card"><span class="muted">کل دستگاه‌ها</span><div class="metric"><?=$totalDevices?></div></div>
  <div class="card"><span class="muted">آنلاین الان</span><div class="metric"><?=$onlineDevices?></div></div>
  <div class="card"><span class="muted">مسدود شده</span><div class="metric"><?=$blockedDevices?></div></div>
</div>

<div class="card" style="margin-top:14px">
  <form method="get" class="actions" style="align-items:center">
    <input type="text" name="q" value="<?=e($q)?>" placeholder="جستجو: مدل، سازنده، IP یا شناسه دستگاه" style="max-width:280px">
    <select name="filter" style="max-width:150px">
      <option value="all" <?=$filter==='all'?'selected':''?>>همه دستگاه‌ها</option>
      <option value="online" <?=$filter==='online'?'selected':''?>>فقط آنلاین</option>
      <option value="blocked" <?=$filter==='blocked'?'selected':''?>>فقط مسدود</option>
    </select>
    <button>جستجو</button>
    <a class="btn secondary" href="<?=e(app_path('/admin/devices_export.php?' . http_build_query(['q' => $q, 'filter' => $filter])))?>">خروجی CSV</a>
  </form>
</div>

<div class="card" style="margin-top:14px">
<table>
<thead><tr>
  <th>وضعیت</th><th>مدل گوشی</th><th>سازنده</th><th>اندروید</th><th>نسخه اپ</th><th>آی‌پی</th><th>اولین اتصال</th><th>آخرین اتصال</th><th>عملیات</th>
</tr></thead>
<tbody>
<?php foreach ($devices as $d): $online = device_is_online($d['last_seen_at']); ?>
<tr>
  <td>
    <?php if ((int)$d['blocked'] === 1): ?><span class="badge bad">مسدود</span>
    <?php elseif ($online): ?><span class="badge good">آنلاین</span>
    <?php else: ?><span class="badge">آفلاین</span>
    <?php endif; ?>
  </td>
  <td><?=e($d['model'] ?: '—')?></td>
  <td><?=e($d['manufacturer'] ?: '—')?></td>
  <td><?=$d['android_version'] ? 'Android ' . e($d['android_version']) : '—'?></td>
  <td><?=e($d['app_version'] ?: '—')?></td>
  <td><code><?=e($d['ip'] ?: '—')?></code></td>
  <td><small><?=e($d['first_seen_at'])?></small></td>
  <td><small><?=e($d['last_seen_at'])?></small></td>
  <td>
    <div class="actions">
      <form method="post">
        <?=csrf_field()?>
        <input type="hidden" name="id" value="<?=$d['id']?>">
        <input type="hidden" name="return_qs" value="<?=e($returnQs)?>">
        <?php if ((int)$d['blocked'] === 1): ?>
          <input type="hidden" name="action" value="unblock"><button class="btn secondary">آزاد کردن</button>
        <?php else: ?>
          <input type="hidden" name="action" value="block"><button class="btn bad">مسدود کردن</button>
        <?php endif; ?>
      </form>
      <form method="post" onsubmit="return confirm('این دستگاه حذف شود؟')">
        <?=csrf_field()?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?=$d['id']?>">
        <input type="hidden" name="return_qs" value="<?=e($returnQs)?>">
        <button class="btn bad">حذف</button>
      </form>
    </div>
  </td>
</tr>
<?php endforeach; if (!$devices): ?>
<tr><td colspan="9" class="muted">دستگاهی پیدا نشد.</td></tr>
<?php endif; ?>
</tbody>
</table>

<?php if ($pages > 1): ?>
<div class="actions" style="margin-top:12px">
  <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a class="btn <?=$p===$page?'':'secondary'?>" href="?<?=e(http_build_query(['q'=>$q,'filter'=>$filter,'page'=>$p]))?>"><?=$p?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php';
