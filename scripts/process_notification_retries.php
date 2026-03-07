#!/usr/bin/env php
<?php

declare(strict_types=1);

use DI\Bridge\Slim\Bridge as SlimBridge;
use DI\ContainerBuilder;
use WaterTruck\Services\NotificationService;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
$containerDefinitions = require __DIR__ . '/../config/container.php';
$containerDefinitions($containerBuilder);
$container = $containerBuilder->build();

// Boot slim app to ensure config/services are initialized the same way as web requests.
SlimBridge::create($container);

/** @var NotificationService $notificationService */
$notificationService = $container->get(NotificationService::class);
$result = $notificationService->processDeliveryRetries(100);

echo json_encode([
    'success' => true,
    'data' => $result,
], JSON_PRETTY_PRINT) . PHP_EOL;
