<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class PushSubscriptionDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Save or update a push subscription for a truck
     */
    public function save(int $truckId, string $endpoint, string $p256dh, string $auth): bool
    {
        $sql = "
            INSERT INTO push_subscriptions (truck_id, endpoint, p256dh, auth)
            VALUES (:truck_id, :endpoint, :p256dh, :auth)
            ON DUPLICATE KEY UPDATE
                endpoint = VALUES(endpoint),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                created_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'truck_id' => $truckId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
        ]);
    }

    /**
     * Get push subscription for a truck
     */
    public function getByTruckId(int $truckId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM push_subscriptions WHERE truck_id = :truck_id'
        );
        $stmt->execute(['truck_id' => $truckId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get push subscriptions for multiple trucks
     */
    public function getByTruckIds(array $truckIds): array
    {
        if (empty($truckIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($truckIds), '?'));
        $sql = "SELECT * FROM push_subscriptions WHERE truck_id IN ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($truckIds);
        return $stmt->fetchAll();
    }

    /**
     * Delete push subscription for a truck
     */
    public function delete(int $truckId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_subscriptions WHERE truck_id = :truck_id'
        );
        return $stmt->execute(['truck_id' => $truckId]);
    }
}
