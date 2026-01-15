<?php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Core\Database;
use Workz\Platform\Models\PostMedia;
use Workz\Platform\Services\StorageService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PostMediaController
{
    private PostMedia $media;
    private string $dbName;

    public function __construct()
    {
        $this->media = new PostMedia();
        $this->dbName = $_ENV['MEDIA_DB_NAME'] ?? 'workz_data';
    }

    public function init(?object $payload): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        $type = strtolower((string)($input['type'] ?? ''));
        $mime = (string)($input['mime'] ?? '');
        $size = isset($input['size']) ? (int)$input['size'] : null;
        $cm = isset($input['cm']) ? (int)$input['cm'] : 0;
        $em = isset($input['em']) ? (int)$input['em'] : 0;
        if ($cm > 0) { $em = 0; }
        if ($em > 0) { $cm = 0; }

        if ($userId <= 0 || !in_array($type, ['image', 'video'], true)) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de mídia inválido.']);
            return;
        }

        $allowed = $this->allowedMimeMap();
        $ext = $mime && isset($allowed[$type][$mime]) ? $allowed[$type][$mime] : null;
        if (!$ext) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Mime type inválido.']);
            return;
        }

        $maxSizeMb = (int)($_ENV['POST_MEDIA_MAX_SIZE_MB'] ?? 64);
        if ($type === 'video') {
            $maxSizeMb = (int)($_ENV['POST_MEDIA_MAX_VIDEO_MB'] ?? 80);
        }
        if ($size !== null && $size > ($maxSizeMb * 1024 * 1024)) {
            http_response_code(413);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo excede o tamanho máximo.']);
            return;
        }

        $objectKey = $this->buildObjectKey($userId, $ext);
        $now = (new DateTime())->format('Y-m-d H:i:s');

        $mediaId = $this->media->insert([
            'us' => $userId,
            'cm' => $cm,
            'em' => $em,
            'type' => $type,
            'mime' => $mime ?: null,
            'mime_type' => $mime ?: 'application/octet-stream',
            'size' => $size,
            'size_bytes' => $size ?? 0,
            'object_key' => $objectKey,
            'url' => null,
            'storage_driver' => 'oci',
            'status' => 'init',
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (!$mediaId) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao iniciar mídia.']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'media_id' => $mediaId,
            'object_key' => $objectKey,
            'upload_strategy' => 'server',
        ]);
    }

    public function upload(?object $payload): void
    {
        header('Content-Type: application/json');
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $mediaId = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;
        if ($mediaId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'media_id obrigatório.']);
            return;
        }

        $media = $this->media->findById($mediaId);
        if (!$media) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Mídia não encontrada.']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        if ((int)($media['us'] ?? 0) !== $userId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Mídia não pertence ao usuário.']);
            return;
        }

        if (!in_array($media['status'], ['init', 'uploaded'], true)) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Status inválido para upload.']);
            return;
        }

        $file = $_FILES['file'] ?? $_FILES['files'] ?? null;
        if (!$file) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nenhum arquivo enviado.']);
            return;
        }

        if (is_array($file['name'])) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Envie apenas um arquivo por requisição.']);
            return;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falha no upload do arquivo.']);
            return;
        }

        $mime = @mime_content_type($file['tmp_name']) ?: ($file['type'] ?? '');
        $allowed = $this->allowedMimeMap();
        $type = $media['type'] ?? '';
        $ext = $mime && isset($allowed[$type][$mime]) ? $allowed[$type][$mime] : null;
        if (!$ext) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de arquivo não suportado.']);
            return;
        }

        $sizeBytes = (int)($file['size'] ?? 0);
        if ($type === 'video') {
            $maxVideoMb = (int)($_ENV['POST_MEDIA_MAX_VIDEO_MB'] ?? 40);
            if ($sizeBytes > ($maxVideoMb * 1024 * 1024)) {
                http_response_code(413);
                echo json_encode(['status' => 'error', 'message' => 'Vídeo excede o tamanho máximo permitido.']);
                return;
            }
        } else {
            $maxImageMb = (int)($_ENV['POST_MEDIA_MAX_SIZE_MB'] ?? 15);
            if ($sizeBytes > ($maxImageMb * 1024 * 1024)) {
                http_response_code(413);
                echo json_encode(['status' => 'error', 'message' => 'Imagem excede o tamanho máximo permitido.']);
                return;
            }
        }

        $objectKey = $media['object_key'] ?? '';
        if ($objectKey === '') {
            $objectKey = $this->buildObjectKey($userId, $ext);
        }

        $storage = new StorageService();
        $objectKey = ltrim($objectKey, '/');
        $tmpRoot = $_ENV['POST_MEDIA_TMP_DIR'] ?? (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'workz_post_media');
        if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0755, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível preparar o diretório.']);
            return;
        }

        $tmpTarget = tempnam($tmpRoot, 'post_media_');
        if ($tmpTarget === false || !move_uploaded_file($file['tmp_name'], $tmpTarget)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível salvar o arquivo.']);
            return;
        }

        try {
            $storage->putObject($objectKey, $tmpTarget, $mime ?: 'application/octet-stream');
        } catch (\Throwable $e) {
            @unlink($tmpTarget);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao enviar mídia para storage.']);
            return;
        }

        @unlink($tmpTarget);

        $url = $this->buildInternalUrl($mediaId);
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->media->update($mediaId, [
            'mime' => $mime ?: ($media['mime'] ?? null),
            'mime_type' => $mime ?: ($media['mime_type'] ?? 'application/octet-stream'),
            'size' => $sizeBytes,
            'size_bytes' => $sizeBytes,
            'object_key' => $objectKey,
            'url' => $url,
            'storage_driver' => 'oci',
            'status' => 'uploaded',
            'updated_at' => $now,
        ]);

        echo json_encode([
            'status' => 'success',
            'media_id' => $mediaId,
            'url' => $url,
            'object_key' => $objectKey,
        ]);
    }

    public function complete(?object $payload): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $mediaId = (int)($input['media_id'] ?? 0);
        if ($mediaId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'media_id obrigatório.']);
            return;
        }

        $media = $this->media->findById($mediaId);
        if (!$media) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Mídia não encontrada.']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        if ((int)($media['us'] ?? 0) !== $userId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Mídia não pertence ao usuário.']);
            return;
        }

        if (!in_array($media['status'], ['init', 'uploaded'], true)) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Status inválido para completar.']);
            return;
        }

        $url = $media['url'] ?? null;
        if (!$url) {
            $url = $this->buildInternalUrl($mediaId);
        }

        if ($media['status'] !== 'uploaded') {
            $now = (new DateTime())->format('Y-m-d H:i:s');
            $this->media->update($mediaId, [
                'status' => 'uploaded',
                'url' => $url,
                'storage_driver' => $media['storage_driver'] ?? 'oci',
                'updated_at' => $now,
            ]);
        }

        echo json_encode([
            'status' => 'success',
            'media_id' => $mediaId,
            'url_final' => $url,
        ]);
    }

    public function batch(?object $payload): void
    {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $ids = $input['ids'] ?? null;
        if (!is_array($ids)) {
            $queryIds = $_GET['ids'] ?? '';
            $ids = $queryIds !== '' ? explode(',', $queryIds) : [];
        }

        $ids = array_values(array_filter(array_map('intval', (array)$ids), fn($id) => $id > 0));
        if (!$ids) {
            echo json_encode(['status' => 'success', 'items' => []]);
            return;
        }

        $rows = $this->media->findByIds($ids);
        $items = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if (!in_array($status, ['uploaded', 'ready'], true)) {
                continue;
            }
            $url = $row['url'] ?? null;
            if (!$url || str_starts_with((string)$url, '/uploads/')) {
                $url = $this->buildInternalUrl((int)($row['id'] ?? 0));
            }
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'url' => $url,
                'mime' => $row['mime'] ?? ($row['mime_type'] ?? null),
                'type' => $row['type'] ?? null,
                'status' => $status,
                'object_key' => $row['object_key'] ?? null,
            ];
        }

        echo json_encode([
            'status' => 'success',
            'items' => $items,
        ]);
    }

    private function buildObjectKey(int $userId, string $ext): string
    {
        $uuid = bin2hex(random_bytes(16));
        return "uploads/posts/{$userId}/{$uuid}.{$ext}";
    }

    private function buildInternalUrl(int $mediaId): string
    {
        return "/api/media/show/{$mediaId}";
    }

    public function show($id = null, $payload = null): void
    {
        $mediaId = $this->extractMediaIdFromArgs($id, $payload);
        if ($this->shouldLogMediaDebug()) {
            error_log(sprintf('[PostMedia::show] received id=%s mediaId=%d', (string)$id, $mediaId));
        }
        if ($mediaId <= 0) {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo 'media_id inválido.';
            return;
        }

        $media = $this->media->findById($mediaId);
        if (!$media) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Mídia não encontrada.';
            return;
        }

        $post = $this->findPostByMedia($mediaId);

        $payloadObj = $payload ?? $this->getOptionalPayload();
        $userId = (int)($payloadObj?->sub ?? 0);

        if (!$post) {
            if ($this->shouldLogMediaDebug()) {
                error_log(sprintf('[PostMedia::show] mediaId=%d post not found (fallback owner-only)', $mediaId));
            }
            // Fallback: permitir apenas o dono da mídia quando não há vínculo com post.
            if ($userId <= 0 || (int)($media['us'] ?? 0) !== $userId) {
                http_response_code(404);
                header('Content-Type: text/plain');
                echo 'Post não encontrado.';
                return;
            }
            $privacy = 0;
            $privacyToken = null;
            $scope = 'owner_only';
        } else {
            $privacy = (int)($post['post_privacy'] ?? 0);
            $privacyToken = $this->extractPrivacyToken($post['ct'] ?? null);
            $scope = $this->resolvePostScope($post);
        }

        if ($this->shouldLogMediaDebug()) {
            error_log(sprintf(
                '[PostMedia::show] mediaId=%d driver=%s post_id=%s privacy=%d token=%s scope=%s user=%s',
                $mediaId,
                (string)($media['storage_driver'] ?? 'local'),
                (string)($post['id'] ?? ''),
                $privacy,
                (string)($privacyToken ?? ''),
                $scope,
                $userId > 0 ? (string)$userId : 'anon'
            ));
        }

        [$allowed, $denyReason, $denyStatus] = $this->evaluatePostAccess($post, $userId, $privacy, $privacyToken, $media);
        if (!$allowed) {
            if ($this->shouldLogMediaDebug()) {
                error_log(sprintf('[PostMedia::show] mediaId=%d deny=%s', $mediaId, $denyReason));
            }
            http_response_code($denyStatus);
            header('Content-Type: text/plain');
            echo $denyReason;
            return;
        }

        $driver = $media['storage_driver'] ?? null;
        $objectKey = $media['object_key'] ?? '';
        if ($driver === 'oci' && $objectKey !== '') {
            if ($this->shouldLogMediaDebug()) {
                error_log(sprintf('[PostMedia::show] mediaId=%d branch=oci', $mediaId));
            }
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            $storage = new StorageService();
            $ttl = (int)($_ENV['OCI_PRESIGN_TTL'] ?? 600);
            $presigned = $storage->presignGet($objectKey, $ttl);
            header('Location: ' . $presigned, true, 302);
            return;
        }

        if ($objectKey !== '') {
            if ($this->shouldLogMediaDebug()) {
                error_log(sprintf('[PostMedia::show] mediaId=%d branch=local', $mediaId));
            }
            $publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
            $objectKey = ltrim($objectKey, '/');
            $path = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $objectKey);
            $this->streamLocalFile($path, $media['mime'] ?? ($media['mime_type'] ?? null));
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain');
        echo 'Arquivo não disponível.';
    }

    private function findPostByMedia(int $mediaId): array|false
    {
        $pdo = Database::getInstance($this->dbName);
        $sql = 'SELECT h.id, h.us, h.post_privacy, h.cm, h.em, h.ct
                FROM hpl h
                INNER JOIN hpl_media hm ON hm.post_id = h.id
                WHERE hm.media_id = :media_id
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['media_id' => $mediaId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        // Fallback quando tabela pivô não existe ou não foi preenchida: buscar por JSON no ct.
        try {
            $sql2 = "SELECT id, us, post_privacy, cm, em, ct
                     FROM hpl
                     WHERE JSON_CONTAINS(ct, CAST(:media_id AS JSON), '$.media[*].media_id')
                     LIMIT 1";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute(['media_id' => $mediaId]);
            $row2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            return $row2 ?: false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getOptionalPayload(): ?object
    {
        $token = null;
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if ($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }
        if (!$token && isset($_COOKIE['jwt_token'])) {
            $token = $_COOKIE['jwt_token'];
        }
        if (!$token) {
            return null;
        }
        try {
            $secretKey = $_ENV['JWT_SECRET'];
            return JWT::decode($token, new Key($secretKey, 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function streamLocalFile(string $path, ?string $mime): void
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'Arquivo não encontrado.';
            return;
        }

        $size = filesize($real);
        if ($size === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao ler arquivo.']);
            return;
        }

        $contentType = $mime ?: $this->detectMimeType($real);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $contentType);
        header('Accept-Ranges: bytes');

        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range) {
            if (!preg_match('/bytes=(\d*)-(\d*)/i', $range, $matches)) {
                http_response_code(416);
                return;
            }
            $start = $matches[1] !== '' ? (int)$matches[1] : 0;
            $end = $matches[2] !== '' ? (int)$matches[2] : ($size - 1);
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                header('Content-Range: bytes */' . $size);
                return;
            }
            $end = min($end, $size - 1);
            $length = $end - $start + 1;

            http_response_code(206);
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            $this->outputFileRange($real, $start, $length);
            return;
        }

        header('Content-Length: ' . $size);
        $this->outputFileRange($real, 0, $size);
    }

    private function outputFileRange(string $path, int $start, int $length): void
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            http_response_code(500);
            return;
        }
        if ($start > 0) {
            fseek($fp, $start);
        }

        $remaining = $length;
        $chunk = 8192;
        while ($remaining > 0 && !feof($fp)) {
            $read = fread($fp, min($chunk, $remaining));
            if ($read === false || $read === '') {
                break;
            }
            echo $read;
            $remaining -= strlen($read);
        }
        fclose($fp);
    }

    private function detectMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }
        return 'application/octet-stream';
    }

    private function extractMediaIdFromArgs($id, $payload): int
    {
        if (is_numeric($id)) {
            return (int)$id;
        }
        if (is_object($id)) {
            if (isset($id->id) && is_numeric($id->id)) {
                return (int)$id->id;
            }
            if (isset($id->params) && is_array($id->params) && isset($id->params[0]) && is_numeric($id->params[0])) {
                return (int)$id->params[0];
            }
        }
        if (is_numeric($payload)) {
            return (int)$payload;
        }
        if (is_object($payload)) {
            if (isset($payload->id) && is_numeric($payload->id)) {
                return (int)$payload->id;
            }
            if (isset($payload->params) && is_array($payload->params) && isset($payload->params[0]) && is_numeric($payload->params[0])) {
                return (int)$payload->params[0];
            }
        }
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            return (int)$_GET['id'];
        }
        return 0;
    }

    private function shouldLogMediaDebug(): bool
    {
        $debug = $_ENV['MEDIA_DEBUG'] ?? ($_ENV['DEBUG'] ?? '');
        return ($debug === '1' || strtolower((string)$debug) === 'true');
    }

    private function extractPrivacyToken($ct): ?string
    {
        if (!$ct) return null;
        try {
            $obj = is_string($ct) ? json_decode($ct, true) : (is_array($ct) ? $ct : null);
            if (!is_array($obj)) return null;
            return $obj['post_privacy_token'] ?? $obj['privacy_token'] ?? $obj['privacy'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolvePostScope(array $post): string
    {
        if (!empty($post['em'])) return 'business';
        if (!empty($post['cm'])) return 'team';
        return 'profile';
    }

    private function evaluatePostAccess($post, int $userId, int $privacy, ?string $token, array $media): array
    {
        if (!$post) {
            $owner = (int)($media['us'] ?? 0);
            if ($userId > 0 && $userId === $owner) return [true, 'owner', 200];
            return [false, 'post_not_found', 404];
        }
        $owner = (int)($post['us'] ?? 0);
        $cm = (int)($post['cm'] ?? 0);
        $em = (int)($post['em'] ?? 0);
        $isOwner = ($userId > 0 && $userId === $owner);
        $isLogged = ($userId > 0);

        if ($isOwner) return [true, 'owner', 200];
        if ($privacy >= 3) return [true, 'public', 200];
        if (!$isLogged) return [false, 'login_required', 401];

        if ($em <= 0 && $cm <= 0) {
            if ($privacy <= 0) return [false, 'private_owner_only', 403];
            if ($privacy === 1) {
                return $this->isFollower($userId, $owner)
                    ? [true, 'follower', 200]
                    : [false, 'followers_only', 403];
            }
            if ($privacy === 2) return [true, 'logged_in', 200];
            return [false, 'private', 403];
        }

        if ($em > 0) {
            $isMember = $this->isBusinessMember($userId, $em);
            $isManager = $this->isBusinessManager($userId, $em);
            if ($token === 'mod' || $privacy === 1) {
                return $isManager ? [true, 'business_manager', 200] : [false, 'business_manager_only', 403];
            }
            if ($token === 'lv1') return $isMember ? [true, 'business_member', 200] : [false, 'business_members_only', 403];
            if ($token === 'lv2') return [true, 'logged_in', 200];
            if ($token === 'lv3' || $privacy >= 3) return [true, 'public', 200];
            if ($privacy === 2) return $isMember ? [true, 'business_member', 200] : [false, 'business_members_only', 403];
            return [false, 'private', 403];
        }

        if ($cm > 0) {
            $teamRow = $this->getTeamRow($cm);
            $bizId = (int)($teamRow['em'] ?? 0);
            $isTeamMember = $this->isTeamMember($userId, $cm);
            $isTeamModerator = $this->isTeamModerator($userId, $cm);
            if ($token === 'mod' || $privacy === 1) {
                return $isTeamModerator ? [true, 'team_moderator', 200] : [false, 'team_moderator_only', 403];
            }
            if ($token === 'lv1') return $isTeamMember ? [true, 'team_member', 200] : [false, 'team_members_only', 403];
            if ($token === 'lv2') {
                return $bizId > 0 && $this->isBusinessMember($userId, $bizId)
                    ? [true, 'business_member', 200]
                    : [false, 'business_members_only', 403];
            }
            if ($token === 'lv3' || $privacy >= 3) return [true, 'public', 200];
            if ($privacy === 2) return $isTeamMember ? [true, 'team_member', 200] : [false, 'team_members_only', 403];
            return [false, 'private', 403];
        }

        return [false, 'private', 403];
    }

    private function isFollower(int $viewerId, int $ownerId): bool
    {
        if ($viewerId <= 0 || $ownerId <= 0) return false;
        try {
            $pdo = Database::getInstance('workz_data');
            $stmt = $pdo->prepare('SELECT 1 FROM usg WHERE s0 = :viewer AND s1 = :owner LIMIT 1');
            $stmt->execute(['viewer' => $viewerId, 'owner' => $ownerId]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isBusinessMember(int $userId, int $businessId): bool
    {
        if ($userId <= 0 || $businessId <= 0) return false;
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT nv, st FROM employees WHERE us = :us AND em = :em LIMIT 1');
            $stmt->execute(['us' => $userId, 'em' => $businessId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && (int)($row['st'] ?? 0) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isBusinessManager(int $userId, int $businessId): bool
    {
        if ($userId <= 0 || $businessId <= 0) return false;
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT nv, st FROM employees WHERE us = :us AND em = :em LIMIT 1');
            $stmt->execute(['us' => $userId, 'em' => $businessId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && (int)($row['st'] ?? 0) === 1 && (int)($row['nv'] ?? 0) >= 3;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isTeamMember(int $userId, int $teamId): bool
    {
        if ($userId <= 0 || $teamId <= 0) return false;
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT nv, st FROM teams_users WHERE us = :us AND cm = :cm LIMIT 1');
            $stmt->execute(['us' => $userId, 'cm' => $teamId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && (int)($row['st'] ?? 0) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isTeamModerator(int $userId, int $teamId): bool
    {
        if ($userId <= 0 || $teamId <= 0) return false;
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT nv, st FROM teams_users WHERE us = :us AND cm = :cm LIMIT 1');
            $stmt->execute(['us' => $userId, 'cm' => $teamId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row && (int)($row['st'] ?? 0) === 1 && (int)($row['nv'] ?? 0) >= 3;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getTeamRow(int $teamId): ?array
    {
        if ($teamId <= 0) return null;
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT id, em FROM teams WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $teamId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function allowedMimeMap(): array
    {
        return [
            'image' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'video' => [
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
            ],
        ];
    }
}
