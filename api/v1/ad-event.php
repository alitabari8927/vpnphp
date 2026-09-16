<?php require_once dirname(__DIR__,2).'/core/bootstrap.php';require_app_key($pdo);

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '[]', true);
if (!is_array($body)) $body = [];

$adId = (int)($body['ad_id'] ?? $_POST['ad_id'] ?? 0);
$event = (string)($body['event'] ?? $_POST['event'] ?? '');

if ($adId <= 0 || !in_array($event, ['impression', 'click'], true)) {
    json_response(['ok' => false, 'error' => 'invalid_request'], 400);
}

record_ad_event($pdo, $adId, $event);
json_response(['ok' => true]);
