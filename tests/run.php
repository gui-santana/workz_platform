<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Workz\Platform\Services\AuthorizationService;
use Workz\Platform\Controllers\GeneralController;

class FakeAuthorizationService extends AuthorizationService
{
    private array $data;

    public function __construct(array $data)
    {
        parent::__construct();
        $this->data = $data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    protected function getBusinessMembership(int $userId, int $businessId): ?array
    {
        return $this->data['business'][$userId][$businessId] ?? null;
    }

    protected function getTeamMembership(int $userId, int $teamId): ?array
    {
        return $this->data['teamMembership'][$userId][$teamId] ?? null;
    }

    protected function getTeamRow(int $teamId): ?array
    {
        return $this->data['teamRows'][$teamId] ?? null;
    }

    protected function hasBusinessApp(int $businessId, int $appId): bool
    {
        foreach ($this->data['gappBusiness'] as $row) {
            if ((int)$row['em'] === $businessId && (int)$row['ap'] === $appId && (int)($row['st'] ?? 1) === 1) {
                return true;
            }
        }
        return false;
    }

    protected function hasTeamApp(int $businessId, int $teamId, int $appId): bool
    {
        foreach ($this->data['gappTeam'] as $row) {
            if ((int)$row['em'] === $businessId && (int)$row['cm'] === $teamId && (int)$row['ap'] === $appId && (int)($row['st'] ?? 1) === 1) {
                return true;
            }
        }
        return false;
    }


    protected function hasUserApp(int $userId, int $appId): bool
    {
        foreach ($this->data['gappUser'] ?? [] as $row) {
            if ((int)$row['us'] === $userId && (int)$row['ap'] === $appId && (int)($row['st'] ?? 1) === 1) {
                return true;
            }
        }
        return false;
    }

}

class GeneralControllerTestProxy extends GeneralController
{
    private array $stub = [
        'userBusinesses' => [],
        'userTeams' => [],
        'companyOwners' => [],
        'teamRows' => [],
        'employeeRows' => [],
        'testimonialRows' => [],
    ];

    public function setStub(string $key, $value): void
    {
        $this->stub[$key] = $value;
    }

    public function probeSelfScopedRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        return $this->evaluateSelfScopedRead($operation, $db, $table, $conditions, $columns, $userId, $exists);
    }

    public function probeUserMembershipRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        return $this->evaluateUserMembershipRead($operation, $db, $table, $conditions, $columns, $userId, $exists);
    }

    public function probePublicMembershipRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        return $this->evaluatePublicMembershipRead($operation, $db, $table, $conditions, $columns, $userId, $exists);
    }

    public function probeFeedRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        return $this->evaluateFeedRead($operation, $db, $table, $conditions, $columns, $exists);
    }

    public function probePostRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        return $this->evaluatePostRead($operation, $db, $table, $conditions, $columns, $exists);
    }

    public function probeUsgRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        return $this->evaluateUsgRead($operation, $db, $table, $conditions, $columns, $exists);
    }

    public function probePublicRead(string $operation, string $db, string $table, array $conditions, array $columns, array $exists = []): array
    {
        return $this->evaluatePublicListRead($operation, $db, $table, $conditions, $columns, $exists);
    }

    public function probeLimitedRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        return $this->evaluateLimitedRead($operation, $db, $table, $conditions, $columns);
    }

    public function probeAppRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        return $this->evaluateAppRead($operation, $db, $table, $conditions, $columns);
    }

    public function probeGappMemberRead(string $operation, string $db, string $table, array $conditions, array $columns, int $userId, array $exists = []): array
    {
        return $this->evaluateGappMemberRead($operation, $db, $table, $conditions, $columns, $userId, $exists);
    }

    public function probeSelfScopedWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        return $this->evaluateSelfScopedWrite($operation, $db, $table, $data, $conditions, $userId);
    }

    public function probeMembershipWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        return $this->evaluateMembershipRequestWrite($operation, $db, $table, $data, $conditions, $userId);
    }

    public function probeEmployeeSelfWriteById(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        return $this->evaluateEmployeeSelfWriteById($operation, $db, $table, $data, $conditions, $userId);
    }

    public function probeCompanyWrite(string $operation, string $db, string $table, array $data, array $conditions, int $userId): array
    {
        return $this->evaluateCompanyWrite($operation, $db, $table, $data, $conditions, $userId);
    }

    public function probeTestimonialsWrite(string $operation, string $db, string $table, array $data, array $conditions, array $user): array
    {
        return $this->evaluateTestimonialsWrite($operation, $db, $table, $data, $conditions, $user);
    }

    public function probeNicknameRead(string $operation, string $db, string $table, array $conditions, array $columns): array
    {
        return $this->evaluateNicknameRead($operation, $db, $table, $conditions, $columns);
    }

    public function probeNormalizeReadColumns(string $operation, string $db, string $table, array $columns): array
    {
        return $this->normalizeReadColumns($operation, $db, $table, $columns);
    }

    protected function getUserBusinessIds(int $userId): array
    {
        return $this->stub['userBusinesses'][$userId] ?? [];
    }

    protected function getUserTeamIds(int $userId): array
    {
        return $this->stub['userTeams'][$userId] ?? [];
    }

    protected function getCompanyOwnerId(int $businessId): ?int
    {
        return $this->stub['companyOwners'][$businessId] ?? null;
    }

    protected function getTeamRowById(int $teamId): ?array
    {
        return $this->stub['teamRows'][$teamId] ?? null;
    }

    protected function getEmployeeRowById(int $id): ?array
    {
        return $this->stub['employeeRows'][$id] ?? null;
    }

    protected function getTestimonialRowById(int $id): ?array
    {
        return $this->stub['testimonialRows'][$id] ?? null;
    }
}

function assertResult($condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$baseData = [
    'business' => [
        1 => [
            1 => ['us' => 1, 'em' => 1, 'nv' => 3, 'st' => 1], // ADMIN in business 1
        ],
    ],
    'teamMembership' => [
        1 => [
            2 => ['us' => 1, 'cm' => 2, 'nv' => 1, 'st' => 1], // VIEWER in team 2
        ],
    ],
    'teamRows' => [
        2 => ['id' => 2, 'em' => 1, 'us' => 99, 'usmn' => '[]', 'st' => 1],
    ],
    'gappBusiness' => [
        ['em' => 1, 'ap' => 10, 'st' => 1],
    ],
    'gappTeam' => [
        ['em' => 1, 'cm' => 2, 'ap' => 10, 'st' => 1],
    ],
    'gappUser' => [
        ['us' => 1, 'ap' => 99, 'st' => 1],
    ],
];

$authz = new FakeAuthorizationService($baseData);
$user = ['id' => 1];

// 1) ADMIN no business mas não membro do team -> deny app.*
$data = $baseData;
unset($data['teamMembership'][1][2]); // remove membership
$authz->setData($data);
$res = $authz->can($user, 'app.read', ['em' => 1, 'cm' => 2, 'ap' => 10]);
assertResult(!$res->allowed, 'Admin sem vínculo de time deve negar app.read');

// 2) app subscribed no business mas não enabled no team -> deny
$data = $baseData;
$data['gappTeam'] = []; // not enabled for team
$authz->setData($data);
$res = $authz->can($user, 'app.read', ['em' => 1, 'cm' => 2, 'ap' => 10]);
assertResult(!$res->allowed && $res->reason === 'app.not_enabled_team', 'App não habilitado no time deve negar');

// 3) VIEWER no team -> allow read; deny create
$data = $baseData;
$authz->setData($data);
$resRead = $authz->can($user, 'app.read', ['em' => 1, 'cm' => 2, 'ap' => 10]);
$resCreate = $authz->can($user, 'app.create', ['em' => 1, 'cm' => 2, 'ap' => 10]);
assertResult($resRead->allowed === true, 'Viewer deve poder app.read');
assertResult($resCreate->allowed === false, 'Viewer deve negar app.create');

// 4) ADMIN (nv=3) -> allow manage_teams; deny manage_billing
$resTeams = $authz->can($user, 'business.manage_teams', ['em' => 1]);
$resBilling = $authz->can($user, 'business.manage_billing', ['em' => 1]);
assertResult($resTeams->allowed === true, 'Admin deve poder business.manage_teams');
assertResult($resBilling->allowed === false, 'Admin deve negar business.manage_billing');

// 5) Alteração de role reflete imediatamente
$data = $baseData;
$authz->setData($data);
// primeiro como viewer (nv=1) -> deny create
$resCreate = $authz->can($user, 'app.create', ['em' => 1, 'cm' => 2, 'ap' => 10]);
assertResult($resCreate->allowed === false, 'Viewer deve negar app.create inicialmente');
// promove para OPERATOR (nv=3) e reavalia
$data['teamMembership'][1][2]['nv'] = 3;
$authz->setData($data);
$resCreateAfter = $authz->can($user, 'app.create', ['em' => 1, 'cm' => 2, 'ap' => 10]);
assertResult($resCreateAfter->allowed === true, 'Mudança de nv deve refletir imediatamente');


// 6) Owner sem teams_users row -> allow team.manage_settings
$data = $baseData;
$data['teamRows'][3] = ['id' => 3, 'em' => 1, 'us' => 1, 'usmn' => '[]', 'st' => 1];
$authz->setData($data);
$resOwner = $authz->can($user, 'team.manage_settings', ['em' => 1, 'cm' => 3]);
assertResult($resOwner->allowed === true, 'Owner sem teams_users deve gerenciar time');

// 7) Moderador sem teams_users row -> allow team.approve_member
$data = $baseData;
$data['teamRows'][4] = ['id' => 4, 'em' => 1, 'us' => 99, 'usmn' => '["1"]', 'st' => 1];
$authz->setData($data);
$resMod = $authz->can($user, 'team.approve_member', ['em' => 1, 'cm' => 4]);
assertResult($resMod->allowed === true, 'Moderador sem teams_users deve aprovar membro');

// 8) User-level gapp permite app.read pessoal; ausência nega
$data = $baseData;
$authz->setData($data);
$resUserApp = $authz->can($user, 'app.read', ['ap' => 99]);
assertResult($resUserApp->allowed === true, 'Assinatura por usuário deve permitir app.read pessoal');

$data = $baseData;
$data['gappUser'] = [];
$authz->setData($data);
$resUserAppDeny = $authz->can($user, 'app.read', ['ap' => 99]);
assertResult($resUserAppDeny->allowed === false, 'Sem assinatura por usuário deve negar app.read pessoal');

// 9) GeneralController self-scoped read exception
$gc = new GeneralControllerTestProxy();
$selfAllowed = $gc->probeSelfScopedRead(
    'search',
    'workz_apps',
    'gapp',
    ['us' => 1, 'st' => 1],
    ['ap', 'st'],
    1,
    []
);
assertResult($selfAllowed['allowed'] === true, 'Self-scoped search gapp deve permitir com columns allowlist');

$selfMismatch = $gc->probeSelfScopedRead(
    'search',
    'workz_apps',
    'gapp',
    ['us' => 2, 'st' => 1],
    ['ap', 'st'],
    1,
    []
);
assertResult($selfMismatch['allowed'] === false && $selfMismatch['mismatchUser'] === true, 'Self-scoped search gapp deve negar quando us != current');

$selfUpdate = $gc->probeSelfScopedRead(
    'update',
    'workz_apps',
    'gapp',
    ['us' => 1],
    ['ap', 'st'],
    1,
    []
);
assertResult($selfUpdate['allowed'] === false, 'Self-scoped update gapp deve negar');

$selfColumns = $gc->probeSelfScopedRead(
    'search',
    'workz_apps',
    'gapp',
    ['us' => 1],
    ['ap', 'subscription_secret'],
    1,
    []
);
assertResult($selfColumns['allowed'] === false && $selfColumns['columnsNotAllowed'] === true, 'Self-scoped search com coluna fora da allowlist deve negar');

// 10) Employees self-scoped with columns ["*"] -> normalize and allow
$colsEmployees = $gc->probeNormalizeReadColumns('search', 'workz_companies', 'employees', ['*']);
assertResult(!in_array('*', $colsEmployees, true) && in_array('us', $colsEmployees, true) && in_array('em', $colsEmployees, true), 'Normalize columns deve usar allowlist de employees');
$selfEmployees = $gc->probeSelfScopedRead(
    'search',
    'workz_companies',
    'employees',
    ['us' => 1],
    $colsEmployees,
    1,
    []
);
assertResult($selfEmployees['allowed'] === true, 'Search employees com "*" e us==currentUser deve permitir');

// 11) Teams_users self-scoped with columns ["*"] -> allow
$colsTeamsUsers = $gc->probeNormalizeReadColumns('search', 'workz_companies', 'teams_users', ['*']);
$selfTeamsUsers = $gc->probeSelfScopedRead(
    'search',
    'workz_companies',
    'teams_users',
    ['us' => 1],
    $colsTeamsUsers,
    1,
    []
);
assertResult($selfTeamsUsers['allowed'] === true, 'Search teams_users com "*" e us==currentUser deve permitir');

// 12) Quickapps self-scoped read -> allow
$colsQuick = $gc->probeNormalizeReadColumns('search', 'workz_apps', 'quickapps', ['*']);
$selfQuick = $gc->probeSelfScopedRead(
    'search',
    'workz_apps',
    'quickapps',
    ['us' => 1],
    $colsQuick,
    1,
    []
);
assertResult($selfQuick['allowed'] === true, 'Search quickapps com us==currentUser deve permitir');

// 13) lke feed read with pl IN <= 200 -> allow
$feedAllow = $gc->probeFeedRead(
    'search',
    'workz_data',
    'lke',
    ['pl' => ['op' => 'IN', 'value' => [1,2,3]]],
    ['pl','us'],
    []
);
assertResult($feedAllow['allowed'] === true, 'Search lke com pl IN <=200 deve permitir');

// 14) lke feed read with pl IN > 200 -> deny
$bigList = range(1, 201);
$feedDeny = $gc->probeFeedRead(
    'search',
    'workz_data',
    'lke',
    ['pl' => ['op' => 'IN', 'value' => $bigList]],
    ['pl','us'],
    []
);
assertResult($feedDeny['allowed'] === false, 'Search lke com pl IN >200 deve negar');

// 15) employees search sem conditions.us -> deny
$noUs = $gc->probeSelfScopedRead(
    'search',
    'workz_companies',
    'employees',
    ['st' => 1],
    $colsEmployees,
    1,
    []
);
assertResult($noUs['allowed'] === false && $noUs['mismatchUser'] === true, 'Search employees sem us deve negar');

// 16) apps read by id -> allow
$colsApps = $gc->probeNormalizeReadColumns('search', 'workz_apps', 'apps', ['*']);
$appsRead = $gc->probeAppRead(
    'search',
    'workz_apps',
    'apps',
    ['id' => 10],
    $colsApps
);
assertResult($appsRead['allowed'] === true, 'Search apps por id deve permitir');

// 17) hpl feed read with st=1 and _or -> allow
$postRead = $gc->probePostRead(
    'search',
    'workz_data',
    'hpl',
    ['st' => 1, '_or' => [ ['us' => 1], ['em' => 2] ]],
    ['id','us','em','cm','tt','ct','dt','im','post_privacy','st'],
    []
);
assertResult($postRead['allowed'] === true, 'Search hpl com st=1 e _or deve permitir');

$postCountRestricted = $gc->probePostRead(
    'count',
    'workz_data',
    'hpl',
    ['us' => -1, 'em' => 0, 'cm' => 0],
    ['id'],
    []
);
assertResult($postCountRestricted['allowed'] === true, 'Count hpl com scope vazio e sem st deve permitir');

$postCountMissingSt = $gc->probePostRead(
    'count',
    'workz_data',
    'hpl',
    ['us' => 5],
    ['id'],
    []
);
assertResult($postCountMissingSt['allowed'] === false && $postCountMissingSt['invalidConditions'] === true, 'Count hpl sem st deve negar para id positivo');

// 18) usg read with s0 -> allow
$usgRead = $gc->probeUsgRead(
    'search',
    'workz_data',
    'usg',
    ['s0' => 1],
    ['s0','s1'],
    []
);
assertResult($usgRead['allowed'] === true, 'Search usg com s0 deve permitir');

// 19) companies public list st=1 -> allow
$companyPublic = $gc->probePublicRead(
    'search',
    'workz_companies',
    'companies',
    ['st' => 1],
    $gc->probeNormalizeReadColumns('search', 'workz_companies', 'companies', ['*']),
    []
);
assertResult($companyPublic['allowed'] === true, 'Search companies com st=1 deve permitir');

// 20) self-scoped write quickapps insert -> allow
$quickWrite = $gc->probeSelfScopedWrite(
    'insert',
    'workz_apps',
    'quickapps',
    ['us' => 1, 'ap' => 9],
    [],
    1
);
assertResult($quickWrite['allowed'] === true, 'Insert quickapps com us==currentUser deve permitir');
// 20.1) Empty IN should allow limited read
$colsTeams = $gc->probeNormalizeReadColumns('search', 'workz_companies', 'teams', ['*']);
$limitedEmpty = $gc->probeLimitedRead(
    'search',
    'workz_companies',
    'teams',
    ['id' => ['op' => 'IN', 'value' => []]],
    $colsTeams
);
assertResult($limitedEmpty['allowed'] === true, 'Search teams com IN vazio deve permitir');

// 20.2) Teams self-scoped read by owner -> allow
$teamSelf = $gc->probeSelfScopedRead(
    'search',
    'workz_companies',
    'teams',
    ['us' => 1],
    $colsTeams,
    1,
    []
);
assertResult($teamSelf['allowed'] === true, 'Search teams por us=self deve permitir');

// 20.3) Nickname search -> allow
$nicknameRead = $gc->probeNicknameRead(
    'search',
    'workz_companies',
    'teams',
    ['un' => 'alpha'],
    ['id','un']
);
assertResult($nicknameRead['allowed'] === true, 'Search nickname por un deve permitir');


// 21) User membership read by us (self only) -> allow
$userMembershipSelf = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['us' => 1, 'st' => 1],
    ['us','em','st'],
    1,
    []
);
assertResult($userMembershipSelf['allowed'] === true, 'Search employees por us=self deve permitir');

$userMembershipOther = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['us' => 2, 'st' => 1],
    ['us','em','st'],
    1,
    []
);
assertResult($userMembershipOther['allowed'] === false && $userMembershipOther['invalidConditions'] === true, 'Search employees por us diferente deve negar');

$publicMembershipEmployees = $gc->probePublicMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['us' => 2, 'st' => 1],
    ['em'],
    1,
    [[ 'db' => 'workz_companies', 'table' => 'companies', 'local' => 'em', 'remote' => 'id', 'conditions' => ['st' => 1] ]]
);
assertResult($publicMembershipEmployees['allowed'] === true, 'Public membership employees por us diferente deve permitir');

$publicMembershipTeams = $gc->probePublicMembershipRead(
    'search',
    'workz_companies',
    'teams_users',
    ['us' => 2],
    ['cm'],
    1,
    [[ 'db' => 'workz_companies', 'table' => 'teams', 'local' => 'cm', 'remote' => 'id', 'conditions' => ['st' => 1] ]]
);
assertResult($publicMembershipTeams['allowed'] === true, 'Public membership teams_users por us diferente deve permitir');

$publicMembershipColumns = $gc->probePublicMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['us' => 2, 'st' => 1],
    ['em','nv'],
    1,
    [[ 'db' => 'workz_companies', 'table' => 'companies', 'local' => 'em', 'remote' => 'id', 'conditions' => ['st' => 1] ]]
);
assertResult($publicMembershipColumns['allowed'] === false && $publicMembershipColumns['columnsNotAllowed'] === true, 'Public membership deve restringir colunas');

// 21.1) Membership read by em/cm with st=1 -> allow for member
$gc->setStub('userBusinesses', [1 => [5]]);
$gc->setStub('userTeams', [1 => [7]]);
$membershipByEm = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['em' => 5, 'st' => 1],
    ['us','em','st'],
    1,
    []
);
assertResult($membershipByEm['allowed'] === true, 'Search employees por em com st=1 deve permitir');

$membershipByCm = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'teams_users',
    ['cm' => 7, 'st' => 1],
    ['us','cm','st'],
    1,
    []
);
assertResult($membershipByCm['allowed'] === true, 'Search teams_users por cm com st=1 deve permitir');

// 21.2) Membership list sem st requer auth
$membershipByEmAuth = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'employees',
    ['em' => 5],
    ['us','em','st'],
    1,
    []
);
assertResult(!empty($membershipByEmAuth['requiresAuth']) && $membershipByEmAuth['action'] === 'business.manage_teams', 'Search employees sem st deve exigir auth');

$membershipByCmAuth = $gc->probeUserMembershipRead(
    'search',
    'workz_companies',
    'teams_users',
    ['cm' => 7],
    ['us','cm','st'],
    1,
    []
);
assertResult(!empty($membershipByCmAuth['requiresAuth']) && $membershipByCmAuth['action'] === 'team.manage_settings', 'Search teams_users sem st deve exigir auth');

// 22) gapp business-level read for member businesses -> allow
$gc->setStub('userBusinesses', [1 => [10, 11]]);
$gappMemberAllow = $gc->probeGappMemberRead(
    'search',
    'workz_apps',
    'gapp',
    ['em' => ['op' => 'IN', 'value' => [10]], 'st' => 1],
    ['ap','st'],
    1,
    []
);
assertResult($gappMemberAllow['allowed'] === true, 'Gapp por em IN deve permitir quando membro');

$gappMemberSub = $gc->probeGappMemberRead(
    'search',
    'workz_apps',
    'gapp',
    ['em' => 10, 'subscription' => 1],
    ['ap','subscription'],
    1,
    []
);
assertResult($gappMemberSub['allowed'] === true, 'Gapp por subscription deve permitir quando membro');

$gappMemberDeny = $gc->probeGappMemberRead(
    'search',
    'workz_apps',
    'gapp',
    ['em' => ['op' => 'IN', 'value' => [99]], 'st' => 1],
    ['ap','st'],
    1,
    []
);
assertResult($gappMemberDeny['allowed'] === false && $gappMemberDeny['mismatchUser'] === true, 'Gapp por em IN deve negar quando não membro');


// 23) Membership request writes (employees)
$memInsert = $gc->probeMembershipWrite(
    'insert',
    'workz_companies',
    'employees',
    ['us' => 1, 'em' => 10, 'st' => 0],
    [],
    1
);
assertResult($memInsert['allowed'] === true, 'Insert employees st=0 self deve permitir');

$memUpdate = $gc->probeMembershipWrite(
    'update',
    'workz_companies',
    'employees',
    ['st' => 0],
    ['us' => 1, 'em' => 10],
    1
);
assertResult($memUpdate['allowed'] === true, 'Update employees st=0 self deve permitir');

$memUpdateDeny = $gc->probeMembershipWrite(
    'update',
    'workz_companies',
    'employees',
    ['st' => 1],
    ['us' => 1, 'em' => 10],
    1
);
assertResult($memUpdateDeny['allowed'] === false, 'Update employees st=1 self deve negar');

$memDelete = $gc->probeMembershipWrite(
    'delete',
    'workz_companies',
    'employees',
    [],
    ['us' => 1, 'em' => 10, 'st' => 0],
    1
);
assertResult($memDelete['allowed'] === true, 'Delete employees st=0 self deve permitir');

// 24) Employee self update by id should not allow pending -> active
$gc->setStub('employeeRows', [77 => ['id' => 77, 'us' => 1, 'st' => 0]]);
$empUpdateDeny = $gc->probeEmployeeSelfWriteById(
    'update',
    'workz_companies',
    'employees',
    ['st' => 1],
    ['id' => 77],
    1
);
assertResult($empUpdateDeny['allowed'] === false, 'Update employee st=1 a partir de pendente deve negar');

$empUpdateAllow = $gc->probeEmployeeSelfWriteById(
    'update',
    'workz_companies',
    'employees',
    ['visibility' => 1, 'st' => 0],
    ['id' => 77],
    1
);
assertResult($empUpdateAllow['allowed'] === true, 'Update employee self com campos permitidos deve permitir');

// 25) Company write for owner
$gc->setStub('companyOwners', [10 => 1]);
$companyInsert = $gc->probeCompanyWrite(
    'insert',
    'workz_companies',
    'companies',
    ['tt' => 'Teste', 'us' => 1, 'st' => 1],
    [],
    1
);
assertResult($companyInsert['allowed'] === true, 'Insert company com us atual deve permitir');

$companyUpdate = $gc->probeCompanyWrite(
    'update',
    'workz_companies',
    'companies',
    ['tt' => 'Novo'],
    ['id' => 10],
    1
);
assertResult($companyUpdate['allowed'] === true, 'Update company por owner deve permitir');

// 26) Public list search with tt LIKE should allow
$colsCompanies = $gc->probeNormalizeReadColumns('search', 'workz_companies', 'companies', ['*']);
$companySearch = $gc->probePublicRead(
    'search',
    'workz_companies',
    'companies',
    ['st' => 1, 'tt' => ['op' => 'LIKE', 'value' => '%foo%']],
    $colsCompanies,
    []
);
assertResult($companySearch['allowed'] === true, 'Search companies com tt LIKE deve permitir');

// 27) Testimonials update for recipient user -> allow
$gc->setStub('testimonialRows', [88 => ['id' => 88, 'recipient' => 1, 'recipient_type' => 'people']]);
$testimonialAllow = $gc->probeTestimonialsWrite(
    'update',
    'workz_data',
    'testimonials',
    ['status' => 1],
    ['id' => 88],
    ['id' => 1]
);
assertResult($testimonialAllow['allowed'] === true, 'Update testimonial para perfil deve permitir');

// 28) Testimonials update for business -> require auth
$gc->setStub('testimonialRows', [89 => ['id' => 89, 'recipient' => 10, 'recipient_type' => 'businesses']]);
$testimonialAuth = $gc->probeTestimonialsWrite(
    'update',
    'workz_data',
    'testimonials',
    ['status' => 2],
    ['id' => 89],
    ['id' => 1]
);
assertResult(!empty($testimonialAuth['requiresAuth']) && $testimonialAuth['action'] === 'business.manage_teams', 'Update testimonial business deve exigir auth');

echo "OK\n";
