<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use WaterTruck\DAO\InviteDAO;
use WaterTruck\DAO\OperatorDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\UserDAO;

class InviteService
{
    public function __construct(
        private InviteDAO $inviteDAO,
        private OperatorDAO $operatorDAO,
        private TruckDAO $truckDAO,
        private UserDAO $userDAO
    ) {
    }

    /**
     * Generate a new invite link for an operator
     */
    public function createInvite(int $operatorId): array
    {
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator) {
            throw new \RuntimeException('Operator not found');
        }
        
        $token = UtilityService::generateUuid();
        $inviteId = $this->inviteDAO->create($operatorId, $token);
        
        $invite = $this->inviteDAO->findById($inviteId);
        $invite['url'] = UtilityService::getBaseUrl() . '/invite/' . $token;
        
        return $invite;
    }

    /**
     * Get invite details by token
     */
    public function getInviteByToken(string $token): ?array
    {
        $invite = $this->inviteDAO->findByToken($token);
        if (!$invite) {
            return null;
        }
        
        // Get operator info
        $operator = $this->operatorDAO->findById((int) $invite['operator_id']);
        if ($operator) {
            $user = $this->userDAO->findById((int) $operator['user_id']);
            $invite['operator_name'] = $user['name'] ?? 'Unknown Operator';
        }
        
        return $invite;
    }

    /**
     * Redeem an invite - bind truck to operator
     */
    public function redeemInvite(string $token, int $userId): array
    {
        $invite = $this->inviteDAO->findByToken($token);
        if (!$invite) {
            throw new \RuntimeException('Invalid invite token');
        }
        
        if ($invite['used']) {
            throw new \RuntimeException('Invite has already been used');
        }
        
        // Check if user already has a truck
        $existingTruck = $this->truckDAO->findByUserId($userId);
        
        if ($existingTruck) {
            // Bind existing truck to operator
            $this->truckDAO->setOperator((int) $existingTruck['id'], (int) $invite['operator_id']);
            $truckId = (int) $existingTruck['id'];
        } else {
            // Create new truck for user, linked to operator
            $this->userDAO->update($userId, ['role' => 'truck']);
            $truckId = $this->truckDAO->create($userId, (int) $invite['operator_id']);
        }
        
        // Mark invite as used
        $this->inviteDAO->markUsed((int) $invite['id'], $truckId);
        
        return [
            'success' => true,
            'truck_id' => $truckId,
            'operator_id' => $invite['operator_id']
        ];
    }

    /**
     * Check if invite token is valid
     */
    public function isValidToken(string $token): bool
    {
        return $this->inviteDAO->isValid($token);
    }

    /**
     * Get all invites for an operator
     */
    public function getOperatorInvites(int $operatorId): array
    {
        return $this->inviteDAO->findByOperatorId($operatorId);
    }
}
