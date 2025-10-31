<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Core\PerformanceOptimizer;
use Workz\Platform\Core\RuntimeEngine;

/**
 * Controller para gerenciar rotas de performance
 */
class PerformanceController
{
    private \Workz\Platform\Models\General $generalModel;

    public function __construct()
    {
        $this->generalModel = new \Workz\Platform\Models\General();
    }

    /**
     * Serve cached static assets
     */
    public function serveCachedAsset($payload, $assetPath)
    {
        try {
            // A classe PerformanceOptimizer não foi fornecida, então instanciá-la diretamente pode causar erro.
            $performanceOptimizer = new PerformanceOptimizer();
            
            $cacheDir = '/var/cache/workz/static';
            $fullPath = $cacheDir . '/' . $assetPath;
            
            if (!file_exists($fullPath) || !is_file($fullPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Asset not found']);
                return;
            }
            
            // Load metadata if available
            $metadataPath = $fullPath . '.meta';
            $metadata = [];
            if (file_exists($metadataPath)) {
                $metadata = json_decode(file_get_contents($metadataPath), true) ?? [];
            }
            
            // Set cache headers
            $ttl = 3600; // 1 hour
            header('Cache-Control: public, max-age=' . $ttl);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
            header('Vary: Accept-Encoding');
            
            // Set ETag if available
            if (isset($metadata['etag'])) {
                header('ETag: ' . $metadata['etag']);
                
                // Check if client has cached version
                $clientETag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
                if ($clientETag === $metadata['etag']) {
                    http_response_code(304);
                    return;
                }
            }
            
            // Set content type
            $mimeType = mime_content_type($fullPath);
            header('Content-Type: ' . $mimeType);
            
            // Set content length
            header('Content-Length: ' . filesize($fullPath));
            
            // Enable compression for text-based assets
            if (strpos($mimeType, 'text/') === 0 || 
                strpos($mimeType, 'application/javascript') === 0 ||
                strpos($mimeType, 'application/json') === 0) {
                
                $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
                if (strpos($acceptEncoding, 'gzip') !== false) {
                    header('Content-Encoding: gzip');
                    echo gzencode(file_get_contents($fullPath));
                    return;
                }
            }
            
            // Serve the file
            readfile($fullPath);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to serve asset: ' . $e->getMessage()]);
        }
    }

    /**
     * Get performance metrics
     */
    public function getMetrics($payload)
    {
        try {
            $runtimeEngine = new RuntimeEngine();
            $metrics = $runtimeEngine->getPerformanceMetrics();
            
            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'timestamp' => date('c')
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get metrics: ' . $e->getMessage()]);
        }
    }

    /**
     * Clear performance caches
     */
    public function clearCache($payload)
    {
        try {
            $performanceOptimizer = new PerformanceOptimizer();
            $runtimeEngine = new RuntimeEngine();
            
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $appId = $input['app_id'] ?? null;
            
            if ($appId) {
                // Clear cache for specific app
                $runtimeEngine->clearCache($appId);
                $success = true;
                $message = "Cache cleared for app {$appId}";
            } else {
                // Clear all caches
                $success = $performanceOptimizer->clearAllCaches();
                $runtimeEngine->clearCache();
                $message = $success ? 'All caches cleared successfully' : 'Failed to clear some caches';
            }
            
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to clear cache: ' . $e->getMessage()]);
        }
    }

    /**
     * Preload app assets
     */
    public function preloadApp($payload)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $appId = $input['app_id'] ?? null;
            $platform = $input['platform'] ?? 'web';
            
            if (!$appId) {
                http_response_code(400);
                echo json_encode(['error' => 'app_id is required']);
                return;
            }
            
            $runtimeEngine = new RuntimeEngine();
            $success = $runtimeEngine->preloadApp($appId, $platform);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'App preloaded successfully' : 'Failed to preload app'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to preload app: ' . $e->getMessage()]);
        }
    }

    /**
     * Optimize memory usage
     */
    public function optimizeMemory($payload)
    {
        try {
            $runtimeEngine = new RuntimeEngine();
            $metrics = $runtimeEngine->getPerformanceMetrics();
            
            // Get current running apps
            $runningApps = $metrics['running_apps']['apps'] ?? [];
            
            $performanceOptimizer = new PerformanceOptimizer();
            $optimization = $performanceOptimizer->optimizeMemoryUsage($runningApps);
            
            echo json_encode([
                'success' => true,
                'optimization' => $optimization,
                'message' => 'Memory optimization completed'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to optimize memory: ' . $e->getMessage()]);
        }
    }

    /**
     * Get app performance status
     */
    public function getAppStatus($payload, $appId)
    {
        try {
            $appId = (int) $appId;
            $runtimeEngine = new RuntimeEngine();
            $metrics = $runtimeEngine->getPerformanceMetrics();
            
            // Find app in running apps
            $appStatus = null;
            foreach ($metrics['running_apps']['apps'] as $app) {
                if ($app['id'] === $appId) {
                    $appStatus = $app;
                    break;
                }
            }
            
            if (!$appStatus) {
                $appStatus = [
                    'id' => $appId,
                    'status' => 'not_running',
                    'message' => 'App is not currently loaded'
                ];
            } else {
                $appStatus['status'] = 'running';
            }
            
            echo json_encode([
                'success' => true,
                'app_status' => $appStatus
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get app status: ' . $e->getMessage()]);
        }
    }

    /**
     * Update app access time
     */
    public function updateAppAccess($payload, $appId)
    {
        try {
            $appId = (int) $appId;
            $runtimeEngine = new RuntimeEngine();
            $runtimeEngine->updateAppAccess($appId);
            
            echo json_encode([
                'success' => true,
                'message' => 'App access time updated'
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update app access: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/performance/apps/{id}/access
     * Registra um acesso ao aplicativo para fins de métricas.
     * Protegido por AuthMiddleware.
     */
    public function trackAppAccess(object $auth, int $appId): void
    {
        header("Content-Type: application/json");
        $userId = (int)($auth->sub ?? 0);

        if ($userId <= 0 || $appId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
            return;
        }

        // Lógica para salvar no banco de dados (ex: tabela app_access_logs)
        // Por enquanto, apenas registramos no log do servidor.
        error_log("App access tracked: User {$userId} accessed App {$appId}");

        echo json_encode(['success' => true, 'message' => 'Acesso registrado.']);
    }
}