<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class NotificationDeliveryQueueDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function enqueueRetry(
        int $userId,
        int $truckId,
        int $jobId,
        string $title,
        string $body,
        array $payloadData,
        int $attemptCount,
        int $maxAttempts,
        int $delaySeconds
    ): bool {
        $dedupeKey = sprintf('job:%d:truck:%d', $jobId, $truckId);

        $sql = "
            INSERT INTO notification_delivery_queue (
                dedupe_key,
                user_id,
                truck_id,
                job_id,
                title,
                body,
                payload_json,
                attempt_count,
                max_attempts,
                status,
                next_attempt_at
            )
            VALUES (
                :dedupe_key,
                :user_id,
                :truck_id,
                :job_id,
                :title,
                :body,
                :payload_json,
                :attempt_count,
                :max_attempts,
                'pending',
                DATE_ADD(NOW(), INTERVAL :delay_seconds SECOND)
            )
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                title = VALUES(title),
                body = VALUES(body),
                payload_json = VALUES(payload_json),
                attempt_count = LEAST(attempt_count, VALUES(attempt_count)),
                max_attempts = VALUES(max_attempts),
                status = CASE
                    WHEN status = 'dead_letter' THEN status
                    ELSE 'pending'
                END,
                next_attempt_at = CASE
                    WHEN status = 'dead_letter' THEN next_attempt_at
                    ELSE NOW()
                END,
                updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'dedupe_key' => $dedupeKey,
            'user_id' => $userId,
            'truck_id' => $truckId,
            'job_id' => $jobId,
            'title' => $title,
            'body' => $body,
            'payload_json' => json_encode($payloadData),
            'attempt_count' => $attemptCount,
            'max_attempts' => $maxAttempts,
            'delay_seconds' => max(0, $delaySeconds),
        ]);
    }

    public function fetchDue(int $limit): array
    {
        $safeLimit = max(1, $limit);
        $sql = "
            SELECT *
            FROM notification_delivery_queue
            WHERE status IN ('pending', 'failed')
              AND next_attempt_at <= NOW()
            ORDER BY next_attempt_at ASC, id ASC
            LIMIT {$safeLimit}
        ";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function markProcessing(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_delivery_queue
             SET status = 'processing', locked_at = NOW(), updated_at = CURRENT_TIMESTAMP
             WHERE id = ?
               AND status IN ('pending', 'failed')
               AND next_attempt_at <= NOW()"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function markSent(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_delivery_queue
             SET status = 'sent', updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    public function markForRetry(int $id, int $attemptCount, string $lastError, int $delaySeconds): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_delivery_queue
             SET status = 'failed',
                 attempt_count = ?,
                 last_error = ?,
                 next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );

        return $stmt->execute([$attemptCount, $lastError, $delaySeconds, $id]);
    }

    public function markDeadLetter(int $id, int $attemptCount, string $lastError): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notification_delivery_queue
             SET status = 'dead_letter',
                 attempt_count = ?,
                 last_error = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        );

        return $stmt->execute([$attemptCount, $lastError, $id]);
    }
}
