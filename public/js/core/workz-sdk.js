// public/js/core/workz-sdk.js
// Updated: 2025-01-20 - Added PUT method support

(function(global){
  const DEFAULT_BASE = '/api'; // Usar caminho relativo para ser agnóstico ao domínio

  function parseQueryToken(){
    try {
      const url = new URL(window.location.href);
      const fromQuery = url.searchParams.get('token');
      if (fromQuery) {
        // Não remover o token da URL interna do iframe, pois pode ser necessário em reloads.
        // A URL visível para o usuário não contém o token.
        return fromQuery;
      }
      if (window.location.hash) {
        const h = new URLSearchParams(window.location.hash.slice(1));
        const t = h.get('token');
        if (t) {
          // Não remover o token
          return t;
        }
      }
    } catch(e) {
      // Em iframes com `src` sendo uma data URI ou similar, `new URL` pode falhar.
      // Podemos tentar uma regex como fallback.
      const match = window.location.search.match(/[?&]token=([^&]+)/);
      if (match) return match[1];
    }
    return null;
  }

  const WorkzSDK = {
    _version: '1.0.1-20250120', // Version with PUT support
    _cfg: { mode: 'standalone', baseUrl: DEFAULT_BASE },
    _token: null,
    _user: null,
    _context: null,
    _ready: false,
    _listeners: {},

    async init(cfg={}){
      this._cfg = Object.assign({ mode: 'standalone', baseUrl: DEFAULT_BASE }, cfg||{});
      this._token = parseQueryToken();

      if (this._cfg.mode === 'standalone') {
        // No modo standalone, o token DEVE vir da URL.
        // Se não vier, o app pode funcionar em modo público, mas não autenticado.
        if (this._token) localStorage.setItem('jwt_token', this._token);
        else this._token = localStorage.getItem('jwt_token'); // Fallback para token já salvo
        if (this._token && !this._user) {
          try { this._user = await this._fetchMe(); } catch(_) {}
        }
        this._ready = true;
        return true;
      }

      if (this._cfg.mode === 'embed') {
        // No modo embed, o token pode vir da URL (nosso caso) ou do postMessage.
        // Se já temos da URL, podemos prosseguir.
        if (this._token) localStorage.setItem('jwt_token', this._token);

        return new Promise(resolve => {
          const onMsg = (ev) => {
            const data = ev?.data || {};
            if (!data || typeof data !== 'object') return;
            if (data.type === 'workz-sdk:auth') {
              this._token = data.jwt || null;
              this._user = data.user || null;
              this._context = data.context || null;
              if (this._token) {
                localStorage.setItem('jwt_token', this._token);
              }

              const finish = async () => {
                if (!this._user && this._token) {
                  try { this._user = await this._fetchMe(); } catch(_) {}
                }
                this._ready = true;
                resolve(true);
              };
              finish();
            }
            if (data.type && this._listeners[data.type]) {
              try { this._listeners[data.type].forEach(fn => fn(data)); } catch(_) {}
            }
          };
          window.addEventListener('message', onMsg, false);

          // Se já temos o token da URL, não precisamos esperar pelo handshake.
          if (this._token) {
            const finish = async () => {
              if (!this._user) { try { this._user = await this._fetchMe(); } catch(_) {} }
              this._ready = true;
              resolve(true);
            };
            finish();
            return;
          }

          // Initiate handshake
          try { window.parent.postMessage({ type: 'workz-sdk:init' }, '*'); } catch(_) {}
        });
      }

      return false;
    },

    on(type, fn){
      if (!this._listeners[type]) this._listeners[type] = [];
      this._listeners[type].push(fn);
    },

    emit(type, payload){
      try { window.parent.postMessage(Object.assign({ type }, payload||{}), '*'); } catch(_) {}
    },

    getToken(){ return this._token; },
    getUser(){ return this._user; },
    getContext(){ return this._context; },

    async apiGet(path){
      const url = this._url(path);
      const resp = await fetch(url, { method: 'GET', headers: this._headers() });
      return await this._parseJsonSafe(resp);
    },
    async apiPost(path, body){
      const url = this._url(path);
      const resp = await fetch(url, { method: 'POST', headers: this._headers(true), body: JSON.stringify(body||{}) });
      return await this._parseJsonSafe(resp);
    },
    async apiPut(path, body){
      const url = this._url(path);
      const resp = await fetch(url, { method: 'PUT', headers: this._headers(true), body: JSON.stringify(body||{}) });
      return await this._parseJsonSafe(resp);
    },
    // Alias de compatibilidade com contrato sugerido (WorkzSDK.api.get/post/put)
    api: {
      get: async function(path){ return await WorkzSDK.apiGet(path); },
      post: async function(path, body){ return await WorkzSDK.apiPost(path, body); },
      put: async function(path, body){ return await WorkzSDK.apiPut(path, body); }
    },

    // Storage API
    storage: {
      kv: {
        async set(data) {
          // Usar a API existente /api/appdata/kv
          return await WorkzSDK.apiPost('/appdata/kv', {
            key: data.key,
            value: data.value,
            ttl: data.ttl,
            scopeType: 'user', // Por padrão usar escopo do usuário
            scopeId: WorkzSDK._user?.id || 0
          });
        },
        async get(key) {
          // GET /api/appdata/kv?key=...&scopeType=user&scopeId=...
          const params = new URLSearchParams({
            key: key,
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          return await WorkzSDK.apiGet(`/appdata/kv?${params}`);
        },
        async delete(key) {
          // DELETE /api/appdata/kv?key=...&scopeType=user&scopeId=...
          const params = new URLSearchParams({
            key: key,
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          const url = WorkzSDK._url(`/appdata/kv?${params}`);
          const resp = await fetch(url, {
            method: 'DELETE',
            headers: WorkzSDK._headers()
          });
          return resp.json();
        },
        async list() {
          // GET /api/appdata/kv?scopeType=user&scopeId=... (sem key para listar)
          const params = new URLSearchParams({
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          return await WorkzSDK.apiGet(`/appdata/kv?${params}`);
        }
      },
      docs: {
        async save(id, document) {
          // POST /api/appdata/docs/upsert
          return await WorkzSDK.apiPost('/appdata/docs/upsert', {
            docType: 'user_data',
            docId: id,
            document: document,
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
        },
        async get(id) {
          // Usar query para buscar documento específico
          return await WorkzSDK.apiPost('/appdata/docs/query', {
            docType: 'user_data',
            filters: { docId: id },
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
        },
        async delete(id) {
          // DELETE /api/appdata/docs/{docType}/{docId}?scopeType=user&scopeId=...
          const params = new URLSearchParams({
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          const url = WorkzSDK._url(`/appdata/docs/user_data/${encodeURIComponent(id)}?${params}`);
          const resp = await fetch(url, {
            method: 'DELETE',
            headers: WorkzSDK._headers()
          });
          return resp.json();
        },
        async list() {
          // Query todos os documentos do usuário
          return await WorkzSDK.apiPost('/appdata/docs/query', {
            docType: 'user_data',
            filters: {},
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
        },
        async query(query) {
          return await WorkzSDK.apiPost('/appdata/docs/query', {
            docType: 'user_data',
            filters: query,
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
        }
      },
      blobs: {
        async upload(name, file) {
          const formData = new FormData();
          formData.append('name', name);
          formData.append('file', file);
          formData.append('scopeType', 'user');
          formData.append('scopeId', WorkzSDK._user?.id || 0);
          
          const url = WorkzSDK._url('/appdata/blobs/upload');
          const resp = await fetch(url, {
            method: 'POST',
            headers: {
              'Authorization': WorkzSDK._token ? 'Bearer ' + WorkzSDK._token : undefined
            },
            body: formData
          });
          return resp.json();
        },
        async get(id) {
          const params = new URLSearchParams({
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          
          // Só adicionar token se ele existir
          if (WorkzSDK._token) {
            params.set('token', WorkzSDK._token);
          }
          const url = WorkzSDK._url(`/appdata/blobs/get/${encodeURIComponent(id)}?${params}`);
          
          // Debug: log da URL gerada
          console.log('Blob download URL:', url);
          console.log('Token disponível:', !!WorkzSDK._token);
          
          // Para download, abrir em nova janela
          window.open(url, '_blank');
          
          return {
            success: true,
            id: id,
            url: url,
            message: 'Download iniciado'
          };
        },
        async delete(id) {
          const params = new URLSearchParams({
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          const url = WorkzSDK._url(`/appdata/blobs/delete/${encodeURIComponent(id)}?${params}`);
          const resp = await fetch(url, {
            method: 'DELETE',
            headers: WorkzSDK._headers()
          });
          return resp.json();
        },
        async list() {
          const params = new URLSearchParams({
            scopeType: 'user',
            scopeId: WorkzSDK._user?.id || 0
          });
          return await WorkzSDK.apiGet(`/appdata/blobs/list?${params}`);
        }
      }
    },

    async _fetchMe(){
      const res = await fetch(this._url('/me'), { method: 'GET', headers: this._headers() });
      const data = await res.json();
      if (res.ok) {
        return data; // A rota /me retorna o objeto do usuário diretamente
      }
      // Se o token for inválido, o erro será capturado pela chamada original.
      return null;
    },

    _url(path){
      let p = String(path||'');
      if (!p.startsWith('/')) p = '/' + p;
      return (this._cfg.baseUrl || DEFAULT_BASE) + p;
    },
    _headers(withJson){
      const h = {};
      if (withJson) h['Content-Type'] = 'application/json';
      if (this._token) h['Authorization'] = 'Bearer ' + this._token;
      return h;
    },
    async _parseJsonSafe(resp){
      try {
        // Try JSON first
        return await resp.json();
      } catch (_) {
        try {
          const txt = await resp.text();
          // Normalize whitespace and trim to avoid overly long messages
          const preview = (txt || '').toString().slice(0, 1000);
          return {
            success: false,
            status: resp.status,
            message: preview || 'Non-JSON response from server',
            raw: preview
          };
        } catch (e2) {
          return { success: false, status: resp.status, message: 'Failed to parse response' };
        }
      }
    }
  };

  // UMD-ish: attach to window
  global.WorkzSDK = WorkzSDK;
})(typeof window !== 'undefined' ? window : globalThis);
