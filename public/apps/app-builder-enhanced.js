/*
App Studio v0.9.2-mvpplus
Changelog:
- Aligned manifest handling with WorkzSDK v2 where needed (runtime + contextRequirements).
- Normalized storage.scope to user/context; legacy app -> context.
- Enforced docs.types rules (default when missing, invalid when empty).
- AllowedOrigins normalized (no "*", prefer referrer origin, fallback to location.origin).
- Preview now loads real WorkzSDK v2 and surfaces init errors in DOM.
- Preview now awaits WorkzSDK.init before executing app code and blocks on init failure.
- contextRequirements.mode normalized to user|business|team|hybrid (company->business).
- MVP-Plus: list/ID robustness, edit loading flow, CSRF headers, save diagnostics.
- MVP-Plus: draft autosave + restore, import JSON, duplicate/delete, manifest UI tweaks.
- MVP-Plus: preview diagnostics with logs + open in new tab.

MIGRATION NOTES
- Flutter/Dart flows removed entirely; App Studio now supports JavaScript apps only.
- Mini-IDE (CodeMirror) preserved and improved with safe init/destroy, debounce, and shortcuts.
- Preview now uses iframe srcdoc with sandbox; postMessage uses targetOrigin "*" and parent filters by origin.
- Manifest defaults normalized; docs types validation blocks preview/export if invalid.
- UI reduced to list -> edit -> preview/export; no new backend routes required.
- InnerHTML with user content removed; user data is set with textContent.
- If CodeMirror CDN fails, editor/preview are disabled with a clear warning.
*/
(function () {
    'use strict';

    // === CONFIG ===
    var VERSION = '0.9.2-mvpplus';
    var DEBUG = false;
    var CODEMIRROR_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css';
    var CODEMIRROR_THEME = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/theme/monokai.min.css';
    var CODEMIRROR_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js';
    var CODEMIRROR_MODE_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/javascript/javascript.min.js';
    var CODEMIRROR_DIALOG_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/dialog/dialog.min.css';
    var CODEMIRROR_FULLSCREEN_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/display/fullscreen.min.css';
    var CODEMIRROR_DIALOG_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/dialog/dialog.min.js';
    var CODEMIRROR_SEARCHCURSOR_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/search/searchcursor.min.js';
    var CODEMIRROR_SEARCH_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/search/search.min.js';
    var CODEMIRROR_MATCHBRACKETS_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/edit/matchbrackets.min.js';
    var CODEMIRROR_CLOSEBRACKETS_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/edit/closebrackets.min.js';
    var CODEMIRROR_ACTIVE_LINE_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/selection/active-line.min.js';
    var CODEMIRROR_COMMENT_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/comment/comment.min.js';
    var CODEMIRROR_FULLSCREEN_JS = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/addon/display/fullscreen.min.js';
    var TAILWIND_JS = 'https://cdn.tailwindcss.com';
    var APP_STUDIO_MANIFEST = {
        runtime: 'js',
        contextRequirements: { mode: 'user', allowContextSwitch: true },
        capabilities: {
            api: {
                allow: [
                    'GET /me',
                    'GET /apps/my-apps',
                    'GET /apps/*',
                    'POST /apps/create',
                    'POST /apps/update/*',
                    'POST /apps/delete/*',
                    'DELETE /apps/*'
                ]
            },
            events: { publish: [], subscribe: [] },
            storage: { kv: false, docs: { enabled: false, types: [] }, blobs: false, scope: 'context' }
        },
        sandbox: { postMessage: { allowedOrigins: [] } }
    };
    var DEFAULT_STARTER_CODE = [
        '(function(){',
        '  var SHOW_RAW = false;',
        '  var Modules = {};',
        '  function define(name, factory){ Modules[name] = { factory: factory, instance: null }; }',
        '  function require(name){',
        '    var mod = Modules[name];',
        '    if (!mod) throw new Error(\"Module not found: \" + name);',
        '    if (!mod.instance) mod.instance = mod.factory();',
        '    return mod.instance;',
        '  }',
        '',
        '  define(\"core/ui\", function(){',
        '    function el(tag, text){',
        '      var n = document.createElement(tag);',
        '      if (text !== undefined) n.textContent = String(text);',
        '      return n;',
        '    }',
        '    function renderBase(root){',
        '      root.textContent = \"\";',
        '      var wrap = el(\"div\");',
        '      var line1 = el(\"div\", \"WorkzSDK keys: \");',
        '      var line2 = el(\"div\", \"WorkzApp ready: \");',
        '      var line3 = el(\"div\", \"User id: n/a\");',
        '      wrap.appendChild(line1);',
        '      wrap.appendChild(line2);',
        '      wrap.appendChild(line3);',
        '      var actions = el(\"div\");',
        '      actions.style.marginTop = \"8px\";',
        '      wrap.appendChild(actions);',
        '      var pre = el(\"pre\");',
        '      pre.style.whiteSpace = \"pre-wrap\";',
        '      pre.style.fontSize = \"12px\";',
        '      root.appendChild(wrap);',
        '      root.appendChild(pre);',
        '      return { line1: line1, line2: line2, line3: line3, pre: pre, actions: actions };',
        '    }',
        '    return { el: el, renderBase: renderBase };',
        '  });',
        '',
        '  define(\"core/bus\", function(){',
        '    function publish(name, payload){',
        '      if (!window.WorkzApp || !WorkzApp.events || typeof WorkzApp.events.publish !== \"function\") {',
        '        throw new Error(\"events indisponiveis\");',
        '      }',
        '      return WorkzApp.events.publish(name, payload);',
        '    }',
        '    function on(name, handler){',
        '      if (!window.WorkzApp || !WorkzApp.events || typeof WorkzApp.events.on !== \"function\") {',
        '        throw new Error(\"events indisponiveis\");',
        '      }',
        '      if (window.WorkzSDK && typeof WorkzSDK.isEventSubscribeAllowed === \"function\") {',
        '        if (!WorkzSDK.isEventSubscribeAllowed(name)) throw new Error(\"events bloqueado\");',
        '      }',
        '      return WorkzApp.events.on(name, handler);',
        '    }',
        '    return { publish: publish, on: on };',
        '  });',
        '',
        '  define(\"core/sdk\", function(){',
        '    function getSdkKeys(){ return window.WorkzSDK ? Object.keys(window.WorkzSDK) : []; }',
        '    function isReady(){ return !!(window.WorkzApp && WorkzApp.state && window.WorkzApp.state.inited); }',
        '    async function getMe(){',
        '      if (!window.WorkzApp || !WorkzApp.api || typeof WorkzApp.api.getMe !== \"function\") {',
        '        throw new Error(\"WorkzApp.api.getMe indisponivel\");',
        '      }',
        '      return WorkzApp.api.getMe();',
        '    }',
        '    return { getSdkKeys: getSdkKeys, isReady: isReady, getMe: getMe };',
        '  });',
        '',
        '  define(\"app/main\", function(){',
        '    var ui = require(\"core/ui\");',
        '    var sdk = require(\"core/sdk\");',
        '    var bus = require(\"core/bus\");',
        '    function maskMl(value){',
        '      var v = String(value || \"\");',
        '      if (!v) return \"\";',
        '      if (v.length <= 4) return \"***\";',
        '      return v.slice(0, 2) + \"***\" + v.slice(-2);',
        '    }',
        '    function appendLine(pre, text){',
        '      var line = String(text || \"\");',
        '      pre.textContent = (pre.textContent || \"\") + line + \"\\n\";',
        '    }',
        '    function setPre(pre, text, replace){',
        '      if (replace) { pre.textContent = String(text || \"\"); return; }',
        '      if (pre.textContent) pre.textContent += \"\\n\" + String(text || \"\");',
        '      else pre.textContent = String(text || \"\");',
        '    }',
        '    async function boot(opts){',
        '      var root = (opts && opts.root) || document.getElementById(\"app\") || document.getElementById(\"app-root\");',
        '      if (!root) return;',
        '      var view = ui.renderBase(root);',
        '      view.line1.textContent = \"WorkzSDK keys: \" + sdk.getSdkKeys().join(\", \");',
        '      view.line2.textContent = \"WorkzApp ready: \" + (sdk.isReady() ? \"true\" : \"false\");',
        '      var btn = ui.el(\"button\", \"Emit test event\");',
        '      btn.type = \"button\";',
        '      btn.className = \"btn btn-sm btn-outline-secondary\";',
        '      view.actions.appendChild(btn);',
        '      btn.addEventListener(\"click\", function(){',
        '        try {',
        '          var ok = bus.publish(\"app:test\", { ts: Date.now() });',
        '          if (ok === false) appendLine(view.pre, \"events: publish bloqueado\");',
        '        } catch (e) {',
        '          appendLine(view.pre, \"events: indisponivel ou bloqueado\");',
        '        }',
        '      });',
        '      try {',
        '        bus.on(\"app:test\", function(){',
        '          appendLine(view.pre, \"event app:test recebido\");',
        '        });',
        '      } catch (e) {',
        '        appendLine(view.pre, \"events: subscribe indisponivel ou bloqueado\");',
        '      }',
        '      try {',
        '        var me = await sdk.getMe();',
        '        var user = (me && me.user) ? me.user : {};',
        '        var userId = (user.id !== undefined && user.id !== null) ? user.id :',
        '          (user.us !== undefined && user.us !== null) ? user.us :',
        '          (user.user_id !== undefined && user.user_id !== null) ? user.user_id :',
        '          (user.uid !== undefined && user.uid !== null) ? user.uid : \"n/a\";',
        '        view.line3.textContent = \"User id: \" + userId;',
        '        var raw = me.raw || {};',
        '        var companies = raw.companies || (me.data && me.data.companies) || [];',
        '        var summary = {',
        '          id: userId,',
        '          tt: raw.tt || user.tt || user.name || \"\",',
        '          companies_count: Array.isArray(companies) ? companies.length : 0,',
        '          ml: maskMl(user.ml || raw.ml || \"\")',
        '        };',
        '        var allowRaw = SHOW_RAW || (window.WorkzApp && WorkzApp.state && (WorkzApp.state.preview || WorkzApp.state.debug));',
        '        if (allowRaw && SHOW_RAW) {',
        '          setPre(view.pre, JSON.stringify(me.raw || null, null, 2), true);',
        '        } else {',
        '          setPre(view.pre, JSON.stringify(summary, null, 2), false);',
        '        }',
        '      } catch (e) {',
        '        view.line3.textContent = \"User id: n/a\";',
        '        setPre(view.pre, String((e && e.message) ? e.message : e), false);',
        '      }',
        '    }',
        '    return { boot: boot };',
        '  });',
        '',
        '  window.StoreApp = window.StoreApp || {};',
        '  window.StoreApp.bootstrap = function(opts){',
        '    if (window.StoreApp.__bootstrapped) return;',
        '    window.StoreApp.__bootstrapped = true;',
        '    return require(\"app/main\").boot(opts || {});',
        '  };',
        '  if (window.WorkzAppConfig && window.WorkzAppConfig.preview) {',
        '    window.StoreApp.bootstrap({ sdk: window.WorkzSDK, appConfig: window.WorkzAppConfig, root: document.getElementById(\"app\") });',
        '  }',
        '})();'
    ].join('\n');

    // === STATE ===
    var state = {
        view: 'list',
        apps: [],
        companies: [],
        loadingApps: false,
        loadingCompanies: false,
        loadingEditor: false,
        editorReady: false,
        editorDisabled: false,
        editorError: '',
        editorWrap: false,
        editorFullscreen: false,
        dirty: false,
        slugTouched: false,
        validationErrors: [],
        validationWarnings: [],
        app: {
            id: null,
            title: '',
            slug: '',
            description: '',
            version: '1.0.0',
            companyId: '',
            accessLevel: 1,
            logo: '',
            color: '',
            status: 1,
            price: 0,
            aspectRatio: '',
            supportsPortrait: true,
            supportsLandscape: true,
            exclusiveEntityId: '',
            code: DEFAULT_STARTER_CODE,
            manifest: {
                runtime: 'js',
                contextRequirements: {
                    mode: 'user',
                    allowContextSwitch: true
                },
                capabilities: {
                    api: {
                        allow: ['GET /me']
                    },
                    storage: {
                        kv: true,
                        docs: {
                            enabled: true,
                            types: ['user_data']
                        },
                        blobs: true,
                        scope: 'context'
                    },
                    events: {
                        publish: ['sdk:ready', 'app:test'],
                        subscribe: ['sdk:ready', 'app:test']
                    },
                    proxy: {
                        sources: []
                    }
                },
                sandbox: {
                    postMessage: {
                        allowedOrigins: []
                    }
                }
            }
        }
    };

    var cm = null;
    var debouncedValidate = null;
    var debouncedDraftSave = null;
    var sdkFallbackNotified = false;

    // === HELPERS ===
    function log() {
        if (!DEBUG) return;
        try { console.log.apply(console, arguments); } catch (_) {}
    }

    function getDefaultAppManifest() {
        return {
            runtime: 'js',
            contextRequirements: { mode: 'user', allowContextSwitch: true },
            capabilities: {
                api: { allow: ['GET /me'] },
                storage: {
                    kv: true,
                    docs: { enabled: true, types: ['user_data'] },
                    blobs: true,
                    scope: 'context'
                },
                events: { publish: ['sdk:ready', 'app:test'], subscribe: ['sdk:ready', 'app:test'] },
                proxy: { sources: [] }
            },
            sandbox: { postMessage: { allowedOrigins: [] } }
        };
    }

    function buildStudioManifest() {
        var manifest = JSON.parse(JSON.stringify(APP_STUDIO_MANIFEST));
        try {
            if (window.location && window.location.origin) {
                manifest.sandbox.postMessage.allowedOrigins = [window.location.origin];
            }
        } catch (_) {}
        return manifest;
    }

    async function initStudioSdk() {
        if (!window.WorkzSDK || typeof WorkzSDK.init !== 'function') return;
        var manifest = buildStudioManifest();
        if (!window.WorkzAppConfig) window.WorkzAppConfig = {};
        var existing = window.WorkzAppConfig.manifest;
        var existingSummary = null;
        if (existing && typeof existing === 'object') {
            existingSummary = {
                runtime: typeof existing.runtime,
                capabilities: typeof existing.capabilities,
                sandbox: typeof existing.sandbox
            };
        } else if (existing !== undefined) {
            existingSummary = { type: typeof existing };
        }
        console.warn('[sdk:security] App Studio init manifest override', existingSummary);
        window.WorkzAppConfig.manifest = manifest;
        window.WorkzAppConfig.preview = false;
        try {
            await WorkzSDK.init({
                mode: 'standalone',
                appConfig: window.WorkzAppConfig,
                manifest: manifest
            });
            console.warn('[sdk:security] App Studio manifestValidation', WorkzSDK.manifestValidation, manifest.runtime);
            if (WorkzSDK && WorkzSDK.contextAllowed === false && WorkzSDK.contextDenyReason) {
                var deny = WorkzSDK.contextDenyReason || {};
                var required = deny.required || '';
                var code = deny.code || '';
                if (code === 'context_not_allowed' && String(required).toLowerCase() === 'user') {
                    try {
                        var meResp = null;
                        if (WorkzSDK.apiClient && typeof WorkzSDK.apiClient.request === 'function') {
                            meResp = await WorkzSDK.apiClient.request('GET', '/me');
                        } else if (WorkzSDK.api && typeof WorkzSDK.api.get === 'function') {
                            meResp = await WorkzSDK.api.get('/me');
                        }
                        var data = (meResp && typeof meResp === 'object' && meResp.data && typeof meResp.data === 'object') ? meResp.data : (meResp || {});
                        var user = (data && data.user) ? data.user : data;
                        var userId = (user.id !== undefined && user.id !== null) ? user.id :
                            (user.us !== undefined && user.us !== null) ? user.us :
                            (user.user_id !== undefined && user.user_id !== null) ? user.user_id :
                            (user.uid !== undefined && user.uid !== null) ? user.uid : null;
                        if (userId && WorkzSDK && typeof WorkzSDK.setContext === 'function') {
                            WorkzSDK.setContext({ mode: 'user', id: userId });
                            console.warn('[sdk:security] App Studio context set', userId);
                        }
                    } catch (e) {
                        console.warn('[sdk:security] App Studio context resolve failed', e);
                    }
                }
            }
            if (WorkzSDK.manifestValidation && WorkzSDK.manifestValidation.ok !== true) {
                notifySdkFallback('manifest_invalid_on_init');
            }
        } catch (e) {
            notifySdkFallback('sdk_init_failed');
        }
    }

    function isStudioEndpoint(path) {
        var p = path.startsWith('/') ? path : '/' + path;
        return p === '/me' || p.indexOf('/apps/') === 0;
    }

    function notifySdkFallback(reason) {
        if (sdkFallbackNotified) return;
        sdkFallbackNotified = true;
        console.warn('[sdk:security] App Studio SDK fallback:', reason);
        showNotice('SDK do App Studio inválido; usando fallback de rede.', 'warning');
    }

    function safeJsonParse(input, fallback) {
        try { return JSON.parse(input); } catch (_) { return fallback; }
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, wait);
        };
    }

    function el(tag, opts) {
        var node = document.createElement(tag);
        opts = opts || {};
        if (opts.className) node.className = opts.className;
        if (opts.text !== undefined) node.textContent = opts.text;
        if (opts.type) node.type = opts.type;
        if (opts.value !== undefined) node.value = opts.value;
        if (opts.id) node.id = opts.id;
        if (opts.attrs) {
            Object.keys(opts.attrs).forEach(function (k) { node.setAttribute(k, opts.attrs[k]); });
        }
        return node;
    }

    function qs(sel, root) { return (root || document).querySelector(sel); }
    function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    // MVP-Plus #1 — Diagnóstico e Robustez de ID/Lista
    function getAppId(app) {
        if (!app || typeof app !== 'object') return null;
        var id = app.id || app.app_id || app.aid || app.appId || app.ID || app.appid || app.appID || app._id;
        if (id === undefined || id === null || id === '') return null;
        if (typeof id === 'number') return String(id);
        if (typeof id === 'string') return id.trim() || null;
        return id;
    }

    // MVP-Plus #4 — Save to Server: payload compat + melhor retorno
    function extractAppIdFromSaveResponse(resp) {
        if (!resp || typeof resp !== 'object') return null;
        var id = resp.app_id || resp.id;
        if (!id && resp.data && typeof resp.data === 'object') {
            id = resp.data.id || resp.data.app_id || resp.data.appId;
        }
        if (id === undefined || id === null || id === '') return null;
        if (typeof id === 'number') return String(id);
        if (typeof id === 'string') return id.trim() || null;
        return id;
    }

    // MVP-Plus #3 — CSRF + headers padrão
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '').trim() : '';
    }

    // MVP-Plus #1 — Diagnóstico e Robustez de ID/Lista
    function normalizeAppsResponse(response) {
        if (!response) return [];
        if (Array.isArray(response)) return response;
        if (response.data) {
            if (Array.isArray(response.data)) return response.data;
            if (response.data.apps && Array.isArray(response.data.apps)) return response.data.apps;
        }
        if (response.apps && Array.isArray(response.apps)) return response.apps;
        return [];
    }

    // MVP-Plus #7 — Duplicate App (clonar)
    function getUniqueSlug(base) {
        var clean = sanitizeSlug(base || 'app');
        var baseSlug = clean.endsWith('-copy') ? clean : (clean + '-copy');
        var existing = {};
        state.apps.forEach(function (item) {
            if (item && item.slug) existing[String(item.slug).toLowerCase()] = true;
        });
        if (!existing[baseSlug]) return baseSlug;
        var i = 2;
        while (existing[baseSlug + i]) i += 1;
        return baseSlug + i;
    }

    // MVP-Plus #5 — Rascunho persistente por app (localStorage)
    function getDraftKey(appId, slug) {
        var key = appId || slug || 'new';
        return 'appstudio:draft:' + String(key);
    }

    function saveDraftLocal(appId, slug, payload) {
        try {
            var key = getDraftKey(appId, slug);
            localStorage.setItem(key, JSON.stringify(payload));
        } catch (_) {}
    }

    function loadDraftLocal(appId, slug) {
        try {
            var key = getDraftKey(appId, slug);
            var raw = localStorage.getItem(key);
            return raw ? safeJsonParse(raw, null) : null;
        } catch (_) {
            return null;
        }
    }

    function discardDraftLocal(appId, slug) {
        try {
            var key = getDraftKey(appId, slug);
            localStorage.removeItem(key);
        } catch (_) {}
    }

    function sanitizeTitle(value) {
        var v = String(value || '').replace(/[\x00-\x1F\x7F]/g, '').trim();
        if (v.length > 80) v = v.slice(0, 80);
        return v;
    }

    function sanitizeSlug(value) {
        var v = String(value || '').toLowerCase();
        v = v.replace(/[^a-z0-9-]/g, '-');
        v = v.replace(/-+/g, '-').replace(/^-|-$/g, '');
        if (v.length > 40) v = v.slice(0, 40);
        return v;
    }

    function toNumber(value, fallback) {
        var n = Number(String(value || '').replace(',', '.'));
        return Number.isFinite(n) ? n : (fallback || 0);
    }

    function isValidCnpj(value) {
        var cnpj = String(value || '').replace(/\D/g, '');
        if (cnpj.length !== 14) return false;
        if (/^(\d)\1+$/.test(cnpj)) return false;
        var calcCheck = function (base) {
            var sum = 0;
            var weight = base.length - 7;
            for (var i = 0; i < base.length; i += 1) {
                sum += parseInt(base.charAt(i), 10) * weight--;
                if (weight < 2) weight = 9;
            }
            var mod = sum % 11;
            return (mod < 2) ? 0 : (11 - mod);
        };
        var base12 = cnpj.slice(0, 12);
        var d1 = calcCheck(base12);
        var d2 = calcCheck(base12 + d1);
        return cnpj === base12 + String(d1) + String(d2);
    }

    function domReady() {
        if (document.readyState === 'loading') {
            return new Promise(function (resolve) {
                document.addEventListener('DOMContentLoaded', resolve, { once: true });
            });
        }
        return Promise.resolve();
    }

    function loadCSS(href) {
        return new Promise(function (resolve, reject) {
            if (document.querySelector('link[href="' + href + '"]')) {
                resolve();
                return;
            }
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = resolve;
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadJS(src) {
        return new Promise(function (resolve, reject) {
            if (document.querySelector('script[src="' + src + '"]')) {
                resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = src;
            script.async = false;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function setDirty(value) {
        state.dirty = !!value;
        renderDirtyIndicator();
    }

    function renderDirtyIndicator() {
        var badge = qs('#dirty-indicator');
        if (!badge) return;
        if (!state.app.id || state.dirty) {
            badge.textContent = 'Não salvo';
            badge.classList.remove('text-slate-500');
            badge.classList.add('text-amber-600');
        } else {
            badge.textContent = 'Salvo';
            badge.classList.remove('text-amber-600');
            badge.classList.add('text-slate-500');
        }
    }

    function showNotice(message, type) {
        var area = qs('#notice-area');
        if (!area) return;
        area.textContent = '';
        if (!message) return;
        var alert = el('div', { className: twAlert(type || 'info') });
        alert.textContent = message;
        area.appendChild(alert);
    }

    function updateValidationErrors(errors, warnings) {
        state.validationErrors = errors || [];
        state.validationWarnings = warnings || [];
        var area = qs('#validation-errors');
        if (!area) return;
        area.textContent = '';
        if (!state.validationErrors.length && !state.validationWarnings.length) return;
        area.className = twAlert(state.validationErrors.length ? 'danger' : 'warning');
        if (state.validationErrors.length) {
            area.appendChild(el('div', { className: 'font-semibold mb-1', text: 'Erros' }));
            var list = el('ul', { className: 'list-disc pl-5 space-y-1' });
            state.validationErrors.forEach(function (err) {
                var item = el('li', { text: err });
                list.appendChild(item);
            });
            area.appendChild(list);
        }
        if (state.validationWarnings.length) {
            area.appendChild(el('div', { className: 'font-semibold mt-3 mb-1', text: 'Avisos' }));
            var listWarn = el('ul', { className: 'list-disc pl-5 space-y-1' });
            state.validationWarnings.forEach(function (msg) {
                var itemWarn = el('li', { text: msg });
                listWarn.appendChild(itemWarn);
            });
            area.appendChild(listWarn);
        }
    }

    function normalizeManifestFromState() {
        var origin = (window.location && window.location.origin) ? window.location.origin : '';
        var refOrigin = '';
        try {
            if (document.referrer) {
                refOrigin = new URL(document.referrer).origin;
            }
        } catch (_) {}

        var manifest = JSON.parse(JSON.stringify(state.app.manifest || {}));

        // Legacy migration
        if (!manifest.runtime) manifest.runtime = 'js';
        if (!manifest.contextRequirements) manifest.contextRequirements = {};
        if (manifest.contextMode && !manifest.contextRequirements.mode) {
            manifest.contextRequirements.mode = String(manifest.contextMode).toLowerCase();
        }
        if (typeof manifest.allowContextSwitch === 'boolean' && typeof manifest.contextRequirements.allowContextSwitch === 'undefined') {
            manifest.contextRequirements.allowContextSwitch = manifest.allowContextSwitch;
        }
        delete manifest.contextMode;
        delete manifest.allowContextSwitch;
        var allowedModes = { user: true, business: true, team: true, hybrid: true };
        var rawMode = String(manifest.contextRequirements.mode || '').toLowerCase();
        if (rawMode === 'company') rawMode = 'business';
        if (!allowedModes[rawMode]) rawMode = 'user';
        manifest.contextRequirements.mode = rawMode;

        if (!manifest.capabilities) manifest.capabilities = {};
        if (!manifest.capabilities.api) manifest.capabilities.api = { allow: [] };
        if (!Array.isArray(manifest.capabilities.api.allow)) manifest.capabilities.api.allow = [];

        if (!manifest.capabilities.events) manifest.capabilities.events = { publish: [], subscribe: [] };
        if (!Array.isArray(manifest.capabilities.events.publish)) manifest.capabilities.events.publish = [];
        if (!Array.isArray(manifest.capabilities.events.subscribe)) manifest.capabilities.events.subscribe = [];

        if (!manifest.capabilities.storage) manifest.capabilities.storage = {};
        if (!manifest.capabilities.storage.docs) {
            manifest.capabilities.storage.docs = { enabled: false, types: [] };
        }
        if (manifest.capabilities.storage.scope === 'app' || !manifest.capabilities.storage.scope) {
            manifest.capabilities.storage.scope = 'context';
        }
        if (manifest.capabilities.storage.scope !== 'user' && manifest.capabilities.storage.scope !== 'context') {
            manifest.capabilities.storage.scope = 'context';
        }

        var docs = manifest.capabilities.storage.docs;
        if (docs.enabled) {
            if (typeof docs.types === 'undefined') {
                docs.types = ['user_data'];
            } else if (!Array.isArray(docs.types)) {
                docs.types = [];
            }
            if (docs.types.length === 0) {
                return { ok: false, error: 'Docs habilitado, mas \'types\' está vazio. Informe ao menos um tipo (ex.: user_data).' };
            }
        } else {
            docs.types = [];
        }

        if (!manifest.capabilities.proxy) {
            manifest.capabilities.proxy = { sources: [] };
        }
        if (!Array.isArray(manifest.capabilities.proxy.sources)) {
            manifest.capabilities.proxy.sources = [];
        }

        if (!manifest.sandbox) manifest.sandbox = {};
        if (!manifest.sandbox.postMessage) manifest.sandbox.postMessage = {};
        var allowed = manifest.sandbox.postMessage.allowedOrigins;
        if (!Array.isArray(allowed)) allowed = [];
        allowed = allowed.filter(function (o) { return o && o !== '*'; });
        if (!allowed.length) {
            if (refOrigin) allowed = [refOrigin];
            else if (origin) allowed = [origin];
        }
        manifest.sandbox.postMessage.allowedOrigins = allowed;

        return { ok: true, manifest: manifest };
    }

    function buildPreviewHtml(code, manifest) {
        var safeCode = String(code || '').replace(/<\/(script)/gi, '<\\/$1');
        var manifestJson = JSON.stringify(manifest || {});
        return '<!doctype html><html><head><meta charset="utf-8" />' +
            '<meta name="viewport" content="width=device-width, initial-scale=1" />' +
            '<style>html,body{height:100%}body{margin:0;font-family:system-ui,Arial,sans-serif}#app{min-height:100%}</style>' +
            '</head><body><div id="app"></div>' +
            '<script>window.WorkzAppConfig={manifest:' + manifestJson + ',preview:true};</script>' +
            '<script src="/js/core/workz-sdk-v2.js"></script>' +
            '<script src="/js/core/workz-app-pro.js"></script>' +
            '<script>(async function(){' +
            'var app=document.getElementById(\"app\");' +
            'var postMessage=function(source,type,msg){try{window.parent.postMessage({source:source,type:type,message:msg},\"*\");}catch(_){}};' +
            'var postLog=function(type,msg){postMessage(\"appstudio-preview\",type,msg);};' +
            'var postSdk=function(type,msg){postMessage(\"sdk\",type,msg);};' +
            'var logContext=function(){' +
            'var allowed=!!(window.WorkzSDK && WorkzSDK.contextAllowed);' +
            'var reason=\"\";' +
            'try{' +
            'var deny=(WorkzSDK && WorkzSDK.contextDenyReason) ? WorkzSDK.contextDenyReason : null;' +
            'if (typeof deny === \"string\") reason=deny;' +
            'else if (deny && typeof deny === \"object\") reason=deny.code || (deny.required ? \"context_not_allowed\" : \"\");' +
            '}catch(_){}' +
            'var mode=(window.WorkzAppConfig && WorkzAppConfig.manifest && WorkzAppConfig.manifest.contextRequirements && WorkzAppConfig.manifest.contextRequirements.mode) || \"n/a\";' +
            'postSdk(\"context\",\"allowed=\"+allowed+\" reason=\"+reason+\" mode=\"+mode);' +
            '};' +
            'var renderSdkError=function(e){var m=(e && e.message ? e.message : e);if(app){app.textContent=\"Erro ao iniciar preview (SDK): \" + m;}postSdk(\"error\",String(m));};' +
            'var renderWorkzAppError=function(e){var m=(e && e.message ? e.message : e);if(app){app.textContent=\"Erro ao iniciar preview (WorkzApp): \" + m;}postLog(\"workzapp\",String(m));};' +
            'var renderAppError=function(e){var m=(e && e.message ? e.message : e);if(app){app.textContent=\"Erro ao executar app (JS): \" + m;}postLog(\"app\",String(m));};' +
            'window.onerror=function(msg,src,line,col,err){renderAppError(err||msg);};' +
            'window.onunhandledrejection=function(ev){renderAppError(ev && ev.reason ? ev.reason : ev);};' +
            'try{' +
            'if(!window.WorkzSDK || typeof WorkzSDK.init!==\"function\"){throw new Error(\"WorkzSDK não carregou\");}' +
            'if (window.WorkzSDK && typeof WorkzSDK.on === \"function\") {' +
            'WorkzSDK.on(\"sdk:security\", function(p){var msg=\"action=\"+(p && p.action ? p.action : \"n/a\")+\" reason=\"+(p && p.reason ? p.reason : \"n/a\")+\" type=\"+(p && p.type ? p.type : \"n/a\");postSdk(\"security\",msg);});' +
            'WorkzSDK.on(\"sdk:telemetry\", function(p){var msg=\"type=\"+(p && p.type ? p.type : \"n/a\")+\" ok=\"+(p && typeof p.ok!==\"undefined\" ? p.ok : \"n/a\")+\" ms=\"+(p && p.durationMs ? p.durationMs : 0);postSdk(\"telemetry\",msg);});' +
            'WorkzSDK.on(\"app:context_denied\", function(p){var msg=\"required=\"+(p && p.required ? p.required : \"n/a\")+\" received=\"+(p && p.received ? p.received : \"n/a\");postSdk(\"context\",msg);});' +
            'WorkzSDK.on(\"sdk:ready\", function(p){var msg=\"ready v=\"+(p && p.version ? p.version : \"n/a\");postSdk(\"ready\",msg);});' +
            '}' +
            'await WorkzSDK.init({mode:\"standalone\",appConfig:window.WorkzAppConfig,manifest:window.WorkzAppConfig.manifest});' +
            '}catch(e){console.warn(\"WorkzSDK init failed\",e);renderSdkError(e);return;}' +
            // WorkzApp Pro integration
            'try{' +
            'if(!window.WorkzApp || typeof WorkzApp.init!==\"function\"){throw new Error(\"WorkzApp não carregou\");}' +
            'await WorkzApp.init({sdk:WorkzSDK,appConfig:window.WorkzAppConfig,preview:true,debug:true,initSdk:false});' +
            '}catch(e){console.warn(\"WorkzApp init failed\",e);renderWorkzAppError(e);return;}' +
            'try{' +
            'logContext();' +
            'var denyReason = (WorkzSDK && WorkzSDK.contextDenyReason) ? WorkzSDK.contextDenyReason : null;' +
            'var denyCode = (typeof denyReason === \"string\") ? denyReason : (denyReason && typeof denyReason === \"object\" ? denyReason.code : \"\");' +
            'var required = (denyReason && typeof denyReason === \"object\" && denyReason.required) ? denyReason.required : null;' +
            'var mode = (window.WorkzAppConfig && WorkzAppConfig.manifest && WorkzAppConfig.manifest.contextRequirements && WorkzAppConfig.manifest.contextRequirements.mode) || \"\";' +
            'if (WorkzSDK && WorkzSDK.contextAllowed === false && (denyCode === \"context_not_allowed\" || required === \"user\") && String(mode).toLowerCase() === \"user\") {' +
            'var pf = (WorkzSDK && typeof WorkzSDK.getPlatform === \"function\") ? WorkzSDK.getPlatform() : (WorkzSDK ? WorkzSDK.platform : null);' +
            'var isIframe = pf && pf.isIframe ? true : false;' +
            'postSdk(\"context\",\"before iframe=\"+isIframe+\" preview=\"+!!(window.WorkzAppConfig && WorkzAppConfig.preview)+\" sdkPreview=\"+!!(WorkzSDK && WorkzSDK.appConfig && WorkzSDK.appConfig.preview)+\" allowed=\"+WorkzSDK.contextAllowed+\" reason=\"+denyCode);' +
            'var meResp = null;' +
            'if (WorkzSDK.apiClient && typeof WorkzSDK.apiClient.request === \"function\") {' +
            'meResp = await WorkzSDK.apiClient.request(\"GET\", \"/me\");' +
            '} else if (WorkzSDK.api && typeof WorkzSDK.api.get === \"function\") {' +
            'meResp = await WorkzSDK.api.get(\"/me\");' +
            '}' +
            'var data = (meResp && typeof meResp === \"object\" && meResp.data && typeof meResp.data === \"object\") ? meResp.data : (meResp || {});' +
            'var user = (data && data.user) ? data.user : data;' +
            'var userId = (user.id !== undefined && user.id !== null) ? user.id :' +
            '(user.us !== undefined && user.us !== null) ? user.us :' +
            '(user.user_id !== undefined && user.user_id !== null) ? user.user_id :' +
            '(user.uid !== undefined && user.uid !== null) ? user.uid : null;' +
            'if (userId && WorkzSDK && typeof WorkzSDK.setContext === \"function\") {' +
            'WorkzSDK.setContext({ mode: \"user\", id: userId });' +
            'logContext();' +
            '} else { postSdk(\"context\",\"resolve_failed_no_user\"); }' +
            '}' +
            '}catch(e){postSdk(\"context\",\"resolve_failed\");}' +
            'try{' +
            '(function(){\n' + safeCode + '\n})();' +
            '}catch(e){console.warn(\"App code error\",e);renderAppError(e);}' +
            '})();</script>' +
            '</body></html>';
    }

    function readManifestFromForm() {
        var apiAllow = (qs('#manifest-api-allow') || {}).value || '';
        var publish = (qs('#manifest-events-publish') || {}).value || '';
        var subscribe = (qs('#manifest-events-subscribe') || {}).value || '';
        var origins = (qs('#manifest-origins') || {}).value || '';
        var ctxMode = (qs('#manifest-context-mode') || {}).value || 'user';
        var ctxSwitch = (qs('#manifest-allow-context-switch') || {}).checked;
        var docsEnabled = (qs('#manifest-docs-enabled') || {}).checked;
        var docsTypes = (qs('#manifest-docs-types') || {}).value || '';
        var kvEnabled = (qs('#manifest-kv-enabled') || {}).checked;
        var blobsEnabled = (qs('#manifest-blobs-enabled') || {}).checked;
        var proxySources = (qs('#manifest-proxy-sources') || {}).value || '';
        var storageScope = (qs('#manifest-storage-scope') || {}).value || 'context';
        if (storageScope === 'app') storageScope = 'context';
        if (storageScope !== 'user' && storageScope !== 'context') storageScope = 'context';

        state.app.manifest.contextRequirements = state.app.manifest.contextRequirements || {};
        state.app.manifest.contextRequirements.mode = ctxMode;
        state.app.manifest.contextRequirements.allowContextSwitch = !!ctxSwitch;
        state.app.manifest.capabilities.api.allow = apiAllow.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
        state.app.manifest.capabilities.events.publish = publish.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
        state.app.manifest.capabilities.events.subscribe = subscribe.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
        state.app.manifest.sandbox.postMessage.allowedOrigins = origins.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
        state.app.manifest.capabilities.storage.docs.enabled = !!docsEnabled;
        state.app.manifest.capabilities.storage.docs.types = docsTypes.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
        state.app.manifest.capabilities.storage.kv = !!kvEnabled;
        state.app.manifest.capabilities.storage.blobs = !!blobsEnabled;
        state.app.manifest.capabilities.storage.scope = storageScope;
        state.app.manifest.capabilities.proxy = state.app.manifest.capabilities.proxy || { sources: [] };
        state.app.manifest.capabilities.proxy.sources = proxySources.split(/\n+/).map(function (s) { return s.trim(); }).filter(Boolean);
    }

    function formatApiAllowList(allow) {
        if (!Array.isArray(allow)) return [];
        return allow.map(function (entry) {
            if (!entry) return '';
            if (typeof entry === 'string') return entry.trim();
            if (typeof entry === 'object') {
                var method = entry.method || entry.m || entry.verb || '';
                var path = entry.path || entry.url || '';
                method = String(method || '').toUpperCase().trim();
                path = String(path || '').trim();
                if (method && path) return method + ' ' + path;
            }
            return '';
        }).filter(Boolean);
    }

    function normalizeApiAllowState() {
        if (!state.app || !state.app.manifest || !state.app.manifest.capabilities || !state.app.manifest.capabilities.api) return;
        var api = state.app.manifest.capabilities.api;
        var allow = api.allow;
        if (!allow && Array.isArray(api.allowlist)) allow = api.allowlist;
        if (Array.isArray(allow)) {
            api.allow = formatApiAllowList(allow);
        }
    }

    function writeManifestToForm() {
        if (!qs('#manifest-api-allow')) return;
        normalizeApiAllowState();
        var ctxMode = (state.app.manifest.contextRequirements && state.app.manifest.contextRequirements.mode) ? state.app.manifest.contextRequirements.mode : 'user';
        var ctxSwitch = (state.app.manifest.contextRequirements && typeof state.app.manifest.contextRequirements.allowContextSwitch === 'boolean')
            ? state.app.manifest.contextRequirements.allowContextSwitch
            : true;
        var ctxSelect = qs('#manifest-context-mode');
        var ctxCheck = qs('#manifest-allow-context-switch');
        if (ctxSelect) ctxSelect.value = ctxMode;
        if (ctxCheck) ctxCheck.checked = !!ctxSwitch;
        qs('#manifest-api-allow').value = formatApiAllowList(state.app.manifest.capabilities.api.allow || []).join('\n');
        qs('#manifest-events-publish').value = (state.app.manifest.capabilities.events.publish || []).join('\n');
        qs('#manifest-events-subscribe').value = (state.app.manifest.capabilities.events.subscribe || []).join('\n');
        qs('#manifest-origins').value = (state.app.manifest.sandbox.postMessage.allowedOrigins || []).join('\n');
        qs('#manifest-proxy-sources').value = (state.app.manifest.capabilities.proxy && state.app.manifest.capabilities.proxy.sources)
            ? state.app.manifest.capabilities.proxy.sources.join('\n')
            : '';
        qs('#manifest-docs-enabled').checked = !!state.app.manifest.capabilities.storage.docs.enabled;
        qs('#manifest-docs-types').value = (state.app.manifest.capabilities.storage.docs.types || []).join('\n');
        qs('#manifest-kv-enabled').checked = !!state.app.manifest.capabilities.storage.kv;
        qs('#manifest-blobs-enabled').checked = !!state.app.manifest.capabilities.storage.blobs;
        qs('#manifest-storage-scope').value = state.app.manifest.capabilities.storage.scope || 'context';
    }

    // === SDK / API ===
    async function apiCall(method, path, body) {
        var useSdk = window.WorkzSDK && WorkzSDK.api && typeof WorkzSDK.api[method] === 'function';
        var sdkOk = useSdk && WorkzSDK.manifestValidation && WorkzSDK.manifestValidation.ok === true;
        if (useSdk && sdkOk) {
            var sdkResp = await WorkzSDK.api[method](path, body);
            var msg = sdkResp && typeof sdkResp.message === 'string' ? sdkResp.message : '';
            var invalidMsg = msg.indexOf('Manifesto inválido') === 0;
            if (sdkResp && (sdkResp.code === 'manifest_invalid' || invalidMsg) && isStudioEndpoint(path)) {
                notifySdkFallback('manifest_invalid');
            } else if (sdkResp && sdkResp.code === 'context_not_allowed' && isStudioEndpoint(path)) {
                notifySdkFallback('context_not_allowed');
            } else {
                return sdkResp;
            }
        } else if (useSdk && !sdkOk && isStudioEndpoint(path)) {
            notifySdkFallback('manifest_not_ok');
        }
        var basePath = path.startsWith('/') ? path : '/' + path;
        var urls = ['/api' + basePath, basePath];
        if (state && state.debug) {
            urls = urls.map(function (u) { return u.indexOf('?') === -1 ? (u + '?debug=1') : (u + '&debug=1'); });
        }
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var csrf = getCsrfToken();
        if (csrf) headers['X-CSRF-TOKEN'] = csrf;
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        for (var i = 0; i < urls.length; i += 1) {
            var url = urls[i];
            var resp = await fetch(url, {
                method: method.toUpperCase(),
                headers: headers,
                credentials: 'same-origin',
                body: body !== undefined ? JSON.stringify(body || {}) : undefined
            });
            if (resp.status === 404 || resp.status === 405) {
                continue;
            }
            return parseJsonSafe(resp, url);
        }
        var lastUrl = urls[urls.length - 1];
        var lastResp = await fetch(lastUrl, {
            method: method.toUpperCase(),
            headers: headers,
            credentials: 'same-origin',
            body: body !== undefined ? JSON.stringify(body || {}) : undefined
        });
        return parseJsonSafe(lastResp, lastUrl);
    }

    async function parseJsonSafe(resp, url) {
        var status = resp && typeof resp.status === 'number' ? resp.status : 0;
        try {
            var data = await resp.json();
            if (!data || typeof data !== 'object') {
                return { success: false, status: status, message: 'Resposta JSON inválida', __httpStatus: status, __url: url || '' };
            }
            data.__httpStatus = status;
            data.__url = url || '';
            return data;
        } catch (_) {
            try {
                var txt = await resp.text();
                return { success: false, status: status, message: txt.slice(0, 500), __httpStatus: status, __url: url || '' };
            } catch (e2) {
                return { success: false, status: status, message: 'Falha ao interpretar resposta', __httpStatus: status, __url: url || '' };
            }
        }
    }

    function twContainer() {
        return 'mx-auto w-full max-w-6xl px-4 py-6';
    }

    function twHeaderRow() {
        return 'flex flex-wrap items-center justify-between gap-3 mb-4';
    }

    function twCard() {
        return 'rounded-xl border border-slate-200 bg-white shadow-sm';
    }

    function twCardBody() {
        return 'p-4';
    }

    function twTitle() {
        return 'text-2xl font-semibold text-slate-900';
    }

    function twSectionTitle() {
        return 'text-lg font-semibold text-slate-900';
    }

    function twMuted() {
        return 'text-sm text-slate-500';
    }

    function twLabel() {
        return 'block text-sm font-medium text-slate-700 mb-1';
    }

    function twInput() {
        return 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20';
    }

    function twTextarea() {
        return 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20';
    }

    function twSelect() {
        return twInput() + ' pr-8';
    }

    function twCheckbox() {
        return 'h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500/20';
    }

    function twBtn(variant, size, outline) {
        var base = 'inline-flex items-center justify-center rounded-md font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
        var sizing = size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm';
        var styles = {
            primary: outline ? 'border border-blue-600 text-blue-700 hover:bg-blue-50 focus:ring-blue-500' : 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
            secondary: outline ? 'border border-slate-300 text-slate-700 hover:bg-slate-50 focus:ring-slate-400' : 'bg-slate-700 text-white hover:bg-slate-800 focus:ring-slate-500',
            success: outline ? 'border border-emerald-600 text-emerald-700 hover:bg-emerald-50 focus:ring-emerald-500' : 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
            danger: outline ? 'border border-rose-600 text-rose-700 hover:bg-rose-50 focus:ring-rose-500' : 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500',
            warning: outline ? 'border border-amber-600 text-amber-700 hover:bg-amber-50 focus:ring-amber-500' : 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-500'
        };
        var pick = styles[variant] || styles.primary;
        return [base, sizing, pick].join(' ');
    }

    function twAlert(type) {
        var base = 'rounded-md border px-3 py-2 text-sm';
        var map = {
            info: 'border-sky-200 bg-sky-50 text-sky-800',
            success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            warning: 'border-amber-200 bg-amber-50 text-amber-900',
            danger: 'border-rose-200 bg-rose-50 text-rose-900'
        };
        return base + ' ' + (map[type] || map.info);
    }

    // === UI ===
    function render() {
        var root = qs('#app-root');
        if (!root) return;
        root.textContent = '';
        if (state.view === 'list') {
            renderList(root);
        } else {
            renderEditor(root);
        }
    }

    function renderList(root) {
        var container = el('div', { className: twContainer() });
        var header = el('div', { className: twHeaderRow() });
        var title = el('h3', { className: twTitle(), text: 'App Studio (JS)' });
        var button = el('button', { className: twBtn('primary'), text: 'Criar app' });
        button.addEventListener('click', function () {
            startNewApp();
        });
        header.appendChild(title);
        header.appendChild(button);
        container.appendChild(header);

        var notice = el('div', { id: 'notice-area' });
        container.appendChild(notice);

        var list = el('div', { className: 'grid grid-cols-1 gap-4 md:grid-cols-2' });
        if (state.loadingApps) {
            var loadWrap = el('div', { className: 'col-span-full flex items-center gap-2 text-sm text-slate-500' });
            loadWrap.appendChild(el('div', { className: 'h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-transparent', attrs: { role: 'status', 'aria-hidden': 'true' } }));
            loadWrap.appendChild(el('div', { text: 'Carregando apps...' }));
            list.appendChild(loadWrap);
            for (var i = 0; i < 2; i += 1) {
                var skCard = el('div', { className: twCard() + ' h-full' });
                var skBody = el('div', { className: twCardBody() + ' animate-pulse space-y-3' });
                skBody.appendChild(el('div', { className: 'h-4 w-2/3 rounded bg-slate-200' }));
                skBody.appendChild(el('div', { className: 'h-3 w-1/2 rounded bg-slate-200' }));
                skBody.appendChild(el('div', { className: 'h-3 w-1/3 rounded bg-slate-200' }));
                skBody.appendChild(el('div', { className: 'h-8 w-24 rounded bg-slate-200' }));
                skBody.appendChild(el('div', { className: 'h-8 w-24 rounded bg-slate-200' }));
                skCard.appendChild(skBody);
                list.appendChild(skCard);
            }
        } else if (!state.apps.length) {
            var empty = el('div', { className: 'col-span-full text-sm text-slate-500', text: 'Nenhum app encontrado.' });
            list.appendChild(empty);
        } else {
            state.apps.forEach(function (app) {
                var card = el('div', { className: twCard() + ' h-full' });
                var body = el('div', { className: twCardBody() + ' flex flex-col gap-2' });
            var name = el('h5', { className: 'text-lg font-semibold text-slate-900', text: app.tt || app.title || 'Sem título' });
            var slug = el('div', { className: 'text-sm text-slate-500', text: app.slug ? app.slug + '.workz.co' : 'Sem slug' });
            var idNote = null;
            var btn = el('button', { className: twBtn('primary', 'sm', true) + ' mt-3', text: 'Editar', type: 'button' });
            var dupBtn = el('button', { className: twBtn('secondary', 'sm', true) + ' mt-1', text: 'Duplicar', type: 'button' });
            var appId = getAppId(app);
            if (!appId) {
                idNote = el('div', { className: 'text-xs text-amber-600 mt-2', text: 'Sem id retornado pela API' });
                btn.setAttribute('aria-disabled', 'true');
                btn.classList.add('opacity-50');
                btn.style.cursor = 'not-allowed';
                btn.addEventListener('click', function () {
                    log('[AppStudio] Edit click', { appId: appId });
                    showNotice('App sem id retornado pela API; verifique loadApps().', 'warning');
                });
                dupBtn.disabled = true;
            } else {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    log('[AppStudio] Edit click', { appId: appId });
                    editApp(appId);
                });
                dupBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    duplicateApp(app);
                });
            }
            body.appendChild(name);
            body.appendChild(slug);
            if (idNote) body.appendChild(idNote);
            body.appendChild(btn);
            body.appendChild(dupBtn);
                card.appendChild(body);
                list.appendChild(card);
            });
        }
        container.appendChild(list);
        root.appendChild(container);
    }

    function renderEditor(root) {
        var container = el('div', { className: twContainer() });

        var header = el('div', { className: twHeaderRow() });
        var title = el('div');
        var h3 = el('h3', { className: twTitle(), text: 'Editar app' });
        var dirty = el('span', { id: 'dirty-indicator', className: 'ml-2 text-sm text-slate-500', text: 'Salvo' });
        title.appendChild(h3);
        title.appendChild(dirty);
        var back = el('button', { className: twBtn('secondary', 'md', true), text: 'Voltar à lista' });
        back.addEventListener('click', function () { maybeBackToList(); });
        header.appendChild(title);
        header.appendChild(back);
        container.appendChild(header);

        var notice = el('div', { id: 'notice-area' });
        container.appendChild(notice);

        var topActions = renderActionBar();
        container.appendChild(topActions);

        if (state.loadingEditor) {
            var loadingCard = el('div', { className: twCard() + ' mb-4' });
            var loadingBody = el('div', { className: twCardBody() + ' animate-pulse space-y-3' });
            loadingBody.appendChild(el('div', { className: 'h-4 w-1/3 rounded bg-slate-200' }));
            loadingBody.appendChild(el('div', { className: 'h-4 w-2/3 rounded bg-slate-200' }));
            loadingBody.appendChild(el('div', { className: 'h-4 w-1/2 rounded bg-slate-200' }));
            loadingBody.appendChild(el('div', { className: 'h-4 w-3/4 rounded bg-slate-200' }));
            loadingCard.appendChild(loadingBody);
            container.appendChild(loadingCard);
            root.appendChild(container);
            return;
        }

        var form = el('div', { className: twCard() + ' mb-4' });
        var body = el('div', { className: twCardBody() });

        body.appendChild(fieldRow('Nome', 'app-title', state.app.title));
        body.appendChild(fieldRow('Slug', 'app-slug', state.app.slug));
        body.appendChild(textareaRow('Descrição', 'app-description'));
        body.appendChild(fieldRow('Versão', 'app-version', state.app.version));
        var logoWrap = el('div', { className: 'mb-4' });
        logoWrap.appendChild(el('label', { className: twLabel(), text: 'Logo' }));
        var logoInput = el('input', { className: twInput(), id: 'app-logo-file', attrs: { type: 'file', accept: 'image/*' } });
        var logoPreview = el('img', { attrs: { id: 'app-logo-preview', alt: 'Logo preview', style: 'display:none;max-width:120px;max-height:120px;margin-top:8px;border-radius:8px;border:1px solid #eee;' } });
        if (state.app.logo) {
            logoPreview.src = state.app.logo;
            logoPreview.style.display = 'block';
        }
        logoWrap.appendChild(logoInput);
        logoWrap.appendChild(logoPreview);
        body.appendChild(logoWrap);

        var colorWrap = el('div', { className: 'mb-4' });
        colorWrap.appendChild(el('label', { className: twLabel(), text: 'Cor' }));
        var colorInput = el('input', { className: 'h-10 w-20 cursor-pointer rounded-md border border-slate-300 bg-white p-1', id: 'app-color', attrs: { type: 'color' } });
        colorInput.value = state.app.color || '#000000';
        colorWrap.appendChild(colorInput);
        body.appendChild(colorWrap);

        var aspectWrap = el('div', { className: 'mb-4' });
        aspectWrap.appendChild(el('label', { className: twLabel(), text: 'Aspect ratio' }));
        var aspectSelect = el('select', { className: twSelect(), id: 'app-aspect-ratio' });
        ['','4:3','16:9','9:16','1:1','3:4','21:9'].forEach(function (optv) {
            var o = el('option', { text: optv || 'Padrão', value: optv });
            aspectSelect.appendChild(o);
        });
        aspectSelect.value = state.app.aspectRatio || '';
        aspectWrap.appendChild(aspectSelect);
        body.appendChild(aspectWrap);

        var exclusiveWrap = el('div', { className: 'mb-4' });
        exclusiveWrap.appendChild(el('label', { className: twLabel(), text: 'Exclusivo para negócio' }));
        var exclusiveSelect = el('select', { className: twSelect(), id: 'app-exclusive-entity' });
        exclusiveSelect.appendChild(el('option', { text: 'Não exclusivo', value: '' }));
        if (state.loadingCompanies) {
            var exclusiveLoading = el('option', { text: 'Carregando negócios...', value: '' });
            exclusiveLoading.disabled = true;
            exclusiveSelect.appendChild(exclusiveLoading);
        }
        exclusiveSelect.disabled = !!state.loadingCompanies;
        state.companies.forEach(function (c) {
            var o = el('option', { text: c.name, value: String(c.id) });
            if (String(c.id) === String(state.app.exclusiveEntityId)) o.selected = true;
            exclusiveSelect.appendChild(o);
        });
        exclusiveWrap.appendChild(exclusiveSelect);
        body.appendChild(exclusiveWrap);

        var statusRow = el('div', { className: 'grid grid-cols-1 gap-4 md:grid-cols-3 mb-4' });
        var statusCol = el('div');
        statusCol.appendChild(el('label', { className: twLabel(), text: 'Status' }));
        var statusSelect = el('select', { className: twSelect(), id: 'app-status' });
        statusSelect.appendChild(el('option', { text: 'Ativo', value: '1' }));
        statusSelect.appendChild(el('option', { text: 'Inativo', value: '0' }));
        statusSelect.value = String((state.app.status !== undefined && state.app.status !== null) ? state.app.status : 0);
        statusCol.appendChild(statusSelect);

        var priceCol = el('div');
        priceCol.appendChild(el('label', { className: twLabel(), text: 'Valor (R$)' }));
        var priceInput = el('input', { className: twInput(), id: 'app-price', attrs: { type: 'number', step: '0.01', min: '0' } });
        priceInput.value = String(state.app.price || 0);
        priceCol.appendChild(priceInput);

        var orientationCol = el('div');
        orientationCol.appendChild(el('label', { className: twLabel(), text: 'Orientação' }));
        var orientationWrap = el('div', { className: 'flex flex-wrap gap-4 mt-1' });
        var portraitCheck = el('div', { className: 'flex items-center gap-2' });
        var portraitInput = el('input', { className: twCheckbox(), id: 'app-supports-portrait', type: 'checkbox' });
        portraitInput.checked = state.app.supportsPortrait !== false;
        var portraitLabel = el('label', { className: 'text-sm text-slate-700', text: 'Portrait' });
        portraitLabel.setAttribute('for', 'app-supports-portrait');
        portraitCheck.appendChild(portraitInput);
        portraitCheck.appendChild(portraitLabel);
        var landscapeCheck = el('div', { className: 'flex items-center gap-2' });
        var landscapeInput = el('input', { className: twCheckbox(), id: 'app-supports-landscape', type: 'checkbox' });
        landscapeInput.checked = state.app.supportsLandscape !== false;
        var landscapeLabel = el('label', { className: 'text-sm text-slate-700', text: 'Landscape' });
        landscapeLabel.setAttribute('for', 'app-supports-landscape');
        landscapeCheck.appendChild(landscapeInput);
        landscapeCheck.appendChild(landscapeLabel);
        orientationWrap.appendChild(portraitCheck);
        orientationWrap.appendChild(landscapeCheck);
        orientationCol.appendChild(orientationWrap);

        statusRow.appendChild(statusCol);
        statusRow.appendChild(priceCol);
        statusRow.appendChild(orientationCol);
        body.appendChild(statusRow);

        var companyRow = el('div', { className: 'mb-4' });
        var companyLabel = el('label', { className: twLabel(), text: 'Empresa (publisher)' });
        var companySelect = el('select', { className: twSelect(), id: 'company-select' });
        var opt = el('option', { text: 'Selecionar empresa', value: '' });
        companySelect.appendChild(opt);
        if (state.loadingCompanies) {
            var loadingOpt = el('option', { text: 'Carregando empresas...', value: '' });
            loadingOpt.disabled = true;
            companySelect.appendChild(loadingOpt);
        }
        companySelect.disabled = !!state.loadingCompanies;
        state.companies.filter(function (c) {
            var level = Number(c.nv || 0);
            return level >= 3 && isValidCnpj(c.cnpj || '');
        }).forEach(function (c) {
            var label = c.name || 'Negócio';
            var cnpj = String(c.cnpj || '').trim();
            if (cnpj) label += ' — ' + cnpj;
            var o = el('option', { text: label, value: String(c.id) });
            if (String(c.id) === String(state.app.companyId)) o.selected = true;
            companySelect.appendChild(o);
        });
        companyRow.appendChild(companyLabel);
        companyRow.appendChild(companySelect);
        body.appendChild(companyRow);

        form.appendChild(body);
        container.appendChild(form);

        var editorCard = el('div', { className: twCard() + ' mb-4', attrs: { id: 'editor-card' } });
        var editorBody = el('div', { className: twCardBody() + ' editor-card-body' });
        var editorHeader = el('div', { className: 'flex flex-wrap items-center justify-between gap-3 mb-3' });
        editorHeader.appendChild(el('h5', { className: twSectionTitle(), text: 'Código' }));
        var tools = el('div', { className: 'flex flex-wrap gap-2' });
        var draftBtn = el('button', { className: twBtn('primary', 'sm', true), text: 'Salvar rascunho (Ctrl+S)' });
        draftBtn.addEventListener('click', function () { saveDraft(); });
        var discardDraftBtn = el('button', { className: twBtn('danger', 'sm', true), text: 'Descartar rascunho' });
        discardDraftBtn.addEventListener('click', function () {
            discardDraftLocal(state.app.id, state.app.slug);
            showNotice('Rascunho local descartado.', 'info');
        });
        var formatBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Formatar' });
        formatBtn.addEventListener('click', function () { formatCode(); });
        var findBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Buscar' });
        findBtn.addEventListener('click', function () { runEditorFind(); });
        var replaceBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Substituir' });
        replaceBtn.addEventListener('click', function () { runEditorReplace(); });
        var wrapBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Quebra: Desl.', attrs: { id: 'editor-wrap-toggle' } });
        wrapBtn.addEventListener('click', function () { toggleEditorWrap(); });
        var fullscreenBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Tela cheia', attrs: { id: 'editor-fullscreen-toggle' } });
        fullscreenBtn.addEventListener('click', function () { toggleEditorFullscreen(); });
        tools.appendChild(draftBtn);
        tools.appendChild(discardDraftBtn);
        tools.appendChild(formatBtn);
        tools.appendChild(findBtn);
        tools.appendChild(replaceBtn);
        tools.appendChild(wrapBtn);
        tools.appendChild(fullscreenBtn);
        editorHeader.appendChild(el('span', { className: twMuted(), text: 'IDE' }));
        editorBody.appendChild(editorHeader);

        var editorToolbar = el('div', { className: 'mb-3' });
        editorToolbar.appendChild(tools);
        editorBody.appendChild(editorToolbar);

        var warning = el('div', { id: 'editor-warning', className: twAlert('warning') + ' hidden' });
        warning.textContent = 'Falha ao carregar o CodeMirror. Edição e preview desabilitados.';
        editorBody.appendChild(warning);

        var editorContainer = el('div', { id: 'code-editor-container', className: 'rounded-md border border-slate-200' });
        var textarea = el('textarea', { id: 'app-code', className: twTextarea(), attrs: { rows: '14' } });
        textarea.value = state.app.code || '';
        editorContainer.appendChild(textarea);
        editorBody.appendChild(editorContainer);
        editorCard.appendChild(editorBody);
        container.appendChild(editorCard);

        var manifestCard = el('div', { className: twCard() + ' mb-4' });
        var manifestBody = el('div', { className: twCardBody() });
        var manifestHeader = el('div', { className: 'flex flex-wrap items-center justify-between gap-3 mb-4' });
        manifestHeader.appendChild(el('h5', { className: twSectionTitle(), text: 'Manifesto (PIPE)' }));
        var resetBtn = el('button', { className: twBtn('secondary', 'sm', true), text: 'Resetar manifesto' });
        resetBtn.addEventListener('click', function () {
            if (!confirm('Resetar manifest para os padrões?')) return;
            state.app.manifest = getDefaultAppManifest();
            writeManifestToForm();
            setDirty(true);
            scheduleValidate();
        });
        manifestHeader.appendChild(resetBtn);
        manifestBody.appendChild(manifestHeader);

        var ctxRow = el('div', { className: 'grid grid-cols-1 gap-4 md:grid-cols-2 mb-4' });
        var ctxCol = el('div');
        ctxCol.appendChild(el('label', { className: twLabel(), text: 'Modo de contexto' }));
        var ctxSelect = el('select', { className: twSelect(), id: 'manifest-context-mode' });
        ['user', 'business', 'team', 'hybrid'].forEach(function (mode) {
            var o = el('option', { text: mode, value: mode });
            ctxSelect.appendChild(o);
        });
        ctxCol.appendChild(ctxSelect);
        var ctxSwitchCol = el('div');
        var ctxCheck = el('div', { className: 'flex items-center gap-2 mt-6' });
        var ctxInput = el('input', { className: twCheckbox(), id: 'manifest-allow-context-switch', type: 'checkbox' });
        var ctxLabel = el('label', { className: 'text-sm text-slate-700', text: 'Permitir troca de contexto' });
        ctxLabel.setAttribute('for', 'manifest-allow-context-switch');
        ctxCheck.appendChild(ctxInput);
        ctxCheck.appendChild(ctxLabel);
        ctxSwitchCol.appendChild(ctxCheck);
        ctxRow.appendChild(ctxCol);
        ctxRow.appendChild(ctxSwitchCol);
        manifestBody.appendChild(ctxRow);

        manifestBody.appendChild(textareaRow('Allowlist de API (uma por linha)', 'manifest-api-allow'));
        manifestBody.appendChild(textareaRow('Eventos (publish)', 'manifest-events-publish'));
        manifestBody.appendChild(textareaRow('Eventos (subscribe)', 'manifest-events-subscribe'));
        manifestBody.appendChild(textareaRow('Origens permitidas (sem "*", uma por linha)', 'manifest-origins'));
        manifestBody.appendChild(textareaRow('Fontes de proxy (uma por linha)', 'manifest-proxy-sources'));

        var docsRow = el('div', { className: 'grid grid-cols-1 gap-4 md:grid-cols-3 mb-4' });
        var docsCol = el('div');
        var docsCheck = el('div', { className: 'flex items-center gap-2 mt-6' });
        var docsInput = el('input', { className: twCheckbox(), id: 'manifest-docs-enabled', type: 'checkbox' });
        var docsLabel = el('label', { className: 'text-sm text-slate-700', text: 'Ativar docs' });
        docsLabel.setAttribute('for', 'manifest-docs-enabled');
        docsCheck.appendChild(docsInput);
        docsCheck.appendChild(docsLabel);
        docsCol.appendChild(docsCheck);

        var docsTypesCol = el('div');
        docsTypesCol.appendChild(el('label', { className: twLabel(), text: 'Tipos de docs (um por linha)' }));
        docsTypesCol.appendChild(el('textarea', { className: twTextarea(), id: 'manifest-docs-types', attrs: { rows: '3' } }));

        var scopeCol = el('div');
        scopeCol.appendChild(el('label', { className: twLabel(), text: 'Escopo de armazenamento' }));
        var scopeSelect = el('select', { className: twSelect(), id: 'manifest-storage-scope' });
        ['context', 'user'].forEach(function (optv) {
            var o = el('option', { text: optv, value: optv });
            scopeSelect.appendChild(o);
        });
        scopeCol.appendChild(scopeSelect);

        docsRow.appendChild(docsCol);
        docsRow.appendChild(docsTypesCol);
        docsRow.appendChild(scopeCol);
        manifestBody.appendChild(docsRow);

        var kvRow = el('div', { className: 'grid grid-cols-1 gap-4 md:grid-cols-2 mb-3' });
        kvRow.appendChild(checkRow('manifest-kv-enabled', 'Ativar KV'));
        kvRow.appendChild(checkRow('manifest-blobs-enabled', 'Ativar Blobs'));
        manifestBody.appendChild(kvRow);

        var errors = el('div', { id: 'validation-errors', className: twAlert('danger') + ' hidden' });
        manifestBody.appendChild(errors);
        var summary = el('div', { id: 'capabilities-summary', className: 'text-xs text-slate-500' });
        manifestBody.appendChild(summary);

        manifestCard.appendChild(manifestBody);
        container.appendChild(manifestCard);

        root.appendChild(container);

        bindEditorForm();
        writeManifestToForm();
        setTimeout(function () {
            restoreDraftIfNeeded();
            initEditor();
            renderDirtyIndicator();
            validateForm();
        }, 0);
    }

    function fieldRow(labelText, id, value) {
        var wrap = el('div', { className: 'mb-4' });
        var label = el('label', { className: twLabel(), text: labelText });
        label.setAttribute('for', id);
        var input = el('input', { className: twInput(), id: id });
        input.value = value || '';
        wrap.appendChild(label);
        wrap.appendChild(input);
        return wrap;
    }

    function textareaRow(labelText, id) {
        var wrap = el('div', { className: 'mb-4' });
        var label = el('label', { className: twLabel(), text: labelText });
        label.setAttribute('for', id);
        var input = el('textarea', { className: twTextarea(), id: id, attrs: { rows: '3' } });
        if (id === 'app-description') {
            input.value = state.app.description || '';
        }
        wrap.appendChild(label);
        wrap.appendChild(input);
        return wrap;
    }

    function checkRow(id, labelText) {
        var col = el('div');
        var check = el('div', { className: 'flex items-center gap-2 mt-6' });
        var input = el('input', { className: twCheckbox(), id: id, type: 'checkbox' });
        var label = el('label', { className: 'text-sm text-slate-700', text: labelText });
        label.setAttribute('for', id);
        check.appendChild(input);
        check.appendChild(label);
        col.appendChild(check);
        return col;
    }

    function renderActionBar() {
        var actions = el('div', { className: twCard() + ' mb-4 sticky top-2 z-20', attrs: { style: 'z-index: 1020;' } });
        var body = el('div', { className: twCardBody() + ' flex flex-wrap gap-2 items-center' });
        var saveBtn = el('button', { className: twBtn('success'), text: 'Salvar no servidor' });
        saveBtn.addEventListener('click', function () { saveApp(); });
        var previewBtn = el('button', { className: twBtn('primary', 'md', true), text: 'Pré-visualizar (Ctrl+Enter)' });
        previewBtn.addEventListener('click', function () { showPreview(); });
        var previewTabBtn = el('button', { className: twBtn('primary', 'md', true), text: 'Abrir preview em nova aba' });
        previewTabBtn.addEventListener('click', function () { openPreviewInNewTab(); });
        var importBtn = el('button', { className: twBtn('secondary', 'md', true), text: 'Importar JSON' });
        var importInput = el('input', { attrs: { type: 'file', accept: 'application/json', style: 'display:none' } });
        importBtn.addEventListener('click', function () { importInput.click(); });
        importInput.addEventListener('change', function (e) {
            var file = e.target.files && e.target.files[0];
            if (file) importJsonFile(file);
            e.target.value = '';
        });
        var exportBtn = el('button', { className: twBtn('secondary', 'md', true), text: 'Exportar JSON' });
        exportBtn.addEventListener('click', function () { exportApp(); });
        var deleteBtn = el('button', { className: twBtn('danger', 'md', true) + ' ml-auto', text: 'Excluir' });
        deleteBtn.addEventListener('click', function () { deleteApp(); });
        body.appendChild(saveBtn);
        body.appendChild(previewBtn);
        body.appendChild(previewTabBtn);
        body.appendChild(importBtn);
        body.appendChild(exportBtn);
        body.appendChild(importInput);
        body.appendChild(deleteBtn);
        actions.appendChild(body);
        return actions;
    }

    // === EDITOR ===
    function ensureEditorStyles() {
        if (document.getElementById('editor-ide-styles')) return;
        var style = document.createElement('style');
        style.id = 'editor-ide-styles';
        style.textContent = [
            'body.cm-fullscreen { overflow: hidden; }',
            '#editor-card.cm-fullscreen { position: fixed; inset: 0; margin: 0; border-radius: 0; z-index: 1050; }',
            '#editor-card.cm-fullscreen .editor-card-body { height: 100%; display: flex; flex-direction: column; }',
            '#editor-card.cm-fullscreen #code-editor-container { flex: 1; }',
            '#editor-card.cm-fullscreen .CodeMirror { height: 100%; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    function updateWrapButton() {
        var btn = qs('#editor-wrap-toggle');
        if (!btn) return;
        btn.textContent = state.editorWrap ? 'Quebra: Lig.' : 'Quebra: Desl.';
    }

    function updateFullscreenButton() {
        var btn = qs('#editor-fullscreen-toggle');
        if (!btn) return;
        btn.textContent = state.editorFullscreen ? 'Sair da tela cheia' : 'Tela cheia';
    }

    function toggleEditorWrap(force) {
        if (typeof force === 'boolean') {
            state.editorWrap = force;
        } else {
            state.editorWrap = !state.editorWrap;
        }
        if (cm) cm.setOption('lineWrapping', state.editorWrap);
        updateWrapButton();
    }

    function toggleEditorFullscreen(force) {
        if (typeof force === 'boolean') {
            state.editorFullscreen = force;
        } else {
            state.editorFullscreen = !state.editorFullscreen;
        }
        ensureEditorStyles();
        var card = qs('#editor-card');
        if (card) {
            if (state.editorFullscreen) {
                card.classList.add('cm-fullscreen');
                document.body.classList.add('cm-fullscreen');
            } else {
                card.classList.remove('cm-fullscreen');
                document.body.classList.remove('cm-fullscreen');
            }
        }
        if (cm) {
            try { cm.setOption('fullScreen', state.editorFullscreen); } catch (_) {}
            setTimeout(function () { cm.refresh(); }, 50);
        }
        updateFullscreenButton();
    }

    function fallbackFind() {
        if (!cm) return;
        var query = prompt('Buscar');
        if (!query) return;
        var text = cm.getValue();
        var start = cm.indexFromPos(cm.getCursor('to'));
        var idx = text.indexOf(query, start);
        if (idx === -1) idx = text.indexOf(query);
        if (idx === -1) return;
        var from = cm.posFromIndex(idx);
        var to = cm.posFromIndex(idx + query.length);
        cm.setSelection(from, to);
        cm.focus();
    }

    function runEditorFind() {
        if (cm && typeof cm.execCommand === 'function') {
            try {
                cm.execCommand('findPersistent');
                return;
            } catch (_) {}
        }
        fallbackFind();
    }

    function runEditorReplace() {
        if (cm && typeof cm.execCommand === 'function') {
            try {
                cm.execCommand('replace');
                return;
            } catch (_) {}
        }
        fallbackFind();
    }

    function initEditor() {
        destroyEditor();
        if (state.editorDisabled) {
            var warning = qs('#editor-warning');
            if (warning) warning.classList.remove('hidden');
            var area = qs('#app-code');
            if (area) area.disabled = true;
            return;
        }
        var textarea = qs('#app-code');
        if (!textarea) return;
        if (typeof window.CodeMirror === 'undefined') {
            state.editorDisabled = true;
            initEditor();
            return;
        }
        ensureEditorStyles();
        cm = window.CodeMirror.fromTextArea(textarea, {
            lineNumbers: true,
            mode: 'javascript',
            theme: 'monokai',
            tabSize: 2,
            indentWithTabs: false,
            lineWrapping: state.editorWrap,
            autofocus: true,
            matchBrackets: true,
            autoCloseBrackets: true,
            styleActiveLine: true,
            extraKeys: {
                'Ctrl-F': runEditorFind,
                'Cmd-F': runEditorFind,
                'Ctrl-H': runEditorReplace,
                'Cmd-Alt-F': runEditorReplace,
                'Ctrl-G': 'findNext',
                'Shift-Ctrl-G': 'findPrev',
                'Ctrl-/': 'toggleComment',
                'Cmd-/': 'toggleComment',
                'Alt-Z': function () { toggleEditorWrap(); },
                'F11': function () { toggleEditorFullscreen(); },
                'Esc': function () { if (state.editorFullscreen) toggleEditorFullscreen(false); }
            }
        });
        cm.setValue(state.app.code || '');
        cm.on('change', function (instance) {
            state.app.code = instance.getValue();
            setDirty(true);
            scheduleValidate();
            scheduleDraftSave();
        });
        state.editorReady = true;
        updateWrapButton();
        updateFullscreenButton();
        setTimeout(function () { cm.refresh(); }, 50);
    }

    function destroyEditor() {
        if (cm) {
            try { cm.toTextArea(); } catch (_) {}
            cm = null;
        }
        state.editorReady = false;
        if (state.editorFullscreen) {
            state.editorFullscreen = false;
            var card = qs('#editor-card');
            if (card) card.classList.remove('cm-fullscreen');
            document.body.classList.remove('cm-fullscreen');
        }
    }

    function formatCode() {
        var code = state.app.code || '';
        var formatted = code.replace(/[ \t]+$/gm, '').trim() + '\n';
        state.app.code = formatted;
        if (cm) {
            cm.setValue(formatted);
        } else {
            var area = qs('#app-code');
            if (area) area.value = formatted;
        }
        setDirty(true);
    }

    function saveDraft() {
        if (state.editorDisabled) return;
        if (cm) state.app.code = cm.getValue();
        saveDraftLocal(state.app.id, state.app.slug, buildDraftPayload());
        showNotice('Rascunho salvo localmente (nao enviado ao servidor).', 'info');
        setTimeout(function () { showNotice('', ''); }, 1500);
    }

    function scheduleValidate() {
        if (!debouncedValidate) debouncedValidate = debounce(validateForm, 120);
        debouncedValidate();
    }

    function buildDraftPayload() {
        return {
            code: state.app.code || '',
            manifest: state.app.manifest || {},
            meta: {
                title: state.app.title || '',
                slug: state.app.slug || '',
                version: state.app.version || ''
            },
            ts: Date.now()
        };
    }

    function scheduleDraftSave() {
        if (state.view !== 'editor') return;
        if (!debouncedDraftSave) debouncedDraftSave = debounce(saveDraftAuto, 800);
        debouncedDraftSave();
    }

    function saveDraftAuto() {
        if (state.editorDisabled) return;
        var payload = buildDraftPayload();
        saveDraftLocal(state.app.id, state.app.slug, payload);
    }

    function restoreDraftIfNeeded() {
        var draft = loadDraftLocal(state.app.id, state.app.slug);
        if (!draft || typeof draft !== 'object') return;
        var current = buildDraftPayload();
        var draftCode = String(draft.code || '');
        var currCode = String(current.code || '');
        var draftManifest = JSON.stringify(draft.manifest || {});
        var currManifest = JSON.stringify(current.manifest || {});
        var draftMeta = JSON.stringify(draft.meta || {});
        var currMeta = JSON.stringify(current.meta || {});
        if (draftCode === currCode && draftManifest === currManifest && draftMeta === currMeta) {
            return;
        }
        var ok = confirm('Encontramos um rascunho local diferente. Deseja restaurar?');
        if (!ok) return;
        state.app.code = draft.code || '';
        if (draft.manifest && typeof draft.manifest === 'object') {
            state.app.manifest = draft.manifest;
        }
        if (draft.meta && typeof draft.meta === 'object') {
            state.app.title = sanitizeTitle(draft.meta.title || '');
            state.app.slug = sanitizeSlug(draft.meta.slug || '');
            state.app.version = String(draft.meta.version || '').trim();
        }
        var titleField = qs('#app-title');
        var slugField = qs('#app-slug');
        var descField = qs('#app-description');
        var versionField = qs('#app-version');
        var logoField = qs('#app-logo-file');
        var logoPreview = qs('#app-logo-preview');
        var colorField = qs('#app-color');
        var statusField = qs('#app-status');
        var priceField = qs('#app-price');
        var aspectField = qs('#app-aspect-ratio');
        var portraitField = qs('#app-supports-portrait');
        var landscapeField = qs('#app-supports-landscape');
        var exclusiveField = qs('#app-exclusive-entity');
        if (titleField) titleField.value = state.app.title;
        if (slugField) slugField.value = state.app.slug;
        if (descField) descField.value = state.app.description || '';
        if (versionField) versionField.value = state.app.version;
        if (logoPreview && state.app.logo) {
            logoPreview.src = state.app.logo;
            logoPreview.style.display = 'block';
        }
        if (colorField) colorField.value = state.app.color || '';
        if (statusField) statusField.value = String((state.app.status !== undefined && state.app.status !== null) ? state.app.status : 0);
        if (priceField) priceField.value = String(state.app.price || 0);
        if (aspectField) aspectField.value = state.app.aspectRatio || '';
        if (portraitField) portraitField.checked = state.app.supportsPortrait !== false;
        if (landscapeField) landscapeField.checked = state.app.supportsLandscape !== false;
        if (exclusiveField) exclusiveField.value = state.app.exclusiveEntityId || '';
        writeManifestToForm();
        setDirty(true);
        showNotice('Rascunho local restaurado.', 'info');
    }

    // === MANIFEST ===
    function validateForm() {
        var errors = [];
        var warnings = [];
        var title = sanitizeTitle(state.app.title);
        var slug = sanitizeSlug(state.app.slug);
        if (!title) errors.push('Nome é obrigatório.');
        if (!slug) errors.push('Slug é obrigatório (a-z, 0-9, hífen).');
        if (slug.length > 40) errors.push('Slug está muito longo.');
        readManifestFromForm();
        var norm = normalizeManifestFromState();
        if (!norm.ok) errors.push(norm.error);
        var code = cm ? cm.getValue() : (qs('#app-code') ? qs('#app-code').value : state.app.code);
        if (code) {
            var apiHints = /\/me\b|WorkzSDK\.api|apiClient/i.test(code);
            var allow = (state.app.manifest.capabilities && state.app.manifest.capabilities.api && Array.isArray(state.app.manifest.capabilities.api.allow))
                ? state.app.manifest.capabilities.api.allow
                : [];
            var hasGetMe = allow.some(function (entry) {
                return String(entry || '').toLowerCase().replace(/\s+/g, ' ').trim() === 'get /me';
            });
            if (apiHints && !hasGetMe) {
                warnings.push('Code parece chamar API (/me ou WorkzSDK.api); confira manifest.capabilities.api.allow (ex.: GET /me).');
            }
            if (/WorkzSDK\.emit\s*\(|events\.publish\s*\(/i.test(code)) {
                warnings.push('Code parece emitir eventos; confira manifest.capabilities.events.publish.');
            }
            if (/WorkzSDK\.on\s*\(|events\.on\s*\(/i.test(code)) {
                warnings.push('Code parece assinar eventos; confira manifest.capabilities.events.subscribe.');
            }
            if (/WorkzSDK\.storage|WorkzApp\.storage|storage\./i.test(code)) {
                warnings.push('Code parece usar storage; confira manifest.capabilities.storage (kv/docs/blobs).');
            }
            if (/docs\./i.test(code)) {
                warnings.push('Code parece usar docs; confira manifest.capabilities.storage.docs e docs.types.');
            }
            if (/blobs\./i.test(code)) {
                warnings.push('Code parece usar blobs; confira manifest.capabilities.storage.blobs.');
            }
        }
        updateCapabilitiesSummary();
        updateValidationErrors(errors, warnings);
        var box = qs('#validation-errors');
        if (box) {
            if (errors.length || warnings.length) box.classList.remove('hidden');
            else box.classList.add('hidden');
        }
        return errors.length === 0;
    }

    // MVP-Plus #9 — Manifest UI essencial (sem complicar)
    function updateCapabilitiesSummary() {
        var el = qs('#capabilities-summary');
        if (!el) return;
        var caps = state.app.manifest.capabilities || {};
        var apiCount = (caps.api && Array.isArray(caps.api.allow)) ? caps.api.allow.length : 0;
        var docsEnabled = caps.storage && caps.storage.docs && caps.storage.docs.enabled;
        var docsTypes = (caps.storage && caps.storage.docs && Array.isArray(caps.storage.docs.types)) ? caps.storage.docs.types.length : 0;
        var kv = caps.storage && caps.storage.kv ? 'KV' : '';
        var blobs = caps.storage && caps.storage.blobs ? 'Blobs' : '';
        var scope = (caps.storage && caps.storage.scope) ? caps.storage.scope : 'context';
        var pub = (caps.events && Array.isArray(caps.events.publish)) ? caps.events.publish.length : 0;
        var sub = (caps.events && Array.isArray(caps.events.subscribe)) ? caps.events.subscribe.length : 0;
        var proxyCount = (caps.proxy && Array.isArray(caps.proxy.sources)) ? caps.proxy.sources.length : 0;
        var mode = (state.app.manifest.contextRequirements && state.app.manifest.contextRequirements.mode) ? state.app.manifest.contextRequirements.mode : 'user';
        var parts = [
            'Contexto: ' + mode,
            'API: ' + apiCount,
            'Storage: ' + [kv, blobs].filter(Boolean).join(' ') + ' (' + scope + ')',
            'Docs: ' + (docsEnabled ? ('ativo, ' + docsTypes + ' tipos') : 'inativo'),
            'Eventos: ' + pub + '/' + sub,
            'Proxy: ' + proxyCount
        ];
        el.textContent = parts.join(' | ');
    }

    // === EVENTS ===
    function bindEditorForm() {
        var titleInput = qs('#app-title');
        var slugInput = qs('#app-slug');
        var descInput = qs('#app-description');
        var verInput = qs('#app-version');
        var logoInput = qs('#app-logo-file');
        var logoPreview = qs('#app-logo-preview');
        var colorInput = qs('#app-color');
        var statusInput = qs('#app-status');
        var priceInput = qs('#app-price');
        var aspectInput = qs('#app-aspect-ratio');
        var portraitInput = qs('#app-supports-portrait');
        var landscapeInput = qs('#app-supports-landscape');
        var exclusiveInput = qs('#app-exclusive-entity');
        var companySelect = qs('#company-select');

        if (titleInput && !titleInput.dataset.bound) {
            titleInput.addEventListener('input', function (e) {
                var value = sanitizeTitle(e.target.value);
                state.app.title = value;
                e.target.value = value;
                if (!state.slugTouched) {
                    var s = sanitizeSlug(value);
                    state.app.slug = s;
                    if (slugInput) slugInput.value = s;
                }
                setDirty(true);
                scheduleValidate();
                scheduleDraftSave();
            });
            titleInput.dataset.bound = '1';
        }

        if (slugInput && !slugInput.dataset.bound) {
            slugInput.addEventListener('input', function (e) {
                state.slugTouched = true;
                var value = sanitizeSlug(e.target.value);
                state.app.slug = value;
                e.target.value = value;
                setDirty(true);
                scheduleValidate();
                scheduleDraftSave();
            });
            slugInput.dataset.bound = '1';
        }

        if (descInput && !descInput.dataset.bound) {
            descInput.addEventListener('input', function (e) {
                state.app.description = String(e.target.value || '');
                setDirty(true);
                scheduleDraftSave();
            });
            descInput.dataset.bound = '1';
        }

        if (verInput && !verInput.dataset.bound) {
            verInput.addEventListener('input', function (e) {
                state.app.version = String(e.target.value || '').trim();
                setDirty(true);
                scheduleDraftSave();
            });
            verInput.dataset.bound = '1';
        }

        if (logoInput && !logoInput.dataset.bound) {
            logoInput.addEventListener('change', function (e) {
                var file = e.target.files && e.target.files[0];
                if (!file) return;
                var reader = new FileReader();
                reader.onload = function () {
                    var dataUrl = String(reader.result || '');
                    state.app.logo = dataUrl;
                    if (logoPreview) {
                        logoPreview.src = dataUrl;
                        logoPreview.style.display = 'block';
                    }
                    setDirty(true);
                    scheduleDraftSave();
                };
                reader.readAsDataURL(file);
            });
            logoInput.dataset.bound = '1';
        }

        if (colorInput && !colorInput.dataset.bound) {
            colorInput.addEventListener('input', function (e) {
                state.app.color = String(e.target.value || '').trim();
                setDirty(true);
                scheduleDraftSave();
            });
            colorInput.dataset.bound = '1';
        }

        if (statusInput && !statusInput.dataset.bound) {
            statusInput.addEventListener('change', function (e) {
                state.app.status = Number(e.target.value || 0);
                setDirty(true);
                scheduleDraftSave();
            });
            statusInput.dataset.bound = '1';
        }

        if (priceInput && !priceInput.dataset.bound) {
            priceInput.addEventListener('input', function (e) {
                state.app.price = toNumber(e.target.value, 0);
                setDirty(true);
                scheduleDraftSave();
            });
            priceInput.dataset.bound = '1';
        }

        if (aspectInput && !aspectInput.dataset.bound) {
            aspectInput.addEventListener('input', function (e) {
                state.app.aspectRatio = String(e.target.value || '').trim();
                setDirty(true);
                scheduleDraftSave();
            });
            aspectInput.dataset.bound = '1';
        }

        if (portraitInput && !portraitInput.dataset.bound) {
            portraitInput.addEventListener('change', function (e) {
                state.app.supportsPortrait = !!e.target.checked;
                setDirty(true);
                scheduleDraftSave();
            });
            portraitInput.dataset.bound = '1';
        }

        if (landscapeInput && !landscapeInput.dataset.bound) {
            landscapeInput.addEventListener('change', function (e) {
                state.app.supportsLandscape = !!e.target.checked;
                setDirty(true);
                scheduleDraftSave();
            });
            landscapeInput.dataset.bound = '1';
        }

        if (exclusiveInput && !exclusiveInput.dataset.bound) {
            exclusiveInput.addEventListener('change', function (e) {
                state.app.exclusiveEntityId = String(e.target.value || '').trim();
                setDirty(true);
                scheduleDraftSave();
            });
            exclusiveInput.dataset.bound = '1';
        }

        if (companySelect && !companySelect.dataset.bound) {
            companySelect.addEventListener('change', function (e) {
                state.app.companyId = e.target.value || '';
                setDirty(true);
                scheduleDraftSave();
            });
            companySelect.dataset.bound = '1';
        }

        qsa('#manifest-api-allow,#manifest-events-publish,#manifest-events-subscribe,#manifest-origins,#manifest-proxy-sources,#manifest-docs-types').forEach(function (node) {
            if (node.dataset.bound) return;
            node.addEventListener('input', function () {
                setDirty(true);
                scheduleValidate();
                scheduleDraftSave();
            });
            node.dataset.bound = '1';
        });

        qsa('#manifest-docs-enabled,#manifest-kv-enabled,#manifest-blobs-enabled,#manifest-storage-scope,#manifest-context-mode,#manifest-allow-context-switch').forEach(function (node) {
            if (node.dataset.bound) return;
            node.addEventListener('change', function () {
                setDirty(true);
                scheduleValidate();
                scheduleDraftSave();
            });
            node.dataset.bound = '1';
        });

        if (!document.body.dataset.shortcutsBound) {
            document.addEventListener('keydown', function (e) {
                var key = String(e.key || '').toLowerCase();
                var meta = e.ctrlKey || e.metaKey;
                if (!meta) return;
                if (key === 's') {
                    e.preventDefault();
                    if (state.view === 'editor') saveDraft();
                }
                if (key === 'enter') {
                    e.preventDefault();
                    if (state.view === 'editor') showPreview();
                }
            }, { passive: false });
            document.body.dataset.shortcutsBound = '1';
        }
    }

    // === PREVIEW ===
    function showPreview() {
        if (state.editorDisabled) {
        showNotice('Preview desabilitado porque o editor não carregou.', 'warning');
            return;
        }
        if (!validateForm()) {
        showNotice('Corrija os erros de validação antes do preview.', 'warning');
            return;
        }
        readManifestFromForm();
        var norm = normalizeManifestFromState();
        if (!norm.ok) {
            showNotice(norm.error, 'warning');
            return;
        }
        var code = cm ? cm.getValue() : (qs('#app-code') ? qs('#app-code').value : state.app.code);
        if (!code || !code.trim()) {
        showNotice('Adicione código antes do preview.', 'warning');
            return;
        }
        var html = buildPreviewHtml(code, norm.manifest);
        openPreviewModal(html);
    }

    // MVP-Plus #10 — Preview mais fiel + diagnóstico
    function openPreviewInNewTab() {
        if (state.editorDisabled) {
        showNotice('Preview desabilitado porque o editor não carregou.', 'warning');
            return;
        }
        if (!validateForm()) {
        showNotice('Corrija os erros de validação antes do preview.', 'warning');
            return;
        }
        readManifestFromForm();
        var norm = normalizeManifestFromState();
        if (!norm.ok) {
            showNotice(norm.error, 'warning');
            return;
        }
        var code = cm ? cm.getValue() : (qs('#app-code') ? qs('#app-code').value : state.app.code);
        if (!code || !code.trim()) {
        showNotice('Adicione código antes do preview.', 'warning');
            return;
        }
        var html = buildPreviewHtml(code, norm.manifest);
        var blob = new Blob([html], { type: 'text/html' });
        var url = URL.createObjectURL(blob);
        window.open(url, '_blank');
        setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
    }

    function openPreviewModal(html) {
        var modal = qs('#preview-modal');
        if (!modal) {
            modal = el('div', { id: 'preview-modal', className: 'fixed inset-0 z-50 hidden' });
            var overlay = el('div', { className: 'absolute inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4' });
            var panel = el('div', { className: 'w-full max-w-6xl bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]' });
            var header = el('div', { className: 'flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-200' });
            var title = el('div', { className: 'text-base font-semibold text-slate-900', text: 'Pré-visualização' });
            var close = el('button', { className: twBtn('secondary', 'sm', true), text: 'Fechar' });
            close.addEventListener('click', function () { modal.classList.add('hidden'); });
            header.appendChild(title);
            header.appendChild(close);
            var body = el('div', { className: 'flex-1 min-h-[60vh] bg-white' });
            var iframe = el('iframe', { id: 'preview-iframe', className: 'w-full h-full', attrs: { sandbox: 'allow-scripts allow-same-origin' } });
            body.appendChild(iframe);
            var logs = el('div', { id: 'preview-log-panel', className: 'border-t border-slate-200 px-4 py-2 text-xs text-slate-500', text: 'Logs:' });
            var logBody = el('pre', { id: 'preview-log-body', className: 'm-0 whitespace-pre-wrap' });
            logs.appendChild(logBody);
            panel.appendChild(header);
            panel.appendChild(body);
            panel.appendChild(logs);
            overlay.appendChild(panel);
            modal.appendChild(overlay);
            document.body.appendChild(modal);
        }
        var iframeEl = qs('#preview-iframe');
        var logBodyEl = qs('#preview-log-body');
        if (logBodyEl) logBodyEl.textContent = '';
        iframeEl.srcdoc = html;
        modal.classList.remove('hidden');

        if (!window.__previewLogListener) {
            window.addEventListener('message', function (event) {
                if (event.origin !== window.location.origin) return;
                var data = event.data || {};
                var target = qs('#preview-log-body');
                if (!target) return;
                if (data.source === 'appstudio-preview') {
                    var line = '[' + data.type + '] ' + data.message + '\\n';
                    target.textContent += line;
                    return;
                }
                if (data.source === 'sdk') {
                    var lineSdk = '[sdk:' + (data.type || 'log') + '] ' + data.message + '\\n';
                    target.textContent += lineSdk;
                    return;
                }
                // WorkzApp Pro integration
                if (data.source === 'workzapp') {
                    var payload = data.payload || {};
                    var msg = '';
                    if (typeof payload === 'string') msg = payload;
                    else if (payload && typeof payload.message === 'string') msg = payload.message;
                    else if (payload && typeof payload.name === 'string') msg = 'event=' + payload.name;
                    else msg = JSON.stringify(payload || {}).slice(0, 300);
                    var line2 = '[workzapp:' + (data.type || 'log') + '] ' + msg + '\\n';
                    target.textContent += line2;
                }
            });
            window.__previewLogListener = true;
        }
    }

    // === EXPORT ===
    function exportApp() {
        if (!validateForm()) {
        showNotice('Corrija os erros de validação antes de exportar.', 'warning');
            return;
        }
        readManifestFromForm();
        var norm = normalizeManifestFromState();
        if (!norm.ok) {
            showNotice(norm.error, 'warning');
            return;
        }
        var code = cm ? cm.getValue() : (qs('#app-code') ? qs('#app-code').value : state.app.code);
        var payload = {
            manifest: norm.manifest,
            code: code || '',
            metadata: {
                title: state.app.title,
                slug: state.app.slug,
                version: state.app.version
            }
        };
        var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = el('a', { attrs: { href: url, download: (state.app.slug || 'app') + '.json' } });
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    // MVP-Plus #6 — Import JSON
    function importJsonFile(file) {
        var reader = new FileReader();
        reader.onload = function () {
            var raw = String(reader.result || '');
            var data = safeJsonParse(raw, null);
            if (!data || typeof data !== 'object') {
                showNotice('Falha ao importar: JSON inválido.', 'warning');
                return;
            }
            if (data.metadata && typeof data.metadata === 'object') {
                state.app.title = sanitizeTitle(data.metadata.title || state.app.title);
                state.app.slug = sanitizeSlug(data.metadata.slug || state.app.slug);
                state.app.version = String(data.metadata.version || state.app.version || '').trim();
            }
            if (data.manifest && typeof data.manifest === 'object') {
                state.app.manifest = data.manifest;
            }
            if (typeof data.code === 'string') {
                state.app.code = data.code;
            }
            var titleField = qs('#app-title');
            var slugField = qs('#app-slug');
            var versionField = qs('#app-version');
            var codeField = qs('#app-code');
            if (titleField) titleField.value = state.app.title;
            if (slugField) slugField.value = state.app.slug;
            if (versionField) versionField.value = state.app.version;
            writeManifestToForm();
            if (cm) {
                cm.setValue(state.app.code || '');
            } else if (codeField) {
                codeField.value = state.app.code || '';
            }
            setDirty(true);
            scheduleValidate();
            showNotice('Import realizado. Revise e salve.', 'info');
        };
        reader.onerror = function () {
            showNotice('Falha ao importar: erro ao ler arquivo.', 'warning');
        };
        reader.readAsText(file);
    }

    // === DATA FLOW ===
    async function loadCompanies() {
        state.loadingCompanies = true;
        render();
        try {
            var response = await apiCall('get', '/me');
            var companies = null;
            if (response && response.companies) companies = response.companies;
            if (response && response.data && response.data.companies) companies = response.data.companies;
            if (companies && Array.isArray(companies)) {
                state.companies = companies.map(function (c) {
                    return {
                        id: c.id,
                        name: c.name || c.tt || 'Negócio',
                        nv: c.nv,
                        cnpj: c.cnpj || c.national_id || ''
                    };
                });
            }
        } catch (_) {
            state.companies = [];
        }
        state.loadingCompanies = false;
        render();
    }

    async function loadApps() {
        state.loadingApps = true;
        render();
        try {
            var response = await apiCall('get', '/apps/my-apps');
            if (response && response.success === false) {
                var msg = response.message || 'Falha ao carregar apps.';
                var st = response.__httpStatus || response.status || 'n/a';
                var url = response.__url || 'n/a';
                showNotice(msg + ' (status ' + st + ', url ' + url + ')', 'warning');
                state.apps = [];
                state.loadingApps = false;
                render();
                return;
            }
            state.apps = normalizeAppsResponse(response);
        } catch (e) {
            state.apps = [];
            showNotice('Falha ao carregar apps.', 'warning');
        }
        state.loadingApps = false;
        render();
    }

    function resetAppState() {
        state.app = {
            id: null,
            title: '',
            slug: '',
            description: '',
            version: '1.0.0',
            companyId: '',
            accessLevel: 1,
            logo: '',
            color: '',
            status: 1,
            price: 0,
            aspectRatio: '',
            supportsPortrait: true,
            supportsLandscape: true,
            exclusiveEntityId: '',
            code: DEFAULT_STARTER_CODE,
            manifest: getDefaultAppManifest()
        };
        state.slugTouched = false;
        state.loadingEditor = false;
        setDirty(false);
    }

    function startNewApp() {
        resetAppState();
        state.view = 'editor';
        render();
    }

    // MVP-Plus #2 — Edit confiável (sem “reset falso”)
    async function editApp(appId) {
        if (!appId) {
            showNotice('Não foi possível editar: id do app inválido.', 'warning');
            return;
        }
        state.view = 'editor';
        state.loadingEditor = true;
        render();
        showNotice('Carregando app...', 'info');
        try {
            var response = await apiCall('get', '/apps/' + appId);
            if (response && response.success === false) {
                var msg = response.message || 'Falha ao carregar app.';
                var st = response.__httpStatus || response.status || 'n/a';
                var url = response.__url || 'n/a';
                showNotice(msg + ' (status ' + st + ', url ' + url + ')', 'warning');
                state.loadingEditor = false;
                render();
                return;
            }
            var app = (response && response.data) ? response.data : response;
            if (!app || typeof app !== 'object') {
                showNotice('Falha ao carregar app (resposta inválida).', 'warning');
                state.loadingEditor = false;
                render();
                return;
            }
            destroyEditor();
            resetAppState();
            state.app.id = appId;
            state.app.title = sanitizeTitle(app.tt || app.title || '');
            state.app.slug = sanitizeSlug(app.slug || '');
            state.app.description = String(app.ds || app.description || '');
            state.app.version = app.version || '1.0.0';
            state.app.companyId = app.publisher || app.company_id || '';
            state.app.logo = app.im || app.logo || '';
            state.app.color = app.color || '';
            state.app.status = Number((app.st !== undefined && app.st !== null) ? app.st : (app.status !== undefined && app.status !== null ? app.status : 0));
            state.app.price = toNumber(app.vl || app.price || 0, 0);
            state.app.aspectRatio = app.aspect_ratio || app.aspectRatio || '';
            state.app.supportsPortrait = (app.supports_portrait !== undefined) ? !!app.supports_portrait : (app.supportsPortrait !== undefined ? !!app.supportsPortrait : true);
            state.app.supportsLandscape = (app.supports_landscape !== undefined) ? !!app.supports_landscape : (app.supportsLandscape !== undefined ? !!app.supportsLandscape : true);
            state.app.exclusiveEntityId = app.exclusive_to_entity_id || app.exclusiveEntityId || '';
            state.app.code = app.js_code || app.source_code || app.code || '';
            var manifestRaw = app.manifest || app.manifest_json;
            if (manifestRaw && typeof manifestRaw === 'string') {
                state.app.manifest = safeJsonParse(manifestRaw, state.app.manifest);
            } else if (manifestRaw && typeof manifestRaw === 'object') {
                state.app.manifest = manifestRaw;
            }
            // Migrate legacy manifest fields in-memory to v2 schema
            if (state.app.manifest.contextMode && !state.app.manifest.contextRequirements) {
                state.app.manifest.contextRequirements = { mode: String(state.app.manifest.contextMode).toLowerCase() };
            }
            if (typeof state.app.manifest.allowContextSwitch === 'boolean') {
                state.app.manifest.contextRequirements = state.app.manifest.contextRequirements || {};
                state.app.manifest.contextRequirements.allowContextSwitch = state.app.manifest.allowContextSwitch;
            }
            state.app.manifest.runtime = state.app.manifest.runtime || 'js';
            if (!state.app.manifest.capabilities) state.app.manifest.capabilities = {};
            if (!state.app.manifest.capabilities.storage) state.app.manifest.capabilities.storage = {};
            if (state.app.manifest.capabilities.storage.scope === 'app') {
                state.app.manifest.capabilities.storage.scope = 'context';
            }
            if (!state.app.manifest.sandbox || !state.app.manifest.sandbox.postMessage) {
                state.app.manifest.sandbox = { postMessage: { allowedOrigins: [] } };
            }
            normalizeApiAllowState();
            state.loadingEditor = false;
            setDirty(false);
            showNotice('', '');
        state.view = 'editor';
        render();
    } catch (e) {
        state.loadingEditor = false;
        render();
        showNotice('Falha ao carregar app.', 'warning');
    }
    }

    async function duplicateApp(app) {
        var baseId = getAppId(app);
        if (!baseId) {
            showNotice('Não foi possível duplicar: id do app inválido.', 'warning');
            return;
        }
        var baseTitle = String(app.tt || app.title || 'App');
        var baseSlug = sanitizeSlug(app.slug || baseTitle || 'app');
        var proposed = baseSlug + '-copy';
        var newSlug = getUniqueSlug(proposed);
            var ok = confirm('Duplicar app "' + baseTitle + '" como "' + newSlug + '"?');
        if (!ok) return;

        var manifestRaw = app.manifest || app.manifest_json || null;
        var manifestObj = null;
        if (manifestRaw && typeof manifestRaw === 'object') manifestObj = manifestRaw;
        if (manifestRaw && typeof manifestRaw === 'string') manifestObj = safeJsonParse(manifestRaw, null);
        if (!manifestObj) manifestObj = JSON.parse(JSON.stringify(state.app.manifest));
        var payload = {
            title: baseTitle + ' (Copy)',
            slug: newSlug,
            description: app.ds || app.description || '',
            version: app.version || '1.0.0',
            app_type: 'javascript',
            js_code: app.js_code || app.source_code || app.code || '',
            manifest: JSON.stringify(manifestObj),
            manifest_json: JSON.stringify(manifestObj),
            im: app.im || app.logo || '',
            color: app.color || '',
            st: Number((app.st !== undefined && app.st !== null) ? app.st : (app.status !== undefined && app.status !== null ? app.status : 0)),
            vl: toNumber(app.vl || app.price || 0, 0),
            aspect_ratio: app.aspect_ratio || app.aspectRatio || '',
            supports_portrait: (app.supports_portrait !== undefined) ? (app.supports_portrait ? 1 : 0) : (app.supportsPortrait ? 1 : 0),
            supports_landscape: (app.supports_landscape !== undefined) ? (app.supports_landscape ? 1 : 0) : (app.supportsLandscape ? 1 : 0),
            exclusive_to_entity_id: app.exclusive_to_entity_id || app.exclusiveEntityId || null
        };
        if (app.publisher || app.company_id || app.exclusive_to_entity_id) {
            payload.company_id = app.publisher || app.company_id || app.exclusive_to_entity_id;
            payload.publisher = payload.company_id;
        }
        try {
            var resp = await apiCall('post', '/apps/create', payload);
            if (resp && resp.success) {
                showNotice('App duplicado com sucesso.', 'success');
                await loadApps();
            } else {
                var msg = (resp && resp.message ? resp.message : 'unknown error');
                var st = (resp && resp.__httpStatus) ? resp.__httpStatus : (resp && resp.status ? resp.status : 'n/a');
                var url = (resp && resp.__url) ? resp.__url : 'n/a';
                showNotice('Falha ao duplicar: ' + msg + ' (status ' + st + ', url ' + url + ')', 'warning');
            }
        } catch (e) {
            showNotice('Falha ao duplicar app.', 'warning');
        }
    }

    async function saveApp() {
        if (!validateForm()) {
            showNotice('Corrija os erros de validação antes de salvar.', 'warning');
            return;
        }
        readManifestFromForm();
        var norm = normalizeManifestFromState();
        if (!norm.ok) {
            showNotice(norm.error, 'warning');
            return;
        }
        var code = cm ? cm.getValue() : (qs('#app-code') ? qs('#app-code').value : state.app.code);
        var payload = {
            title: state.app.title,
            slug: state.app.slug,
            description: state.app.description,
            version: state.app.version,
            app_type: 'javascript',
            js_code: code || '',
            manifest: JSON.stringify(norm.manifest),
            manifest_json: JSON.stringify(norm.manifest),
            im: state.app.logo || '',
            color: state.app.color || '',
            st: Number((state.app.status !== undefined && state.app.status !== null) ? state.app.status : 0),
            vl: toNumber(state.app.price || 0, 0),
            aspect_ratio: state.app.aspectRatio || '',
            supports_portrait: state.app.supportsPortrait ? 1 : 0,
            supports_landscape: state.app.supportsLandscape ? 1 : 0,
            exclusive_to_entity_id: state.app.exclusiveEntityId ? parseInt(state.app.exclusiveEntityId, 10) : null
        };
        if (state.app.companyId) {
            payload.company_id = parseInt(state.app.companyId, 10);
            payload.publisher = parseInt(state.app.companyId, 10);
        }

        try {
            var resp;
            if (state.app.id) {
                resp = await apiCall('post', '/apps/update/' + state.app.id, payload);
            } else {
                resp = await apiCall('post', '/apps/create', payload);
            }
            if (resp && resp.success) {
                var newId = extractAppIdFromSaveResponse(resp);
                if (newId) state.app.id = newId;
                setDirty(false);
                showNotice('App salvo.', 'success');
                await loadApps();
            } else {
                var msg = (resp && resp.message ? resp.message : 'unknown error');
                var st = (resp && resp.__httpStatus) ? resp.__httpStatus : (resp && resp.status ? resp.status : 'n/a');
                var url = (resp && resp.__url) ? resp.__url : 'n/a';
                var details = '';
                if (resp && resp.errors && Array.isArray(resp.errors)) {
                    details = ' - ' + resp.errors.join(', ');
                } else if (resp && resp.validation && typeof resp.validation === 'object') {
                    try { details = ' - ' + Object.keys(resp.validation).join(', '); } catch (_) {}
                }
                showNotice('Falha ao salvar: ' + msg + details + ' (status ' + st + ', url ' + url + ')', 'warning');
            }
        } catch (e) {
            showNotice('Falha ao salvar: erro de rede.', 'warning');
        }
    }

    // MVP-Plus #8 — Delete App (com segurança)
    async function deleteApp() {
        if (!state.app.id) {
            showNotice('Não foi possível excluir: id do app inválido.', 'warning');
            return;
        }
        var name = state.app.title || '';
        var slug = state.app.slug || '';
        var ok = confirm('Excluir app "' + name + '" (' + slug + ')? Esta ação não pode ser desfeita.');
        if (!ok) return;
        try {
            var resp = await apiCall('post', '/apps/delete/' + state.app.id);
            if (!resp || resp.success === false) {
                resp = await apiCall('delete', '/apps/' + state.app.id);
            }
            if (resp && resp.success) {
                showNotice('App excluído.', 'success');
                await loadApps();
                state.view = 'list';
                render();
            } else {
                var msg = (resp && resp.message ? resp.message : 'unknown error');
                var st = (resp && resp.__httpStatus) ? resp.__httpStatus : (resp && resp.status ? resp.status : 'n/a');
                var url = (resp && resp.__url) ? resp.__url : 'n/a';
                showNotice('Falha ao excluir: ' + msg + ' (status ' + st + ', url ' + url + ')', 'warning');
            }
        } catch (e) {
            showNotice('Falha ao excluir app.', 'warning');
        }
    }

    function maybeBackToList() {
        if (state.dirty) {
            var ok = confirm('Você tem alterações não salvas. Descartar e voltar?');
            if (!ok) return;
        }
        destroyEditor();
        state.view = 'list';
        render();
    }

    window.initApp = function initApp() {
        if (window.StoreApp && typeof window.StoreApp.init === 'function') {
            return window.StoreApp.init();
        }
    };

    // === INIT ===
    async function init() {
        await domReady();
        await initStudioSdk();
        try {
            await loadJS(TAILWIND_JS);
        } catch (_) {}

        try {
            await loadCSS(CODEMIRROR_CSS);
            await loadCSS(CODEMIRROR_THEME);
            await loadCSS(CODEMIRROR_DIALOG_CSS);
            await loadCSS(CODEMIRROR_FULLSCREEN_CSS);
            await loadJS(CODEMIRROR_JS);
            await loadJS(CODEMIRROR_MODE_JS);
            await loadJS(CODEMIRROR_DIALOG_JS);
            await loadJS(CODEMIRROR_SEARCHCURSOR_JS);
            await loadJS(CODEMIRROR_SEARCH_JS);
            await loadJS(CODEMIRROR_MATCHBRACKETS_JS);
            await loadJS(CODEMIRROR_CLOSEBRACKETS_JS);
            await loadJS(CODEMIRROR_ACTIVE_LINE_JS);
            await loadJS(CODEMIRROR_COMMENT_JS);
            await loadJS(CODEMIRROR_FULLSCREEN_JS);
        } catch (e) {
            state.editorDisabled = true;
            state.editorError = 'Falha ao carregar o CodeMirror.';
        }

        await loadCompanies();
        await loadApps();
        render();
    }

    window.StoreApp = {
        init: init,
        state: state,
        version: VERSION
    };

    if (typeof window.WorkzSDKRunner === 'undefined') {
        init();
    }
})();

/*
MVP-Plus Manual QA
1) Load list: list renders; cards with missing id show warning + disabled Edit/Duplicate.
2) Edit flow: click Edit shows loading notice, then editor opens only after data arrives; failure shows status/url.
3) CSRF: if meta csrf-token exists, save uses X-CSRF-TOKEN header and same-origin credentials.
4) Save: save success shows “App saved.” and keeps editor; failure shows status + url.
5) Drafts: type code, wait autosave, reload page -> restore prompt; Discard Draft removes it.
6) Import JSON: import exported JSON populates title/slug/version/manifest/code and marks dirty.
7) Duplicate: duplicate from list creates copy with -copy suffix and refreshes list.
8) Delete: delete from editor confirms and removes app; list refreshes after delete.
9) Manifest UI: change context mode/allow switch; capabilities summary updates; docs types empty blocks.
10) Preview: open preview modal/new tab, SDK init errors show clearly; iframe logs appear in panel.
*/
