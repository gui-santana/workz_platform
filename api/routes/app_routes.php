<?php
// api/routes/app_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\AppsController;
use Workz\Platform\Middleware\AuthMiddleware;

return function(Router $router) {
    // Rotas do App Runner e Catálogo
    $router->add('GET', '/api/app/run/([a-z0-9-]+)', [AppsController::class, 'run'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/catalog', [AppsController::class, 'catalog']);
    $router->add('POST', '/api/apps/sso', [AppsController::class, 'sso'], [AuthMiddleware::class, 'handle']);    
};