<?php
// src/Controllers/AppsController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Core\UniversalRuntime; // Para obter informações de build de apps JS
use Workz\Platform\Models\General; 
use Workz\Platform\Core\StorageManager;
use Firebase\JWT\JWT;

class AppsController
{
    private General $generalModel;

    // ... (construtor e outros métodos existentes) ...
    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * GET /api/apps/catalog
     * Lista o catálogo básico de apps ativos.
     * Aberto (sem middleware) por enquanto.
     * Updated to include storage type information for backward compatibility.
     */
    public function catalog(): void
    {
        header("Content-Type: application/json");
        try {
            $res = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'slug', 'tt', 'im', 'vl', 'st', 'src', 'embed_url', 'color', 'ds', 'app_type', 'storage_type', 'version'],
                ['st' => 1],
                true,
                200,
                0,
                ['by' => 'tt', 'dir' => 'ASC']
            );
            
            // Add backward compatibility fields and storage information
            if (is_array($res)) {
                foreach ($res as &$app) {
                    // Ensure backward compatibility
                    $app['storage_type'] = $app['storage_type'] ?? 'database';
                    $app['app_type'] = $app['app_type'] ?? 'javascript';
                    $app['version'] = $app['version'] ?? '1.0.0';
                }
            }
            
            echo json_encode(['data' => is_array($res) ? $res : []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao obter catálogo de apps.']);
        }
    }

    /**
     * GET /api/apps/entitlements?app_id=ID[&em=ID&cm=ID]
     * Retorna se o usuário logado (via middleware) tem vínculo/instalação com o app
     * no contexto pessoal (us) e/ou empresa/equipe.
     * Protegido por AuthMiddleware (payload é o primeiro argumento).
     */
    public function entitlements(object $auth): void
    {
        header("Content-Type: application/json");
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $appId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;
            if ($appId <= 0) { http_response_code(400); echo json_encode(['error' => 'Parâmetro app_id é obrigatório.']); return; }

            $em = isset($_GET['em']) ? (int)$_GET['em'] : null; // empresa opcional

            // Entitlement pessoal
            $hasUser = $this->generalModel->count('workz_apps', 'gapp', [ 'us' => $userId, 'ap' => $appId, 'st' => 1 ]) > 0;

            // Entitlement empresa (se informado)
            $hasCompany = false;
            if (!empty($em)) {
                $hasCompany = $this->generalModel->count('workz_apps', 'gapp', [ 'em' => $em, 'ap' => $appId, 'st' => 1 ]) > 0;
            }

            // Equipe (cm) poderá ser suportado futuramente no schema
            $hasTeam = false;

            echo json_encode([
                'data' => [
                    'user' => $hasUser,
                    'company' => $hasCompany,
                    'team' => $hasTeam,
                ]
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao obter entitlements.']);
        }
    }

    /**
     * POST /api/apps/sso
     * Body JSON: { app_id: number, ctx: { type: 'user'|'business'|'team', id: number } }
     * Retorna token curto (HS256 por enquanto) com claims: sub, aud, ctx, scopes (placeholder).
     * Protegido por AuthMiddleware.
     */
    public function sso(object $auth): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $appId = (int)($input['app_id'] ?? 0);
        $ctx   = $input['ctx'] ?? null; // ['type' => ..., 'id' => ...]

        if ($appId <= 0) { http_response_code(400); echo json_encode(['error' => 'Parâmetro app_id é obrigatório.']); return; }
        $userId = (int)($auth->sub ?? 0);
        if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

        // Verificação básica de entitlement (pessoal/empresa, quando fornecido)
        $hasUser = $this->generalModel->count('workz_apps', 'gapp', [ 'us' => $userId, 'ap' => $appId, 'st' => 1 ]) > 0;
        $hasCompany = false;
        if (is_array($ctx) && ($ctx['type'] ?? '') === 'business') {
            $em = (int)($ctx['id'] ?? 0);
            if ($em > 0) {
                $hasCompany = $this->generalModel->count('workz_apps', 'gapp', [ 'em' => $em, 'ap' => $appId, 'st' => 1 ]) > 0;
            }
        }

        if (!$hasUser && !$hasCompany) {
            // Poderia permitir trial; por ora, negar
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão de uso para este app no contexto informado.']);
            return;
        }

        // Buscar dados mínimos do app (para aud/slug futuramente)
        $app = $this->generalModel->search('workz_apps', 'apps', ['id','tt'], ['id' => $appId], false);
        if (!$app) { http_response_code(404); echo json_encode(['error' => 'App não encontrado.']); return; }

        $issuedAt = time();
        $expire   = $issuedAt + 600; // 10 minutos
        $payload  = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $userId,
            'aud' => 'app:' . $app['id'],
            'ctx' => is_array($ctx) ? $ctx : ['type' => 'user', 'id' => $userId],
            // Scopes do app (do campo scopes da tabela apps)
            'scopes' => json_decode($app['scopes'] ?? '[]', true),
        ];

        $secretKey = $_ENV['JWT_SECRET'] ?? '';
        if (!$secretKey) { http_response_code(500); echo json_encode(['error' => 'JWT não configurado.']); return; }

        $jwt = JWT::encode($payload, $secretKey, 'HS256');
        echo json_encode([
            'token' => $jwt,
            'exp' => $expire,
            'user' => [ 'id' => $userId ],
            'context' => $payload['ctx'],
        ]);
    }

    /**
     * GET /api/apps/manifest/{slug}
     * Gera o manifesto dinâmico do app
     */
    public function manifest(string $slug): void
    {
        header("Content-Type: application/json");
        
        try {
            // Buscar dados do app (updated to include storage information)
            $app = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'version', 'publisher', 'access_level', 'entity_type', 'scopes', 'created_at', 'updated_at', 'app_type', 'storage_type', 'code_size_bytes'],
                ['slug' => $slug, 'st' => 1],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['error' => 'App não encontrado']);
                return;
            }

            // Carregar template do manifesto
            $templatePath = dirname(__DIR__, 2) . '/public/apps/manifest-template.json';
            if (!file_exists($templatePath)) {
                http_response_code(500);
                echo json_encode(['error' => 'Template do manifesto não encontrado']);
                return;
            }

            $template = file_get_contents($templatePath);

            // Substituir placeholders (updated to include storage information)
            $manifest = str_replace([
                '{{APP_NAME}}',
                '{{APP_SHORT_NAME}}',
                '{{APP_DESCRIPTION}}',
                '{{APP_VERSION}}',
                '{{APP_SLUG}}',
                '{{APP_PUBLISHER}}',
                '{{APP_COLOR}}',
                '{{APP_ICON}}',
                '{{APP_SCOPES}}',
                '{{APP_ACCESS_LEVEL}}',
                '{{APP_ENTITY_TYPE}}',
                '{{APP_PRICE}}',
                '{{APP_CREATED_AT}}',
                '{{APP_UPDATED_AT}}',
                '{{APP_TYPE}}',
                '{{STORAGE_TYPE}}',
                '{{CODE_SIZE_BYTES}}'
            ], [
                $app['tt'],
                strlen($app['tt']) > 12 ? substr($app['tt'], 0, 12) : $app['tt'],
                $app['ds'] ?: 'Aplicativo da plataforma Workz',
                $app['version'] ?: '1.0.0',
                $app['slug'],
                $app['publisher'] ?: 'Workz Platform',
                $app['color'] ?: '#3b82f6',
                $app['im'] ?: '/images/no-image.jpg',
                $app['scopes'] ?: '[]',
                $app['access_level'] ?: 1,
                $app['entity_type'] ?: 0,
                $app['vl'] ?: 0.00,
                $app['created_at'] ?: date('Y-m-d\TH:i:s\Z'),
                $app['updated_at'] ?: date('Y-m-d\TH:i:s\Z'),
                $app['app_type'] ?: 'javascript',
                $app['storage_type'] ?: 'database',
                $app['code_size_bytes'] ?: 0
            ], $template);

            echo $manifest;

        } catch (\Throwable $e) {
            error_log("Erro ao gerar manifesto: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno do servidor']);
        }
    }

    /**
     * GET /app/run/{slug}
     * O "App Runner" que serve o HTML do aplicativo com o JS injetado.
     * Protegido por AuthMiddleware.
     */
    public function run(object $auth, string $slug): void
    {
        $userId = (int)($auth->sub ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo "Acesso negado. Faça login para usar os aplicativos.";
            return;
        }

        // 1. Buscar metadados do app pelo slug (updated to include storage information)
        $app = $this->generalModel->search(
            'workz_apps',
            'apps',
            ['id', 'tt', 'im', 'st', 'access_level', 'js_code', 'dart_code', 'app_type', 'color', 'storage_type', 'repository_path', 'scopes', 'version'],
            ['slug' => $slug],
            false // fetchOne
        );

        if (!$app || (int)$app['st'] !== 1) {
            http_response_code(404);
            echo "Aplicativo não encontrado ou inativo.";
            return;
        }

        // 2. Verificar permissões de acesso
        $accessLevel = (int)$app['access_level'];
        if ($accessLevel > 1) { // Requer vínculo
            $hasAccess = $this->generalModel->count(
                'workz_apps',
                'gapp',
                ['us' => $userId, 'ap' => $app['id'], 'st' => 1]
            ) > 0;

            if (!$hasAccess) {
                http_response_code(403);
                echo "Você não tem permissão para acessar este aplicativo.";
                return;
            }
        }

        // 3. Verificar tipo do app e renderizar adequadamente (updated for unified storage)
        $appType = $app['app_type'] ?? 'javascript';
        $storageType = $app['storage_type'] ?? 'database';
        
        if ($appType === 'flutter') {
            // Canonical artifact path only (no slug fallback)
            $canonicalPath = "/apps/flutter/{$app['id']}/web/index.html";
            $canonicalFull = dirname(__DIR__, 2) . '/public' . $canonicalPath;

            // No legacy slug-based path fallback
            $chosenFull = file_exists($canonicalFull) ? $canonicalFull : null;
            if ($chosenFull) {
                header('Content-Type: text/html; charset=utf-8');
                echo file_get_contents($chosenFull);
                return;
            }

            // Se não existe build, mostrar mensagem de erro
            http_response_code(404);
            echo "App Flutter ainda não foi compilado. Aguarde o processo de build.";
            return;
        }
        
        // 4. Para apps JavaScript, usar o template embed.html
        $templatePath = dirname(__DIR__, 2) . '/public/apps/embed.html';
        if (!file_exists($templatePath)) {
            http_response_code(500);
            echo "Erro interno: Template do aplicativo não encontrado.";
            return;
        }
        $template = file_get_contents($templatePath);

        // 5. Injetar o código JS e configurações (updated for unified storage)
        $jsCode = $app['js_code'] ?? 'console.error("Nenhum código de aplicativo encontrado.");';
        
        // Se o app usa filesystem storage, carregar código do sistema de storage
        if ($storageType === 'filesystem' && !empty($app['repository_path'])) {
            try {
                $storageManager = new StorageManager();
                $codeData = $storageManager->getAppCode($app['id']);
                $jsCode = $codeData['js_code'] ?? $jsCode;
            } catch (\Throwable $e) {
                error_log("Error loading code from filesystem storage: " . $e->getMessage());
                // Fallback to database code
            }
        }
        
        $appColor = $app['color'] ?? '#3b82f6';
        $appIcon = $app['im'] ?? '/images/no-image.jpg';
        $appName = $app['tt'] ?? 'Workz App';
        $appScopes = json_encode(json_decode($app['scopes'] ?? '[]', true));

        // Substituir todos os placeholders do template embed.html
        $replacements = [
            '{{APP_ID}}' => $app['id'],
            '{{APP_NAME}}' => htmlspecialchars($appName),
            '{{APP_SLUG}}' => htmlspecialchars($slug),
            '{{APP_TYPE}}' => htmlspecialchars($appType),
            '{{STORAGE_TYPE}}' => htmlspecialchars($storageType),
            '{{APP_VERSION}}' => htmlspecialchars($app['version'] ?? '1.0.0'),
            '{{APP_COLOR}}' => htmlspecialchars($appColor),
            '{{APP_ICON}}' => htmlspecialchars($appIcon),
            '{{APP_SCOPES}}' => htmlspecialchars($appScopes),
            '{{TARGET_PLATFORM}}' => 'web',
            '{{EXECUTION_MODE}}' => $storageType === 'filesystem' ? 'artifact' : 'direct',
            '{{FLUTTER_WEB_SCRIPTS}}' => $appType === 'flutter' ? $this->generateFlutterWebScripts((int)$app['id']) : '',
            '{{APP_CONTENT}}' => $this->generateAppContent($jsCode, $appType, $storageType)
        ];

        $output = str_replace(array_keys($replacements), array_values($replacements), $template);

        // 6. Servir o HTML
        header('Content-Type: text/html; charset=utf-8');
        echo $output;
    }

    /**
     * Gera scripts necessários para Flutter Web
     */
    private function generateFlutterWebScripts(int $appId): string
    {
        return <<<HTML
    <script>
        window.addEventListener('load', function(ev) {
            // Download main.dart.js
            _flutter.loader.loadEntrypoint({
                serviceWorker: {
                    serviceWorkerVersion: null,
                },
                onEntrypointLoaded: function(engineInitializer) {
                    engineInitializer.initializeEngine().then(function(appRunner) {
                        appRunner.runApp();
                    });
                }
            });
        });
    </script>
    <script src="/apps/flutter/{$appId}/web/flutter.js" defer></script>
HTML;
    }

    /**
     * Gera o conteúdo do aplicativo baseado no tipo e modo de execução
     */
    private function generateAppContent(string $jsCode, string $appType, string $storageType): string
    {
        if ($appType === 'flutter') {
            // Para Flutter, o conteúdo é carregado pelos scripts Flutter
            return '<!-- Flutter app content loaded by Flutter runtime -->';
        }

        // Para JavaScript, injetar o código de forma segura
        $escapedJsCode = json_encode($jsCode);
        $content = <<<HTML
<script>
    // Código do aplicativo JavaScript
    window.WorkzAppCode = {$escapedJsCode};
    
    // Executar código se não houver ponto de entrada específico
    try {
        if (typeof window.StoreApp === 'undefined' && typeof window.initApp === 'undefined') {
            eval(window.WorkzAppCode);
        }
    } catch (error) {
        console.error('Erro ao executar código do aplicativo:', error);
        if (window.WorkzSDK && window.WorkzSDK.emit) {
            window.WorkzSDK.emit('app:error', {
                type: 'execution_error',
                message: error.message,
                stack: error.stack
            });
        }
    }
</script>
HTML;

        return $content;
    }

    /**
     * POST /api/apps/{id}/rebuild
     * Triggers a new build for a Flutter app by calling the build worker.
     * Protegido por AuthMiddleware.
     */
    public function rebuild(object $auth, int $appId): void
    {
        header("Content-Type: application/json");
        $userId = (int)($auth->sub ?? 0);

        try {
            // 1. Fetch app data
            $app = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'slug', 'dart_code', 'app_type', 'user_id', 'files', 'storage_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado.']);
                return;
            }

            // 2. Basic permission check (is the user the owner?)
            // You might want to add more complex logic (e.g., company moderator)
            if ($app['user_id'] != $userId && !(\Workz\Platform\Policies\BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'] ?? null))) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Você não tem permissão para compilar este app.']);
                return;
            }

            $isFilesystem = ($app['storage_type'] ?? 'database') === 'filesystem';
            $hasCode = !empty($app['dart_code']) || ($isFilesystem && !empty($app['files']));

            if ($app['app_type'] !== 'flutter' || !$hasCode) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Este app não é um app Flutter válido ou não possui código.']);
                return;
            }

            // 3. Call the Node.js Build Worker
            $workerUrl = 'http://localhost:9091/build/' . $appId;
            
            $payloadData = ['slug' => $app['slug']];
            if ($isFilesystem && !empty($app['files'])) {
                $payloadData['files'] = json_decode($app['files'], true);
            } else {
                $payloadData['dart_code'] = $app['dart_code'];
            }

            $payload = json_encode($payloadData);
            $ch = curl_init($workerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            // Set a short timeout, as the worker should respond immediately (202)
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("cURL Error em rebuild() ao contatar Build Worker: " . $curlError);
            }

            if ($httpCode === 202) {
                echo json_encode(['success' => true, 'message' => 'Solicitação de build enviada ao worker.']);
            } else {
                http_response_code(502); // Bad Gateway
                echo json_encode(['success' => false, 'message' => 'Não foi possível conectar ao serviço de build.', 'details' => $response]);
            }

        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno ao iniciar o build.', 'error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/apps/{id}/build-status
     * Endpoint for the build worker to post status updates.
     * Should be protected by an internal secret/token.
     */
    public function updateBuildStatus(int $appId): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true);

        // SECURITY: Add a secret key check to ensure only the worker can call this
        // $secret = $_SERVER['HTTP_X_WORKER_SECRET'] ?? '';
        // if ($secret !== ($_ENV['WORKER_SECRET'] ?? '')) {
        //     http_response_code(403);
        //     echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        //     return;
        // }

        $status = $input['status'] ?? 'pending';
        $log = $input['log'] ?? 'Nenhum log fornecido.';
        $buildVersion = $input['build_version'] ?? '1.0.0'; // O worker pode enviar isso
        $platform = $input['platform'] ?? 'web'; // O worker pode especificar a plataforma
        $incomingFilePath = $input['file_path'] ?? null;

        // Update the 'apps' table with the general build status and timestamp
        $this->generalModel->update('workz_apps', 'apps', ['build_status' => $status, 'last_build_at' => date('Y-m-d H:i:s')], ['id' => $appId]);

        // Inserir ou atualizar um registro detalhado na tabela `flutter_builds`
        $existingBuild = $this->generalModel->search(
            'workz_apps',
            'flutter_builds',
            ['id'],
            ['app_id' => $appId, 'platform' => $platform],
            false
        );

        // Resolve canonical file path used to serve the build artifacts
        $defaultFilePath = "/apps/flutter/{$appId}/web/";
        $resolvedFilePath = $incomingFilePath ?: $defaultFilePath;

        $buildData = [
            'app_id' => $appId,
            'platform' => $platform,
            'build_version' => $buildVersion,
            'status' => $status,
            'file_path' => ($status === 'success') ? $resolvedFilePath : null,
            'build_log' => $log,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($existingBuild) {
            $this->generalModel->update('workz_apps', 'flutter_builds', $buildData, ['id' => $existingBuild['id']]);
        } else {
            $this->generalModel->insert('workz_apps', 'flutter_builds', $buildData);
        }

        echo json_encode(['success' => true, 'message' => 'Status atualizado.']);
    }

    /**
     * GET /api/apps/{id}/build-status
     * Retorna o status detalhado de build de um app (especialmente Flutter).
     * Protegido por AuthMiddleware.
     */
    public function getBuildStatus(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            // 1. Buscar dados básicos do app
            $app = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'slug', 'app_type', 'exclusive_to_entity_id', 'build_status'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // 2. Verificar permissões
            if (!\Workz\Platform\Policies\BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            $response = [
                'success' => true,
                'data' => [
                    'app_id' => $appId,
                    'app_type' => $app['app_type'] ?? 'javascript',
                    'build_status' => $app['build_status'] ?? 'pending', // Status geral do app
                    'compiled_at' => null,
                    'build_log' => 'Nenhum log disponível.',
                    'builds' => [] // Detalhes de builds por plataforma
                ]
            ];

            // Considerar o estado mais recente da fila para refletir 'pending', 'building' e também 'failed'
            try {
                $queueInfo = $this->generalModel->search(
                    'workz_apps',
                    'build_queue',
                    ['status','updated_at'],
                    ['app_id' => $appId],
                    true,
                    1,
                    0,
                    ['by' => 'updated_at', 'dir' => 'DESC']
                );
                if (!empty($queueInfo)) {
                    $q = $queueInfo[0];
                    $queueStatus = $q['status'] ?? null;
                    if (in_array($queueStatus, ['pending','building','failed','success'])) {
                        $response['data']['build_status'] = $queueStatus;
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }

            // 3. Se for um app Flutter, buscar detalhes da tabela flutter_builds
            if (($app['app_type'] ?? 'javascript') === 'flutter') {
                $flutterBuilds = $this->generalModel->search(
                    'workz_apps',
                    'flutter_builds',
                    ['id', 'platform', 'build_version', 'status', 'file_path', 'updated_at', 'build_log'],
                    ['app_id' => $appId],
                    true, // fetch all
                    10, // limit
                    0,
                    ['by' => 'updated_at', 'dir' => 'DESC']
                );

                if (!empty($flutterBuilds)) {
                    $response['data']['builds'] = array_map(function($build) use ($appId, $app) {
                        $build['download_url'] = null;
                        $build['store_url'] = null;
                        if ($build['status'] === 'success') {
                            if ($build['platform'] === 'web') {
                                // Prefer recorded file_path; fallback only to canonical ID path
                                $path = $build['file_path'] ?: "/apps/flutter/{$appId}/web/";
                                $build['download_url'] = $path;
                            } else {
                                $build['download_url'] = "/api/apps/{$appId}/artifacts/{$build['platform']}";
                            }
                        }
                        return $build;
                    }, $flutterBuilds);

                    // Atualizar status geral e compiled_at com base no build mais recente
                    $latestBuild = $flutterBuilds[0];
                    $response['data']['build_status'] = $latestBuild['status'];
                    $response['data']['compiled_at'] = $latestBuild['updated_at'];
                    $response['data']['build_log'] = $latestBuild['build_log'] ?? 'Logs detalhados disponíveis nos artefatos.';
                }
            }
            
            // Se o app é JavaScript, usar UniversalRuntime's info (fallback para apps JS)
            if (($app['app_type'] ?? 'javascript') === 'javascript') {
                $buildInfo = UniversalRuntime::getBuildInfo($appId, 'javascript');
                $response['data']['build_status'] = $buildInfo['is_compiled'] ? 'success' : 'pending';
                $response['data']['compiled_at'] = $buildInfo['last_modified'];
                $response['data']['build_log'] = 'Compilado pelo Runtime Universal';
            }

            echo json_encode($response);

        } catch (\Throwable $e) {
            error_log("Erro AppsController::getBuildStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
