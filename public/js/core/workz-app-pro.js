(function (w) {
  "use strict";

  // 1) Garantia de objeto global
  const WorkzApp = (w.WorkzApp = w.WorkzApp || {});

  // 2) Helpers de log (para aparecer no preview panel)
  function post(type, payload) {
    try {
      if (w.parent && w.parent !== w) {
        w.parent.postMessage({ source: "workzapp", type, payload }, "*");
      }
    } catch (_) {}
  }

  function log(msg, extra) {
    try {
      console.log("[WorkzAppPro]", msg, extra || "");
    } catch (_) {}
    post("log", { message: String(msg), extra: extra || null });
  }

  function warn(msg, extra) {
    try {
      console.warn("[WorkzAppPro]", msg, extra || "");
    } catch (_) {}
    post("warn", { message: String(msg), extra: extra || null });
  }

  function err(msg, extra) {
    try {
      console.error("[WorkzAppPro]", msg, extra || "");
    } catch (_) {}
    post("error", { message: String(msg), extra: extra || null });
  }

  // 3) Estado interno
  WorkzApp.state = WorkzApp.state || {
    inited: false,
    sdk: null,
    appConfig: null,
    preview: false,
    debug: false,
  };

  // 4) Init principal
  WorkzApp.init = async function init(opts) {
    opts = opts || {};
    const sdk = opts.sdk;
    const appConfig = opts.appConfig;

    if (!sdk) throw new Error("WorkzApp.init: opts.sdk é obrigatório (WorkzSDK).");
    if (!appConfig) throw new Error("WorkzApp.init: opts.appConfig é obrigatório.");
    if (!appConfig.manifest) throw new Error("WorkzApp.init: appConfig.manifest ausente.");

    WorkzApp.state.sdk = sdk;
    WorkzApp.state.appConfig = appConfig;
    WorkzApp.state.preview = !!opts.preview;
    WorkzApp.state.debug = !!opts.debug;
    WorkzApp.state.inited = true;

    // Expor atalhos úteis
    WorkzApp.sdk = sdk;
    WorkzApp.manifest = appConfig.manifest;

    log("loaded + init OK", {
      preview: WorkzApp.state.preview,
      debug: WorkzApp.state.debug,
      sdkKeys: Object.keys(sdk || {}),
    });

    // Sinalizar que o WorkzApp está pronto
    try {
      const ev = { name: "workzapp:ready", ts: Date.now() };
      w.dispatchEvent(new CustomEvent("workzapp:ready", { detail: ev }));
      post("event", { name: "workzapp:ready" });
    } catch (_) {}

    return true;
  };

  // 5) API mínima (só pra teste / base)
  WorkzApp.api = WorkzApp.api || {};
  WorkzApp.api.unwrap = function unwrap(res) {
    if (res && typeof res === "object" && res.data && typeof res.data === "object") return res.data;
    return res;
  };
  WorkzApp.api.getMe = async function getMe() {
    if (!WorkzApp.state.inited) throw new Error("WorkzApp.api.getMe: WorkzApp não inicializado.");
    const raw = await WorkzApp.api.request({ method: "GET", url: "/me" });
    const data = WorkzApp.api.unwrap(raw);
    const user = data && typeof data.user === "object" ? data.user : data;
    const userId =
      (user && user.id !== undefined && user.id !== null) ? user.id :
      (user && user.us !== undefined && user.us !== null) ? user.us :
      (user && user.user_id !== undefined && user.user_id !== null) ? user.user_id :
      (user && user.uid !== undefined && user.uid !== null) ? user.uid : null;
    if (userId !== null) {
      WorkzApp.state.previewUserId = userId;
    }
    if (WorkzApp.state.preview || WorkzApp.state.debug) {
      WorkzApp.log("DEBUG /me raw keys", Object.keys(raw || {}));
      WorkzApp.log("DEBUG /me data keys", Object.keys(data || {}));
    }
    return { raw, data, user };
  };

  // 6) Marca de carregamento (pra você bater o olho no console)
  log("script carregado (global) — window.WorkzApp disponível.");

    WorkzApp.log = function (msg, extra) { log(msg, extra); };
    WorkzApp.warn = function (msg, extra) { warn(msg, extra); };
    WorkzApp.error = function (msg, extra) { err(msg, extra); };

  WorkzApp.api.request = async function ({ method = "GET", url = "/", path, body, data } = {}) {
        if (!WorkzApp.state.inited) throw new Error("WorkzApp.api.request: WorkzApp não inicializado.");
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        const c = sdk && sdk.apiClient;
        if (!c) throw new Error("WorkzApp.api.request: sdk.apiClient indisponível.");

        const m = String(method || "GET").toUpperCase();
        let p = url || path || "/";
        p = String(p || "/");
        if (!p.startsWith("/")) p = "/" + p;
        const payload = (body !== undefined) ? body : (data !== undefined ? data : null);

        if (sdk && sdk.api) {
            const apiKey = m.toLowerCase();
            const apiFn = sdk.api[apiKey];
            if (typeof apiFn === "function") {
                if (m === "GET" || m === "DELETE") return apiFn(p);
                return apiFn(p, payload || {});
            }
        }
        if (typeof c.request === "function") {
            return c.request(m, p, payload);
        }
        if (m === "GET" && typeof c.get === "function") return c.get(p);
        if (m === "DELETE" && typeof c.delete === "function") return c.delete(p);
        if (m === "PUT" && typeof c.put === "function") return c.put(p, payload || {});
        if (m === "POST" && typeof c.post === "function") return c.post(p, payload || {});
        throw new Error("WorkzApp.api.request: métodos request/get/post não encontrados em sdk.apiClient.");
    };

    WorkzApp.ui = WorkzApp.ui || {};
    WorkzApp.ui.mount = function (html) {
        const root = document.getElementById("app");
        if (!root) return;
        root.innerHTML = html || "";
    };

    WorkzApp.events = WorkzApp.events || {};

    function ensurePreviewContext() {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (!WorkzApp.state.preview) return false;
        if (!sdk || typeof sdk.setContext !== "function") return false;
        if (sdk.contextAllowed !== false) return true;
        const reason = sdk.contextDenyReason || {};
        if (reason && typeof reason === "object" && reason.code && reason.code !== "context_not_allowed") return false;
        const mode = (WorkzApp.manifest && WorkzApp.manifest.contextRequirements && WorkzApp.manifest.contextRequirements.mode) || "user";
        if (String(mode).toLowerCase() !== "user") return false;
        const userId = WorkzApp.state.previewUserId;
        if (!userId) return false;
        return sdk.setContext({ mode: "user", id: userId });
    }

    WorkzApp.events.publish = function (name, payload) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && typeof sdk.emit === "function") {
            return sdk.emit(name, payload);
        }
        WorkzApp.warn("WorkzSDK indisponível; usando fallback local para events.publish.");
        post("event", { name, payload: payload || null });
        try {
            window.dispatchEvent(new CustomEvent(name, { detail: payload || null }));
        } catch (_) {}
        return true;
    };

    WorkzApp.events.on = function (name, handler) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && typeof sdk.on === "function") {
            return sdk.on(name, handler);
        }
        WorkzApp.warn("WorkzSDK indisponível; usando fallback local para events.on.");
        WorkzApp.events._localHandlers = WorkzApp.events._localHandlers || {};
        WorkzApp.events._localHandlers[name] = WorkzApp.events._localHandlers[name] || new Map();
        var wrapped = function (ev) {
            try { handler(ev.detail); } catch (e) { err("handler error", e); }
        };
        WorkzApp.events._localHandlers[name].set(handler, wrapped);
        window.addEventListener(name, wrapped);
    };

    WorkzApp.events.off = function (name, handler) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && typeof sdk.off === "function") {
            return sdk.off(name, handler);
        }
        WorkzApp.warn("WorkzSDK indisponível; usando fallback local para events.off.");
        if (WorkzApp.events._localHandlers && WorkzApp.events._localHandlers[name]) {
            var wrapped = WorkzApp.events._localHandlers[name].get(handler);
            if (wrapped) {
                window.removeEventListener(name, wrapped);
                WorkzApp.events._localHandlers[name].delete(handler);
                return;
            }
        }
        window.removeEventListener(name, handler);
    };

    // WorkzApp Pro integration: thin wrappers over SDK context/user/storage
    WorkzApp.context = WorkzApp.context || {};
    WorkzApp.context.get = function () {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && typeof sdk.getContext === "function") return sdk.getContext();
        throw new Error("WorkzApp.context.get indisponível (SDK).");
    };

    WorkzApp.user = WorkzApp.user || {};
    WorkzApp.user.get = function () {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && typeof sdk.getUser === "function") return sdk.getUser();
        throw new Error("WorkzApp.user.get indisponível (SDK).");
    };

    WorkzApp.storage = WorkzApp.storage || {};
    WorkzApp.storage.kv = WorkzApp.storage.kv || {};
    WorkzApp.storage.kv.get = async function (key) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.kv && typeof sdk.storage.kv.get === "function") {
            return sdk.storage.kv.get(key);
        }
        throw new Error("WorkzApp.storage.kv.get indisponível (SDK).");
    };
    WorkzApp.storage.kv.set = async function (data) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.kv && typeof sdk.storage.kv.set === "function") {
            return sdk.storage.kv.set(data);
        }
        throw new Error("WorkzApp.storage.kv.set indisponível (SDK).");
    };
    WorkzApp.storage.kv.remove = async function (key) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.kv && typeof sdk.storage.kv.delete === "function") {
            return sdk.storage.kv.delete(key);
        }
        throw new Error("WorkzApp.storage.kv.remove indisponível (SDK).");
    };

    WorkzApp.storage.docs = WorkzApp.storage.docs || {};
    WorkzApp.storage.docs.list = async function () {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.docs && typeof sdk.storage.docs.list === "function") {
            return sdk.storage.docs.list();
        }
        throw new Error("WorkzApp.storage.docs.list indisponível (SDK).");
    };
    WorkzApp.storage.docs.get = async function (id) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.docs && typeof sdk.storage.docs.get === "function") {
            return sdk.storage.docs.get(id);
        }
        throw new Error("WorkzApp.storage.docs.get indisponível (SDK).");
    };
    WorkzApp.storage.docs.set = async function (id, document) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.docs && typeof sdk.storage.docs.save === "function") {
            return sdk.storage.docs.save(id, document);
        }
        throw new Error("WorkzApp.storage.docs.set indisponível (SDK).");
    };
    WorkzApp.storage.docs.delete = async function (id) {
        const sdk = WorkzApp.sdk || w.WorkzSDK;
        if (sdk && sdk.storage && sdk.storage.docs && typeof sdk.storage.docs.delete === "function") {
            return sdk.storage.docs.delete(id);
        }
        throw new Error("WorkzApp.storage.docs.delete indisponível (SDK).");
    };


})(window);
