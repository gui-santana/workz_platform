<?php

namespace Workz\Platform\Policies;

class TeamPolicy
{
    public static function isOwner(int $userId, array $team): bool
    {
        return isset($team['us']) && (int)$team['us'] === $userId;
    }

    public static function isModerator(int $userId, array $team): bool
    {
        if (empty($team['usmn'])) return false;
        try {
            $mods = json_decode($team['usmn'], true);
            if (!is_array($mods)) return false;
            return in_array((string)$userId, array_map('strval', $mods), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function canManage(int $userId, array $team): bool
    {
        return self::isOwner($userId, $team) || self::isModerator($userId, $team);
    }

    public static function canDelete(int $userId, array $team): bool
    {
        // Mesma regra de gestão para exclusão da equipe
        return self::canManage($userId, $team);
    }

    /**
     * Avalia autorização para ações team.* usando nv do vínculo e regras de owner/moderador.
     * @param array $teamMembership Array do vínculo em teams_users (pode estar vazio).
     * @param array $teamRow Row completo de teams (para owner/moderadores).
     */
    public function allows(string $action, array $teamMembership, array $teamRow = []): bool
    {
        $role = strtoupper((string)($teamMembership['role'] ?? 'GUEST'));

        // Owner (LEAD) ou LEAD explícito: pode tudo no time
        if ($role === 'LEAD') {
            return true;
        }

        if ($role === 'OPERATOR') {
            $allowed = [
                'team.manage_settings',
                'team.approve_member',
            ];
            return in_array($action, $allowed, true);
        }

        if ($role === 'MEMBER') {
            // Não concedemos ações administrativas por padrão
            return false;
        }

        if ($role === 'VIEWER' || $role === 'GUEST') {
            return false;
        }

        return false;
    }
}
