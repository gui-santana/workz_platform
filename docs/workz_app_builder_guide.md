
# Workz! App Builder — Guia de Arquitetura, Runtime e Integração
> Abrange apps JavaScript **nativos** renderizados via `/public/apps/embed.html` e o fluxo de criação/edição/publicação pelo **App Builder**.

## 1) Visão Geral
O **App Builder** é um aplicativo JavaScript nativo (executado dentro da própria plataforma) que permite criar outros aplicativos Workz! em **JavaScript** ou **Flutter**.  
Os apps **JavaScript** têm seu código-fonte persistido em **`apps.js_code`** e são renderizados em um _iframe_ através do documento **`/public/apps/embed.html`**.

Para apps **Flutter**, o Builder aciona pipelines de _build_ multiplataforma que alimentam as tabelas `build_queue` e `flutter_builds` (descritas na documentação do schema).

---

## 2) Pipeline de Renderização (JavaScript)
```
apps (js_code, color, icon, ...)
         │
         ├──> Backend lê e injeta em /public/apps/embed.html
         │         • {{APP_COLOR}}  → apps.color
         │         • {{APP_ICON}}   → ícone do app (URL/Base64)
         │         • {{APP_SCRIPT}} → apps.js_code
         │
         └──> /public/apps/embed.html carrega:
                    - Pico.css (UI rápida)
                    - WorkzSDK (/js/core/workz-sdk.js)
                    - Script do app (injetado)
                    - Chama window.StoreApp.bootstrap() após WorkzSDK.init()
```
**Contrato de inicialização:**
- O script do app **DEVE** definir `window.StoreApp.bootstrap = async () => { ... }`.
- O `embed.html` oculta o _loader_ quando o bootstrap termina (ou ao final do `try/finally`).  
- O SDK é inicializado em modo `standalone` (token no querystring do iframe).

### 2.1) `embed.html` (pontos-chave)
- **Theme/branding:** `--pico-primary: {{APP_COLOR}}` controla a cor principal.  
- **Loader acessível:** `#app-loader` com `aria-label="Carregando aplicativo"` e _skeleton_ usando `aria-busy`.  
- **Isolamento:** o app roda em um contêiner simples; o SDK media a comunicação com a plataforma.

---

## 3) Contrato do App (JavaScript)
O app JavaScript deve expor um objeto global **`window.StoreApp`** contendo ao menos:
- `bootstrap(): Promise<void>` — ponto de entrada.  
Opcionalmente, pode organizar utilitários e _handlers_ internos. Exemplo mínimo:
```js
window.StoreApp = {
  async bootstrap () {
    await WorkzSDK.init({ mode: 'standalone' });
    const user = WorkzSDK.getUser();
    const ctx  = WorkzSDK.getContext();
    document.getElementById('app-root').innerHTML = `Olá, ${user.name}! [${ctx?.type}]`;
  }
};
```

### 3.1) WorkzSDK (expectativas)
- `init({ mode })` — inicializa o canal com a plataforma.
- `getUser()` e `getContext()` — dados básicos para render.  
- `api.get/post/put(path, body?)` — _proxy_ HTTP autenticado para rotas da plataforma.  
- Eventos: `WorkzSDK.on(event, cb)` / `WorkzSDK.emit(event, data)`.  
- Storage (quando o app solicitou _scopes_): `storage.kv|docs|blobs`.

> **Dica**: trate o SDK como **assíncrono** na inicialização do app; carregue dados após `init()`.

---

## 4) App Builder — UX, Campos e Mapeamentos
O Builder guia o autor por **6 passos**. Abaixo, os principais campos e seu mapeamento na base **`workz_apps`**.

| Passo | Campo (UI) | App Builder (state) | Tabela/Coluna |
|---|---|---|---|
| 1: Empresa | Empresa (select) | `appData.company.id` | `apps.exclusive_to_entity_id` (*restrição opcional de uso*) |
| 2: Tipo | JavaScript ou Flutter | `appType` | `apps.app_type` |
| 3: Informações | Nome | `appData.title` | `apps.t` |
| | Slug | `appData.slug` | `apps.slug` |
| | Descrição | `appData.description` | `apps.ds` |
| | Ícone | `appData.icon` (Base64/URL) | (normalmente persistido como URL em `apps.icon` ou em storage) |
| | Cor | `appData.color` | `apps.color` |
| 4: Configuração | Nível de Acesso | `appData.accessLevel` | `apps.access_level` |
| | Versão | `appData.version` | `apps.version`/`apps.js_version` (JS) |
| | Tipo de Entidade | `appData.entityType` | `apps.entity_type` |
| | Preço (R$) | `appData.price` | `apps.vl` |
| | Scopes | `appData.scopes[]` | `apps.scopes` (JSON) |
| 5: Código | JS ou Dart | `appData.code` / `appData.dartCode` | `apps.js_code` / `apps.dart_code` |
| 6: Revisão | Publicar | API `POST /apps/create` ou `PUT /apps/:id` | cria/atualiza **`apps`** e opcionalmente **`app_versions`** |

**Revisão & Publicação**
- `app_reviews` registra aprovações/rejeições.  
- Publicação/retirada da biblioteca: `POST /apps/publish` e `POST /apps/unpublish` (status exibido em “Meus Apps”).

---

## 5) Rotas consumidas (pelo Builder)
- `GET /me` — obtém empresas do usuário (filtra moderador: `nv >= 3`).  
- `POST /apps/create` — cria app; **payload base**:
```json
{
  "company_id": <int>,
  "title": "string",
  "slug": "string",
  "description": "string",
  "color": "#RRGGBB",
  "access_level": 1,
  "version": "1.0.0",
  "entity_type": 0,
  "price": 0,
  "scopes": ["profile.read","storage.kv.write"],
  "app_type": "javascript|flutter",
  "js_code": "...",
  "dart_code": "...",
  "icon": "data:image/png;base64,... | URL"
}
```
- `PUT /apps/:id` (ou _fallback_ `POST /apps/update/:id`) — atualiza app existente.  
- `GET /apps/my-apps` — lista apps do autor (estado de publicação).  
- `POST /apps/publish` / `POST /apps/unpublish` — alterna visibilidade.  
- `GET /apps/:id` — carrega dados para “Editar”.  
- `GET /apps/build-status/:id` — mostra status + artefatos (`flutter_builds`).

> O Builder possui _polyfill_ para `api.put()` caso a implementação do SDK não a exponha (`ensurePutMethod()`).

---

## 6) Modo Preview (JavaScript)
O Builder abre um **modal** com um _iframe_ de _preview_ que:
- injeta **Pico.css**, simula `WorkzSDK` (métodos mínimos) e **executa o código** do campo editor;
- intercepta `console.log/error/warn` e erros JS e **posta mensagens** para o _parent_ via `postMessage` para um “Console de Debug”;
- orienta o autor a definir `window.StoreApp.bootstrap()` e tenta executá-lo após 100ms.

**Segurança do Preview**
- O `iframe` usa `sandbox="allow-scripts allow-same-origin"`. **Não** há `allow-top-navigation`, reduzindo riscos.  
- O código não persiste nada por padrão; chamadas de API são _mockadas_.

---

## 7) Editor de Código
- Biblioteca: **CodeMirror 5** (JS + _hints_ básicos para WorkzSDK).  
- Recursos: _line numbers_, _active line_, _fold_, _autocomplete_, _toggle wrap_, _formatador_ simples.  
- Teclas de atalho: `Ctrl-/` comenta, `F11` tela cheia, `Esc` sai da tela cheia, `Ctrl-F/H/G` busca/troca/linha.  
- Métricas exibidas: linhas, caracteres, palavras.

---

## 8) Fluxo Flutter (Builder → CI → Tabelas)
1. Autor escolhe **Flutter** no Passo 2; o código **Dart** é salvo em `apps.dart_code` (ou repositório externo, conforme estratégia).  
2. Uma solicitação de build é enfileirada em **`build_queue`** (`build_type`), com `status='pending'`.  
3. O _runner_ CI atualiza `started_at/completed_at/build_log` e grava artefatos por plataforma em **`flutter_builds`** (`status: building→ready→published`).  
4. O App Builder exibe o painel de _builds_ via `GET /apps/build-status/:id`.

---

## 9) Escopos e Integração com Storage
Ao publicar, o autor declara _scopes_ de permissões. Com base neles, o app pode usar:
- `storage.kv.*` → **`storage_kv`** (preferível para chaves pequenas com TTL).  
- `storage.docs.*` → **`storage_docs`** (documentos JSON versionados).  
- `storage.blobs.*` → **`storage_blobs`** (uploads/arquivos).

> Reforce _server-side_ a verificação de _scopes_ ao atender às chamadas do SDK.

---

## 10) Mapeamento de Estados/Status
- **`apps.build_status`**: `pending` → `building` → `success` | `failed`.  
- **`flutter_builds.status`**: `building` → `ready` → `published` | `failed`.  
- **`app_reviews.status`**: `pending` → `approved` | `rejected` | `needs_changes`.

---

## 11) Boas Práticas de Segurança
- **CSP** no `embed.html` (ex.: restringir `script-src` à CDN e ao domínio Workz!).  
- Sanitizar `{{APP_ICON}}`/`{{APP_SCRIPT}}` no backend; bloquear HTML direto não intencional.  
- Tokenização do _iframe_ (modo `standalone`) com **tempo de vida curto**; valide _origin_ nas mensagens.  
- Rate limit e auditoria para rotas `/apps/*`; registre `reviewer_id` e comentários.  
- Ao publicar preview/execução: **não** eleve permissões; o SDK deve continuar aplicando _scopes_.

---

## 12) Troubleshooting
- **“WorkzSDK não foi encontrado”** → verifique `/js/core/workz-sdk.js` no `embed.html` e _load order_.  
- **`window.StoreApp.bootstrap` não existe** → template mínimo ausente no `js_code`.  
- **Preview sem logs** → checar `postMessage` (mesmo domínio) e listener `window.addEventListener('message', ...)`.  
- **PUT ausente** → `ensurePutMethod()` ativa `apiPut` de _fallback_.  
- **Build Flutter preso em `pending`** → validar _worker_ da `build_queue` e permissões em storage.

---

## 13) Exemplos
### 13.1) “Hello, Workz!” (JavaScript)
```js
window.StoreApp = {
  async bootstrap() {
    await WorkzSDK.init({ mode: 'standalone' });
    const user = WorkzSDK.getUser();
    document.getElementById('app-root').innerHTML = `
      <article class="container">
        <h2>Olá, ${user.name || 'Usuário'}!</h2>
        <p>Este é meu primeiro app Workz! 🚀</p>
      </article>`;
  }
};
```

### 13.2) Payload de Criação (resumo)
```json
{ "company_id": 123, "app_type": "javascript", "title": "Meu App",
  "slug": "meu-app", "js_code": "/* ... */", "color": "#3b82f6",
  "access_level": 1, "scopes": ["storage.kv.write"] }
```

---

## 14) Itens de Evolução (sugestões)
- Salvar _snapshots_ de `js_code` em **`app_versions`** automaticamente a cada publicação.  
- Adicionar `CSP` e `sandbox` também ao `embed.html` em produção com _nonce_.  
- Normalizar `icon` no **storage_blobs** e referenciar o caminho/URL.  
- Validar `slug` único por `company_id` e criar índice funcional.

---

> **Resumo:** O App Builder conecta o autor ao ecossistema Workz!: coleta metadados, grava `apps` (JS/Dart), aciona _builds_ (Flutter), fornece _preview_ seguro e usa o **WorkzSDK** como camada de integração. O `embed.html` é o _shell_ de execução dos apps JavaScript, injetando cor, ícone e código de forma controlada.
