<?php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Models\General;

class PostsController
{
    private General $db;

    public function __construct()
    {
        $this->db = new General();
    }

    // Cria um post na tabela workz_data.hpl usando o modelo General
    public function create(?object $payload): void
    {
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

        // ct pode vir como objeto/array ou string JSON; sempre persistimos como string JSON
        $ctJson = is_string($ct) ? $ct : json_encode($ct, JSON_UNESCAPED_UNICODE);

        $data = [
            'us' => $userId,
            'tp' => $tpDb,
            'dt' => (new DateTime())->format('Y-m-d H:i:s'),
            'cm' => $cm,
            'em' => $em,
            'st' => 1,
            'ct' => $ctJson,
        ];

        $id = $this->db->insert('workz_data', 'hpl', $data);
        if ($id) {
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

        $rows = $this->db->search('workz_data', 'hpl', ['id','us','tp','dt','cm','em','ct'], $conditions, true, $limit, $offset, $order);
        if ($rows === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to load feed.']);
            return;
        }

        // Decodificar ct JSON por conveniência
        foreach ($rows as &$row) {
            if (isset($row['ct']) && is_string($row['ct'])) {
                $decoded = json_decode($row['ct'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['ct'] = $decoded;
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'items' => $rows,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    // Upload de mídias (imagens/vídeos) para uso em posts. Aceita múltiplos arquivos.
    public function uploadMedia(?object $payload): void
    {
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
