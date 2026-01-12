<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use WaterTruck\Middleware\IdentityMiddleware;
use WaterTruck\Controllers\IdentityController;
use WaterTruck\Controllers\TruckController;
use WaterTruck\Controllers\JobController;
use WaterTruck\Controllers\OperatorController;
use WaterTruck\Controllers\InviteController;

return function (App $app) {
    
    // Public config endpoint (no auth required)
    $app->get('/api/config', function (Request $request, Response $response) {
        $config = [
            'app_name' => \WaterTruck\Services\ConfigService::get('app.name', 'Water Truck'),
            'url' => \WaterTruck\Services\ConfigService::get('app.url', ''),
            'logo' => \WaterTruck\Services\ConfigService::get('app.logo', ''),
            'country_code' => \WaterTruck\Services\ConfigService::get('locale.country_code', '+1'),
            'country_name' => \WaterTruck\Services\ConfigService::get('locale.country_name', ''),
            'phone_digits' => \WaterTruck\Services\ConfigService::get('locale.phone_digits', 10),
            'default_avg_job_minutes' => \WaterTruck\Services\ConfigService::get('truck.default_avg_job_minutes', 30),
            'offline_timeout_minutes' => \WaterTruck\Services\ConfigService::get('truck.offline_timeout_minutes', 30),
            'location_update_interval_seconds' => \WaterTruck\Services\ConfigService::get('truck.location_update_interval_seconds', 60),
            'max_distance_km' => \WaterTruck\Services\ConfigService::get('truck.max_distance_km', 50),
            'vapid_public_key' => \WaterTruck\Services\ConfigService::get('notifications.vapid_public_key', ''),
            'notifications_enabled' => \WaterTruck\Services\ConfigService::get('notifications.enabled', false),
            // SEO fields
            'seo_description' => \WaterTruck\Services\ConfigService::get('seo.description', ''),
            'seo_keywords' => \WaterTruck\Services\ConfigService::get('seo.keywords', ''),
            'seo_truck_description' => \WaterTruck\Services\ConfigService::get('seo.truck_description', ''),
            'seo_truck_keywords' => \WaterTruck\Services\ConfigService::get('seo.truck_keywords', ''),
        ];
        $response->getBody()->write(json_encode(['success' => true, 'data' => $config]));
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // All API routes require identity middleware
    $app->group('/api', function (RouteCollectorProxy $group) {
        
        // Identity endpoints
        $group->get('/me', [IdentityController::class, 'me']);
        $group->post('/me', [IdentityController::class, 'update']);
        
        // Public truck endpoints
        $group->get('/trucks/available', [TruckController::class, 'available']);
        
        // Truck management endpoints
        $group->post('/trucks', [TruckController::class, 'create']);
        $group->get('/trucks/{id}', [TruckController::class, 'get']);
        $group->put('/trucks/{id}', [TruckController::class, 'update']);
        $group->get('/trucks/{id}/jobs', [TruckController::class, 'getJobs']);
        $group->post('/trucks/{id}/location', [TruckController::class, 'updateLocation']);
        $group->post('/trucks/{id}/subscribe', [TruckController::class, 'subscribe']);
        
        // Notification endpoint - notify offline trucks when customer visits
        $group->post('/notify-trucks', function (Request $request, Response $response) use ($group) {
            $data = $request->getParsedBody() ?? [];
            $lat = isset($data['lat']) ? (float) $data['lat'] : null;
            $lng = isset($data['lng']) ? (float) $data['lng'] : null;
            
            // Get NotificationService from container
            $container = $group->getContainer();
            $notificationService = $container->get(\WaterTruck\Services\NotificationService::class);
            
            // Queue notifications for nearby offline trucks
            $notificationService->queueNotificationForNearbyTrucks($lat, $lng);
            
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        });
        
        // Job endpoints
        $group->post('/jobs', [JobController::class, 'create']);
        $group->get('/jobs/{id}', [JobController::class, 'get']);
        $group->post('/jobs/{id}/accept', [JobController::class, 'accept']);
        $group->post('/jobs/{id}/reject', [JobController::class, 'reject']);
        $group->post('/jobs/{id}/status', [JobController::class, 'updateStatus']);
        $group->post('/jobs/{id}/cancel', [JobController::class, 'cancel']);
        
        // Operator endpoints
        $group->post('/operator', [OperatorController::class, 'create']);
        $group->get('/operator', [OperatorController::class, 'get']);
        $group->post('/operator/mode', [OperatorController::class, 'setMode']);
        $group->get('/operator/trucks', [OperatorController::class, 'getTrucks']);
        $group->get('/operator/jobs', [OperatorController::class, 'getJobs']);
        $group->post('/operator/jobs/{id}/assign', [OperatorController::class, 'assignJob']);
        
        // Invite endpoints
        $group->post('/invites', [InviteController::class, 'create']);
        $group->get('/invites/{token}', [InviteController::class, 'get']);
        $group->post('/invites/{token}/redeem', [InviteController::class, 'redeem']);
        
    })->add(IdentityMiddleware::class);
    
    // Handle CORS preflight
    $app->options('/{routes:.+}', function (Request $request, Response $response) {
        return $response;
    });
};
