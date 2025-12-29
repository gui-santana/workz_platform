<?php
// api/routes/billing_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\BillingController;
use Workz\Platform\Middleware\AuthMiddleware;

return function (Router $router) {
    // Payment methods (Stripe only)
    $router->add('GET', '/api/billing/payment-methods', [BillingController::class, 'listStripePaymentMethods'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/billing/payment-methods', [BillingController::class, 'createStripePaymentMethod'], [AuthMiddleware::class, 'handle']);
    $router->add('PUT', '/api/billing/payment-methods/(\\d+)', [BillingController::class, 'updatePaymentMethod'], [AuthMiddleware::class, 'handle']);
    $router->add('DELETE', '/api/billing/payment-methods/(\\d+)', [BillingController::class, 'deleteStripePaymentMethod'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/billing/stripe/setup-intent', [BillingController::class, 'createStripeSetupIntent'], [AuthMiddleware::class, 'handle']);

    // Bank accounts (business only)
    $router->add('GET', '/api/billing/bank-accounts', [BillingController::class, 'listBankAccounts'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/billing/bank-accounts', [BillingController::class, 'createBankAccount'], [AuthMiddleware::class, 'handle']);
    $router->add('PUT', '/api/billing/bank-accounts/(\\d+)', [BillingController::class, 'updateBankAccount'], [AuthMiddleware::class, 'handle']);
    $router->add('DELETE', '/api/billing/bank-accounts/(\\d+)', [BillingController::class, 'deleteBankAccount'], [AuthMiddleware::class, 'handle']);

    // Legacy MP endpoints removidos
};
