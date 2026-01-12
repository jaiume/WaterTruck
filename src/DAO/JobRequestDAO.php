<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class JobRequestDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM job_requests WHERE id = ?');
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        return $request ?: null;
    }

    public function findByJobId(int $jobId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT jr.*, t.name as truck_name, t.capacity_gallons, t.price_fixed
             FROM job_requests jr
             INNER JOIN trucks t ON jr.truck_id = t.id
             WHERE jr.job_id = ?
             ORDER BY jr.created_at'
        );
        $stmt->execute([$jobId]);
        return $stmt->fetchAll();
    }

    public function findByTruckId(int $truckId, ?string $status = null): array
    {
        $sql = 'SELECT jr.*, j.location, j.customer_name, j.created_at as job_created_at
                FROM job_requests jr
                INNER JOIN jobs j ON jr.job_id = j.id
                WHERE jr.truck_id = ?';
        $params = [$truckId];

        if ($status !== null) {
            $sql .= ' AND jr.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY jr.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findPendingByTruckId(int $truckId): array
    {
        $sql = "
            SELECT jr.*, j.location, j.customer_name, j.customer_phone, j.lat, j.lng, j.created_at as job_created_at
            FROM job_requests jr
            INNER JOIN jobs j ON jr.job_id = j.id
            WHERE jr.truck_id = ?
            AND jr.status = 'pending'
            AND j.status = 'pending'
            ORDER BY jr.created_at ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$truckId]);
        return $stmt->fetchAll();
    }

    public function findByJobAndTruck(int $jobId, int $truckId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM job_requests WHERE job_id = ? AND truck_id = ?'
        );
        $stmt->execute([$jobId, $truckId]);
        $request = $stmt->fetch();
        return $request ?: null;
    }

    public function create(int $jobId, int $truckId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO job_requests (job_id, truck_id) VALUES (?, ?)'
        );
        $stmt->execute([$jobId, $truckId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE job_requests SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function accept(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE job_requests SET status = 'accepted' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function reject(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE job_requests SET status = 'rejected' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Expire all other pending requests for a job (when one is accepted)
     */
    public function expireOthersForJob(int $jobId, int $exceptRequestId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE job_requests SET status = 'expired' WHERE job_id = ? AND id != ? AND status = 'pending'"
        );
        return $stmt->execute([$jobId, $exceptRequestId]);
    }

    /**
     * Expire all requests for a job (when cancelled)
     */
    public function expireAllForJob(int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE job_requests SET status = 'expired' WHERE job_id = ? AND status = 'pending'"
        );
        return $stmt->execute([$jobId]);
    }

    /**
     * Check if any request for this job has been accepted
     */
    public function hasAcceptedRequest(int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM job_requests WHERE job_id = ? AND status = 'accepted'"
        );
        $stmt->execute([$jobId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Check if all requests for a job have been rejected
     */
    public function allRejected(int $jobId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM job_requests WHERE job_id = ? AND status = 'pending'"
        );
        $stmt->execute([$jobId]);
        return (int) $stmt->fetchColumn() === 0;
    }
}
