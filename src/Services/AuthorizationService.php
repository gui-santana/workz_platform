<?php

namespace Workz\Platform\Services;

use Workz\Platform\Core\Database;
use Workz\Platform\Policies\BusinessPolicy;
use Workz\Platform\Policies\TeamPolicy;
use Workz\Platform\Policies\AppPolicy;
use PDO;

class AuthorizationService
{
    private BusinessPolicy $businessPolicy;
    private TeamPolicy $teamPolicy;
    private AppPolicy $appPolicy;

    /**
     * Cache simples por request para memberships e vínculos de app.
     */
    private static array $cache = [
        'business' => [], // [userId][businessId] => row
        'team' => [],     // [userId][teamId] => row
        'teamRow' => [],  // [teamId] => row
        'gappBusiness' => [], // [businessId][appId] => bool
        'gappTeam' => [],     // [teamId][appId] => bool
        'gappUser' => [],
        'appPublisher' => [], // [appId] => publisherId
        'appIdBySlug' => [],  // [slug] => appId
        'userBusinesses' => [], // [userId] => [businessId...]
        'gappColumns' => [], // [column] => bool
    ];

    public function __construct()
    {
        $this->businessPolicy = new BusinessPolicy();
        $this->teamPolicy = new TeamPolicy();
        $this->appPolicy = new AppPolicy();
    }

    public function can(array $user, string $action, array $ctx = []): AuthorizationResult
    {
        $userId = (int)($user['id'] ?? $user['sub'] ?? $user['user_id'] ?? 0);
        if ($userId <= 0) {
            return AuthorizationResult::deny('unauthenticated');
        }

        $businessId = isset($ctx['em']) ? (int)$ctx['em'] : null;
        $teamId = isset($ctx['cm']) ? (int)$ctx['cm'] : null;
        $appId = isset($ctx['ap']) ? (int)$ctx['ap'] : null;
        $appSlug = $ctx['ap_slug'] ?? null;

        // Resolve app id from slug when needed
        if (!$appId && $appSlug) {
            $appId = $this->resolveAppIdBySlug($appSlug);
            if (!$appId) {
                return AuthorizationResult::deny('app.not_found');
            }
        }

        // Normalize team->business when business not provided
        $teamRow = $teamId ? $this->getTeamRow($teamId) : null;
        if ($teamRow && !$businessId) {
            $businessId = (int)($teamRow['em'] ?? 0) ?: null;
        }

        $businessMembership = ($businessId && $userId) ? $this->getBusinessMembership($userId, $businessId) : null;
        $teamMembership = ($teamId && $userId) ? $this->getTeamMembership($userId, $teamId) : null;

        // Validate team belongs to business
        if ($teamRow && $businessId && (int)$teamRow['em'] !== $businessId) {
            return AuthorizationResult::deny('team.business_mismatch');
        }

        $businessRole = $this->deriveBusinessRole($businessMembership);
        $teamRole = $this->deriveTeamRole($teamMembership, $teamRow, $userId);

        // Hard deny if memberships are inactive when context is provided
        if ($businessId && (!$businessMembership || (int)($businessMembership['st'] ?? 0) !== 1)) {
            return AuthorizationResult::deny('business.inactive', ['business_role' => $businessRole, 'team_role' => $teamRole]);
        }
        if ($teamId && (!$teamMembership || (int)($teamMembership['st'] ?? 0) !== 1)) {
            if (!in_array($teamRole, ['LEAD','OPERATOR'], true)) {
                return AuthorizationResult::deny('team.inactive', ['business_role' => $businessRole, 'team_role' => $teamRole]);
            }
        }

        // Business-level actions
        if (strpos($action, 'business.') === 0) {
            if (!$businessId) {
                return AuthorizationResult::deny('business.context_required');
            }
            $allowed = $this->businessPolicy->allows($action, ['role' => $businessRole, 'nv' => $businessMembership['nv'] ?? null], $ctx);
            return $allowed
                ? AuthorizationResult::allow(['business_role' => $businessRole])
                : AuthorizationResult::deny('business.policy_denied', ['business_role' => $businessRole]);
        }

        // Team-level actions
        if (strpos($action, 'team.') === 0) {
            if (!$teamId) {
                return AuthorizationResult::deny('team.context_required');
            }
            if (!$businessId) {
                return AuthorizationResult::deny('business.context_required');
            }
            $allowed = $this->teamPolicy->allows($action, ['role' => $teamRole, 'nv' => $teamMembership['nv'] ?? null], $teamRow ?? []);
            return $allowed
                ? AuthorizationResult::allow(['team_role' => $teamRole, 'business_role' => $businessRole])
                : AuthorizationResult::deny('team.policy_denied', ['team_role' => $teamRole, 'business_role' => $businessRole]);
        }

        // App-level actions
        if (strpos($action, 'app.') === 0) {
            if (!$appId) {
                return AuthorizationResult::deny('app.context_required');
            }

            $publisherId = $this->getAppPublisherId($appId);
            if ($publisherId > 0) {
                if ($publisherId === $userId || $this->businessPolicy->canManage($userId, $publisherId)) {
                    return AuthorizationResult::allow([
                        'publisher' => $publisherId,
                        'owner_override' => true,
                    ]);
                }
            }

            // Personal context (legacy user-level apps)
            if (!$businessId && !$teamId) {
                $hasUserApp = $this->hasUserApp($userId, $appId);
                if (!$hasUserApp) {
                    // Compat: permitir escopo user quando há assinatura via negócio.
                    $businessIds = $this->getUserBusinessIds($userId);
                    foreach ($businessIds as $em) {
                        $membership = $this->getBusinessMembership($userId, $em);
                        if (!$membership || (int)($membership['st'] ?? 0) !== 1) {
                            continue;
                        }
                        if (!$this->hasBusinessApp($em, $appId)) {
                            continue;
                        }
                        $bizRole = $this->deriveBusinessRole($membership);
                        $legacyTeamRole = $this->deriveLegacyTeamRoleFromBusiness($bizRole);
                        $allowed = $this->appPolicy->allows($action, $legacyTeamRole, $bizRole);
                        return $allowed
                            ? AuthorizationResult::allow([
                                'has_business_app' => true,
                                'business_role' => $bizRole,
                                'team_role' => $legacyTeamRole,
                            ])
                            : AuthorizationResult::deny('app.policy_denied', [
                                'has_business_app' => true,
                                'business_role' => $bizRole,
                                'team_role' => $legacyTeamRole,
                            ]);
                    }
                    return AuthorizationResult::deny('app.not_subscribed_user');
                }
                $allowed = $this->appPolicy->allows($action, 'LEAD', null);
                return $allowed
                    ? AuthorizationResult::allow([
                        'has_user_app' => $hasUserApp,
                        'team_role' => 'LEAD',
                    ])
                    : AuthorizationResult::deny('app.policy_denied', [
                        'has_user_app' => $hasUserApp,
                        'team_role' => 'LEAD',
                    ]);
            }

            if ($businessId && !$teamId) {
                $hasBusinessApp = $this->hasBusinessApp($businessId, $appId);
                if (!$hasBusinessApp) {
                    return AuthorizationResult::deny('app.not_subscribed_business', [
                        'business_role' => $businessRole,
                        'has_business_app' => false,
                    ]);
                }

                $legacyTeamRole = $this->deriveLegacyTeamRoleFromBusiness($businessRole);
                $allowed = $this->appPolicy->allows($action, $legacyTeamRole, $businessRole);
                return $allowed
                    ? AuthorizationResult::allow([
                        'business_role' => $businessRole,
                        'team_role' => $legacyTeamRole,
                        'has_business_app' => $hasBusinessApp,
                        'has_team_app' => false,
                    ])
                    : AuthorizationResult::deny('app.policy_denied', [
                        'business_role' => $businessRole,
                        'team_role' => $legacyTeamRole,
                        'has_business_app' => $hasBusinessApp,
                        'has_team_app' => false,
                    ]);
            }

            if (!$businessId) {
                return AuthorizationResult::deny('business.context_required');
            }
            if (!$teamId) {
                return AuthorizationResult::deny('team.context_required');
            }

            $hasBusinessApp = $this->hasBusinessApp($businessId, $appId);
            $hasTeamApp = $this->hasTeamApp($businessId, $teamId, $appId);

            if (!$hasBusinessApp) {
                return AuthorizationResult::deny('app.not_subscribed_business', [
                    'business_role' => $businessRole,
                    'team_role' => $teamRole,
                    'has_business_app' => false,
                    'has_team_app' => $hasTeamApp,
                ]);
            }
            if (!$hasTeamApp) {
                return AuthorizationResult::deny('app.not_enabled_team', [
                    'business_role' => $businessRole,
                    'team_role' => $teamRole,
                    'has_business_app' => true,
                    'has_team_app' => false,
                ]);
            }

            $allowed = $this->appPolicy->allows($action, $teamRole, $businessRole);
            return $allowed
                ? AuthorizationResult::allow([
                    'business_role' => $businessRole,
                    'team_role' => $teamRole,
                    'has_business_app' => $hasBusinessApp,
                    'has_team_app' => $hasTeamApp,
                ])
                : AuthorizationResult::deny('app.policy_denied', [
                    'business_role' => $businessRole,
                    'team_role' => $teamRole,
                    'has_business_app' => $hasBusinessApp,
                    'has_team_app' => $hasTeamApp,
                ]);
        }

        // Unknown action: deny by default
        return AuthorizationResult::deny('action.unknown');
    }

    protected function getBusinessMembership(int $userId, int $businessId): ?array
    {
        if (isset(self::$cache['business'][$userId][$businessId])) {
            return self::$cache['business'][$userId][$businessId];
        }
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT us, em, nv, st FROM employees WHERE us = :us AND em = :em LIMIT 1');
            $stmt->execute([':us' => $userId, ':em' => $businessId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $row = null;
        }
        self::$cache['business'][$userId][$businessId] = $row;
        return $row;
    }

    protected function getTeamMembership(int $userId, int $teamId): ?array
    {
        if (isset(self::$cache['team'][$userId][$teamId])) {
            return self::$cache['team'][$userId][$teamId];
        }
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT us, cm, nv, st FROM teams_users WHERE us = :us AND cm = :cm LIMIT 1');
            $stmt->execute([':us' => $userId, ':cm' => $teamId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $row = null;
        }
        self::$cache['team'][$userId][$teamId] = $row;
        return $row;
    }

    protected function getTeamRow(int $teamId): ?array
    {
        if (isset(self::$cache['teamRow'][$teamId])) {
            return self::$cache['teamRow'][$teamId];
        }
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT id, em, us, usmn, st FROM teams WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $teamId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $row = null;
        }
        self::$cache['teamRow'][$teamId] = $row;
        return $row;
    }

    protected function gappHasColumn(string $column): bool
    {
        if (isset(self::$cache['gappColumns'][$column])) {
            return self::$cache['gappColumns'][$column];
        }
        $exists = false;
        try {
            $pdo = Database::getInstance('workz_apps');
            $stmt = $pdo->prepare('SHOW COLUMNS FROM gapp LIKE :col');
            $stmt->execute([':col' => $column]);
            $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $exists = false;
        }
        self::$cache['gappColumns'][$column] = $exists;
        return $exists;
    }

    protected function getUserBusinessIds(int $userId): array
    {
        if (isset(self::$cache['userBusinesses'][$userId])) {
            return self::$cache['userBusinesses'][$userId];
        }
        $ids = [];
        try {
            $pdo = Database::getInstance('workz_companies');
            $stmt = $pdo->prepare('SELECT em FROM employees WHERE us = :us AND st = 1');
            $stmt->execute([':us' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                if (isset($row['em']) && is_numeric($row['em'])) {
                    $ids[] = (int)$row['em'];
                }
            }
        } catch (\Throwable $e) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        self::$cache['userBusinesses'][$userId] = $ids;
        return $ids;
    }

    protected function hasBusinessApp(int $businessId, int $appId): bool
    {
        if (isset(self::$cache['gappBusiness'][$businessId][$appId])) {
            return self::$cache['gappBusiness'][$businessId][$appId];
        }
        $result = false;
        try {
            $pdo = Database::getInstance('workz_apps');
            $hasCm = $this->gappHasColumn('cm');
            $hasSubscription = $this->gappHasColumn('subscription');
            $subClause = $hasSubscription ? '(st = 1 OR subscription = 1)' : 'st = 1';
            if ($hasCm) {
                // Permite entitlement de empresa mesmo se houver "us" preenchido (compra feita por um usuário).
                $stmt = $pdo->prepare("SELECT id FROM gapp WHERE em = :em AND ap = :ap AND (cm IS NULL OR cm = 0) AND {$subClause} LIMIT 1");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM gapp WHERE em = :em AND ap = :ap AND {$subClause} LIMIT 1");
            }
            $stmt->execute([':em' => $businessId, ':ap' => $appId]);
            $result = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $result = false;
        }
        self::$cache['gappBusiness'][$businessId][$appId] = $result;
        return $result;
    }

    protected function hasTeamApp(int $businessId, int $teamId, int $appId): bool
    {
        if (isset(self::$cache['gappTeam'][$teamId][$appId])) {
            return self::$cache['gappTeam'][$teamId][$appId];
        }
        $result = false;
        try {
            $pdo = Database::getInstance('workz_apps');
            if (!$this->gappHasColumn('cm')) {
                // Sem coluna cm: equipe herda o entitlement da empresa.
                $result = $this->hasBusinessApp($businessId, $appId);
                self::$cache['gappTeam'][$teamId][$appId] = $result;
                return $result;
            }
            $subClause = $this->gappHasColumn('subscription') ? '(st = 1 OR subscription = 1)' : 'st = 1';
            $stmt = $pdo->prepare("SELECT id FROM gapp WHERE em = :em AND cm = :cm AND ap = :ap AND {$subClause} LIMIT 1");
            $stmt->execute([':em' => $businessId, ':cm' => $teamId, ':ap' => $appId]);
            $result = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$result) {
                // Fallback: se a empresa assinou o app, liberar para as equipes.
                $result = $this->hasBusinessApp($businessId, $appId);
            }
        } catch (\Throwable $e) {
            $result = false;
        }
        self::$cache['gappTeam'][$teamId][$appId] = $result;
        return $result;
    }

    protected function hasUserApp(int $userId, int $appId): bool
    {
        if (isset(self::$cache['gappUser'][$userId][$appId])) {
            return self::$cache['gappUser'][$userId][$appId];
        }
        $result = false;
        try {
            $pdo = Database::getInstance('workz_apps');
            $hasCm = $this->gappHasColumn('cm');
            $hasSubscription = $this->gappHasColumn('subscription');
            $subClause = $hasSubscription ? '(st = 1 OR subscription = 1)' : 'st = 1';
            if ($hasCm) {
                $stmt = $pdo->prepare("SELECT id FROM gapp WHERE us = :us AND ap = :ap AND (cm IS NULL OR cm = 0) AND {$subClause} LIMIT 1");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM gapp WHERE us = :us AND ap = :ap AND {$subClause} LIMIT 1");
            }
            $stmt->execute([':us' => $userId, ':ap' => $appId]);
            $result = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $result = false;
        }
        self::$cache['gappUser'][$userId][$appId] = $result;
        return $result;
    }

    protected function getAppPublisherId(int $appId): int
    {
        if (isset(self::$cache['appPublisher'][$appId])) {
            return self::$cache['appPublisher'][$appId];
        }
        $publisher = 0;
        try {
            $pdo = Database::getInstance('workz_apps');
            $stmt = $pdo->prepare('SELECT publisher FROM apps WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $appId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['publisher']) && is_numeric($row['publisher'])) {
                $publisher = (int)$row['publisher'];
            }
        } catch (\Throwable $e) {
            $publisher = 0;
        }
        self::$cache['appPublisher'][$appId] = $publisher;
        return $publisher;
    }

    protected function resolveAppIdBySlug(string $slug): ?int
    {
        if (isset(self::$cache['appIdBySlug'][$slug])) {
            return self::$cache['appIdBySlug'][$slug];
        }
        $id = null;
        try {
            $pdo = Database::getInstance('workz_apps');
            $stmt = $pdo->prepare('SELECT id FROM apps WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['id'])) {
                $id = (int)$row['id'];
            }
        } catch (\Throwable $e) {
            $id = null;
        }
        self::$cache['appIdBySlug'][$slug] = $id;
        return $id;
    }

    private function deriveLegacyTeamRoleFromBusiness(string $businessRole): string
    {
        return match (strtoupper($businessRole)) {
            'OWNER', 'ADMIN' => 'LEAD',
            'MEMBER' => 'MEMBER',
            default => 'GUEST',
        };
    }

    private function deriveBusinessRole(?array $membership): string
    {
        if (!$membership) {
            return 'GUEST';
        }
        $nv = (int)($membership['nv'] ?? 0);
        return match ($nv) {
            4 => 'OWNER',
            3 => 'ADMIN',
            2 => 'MEMBER',
            default => 'GUEST',
        };
    }

    private function deriveTeamRole(?array $membership, ?array $teamRow, int $userId): string
    {
        // Owner of the team is always LEAD
        if ($teamRow && (int)($teamRow['us'] ?? 0) === $userId) {
            return 'LEAD';
        }

        // Moderators list stored as JSON in teams.usmn
        if ($teamRow && !empty($teamRow['usmn'])) {
            try {
                $mods = json_decode($teamRow['usmn'], true);
                if (is_array($mods) && in_array((string)$userId, array_map('strval', $mods), true)) {
                    return 'OPERATOR';
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!$membership) {
            return 'GUEST';
        }

        $nv = (int)($membership['nv'] ?? 0);
        return match ($nv) {
            4 => 'LEAD',
            3 => 'OPERATOR',
            2 => 'MEMBER',
            1 => 'VIEWER',
            default => 'GUEST',
        };
    }
}
