<?php require_once dirname(__DIR__).'/core/bootstrap.php';require_admin();

$q      = trim($_GET['q'] ?? '');
$filter = in_array($_GET['filter'] ?? 'all', ['all', 'online', 'blocked'], true) ? $_GET['filter'] : 'all';

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

$stmt = $pdo->prepare("SELECT device_id, model, manufacturer, android_version, app_version, ip, blocked, request_count, first_seen_at, last_seen_at FROM devices $whereSql ORDER BY last_seen_at DESC");
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="devices-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens Persian text correctly
$out = fopen('php://output', 'w');
fputcsv($out, ['Device ID', 'Model', 'Manufacturer', 'Android Version', 'App Version', 'IP', 'Blocked', 'Request Count', 'First Seen', 'Last Seen']);
while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['device_id'], $row['model'], $row['manufacturer'], $row['android_version'], $row['app_version'],
        $row['ip'], $row['blocked'] ? 'yes' : 'no', $row['request_count'], $row['first_seen_at'], $row['last_seen_at'],
    ]);
}
fclose($out);
exit;
