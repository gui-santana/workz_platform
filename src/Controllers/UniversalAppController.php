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
        header("Content-Type: application/json");

        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Não autenticado']);
                return;
            }

            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true) ?: [];
            
            error_log("UpdateApp Universal - App $appId - Dados: " . json_encode($input));

            $generalModel = new General();
            
            // Verificar se o app existe
            $existingApp = $generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'exclusive_to_entity_id', 'tt', 'app_type', 'slug', 'build_status'],
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

            // Campos básicos (genéricos)
            if (isset($input['title']) && trim($input['title'])) {
                $updateData['tt'] = trim($input['title']);
            }
            
            if (isset($input['slug']) && trim($input['slug'])) {
                $newSlug = trim($input['slug']);
                // Verificar conflito de slug com outros apps
                try {
                    $hasConflict = (new General())->count(
                        'workz_apps',
                        'apps',
                        [
                            'slug' => $newSlug,
                            'id' => ['op' => '<>', 'value' => $appId]
                        ]
                    ) > 0;
                    if ($hasConflict) {
                        http_response_code(409);
                        echo json_encode(['success' => false, 'message' => 'Slug já está em uso por outro app']);
                        return;
                    }
                } catch (\Throwable $e) { /* ignore and proceed */ }

                $updateData['slug'] = $newSlug;
            }
            
            if (isset($input['description'])) {
                $updateData['ds'] = trim($input['description']);
            }
            
            if (isset($input['version'])) {
                $updateData['version'] = trim($input['version']);
            }
            
            if (isset($input['color'])) {
                $updateData['color'] = trim($input['color']);
            }
            
            if (isset($input['price'])) {
                $updateData['vl'] = floatval($input['price']);
            }

            // Código do app (genérico - qualquer linguagem)
            if (isset($input['js_code'])) {
                $updateData['js_code'] = $input['js_code'];
                $hasCodeChanges = true;
            }
            
            if (isset($input['dart_code'])) {
                $updateData['dart_code'] = $input['dart_code'];
                $hasCodeChanges = true;
            }

            // Novo: Suporte para múltiplos arquivos (Mini-IDE) + prioridade do textarea para Flutter
            $incomingFiles = [];
            if (isset($input['files']) && is_array($input['files'])) { $incomingFiles = $input['files']; }
            $dcIncoming = isset($input['dart_code']) ? (string)$input['dart_code'] : '';
            if (trim($dcIncoming) === '' && !empty($input['js_code'])) {
                // Heuristic: js_code pode conter Dart/Flutter vindo do App Editor
                $js = (string)$input['js_code'];
                $needle1 = strpos($js, "package:flutter/") !== false;
                $needle2 = preg_match('/void\s+main\s*\(/i', $js) === 1;
                $needle3 = strpos($js, 'MaterialApp') !== false;
                if ($needle1 || ($needle2 && $needle3)) {
                    $dcIncoming = $js;
                }
            }
            if (trim($dcIncoming) !== '') {
                // Força textarea em lib/main.dart
                $incomingFiles['lib/main.dart'] = $dcIncoming;
            }
            if (!empty($incomingFiles)) {
                $updateData['files'] = json_encode($incomingFiles);
                $updateData['storage_type'] = 'filesystem'; // Sinaliza que o app usa o novo formato
                $hasCodeChanges = true;
            }

            // Atualizar no banco (genérico)
            $dbUpdated = false;
            if (!empty($updateData)) {
                $result = $generalModel->update(
                    'workz_apps',
                    'apps',
                    $updateData,
                    ['id' => $appId]
                );

                // Considera dbUpdated como true se a operação de update não falhou (retornou false)
                // Independentemente de ter afetado 0 linhas (dados idênticos).
                // Isso é importante para não bloquear o build se o código for idêntico, mas o frontend enviou.
                if ($result !== false) {
                    $dbUpdated = true;
                    error_log("App $appId: Dados enviados para atualização no banco. Resultado: " . ($result === 0 ? '0 linhas afetadas (dados idênticos)' : "$result linhas afetadas"));
                } else {
                    error_log("App $appId: Falha ao atualizar dados no banco.");
                }
            }

            // Acionar build se houve mudanças no código
            $appCompiled = false; // Flag para indicar se o build foi acionado
            $buildStatus = $existingApp['build_status'] ?? null; // Estado atual/default
            if ($hasCodeChanges) {
                // Buscar dados atualizados
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
                        $isFs = (($updatedApp['storage_type'] ?? 'database') === 'filesystem');
                        // Prefere os arquivos já normalizados com lib/main.dart derivado do textarea
                        if ($isFs) {
                            $outFiles = !empty($incomingFiles) ? $incomingFiles : ($input['files'] ?? []);
                            if (!empty($outFiles)) {
                                $payloadData['files'] = $outFiles;
                                error_log("Enviando múltiplos arquivos para o worker (normalizados).");
                            }
                        }
                        // Sempre que houver código no textarea (dcIncoming), envie também para ter prioridade no worker
                        if (!empty($dcIncoming)) {
                            $payloadData['dart_code'] = $dcIncoming;
                            error_log("Incluindo dart_code de textarea no payload para prioridade.");
                        } elseif (!isset($payloadData['dart_code'])) {
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

            // Resposta
            $message = ($dbUpdated || $hasCodeChanges) ? 'App atualizado com sucesso!' : 'Nenhuma alteração detectada.';
            if ($appCompiled) $message .= ' Build acionado.';

            echo json_encode([
                'success' => true,
                'message' => $message,
                'app_id' => $appId,
                'app_type' => $existingApp['app_type'] ?? 'javascript',
                'db_updated' => $dbUpdated,
                'app_compiled' => $appCompiled,
                'has_code_changes' => $hasCodeChanges,
                'build_status' => $buildStatus
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
                    ['id', 'tt', 'slug', 'ds', 'im', 'color', 'vl', 'st', 'version', 'publisher', 'created_at', 'exclusive_to_entity_id', 'app_type'],
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
}
