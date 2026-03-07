<?php

declare(strict_types=1);

namespace WaterTruck\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use WaterTruck\DAO\PushSubscriptionDAO;
use WaterTruck\DAO\NotificationQueueDAO;
use WaterTruck\DAO\NotificationDeliveryQueueDAO;
use WaterTruck\DAO\NearbyNotifySignalDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\JobDAO;

class NotificationService
{
    private PushSubscriptionDAO $pushSubscriptionDAO;
    private NotificationQueueDAO $notificationQueueDAO;
    private NotificationDeliveryQueueDAO $notificationDeliveryQueueDAO;
    private NearbyNotifySignalDAO $nearbyNotifySignalDAO;
    private TruckDAO $truckDAO;
    private JobDAO $jobDAO;
    private ?WebPush $webPush = null;

    public function __construct(
        PushSubscriptionDAO $pushSubscriptionDAO,
        NotificationQueueDAO $notificationQueueDAO,
        TruckDAO $truckDAO,
        JobDAO $jobDAO,
        NotificationDeliveryQueueDAO $notificationDeliveryQueueDAO,
        NearbyNotifySignalDAO $nearbyNotifySignalDAO
    ) {
        $this->pushSubscriptionDAO = $pushSubscriptionDAO;
        $this->notificationQueueDAO = $notificationQueueDAO;
        $this->truckDAO = $truckDAO;
        $this->jobDAO = $jobDAO;
        $this->notificationDeliveryQueueDAO = $notificationDeliveryQueueDAO;
        $this->nearbyNotifySignalDAO = $nearbyNotifySignalDAO;
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
     * Save a push subscription for a user (unified - works for any user type)
     */
    public function saveSubscription(int $userId, array $subscription): bool
    {
        if (empty($subscription['endpoint']) || 
            empty($subscription['keys']['p256dh']) || 
            empty($subscription['keys']['auth'])) {
            return false;
        }
        
        return $this->pushSubscriptionDAO->save(
            $userId,
            $subscription['endpoint'],
            $subscription['keys']['p256dh'],
            $subscription['keys']['auth']
        );
    }

    /**
     * Send a notification to a specific user
     */
    public function sendNotification(int $userId, string $title, string $body, array $data = []): bool
    {
        $result = $this->deliverToUser($userId, $title, $body, $data);
        return $result['status'] === 'sent';
    }

    /**
     * Notify selected trucks after job creation and return advisory delivery outcomes.
     *
     * @param array<int, array<string, mixed>> $trucks
     * @return array<int, array<string, mixed>>
     */
    public function notifySelectedTrucksForJob(array $trucks, int $jobId, string $location): array
    {
        if (!ConfigService::get('notifications.enabled', false)) {
            return array_map(
                fn (array $truck): array => [
                    'truck_id' => (int) $truck['id'],
                    'status' => 'send_failed',
                ],
                $trucks
            );
        }

        $outcomes = [];
        $maxAttempts = 3;
        $firstRetryDelaySeconds = 2;

        foreach ($trucks as $truck) {
            $truckId = (int) $truck['id'];
            $userId = (int) $truck['user_id'];
            $truckName = (string) ($truck['name'] ?? 'A truck');

            $title = 'New Water Request Nearby';
            $body = sprintf('%s has a new request near %s', $truckName, $location);
            $payload = [
                'type' => 'new_job_request',
                'job_id' => $jobId,
                'truck_id' => $truckId,
                'url' => '/truck',
            ];

            $result = $this->deliverToUser($userId, $title, $body, $payload);
            $status = $result['status'];

            if ($status === 'send_failed') {
                $this->notificationDeliveryQueueDAO->enqueueRetry(
                    $userId,
                    $truckId,
                    $jobId,
                    $title,
                    $body,
                    $payload,
                    1,
                    $maxAttempts,
                    $firstRetryDelaySeconds
                );
            }

            $outcomes[] = [
                'truck_id' => $truckId,
                'status' => $status,
            ];
        }

        return $outcomes;
    }

    /**
     * Process queued delivery retries in the background.
     *
     * @return array<string, int>
     */
    public function processDeliveryRetries(int $limit = 100): array
    {
        $safeLimit = max(1, min(100, $limit));
        $rows = $this->notificationDeliveryQueueDAO->fetchDue($safeLimit);
        $processed = 0;
        $sent = 0;
        $deadLettered = 0;
        $rescheduled = 0;

        foreach ($rows as $row) {
            $queueId = (int) $row['id'];
            if (!$this->notificationDeliveryQueueDAO->markProcessing($queueId)) {
                continue;
            }

            $processed++;
            $attemptCount = (int) $row['attempt_count'];
            $maxAttempts = (int) $row['max_attempts'];
            $nextAttemptCount = $attemptCount + 1;
            $userId = (int) $row['user_id'];
            $truckId = (int) $row['truck_id'];
            $payload = json_decode((string) $row['payload_json'], true) ?: [];

            $result = $this->deliverToUser(
                $userId,
                (string) $row['title'],
                (string) $row['body'],
                is_array($payload) ? $payload : []
            );

            if ($result['status'] === 'sent') {
                $this->notificationDeliveryQueueDAO->markSent($queueId);
                $sent++;
                continue;
            }

            $lastError = (string) ($result['reason'] ?? $result['status']);

            if ($result['status'] === 'expired_subscription' || $result['status'] === 'no_subscription' || $nextAttemptCount >= $maxAttempts) {
                $this->notificationDeliveryQueueDAO->markDeadLetter($queueId, $nextAttemptCount, $lastError);
                $deadLettered++;
                continue;
            }

            $delay = $this->retryDelayForCompletedAttempt($nextAttemptCount);
            $this->notificationDeliveryQueueDAO->markForRetry($queueId, $nextAttemptCount, $lastError, $delay);
            $rescheduled++;

            $this->logInfo('push_retry_rescheduled', [
                'queue_id' => $queueId,
                'truck_hash' => $this->hashIdentifier((string) $truckId),
                'next_attempt_count' => $nextAttemptCount,
                'delay_seconds' => $delay,
            ]);
        }

        return [
            'processed' => $processed,
            'sent' => $sent,
            'dead_lettered' => $deadLettered,
            'rescheduled' => $rescheduled,
        ];
    }

    /**
     * @return array{status: string, reason?: string}
     */
    private function deliverToUser(int $userId, string $title, string $body, array $data = []): array
    {
        $subscription = $this->pushSubscriptionDAO->getByUserId($userId);
        if (!$subscription) {
            return ['status' => 'no_subscription'];
        }

        try {
            $webPush = $this->getWebPush();
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'icon' => '/images/868Water_logo.png',
                'badge' => '/images/868Water_logo.png',
                'data' => $data,
            ]);

            $sub = Subscription::create([
                'endpoint' => $subscription['endpoint'],
                'keys' => [
                    'p256dh' => $subscription['p256dh'],
                    'auth' => $subscription['auth'],
                ],
            ]);

            $report = $webPush->sendOneNotification($sub, $payload);
            if ($report->isSuccess()) {
                return ['status' => 'sent'];
            }

            if ($report->isSubscriptionExpired()) {
                $this->pushSubscriptionDAO->delete($userId);
                $this->logInfo('push_subscription_expired', [
                    'user_hash' => $this->hashIdentifier((string) $userId),
                ]);
                return ['status' => 'expired_subscription'];
            }

            $reason = trim((string) $report->getReason());
            $this->logInfo('push_send_failed', [
                'user_hash' => $this->hashIdentifier((string) $userId),
                'reason' => $reason !== '' ? $reason : 'unknown',
            ]);

            return [
                'status' => 'send_failed',
                'reason' => $reason !== '' ? $reason : 'unknown',
            ];
        } catch (\Throwable $e) {
            // Malformed subscription payloads should never take down request flow.
            $this->pushSubscriptionDAO->delete($userId);
            $this->logInfo('push_subscription_invalid', [
                'user_hash' => $this->hashIdentifier((string) $userId),
                'reason' => substr($e->getMessage(), 0, 120),
            ]);

            return [
                'status' => 'send_failed',
                'reason' => 'invalid_subscription_data',
            ];
        }
    }

    private function retryDelayForCompletedAttempt(int $completedAttemptCount): int
    {
        return $completedAttemptCount >= 2 ? 8 : 2;
    }

    private function hashIdentifier(string $value): string
    {
        return substr(hash('sha256', $value), 0, 12);
    }

    private function logInfo(string $event, array $context = []): void
    {
        error_log('[notifications] ' . $event . ' ' . json_encode($context));
    }

    /**
     * Notify customer that a truck has accepted their job
     * Called when job status changes to 'accepted'
     */
    public function notifyCustomerJobAccepted(int $jobId): bool
    {
        if (!ConfigService::get('notifications.enabled', false)) {
            return false;
        }

        $job = $this->jobDAO->findByIdWithDetails($jobId);
        if (!$job) {
            return false;
        }

        $customerUserId = (int) $job['customer_user_id'];
        $truckName = $job['truck_name'] ?? 'A truck';

        return $this->sendNotification(
            $customerUserId,
            'Truck On Its Way!',
            "{$truckName} has accepted your request and will collect water soon",
            [
                'type' => 'water_collected',
                'url' => "/job/{$jobId}",
                'job_id' => $jobId,
            ]
        );
    }

    /**
     * Notify customer that their water has been collected and is on the way
     * Called when job status changes to 'en_route'
     */
    public function notifyCustomerWaterCollected(int $jobId): bool
    {
        // Check if notifications are enabled
        if (!ConfigService::get('notifications.enabled', false)) {
            return false;
        }
        
        // Get job details
        $job = $this->jobDAO->findByIdWithDetails($jobId);
        if (!$job) {
            return false;
        }
        
        $customerUserId = (int) $job['customer_user_id'];
        $truckName = $job['truck_name'] ?? 'Your truck';
        
        return $this->sendNotification(
            $customerUserId,
            'Water On The Way!',
            "{$truckName} has collected your water and is heading to you",
            [
                'type' => 'water_collected',
                'url' => "/job/{$jobId}",
                'job_id' => $jobId,
            ]
        );
    }

    /**
     * Queue a notification for offline trucks near a customer
     * Called when a customer visits the customer page with GPS
     */
    public function queueNotificationForNearbyTrucks(
        ?float $customerLat,
        ?float $customerLng,
        ?string $identityKey = null
    ): array
    {
        if (!ConfigService::get('notifications.enabled', false)) {
            return ['queued' => 0, 'skipped_reason' => 'disabled'];
        }

        if ($customerLat === null || $customerLng === null) {
            return ['queued' => 0, 'skipped_reason' => 'missing_coordinates'];
        }

        $identityHash = $this->hashIdentifier((string) ($identityKey ?? 'anonymous'));
        $rateLimitCount = (int) ConfigService::get('notifications.nearby_signal_rate_limit_count', 30);
        $rateLimitWindowSeconds = (int) ConfigService::get('notifications.nearby_signal_rate_limit_window_seconds', 60);

        if ($this->nearbyNotifySignalDAO->isRateLimited($identityHash, $rateLimitCount, $rateLimitWindowSeconds)) {
            $this->logInfo('nearby_signal_rate_limited', ['identity_hash' => $identityHash]);
            return ['queued' => 0, 'skipped_reason' => 'rate_limited'];
        }

        $latBucket = number_format(round($customerLat, 3), 3, '.', '');
        $lngBucket = number_format(round($customerLng, 3), 3, '.', '');
        $cooldownSeconds = (int) ConfigService::get('notifications.nearby_signal_cooldown_seconds', 60);
        $bucketStart = (int) (floor(time() / max(1, $cooldownSeconds)) * max(1, $cooldownSeconds));
        $dedupeKey = implode(':', [$identityHash, $latBucket, $lngBucket, (string) $bucketStart]);

        if (!$this->nearbyNotifySignalDAO->tryRegisterSignal($identityHash, $dedupeKey, $latBucket, $lngBucket)) {
            return ['queued' => 0, 'skipped_reason' => 'duplicate_signal'];
        }

        $retentionHours = (int) ConfigService::get('notifications.nearby_signal_retention_hours', 24);
        $this->nearbyNotifySignalDAO->cleanupOldSignals($retentionHours);

        $offlineTrucks = $this->truckDAO->getOfflineTrucksWithLocation();
        if (empty($offlineTrucks)) {
            return ['queued' => 0, 'skipped_reason' => 'no_inactive_trucks'];
        }

        $maxDistance = (float) ConfigService::get('truck.max_distance_km', 50);
        $queuedCount = 0;

        foreach ($offlineTrucks as $truck) {
            if (empty($truck['current_lat']) || empty($truck['current_lng'])) {
                continue;
            }

            $distance = $this->calculateDistance(
                (float) $truck['current_lat'],
                (float) $truck['current_lng'],
                $customerLat,
                $customerLng
            );
            if ($distance > $maxDistance) {
                continue;
            }

            $truckUserId = (int) $truck['user_id'];
            $subscription = $this->pushSubscriptionDAO->getByUserId($truckUserId);
            if (!$subscription) {
                continue;
            }

            $this->notificationQueueDAO->incrementCustomerCount($truckUserId);
            $queuedCount++;
        }

        if ($queuedCount > 0) {
            $this->processPendingNotifications();
        }

        return ['queued' => $queuedCount];
    }

    /**
     * Process pending notifications - send to users that haven't been notified within throttle period
     */
    public function processPendingNotifications(): void
    {
        $throttleMinutes = (int) ConfigService::get('notifications.throttle_minutes', 15);
        $usersToNotify = $this->notificationQueueDAO->getUsersNeedingNotification($throttleMinutes);
        
        if (empty($usersToNotify)) {
            return;
        }
        
        foreach ($usersToNotify as $queueEntry) {
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
            
            $result = $this->deliverToUser(
                (int) $queueEntry['user_id'],
                $title,
                $body,
                [
                    'type' => 'customers_nearby',
                    'url' => '/truck',
                    'customer_count' => $customerCount,
                ]
            );

            if ($result['status'] !== 'sent') {
                $this->logInfo('nearby_push_delivery_result', [
                    'user_hash' => $this->hashIdentifier((string) $queueEntry['user_id']),
                    'status' => $result['status'],
                    'reason' => $result['reason'] ?? null,
                ]);
            }

            // Always mark notified so we respect queue throttle semantics and avoid rapid repeats.
            $this->notificationQueueDAO->markNotified((int) $queueEntry['user_id']);
        }
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
