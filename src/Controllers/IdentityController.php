<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use WaterTruck\Services\IdentityService;

class IdentityController
{
    public function __construct(private IdentityService $identityService)
    {
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
}
