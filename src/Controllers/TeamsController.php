<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Policies\TeamPolicy;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;
use Workz\Platform\Services\AuditLogService;

class TeamsController
{
    use AuthorizationTrait;

    private General $db;
    private AuditLogService $audit;

    public function __construct()
    {
        $this->db = new General();
        $this->audit = new AuditLogService();
    }

    public function delete(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $teamId = (int)($input['id'] ?? 0);
        if (!$payload || !$teamId) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        $team = $this->db->search('workz_companies','teams',['*'],['id'=>$teamId], false);
        if (!$team) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Equipe não encontrada']); return; }

        $this->authorize('team.manage_settings', ['cm' => $teamId, 'em' => $team['em'] ?? null], $payload);

        // Remove vínculos e equipe
        $this->db->delete('workz_companies','teams_users', ['cm'=>$teamId]);
        $ok = $this->db->delete('workz_companies','teams', ['id'=>$teamId]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Falha ao excluir']); }
        if ($ok) {
            $this->audit->log($userId, 'team.delete', ['cm' => $teamId, 'em' => $team['em'] ?? null], $team, [], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    public function updateMemberLevel(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $teamId = (int)($input['teamId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        $nv = (int)($input['nv'] ?? 0);
        if (!$payload || !$teamId || !$targetUser || $nv < 1 || $nv > 4) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        $team = $this->db->search('workz_companies','teams',['*'],['id'=>$teamId], false);
        if (!$team) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Equipe não encontrada']); return; }
        $this->authorize('team.set_roles', ['cm' => $teamId, 'em' => $team['em'] ?? null], $payload);

        $ok = $this->db->update('workz_companies','teams_users', ['nv'=>$nv], ['cm'=>$teamId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log($userId, 'team.member.set_role', ['cm' => $teamId, 'em' => $team['em'] ?? null, 'target_type' => 'user', 'target_id' => $targetUser], [], ['nv' => $nv], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    public function acceptMember(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $teamId = (int)($input['teamId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        if (!$payload || !$teamId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;
        $team = $this->db->search('workz_companies','teams',['*'],['id'=>$teamId], false);
        if (!$team) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Equipe não encontrada']); return; }
        $this->authorize('team.approve_member', ['cm' => $teamId, 'em' => $team['em'] ?? null], $payload);
        $ok = $this->db->update('workz_companies','teams_users', ['st'=>1], ['cm'=>$teamId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log($userId, 'team.member.accept', ['cm' => $teamId, 'em' => $team['em'] ?? null, 'target_type' => 'user', 'target_id' => $targetUser], [], ['st' => 1], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
    }

    public function rejectMember(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $teamId = (int)($input['teamId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        $remove = !empty($input['remove']);
        if (!$payload || !$teamId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;
        $team = $this->db->search('workz_companies','teams',['*'],['id'=>$teamId], false);
        if (!$team) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Equipe não encontrada']); return; }
        $this->authorize($remove ? 'team.manage_settings' : 'team.approve_member', ['cm' => $teamId, 'em' => $team['em'] ?? null], $payload);

        if ($remove && (int)($team['us'] ?? 0) === $targetUser) {
            http_response_code(403);
            echo json_encode(['status'=>'error','message'=>'Não é possível remover o líder da equipe.']);
            return;
        }

        $before = $this->db->search('workz_companies','teams_users', ['*'], ['cm' => $teamId, 'us' => $targetUser], false);
        $ok = $remove
            ? $this->db->delete('workz_companies','teams_users', ['cm'=>$teamId,'us'=>$targetUser])
            : $this->db->delete('workz_companies','teams_users', ['cm'=>$teamId,'us'=>$targetUser,'st'=>0]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
        if ($ok) {
            $this->audit->log(
                $userId,
                $remove ? 'team.member.remove' : 'team.member.reject',
                ['cm' => $teamId, 'em' => $team['em'] ?? null, 'target_type' => 'user', 'target_id' => $targetUser],
                $before ?: [],
                $remove ? [] : ['st' => 0],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        }
    }
}
