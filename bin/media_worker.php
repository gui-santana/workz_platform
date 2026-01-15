#!/usr/bin/env php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Workz\Platform\Models\Media;
use Workz\Platform\Services\StorageService;

$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->load();
}

$mediaModel = new Media();
$storage = new StorageService();

$rawDir = $_ENV['MEDIA_RAW_DIR'] ?? '/var/app/uploads/raw';
$tmpRoot = $_ENV['MEDIA_TMP_DIR'] ?? '/var/app/uploads/tmp';
$batchSize = (int)($_ENV['MEDIA_WORKER_BATCH'] ?? 3);

$items = $mediaModel->fetchPending($batchSize);
if (!$items) {
    echo "No media items to process.\n";
    exit(0);
}

foreach ($items as $item) {
    $mediaId = (int)$item['id'];
    $now = (new DateTime())->format('Y-m-d H:i:s');

    try {
        $rawPath = rtrim($rawDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $mediaId . '.orig';
        if (!is_file($rawPath)) {
            throw new RuntimeException('Arquivo bruto nao encontrado.');
        }

        $tmpDir = rtrim($tmpRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $mediaId;
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true)) {
            throw new RuntimeException('Falha ao preparar diretorio temporario.');
        }

        $createdAt = $item['created_at'] ?? $now;
        $dt = new DateTime($createdAt);
        $prefix = $dt->format('Y/m');

        if ($item['type'] === 'video') {
            $result = processVideo($rawPath, $tmpDir);
            $videoBase = 'videos/' . $prefix . '/' . $mediaId;
            $thumbBase = 'thumbs/' . $prefix . '/' . $mediaId;

            $objectKeys = [
                '480p' => $videoBase . '/480p.mp4',
            ];

            $thumbKeys = [
                'poster' => $thumbBase . '/poster.jpg',
                'thumbs' => [
                    $thumbBase . '/thumb_1.jpg',
                    $thumbBase . '/thumb_2.jpg',
                    $thumbBase . '/thumb_3.jpg',
                ],
            ];

            $storage->putObject($objectKeys['480p'], $result['file_480p'], 'video/mp4');
            $storage->putObject($thumbKeys['poster'], $result['poster'], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][0], $result['thumbs'][0], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][1], $result['thumbs'][1], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][2], $result['thumbs'][2], 'image/jpeg');

            $mediaModel->update($mediaId, [
                'status' => 'ready',
                'object_keys' => json_encode($objectKeys, JSON_UNESCAPED_SLASHES),
                'thumb_keys' => json_encode($thumbKeys, JSON_UNESCAPED_SLASHES),
                'duration_seconds' => $result['duration'],
                'width' => $result['width'],
                'height' => $result['height'],
                'processed_at' => $now,
                'updated_at' => $now,
                'error_message' => null,
            ]);
        } else {
            $result = processImage($rawPath, $tmpDir, $item['mime_type'] ?? 'image/jpeg');
            $imageBase = 'images/' . $prefix . '/' . $mediaId;
            $thumbBase = 'thumbs/' . $prefix . '/' . $mediaId;

            $objectKeys = [
                'original' => $imageBase . '/original.' . $result['ext'],
            ];
            $thumbKeys = [
                'poster' => $thumbBase . '/poster.jpg',
                'thumbs' => [
                    $thumbBase . '/thumb_1.jpg',
                    $thumbBase . '/thumb_2.jpg',
                    $thumbBase . '/thumb_3.jpg',
                ],
            ];

            $storage->putObject($objectKeys['original'], $result['original'], $result['content_type']);
            $storage->putObject($thumbKeys['poster'], $result['poster'], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][0], $result['thumbs'][0], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][1], $result['thumbs'][1], 'image/jpeg');
            $storage->putObject($thumbKeys['thumbs'][2], $result['thumbs'][2], 'image/jpeg');

            $mediaModel->update($mediaId, [
                'status' => 'ready',
                'object_keys' => json_encode($objectKeys, JSON_UNESCAPED_SLASHES),
                'thumb_keys' => json_encode($thumbKeys, JSON_UNESCAPED_SLASHES),
                'width' => $result['width'],
                'height' => $result['height'],
                'processed_at' => $now,
                'updated_at' => $now,
                'error_message' => null,
            ]);
        }

        cleanupPath($rawPath);
        cleanupPath($tmpDir, true);

        echo "Processed media {$mediaId}.\n";
    } catch (Throwable $e) {
        $mediaModel->update($mediaId, [
            'status' => 'error',
            'error_message' => substr($e->getMessage(), 0, 2000),
            'updated_at' => $now,
        ]);
        cleanupPath($tmpDir ?? null, true);
        echo "Failed media {$mediaId}: {$e->getMessage()}\n";
    }
}

function processVideo(string $input, string $tmpDir): array
{
    $probe = probeVideo($input);
    if (!$probe) {
        throw new RuntimeException('Falha ao ler metadados de video.');
    }

    $file480p = $tmpDir . DIRECTORY_SEPARATOR . '480p.mp4';
    $cmd480 = buildFfmpegCommand($input, 480, $file480p, 800);
    runCommand($cmd480, 'Falha ao gerar 480p.mp4');

    $poster = $tmpDir . DIRECTORY_SEPARATOR . 'poster.jpg';
    $thumb1 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_1.jpg';
    $thumb2 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_2.jpg';
    $thumb3 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_3.jpg';

    $duration = max(1.0, (float)$probe['duration']);
    $times = [
        max(1.0, min($duration - 1.0, $duration * 0.1)),
        max(1.0, min($duration - 1.0, $duration * 0.5)),
        max(1.0, min($duration - 1.0, $duration * 0.9)),
    ];

    runCommand(buildThumbnailCommand($input, $times[1], 1280, $poster), 'Falha ao gerar poster');
    runCommand(buildThumbnailCommand($input, $times[0], 320, $thumb1), 'Falha ao gerar thumb 1');
    runCommand(buildThumbnailCommand($input, $times[1], 320, $thumb2), 'Falha ao gerar thumb 2');
    runCommand(buildThumbnailCommand($input, $times[2], 320, $thumb3), 'Falha ao gerar thumb 3');

    return [
        'file_480p' => $file480p,
        'poster' => $poster,
        'thumbs' => [$thumb1, $thumb2, $thumb3],
        'duration' => $probe['duration'],
        'width' => $probe['width'],
        'height' => $probe['height'],
    ];
}

function processImage(string $input, string $tmpDir, string $mimeType): array
{
    $info = @getimagesize($input);
    if (!$info) {
        throw new RuntimeException('Falha ao ler imagem.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mimeType] ?? 'jpg';
    $contentType = $mimeType;

    $original = $tmpDir . DIRECTORY_SEPARATOR . 'original.' . $ext;
    if (!copy($input, $original)) {
        throw new RuntimeException('Falha ao copiar imagem original.');
    }

    $poster = $tmpDir . DIRECTORY_SEPARATOR . 'poster.jpg';
    $thumb1 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_1.jpg';
    $thumb2 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_2.jpg';
    $thumb3 = $tmpDir . DIRECTORY_SEPARATOR . 'thumb_3.jpg';

    runCommand(buildImageResizeCommand($input, 1280, $poster), 'Falha ao gerar poster');
    runCommand(buildImageResizeCommand($input, 320, $thumb1), 'Falha ao gerar thumb 1');
    runCommand(buildImageResizeCommand($input, 320, $thumb2), 'Falha ao gerar thumb 2');
    runCommand(buildImageResizeCommand($input, 320, $thumb3), 'Falha ao gerar thumb 3');

    return [
        'original' => $original,
        'poster' => $poster,
        'thumbs' => [$thumb1, $thumb2, $thumb3],
        'width' => $info[0] ?? null,
        'height' => $info[1] ?? null,
        'ext' => $ext,
        'content_type' => $contentType,
    ];
}

function probeVideo(string $file): ?array
{
    $cmd = sprintf(
        'ffprobe -v error -select_streams v:0 -show_entries stream=width,height -show_entries format=duration -of json %s 2>/dev/null',
        escapeshellarg($file)
    );
    $output = shell_exec($cmd);
    if (!$output) {
        return null;
    }

    $json = json_decode($output, true);
    if (!$json) {
        return null;
    }

    $width = (int)($json['streams'][0]['width'] ?? 0);
    $height = (int)($json['streams'][0]['height'] ?? 0);
    $duration = (float)($json['format']['duration'] ?? 0);

    return [
        'width' => $width > 0 ? $width : null,
        'height' => $height > 0 ? $height : null,
        'duration' => $duration > 0 ? $duration : null,
    ];
}

function buildFfmpegCommand(string $input, int $height, string $output, int $videoBitrateK): string
{
    $scale = 'scale=-2:' . $height;
    $forceKeyframes = 'expr:gte(t,n_forced*2)';

    return sprintf(
        'ffmpeg -y -i %s -vf %s -c:v libx264 -preset fast -profile:v main -level 3.1 -pix_fmt yuv420p -b:v %dk -maxrate %dk -bufsize %dk -g 60 -keyint_min 60 -sc_threshold 0 -force_key_frames %s -c:a aac -b:a 128k -ac 2 -af loudnorm -movflags +faststart %s 2>/dev/null',
        escapeshellarg($input),
        escapeshellarg($scale),
        $videoBitrateK,
        (int)($videoBitrateK * 1.2),
        (int)($videoBitrateK * 1.6),
        escapeshellarg($forceKeyframes),
        escapeshellarg($output)
    );
}

function buildThumbnailCommand(string $input, float $time, int $width, string $output): string
{
    $scale = 'scale=' . $width . ':-2';
    return sprintf(
        'ffmpeg -y -ss %s -i %s -vframes 1 -vf %s -q:v 2 %s 2>/dev/null',
        escapeshellarg((string)$time),
        escapeshellarg($input),
        escapeshellarg($scale),
        escapeshellarg($output)
    );
}

function buildImageResizeCommand(string $input, int $width, string $output): string
{
    $scale = 'scale=' . $width . ':-2';
    return sprintf(
        'ffmpeg -y -i %s -vframes 1 -vf %s -q:v 2 %s 2>/dev/null',
        escapeshellarg($input),
        escapeshellarg($scale),
        escapeshellarg($output)
    );
}

function runCommand(string $cmd, string $errorMessage): void
{
    exec($cmd, $output, $code);
    if ($code !== 0) {
        throw new RuntimeException($errorMessage);
    }
}

function cleanupPath(?string $path, bool $recursive = false): void
{
    if (!$path) {
        return;
    }

    if (is_file($path)) {
        @unlink($path);
        return;
    }

    if ($recursive && is_dir($path)) {
        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $full = $path . DIRECTORY_SEPARATOR . $item;
            cleanupPath($full, true);
        }
        @rmdir($path);
    }
}
