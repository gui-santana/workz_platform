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

    /**
     * Avalia autorização para ações business.* a partir do role derivado.
     * Mantém compatibilidade com o mapeamento nv->role sem criar colunas novas.
     */
    public function allows(string $action, array $membership, array $ctx = []): bool
    {
        $role = strtoupper((string)($membership['role'] ?? 'GUEST'));

        // OWNER pode tudo no escopo de negócio
        if ($role === 'OWNER') {
            return true;
        }

        // ADMIN: permite a maioria das ações institucionais
        $adminAllowed = [
            'business.manage_apps',
            'business.manage_teams',
            'business.approve_member',
            'business.view_audit',
        ];
        if ($role === 'ADMIN') {
            return in_array($action, $adminAllowed, true);
        }

        // MEMBER / GUEST: por padrão negam ações administrativas
        $memberAllowed = [
            'business.view_audit',
        ];
        if ($role === 'MEMBER') {
            return in_array($action, $memberAllowed, true);
        }

        return false;
    }
}
