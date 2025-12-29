# Permissões (RBAC) — Workz!

## Papéis (derivados de `nv`)
- **Business** (`workz_companies.employees.nv`): 4 OWNER, 3 ADMIN, 2 MEMBER, 1 GUEST.
- **Team** (`workz_companies.teams_users.nv`): 4 LEAD, 3 OPERATOR, 2 MEMBER, 1 VIEWER.
- Regras adicionais de time: `teams.us` => LEAD; `teams.usmn` => pelo menos OPERATOR.

## Cadeia de precedência
1) membership ativo no negócio (`employees.st=1`)
2) membership ativo no time (`teams_users.st=1`, mesma empresa)
3) app assinado no negócio (`workz_apps.gapp em=business, cm NULL, st=1`)
4) app habilitado no time (`gapp em=business, cm=team, st=1`)
5) policy do app/negócio (BusinessPolicy, TeamPolicy, AppPolicy)
> Business policies só negam (não ampliam).

## Actions canônicas
- `business.manage_apps`, `business.manage_billing`, `business.manage_teams`, `business.approve_member`, `business.view_audit`
- `team.manage_settings`, `team.approve_member`, `team.set_roles`
- `app.read`, `app.create`, `app.update_own`, `app.update_any`, `app.delete`, `app.approve`, `app.admin_settings`

## Matriz (resumida)
- Business: OWNER/ADMIN liberam business.*; MEMBER apenas `business.view_audit`; GUEST nega.
- Team: LEAD tudo em team.*; OPERATOR `team.manage_settings`/`team.approve_member`; demais negam.
- App (role do time): LEAD tudo; OPERATOR read/create/update_any/delete; MEMBER read/create/update_own; VIEWER read; GUEST nega. Negócio inativo => deny.

## Schema incremental
- `workz_apps.gapp`: coluna `cm` (team_id) + `cm0` (IFNULL(cm,0)) + UNIQUE(em, ap, cm0) + índices (em,ap,cm) e (us,em,ap).
- `workz_data.audit_logs`: actor_us, action, em, cm, ap, target_type/id, before_json, after_json, ip, ua, created_at.

## Integrações
- Serviço central: `src/Services/AuthorizationService.php` (`can($user, $action, $ctx)`).
- Resultados: `AuthorizationResult` (allowed, reason, meta).
- Controllers usam helper `authorize()`; GeneralController requer Auth + allowlist e validação por ação/ctx.
- `AppsController::entitlements/SSO` agora validam business+team+gapp e roles reais.
- Storage (/api/appdata/*) chama autorização para `app.read/create/delete`.
- Auditoria: `AuditLogService` grava mudanças de papel/convite (business/team).
