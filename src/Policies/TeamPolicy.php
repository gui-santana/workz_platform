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
}

