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
    constructor() {
      this.token = null;
      this.user = null;
      this.context = null;
      this.ready = false;
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
      
      if (this.token) {
        localStorage.setItem('jwt_token', this.token);
      } else {
        this.token = localStorage.getItem('jwt_token');
      }
      
      if (this.token && !this.user) {
        try {
          this.user = await this.fetchUserProfile(apiClient);
        } catch(e) {
          console.warn('Failed to fetch user profile:', e);
        }
      }
      
      this.ready = true;
      return true;
    }

    async initEmbed(apiClient) {
      this.token = this.parseTokenFromUrl();
      if (this.token) {
        localStorage.setItem('jwt_token', this.token);
      }

      return new Promise((resolve) => {
        const onMessage = (ev) => {
          const data = ev?.data || {};
          if (!data || typeof data !== 'object') return;
          
          if (data.type === 'workz-sdk:auth') {
            this.token = data.jwt || null;
            this.user = data.user || null;
            this.context = data.context || null;
            
            if (this.token) {
              localStorage.setItem('jwt_token', this.token);
            }
            
            this.finishInit(apiClient, resolve);
          }
        };

        window.addEventListener('message', onMessage, false);

        // If we already have token from URL, proceed without handshake
        if (this.token) {
          this.finishInit(apiClient, resolve);
          return;
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
            ...this.getUserScope()
          });
        },

        get: async (key) => {
          const params = new URLSearchParams({
            key: key,
            ...this.getUserScope()
          });
          return this.apiClient.get(`/appdata/kv?${params}`);
        },

        delete: async (key) => {
          const params = new URLSearchParams({
            key: key,
            ...this.getUserScope()
          });
          const url = this.apiClient.buildUrl(`/appdata/kv?${params}`);
          const response = await fetch(url, {
            method: 'DELETE',
            headers: this.apiClient.getHeaders()
          });
          return response.json();
        },

        list: async () => {
          const params = new URLSearchParams(this.getUserScope());
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
            ...this.getUserScope()
          });
        },

        get: async (id) => {
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: { docId: id },
            ...this.getUserScope()
          });
        },

        delete: async (id) => {
          const params = new URLSearchParams(this.getUserScope());
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
            ...this.getUserScope()
          });
        },

        query: async (query) => {
          return this.apiClient.post('/appdata/docs/query', {
            docType: 'user_data',
            filters: query,
            ...this.getUserScope()
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
          const scope = this.getUserScope();
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
          const params = new URLSearchParams(this.getUserScope());
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
          const params = new URLSearchParams(this.getUserScope());
          const url = this.apiClient.buildUrl(`/appdata/blobs/delete/${encodeURIComponent(id)}?${params}`);
          const response = await fetch(url, {
            method: 'DELETE',
            headers: this.apiClient.getHeaders()
          });
          return response.json();
        },

        list: async () => {
          const params = new URLSearchParams(this.getUserScope());
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
      this.listeners = {};
      this.initialized = false;
    }

    async init(config = {}) {
      if (this.initialized) {
        console.warn('WorkzSDK already initialized');
        return true;
      }

      // Detect platform
      this.platform = PlatformDetector.detect();
      
      // Initialize authentication module
      this.auth = new AuthenticationModule();
      
      // Initialize API client
      this.apiClient = new ApiClient({
        baseUrl: config.baseUrl || '/api',
        authModule: this.auth
      });
      
      // Initialize storage module
      this.storage = new StorageModule(this.apiClient, this.auth);
      
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
      
      this.initialized = true;
      
      // Emit ready event
      this.emit('sdk:ready', {
        platform: this.platform,
        user: this.auth.getUser(),
        version: this.version
      });
      
      return authResult;
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

    emit(type, payload) {
      if (this.listeners[type]) {
        this.listeners[type].forEach(callback => {
          try {
            callback(payload);
          } catch(e) {
            console.error('Error in event listener:', e);
          }
        });
      }
      
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
