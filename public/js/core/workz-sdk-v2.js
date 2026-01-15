// public/js/core/workz-sdk-v2.js
// Unified WorkzSDK Core - Version 2.0.0
// Supports both JavaScript and Flutter applications with consistent API

(function(global) {
  'use strict';

  // Platform Detection
  const PlatformDetector = {
    detect() {
      const userAgent = navigator.userAgent || '';
      const isFlutterWeb = window.flutterCanvasKit !== undefined || 
                          window.flutter !== undefined ||
                          document.querySelector('flutter-view') !== null;
      
      return {
        type: isFlutterWeb ? 'flutter-web' : 'javascript',
        isWeb: true,
        isMobile: /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent),
        isIframe: window !== window.top,
        userAgent
      };
    }
  };

  // Authentication Module
  class AuthenticationModule {
    constructor(options = {}) {
      this.token = null;
      this.user = null;
      this.context = null;
      this.isAppToken = false;
      this.ready = false;
      this.onAuthUpdate = typeof options.onAuthUpdate === 'function' ? options.onAuthUpdate : null;
    }

    decodeJwtPayload(token) {
      try {
        const parts = String(token || '').split('.');
        if (parts.length < 2) return null;
        let b64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
        const pad = b64.length % 4;
        if (pad) {
          b64 += '='.repeat(4 - pad);
        }
        const json = decodeURIComponent(atob(b64).split('').map((c) => {
          return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
        }).join(''));
        return JSON.parse(json);
      } catch (_) {
        return null;
      }
    }

    isAppScopedToken(token) {
      const payload = this.decodeJwtPayload(token);
      const aud = payload?.aud || '';
      return (typeof aud === 'string') && aud.startsWith('app:');
    }

    getContextFromToken(token) {
      const payload = this.decodeJwtPayload(token);
      const rawCtx = payload?.ctx || payload?.context;
      if (!rawCtx || typeof rawCtx !== 'object') return null;
      const type = String(rawCtx.type || '').toLowerCase();
      const id = parseInt(rawCtx.id, 10);
      if (!type || !Number.isFinite(id) || id <= 0) return null;
      return Object.assign({}, rawCtx, { type, id });
    }

    notifyAuthUpdate() {
      if (this.onAuthUpdate) {
        try {
          this.onAuthUpdate({ token: this.token, user: this.user, context: this.context });
        } catch (_) {}
      }
    }

    parseTokenFromUrl() {
      try {
        const url = new URL(window.location.href);
        const fromQuery = url.searchParams.get('token');
        if (fromQuery) return fromQuery;
        
        if (window.location.hash) {
          const h = new URLSearchParams(window.location.hash.slice(1));
          const t = h.get('token');
          if (t) return t;
        }
      } catch(e) {
        // Fallback for data URIs or restricted contexts
        const match = window.location.search.match(/[?&]token=([^&]+)/);
        if (match) return match[1];
      }
      return null;
    }

    async initStandalone(apiClient) {
      this.token = this.parseTokenFromUrl();
      this.isAppToken = this.isAppScopedToken(this.token);
      
      if (this.token) {
        if (!this.isAppToken) {
          localStorage.setItem('jwt_token', this.token);
        }
      } else {
        this.token = localStorage.getItem('jwt_token');
        this.isAppToken = this.isAppScopedToken(this.token);
      }

      if (!this.context && this.token) {
        this.context = this.getContextFromToken(this.token);
      }
      
      if (this.token && !this.user) {
        try {
          this.user = await this.fetchUserProfile(apiClient);
        } catch(e) {
          console.warn('Failed to fetch user profile:', e);
        }
      }
      
      this.ready = true;
      this.notifyAuthUpdate();
      return true;
    }

    async initEmbed(apiClient) {
      this.token = this.parseTokenFromUrl();
      this.isAppToken = this.isAppScopedToken(this.token);
      if (this.token) {
        if (!this.isAppToken) {
          localStorage.setItem('jwt_token', this.token);
        }
      }
      if (!this.context && this.token) {
        this.context = this.getContextFromToken(this.token);
      }

      return new Promise((resolve) => {
        const onMessage = (ev) => {
          const data = ev?.data || {};
          if (!data || typeof data !== 'object') return;
          
          if (data.type === 'workz-sdk:auth') {
            if (!this.isAppToken) {
              this.token = data.jwt || null;
            }
            this.user = data.user || null;
            this.context = data.context || null;
            
            if (this.token && !this.isAppToken) {
              localStorage.setItem('jwt_token', this.token);
            }

            this.notifyAuthUpdate();
            this.finishInit(apiClient, resolve);
          }
        };

        window.addEventListener('message', onMessage, false);

        // If we already have token from URL, proceed without handshake
        if (this.token) {
          this.finishInit(apiClient, resolve);
          // Continua o handshake para obter user/context quando possível.
        }

        // Initiate handshake with parent
        try {
          window.parent.postMessage({ type: 'workz-sdk:init' }, '*');
        } catch(e) {
          console.warn('Failed to initiate handshake:', e);
        }
      });
    }

    async finishInit(apiClient, resolve) {
      if (!this.user && this.token) {
        try {
          this.user = await this.fetchUserProfile(apiClient);
        } catch(e) {
          console.warn('Failed to fetch user profile:', e);
        }
      }
      this.ready = true;
      this.notifyAuthUpdate();
      resolve(true);
    }

    async fetchUserProfile(apiClient) {
      const response = await apiClient.get('/me');
      return response;
    }

    getToken() {
      return this.token;
    }

    getUser() {
      return this.user;
    }

    getContext() {
      return this.context;
    }

    isReady() {
      return this.ready;
    }
  }

  // API Client Module
  class ApiClient {
    constructor(config = {}) {
      this.baseUrl = config.baseUrl || '/api';
      this.authModule = config.authModule;
      this.guard = config.guard || null;
    }

    buildUrl(path) {
      let p = String(path || '');
      if (!p.startsWith('/')) p = '/' + p;
      return this.baseUrl + p;
    }

    getHeaders(includeContentType = false) {
      const headers = {};
      if (includeContentType) {
        headers['Content-Type'] = 'application/json';
      }
      if (this.authModule && this.authModule.getToken()) {
        headers['Authorization'] = 'Bearer ' + this.authModule.getToken();
      }
      return headers;
    }

    async request(method, path, body = null) {
      const url = this.buildUrl(path);
      if (typeof this.guard === 'function') {
        try {
          const guardResult = this.guard({ method, path, body });
          if (guardResult && guardResult.blocked) {
            return {
              success: false,
              status: guardResult.status || 403,
              code: guardResult.code || 'blocked',
              message: guardResult.message || 'Request blocked by SDK guard'
            };
          }
        } catch (e) {
          return { success: false, status: 500, code: 'guard_error', message: e?.message || 'Guard error' };
        }
      }
      const options = {
        method: method.toUpperCase(),
        headers: this.getHeaders(body !== null)
      };

      if (body !== null) {
        options.body = JSON.stringify(body);
      }

      const response = await fetch(url, options);

      // Parse JSON safely to avoid SyntaxError on HTML error pages
      let data;
      try {
        data = await response.json();
      } catch (_) {
        try {
          const txt = await response.text();
          const preview = (txt || '').toString().slice(0, 1000);
          data = { success: false, status: response.status, message: preview, raw: preview };
        } catch (e2) {
          data = { success: false, status: response.status, message: 'Failed to parse response' };
        }
      }

      // If HTTP is not ok, return structured error instead of throwing
      if (!response.ok) {
        if (typeof data !== 'object' || data === null) {
          data = { success: false, status: response.status, message: response.statusText };
        } else if (data.success === undefined) {
          data.success = false;
          data.status = response.status;
        }

        // Redirect to app login when token is missing (external access)
        try {
          const errMsg = String(data.error || data.message || '');
          const missingToken = response.status === 401 && /token/i.test(errMsg) && /fornecido|missing|not provided/i.test(errMsg);
          if (missingToken && typeof window !== 'undefined') {
            const slug = window.WorkzAppConfig?.slug;
            if (slug) {
              const target = `/app/public/${encodeURIComponent(slug)}`;
              if (!window.location.pathname.startsWith('/app/public/')) {
                window.location.href = target;
              }
            }
          }
        } catch (_) {}
      }

      return data;
    }

    async get(path) {
      return this.request('GET', path);
    }

    async post(path, body) {
      return this.request('POST', path, body);
    }

    async put(path, body) {
      return this.request('PUT', path, body);
    }

    async delete(path) {
      return this.request('DELETE', path);
    }
  }

  // Storage Module
  class StorageModule {
    constructor(apiClient, authModule) {
      this.apiClient = apiClient;
      this.authModule = authModule;
    }

    getContextScope() {
      const ctx = this.authModule.getContext ? this.authModule.getContext() : null;
      if (ctx && typeof ctx === 'object') {
        const rawType = String(ctx.type || '').toLowerCase();
        const id = parseInt(ctx.id, 10);
        const scopeType = rawType === 'business' || rawType === 'team' || rawType === 'user'
          ? rawType
          : null;
        if (scopeType && Number.isFinite(id) && id > 0) {
          return { scopeType, scopeId: id };
        }
      }
      return this.getUserScope();
    }

    getUserScope() {
      const user = this.authModule.getUser();
      return {
        scopeType: 'user',
        scopeId: user?.id || 0
      };
    }

    // Key-Value Storage
    get kv() {
      return {
        set: async (data) => {
          return this.apiClient.post('/appdata/kv', {
            key: data.key,
            value: data.value,
            ttl: data.ttl,
            ...this.getContextScope()
          });
        },

        get: async (key) => {
          const params = new URLSearchParams({
            key: key,
            ...this.getContextScope()
          });
          return this.apiClient.get(`/appdata/kv?${params}`);
        },

        delete: async (key) => {
          const params = new URLSearchParams({
            key: key,
            ...this.getContextScope()
          });
          const url = this.apiClient.buildUrl(`/appdata/kv?${params}`);
          const response = await fetch(url, {
            method: 'DELETE',
            headers: this.apiClient.getHeaders()
          });
          return response.json();
        },

        list: async () => {
          const params = new URLSearchParams(this.getContextScope());
          return this.apiClient.get(`/appdata/kv?${params}`);
        }
      };
    }

    // Document Storage
    get docs() {
      return {
        save: async (id, document) => {
          return this.apiClient.post('/appdata/docs/upsert', {
            docType: 'user_data',
            docId: id,
            document: document,
            ...this.getContextScope()
          });
        },

        get: async (id) => {
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: { docId: id },
            ...this.getContextScope()
          });
        },

        delete: async (id) => {
          const params = new URLSearchParams(this.getContextScope());
          const url = this.apiClient.buildUrl(`/appdata/docs/user_data/${encodeURIComponent(id)}?${params}`);
          const response = await fetch(url, {
            method: 'DELETE',
            headers: this.apiClient.getHeaders()
          });
          return response.json();
        },

        list: async () => {
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: {},
            ...this.getContextScope()
          });
        },

        query: async (query) => {
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: query,
            ...this.getContextScope()
          });
        }
      };
    }

    // Blob Storage
    get blobs() {
      return {
        upload: async (name, file) => {
          const formData = new FormData();
          formData.append('name', name);
          formData.append('file', file);
          const scope = this.getContextScope();
          formData.append('scopeType', scope.scopeType);
          formData.append('scopeId', scope.scopeId);

          const url = this.apiClient.buildUrl('/appdata/blobs/upload');
          const response = await fetch(url, {
            method: 'POST',
            headers: {
              'Authorization': this.authModule.getToken() ? 'Bearer ' + this.authModule.getToken() : undefined
            },
            body: formData
          });
          return response.json();
        },

        get: async (id) => {
          const params = new URLSearchParams(this.getContextScope());
          if (this.authModule.getToken()) {
            params.set('token', this.authModule.getToken());
          }
          const url = this.apiClient.buildUrl(`/appdata/blobs/get/${encodeURIComponent(id)}?${params}`);
          
          window.open(url, '_blank');
          return {
            success: true,
            id: id,
            url: url,
            message: 'Download initiated'
          };
        },

        delete: async (id) => {
          const params = new URLSearchParams(this.getContextScope());
          const url = this.apiClient.buildUrl(`/appdata/blobs/delete/${encodeURIComponent(id)}?${params}`);
          const response = await fetch(url, {
            method: 'DELETE',
            headers: this.apiClient.getHeaders()
          });
          return response.json();
        },

        list: async () => {
          const params = new URLSearchParams(this.getContextScope());
          return this.apiClient.get(`/appdata/blobs/list?${params}`);
        }
      };
    }
  }

  // Platform Adapter Interface
  class PlatformAdapter {
    constructor(platform, sdk) {
      this.platform = platform;
      this.sdk = sdk;
    }

    async initialize() {
      // Override in platform-specific adapters
      return true;
    }

    async postMessage(type, payload) {
      // Override in platform-specific adapters
      try {
        window.parent.postMessage(Object.assign({ type }, payload || {}), '*');
      } catch(e) {
        console.warn('Failed to post message:', e);
      }
    }

    addEventListener(type, callback) {
      // Override in platform-specific adapters
      window.addEventListener('message', (ev) => {
        const data = ev?.data || {};
        if (data.type === type) {
          callback(data);
        }
      });
    }
  }

  // JavaScript Platform Adapter
  class JavaScriptAdapter extends PlatformAdapter {
    async initialize() {
      // JavaScript-specific initialization
      return true;
    }
  }

  // Flutter Web Platform Adapter
  class FlutterWebAdapter extends PlatformAdapter {
    async initialize() {
      // Flutter Web-specific initialization
      // Wait for Flutter to be ready
      if (window.flutter) {
        await new Promise(resolve => {
          if (window.flutter.loader) {
            window.flutter.loader.loadEntrypoint().then(resolve);
          } else {
            resolve();
          }
        });
      }
      return true;
    }

    async postMessage(type, payload) {
      // Enhanced message posting for Flutter Web
      const message = Object.assign({ type, platform: 'flutter-web' }, payload || {});
      try {
        window.parent.postMessage(message, '*');
        // Also try to send to Flutter if available
        if (window.flutter && window.flutter.postMessage) {
          window.flutter.postMessage(message);
        }
      } catch(e) {
        console.warn('Failed to post message to Flutter:', e);
      }
    }
  }

  // Main WorkzSDK Class
  class WorkzSDK {
    constructor() {
      this.version = '2.0.0';
      this.platform = null;
      this.adapter = null;
      this.auth = null;
      this.apiClient = null;
      this.storage = null;
      this.payments = null;
      this.listeners = {};
      this.initialized = false;
      this.appConfig = null;
      this.manifest = null;
      this.contextAllowed = true;
      this.contextDenyReason = null;
      this._postMessageHandler = null;
    }

    async init(config = {}) {
      if (this.initialized) {
        console.warn('WorkzSDK already initialized');
        return true;
      }

      // Detect platform
      this.platform = PlatformDetector.detect();
      
      // Initialize authentication module
      this.auth = new AuthenticationModule({
        onAuthUpdate: () => {
          if (this.manifest) {
            this.refreshContextAccess();
          }
        }
      });
      
      // Initialize API client
      this.apiClient = new ApiClient({
        baseUrl: config.baseUrl || '/api',
        authModule: this.auth
      });
      
      // Initialize storage module
      this.storage = new StorageModule(this.apiClient, this.auth);
      
      // Initialize payments module (Phase 1: one-time purchases)
      this.payments = {
        /**
         * Creates a Mercado Pago preference for one-time purchases.
         * params: { appId, title?, quantity?, unitPrice?, currency?, companyId?, backUrls? }
         * returns: { success, transaction_id, preference_id, init_point }
         */
        createPurchase: async (params) => {
          const body = {
            app_id: params.appId,
            title: params.title,
            quantity: params.quantity,
            unit_price: params.unitPrice,
            currency: params.currency,
            company_id: params.companyId,
            back_urls: params.backUrls,
          };
          return await this.apiClient.post('/payments/preference', body);
        },
        /**
         * Fetches a transaction by ID
         */
        getTransaction: async (id) => {
          return await this.apiClient.get(`/payments/transactions/${encodeURIComponent(id)}`);
        },
        /**
         * Lists current user's transactions with optional filters
         */
        listMyTransactions: async (filters = {}) => {
          const params = new URLSearchParams();
          if (filters.appId) params.set('app_id', String(filters.appId));
          if (filters.status) params.set('status', String(filters.status));
          if (filters.limit) params.set('limit', String(filters.limit));
          const qs = params.toString();
          const path = qs ? `/payments/transactions?${qs}` : '/payments/transactions';
          return await this.apiClient.get(path);
        },
        /**
         * Gets only the status of a transaction
         */
        getStatus: async (id) => {
          return await this.apiClient.get(`/payments/status/${encodeURIComponent(id)}`);
        },
        /**
         * Charges using Stripe PaymentIntent.
         * params: { appId, amount, currency?, paymentMethodId?, pmId?, companyId?, description?, metadata?, usePix? }
         * If paymentMethodId/pmId is omitted, backend returns client_secret for client-side confirmation.
         */
        chargeToken: async (params) => {
          const body = {
            app_id: params.appId,
            amount: params.amount,
            currency: params.currency,
            payment_method_id: params.paymentMethodId,
            pm_id: params.pmId,
            company_id: params.companyId,
            description: params.description,
            metadata: params.metadata,
            use_pix: params.usePix,
          };
          return await this.apiClient.post('/payments/charge', body);
        },
      };
      
      // Load platform adapter
      this.adapter = this.loadAdapter();
      await this.adapter.initialize();
      
      // Initialize authentication based on mode
      const mode = config.mode || (this.platform.isIframe ? 'embed' : 'standalone');
      
      let authResult;
      if (mode === 'embed') {
        authResult = await this.auth.initEmbed(this.apiClient);
      } else {
        authResult = await this.auth.initStandalone(this.apiClient);
      }

      this.appConfig = config.appConfig || global.WorkzAppConfig || {};
      this.manifest = this.resolveManifest(this.appConfig.manifest || this.appConfig.workzManifest || null);
      this.refreshContextAccess();
      this.apiClient.guard = ({ method, path }) => {
        if (this.contextAllowed) return null;
        const p = String(path || '');
        const m = String(method || '').toUpperCase();
        // Allow bootstrapping endpoints before context is chosen.
        const allowlist = [
          'GET /me',
          'GET /apps/my-apps',
          'GET /apps/storage/stats'
        ];
        if (allowlist.includes(`${m} ${p}`)) return null;
        const required = this.contextDenyReason?.required || 'unknown';
        const received = this.contextDenyReason?.received || 'unknown';
        return {
          blocked: true,
          status: 403,
          code: 'context_not_allowed',
          message: `Contexto inválido para o app (necessário: ${required}, recebido: ${received}).`
        };
      };

      this.initialized = true;

      if (!this._postMessageHandler && typeof window !== 'undefined') {
        this._postMessageHandler = (ev) => {
          const data = ev?.data || {};
          if (!data || typeof data !== 'object') return;
          const type = data.type;
          if (!type || typeof type !== 'string') return;
          if (type === 'workz-sdk:init' || type === 'workz-sdk:auth') return;
          const payload = (data.payload !== undefined) ? data.payload : data;
          this.dispatchLocal(type, payload);
        };
        window.addEventListener('message', this._postMessageHandler, false);
      }
      
      // Emit ready event
      this.emit('sdk:ready', {
        platform: this.platform,
        user: this.auth.getUser(),
        version: this.version
      });
      
      return authResult;
    }

    resolveManifest(raw) {
      if (raw && typeof raw === 'object') return raw;
      if (typeof raw === 'string' && raw.trim() !== '') {
        try {
          const parsed = JSON.parse(raw);
          return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (_) {
          return null;
        }
      }
      return null;
    }

    enforceContextRequirements() {
      if (!this.manifest || !this.manifest.contextRequirements) {
        this.contextDenyReason = null;
        return true;
      }
      const mode = String(this.manifest.contextRequirements.mode || '').toLowerCase();
      if (!mode || mode === 'hybrid') {
        this.contextDenyReason = null;
        return true;
      }
      const ctx = this.getContext() || {};
      const ctxType = String(ctx.type || '').toLowerCase();
      const allowed = (ctxType === mode);
      if (!allowed) {
        this.contextDenyReason = { required: mode, received: ctxType || 'none' };
        this.emit('app:context_denied', {
          required: mode,
          received: ctxType || 'none',
          context: ctx
        });
      } else {
        this.contextDenyReason = null;
      }
      return allowed;
    }

    refreshContextAccess() {
      this.contextAllowed = this.enforceContextRequirements();
      return this.contextAllowed;
    }

    loadAdapter() {
      switch (this.platform.type) {
        case 'flutter-web':
          return new FlutterWebAdapter(this.platform, this);
        case 'javascript':
        default:
          return new JavaScriptAdapter(this.platform, this);
      }
    }

    // Event system
    on(type, callback) {
      if (!this.listeners[type]) {
        this.listeners[type] = [];
      }
      this.listeners[type].push(callback);
    }

    off(type, callback) {
      if (this.listeners[type]) {
        const index = this.listeners[type].indexOf(callback);
        if (index > -1) {
          this.listeners[type].splice(index, 1);
        }
      }
    }

    dispatchLocal(type, payload) {
      if (this.listeners[type]) {
        this.listeners[type].forEach(callback => {
          try {
            callback(payload);
          } catch(e) {
            console.error('Error in event listener:', e);
          }
        });
      }
    }

    emit(type, payload) {
      this.dispatchLocal(type, payload);
      // Also emit to parent if in iframe
      if (this.adapter) {
        this.adapter.postMessage(type, payload);
      }
    }

    // Convenience getters
    getToken() {
      return this.auth ? this.auth.getToken() : null;
    }

    getUser() {
      return this.auth ? this.auth.getUser() : null;
    }

    getContext() {
      return this.auth ? this.auth.getContext() : null;
    }

    getPlatform() {
      return this.platform;
    }

    getManifest() {
      return this.manifest;
    }

    isReady() {
      return this.initialized && this.auth && this.auth.isReady();
    }

    // API access (backward compatibility)
    get api() {
      return {
        get: (path) => this.apiClient.get(path),
        post: (path, body) => this.apiClient.post(path, body),
        put: (path, body) => this.apiClient.put(path, body),
        delete: (path) => this.apiClient.delete(path)
      };
    }

    // Legacy method aliases for backward compatibility
    async apiGet(path) {
      return this.apiClient.get(path);
    }

    async apiPost(path, body) {
      return this.apiClient.post(path, body);
    }

    async apiPut(path, body) {
      return this.apiClient.put(path, body);
    }
  }

  // Create singleton instance
  const sdkInstance = new WorkzSDK();

  // Export both the class and instance for flexibility
  global.WorkzSDK = sdkInstance;
  global.WorkzSDKClass = WorkzSDK;

  // For module systems
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { WorkzSDK: sdkInstance, WorkzSDKClass };
  }

})(typeof window !== 'undefined' ? window : globalThis);
