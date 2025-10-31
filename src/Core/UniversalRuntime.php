<?php
// src/Core/UniversalRuntime.php
// Runtime Universal - Executa QUALQUER tipo de app

namespace Workz\Platform\Core;

class UniversalRuntime
{
    /**
     * Compila e deploya qualquer app (genérico)
     * NÃO conhece tipos específicos - apenas processa código
     */
    public static function deployApp(int $appId, array $appData): array
    {
        try {
            $title = $appData['tt'] ?? "App $appId";
            $appType = self::detectAppType($appData);
            $sourceCode = self::extractSourceCode($appData, $appType);
            
            if (empty($sourceCode)) {
                throw new \Exception('Nenhum código fonte encontrado');
            }

            // 1. Compilar código (genérico)
            $compiledCode = UniversalCompiler::compile($sourceCode, $appType);
            
            // 2. Salvar código compilado
            $scriptPath = self::saveCompiledCode($appId, $compiledCode, $appType);
            
            // 3. Gerar HTML universal
            $htmlPath = UniversalHtmlGenerator::save($appId, $title, $appType);
            
            // 4. Gerar assets adicionais se necessário
            self::generateAdditionalAssets($appId, $appType);
            
            return [
                'success' => true,
                'app_id' => $appId,
                'app_type' => $appType,
                'title' => $title,
                'html_path' => $htmlPath,
                'script_path' => $scriptPath,
                'url' => self::getAppUrl($appId, $appType),
                'compiled_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Throwable $e) {
            error_log("Erro no deploy do app $appId: " . $e->getMessage());
            
            return [
                'success' => false,
                'app_id' => $appId,
                'error' => $e->getMessage(),
                'compiled_at' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Detecta o tipo do app baseado nos dados (genérico)
     */
    private static function detectAppType(array $appData): string
    {
        // Verificar se tem código Dart
        if (!empty($appData['dart_code'])) {
            return 'dart';
        }
        
        // Verificar se tem código JavaScript
        if (!empty($appData['js_code'])) {
            return 'javascript';
        }
        
        // Verificar campo app_type
        if (!empty($appData['app_type'])) {
            return strtolower($appData['app_type']);
        }
        
        // Default
        return 'javascript';
    }

    /**
     * Extrai o código fonte baseado no tipo (genérico)
     */
    private static function extractSourceCode(array $appData, string $appType): string
    {
        switch (strtolower($appType)) {
            case 'dart':
            case 'flutter':
                return $appData['dart_code'] ?? '';
            case 'javascript':
            case 'js':
            default:
                return $appData['js_code'] ?? '';
        }
    }

    /**
     * Salva o código compilado no local correto (genérico)
     */
    private static function saveCompiledCode(int $appId, string $compiledCode, string $appType): string
    {
        $filePath = self::getScriptFilePath($appId, $appType);
        
        // Criar diretório se não existir
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Salvar código compilado
        file_put_contents($filePath, $compiledCode);
        
        return $filePath;
    }

    /**
     * Determina o caminho do arquivo de script (genérico)
     */
    private static function getScriptFilePath(int $appId, string $appType): string
    {
        $baseDir = __DIR__ . '/../../public/apps';
        
        switch (strtolower($appType)) {
            case 'dart':
            case 'flutter':
                return "$baseDir/flutter/$appId/web/main.dart.js";
            case 'javascript':
            case 'js':
            default:
                return "$baseDir/javascript/$appId/app.js";
        }
    }

    /**
     * Gera assets adicionais se necessário (genérico)
     */
    private static function generateAdditionalAssets(int $appId, string $appType): void
    {
        if (strtolower($appType) === 'dart' || strtolower($appType) === 'flutter') {
            // Gerar service worker para Flutter
            self::generateFlutterServiceWorker($appId);
        }
    }

    /**
     * Gera service worker para apps Flutter (genérico)
     */
    private static function generateFlutterServiceWorker(int $appId): void
    {
        $serviceWorkerPath = __DIR__ . "/../../public/apps/flutter/$appId/web/flutter_service_worker.js";
        
        $serviceWorkerCode = <<<JS
// Flutter Service Worker (Universal)
// Gerado automaticamente pelo Runtime Universal

const CACHE_NAME = 'flutter-app-$appId-v1';
const RESOURCES = {
  '/': 'index.html',
  '/main.dart.js': 'main.dart.js',
  '/js/core/workz-sdk-v2.js': 'workz-sdk-v2.js'
};

self.addEventListener('install', (event) => {
  console.log('Service Worker instalado para app $appId');
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(Object.keys(RESOURCES));
    })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
JS;
        
        file_put_contents($serviceWorkerPath, $serviceWorkerCode);
    }

    /**
     * Gera URL do app (genérico)
     */
    private static function getAppUrl(int $appId, string $appType): string
    {
        switch (strtolower($appType)) {
            case 'dart':
            case 'flutter':
                return "/apps/flutter/$appId/web/";
            case 'javascript':
            case 'js':
            default:
                return "/apps/javascript/$appId/";
        }
    }

    /**
     * Verifica se um app está compilado (genérico)
     */
    public static function isAppCompiled(int $appId, string $appType = 'javascript'): bool
    {
        $scriptPath = self::getScriptFilePath($appId, $appType);
        $htmlPath = self::getHtmlFilePath($appId, $appType);
        
        return file_exists($scriptPath) && file_exists($htmlPath);
    }

    /**
     * Determina o caminho do arquivo HTML (genérico)
     */
    private static function getHtmlFilePath(int $appId, string $appType): string
    {
        $baseDir = __DIR__ . '/../../public/apps';
        
        switch (strtolower($appType)) {
            case 'dart':
            case 'flutter':
                return "$baseDir/flutter/$appId/web/index.html";
            case 'javascript':
            case 'js':
            default:
                return "$baseDir/javascript/$appId/index.html";
        }
    }

    /**
     * Remove arquivos compilados de um app (genérico)
     */
    public static function cleanApp(int $appId, string $appType = 'javascript'): bool
    {
        try {
            $scriptPath = self::getScriptFilePath($appId, $appType);
            $htmlPath = self::getHtmlFilePath($appId, $appType);
            
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
            
            if (file_exists($htmlPath)) {
                unlink($htmlPath);
            }
            
            return true;
            
        } catch (\Throwable $e) {
            error_log("Erro ao limpar app $appId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtém informações de build de um app (genérico)
     */
    public static function getBuildInfo(int $appId, string $appType = 'javascript'): array
    {
        $scriptPath = self::getScriptFilePath($appId, $appType);
        $htmlPath = self::getHtmlFilePath($appId, $appType);
        
        $info = [
            'app_id' => $appId,
            'app_type' => $appType,
            'is_compiled' => false,
            'script_exists' => false,
            'html_exists' => false,
            'script_size' => 0,
            'html_size' => 0,
            'last_modified' => null,
            'url' => self::getAppUrl($appId, $appType)
        ];
        
        if (file_exists($scriptPath)) {
            $info['script_exists'] = true;
            $info['script_size'] = filesize($scriptPath);
            $info['last_modified'] = date('Y-m-d H:i:s', filemtime($scriptPath));
        }
        
        if (file_exists($htmlPath)) {
            $info['html_exists'] = true;
            $info['html_size'] = filesize($htmlPath);
        }
        
        $info['is_compiled'] = $info['script_exists'] && $info['html_exists'];
        
        return $info;
    }
}