<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use WaterTruck\Services\TruckService;
use WaterTruck\Services\NotificationService;

class TruckController
{
    public function __construct(
        private TruckService $truckService,
        private NotificationService $notificationService
    ) {
    }

    /**
     * GET /api/trucks/available - List available trucks
     */
    public function available(Request $request, Response $response): Response
    {
        // Get optional customer coordinates from query params
        $queryParams = $request->getQueryParams();
        $lat = isset($queryParams['lat']) ? (float) $queryParams['lat'] : null;
        $lng = isset($queryParams['lng']) ? (float) $queryParams['lng'] : null;
        
        // Only pass coordinates if both are provided
        if ($lat !== null && $lng !== null) {
            $trucks = $this->truckService->getAvailable($lat, $lng);
        } else {
            $trucks = $this->truckService->getAvailable();
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $trucks
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/trucks - Create/setup truck profile
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        try {
            // Create truck for current user
            $truck = $this->truckService->createTruck((int) $user['id']);
            
            // If additional data provided, update the truck
            if (!empty($data)) {
                $truck = $this->truckService->updateTruck((int) $truck['id'], $data);
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $truck
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    /**
     * GET /api/trucks/{id} - Get truck details
     */
    public function get(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $truckId = (int) $route->getArgument('id');
        
        $truck = $this->truckService->getTruckWithQueue($truckId);
        
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Truck not found'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $truck
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * PUT /api/trucks/{id} - Update truck details
     */
    public function update(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $truckId = (int) $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];
        
        // Verify ownership
        $truck = $this->truckService->getTruckWithQueue($truckId);
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Truck not found'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        if ((int) $truck['user_id'] !== (int) $user['id']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Not authorized to update this truck'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        try {
            $updated = $this->truckService->updateTruck($truckId, $data);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $updated
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    /**
     * GET /api/trucks/{id}/jobs - Get jobs for a truck
     */
    public function getJobs(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $truckId = (int) $route->getArgument('id');
        
        // Verify ownership
        $truck = $this->truckService->getTruckWithQueue($truckId);
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Truck not found'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        if ((int) $truck['user_id'] !== (int) $user['id']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Not authorized'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        // Update last seen timestamp (this is the truck's "heartbeat")
        $this->truckService->updateLastSeen($truckId);
        
        $jobs = $this->truckService->getTruckJobs($truckId);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $jobs
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/trucks/{id}/location - Update truck's current location
     */
    public function updateLocation(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $truckId = (int) $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];
        
        // Verify ownership
        $truck = $this->truckService->getTruckWithQueue($truckId);
        if (!$truck || (int) $truck['user_id'] !== (int) $user['id']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Not authorized'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        if (!isset($data['lat']) || !isset($data['lng'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'lat and lng are required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $this->truckService->updateTruckLocation($truckId, (float) $data['lat'], (float) $data['lng']);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Location updated'
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/trucks/{id}/subscribe - Subscribe truck to push notifications
     * Note: Uses the truck's user_id for the unified push subscription system
     */
    public function subscribe(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $truckId = (int) $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];
        
        // Verify ownership
        $truck = $this->truckService->getTruckWithQueue($truckId);
        if (!$truck || (int) $truck['user_id'] !== (int) $user['id']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Not authorized'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }
        
        // Validate subscription data
        if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid subscription data'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Save subscription using the truck's user_id (unified push system)
        $success = $this->notificationService->saveSubscription((int) $truck['user_id'], $data);
        
        if ($success) {
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Subscription saved'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Failed to save subscription'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
