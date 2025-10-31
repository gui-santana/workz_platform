<?php
// api/routes/app_builder_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\UniversalAppController;
use Workz\Platform\Controllers\AppsController;
use Workz\Platform\Middleware\AuthMiddleware;

return function(Router $router) {
    // Rotas Universais do App Builder (Genéricas)
    $router->add('GET', '/api/apps/my-apps', [UniversalAppController::class, 'myApps'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\d+)/?', [UniversalAppController::class, 'getApp'], [AuthMiddleware::class, 'handle']);
    $router->add('PUT', '/api/apps/(\d+)/?', [UniversalAppController::class, 'updateApp'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/update/(\d+)/?', [UniversalAppController::class, 'updateApp'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\d+)/compile/?', [UniversalAppController::class, 'forceCompile'], [AuthMiddleware::class, 'handle']);
    // Para Flutter, direcionar rebuild para AppsController (Build Worker)
    $router->add('POST', '/api/apps/(\d+)/rebuild/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);
    // A rota /build também foca no fluxo do AppsController (mantida por compatibilidade)
    $router->add('POST', '/api/apps/(\d+)/build/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);
    
    // Storage management endpoints (Genéricos)
    $router->add('GET', '/api/apps/storage/stats', [UniversalAppController::class, 'getStorageStats'], [AuthMiddleware::class, 'handle']);
};
