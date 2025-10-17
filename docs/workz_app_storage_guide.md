# Guia de uso do App Storage (KV / DOCS / BLOBS)

## Visão geral

- **KV**: pares chave-valor por **app** e **escopo** (user, business, team). Perfeito para **preferências**, **flags**, **contadores**, **config**.
- **DOCS**: documentos JSON (com `doc_type` livre). Use para **listas/itens** (tarefas, notas, eventos, etc.). Indexe campos usados via **colunas geradas**.
- **BLOBS**: metadados de anexos (arquivo vai para S3/MinIO/CDN). Use para **uploads**.

### Namespacing forte
Todas as operações recebem:
- `app_id` (inferido do **JWT** `aud=app:{id}`),
- `scope_type` (`user` | `business` | `team`),
- `scope_id` (id do usuário/negócio/equipe).

Assim, **um app não enxerga o storage do outro** e cada app fica isolado por contexto.

---

## Checklist ao criar um novo app

1. **Defina o `doc_type`** (se for usar DOCS). Ex.: `"task"`, `"note"`, `"event"`.
2. **Liste campos que serão filtrados/ordenados com frequência** (ex.: `status`, `dueDate`).
3. **Peça índices** para esses campos: crie **colunas geradas STORED** e índices (vide exemplos).
4. **Decida o escopo**: seu app vai gravar/ler por `user`, `business` ou `team`?
5. **Use o SDK** para chamadas: `WorkzSDK.api.get/post(...)` (sempre com JWT do SSO).
6. **Projete quotas** (máx. docs/kv/blobs por escopo) — o painel aplica limites.
7. **Implemente export/import** no painel do app (útil para backup/migrações).

---

## Contratos de API (sugestão mínima)

> Os endpoints abaixo são o “contrato estável” para apps. O backend valida o **JWT** (audience = app) e resolve `app_id` e permissões.

### KV

- **GET** `/api/appdata/kv?scopeType=user&scopeId=123&key=prefs.theme`
- **POST** `/api/appdata/kv`

### DOCS

- **POST** `/api/appdata/docs/query`
- **POST** `/api/appdata/docs/upsert`
- **DELETE** `/api/appdata/docs/{type}/{docId}`

### BLOBS

- **POST** `/api/appdata/blobs/sign`
- **POST** `/api/appdata/blobs/commit`

> **Permissões sugeridas (JWT scopes)**:  
> `apps:data:kv.read`, `apps:data:kv.write`, `apps:data:docs.read`, `apps:data:docs.write`, `apps:data:blobs.write`

---

## Exemplos SQL internos

### KV — upsert idempotente
```sql
INSERT INTO workz_apps_storage_kv (app_id, scope_type, scope_id, `key`, `value`, `version`)
VALUES (:app_id, :scope_type, :scope_id, :key, CAST(:json AS JSON), 1)
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `version` = `version` + 1,
  `updated_at` = CURRENT_TIMESTAMP;
```

### DOCS — criar/atualizar documento
```sql
INSERT INTO workz_apps_storage_docs
  (app_id, scope_type, scope_id, doc_type, doc_id, `data`, `meta`, `version`)
VALUES
  (:app_id,:scope_type,:scope_id,:doc_type,:doc_id, CAST(:data_json AS JSON), CAST(:meta_json AS JSON), 1)
ON DUPLICATE KEY UPDATE
  `data` = VALUES(`data`),
  `meta` = VALUES(`meta`),
  `version` = `version` + 1,
  `updated_at` = CURRENT_TIMESTAMP;
```

### BLOBS — salvar metadados após upload
```sql
INSERT INTO workz_apps_storage_blobs
  (app_id, scope_type, scope_id, blob_id, `path`, mime, size_bytes, checksum)
VALUES
  (:app_id,:scope_type,:scope_id,:blob_id,:path,:mime,:size,:checksum)
ON DUPLICATE KEY UPDATE
  `path` = VALUES(`path`),
  mime = VALUES(mime),
  size_bytes = VALUES(size_bytes),
  checksum = VALUES(checksum);
```

---

## Exemplos de uso via SDK

```js
// Ler preferências (KV)
const prefs = await WorkzSDK.api.get(`/appdata/kv?scopeType=user&scopeId=${user.id}&key=prefs.theme`);

// Salvar preferências (KV)
await WorkzSDK.api.post('/appdata/kv', {
  scopeType: 'user',
  scopeId: user.id,
  key: 'prefs.theme',
  value: { primary: '#3b82f6', density: 'compact' },
  ifVersion: prefs?.version ?? 0
});
```

---

## Boas práticas

- **JWT `aud` = `app:{id}`**: backend extrai `app_id` **do token**, não do body.  
- **Scopes**: habilite/negue operações por `apps:data:*` para cada app/instalação.
- **Quotas**: limite `max_docs`, `max_kv`, `max_blobs` por `scope` (por app).
- **Auditoria**: registre `actor_user_id`, `action`, `doc_id/key`, `diff` (JSON patch).
- **Export/Import**: forneça `GET /api/appdata/export?scope...` (NDJSON) para backup/migração.
- **Índices sob demanda**: quando perceber filtros repetidos, crie **colunas geradas STORED** + índice.  
- **Keyset pagination** para listas grandes.
- **LGPD/GDPR**: tenha endpoint administrativo para apagar tudo de um `scopeType=user&scopeId=X` em todos os apps.

---

## Quando evoluir para “Dedicated Schema”

Se um app exigir **joins complexos**/altíssimo volume, promova seu `app_id` para `storage_driver='schema'`:
- Crie schema `workz_app_{id}` (no mesmo MySQL).
- Migrações versionadas desse app moram **nesse schema**.
- O **adapter** do backend seleciona o driver por `app_id`, mantendo os **mesmos endpoints**.

---

## TL;DR

- **KV** → preferências/configs  
- **DOCS** → listas e registros  
- **BLOBS** → anexos  
- Tudo namespaced por **app + scope**.  
- **Índices**: use colunas geradas STORED para filtros/ordenação.  
- **Mesmos endpoints** para todos os apps — simples via SDK.  
- **Upgrade opcional** para schema dedicado quando necessário.
