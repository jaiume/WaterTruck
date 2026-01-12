<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

// DAOs
use WaterTruck\DAO\UserDAO;
use WaterTruck\DAO\OperatorDAO;
use WaterTruck\DAO\TruckDAO;
use WaterTruck\DAO\JobDAO;
use WaterTruck\DAO\JobRequestDAO;
use WaterTruck\DAO\InviteDAO;

// Services
use WaterTruck\Services\ConfigService;
use WaterTruck\Services\IdentityService;
use WaterTruck\Services\TruckService;
use WaterTruck\Services\JobService;
use WaterTruck\Services\OperatorService;
use WaterTruck\Services\InviteService;
use WaterTruck\Services\UtilityService;

// Controllers
use WaterTruck\Controllers\IdentityController;
use WaterTruck\Controllers\TruckController;
use WaterTruck\Controllers\JobController;
use WaterTruck\Controllers\OperatorController;
use WaterTruck\Controllers\InviteController;

// Middleware
use WaterTruck\Middleware\IdentityMiddleware;

return function (ContainerBuilder $containerBuilder) {
    
    $containerBuilder->addDefinitions([
        
        // PDO Database Connection
        PDO::class => function (ContainerInterface $c) {
            return UtilityService::getDbConnection();
        },
        
        // DAOs
        UserDAO::class => function (ContainerInterface $c) {
            return new UserDAO($c->get(PDO::class));
        },
        
        OperatorDAO::class => function (ContainerInterface $c) {
            return new OperatorDAO($c->get(PDO::class));
        },
        
        TruckDAO::class => function (ContainerInterface $c) {
            return new TruckDAO($c->get(PDO::class));
        },
        
        JobDAO::class => function (ContainerInterface $c) {
            return new JobDAO($c->get(PDO::class));
        },
        
        JobRequestDAO::class => function (ContainerInterface $c) {
            return new JobRequestDAO($c->get(PDO::class));
        },
        
        InviteDAO::class => function (ContainerInterface $c) {
            return new InviteDAO($c->get(PDO::class));
        },
        
        // Services
        IdentityService::class => function (ContainerInterface $c) {
            return new IdentityService(
                $c->get(UserDAO::class),
                $c->get(TruckDAO::class),
                $c->get(OperatorDAO::class)
            );
        },
        
        TruckService::class => function (ContainerInterface $c) {
            return new TruckService(
                $c->get(TruckDAO::class),
                $c->get(UserDAO::class),
                $c->get(JobDAO::class),
                $c->get(JobRequestDAO::class)
            );
        },
        
        JobService::class => function (ContainerInterface $c) {
            return new JobService(
                $c->get(JobDAO::class),
                $c->get(JobRequestDAO::class),
                $c->get(TruckDAO::class),
                $c->get(OperatorDAO::class),
                $c->get(PDO::class)
            );
        },
        
        OperatorService::class => function (ContainerInterface $c) {
            return new OperatorService(
                $c->get(OperatorDAO::class),
                $c->get(UserDAO::class),
                $c->get(TruckDAO::class),
                $c->get(JobDAO::class)
            );
        },
        
        InviteService::class => function (ContainerInterface $c) {
            return new InviteService(
                $c->get(InviteDAO::class),
                $c->get(OperatorDAO::class),
                $c->get(TruckDAO::class),
                $c->get(UserDAO::class)
            );
        },
        
        UtilityService::class => function (ContainerInterface $c) {
            return new UtilityService();
        },
        
        // Controllers
        IdentityController::class => function (ContainerInterface $c) {
            return new IdentityController(
                $c->get(IdentityService::class)
            );
        },
        
        TruckController::class => function (ContainerInterface $c) {
            return new TruckController(
                $c->get(TruckService::class)
            );
        },
        
        JobController::class => function (ContainerInterface $c) {
            return new JobController(
                $c->get(JobService::class),
                $c->get(TruckService::class)
            );
        },
        
        OperatorController::class => function (ContainerInterface $c) {
            return new OperatorController(
                $c->get(OperatorService::class),
                $c->get(JobService::class)
            );
        },
        
        InviteController::class => function (ContainerInterface $c) {
            return new InviteController(
                $c->get(InviteService::class),
                $c->get(OperatorService::class)
            );
        },
        
        // Middleware
        IdentityMiddleware::class => function (ContainerInterface $c) {
            return new IdentityMiddleware(
                $c->get(IdentityService::class)
            );
        },
    ]);
};
