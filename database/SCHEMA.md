# Esquema de Dados — Workz Platform

Este documento descreve as bases de dados, tabelas e colunas utilizadas no projeto, com base no código dos controllers PHP e do frontend (`public/js/main.js` e `public/js/editor.js`). Sempre que possível, são indicadas relações, chaves e observações de uso.

Observação sobre nomes de bases: o backend abre conexão por nome (parâmetro `db`) em tempo de execução (ver `src/Models/General.php:1` e `src/Core/Database.php:1`). No código atual aparecem principalmente três bases lógicas:
- `workz_data` — dados centrais: pessoas/usuários, posts, comentários, curtidas, seguidores, depoimentos, histórico profissional.
- `workz_companies` — empresas, equipes e vínculos (empregados e membros de equipe).
- `workz_apps` — catálogo de aplicativos e instalações/assinaturas.

Há também o script `database/recreate_workz_platform_db.sql`, que recria idempotentemente os três schemas usados pela aplicação: `workz_data`, `workz_companies` e `workz_apps` (incluindo tabelas e chaves). Se o seu ambiente tiver variáveis apontando para outro schema por padrão, alinhe antes de executar os scripts.


## Convenções de nomes (glossário de colunas)
- `id`: chave primária numérica.
- `tt`: título/nome exibido (p. ex., nome do usuário/empresa/equipe/app).
- `ml`: email (mail).
- `pw`: senha (hash) — nunca retornada no `General::search`.
- `dt`: data/hora de criação/registro.
- `st`: status/ativo (0/1 em geral).
- `im`: caminho/URL da imagem principal (avatar/logo).
- `bk`: caminho/URL da imagem de capa (background).
- `cf`: descrição/biografia (texto livre).
- `un`: apelido/slug público (único por tabela lógica).
- `us`: id de usuário (people).
- `em`: id de empresa (company).
- `cm`: id de equipe (team).
- `nv`: nível/perfil de vínculo (1..4), conforme políticas.
- `pl`: id de post (publicação do editor).
- `ds`: descrição/texto (ex.: conteúdo de comentário).
- `ct`: conteúdo JSON (ex.: payload de post com mídia).
- `usmn`: lista JSON com ids de moderadores da equipe.
- `page_privacy`, `feed_privacy`: configurações de privacidade (numérico).
- `gender`, `birth`: dados demográficos opcionais.
- `contacts`: JSON normalizado de contatos/links, ex.: `[ { "type": "site", "value": "https://..." }, ... ]`.
- `national_id`: documento (ex.: CNPJ) normalizado apenas com dígitos.
- Endereço: `zip_code`, `country`, `state`, `city`, `district`, `address`, `complement`.
- Fluxo de troca de email: `pending_email`, `email_change_token`, `email_change_expires_at`.


## Base: workz_data

### Tabela: `hus` (Pessoas/Usuários)
Campos usados no código:
- `id` (PK)
- `tt` (nome)
- `ml` (email)
- `pw` (hash de senha)
- `dt` (criado em)
- `provider` (ex.: `local`, `google`, `microsoft`)
- `st` (status)
- `im` (imagem)
- `bk` (capa)
- `un` (apelido/slug único por usuário)
- `cf` (bio/descrição)
- `page_privacy`, `feed_privacy`
- `gender`, `birth`
- `contacts` (JSON)
- `pending_email`, `email_change_token`, `email_change_expires_at`

Observações
- Login/registro local e social usam esta tabela (`src/Controllers/AuthController.php:1`).
- O frontend valida disponibilidade de `un` em `hus`, `companies` e `teams`.
- Recomenda-se índice único em `ml` e (opcional) em `un`.

### Tabela: `hpl` (Posts do editor)
Definida em `database/recreate_workz_platform_db.sql` e usada no backend em `src/Controllers/PostsController.php:1`.
Campos usados no código:
- `id` (PK)
- `us` (autor, usuário)
- `tp` (tipo; no frontend mapeado para 1=image, 2=video, 3=mixed)
- `dt` (data/hora)
- `cm` (equipe — opcional; 0 quando não aplicável)
- `em` (empresa — opcional; 0 quando não aplicável)
- `st` (status de publicação, padrão 1)
- `ct` (JSON com a mídia e metadados do post)
Índices recomendados/usados: `us`, `tp`, `dt`, `st`.

### Tabela: `hpl_comments` (Comentários dos posts)
Campos usados no código:
- `id` (PK)
- `pl` (post — `hpl.id`)
- `us` (autor do comentário)
- `ds` (texto)
- `dt` (data/hora)
Índices recomendados: `pl`, `us`, `dt`.

### Tabela: `lke` (Curtidas de post)
Campos usados no código (frontend):
- `pl` (post — `hpl.id`)
- `us` (usuário)
- `dt` (data/hora)
Regras de negócio: par (`pl`,`us`) deve ser único. O frontend consulta total por `pl` e estado do usuário atual.

### Tabela: `usg` (Seguidores de pessoas)
Campos usados no código:
- `s0` (seguidor — user id)
- `s1` (seguido — user id)
Regra: par (`s0`,`s1`) deve ser único. Usada para construir widgets de relacionamentos.

### Tabela: `testimonials` (Depoimentos)
Campos usados no código (frontend):
- `id`
- `author` (id de usuário autor)
- `recipient` (id do destinatário — pode ser pessoa/empresa/equipe)
- `recipient_type` (ex.: `people` | `businesses` | `teams`)
- `content` (texto)
- `status` (0=pending, 1=aceito, 2=revertido)

### Tabela: `work_history` (Histórico profissional)
Definição base em `database/migrations/2025_02_XX_create_work_history.sql` e replicada em `database/recreate_workz_platform_db.sql`.
Campos:
- `id` (PK)
- `us` (usuário)
- `em` (empresa — opcional)
- `tt` (título/cargo)
- `cf` (descrição)
- `type` (ex.: `clt`, `contrato`, `freelancer`, `estagio`)
- `location`
- `start_date`, `end_date` (nulo = vigente)
- `visibility` (1 visível, 0 oculto)
- `verified` (0/1), `verified_by`, `verified_at`
- `st` (status/ativo)
- `created_at`, `updated_at`
Índices: `us`, `em`, `st`, `verified`.


## Base: workz_companies

### Tabela: `companies` (Negócios)
Campos usados no código:
- `id` (PK)
- `tt` (nome)
- `im` (logo)
- `bk` (capa)
- `st` (status)
- `un` (apelido/slug do negócio)
- `cf` (descrição)
- `page_privacy`, `feed_privacy`
- `national_id` (CNPJ apenas dígitos)
- Endereço: `zip_code`, `country`, `state`, `city`, `district`, `address`, `complement`
- `contacts` (JSON)
Observações: permissões de gestão são derivadas via vínculo ativo e nível no `employees` (ver `src/Policies/BusinessPolicy.php:1`).

### Tabela: `employees` (Vínculo usuário–empresa)
Campos usados no código:
- `id` (PK)
- `us` (usuário)
- `em` (empresa)
- `nv` (nível 1..4; 4=gestor)
- `st` (status: 1 ativo, 0 pendente/convite)
- `start_date`, `end_date`
Observações: controlada por `CompaniesController` para aceitar/recusar/alterar nível.

### Tabela: `teams` (Equipes)
Campos usados no código:
- `id` (PK)
- `tt` (nome)
- `im` (imagem)
- `bk` (capa)
- `st` (status)
- `un` (apelido/slug da equipe)
- `us` (dono/criador — user id)
- `usmn` (JSON com ids de moderadores)
- `em` (empresa à qual a equipe pertence)
- `cf` (descrição)
- `feed_privacy`
- `contacts` (JSON)
Observações: políticas em `src/Policies/TeamPolicy.php:1` (owner ou moderador pode gerir/excluir).

### Tabela: `teams_users` (Vínculo usuário–equipe)
Campos usados no código:
- `id` (PK)
- `us` (usuário)
- `cm` (equipe — team id)
- `nv` (nível 1..4)
- `st` (status: 1 ativo, 0 pendente)


## Base: workz_apps

### Tabela: `apps` (Catálogo de aplicativos)
Campos usados no código:
- `id` (PK)
- `tt` (nome)
- `im` (ícone)
- `vl` (valor/preço; `0` = gratuito)

### Tabela: `gapp` (Instalações/Assinaturas)
Campos usados no código:
- `id` (PK)
- `us` (usuário instalador/assinante)
- `em` (empresa — quando instalação é no contexto do negócio)
- `ap` (app id)
- `subscription` (0/1)
- `start_date`, `end_date` (assinaturas ativas têm `end_date = NULL`)
Observações: o frontend usa `EXISTS` para cruzar `gapp.ap` com `apps.id` ao listar/mostrar assinaturas.

### Tabela: `quickapps` (Favoritos/Acesso Rápido)
Campos:
- `id` (PK)
- `us` (usuário)
- `ap` (app id)
- `sort` (posição opcional)
- `dt` (criado em)
Regras:
- Único por `(us, ap)`; cascata ao excluir usuário/app.
Uso no frontend: define os apps mostrados na barra de acesso rápido do dashboard e permite adicionar/remover pelos botões de estrela na biblioteca ou pelas configurações do app no menu lateral.


## Tabelas auxiliares de mídia (uploads)
O upload de mídias dos posts salva arquivos em `public/uploads/posts/...` (ver `src/Controllers/PostsController.php:1`). As URLs relativas são gravadas dentro do JSON `ct` dos posts `hpl`.

A atualização de imagens (avatar/capa) usa `GeneralController::uploadImage` e grava `im`/`bk` nas tabelas-alvo conforme a entidade:
- Pessoas → `workz_data.hus`
- Negócios → `workz_companies.companies`
- Equipes → `workz_companies.teams`


## Notas de implantação
- Docker compose agora define `MYSQL_DATABASE: workz_data` (ver `docker-compose.yml`), apenas como schema inicial em ambientes novos. O backend acessa explicitamente `workz_data`, `workz_companies` e `workz_apps` conforme cada operação.
- `.env` deve alinhar `DB_NAME`/`DB_DATABASE` para `workz_data` para compatibilidade com scripts PHP legados em `public/app/`.
- O script `public/app/setup_database.php` cria `hpl` e `hpl_comments` no `dbname` configurado (padrão `workz_data`). Já o `database/recreate_workz_platform_db.sql` cria/garante os três schemas (`workz_data`, `workz_companies`, `workz_apps`).

Procedimento de restauração (Docker)
- Subir MySQL e aguardar health: `docker compose up -d mysql` e `docker compose ps mysql`.
- Importar schemas: `docker cp database/recreate_workz_platform_db.sql workz_platform_mysql:/tmp/recreate.sql` e `docker exec -i workz_platform_mysql mysql -uroot -proot_password < /tmp/recreate.sql`.
- Opcional seed: `docker cp database/seed_workz_platform.sql workz_platform_mysql:/tmp/seed.sql` e `docker exec -i workz_platform_mysql mysql -uroot -proot_password < /tmp/seed.sql`.
- Validar em `http://localhost:8081` (phpMyAdmin) e `http://localhost:9090/app/test_db.php`.
- Para `hus.ml` (email) e `hus.un` (apelido), e para pares únicos de vínculo (`employees`, `teams_users`, `usg`, `lke`), recomenda‑se criar índices únicos para coerência e integridade referencial.


## Relações resumo
- Pessoas (`workz_data.hus`) têm Posts (`workz_data.hpl`), Comentários (`workz_data.hpl_comments`) e Curtidas (`workz_data.lke`).
- Seguidores (`workz_data.usg`) modela follow (s0 → s1) entre pessoas.
- Pessoas vinculam‑se a Empresas via `workz_companies.employees (us, em)`.
- Empresas possuem Equipes (`workz_companies.teams.em = companies.id`).
- Pessoas vinculam‑se a Equipes via `workz_companies.teams_users (us, cm)`.
- Apps instalados/assinados via `workz_apps.gapp (us|em, ap)` com detalhes em `workz_apps.apps`.
- Histórico profissional em `workz_data.work_history` pode referenciar opcionalmente uma Empresa (`em`).


---
Última atualização: gerada a partir de Controllers e JS do repositório nesta data.
