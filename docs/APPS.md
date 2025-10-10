# Workz Platform — Apps (Visão Geral)

Este documento descreve como “Apps” integram com a plataforma Workz, cobrindo arquitetura, segurança, escalabilidade, monetização, dados, SDK e ciclo de vida. Está alinhado ao código atual do repositório e destaca pontos já existentes para aproveitamento imediato.

## Sumário rápido
- Tipos de execução: embutido (iframe) e independente (aba/domínio `app.localhost`).
- Catálogo/instalação: tabelas `workz_apps.apps` e `workz_apps.gapp` (já existentes).
- SSO: troca de token curta por app (proposta), sem repassar JWT principal a terceiros.
- SDK leve: utilitários para SSO, `postMessage` (embed) e chamadas `/api` com JWT.
- Segurança: iframe sandbox + CSP, CORS, rate limit, isolamento do storage do app.
- Escala: adapter de storage (SQLite dev/edge → DB externo em produção), tokens curtos, jobs assíncronos.
- Monetização: catálogo com preço e billing, entitlements em `gapp`, SSO só emite se apto.

## Terminologia
- Host/Plataforma: Workz (este repositório). UI principal em `public/` e API em `api/index.php`.
- App: micro-frontend acoplável ao Host (embed) ou abrível de forma independente, reutilizando as APIs do Host.
- Catálogo: lista oficial de apps (`workz_apps.apps`).
- Instalação/Assinatura: vínculo do usuário/empresa ao app (`workz_apps.gapp`).

## O que já existe no código
- UI “Aplicativos” (sidebar e biblioteca):
  - Construção/lista em `public/js/main.js:3432`, `public/js/main.js:6917`, `public/js/main.js:7050`.
  - Busca de apps e vínculos: `workz_apps.apps`/`workz_apps.gapp` (`public/js/main.js:3436`, `public/js/main.js:4817`).
- API genérica/roteamento: `api/index.php` com rotas `/api/*` (auth, CRUD, posts, equipes, etc.).
- Cliente API com JWT: `public/js/core/ApiClient.js` (envia Authorization quando há `jwt_token`).
- Schemas base (inclui `workz_apps`): `database/recreate_workz_platform_db.sql` (seções 4.1/4.2).
- Servidor: nginx roteando `/api` para PHP-FPM com `Authorization` (`.docker/nginx/default.conf`).

## Arquitetura de Apps
- Execução
  - Embutido (recomendado para terceiros): app roda em `<iframe>` no “desktop” do Host; comunicação via `postMessage` + SSO curto.
  - Independente: aberto em `http(s)://app.localhost:9090/<slug>/` e autenticado via SSO por query/hash.
- Catálogo e Instalação
  - Catálogo: `workz_apps.apps` (dados do app — nome, ícone, slug, origem). Já há consumo no front.
  - Instalação/Vínculo: `workz_apps.gapp` (quem tem o app — por usuário e/ou empresa/equipe). Já há uso no front.
- Contexto
  - O Host informa contexto de abertura: `user`, `business` (`em`), `team` (`cm`). Apps devem persistir e aplicar o contexto ao filtrar dados.

## Modelo de dados (proposto → incremental)
- Tabela `workz_apps.apps` (ampliar com metadados):
  - Colunas sugeridas: `slug (UNIQUE)`, `src` (URL standalone), `embed_url` (URL para iframe), `color`, `desc`, `publisher`, `version`, `scopes (JSON)`, `price (DECIMAL)`, `billing_model (ENUM)`, `trial_days (INT)`.
- Tabela `workz_apps.gapp` (instalação/assinatura):
  - Campos já existentes: `us`, `em`, `ap`, `subscription`, `start_date`, `end_date`, `st`.
  - Pode receber `entitlements JSON` no futuro para granularidade.
- Observações/Referências
  - Ver `database/recreate_workz_platform_db.sql:240` em diante (seções 4.1/4.2) para base atual.

## SSO e Autenticação (recomendado)
- Objetivos
  - Não repassar o mesmo JWT do Host para o App; emitir um token curto (SSO) com claims específicas.
- Proposta
  - Endpoint: `POST /api/apps/sso` → emite JWT RS256 curto (ex.: 10 min) com claims:
    - `sub` (id do usuário), `aud` (`app:<slug>`), `ctx` (`{ type: user|business|team, id }`), `scopes` (autorizadas), `entitlements`.
  - JWKS: `GET /.well-known/jwks.json` com chave pública para os apps validarem.
  - Compatibilidade: Host mantém HS256 para o ecossistema atual até a migração total.
- Uso
  - Embutido: handshake `postMessage` do Host → App com `{ jwt, user, context }`.
  - Independente: `window.open(app.src + '?token=<SSO>')` e o app armazena o token (limpa da URL e usa em headers).

## SDK do App (contrato sugerido)
- Arquivo: `public/js/core/workz-sdk.js` (a ser criado) — interface mínima:
  - `await WorkzSDK.init({ mode: 'embed'|'standalone' })`
  - `WorkzSDK.getToken()` e `WorkzSDK.getUser()`
  - `WorkzSDK.api.get|post(path, data)` → chama `http://localhost:9090/api` com `Authorization: Bearer <token>`
  - Eventos/bridge: `WorkzSDK.on('host:event', fn)`, `WorkzSDK.emit('app:event', payload)`
  - Handshake/cor: Host envia tema/cor padrão do app (`app.color`).

## Segurança
- Iframe sandbox + CSP ao embutir apps de terceiros (limitar origins, disallow top-navigation, etc.).
- CORS no `/api` para `app.localhost` quando o app roda independente.
- Rate limit por app/token e auditoria (registrar `aud`, `slug`, `us` em logs sensíveis).
- Storage do App isolado
  - Se SQLite: manter em `storage/apps/<slug>/<db>.sqlite` (fora de `public/`) e bloquear `.sqlite` no nginx.
  - Segredos do app via `.env.local` privado e sem commit.
- Tokens de SSO curtos + revogação por expiração/renovação em `/api/apps/sso`.

## Escalabilidade
- Storage adapter para dados do app:
  - Dev/single-node: SQLite com `WAL`, `busy_timeout`, transações.
  - Produção multi-instância: DB externo (MySQL/Postgres) com mesmas queries.
- Stateless do app; usar filas/cron para tarefas pesadas; cache local leve para `/api/me`.
- Observabilidade: logs estruturados com `app_id`, `slug`, `aud`, `us`; métricas de latência/erro por app.

## Monetização
- Catálogo com pricing e modelos: `free`, `subscription`, `one_time`.
- Assinaturas em `gapp` (já há campos): validação no SSO (só emite token para vínculos válidos ou trial).
- Loops de pagamento (ex.: Stripe/MercadoPago) 
  - Checkout no Host; webhook atualiza `gapp`; SSO passa a refletir entitlement imediatamente.
- Relatórios de receita por `publisher` e governança (suspensão de apps com descumprimento).

## Lifecycle do App
1) Registro no catálogo: inserir em `workz_apps.apps` (nome, slug, ícone, `src`/`embed_url`, cor, scopes, preço).
2) Publicação de build: `public/apps/<slug>/` para assets estáticos (independente/embeeded).
3) Instalação: Host insere `workz_apps.gapp` para `us` ou `em`/`cm` conforme contexto.
4) Lançamento: Host abre app via `embed_url` (iframe) ou `src?token=<SSO>`; envia contexto.
5) Execução: app usa SDK para `/api/me`, dados de negócio/equipe e suas operações internas.

## Exemplo prático: “Tasks”
- Front: `public/apps/tasks/{index.html, embed.html, app.js, manifest.json}`.
- Storage do app: `storage/apps/tasks/tasks.sqlite` (criado sob demanda), bloqueado no nginx.
- Endpoints do app (PHP simples): `public/apps/tasks/api/tasks.php` verificando `Authorization` (decodificar SSO, validar `aud=app:tasks`).
- Tabelas (SQLite): `tasks`, `task_logs` com colunas mínimas e índices por `us`/`em`/`cm`.
- Contexto: o Host abre com `{ type, id }`; app filtra por escopo.

## Servidor e Roteamento (nginx)
- Host atual: `.docker/nginx/default.conf` já roteia `/api` e preserva `Authorization`.
- Recomendado:
  - Novo server block para `app.localhost` servindo `public/apps` (`try_files $uri $uri/ /index.html;`).
  - CORS nas rotas `/api` (permitir `Origin: app.localhost` e `Authorization`).
  - Negar acesso a `*.sqlite` e a `storage/`.

## Contratos de API do Host (propostos)
- `GET /api/me` (já existe): retorna dados do usuário logado.
- `GET /api/apps/catalog` (catálogo público/instalável por contexto).
- `GET /api/apps/entitlements?slug=<slug>&ctx=...` (status/vínculos).
- `POST /api/apps/sso` body: `{ slug, ctx }` → `{ token, exp, user, context }`.

## Checklist para publicar um App
- [ ] Definir `slug` único, ícone, cor, `src` e/ou `embed_url`.
- [ ] `manifest.json` com `name`, `version`, `scopes`, `publisher`.
- [ ] Usar `workz-sdk.js` para SSO + `/api`.
- [ ] Se usar storage local, manter fora de `public/` e com WAL.
- [ ] Registrar no catálogo e testar instalação/launch pelo Host.
- [ ] Validar CSP, sandbox e CORS (no modo embed/independente).

## Roadmap sugerido (incremental)
- Fase 1: colunas extras em `workz_apps.apps`, CORS no `/api`, bloquear `.sqlite`, `workz-sdk.js`, `launchApp(app)` unificado.
- Fase 2: `AppsController` com `catalog`, `entitlements`, `sso` e JWKS; app “Store” como referência.
- Fase 3: RS256 como padrão e enforcement de `scopes`; métricas/pagamentos/relatórios.

---
Última atualização: alinhada ao código atual em `public/js/main.js`, `api/index.php`, `database/recreate_workz_platform_db.sql` e `.docker/nginx/default.conf`.

