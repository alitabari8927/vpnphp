<?php
/** Valid ad placements shown in the Android app. */
function ad_placements(): array {
    return [
        'pre_connect'  => 'قبل از اتصال (تمام‌صفحه)',
        'post_connect' => 'بعد از اتصال موفق (بنر کوچک)',
    ];
}

/**
 * Returns up to $limit active, currently-scheduled ads for a placement,
 * ordered by priority. The app rotates randomly among the returned list
 * (weighted by priority) instead of always showing the same single ad.
 */
function ads_for_placement(PDO $pdo, string $placement, int $limit = 5): array {
    $stmt = $pdo->prepare(
        "SELECT id, title, image_path, target_url, display_seconds, priority
         FROM ads
         WHERE active=1 AND placement=? AND plan='free'
           AND (starts_at IS NULL OR starts_at <= NOW())
           AND (ends_at IS NULL OR ends_at >= NOW())
         ORDER BY priority DESC, id DESC
         LIMIT " . max(1, (int)$limit)
    );
    $stmt->execute([$placement]);
    $rows = $stmt->fetchAll();

    $base = rtrim(setting($pdo, 'base_url', ''), '/');
    return array_map(static function (array $a) use ($base, $placement): array {
        return [
            'id'               => (int)$a['id'],
            'title'            => $a['title'],
            'image_url'        => $base . '/' . ltrim($a['image_path'], '/'),
            'target_url'       => $a['target_url'],
            'display_seconds'  => (int)$a['display_seconds'],
            'placement'        => $placement,
        ];
    }, $rows);
}

/** Whether an ad is currently within its optional scheduled window. */
function ad_schedule_status(?string $startsAt, ?string $endsAt): string {
    $now = time();
    if ($startsAt && strtotime($startsAt) > $now) return 'scheduled';
    if ($endsAt && strtotime($endsAt) < $now) return 'expired';
    return 'live';
}

/** Records an impression or click for an ad. Silently ignores unknown ids/events. */
function record_ad_event(PDO $pdo, int $adId, string $event): void {
    if ($adId <= 0) return;
    $column = match ($event) {
        'impression' => 'impressions',
        'click' => 'clicks',
        default => null,
    };
    if ($column === null) return;
    $pdo->prepare("UPDATE ads SET $column = $column + 1 WHERE id = ?")->execute([$adId]);
}
