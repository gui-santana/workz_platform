<?php
// src/Controllers/AppsController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Core\UniversalRuntime; // Para obter informações de build de apps JS
use Workz\Platform\Models\General; 
use Workz\Platform\Core\StorageManager;
use Firebase\JWT\JWT;
use Workz\Platform\Middleware\AuthMiddleware;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;

class AppsController
{
    use AuthorizationTrait;

    private General $generalModel;

    // ... (construtor e outros métodos existentes) ...
    public function __construct()
    {
        $this->generalModel = new General();
    }

    private function getUserBusinessIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $ids = [];
        try {
            $rows = $this->generalModel->search(
                'workz_companies',
                'employees',
                ['em'],
                ['us' => $userId, 'st' => 1],
                true
            );
            foreach ($rows ?: [] as $row) {
                if (isset($row['em']) && is_numeric($row['em'])) {
                    $ids[] = (int)$row['em'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $rows = $this->generalModel->search(
                'workz_companies',
                'companies',
                ['id'],
                ['us' => $userId, 'st' => 1],
                true
            );
            foreach ($rows ?: [] as $row) {
                if (isset($row['id']) && is_numeric($row['id'])) {
                    $ids[] = (int)$row['id'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        return $ids;
    }

    private function userHasPersonalApp(int $userId, int $appId): bool
    {
        try {
            $rows = $this->generalModel->search(
                'workz_apps',
                'gapp',
                ['id'],
                ['us' => $userId, 'ap' => $appId, 'st' => 1],
                true,
                1
            );
            if (empty($rows)) {
                $rows = $this->generalModel->search(
                    'workz_apps',
                    'gapp',
                    ['id'],
                    ['us' => $userId, 'ap' => $appId, 'subscription' => 1],
                    true,
                    1
                );
            }
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function userHasBusinessApp(int $userId, int $appId): bool
    {
        $businessIds = $this->getUserBusinessIds($userId);
        if (empty($businessIds)) {
            return false;
        }
        try {
            $rows = $this->generalModel->search(
                'workz_apps',
                'gapp',
                ['id'],
                [
                    'em' => ['op' => 'IN', 'value' => $businessIds],
                    'ap' => $appId,
                    'st' => 1,
                    'cm' => null,
                    'us' => null
                ],
                true,
                1
            );
            if (empty($rows)) {
                $rows = $this->generalModel->search(
                    'workz_apps',
                    'gapp',
                    ['id'],
                    [
                        'em' => ['op' => 'IN', 'value' => $businessIds],
                        'ap' => $appId,
                        'subscription' => 1,
                        'cm' => null,
                        'us' => null
                    ],
                    true,
                    1
                );
            }
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function userCanAccessApp(int $userId, int $appId): bool
    {
        if ($this->userHasPersonalApp($userId, $appId)) {
            return true;
        }
        if ($this->userHasBusinessApp($userId, $appId)) {
            return true;
        }
        return false;
    }

    private function getAppBySlug(string $slug, bool $withCode = false): ?array
    {
        if ($slug === '') {
            return null;
        }
        $columns = $withCode
            ? ['*']
            : ['id','tt','slug','ds','im','color','app_type','storage_type','version','scopes','access_level','publisher','vl','entity_type','st'];
        $app = $this->generalModel->search('workz_apps', 'apps', $columns, ['slug' => $slug], false);
        if (!$app && !$withCode) {
            $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['slug' => $slug], false);
        }
        return is_array($app) ? $app : null;
    }

    private function getAppById(int $appId, bool $withCode = false): ?array
    {
        if ($appId <= 0) {
            return null;
        }
        $columns = $withCode
            ? ['*']
            : ['id','tt','slug','ds','im','color','app_type','storage_type','version','scopes','access_level','publisher','vl','entity_type','st'];
        $app = $this->generalModel->search('workz_apps', 'apps', $columns, ['id' => $appId], false);
        if (!$app && !$withCode) {
            $app = $this->generalModel->search('workz_apps', 'apps', ['*'], ['id' => $appId], false);
        }
        return is_array($app) ? $app : null;
    }

    private function normalizeColor(?string $color): string
    {
        $color = trim((string)$color);
        if ($color === '') {
            return '#ff7a00';
        }
        if ($color[0] !== '#') {
            $color = '#' . $color;
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return '#ff7a00';
        }
        return strtolower($color);
    }

    private function escapeTemplateValue(string $value): string
    {
        $value = str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
        $value = str_replace(["\r", "\n"], ['\\r', '\\n'], $value);
        return $value;
    }

    private function coerceManifestPayload($raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private function buildWorkzManifestFromApp(array $app): array
    {
        $name = (string)($app['tt'] ?? 'Workz App');
        $slug = (string)($app['slug'] ?? 'workz-app');
        $version = (string)($app['version'] ?? '1.0.0');
        $appType = strtolower((string)($app['app_type'] ?? 'javascript'));
        $color = $this->normalizeColor($app['color'] ?? null);

        $entityType = isset($app['entity_type']) ? (int)$app['entity_type'] : 1;
        $contextMode = $entityType === 2 ? 'business' : 'user';

        $scopes = $app['scopes'] ?? '[]';
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }
        $storage = [];
        foreach ((array)$scopes as $scope) {
            if (strpos($scope, 'storage.kv') === 0) $storage[] = 'kv';
            if (strpos($scope, 'storage.docs') === 0) $storage[] = 'docs';
            if (strpos($scope, 'storage.blobs') === 0) $storage[] = 'blobs';
        }
        $storage = array_values(array_unique($storage));

        $entitlements = [
            'type' => ((float)($app['vl'] ?? 0) > 0) ? 'paid' : 'free',
            'price' => (float)($app['vl'] ?? 0),
        ];

        return [
            'id' => $slug,
            'name' => $name,
            'version' => $version,
            'appType' => $appType,
            'entry' => 'dist/index.html',
            'contextRequirements' => [
                'mode' => $contextMode,
                'allowContextSwitch' => true
            ],
            'permissions' => [
                'view' => [],
                'scopes' => $scopes,
                'storage' => $storage,
                'externalApi' => []
            ],
            'uiShell' => [
                'layout' => 'standard',
                'theme' => ['primary' => $color]
            ],
            'routes' => [],
            'entitlements' => $entitlements
        ];
    }

    private function resolveWorkzManifest(array $app): array
    {
        $base = $this->buildWorkzManifestFromApp($app);
        $provided = $this->coerceManifestPayload($app['manifest_json'] ?? null);
        if (!$provided) {
            return $base;
        }
        return array_replace_recursive($base, $provided);
    }

    private function renderEmbedHtml(array $app): ?string
    {
        $templatePath = dirname(__DIR__, 2) . '/public/apps/embed.html';
        $tpl = @file_get_contents($templatePath);
        if ($tpl === false) {
            return null;
        }

        $name = $this->escapeTemplateValue((string)($app['tt'] ?? 'Workz App'));
        $slug = $this->escapeTemplateValue((string)($app['slug'] ?? 'workz-app'));
        $appType = strtolower((string)($app['app_type'] ?? 'javascript'));
        $storageType = $this->escapeTemplateValue((string)($app['storage_type'] ?? 'database'));
        $version = $this->escapeTemplateValue((string)($app['version'] ?? '1.0.0'));
        $color = $this->normalizeColor($app['color'] ?? null);
        $icon = $this->escapeTemplateValue((string)($app['im'] ?? '/images/app-default.png'));
        $scopes = $app['scopes'] ?? '[]';
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }
        $scopesJson = json_encode($scopes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $scopesJson = $this->escapeTemplateValue((string)($scopesJson ?: '[]'));

        $appContent = '';
        if ($appType === 'javascript') {
            $jsCode = (string)($app['js_code'] ?? '');
            if ($jsCode !== '') {
                $jsCode = preg_replace('/<\/script>/i', '<\\/script>', $jsCode);
                $appContent = "<script>\n{$jsCode}\n</script>";
            } elseif (!empty($app['src'])) {
                $src = $this->escapeTemplateValue((string)$app['src']);
                $appContent = "<script src=\"{$src}\"></script>";
            }
        }

        $manifestPayload = $this->resolveWorkzManifest($app);
        $manifestJson = json_encode($manifestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($manifestJson) || $manifestJson === '') {
            $manifestJson = 'null';
        } else {
            $manifestJson = str_replace('</script>', '<\\/script>', $manifestJson);
        }

        $replacements = [
            '{{APP_ID}}' => (string)($app['id'] ?? 0),
            '{{APP_NAME}}' => $name,
            '{{APP_SLUG}}' => $slug,
            '{{APP_TYPE}}' => $this->escapeTemplateValue($appType ?: 'javascript'),
            '{{STORAGE_TYPE}}' => $storageType,
            '{{APP_VERSION}}' => $version,
            '{{REQUIRES_LOGIN}}' => ((int)($app['access_level'] ?? 1) > 0) ? 'true' : 'false',
            '{{APP_COLOR}}' => $color,
            '{{APP_ICON}}' => $icon,
            '{{APP_SCOPES}}' => $scopesJson,
            '{{TARGET_PLATFORM}}' => 'web',
            '{{EXECUTION_MODE}}' => 'direct',
            '{{ASPECT_RATIO}}' => $this->escapeTemplateValue((string)($app['aspect_ratio'] ?? '4:3')),
            '{{SUPPORTS_PORTRAIT}}' => ((int)($app['supports_portrait'] ?? 1) === 1) ? 'true' : 'false',
            '{{SUPPORTS_LANDSCAPE}}' => ((int)($app['supports_landscape'] ?? 1) === 1) ? 'true' : 'false',
            '{{APP_CONTENT}}' => $appContent,
            '{{FLUTTER_WEB_SCRIPTS}}' => '',
            '{{APP_MANIFEST}}' => $manifestJson,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $tpl);
    }

    private function renderLoginHtml(array $app): ?string
    {
        $templatePath = dirname(__DIR__, 2) . '/public/apps/app-login.html';
        $tpl = @file_get_contents($templatePath);
        if ($tpl === false) {
            return null;
        }

        $name = $this->escapeTemplateValue((string)($app['tt'] ?? 'Workz App'));
        $color = $this->normalizeColor($app['color'] ?? null);
        $icon = $this->escapeTemplateValue((string)($app['im'] ?? '/images/app-default.png'));

        $replacements = [
            '{{APP_NAME}}' => $name,
            '{{APP_COLOR}}' => $color,
            '{{APP_ICON}}' => $icon,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $tpl);
    }

    private function renderCompiledHtml(int $appId, string $appType): ?string
    {
        $appType = strtolower($appType);
        $baseDir = dirname(__DIR__, 2) . '/public/apps';
        switch ($appType) {
            case 'dart':
            case 'flutter':
                $path = $baseDir . "/flutter/{$appId}/web/index.html";
                break;
            default:
                $path = $baseDir . "/javascript/{$appId}/index.html";
                break;
        }

        $html = @file_get_contents($path);
        return $html === false ? null : $html;
    }

    private function shouldForceBaseHostRedirect(): bool
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        if ($appUrl !== '') {
            $baseHost = parse_url($appUrl, PHP_URL_HOST);
            $basePort = parse_url($appUrl, PHP_URL_PORT);
            if (is_string($baseHost) && $baseHost !== '') {
                $base = strtolower($baseHost . ($basePort ? ':' . $basePort : ''));
                return $host !== $base;
            }
        }

        $hostNoPort = preg_replace('/:\d+$/', '', $host);
        return str_ends_with($hostNoPort, '.localhost');
    }

    private function buildBaseHostUrl(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
            ? (string)$_SERVER['HTTP_X_FORWARDED_PROTO']
            : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        $appUrl = rtrim((string)($_ENV['APP_URL'] ?? ''), '/');
        if ($appUrl !== '') {
            return $appUrl . $path;
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $hostNoPort = preg_replace('/:\d+$/', '', $host);
        $port = (string)($_SERVER['SERVER_PORT'] ?? '');
        $portSuffix = ($port !== '' && $port !== '80' && $port !== '443') ? ':' . $port : '';

        if ($hostNoPort !== '' && $hostNoPort !== 'localhost' && str_ends_with($hostNoPort, '.localhost')) {
            return $scheme . '://localhost' . $portSuffix . $path;
        }

        return $scheme . '://' . $host . $path;
    }

    private function normalizeRedirectUrl(string $url): string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if ($this->shouldForceBaseHostRedirect()) {
            return $this->buildBaseHostUrl($url);
        }
        return $url;
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
                ['id', 'slug', 'tt', 'im', 'vl', 'st', 'src', 'embed_url', 'color', 'ds', 'app_type', 'storage_type', 'version', 'access_level'],
                [ 'st' => 1, 'access_level' => ['op' => '<>', 'value' => 2] ],
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
            $cm = isset($_GET['cm']) ? (int)$_GET['cm'] : null; // equipe opcional

            $hasUser = $this->generalModel->count('workz_apps', 'gapp', [ 'us' => $userId, 'ap' => $appId, 'st' => 1 ]) > 0;

            $hasCompany = false;
            if (!empty($em)) {
            $rows = $this->generalModel->search(
                'workz_apps',
                'gapp',
                ['id'],
                [
                    'em' => $em,
                    'ap' => $appId,
                    'st' => 1,
                    'cm' => null,
                    'us' => null
                ],
                true,
                1
            );
            $hasCompany = !empty($rows);
            }

            $hasTeam = false;
            if (!empty($cm)) {
                $ctx = ['em' => $em, 'cm' => $cm, 'ap' => $appId];
                $result = $this->getAuthorizationService()->can($this->currentUserFromPayload($auth), 'app.read', $ctx);
                if (!$result->allowed) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Sem permissão', 'reason' => 'forbidden']);
                    return;
                }
                $hasTeam = (bool)($result->meta['has_team_app'] ?? false);
            }

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

        $ctxType = is_array($ctx) ? ($ctx['type'] ?? 'user') : 'user';
        $ctxId = is_array($ctx) ? (int)($ctx['id'] ?? 0) : 0;

        $authCtx = ['ap' => $appId];
        if ($ctxType === 'team') {
            $authCtx['cm'] = $ctxId;
            if (is_array($ctx) && isset($ctx['em'])) {
                $authCtx['em'] = (int)$ctx['em'];
            }
        } elseif ($ctxType === 'business') {
            $authCtx['em'] = $ctxId;
        }

        $authResult = $this->getAuthorizationService()->can($this->currentUserFromPayload($auth), 'app.read', $authCtx);
        $allowed = $authResult->allowed;
        if (!$allowed && $ctxType === 'user') {
            $allowed = $this->userCanAccessApp($userId, $appId);
        }
        if (!$allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão', 'reason' => 'forbidden']);
            return;
        }

        // Buscar dados do app (para manifesto/escopos)
        $app = $this->generalModel->search(
            'workz_apps',
            'apps',
            ['id','tt','slug','app_type','entity_type','vl','scopes','color','manifest_json'],
            ['id' => $appId],
            false
        );
        if (!$app) {
            echo json_encode([
                'success' => false,
                'error' => 'App não encontrado.',
                'code' => 'app_not_found'
            ]);
            return;
        }

        // Enforce contexto do manifesto (fase 1)
        $manifest = $this->resolveWorkzManifest($app);
        $reqMode = $manifest['contextRequirements']['mode'] ?? null;
        if ($reqMode && $reqMode !== 'hybrid') {
            $reqMode = strtolower((string)$reqMode);
            if ($reqMode === 'business' && $ctxType !== 'business') {
                http_response_code(403);
                echo json_encode(['error' => 'Contexto inválido', 'reason' => 'context_required_business']);
                return;
            }
            if ($reqMode === 'team' && $ctxType !== 'team') {
                http_response_code(403);
                echo json_encode(['error' => 'Contexto inválido', 'reason' => 'context_required_team']);
                return;
            }
            if ($reqMode === 'user' && $ctxType !== 'user') {
                http_response_code(403);
                echo json_encode(['error' => 'Contexto inválido', 'reason' => 'context_required_user']);
                return;
            }
        }

        $issuedAt = time();
        $expire   = $issuedAt + 86400; // 24 horas (alinhado ao token principal)
        $payload  = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => $userId,
            'aud' => 'app:' . $app['id'],
            'ctx' => is_array($ctx) ? $ctx : ['type' => $ctxType, 'id' => ($ctxType === 'user' ? $userId : $ctxId)],
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
     * GET /api/app/run/{slug}
     * Runner autenticado para apps.
     */
    public function run(object $auth, string $slug): void
    {
        $userId = (int)($auth->sub ?? 0);
        if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

        $app = $this->getAppBySlug($slug, true);
        if (!$app || (int)($app['st'] ?? 0) !== 1) {
            http_response_code(404);
            echo json_encode(['error' => 'App não encontrado.']);
            return;
        }

        $appId = (int)$app['id'];
        if (!$this->userCanAccessApp($userId, $appId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão', 'reason' => 'forbidden']);
            return;
        }

        $appType = strtolower((string)($app['app_type'] ?? 'javascript'));
        $storageType = strtolower((string)($app['storage_type'] ?? 'database'));

        if ($appType === 'javascript' && $storageType === 'database') {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        if ($appType === 'javascript' && $this->shouldForceBaseHostRedirect()) {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        $buildInfo = UniversalRuntime::getBuildInfo($appId, $appType);
        if (!empty($buildInfo['is_compiled'])) {
            if ($this->shouldForceBaseHostRedirect()) {
                $compiledHtml = $this->renderCompiledHtml($appId, $appType);
                if ($compiledHtml !== null) {
                    header('Content-Type: text/html; charset=UTF-8');
                    echo $compiledHtml;
                    return;
                }
            }
            $url = $buildInfo['url'];
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if ($qs !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
            }
            $url = $this->normalizeRedirectUrl($url);
            header('Location: ' . $url, true, 302);
            return;
        }

        if ($appType === 'javascript') {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'App build indisponível.']);
    }

    /**
     * GET /api/app/public/{slug}
     * Runner público (sem auth) para apps com acesso liberado.
     */
    public function publicRun(string $slug): void
    {
        $app = $this->getAppBySlug($slug, true);
        if (!$app || (int)($app['st'] ?? 0) !== 1) {
            http_response_code(404);
            echo json_encode(['error' => 'App não encontrado.']);
            return;
        }
        if (!empty($_GET['token'])) {
            $auth = AuthMiddleware::handle();
            $this->run($auth, $slug);
            return;
        }
        if ((int)($app['access_level'] ?? 0) === 2) {
            $html = $this->renderLoginHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
            http_response_code(403);
            echo json_encode(['error' => 'Sem permissão']);
            return;
        }

        $appId = (int)$app['id'];
        $appType = strtolower((string)($app['app_type'] ?? 'javascript'));
        $storageType = strtolower((string)($app['storage_type'] ?? 'database'));

        if ($appType === 'javascript' && $storageType === 'database') {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        if ($appType === 'javascript' && $this->shouldForceBaseHostRedirect()) {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        $buildInfo = UniversalRuntime::getBuildInfo($appId, $appType);
        if (!empty($buildInfo['is_compiled'])) {
            if ($this->shouldForceBaseHostRedirect()) {
                $compiledHtml = $this->renderCompiledHtml($appId, $appType);
                if ($compiledHtml !== null) {
                    header('Content-Type: text/html; charset=UTF-8');
                    echo $compiledHtml;
                    return;
                }
            }
            $url = $buildInfo['url'];
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            if ($qs !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $qs;
            }
            $url = $this->normalizeRedirectUrl($url);
            header('Location: ' . $url, true, 302);
            return;
        }

        if ($appType === 'javascript') {
            $html = $this->renderEmbedHtml($app);
            if ($html !== null) {
                header('Content-Type: text/html; charset=UTF-8');
                echo $html;
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'App build indisponível.']);
    }

    /**
     * GET /api/apps/manifest/{slug}
     * Manifest simplificado para apps ativos.
     */
    public function manifest(string $slug): void
    {
        $app = $this->getAppBySlug($slug, false);
        if (!$app || (int)($app['st'] ?? 0) !== 1) {
            http_response_code(404);
            echo json_encode(['error' => 'App não encontrado.']);
            return;
        }

        $name = (string)($app['tt'] ?? 'Workz App');
        $shortName = substr($name, 0, 16);
        $color = $this->normalizeColor($app['color'] ?? null);
        $icon = (string)($app['im'] ?? '/images/app-default.png');
        $scopes = $app['scopes'] ?? '[]';
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }

        $manifest = [
            'name' => $name,
            'short_name' => $shortName,
            'description' => (string)($app['ds'] ?? ''),
            'version' => (string)($app['version'] ?? '1.0.0'),
            'slug' => (string)($app['slug'] ?? $slug),
            'publisher' => (int)($app['publisher'] ?? 0),
            'start_url' => '/app/run/' . $slug,
            'display' => 'standalone',
            'background_color' => $color,
            'theme_color' => $color,
            'icons' => [
                [
                    'src' => $icon,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ],
            'categories' => ['productivity', 'business'],
            'lang' => 'pt-BR',
            'scope' => '/app/run/' . $slug . '/',
            'permissions' => $scopes,
            'workz' => [
                'access_level' => (int)($app['access_level'] ?? 0),
                'entity_type' => (int)($app['entity_type'] ?? 0),
                'price' => (float)($app['vl'] ?? 0),
            ],
        ];

        header('Content-Type: application/manifest+json');
        echo json_encode($manifest);
    }
}
