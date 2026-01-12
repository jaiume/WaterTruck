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
     * Increment customer count for a user or create new queue entry
     */
    public function incrementCustomerCount(int $userId): bool
    {
        $sql = "
            INSERT INTO notification_queue (user_id, customer_count, last_customer_at)
            VALUES (:user_id, 1, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                customer_count = customer_count + 1,
                last_customer_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Get queue entry for a user
     */
    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_queue WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all users that need notification (not notified within throttle period)
     */
    public function getUsersNeedingNotification(int $throttleMinutes): array
    {
        $sql = "
            SELECT nq.*, ps.endpoint, ps.p256dh, ps.auth
            FROM notification_queue nq
            INNER JOIN push_subscriptions ps ON nq.user_id = ps.user_id
            WHERE nq.last_notified_at IS NULL 
               OR nq.last_notified_at <= DATE_SUB(NOW(), INTERVAL :throttle MINUTE)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['throttle' => $throttleMinutes]);
        return $stmt->fetchAll();
    }

    /**
     * Mark user as notified and reset customer count
     */
    public function markNotified(int $userId): bool
    {
        $sql = "
            UPDATE notification_queue 
            SET last_notified_at = CURRENT_TIMESTAMP, customer_count = 0
            WHERE user_id = :user_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Delete queue entry for a user
     */
    public function delete(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notification_queue WHERE user_id = :user_id'
        );
        return $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Check if user can be notified (not within throttle period)
     */
    public function canNotify(int $userId, int $throttleMinutes): bool
    {
        $sql = "
            SELECT COUNT(*) as count FROM notification_queue 
            WHERE user_id = :user_id 
            AND last_notified_at IS NOT NULL
            AND last_notified_at > DATE_SUB(NOW(), INTERVAL :throttle MINUTE)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'throttle' => $throttleMinutes,
        ]);
        
        $result = $stmt->fetch();
        return (int) $result['count'] === 0;
    }
}
