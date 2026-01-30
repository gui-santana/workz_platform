// public/js/core/workz-sdk-v2.js
// Unified WorkzSDK Core - Version 2.0.0
// Supports both JavaScript and Flutter applications with consistent API
// PIPE compliance rules:
// - docs.types defaults to ["user_data"] when storage.docs enabled and types not provided; empty types is invalid.
// - Embed requires a manifest when running in iframe or when appConfig.slug is set.
// - Embed disallows "*" in sandbox.postMessage.allowedOrigins; normalize to referrer origin when present, else invalidate.
// - sdk:ready/sdk:telemetry/sdk:security/app:context_denied always emit locally; parent posting still gated (sdk:security allowed).
// - Embed handshake validates origin/source and uses a concrete targetOrigin (referrer origin or window.location.origin).

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
      this.allowedOrigins = Array.isArray(options.allowedOrigins) ? options.allowedOrigins.slice() : null;
      this.enforceEmbed = !!options.enforceEmbed;
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
      this.token = null;
      this.isAppToken = false;

      return new Promise((resolve) => {
        let gotAuth = false;
        const targetOrigin = this.getHandshakeTargetOrigin() || '*';
        const onMessage = (ev) => {
          const data = ev?.data || {};
          if (!data || typeof data !== 'object') return;
          
          if (data.type === 'workz-sdk:auth') {
            if (ev?.source !== window.parent) return;
            if (!this.isOriginAllowed(ev?.origin)) return;
            gotAuth = true;
            this.token = data.jwt || null;
            this.isAppToken = this.isAppScopedToken(this.token);
            if (this.token && !this.isAppToken) {
              console.warn('WorkzSDK embed: token sem aud app:*, ignorando.');
              this.token = null;
            }
            this.user = data.user || null;
            this.context = data.context || (this.token ? this.getContextFromToken(this.token) : null);

            this.notifyAuthUpdate();
            this.finishInit(apiClient, resolve);
            window.removeEventListener('message', onMessage, false);
          }
        };

        window.addEventListener('message', onMessage, false);

        // Initiate handshake with parent
        const sendInit = () => {
          try {
            window.parent.postMessage({ type: 'workz-sdk:init' }, targetOrigin);
          } catch (e) {
            console.warn('Failed to initiate handshake:', e);
          }
        };

        sendInit();

        const startedAt = Date.now();
        const timer = setInterval(() => {
          if (gotAuth) {
            clearInterval(timer);
            return;
          }
          if (Date.now() - startedAt > 6000) {
            clearInterval(timer);
            return;
          }
          sendInit();
        }, 250);
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

    isOriginAllowed(origin) {
      const origins = this.allowedOrigins;
      if (!Array.isArray(origins) || origins.length === 0) {
        return this.enforceEmbed ? false : true;
      }
      if (origins.includes('*')) return true;
      if (!origin) return false;
      return origins.includes(origin);
    }

    getHandshakeTargetOrigin() {
      try {
        const ref = document.referrer ? new URL(document.referrer).origin : '';
        if (ref) return ref;
      } catch (_) {}
      if (typeof window !== 'undefined' && window.location && window.location.origin) {
        return window.location.origin;
      }
      return null;
    }
  }

  // API Client Module
  class ApiClient {
    constructor(config = {}) {
      this.baseUrl = config.baseUrl || '/api';
      this.authModule = config.authModule;
      this.guard = config.guard || null;
      this.telemetryHook = typeof config.telemetryHook === 'function' ? config.telemetryHook : null;
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
      const startTs = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
      const url = this.buildUrl(path);
      if (typeof this.guard === 'function') {
        try {
          const guardResult = this.guard({ method, path, body });
          if (guardResult && guardResult.blocked) {
            if (this.telemetryHook) {
              const endTs = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
              this.telemetryHook({
                type: 'api',
                method: String(method || '').toUpperCase(),
                path: String(path || ''),
                durationMs: Math.max(0, Math.round(endTs - startTs)),
                ok: false
              });
            }
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

      if (this.telemetryHook) {
        const endTs = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        const ok = response.ok && !(data && data.success === false);
        this.telemetryHook({
          type: 'api',
          method: String(method || '').toUpperCase(),
          path: String(path || ''),
          durationMs: Math.max(0, Math.round(endTs - startTs)),
          ok: ok
        });
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
    constructor(apiClient, authModule, guard, telemetryHook, scopeResolver) {
      this.apiClient = apiClient;
      this.authModule = authModule;
      this.guard = typeof guard === 'function' ? guard : null;
      this.telemetryHook = typeof telemetryHook === 'function' ? telemetryHook : null;
      this.scopeResolver = typeof scopeResolver === 'function' ? scopeResolver : null;
    }

    checkGuard(action) {
      if (this.guard) {
        try {
          const res = this.guard(action);
          if (res && res.blocked) {
            if (this.telemetryHook && action && action.action) {
              this.telemetryHook({
                type: 'storage',
                op: action.action,
                ok: false,
                reason: res.reason || res.code || res.message || 'blocked'
              });
            }
            return {
              success: false,
              status: res.status || 403,
              code: res.code || 'blocked',
              message: res.message || 'Ação bloqueada pelo SDK'
            };
          }
        } catch (e) {
          return { success: false, status: 500, code: 'guard_error', message: e?.message || 'Erro no guard' };
        }
      }
      return null;
    }

    getContextScope() {
      if (this.scopeResolver && this.scopeResolver() === 'user') {
        return this.getUserScope();
      }
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
        set: async (data, value, ttl) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'kv.set' });
          if (blocked) return blocked;
          let payload = data;
          if (typeof data === 'string') {
            payload = { key: data, value: value, ttl: ttl };
          }
          payload = payload && typeof payload === 'object' ? payload : {};
          return this.apiClient.post('/appdata/kv', {
            key: payload.key,
            value: payload.value,
            ttl: payload.ttl,
            ...this.getContextScope()
          });
        },

        get: async (key) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'kv.get' });
          if (blocked) return blocked;
          const params = new URLSearchParams({
            key: key,
            ...this.getContextScope()
          });
          return this.apiClient.get(`/appdata/kv?${params}`);
        },

        delete: async (key) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'kv.delete' });
          if (blocked) return blocked;
          const params = new URLSearchParams({
            key: key,
            ...this.getContextScope()
          });
          return this.apiClient.delete(`/appdata/kv?${params}`);
        },

        list: async () => {
          const blocked = this.checkGuard({ type: 'storage', action: 'kv.list' });
          if (blocked) return blocked;
          const params = new URLSearchParams(this.getContextScope());
          return this.apiClient.get(`/appdata/kv?${params}`);
        }
      };
    }

    // Document Storage
    get docs() {
      return {
        save: async (id, document) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'docs.save', docType: 'user_data' });
          if (blocked) return blocked;
          return this.apiClient.post('/appdata/docs/upsert', {
            docType: 'user_data',
            docId: id,
            document: document,
            ...this.getContextScope()
          });
        },

        get: async (id) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'docs.get', docType: 'user_data' });
          if (blocked) return blocked;
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: { docId: id },
            ...this.getContextScope()
          });
        },

        delete: async (id) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'docs.delete', docType: 'user_data' });
          if (blocked) return blocked;
          const params = new URLSearchParams(this.getContextScope());
          return this.apiClient.delete(`/appdata/docs/user_data/${encodeURIComponent(id)}?${params}`);
        },

        list: async () => {
          const blocked = this.checkGuard({ type: 'storage', action: 'docs.list', docType: 'user_data' });
          if (blocked) return blocked;
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: {},
            ...this.getContextScope()
          });
        },

        query: async (query) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'docs.query', docType: 'user_data' });
          if (blocked) return blocked;
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
          const blocked = this.checkGuard({ type: 'storage', action: 'blobs.upload' });
          if (blocked) return blocked;
          const formData = new FormData();
          formData.append('name', name);
          formData.append('file', file);
          const scope = this.getContextScope();
          formData.append('scopeType', scope.scopeType);
          formData.append('scopeId', scope.scopeId);

          const url = this.apiClient.buildUrl('/appdata/blobs/upload');
          const headers = {};
          if (this.authModule.getToken()) {
            headers['Authorization'] = 'Bearer ' + this.authModule.getToken();
          }
          const response = await fetch(url, {
            method: 'POST',
            headers,
            body: formData
          });
          return response.json();
        },

        get: async (id) => {
          const blocked = this.checkGuard({ type: 'storage', action: 'blobs.get' });
          if (blocked) return blocked;
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
          const blocked = this.checkGuard({ type: 'storage', action: 'blobs.delete' });
          if (blocked) return blocked;
          const params = new URLSearchParams(this.getContextScope());
          return this.apiClient.delete(`/appdata/blobs/delete/${encodeURIComponent(id)}?${params}`);
        },

        list: async () => {
          const blocked = this.checkGuard({ type: 'storage', action: 'blobs.list' });
          if (blocked) return blocked;
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
        const targetOrigin = this.sdk ? this.sdk.getPostMessageTargetOrigin() : undefined;
        if (!targetOrigin) return;
        window.parent.postMessage(Object.assign({ type }, payload || {}), targetOrigin);
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
        const targetOrigin = this.sdk ? this.sdk.getPostMessageTargetOrigin() : undefined;
        if (targetOrigin) {
          window.parent.postMessage(message, targetOrigin);
        }
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
      this.manifestValidation = null;
      this.contextAllowed = true;
      this.contextDenyReason = null;
      this._postMessageHandler = null;
      this._telemetryBuffer = [];
      this._telemetryBufferSize = 200;
    }

    async init(config = {}) {
      if (this.initialized) {
        console.warn('WorkzSDK already initialized');
        return true;
      }

      // Detect platform
      this.platform = PlatformDetector.detect();

      this.appConfig = config.appConfig || global.WorkzAppConfig || {};
      const mode = config.mode || (this.platform.isIframe ? 'embed' : 'standalone');
      const requireManifest = mode === 'embed' || !!this.appConfig.slug;
      const manifestRaw = this.appConfig.manifest || this.appConfig.workzManifest || null;
      const manifestResult = this.validateManifest(manifestRaw, {
        requireManifest: requireManifest,
        embed: mode === 'embed',
        referrerOrigin: this.getReferrerOrigin()
      });
      this.manifestValidation = manifestResult;
      if (manifestResult.ok) {
        this.manifest = manifestResult.normalizedManifest;
        this.refreshContextAccess();
      } else {
        this.manifest = manifestResult.normalizedManifest || null;
        this.contextAllowed = false;
        this.contextDenyReason = { code: 'manifest_invalid', errors: manifestResult.errors.slice() };
      }
      
      // Initialize authentication module
      this.auth = new AuthenticationModule({
        onAuthUpdate: () => {
          if (this.manifest && this.manifestValidation?.ok !== false) {
            this.refreshContextAccess();
          }
        },
        allowedOrigins: this.manifest?.sandbox?.postMessage?.allowedOrigins || null,
        enforceEmbed: mode === 'embed'
      });
      
      // Initialize API client
      this.apiClient = new ApiClient({
        baseUrl: config.baseUrl || '/api',
        authModule: this.auth,
        telemetryHook: (payload) => this.handleTelemetry(payload)
      });
      
      // Initialize storage module
      this.storage = new StorageModule(
        this.apiClient,
        this.auth,
        (action) => this.storageGuard(action),
        (payload) => this.handleTelemetry(payload),
        () => this.getStorageScopeMode()
      );
      
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
      let authResult;
      if (mode === 'embed') {
        authResult = await this.auth.initEmbed(this.apiClient);
      } else {
        authResult = await this.auth.initStandalone(this.apiClient);
      }
      this.apiClient.guard = ({ method, path }) => {
        const p = this.normalizeApiPath(path);
        const m = this.normalizeApiMethod(method);
        // Allow bootstrapping endpoints before context is chosen.
        const allowlist = [
          'GET /me',
          'GET /apps/my-apps',
          'GET /apps/storage/stats'
        ];
        if (allowlist.includes(`${m} ${p}`)) return null;
        if (!this.contextAllowed) {
          const reason = this.contextDenyReason || {};
          if (reason.code === 'manifest_invalid') {
            const details = Array.isArray(reason.errors) ? reason.errors.join('; ') : 'Manifesto inválido';
            return {
              blocked: true,
              status: 403,
              code: 'manifest_invalid',
              message: `Manifesto inválido: ${details}`
            };
          }
          const required = reason.required || 'unknown';
          const received = reason.received || 'unknown';
          return {
            blocked: true,
            status: 403,
            code: 'context_not_allowed',
            message: `Contexto inválido para o app (necessário: ${required}, recebido: ${received}).`
          };
        }
        if (!this.isApiAllowed(m, p)) {
          return {
            blocked: true,
            status: 403,
            code: 'capability_denied',
            message: 'Rota de API não permitida pelo manifesto.'
          };
        }
        return null;
      };

      this.initialized = true;

      if (!this._postMessageHandler && typeof window !== 'undefined') {
        this._postMessageHandler = (ev) => {
          const data = ev?.data || {};
          if (!data || typeof data !== 'object') return;
          const type = data.type;
          if (!type || typeof type !== 'string') return;
          if (type === 'workz-sdk:init' || type === 'workz-sdk:auth') return;
          if (mode === 'embed' && this.platform && this.platform.isIframe) {
            if (ev?.source !== window.parent) {
              this.emitSecurity('postMessage', 'source_mismatch', { type, origin: ev?.origin || '' });
              return;
            }
            if (!this.isOriginAllowed(ev?.origin)) {
              this.emitSecurity('postMessage', 'origin_not_allowed', { type, origin: ev?.origin || '' });
              return;
            }
          }
          if (!this.contextAllowed) {
            this.emitSecurity('event_subscribe', 'context_not_allowed', { type });
            return;
          }
          if (!this.isEventSubscribeAllowed(type)) {
            this.emitSecurity('event_subscribe', 'event_not_allowed', { type });
            return;
          }
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

    normalizeApiPath(path) {
      let p = String(path || '').trim();
      if (!p) return '';
      p = p.split('?')[0];
      if (!p.startsWith('/')) p = '/' + p;
      if (p === '/api') return '/';
      if (p.indexOf('/api/') === 0) p = p.slice(4) || '/';
      return p.toLowerCase();
    }

    normalizeApiMethod(method) {
      return String(method || '').trim().toUpperCase();
    }

    parseApiAllowEntry(entry, index, errors) {
      if (typeof entry === 'string') {
        const raw = entry.trim();
        const match = raw.match(/^([A-Za-z]+)\s+(.+)$/);
        if (!match) {
          errors.push(`manifest.capabilities.api.allow[${index}] deve ser 'METODO /rota'.`);
          return null;
        }
        const method = this.normalizeApiMethod(match[1]);
        const path = this.normalizeApiPath(match[2]);
        if (!method || !path) {
          errors.push(`manifest.capabilities.api.allow[${index}] inválido.`);
          return null;
        }
        return { method, path };
      }
      if (entry && typeof entry === 'object') {
        const method = this.normalizeApiMethod(entry.method);
        const path = this.normalizeApiPath(entry.path);
        if (!method || !path) {
          errors.push(`manifest.capabilities.api.allow[${index}] deve ter method e path.`);
          return null;
        }
        return { method, path };
      }
      errors.push(`manifest.capabilities.api.allow[${index}] deve ser string ou objeto.`);
      return null;
    }

    normalizeAllowedOrigins(origins, errors) {
      if (origins === undefined || origins === null) return ['*'];
      let list = origins;
      if (typeof origins === 'string') {
        list = [origins];
      }
      if (!Array.isArray(list)) {
        errors.push('manifest.sandbox.postMessage.allowedOrigins deve ser array ou string.');
        return ['*'];
      }
      const normalized = list.map((item, index) => {
        if (typeof item !== 'string') {
          errors.push(`manifest.sandbox.postMessage.allowedOrigins[${index}] deve ser string.`);
          return '';
        }
        return item.trim();
      }).filter(Boolean);
      return normalized.length ? normalized : ['*'];
    }

    validateManifest(rawManifest, options = {}) {
      const errors = [];
      let manifest = rawManifest;
      const requireManifest = !!options.requireManifest;
      const isEmbed = !!options.embed;
      const referrerOrigin = options.referrerOrigin || '';

      if (manifest === null || manifest === undefined) {
        if (requireManifest) {
          errors.push('manifest é obrigatório neste contexto.');
          return { ok: false, errors };
        }
        return {
          ok: true,
          errors: [],
          normalizedManifest: {
            runtime: null,
            contextRequirements: { mode: 'hybrid' },
            capabilities: {
              api: { allowAll: true, allow: [] },
              storage: {
                enabled: true,
                kv: true,
                docs: true,
                docsTypes: ['user_data'],
                blobs: true,
                scope: 'context'
              },
              events: { enabled: true, allowAll: true, allow: [], publish: [], subscribe: [] }
            },
            telemetry: { enabled: false },
            sandbox: { postMessage: { allowedOrigins: ['*'] } }
          }
        };
      }

      if (typeof manifest === 'string') {
        const raw = manifest.trim();
        if (!raw) {
          if (requireManifest) {
            errors.push('manifest é obrigatório neste contexto.');
            return { ok: false, errors };
          }
          return {
            ok: true,
            errors: [],
            normalizedManifest: {
              runtime: null,
              contextRequirements: { mode: 'hybrid' },
              capabilities: {
                api: { allowAll: true, allow: [] },
                storage: {
                  enabled: true,
                  kv: true,
                  docs: true,
                  docsTypes: ['user_data'],
                  blobs: true,
                  scope: 'context'
                },
                events: { enabled: true, allowAll: true, allow: [], publish: [], subscribe: [] }
              },
              telemetry: { enabled: false },
              sandbox: { postMessage: { allowedOrigins: ['*'] } }
            }
          };
        } else {
          try {
            manifest = JSON.parse(raw);
          } catch (_) {
            errors.push('manifest JSON inválido.');
            manifest = null;
          }
        }
      }

      if (!manifest || typeof manifest !== 'object') {
        errors.push('manifest deve ser um objeto.');
        return { ok: false, errors };
      }

      const normalized = {
        runtime: null,
        contextRequirements: { mode: 'hybrid' },
        capabilities: {
          api: { allowAll: true, allow: [] },
          storage: {
            enabled: true,
            kv: true,
            docs: true,
            docsTypes: ['user_data'],
            blobs: true,
            scope: 'context'
          },
          events: { enabled: true, allowAll: true, allow: [], publish: [], subscribe: [] }
        },
        telemetry: { enabled: false },
        sandbox: { postMessage: { allowedOrigins: ['*'] } }
      };

      if (typeof manifest.runtime !== 'string' || !manifest.runtime.trim()) {
        errors.push('manifest.runtime deve ser string não vazia.');
      } else {
        normalized.runtime = manifest.runtime.trim().toLowerCase();
      }

      if (!manifest.contextRequirements || typeof manifest.contextRequirements !== 'object') {
        errors.push('manifest.contextRequirements deve ser um objeto.');
      } else {
        const mode = String(manifest.contextRequirements.mode || '').trim().toLowerCase();
        const allowedModes = ['user', 'business', 'team', 'hybrid'];
        if (!mode || !allowedModes.includes(mode)) {
          errors.push(`manifest.contextRequirements.mode deve ser um de: ${allowedModes.join(', ')}.`);
        } else {
          normalized.contextRequirements.mode = mode;
        }
        if (typeof manifest.contextRequirements.allowContextSwitch === 'boolean') {
          normalized.contextRequirements.allowContextSwitch = manifest.contextRequirements.allowContextSwitch;
        }
      }

      if (!manifest.capabilities || typeof manifest.capabilities !== 'object') {
        errors.push('manifest.capabilities deve ser um objeto.');
      } else {
        const api = manifest.capabilities.api;
        if (!api || typeof api !== 'object') {
          errors.push('manifest.capabilities.api deve ser um objeto.');
        } else if (api.allow !== undefined) {
          let allowList = api.allow;
          if (typeof allowList === 'string') {
            allowList = [allowList];
          }
          if (!Array.isArray(allowList)) {
            errors.push('manifest.capabilities.api.allow deve ser array ou string.');
          } else {
            normalized.capabilities.api.allowAll = false;
            allowList.forEach((entry, index) => {
              const parsed = this.parseApiAllowEntry(entry, index, errors);
              if (parsed) normalized.capabilities.api.allow.push(parsed);
            });
          }
        }

        const storage = manifest.capabilities.storage;
        if (storage !== undefined) {
          let docsTypesProvided = false;
          if (typeof storage === 'boolean') {
            normalized.capabilities.storage.enabled = storage;
            normalized.capabilities.storage.kv = storage;
            normalized.capabilities.storage.docs = storage;
            normalized.capabilities.storage.blobs = storage;
            normalized.capabilities.storage.docsTypes = storage ? ['user_data'] : [];
          } else if (storage && typeof storage === 'object') {
            const kv = (storage.kv !== undefined) ? Boolean(storage.kv) : normalized.capabilities.storage.kv;
            let docs = normalized.capabilities.storage.docs;
            let docsTypes = normalized.capabilities.storage.docsTypes.slice();
            let docsTypesSource = '';
            if (storage.docs !== undefined) {
              if (typeof storage.docs === 'boolean') {
                docs = storage.docs;
              } else if (storage.docs && typeof storage.docs === 'object') {
                if (storage.docs.enabled !== undefined) {
                  docs = Boolean(storage.docs.enabled);
                } else {
                  docs = true;
                }
                if (Array.isArray(storage.docs.types)) {
                  docsTypesProvided = true;
                  docsTypesSource = 'docs.types';
                  docsTypes = storage.docs.types.map((item, index) => {
                    if (typeof item !== 'string') {
                      errors.push(`manifest.capabilities.storage.docs.types[${index}] deve ser string.`);
                      return '';
                    }
                    return item.trim().toLowerCase();
                  }).filter(Boolean);
                }
              } else {
                errors.push('manifest.capabilities.storage.docs deve ser boolean ou objeto.');
              }
            }
            const blobs = (storage.blobs !== undefined) ? Boolean(storage.blobs) : normalized.capabilities.storage.blobs;
            normalized.capabilities.storage.kv = kv;
            normalized.capabilities.storage.docs = docs;
            if (!docsTypesProvided && Array.isArray(storage.docsTypes)) {
              docsTypesProvided = true;
              docsTypesSource = 'docsTypes';
              docsTypes = storage.docsTypes.map((item, index) => {
                if (typeof item !== 'string') {
                  errors.push(`manifest.capabilities.storage.docsTypes[${index}] deve ser string.`);
                  return '';
                }
                return item.trim().toLowerCase();
              }).filter(Boolean);
            }
            if (docs) {
              if (!docsTypesProvided) {
                docsTypes = ['user_data'];
              } else if (docsTypes.length === 0) {
                // PIPE rule: empty docs.types is invalid when docs is enabled.
                errors.push(
                  docsTypesSource === 'docsTypes'
                    ? 'manifest.capabilities.storage.docsTypes não pode ser vazio.'
                    : 'manifest.capabilities.storage.docs.types não pode ser vazio.'
                );
              }
            }
            if (!docs) docsTypes = [];
            normalized.capabilities.storage.docsTypes = docsTypes;
            normalized.capabilities.storage.blobs = blobs;
            normalized.capabilities.storage.enabled = kv || docs || blobs;

            if (storage.scope !== undefined) {
              const scope = String(storage.scope || '').trim().toLowerCase();
              if (!scope || (scope !== 'user' && scope !== 'context')) {
                errors.push('manifest.capabilities.storage.scope deve ser "user" ou "context".');
              } else {
                normalized.capabilities.storage.scope = scope;
              }
            }
          } else {
            errors.push('manifest.capabilities.storage deve ser boolean ou objeto.');
          }
        }

        const events = manifest.capabilities.events;
        if (events !== undefined) {
          if (typeof events === 'boolean') {
            normalized.capabilities.events.enabled = events;
            normalized.capabilities.events.allowAll = events;
          } else if (Array.isArray(events)) {
            normalized.capabilities.events.allowAll = false;
            normalized.capabilities.events.allow = events.map((item, index) => {
              if (typeof item !== 'string') {
                errors.push(`manifest.capabilities.events[${index}] deve ser string.`);
                return '';
              }
              return item.trim();
            }).filter(Boolean);
            normalized.capabilities.events.publish = normalized.capabilities.events.allow.slice();
            normalized.capabilities.events.subscribe = normalized.capabilities.events.allow.slice();
            normalized.capabilities.events.enabled = normalized.capabilities.events.allow.length > 0;
          } else if (events && typeof events === 'object') {
            const hasLists = events.allow !== undefined || events.publish !== undefined || events.subscribe !== undefined;
            if (events.allow !== undefined) {
              if (!Array.isArray(events.allow)) {
                errors.push('manifest.capabilities.events.allow deve ser array.');
              } else {
                normalized.capabilities.events.allowAll = false;
                normalized.capabilities.events.allow = events.allow.map((item, index) => {
                  if (typeof item !== 'string') {
                    errors.push(`manifest.capabilities.events.allow[${index}] deve ser string.`);
                    return '';
                  }
                  return item.trim();
                }).filter(Boolean);
                normalized.capabilities.events.publish = normalized.capabilities.events.allow.slice();
                normalized.capabilities.events.subscribe = normalized.capabilities.events.allow.slice();
              }
            }
            if (events.publish !== undefined) {
              if (!Array.isArray(events.publish)) {
                errors.push('manifest.capabilities.events.publish deve ser array.');
              } else {
                normalized.capabilities.events.allowAll = false;
                normalized.capabilities.events.publish = events.publish.map((item, index) => {
                  if (typeof item !== 'string') {
                    errors.push(`manifest.capabilities.events.publish[${index}] deve ser string.`);
                    return '';
                  }
                  return item.trim();
                }).filter(Boolean);
              }
            }
            if (events.subscribe !== undefined) {
              if (!Array.isArray(events.subscribe)) {
                errors.push('manifest.capabilities.events.subscribe deve ser array.');
              } else {
                normalized.capabilities.events.allowAll = false;
                normalized.capabilities.events.subscribe = events.subscribe.map((item, index) => {
                  if (typeof item !== 'string') {
                    errors.push(`manifest.capabilities.events.subscribe[${index}] deve ser string.`);
                    return '';
                  }
                  return item.trim();
                }).filter(Boolean);
              }
            }
            if (events.enabled !== undefined) {
              normalized.capabilities.events.enabled = Boolean(events.enabled);
              if (!normalized.capabilities.events.enabled) {
                normalized.capabilities.events.allowAll = false;
                normalized.capabilities.events.allow = [];
                normalized.capabilities.events.publish = [];
                normalized.capabilities.events.subscribe = [];
              }
            } else if (hasLists) {
              const hasAny = normalized.capabilities.events.publish.length > 0 ||
                normalized.capabilities.events.subscribe.length > 0;
              normalized.capabilities.events.enabled = hasAny;
            } else {
              normalized.capabilities.events.allowAll = false;
            }
          } else {
            errors.push('manifest.capabilities.events deve ser boolean, array ou objeto.');
          }
        }
      }

      if (manifest.telemetry !== undefined) {
        if (!manifest.telemetry || typeof manifest.telemetry !== 'object') {
          errors.push('manifest.telemetry deve ser um objeto.');
        } else if (manifest.telemetry.enabled !== undefined) {
          normalized.telemetry.enabled = Boolean(manifest.telemetry.enabled);
        }
      }

      if (!manifest.sandbox || typeof manifest.sandbox !== 'object') {
        errors.push('manifest.sandbox deve ser um objeto.');
      } else {
        const postMessage = manifest.sandbox.postMessage;
        if (!postMessage || typeof postMessage !== 'object') {
          errors.push('manifest.sandbox.postMessage deve ser um objeto.');
        } else {
          normalized.sandbox.postMessage.allowedOrigins = this.normalizeAllowedOrigins(
            postMessage.allowedOrigins,
            errors
          );
          if (isEmbed && normalized.sandbox.postMessage.allowedOrigins.includes('*')) {
            if (referrerOrigin) {
              normalized.sandbox.postMessage.allowedOrigins = [referrerOrigin];
            } else {
              errors.push('manifest.sandbox.postMessage.allowedOrigins não pode conter "*" em embed.');
              normalized.sandbox.postMessage.allowedOrigins = [];
            }
          }
        }
      }

      return {
        ok: errors.length === 0,
        errors,
        normalizedManifest: normalized
      };
    }

    isApiAllowed(method, path) {
      if (!this.manifest || !this.manifest.capabilities || !this.manifest.capabilities.api) {
        return true;
      }
      const api = this.manifest.capabilities.api;
      if (api.allowAll) return true;
      const allow = Array.isArray(api.allow) ? api.allow : [];
      const normalizedMethod = this.normalizeApiMethod(method);
      const normalizedPath = this.normalizeApiPath(path);
      return allow.some((rule) => {
        if (!rule || !rule.method || !rule.path) return false;
        if (rule.method !== normalizedMethod) return false;
        if (rule.path === '*' || rule.path === '/*') return true;
        if (rule.path.endsWith('*')) {
          const prefix = rule.path.slice(0, -1);
          return normalizedPath.startsWith(prefix);
        }
        return rule.path === normalizedPath;
      });
    }

    isStorageAllowed(action) {
      if (!this.manifest || !this.manifest.capabilities || !this.manifest.capabilities.storage) {
        return true;
      }
      const storage = this.manifest.capabilities.storage;
      if (storage.enabled === false) return false;
      const enabled = (storage.enabled === true)
        || Boolean(storage.kv || (storage.docs && (storage.docs.enabled || Array.isArray(storage.docs.types))) || storage.blobs);
      if (!action) return enabled;
      if (action.indexOf('kv.') === 0) return storage.kv;
      if (action.indexOf('docs.') === 0) return storage.docs;
      if (action.indexOf('blobs.') === 0) return storage.blobs;
      return enabled;
    }

    isDocTypeAllowed(docType) {
      if (!this.manifest || !this.manifest.capabilities || !this.manifest.capabilities.storage) {
        return true;
      }
      const storage = this.manifest.capabilities.storage;
      if (!storage.docs) return false;
      const types = Array.isArray(storage.docsTypes) ? storage.docsTypes : [];
      if (!types.length) return false;
      if (!docType) return false;
      return types.includes(String(docType).toLowerCase());
    }

    getStorageScopeMode() {
      const scope = this.manifest?.capabilities?.storage?.scope;
      return scope === 'user' ? 'user' : 'context';
    }

    matchEventRule(rule, type) {
      const r = String(rule || '').trim();
      if (!r) return false;
      if (r === '*') return true;
      if (r.endsWith('*')) {
        const prefix = r.slice(0, -1);
        return String(type || '').startsWith(prefix);
      }
      return r === type;
    }

    isEventPublishAllowed(type) {
      if (!this.manifest || !this.manifest.capabilities || !this.manifest.capabilities.events) {
        return true;
      }
      const events = this.manifest.capabilities.events;
      if (!events.enabled) return false;
      if (events.allowAll) return true;
      const allow = Array.isArray(events.publish) ? events.publish : [];
      if (!allow.length) return false;
      return allow.some((rule) => this.matchEventRule(rule, type));
    }

    isEventSubscribeAllowed(type) {
      if (!this.manifest || !this.manifest.capabilities || !this.manifest.capabilities.events) {
        return true;
      }
      const events = this.manifest.capabilities.events;
      if (!events.enabled) return false;
      if (events.allowAll) return true;
      const allow = Array.isArray(events.subscribe) ? events.subscribe : [];
      if (!allow.length) return false;
      return allow.some((rule) => this.matchEventRule(rule, type));
    }

    isOriginAllowed(origin) {
      const origins = this.manifest?.sandbox?.postMessage?.allowedOrigins;
      if (!Array.isArray(origins) || origins.length === 0) return true;
      if (origins.includes('*')) return true;
      if (!origin) return false;
      return origins.includes(origin);
    }

    getReferrerOrigin() {
      try {
        const ref = document.referrer ? new URL(document.referrer).origin : '';
        if (ref) return ref;
      } catch (_) {}
      return '';
    }

    getPostMessageTargetOrigin() {
      const origins = this.manifest?.sandbox?.postMessage?.allowedOrigins;
      if (Array.isArray(origins)) {
        const primary = origins.find((item) => item && item !== '*');
        if (primary) return primary;
      }
      try {
        const referrer = document.referrer ? new URL(document.referrer).origin : '';
        if (referrer) return referrer;
      } catch (_) {}
      if (typeof window !== 'undefined' && window.location && window.location.origin) {
        return window.location.origin;
      }
      return null;
    }

    isTelemetryEnabled() {
      return Boolean(this.manifest?.telemetry?.enabled);
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
        this.contextDenyReason = { code: 'context_not_allowed', required: mode, received: ctxType || 'none' };
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
      if (this.manifestValidation && this.manifestValidation.ok === false) {
        this.contextAllowed = false;
        return this.contextAllowed;
      }
      this.contextAllowed = this.enforceContextRequirements();
      return this.contextAllowed;
    }

    storageGuard(action) {
      if (!this.contextAllowed) {
        const reason = this.contextDenyReason || {};
        if (reason.code === 'manifest_invalid') {
          return {
            blocked: true,
            status: 403,
            code: 'manifest_invalid',
            message: 'Manifesto inválido: storage bloqueado.',
            reason: 'manifest_invalid'
          };
        }
        return {
          blocked: true,
          status: 403,
          code: 'context_not_allowed',
          message: 'Contexto inválido para storage.',
          reason: 'context_not_allowed'
        };
      }
      const actionId = action?.action || '';
      if (!this.isStorageAllowed(actionId)) {
        return {
          blocked: true,
          status: 403,
          code: 'storage_capability_denied',
          message: 'Storage não permitido pelo manifesto.',
          reason: 'storage_capability_denied'
        };
      }
      if (actionId.indexOf('docs.') === 0) {
        const docType = action?.docType || '';
        if (!this.isDocTypeAllowed(docType)) {
          return {
            blocked: true,
            status: 403,
            code: 'storage_capability_denied',
            message: 'DocType não permitido pelo manifesto.',
            reason: 'doc_type_not_allowed'
          };
        };
      }
      return null;
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
      if (!this.isEventSubscribeAllowed(type)) {
        console.warn('Evento não permitido para subscribe pelo manifesto:', type);
        this.emitSecurity('event_subscribe', 'event_subscribe_denied', { type: type });
        return;
      }
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

    isInternalEvent(type) {
      return type === 'sdk:ready' ||
        type === 'sdk:telemetry' ||
        type === 'sdk:security' ||
        type === 'app:context_denied';
    }

    emit(type, payload) {
      const internal = this.isInternalEvent(type);
      if (!internal) {
        if (!this.contextAllowed) {
          this.emitSecurity('event_publish', 'context_not_allowed', { type: type });
          return false;
        }
        if (!this.isEventPublishAllowed(type)) {
          console.warn('Evento não permitido para publish pelo manifesto:', type);
          this.emitSecurity('event_publish', 'event_publish_denied', { type: type, code: 'event_publish_denied' });
          return false;
        }
      }
      this.dispatchLocal(type, payload);
      // Also emit to parent if in iframe
      if (this.adapter) {
        const canPublish = this.contextAllowed && this.isEventPublishAllowed(type);
        if (type === 'sdk:security' || canPublish) {
          this.adapter.postMessage(type, payload);
        }
      }
      return true;
    }

    emitSecurity(action, reason, details) {
      const payload = Object.assign(
        { action: action, reason: reason },
        (details && typeof details === 'object') ? details : {}
      );
      this.dispatchLocal('sdk:security', payload);
      if (this.adapter) {
        this.adapter.postMessage('sdk:security', payload);
      }
    }

    handleTelemetry(payload) {
      if (!this.isTelemetryEnabled()) return;
      if (!payload || (payload.type !== 'api' && payload.type !== 'storage')) return;
      if (payload.type === 'api') {
        this._telemetryBuffer.push({
          durationMs: Number(payload.durationMs) || 0,
          ok: Boolean(payload.ok)
        });
        if (this._telemetryBuffer.length > this._telemetryBufferSize) {
          this._telemetryBuffer.splice(0, this._telemetryBuffer.length - this._telemetryBufferSize);
        }
      }
      this.emit('sdk:telemetry', payload);
    }

    getTelemetrySnapshot() {
      const samples = this._telemetryBuffer.slice();
      const count = samples.length;
      if (!count) {
        return { count: 0, avgMs: 0, p95Ms: 0 };
      }
      const total = samples.reduce((sum, item) => sum + (item.durationMs || 0), 0);
      const durations = samples.map(item => item.durationMs || 0).sort((a, b) => a - b);
      const idx = Math.max(0, Math.ceil(0.95 * durations.length) - 1);
      return {
        count: count,
        avgMs: Math.round(total / count),
        p95Ms: durations[idx] || 0
      };
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

    setContext(ctx) {
      if (!this.auth) {
        console.warn('WorkzSDK.setContext: auth module indisponivel.');
        return false;
      }
      if (this.platform && this.platform.isIframe) {
        const isPreview = !!(this.appConfig && this.appConfig.preview) ||
          !!(global && global.WorkzAppConfig && global.WorkzAppConfig.preview);
        if (!isPreview) {
          console.warn('WorkzSDK.setContext: blocked in embed (not preview).');
          return false;
        }
        console.warn('WorkzSDK.setContext: allowed in preview iframe.');
      }
      if (!ctx || typeof ctx !== 'object') {
        console.warn('WorkzSDK.setContext: contexto invalido.');
        return false;
      }
      const mode = String(ctx.mode || ctx.type || '').toLowerCase();
      const id = parseInt(ctx.id, 10);
      if (!mode || !Number.isFinite(id) || id <= 0) {
        console.warn('WorkzSDK.setContext: contexto invalido.');
        return false;
      }
      this.auth.context = { type: mode, id: id };
      if (typeof this.auth.notifyAuthUpdate === 'function') {
        try { this.auth.notifyAuthUpdate(); } catch (_) {}
      }
      this.contextAllowed = this.enforceContextRequirements();
      return this.contextAllowed;
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
  if (typeof sdkInstance.validateManifest !== 'function') {
    sdkInstance.validateManifest = function(manifest) {
      return WorkzSDK.prototype.validateManifest.call(sdkInstance, manifest);
    };
  }

  // Export both the class and instance for flexibility
  global.WorkzSDK = sdkInstance;
  global.WorkzSDKClass = WorkzSDK;

  // For module systems
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { WorkzSDK: sdkInstance, WorkzSDKClass };
  }

  /*
  Manual tests (validateManifest):
  A) docs true, no docsTypes/docs.types -> ok, docsTypes ["user_data"]
     WorkzSDK.validateManifest({ runtime: 'js', contextRequirements:{mode:'hybrid'}, capabilities:{ storage:{ docs:true } }, sandbox:{postMessage:{allowedOrigins:['https://x']}} })
  B) docs true, docsTypes [] -> ok=false, errors include "manifest.capabilities.storage.docsTypes não pode ser vazio."
     WorkzSDK.validateManifest({ runtime: 'js', contextRequirements:{mode:'hybrid'}, capabilities:{ storage:{ docs:true, docsTypes:[] } }, sandbox:{postMessage:{allowedOrigins:['https://x']}} })
  C) docs false, docsTypes ["user_data"] -> ok=true, docsTypes []
     WorkzSDK.validateManifest({ runtime: 'js', contextRequirements:{mode:'hybrid'}, capabilities:{ storage:{ docs:false, docsTypes:['user_data'] } }, sandbox:{postMessage:{allowedOrigins:['https://x']}} })
  */

})(typeof window !== 'undefined' ? window : globalThis);
