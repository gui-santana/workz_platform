<?php
// api/routes/subscriptions_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\SubscriptionsController;
use Workz\Platform\Middleware\AuthMiddleware;

return function (Router $router) {
    $router->add('POST', '/api/subscriptions/plans', [SubscriptionsController::class, 'createPlan'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/subscriptions/subscribe', [SubscriptionsController::class, 'subscribe'], [AuthMiddleware::class, 'handle']);
    // Optional list
    $router->add('GET', '/api/subscriptions', [SubscriptionsController::class, 'list'], [AuthMiddleware::class, 'handle']);
    // Cancel subscription
    $router->add('POST', '/api/subscriptions/cancel', [SubscriptionsController::class, 'cancel'], [AuthMiddleware::class, 'handle']);
};
