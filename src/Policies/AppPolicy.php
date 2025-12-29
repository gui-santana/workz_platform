<?php

namespace Workz\Platform\Policies;

class AppPolicy
{
    /**
     * Avalia ações app.* com base no papel no time (e opcionalmente no negócio).
     */
    public function allows(string $action, string $teamRole, ?string $businessRole = null): bool
    {
        $teamRole = strtoupper($teamRole);
        $businessRole = $businessRole ? strtoupper($businessRole) : null;

        // Reforço: se o negócio não está ativo (papel GUEST), negar tudo
        if ($businessRole === 'GUEST') {
            return false;
        }

        // Matriz de permissões por papel do time
        $matrix = [
            'LEAD' => [
                'app.read',
                'app.create',
                'app.update_own',
                'app.update_any',
                'app.delete',
                'app.approve',
                'app.admin_settings',
            ],
            'OPERATOR' => [
                'app.read',
                'app.create',
                'app.update_any',
                'app.delete',
            ],
            'MEMBER' => [
                'app.read',
                'app.create',
                'app.update_own',
            ],
            'VIEWER' => [
                'app.read',
            ],
            'GUEST' => [],
        ];

        return in_array($action, $matrix[$teamRole] ?? [], true);
    }
}
