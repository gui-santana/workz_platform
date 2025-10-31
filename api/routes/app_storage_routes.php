<?php
// api/routes/app_storage_routes.php

use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\AppStorageController;
use Workz\Platform\Middleware\AuthMiddleware;

return function(Router $router) {
    // ==================== KV STORAGE ====================
    
    // GET /api/appdata/kv?scopeType=user&scopeId=123&key=prefs.theme
    $router->add('GET', '/api/appdata/kv', [AppStorageController::class, 'kvGet'], [AuthMiddleware::class, 'handle']);
    
    // POST /api/appdata/kv
    $router->add('POST', '/api/appdata/kv', [AppStorageController::class, 'kvSet'], [AuthMiddleware::class, 'handle']);
    
    // DELETE /api/appdata/kv?scopeType=user&scopeId=123&key=prefs.theme
    $router->add('DELETE', '/api/appdata/kv', [AppStorageController::class, 'kvDelete'], [AuthMiddleware::class, 'handle']);

    // ==================== DOCS STORAGE ====================
    
    // POST /api/appdata/docs/query
    $router->add('POST', '/api/appdata/docs/query', [AppStorageController::class, 'docsQuery'], [AuthMiddleware::class, 'handle']);
    
    // POST /api/appdata/docs/upsert
    $router->add('POST', '/api/appdata/docs/upsert', [AppStorageController::class, 'docsUpsert'], [AuthMiddleware::class, 'handle']);
    
    // DELETE /api/appdata/docs/{docType}/{docId}?scopeType=user&scopeId=123
    $router->add('DELETE', '/api/appdata/docs/([^/]+)/([^/]+)', [AppStorageController::class, 'docsDelete'], [AuthMiddleware::class, 'handle']);

    // ==================== BLOBS STORAGE ====================
    
    // POST /api/appdata/blobs/upload
    $router->add('POST', '/api/appdata/blobs/upload', [AppStorageController::class, 'blobsUpload'], [AuthMiddleware::class, 'handle']);
    
    // GET /api/appdata/blobs/list
    $router->add('GET', '/api/appdata/blobs/list', [AppStorageController::class, 'blobsList'], [AuthMiddleware::class, 'handle']);
    
    // GET /api/appdata/blobs/get/{blobId}
    $router->add('GET', '/api/appdata/blobs/get/([^/]+)', [AppStorageController::class, 'blobsGet'], [AuthMiddleware::class, 'handle']);
    
    // DELETE /api/appdata/blobs/delete/{blobId}
    $router->add('DELETE', '/api/appdata/blobs/delete/([^/]+)', [AppStorageController::class, 'blobsDelete'], [AuthMiddleware::class, 'handle']);

    // ==================== STORAGE STATS ====================
    
    // GET /api/apps/storage/stats - Storage statistics for monitoring
    $router->add('GET', '/api/apps/storage/stats', [AppStorageController::class, 'getStorageStats'], [AuthMiddleware::class, 'handle']);
};