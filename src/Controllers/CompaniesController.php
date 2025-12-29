<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Policies\BusinessPolicy;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;
use Workz\Platform\Services\AuditLogService;

class CompaniesController
{
    use AuthorizationTrait;

    private General $db;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->db = new General();
        $this->audit = new AuditLogService();
    }

    public function updateMemberLevel(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        $nv = (int)($input['nv'] ?? 0);
        if (!$payload || !$companyId || !$targetUser || $nv < 1 || $nv > 4) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        $this->authorize('business.manage_teams', ['em' => $companyId], $payload);

        $ok = $this->db->update('workz_companies','employees', ['nv'=>$nv], ['em'=>$companyId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log($userId, 'business.member.set_role', ['em' => $companyId, 'target_type' => 'user', 'target_id' => $targetUser], [], ['nv' => $nv], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    public function acceptMember(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        if (!$payload || !$companyId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        $this->authorize('business.approve_member', ['em' => $companyId], $payload);
        $ok = $this->db->update('workz_companies','employees', ['st'=>1], ['em'=>$companyId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log($userId, 'business.member.accept', ['em' => $companyId, 'target_type' => 'user', 'target_id' => $targetUser], [], ['st' => 1], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    public function rejectMember(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        $remove = !empty($input['remove']);
        if (!$payload || !$companyId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        $this->authorize($remove ? 'business.manage_teams' : 'business.approve_member', ['em' => $companyId], $payload);

        if ($remove) {
            $company = $this->db->search('workz_companies', 'companies', ['id','us'], ['id' => $companyId], false);
            if (!$company) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Negócio não encontrado']); return; }
            if ((int)($company['us'] ?? 0) === $targetUser) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Não é possível remover o proprietário.']); return; }
        }

        $before = $this->db->search('workz_companies','employees', ['*'], ['em' => $companyId, 'us' => $targetUser], false);
        $ok = $remove
            ? $this->db->delete('workz_companies','employees', ['em'=>$companyId,'us'=>$targetUser])
            : $this->db->delete('workz_companies','employees', ['em'=>$companyId,'us'=>$targetUser,'st'=>0]);
        if ($ok && $remove) {
            // Remove o usuário de todas as equipes do negócio.
            $teams = $this->db->search('workz_companies', 'teams', ['id'], ['em' => $companyId], true) ?: [];
            foreach ($teams as $team) {
                $teamId = (int)($team['id'] ?? 0);
                if ($teamId > 0) {
                    $this->db->delete('workz_companies', 'teams_users', ['cm' => $teamId, 'us' => $targetUser]);
                }
            }
        }
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log(
                $userId,
                $remove ? 'business.member.remove' : 'business.member.reject',
                ['em' => $companyId, 'target_type' => 'user', 'target_id' => $targetUser],
                $before ?: [],
                $remove ? [] : ['st' => 0],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        }
    }
}
