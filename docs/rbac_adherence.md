# Aderencia RBAC — Workz!

Este documento descreve, de forma detalhada e operacional, como a plataforma Workz!
adere ao modelo de RBAC contextual em duas camadas (business + team), mantendo
compatibilidade com fluxos legados e negacao por padrao.

## 1) Escopo e principios
- **Sem superadmin global**: permissoes sao sempre contextuais (business/team/app).
- **Negacao por padrao**: nenhuma acao e permitida sem validacao explicita.
- **Compatibilidade legada**: apps pessoais e assinaturas por usuario continuam funcionais.
- **Minima superficie**: CRUD generico e endurecido via allowlist + validacoes.

## 2) Mapeamento de papeis (roles) a partir de `nv`

### Business (workz_companies.employees.nv)
- 4 = OWNER
- 3 = ADMIN
- 2 = MEMBER
- 1 = GUEST

### Team (workz_companies.teams_users.nv)
- 4 = LEAD
- 3 = OPERATOR
- 2 = MEMBER
- 1 = VIEWER

### Regras adicionais de equipe
- `teams.us` (owner) sempre LEAD, mesmo sem row em `teams_users`.
- `teams.usmn` (moderadores) sao no minimo OPERATOR, mesmo sem row em `teams_users`.

## 3) Chave de contexto (ctx)
Todas as decisoes sao tomadas com contexto explicito:
- `em` = business_id
- `cm` = team_id
- `ap` = app_id
- `ap_slug` = slug do app (resolvido para `ap`)

## 4) Cadeia de precedencia para apps
Para uma acao `app.*` no contexto de negocio/equipe:
1) Membership ativo no business (`employees.st=1`).
2) Membership ativo no team (`teams_users.st=1`), quando `cm` e informado.
3) App assinado no business (`gapp em=business, cm NULL, us NULL, st=1`).
4) App habilitado no team (`gapp em=business, cm=team, st=1`) quando `cm` e informado.
5) Policy do app para o role do usuario na equipe.
6) Policies do business **podem restringir**, nunca ampliar.

Para apps pessoais (sem `em`/`cm`):
- Exige assinatura por usuario: `gapp us=user, ap=app, cm NULL, st=1`.

## 5) Entitlements e semantica de `gapp`
Tabela `workz_apps.gapp` suporta tres casos:
- **Assinatura do business**: `em` + `ap` + `cm NULL` + `us NULL`.
- **Habilitacao por team**: `em` + `ap` + `cm=team`.
- **Assinatura pessoal**: `us` + `ap` + `cm NULL` (nao concede acesso business-wide).

Protecoes adicionais:
- `AuthorizationService` ignora `gapp.us` para permissao business.
- `gapp` com `cm` nao "vaza" para equipes de outros negocios.

## 6) Servico central de autorizacao
Arquivo: `src/Services/AuthorizationService.php`

Fluxo resumido:
1) Resolve `user_id` a partir do JWT (`sub`).
2) Resolve memberships:
   - Business: `employees` com `st=1`.
   - Team: `teams_users` com `st=1` + fallback owner/moderador.
3) Valida coerencia de contexto:
   - `teams.em` deve bater com `ctx.em` quando ambos sao informados.
4) Valida entitlements (`gapp`) conforme a regra do escopo.
5) Aplica policies:
   - `BusinessPolicy` para `business.*`
   - `TeamPolicy` para `team.*`
   - `AppPolicy` para `app.*`

### Cache por request
Memberships e gapp sao cacheados em memoria no request para reduzir I/O.

## 7) Policies aplicadas

### BusinessPolicy
- OWNER: `business.*`
- ADMIN: `business.*` (exceto ownership critico, se existir)
- MEMBER/GUEST: negado para `business.manage_*`

### TeamPolicy
- LEAD: `team.manage_settings`, `team.approve_member`, `team.set_roles`
- OPERATOR: `team.manage_settings`, `team.approve_member`
- MEMBER/VIEWER/GUEST: negado

### AppPolicy
- LEAD: read/create/update_any/delete/approve/admin_settings
- OPERATOR: read/create/update_any/delete (sem admin_settings)
- MEMBER: read + create (opcional) + update_own
- VIEWER: read-only
- GUEST: deny

## 8) Integracao nos controllers

### AuthorizationTrait
Arquivo: `src/Controllers/Traits/AuthorizationTrait.php`
- `authorize()` chama o servico central e devolve 403 quando negado.
- Reason/meta sao sanitizados antes de ir ao cliente.

### Controllers protegidos
- `CompaniesController`, `TeamsController`: operacoes sensiveis exigem `business.*` ou `team.*`.
- `AppsController`: `entitlements` e `sso` validam `app.read` com contexto real.
- `AppStorageController`: exige `app.read/create/update` conforme metodo.

## 9) CRUD generico (GeneralController)
Arquivo: `src/Controllers/GeneralController.php`

Medidas de hardening:
- Auth obrigatorio para todas as operacoes.
- Allowlist deny-by-default por db/tabela.
- Tabelas sensiveis exigem `action+ctx` com `AuthorizationService`.
- Excecoes **seguras** de leitura self-scoped:
  - `conditions.us == auth_user_id`
  - somente `search/count`
  - colunas restritas por allowlist
  - joins `exists` limitados a combinacoes conhecidas
- Reads de feed (`lke`, `hpl_comments`) limitados a `pl IN [...]` com teto.
- Logs internos registram o motivo de bloqueio (sem expor ao cliente).

## 10) Storage (appdata)
Rotas `/api/appdata/*` exigem:
- `app.read` para GET.
- `app.create`/`app.update_*`/`app.delete` para writes.
- Em `scopeType=user`, acesso somente ao proprio usuario.

## 11) Auditoria
Tabela: `workz_data.audit_logs`
- Registra mudancas de role, aprovacoes e habilitacoes de app.
- Campos principais: actor_us, action, em/cm/ap, before_json/after_json.
- Nao registra payload sensivel completo (minimo necessario).

## 12) Compatibilidade legada
Garantias preservadas:
- Apps pessoais: `gapp.us` continua valido sem exigir `em/cm`.
- Entitlements nao exigem `cm` quando nao informado.
- Fluxos antigos de UI continuam usando `/api/search` com excecoes seguras.

## 13) Checklist de conformidade RBAC
- [x] Roles derivadas de `nv` com regras de owner/moderador.
- [x] Cadeia business+team+entitlement aplicada em `app.*`.
- [x] Policies centralizadas e consistentes.
- [x] CRUD generico sem bypass para tabelas sensiveis.
- [x] Fluxos legados suportados sem abrir escrita ou leitura cross-user.

## 14) Arquivos chave (referencia rapida)
- `src/Services/AuthorizationService.php`
- `src/Policies/BusinessPolicy.php`
- `src/Policies/TeamPolicy.php`
- `src/Policies/AppPolicy.php`
- `src/Controllers/GeneralController.php`
- `src/Controllers/AppsController.php`
- `src/Controllers/Traits/AuthorizationTrait.php`
- `docs/permissions.md`
