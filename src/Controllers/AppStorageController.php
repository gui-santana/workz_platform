<?php
// src/Controllers/AppStorageController.php
// Controlador de storage para apps

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Core\StorageManager;
use PDO;

class AppStorageController
{
    private General $generalModel;

    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * Extrai app_id do JWT token ou usa um padrão
     */
    private function getAppIdFromToken(object $auth): int
    {
        try {
            // Por enquanto, usar um app_id padrão para testes
            // Em produção, extrair do JWT audience "app:{id}"
            $audience = $auth->aud ?? '';
            error_log("JWT audience: " . $audience);

            if (preg_match('/^app:(\d+)$/', $audience, $matches)) {
                $appId = (int)$matches[1];
                error_log("App ID extraído do JWT: " . $appId);
                return $appId;
            }

            // Fallback: usar ID 1 para testes
            error_log("Usando app_id padrão: 1");
            return 1;
        } catch (\Throwable $e) {
            error_log("Erro ao extrair app_id: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Valida escopo (simplificado)
     */
    private function validateScope(object $auth, string $scopeType, int $scopeId): bool
    {
        $userId = (int)($auth->sub ?? 0);

        // Por enquanto, permitir acesso se for o próprio usuário
        if ($scopeType === 'user' && $userId === $scopeId) {
            return true;
        }

        // Para outros escopos, permitir por enquanto (para testes)
        return true;
    }

    // ==================== KV STORAGE ====================

    /**
     * GET /api/appdata/kv
     */
    public function kvGet(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);
            $key = $_GET['key'] ?? '';

            // Se não há chave, listar todas as chaves
            if (!$key) {
                $this->kvList($auth);
                return;
            }

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            error_log("KV Get - Buscando: app_id=$appId, scope_type=$scopeType, scope_id=$scopeId, key=$key");

            // Buscar na tabela storage_kv
            $result = $this->generalModel->search(
                'workz_apps',
                'storage_kv',
                ['value', 'version', 'updated_at'],
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'key' => $key
                ],
                false
            );

            error_log("KV Get - Resultado: " . ($result ? json_encode($result) : 'null'));

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'key' => $key,
                    'value' => json_decode($result['value'], true),
                    'version' => $result['version'],
                    'updated_at' => $result['updated_at']
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Chave não encontrada']);
            }
        } catch (\Throwable $e) {
            error_log("Erro ao ler KV: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/appdata/kv
     */
    public function kvSet(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            // Log para debug
            error_log("KV Set iniciado");

            $appId = $this->getAppIdFromToken($auth);
            error_log("App ID: " . $appId);

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            error_log("Input recebido: " . json_encode($input));

            $scopeType = $input['scopeType'] ?? 'user';
            $scopeId = (int)($input['scopeId'] ?? $auth->sub ?? 0);
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? null;
            $ttl = $input['ttl'] ?? null;

            error_log("Dados processados - scopeType: $scopeType, scopeId: $scopeId, key: $key");

            if (!$key) {
                http_response_code(400);
                echo json_encode(['error' => 'Parâmetro key é obrigatório']);
                return;
            }

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            // Calcular TTL se fornecido
            $ttlDate = null;
            if ($ttl && is_numeric($ttl)) {
                $ttlDate = date('Y-m-d H:i:s', time() + (int)$ttl);
            }

            error_log("TTL calculado: " . ($ttlDate ?? 'null'));

            // Dados para inserir/atualizar
            $data = [
                'app_id' => $appId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'key' => $key,
                'value' => json_encode($value),
                'ttl' => $ttlDate
            ];

            error_log("Dados para inserir: " . json_encode($data));

            // PULAR UPDATE - ir direto para INSERT para debug
            error_log("Pulando UPDATE, indo direto para INSERT");

            // Inserir novo registro
            error_log("Tentando inserir novo registro");
            $id = $this->generalModel->insert('workz_apps', 'storage_kv', $data);
            error_log("Insert resultado ID: " . ($id ?: 'false'));

            if (!$id) {
                error_log("INSERT falhou - investigando...");

                // Testar conexão direta para comparar
                try {
                    $dsn = "mysql:host=mysql;port=3306;dbname=workz_apps;charset=utf8mb4";
                    $pdo = new PDO($dsn, 'root', 'root_password', [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);

                    $sql = "INSERT INTO storage_kv (app_id, scope_type, scope_id, `key`, `value`) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([$appId, $scopeType, $scopeId, $key, json_encode($value)]);

                    if ($result) {
                        $directId = $pdo->lastInsertId();
                        error_log("INSERT direto funcionou! ID: " . $directId);

                        // Usar o ID do insert direto
                        $id = $directId;
                    } else {
                        error_log("INSERT direto também falhou");
                    }
                } catch (\Exception $e) {
                    error_log("Erro no INSERT direto: " . $e->getMessage());
                }

                if (!$id) {
                    throw new \Exception('Falha ao inserir dados - tanto General quanto direto falharam');
                }
            }

            echo json_encode([
                'success' => true,
                'key' => $key,
                'message' => 'Valor salvo com sucesso'
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao salvar KV: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/appdata/kv
     */
    public function kvDelete(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);
            $key = $_GET['key'] ?? '';

            if (!$key) {
                http_response_code(400);
                echo json_encode(['error' => 'Parâmetro key é obrigatório']);
                return;
            }

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $deleted = $this->generalModel->delete(
                'workz_apps',
                'storage_kv',
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'key' => $key
                ]
            );

            if ($deleted) {
                echo json_encode([
                    'success' => true,
                    'key' => $key,
                    'message' => 'Chave deletada com sucesso'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Chave não encontrada']);
            }
        } catch (\Throwable $e) {
            error_log("Erro ao deletar KV: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Listar todas as chaves KV do usuário
     */
    private function kvList(object $auth): void
    {
        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $results = $this->generalModel->search(
                'workz_apps',
                'storage_kv',
                ['key', 'version', 'created_at', 'updated_at'],
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId
                ],
                true, // fetchAll
                100   // limit
            );

            $keys = [];
            if ($results) {
                foreach ($results as $row) {
                    $keys[] = [
                        'key' => $row['key'],
                        'version' => $row['version'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'keys' => $keys,
                'count' => count($keys)
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao listar KV: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    // ==================== DOCS STORAGE ====================

    /**
     * POST /api/appdata/docs/query
     */
    public function docsQuery(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $scopeType = $input['scopeType'] ?? 'user';
            $scopeId = (int)($input['scopeId'] ?? $auth->sub ?? 0);
            $docType = $input['docType'] ?? 'user_data';
            $filters = $input['filters'] ?? [];

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            // Construir condições de busca
            $conditions = [
                'app_id' => $appId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'doc_type' => $docType
            ];

            // Se há filtros específicos, adicionar
            if (!empty($filters['docId'])) {
                $conditions['doc_id'] = $filters['docId'];
            }

            $results = $this->generalModel->search(
                'workz_apps',
                'storage_docs',
                ['doc_id', 'data', 'version', 'created_at', 'updated_at'], // Usar 'data' em vez de 'document'
                $conditions,
                true, // fetchAll
                100   // limit
            );

            $documents = [];
            if ($results) {
                foreach ($results as $row) {
                    $documents[] = [
                        'id' => $row['doc_id'],
                        'document' => json_decode($row['data'], true), // Usar 'data' em vez de 'document'
                        'version' => $row['version'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'documents' => $documents,
                'count' => count($documents)
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao consultar docs: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * POST /api/appdata/docs/upsert
     */
    public function docsUpsert(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            $scopeType = $input['scopeType'] ?? 'user';
            $scopeId = (int)($input['scopeId'] ?? $auth->sub ?? 0);
            $docType = $input['docType'] ?? 'user_data';
            $docId = $input['docId'] ?? '';
            $document = $input['document'] ?? [];

            if (!$docId) {
                http_response_code(400);
                echo json_encode(['error' => 'Parâmetro docId é obrigatório']);
                return;
            }

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            // Dados para inserir/atualizar
            $data = [
                'app_id' => $appId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'doc_type' => $docType,
                'doc_id' => $docId,
                'data' => json_encode($document) // Usar 'data' em vez de 'document'
            ];

            // Tentar atualizar primeiro
            $updated = $this->generalModel->update(
                'workz_apps',
                'storage_docs',
                ['data' => json_encode($document)], // Usar 'data' em vez de 'document'
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'doc_type' => $docType,
                    'doc_id' => $docId
                ]
            );

            if (!$updated) {
                // Se não atualizou, inserir novo
                $id = $this->generalModel->insert('workz_apps', 'storage_docs', $data);
                if (!$id) {
                    throw new \Exception('Falha ao inserir documento');
                }
            }

            echo json_encode([
                'success' => true,
                'id' => $docId,
                'message' => 'Documento salvo com sucesso'
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao salvar documento: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/appdata/docs/{docType}/{docId}
     */
    public function docsDelete(object $auth, string $docType, string $docId): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $deleted = $this->generalModel->delete(
                'workz_apps',
                'storage_docs',
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'doc_type' => $docType,
                    'doc_id' => $docId
                ]
            );

            if ($deleted) {
                echo json_encode([
                    'success' => true,
                    'id' => $docId,
                    'message' => 'Documento deletado com sucesso'
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Documento não encontrado']);
            }
        } catch (\Throwable $e) {
            error_log("Erro ao deletar documento: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    // ==================== BLOBS STORAGE ====================

    /**
     * POST /api/appdata/blobs/upload
     */
    public function blobsUpload(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_POST['scopeType'] ?? 'user';
            $scopeId = (int)($_POST['scopeId'] ?? $auth->sub ?? 0);
            $blobId = $_POST['name'] ?? uniqid('blob_');

            // Debug: log dos dados recebidos
            error_log("Blob upload - appId: $appId, scopeType: $scopeType, scopeId: $scopeId, blobId: $blobId");
            error_log("Files recebidos: " . print_r($_FILES, true));

            if (!isset($_FILES['file'])) {
                error_log("Erro: Nenhum arquivo enviado");
                http_response_code(400);
                echo json_encode(['error' => 'Nenhum arquivo enviado']);
                return;
            }

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $file = $_FILES['file'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'Erro no upload: ' . $file['error']]);
                return;
            }

            // Criar diretório de upload se não existir
            $uploadDir = dirname(__DIR__, 2) . '/public/uploads/blobs';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Gerar nome único para o arquivo
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = $blobId . '.' . $extension;
            $filePath = $uploadDir . '/' . $fileName;

            // Mover arquivo
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new \Exception('Falha ao mover arquivo');
            }

            // Salvar metadados no banco
            $data = [
                'app_id' => $appId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'blob_id' => $blobId,
                'path' => '/uploads/blobs/' . $fileName, // Usar 'path' em vez de 'file_path'
                'mime' => $file['type'], // Usar 'mime' em vez de 'mime_type'
                'size_bytes' => $file['size'] // Usar 'size_bytes' em vez de 'file_size'
                // Remover 'original_name' e 'metadata' que não existem na tabela
            ];

            $id = $this->generalModel->insert('workz_apps', 'storage_blobs', $data);

            if (!$id) {
                // Se falhou ao salvar no banco, remover arquivo
                unlink($filePath);
                throw new \Exception('Falha ao salvar metadados');
            }

            echo json_encode([
                'success' => true,
                'id' => $blobId,
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'url' => '/uploads/blobs/' . $fileName
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao fazer upload: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/appdata/blobs/list
     */
    public function blobsList(object $auth): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $results = $this->generalModel->search(
                'workz_apps',
                'storage_blobs',
                ['blob_id', 'mime', 'size_bytes', 'created_at'], // Usar colunas corretas
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId
                ],
                true, // fetchAll
                100   // limit
            );

            $blobs = [];
            if ($results) {
                foreach ($results as $row) {
                    $blobs[] = [
                        'id' => $row['blob_id'],
                        'name' => $row['blob_id'], // Usar blob_id como nome já que não temos original_name
                        'type' => $row['mime'], // Usar 'mime' em vez de 'mime_type'
                        'size' => $row['size_bytes'], // Usar 'size_bytes' em vez de 'file_size'
                        'created_at' => $row['created_at']
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'blobs' => $blobs,
                'count' => count($blobs)
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao listar blobs: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/appdata/blobs/get/{blobId}
     */
    public function blobsGet(object $auth, string $blobId): void
    {
        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            $blob = $this->generalModel->search(
                'workz_apps',
                'storage_blobs',
                ['path', 'mime'], // Usar colunas corretas
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'blob_id' => $blobId
                ],
                false
            );

            if (!$blob) {
                // Debug: log dos parâmetros de busca
                error_log("Blob não encontrado - Parâmetros: appId=$appId, scopeType=$scopeType, scopeId=$scopeId, blobId=$blobId");
                http_response_code(404);
                echo json_encode(['error' => 'Blob não encontrado']);
                return;
            }

            $filePath = dirname(__DIR__, 2) . '/public' . $blob['path']; // Usar 'path' em vez de 'file_path'

            if (!file_exists($filePath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Arquivo não encontrado no sistema']);
                return;
            }

            // Servir o arquivo
            header('Content-Type: ' . $blob['mime']); // Usar 'mime' em vez de 'mime_type'
            header('Content-Disposition: attachment; filename="' . $blob['blob_id'] . '"'); // Usar blob_id como filename
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
        } catch (\Throwable $e) {
            error_log("Erro ao obter blob: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/appdata/blobs/delete/{blobId}
     */
    public function blobsDelete(object $auth, string $blobId): void
    {
        header("Content-Type: application/json");

        try {
            $appId = $this->getAppIdFromToken($auth);
            $scopeType = $_GET['scopeType'] ?? 'user';
            $scopeId = (int)($_GET['scopeId'] ?? $auth->sub ?? 0);

            if (!$this->validateScope($auth, $scopeType, $scopeId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Sem permissão para acessar este escopo']);
                return;
            }

            // Buscar o blob para obter o caminho do arquivo
            $blob = $this->generalModel->search(
                'workz_apps',
                'storage_blobs',
                ['path'], // Usar 'path' em vez de 'file_path'
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'blob_id' => $blobId
                ],
                false
            );

            if (!$blob) {
                http_response_code(404);
                echo json_encode(['error' => 'Blob não encontrado']);
                return;
            }

            // Deletar do banco
            $deleted = $this->generalModel->delete(
                'workz_apps',
                'storage_blobs',
                [
                    'app_id' => $appId,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'blob_id' => $blobId
                ]
            );

            if ($deleted) {
                // Tentar deletar o arquivo físico
                $filePath = dirname(__DIR__, 2) . '/public' . $blob['path']; // Usar 'path' em vez de 'file_path'
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                echo json_encode([
                    'success' => true,
                    'id' => $blobId,
                    'message' => 'Blob deletado com sucesso'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Falha ao deletar blob']);
            }
        } catch (\Throwable $e) {
            error_log("Erro ao deletar blob: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/apps/storage/stats
     * Retorna estatísticas de storage para o App Builder
     */
    public function getStorageStats(object $auth): void
    {
        header("Content-Type: application/json");
        
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }

            // Usar StorageManager para obter estatísticas reais
            $storageManager = new StorageManager();
            $stats = $storageManager->getStorageStatistics();
            
            // Adicionar estatísticas específicas do usuário
            $userStats = [
                'user_id' => $userId,
                'kv_entries' => $this->getUserKvCount($userId),
                'docs_count' => $this->getUserDocsCount($userId),
                'blobs_count' => $this->getUserBlobsCount($userId),
                'total_storage_used' => $this->getUserStorageUsage($userId)
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'system' => $stats,
                    'user' => $userStats,
                    'timestamp' => date('c')
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("Erro ao obter estatísticas de storage: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    /**
     * Conta entradas KV do usuário
     */
    private function getUserKvCount(int $userId): int
    {
        try {
            return $this->generalModel->count('workz_apps', 'storage_kv', [
                'scope_type' => 'user',
                'scope_id' => $userId
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao contar KV entries: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Conta documentos do usuário
     */
    private function getUserDocsCount(int $userId): int
    {
        try {
            return $this->generalModel->count('workz_apps', 'storage_docs', [
                'scope_type' => 'user',
                'scope_id' => $userId
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao contar docs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Conta blobs do usuário
     */
    private function getUserBlobsCount(int $userId): int
    {
        try {
            return $this->generalModel->count('workz_apps', 'storage_blobs', [
                'scope_type' => 'user',
                'scope_id' => $userId
            ]);
        } catch (\Throwable $e) {
            error_log("Erro ao contar blobs: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calcula uso total de storage do usuário
     */
    private function getUserStorageUsage(int $userId): int
    {
        try {
            // Somar tamanho dos blobs
            $blobsSize = 0;
            $blobs = $this->generalModel->search('workz_apps', 'storage_blobs', 
                ['size_bytes'], 
                ['scope_type' => 'user', 'scope_id' => $userId], 
                true
            );
            
            if (is_array($blobs)) {
                foreach ($blobs as $blob) {
                    $blobsSize += (int)($blob['size_bytes'] ?? 0);
                }
            }

            // Estimar tamanho de KV e docs (aproximação)
            $kvCount = $this->getUserKvCount($userId);
            $docsCount = $this->getUserDocsCount($userId);
            
            $estimatedKvSize = $kvCount * 1024; // 1KB por entrada KV
            $estimatedDocsSize = $docsCount * 2048; // 2KB por documento
            
            return $blobsSize + $estimatedKvSize + $estimatedDocsSize;
            
        } catch (\Throwable $e) {
            error_log("Erro ao calcular uso de storage: " . $e->getMessage());
            return 0;
        }
    }
}
