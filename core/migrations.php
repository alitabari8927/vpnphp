<?php
function migrations(): array {
    return [
        '001_initial' => function(PDO $pdo): void {
            // Initial schema is created by installer. Marker only.
        },
        '002_devices' => function(PDO $pdo): void {
            $pdo->exec("CREATE TABLE IF NOT EXISTS devices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                device_id VARCHAR(190) NOT NULL UNIQUE,
                model VARCHAR(80) NULL,
                manufacturer VARCHAR(60) NULL,
                android_version VARCHAR(20) NULL,
                app_version VARCHAR(20) NULL,
                ip VARCHAR(45) NULL,
                blocked TINYINT(1) NOT NULL DEFAULT 0,
                request_count INT UNSIGNED NOT NULL DEFAULT 0,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                INDEX(last_seen_at), INDEX(blocked)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        },
        '003_ads_stats_placement' => function(PDO $pdo): void {
            $cols = $pdo->query('SHOW COLUMNS FROM ads')->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('impressions', $cols, true)) {
                $pdo->exec('ALTER TABLE ads ADD COLUMN impressions INT UNSIGNED NOT NULL DEFAULT 0 AFTER active');
            }
            if (!in_array('clicks', $cols, true)) {
                $pdo->exec('ALTER TABLE ads ADD COLUMN clicks INT UNSIGNED NOT NULL DEFAULT 0 AFTER impressions');
            }
            $indexes = $pdo->query("SHOW INDEX FROM ads WHERE Key_name='placement'")->fetchAll();
            if (!$indexes) {
                $pdo->exec('ALTER TABLE ads ADD INDEX placement (placement)');
            }
        },
    ];
}
function run_migrations(PDO $pdo): array {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (id VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $applied=$pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $done=[];
    foreach(migrations() as $id=>$fn){
        if(in_array($id,$applied,true))continue;
        $pdo->beginTransaction();
        try{$fn($pdo);$pdo->prepare('INSERT INTO schema_migrations(id,applied_at) VALUES(?,NOW())')->execute([$id]);$pdo->commit();$done[]=$id;}
        catch(Throwable $e){$pdo->rollBack();throw $e;}
    }
    return $done;
}
