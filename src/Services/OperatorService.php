<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use WaterTruck\DAO\OperatorDAO;
use WaterTruck\DAO\UserDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\JobDAO;

class OperatorService
{
    public function __construct(
        private OperatorDAO $operatorDAO,
        private UserDAO $userDAO,
        private TruckDAO $truckDAO,
        private JobDAO $jobDAO
    ) {
    }

    /**
     * Create operator profile for a user
     */
    public function createOperator(int $userId, ?string $serviceArea = null): array
    {
        // Check if user already has an operator profile
        $existing = $this->operatorDAO->findByUserId($userId);
        if ($existing) {
            throw new \RuntimeException('User is already an operator');
        }
        
        // Update user role
        $this->userDAO->update($userId, ['role' => 'operator']);
        
        // Create operator record
        $operatorId = $this->operatorDAO->create($userId, 'delegated', $serviceArea);
        
        return $this->getOperatorWithDetails($operatorId);
    }

    /**
     * Get operator with truck count
     */
    public function getOperatorWithDetails(int $operatorId): ?array
    {
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator) {
            return null;
        }
        
        $operator['truck_count'] = $this->operatorDAO->getTruckCount($operatorId);
        
        return $operator;
    }

    /**
     * Get operator by user ID with details
     */
    public function getOperatorByUserId(int $userId): ?array
    {
        $operator = $this->operatorDAO->findByUserId($userId);
        if (!$operator) {
            return null;
        }
        
        return $this->getOperatorWithDetails((int) $operator['id']);
    }

    /**
     * Switch operator mode (delegated/dispatcher)
     */
    public function setMode(int $operatorId, string $mode): array
    {
        $validModes = ['delegated', 'dispatcher'];
        if (!in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException('Invalid mode. Use "delegated" or "dispatcher"');
        }
        
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator) {
            throw new \RuntimeException('Operator not found');
        }
        
        $this->operatorDAO->updateMode($operatorId, $mode);
        
        return $this->getOperatorWithDetails($operatorId);
    }

    /**
     * Get all trucks for an operator
     */
    public function getTrucks(int $operatorId): array
    {
        $trucks = $this->truckDAO->findByOperatorId($operatorId);
        
        // Add queue info to each truck
        return array_map(function ($truck) {
            $truck['queue_length'] = $this->truckDAO->getQueueLength((int) $truck['id']);
            $truck['estimated_delay_minutes'] = $truck['queue_length'] * (int) $truck['avg_job_minutes'];
            return $truck;
        }, $trucks);
    }

    /**
     * Get jobs dashboard for operator
     */
    public function getJobs(int $operatorId): array
    {
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator) {
            throw new \RuntimeException('Operator not found');
        }
        
        return [
            'pending' => $this->jobDAO->findPendingByOperatorId($operatorId),
            'active' => $this->jobDAO->findActiveByOperatorId($operatorId),
            'mode' => $operator['mode']
        ];
    }

    /**
     * Update operator's service area
     */
    public function updateServiceArea(int $operatorId, string $serviceArea): array
    {
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator) {
            throw new \RuntimeException('Operator not found');
        }
        
        $this->operatorDAO->updateServiceArea($operatorId, $serviceArea);
        
        return $this->getOperatorWithDetails($operatorId);
    }
}
