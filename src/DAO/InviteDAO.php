<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class InviteDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites WHERE id = ?');
        $stmt->execute([$id]);
        $invite = $stmt->fetch();
        return $invite ?: null;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invites WHERE token = ?');
        $stmt->execute([$token]);
        $invite = $stmt->fetch();
        return $invite ?: null;
    }

    public function findByOperatorId(int $operatorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, t.name as truck_name
             FROM invites i
             LEFT JOIN trucks t ON i.truck_id = t.id
             WHERE i.operator_id = ?
             ORDER BY i.created_at DESC'
        );
        $stmt->execute([$operatorId]);
        return $stmt->fetchAll();
    }

    public function create(int $operatorId, string $token): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO invites (operator_id, token) VALUES (?, ?)'
        );
        $stmt->execute([$operatorId, $token]);
        return (int) $this->pdo->lastInsertId();
    }

    public function markUsed(int $id, int $truckId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invites SET used = 1, truck_id = ?, used_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$truckId, $id]);
    }

    public function isValid(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM invites WHERE token = ? AND used = 0'
        );
        $stmt->execute([$token]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM invites WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
