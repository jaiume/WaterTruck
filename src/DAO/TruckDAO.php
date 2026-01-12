<?php

declare(strict_types=1);

namespace WaterTruck\DAO;

use PDO;

class TruckDAO
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trucks WHERE id = ?');
        $stmt->execute([$id]);
        $truck = $stmt->fetch();
        return $truck ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trucks WHERE user_id = ?');
        $stmt->execute([$userId]);
        $truck = $stmt->fetch();
        return $truck ?: null;
    }

    public function findByOperatorId(int $operatorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trucks WHERE operator_id = ? ORDER BY name');
        $stmt->execute([$operatorId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, ?int $operatorId = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO trucks (user_id, operator_id) VALUES (?, ?)'
        );
        $stmt->execute([$userId, $operatorId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['name', 'phone', 'capacity_gallons', 'price_fixed', 'avg_job_minutes', 'is_active', 'operator_id'];
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
        $sql = 'UPDATE trucks SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function setOperator(int $truckId, int $operatorId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE trucks SET operator_id = ? WHERE id = ?');
        return $stmt->execute([$operatorId, $truckId]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM trucks WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Get all active trucks with queue information
     * Only returns trucks that have been seen within offline_timeout_minutes
     */
    /**
     * Get all active trucks with queue information
     * Optionally filter by distance from customer location
     */
    public function getAvailable(?float $customerLat = null, ?float $customerLng = null): array
    {
        $offlineTimeout = (int) \WaterTruck\Services\ConfigService::get('truck.offline_timeout_minutes', 5);
        
        $sql = "
            SELECT 
                t.*,
                o.mode as operator_mode,
                (
                    SELECT COUNT(*) 
                    FROM jobs j 
                    WHERE j.truck_id = t.id 
                    AND j.status IN ('accepted', 'en_route')
                ) as queue_length
            FROM trucks t
            LEFT JOIN operators o ON t.operator_id = o.id
            WHERE t.is_active = 1
            AND t.name IS NOT NULL
            AND t.phone IS NOT NULL
            AND t.capacity_gallons IS NOT NULL
            AND t.last_seen_at IS NOT NULL
            AND t.last_seen_at >= NOW() - INTERVAL {$offlineTimeout} MINUTE
            ORDER BY queue_length ASC, t.name ASC
        ";
        
        $stmt = $this->pdo->query($sql);
        $trucks = $stmt->fetchAll();
        
        // If customer coordinates provided, filter by distance
        if ($customerLat !== null && $customerLng !== null) {
            $maxDistance = (float) \WaterTruck\Services\ConfigService::get('truck.max_distance_km', 50);
            
            $trucks = array_filter($trucks, function ($truck) use ($customerLat, $customerLng, $maxDistance) {
                // If truck has no location, include it (don't penalize trucks without GPS)
                if (empty($truck['current_lat']) || empty($truck['current_lng'])) {
                    return true;
                }
                
                $distance = $this->calculateDistance(
                    (float) $truck['current_lat'],
                    (float) $truck['current_lng'],
                    $customerLat,
                    $customerLng
                );
                
                return $distance <= $maxDistance;
            });
            
            // Re-index array after filtering
            $trucks = array_values($trucks);
        }
        
        return $trucks;
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in kilometers
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
     * Check if truck meets minimum requirements to be active
     */
    public function meetsMinimumRequirements(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM trucks 
             WHERE id = ? AND name IS NOT NULL AND phone IS NOT NULL AND capacity_gallons IS NOT NULL'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get queue length for a specific truck
     */
    public function getQueueLength(int $truckId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM jobs WHERE truck_id = ? AND status IN ('accepted', 'en_route')"
        );
        $stmt->execute([$truckId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Update last_seen_at timestamp for a truck
     */
    public function updateLastSeen(int $truckId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE trucks SET last_seen_at = NOW() WHERE id = ?');
        return $stmt->execute([$truckId]);
    }

    /**
     * Deactivate trucks that haven't been seen for the specified minutes
     */
    public function deactivateStaleTrucks(int $timeoutMinutes): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE trucks SET is_active = 0 
             WHERE is_active = 1 
             AND last_seen_at IS NOT NULL 
             AND last_seen_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $stmt->execute([$timeoutMinutes]);
        return $stmt->rowCount();
    }

    /**
     * Update truck's current location
     */
    public function updateLocation(int $truckId, float $lat, float $lng): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE trucks SET current_lat = ?, current_lng = ?, location_updated_at = NOW(), last_seen_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([$lat, $lng, $truckId]);
    }

    /**
     * Get truck location info
     */
    public function getLocation(int $truckId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT current_lat, current_lng, location_updated_at FROM trucks WHERE id = ?'
        );
        $stmt->execute([$truckId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get offline trucks (inactive or not seen recently) that have location data
     * Used for sending notifications to nearby offline trucks
     */
    public function getOfflineTrucksWithLocation(): array
    {
        $offlineTimeout = (int) \WaterTruck\Services\ConfigService::get('truck.offline_timeout_minutes', 5);
        
        $sql = "
            SELECT t.*
            FROM trucks t
            WHERE t.name IS NOT NULL
            AND t.phone IS NOT NULL
            AND t.capacity_gallons IS NOT NULL
            AND (
                t.is_active = 0
                OR t.last_seen_at IS NULL
                OR t.last_seen_at < NOW() - INTERVAL {$offlineTimeout} MINUTE
            )
        ";
        
        return $this->pdo->query($sql)->fetchAll();
    }
}
