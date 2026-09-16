<?php
/**
 * Registers/updates a device check-in coming from the Android app.
 * Called from api/v1/manifest.php on every manifest request.
 *
 * @return array{ok:bool, blocked:bool}
 */
function upsert_device(PDO $pdo, array $data): array
{
    $deviceId = trim((string)($data['device_id'] ?? ''));
    if ($deviceId === '' || strlen($deviceId) > 190) {
        return ['ok' => false, 'blocked' => false];
    }

    $ip             = text_substr((string)($data['ip'] ?? client_ip()), 0, 45);
    $model          = text_substr(trim((string)($data['model'] ?? '')), 0, 80);
    $manufacturer   = text_substr(trim((string)($data['manufacturer'] ?? '')), 0, 60);
    $androidVersion = text_substr(trim((string)($data['android_version'] ?? '')), 0, 20);
    $appVersion     = text_substr(trim((string)($data['app_version'] ?? '')), 0, 20);

    $stmt = $pdo->prepare('SELECT id, blocked FROM devices WHERE device_id = ? LIMIT 1');
    $stmt->execute([$deviceId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare('UPDATE devices SET model=?, manufacturer=?, android_version=?, app_version=?, ip=?, last_seen_at=NOW(), request_count=request_count+1 WHERE id=?')
            ->execute([$model, $manufacturer, $androidVersion, $appVersion, $ip, $existing['id']]);
        return ['ok' => true, 'blocked' => (bool)$existing['blocked']];
    }

    $pdo->prepare('INSERT INTO devices(device_id, model, manufacturer, android_version, app_version, ip, blocked, request_count, first_seen_at, last_seen_at) VALUES (?,?,?,?,?,?,0,1,NOW(),NOW())')
        ->execute([$deviceId, $model, $manufacturer, $androidVersion, $appVersion, $ip]);

    return ['ok' => true, 'blocked' => false];
}

/** A device counts as "online" if it checked in within the last few minutes. */
function device_is_online(?string $lastSeenAt, int $windowSeconds = 180): bool
{
    if (!$lastSeenAt) return false;
    return (time() - strtotime($lastSeenAt)) <= $windowSeconds;
}
