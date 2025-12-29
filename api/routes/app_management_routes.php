<?php
// api/routes/app_management_routes.php

use Workz\Platform\Controllers\AppManagementController;
use Workz\Platform\Controllers\AppsController;
use Workz\Platform\Middleware\AuthMiddleware;

return function ($router) {
    // ==================== BUILD STATUS ====================
    // Agora delega para AppManagementController::getBuildStatus(), que por sua vez
    // usa BuildPipeline::getBuildStatus() para agregar status/artefatos da fila.
    $router->add('GET', '/api/apps/(\\d+)/build-status/?', [AppManagementController::class, 'getBuildStatus'], [AuthMiddleware::class, 'handle']);
    // Endpoint para o Build Worker enviar atualizações de status (permanece em AppsController)
    $router->add('POST', '/api/apps/(\\d+)/build-status/?', [AppsController::class, 'updateBuildStatus']);
    // Fallback route para compatibilidade com /apps/build-status/{id}
    $router->add('GET', '/api/apps/build-status/(\\d+)/?', [AppManagementController::class, 'getBuildStatus'], [AuthMiddleware::class, 'handle']);

    // ==================== REBUILD/BUILD (compat routes) ====================
    $router->add('POST', '/api/apps/(\\d+)/rebuild/?', [AppManagementController::class, 'triggerBuild'], [AuthMiddleware::class, 'handle']);
    // Fallback patterns with reversed param position (legacy)
    $router->add('POST', '/api/apps/rebuild/(\\d+)/?', [AppManagementController::class, 'triggerBuild'], [AuthMiddleware::class, 'handle']);

    // ==================== APP MANAGEMENT APIs (AppManagementController) ====================
    $router->add('POST', '/api/apps/create', [AppManagementController::class, 'createApp'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/storage', [AppManagementController::class, 'getStorageInfo'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\\d+)/storage/migrate', [AppManagementController::class, 'migrateStorage'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/code', [AppManagementController::class, 'getCode'], [AuthMiddleware::class, 'handle']);
    $router->add('PUT', '/api/apps/(\\d+)/code', [AppManagementController::class, 'updateCode'], [AuthMiddleware::class, 'handle']);
    // Endpoint principal de build que enfileira jobs na build_queue (BuildPipeline::triggerBuild)
    $router->add('POST', '/api/apps/(\\d+)/build', [AppManagementController::class, 'triggerBuild'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\\d+)/build/webhook', [AppManagementController::class, 'buildWebhook']); // Webhook pode não ter auth
    $router->add('GET', '/api/apps/(\\d+)/artifacts', [AppManagementController::class, 'getArtifacts'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/artifacts/([a-z]+)', [AppManagementController::class, 'downloadArtifact'], [AuthMiddleware::class, 'handle']);

    // Delete app (DB + filesystem)
    $router->add('DELETE', '/api/apps/(\\d+)', [AppManagementController::class, 'deleteApp'], [AuthMiddleware::class, 'handle']);
    // Fallback POST route for environments that can't send DELETE
    $router->add('POST', '/api/apps/(\\d+)/delete', [AppManagementController::class, 'deleteApp'], [AuthMiddleware::class, 'handle']);

    // ==================== CURADORIA DE APPS (AppManagementController) ====================
    // GET /api/apps/reviews?status=pending - Busca apps pendentes de revisão
    $router->add('GET', '/api/apps/reviews', [AppManagementController::class, 'getReviews'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/reviews/(\\d+)/approve', [AppManagementController::class, 'approveReview'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/reviews/(\\d+)/reject', [AppManagementController::class, 'rejectReview'], [AuthMiddleware::class, 'handle']);
};
