<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use WaterTruck\Services\InviteService;
use WaterTruck\Services\OperatorService;

class InviteController
{
    public function __construct(
        private InviteService $inviteService,
        private OperatorService $operatorService
    ) {
    }

    /**
     * POST /api/invites - Generate new invite link
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        
        $operator = $this->operatorService->getOperatorByUserId((int) $user['id']);
        if (!$operator) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Not an operator'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        try {
            $invite = $this->inviteService->createInvite((int) $operator['id']);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $invite
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
                ->withStatus(500);
        }
    }

    /**
     * GET /api/invites/{token} - Get invite details
     */
    public function get(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $token = $route->getArgument('token');
        
        $invite = $this->inviteService->getInviteByToken($token);
        
        if (!$invite) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invite not found'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        if ($invite['used']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Invite has already been used'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(410);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'token' => $invite['token'],
                'operator_name' => $invite['operator_name'] ?? null,
                'created_at' => $invite['created_at']
            ]
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/invites/{token}/redeem - Redeem invite, bind truck to operator
     */
    public function redeem(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $token = $route->getArgument('token');
        
        try {
            $result = $this->inviteService->redeemInvite($token, (int) $user['id']);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
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
