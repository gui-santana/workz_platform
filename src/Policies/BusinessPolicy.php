<?php

namespace Workz\Platform\Policies;

use Workz\Platform\Models\General;

class BusinessPolicy
{
    public static function canManage(int $userId, int $companyId): bool
    {
        // Usuário precisa ter vínculo ativo (st=1) e nível >= 3 em employees
        $model = new General();
        $emp = $model->search('workz_companies', 'employees', ['nv','st'], [ 'us' => $userId, 'em' => $companyId, 'st' => 1 ], false);
        if (!$emp) return false;
        $level = (int)($emp['nv'] ?? 0);
        return $level >= 3;
    }
}

