<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class UserDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByDeviceToken(string $deviceToken): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE device_token = ?');
        $stmt->execute([$deviceToken]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE phone = ?');
        $stmt->execute([$phone]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $deviceToken, string $role = 'customer'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (device_token, role) VALUES (?, ?)'
        );
        $stmt->execute([$deviceToken, $role]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'phone', 'email', 'role'];
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
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }
}
