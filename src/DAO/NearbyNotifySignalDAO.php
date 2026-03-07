<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class NearbyNotifySignalDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function isRateLimited(string $identityHash, int $maxRequests, int $windowSeconds): bool
    {
        $safeMax = max(1, $maxRequests);
        $safeWindow = max(1, $windowSeconds);

        $sql = "
            SELECT COUNT(*) AS request_count
            FROM nearby_notify_signals
            WHERE identity_hash = :identity_hash
              AND created_at >= DATE_SUB(NOW(), INTERVAL {$safeWindow} SECOND)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['identity_hash' => $identityHash]);
        $count = (int) $stmt->fetchColumn();

        return $count >= $safeMax;
    }

    public function tryRegisterSignal(
        string $identityHash,
        string $dedupeKey,
        string $latBucket,
        string $lngBucket
    ): bool {
        $sql = "
            INSERT INTO nearby_notify_signals (
                dedupe_key,
                identity_hash,
                lat_bucket,
                lng_bucket
            )
            VALUES (
                :dedupe_key,
                :identity_hash,
                :lat_bucket,
                :lng_bucket
            )
            ON DUPLICATE KEY UPDATE dedupe_key = dedupe_key
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dedupe_key' => $dedupeKey,
            'identity_hash' => $identityHash,
            'lat_bucket' => $latBucket,
            'lng_bucket' => $lngBucket,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function cleanupOldSignals(int $retentionHours): int
    {
        $safeHours = max(1, $retentionHours);
        $sql = "DELETE FROM nearby_notify_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL {$safeHours} HOUR)";
        return $this->pdo->exec($sql);
    }
}
