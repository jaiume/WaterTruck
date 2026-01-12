<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use WaterTruck\Services\IdentityService;
use WaterTruck\Services\NotificationService;

class IdentityController
{
    public function __construct(
        private IdentityService $identityService,
        private NotificationService $notificationService
    ) {
    }

    /**
     * GET /api/me - Get current user
     */
    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $user
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/me - Update current user profile
     */
    public function update(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        try {
            $updated = $this->identityService->updateProfile((int) $user['id'], $data);
            
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
     * POST /api/push/subscribe - Subscribe current user to push notifications
     */
    public function subscribe(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        // Validate subscription data
        if (empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invalid subscription data'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $success = $this->notificationService->saveSubscription((int) $user['id'], $data);
        
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
