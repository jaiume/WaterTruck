<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use WaterTruck\Services\OperatorService;
use WaterTruck\Services\JobService;

class OperatorController
{
    public function __construct(
        private OperatorService $operatorService,
        private JobService $jobService
    ) {
    }

    /**
     * POST /api/operator - Create operator profile
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        try {
            $operator = $this->operatorService->createOperator(
                (int) $user['id'],
                $data['service_area'] ?? null
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $operator
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
        }
    }

    /**
     * GET /api/operator - Get current user's operator profile
     */
    public function get(Request $request, Response $response): Response
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
                ->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $operator
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/operator/mode - Switch operator mode
     */
    public function setMode(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        if (empty($data['mode'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Mode is required (delegated or dispatcher)'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        
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
            $updated = $this->operatorService->setMode((int) $operator['id'], $data['mode']);
            
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
     * GET /api/operator/trucks - Get operator's trucks
     */
    public function getTrucks(Request $request, Response $response): Response
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
        
        $trucks = $this->operatorService->getTrucks((int) $operator['id']);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $trucks
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/operator/jobs - Get operator's job dashboard
     */
    public function getJobs(Request $request, Response $response): Response
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
        
        $jobs = $this->operatorService->getJobs((int) $operator['id']);
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $jobs
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/operator/jobs/{id}/assign - Assign job to truck (dispatcher mode)
     */
    public function assignJob(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];
        
        if (empty($data['truck_id'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'truck_id is required'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        
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
            $job = $this->jobService->assignJob(
                $jobId,
                (int) $data['truck_id'],
                (int) $operator['id']
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $job
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(409);
        }
    }
}
