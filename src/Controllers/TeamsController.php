<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Policies\TeamPolicy;

class TeamsController
{
    private General $db;

    public function __construct()
    {
        $this->db = new General();
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

        if (!TeamPolicy::canDelete($userId, $team)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }

        // Remove vínculos e equipe
        $this->db->delete('workz_companies','teams_users', ['cm'=>$teamId]);
        $ok = $this->db->delete('workz_companies','teams', ['id'=>$teamId]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Falha ao excluir']); }
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
        if (!TeamPolicy::canManage($userId, $team)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }

        $ok = $this->db->update('workz_companies','teams_users', ['nv'=>$nv], ['cm'=>$teamId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
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
        if (!TeamPolicy::canManage($userId, $team)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }
        $ok = $this->db->update('workz_companies','teams_users', ['st'=>1], ['cm'=>$teamId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
    }

    public function rejectMember(?object $payload): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $teamId = (int)($input['teamId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        if (!$payload || !$teamId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;
        $team = $this->db->search('workz_companies','teams',['*'],['id'=>$teamId], false);
        if (!$team) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'Equipe não encontrada']); return; }
        if (!TeamPolicy::canManage($userId, $team)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }
        $ok = $this->db->delete('workz_companies','teams_users', ['cm'=>$teamId,'us'=>$targetUser,'st'=>0]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
    }
}
