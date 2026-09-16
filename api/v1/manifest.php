<?php require_once dirname(__DIR__,2).'/core/bootstrap.php';require_app_key($pdo);

// Register/refresh this device's check-in (model, IP, versions) and enforce blocks.
$deviceId = trim((string)($_SERVER['HTTP_X_DEVICE_ID'] ?? ''));
if ($deviceId !== '') {
    $device = upsert_device($pdo, [
        'device_id'       => $deviceId,
        'model'           => $_SERVER['HTTP_X_DEVICE_MODEL'] ?? '',
        'manufacturer'    => $_SERVER['HTTP_X_DEVICE_MANUFACTURER'] ?? '',
        'android_version' => $_SERVER['HTTP_X_ANDROID_VERSION'] ?? '',
        'app_version'     => $_SERVER['HTTP_X_APP_VERSION'] ?? '',
        'ip'              => client_ip(),
    ]);
    if (!empty($device['blocked'])) {
        json_response(['ok' => false, 'error' => 'device_blocked'], 403);
    }
}

if(setting($pdo,'maintenance_mode','0')==='1')json_response(['ok'=>true,'maintenance'=>true,'minimum_app_version'=>setting($pdo,'minimum_app_version','1.0.0'),'servers'=>[],'ads'=>['pre_connect'=>ads_for_placement($pdo,'pre_connect'),'post_connect'=>ads_for_placement($pdo,'post_connect')]]);
// Refresh only stale sources. For best performance, also configure cron.
refresh_all_subscriptions($pdo,false);
$nodes=get_cached_nodes($pdo);
json_response(['ok'=>true,'maintenance'=>false,'generated_at'=>gmdate('c'),'minimum_app_version'=>setting($pdo,'minimum_app_version','1.0.0'),'servers'=>$nodes,'ads'=>['pre_connect'=>ads_for_placement($pdo,'pre_connect'),'post_connect'=>ads_for_placement($pdo,'post_connect')]]);
