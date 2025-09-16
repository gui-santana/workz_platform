<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Policies\BusinessPolicy;

class CompaniesController
{
    private General $db;

    public function __construct()
    {
        $this->db = new General();
    }

    public function updateMemberLevel(?object $payload): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        $nv = (int)($input['nv'] ?? 0);
        if (!$payload || !$companyId || !$targetUser || $nv < 1 || $nv > 4) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        if (!BusinessPolicy::canManage($userId, $companyId)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }

        $ok = $this->db->update('workz_companies','employees', ['nv'=>$nv], ['em'=>$companyId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
    }

    public function acceptMember(?object $payload): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        if (!$payload || !$companyId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        if (!BusinessPolicy::canManage($userId, $companyId)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }
        $ok = $this->db->update('workz_companies','employees', ['st'=>1], ['em'=>$companyId,'us'=>$targetUser]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
    }

    public function rejectMember(?object $payload): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $companyId = (int)($input['companyId'] ?? 0);
        $targetUser = (int)($input['userId'] ?? 0);
        if (!$payload || !$companyId || !$targetUser) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Dados inválidos']); return; }
        $userId = (int)$payload->sub;

        if (!BusinessPolicy::canManage($userId, $companyId)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Sem permissão']); return; }
        $ok = $this->db->delete('workz_companies','employees', ['em'=>$companyId,'us'=>$targetUser,'st'=>0]);
        if ($ok) { echo json_encode(['status'=>'success']); } else { http_response_code(500); echo json_encode(['status'=>'error']); }
    }
}

