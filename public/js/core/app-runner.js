// public/js/core/app-runner.js

/**
 * Universal app runner for both JavaScript and Flutter applications.
 * Ensures WorkzSDK is initialized before app code execution with platform detection.
 */
(async function() {
    if (typeof window.WorkzSDK === 'undefined') {
        console.error('WorkzSDK not found. Application cannot start.');
        return;
    }

    try {
        window.__workzAppRunnerActive = true;
        if (typeof window.__workzAppRunnerHandled === 'undefined') {
            window.__workzAppRunnerHandled = false;
        }
        const appConfig = window.WorkzAppConfig || {};
        const manifest = (function () {
            if (!appConfig || !appConfig.manifest) return {};
            if (typeof appConfig.manifest === 'object') return appConfig.manifest;
            try {
                return JSON.parse(appConfig.manifest);
            } catch (_) {
                return {};
            }
        })();
        const normalizeAllowedOrigins = () => {
            if (!manifest.sandbox || typeof manifest.sandbox !== 'object') {
                manifest.sandbox = {};
            }
            if (!manifest.sandbox.postMessage || typeof manifest.sandbox.postMessage !== 'object') {
                manifest.sandbox.postMessage = {};
            }
            let allowed = manifest.sandbox.postMessage.allowedOrigins;
            if (typeof allowed === 'string') {
                allowed = [allowed];
            }
            if (!Array.isArray(allowed)) allowed = [];
            allowed = allowed.filter((o) => o && o !== '*');
            if (!allowed.length) {
                let refOrigin = '';
                try {
                    if (document.referrer) {
                        refOrigin = new URL(document.referrer).origin;
                    }
                } catch (_) {}
                if (!refOrigin && window.location && window.location.origin) {
                    refOrigin = window.location.origin;
                }
                if (refOrigin) allowed = [refOrigin];
            }
            manifest.sandbox.postMessage.allowedOrigins = allowed;
        };
        normalizeAllowedOrigins();
        appConfig.manifest = manifest;

        const waitForDom = () => new Promise(resolve => {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', resolve, { once: true });
            } else {
                resolve();
            }
        });

        const getAppCode = () => {
            if (typeof window.WorkzAppCode === 'string' && window.WorkzAppCode.trim()) {
                return window.WorkzAppCode;
            }
            const script = document.querySelector('script[data-app-code]');
            if (script && script.textContent) {
                return script.textContent;
            }
            return '';
        };

        const ensureAppCodeLoaded = () => {
            if (window.__workzAppCodeEvaluated) return;
            if (window.StoreApp && typeof window.StoreApp.bootstrap === 'function') return;
            if (typeof window.initApp === 'function') return;
            const code = getAppCode();
            if (!code) return;
            try {
                window.__workzAppCodeEvaluated = true;
                // eslint-disable-next-line no-eval
                eval(code);
            } catch (e) {
                console.error('Erro ao executar código do aplicativo:', e);
                throw new Error('Falha ao executar código JavaScript do aplicativo');
            }
        };

        const resolveContextIfNeeded = async () => {
            try {
                if (appConfig && appConfig.preview) return false;
                const mode = String((manifest && manifest.contextRequirements && manifest.contextRequirements.mode) || '').toLowerCase();
                if (mode !== 'user') return false;
                if (window.WorkzSDK.contextAllowed !== false) return false;
                if (typeof window.WorkzSDK.setContext !== 'function') return false;

                let meResp = null;
                if (window.WorkzSDK.apiClient && typeof window.WorkzSDK.apiClient.request === 'function') {
                    meResp = await window.WorkzSDK.apiClient.request('GET', '/me');
                } else if (window.WorkzSDK.api && typeof window.WorkzSDK.api.get === 'function') {
                    meResp = await window.WorkzSDK.api.get('/me');
                }

                const data = (meResp && typeof meResp === 'object' && meResp.data && typeof meResp.data === 'object') ? meResp.data : (meResp || {});
                const user = (data && data.user) ? data.user : data;
                const userId = (user.id !== undefined && user.id !== null) ? user.id :
                    (user.us !== undefined && user.us !== null) ? user.us :
                    (user.user_id !== undefined && user.user_id !== null) ? user.user_id :
                    (user.uid !== undefined && user.uid !== null) ? user.uid : null;
                if (!userId) return false;

                const ok = window.WorkzSDK.setContext({ mode: 'user', id: userId });
                console.warn('WorkzSDK context resolved in runner:', ok, userId);
                return !!ok;
            } catch (e) {
                console.warn('WorkzSDK context resolve failed in runner', e);
                return false;
            }
        };

        // Detect platform and initialize SDK with appropriate mode
        const mode = appConfig && appConfig.preview ? 'standalone' : 'embed';
        
        const initStartedAt = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        console.log('Initializing WorkzSDK v2 in', mode, 'mode');
        
        // Initialize the unified SDK
        const success = await window.WorkzSDK.init({
            mode,
            appConfig,
            manifest
        });
        
        if (!success) {
            throw new Error('SDK initialization failed');
        }
        const initDoneAt = (typeof performance !== 'undefined' && performance.now) ? performance.now() : Date.now();
        console.log('WorkzSDK init completed in', Math.round(initDoneAt - initStartedAt), 'ms');
        
        // Log platform information
        const platform = window.WorkzSDK.getPlatform();
        console.log('Platform detected:', platform.type, platform);
        
        // Wait for SDK to be fully ready (avoid sdk:ready subscribe)
        if (!window.WorkzSDK.isReady()) {
            await new Promise(resolve => {
                const startedAt = Date.now();
                const timer = setInterval(() => {
                    if (window.WorkzSDK.isReady()) {
                        clearInterval(timer);
                        resolve();
                        return;
                    }
                    if (Date.now() - startedAt > 2000) {
                        clearInterval(timer);
                        resolve();
                    }
                }, 120);
            });
        }
        
        console.log('WorkzSDK ready, starting application...');

        // WorkzApp Pro integration (do not block app startup on slow init)
        if (window.WorkzApp && typeof window.WorkzApp.init === 'function') {
            const withTimeout = (promise, timeoutMs, label) => new Promise((resolve, reject) => {
                let done = false;
                const timer = setTimeout(() => {
                    if (done) return;
                    done = true;
                    reject(new Error(label || 'workzapp_init_timeout'));
                }, timeoutMs);
                Promise.resolve(promise)
                    .then((value) => {
                        if (done) return;
                        done = true;
                        clearTimeout(timer);
                        resolve(value);
                    })
                    .catch((err) => {
                        if (done) return;
                        done = true;
                        clearTimeout(timer);
                        reject(err);
                    });
            });
            try {
                await withTimeout(window.WorkzApp.init({
                    sdk: window.WorkzSDK,
                    appConfig,
                    preview: false,
                    debug: false,
                    initSdk: false
                }), 3000, 'workzapp_init_timeout');
            } catch (e) {
                console.warn('WorkzApp init failed or timed out', e);
            }
        }

        if (mode !== 'embed') {
            await resolveContextIfNeeded();
        }

        await waitForDom();
        ensureAppCodeLoaded();
        
        const resolveEntryPoint = () => {
            if (window.StoreApp && typeof window.StoreApp.bootstrap === 'function') {
                return { type: 'storeapp', fn: window.StoreApp.bootstrap };
            }
            if (typeof window.initApp === 'function') {
                return { type: 'initapp', fn: window.initApp };
            }
            return null;
        };

        const waitForEntryPoint = async (timeoutMs = 1500) => {
            const startedAt = Date.now();
            let entry = resolveEntryPoint();
            if (entry) return entry;
            await new Promise(resolve => {
                const timer = setInterval(() => {
                    entry = resolveEntryPoint();
                    if (entry || Date.now() - startedAt > timeoutMs) {
                        clearInterval(timer);
                        resolve();
                    }
                }, 60);
            });
            return entry || null;
        };

        // Start the application based on platform
        if (platform.type === 'flutter-web') {
            // Flutter web apps will handle their own initialization
            // The SDK is now available globally for Flutter interop
            console.log('Flutter web app detected, SDK ready for interop');
        } else {
            // JavaScript apps - unified bootstrap
            const entry = await waitForEntryPoint();
            if (entry && entry.type === 'storeapp') {
                window.__workzAppRunnerHandled = true;
                const root = document.getElementById('app-root') || document.getElementById('app');
                await entry.fn({
                    sdk: window.WorkzSDK,
                    appConfig,
                    root
                });
            } else if (entry && entry.type === 'initapp') {
                window.__workzAppRunnerHandled = true;
                await entry.fn();
            } else {
                console.warn('Nenhum ponto de entrada encontrado para o aplicativo JavaScript');
            }
        }
        
        // Emit app started event
        window.WorkzSDK.emit('app:started', {
            platform: platform.type,
            timestamp: new Date().toISOString()
        });
        
    } catch (error) {
        console.error('Failed to initialize WorkzSDK or application:', error);
        
        // Emit error event for debugging
        if (window.WorkzSDK && window.WorkzSDK.emit) {
            window.WorkzSDK.emit('app:error', {
                error: error.message,
                timestamp: new Date().toISOString()
            });
        }
    }
})();
