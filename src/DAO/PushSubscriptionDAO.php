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
     * Save or update a push subscription for a user
     */
    public function save(int $userId, string $endpoint, string $p256dh, string $auth): bool
    {
        $sql = "
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
            VALUES (:user_id, :endpoint, :p256dh, :auth)
            ON DUPLICATE KEY UPDATE
                endpoint = VALUES(endpoint),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                created_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
        ]);
    }

    /**
     * Get push subscription for a user
     */
    public function getByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM push_subscriptions WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get push subscriptions for multiple users
     */
    public function getByUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT * FROM push_subscriptions WHERE user_id IN ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($userIds);
        return $stmt->fetchAll();
    }

    /**
     * Delete push subscription for a user
     */
    public function delete(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_subscriptions WHERE user_id = :user_id'
        );
        return $stmt->execute(['user_id' => $userId]);
    }
}
