<?php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Models\Media;
use Workz\Platform\Services\StorageService;

class MediaController
{
    private Media $media;

    public function __construct()
    {
        $this->media = new Media();
    }

    public function upload(?object $payload): void
    {
        header('Content-Type: application/json');
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $file = $_FILES['file'] ?? $_FILES['files'] ?? null;
        if (!$file) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado. Use o campo file.']);
            return;
        }

        $normalized = [];
        if (is_array($file['name'])) {
            $count = count($file['name']);
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $file['name'][$i],
                    'type' => $file['type'][$i] ?? '',
                    'tmp_name' => $file['tmp_name'][$i] ?? '',
                    'error' => $file['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $file['size'][$i] ?? 0,
                ];
            }
        } else {
            $normalized[] = $file;
        }

        if (count($normalized) > 1) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Envie apenas um arquivo por requisicao.']);
            return;
        }

        $item = $normalized[0];
        if (($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falha no upload do arquivo.']);
            return;
        }

        $maxSizeMb = (int)($_ENV['MEDIA_MAX_SIZE_MB'] ?? 128);
        $maxSize = $maxSizeMb * 1024 * 1024;
        if (($item['size'] ?? 0) <= 0 || ($item['size'] ?? 0) > $maxSize) {
            http_response_code(413);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo excede o tamanho maximo.']);
            return;
        }

        $tmp = $item['tmp_name'] ?? '';
        $mime = @mime_content_type($tmp) ?: ($item['type'] ?? '');
        $allowedImages = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $allowedVideos = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];

        $type = '';
        if (isset($allowedImages[$mime])) {
            $type = 'image';
        } elseif (isset($allowedVideos[$mime])) {
            $type = 'video';
        }

        if ($type === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de arquivo nao suportado.']);
            return;
        }

        $duration = null;
        if ($type === 'video') {
            $duration = $this->probeDuration($tmp);
            $maxDuration = (int)($_ENV['MEDIA_MAX_VIDEO_SECONDS'] ?? 60);
            if ($duration === null || $duration > $maxDuration) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Duracao maxima excedida.']);
                return;
            }
        }

        $now = (new DateTime())->format('Y-m-d H:i:s');
        $mediaId = $this->media->insert([
            'user_id' => (int)($payload->sub ?? 0),
            'status' => 'processing',
            'type' => $type,
            'mime_type' => $mime,
            'original_name' => $item['name'] ?? '',
            'size_bytes' => (int)($item['size'] ?? 0),
            'duration_seconds' => $duration,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$mediaId) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Nao foi possivel criar o registro de midia.']);
            return;
        }

        $rawDir = $_ENV['MEDIA_RAW_DIR'] ?? '/var/app/uploads/raw';
        if (!is_dir($rawDir) && !mkdir($rawDir, 0755, true)) {
            $this->media->update($mediaId, [
                'status' => 'error',
                'error_message' => 'Falha ao preparar diretorio de upload.',
                'updated_at' => $now,
            ]);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Nao foi possivel salvar o arquivo.']);
            return;
        }

        $target = rtrim($rawDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $mediaId . '.orig';
        if (!move_uploaded_file($tmp, $target)) {
            $this->media->update($mediaId, [
                'status' => 'error',
                'error_message' => 'Falha ao mover o arquivo para processamento.',
                'updated_at' => $now,
            ]);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Nao foi possivel salvar o arquivo.']);
            return;
        }

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'media' => [
                'id' => $mediaId,
                'status' => 'processing',
                'type' => $type,
                'mimeType' => $mime,
                'originalName' => $item['name'] ?? '',
                'size' => (int)($item['size'] ?? 0),
            ],
        ]);
    }

    public function show(?object $payload, int $mediaId): void
    {
        header('Content-Type: application/json');
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $media = $this->media->findById($mediaId);
        if (!$media) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Midia nao encontrada.']);
            return;
        }

        $storage = new StorageService();
        $objectKeys = $media['object_keys'] ? json_decode($media['object_keys'], true) : [];
        $thumbKeys = $media['thumb_keys'] ? json_decode($media['thumb_keys'], true) : [];

        $playbackKey = $objectKeys['480p'] ?? ($objectKeys['original'] ?? null);
        $thumbKey = $thumbKeys['poster'] ?? null;
        $urlTtl = (int)($_ENV['MEDIA_URL_TTL_SECONDS'] ?? 3600);

        $playbackUrl = $playbackKey && $media['status'] === 'ready' ? $storage->presignGet($playbackKey, $urlTtl) : null;
        $thumbUrl = $thumbKey && $media['status'] === 'ready' ? $storage->presignGet($thumbKey, $urlTtl) : null;

        echo json_encode([
            'status' => 'success',
            'media' => [
                'id' => (int)$media['id'],
                'status' => $media['status'],
                'type' => $media['type'],
                'mimeType' => $media['mime_type'],
                'duration' => $media['duration_seconds'] !== null ? (float)$media['duration_seconds'] : null,
                'width' => $media['width'] !== null ? (int)$media['width'] : null,
                'height' => $media['height'] !== null ? (int)$media['height'] : null,
                'video_url' => $playbackUrl,
                'poster_url' => $thumbUrl,
                'object_keys' => $objectKeys,
                'thumb_keys' => $thumbKeys,
                'error_message' => $media['error_message'],
            ],
        ]);
    }

    private function probeDuration(string $file): ?float
    {
        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($file)
        );
        $output = trim((string)shell_exec($cmd));
        if ($output === '') {
            return null;
        }
        $value = (float)$output;
        return $value > 0 ? $value : null;
    }
}
