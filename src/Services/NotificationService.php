<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use WaterTruck\DAO\PushSubscriptionDAO;
use WaterTruck\DAO\NotificationQueueDAO;
use WaterTruck\DAO\TruckDAO;

class NotificationService
{
    private PushSubscriptionDAO $pushSubscriptionDAO;
    private NotificationQueueDAO $notificationQueueDAO;
    private TruckDAO $truckDAO;
    private ?WebPush $webPush = null;

    public function __construct(
        PushSubscriptionDAO $pushSubscriptionDAO,
        NotificationQueueDAO $notificationQueueDAO,
        TruckDAO $truckDAO
    ) {
        $this->pushSubscriptionDAO = $pushSubscriptionDAO;
        $this->notificationQueueDAO = $notificationQueueDAO;
        $this->truckDAO = $truckDAO;
    }

    /**
     * Initialize WebPush with VAPID credentials
     */
    private function getWebPush(): WebPush
    {
        if ($this->webPush === null) {
            $auth = [
                'VAPID' => [
                    'subject' => ConfigService::get('notifications.vapid_subject', 'mailto:admin@example.com'),
                    'publicKey' => ConfigService::get('notifications.vapid_public_key', ''),
                    'privateKey' => ConfigService::get('notifications.vapid_private_key', ''),
                ],
            ];
            
            $this->webPush = new WebPush($auth);
        }
        
        return $this->webPush;
    }

    /**
     * Save a push subscription for a truck
     */
    public function saveSubscription(int $truckId, array $subscription): bool
    {
        if (empty($subscription['endpoint']) || 
            empty($subscription['keys']['p256dh']) || 
            empty($subscription['keys']['auth'])) {
            return false;
        }
        
        return $this->pushSubscriptionDAO->save(
            $truckId,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );
    }

    /**
     * Queue a notification for offline trucks near a customer
     * Called when a customer visits the customer page with GPS
     */
    public function queueNotificationForNearbyTrucks(?float $customerLat, ?float $customerLng): void
    {
        // Check if notifications are enabled
        if (!ConfigService::get('notifications.enabled', false)) {
            return;
        }
        
        // Get offline trucks (trucks that are not currently active or haven't been seen recently)
        $offlineTrucks = $this->truckDAO->getOfflineTrucksWithLocation();
        
        if (empty($offlineTrucks)) {
            return;
        }
        
        $maxDistance = (float) ConfigService::get('truck.max_distance_km', 50);
        $throttleMinutes = (int) ConfigService::get('notifications.throttle_minutes', 15);
        
        foreach ($offlineTrucks as $truck) {
            // If customer has GPS, filter by distance
            if ($customerLat !== null && $customerLng !== null) {
                // If truck has location, check distance
                if (!empty($truck['current_lat']) && !empty($truck['current_lng'])) {
                    $distance = $this->calculateDistance(
                        (float) $truck['current_lat'],
                        (float) $truck['current_lng'],
                        $customerLat,
                        $customerLng
                    );
                    
                    // Skip if truck is too far
                    if ($distance > $maxDistance) {
                        continue;
                    }
                }
            } else {
                // No customer GPS - skip notification (only notify nearby trucks)
                continue;
            }
            
            // Check if truck has a push subscription
            $subscription = $this->pushSubscriptionDAO->getByTruckId((int) $truck['id']);
            if (!$subscription) {
                continue;
            }
            
            // Increment customer count in queue
            $this->notificationQueueDAO->incrementCustomerCount((int) $truck['id']);
        }
        
        // Process pending notifications (respecting throttle)
        $this->processPendingNotifications();
    }

    /**
     * Process pending notifications - send to trucks that haven't been notified within throttle period
     */
    public function processPendingNotifications(): void
    {
        $throttleMinutes = (int) ConfigService::get('notifications.throttle_minutes', 15);
        $trucksToNotify = $this->notificationQueueDAO->getTrucksNeedingNotification($throttleMinutes);
        
        if (empty($trucksToNotify)) {
            return;
        }
        
        $webPush = $this->getWebPush();
        
        foreach ($trucksToNotify as $queueEntry) {
            $customerCount = (int) $queueEntry['customer_count'];
            
            // Skip if no customers have been counted
            if ($customerCount === 0) {
                continue;
            }
            
            // Create notification payload
            $title = 'Customers Looking for Water!';
            $body = $customerCount === 1 
                ? '1 customer is looking for water in your area'
                : "{$customerCount} customers are looking for water in your area";
            
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/images/868Water_logo.png',
                'badge' => '/images/868Water_logo.png',
                'data' => [
                    'url' => '/truck',
                    'customer_count' => $customerCount,
                ],
            ]);
            
            // Create subscription object
            $subscription = Subscription::create([
                'endpoint' => $queueEntry['endpoint'],
                'keys' => [
                    'p256dh' => $queueEntry['p256dh'],
                    'auth' => $queueEntry['auth'],
                ],
            ]);
            
            // Queue the notification
            $webPush->queueNotification($subscription, $payload);
            
            // Mark as notified
            $this->notificationQueueDAO->markNotified((int) $queueEntry['truck_id']);
        }
        
        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            // Log failed notifications (optional)
            if (!$report->isSuccess()) {
                error_log("Push notification failed: " . $report->getReason());
                
                // If subscription is expired, remove it
                if ($report->isSubscriptionExpired()) {
                    $endpoint = $report->getRequest()->getUri()->__toString();
                    // Could add logic here to clean up expired subscriptions
                }
            }
        }
    }

    /**
     * Send an immediate notification to a specific truck
     */
    public function sendImmediateNotification(int $truckId, string $title, string $body, array $data = []): bool
    {
        $subscription = $this->pushSubscriptionDAO->getByTruckId($truckId);
        if (!$subscription) {
            return false;
        }
        
        $webPush = $this->getWebPush();
        
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/images/868Water_logo.png',
            'badge' => '/images/868Water_logo.png',
            'data' => array_merge(['url' => '/truck'], $data),
        ]);
        
        $sub = Subscription::create([
            'endpoint' => $subscription['endpoint'],
            'keys' => [
                'p256dh' => $subscription['p256dh'],
                'auth' => $subscription['auth'],
            ],
        ]);
        
        $report = $webPush->sendOneNotification($sub, $payload);
        
        return $report->isSuccess();
    }

    /**
     * Calculate distance between two points using Haversine formula
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
}
