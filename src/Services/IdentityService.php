<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use WaterTruck\DAO\UserDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\OperatorDAO;

class IdentityService
{
    public function __construct(
        private UserDAO $userDAO,
        private TruckDAO $truckDAO,
        private OperatorDAO $operatorDAO
    ) {
    }

    /**
     * Get or create user by device token
     */
    public function getOrCreateByDeviceToken(string $deviceToken): array
    {
        $user = $this->userDAO->findByDeviceToken($deviceToken);
        
        if ($user === null) {
            $userId = $this->userDAO->create($deviceToken);
            $user = $this->userDAO->findById($userId);
        }
        
        return $this->enrichUser($user);
    }

    /**
     * Get user with related data (truck/operator info)
     */
    public function enrichUser(array $user): array
    {
        // Always check if user has a truck (regardless of role)
        $truck = $this->truckDAO->findByUserId((int) $user['id']);
        if ($truck) {
            // If truck is linked to an operator, get operator info
            if ($truck['operator_id']) {
                $operator = $this->operatorDAO->findById((int) $truck['operator_id']);
                if ($operator) {
                    $operatorUser = $this->userDAO->findById((int) $operator['user_id']);
                    $truck['operator_name'] = $operatorUser['name'] ?? $operator['service_area'] ?? 'Fleet';
                }
            }
            $user['truck'] = $truck;
        }
        
        // Always check if user is an operator (regardless of role)
        $operator = $this->operatorDAO->findByUserId((int) $user['id']);
        if ($operator) {
            $user['operator'] = $operator;
        }
        
        return $user;
    }

    /**
     * Update user profile (name, phone, email)
     */
    public function updateProfile(int $userId, array $data): array
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }
        
        if (isset($data['phone'])) {
            $updateData['phone'] = trim($data['phone']);
        }
        
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email format');
            }
            if ($this->userDAO->emailExists($email, $userId)) {
                throw new \InvalidArgumentException('Email already in use');
            }
            $updateData['email'] = $email;
        }
        
        if (!empty($updateData)) {
            $this->userDAO->update($userId, $updateData);
        }
        
        $user = $this->userDAO->findById($userId);
        return $this->enrichUser($user);
    }

    /**
     * Upgrade user role
     */
    public function setRole(int $userId, string $role): bool
    {
        $validRoles = ['customer', 'truck', 'operator', 'admin'];
        if (!in_array($role, $validRoles, true)) {
            throw new \InvalidArgumentException('Invalid role');
        }
        
        return $this->userDAO->update($userId, ['role' => $role]);
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $userId): ?array
    {
        $user = $this->userDAO->findById($userId);
        return $user ? $this->enrichUser($user) : null;
    }
}
