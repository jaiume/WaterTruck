<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use WaterTruck\DAO\JobDAO;
use WaterTruck\DAO\JobRequestDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\OperatorDAO;
use PDO;

class JobService
{
    public function __construct(
        private JobDAO $jobDAO,
        private JobRequestDAO $jobRequestDAO,
        private TruckDAO $truckDAO,
        private OperatorDAO $operatorDAO,
        private PDO $pdo,
        private ?NotificationService $notificationService = null
    ) {
    }

    /**
     * Create a new job with requests to multiple trucks
     */
    public function createJob(
        int $customerUserId,
        string $location,
        array $truckIds,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?float $lat = null,
        ?float $lng = null
    ): array {
        if (empty($truckIds)) {
            throw new \InvalidArgumentException('At least one truck must be selected');
        }
        
        // Validate all trucks exist and are active
        foreach ($truckIds as $truckId) {
            $truck = $this->truckDAO->findById((int) $truckId);
            if (!$truck) {
                throw new \InvalidArgumentException("Truck {$truckId} not found");
            }
            if (!$truck['is_active']) {
                throw new \InvalidArgumentException("Truck {$truckId} is not available");
            }
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Create the job
            $jobId = $this->jobDAO->create($customerUserId, $location, $customerName, $customerPhone, $lat, $lng);
            
            // Create requests for each truck
            foreach ($truckIds as $truckId) {
                $this->jobRequestDAO->create($jobId, (int) $truckId);
            }
            
            $this->pdo->commit();
            
            return $this->getJobWithDetails($jobId);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get job with all details
     */
    public function getJobWithDetails(int $jobId): ?array
    {
        $job = $this->jobDAO->findByIdWithDetails($jobId);
        if (!$job) {
            return null;
        }
        
        $job['requests'] = $this->jobRequestDAO->findByJobId($jobId);
        
        // If job is en_route and has a truck, include truck location for ETA
        if ($job['status'] === 'en_route' && $job['truck_id']) {
            $truckLocation = $this->truckDAO->getLocation((int) $job['truck_id']);
            if ($truckLocation && $truckLocation['current_lat'] && $truckLocation['current_lng']) {
                $job['truck_location'] = [
                    'lat' => (float) $truckLocation['current_lat'],
                    'lng' => (float) $truckLocation['current_lng'],
                    'updated_at' => $truckLocation['location_updated_at'],
                ];
                
                // Calculate ETA if customer location is available
                if ($job['lat'] && $job['lng']) {
                    $distance = $this->calculateDistance(
                        (float) $truckLocation['current_lat'],
                        (float) $truckLocation['current_lng'],
                        (float) $job['lat'],
                        (float) $job['lng']
                    );
                    $job['truck_location']['distance_km'] = round($distance, 2);
                    // Estimate 30 km/h average speed in urban areas
                    $job['truck_location']['eta_minutes'] = max(1, round(($distance / 30) * 60));
                }
                // If no customer GPS, truck_location still has lat/lng for map tracking
            } else {
                $job['truck_location'] = null;
            }
        }
        
        return $job;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Accept a job request (first acceptance wins)
     */
    public function acceptJob(int $jobId, int $truckId): array
    {
        $job = $this->jobDAO->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }
        
        if ($job['status'] !== 'pending') {
            throw new \RuntimeException('Job is no longer available');
        }
        
        // Find the request for this truck
        $request = $this->jobRequestDAO->findByJobAndTruck($jobId, $truckId);
        if (!$request) {
            throw new \RuntimeException('No request found for this truck');
        }
        
        if ($request['status'] !== 'pending') {
            throw new \RuntimeException('Request is no longer pending');
        }
        
        // Get truck to lock in price
        $truck = $this->truckDAO->findById($truckId);
        if (!$truck || !$truck['price_fixed']) {
            throw new \RuntimeException('Truck price not set');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Accept this request
            $this->jobRequestDAO->accept((int) $request['id']);
            
            // Accept the job with locked price
            $this->jobDAO->accept($jobId, $truckId, (float) $truck['price_fixed']);
            
            // Expire all other pending requests
            $this->jobRequestDAO->expireOthersForJob($jobId, (int) $request['id']);
            
            $this->pdo->commit();
            
            return $this->getJobWithDetails($jobId);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a job request
     */
    public function rejectJob(int $jobId, int $truckId): array
    {
        $job = $this->jobDAO->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }
        
        $request = $this->jobRequestDAO->findByJobAndTruck($jobId, $truckId);
        if (!$request) {
            throw new \RuntimeException('No request found for this truck');
        }
        
        if ($request['status'] !== 'pending') {
            throw new \RuntimeException('Request is no longer pending');
        }
        
        $this->jobRequestDAO->reject((int) $request['id']);
        
        // Check if all requests have been rejected
        if ($this->jobRequestDAO->allRejected($jobId)) {
            $this->jobDAO->updateStatus($jobId, 'expired');
        }
        
        return $this->getJobWithDetails($jobId);
    }

    /**
     * Update job status (en_route, delivered)
     */
    public function updateStatus(int $jobId, string $status, int $truckId): array
    {
        $job = $this->jobDAO->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }
        
        // Verify truck owns this job
        if ((int) $job['truck_id'] !== $truckId) {
            throw new \RuntimeException('Not authorized to update this job');
        }
        
        $validTransitions = [
            'accepted' => ['en_route', 'cancelled'],
            'en_route' => ['delivered', 'cancelled'],
        ];
        
        $currentStatus = $job['status'];
        if (!isset($validTransitions[$currentStatus]) || 
            !in_array($status, $validTransitions[$currentStatus], true)) {
            throw new \InvalidArgumentException(
                "Cannot transition from {$currentStatus} to {$status}"
            );
        }
        
        $this->jobDAO->updateStatus($jobId, $status);
        
        // Notify customer when water is collected (status changes to en_route)
        if ($status === 'en_route' && $this->notificationService !== null) {
            $this->notificationService->notifyCustomerWaterCollected($jobId);
        }
        
        return $this->getJobWithDetails($jobId);
    }

    /**
     * Cancel job by customer (only allowed before truck is en_route)
     */
    public function cancelByCustomer(int $jobId, int $customerUserId): array
    {
        $job = $this->jobDAO->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }
        
        // Verify customer owns this job
        if ((int) $job['customer_user_id'] !== $customerUserId) {
            throw new \RuntimeException('Not authorized to cancel this job');
        }
        
        // Can only cancel if not yet en_route
        $cancellableStatuses = ['pending', 'accepted'];
        if (!in_array($job['status'], $cancellableStatuses, true)) {
            throw new \RuntimeException('Cannot cancel job once delivery has started');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Cancel the job
            $this->jobDAO->updateStatus($jobId, 'cancelled');
            
            // Expire all pending requests
            $this->jobRequestDAO->expireAllForJob($jobId);
            
            $this->pdo->commit();
            
            return $this->getJobWithDetails($jobId);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Assign job to a specific truck (dispatcher mode)
     */
    public function assignJob(int $jobId, int $truckId, int $operatorId): array
    {
        $job = $this->jobDAO->findById($jobId);
        if (!$job) {
            throw new \RuntimeException('Job not found');
        }
        
        if ($job['status'] !== 'pending') {
            throw new \RuntimeException('Job is no longer pending');
        }
        
        // Verify truck belongs to operator
        $truck = $this->truckDAO->findById($truckId);
        if (!$truck || (int) $truck['operator_id'] !== $operatorId) {
            throw new \RuntimeException('Truck does not belong to this operator');
        }
        
        // Verify operator is in dispatcher mode
        $operator = $this->operatorDAO->findById($operatorId);
        if (!$operator || $operator['mode'] !== 'dispatcher') {
            throw new \RuntimeException('Operator must be in dispatcher mode');
        }
        
        // Check if there's a request for this truck
        $request = $this->jobRequestDAO->findByJobAndTruck($jobId, $truckId);
        if (!$request) {
            // Create a request if one doesn't exist
            $this->jobRequestDAO->create($jobId, $truckId);
            $request = $this->jobRequestDAO->findByJobAndTruck($jobId, $truckId);
        }
        
        // Accept the job
        return $this->acceptJob($jobId, $truckId);
    }

    /**
     * Get jobs for a customer
     */
    public function getCustomerJobs(int $customerUserId): array
    {
        return $this->jobDAO->findByCustomerId($customerUserId);
    }
}
