(function () {
  'use strict';

  var WorkzHostBridge = {};
  var frameRegistry = new Map();
  var enabled = false;
  var debug = !!(window && window.WorkzHostBridgeDebug);
  var resolveContext = null;
  var resolveUser = null;
  var issueToken = null;
  var resolveAppId = null;

  function normalizeList(input) {
    if (!input) return [];
    if (Array.isArray(input)) return input.filter(Boolean);
    if (typeof input === 'string') return input.split(/\s*,\s*/).filter(Boolean);
    return [];
  }

  function normalizeOrigin(value) {
    if (!value || typeof value !== 'string') return '';
    var raw = value.trim();
    if (!raw) return '';
    if (raw === '*') return '*';
    try {
      return new URL(raw).origin.toLowerCase();
    } catch (_) {
      try {
        if (raw.startsWith('//')) {
          return new URL('http:' + raw).origin.toLowerCase();
        }
        if (!/^https?:\/\//i.test(raw)) {
          return new URL('http://' + raw).origin.toLowerCase();
        }
      } catch (_) {}
    }
    return raw.toLowerCase();
  }

  function normalizeOrigins(input) {
    return normalizeList(input).map(normalizeOrigin).filter(Boolean);
  }

  function parseEventList(input) {
    if (!input) return [];
    if (Array.isArray(input)) return input.filter(Boolean);
    if (typeof input === 'string') {
      return input.split(/\s*,\s*/).filter(Boolean);
    }
    return [];
  }

  function parseAppSlugFromUrl(src) {
    try {
      var url = new URL(src, window.location.origin);
      var match = url.pathname.match(/\/app\/(?:run|public|shell)\/([a-z0-9-]+)/i);
      if (match && match[1]) return match[1];
      match = url.pathname.match(/\/api\/app\/(?:run|public|shell)\/([a-z0-9-]+)/i);
      if (match && match[1]) return match[1];
    } catch (_) {}
    return '';
  }

  function isOriginAllowed(meta, origin) {
    var allowed = normalizeOrigins(meta.allowedOrigins);
    var normalizedOrigin = normalizeOrigin(origin);
    if (!allowed.length) {
      var isLocalDev = /^https?:\/\/(localhost|127\.|.*\.localhost)(:\d+)?$/i.test(normalizedOrigin);
      return isLocalDev;
    }
    if (allowed.indexOf('*') >= 0) return true;
    return allowed.indexOf(normalizedOrigin) >= 0;
  }

  function isEventAllowed(meta, type, listName) {
    if (!meta || !type) return false;
    var list = meta[listName] || [];
    var allowAll = !!meta.allowAllEvents;
    var isAppScoped = String(type).indexOf('app:') === 0;
    var isListed = list.indexOf(type) >= 0;
    if (!isAppScoped && !isListed) return false;
    if (allowAll && isAppScoped) return true;
    return isListed;
  }

  function getTargetOrigin(meta, fallbackOrigin) {
    var allowed = normalizeOrigins(meta.allowedOrigins);
    if (meta.targetOrigin) return meta.targetOrigin;
    if (allowed.indexOf('*') >= 0) return '*';
    if (allowed.length === 1) return allowed[0];
    return fallbackOrigin || window.location.origin || '*';
  }

  function extractMetaFromIframe(iframe) {
    if (!iframe) return null;
    var dataset = iframe.dataset || {};
    var meta = {
      iframe: iframe,
      window: iframe.contentWindow || null,
      appId: dataset.workzAppId ? parseInt(dataset.workzAppId, 10) : null,
      appSlug: dataset.workzAppSlug || parseAppSlugFromUrl(iframe.src || ''),
      allowedOrigins: [],
      eventsPublish: [],
      eventsSubscribe: [],
      allowAllEvents: false,
      targetOrigin: dataset.workzTargetOrigin || ''
    };
    if (dataset.workzAllowedOrigins) {
      meta.allowedOrigins = normalizeList(dataset.workzAllowedOrigins);
    }
    if (dataset.workzEventsPublish) {
      meta.eventsPublish = parseEventList(dataset.workzEventsPublish);
    }
    if (dataset.workzEventsSubscribe) {
      meta.eventsSubscribe = parseEventList(dataset.workzEventsSubscribe);
    }
    if (dataset.workzEventsAllowAll === '1') {
      meta.allowAllEvents = true;
    }
    return meta;
  }

  function findIframeByWindow(win) {
    if (!win) return null;
    if (frameRegistry.has(win)) return frameRegistry.get(win);
    var iframes = document.querySelectorAll('iframe');
    for (var i = 0; i < iframes.length; i += 1) {
      var iframe = iframes[i];
      if (iframe.contentWindow === win) {
        var meta = extractMetaFromIframe(iframe);
        if (meta && meta.window) {
          frameRegistry.set(meta.window, meta);
          return meta;
        }
      }
    }
    return null;
  }

  async function handleInit(ev) {
    var meta = findIframeByWindow(ev.source);
    if (!meta || !meta.window) return;
    var originOk = isOriginAllowed(meta, ev.origin);
    if (debug) {
      console.log('[HostBridge] init received', {
        origin: ev.origin,
        appId: meta.appId || null,
        appSlug: meta.appSlug || '',
        originOk: originOk
      });
    }
    if (!originOk) return;

    var ctx = null;
    var user = null;
    var appId = meta.appId || null;
    try {
      if (!appId && meta.appSlug && typeof resolveAppId === 'function') {
        appId = await resolveAppId(meta.appSlug);
      }
      if (typeof resolveContext === 'function') ctx = await resolveContext(meta);
      if (typeof resolveUser === 'function') user = await resolveUser(meta);
    } catch (_) {}

    var jwt = null;
    if (appId && typeof issueToken === 'function') {
      try {
        jwt = await issueToken(appId, ctx, meta);
      } catch (_) {}
    }
    if (debug) {
      console.log('[HostBridge] sso result', {
        ok: !!jwt,
        appId: appId || null,
        ctx: ctx || null,
        jwtLen: jwt ? String(jwt).length : 0
      });
    }

    try {
      ev.source.postMessage({
        type: 'workz-sdk:auth',
        jwt: jwt,
        user: user || null,
        context: ctx || null
      }, getTargetOrigin(meta, ev.origin));
      if (debug) {
        console.log('[HostBridge] auth sent', {
          origin: ev.origin,
          appId: appId || null,
          jwtLen: jwt ? String(jwt).length : 0
        });
      }
    } catch (_) {}
  }

  function isReservedType(type) {
    if (!type) return true;
    if (type.indexOf('workz-sdk:') === 0) return true;
    if (type.indexOf('sdk:') === 0) return true;
    return false;
  }

  function handleEvent(ev) {
    var data = ev?.data || {};
    if (!data || typeof data !== 'object') return;
    var type = data.type;
    if (!type || typeof type !== 'string') return;
    if (type === 'workz-sdk:init' || type === 'workz-sdk:auth') return;
    if (isReservedType(type)) return;

    var sender = findIframeByWindow(ev.source);
    if (!sender || !sender.window) return;
    if (!isOriginAllowed(sender, ev.origin)) return;
    if (!isEventAllowed(sender, type, 'eventsPublish')) return;

    frameRegistry.forEach(function (target) {
      if (!target || !target.window) return;
      if (!isEventAllowed(target, type, 'eventsSubscribe')) return;
      try {
        target.window.postMessage(Object.assign({}, data, { type: type }), getTargetOrigin(target, ev.origin));
      } catch (_) {}
    });
  }

  function onMessage(ev) {
    var data = ev?.data || {};
    if (!data || typeof data !== 'object') return;
    if (data.type === 'workz-sdk:init') {
      handleInit(ev);
      return;
    }
    handleEvent(ev);
  }

  WorkzHostBridge.registerAppIframe = function (iframe, meta) {
    if (!iframe || !iframe.contentWindow) return;
    var info = meta && typeof meta === 'object' ? meta : {};
    var entry = {
      iframe: iframe,
      window: iframe.contentWindow,
      appId: info.appId || null,
      appSlug: info.appSlug || parseAppSlugFromUrl(iframe.src || ''),
      allowedOrigins: normalizeOrigins(info.allowedOrigins),
      eventsPublish: parseEventList(info.eventsPublish),
      eventsSubscribe: parseEventList(info.eventsSubscribe),
      allowAllEvents: !!info.allowAllEvents,
      targetOrigin: info.targetOrigin || ''
    };

    iframe.dataset.workzAppId = entry.appId ? String(entry.appId) : '';
    iframe.dataset.workzAppSlug = entry.appSlug || '';
    if (entry.allowedOrigins.length) {
      iframe.dataset.workzAllowedOrigins = entry.allowedOrigins.join(',');
    }
    if (entry.eventsPublish.length) {
      iframe.dataset.workzEventsPublish = entry.eventsPublish.join(',');
    }
    if (entry.eventsSubscribe.length) {
      iframe.dataset.workzEventsSubscribe = entry.eventsSubscribe.join(',');
    }
    if (entry.allowAllEvents) {
      iframe.dataset.workzEventsAllowAll = '1';
    }
    if (entry.targetOrigin) {
      iframe.dataset.workzTargetOrigin = entry.targetOrigin;
    }

    frameRegistry.set(entry.window, entry);
  };

  WorkzHostBridge.setContextResolver = function (fn) { resolveContext = fn; };
  WorkzHostBridge.setUserResolver = function (fn) { resolveUser = fn; };
  WorkzHostBridge.setTokenIssuer = function (fn) { issueToken = fn; };
  WorkzHostBridge.setAppResolver = function (fn) { resolveAppId = fn; };

  WorkzHostBridge.enable = function () {
    if (enabled) return;
    enabled = true;
    window.addEventListener('message', onMessage, false);
  };

  WorkzHostBridge.disable = function () {
    if (!enabled) return;
    enabled = false;
    window.removeEventListener('message', onMessage, false);
  };

  window.WorkzHostBridge = WorkzHostBridge;
})();
