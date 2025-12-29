<?php
// api/routes/payments_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\PaymentsController;
use Workz\Platform\Middleware\AuthMiddleware;

return function (Router $router) {
    // Webhook endpoint (public). Use signature verification
    $router->add('POST', '/api/payments/webhook', [PaymentsController::class, 'stripeWebhook']);
    $router->add('GET', '/api/payments/stripe/public-key', [PaymentsController::class, 'getStripePublicKey']);

    // Query endpoints (protected)
    $router->add('GET', '/api/payments/transactions', [PaymentsController::class, 'listTransactions'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/payments/transactions/(\\d+)', [PaymentsController::class, 'getTransaction'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/payments/status/(\\d+)', [PaymentsController::class, 'getStatus'], [AuthMiddleware::class, 'handle']);

    // Direct charge using Stripe (protected)
    $router->add('POST', '/api/payments/charge', [PaymentsController::class, 'charge'], [AuthMiddleware::class, 'handle']);
};
