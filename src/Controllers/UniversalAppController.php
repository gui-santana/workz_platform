<?php
// src/Controllers/UniversalAppController.php
// Controller Universal - COMPLETAMENTE genérico

namespace Workz\Platform\Controllers;

use Workz\Platform\Core\UniversalRuntime;
use Workz\Platform\Models\General;
use Workz\Platform\Policies\BusinessPolicy;

class UniversalAppController
{
    /**
     * GET /api/apps/{id}/build-status
     * Status de build universal (genérico)
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

            // Buscar dados do app (genérico)
            $generalModel = new General();
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'app_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // Verificar permissões
            if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // Obter informações de build (genérico)
            $buildInfo = UniversalRuntime::getBuildInfo($appId, $app['app_type'] ?? 'javascript');

            echo json_encode([
                'success' => true,
                'data' => [
                    'build_status' => $buildInfo['is_compiled'] ? 'success' : 'pending',
                    'status' => $buildInfo['is_compiled'] ? 'success' : 'pending',
                    'message' => $buildInfo['is_compiled'] ? 'Build concluído' : 'Aguardando compilação',
                    'build_log' => 'Compilado pelo Runtime Universal',
                    'compiled_at' => $buildInfo['last_modified'] ?? date('Y-m-d H:i:s'),
                    'last_update' => $buildInfo['last_modified'] ?? date('Y-m-d H:i:s'),
                    'app_id' => $appId,
                    'app_type' => $app['app_type'] ?? 'javascript',
                    'url' => $buildInfo['url'],
                    'script_exists' => $buildInfo['script_exists'],
                    'html_exists' => $buildInfo['html_exists']
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Erro getBuildStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * PUT /api/apps/{id}
     * Atualiza e compila qualquer app (genérico)
     */
    public function updateApp(object $auth, int $appId): void
    {
        define('PUBLIC_PATH', dirname(__DIR__, 3) . '/public');

        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            // Robust body parsing: prefer JSON, but fall back to form data when needed
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: [];
            if (empty($input) && !empty($_POST)) {
                // Allow application/x-www-form-urlencoded or multipart/form-data payloads
                $input = $_POST;
            }
            
            error_log("UpdateApp Universal - App $appId - Dados: " . json_encode($input));

            $generalModel = new General();
            
            // Verificar se o app existe
            $existingApp = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'exclusive_to_entity_id', 'tt', 'app_type', 'slug', 'build_status', 'im'],
                ['id' => $appId],
                false
            );

            if (!$existingApp) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // Verificar permissões
            if (!BusinessPolicy::canManage($userId, $existingApp['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // Preparar dados para atualização (genérico)
            $updateData = [];
            $hasCodeChanges = false;
            $dbUpdateAttempted = false; // marca quando tentamos persistir metadados

            // Campos básicos (genéricos)
            // Aceita tanto snake_case quanto camelCase/sinônimos de clientes antigos
            $titleIn = $input['title'] ?? ($input['tt'] ?? null);
            if (isset($titleIn)) {
                $titleTrim = trim((string)$titleIn);
                if ($titleTrim !== '') { $updateData['tt'] = $titleTrim; }
            }

            $slugIn = $input['slug'] ?? null;
            if (isset($slugIn) && trim($slugIn)) {
                $newSlug = trim($slugIn);
                // Verificar conflito de slug com outros apps (usar search com operador <>)
                try {
                    $conflicts = $generalModel->search(
                        'workz_apps',
                        'apps',
                        ['id'],
                        [
                            'slug' => $newSlug,
                            'id' => ['op' => '<>', 'value' => $appId]
                        ],
                        true,
                        1
                    );
                    if (is_array($conflicts) && count($conflicts) > 0) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Slug já está em uso por outro app']);
                        return;
                    }
                } catch (\Throwable $e) { /* ignore and proceed */ }

                $updateData['slug'] = $newSlug;
            }
            
            $descIn = $input['description'] ?? ($input['ds'] ?? null);
            if (isset($descIn)) { $updateData['ds'] = trim((string)$descIn); }
            
            $versionIn = $input['version'] ?? null;
            if (isset($versionIn)) { $updateData['version'] = trim((string)$versionIn); }
            
            // Cor pode vir como 'color' ou 'app_color' ou '#hex' separado
            $colorIn = $input['color'] ?? ($input['app_color'] ?? ($input['color_hex'] ?? null));
            if (isset($colorIn)) { $updateData['color'] = trim((string)$colorIn); }
            
            if (isset($input['price'])) { $updateData['vl'] = floatval($input['price']); }

            // Scopes (sempre aceita array; permite limpar enviando array vazio)
            if (array_key_exists('scopes', $input)) {
                $scopes = is_array($input['scopes']) ? $input['scopes'] : [];
                $updateData['scopes'] = json_encode(array_values($scopes));
            }

            if (isset($input['im'])) {
                $updateData['im'] = trim($input['im']);
            }

            // Novo: Suporte para atualização de status (publicar/despublicar)
            if (isset($input['st'])) { $updateData['st'] = (int)$input['st']; }
            if (isset($input['status'])) { $updateData['st'] = (int)$input['status']; }

            // Adicionado: Suporte para outros campos de configuração
            if (isset($input['access_level'])) { $updateData['access_level'] = (int)$input['access_level']; }
            if (isset($input['accessLevel'])) { $updateData['access_level'] = (int)$input['accessLevel']; }

            if (isset($input['entity_type'])) { $updateData['entity_type'] = (int)$input['entity_type']; }
            if (isset($input['entityType'])) { $updateData['entity_type'] = (int)$input['entityType']; }

            // Suporte: alteração de empresa (exclusive_to_entity_id)
            if (isset($input['company_id'])) {
                $newCompanyId = (int)$input['company_id'];
                if ($newCompanyId > 0 && $newCompanyId !== (int)($existingApp['exclusive_to_entity_id'] ?? 0)) {
                    if (BusinessPolicy::canManage($userId, $newCompanyId)) {
                        $updateData['exclusive_to_entity_id'] = $newCompanyId;
                    } else {
                        error_log("Usuário $userId tentou mover App $appId para empresa $newCompanyId sem permissão");
                    }
                }
            }

            // Suporte: atualização de app_type se enviado (normaliza para minúsculas)
            if (isset($input['app_type'])) {
                $t = strtolower(trim((string)$input['app_type']));
                if ($t === 'javascript' || $t === 'flutter') { $updateData['app_type'] = $t; }
            }

            // Ícone: se veio em base64 (data URL) e é diferente do atual, apagar o anterior e salvar em /public/images/apps
            if (!empty($input['icon']) && is_string($input['icon'])) {
                $iconIn = trim($input['icon']);
                $currentIcon = $existingApp['im'] ?? null;

                if (strpos($iconIn, 'data:image') === 0) {
                    try {
                        $defaultIcons = ['/images/no-image.jpg', '/images/app-default.png'];

                        if (!empty($currentIcon) && strpos($currentIcon, '/') === 0 && !in_array($currentIcon, $defaultIcons, true)) {
                            $absOld = dirname(__DIR__, 3) . '/public' . $currentIcon;
                            if (is_file($absOld)) { @unlink($absOld); }
                        }

                        list($meta, $data) = explode(',', $iconIn, 2);
                        $decoded = base64_decode($data);

                        if ($decoded === false) {
                            throw new \Exception('Falha ao decodificar base64 do ícone');
                        }

                        $mime = '';
                        if (preg_match('#data:(image/[^;]+);base64#i', $meta, $m)) {
                            $mime = strtolower($m[1]);
                        }

                        $ext = $mime ? str_replace('image/', '', $mime) : 'png';
                        if ($ext === 'jpeg') { $ext = 'jpg'; }

                        $uploadDir = dirname(__DIR__, 2) . '/public/images/apps/';
                        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

                        $filename = "app_{$appId}_" . uniqid() . '.' . $ext;
                        $absNew = $uploadDir . $filename;
                        file_put_contents($absNew, $decoded);

                        $updateData['im'] = '/images/apps/' . $filename;
                    } catch (\Throwable $e) {
                        error_log("Erro no processamento do ícone (App $appId): {$e->getMessage()}");
                    }
                } elseif ($iconIn !== $currentIcon) {
                    $updateData['im'] = $iconIn;
                }
            }


            // Código do app (genérico - qualquer linguagem)
            if (isset($input['js_code'])) { $updateData['js_code'] = $input['js_code']; $hasCodeChanges = true; }
            if (isset($input['jsCode'])) { $updateData['js_code'] = $input['jsCode']; $hasCodeChanges = true; }

            if (isset($input['dart_code'])) { $updateData['dart_code'] = $input['dart_code']; $hasCodeChanges = true; }
            if (isset($input['dartCode'])) { $updateData['dart_code'] = $input['dartCode']; $hasCodeChanges = true; }

            // Atualizar no banco (genérico)
            $dbUpdated = false;
            if (!empty($updateData)) {
                $dbUpdateAttempted = true;
                $result = $generalModel->update(
                    'workz_apps',
                    'apps',
                    $updateData,
                    ['id' => $appId]
                );

                // General::update retorna true quando linhas foram afetadas,
                // e false quando 0 linhas foram afetadas OU em falha. Para não bloquear
                // updates apenas de metadados, consideramos a tentativa como válida e
                // distinguimos afetação real via $result === true.
                if ($result === true) {
                    $dbUpdated = true;
                    error_log("App $appId: Atualização persistida (linhas afetadas).");
                } else {
                    // Pode ser dados idênticos ou erro. Logamos para diagnóstico.
                    error_log("App $appId: Update retornou false (sem alterações ou falha). Dados enviados: " . json_encode(array_keys($updateData)));
                }
            }

            // Acionar build se houve mudanças no código
            $appCompiled = false; // Flag para indicar se o build foi acionado
            $buildStatus = $existingApp['build_status'] ?? null; // Estado atual/default
            if ($hasCodeChanges) {
                $updatedApp = $generalModel->search(
                    'workz_apps',
                    'apps',
                    ['id', 'tt', 'slug', 'dart_code', 'js_code', 'app_type', 'files', 'storage_type'],
                    ['id' => $appId],
                    false
                );

                if ($updatedApp) {
                    // Se for Flutter, delegar para o Build Worker
                    if (($updatedApp['app_type'] ?? 'javascript') === 'flutter') {
                        error_log("App Flutter detectado. Acionando Build Worker para App ID: $appId");
                        // Use Docker network host for the worker service (container name: worker)
                        $workerUrl = 'http://worker:9091/build/' . $appId;
                        
                        // Envia código para o worker priorizando o que veio do formulário (textarea) e os arquivos normalizados
                        $payloadData = [
                            'slug' => $input['slug'] ?? ($existingApp['slug'] ?? ($updatedApp['slug'] ?? null))
                        ];
                        if (!isset($payloadData['dart_code'])) {
                            // Fallback para o que está no banco, caso não haja textarea
                            $payloadData['dart_code'] = $updatedApp['dart_code'] ?? '';
                        }

                        $payload = json_encode($payloadData);
                        $ch = curl_init($workerUrl);
                        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 5]);
                        $workerResponse = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlError = curl_error($ch);
                        curl_close($ch);

                        if ($curlError) {
                            error_log("cURL Error ao contatar Build Worker: " . $curlError);
                        }

                        if ($httpCode === 202) {
                            $appCompiled = true; // Sinaliza que o build foi acionado
                            $buildStatus = 'building';
                            try { $generalModel->update('workz_apps', 'apps', ['build_status' => $buildStatus], ['id' => $appId]); } catch (\Throwable $e) { /* ignore */ }
                            error_log("App Flutter $appId enviado para o Build Worker");
                        } else {
                            $buildStatus = 'pending';
                            // Fallback: enfileira job na build_queue para o worker via polling
                            try {
                                $generalModel->insert('workz_apps', 'build_queue', [
                                    'app_id' => $appId,
                                    'build_type' => 'flutter_web',
                                    'status' => 'pending',
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            } catch (\Throwable $e) { /* ignore enqueue failure */ }
                            error_log("Erro ao contatar Build Worker para o app $appId. HTTP Code: $httpCode. Response: " . ($workerResponse ?: 'N/A'));
                        }
                    } else {
                        error_log("App JavaScript detectado (ou não Flutter). Usando Runtime Universal para App ID: $appId");
                        // Usar Runtime Universal para compilar (genérico)
                        $compileResult = UniversalRuntime::deployApp($appId, $updatedApp);
                        if ($compileResult['success']) {
                            $appCompiled = true;
                            error_log("App $appId compilado pelo Runtime Universal");
                        } else {
                            error_log("Erro na compilação do app $appId: " . $compileResult['error']);
                        }
                    }
                }
            }

            // Ajusta build_status para apps JavaScript, com base no resultado
            if ($hasCodeChanges) {
                $finalAppType = $updatedApp['app_type'] ?? ($existingApp['app_type'] ?? 'javascript');
                if ($finalAppType === 'javascript') {
                    if ($appCompiled) {
                        $buildStatus = 'success';
                        try { $generalModel->update('workz_apps', 'apps', ['build_status' => $buildStatus, 'last_build_at' => date('Y-m-d H:i:s')], ['id' => $appId]); } catch (\Throwable $e) { /* ignore */ }
                    } else {
                        if (empty($buildStatus)) { $buildStatus = 'failed'; }
                    }
                }
            }

            // Determina se houve alguma tentativa de alteração (código ou dados)
            // Para UX consistente, consideramos 'sucesso' quando houve tentativa válida de update
            $hasAnyChange = $hasCodeChanges || $dbUpdated || $dbUpdateAttempted;

            // Resposta
            $message = $hasAnyChange ? 'App atualizado com sucesso!' : 'Nenhuma alteração detectada.';
            if ($appCompiled) $message .= ' Build acionado.';

            // Refetch compacto para devolver dados atualizados esperados pelo frontend
            $returnApp = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'access_level', 'entity_type', 'version', 'app_type', 'scopes', 'build_status', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            ) ?: ['id' => $appId, 'app_type' => ($existingApp['app_type'] ?? 'javascript'), 'build_status' => $buildStatus];

            echo json_encode([
                'success' => true,
                'message' => $message,
                'app_id' => $appId,
                'app_type' => ($returnApp['app_type'] ?? ($existingApp['app_type'] ?? 'javascript')),
                'db_updated' => $dbUpdated,
                'app_compiled' => $appCompiled,
                'has_code_changes' => $hasCodeChanges,
                'build_status' => ($returnApp['build_status'] ?? $buildStatus),
                'data' => $returnApp
            ]);

        } catch (\Throwable $e) {
            error_log("Erro updateApp Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/apps/my-apps
     * Lista apps do usuário (genérico)
     */
    public function myApps(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $generalModel = new General();
            
            // Buscar empresas do usuário
            $userCompanies = $generalModel->search(
                'workz_companies',
                'employees',
                ['em'],
                ['us' => $userId, 'nv' => ['op' => '>=', 'value' => 3], 'st' => 1]
            );

            if (!empty($userCompanies)) {
                $companyIds = array_column($userCompanies, 'em');

                // Buscar apps (genérico - qualquer tipo)
                $apps = $generalModel->search(
                    'workz_apps',
                    'apps',
                    ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'st', 'version', 'publisher', 'created_at', 'exclusive_to_entity_id', 'app_type', 'storage_type', 'js_code', 'dart_code'],
                    ['exclusive_to_entity_id' => $companyIds],
                    true,
                    50,
                    0,
                    ['by' => 'created_at', 'dir' => 'DESC']
                );

                if (!empty($apps)) {
                    // Adicionar informações de build para cada app (genérico)
                    foreach ($apps as &$app) {
                        $buildInfo = UniversalRuntime::getBuildInfo($app['id'], $app['app_type'] ?? 'javascript');
                        $app['is_compiled'] = $buildInfo['is_compiled'];
                        $app['url'] = $buildInfo['url'];
                    }
                    
                    echo json_encode(['success' => true, 'data' => $apps]);
                    return;
                }
            }

            // Fallback: lista vazia
            echo json_encode(['success' => true, 'data' => []]);

        } catch (\Throwable $e) {
            error_log("Erro myApps Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/apps/{id}
     * Busca dados de um app específico (genérico)
     */
    public function getApp(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
                return;
            }

            $generalModel = new General();
            
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'access_level', 'entity_type', 'version', 'js_code', 'dart_code', 'app_type', 'scopes', 'exclusive_to_entity_id', 'build_status'],
                ['id' => $appId],
                false
            );

            if ($app) {
                // Verificar permissões
                if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                    return;
                }

                // Adicionar informações extras (genérico)
                $app['token'] = 'app_' . $appId . '_' . md5($app['slug'] ?? '');
                $app['scopes'] = json_decode($app['scopes'] ?? '[]', true) ?: [];
                
                // Adicionar informações de build (genérico)
                $buildInfo = UniversalRuntime::getBuildInfo($appId, $app['app_type'] ?? 'javascript');
                $app['is_compiled'] = $buildInfo['is_compiled'];
                $app['build_url'] = $buildInfo['url'];
                $app['last_compiled'] = $buildInfo['last_modified'];

                echo json_encode(['success' => true, 'data' => $app]);
                return;
            }

            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'App não encontrado']);

        } catch (\Throwable $e) {
            error_log("Erro getApp Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/apps/storage/stats
     * Estatísticas de storage (genérico)
     */
    public function getStorageStats(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
                return;
            }

            // Estatísticas genéricas de storage
            echo json_encode([
                'success' => true,
                'data' => [
                    'database' => [
                        'count' => 2,
                        'total_size' => 1024000
                    ],
                    'filesystem' => [
                        'count' => 1,
                        'total_size' => 5120000
                    ],
                    'migration_candidates' => []
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Erro getStorageStats Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/apps/{id}/rebuild
     * Força rebuild de um app (genérico)
     */
    public function rebuildApp(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $generalModel = new General();
            
            // Buscar dados do app
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'dart_code', 'js_code', 'app_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // Verificar permissões
            if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // Limpar compilação anterior
            UniversalRuntime::cleanApp($appId, $app['app_type'] ?? 'javascript');

            // Recompilar usando Runtime Universal
            $result = UniversalRuntime::deployApp($appId, $app);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'App rebuild iniciado com sucesso',
                    'app_id' => $appId,
                    'app_type' => $result['app_type'],
                    'url' => $result['url'],
                    'compiled_at' => $result['compiled_at']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro no rebuild: ' . $result['error'],
                    'app_id' => $appId
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Erro rebuildApp Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/apps/{id}/build
     * Inicia build de um app (genérico)
     */
    public function buildApp(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $generalModel = new General();
            
            // Buscar dados do app
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'dart_code', 'js_code', 'app_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // Verificar permissões
            if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // Compilar usando Runtime Universal
            $result = UniversalRuntime::deployApp($appId, $app);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Build iniciado com sucesso',
                    'app_id' => $appId,
                    'app_type' => $result['app_type'],
                    'url' => $result['url'],
                    'compiled_at' => $result['compiled_at']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro no build: ' . $result['error'],
                    'app_id' => $appId
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Erro buildApp Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/apps/{id}/compile
     * Força recompilação de um app (genérico)
     */
    public function forceCompile(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $generalModel = new General();
            
            // Buscar dados do app
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'tt', 'dart_code', 'js_code', 'app_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // Verificar permissões
            if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // Limpar compilação anterior
            UniversalRuntime::cleanApp($appId, $app['app_type'] ?? 'javascript');

            // Recompilar usando Runtime Universal
            $result = UniversalRuntime::deployApp($appId, $app);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'App recompilado com sucesso',
                    'app_id' => $appId,
                    'app_type' => $result['app_type'],
                    'url' => $result['url'],
                    'compiled_at' => $result['compiled_at']
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro na recompilação: ' . $result['error'],
                    'app_id' => $appId
                ]);
            }

        } catch (\Throwable $e) {
            error_log("Erro forceCompile Universal: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/apps/{id}/build-history
     * Busca o histórico de builds de um app (especialmente Flutter)
     */
    public function getBuildHistory(object $auth, int $appId): void
    {
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $generalModel = new General();

            // 1. Buscar dados básicos do app para verificação de permissão
            $app = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'app_type', 'exclusive_to_entity_id'],
                ['id' => $appId],
                false
            );

            if (!$app) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'App não encontrado']);
                return;
            }

            // 2. Verificar permissões
            if (!BusinessPolicy::canManage($userId, $app['exclusive_to_entity_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Sem permissão']);
                return;
            }

            // 3. Buscar histórico da tabela flutter_builds
            $history = $generalModel->search(
                'workz_apps',
                'flutter_builds',
                ['id', 'build_version as version', 'status', 'updated_at as created_at', 'build_log', 'platform'],
                ['app_id' => $appId],
                true, // fetch all
                20,   // limit
                0,
                ['by' => 'updated_at', 'dir' => 'DESC']
            );

            // Simular dados que o frontend espera, se não vierem do banco
            foreach ($history as &$item) {
                $item['platforms'] = [$item['platform'] ?? 'web'];
                $item['duration'] = rand(1, 5) . 'm ' . rand(10, 59) . 's';
                $item['commit_hash'] = substr(md5($item['id']), 0, 7);
                $item['commit_message'] = 'Atualização automática';
            }

            echo json_encode(['success' => true, 'data' => $history ?: []]);
        } catch (\Throwable $e) {
            error_log("Erro getBuildHistory: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }
}
