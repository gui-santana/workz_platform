<?php
// api/routes/app_management_routes.php

use Workz\Platform\Controllers\AppManagementController;
use Workz\Platform\Controllers\AppsController;
// Removed UniversalAppController from here as it's not needed for build status anymore
use Workz\Platform\Middleware\AuthMiddleware;

return function ($router) {
    // ==================== BUILD STATUS (Prioridade para AppsController) ====================
    // Esta rota agora aponta para AppsController para obter o status detalhado do build
    $router->add('GET', '/api/apps/(\\d+)/build-status/?', [AppsController::class, 'getBuildStatus'], [AuthMiddleware::class, 'handle']);
    // Endpoint para o Build Worker enviar atualizações de status
    $router->add('POST', '/api/apps/(\\d+)/build-status/?', [AppsController::class, 'updateBuildStatus']);
    // Fallback route for build status (if frontend uses /apps/build-status/{id})
    $router->add('GET', '/api/apps/build-status/(\\d+)/?', [AppsController::class, 'getBuildStatus'], [AuthMiddleware::class, 'handle']);

    // ==================== REBUILD/BUILD (compat routes) ====================
    $router->add('POST', '/api/apps/(\\d+)/rebuild/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\\d+)/build/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);
    // Fallback patterns with reversed param position (legacy)
    $router->add('POST', '/api/apps/rebuild/(\\d+)/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/build/(\\d+)/?', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']);

    // ==================== APP MANAGEMENT APIs (AppManagementController) ====================
    $router->add('POST', '/api/apps/create', [AppManagementController::class, 'createApp'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/storage', [AppManagementController::class, 'getStorageInfo'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\\d+)/storage/migrate', [AppManagementController::class, 'migrateStorage'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/code', [AppManagementController::class, 'getCode'], [AuthMiddleware::class, 'handle']);
    $router->add('PUT', '/api/apps/(\\d+)/code', [AppManagementController::class, 'updateCode'], [AuthMiddleware::class, 'handle']);
    $router->add('POST', '/api/apps/(\\d+)/build', [AppsController::class, 'rebuild'], [AuthMiddleware::class, 'handle']); // Reutiliza a lógica de rebuild
    $router->add('POST', '/api/apps/(\\d+)/build/webhook', [AppManagementController::class, 'buildWebhook']); // Webhook pode não ter auth
    $router->add('GET', '/api/apps/(\\d+)/artifacts', [AppManagementController::class, 'getArtifacts'], [AuthMiddleware::class, 'handle']);
    $router->add('GET', '/api/apps/(\\d+)/artifacts/([a-z]+)', [AppManagementController::class, 'downloadArtifact'], [AuthMiddleware::class, 'handle']);

    // Delete app (DB + filesystem)
    $router->add('DELETE', '/api/apps/(\\d+)', [AppManagementController::class, 'deleteApp'], [AuthMiddleware::class, 'handle']);
    // Fallback POST route for environments that can't send DELETE
    $router->add('POST', '/api/apps/(\\d+)/delete', [AppManagementController::class, 'deleteApp'], [AuthMiddleware::class, 'handle']);
};

