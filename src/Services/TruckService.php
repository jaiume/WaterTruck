<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\UserDAO;
use WaterTruck\DAO\JobDAO;
use WaterTruck\DAO\JobRequestDAO;

class TruckService
{
    public function __construct(
        private TruckDAO $truckDAO,
        private UserDAO $userDAO,
        private JobDAO $jobDAO,
        private JobRequestDAO $jobRequestDAO
    ) {
    }

    /**
     * Get all available trucks with queue and ETA info
     */
    public function getAvailable(?float $customerLat = null, ?float $customerLng = null): array
    {
        // First, deactivate any stale trucks
        $timeoutMinutes = (int) ConfigService::get('truck.offline_timeout_minutes', 30);
        $this->truckDAO->deactivateStaleTrucks($timeoutMinutes);
        
        $trucks = $this->truckDAO->getAvailable($customerLat, $customerLng);
        
        return array_map(function ($truck) {
            $queueLength = (int) $truck['queue_length'];
            $avgMinutes = (int) $truck['avg_job_minutes'];
            
            $truck['estimated_delay_minutes'] = $queueLength * $avgMinutes;
            $truck['eta_text'] = $this->formatEtaText($queueLength, $avgMinutes);
            
            return $truck;
        }, $trucks);
    }

    /**
     * Update last seen timestamp for a truck (call this when truck is active)
     */
    public function updateLastSeen(int $truckId): void
    {
        $this->truckDAO->updateLastSeen($truckId);
    }

    /**
     * Update truck's current GPS location
     */
    public function updateTruckLocation(int $truckId, float $lat, float $lng): void
    {
        $this->truckDAO->updateLocation($truckId, $lat, $lng);
    }

    /**
     * Get truck's current location
     */
    public function getTruckLocation(int $truckId): ?array
    {
        return $this->truckDAO->getLocation($truckId);
    }

    /**
     * Format ETA as human-readable text
     */
    private function formatEtaText(int $queueLength, int $avgMinutes): string
    {
        if ($queueLength === 0) {
            return 'Available now';
        }
        
        $minMinutes = $queueLength * $avgMinutes;
        $maxMinutes = ($queueLength + 1) * $avgMinutes;
        
        if ($minMinutes < 60) {
            return sprintf('%d-%d minutes', $minMinutes, $maxMinutes);
        }
        
        $minHours = floor($minMinutes / 60);
        $maxHours = ceil($maxMinutes / 60);
        
        if ($minHours === $maxHours) {
            return sprintf('~%d hour%s', $minHours, $minHours > 1 ? 's' : '');
        }
        
        return sprintf('%d-%d hours', $minHours, $maxHours);
    }

    /**
     * Create a new truck for a user
     */
    public function createTruck(int $userId, ?int $operatorId = null): array
    {
        // Check if user already has a truck
        $existing = $this->truckDAO->findByUserId($userId);
        if ($existing) {
            throw new \RuntimeException('User already has a truck');
        }
        
        // Update user role to truck
        $this->userDAO->update($userId, ['role' => 'truck']);
        
        // Create the truck
        $truckId = $this->truckDAO->create($userId, $operatorId);
        
        return $this->truckDAO->findById($truckId);
    }

    /**
     * Update truck details
     */
    public function updateTruck(int $truckId, array $data): array
    {
        $truck = $this->truckDAO->findById($truckId);
        if (!$truck) {
            throw new \RuntimeException('Truck not found');
        }
        
        $updateData = [];
        
        // Build merged state (existing + new) for validation
        $mergedName = $truck['name'];
        $mergedPhone = $truck['phone'];
        $mergedCapacity = $truck['capacity_gallons'];
        
        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
            $mergedName = $updateData['name'];
        }
        
        if (isset($data['phone'])) {
            $updateData['phone'] = trim($data['phone']);
            $mergedPhone = $updateData['phone'];
        }
        
        if (isset($data['capacity_gallons'])) {
            $capacity = (int) $data['capacity_gallons'];
            if ($capacity <= 0) {
                throw new \InvalidArgumentException('Capacity must be positive');
            }
            $updateData['capacity_gallons'] = $capacity;
            $mergedCapacity = $capacity;
        }
        
        if (isset($data['price_fixed']) || isset($data['price'])) {
            $price = (float) ($data['price_fixed'] ?? $data['price']);
            if ($price < 0) {
                throw new \InvalidArgumentException('Price cannot be negative');
            }
            $updateData['price_fixed'] = $price;
        }
        
        if (isset($data['avg_job_minutes'])) {
            $minutes = (int) $data['avg_job_minutes'];
            if ($minutes <= 0) {
                throw new \InvalidArgumentException('Average job time must be positive');
            }
            $updateData['avg_job_minutes'] = $minutes;
        }
        
        if (isset($data['is_active'])) {
            // Can only activate if minimum requirements are met (check merged data)
            if ($data['is_active']) {
                $meetsRequirements = !empty($mergedName) && !empty($mergedPhone) && !empty($mergedCapacity);
                if (!$meetsRequirements) {
                    throw new \InvalidArgumentException(
                        'Truck must have name, phone, and capacity before activating'
                    );
                }
            }
            $updateData['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        if (!empty($updateData)) {
            $this->truckDAO->update($truckId, $updateData);
        }
        
        return $this->getTruckWithQueue($truckId);
    }

    /**
     * Get truck with queue information
     */
    public function getTruckWithQueue(int $truckId): ?array
    {
        $truck = $this->truckDAO->findById($truckId);
        if (!$truck) {
            return null;
        }
        
        $truck['queue_length'] = $this->truckDAO->getQueueLength($truckId);
        $truck['estimated_delay_minutes'] = $truck['queue_length'] * (int) $truck['avg_job_minutes'];
        
        return $truck;
    }

    /**
     * Get truck by user ID
     */
    public function getTruckByUserId(int $userId): ?array
    {
        $truck = $this->truckDAO->findByUserId($userId);
        if (!$truck) {
            return null;
        }
        
        $truck['queue_length'] = $this->truckDAO->getQueueLength((int) $truck['id']);
        return $truck;
    }

    /**
     * Get pending job requests for a truck
     */
    public function getPendingRequests(int $truckId): array
    {
        return $this->jobRequestDAO->findPendingByTruckId($truckId);
    }

    /**
     * Get active jobs for a truck
     */
    public function getActiveJobs(int $truckId): array
    {
        return $this->jobDAO->findByTruckId($truckId, 'accepted') 
             + $this->jobDAO->findByTruckId($truckId, 'en_route');
    }

    /**
     * Get all jobs for a truck (for dashboard)
     */
    public function getTruckJobs(int $truckId): array
    {
        $pending = $this->jobRequestDAO->findPendingByTruckId($truckId);
        $active = $this->jobDAO->findByTruckId($truckId);
        
        return [
            'pending_requests' => $pending,
            'jobs' => $active
        ];
    }
}
