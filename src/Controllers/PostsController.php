<?php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Core\Database;
use Workz\Platform\Models\General;
use Workz\Platform\Models\PostMedia;
use Workz\Platform\Services\StorageService;

class PostsController
{
    private General $db;
    private PostMedia $media;

    public function __construct()
    {
        $this->db = new General();
        $this->media = new PostMedia();
    }

    // Cria um post na tabela workz_data.hpl usando o modelo General
    public function create(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        $tp = (string)($input['tp'] ?? '');
        // Escopo: dashboard => cm=0, em=0; entidade => um deles > 0
        $cm = isset($input['cm']) ? (int)$input['cm'] : 0;
        $em = isset($input['em']) ? (int)$input['em'] : 0;
        if ($cm > 0) { $em = 0; }
        if ($em > 0) { $cm = 0; }
        $ct = $input['ct'] ?? null;
        // Privacidade de publicação (0=only me; 1=followers/moderators; 2=logged-in/members; 3=public)
        $postPrivacy = isset($input['post_privacy']) ? (int)$input['post_privacy'] : null;
        if ($postPrivacy !== null) {
            if ($postPrivacy < 0) $postPrivacy = 0;
            if ($postPrivacy > 3) $postPrivacy = 3;
        }

        if ($userId <= 0 || $tp === '' || $ct === null) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields (tp, ct).']);
            return;
        }

        $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
        $tpDb = isset($tpMap[$tp]) ? $tpMap[$tp] : (is_numeric($tp) ? (int)$tp : 0);
        if ($tpDb === 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Invalid post type.']);
            return;
        }

        // ct pode vir como objeto/array ou string JSON; validamos para mídia v2
        $ctData = null;
        if (is_string($ct)) {
            $decoded = json_decode($ct, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $ctData = $decoded;
            }
        } elseif (is_array($ct)) {
            $ctData = $ct;
        }

        $mediaItems = is_array($ctData['media'] ?? null) ? $ctData['media'] : [];
        $mediaIds = [];
        foreach ($mediaItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mediaId = isset($item['media_id']) ? (int)$item['media_id'] : 0;
            if ($mediaId > 0) {
                $mediaIds[] = $mediaId;
            }
        }

        if ($mediaIds) {
            $mediaMap = $this->media->findByIds($mediaIds);
            foreach ($mediaItems as $idx => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $mediaId = isset($item['media_id']) ? (int)$item['media_id'] : 0;
                if ($mediaId <= 0) {
                    continue;
                }
                $row = $mediaMap[$mediaId] ?? null;
                if (!$row) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'Mídia inválida no post.']);
                    return;
                }
                if ((int)($row['us'] ?? 0) !== $userId) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Mídia não pertence ao usuário.']);
                    return;
                }
                if (($row['status'] ?? '') !== 'uploaded') {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'Mídia ainda não finalizada.']);
                    return;
                }
                $rowCm = (int)($row['cm'] ?? 0);
                $rowEm = (int)($row['em'] ?? 0);
                if ($rowCm !== $cm || $rowEm !== $em) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'Mídia fora do escopo do post.']);
                    return;
                }
                $mediaItems[$idx]['type'] = $mediaItems[$idx]['type'] ?? $row['type'];
            }

            if ($ctData === null) {
                $ctData = [];
            }
            $ctData['version'] = 2;
            $ctData['media'] = $mediaItems;
        }

        $ctJson = is_string($ct) ? ($ctData !== null ? json_encode($ctData, JSON_UNESCAPED_UNICODE) : $ct) : json_encode($ctData ?? $ct, JSON_UNESCAPED_UNICODE);

        $data = [
            'us' => $userId,
            'tp' => $tpDb,
            'dt' => (new DateTime())->format('Y-m-d H:i:s'),
            'cm' => $cm,
            'em' => $em,
            'st' => 1,
            'ct' => $ctJson,
        ];
        if ($postPrivacy !== null) { $data['post_privacy'] = $postPrivacy; }

        $id = $this->db->insert('workz_data', 'hpl', $data);
        if ($id) {
            if ($mediaIds) {
                try {
                    $pdo = Database::getInstance('workz_data');
                    $stmt = $pdo->prepare('INSERT INTO hpl_media (post_id, media_id, ord) VALUES (:post_id, :media_id, :ord)');
                    $seen = [];
                    foreach ($mediaItems as $ord => $item) {
                        $mediaId = isset($item['media_id']) ? (int)$item['media_id'] : 0;
                        if ($mediaId <= 0 || isset($seen[$mediaId])) {
                            continue;
                        }
                        $seen[$mediaId] = true;
                        $stmt->execute([
                            'post_id' => $id,
                            'media_id' => $mediaId,
                            'ord' => $ord,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Se a tabela pivô não existir, seguimos sem bloquear a criação do post.
                }
            }
            http_response_code(201);
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create post.']);
        }
    }

    // Lista posts para o feed (padrão: mais recentes)
    public function feed(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $limit = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 20;
        $offset = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;

        $conditions = [];
        if (isset($input['us'])) { $conditions['us'] = (int)$input['us']; }
        if (isset($input['cm'])) { $conditions['cm'] = (string)$input['cm']; }
        if (isset($input['em'])) { $conditions['em'] = (string)$input['em']; }
        if (isset($input['tp'])) {
            $tpIn = $input['tp'];
            $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
            $conditions['tp'] = is_numeric($tpIn) ? (int)$tpIn : ($tpMap[$tpIn] ?? 0);
        }

        $order = ['by' => 'dt', 'dir' => 'DESC'];

        $rows = $this->db->search('workz_data', 'hpl', ['id','us','tp','dt','cm','em','ct','post_privacy'], $conditions, true, $limit, $offset, $order);
        if ($rows === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to load feed.']);
            return;
        }

        $mediaIds = [];
        foreach ($rows as &$row) {
            if (!isset($row['ct']) || !is_string($row['ct'])) {
                continue;
            }
            $decoded = json_decode($row['ct'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                continue;
            }
            $row['ct'] = $decoded;
            $version = isset($decoded['version']) ? (int)$decoded['version'] : 0;
            if ($version < 2) {
                continue;
            }
            $mediaList = is_array($decoded['media'] ?? null) ? $decoded['media'] : [];
            foreach ($mediaList as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $mediaId = isset($item['media_id']) ? (int)$item['media_id'] : 0;
                if ($mediaId > 0) {
                    $mediaIds[] = $mediaId;
                }
            }
        }
        unset($row);

        $mediaMap = $mediaIds ? $this->media->findByIds($mediaIds) : [];
        if ($mediaMap) {
            foreach ($rows as &$row) {
                $ct = $row['ct'] ?? null;
                if (!is_array($ct)) {
                    continue;
                }
                $version = isset($ct['version']) ? (int)$ct['version'] : 0;
                if ($version < 2 || !is_array($ct['media'] ?? null)) {
                    continue;
                }
                $resolved = [];
                foreach ($ct['media'] as $item) {
                    if (!is_array($item)) {
                        $resolved[] = $item;
                        continue;
                    }
                    $mediaId = isset($item['media_id']) ? (int)$item['media_id'] : 0;
                    if ($mediaId <= 0) {
                        $resolved[] = $item;
                        continue;
                    }
                    $rowMedia = $mediaMap[$mediaId] ?? null;
                    if (!$rowMedia) {
                        $resolved[] = $item;
                        continue;
                    }
                    $item['type'] = $item['type'] ?? $rowMedia['type'] ?? null;
                    $item['mimeType'] = $rowMedia['mime'] ?? $rowMedia['mime_type'] ?? null;
                    $item['url'] = $rowMedia['url'] ?? null;
                    if (!$item['url'] || str_starts_with((string)$item['url'], '/uploads/')) {
                        $item['url'] = '/api/media/show/' . $mediaId;
                    }
                    $item['status'] = $rowMedia['status'] ?? null;
                    $resolved[] = $item;
                }
                $row['ct']['media'] = $resolved;
            }
            unset($row);
        }

        echo json_encode([
            'status' => 'success',
            'items' => $rows,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function delete(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $postId = isset($input['id']) ? (int)$input['id'] : 0;
        if ($postId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID inválido.']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        $pdo = Database::getInstance('workz_data');

        $stmt = $pdo->prepare('SELECT id, us, cm, em, ct FROM hpl WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $postId]);
        $post = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$post) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Post não encontrado.']);
            return;
        }

        if (!$this->canDeletePost($post, $userId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Sem permissão para excluir.']);
            return;
        }

        $mediaRows = [];
        $mediaIds = [];
        $hasPivot = $this->tableExists($pdo, 'hpl_media');

        if ($hasPivot) {
            $stmt = $pdo->prepare('SELECT m.id, m.object_key, m.storage_driver, m.mime, m.mime_type
                                   FROM hpl_media hm
                                   INNER JOIN media m ON m.id = hm.media_id
                                   WHERE hm.post_id = :post_id');
            $stmt->execute(['post_id' => $postId]);
            $mediaRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        if (!$mediaRows) {
            $mediaIds = $this->extractMediaIdsFromCt($post['ct'] ?? null);
            if ($mediaIds) {
                $in = implode(',', array_fill(0, count($mediaIds), '?'));
                $stmt = $pdo->prepare("SELECT id, object_key, storage_driver, mime, mime_type FROM media WHERE id IN ($in)");
                $stmt->execute($mediaIds);
                $mediaRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        }

        $mediaIds = array_values(array_unique(array_map(fn($r) => (int)$r['id'], $mediaRows)));

        try {
            $pdo->beginTransaction();

            if ($this->tableExists($pdo, 'hpl_comments')) {
                $stmt = $pdo->prepare('DELETE FROM hpl_comments WHERE pl = :post_id');
                $stmt->execute(['post_id' => $postId]);
            }

            if ($hasPivot) {
                $stmt = $pdo->prepare('DELETE FROM hpl_media WHERE post_id = :post_id');
                $stmt->execute(['post_id' => $postId]);
            }

            $stmt = $pdo->prepare('DELETE FROM hpl WHERE id = :id');
            $stmt->execute(['id' => $postId]);

            if ($mediaIds) {
                foreach ($mediaIds as $mid) {
                    $canDelete = true;
                    if ($hasPivot) {
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM hpl_media WHERE media_id = :mid');
                        $stmt->execute(['mid' => $mid]);
                        $count = (int)$stmt->fetchColumn();
                        $canDelete = ($count === 0);
                    }
                    if ($canDelete) {
                        $stmt = $pdo->prepare('DELETE FROM media WHERE id = :mid');
                        $stmt->execute(['mid' => $mid]);
                    }
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao excluir post.']);
            return;
        }

        $this->cleanupMediaStorage($mediaRows);

        echo json_encode(['status' => 'success', 'id' => $postId]);
    }

    private function extractMediaIdsFromCt($ct): array
    {
        if (!$ct) return [];
        try {
            $obj = is_string($ct) ? json_decode($ct, true) : (is_array($ct) ? $ct : null);
            if (!is_array($obj) || !is_array($obj['media'] ?? null)) return [];
            $ids = [];
            foreach ($obj['media'] as $m) {
                if (!is_array($m)) continue;
                $mid = isset($m['media_id']) ? (int)$m['media_id'] : 0;
                if ($mid > 0) $ids[] = $mid;
            }
            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function cleanupMediaStorage(array $mediaRows): void
    {
        if (!$mediaRows) return;
        $storage = new StorageService();
        $pdo = Database::getInstance('workz_data');
        $hasQueue = $this->tableExists($pdo, 'media_cleanup_queue');

        foreach ($mediaRows as $row) {
            $driver = $row['storage_driver'] ?? 'local';
            $key = $row['object_key'] ?? '';
            if ($key === '') continue;
            try {
                if ($driver === 'oci') {
                    $storage->deleteObject($key);
                } else {
                    $publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
                    $path = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($key, '/'));
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            } catch (\Throwable $e) {
                if ($hasQueue) {
                    $stmt = $pdo->prepare('INSERT INTO media_cleanup_queue (media_id, object_key, storage_driver, attempts, last_error, status, created_at, updated_at)
                                           VALUES (:mid, :key, :driver, 0, :err, :status, NOW(), NOW())');
                    $stmt->execute([
                        'mid' => (int)($row['id'] ?? 0),
                        'key' => $key,
                        'driver' => $driver,
                        'err' => substr($e->getMessage(), 0, 2000),
                        'status' => 'pending',
                    ]);
                } else {
                    error_log('[PostsController::delete] storage cleanup failed: ' . $e->getMessage());
                }
            }
        }
    }

    private function canDeletePost(array $post, int $userId): bool
    {
        $owner = (int)($post['us'] ?? 0);
        if ($owner > 0 && $owner === $userId) return true;

        $cm = (int)($post['cm'] ?? 0);
        $em = (int)($post['em'] ?? 0);

        try {
            $pdo = Database::getInstance('workz_companies');
            if ($cm > 0) {
                $stmt = $pdo->prepare('SELECT nv, st FROM teams_users WHERE us = :us AND cm = :cm LIMIT 1');
                $stmt->execute(['us' => $userId, 'cm' => $cm]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && (int)($row['st'] ?? 0) === 1 && (int)($row['nv'] ?? 0) >= 3) {
                    return true;
                }
            }
            if ($em > 0) {
                $stmt = $pdo->prepare('SELECT nv, st FROM employees WHERE us = :us AND em = :em LIMIT 1');
                $stmt->execute(['us' => $userId, 'em' => $em]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && (int)($row['st'] ?? 0) === 1 && (int)($row['nv'] ?? 0) >= 3) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    private function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :tbl');
            $stmt->execute(['tbl' => $table]);
            return (bool)$stmt->fetch(\PDO::FETCH_NUM);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Upload de mídias (imagens/vídeos) para uso em posts. Aceita múltiplos arquivos.
    public function uploadMedia(?object $payload): void
    {
        header("Content-Type: application/json");
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        // Garante estrutura de arquivos mesmo quando apenas um arquivo é enviado
        $files = $_FILES['files'] ?? $_FILES['file'] ?? null;
        if (!$files) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado. Use o campo files[].']);
            return;
        }

        // Normaliza o array de arquivos (multi ou single)
        $normalized = [];
        if (is_array($files['name'])) {
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$i] ?? 0,
                ];
            }
        } else {
            $normalized[] = $files;
        }

        $maxItems = 10;
        if (count($normalized) > $maxItems) {
            http_response_code(413);
            echo json_encode(['status' => 'error', 'message' => 'Número máximo de arquivos excedido. (máx. 10)']);
            return;
        }

        // Limites e validações
        $maxSize = 64 * 1024 * 1024; // 64MB
        $allowedImages = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];
        $allowedVideos = [
            'video/mp4'  => 'mp4',
            'video/webm' => 'webm',
        ];

        $publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $uploadDir = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível preparar o diretório de uploads.']);
            return;
        }

        $output = [];
        foreach ($normalized as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue; // ignora arquivos inválidos
            }
            if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize) {
                continue;
            }

            // Tentar detectar mime real
            $tmp = $file['tmp_name'];
            $mime = @mime_content_type($tmp) ?: ($file['type'] ?? '');
            $type = '';
            $ext = '';
            if (isset($allowedImages[$mime])) { $type = 'image'; $ext = $allowedImages[$mime]; }
            elseif (isset($allowedVideos[$mime])) { $type = 'video'; $ext = $allowedVideos[$mime]; }
            else { continue; }

            $unique = uniqid('post_', true);
            $filename = $unique . '.' . $ext;
            $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($tmp, $target)) {
                continue;
            }

            $relative = '/uploads/posts/' . $filename;
            $descriptor = [
                'type' => $type,
                'url' => $relative,
                'path' => ltrim($relative, '/'),
                'originalName' => $file['name'] ?? $filename,
                'size' => (int)($file['size'] ?? 0),
                'mimeType' => $mime,
            ];
            // Enriquecer metadados de imagem
            if ($type === 'image') {
                $info = @getimagesize($target);
                if ($info) {
                    $descriptor['w'] = $info[0] ?? null;
                    $descriptor['h'] = $info[1] ?? null;
                }
            }
            $output[] = $descriptor;
        }

        if (!$output) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Nenhuma mídia válida foi enviada.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'media' => $output,
            'count' => count($output),
        ]);
    }
}
