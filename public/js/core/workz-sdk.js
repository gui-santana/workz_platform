// public/js/core/workz-sdk.js

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
      return resp.json();
    },
    async apiPost(path, body){
      const url = this._url(path);
      const resp = await fetch(url, { method: 'POST', headers: this._headers(true), body: JSON.stringify(body||{}) });
      return resp.json();
    },
    // Alias de compatibilidade com contrato sugerido (WorkzSDK.api.get/post)
    api: {
      get: async function(path){ return await WorkzSDK.apiGet(path); },
      post: async function(path, body){ return await WorkzSDK.apiPost(path, body); }
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
    }
  };

  // UMD-ish: attach to window
  global.WorkzSDK = WorkzSDK;
})(typeof window !== 'undefined' ? window : globalThis);
