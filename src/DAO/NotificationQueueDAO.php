<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class NotificationQueueDAO
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Increment customer count for a truck or create new queue entry
     */
    public function incrementCustomerCount(int $truckId): bool
    {
        $sql = "
            INSERT INTO notification_queue (truck_id, customer_count, last_customer_at)
            VALUES (:truck_id, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                customer_count = customer_count + 1,
                last_customer_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['truck_id' => $truckId]);
    }

    /**
     * Get queue entry for a truck
     */
    public function getByTruckId(int $truckId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_queue WHERE truck_id = :truck_id'
        );
        $stmt->execute(['truck_id' => $truckId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all trucks that need notification (not notified within throttle period)
     */
    public function getTrucksNeedingNotification(int $throttleMinutes): array
    {
        $sql = "
            SELECT nq.*, ps.endpoint, ps.p256dh, ps.auth
            FROM notification_queue nq
            INNER JOIN push_subscriptions ps ON nq.truck_id = ps.truck_id
            WHERE nq.last_notified_at IS NULL 
               OR nq.last_notified_at <= DATE_SUB(NOW(), INTERVAL :throttle MINUTE)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['throttle' => $throttleMinutes]);
        return $stmt->fetchAll();
    }

    /**
     * Mark truck as notified and reset customer count
     */
    public function markNotified(int $truckId): bool
    {
        $sql = "
            UPDATE notification_queue 
            SET last_notified_at = CURRENT_TIMESTAMP, customer_count = 0
            WHERE truck_id = :truck_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['truck_id' => $truckId]);
    }

    /**
     * Delete queue entry for a truck
     */
    public function delete(int $truckId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notification_queue WHERE truck_id = :truck_id'
        );
        return $stmt->execute(['truck_id' => $truckId]);
    }

    /**
     * Check if truck can be notified (not within throttle period)
     */
    public function canNotify(int $truckId, int $throttleMinutes): bool
    {
        $sql = "
            SELECT COUNT(*) as count FROM notification_queue 
            WHERE truck_id = :truck_id 
            AND last_notified_at IS NOT NULL
            AND last_notified_at > DATE_SUB(NOW(), INTERVAL :throttle MINUTE)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'truck_id' => $truckId,
            'throttle' => $throttleMinutes,
        ]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] === 0;
    }
}
