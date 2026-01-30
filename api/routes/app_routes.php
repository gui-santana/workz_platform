<?php
// api/routes/app_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\AppsController;
use Workz\Platform\Controllers\PublicController;
use Workz\Platform\Middleware\AuthMiddleware;

return function(Router $router) {
    // Rotas do App Runner e Catálogo
    $router->add('GET', '/api/app/run/([a-z0-9-]+)', [AppsController::class, 'run'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/app/run/([a-z0-9-]+)', [AppsController::class, 'run'], [AuthMiddleware::class, 'handle']);
    // Acesso público/standalone via slug (sem middleware): usado por apps.localhost
    $router->add('GET', '/api/app/public/([a-z0-9-]+)', [AppsController::class, 'publicRun']);
    $router->add('GET', '/app/public/([a-z0-9-]+)', [AppsController::class, 'publicRun']);
    $router->add('GET', '/api/app/shell/([a-z0-9-]+)', [AppsController::class, 'shell']);
    $router->add('GET', '/app/shell/([a-z0-9-]+)', [AppsController::class, 'shell']);
    $router->add('GET', '/api/apps/catalog', [AppsController::class, 'catalog']);
    $router->add('GET', '/api/apps/manifest/([a-z0-9-]+)', [AppsController::class, 'manifest']);
    $router->add('POST', '/api/apps/sso', [AppsController::class, 'sso'], [AuthMiddleware::class, 'handle']);    
    $router->add('POST', '/api/apps/proxy', [AppsController::class, 'proxy'], [AuthMiddleware::class, 'handle']);

    // Rotas públicas de entidades (sem middleware)
    $router->add('GET', '/api/public/profile/(\d+)', [PublicController::class, 'profile']);
    $router->add('GET', '/api/public/business/(\d+)', [PublicController::class, 'business']);
};
