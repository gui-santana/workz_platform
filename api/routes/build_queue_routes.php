<?php
// api/routes/build_queue_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\BuildQueueController;

return function(Router $router) {
    // Endpoints internos para o Build Worker (autenticados via X-Worker-Secret)
    $router->add('POST', '/api/build-queue/claim', [BuildQueueController::class, 'claim']);
    $router->add('POST', '/api/build-queue/update/(\d+)', [BuildQueueController::class, 'updateJob']);
};

