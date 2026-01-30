<?php
// src/Controllers/UserController.php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Core\Database;
use Workz\Platform\Models\General;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;

class GeneralController
{
    use AuthorizationTrait;

    private General $generalModel;

    private array $tableAllowlist = [
        'workz_data' => ['hus', 'hpl', 'usg', 'testimonials', 'billing_payment_methods', 'billing_bank_accounts'],
        'workz_companies' => ['companies', 'employees', 'teams', 'teams_users'],
        'workz_apps' => ['apps', 'gapp', 'storage_kv', 'storage_docs', 'storage_blobs', 'build_queue', 'workz_payments_transactions'],
    ];

    // Compat patch: allow read-only access for select tables without opening writes.
    private array $readOnlyAllowlist = [
        'workz_apps.gapp' => ['id','us','em','cm','ap','st','subscription','start_date','end_date'],
        'workz_apps.workz_payments_transactions' => ['id','user_id','app_id','status','metadata','created_at'],
        'workz_apps.quickapps' => ['id','us','ap','sort','st'],
        'workz_apps.apps' => ['id','tt','im','slug','st','vl','ds','src','embed_url','color','app_type','storage_type','version','publisher','access_level'],
        'workz_companies.employees' => ['id','us','em','nv','st'],
        'workz_companies.teams_users' => ['id','us','cm','nv','st'],
        'workz_companies.teams' => ['id','tt','im','em','us','usmn','st','feed_privacy','cf','un','contacts','url','bk'],
        'workz_companies.companies' => ['id','tt','im','us','usmn','st','feed_privacy','page_privacy','cf','contacts','url','bk','ml','national_id','zip_code','country','state','city','district','address','complement'],
        'workz_data.hpl' => ['id','us','em','cm','tt','ct','dt','im','post_privacy','st'],
        'workz_data.lke' => ['pl','us','dt'],
        'workz_data.hpl_comments' => ['id','pl','us','ds','dt'],
        'workz_data.usg' => ['s0','s1','dt'],
        'workz_data.testimonials' => ['id','author','content','status','recipient','recipient_type','dt'],
    ];

    private array $selfScopedReadable = [
        'workz_apps.gapp' => true,
        'workz_apps.quickapps' => true,
        'workz_companies.employees' => true,
        'workz_companies.teams_users' => true,
        'workz_companies.companies' => true,
        'workz_companies.teams' => true,
        'workz_apps.workz_payments_transactions' => ['userField' => 'user_id'],
    ];

    private array $feedReadable = [
        'workz_data.lke' => true,
        'workz_data.hpl_comments' => true,
    ];

    // Self-scoped writes for user actions (insert/delete only).
    private array $selfScopedWritable = [
        'workz_apps.quickapps' => ['userField' => 'us', 'required' => ['us','ap'], 'allowed' => ['us','ap','sort','st']],
        'workz_apps.gapp' => ['userField' => 'us', 'required' => ['us','ap'], 'allowed' => ['us','ap']],
        'workz_data.lke' => ['userField' => 'us', 'required' => ['us','pl'], 'allowed' => ['pl','us','dt']],
        'workz_data.hpl_comments' => ['userField' => 'us', 'required' => ['us','pl','ds'], 'allowed' => ['id','pl','us','ds','dt']],
        'workz_data.usg' => ['userField' => 's0', 'required' => ['s0','s1'], 'allowed' => ['s0','s1','dt']],
        'workz_data.testimonials' => ['userField' => 'author', 'required' => ['author','content','recipient','recipient_type'], 'allowed' => ['author','content','status','recipient','recipient_type','dt']],
    ];

    // Used to log CRUD denials triggered by AuthorizationTrait.
    private ?array $authzDenyLogContext = null;
    private array $userBusinessCache = [];
    private array $userTeamCache = [];
    private array $companyOwnerCache = [];
    private array $teamRowCache = [];
    private array $employeeRowCache = [];
    private array $testimonialRowCache = [];
    private array $tableColumnCache = [];
    
    public function __construct()
    {
        $this->generalModel = new General();
    }

    private function ensureAuthenticated(?object $payload): int
    {
        $userId = (int)($payload->sub ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['message' => 'Unauthorized', 'status' => 'error']);
            exit();
        }
        return $userId;
    }

    private function isTableAllowed(string $db, string $table): bool
    {
        return isset($this->tableAllowlist[$db]) && in_array($table, $this->tableAllowlist[$db], true);
    }

    protected function normalizeReadColumns(string $operation, string $db, string $table, array $columns): array
    {
        if ($operation !== 'search') {
            return $columns;
        }

        $key = $db . '.' . $table;
        if (!isset($this->readOnlyAllowlist[$key])) {
            return $columns;
        }

        $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
        if (!empty($allowedCols)) {
            $actualCols = $this->getTableColumns($db, $table);
            if (!empty($actualCols)) {
                $allowedCols = array_values(array_intersect($allowedCols, $actualCols));
            }
        }

        if (empty($columns) || in_array('*', $columns, true)) {
            return !empty($allowedCols) ? $allowedCols : $columns;
        }

        return $columns;
    }

    protected function getTableColumns(string $db, string $table): array
    {
        $key = $db . '.' . $table;
        if (isset($this->tableColumnCache[$key])) {
            return $this->tableColumnCache[$key];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return [];
        }

        try {
            $pdo = Database::getInstance($db);
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}`");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $cols = [];
            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $cols[] = $row['Field'];
                }
            }
            $this->tableColumnCache[$key] = $cols;
            return $cols;
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getUserBusinessIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (isset($this->userBusinessCache[$userId])) {
            return $this->userBusinessCache[$userId];
        }

        $ids = [];
        try {
            $rows = $this->generalModel->search(
                'workz_companies',
                'employees',
                ['em'],
                ['us' => $userId, 'st' => 1],
                true
            );
            foreach ($rows ?: [] as $row) {
                if (isset($row['em']) && is_numeric($row['em'])) {
                    $ids[] = (int)$row['em'];
                }
            }
        } catch (\Throwable $e) {
            $ids = [];
        }

        try {
            $owned = $this->generalModel->search(
                'workz_companies',
                'companies',
                ['id'],
                ['us' => $userId, 'st' => 1],
                true
            );
            foreach ($owned ?: [] as $row) {
                if (isset($row['id']) && is_numeric($row['id'])) {
                    $ids[] = (int)$row['id'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));
        $this->userBusinessCache[$userId] = $ids;
        return $ids;
    }

    protected function getUserTeamIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (isset($this->userTeamCache[$userId])) {
            return $this->userTeamCache[$userId];
        }

        $ids = [];
        try {
            $rows = $this->generalModel->search(
                'workz_companies',
                'teams_users',
                ['cm'],
                ['us' => $userId, 'st' => 1],
                true,
                500,
                null,
                null,
                null,
                []
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!isset($row['cm']) || !is_numeric($row['cm'])) {
                        continue;
                    }
                    $ids[] = (int)$row['cm'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $ids = array_values(array_unique($ids));
        $this->userTeamCache[$userId] = $ids;
        return $ids;
    }


    protected function getCompanyOwnerId(int $businessId): ?int
    {
        if ($businessId <= 0) {
            return null;
        }
        if (array_key_exists($businessId, $this->companyOwnerCache)) {
            return $this->companyOwnerCache[$businessId];
        }
        $ownerId = null;
        try {
            $row = $this->generalModel->search(
                'workz_companies',
                'companies',
                ['us'],
                ['id' => $businessId],
                false
            );
            if (is_array($row) && isset($row['us']) && is_numeric($row['us'])) {
                $ownerId = (int)$row['us'];
            }
        } catch (\Throwable $e) {
            $ownerId = null;
        }
        $this->companyOwnerCache[$businessId] = $ownerId;
        return $ownerId;
    }

    protected function getTeamRowById(int $teamId): ?array
    {
        if ($teamId <= 0) {
            return null;
        }
        if (array_key_exists($teamId, $this->teamRowCache)) {
            return $this->teamRowCache[$teamId];
        }
        $row = null;
        try {
            $row = $this->generalModel->search(
                'workz_companies',
                'teams',
                ['id', 'us', 'usmn'],
                ['id' => $teamId],
                false
            );
        } catch (\Throwable $e) {
            $row = null;
        }
        $this->teamRowCache[$teamId] = is_array($row) ? $row : null;
        return $this->teamRowCache[$teamId];
    }

    protected function getEmployeeRowById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (array_key_exists($id, $this->employeeRowCache)) {
            return $this->employeeRowCache[$id];
        }
        $row = null;
        try {
            $row = $this->generalModel->search(
                'workz_companies',
                'employees',
                ['id', 'us', 'st'],
                ['id' => $id],
                false
            );
        } catch (\Throwable $e) {
            $row = null;
        }
        $this->employeeRowCache[$id] = is_array($row) ? $row : null;
        return $this->employeeRowCache[$id];
    }

    protected function getTestimonialRowById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (array_key_exists($id, $this->testimonialRowCache)) {
            return $this->testimonialRowCache[$id];
        }
        $row = null;
        try {
            $row = $this->generalModel->search(
                'workz_data',
                'testimonials',
                ['id', 'recipient', 'recipient_type'],
                ['id' => $id],
                false
            );
        } catch (\Throwable $e) {
            $row = null;
        }
        $this->testimonialRowCache[$id] = is_array($row) ? $row : null;
        return $this->testimonialRowCache[$id];
    }

    protected function isBusinessOwner(int $userId, int $businessId): bool
    {
        $ownerId = $this->getCompanyOwnerId($businessId);
        return $ownerId !== null && $ownerId === $userId;
    }

    protected function isTeamOwnerOrModerator(int $userId, int $teamId): bool
    {
        $team = $this->getTeamRowById($teamId);
        if (!is_array($team)) {
            return false;
        }
        if ((int)($team['us'] ?? 0) === $userId) {
            return true;
        }
        $mods = [];
        $raw = $team['usmn'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $mods = $decoded;
            }
        } elseif (is_array($raw)) {
            $mods = $raw;
        }
        foreach ($mods as $mid) {
            if ((string)$mid === (string)$userId) {
                return true;
            }
        }
        return false;
    }

    protected function validateExistsJoins(string $db, string $table, array $exists): bool
    {
        if (empty($exists)) {
            return true;
        }

        $allowed = [
            'workz_companies.employees' => [
                [ 'db' => 'workz_companies', 'table' => 'companies', 'local' => 'em', 'remote' => 'id', 'conditions' => ['st' => 1] ],
                [ 'db' => 'workz_data', 'table' => 'hus', 'local' => 'us', 'remote' => 'id', 'conditions' => ['st' => 1] ],
            ],
            'workz_companies.teams_users' => [
                [ 'db' => 'workz_companies', 'table' => 'teams', 'local' => 'cm', 'remote' => 'id', 'conditions' => ['st' => 1] ],
                [ 'db' => 'workz_data', 'table' => 'hus', 'local' => 'us', 'remote' => 'id', 'conditions' => ['st' => 1] ],
            ],
            'workz_companies.teams' => [
                [ 'db' => 'workz_companies', 'table' => 'companies', 'local' => 'em', 'remote' => 'id', 'conditions' => ['st' => 1] ],
            ],
            'workz_data.usg' => [
                [ 'db' => 'workz_data', 'table' => 'hus', 'local' => 's1', 'remote' => 'id', 'conditions' => ['st' => 1] ],
            ],
            'workz_data.hpl' => [
                [ 'db' => 'workz_data', 'table' => 'hus', 'local' => 'us', 'remote' => 'id', 'conditions' => ['st' => 1, 'feed_privacy' => 3, 'page_privacy' => 1] ],
            ],
            'workz_apps.gapp' => [
                [ 'db' => 'workz_apps', 'table' => 'apps', 'local' => 'ap', 'remote' => 'id', 'conditions' => [] ],
            ],
        ];

        $key = $db . '.' . $table;
        if (!isset($allowed[$key])) {
            return false;
        }

        foreach ($exists as $ex) {
            $exDb = $ex['db'] ?? $db;
            $exTable = $ex['table'] ?? null;
            $exLocal = $ex['local'] ?? null;
            $exRemote = $ex['remote'] ?? null;
            $exConds = $ex['conditions'] ?? [];

            $match = null;
            foreach ($allowed[$key] as $rule) {
                if ($exDb === $rule['db'] && $exTable === $rule['table'] && $exLocal === $rule['local'] && $exRemote === $rule['remote']) {
                    $match = $rule;
                    break;
                }
            }
            if ($match === null) {
                return false;
            }

            $allowedConds = $match['conditions'] ?? [];
            if (empty($exConds)) {
                if (!empty($allowedConds)) {
                    return false;
                }
            } else {
                foreach ($exConds as $condKey => $condVal) {
                    if (!array_key_exists($condKey, $allowedConds)) {
                        return false;
                    }
                    if ((string)$allowedConds[$condKey] !== (string)$condVal) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    protected function evaluateSelfScopedRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'selfScopedCandidate' => false,
            'mismatchUser' => false,
            'columnsNotAllowed' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!isset($this->selfScopedReadable[$key])) {
            return $result;
        }
        $result['selfScopedCandidate'] = true;

        $userField = 'us';
        $cfg = $this->selfScopedReadable[$key];
        if (is_array($cfg) && !empty($cfg['userField'])) {
            $userField = (string)$cfg['userField'];
        }

        if (empty($conditions)) {
            $result['mismatchUser'] = true;
            return $result;
        }

        $condUser = $conditions[$userField] ?? ($conditions['where'][$userField] ?? null);
        if (is_array($condUser)) {
            $op = $condUser['op'] ?? '=';
            if (!in_array($op, ['=','=='], true)) {
                $result['mismatchUser'] = true;
                return $result;
            }
            $condUser = $condUser['value'] ?? null;
        }
        if (!is_numeric($condUser)) {
            $result['mismatchUser'] = true;
            return $result;
        }
        if ((int)$condUser !== $userId) {
            $result['mismatchUser'] = true;
            return $result;
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            if (empty($columns) || in_array('*', $columns, true)) {
                $result['columnsNotAllowed'] = true;
                return $result;
            }
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluatePublicMembershipRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'publicMembershipCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.employees', 'workz_companies.teams_users'], true)) {
            return $result;
        }
        $result['publicMembershipCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $allowedKeys = ['us', 'st'];
        foreach ($conditions as $k => $v) {
            if ($k === 'where') {
                continue;
            }
            if (!in_array($k, $allowedKeys, true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $condUser = $conditions['us'] ?? ($conditions['where']['us'] ?? null);
        if ($condUser === null) {
            $result['invalidConditions'] = true;
            return $result;
        }
        if (is_array($condUser)) {
            $op = $condUser['op'] ?? '=';
            if (!in_array($op, ['=','=='], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $condUser = $condUser['value'] ?? null;
        }
        if (!is_numeric($condUser)) {
            $result['invalidConditions'] = true;
            return $result;
        }
        if ($userId > 0 && (int)$condUser === $userId) {
            return $result;
        }

        if (array_key_exists('st', $conditions)) {
            if (!is_numeric($conditions['st']) || (int)$conditions['st'] !== 1) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (empty($exists) || !$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            if (empty($columns) || in_array('*', $columns, true)) {
                $result['columnsNotAllowed'] = true;
                return $result;
            }
            $allowedCols = $table === 'employees' ? ['em'] : ['cm'];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateUserMembershipRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'userMembershipCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
            'requiresAuth' => false,
            'action' => null,
            'ctx' => null,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.employees', 'workz_companies.teams_users'], true)) {
            return $result;
        }
        $result['userMembershipCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $idKey = $table === 'employees' ? 'em' : 'cm';
        $allowedKeys = ['us', $idKey, 'st'];
        foreach ($conditions as $k => $v) {
            if ($k === 'where') {
                continue;
            }
            if (!in_array($k, $allowedKeys, true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $hasScope = false;
        $condUser = $conditions['us'] ?? ($conditions['where']['us'] ?? null);
        if ($condUser !== null) {
            $hasScope = true;
            if (is_array($condUser)) {
                $op = $condUser['op'] ?? '=';
                if (!in_array($op, ['=','=='], true)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                $condUser = $condUser['value'] ?? null;
            }
            if (!is_numeric($condUser)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if ($userId > 0 && (int)$condUser !== $userId) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $condScope = $conditions[$idKey] ?? null;
        $scopeValues = [];
        if ($condScope !== null) {
            $hasScope = true;
            if (is_array($condScope)) {
                $op = strtoupper((string)($condScope['op'] ?? ''));
                if ($op !== 'IN' || !is_array($condScope['value'] ?? null) || count($condScope['value']) > 200) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($condScope['value'] as $v) {
                    if (!is_numeric($v)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    $scopeValues[] = (int)$v;
                }
            } elseif (is_numeric($condScope)) {
                $scopeValues[] = (int)$condScope;
            } else {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!$hasScope) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (array_key_exists('st', $conditions)) {
            if (!is_numeric($conditions['st'])) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $stVal = (int)$conditions['st'];
            if (!in_array($stVal, [0, 1], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if ($condUser === null && !empty($scopeValues)) {
            if ($userId <= 0) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if ($table === 'employees') {
                $bizSet = array_flip($this->getUserBusinessIds($userId));
                foreach ($scopeValues as $emId) {
                    if ($emId <= 0) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    if (!isset($bizSet[$emId]) && !$this->isBusinessOwner($userId, $emId)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
                if (!array_key_exists('st', $conditions)) {
                    if (count($scopeValues) !== 1) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    $result['requiresAuth'] = true;
                    $result['action'] = 'business.manage_teams';
                    $result['ctx'] = ['em' => $scopeValues[0]];
                }
            } else {
                $teamSet = array_flip($this->getUserTeamIds($userId));
                foreach ($scopeValues as $cmId) {
                    if ($cmId <= 0) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    if (!isset($teamSet[$cmId]) && !$this->isTeamOwnerOrModerator($userId, $cmId)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
                if (!array_key_exists('st', $conditions)) {
                    if (count($scopeValues) !== 1) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    $result['requiresAuth'] = true;
                    $result['action'] = 'team.manage_settings';
                    $result['ctx'] = ['cm' => $scopeValues[0]];
                }
            }
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            if (empty($columns) || in_array('*', $columns, true)) {
                $result['columnsNotAllowed'] = true;
                return $result;
            }
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        if ($result['requiresAuth']) {
            return $result;
        }

        $result['allowed'] = true;
        return $result;
    }


    protected function evaluateFeedRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'feedCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!isset($this->feedReadable[$key])) {
            return $result;
        }
        $result['feedCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $pl = $conditions['pl'] ?? null;
        if (!is_array($pl) || strtoupper((string)($pl['op'] ?? '')) !== 'IN' || !is_array($pl['value'] ?? null)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $values = $pl['value'];
        if (count($values) > 200) {
            $result['invalidConditions'] = true;
            return $result;
        }
        foreach ($values as $v) {
            if (!is_numeric($v)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            if (empty($columns) || in_array('*', $columns, true)) {
                $result['columnsNotAllowed'] = true;
                return $result;
            }
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateAppRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        $result = [
            'allowed' => false,
            'appCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_apps.apps') {
            return $result;
        }
        $result['appCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $allowedKeys = ['id','slug','st'];
        foreach ($conditions as $k => $v) {
            if (!in_array($k, $allowedKeys, true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $idCond = $conditions['id'] ?? null;
        $slugCond = $conditions['slug'] ?? null;
        $idOk = false;
        if (is_array($idCond)) {
            $op = strtoupper((string)($idCond['op'] ?? ''));
            if ($op === 'IN' && is_array($idCond['value'] ?? null)) {
                $values = $idCond['value'];
                if (!empty($values) && count($values) <= 200 && !array_filter($values, fn($v) => !is_numeric($v))) {
                    $idOk = true;
                }
            }
        } elseif (is_numeric($idCond)) {
            $idOk = true;
        }

        $slugOk = false;
        if (is_string($slugCond)) {
            $slug = trim($slugCond);
            if ($slug !== '') {
                $slugOk = true;
            }
        }

        if (!$idOk && !$slugOk) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateGappMemberRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'gappMemberCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'mismatchUser' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_apps.gapp') {
            return $result;
        }
        $result['gappMemberCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (array_key_exists('us', $conditions) || array_key_exists('cm', $conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $allowedKeys = ['em','st','ap','subscription'];
        foreach ($conditions as $k => $v) {
            if (!in_array($k, $allowedKeys, true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (array_key_exists('st', $conditions)) {
            if (!is_numeric($conditions['st'])) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $stVal = (int)$conditions['st'];
            if (!in_array($stVal, [0, 1], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (array_key_exists('subscription', $conditions)) {
            if (!is_numeric($conditions['subscription'])) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $subVal = (int)$conditions['subscription'];
            if (!in_array($subVal, [0, 1], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!array_key_exists('st', $conditions) && !array_key_exists('subscription', $conditions) && !array_key_exists('ap', $conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $emCond = $conditions['em'] ?? null;
        $emValues = [];
        if (is_array($emCond)) {
            $op = strtoupper((string)($emCond['op'] ?? ''));
            if ($op !== 'IN' || !is_array($emCond['value'] ?? null)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $values = $emCond['value'];
            if (empty($values) || count($values) > 500) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($values as $v) {
                if (!is_numeric($v)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                $emValues[] = (int)$v;
            }
        } elseif (is_numeric($emCond)) {
            $emValues[] = (int)$emCond;
        } else {
            $result['invalidConditions'] = true;
            return $result;
        }

        $apCond = $conditions['ap'] ?? null;
        if ($apCond !== null) {
            if (is_array($apCond)) {
                $op = strtoupper((string)($apCond['op'] ?? ''));
                if ($op !== 'IN' || !is_array($apCond['value'] ?? null) || count($apCond['value']) > 500) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($apCond['value'] as $v) {
                    if (!is_numeric($v)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            } elseif (!is_numeric($apCond)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $userBusinesses = $this->getUserBusinessIds($userId);
        $bizSet = array_flip($userBusinesses);
        foreach ($emValues as $emId) {
            if ($emId <= 0) {
                $result['mismatchUser'] = true;
                return $result;
            }
            if (!isset($bizSet[$emId]) && !$this->isBusinessOwner($userId, $emId)) {
                $result['mismatchUser'] = true;
                return $result;
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluatePostRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'postCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_data.hpl') {
            return $result;
        }
        $result['postCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!isset($conditions['st'])) {
            if ($operation === 'count' && !array_key_exists('_or', $conditions)) {
                $hasScope = false;
                $allNonPositive = true;
                foreach (['us','em','cm'] as $k) {
                    if (!array_key_exists($k, $conditions)) {
                        continue;
                    }
                    $hasScope = true;
                    $val = $conditions[$k];
                    if (is_array($val)) {
                        $op = strtoupper((string)($val['op'] ?? ''));
                        if ($op !== 'IN' || !is_array($val['value'] ?? null)) {
                            $result['invalidConditions'] = true;
                            return $result;
                        }
                        foreach ($val['value'] as $v) {
                            if (!is_numeric($v)) {
                                $result['invalidConditions'] = true;
                                return $result;
                            }
                            if ((int)$v > 0) {
                                $allNonPositive = false;
                            }
                        }
                    } elseif (is_numeric($val)) {
                        if ((int)$val > 0) {
                            $allNonPositive = false;
                        }
                    } else {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
                if ($hasScope && $allNonPositive) {
                    if (!$this->validateExistsJoins($db, $table, $exists)) {
                        $result['existsNotAllowed'] = true;
                        return $result;
                    }
                    $result['allowed'] = true;
                    return $result;
                }
            }
            $result['invalidConditions'] = true;
            return $result;
        }

        if ((int)$conditions['st'] !== 1) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        $hasScope = false;
        $scopeKeys = ['us','em','cm'];
        foreach ($scopeKeys as $k) {
            if (!array_key_exists($k, $conditions)) {
                continue;
            }
            $hasScope = true;
            $val = $conditions[$k];
            if (is_array($val)) {
                $op = strtoupper((string)($val['op'] ?? ''));
                if ($op !== 'IN' || !is_array($val['value'] ?? null) || count($val['value']) > 1000) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($val['value'] as $v) {
                    if (!is_numeric($v)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            } elseif (!is_numeric($val)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $orBlocks = $conditions['_or'] ?? null;
        if ($orBlocks !== null) {
            if (!is_array($orBlocks) || empty($orBlocks)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($orBlocks as $block) {
                if (!is_array($block) || empty($block)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($block as $bk => $bv) {
                    if (!in_array($bk, $scopeKeys, true)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                    if (is_array($bv)) {
                        $op = strtoupper((string)($bv['op'] ?? ''));
                        if ($op !== 'IN' || !is_array($bv['value'] ?? null) || count($bv['value']) > 1000) {
                            $result['invalidConditions'] = true;
                            return $result;
                        }
                        foreach ($bv['value'] as $v) {
                            if (!is_numeric($v)) {
                                $result['invalidConditions'] = true;
                                return $result;
                            }
                        }
                    } elseif (!is_numeric($bv)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            }
            $hasScope = true;
        }

        if (!$hasScope) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateUsgRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'usgCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_data.usg') {
            return $result;
        }
        $result['usgCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $hasScope = false;
        foreach (['s0','s1'] as $k) {
            if (!array_key_exists($k, $conditions)) {
                continue;
            }
            $hasScope = true;
            $val = $conditions[$k];
            if (is_array($val)) {
                $op = strtoupper((string)($val['op'] ?? ''));
                if ($op !== 'IN' || !is_array($val['value'] ?? null) || count($val['value']) > 200) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($val['value'] as $v) {
                    if (!is_numeric($v)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            } elseif (!is_numeric($val)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!$hasScope) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateTestimonialsRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        $result = [
            'allowed' => false,
            'testimonialsCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_data.testimonials') {
            return $result;
        }
        $result['testimonialsCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!isset($conditions['recipient']) || !isset($conditions['recipient_type'])) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!is_numeric($conditions['recipient'])) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateNicknameRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        $result = [
            'allowed' => false,
            'nicknameCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.companies', 'workz_companies.teams'], true)) {
            return $result;
        }
        $result['nicknameCandidate'] = true;

        if (empty($conditions) || !isset($conditions['un'])) {
            $result['invalidConditions'] = true;
            return $result;
        }

        foreach ($conditions as $k => $v) {
            if ($k !== 'un') {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!is_string($conditions['un'])) {
            $result['invalidConditions'] = true;
            return $result;
        }
        $nickname = trim($conditions['un']);
        if ($nickname === '' || strlen($nickname) > 80) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = ['id','un'];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }


    protected function evaluatePublicListRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        $result = [
            'allowed' => false,
            'publicCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'existsNotAllowed' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.companies', 'workz_companies.teams'], true)) {
            return $result;
        }
        $result['publicCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (!isset($conditions['st']) || (int)$conditions['st'] !== 1) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $allowedKeys = $key === 'workz_companies.teams' ? ['st','em','us','id','tt'] : ['st','us','id','tt'];
        foreach ($conditions as $k => $v) {
            if (!in_array($k, $allowedKeys, true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if ($k === 'tt') {
                if (!is_array($v)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                $op = strtoupper((string)($v['op'] ?? ''));
                $val = $v['value'] ?? null;
                if ($op !== 'LIKE' || !is_string($val)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                $val = trim($val);
                if ($val === '' || strlen($val) > 120) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                continue;
            }
            if (is_array($v)) {
                $op = strtoupper((string)($v['op'] ?? ''));
                if ($op !== 'IN' || !is_array($v['value'] ?? null) || count($v['value']) > 200) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
                foreach ($v['value'] as $vv) {
                    if (!is_numeric($vv)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            } elseif (!is_numeric($v)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        if (!$this->validateExistsJoins($db, $table, $exists)) {
            $result['existsNotAllowed'] = true;
            return $result;
        }

        if ($operation === 'search') {
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateLimitedRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        $result = [
            'allowed' => false,
            'limitedCandidate' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['search','count'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.teams', 'workz_companies.companies'], true)) {
            return $result;
        }
        $result['limitedCandidate'] = true;

        if (empty($conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $idCond = $conditions['id'] ?? null;
        if (is_array($idCond)) {
            if (strtoupper((string)($idCond['op'] ?? '')) !== 'IN' || !is_array($idCond['value'] ?? null)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $values = $idCond['value'];
            if (count($values) > 200) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($values as $v) {
                if (!is_numeric($v)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
            }
        } elseif (!is_numeric($idCond)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'search') {
            if (empty($columns) || in_array('*', $columns, true)) {
                $result['columnsNotAllowed'] = true;
                return $result;
            }
            $allowedCols = $this->readOnlyAllowlist[$key] ?? [];
            foreach ($columns as $col) {
                if (!in_array($col, $allowedCols, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateSelfScopedWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        $result = [
            'allowed' => false,
            'writeCandidate' => false,
            'mismatchUser' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['insert','delete'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!isset($this->selfScopedWritable[$key])) {
            return $result;
        }
        $result['writeCandidate'] = true;

        $cfg = $this->selfScopedWritable[$key];
        $userField = $cfg['userField'] ?? 'us';
        $required = $cfg['required'] ?? [];
        $allowed = $cfg['allowed'] ?? [];

        if ($operation === 'insert') {
            foreach ($required as $req) {
                if (!array_key_exists($req, $data)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
            }
            if (!is_numeric($data[$userField] ?? null) || (int)$data[$userField] !== $userId) {
                $result['mismatchUser'] = true;
                return $result;
            }
            foreach ($data as $k => $v) {
                if (!in_array($k, $allowed, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        } else {
            foreach ($required as $req) {
                if (!array_key_exists($req, $conditions)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
            }
            if (!is_numeric($conditions[$userField] ?? null) || (int)$conditions[$userField] !== $userId) {
                $result['mismatchUser'] = true;
                return $result;
            }
            foreach ($conditions as $k => $v) {
                if (!in_array($k, $allowed, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
            if ($key === 'workz_apps.gapp' && (!empty($conditions['em']) || !empty($conditions['cm']))) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateMembershipRequestWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        $result = [
            'allowed' => false,
            'membershipWriteCandidate' => false,
            'mismatchUser' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['insert','update','delete'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if (!in_array($key, ['workz_companies.employees', 'workz_companies.teams_users'], true)) {
            return $result;
        }
        $result['membershipWriteCandidate'] = true;

        $idField = $table === 'employees' ? 'em' : 'cm';
        $allowedKeys = [$idField, 'us', 'st', 'nv', 'dt'];

        if ($operation === 'insert') {
            if (!array_key_exists('us', $data) || !array_key_exists($idField, $data)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if (!is_numeric($data['us'] ?? null) || (int)$data['us'] !== $userId) {
                $result['mismatchUser'] = true;
                return $result;
            }
            if (!is_numeric($data[$idField] ?? null)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($data as $k => $v) {
                if (!in_array($k, $allowedKeys, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
            $st = isset($data['st']) ? (int)$data['st'] : 0;
            if (!in_array($st, [0, 1], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $hasNv = array_key_exists('nv', $data);
            if ($st === 1 || $hasNv) {
                $targetId = (int)$data[$idField];
                if ($table === 'employees') {
                    if (!$this->isBusinessOwner($userId, $targetId)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                } else {
                    if (!$this->isTeamOwnerOrModerator($userId, $targetId)) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            }
            $result['allowed'] = true;
            return $result;
        }

        if ($operation === 'update') {
            if (!array_key_exists('us', $conditions) || !array_key_exists($idField, $conditions)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if (!is_numeric($conditions['us'] ?? null) || (int)$conditions['us'] !== $userId) {
                $result['mismatchUser'] = true;
                return $result;
            }
            if (!is_numeric($conditions[$idField] ?? null)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($conditions as $k => $v) {
                if (!in_array($k, ['us', $idField, 'st'], true)) {
                    $result['invalidConditions'] = true;
                    return $result;
                }
            }
            foreach ($data as $k => $v) {
                if (!in_array($k, ['st'], true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
            if (!isset($data['st']) || (int)$data['st'] !== 0) {
                $result['invalidConditions'] = true;
                return $result;
            }
            $result['allowed'] = true;
            return $result;
        }

        if (!array_key_exists('us', $conditions) || !array_key_exists($idField, $conditions)) {
            $result['invalidConditions'] = true;
            return $result;
        }
        if (!is_numeric($conditions['us'] ?? null) || (int)$conditions['us'] !== $userId) {
            $result['mismatchUser'] = true;
            return $result;
        }
        if (!is_numeric($conditions[$idField] ?? null)) {
            $result['invalidConditions'] = true;
            return $result;
        }
        foreach ($conditions as $k => $v) {
            if (!in_array($k, ['us', $idField, 'st'], true)) {
                $result['invalidConditions'] = true;
                return $result;
            }
        }
        if (!isset($conditions['st']) || (int)$conditions['st'] !== 0) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateEmployeeSelfWriteById(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        $result = [
            'allowed' => false,
            'employeeSelfCandidate' => false,
            'mismatchUser' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
        ];

        if (!in_array($operation, ['update','delete'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_companies.employees') {
            return $result;
        }
        $result['employeeSelfCandidate'] = true;

        if (empty($conditions) || !isset($conditions['id']) || !is_numeric($conditions['id'])) {
            $result['invalidConditions'] = true;
            return $result;
        }
        if (count($conditions) > 1) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $row = $this->getEmployeeRowById((int)$conditions['id']);
        if (!is_array($row) || !isset($row['us'])) {
            $result['invalidConditions'] = true;
            return $result;
        }
        if ((int)$row['us'] !== $userId) {
            $result['mismatchUser'] = true;
            return $result;
        }

        if ($operation === 'update') {
            if (empty($data)) {
                $result['invalidConditions'] = true;
                return $result;
            }
            foreach ($data as $k => $v) {
                if (in_array($k, ['us','em','nv'], true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
                if ($k === 'st') {
                    $currentSt = (int)($row['st'] ?? 0);
                    $nextSt = (int)$v;
                    if ($currentSt === 0 && $nextSt === 1) {
                        $result['invalidConditions'] = true;
                        return $result;
                    }
                }
            }
        }

        $result['allowed'] = true;
        return $result;
    }

    protected function evaluateCompanyWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        $result = [
            'allowed' => false,
            'companyWriteCandidate' => false,
            'mismatchUser' => false,
            'columnsNotAllowed' => false,
            'invalidConditions' => false,
            'requiresAuth' => false,
            'action' => null,
            'ctx' => null,
        ];

        if (!in_array($operation, ['insert','update','delete'], true)) {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_companies.companies') {
            return $result;
        }
        $result['companyWriteCandidate'] = true;

        $allowedFields = [
            'tt','us','st','dt','ml','national_id','cf','un','page_privacy','feed_privacy','zip_code',
            'country','state','city','district','address','complement','contacts','bk','im',
        ];

        if ($operation === 'insert') {
            if (!array_key_exists('us', $data) || !is_numeric($data['us'])) {
                $result['invalidConditions'] = true;
                return $result;
            }
            if ((int)$data['us'] !== $userId) {
                $result['mismatchUser'] = true;
                return $result;
            }
            foreach ($data as $k => $v) {
                if (!in_array($k, $allowedFields, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
            $result['allowed'] = true;
            return $result;
        }

        if (!isset($conditions['id']) || !is_numeric($conditions['id'])) {
            $result['invalidConditions'] = true;
            return $result;
        }
        $companyId = (int)$conditions['id'];
        if ($companyId <= 0) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if ($operation === 'update') {
            foreach ($data as $k => $v) {
                if (!in_array($k, $allowedFields, true)) {
                    $result['columnsNotAllowed'] = true;
                    return $result;
                }
            }
        }

        if ($this->isBusinessOwner($userId, $companyId)) {
            $result['allowed'] = true;
            return $result;
        }

        $result['requiresAuth'] = true;
        $result['action'] = 'business.manage_teams';
        $result['ctx'] = ['em' => $companyId];
        return $result;
    }

    protected function evaluateTestimonialsWrite(string $operation, string $db, string $table, array $data, array $conditions, array $user): array
    {
        $result = [
            'allowed' => false,
            'testimonialWriteCandidate' => false,
            'invalidConditions' => false,
            'requiresAuth' => false,
            'action' => null,
            'ctx' => null,
        ];

        if ($operation !== 'update') {
            return $result;
        }

        $key = $db . '.' . $table;
        if ($key !== 'workz_data.testimonials') {
            return $result;
        }
        $result['testimonialWriteCandidate'] = true;

        if (!isset($conditions['id']) || !is_numeric($conditions['id'])) {
            $result['invalidConditions'] = true;
            return $result;
        }
        $status = $data['status'] ?? null;
        if ($status === null || !is_numeric($status) || !in_array((int)$status, [0,1,2], true)) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $row = $this->getTestimonialRowById((int)$conditions['id']);
        if (!is_array($row) || !isset($row['recipient'], $row['recipient_type'])) {
            $result['invalidConditions'] = true;
            return $result;
        }

        $recipientId = (int)$row['recipient'];
        $recipientType = strtolower((string)$row['recipient_type']);
        $userId = (int)($user['id'] ?? $user['sub'] ?? 0);

        if ($recipientId <= 0) {
            $result['invalidConditions'] = true;
            return $result;
        }

        if (in_array($recipientType, ['people','profile','user'], true)) {
            if ($recipientId === $userId) {
                $result['allowed'] = true;
            } else {
                $result['invalidConditions'] = true;
            }
            return $result;
        }

        if (in_array($recipientType, ['businesses','business','company','companies'], true)) {
            $result['requiresAuth'] = true;
            $result['action'] = 'business.manage_teams';
            $result['ctx'] = ['em' => $recipientId];
            return $result;
        }

        if (in_array($recipientType, ['teams','team'], true)) {
            $result['requiresAuth'] = true;
            $result['action'] = 'team.manage_settings';
            $result['ctx'] = ['cm' => $recipientId];
            return $result;
        }

        $result['invalidConditions'] = true;
        return $result;
    }

    private function logCrudDeny(array $context): void
    {
        error_log('general_crud_forbidden: ' . json_encode($context));
    }

    private function enforceCrudAuthorization(?object $payload, string $operation, string $db, string $table, array $data = [], array $conditions = [], ?string $authAction = null, ?array $authCtx = null, ?array $columns = null, ?array $exists = null): void
    {
        $user = $this->currentUserFromPayload($payload);
        $operation = strtolower($operation);
        $hasAction = $authAction !== null;
        $hasCtx = is_array($authCtx);
        $columnsList = is_array($columns) ? $columns : [];
        $existsList = is_array($exists) ? $exists : [];
        $tableKey = $db . '.' . $table;
        $isReadOp = in_array($operation, ['search','count'], true);
        $isWriteOp = in_array($operation, ['insert','update','delete'], true);
        $isReadOnlyTable = isset($this->readOnlyAllowlist[$tableKey]);
        $isWritableTable = isset($this->selfScopedWritable[$tableKey]);
        $tableAllowed = $this->isTableAllowed($db, $table);

        if (!$tableAllowed && !($isReadOp && $isReadOnlyTable) && !($isWriteOp && $isWritableTable)) {
            $this->logCrudDeny([
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => false,
                'mismatchUser' => false,
                'columnsNotAllowed' => false,
                'existsNotAllowed' => false,
            ]);
            http_response_code(403);
            echo json_encode(['message' => 'Tabela não permitida', 'status' => 'error']);
            exit();
        }

        // Sensíveis: employees, teams_users, gapp, apps, billing_*
        $sensitive = ['employees', 'teams_users', 'teams', 'companies', 'gapp', 'apps', 'storage_kv', 'storage_docs', 'storage_blobs', 'build_queue', 'billing_payment_methods', 'billing_bank_accounts', 'workz_payments_transactions'];
        if (!in_array($table, $sensitive, true) && !$isReadOnlyTable) {
            return;
        }

        $selfCheck = $this->evaluateSelfScopedRead($operation, $db, $table, $conditions, $columnsList, (int)($user['id'] ?? 0), $existsList);
        $publicMembershipCheck = $this->evaluatePublicMembershipRead($operation, $db, $table, $conditions, $columnsList, (int)($user['id'] ?? 0), $existsList);
        $userMembershipCheck = $this->evaluateUserMembershipRead($operation, $db, $table, $conditions, $columnsList, (int)($user['id'] ?? 0), $existsList);
        $feedCheck = $this->evaluateFeedRead($operation, $db, $table, $conditions, $columnsList, $existsList);
        $postCheck = $this->evaluatePostRead($operation, $db, $table, $conditions, $columnsList, $existsList);
        $usgCheck = $this->evaluateUsgRead($operation, $db, $table, $conditions, $columnsList, $existsList);
        $publicCheck = $this->evaluatePublicListRead($operation, $db, $table, $conditions, $columnsList, $existsList);
        $limitedCheck = $this->evaluateLimitedRead($operation, $db, $table, $conditions, $columnsList);
        $appCheck = $this->evaluateAppRead($operation, $db, $table, $conditions, $columnsList);
        $testimonialsCheck = $this->evaluateTestimonialsRead($operation, $db, $table, $conditions, $columnsList);
        $nicknameCheck = $this->evaluateNicknameRead($operation, $db, $table, $conditions, $columnsList);
        $gappMemberCheck = $this->evaluateGappMemberRead($operation, $db, $table, $conditions, $columnsList, (int)($user['id'] ?? 0), $existsList);
        $writeCheck = $this->evaluateSelfScopedWrite($operation, $db, $table, $data, $conditions, (int)($user['id'] ?? 0));
        $membershipWriteCheck = $this->evaluateMembershipRequestWrite($operation, $db, $table, $data, $conditions, (int)($user['id'] ?? 0));
        $employeeSelfWriteCheck = $this->evaluateEmployeeSelfWriteById($operation, $db, $table, $data, $conditions, (int)($user['id'] ?? 0));
        $companyWriteCheck = $this->evaluateCompanyWrite($operation, $db, $table, $data, $conditions, (int)($user['id'] ?? 0));
        $testimonialWriteCheck = $this->evaluateTestimonialsWrite($operation, $db, $table, $data, $conditions, $user);
        if ($selfCheck['allowed']) {
            return;
        }
        if ($publicMembershipCheck['allowed']) {
            return;
        }
        if ($userMembershipCheck['allowed']) {
            return;
        }
        if (!empty($userMembershipCheck['requiresAuth']) && !empty($userMembershipCheck['action']) && is_array($userMembershipCheck['ctx'] ?? null)) {
            $this->authzDenyLogContext = [
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
                'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
                'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
                'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
                'publicMembershipCandidate' => $publicMembershipCheck['publicMembershipCandidate'] ?? false,
                'publicMembershipInvalid' => $publicMembershipCheck['invalidConditions'] ?? false,
                'publicMembershipColumnsNotAllowed' => $publicMembershipCheck['columnsNotAllowed'] ?? false,
                'publicMembershipExistsNotAllowed' => $publicMembershipCheck['existsNotAllowed'] ?? false,
                'userMembershipCandidate' => $userMembershipCheck['userMembershipCandidate'] ?? false,
                'feedCandidate' => $feedCheck['feedCandidate'] ?? false,
                'postCandidate' => $postCheck['postCandidate'] ?? false,
                'usgCandidate' => $usgCheck['usgCandidate'] ?? false,
                'publicCandidate' => $publicCheck['publicCandidate'] ?? false,
                'limitedCandidate' => $limitedCheck['limitedCandidate'] ?? false,
                'appCandidate' => $appCheck['appCandidate'] ?? false,
                'testimonialsCandidate' => $testimonialsCheck['testimonialsCandidate'] ?? false,

                'nicknameCandidate' => $nicknameCheck['nicknameCandidate'] ?? false,
                'gappMemberCandidate' => $gappMemberCheck['gappMemberCandidate'] ?? false,
                'writeCandidate' => $writeCheck['writeCandidate'] ?? false,
                'membershipWriteCandidate' => $membershipWriteCheck['membershipWriteCandidate'] ?? false,
                'employeeSelfCandidate' => $employeeSelfWriteCheck['employeeSelfCandidate'] ?? false,
                'companyWriteCandidate' => $companyWriteCheck['companyWriteCandidate'] ?? false,
                'testimonialWriteCandidate' => $testimonialWriteCheck['testimonialWriteCandidate'] ?? false,
            ];
            $this->authorize($userMembershipCheck['action'], $userMembershipCheck['ctx'], $payload);
            return;
        }

        if ($feedCheck['allowed']) {
            return;
        }
        if ($postCheck['allowed']) {
            return;
        }
        if ($usgCheck['allowed']) {
            return;
        }
        if ($publicCheck['allowed']) {
            return;
        }
        if ($limitedCheck['allowed']) {
            return;
        }
        if ($appCheck['allowed']) {
            return;
        }
        if ($nicknameCheck['allowed']) {
            return;
        }
        if ($testimonialsCheck['allowed']) {
            return;
        }
        if ($gappMemberCheck['allowed']) {
            return;
        }
        if ($writeCheck['allowed']) {
            return;
        }
        if ($membershipWriteCheck['allowed']) {
            return;
        }
        if ($employeeSelfWriteCheck['allowed']) {
            return;
        }
        if ($companyWriteCheck['allowed']) {
            return;
        }
        if (!empty($companyWriteCheck['requiresAuth']) && !empty($companyWriteCheck['action']) && is_array($companyWriteCheck['ctx'] ?? null)) {
            $this->authzDenyLogContext = [
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
                'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
                'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
                'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
                'publicMembershipCandidate' => $publicMembershipCheck['publicMembershipCandidate'] ?? false,
                'publicMembershipInvalid' => $publicMembershipCheck['invalidConditions'] ?? false,
                'publicMembershipColumnsNotAllowed' => $publicMembershipCheck['columnsNotAllowed'] ?? false,
                'publicMembershipExistsNotAllowed' => $publicMembershipCheck['existsNotAllowed'] ?? false,
                'userMembershipCandidate' => $userMembershipCheck['userMembershipCandidate'] ?? false,
                'feedCandidate' => $feedCheck['feedCandidate'] ?? false,
                'postCandidate' => $postCheck['postCandidate'] ?? false,
                'usgCandidate' => $usgCheck['usgCandidate'] ?? false,
                'publicCandidate' => $publicCheck['publicCandidate'] ?? false,
                'limitedCandidate' => $limitedCheck['limitedCandidate'] ?? false,
                'appCandidate' => $appCheck['appCandidate'] ?? false,
                'testimonialsCandidate' => $testimonialsCheck['testimonialsCandidate'] ?? false,

                'nicknameCandidate' => $nicknameCheck['nicknameCandidate'] ?? false,
                'gappMemberCandidate' => $gappMemberCheck['gappMemberCandidate'] ?? false,
                'writeCandidate' => $writeCheck['writeCandidate'] ?? false,
                'membershipWriteCandidate' => $membershipWriteCheck['membershipWriteCandidate'] ?? false,
                'employeeSelfCandidate' => $employeeSelfWriteCheck['employeeSelfCandidate'] ?? false,
                'companyWriteCandidate' => $companyWriteCheck['companyWriteCandidate'] ?? false,
                'testimonialWriteCandidate' => $testimonialWriteCheck['testimonialWriteCandidate'] ?? false,
            ];
            $this->authorize($companyWriteCheck['action'], $companyWriteCheck['ctx'], $payload);
            return;
        }
        if ($testimonialWriteCheck['allowed']) {
            return;
        }
        if (!empty($testimonialWriteCheck['requiresAuth']) && !empty($testimonialWriteCheck['action']) && is_array($testimonialWriteCheck['ctx'] ?? null)) {
            $this->authzDenyLogContext = [
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
                'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
                'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
                'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
                'publicMembershipCandidate' => $publicMembershipCheck['publicMembershipCandidate'] ?? false,
                'publicMembershipInvalid' => $publicMembershipCheck['invalidConditions'] ?? false,
                'publicMembershipColumnsNotAllowed' => $publicMembershipCheck['columnsNotAllowed'] ?? false,
                'publicMembershipExistsNotAllowed' => $publicMembershipCheck['existsNotAllowed'] ?? false,
                'userMembershipCandidate' => $userMembershipCheck['userMembershipCandidate'] ?? false,
                'feedCandidate' => $feedCheck['feedCandidate'] ?? false,
                'postCandidate' => $postCheck['postCandidate'] ?? false,
                'usgCandidate' => $usgCheck['usgCandidate'] ?? false,
                'publicCandidate' => $publicCheck['publicCandidate'] ?? false,
                'limitedCandidate' => $limitedCheck['limitedCandidate'] ?? false,
                'appCandidate' => $appCheck['appCandidate'] ?? false,
                'testimonialsCandidate' => $testimonialsCheck['testimonialsCandidate'] ?? false,

                'nicknameCandidate' => $nicknameCheck['nicknameCandidate'] ?? false,
                'gappMemberCandidate' => $gappMemberCheck['gappMemberCandidate'] ?? false,
                'writeCandidate' => $writeCheck['writeCandidate'] ?? false,
                'membershipWriteCandidate' => $membershipWriteCheck['membershipWriteCandidate'] ?? false,
                'employeeSelfCandidate' => $employeeSelfWriteCheck['employeeSelfCandidate'] ?? false,
                'companyWriteCandidate' => $companyWriteCheck['companyWriteCandidate'] ?? false,
                'testimonialWriteCandidate' => $testimonialWriteCheck['testimonialWriteCandidate'] ?? false,
            ];
            $this->authorize($testimonialWriteCheck['action'], $testimonialWriteCheck['ctx'], $payload);
            return;
        }

        $action = null;
        $ctx = [];
        if ($authAction && is_array($authCtx)) {
            $action = $authAction;
            $ctx = $authCtx;
        } elseif (in_array($table, ['storage_kv', 'storage_docs', 'storage_blobs', 'build_queue'], true)) {
            $this->logCrudDeny([
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
                'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
                'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
                'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
            ]);
            http_response_code(403);
            echo json_encode(['message' => 'Ação/ctx obrigatórios', 'status' => 'error']);
            exit();
        } elseif ($table === 'employees') {
            $action = 'business.manage_teams';
            $ctx['em'] = $data['em'] ?? $conditions['em'] ?? null;
        } elseif ($table === 'teams_users') {
            $isRead = in_array($operation, ['search','count'], true);
            $action = $isRead ? 'team.manage_settings' : 'team.set_roles';
            $ctx['cm'] = $data['cm'] ?? $conditions['cm'] ?? null;
            $ctx['em'] = $data['em'] ?? $conditions['em'] ?? null;
        } elseif ($table === 'teams') {
            $teamId = $data['id'] ?? $conditions['id'] ?? null;
            if ($teamId) {
                $action = 'team.manage_settings';
                $ctx['cm'] = (int)$teamId;
            } else {
                $action = 'business.manage_teams';
                $ctx['em'] = $data['em'] ?? $conditions['em'] ?? null;
            }
        } elseif ($table === 'gapp') {
            $hasTeam = array_key_exists('cm', $data) ? $data['cm'] : ($conditions['cm'] ?? null);
            if (!empty($hasTeam)) {
                $action = 'team.manage_settings';
                $ctx['cm'] = (int)$hasTeam;
            } else {
                $action = 'business.manage_apps';
            }
            $ctx['em'] = $data['em'] ?? $conditions['em'] ?? null;
            $ctx['ap'] = $data['ap'] ?? $conditions['ap'] ?? null;
        } elseif ($table === 'apps') {
            $action = 'business.manage_apps';
            $ctx['em'] = $data['publisher'] ?? $conditions['publisher'] ?? $data['em'] ?? $conditions['em'] ?? null;
        } elseif (strncmp($table, 'billing_', 8) === 0) {
            $entityType = $data['entity_type'] ?? $conditions['entity_type'] ?? null;
            $entityId = $data['entity_id'] ?? $conditions['entity_id'] ?? null;
            if ($entityType === 'business') {
                $action = 'business.manage_billing';
                $ctx['em'] = $entityId;
            } elseif ($entityType === 'user' && (int)$entityId === (int)$user['id']) {
                return;
            } else {
                $action = 'business.manage_billing';
                $ctx['em'] = $entityId;
            }
        }

        if ($action !== null) {
            $this->authzDenyLogContext = [
                'table' => $tableKey,
                'op' => $operation,
                'hasAction' => $hasAction,
                'hasCtx' => $hasCtx,
                'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
                'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
                'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
                'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
                'publicMembershipCandidate' => $publicMembershipCheck['publicMembershipCandidate'] ?? false,
                'publicMembershipInvalid' => $publicMembershipCheck['invalidConditions'] ?? false,
                'publicMembershipColumnsNotAllowed' => $publicMembershipCheck['columnsNotAllowed'] ?? false,
                'publicMembershipExistsNotAllowed' => $publicMembershipCheck['existsNotAllowed'] ?? false,
                'userMembershipCandidate' => $userMembershipCheck['userMembershipCandidate'] ?? false,
                'feedCandidate' => $feedCheck['feedCandidate'] ?? false,
                'postCandidate' => $postCheck['postCandidate'] ?? false,
                'usgCandidate' => $usgCheck['usgCandidate'] ?? false,
                'publicCandidate' => $publicCheck['publicCandidate'] ?? false,
                'limitedCandidate' => $limitedCheck['limitedCandidate'] ?? false,
                'appCandidate' => $appCheck['appCandidate'] ?? false,
                'testimonialsCandidate' => $testimonialsCheck['testimonialsCandidate'] ?? false,

                'nicknameCandidate' => $nicknameCheck['nicknameCandidate'] ?? false,
                'gappMemberCandidate' => $gappMemberCheck['gappMemberCandidate'] ?? false,
                'writeCandidate' => $writeCheck['writeCandidate'] ?? false,
                'membershipWriteCandidate' => $membershipWriteCheck['membershipWriteCandidate'] ?? false,
                'employeeSelfCandidate' => $employeeSelfWriteCheck['employeeSelfCandidate'] ?? false,
                'companyWriteCandidate' => $companyWriteCheck['companyWriteCandidate'] ?? false,
                'testimonialWriteCandidate' => $testimonialWriteCheck['testimonialWriteCandidate'] ?? false,
            ];
            $this->authorize($action, $ctx, $payload);
            return;
        }

        $this->logCrudDeny([
            'table' => $tableKey,
            'op' => $operation,
            'hasAction' => $hasAction,
            'hasCtx' => $hasCtx,
            'selfScopedCandidate' => $selfCheck['selfScopedCandidate'] ?? false,
            'mismatchUser' => $selfCheck['mismatchUser'] ?? false,
            'columnsNotAllowed' => $selfCheck['columnsNotAllowed'] ?? false,
            'existsNotAllowed' => $selfCheck['existsNotAllowed'] ?? false,
            'publicMembershipCandidate' => $publicMembershipCheck['publicMembershipCandidate'] ?? false,
            'publicMembershipInvalid' => $publicMembershipCheck['invalidConditions'] ?? false,
            'publicMembershipColumnsNotAllowed' => $publicMembershipCheck['columnsNotAllowed'] ?? false,
            'publicMembershipExistsNotAllowed' => $publicMembershipCheck['existsNotAllowed'] ?? false,
            'userMembershipCandidate' => $userMembershipCheck['userMembershipCandidate'] ?? false,
            'feedCandidate' => $feedCheck['feedCandidate'] ?? false,
            'postCandidate' => $postCheck['postCandidate'] ?? false,
            'usgCandidate' => $usgCheck['usgCandidate'] ?? false,
            'publicCandidate' => $publicCheck['publicCandidate'] ?? false,
            'limitedCandidate' => $limitedCheck['limitedCandidate'] ?? false,
            'appCandidate' => $appCheck['appCandidate'] ?? false,
            'testimonialsCandidate' => $testimonialsCheck['testimonialsCandidate'] ?? false,

            'nicknameCandidate' => $nicknameCheck['nicknameCandidate'] ?? false,
            'gappMemberCandidate' => $gappMemberCheck['gappMemberCandidate'] ?? false,
            'writeCandidate' => $writeCheck['writeCandidate'] ?? false,
            'membershipWriteCandidate' => $membershipWriteCheck['membershipWriteCandidate'] ?? false,
            'employeeSelfCandidate' => $employeeSelfWriteCheck['employeeSelfCandidate'] ?? false,
            'companyWriteCandidate' => $companyWriteCheck['companyWriteCandidate'] ?? false,
            'testimonialWriteCandidate' => $testimonialWriteCheck['testimonialWriteCandidate'] ?? false,
        ]);
        http_response_code(403);
        echo json_encode(['message' => 'Ação/ctx obrigatórios', 'status' => 'error']);
        exit();
    }

    public function insert(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'data'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $data = $input['data'];
        $authAction = $input['action'] ?? null;
        $authCtx = isset($input['ctx']) && is_array($input['ctx']) ? $input['ctx'] : null;

        $this->enforceCrudAuthorization($payload, 'insert', $db, $table, $data, [], $authAction, $authCtx, null, null);

        $id = $this->generalModel->insert($db, $table, $data);

        if ($id) {
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'Record inserted successfully!',
                'status' => 'success',
                'id' => $id
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to insert record.', 'status' => 'error']);
        }        
    }

    public function update(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'data', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $data = $input['data'];
        $authAction = $input['action'] ?? null;
        $authCtx = isset($input['ctx']) && is_array($input['ctx']) ? $input['ctx'] : null;
        $conditions = $input['conditions'];                

        $this->enforceCrudAuthorization($payload, 'update', $db, $table, $data, $conditions, $authAction, $authCtx, null, null);

        $success = $this->generalModel->update($db, $table, $data, $conditions);

        if ($success) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record updated successfully!',
                'status' => 'success'
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to update record.', 'status' => 'error']);
        }        
    }

    public function search(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }        
        
        $requiredFields = ['db', 'table'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $columns    = $input['columns']    ?? ['*'];
        if (!is_array($columns)) { $columns = [$columns]; }
        $conditions = $input['conditions'] ?? [];
        $fetchAll = $input['fetchAll'] ?? true;
        // coleta limit/offset, se existirem
        $limit  = isset($input['limit'])  ? (int)$input['limit']  : null;
        $offset = isset($input['offset']) ? (int)$input['offset'] : null;
        $order  = isset($input['order'])  ? $input['order']  : null;
        $distinct = isset($input['distinct']) ? $input['distinct'] : null;
        $exists = isset($input['exists']) ? $input['exists'] : [];
        $authAction = $input['action'] ?? null;
        $authCtx = isset($input['ctx']) && is_array($input['ctx']) ? $input['ctx'] : null;
        $columns = $this->normalizeReadColumns('search', $db, $table, $columns);

        $this->enforceCrudAuthorization($payload, 'search', $db, $table, $conditions, $conditions, $authAction, $authCtx, is_array($columns) ? $columns : null, is_array($exists) ? $exists : null);

        $results = $this->generalModel->search($db, $table, $columns, $conditions, $fetchAll, $limit, $offset, $order, $distinct, $exists);

        // Quando fetchAll=false e não há registro, General::search retorna false.
        // Nesses casos não é erro de servidor; devolvemos 200 com data=null.
        if ($results === false && $fetchAll === false) {
            http_response_code(200);
            echo json_encode([
                'message' => 'No record found.',
                'status' => 'success',
                'data' => null,
                'pagination' => [ 'limit' => $limit, 'offset' => $offset ]
            ]);
            exit();
        }

        if ($results !== false) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Records retrieved successfully!',
                'status' => 'success',
                'data' => $results,
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to retrieve records.', 'status' => 'error']);
        }

        exit();
    }

    public function publicSearch(): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $columns = $input['columns'] ?? ['*'];
        if (!is_array($columns)) { $columns = [$columns]; }
        $conditions = $input['conditions'] ?? [];
        $fetchAll = $input['fetchAll'] ?? true;
        $limit  = isset($input['limit'])  ? (int)$input['limit']  : null;
        $offset = isset($input['offset']) ? (int)$input['offset'] : null;
        $order  = isset($input['order'])  ? $input['order']  : null;
        $distinct = isset($input['distinct']) ? $input['distinct'] : null;
        $exists = isset($input['exists']) ? $input['exists'] : [];

        $key = $db . '.' . $table;
        $publicAllowlist = [
            'workz_data.hpl' => true,
            'workz_data.lke' => true,
            'workz_data.hpl_comments' => true,
            'workz_data.hus' => true,
            'workz_companies.companies' => true,
            'workz_companies.teams' => true,
        ];

        if (!isset($publicAllowlist[$key])) {
            http_response_code(403);
            echo json_encode(['message' => 'Tabela não permitida', 'status' => 'error']);
            return;
        }

        if ($key === 'workz_data.hus') {
            if (!is_array($conditions)) { $conditions = []; }
            if (!array_key_exists('st', $conditions)) {
                $conditions['st'] = 1;
            }
            if ((int)($conditions['st'] ?? 0) !== 1) {
                http_response_code(400);
                echo json_encode(['message' => 'Condição st inválida.', 'status' => 'error']);
                return;
            }

            foreach ($conditions as $condKey => $_val) {
                if (!in_array($condKey, ['id','st'], true)) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Condições não permitidas.', 'status' => 'error']);
                    return;
                }
            }

            $allowedCols = ['id','tt','im','feed_privacy','page_privacy'];
            if (empty($columns) || in_array('*', $columns, true)) {
                $columns = $allowedCols;
            } else {
                foreach ($columns as $col) {
                    if (!in_array($col, $allowedCols, true)) {
                        http_response_code(403);
                        echo json_encode(['message' => 'Colunas não permitidas', 'status' => 'error']);
                        return;
                    }
                }
            }

            $idCond = $conditions['id'] ?? null;
            if ($idCond === null) {
                http_response_code(400);
                echo json_encode(['message' => 'Condição id é obrigatória.', 'status' => 'error']);
                return;
            }
            if (is_array($idCond)) {
                $op = strtoupper((string)($idCond['op'] ?? ''));
                $values = $idCond['value'] ?? null;
                if ($op !== 'IN' || !is_array($values) || count($values) > 200) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Condição id inválida.', 'status' => 'error']);
                    return;
                }
                foreach ($values as $v) {
                    if (!is_numeric($v)) {
                        http_response_code(400);
                        echo json_encode(['message' => 'Condição id inválida.', 'status' => 'error']);
                        return;
                    }
                }
            } elseif (!is_numeric($idCond)) {
                http_response_code(400);
                echo json_encode(['message' => 'Condição id inválida.', 'status' => 'error']);
                return;
            }
        } else {
            if ($key === 'workz_data.hpl') {
                if (!is_array($conditions)) { $conditions = []; }
                $conditions['post_privacy'] = ['op' => '>=', 'value' => 3];
                $exists = [[
                    'db' => 'workz_data',
                    'table' => 'hus',
                    'local' => 'us',
                    'remote' => 'id',
                    'conditions' => ['st' => 1, 'feed_privacy' => 3, 'page_privacy' => 1],
                ]];
            }

            $columns = $this->normalizeReadColumns('search', $db, $table, $columns);
            $this->enforceCrudAuthorization(null, 'search', $db, $table, $conditions, $conditions, null, null, is_array($columns) ? $columns : null, is_array($exists) ? $exists : null);
        }

        $results = $this->generalModel->search($db, $table, $columns, $conditions, $fetchAll, $limit, $offset, $order, $distinct, $exists);

        if ($results === false && $fetchAll === false) {
            http_response_code(200);
            echo json_encode([
                'message' => 'No record found.',
                'status' => 'success',
                'data' => null,
                'pagination' => [ 'limit' => $limit, 'offset' => $offset ]
            ]);
            exit();
        }

        if ($results !== false) {
            http_response_code(200);
            echo json_encode([
                'message' => 'Records retrieved successfully!',
                'status' => 'success',
                'data' => $results,
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to retrieve records.', 'status' => 'error']);
        }

        exit();
    }

    public function count(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $conditions = $input['conditions'];
        $authAction = $input['action'] ?? null;
        $authCtx = isset($input['ctx']) && is_array($input['ctx']) ? $input['ctx'] : null;
        $distinctCol = $input['distinctCol'] ?? ($input['distinct'] ?? null);
        if ($distinctCol === true) {
            $distinctCol = null;
        }
        $exists = isset($input['exists']) ? $input['exists'] : [];

        $this->enforceCrudAuthorization($payload, 'count', $db, $table, $conditions, $conditions, $authAction, $authCtx, null, is_array($exists) ? $exists : null);

        $count = $this->generalModel->count($db, $table, $conditions, $distinctCol, $exists);

        if ($count !== false) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record count retrieved successfully!',
                'status' => 'success',
                'count' => $count
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to retrieve record count.', 'status' => 'error']);
        }        
    }
    

    public function delete(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $conditions = $input['conditions'];
        $authAction = $input['action'] ?? null;
        $authCtx = isset($input['ctx']) && is_array($input['ctx']) ? $input['ctx'] : null;

        $this->enforceCrudAuthorization($payload, 'delete', $db, $table, $conditions, $conditions, $authAction, $authCtx, null, null);

        $success = $this->generalModel->delete($db, $table, $conditions);

        if ($success) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record deleted successfully!',
                'status' => 'success'
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to delete record.', 'status' => 'error']);
        }        
    }

    public function uploadImage(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = $_POST['entity_id'] ?? '';
        $imageType = $_POST['image_type'] ?? 'im';

        $allowedTypes = ['people', 'businesses', 'teams'];
        if (!in_array($entityType, $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de entidade inválido.']);
            return;
        }

        $entityId = filter_var($entityId, FILTER_VALIDATE_INT);
        if (!$entityId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador inválido.']);
            return;
        }

        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo de imagem não enviado.']);
            return;
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao receber o arquivo de imagem.']);
            return;
        }

        $maxSize = 6 * 1024 * 1024; // 6MB
        if ($file['size'] > $maxSize) {
            http_response_code(413);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo excede o tamanho máximo permitido (6MB).']);
            return;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            http_response_code(415);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo enviado não é uma imagem válida.']);
            return;
        }

        $mime = $imageInfo['mime'] ?? '';
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimes[$mime])) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.']);
            return;
        }

        $subDirs = [
            'people' => 'users',
            'businesses' => 'businesses',
            'teams' => 'teams'
        ];

        $projectRoot = dirname(__DIR__, 3);
        $publicDir = $projectRoot . DIRECTORY_SEPARATOR . 'public';
        if (!is_dir($publicDir)) {
            $projectRoot = dirname(__DIR__, 2);
            $publicDir = $projectRoot . DIRECTORY_SEPARATOR . 'public';
        }
        $rootDir = $publicDir . DIRECTORY_SEPARATOR . 'images';
        $targetDir = $rootDir . DIRECTORY_SEPARATOR . $subDirs[$entityType];
        $relativeBase = '/images';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            $targetDir = null;
        }

        if (!$targetDir || !is_writable($targetDir)) {
            $fallbackRoot = $publicDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'images';
            $fallbackDir = $fallbackRoot . DIRECTORY_SEPARATOR . $subDirs[$entityType];
            if (!is_dir($fallbackDir) && !mkdir($fallbackDir, 0755, true)) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Não foi possível preparar o diretório de imagens.']);
                return;
            }
            $targetDir = $fallbackDir;
            $relativeBase = '/uploads/images';
        }

        $extension = $allowedMimes[$mime];
        $filename = uniqid($entityType . '_', true) . '.' . $extension;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao salvar a imagem no servidor.']);
            return;
        }

        $relativePath = $relativeBase . '/' . $subDirs[$entityType] . '/' . $filename;

        $mapping = [
            'people' => ['db' => 'workz_data', 'table' => 'hus'],
            'businesses' => ['db' => 'workz_companies', 'table' => 'companies'],
            'teams' => ['db' => 'workz_companies', 'table' => 'teams']
        ];

        $columnToUpdate = ($imageType === 'bk') ? 'bk' : 'im';

        $config = $mapping[$entityType];
        $updated = $this->generalModel->update($config['db'], $config['table'], [$columnToUpdate => $relativePath], ['id' => $entityId]);

        if (!$updated) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível atualizar os dados da entidade.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Imagem atualizada com sucesso.',
            'imageUrl' => $relativePath
        ]);
    }

    public function changeEmail(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            http_response_code(400);            
            return;
        }

        $userId = $input['userId'];
        $newEmail = $input['newEmail'];

        if (empty($userId) || empty($newEmail)) {
            echo json_encode(['message' => 'Missing required fields.', 'status' => 'error']);
            http_response_code(400);
            return;
        }
        
        $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['id' => $userId], false);

        if (!$user) {
            echo json_encode(['message' => 'User not found.', 'status' => 'error']);
            http_response_code(404);
            return;
        }
        
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['message' => 'Invalid email address.', 'status' => 'error']);
            http_response_code(422);            
            return;
        }

        $emailExists = $this->generalModel->search('workz_data','hus', ['id'], ['ml' => $newEmail], false);

        if ($emailExists) {
            echo json_encode(['message' => 'Email already exists.', 'status' => 'error']);
            http_response_code(409);
            return;
        }

        
        if (!empty($user['provider'])) {
            echo json_encode(['message' => 'User has a provider account: '.$user['provider'], 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTime('+2 hours'))->format('Y-m-d H:i:s');

        $repo = $this->generalModel->update('workz_data','hus', [
            'pending_email' => $newEmail,
            'email_change_token' => $tokenHash,
            'email_change_expires_at' => $expiresAt
        ], ['id' => $userId]);

        if ($repo) {
            // In a real application, you would send an email with a link containing $token
            // For this example, we'll just return the token
            echo json_encode([
                'message' => 'Email change request initiated. Please check your new email for verification.',
                'status' => 'success',
                'verification_token' => $token // For testing/demonstration purposes
            ]);
            http_response_code(200);
        } else {
            echo json_encode(['message' => 'Failed to initiate email change request.', 'status' => 'error']);
            http_response_code(500);
        }
    }


    public function changePassword(?object $payload = null): void
    {
        header("Content-Type: application/json");
        $this->ensureAuthenticated($payload);
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $userId = $input['userId'] ?? null;
        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';

        if (empty($userId) || $currentPassword === '' || $newPassword === '') {
            echo json_encode(['message' => 'Missing required fields.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $user = $this->generalModel->search('workz_data', 'hus', ['id', 'pw', 'provider'], ['id' => $userId], false);
        if (!$user) {
            echo json_encode(['message' => 'User not found.', 'status' => 'error']);
            http_response_code(404);
            return;
        }

        if (!empty($user['provider']) && $user['provider'] !== 'local') {
            echo json_encode(['message' => 'Password changes are not allowed for social logins.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        if (empty($user['pw'])) {
            echo json_encode(['message' => 'Password not set for this account.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        if (!password_verify($currentPassword, $user['pw'])) {
            echo json_encode(['message' => 'Current password is incorrect.', 'status' => 'error']);
            http_response_code(401);
            return;
        }

        if (password_verify($newPassword, $user['pw'])) {
            echo json_encode(['message' => 'The new password must be different from the current password.', 'status' => 'error']);
            http_response_code(422);
            return;
        }

        if (!$this->isValidPassword($newPassword)) {
            echo json_encode(['message' => 'The new password does not meet the security requirements.', 'status' => 'error']);
            http_response_code(422);
            return;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = $this->generalModel->update('workz_data', 'hus', ['pw' => $hashedPassword], ['id' => $userId]);

        if ($updated) {
            echo json_encode(['message' => 'Password updated successfully!', 'status' => 'success']);
            http_response_code(200);
        } else {
            echo json_encode(['message' => 'Failed to update password.', 'status' => 'error']);
            http_response_code(500);
        }
    }

    private function isValidPassword(string $password): bool
    {
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[@$!%*?&.#])[A-Za-z\\d@$!%*?&.#]{8,}$/';
        return preg_match($regex, $password) === 1;
    }
}
