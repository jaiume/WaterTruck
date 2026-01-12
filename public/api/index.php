<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use DI\Bridge\Slim\Bridge as SlimBridge;
use WaterTruck\Services\ConfigService;

// Load autoloader
require __DIR__ . '/../../vendor/autoload.php';

// Build Container
$containerBuilder = new ContainerBuilder();

// Load container definitions
$containerDefinitions = require __DIR__ . '/../../config/container.php';
$containerDefinitions($containerBuilder);

// Build the container
$container = $containerBuilder->build();

// Create App with PHP-DI
$app = SlimBridge::create($container);

// Add error middleware
$app->addErrorMiddleware(
    ConfigService::get('app.debug', false),
    true,
    true
);

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Load routes
$routes = require __DIR__ . '/../../config/routes.php';
$routes($app);

// Run application
$app->run();
