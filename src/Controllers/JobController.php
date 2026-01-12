<?php

declare(strict_types=1);

namespace WaterTruck\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use WaterTruck\Services\JobService;
use WaterTruck\Services\TruckService;

class JobController
{
    public function __construct(
        private JobService $jobService,
        private TruckService $truckService
    ) {
    }

    /**
     * POST /api/jobs - Create a new job
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody() ?? [];
        
        // Validate required fields
        if (empty($data['location'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Location is required'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        
        if (empty($data['truck_ids']) || !is_array($data['truck_ids'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'At least one truck must be selected'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        
        try {
            $job = $this->jobService->createJob(
                (int) $user['id'],
                $data['location'],
                $data['truck_ids'],
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
                isset($data['lat']) ? (float) $data['lat'] : null,
                isset($data['lng']) ? (float) $data['lng'] : null
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $job
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
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
     * GET /api/jobs/{id} - Get job details
     */
    public function get(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        
        $job = $this->jobService->getJobWithDetails($jobId);
        
        if (!$job) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Job not found'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $job
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /api/jobs/{id}/accept - Accept a job
     */
    public function accept(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        
        // Get truck for current user
        $truck = $this->truckService->getTruckByUserId((int) $user['id']);
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'You must be a truck to accept jobs'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        try {
            $job = $this->jobService->acceptJob($jobId, (int) $truck['id']);
            
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

    /**
     * POST /api/jobs/{id}/reject - Reject a job request
     */
    public function reject(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        
        // Get truck for current user
        $truck = $this->truckService->getTruckByUserId((int) $user['id']);
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'You must be a truck to reject jobs'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        try {
            $job = $this->jobService->rejectJob($jobId, (int) $truck['id']);
            
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

    /**
     * POST /api/jobs/{id}/status - Update job status
     */
    public function updateStatus(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        $data = $request->getParsedBody() ?? [];
        
        if (empty($data['status'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Status is required'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
        
        // Get truck for current user
        $truck = $this->truckService->getTruckByUserId((int) $user['id']);
        if (!$truck) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'You must be a truck to update job status'
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(403);
        }
        
        try {
            $job = $this->jobService->updateStatus($jobId, $data['status'], (int) $truck['id']);
            
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
                ->withStatus(403);
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
     * POST /api/jobs/{id}/cancel - Cancel job (customer only, before en_route)
     */
    public function cancel(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $jobId = (int) $route->getArgument('id');
        
        try {
            $job = $this->jobService->cancelByCustomer($jobId, (int) $user['id']);
            
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
                ->withStatus(403);
        }
    }
}
