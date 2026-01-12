<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class OperatorDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM operators WHERE id = ?');
        $stmt->execute([$id]);
        $operator = $stmt->fetch();
        return $operator ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM operators WHERE user_id = ?');
        $stmt->execute([$userId]);
        $operator = $stmt->fetch();
        return $operator ?: null;
    }

    public function create(int $userId, string $mode = 'delegated', ?string $serviceArea = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO operators (user_id, mode, service_area) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $mode, $serviceArea]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateMode(int $id, string $mode): bool
    {
        $stmt = $this->pdo->prepare('UPDATE operators SET mode = ? WHERE id = ?');
        return $stmt->execute([$mode, $id]);
    }

    public function updateServiceArea(int $id, string $serviceArea): bool
    {
        $stmt = $this->pdo->prepare('UPDATE operators SET service_area = ? WHERE id = ?');
        return $stmt->execute([$serviceArea, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM operators WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getTruckCount(int $operatorId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM trucks WHERE operator_id = ?');
        $stmt->execute([$operatorId]);
        return (int) $stmt->fetchColumn();
    }
}
