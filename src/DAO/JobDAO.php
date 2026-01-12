<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class JobDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jobs WHERE id = ?');
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        return $job ?: null;
    }

    public function findByIdWithDetails(int $id): ?array
    {
        $sql = "
            SELECT 
                j.*,
                t.name as truck_name,
                t.phone as truck_phone,
                t.capacity_gallons,
                u.name as customer_display_name
            FROM jobs j
            LEFT JOIN trucks t ON j.truck_id = t.id
            LEFT JOIN users u ON j.customer_user_id = u.id
            WHERE j.id = ?
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        return $job ?: null;
    }

    public function findByCustomerId(int $customerId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM jobs WHERE customer_user_id = ?';
        $params = [$customerId];

        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByTruckId(int $truckId, ?string $status = null): array
    {
        $sql = 'SELECT id, customer_user_id, truck_id, status, price, location, customer_name, customer_phone, lat, lng, created_at, accepted_at, completed_at FROM jobs WHERE truck_id = ?';
        $params = [$truckId];

        if ($status !== null) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(
        int $customerUserId,
        string $location,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?float $lat = null,
        ?float $lng = null
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (customer_user_id, location, customer_name, customer_phone, lat, lng) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$customerUserId, $location, $customerName, $customerPhone, $lat, $lng]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['truck_id', 'status', 'price', 'accepted_at', 'completed_at'];
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE jobs SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function accept(int $jobId, int $truckId, float $price): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE jobs SET truck_id = ?, status = 'accepted', price = ?, accepted_at = NOW() 
             WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$truckId, $price, $jobId]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $sql = 'UPDATE jobs SET status = ?';
        
        if ($status === 'delivered') {
            $sql .= ', completed_at = NOW()';
        }
        
        $sql .= ' WHERE id = ?';
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $id]);
    }

    /**
     * Get pending jobs for trucks under an operator
     */
    public function findPendingByOperatorId(int $operatorId): array
    {
        $sql = "
            SELECT j.*, jr.truck_id as requested_truck_id, t.name as requested_truck_name
            FROM jobs j
            INNER JOIN job_requests jr ON j.id = jr.job_id AND jr.status = 'pending'
            INNER JOIN trucks t ON jr.truck_id = t.id
            WHERE t.operator_id = ?
            AND j.status = 'pending'
            ORDER BY j.created_at ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$operatorId]);
        return $stmt->fetchAll();
    }

    /**
     * Get active jobs for trucks under an operator
     */
    public function findActiveByOperatorId(int $operatorId): array
    {
        $sql = "
            SELECT j.*, t.name as truck_name
            FROM jobs j
            INNER JOIN trucks t ON j.truck_id = t.id
            WHERE t.operator_id = ?
            AND j.status IN ('accepted', 'en_route')
            ORDER BY j.accepted_at DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$operatorId]);
        return $stmt->fetchAll();
    }
}
