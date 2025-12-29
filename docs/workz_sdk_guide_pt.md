# Guia do Desenvolvedor: WorkzSDK

> **Versão do SDK:** 2.0 (baseado em `workz-sdk-v2.js`)

## 1. Visão Geral

O **WorkzSDK** é a ponte de comunicação entre seu aplicativo (seja ele JavaScript ou Flutter) e a plataforma Workz!. Ele abstrai a complexidade de autenticação, acesso à API e armazenamento de dados, fornecendo uma interface unificada e fácil de usar.

**Principais Funcionalidades:**
- **Inicialização e Autenticação**: Configura o ambiente do aplicativo e gerencia a autenticação de forma transparente.
- **Comunicação com a API**: Oferece um proxy para fazer chamadas autenticadas à API da plataforma (`GET`, `POST`, `PUT`, etc.).
- **Gerenciamento de Estado**: Fornece acesso a informações do usuário (`getUser`) e do contexto de execução (`getContext`).
- **Comunicação entre Janelas**: Permite a troca de eventos entre o app (em um iframe) e a plataforma principal.
- **API de Storage**: Interface completa para armazenamento de dados persistentes, dividida em três tipos:
  - **KV (Chave-Valor)**: Para configurações simples e dados pequenos.
  - **Docs (Documentos)**: Para armazenar objetos JSON mais complexos e estruturados.
  - **Blobs (Arquivos)**: Para upload e download de arquivos.

---

## 2. Início Rápido

A inicialização é o primeiro e mais importante passo. O `embed.html` já garante que o SDK seja carregado antes do seu código.

### 2.1. Contrato de Inicialização (JavaScript)

Seu aplicativo JavaScript **deve** expor um objeto global `window.StoreApp` com um método `bootstrap`. O `embed.html` chamará este método após o SDK estar pronto.

```javascript
// Exemplo mínimo no seu código de app
window.StoreApp = {
  async bootstrap() {
    // O SDK já foi inicializado pelo embed.html,
    // mas você pode acessar os dados aqui.
    console.log('🚀 App inicializado via bootstrap!');

    const user = WorkzSDK.getUser();
    const appConfig = window.WorkzAppConfig;

    const root = document.getElementById('app-root');
    if (root) {
      root.innerHTML = `
        <article>
          <h2>Olá, ${user?.name || 'usuário'}!</h2>
          <p>Você está executando o app "${appConfig.name}".</p>
        </article>
      `;
    }
  }
};
```

### 2.2. Inicialização Manual (Contextos Específicos)

Embora o `embed.html` cuide da inicialização, é útil entender o processo. O método `init` é assíncrono e retorna uma `Promise`.

```javascript
async function initializeMyStandaloneApp() {
  try {
    const success = await WorkzSDK.init({
      mode: 'standalone', // 'standalone' ou 'embed'
      baseUrl: '/api'     // Opcional, padrão é '/api'
    });

    if (success) {
      console.log('SDK inicializado com sucesso!');
      const user = WorkzSDK.getUser();
      console.log('Usuário:', user);
    } else {
      console.error('Falha ao inicializar o SDK.');
    }
  } catch (error) {
    console.error('Erro na inicialização do SDK:', error);
  }
}
```

**Modos de Inicialização:**
- `standalone`: Usado quando o app é executado em um iframe. O token de autenticação é lido automaticamente da querystring (`?token=...`).
- `embed`: Usado quando o app é parte da UI principal e a comunicação é feita via `postMessage`.

> 💡 Na prática, para apps criados no **App Builder**, o `embed.html` já executa `WorkzSDK.init()` para você. Seu foco deve ser o método `bootstrap`.

---

## 3. API Principal

Após a inicialização, todos os métodos do SDK estão disponíveis.

### 3.1. Autenticação e Contexto

Acesse informações sobre o usuário e o ambiente de execução.

```javascript
// Retorna o token JWT atual (string ou null)
const token = WorkzSDK.getToken();

// Retorna o objeto do usuário logado (ou null)
// Ex: { id: 123, name: 'João Silva', email: 'joao@workz.com', ... }
const user = WorkzSDK.getUser();

// Retorna o contexto da plataforma (se disponível)
// Ex: { type: 'user', id: 123 }
const context = WorkzSDK.getContext();
```

### 3.2. Comunicação com a API (`WorkzSDK.api`)

Faça requisições HTTP autenticadas para o backend da Workz!. O SDK injeta o `Authorization: Bearer ...` automaticamente.

```javascript
async function fetchMyData() {
  try {
    // GET
    const myApps = await WorkzSDK.api.get('/apps/my-apps');
    console.log('Meus Apps:', myApps.data);

    // POST
    const newUserPref = { theme: 'dark', lang: 'pt-br' };
    const creationResult = await WorkzSDK.api.post('/preferences', newUserPref);
    if (creationResult.success) {
      console.log('Preferência salva!');
    }

    // PUT
    const updatedData = { title: 'Novo Título do App' };
    const updateResult = await WorkzSDK.api.put('/apps/123', updatedData);
    console.log('App atualizado:', updateResult);

  } catch (error) {
    console.error('Erro na chamada de API:', error);
  }
}
```

### 3.3. Eventos (`on` e `emit`)

Comunique-se com a janela principal da plataforma.

```javascript
// Ouvindo eventos da plataforma
WorkzSDK.on('theme:changed', (themeData) => {
  console.log('Tema da plataforma alterado!', themeData);
  // Ex: aplicar a nova cor primária
  document.documentElement.style.setProperty('--pico-primary', themeData.primaryColor);
});

// Emitindo eventos para a plataforma
function notifyAction() {
  WorkzSDK.emit('app:custom-action', {
    action: 'item-created',
    itemId: 456
  });
}
```

---

## 4. Layout e Orientação de Tela

Ao publicar, informe também os metadados de layout para que o player e o preview saibam adaptar o iframe ao seu app. Esses campos são opcionais, mas recomendamos preenchê-los quanto mais próximo a sua experiência visual estiver do comportamento final.

| Campo | Tipo | Descrição |
| --- | --- | --- |
| `aspect_ratio` | string | Proporção largura:altura (ex: `16:9`, `4:3`). |  
| `supports_portrait` | boolean | `true` se o app está preparado para retrato; `false` caso a interface seja exclusiva em paisagem. |
| `supports_landscape` | boolean | `true` se o app suporta paisagem; `false` se o layout for exclusivamente retrato. |

Se nenhum valor for enviado, usamos `aspect_ratio: '4:3'` e assumimos que retrato e paisagem estão disponíveis (ambos `true`).

```javascript
window.WorkzAppConfig.layout = {
  aspect_ratio: '4:3',
  supports_portrait: true,
  supports_landscape: true
};
```

Esses dados também são expostos na API para que o app runner possa calcular `object-fit`, placeholders e dimensões iniciais quando o aplicativo for carregado em dispositivos móveis ou em previews lado a lado.

## 4. API de Storage

O `WorkzSDK.storage` oferece métodos para persistir dados de forma segura e com escopo definido (por usuário, empresa ou time). O escopo é gerenciado automaticamente pelo backend com base no usuário autenticado.

### 4.1. Storage Chave-Valor (`storage.kv`)

Ideal para armazenar configurações, preferências ou pequenos volumes de dados.

```javascript
async function manageUserPreferences() {
  // Salvar um valor (com TTL opcional de 1 hora em segundos)
  await WorkzSDK.storage.kv.set({
    key: 'user_theme',
    value: { mode: 'dark', contrast: 'high' },
    ttl: 3600
  });

  // Obter um valor
  const themePref = await WorkzSDK.storage.kv.get('user_theme');
  if (themePref.success) {
    console.log('Tema do usuário:', themePref.data.value);
  }

  // Listar todas as chaves do app para o usuário
  const allKeys = await WorkzSDK.storage.kv.list();
  console.log('Todas as chaves:', allKeys.data);

  // Deletar uma chave
  await WorkzSDK.storage.kv.delete('user_theme');
}
```

### 4.2. Storage de Documentos (`storage.docs`)

Perfeito para armazenar objetos JSON mais complexos, como posts, tarefas, ou registros estruturados.

```javascript
async function manageTasks() {
  const taskId = 'task_' + Date.now();

  // Salvar um documento
  await WorkzSDK.storage.docs.save(taskId, {
    title: 'Finalizar documentação do SDK',
    status: 'in-progress',
    tags: ['docs', 'sdk']
  });

  // Obter um documento pelo ID
  const task = await WorkzSDK.storage.docs.get(taskId);
  if (task.success) {
    console.log('Tarefa encontrada:', task.data[0].data);
  }

  // Consultar documentos com filtros
  const pendingTasks = await WorkzSDK.storage.docs.query({
    status: 'in-progress'
  });
  console.log('Tarefas em andamento:', pendingTasks.data);

  // Deletar um documento
  await WorkzSDK.storage.docs.delete(taskId);
}
```

### 4.3. Storage de Arquivos (`storage.blobs`)

Para fazer upload e gerenciar arquivos.

```javascript
// Supondo um <input type="file" id="file-input"> no seu HTML

const fileInput = document.getElementById('file-input');

fileInput.addEventListener('change', async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  try {
    // Fazer upload de um arquivo
    const uploadResult = await WorkzSDK.storage.blobs.upload('meu-avatar.png', file);
    if (uploadResult.success) {
      const fileId = uploadResult.data.id;
      console.log('Upload bem-sucedido! ID do arquivo:', fileId);

      // Para obter o arquivo, o SDK abre uma nova janela para download
      // A URL é autenticada e de curta duração.
      WorkzSDK.storage.blobs.get(fileId);
    }
  } catch (error) {
    console.error('Erro no upload:', error);
  }
});
```

---

## 5. Boas Práticas

1.  **Verifique a Disponibilidade**: Sempre presuma que métodos que retornam dados (como `getUser` ou `storage.kv.get`) podem retornar `null` ou um resultado sem sucesso.

    ```javascript
    const user = WorkzSDK.getUser();
    if (user) {
      // prossiga
    } else {
      // lide com o caso de usuário não autenticado
    }
    ```

2.  **Use `try...catch`**: Envolva chamadas de API e de `storage` em blocos `try...catch` para tratar erros de rede ou de permissão de forma elegante.

3.  **Aproveite o `WorkzAppConfig`**: O objeto `window.WorkzAppConfig` é injetado pelo `embed.html` e contém metadados úteis sobre seu app, como `id`, `name`, `slug`, `version` e `theme`.

    ```javascript
    const appName = window.WorkzAppConfig.name;
    const primaryColor = window.WorkzAppConfig.theme.primaryColor;
    ```

4.  **Estruture seu Código**: Organize a lógica do seu aplicativo dentro do objeto `window.StoreApp` para manter o escopo global limpo.

    ```javascript
    window.StoreApp = {
      // ...
      async bootstrap() { /* ... */ },
      ui: {
        render() { /* ... */ },
        update() { /* ... */ }
      },
      api: {
        fetchData() { /* ... */ }
      }
    };
    ```

---

## 6. Pagamentos (Fase 1)

O SDK v2 inclui um módulo inicial de pagamentos para compras avulsas com Mercado Pago (Checkout Pro).

Exemplo:

```javascript
const res = await WorkzSDK.payments.createPurchase({
  appId: window.WorkzAppConfig.id,
  title: 'Licença do App',
  unitPrice: 19.90,
  quantity: 1,
  currency: 'BRL',
  backUrls: {
    success: location.href,
    failure: location.href,
    pending: location.href,
  },
});
if (res && res.success && res.init_point) {
  window.location.href = res.init_point; // abrir checkout
}
```

Ao aprovar o pagamento, o webhook atualiza a transação e a plataforma libera automaticamente o acesso do usuário ao app (entitlement).
