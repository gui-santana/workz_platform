<?php
// src/Controllers/AppsController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Firebase\JWT\JWT;

class AppsController
{
    private General $generalModel;

    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * GET /api/apps/catalog
     * Lista o catálogo básico de apps ativos.
     * Aberto (sem middleware) por enquanto.
     */
    public function catalog(): void
    {
        try {
            $res = $this->generalModel->search(
                'workz_apps',
                'apps',
                ['id', 'slug', 'tt', 'im', 'vl', 'st', 'src', 'embed_url', 'color', 'ds'],
                ['st' => 1],
                true,
                200,
                0,
                ['by' => 'tt', 'dir' => 'ASC']
            );
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
            // Placeholder de scopes/entitlements (poderá vir de apps.scopes e gapp)
            'scopes' => [],
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
}
