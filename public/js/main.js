// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {

    /*
     * =========================================================================
     *  Workz! Main Script Organization
     * -------------------------------------------------------------------------
     *  1. Global State & DOM References
     *  2. Template Definitions
     *  3. UI Construction Helpers
     *  4. Media & Upload Utilities
     *  5. Template Rendering & Messaging Utilities
     *  6. Domain Constants & Data Mappers
     *  7. Feed Helpers & Social Interactions
     *  8. Generic Helpers (navigation, notifications, masks)
     *  9. Startup Flow & Event Bindings
     * =========================================================================
     */

    // =====================================================================
    // 1. GLOBAL STATE & DOM REFERENCES
    // =====================================================================

    const mainWrapper = document.querySelector("#main-wrapper"); //Main Wrapper
    const sidebarWrapper = document.querySelector('#sidebar-wrapper');

    let workzContent = '';

    const apiClient = new ApiClient();

    // Global user/page/feed state
    let currentUserData = null;
    let userPeople = [];
    let userBusinesses = [];
    let userTeams = [];

    let userBusinessesData = [];
    let userTeamsData = [];
    let businessesJobs = {};

    let memberStatus = null;
    let memberLevel = null;
    let viewRestricted = false;
    let pageRestricted = false;

    let viewType = null;
    let viewId = null;
    let viewData = null;

    const FEED_PAGE_SIZE = 6;
    let feedOffset = 0;
    let feedLoading = false;
    let feedFinished = false;
    // Observers globais para evitar acúmulo entre navegações
    let feedObserver = null;
    let listObserver = null;

    const feedUserCache = new Map();
    function pruneFeedUserCache(max = 800) {
        try {
            if (!feedUserCache || typeof feedUserCache.size !== 'number') return;
            if (feedUserCache.size <= max) return;
            const removeCount = feedUserCache.size - max;
            let i = 0;
            for (const key of feedUserCache.keys()) {
                feedUserCache.delete(key);
                if (++i >= removeCount) break;
            }
        } catch (_) {}
    }
    let feedInteractionsAttached = false;

    

    // Evita que cliques internos da sidebar acionem o handler global de fechar
    function installSidebarClickShield() {
        if (!sidebarWrapper) return;
        if (sidebarWrapper._clickShieldInstalled) return;
        const stopper = (e) => {
            const target = e.target;
            // Permitir apenas cliques em botões/links com data-sidebar-action subirem
            const isAction = !!(target && target.closest && target.closest('[data-sidebar-action]'));
            if (!isAction && sidebarWrapper.contains(target)) {
                // Interrompe a propagação antes de atingir o document,
                // mas após os handlers internos já terem processado o clique
                e.stopPropagation();
            }
        };
        // Apenas em 'click' e no bubble phase (capture = false),
        // para não bloquear os handlers internos da sidebar
        try { sidebarWrapper.addEventListener('click', stopper, false); sidebarWrapper._clickShieldInstalled = true; } catch (_) {}
    }


    // Navegação estilo iOS para o sidebar (stack)
    const SidebarNav = {
        stack: [],
        mount: null,
        setMount(el) { this.mount = el; },
        current() { return this.stack[this.stack.length - 1]; },
        prev() { return this.stack[this.stack.length - 2]; },
        resetRoot(data) { this.stack = [{ view: 'root', title: 'Ajustes', payload: { data }, type: 'root' }]; this.render(); },
        push(state) { this.stack.push(state); this.render(); },
        back() { if (this.stack.length > 1) { this.stack.pop(); this.render(); } else { this.resetRoot(currentUserData); } },
        async render() {
            if (!this.mount) return;
            const st = this.current();
            destroyImageCropper({ keepContext: st?.view === 'image-crop' });
            const isRoot = (st.view === 'root');
            const payload = { ...(st.payload || {}), view: (isRoot ? null : st.view), origin: 'stack', navTitle: st.title, prevTitle: (this.prev()?.title || 'Ajustes') };
            this.mount.dataset.navMode = 'stack';
            this.mount.dataset.currentView = st.view || 'root';
            await renderTemplate(this.mount, templates.sidebarPageSettings, payload, () => {
                // Root: handler único para main menu
                if (isRoot) {
                    if (this.mount._rootHandler) this.mount.removeEventListener('click', this.mount._rootHandler);
                    const rootHandler = (e) => {
                        const profileCard = e.target.closest('#sidebar-profile-link');
                        if (profileCard && this.mount.contains(profileCard)) {
                            this.push({ view: ENTITY.PROFILE, title: currentUserData?.tt || 'Ajustes', payload: { data: currentUserData } });
                            return;
                        }
                        const it = e.target.closest('#people,#businesses,#teams,#desktop,#apps,#logout');
                        if (!it || !this.mount.contains(it)) return;
                        const id = it.id;
                        if (id === 'desktop') { this.push({ view: 'desktop', title: 'Área de Trabalho', payload: { data: currentUserData } }); return; }
                        if (id === 'logout') { handleLogout(); return; }
                        const titleMap = { people: 'Pessoas', businesses: 'Negócios', teams: 'Equipes', apps: 'Aplicativos' };
                        this.push({ view: id, title: titleMap[id] || 'Ajustes', payload: { data: currentUserData } });
                    };
                    this.mount.addEventListener('click', rootHandler);
                    this.mount._rootHandler = rootHandler;
                }

                // Delegação para abrir itens de listas (negócios/equipes)
                if (this.mount._listHandler) this.mount.removeEventListener('click', this.mount._listHandler);
                const listHandler = async (e) => {
                    if (this.mount.dataset.currentView === 'apps') {
                        const storeEl = e.target.closest('#open-app-store');
                        if (storeEl && this.mount.contains(storeEl)) {
                            // Abre a loja usando função unificada (sem SSO)
                            try { await launchAppBySlug('store', { sso: false }); } catch (_) {}
                            return;
                        }
                    }
                    // Pessoas/Negócios/Equipes (data-id) e Aplicativos (data-app-id)
                    const appRow = e.target.closest('[data-app-id]');
                    if (appRow && this.mount.contains(appRow) && this.mount.dataset.currentView === 'apps') {
                        const ap = appRow.dataset.appId;
                        const res = await apiClient.post('/search', { db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { id: ap } });
                        const app = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (app) this.push({ view: 'app-settings', title: app.tt || 'Aplicativo', payload: { data: app, appId: ap } });
                        return;
                    }
                    const row = e.target.closest('[data-id]');
                    if (!row || !this.mount.contains(row)) return;
                    const id = row.dataset.id;
                    if (!id) return;
                    if (this.mount.dataset.currentView === 'businesses') {
                        const res = await apiClient.post('/search', { db: 'workz_companies', table: 'companies', columns: ['*'], conditions: { id } });
                        const data = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (data) this.push({ view: ENTITY.BUSINESS, title: data.tt || 'Negócio', payload: { data, type: 'business' } });
                    } else if (this.mount.dataset.currentView === 'teams') {
                        const res = await apiClient.post('/search', { db: 'workz_companies', table: 'teams', columns: ['*'], conditions: { id } });
                        const data = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (data) this.push({ view: ENTITY.TEAM, title: data.tt || 'Equipe', payload: { data, type: 'team' } });
                    } else if (this.mount.dataset.currentView === 'people') {
                        // Abrir o perfil do usuário (visualização pública), não ajustes
                        navigateTo(`/profile/${id}`);
                        try { await toggleSidebar(); } catch (_) { }
                    }
                };
                this.mount.addEventListener('click', listHandler);
                this.mount._listHandler = listHandler;

                // Sidebar-scoped actions handler (e.g., create-business/create-team)
                if (this.mount._actionsHandler) this.mount.removeEventListener('click', this.mount._actionsHandler);
                const actionsHandler = (e) => {
                    const btn = e.target.closest('[data-action]');
                    if (!btn || !this.mount.contains(btn)) return;
                    const action = btn.dataset.action;
                    const handler = ACTIONS[action];
                    if (!handler) return;
                    e.preventDefault();
                    try {
                        Promise.resolve(handler({ event: e, button: btn, state: getState() }))
                            .finally(() => {
                                if (action === 'follow-user' || action === 'unfollow-user') {
                                    try {
                                        if (viewType === ENTITY.PROFILE && String(viewId) === String(getState()?.view?.id)) {
                                            const fp = Number(viewData?.feed_privacy ?? 0);
                                            const isOwner = String(currentUserData?.id ?? '') === String(viewId ?? '');
                                            if (fp === 1) {
                                                if (action === 'follow-user') viewRestricted = false;
                                                else if (action === 'unfollow-user' && !isOwner) viewRestricted = true;
                                            }
                                            resetFeed();
                                            loadFeed();
                                        }
                                    } catch (_) {}
                                }
                            });
                    } catch (_) {}
                };
                this.mount.addEventListener('click', actionsHandler);
                this.mount._actionsHandler = actionsHandler;

                // Filtro de busca em Pessoas (live filter)
                if (this.mount.dataset.currentView === 'people') {
                    const input = this.mount.querySelector('#people-search');
                    const listEl = this.mount.querySelector('#people-list');
                    if (input && listEl) {
                        if (this.mount._peopleSearchHandler) input.removeEventListener('input', this.mount._peopleSearchHandler);
                        const handler = (ev) => {
                            const q = (ev.target.value || '').toLowerCase().trim();
                            [...listEl.children].forEach(row => {
                                const name = row.getAttribute('data-name') || '';
                                row.style.display = (!q || name.includes(q)) ? '' : 'none';
                            });
                        };
                        input.addEventListener('input', handler);
                        this.mount._peopleSearchHandler = handler;
                    }
                }

                // Filtro de busca em Negócios
                if (this.mount.dataset.currentView === 'businesses') {
                    const input = this.mount.querySelector('#businesses-search');
                    const listEl = this.mount.querySelector('#businesses-list');
                    if (input && listEl) {
                        if (this.mount._bizSearchHandler) input.removeEventListener('input', this.mount._bizSearchHandler);
                        const handler = (ev) => {
                            const q = (ev.target.value || '').toLowerCase().trim();
                            [...listEl.children].forEach(row => {
                                const name = row.getAttribute('data-name') || '';
                                row.style.display = (!q || name.includes(q)) ? '' : 'none';
                            });
                        };
                        input.addEventListener('input', handler);
                        this.mount._bizSearchHandler = handler;
                    }
                }

                // Filtro de busca em Equipes
                if (this.mount.dataset.currentView === 'teams') {
                    const input = this.mount.querySelector('#teams-search');
                    const listEl = this.mount.querySelector('#teams-list');
                    if (input && listEl) {
                        if (this.mount._teamsSearchHandler) input.removeEventListener('input', this.mount._teamsSearchHandler);
                        const handler = (ev) => {
                            const q = (ev.target.value || '').toLowerCase().trim();
                            [...listEl.children].forEach(row => {
                                const name = row.getAttribute('data-name') || '';
                                row.style.display = (!q || name.includes(q)) ? '' : 'none';
                            });
                        };
                        input.addEventListener('input', handler);
                        this.mount._teamsSearchHandler = handler;
                    }
                }

                // Filtro de busca em Aplicativos
                if (this.mount.dataset.currentView === 'apps') {
                    const input = this.mount.querySelector('#apps-search');
                    const listEl = this.mount.querySelector('#apps-list');
                    if (input && listEl) {
                        if (this.mount._appsSearchHandler) input.removeEventListener('input', this.mount._appsSearchHandler);
                        const handler = (ev) => {
                            const q = (ev.target.value || '').toLowerCase().trim();
                            [...listEl.children].forEach(row => {
                                const name = row.getAttribute('data-name') || '';
                                row.style.display = (!q || name.includes(q)) ? '' : 'none';
                            });
                        };
                        input.addEventListener('input', handler);
                        this.mount._appsSearchHandler = handler;
                    }
                }

                // Se estiver em view de entidade, conecte os atalhos internos
                if ([ENTITY.PROFILE, ENTITY.BUSINESS, ENTITY.TEAM].includes(st.view)) {
                    wireSidebarPageActions(this.mount, payload.data, st.view);
                }

                // Hooks específicos de subviews
                if (st.view === 'user-jobs') {
                    this.mount.addEventListener('change', (e2) => {
                        const sel = e2.target.closest('select[name="type"]'); if (!sel) return;
                        const form = sel.closest('.job-form');
                        const disabled = sel.disabled || form?.dataset.readonlyMode === '1';
                        const currentExtraValue = form?.querySelector('[name="third_party"]')?.value || form?.dataset.thirdParty || '';
                        renderOutsourcedRow(sel, { selected: currentExtraValue, disabled });
                    });
                    this.mount.addEventListener('submit', async (e3) => {
                        if (e3.target.classList.contains('job-form')) {
                            e3.preventDefault();
                            const messageContainer = document.getElementById('message');
                            const form = new FormData(e3.target);
                            const data = Object.fromEntries(form.entries());
                            data.visibility = e3.target.querySelector(`[name="visibility"]`).checked ? 1 : 0;
                            data.st = e3.target.querySelector(`[name="st"]`).checked ? 1 : 0;
                            if (data) {
                                const result = await apiClient.post('/update', { db: 'workz_companies', table: 'employees', data, conditions: { id: e3.target.dataset.jobId } });
                                if (result) renderTemplate(messageContainer, templates.message, { message: 'Experiência profissional atualizada com sucesso!', type: 'success' });
                                else renderTemplate(messageContainer, templates.message, { message: 'Falha na atualização', type: 'error' });
                            }
                        }
                    });
                    initOutsourcedUI(this.mount);
                }

                if (st.view === 'image-crop') {
                    initializeImageCropperView(this.mount, payload.crop);
                    return;
                }

                if (st.view === 'password') {
                    const form = this.mount.querySelector('#change-password-form');
                    if (form) {
                        if (form._changePasswordHandler) {
                            form.removeEventListener('submit', form._changePasswordHandler, true);
                        }
                        const submitHandler = (ev) => {
                            ev.preventDefault();
                            ev.stopPropagation();
                            if (typeof ev.stopImmediatePropagation === 'function') {
                                ev.stopImmediatePropagation();
                            }
                            handleChangePassword(ev);
                        };
                        form.addEventListener('submit', submitHandler, true);
                        form.onsubmit = submitHandler;
                        form._changePasswordHandler = submitHandler;
                    }
                }
            });
        }
    };

    // Constantes de entidade para padronização

    // =====================================================================
    // 6. DOMAIN CONSTANTS & DATA MAPPERS
    // =====================================================================

    // (removido duplicado de ENTITY/ENTITY_TO_TYPE_MAP/ENTITY_TYPE_TO_TABLE_MAP)


    // =====================================================================
    // 4. MEDIA & UPLOAD UTILITIES
    // =====================================================================

    function resolveImageSrc(imValue, label = '', options = {}) {
        const { size = 100, fallbackUrl = null } = options ?? {};
        const raw = imValue ?? '';
        const trimmed = typeof raw === 'string' ? raw.trim() : String(raw).trim();

        if (!trimmed) {
            if (fallbackUrl) return fallbackUrl;
            const initial = ((label ?? '').toString().trim().charAt(0) || '?').toUpperCase();
            const safeInitial = encodeURIComponent(initial);
            return `https://placehold.co/${size}x${size}/EFEFEF/333?text=${safeInitial}`;
        }

        if (/^data:image\//i.test(trimmed)) return trimmed;
        if (/^https?:\/\//i.test(trimmed)) return trimmed;

        // If it starts with a known image path, treat it as a path.
        if (
            trimmed.startsWith('/images/') ||
            trimmed.startsWith('/users/') ||
            trimmed.startsWith('/uploads/') ||
            trimmed.startsWith('/app/uploads/') ||
            /^uploads\//i.test(trimmed)
        ) {
            return trimmed;
        }

        // Otherwise, assume it's raw base64 data.
        return `data:image/png;base64,${trimmed}`;
    }

    function resolveBackgroundImage(imValue, label = '', options = {}) {
        const src = resolveImageSrc(imValue, label, options);
        const sanitized = String(src || '').replace(/['\\]/g, '\\$&');
        return `url('${sanitized}')`;
    }

    function applyEntityBackgroundImage(entityData = null) {
        const coverEl = document.querySelector('#workz-content > div.col-span-12.rounded-b-3xl.h-48.bg-cover.bg-center');
        if (!coverEl) return;

        const hasImage = !!(entityData && entityData.bk);
        if (hasImage) {
            coverEl.style.backgroundImage = resolveBackgroundImage(entityData.bk, entityData.tt, { size: 1280 });
        } else {
            coverEl.style.backgroundImage = '';
        }
    }

    function updateEntityBackgroundImageCache(entityType, entityId, imageUrl) {
        const normalizedId = Number(entityId);
        const resolvedUrl = imageUrl || null;
        if (!Number.isFinite(normalizedId)) return;

        if (entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId) {
            currentUserData.bk = resolvedUrl;
        }

        if (viewData && Number(viewData.id) === normalizedId) {
            viewData.bk = resolvedUrl;
        }

        const matchesCurrentView =
            (viewType === ENTITY.PROFILE && entityType === 'people') ||
            (viewType === ENTITY.BUSINESS && entityType === 'businesses') ||
            (viewType === ENTITY.TEAM && entityType === 'teams');

        if (matchesCurrentView && viewData && Number(viewData.id) === normalizedId) {
            applyEntityBackgroundImage(viewData);
            return;
        }

        const isDashboardCurrentUser = viewType === 'dashboard' && entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId;
        if (isDashboardCurrentUser) {
            applyEntityBackgroundImage(null);
            return;
        }

        if (!viewData && viewType !== 'dashboard' && entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId) {
            applyEntityBackgroundImage(currentUserData);
        }
    }

    // (removido duplicado de IMAGE_UPLOAD_STATE)

    // Estado do editor de posts (carrossel)
    // (removido duplicado de POST_MEDIA_STATE)

    function getImageUploadInput() {
        if (IMAGE_UPLOAD_STATE.input) return IMAGE_UPLOAD_STATE.input;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';
        input.addEventListener('change', handleHeroImageSelection);
        sidebarWrapper.appendChild(input);
        IMAGE_UPLOAD_STATE.input = input;
        return input;
    }

    function resetImageUploadState({ clearContext = true } = {}) {
        if (clearContext) IMAGE_UPLOAD_STATE.context = null;
        if (IMAGE_UPLOAD_STATE.input) {
            IMAGE_UPLOAD_STATE.input.value = '';
        }
    }

    function setupBackgroundImageUpload(sidebarContent, pageSettingsData, pageSettingsView) {
        const changeBackgroundBtn = sidebarContent?.querySelector('[data-action="change-background"]');
        if (!changeBackgroundBtn) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;
        if (!entityId) return;

        const clickHandler = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const input = getImageUploadInput();
            IMAGE_UPLOAD_STATE.context = {
                entityType,
                entityId,
                view: pageSettingsView,
                data: pageSettingsData,
                messageContainer: sidebarContent.querySelector('#message') || document.getElementById('message'),
                imageType: 'bk',
                aspectRatio: 20 / 3,
                outputWidth: 1920,
                outputHeight: 288
            };
            input.value = '';
            input.click();
        };

        if (changeBackgroundBtn._uploadHandler) {
            changeBackgroundBtn.removeEventListener('click', changeBackgroundBtn._uploadHandler);
        }
        changeBackgroundBtn.addEventListener('click', clickHandler);
        changeBackgroundBtn._uploadHandler = clickHandler;
    }

    function setupRemoveBackgroundImage(sidebarContent, pageSettingsData, pageSettingsView) {
        const removeBtn = sidebarContent?.querySelector('[data-action="remove-background"]');
        if (!removeBtn) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;

        const clickHandler = async (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (await confirmDialog('Tem certeza que deseja remover a imagem de fundo?', { danger: true })) {
                try {
                    const { db, table } = ENTITY_TYPE_TO_TABLE_MAP[entityType];
                    await apiClient.post('/update', { db, table, data: { bk: null }, conditions: { id: entityId } });

                    if (pageSettingsData) pageSettingsData.bk = null;
                    updateEntityBackgroundImageCache(entityType, entityId, null);

                    SidebarNav.render();
                    notifySuccess('Imagem de fundo removida.');
                } catch (error) {
                    console.error('[background] remove error', error);
                    notifyError('Não foi possível remover a imagem de fundo.');
                }
            }
        };

        if (removeBtn._removeHandler) {
            removeBtn.removeEventListener('click', removeBtn._removeHandler);
        }
        removeBtn.addEventListener('click', clickHandler);
        removeBtn._removeHandler = clickHandler;
    }

    function setupHeroImageUpload(sidebarContent, pageSettingsData, pageSettingsView) {
        const heroImage = sidebarContent?.querySelector('#sidebar-profile-image');
        if (!heroImage) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;
        if (!entityId) return;

        const clickHandler = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const input = getImageUploadInput();
            IMAGE_UPLOAD_STATE.context = {
                entityType,
                entityId,
                view: pageSettingsView,
                data: pageSettingsData,
                messageContainer: sidebarContent.querySelector('#message') || document.getElementById('message')
            };
            input.value = '';
            input.click();
        };

        heroImage.style.cursor = 'pointer';
        heroImage.classList.add('cursor-pointer');
        if (heroImage._uploadHandler) {
            heroImage.removeEventListener('click', heroImage._uploadHandler);
        }
        heroImage.addEventListener('click', clickHandler);
        heroImage._uploadHandler = clickHandler;
    }

    function handleHeroImageSelection(event) {
        const input = event.target;
        const file = input?.files?.[0];
        const context = IMAGE_UPLOAD_STATE.context;
        if (!file || !context) {
            resetImageUploadState();
            return;
        }

        if (!file.type.startsWith('image/')) {
            const container = context.messageContainer || document.getElementById('message');
            if (container) {
                showMessage(container, 'Selecione um arquivo de imagem válido.', 'warning', { dismissAfter: 5000 });
            }
            resetImageUploadState();
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = reader.result;
            if (!dataUrl) {
                const container = context.messageContainer || document.getElementById('message');
                if (container) {
                    showMessage(container, 'Não foi possível ler o arquivo selecionado.', 'error', { dismissAfter: 5000 });
                }
                resetImageUploadState();
                return;
            }

            openImageCropperView({
                entityContext: { ...context },
                imageDataUrl: dataUrl,
                fileName: file.name,
                fileType: file.type,
                fileSize: file.size
            });
        };
        reader.onerror = () => {
            const container = context.messageContainer || document.getElementById('message');
            if (container) {
                void showMessage(container, 'Falha ao carregar a imagem selecionada.', 'error', { dismissAfter: 5000 });
            }
            resetImageUploadState();
        };
        reader.readAsDataURL(file);
    }

    function openImageCropperView(cropPayload) {
        if (!cropPayload?.entityContext?.entityId) {
            resetImageUploadState();
            return;
        }

        SidebarNav.push({
            view: 'image-crop',
            title: 'Editar imagem',
            payload: {
                data: cropPayload.entityContext.data,
                type: cropPayload.entityContext.view,
                crop: { ...cropPayload }
            }
        });
    }

    // Inicializa o seletor e a bandeja de múltiplas mídias no editor de posts
    function initPostEditorGallery(root = document) {
        const picker = root.querySelector('#postMediaPicker');
        const tray = root.querySelector('#postMediaTray');
        const publishBtn = root.querySelector('#publishGalleryBtn');
        const captionInput = root.querySelector('#postCaption');

        if (!picker || !tray || !publishBtn) return;

        if (!POST_MEDIA_STATE.initialized) {
            cleanupPostMediaState();
            POST_MEDIA_STATE.initialized = true;
        }

        const renderTray = () => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items.length) {
                tray.innerHTML = `<p class="text-sm text-center">Nenhuma mídia adicionada ainda.</p>`;
                return;
            }
            let html = '<div class="grid grid-cols-3 gap-2">';
            items.forEach((m, i) => {
                const isVideo = String(m.type).toLowerCase() === 'video' || String(m.mimeType||'').toLowerCase().startsWith('video');
                const mediaEl = isVideo
                    ? `<video src="${m.url}" class="w-full h-24 object-cover rounded-xl" muted loop playsinline></video>`
                    : `<img src="${m.url}" class="w-full h-24 object-cover rounded-xl"/>`;
                const isActive = (POST_MEDIA_STATE.activeIndex === i);
                html += `
                    <div class="relative group ${isActive ? 'ring-2 ring-indigo-500 rounded-xl' : ''}" data-index="${i}">
                        ${mediaEl}
                        <span class="absolute top-1 left-1 text-xs bg-black/60 text-white rounded px-1">${i+1}/${items.length}</span>
                        <div class="absolute top-1 right-1 flex gap-1 opacity-0 group-hover:opacity-100 transition">
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-black/60 text-white flex items-center justify-center\" data-action=\"move-left\" title=\"Mover para a esquerda\"><i class=\"fas fa-arrow-left text-[10px]\"></i></button>
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-black/60 text-white flex items-center justify-center\" data-action=\"move-right\" title=\"Mover para a direita\"><i class=\"fas fa-arrow-right text-[10px]\"></i></button>
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-red-600/80 text-white flex items-center justify-center\" data-action=\"remove\" title=\"Remover\"><i class=\"fas fa-trash text-[10px]\"></i></button>
                        </div>
                    </div>`;
            });
            html += '</div>';
            tray.innerHTML = html;
        };

        const handleReorderOrRemove = (ev) => {
            const btn = ev.target.closest('button[data-action]');
            if (!btn || !tray.contains(btn)) return;
            const card = btn.closest('[data-index]');
            const idx = Number(card?.dataset?.index ?? -1);
            if (!Number.isFinite(idx) || idx < 0) return;
            const action = btn.dataset.action;
            const items = POST_MEDIA_STATE.items;
            if (!Array.isArray(items) || !items.length) return;
            if (action === 'remove') {
                const removed = items.splice(idx, 1)[0];
                try { if (removed?.url && String(removed.url).startsWith('blob:')) URL.revokeObjectURL(removed.url); } catch(_) {}
            } else if (action === 'move-left' && idx > 0) {
                const [it] = items.splice(idx, 1);
                items.splice(idx - 1, 0, it);
            } else if (action === 'move-right' && idx < items.length - 1) {
                const [it] = items.splice(idx, 1);
                items.splice(idx + 1, 0, it);
            }
            renderTray();
        };

        const ensureBridge = (cb) => {
            if (window.EditorBridge && typeof cb === 'function') { cb(); return; }
            setTimeout(() => ensureBridge(cb), 100);
        };

        const switchToMedia = (idx) => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items[idx]) return;
            // Salvar layout atual no item ativo
            if (POST_MEDIA_STATE.activeIndex != null && window.EditorBridge?.serialize) {
                try {
                    const layout = window.EditorBridge.serialize();
                    POST_MEDIA_STATE.items[POST_MEDIA_STATE.activeIndex].layout = layout;
                } catch (_) {}
            }
            POST_MEDIA_STATE.activeIndex = idx;
            renderTray();
            const it = items[idx];
            const type = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video')) ? 'video' : 'image';
            const apply = () => {
                try { window.EditorBridge?.setBackground?.(it.url || (it.path ? ('/'+String(it.path).replace(/^\/+/, '')) : ''), type); } catch(_) {}
                if (window.EditorBridge?.load) {
                    if (it.layout) { try { window.EditorBridge.load(it.layout); } catch(_) {} }
                    else { try { window.EditorBridge.load({ items: [] }); } catch(_) {} }
                }
            };
            ensureBridge(apply);
        };

        const handlePickerChange = async (ev) => {
            const files = Array.from(ev.target.files || []);
            if (!files.length) return;
            const remain = 10 - (POST_MEDIA_STATE.items?.length || 0);
            const toSend = files.slice(0, Math.max(0, remain));
            if (!toSend.length) {
                notifyError('Limite de 10 mídias por post.');
                return;
            }
            const fd = new FormData();
            toSend.forEach(f => fd.append('files[]', f, f.name));
            const res = await apiClient.upload('/posts/media', fd);
            if (res?.error || res?.status === 'error') {
                notifyError(res?.message || 'Falha no upload das mídias.');
                return;
            }
            const media = Array.isArray(res?.media) ? res.media : [];
            if (!media.length) {
                notifyError('Nenhuma mídia válida recebida.');
                return;
            }
            POST_MEDIA_STATE.items.push(...media);
            picker.value = '';
            renderTray();
            if (POST_MEDIA_STATE.activeIndex == null) {
                switchToMedia(0);
            }
        };

        const computePostType = (items) => {
            const types = new Set((items||[]).map(m => (String(m.type||'').toLowerCase().startsWith('video') || String(m.mimeType||'').toLowerCase().startsWith('video')) ? 'video' : 'image'));
            if (types.size === 1) return types.has('video') ? 'video' : 'image';
            return 'mixed';
        };

        const handlePublish = async () => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items.length) {
                notifyError('Adicione ao menos uma mídia.');
                return;
            }
            const caption = (captionInput?.value || '').toString().trim();
            const tp = computePostType(items);

            // Pré-processamento: gerar composições quando houver layout (imagem e vídeo)
            if (window.EditorBridge) {
                publishBtn.disabled = true; publishBtn.textContent = 'Publicando...';
                // Guardar índice ativo para restaurar
                const prevIdx = POST_MEDIA_STATE.activeIndex;

                // Capturar layout atual do item ativo
                if (POST_MEDIA_STATE.activeIndex != null && window.EditorBridge?.serialize) {
                    try {
                        const layoutNow = window.EditorBridge.serialize();
                        if (items[POST_MEDIA_STATE.activeIndex]) {
                            items[POST_MEDIA_STATE.activeIndex].layout = layoutNow;
                        }
                    } catch (_) {}
                }
                for (let i = 0; i < items.length; i++) {
                    const it = items[i];
                    const isVideo = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video'));
                    // layout será salvo automaticamente ao alternar via switchToMedia
                    if (!it.layout) continue;
                    if (isVideo) {
                        // Compor vídeo com layout
                        await new Promise((resolve)=>{
                            const then = async () => {
                                try {
                                    const vblob = await window.EditorBridge.exportVideoBlob();
                                    if (vblob) {
                                        const fd2 = new FormData();
                                        fd2.append('files[]', vblob, `composite_${Date.now()}.webm`);
                                        const up = await apiClient.upload('/posts/media', fd2);
                                        const first = Array.isArray(up?.media) ? up.media[0] : null;
                                        if (first) {
                                            it.originalUrl = it.url; it.originalPath = it.path; it.originalMime = it.mimeType;
                                            it.url = first.url; it.path = first.path; it.mimeType = first.mimeType;
                                        }
                                    }
                                } catch(_) {}
                                resolve();
                            };
                            switchToMedia(i); setTimeout(then, 200);
                        });
                    } else {
                        // Compor imagem com layout
                        await new Promise((resolve)=>{
                            const then = async () => { try { await window.EditorBridge.renderFrame(); } catch(_){} resolve(); };
                            switchToMedia(i); setTimeout(then, 150);
                        });
                        try {
                            const canvas = document.getElementById('outCanvas');
                            if (canvas && canvas.toBlob) {
                                const blob = await new Promise((res)=> canvas.toBlob(res, 'image/jpeg', 0.9));
                                if (blob) {
                                    const fd2 = new FormData();
                                    fd2.append('files[]', blob, `composite_${Date.now()}.jpg`);
                                    const up = await apiClient.upload('/posts/media', fd2);
                                    const first = Array.isArray(up?.media) ? up.media[0] : null;
                                    if (first) {
                                        // manter referência do original
                                        it.originalUrl = it.url; it.originalPath = it.path; it.originalMime = it.mimeType;
                                        it.url = first.url; it.path = first.path; it.mimeType = first.mimeType;
                                    }
                                }
                            }
                        } catch(_) {}
                    }
                }
                // Restaurar índice
                if (prevIdx != null) { POST_MEDIA_STATE.activeIndex = prevIdx; switchToMedia(prevIdx); }
                publishBtn.disabled = false; publishBtn.textContent = 'Publicar';
            }

            // Upload pendente de itens sem layout (originais) somente no publish
            for (let i = 0; i < items.length; i++) {
                const it = items[i];
                if (!it?.path) {
                    const fileObj = it.file || null;
                    if (fileObj) {
                        try {
                            const fd3 = new FormData();
                            const isVid = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video'));
                            const preferredName = it.fileName || (isVid ? `post_${Date.now()}.webm` : `post_${Date.now()}.jpg`);
                            fd3.append('files[]', fileObj, preferredName);
                            const up3 = await apiClient.upload('/posts/media', fd3);
                            const first3 = Array.isArray(up3?.media) ? up3.media[0] : null;
                            if (first3) {
                                const keepLayout = it.layout ? { layout: it.layout } : {};
                                items[i] = { ...first3, ...keepLayout };
                            }
                        } catch(_) {}
                    }
                }
            }

            const vt = String(viewType || '');
            const vd = viewData || null;
            const cm = (vt === ENTITY.TEAM && vd?.id) ? Number(vd.id) || 0 : 0;
            const em = (vt === ENTITY.BUSINESS && vd?.id) ? Number(vd.id) || 0 : 0;

            const payload = { tp, cm, em, ct: { caption, media: items } };
            const res = await apiClient.post('/posts', payload);
            if (res?.error || res?.status === 'error') {
                notifyError(res?.message || 'Não foi possível publicar o post.');
                return;
            }
            notifySuccess('Post publicado!');
            cleanupPostMediaState();
            if (captionInput) captionInput.value = '';
            renderTray();
            resetFeed();
            loadFeed();
        };

        // Preferir estratégia local-first: não fazer upload no change
        try { if (picker._galleryHandler) { picker.removeEventListener('change', picker._galleryHandler); } } catch(_) {}
        try { setupLocalFirstGalleryUpload(root); } catch(_) {}

        const trayClick = (ev) => {
            // Bloqueia propagação para não acionar listeners globais que fecham a sidebar
            if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
            const btn = ev.target.closest('button[data-action]');
            if (btn) { handleReorderOrRemove(ev); return; }
            const card = ev.target.closest('[data-index]');
            if (!card || !tray.contains(card)) return;
            const idx = Number(card.dataset.index || '-1');
            if (!Number.isFinite(idx) || idx < 0) return;
            switchToMedia(idx);
        };
        if (tray._galleryHandler) { tray.removeEventListener('click', tray._galleryHandler); }
        tray.addEventListener('click', trayClick);
        tray._galleryHandler = trayClick;

        if (publishBtn) {
            publishBtn.classList.add('hidden'); // manter somente um botão visível
            if (publishBtn._galleryHandler) { publishBtn.removeEventListener('click', publishBtn._galleryHandler); }
            publishBtn.addEventListener('click', handlePublish);
            publishBtn._galleryHandler = handlePublish;
        }
        renderTray();

        // Se já houver itens (ex.: retorno ao editor), focar o primeiro
        if ((POST_MEDIA_STATE.items?.length||0) > 0 && POST_MEDIA_STATE.activeIndex == null) {
            switchToMedia(0);
        }
    }

    // Integra captura do editor -> adiciona na galeria automaticamente
    function setupEditorCaptureBridge(root = document) {
        // Redireciona para o modo local-first (sem upload imediato)
        try { if (window._editorCaptureHandler) window.removeEventListener('editor:capture', window._editorCaptureHandler); } catch(_) {}
        try { setupEditorCaptureBridgeLocal(root); } catch(_) {}
    }

    // Unifica pontos de entrada de mídia (captura e rótulos) para a galeria
    function wireUnifiedMediaAdders(root = document) {
        const isMobileLike = () => {
            try {
                return (
                    ('ontouchstart' in window) ||
                    (navigator.maxTouchPoints && navigator.maxTouchPoints > 1) ||
                    (window.matchMedia && window.matchMedia('(pointer:coarse)').matches)
                );
            } catch (_) { return false; }
        };

        const picker = root.querySelector('#postMediaPicker');
        if (!picker) return;

        // 1) Captura: no desktop, redireciona o clique para o seletor da galeria
        const captureBtn = root.querySelector('#captureButton');
        if (captureBtn) {
            const onCaptureClick = (ev) => {
                // Em dispositivos móveis, manter o fluxo nativo (gera editor:capture)
                if (isMobileLike()) return;
                ev.preventDefault();
                if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();
                if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
                try { initPostEditorGallery(root); } catch (_) {}
                picker.click();
            };
            try { captureBtn.removeEventListener('click', captureBtn._unifyGalleryHandler, true); } catch (_) {}
            captureBtn.addEventListener('click', onCaptureClick, true);
            captureBtn._unifyGalleryHandler = onCaptureClick;
        }

        // 2) Toolbar superior: rótulo do bgUpload passa a abrir o seletor da galeria
        const bgInput = root.querySelector('#bgUpload');
        const bgLabel = bgInput ? bgInput.closest('label') : null;
        if (bgLabel) {
            const onBgLabelClick = (ev) => {
                ev.preventDefault();
                if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();
                if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
                try { initPostEditorGallery(root); } catch (_) {}
                picker.click();
            };
            try { bgLabel.removeEventListener('click', bgLabel._unifyGalleryHandler, true); } catch (_) {}
            bgLabel.addEventListener('click', onBgLabelClick, true);
            bgLabel._unifyGalleryHandler = onBgLabelClick;
        }
    }

    // Substitui o upload imediato do seletor por estratégia local-first
    function setupLocalFirstGalleryUpload(root = document) {
        const picker = root.querySelector('#postMediaPicker');
        if (!picker) return;
        if (picker._galleryHandler) {
            try { picker.removeEventListener('change', picker._galleryHandler); } catch(_) {}
        }
        const handler = (ev) => {
            const files = Array.from(ev.target.files || []);
            if (!files.length) return;
            const remain = 10 - (POST_MEDIA_STATE.items?.length || 0);
            const toSend = files.slice(0, Math.max(0, remain));
            if (!toSend.length) return;
            const mediaLocals = toSend.map((f) => {
                const isVid = (f.type||'').toLowerCase().startsWith('video');
                const objUrl = URL.createObjectURL(f);
                return {
                    url: objUrl,
                    path: null,
                    mimeType: f.type || (isVid ? 'video/*' : 'image/*'),
                    type: isVid ? 'video' : 'image',
                    file: f,
                    fileName: f.name || (isVid ? `post_${Date.now()}.webm` : `post_${Date.now()}.jpg`)
                };
            });
            POST_MEDIA_STATE.items = POST_MEDIA_STATE.items || [];
            POST_MEDIA_STATE.items.push(...mediaLocals);
            try { initPostEditorGallery(root); } catch(_) {}
            if (POST_MEDIA_STATE.activeIndex == null) { try { switchToMedia?.(0); } catch(_) {} }
        };
        picker.addEventListener('change', handler);
        picker._galleryHandler = handler;
    }

    // Substitui a ponte de captura para estratégia local-first (sem upload imediato)
    function setupEditorCaptureBridgeLocal(root = document) {
        if (window._editorCaptureHandler) {
            try { window.removeEventListener('editor:capture', window._editorCaptureHandler); } catch(_) {}
        }
        const handler = (e) => {
            const detail = e?.detail || {};
            const blob = detail.blob || null;
            const type = (detail.type || '').toLowerCase();
            if (!blob) return;
            const name = type === 'video' ? `capture_${Date.now()}.webm` : `capture_${Date.now()}.jpg`;
            const objUrl = URL.createObjectURL(blob);
            const media = {
                url: objUrl,
                path: null,
                mimeType: blob.type || (type === 'video' ? 'video/webm' : 'image/jpeg'),
                type: type || ((blob.type||'').toLowerCase().startsWith('video') ? 'video' : 'image'),
                file: blob,
                fileName: name
            };
            try { if (window.EditorBridge?.serialize) { media.layout = window.EditorBridge.serialize(); } } catch(_) {}
            POST_MEDIA_STATE.items = POST_MEDIA_STATE.items || [];
            POST_MEDIA_STATE.items.push(media);
            POST_MEDIA_STATE.activeIndex = POST_MEDIA_STATE.items.length - 1;
            try { initPostEditorGallery(root); } catch(_) {}
            if (typeof notifySuccess === 'function') notifySuccess('Mídia adicionada à galeria.');
        };
        window.addEventListener('editor:capture', handler);
        window._editorCaptureHandler = handler;
    }

    // Unifica o botão "Enviar" do editor ao fluxo da galeria
    function wireUnifiedSendFlow(root = document) {
        const btn = root.querySelector('#btnEnviar');
        if (!btn) return;
        if (btn._unifiedHandler) {
            try { btn.removeEventListener('click', btn._unifiedHandler, true); } catch(_) {}
        }

        const handler = async (ev) => {
            ev.preventDefault();
            if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
            if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();
            
            // Se a galeria já tem itens, publicar usando o fluxo da galeria
            const galleryItems = POST_MEDIA_STATE.items || [];
            if (Array.isArray(galleryItems) && galleryItems.length > 0) {
                const hiddenPublish = root.querySelector('#publishGalleryBtn');
                if (hiddenPublish) { hiddenPublish.click(); return; }
                try { initPostEditorGallery(root); setTimeout(() => root.querySelector('#publishGalleryBtn')?.click(), 0); } catch (_) {}
                return;
            }

            // Caso não haja itens na galeria: exportar conteúdo atual (imagem/vídeo) e publicar direto
            
            // Detectar tipo de conteúdo olhando o DOM do editor
            const editor = root.querySelector('#editor');
            const bg = editor?.querySelector('.bg-media');
            const hasVideoBg = !!bg && String(bg.tagName).toUpperCase() === 'VIDEO';
            const hasAnimated = !!editor?.querySelector('.item[data-anim]:not([data-anim="none"])');
            const isVideo = hasVideoBg || hasAnimated;

            try {
                let mediaDesc = null;
                if (isVideo) {
                    if (!window.EditorBridge?.exportVideoBlob) { notifyError('Exportação de vídeo indisponível.'); return; }
                    const videoBlob = await window.EditorBridge.exportVideoBlob();
                    if (!videoBlob) { notifyError('Falha ao gerar vídeo.'); return; }
                    const fd = new FormData();
                    fd.append('files[]', videoBlob, `post_${Date.now()}.webm`);
                    const up = await apiClient.upload('/posts/media', fd);
                    if (up?.error || up?.status === 'error') { notifyError(up?.message || 'Falha no upload do vídeo'); return; }
                    mediaDesc = Array.isArray(up?.media) ? up.media[0] : null;
                } else {
                    if (!window.EditorBridge?.renderFrame) { notifyError('Exportação de imagem indisponível.'); return; }
                    await window.EditorBridge.renderFrame();
                    const canvas = document.getElementById('outCanvas');
                    if (!canvas || !canvas.toBlob) { notifyError('Canvas de saída indisponível.'); return; }
                    let imgBlob = await new Promise((res)=> canvas.toBlob(res, 'image/jpeg', 0.9));
                    if (!imgBlob) { notifyError('Falha ao gerar imagem.'); return; }
                    const fd = new FormData();
                    fd.append('files[]', imgBlob, `post_${Date.now()}.jpg`);
                    const up = await apiClient.upload('/posts/media', fd);
                    if (up?.error || up?.status === 'error') { notifyError(up?.message || 'Falha no upload da imagem'); return; }
                    mediaDesc = Array.isArray(up?.media) ? up.media[0] : null;
                }

                if (!mediaDesc) { notifyError('Mídia não recebida.'); return; }

                // Publicar post único
                const captionInput = root.querySelector('#postCaption');
                const caption = (captionInput?.value || '').toString().trim();

                const vt = String(viewType || '');
                const vd = viewData || null;
                const cm = (vt === ENTITY.TEAM && vd?.id) ? Number(vd.id) || 0 : 0;
                const em = (vt === ENTITY.BUSINESS && vd?.id) ? Number(vd.id) || 0 : 0;

                const tp = isVideo ? 'video' : 'image';
                const payload = { tp, cm, em, ct: { caption, media: [mediaDesc] } };
                const res = await apiClient.post('/posts', payload);
                if (res?.error || res?.status === 'error') { notifyError(res?.message || 'Não foi possível publicar.'); return; }
                notifySuccess('Post publicado!');
                resetFeed();
                loadFeed();
            } catch (err) {
                console.error('[unified-send] error', err);
                notifyError('Falha ao processar envio.');
            }
        };

        btn.addEventListener('click', handler, true);
        btn._unifiedHandler = handler;
    }
    // (removidos duplicados de variáveis globais e installSidebarClickShield)


    // Navegação estilo iOS para o sidebar (stack)
    /* Duplicate SidebarNav removed */
    // Constantes de entidade para padronização

    // =====================================================================
    // 6. DOMAIN CONSTANTS & DATA MAPPERS
    // =====================================================================

    const ENTITY = Object.freeze({
        PROFILE: 'profile',
        BUSINESS: 'business',
        TEAM: 'team'
    });

    const ENTITY_TO_TYPE_MAP = Object.freeze({
        [ENTITY.PROFILE]: 'people',
        [ENTITY.BUSINESS]: 'businesses',
        [ENTITY.TEAM]: 'teams'
    });

    const ENTITY_TYPE_TO_TABLE_MAP = {
        'people': { db: 'workz_data', table: 'hus' },
        'businesses': { db: 'workz_companies', table: 'companies' },
        'teams': { db: 'workz_companies', table: 'teams' }
    };


    // =====================================================================
    // 4. MEDIA & UPLOAD UTILITIES
    // =====================================================================

    function resolveImageSrc(imValue, label = '', options = {}) {
        const { size = 100, fallbackUrl = null } = options ?? {};
        const raw = imValue ?? '';
        const trimmed = typeof raw === 'string' ? raw.trim() : String(raw).trim();

        if (!trimmed) {
            if (fallbackUrl) return fallbackUrl;
            const initial = ((label ?? '').toString().trim().charAt(0) || '?').toUpperCase();
            const safeInitial = encodeURIComponent(initial);
            return `https://placehold.co/${size}x${size}/EFEFEF/333?text=${safeInitial}`;
        }

        if (/^data:image\//i.test(trimmed)) return trimmed;
        if (/^https?:\/\//i.test(trimmed)) return trimmed;

        // If it starts with a known image path, treat it as a path.
        if (
            trimmed.startsWith('/images/') ||
            trimmed.startsWith('/users/') ||
            trimmed.startsWith('/uploads/') ||
            trimmed.startsWith('/app/uploads/') ||
            /^uploads\//i.test(trimmed)
        ) {
            return trimmed;
        }

        // Otherwise, assume it's raw base64 data.
        return `data:image/png;base64,${trimmed}`;
    }

    function resolveBackgroundImage(imValue, label = '', options = {}) {
        const src = resolveImageSrc(imValue, label, options);
        const sanitized = String(src || '').replace(/['\\]/g, '\\$&');
        return `url('${sanitized}')`;
    }

    function applyEntityBackgroundImage(entityData = null) {
        const coverEl = document.querySelector('#workz-content > div.col-span-12.rounded-b-3xl.h-48.bg-cover.bg-center');
        if (!coverEl) return;

        const hasImage = !!(entityData && entityData.bk);
        if (hasImage) {
            coverEl.style.backgroundImage = resolveBackgroundImage(entityData.bk, entityData.tt, { size: 1280 });
        } else {
            coverEl.style.backgroundImage = '';
        }
    }

    function updateEntityBackgroundImageCache(entityType, entityId, imageUrl) {
        const normalizedId = Number(entityId);
        const resolvedUrl = imageUrl || null;
        if (!Number.isFinite(normalizedId)) return;

        if (entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId) {
            currentUserData.bk = resolvedUrl;
        }

        if (viewData && Number(viewData.id) === normalizedId) {
            viewData.bk = resolvedUrl;
        }

        const matchesCurrentView =
            (viewType === ENTITY.PROFILE && entityType === 'people') ||
            (viewType === ENTITY.BUSINESS && entityType === 'businesses') ||
            (viewType === ENTITY.TEAM && entityType === 'teams');

        if (matchesCurrentView && viewData && Number(viewData.id) === normalizedId) {
            applyEntityBackgroundImage(viewData);
            return;
        }

        const isDashboardCurrentUser = viewType === 'dashboard' && entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId;
        if (isDashboardCurrentUser) {
            applyEntityBackgroundImage(null);
            return;
        }

        if (!viewData && viewType !== 'dashboard' && entityType === 'people' && currentUserData && Number(currentUserData.id) === normalizedId) {
            applyEntityBackgroundImage(currentUserData);
        }
    }

    const IMAGE_UPLOAD_STATE = {
        input: null,
        context: null,
        cropper: null
    };

    // Estado do editor de posts (carrossel)
    const POST_MEDIA_STATE = {
        initialized: false,
        items: [], // { type, url, path, mimeType, size, w?, h?, layout? }
        activeIndex: null
    };

    function cleanupPostMediaState() {
        try {
            const items = Array.isArray(POST_MEDIA_STATE.items) ? POST_MEDIA_STATE.items : [];
            items.forEach((m) => {
                try {
                    const url = m && m.url;
                    if (url && typeof url === 'string' && url.startsWith('blob:')) {
                        URL.revokeObjectURL(url);
                    }
                } catch (_) {}
            });
        } catch (_) {}
        POST_MEDIA_STATE.items = [];
        POST_MEDIA_STATE.activeIndex = null;
    }

    function getImageUploadInput() {
        if (IMAGE_UPLOAD_STATE.input) return IMAGE_UPLOAD_STATE.input;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.style.display = 'none';
        input.addEventListener('change', handleHeroImageSelection);
        sidebarWrapper.appendChild(input);
        IMAGE_UPLOAD_STATE.input = input;
        return input;
    }

    function resetImageUploadState({ clearContext = true } = {}) {
        if (clearContext) IMAGE_UPLOAD_STATE.context = null;
        if (IMAGE_UPLOAD_STATE.input) {
            IMAGE_UPLOAD_STATE.input.value = '';
        }
    }

    function setupBackgroundImageUpload(sidebarContent, pageSettingsData, pageSettingsView) {
        const changeBackgroundBtn = sidebarContent?.querySelector('[data-action="change-background"]');
        if (!changeBackgroundBtn) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;
        if (!entityId) return;

        const clickHandler = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const input = getImageUploadInput();
            IMAGE_UPLOAD_STATE.context = {
                entityType,
                entityId,
                view: pageSettingsView,
                data: pageSettingsData,
                messageContainer: sidebarContent.querySelector('#message') || document.getElementById('message'),
                imageType: 'bk',
                aspectRatio: 20 / 3,
                outputWidth: 1920,
                outputHeight: 288
            };
            input.value = '';
            input.click();
        };

        if (changeBackgroundBtn._uploadHandler) {
            changeBackgroundBtn.removeEventListener('click', changeBackgroundBtn._uploadHandler);
        }
        changeBackgroundBtn.addEventListener('click', clickHandler);
        changeBackgroundBtn._uploadHandler = clickHandler;
    }

    function setupRemoveBackgroundImage(sidebarContent, pageSettingsData, pageSettingsView) {
        const removeBtn = sidebarContent?.querySelector('[data-action="remove-background"]');
        if (!removeBtn) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;

        const clickHandler = async (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (await confirmDialog('Tem certeza que deseja remover a imagem de fundo?', { danger: true })) {
                try {
                    const { db, table } = ENTITY_TYPE_TO_TABLE_MAP[entityType];
                    await apiClient.post('/update', { db, table, data: { bk: null }, conditions: { id: entityId } });

                    if (pageSettingsData) pageSettingsData.bk = null;
                    updateEntityBackgroundImageCache(entityType, entityId, null);

                    SidebarNav.render();
                    notifySuccess('Imagem de fundo removida.');
                } catch (error) {
                    console.error('[background] remove error', error);
                    notifyError('Não foi possível remover a imagem de fundo.');
                }
            }
        };

        if (removeBtn._removeHandler) {
            removeBtn.removeEventListener('click', removeBtn._removeHandler);
        }
        removeBtn.addEventListener('click', clickHandler);
        removeBtn._removeHandler = clickHandler;
    }

    function setupHeroImageUpload(sidebarContent, pageSettingsData, pageSettingsView) {
        const heroImage = sidebarContent?.querySelector('#sidebar-profile-image');
        if (!heroImage) return;

        const entityType = ENTITY_TO_TYPE_MAP[pageSettingsView] || 'people';
        const entityId = pageSettingsData?.id ?? currentUserData?.id;
        if (!entityId) return;

        const clickHandler = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const input = getImageUploadInput();
            IMAGE_UPLOAD_STATE.context = {
                entityType,
                entityId,
                view: pageSettingsView,
                data: pageSettingsData,
                messageContainer: sidebarContent.querySelector('#message') || document.getElementById('message')
            };
            input.value = '';
            input.click();
        };

        heroImage.style.cursor = 'pointer';
        heroImage.classList.add('cursor-pointer');
        if (heroImage._uploadHandler) {
            heroImage.removeEventListener('click', heroImage._uploadHandler);
        }
        heroImage.addEventListener('click', clickHandler);
        heroImage._uploadHandler = clickHandler;
    }

    function handleHeroImageSelection(event) {
        const input = event.target;
        const file = input?.files?.[0];
        const context = IMAGE_UPLOAD_STATE.context;
        if (!file || !context) {
            resetImageUploadState();
            return;
        }

        if (!file.type.startsWith('image/')) {
            const container = context.messageContainer || document.getElementById('message');
            if (container) {
                showMessage(container, 'Selecione um arquivo de imagem válido.', 'warning', { dismissAfter: 5000 });
            }
            resetImageUploadState();
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = reader.result;
            if (!dataUrl) {
                const container = context.messageContainer || document.getElementById('message');
                if (container) {
                    showMessage(container, 'Não foi possível ler o arquivo selecionado.', 'error', { dismissAfter: 5000 });
                }
                resetImageUploadState();
                return;
            }

            openImageCropperView({
                entityContext: { ...context },
                imageDataUrl: dataUrl,
                fileName: file.name,
                fileType: file.type,
                fileSize: file.size
            });
        };
        reader.onerror = () => {
            const container = context.messageContainer || document.getElementById('message');
            if (container) {
                void showMessage(container, 'Falha ao carregar a imagem selecionada.', 'error', { dismissAfter: 5000 });
            }
            resetImageUploadState();
        };
        reader.readAsDataURL(file);
    }

    function openImageCropperView(cropPayload) {
        if (!cropPayload?.entityContext?.entityId) {
            resetImageUploadState();
            return;
        }

        SidebarNav.push({
            view: 'image-crop',
            title: 'Editar imagem',
            payload: {
                data: cropPayload.entityContext.data,
                type: cropPayload.entityContext.view,
                crop: { ...cropPayload }
            }
        });
    }

    // Inicializa o seletor e a bandeja de múltiplas mídias no editor de posts
    function initPostEditorGallery(root = document) {
        const picker = root.querySelector('#postMediaPicker');
        const tray = root.querySelector('#postMediaTray');
        const publishBtn = root.querySelector('#publishGalleryBtn');
        const captionInput = root.querySelector('#postCaption');

        if (!picker || !tray || !publishBtn) return;

        if (!POST_MEDIA_STATE.initialized) {
            cleanupPostMediaState();
            POST_MEDIA_STATE.initialized = true;
        }

        const renderTray = () => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items.length) {
                tray.innerHTML = `<div class="text-sm text-slate-500">Nenhuma mídia adicionada ainda.</div>`;
                return;
            }
            let html = '<div class="grid grid-cols-3 gap-2">';
            items.forEach((m, i) => {
                const isVideo = String(m.type).toLowerCase() === 'video' || String(m.mimeType||'').toLowerCase().startsWith('video');
                const mediaEl = isVideo
                    ? `<video src="${m.url}" class="w-full h-24 object-cover rounded-xl" muted loop playsinline></video>`
                    : `<img src="${m.url}" class="w-full h-24 object-cover rounded-xl"/>`;
                const isActive = (POST_MEDIA_STATE.activeIndex === i);
                html += `
                    <div class="relative group ${isActive ? 'ring-2 ring-indigo-500 rounded-xl' : ''}" data-index="${i}">
                        ${mediaEl}
                        <span class="absolute top-1 left-1 text-xs bg-black/60 text-white rounded px-1">${i+1}/${items.length}</span>
                        <div class="absolute top-1 right-1 flex gap-1 opacity-0 group-hover:opacity-100 transition">
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-black/60 text-white flex items-center justify-center\" data-action=\"move-left\" title=\"Mover para a esquerda\"><i class=\"fas fa-arrow-left text-[10px]\"></i></button>
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-black/60 text-white flex items-center justify-center\" data-action=\"move-right\" title=\"Mover para a direita\"><i class=\"fas fa-arrow-right text-[10px]\"></i></button>
                            <button type=\"button\" class=\"w-7 h-7 rounded-full bg-red-600/80 text-white flex items-center justify-center\" data-action=\"remove\" title=\"Remover\"><i class=\"fas fa-trash text-[10px]\"></i></button>
                        </div>
                    </div>`;
            });
            html += '</div>';
            tray.innerHTML = html;
        };

        const handleReorderOrRemove = (ev) => {
            const btn = ev.target.closest('button[data-action]');
            if (!btn || !tray.contains(btn)) return;
            const card = btn.closest('[data-index]');
            const idx = Number(card?.dataset?.index ?? -1);
            if (!Number.isFinite(idx) || idx < 0) return;
            const action = btn.dataset.action;
            const items = POST_MEDIA_STATE.items;
            if (!Array.isArray(items) || !items.length) return;
            if (action === 'remove') {
                const removed = items.splice(idx, 1)[0];
                try { if (removed?.url && String(removed.url).startsWith('blob:')) URL.revokeObjectURL(removed.url); } catch(_) {}
            } else if (action === 'move-left' && idx > 0) {
                const [it] = items.splice(idx, 1);
                items.splice(idx - 1, 0, it);
            } else if (action === 'move-right' && idx < items.length - 1) {
                const [it] = items.splice(idx, 1);
                items.splice(idx + 1, 0, it);
            }
            renderTray();
        };

        const ensureBridge = (cb) => {
            if (window.EditorBridge && typeof cb === 'function') { cb(); return; }
            setTimeout(() => ensureBridge(cb), 100);
        };

        const switchToMedia = (idx) => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items[idx]) return;
            // Salvar layout atual no item ativo
            if (POST_MEDIA_STATE.activeIndex != null && window.EditorBridge?.serialize) {
                try {
                    const layout = window.EditorBridge.serialize();
                    POST_MEDIA_STATE.items[POST_MEDIA_STATE.activeIndex].layout = layout;
                } catch (_) {}
            }
            POST_MEDIA_STATE.activeIndex = idx;
            renderTray();
            const it = items[idx];
            const type = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video')) ? 'video' : 'image';
            const apply = () => {
                try { window.EditorBridge?.setBackground?.(it.url || (it.path ? ('/'+String(it.path).replace(/^\/+/, '')) : ''), type); } catch(_) {}
                if (window.EditorBridge?.load) {
                    if (it.layout) { try { window.EditorBridge.load(it.layout); } catch(_) {} }
                    else { try { window.EditorBridge.load({ items: [] }); } catch(_) {} }
                }
            };
            ensureBridge(apply);
        };

        const handlePickerChange = async (ev) => {
            const files = Array.from(ev.target.files || []);
            if (!files.length) return;
            const remain = 10 - (POST_MEDIA_STATE.items?.length || 0);
            const toSend = files.slice(0, Math.max(0, remain));
            if (!toSend.length) {
                notifyError('Limite de 10 mídias por post.');
                return;
            }
            const fd = new FormData();
            toSend.forEach(f => fd.append('files[]', f, f.name));
            const res = await apiClient.upload('/posts/media', fd);
            if (res?.error || res?.status === 'error') {
                notifyError(res?.message || 'Falha no upload das mídias.');
                return;
            }
            const media = Array.isArray(res?.media) ? res.media : [];
            if (!media.length) {
                notifyError('Nenhuma mídia válida recebida.');
                return;
            }
            POST_MEDIA_STATE.items.push(...media);
            picker.value = '';
            renderTray();
            if (POST_MEDIA_STATE.activeIndex == null) {
                switchToMedia(0);
            }
        };

        const computePostType = (items) => {
            const types = new Set((items||[]).map(m => (String(m.type||'').toLowerCase().startsWith('video') || String(m.mimeType||'').toLowerCase().startsWith('video')) ? 'video' : 'image'));
            if (types.size === 1) return types.has('video') ? 'video' : 'image';
            return 'mixed';
        };

        const handlePublish = async () => {
            const items = POST_MEDIA_STATE.items || [];
            if (!items.length) {
                notifyError('Adicione ao menos uma mídia.');
                return;
            }
            const caption = (captionInput?.value || '').toString().trim();
            const tp = computePostType(items);

            // Pré-processamento: gerar composições quando houver layout (imagem e vídeo)
            if (window.EditorBridge) {
                publishBtn.disabled = true; publishBtn.textContent = 'Publicando...';
                // Guardar índice ativo para restaurar
                const prevIdx = POST_MEDIA_STATE.activeIndex;

                // Capturar layout atual do item ativo
                if (POST_MEDIA_STATE.activeIndex != null && window.EditorBridge?.serialize) {
                    try {
                        const layoutNow = window.EditorBridge.serialize();
                        if (items[POST_MEDIA_STATE.activeIndex]) {
                            items[POST_MEDIA_STATE.activeIndex].layout = layoutNow;
                        }
                    } catch (_) {}
                }
                for (let i = 0; i < items.length; i++) {
                    const it = items[i];
                    const isVideo = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video'));
                    // layout será salvo automaticamente ao alternar via switchToMedia
                    if (!it.layout) continue;
                    if (isVideo) {
                        // Compor vídeo com layout
                        await new Promise((resolve)=>{
                            const then = async () => {
                                try {
                                    const vblob = await window.EditorBridge.exportVideoBlob();
                                    if (vblob) {
                                        const fd2 = new FormData();
                                        fd2.append('files[]', vblob, `composite_${Date.now()}.webm`);
                                        const up = await apiClient.upload('/posts/media', fd2);
                                        const first = Array.isArray(up?.media) ? up.media[0] : null;
                                        if (first) {
                                            it.originalUrl = it.url; it.originalPath = it.path; it.originalMime = it.mimeType;
                                            it.url = first.url; it.path = first.path; it.mimeType = first.mimeType;
                                        }
                                    }
                                } catch(_) {}
                                resolve();
                            };
                            switchToMedia(i); setTimeout(then, 200);
                        });
                    } else {
                        // Compor imagem com layout
                        await new Promise((resolve)=>{
                            const then = async () => { try { await window.EditorBridge.renderFrame(); } catch(_){} resolve(); };
                            switchToMedia(i); setTimeout(then, 150);
                        });
                        try {
                            const canvas = document.getElementById('outCanvas');
                            if (canvas && canvas.toBlob) {
                                const blob = await new Promise((res)=> canvas.toBlob(res, 'image/jpeg', 0.9));
                                if (blob) {
                                    const fd2 = new FormData();
                                    fd2.append('files[]', blob, `composite_${Date.now()}.jpg`);
                                    const up = await apiClient.upload('/posts/media', fd2);
                                    const first = Array.isArray(up?.media) ? up.media[0] : null;
                                    if (first) {
                                        // manter referência do original
                                        it.originalUrl = it.url; it.originalPath = it.path; it.originalMime = it.mimeType;
                                        it.url = first.url; it.path = first.path; it.mimeType = first.mimeType;
                                    }
                                }
                            }
                        } catch(_) {}
                    }
                }
                // Restaurar índice
                if (prevIdx != null) { POST_MEDIA_STATE.activeIndex = prevIdx; switchToMedia(prevIdx); }
                publishBtn.disabled = false; publishBtn.textContent = 'Publicar';
            }

            // Upload pendente de itens sem layout (originais) somente no publish
            for (let i = 0; i < items.length; i++) {
                const it = items[i];
                if (!it?.path) {
                    const fileObj = it.file || null;
                    if (fileObj) {
                        try {
                            const fd3 = new FormData();
                            const isVid = (String(it.type||'').toLowerCase() === 'video' || String(it.mimeType||'').toLowerCase().startsWith('video'));
                            const preferredName = it.fileName || (isVid ? `post_${Date.now()}.webm` : `post_${Date.now()}.jpg`);
                            fd3.append('files[]', fileObj, preferredName);
                            const up3 = await apiClient.upload('/posts/media', fd3);
                            const first3 = Array.isArray(up3?.media) ? up3.media[0] : null;
                            if (first3) {
                                const keepLayout = it.layout ? { layout: it.layout } : {};
                                items[i] = { ...first3, ...keepLayout };
                            }
                        } catch(_) {}
                    }
                }
            }

            const vt = String(viewType || '');
            const vd = viewData || null;
            const cm = (vt === ENTITY.TEAM && vd?.id) ? Number(vd.id) || 0 : 0;
            const em = (vt === ENTITY.BUSINESS && vd?.id) ? Number(vd.id) || 0 : 0;

            const payload = { tp, cm, em, ct: { caption, media: items } };
            const res = await apiClient.post('/posts', payload);
            if (res?.error || res?.status === 'error') {
                notifyError(res?.message || 'Não foi possível publicar o post.');
                return;
            }
            notifySuccess('Post publicado!');
            cleanupPostMediaState();
            if (captionInput) captionInput.value = '';
            renderTray();
            resetFeed();
            loadFeed();
        };

        // Preferir estratégia local-first: não fazer upload no change
        try { if (picker._galleryHandler) { picker.removeEventListener('change', picker._galleryHandler); } } catch(_) {}
        try { setupLocalFirstGalleryUpload(root); } catch(_) {}

        const trayClick = (ev) => {
            // Bloqueia propagação para não acionar listeners globais que fecham a sidebar
            if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
            const btn = ev.target.closest('button[data-action]');
            if (btn) { handleReorderOrRemove(ev); return; }
            const card = ev.target.closest('[data-index]');
            if (!card || !tray.contains(card)) return;
            const idx = Number(card.dataset.index || '-1');
            if (!Number.isFinite(idx) || idx < 0) return;
            switchToMedia(idx);
        };
        if (tray._galleryHandler) { tray.removeEventListener('click', tray._galleryHandler); }
        tray.addEventListener('click', trayClick);
        tray._galleryHandler = trayClick;

        if (publishBtn) {
            publishBtn.classList.add('hidden'); // manter somente um botão visível
            if (publishBtn._galleryHandler) { publishBtn.removeEventListener('click', publishBtn._galleryHandler); }
            publishBtn.addEventListener('click', handlePublish);
            publishBtn._galleryHandler = handlePublish;
        }
        renderTray();

        // Se já houver itens (ex.: retorno ao editor), focar o primeiro
        if ((POST_MEDIA_STATE.items?.length||0) > 0 && POST_MEDIA_STATE.activeIndex == null) {
            switchToMedia(0);
        }
    }

    // Integra captura do editor -> adiciona na galeria automaticamente
    function setupEditorCaptureBridge(root = document) {
        // Redireciona para o modo local-first (sem upload imediato)
        try { if (window._editorCaptureHandler) window.removeEventListener('editor:capture', window._editorCaptureHandler); } catch(_) {}
        try { setupEditorCaptureBridgeLocal(root); } catch(_) {}
    }

    // Unifica o botão "Enviar" do editor ao fluxo da galeria
    function wireUnifiedSendFlow(root = document) {
        const btn = root.querySelector('#btnEnviar');
        if (!btn) return;
        if (btn._unifiedHandler) {
            try { btn.removeEventListener('click', btn._unifiedHandler, true); } catch(_) {}
        }

        const handler = async (ev) => {
            ev.preventDefault();
            if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
            if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();

            // Se houver itens na galeria, delega a publicação para o botão oculto
            const galleryItems = POST_MEDIA_STATE.items || [];
            if (Array.isArray(galleryItems) && galleryItems.length > 0) {
                const hiddenPublish = root.querySelector('#publishGalleryBtn');
                if (hiddenPublish) { hiddenPublish.click(); return; }
                try { initPostEditorGallery(root); setTimeout(() => root.querySelector('#publishGalleryBtn')?.click(), 0); } catch (_) {}
                return;
            }

            // Caso contrário, exporta o conteúdo atual (vídeo ou imagem) e publica como post único
            const editor = root.querySelector('#editor');
            const bg = editor?.querySelector('.bg-media');
            const hasVideoBg = !!bg && String(bg.tagName).toUpperCase() === 'VIDEO';
            const hasAnimated = !!editor?.querySelector('.item[data-anim]:not([data-anim="none"])');
            const isVideo = hasVideoBg || hasAnimated;

            try {
                let mediaDesc = null;
                if (isVideo) {
                    if (!window.EditorBridge?.exportVideoBlob) { notifyError('Exportação de vídeo indisponível.'); return; }
                    const videoBlob = await window.EditorBridge.exportVideoBlob();
                    if (!videoBlob) { notifyError('Falha ao gerar vídeo.'); return; }
                    const fd = new FormData();
                    fd.append('files[]', videoBlob, `post_${Date.now()}.webm`);
                    const up = await apiClient.upload('/posts/media', fd);
                    if (up?.error || up?.status === 'error') { notifyError(up?.message || 'Falha no upload do vídeo'); return; }
                    mediaDesc = Array.isArray(up?.media) ? up.media[0] : null;
                } else {
                    if (!window.EditorBridge?.renderFrame) { notifyError('Exportação de imagem indisponível.'); return; }
                    await window.EditorBridge.renderFrame();
                    const canvas = document.getElementById('outCanvas');
                    if (!canvas || !canvas.toBlob) { notifyError('Canvas de saída indisponível.'); return; }
                    let imgBlob = await new Promise((res)=> canvas.toBlob(res, 'image/jpeg', 0.9));
                    if (!imgBlob) { notifyError('Falha ao gerar imagem.'); return; }
                    const fd = new FormData();
                    fd.append('files[]', imgBlob, `post_${Date.now()}.jpg`);
                    const up = await apiClient.upload('/posts/media', fd);
                    if (up?.error || up?.status === 'error') { notifyError(up?.message || 'Falha no upload da imagem'); return; }
                    mediaDesc = Array.isArray(up?.media) ? up.media[0] : null;
                }

                if (!mediaDesc) { notifyError('Mídia não recebida.'); return; }

                const captionInput = root.querySelector('#postCaption');
                const caption = (captionInput?.value || '').toString().trim();

                const vt = String(viewType || '');
                const vd = viewData || null;
                const cm = (vt === ENTITY.TEAM && vd?.id) ? Number(vd.id) || 0 : 0;
                const em = (vt === ENTITY.BUSINESS && vd?.id) ? Number(vd.id) || 0 : 0;

                const tp = isVideo ? 'video' : 'image';
                const payload = { tp, cm, em, ct: { caption, media: [mediaDesc] } };
                const res = await apiClient.post('/posts', payload);
                if (res?.error || res?.status === 'error') { notifyError(res?.message || 'Não foi possível publicar.'); return; }
                notifySuccess('Post publicado!');
                resetFeed();
                loadFeed();
            } catch (err) {
                console.error('[unified-send] error', err);
                notifyError('Falha ao processar envio.');
            }
        };

        btn.addEventListener('click', handler, true);
        btn._unifiedHandler = handler;
    }

    function initializeImageCropperView(sidebarMount, cropData) {
        destroyImageCropper({ keepContext: true });
        if (!sidebarMount || !cropData?.imageDataUrl) return;

        sidebarMount.style.maxWidth = '100%';
        sidebarMount.style.overflowX = 'hidden';

        const container = sidebarMount.querySelector('[data-role="cropper-box"]');
        const imageEl = sidebarMount.querySelector('[data-role="cropper-image"]');
        const zoomInput = sidebarMount.querySelector('[data-role="cropper-zoom"]');
        const saveBtn = sidebarMount.querySelector('[data-action="crop-save"]');
        const cancelBtn = sidebarMount.querySelector('[data-action="crop-cancel"]');
        const messageContainer = sidebarMount.querySelector('[data-role="message"]') || document.getElementById('message');

        if (!container || !imageEl || !zoomInput || !saveBtn || !cancelBtn) {
            resetImageUploadState();
            return;
        }

        const aspectRatio = Number(cropData?.entityContext?.aspectRatio) || 1;
        const outputWidth = Number(cropData?.entityContext?.outputWidth) || 600;
        const outputHeight = Number(cropData?.entityContext?.outputHeight) || Math.round(outputWidth / aspectRatio) || 600;
        const isBackgroundCrop = cropData?.entityContext?.imageType === 'bk';

        container.style.position = 'relative';
        container.style.touchAction = 'none';
        container.style.width = '100%';
        container.style.maxWidth = '100%';
        container.style.aspectRatio = `${outputWidth} / ${outputHeight}`;
        if (isBackgroundCrop) {
            container.style.minHeight = '0px';
            container.style.height = 'auto';
        } else {
            container.style.minHeight = '160px';
            container.style.height = '';
        }
        imageEl.style.position = 'absolute';
        imageEl.draggable = false;

        const bounds = container.getBoundingClientRect();
        let viewportWidth = bounds.width || container.clientWidth || outputWidth;
        let viewportHeight = bounds.height || (viewportWidth / aspectRatio);
        if (!Number.isFinite(viewportHeight) || viewportHeight <= 0) {
            viewportHeight = outputHeight;
        }

        const onPointerDown = (event) => {
            const state = IMAGE_UPLOAD_STATE.cropper; // Get the latest state
            if (!state) return;
            event.preventDefault();
            state.dragging = true;
            state.pointerId = event.pointerId;
            state.startPointerX = event.clientX;
            state.startPointerY = event.clientY;
            state.startOffsetX = state.offsetX;
            state.startOffsetY = state.offsetY;
            container.setPointerCapture(event.pointerId);
        };

        const onPointerMove = (event) => {
            const state = IMAGE_UPLOAD_STATE.cropper; // Get the latest state
            if (!state || !state.dragging || event.pointerId !== state.pointerId) return;
            event.preventDefault();
            const deltaX = event.clientX - state.startPointerX;
            const deltaY = event.clientY - state.startPointerY;
            state.offsetX = state.startOffsetX + deltaX;
            state.offsetY = state.startOffsetY + deltaY;
            clampCropperOffsets(state);
            applyCropperState(state);
        };

        const onPointerUp = (event) => {
            const state = IMAGE_UPLOAD_STATE.cropper; // Get the latest state
            if (!state) return;
            if (event.pointerId && container.hasPointerCapture(event.pointerId)) {
                container.releasePointerCapture(event.pointerId);
            }
            state.dragging = false;
            state.pointerId = null;
        };

        const onZoom = (event) => {
            const state = IMAGE_UPLOAD_STATE.cropper; // Get the latest state
            if (!state) return;
            const newScale = parseFloat(event.target.value);
            if (!Number.isFinite(newScale)) return;
            const anchorX = state.viewportWidth / 2;
            const anchorY = state.viewportHeight / 2;
            setCropperScale(state, newScale, anchorX, anchorY);
        };

        const onSave = async (event) => {
            const state = IMAGE_UPLOAD_STATE.cropper; // Get the latest state
            if (!state) return;
            event.preventDefault();
            await handleCropSave(state);
        };

        const onCancel = (event) => {
            event.preventDefault();
            destroyImageCropper();
            SidebarNav.back();
        };

        container.addEventListener('pointerdown', onPointerDown);
        container.addEventListener('pointermove', onPointerMove);
        container.addEventListener('pointerup', onPointerUp);
        container.addEventListener('pointerleave', onPointerUp);
        zoomInput.addEventListener('input', onZoom);
        zoomInput.addEventListener('change', onZoom);
        saveBtn.addEventListener('click', onSave);
        cancelBtn.addEventListener('click', onCancel);

        IMAGE_UPLOAD_STATE.cropper = {
            container,
            imageEl,
            zoomInput,
            saveBtn,
            cancelBtn,
            messageContainer,
            entityContext: cropData.entityContext,
            imageDataUrl: cropData.imageDataUrl,
            fileName: cropData.fileName || 'imagem.png',
            fileType: cropData.fileType || 'image/png',
            naturalWidth: 0,
            naturalHeight: 0,
            viewportWidth,
            viewportHeight,
            aspectRatio,
            scale: 1,
            minScale: 1,
            maxScale: 4,
            offsetX: 0,
            offsetY: 0,
            dragging: false,
            pointerId: null,
            startPointerX: 0,
            startPointerY: 0,
            startOffsetX: 0,
            startOffsetY: 0,
            image: null,
            saving: false,
            handlers: { pointerDown: onPointerDown, pointerMove: onPointerMove, pointerUp: onPointerUp, zoom: onZoom, save: onSave, cancel: onCancel }
        };

        imageEl.src = cropData.imageDataUrl;

        const loader = new Image();
        loader.onload = () => {
            const currentState = IMAGE_UPLOAD_STATE.cropper;
            if (!currentState) return;
            currentState.image = loader;
            currentState.naturalWidth = loader.naturalWidth || 1;
            currentState.naturalHeight = loader.naturalHeight || 1;

            currentState.imageEl.style.transformOrigin = 'top left';
            currentState.imageEl.style.width = `${currentState.naturalWidth}px`;
            currentState.imageEl.style.height = `${currentState.naturalHeight}px`;
            currentState.imageEl.style.maxWidth = 'none';

            const latestBounds = currentState.container.getBoundingClientRect();
            currentState.viewportWidth = latestBounds.width || currentState.viewportWidth || 1;
            currentState.viewportHeight = latestBounds.height || currentState.viewportHeight || (currentState.viewportWidth / currentState.aspectRatio);

            const minScale = Math.max(
                currentState.viewportWidth / currentState.naturalWidth,
                currentState.viewportHeight / currentState.naturalHeight
            ) || 1;
            currentState.minScale = minScale;
            currentState.maxScale = minScale * 4;
            currentState.scale = minScale;
            currentState.offsetX = (currentState.viewportWidth - currentState.naturalWidth * currentState.scale) / 2;
            currentState.offsetY = (currentState.viewportHeight - currentState.naturalHeight * currentState.scale) / 2;
            clampCropperOffsets(currentState);
            applyCropperState(currentState);
            currentState.zoomInput.min = String(minScale);
            currentState.zoomInput.max = String(currentState.maxScale);
            currentState.zoomInput.step = '0.01';
            currentState.zoomInput.value = String(currentState.scale);
        };
        loader.src = cropData.imageDataUrl;
    } function destroyImageCropper({ keepContext = false } = {}) {
        const state = IMAGE_UPLOAD_STATE.cropper;
        if (!state) {
            if (!keepContext) resetImageUploadState();
            return;
        }

        const { container, handlers, zoomInput, saveBtn, cancelBtn } = state;
        if (container && handlers?.pointerDown) {
            container.removeEventListener('pointerdown', handlers.pointerDown);
            container.removeEventListener('pointermove', handlers.pointerMove);
            container.removeEventListener('pointerup', handlers.pointerUp);
            container.removeEventListener('pointerleave', handlers.pointerUp);
        }
        if (zoomInput && handlers?.zoom) {
            zoomInput.removeEventListener('input', handlers.zoom);
            zoomInput.removeEventListener('change', handlers.zoom);
        }
        if (saveBtn && handlers?.save) {
            saveBtn.removeEventListener('click', handlers.save);
        }
        if (cancelBtn && handlers?.cancel) {
            cancelBtn.removeEventListener('click', handlers.cancel);
        }

        IMAGE_UPLOAD_STATE.cropper = null;
        if (!keepContext) {
            resetImageUploadState();
        }
    }

    function clampCropperOffsets(state) {
        const displayWidth = state.naturalWidth * state.scale;
        const displayHeight = state.naturalHeight * state.scale;
        const viewportWidth = state.viewportWidth;
        const viewportHeight = state.viewportHeight;

        if (displayWidth <= viewportWidth) {
            state.offsetX = (viewportWidth - displayWidth) / 2;
        } else {
            const minX = viewportWidth - displayWidth;
            state.offsetX = Math.min(0, Math.max(minX, state.offsetX));
        }

        if (displayHeight <= viewportHeight) {
            state.offsetY = (viewportHeight - displayHeight) / 2;
        } else {
            const minY = viewportHeight - displayHeight;
            state.offsetY = Math.min(0, Math.max(minY, state.offsetY));
        }
    }

    function applyCropperState(state) {
        if (!state.imageEl) return;
        state.imageEl.style.transform = `translate(${state.offsetX}px, ${state.offsetY}px) scale(${state.scale})`;
    }

    function setCropperScale(state, newScale, anchorX, anchorY) {
        if (!state?.image) return;
        const scale = Math.max(state.minScale, Math.min(state.maxScale, newScale));
        if (!Number.isFinite(scale) || scale === state.scale) return;

        const displayWidth = state.naturalWidth * state.scale;
        const displayHeight = state.naturalHeight * state.scale;
        const relX = ((anchorX ?? state.viewportWidth / 2) - state.offsetX) / displayWidth;
        const relY = ((anchorY ?? state.viewportHeight / 2) - state.offsetY) / displayHeight;

        state.scale = scale;
        const newDisplayWidth = state.naturalWidth * state.scale;
        const newDisplayHeight = state.naturalHeight * state.scale;

        state.offsetX = (anchorX ?? state.viewportWidth / 2) - relX * newDisplayWidth;
        state.offsetY = (anchorY ?? state.viewportHeight / 2) - relY * newDisplayHeight;

        clampCropperOffsets(state);
        applyCropperState(state);
        state.zoomInput.value = String(state.scale);
    }

    async function finalizeImageCrop(state) {
        if (!state?.image) {
            throw new Error('Imagem não carregada.');
        }

        const { outputWidth = 600, outputHeight = 600 } = state.entityContext || {};
        const canvas = document.createElement('canvas');
        canvas.width = outputWidth;
        canvas.height = outputHeight;
        const ctx = canvas.getContext('2d');

        const displayWidth = state.naturalWidth * state.scale;
        const displayHeight = state.naturalHeight * state.scale;
        const scaleX = state.naturalWidth / displayWidth;
        const scaleY = state.naturalHeight / displayHeight;
        const visibleX = (0 - state.offsetX) * scaleX;
        const visibleY = (0 - state.offsetY) * scaleY;
        const visibleWidth = state.viewportWidth * scaleX;
        const visibleHeight = state.viewportHeight * scaleY;

        const sx = Math.max(0, visibleX);
        const sy = Math.max(0, visibleY);
        const sw = Math.min(state.naturalWidth - sx, visibleWidth);
        const sh = Math.min(state.naturalHeight - sy, visibleHeight);

        ctx.drawImage(state.image, sx, sy, sw, sh, 0, 0, outputWidth, outputHeight);

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Falha ao gerar a imagem.'));
                    return;
                }
                resolve(blob);
            }, 'image/png', 0.92);
        });
    }

    async function handleCropSave(state) {
        if (!state || state.saving) return;
        state.saving = true;
        setButtonLoading(state.saveBtn, true, 'Salvando...');

        try {
            const blob = await finalizeImageCrop(state);
            const extension = state.fileType && state.fileType.includes('png') ? 'png' : 'jpg';
            const safeName = (state.fileName || 'imagem').replace(/[^a-z0-9_-]/gi, '_').slice(-40);
            const imageType = state.entityContext?.imageType || 'im'; // Default to 'im'

            const uploadResult = await uploadEntityImage({
                entityType: state.entityContext.entityType,
                entityId: state.entityContext.entityId,
                imageType: imageType,
                blob,
                fileName: `${safeName || 'imagem'}.${extension}`
            });

            const imageUrl = uploadResult?.imageUrl || uploadResult?.url || uploadResult?.path;
            if (state.entityContext?.data) {
                if (imageType === 'bk') {
                    state.entityContext.data.bk = imageUrl || state.entityContext.data.bk;
                } else {
                    state.entityContext.data.im = imageUrl || state.entityContext.data.im;
                }
            }

            if (imageType === 'bk') {
                updateEntityBackgroundImageCache(state.entityContext.entityType, state.entityContext.entityId, imageUrl);
            } else {
                updateEntityImageCache(state.entityContext.entityType, state.entityContext.entityId, imageUrl);
            }

            const container = state.messageContainer || document.getElementById('message');
            if (container) {
                await showMessage(container, uploadResult?.message || 'Imagem atualizada com sucesso!', 'success', { dismissAfter: 2000 });
            }
            SidebarNav.back();
        } catch (error) {
            console.error('[image] upload error', error);
            const container = state?.messageContainer || document.getElementById('message');
            if (container) {
                await showMessage(container, error?.message || 'Não foi possível atualizar a imagem.', 'error', { dismissAfter: 6000 });
            }
        } finally {
            setButtonLoading(state.saveBtn, false);
            state.saving = false;
            destroyImageCropper();
        }
    }

    async function uploadEntityImage({ entityType, entityId, imageType = 'im', blob, fileName }) {
        const formData = new FormData();
        formData.append('entity_type', entityType);
        formData.append('entity_id', entityId);
        formData.append('image_type', imageType || 'im');
        formData.append('image', blob, fileName || 'imagem.png');

        const response = await apiClient.upload('/upload-image', formData);
        if (response?.error || response?.status === 'error') {
            throw new Error(response?.message || response?.error || 'Não foi possível salvar a imagem.');
        }
        return response;
    }

    function updateEntityImageCache(entityType, entityId, imageUrl) {
        if (!imageUrl) return;
        const numericId = Number(entityId);

        if (entityType === 'people') {
            if (currentUserData && Number(currentUserData.id) === numericId) {
                currentUserData.im = imageUrl;
            }
            if (viewData && viewType === ENTITY.PROFILE && Number(viewData?.id) === numericId) {
                viewData.im = imageUrl;
            }
        }

        if (entityType === 'businesses') {
            if (Array.isArray(userBusinessesData)) {
                userBusinessesData.forEach((item) => {
                    if (item && Number(item.id) === numericId) {
                        item.im = imageUrl;
                    }
                });
            }
            if (viewData && viewType === ENTITY.BUSINESS && Number(viewData?.id) === numericId) {
                viewData.im = imageUrl;
            }
        }

        if (entityType === 'teams') {
            if (Array.isArray(userTeamsData)) {
                userTeamsData.forEach((item) => {
                    if (item && Number(item.id) === numericId) {
                        item.im = imageUrl;
                    }
                });
            }
            if (viewData && viewType === ENTITY.TEAM && Number(viewData?.id) === numericId) {
                viewData.im = imageUrl;
            }
        }
    }

    // ===================================================================
    // 🏳️ TEMPLATES - Partes do HTML a ser renderizado
    // ===================================================================


    // =====================================================================
    // 2. TEMPLATE DEFINITIONS
    // =====================================================================

    const templates = {

        init: `            
            <div id="main-wrapper-init" class="w-full">
            </div>            
        `,

        // Nova versão do gatilho do editor com botão principal e atalhos
        editorTriggerV2: (currentUserData) => `
            <div class="w-full p-4 flex items-center gap-3 bg-gradient-to-r from-indigo-50 to-white border-b border-gray-100 rounded-t-3xl">
                <img class="page-thumb w-12 h-12 rounded-full pointer shadow-sm ring-2 ring-white" src="/images/no-image.jpg" alt="Avatar" />
                <button id="post-editor" class="flex-1 h-12 rounded-2xl pointer bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 flex items-center justify-center gap-2 shadow-md transition-colors" aria-label="Abrir editor">
                    <i class="fas fa-pen"></i>
                    <span>Abrir Editor</span>
                </button>
            </div>
            <div class="w-full p-3 grid grid-cols-3 gap-2">
                <button class="h-11 pointer rounded-2xl flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors" data-action="editor-quick-text" aria-label="Abrir editor com texto">
                    <i class="fas fa-font"></i>
                    <span class="text-sm font-medium">Texto</span>
                </button>
                <button class="h-11 pointer rounded-2xl flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors" data-action="editor-quick-media" aria-label="Abrir editor e adicionar mídia">
                    <i class="fas fa-image"></i>
                    <span class="text-sm font-medium">Mídia</span>
                </button>
                <button class="h-11 pointer rounded-2xl flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors" data-action="editor-quick-bg" aria-label="Abrir editor e definir plano de fundo">
                    <i class="fas fa-fill-drip"></i>
                    <span class="text-sm font-medium">Fundo</span>
                </button>
            </div>
        `,

        register: `
            <div id="message" class="w-full"></div>
            <form id="register-form">                
                <div class="mb-4">
                    <label for="register-name" class="block mb-1 text-sm font-medium text-white">Nome Completo</label>
                    <input type="text" id="register-name" name="name" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <div class="mb-4">
                    <label for="register-email" class="block mb-1 text-sm font-medium text-white">Email</label>
                    <input type="email" id="register-email" name="email" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <div class="mb-5">
                    <label for="register-password" class="block mb-1 text-sm font-medium text-white">Senha</label>
                    <input type="password" id="register-password" name="password" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <div class="mb-5">
                    <label for="register-password-repeat" class="block mb-1 text-sm font-medium text-white">Repita a Senha</label>
                    <input type="password" id="register-password-repeat" name="password-repeat" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-orange-500 shadow-lg text-white rounded-3xl hover:bg-orange-700 transition-colors">Criar Conta</button>
            </form>
            <p class="text-center text-sm mt-4 text-white">
                Já tem uma conta? 
                <a href="#" id="show-login-link" class="font-bold text-orange-600 hover:underline">Faça o login</a>
            </p>                
        `,

        login: `
            <div id="message" class="w-full"></div>
            <form id="login-form">                
                <div class="mb-4">
                    <label for="email" class="block mb-1 text-sm font-medium text-white">E-mail</label>
                    <input type="email" id="email" name="email" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <div class="mb-5">
                    <label for="password" class="block mb-1 text-sm font-medium text-white">Senha</label>
                    <input type="password" id="password" name="password" required class="w-full p-2 border-none shadow-lg rounded-3xl focus:ring-1 focus:ring-orange-400 focus:outline-none">
                </div>
                <button type="submit" class="w-full py-2 px-4 bg-orange-500 shadow-lg text-white rounded-3xl hover:bg-orange-700 transition-colors">Entrar</button>
            </form>
            <p class="text-center text-sm mt-4 text-white">
                Não tem uma conta? 
                <a href="#" id="show-register-link" class="font-bold text-orange-600 hover:underline">Registre-se</a>
            </p>
            <div class="relative my-6">                  
                <div class="relative flex justify-center text-sm"><span class="px-2 text-white">ou entre com</span></div>
            </div>
            <div class="flex flex-col gap-3">
                <button id="google-login-btn" class="w-full py-2 px-4 border-none shadow-lg rounded-3xl flex items-center justify-center gap-3 bg-gray-100 hover:bg-gray-200 transition-colors"><i class="fab fa-google text-red-500"></i> Entrar com Google</button>
                <button id="microsoft-login-btn" class="w-full py-2 px-4 border-none shadow-lg rounded-3xl flex items-center justify-center gap-3 bg-gray-100 hover:bg-gray-200 transition-colors"><i class="fab fa-microsoft text-blue-500"></i> Entrar com Microsoft</button>
            </div>
        `,

        message: (data = {}) => {
            const {
                message,
                type = 'success',
                autoDismiss = true,
                dismissAfter = 4000
            } = data;


            const styles = {
                success: {
                    bg: 'bg-emerald-100',
                    border: 'border-emerald-500',
                    text: 'text-emerald-700'
                },
                error: {
                    bg: 'bg-red-100',
                    border: 'border-red-500',
                    text: 'text-red-700'
                },
                warning: {
                    bg: 'bg-amber-100',
                    border: 'border-amber-500',
                    text: 'text-amber-700'
                }
            };

            const resolvedType = styles[type] ? type : 'error';
            const style = styles[resolvedType];
            const titleMap = { success: 'Sucesso', error: 'Erro', warning: 'Atenção' };
            const title = titleMap[resolvedType] || 'Aviso';

            const messageId = `message-${Date.now()}-${Math.random().toString(16).slice(2, 8)}`;
            const fallbackCopy = {
                success: 'Operação concluída com sucesso.',
                error: 'Algo não saiu como esperado.',
                warning: 'Verifique os dados informados.'
            };
            const resolvedMessage = (typeof message === 'string') ? message.trim() : '';
            const safeMessage = resolvedMessage || fallbackCopy[resolvedType] || fallbackCopy.error;


            if (autoDismiss && typeof window !== 'undefined') {
                const timeout = Number.isFinite(dismissAfter) && dismissAfter >= 0 ? dismissAfter : 4000;
                window.setTimeout(() => {
                    const element = document.querySelector(`[data-message-id="${messageId}"]`);
                    if (!element) return;

                    const removeElement = () => {
                        element.removeEventListener('transitionend', removeElement);
                        if (element.parentElement) element.parentElement.removeChild(element);
                    };

                    const animateAndRemove = () => {
                        element.addEventListener('transitionend', removeElement, { once: true });
                        element.style.opacity = '0';
                        element.style.transform = 'translateY(-6px)';
                        window.setTimeout(removeElement, 700);
                    };

                    if (typeof window.requestAnimationFrame === 'function') {
                        window.requestAnimationFrame(animateAndRemove);
                    } else {
                        animateAndRemove();
                    }
                }, timeout);
            }

            // The message div now takes full width and has some vertical margin.
            // It's not 'fixed' anymore, so it will appear within the natural flow of the sidebar content.
            return `
                <div data-message-id="${messageId}" class="w-full ${style.bg} ${style.border} ${style.text} border-l-4 p-4 rounded-lg shadow-md transition-all duration-500 ease-in-out my-3" role="alert" style="opacity: 1; transform: translateY(0);">
                    <p class="font-bold">${title}</p>
                    <p>${safeMessage}</p>
                </div>
            `;
        },

        notLoggedIn: `
            <div class="snap-center relative h-full w-full bg-gray-900">
				<div class="absolute top-0 left-0 right-0 bottom-0 overflow-hidden looping_zoom z-0" style="opacity: .7; background-image: url(https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
				<div class="w-full absolute bottom-0">
					<svg class="waves z-2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
						<defs>
						<path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
						</defs>
						<g class="parallax">
                            <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(245,245,245,0.7" />
                            <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(245,245,245,0.5)" />
                            <use xlink:href="#gentle-wave" x="48" y="4" fill="rgba(245,245,245,0.3)" />
                            <use xlink:href="#gentle-wave" x="48" y="4" fill="#F5F5F5" />
						</g>
					</svg>
					<div class="w-full p-8 bg-gray-100 content-center">
						<div class="text-center">
							<a  class=""><a>Workz!</a> © 2025</a><a class="gray"> (Stable 1.0.0)</a>
							<p><small class="" target="_blank">Desenvolvido por <a href="/profile/guisantana" target="_blank" class="font-semibold">Guilherme Santana</a></small></p>
						</div>
					</div>
				</div>
				<div class="absolute h-full w-full m-0 p-0 z-0">
					<div class="h-full max-w-screen-xl mx-auto m-0 p-8 grid grid-rows-12 grid-cols-12">
						<div class="w-full row-span-1 col-span-12 content-center">
							<img title="Workz!" src="/images/icons/workz_wh/145x60.png"></img>
						</div>
						<div id="login" class="px-30 row-span-9 col-span-12 sm:col-span-6 md:col-span-4 content-center justify-center"></div>
					</div>
				</div>
			</div>
			<div class="relative w-full bg-gray-100 z-3 clear">
				<div class="max-w-screen-xl mx-auto grid grid-cols-12">
					<div class="col-span-12 sm:col-span-8 lg:col-span-9 flex flex-col grid grid-cols-12 gap-x-6">
						<div id="timeline" class="col-span-12 flex flex-col grid grid-cols-12 gap-6 pt-6"></div>
                        <div id="feed-sentinel" class="h-10"></div>
					</div>
				</div>				
			</div>
        `,

        dashboard: ` 
            <div id="topbar" class="fixed w-full z-3 content-center">
                <div class="max-w-screen-xl mx-auto p-6 flex items-center justify-between">
                    <a href="/">
                        <!--img class="logo-menu" style="width: 145px; height: 76px;" title="Workz!" src="/images/logos/workz/145x76.png"-->
                    </a>
                    <button id="sidebarTrigger" data-sidebar-action="settings"><img class="page-thumb h-11 w-11 shadow-lg object-cover rounded-full" src="/images/no-image.jpg" /></button>
                </div>
            </div>                                         
            <div id="workz-content" class="max-w-screen-xl mx-auto clearfix grid grid-cols-12 gap-6">
            </div>        
        `,

        workzContent: `
            <div class="col-span-12 rounded-b-3xl h-48 bg-gray-200 bg-cover bg-center"></div>
            <div class="col-span-12 clearfix grid grid-cols-12 gap-6 mx-4 sm:mx-6">
                <div class="col-span-12 sm:col-span-8 lg:col-span-9 flex flex-col grid grid-cols-12 gap-x-6 -mt-24">
                    <!-- Coluna da Esquerda (Menu de Navegação) -->
                    <aside class="hidden sm:flex w-full flex col-span-4 lg:col-span-3 flex-col gap-y-6">                                        
                        <div class="aspect-square w-full rounded-full shadow-lg border-4 border-white overflow-hidden">                        
                            <img id="profile-image" class="w-full h-full object-cover" src="${resolveImageSrc(currentUserData?.im, currentUserData?.tt, { size: 240 })}" alt="${currentUserData?.tt}">                        
                        </div>
                        <div class="bg-white p-3 rounded-3xl font-semibold shadow-lg grow">
                            <nav class="mt-1">
                                <ul id="custom-menu" class="space-y-2"></ul>
                            </nav>
                            <hr class="mt-3 mb-3">
                            <nav class="mb-1">
                                <ul id="standard-menu" class="space-y-2">
                                    <li><button data-action="list-people" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-user-friends fa-stack-1x text-gray-700"></i></span><span class="truncate">Pessoas</span></button></li>
                                    <li><button data-action="list-businesses" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-briefcase fa-stack-1x text-gray-700"></i></span><span class="truncate">Negócios</span></button></li>
                                    <li><button data-action="list-teams" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-users fa-stack-1x text-gray-700"></i></span><span class="truncate">Equipes</span></button></li>
                                    <li><button data-action="logout" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-sign-out-alt fa-stack-1x text-gray-700"></i></span><span class="truncate">Sair</span></button></li>
                                </ul>
                            </nav>
                        </div>
                    </aside>
                    <!-- Coluna do Meio (Conteúdo Principal) -->
                    <main class="col-span-12 sm:col-span-8 lg:col-span-9 flex-col relative space-y-6">                        
                        <div id="main-content" class="w-full"></div>
                        <div id="editor-trigger" class="shadow-lg w-full bg-white rounded-3xl text-center"></div>
                    </main>
                    <!-- Feed de Publicações -->
                    <div id="timeline" class="col-span-12 flex flex-col grid grid-cols-12 gap-6 pt-6"></div>
                    <div id="feed-sentinel" class="h-10"></div>
                </div>
                <aside id="widget-wrapper" class="hidden sm:flex col-span-12 sm:col-span-4 lg:col-span-3 flex-col gap-y-6 -mt-24">
                </aside>
            </div>
        `,

        mainContent: `
            <div class="w-full grid grid-cols-12 gap-6 rounded-3xl p-4 bg-white" style="background-image: url(https://bing.biturl.top/?resolution=1366&amp;format=image&amp;index=0&amp;mkt=en-US); background-position: center; background-repeat: no-repeat; background-size: cover;">
                <div class="col-span-12 grid grid-cols-12 gap-4">
                    <div class="col-span-12 text-white font-bold content-center text-shadow-lg flex items-center justify-between">
                        <div id="wClock" class="text-md">00:00</div>
                        <button id="apps-menu-trigger" class="shrink-0 rounded-full bg-white/20 hover:bg-white/30 text-white p-2 transition" title="Abrir biblioteca de aplicativos" aria-label="Abrir biblioteca de aplicativos">
                            <i class="fas fa-th"></i>
                        </button>
                    </div>
                    <div id="app-library" class="col-span-12 lg:col-span-9 flex-col space-y-4"></div>
                    <div id="app-widget-container" class="hidden lg:block lg:col-span-3 flex-col bg-black/20 rounded-[2rem] p-3 backdrop-blur-md"></div>
                </div>
            </div>
        `,

        editorTrigger: (currentUserData) => `
            <div class="w-full p-3 border-b-2 border-gray-100 flex items-center gap-3">
                <img class="page-thumb w-11 h-11 rounded-full pointer" src="/images/no-image.jpg" />
                <div id="post-editor" class="flex-1 rounded-3xl h-11 pointer text-gray-500 px-4 text-left bg-gray-100 hover:bg-gray-200 flex items-center overflow-hidden whitespace-nowrap truncate">
                    <a class="block w-full overflow-hidden whitespace-nowrap truncate">O que você está pensando, ${currentUserData.tt.split(' ')[0]}?</a>
                </div>
            </div>                                                       
            <div class="w-full p-3 grid grid-cols-2 gap-1">                
                <div class="h-11 pointer rounded-l-3xl rounded-r-lg flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200">
                    <i class="fas fa-video"></i>
                    <a class="text-center">Vídeo</a>
                </div>
                <div class="h-11 pointer rounded-r-3xl rounded-l-lg flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200">
                    <i class="fas fa-newspaper"></i>
                    <a class="text-center">Notícia</a>
                </div>                
            </div>
        `,

        dashboardMain: (currentUserData) => `
            <div class="bg-white p-4 rounded-3xl shadow-lg mb-6 ">                
                <div id="app-launcher-list" class="flex flex-wrap gap-4">
                    <!-- Ícones das Apps serão gerados aqui -->
                </div>
            </div>
            <!-- Caixa de Criar Publicação -->
            <div id="post-container" class="bg-white p-4 rounded-3xl shadow-lg mb-6">
                <textarea class="w-full p-2 border border-gray-200 rounded-md resize-none" rows="3" placeholder="O que você quer publicar, ${currentUserData.name.split(' ')[0]}?"></textarea>
                <div class="flex justify-end mt-2">
                    <button class="bg-blue-600 text-white font-semibold py-2 px-5 rounded-md hover:bg-blue-700">Publicar</button>
                </div>
            </div>
        `,

        sidebarBusinessSettings: (businessData) => `
            <div id="voltar-main-menu" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">                
                <i class="fas fa-chevron-left"></i>
                <a>Ajustes</a>
            </div>
            <h1 class="text-center text-gray-500 text-xl font-bold">${businessData.name}</h1>
            <div class="col-span-1 justify-center">
                <img id="sidebar-profile-image" data-role="entity-image" class="sm:w-1/4 md:w-1/6 lg:w-1/6 shadow-lg cursor-pointer rounded-full mx-auto object-cover" src="${resolveImageSrc(businessData.im, businessData.name, { size: 160 })}" alt="Foto do Utilizador">
            </div>
            <form id="settings-form">
                <div class="w-full shadow-lg rounded-2xl grid grid-cols-1">
                    <div class="rounded-t-2xl border-b-2 border-black-500 bg-white grid grid-cols-4">
                        <label for="name" class="col-span-1 p-4 truncate text-gray-500">Nome*</label>
                        <input class="border-none focus:outline-none flex col-span-3 rounded-tr-2xl p-4" type="text" id="name" name="name" value="${businessData.name}" required>
                    </div>
                    <div class="rounded-b-2xl border-b-2 border-black-500 bg-white grid grid-cols-4">
                        <label for="description" class="col-span-1 p-4 truncate text-gray-500">Descrição</label>
                        <textarea class="border-none focus:outline-none flex col-span-3 rounded-br-2xl p-4" type="text" id="description" name="description">${businessData.description}</textarea>
                    </div>                                   
                </div>
                <button type="submit" class="mt-6 w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
            </form>
            <hr>
            <div class="mt-6">
                <h2 class="text-lg font-bold mb-4">Gerenciar Negócio</h2>
                <p class="text-sm text-gray-600 mb-2">Você pode gerenciar as configurações do seu negócio aqui.</p>
                <button id="manage-members-btn-cf" class="w-full py-2 px-4 bg-green-500 text-white font-semibold rounded-3xl hover:bg-green-700 transition-colors">Gerir Membros</button>
            </div>        
        `,

        membersManagement: (businessData) => `
            <div id="voltar-main-menu" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">
                <i class="fas fa-chevron-left"></i>
                <a>${businessData.name}</a>
            </div>
            <h1 class="text-center text-gray-500 text-xl font-bold mb-4">Gerenciar Membros de ${businessData.name}</h1>
            <div class="w-full shadow-lg rounded-2xl p-4 bg-white">
                <h2 class="text-lg font-bold mb-4">Membros Atuais</h2>
                <ul id="members-list" class="space-y-2"></ul>
                <h2 class="text-lg font-bold mt-6 mb-4">Solicitações Pendentes</h2>
                <ul id="pending-requests-list" class="space-y-2"></ul>
            </div>
        `,

        listView: (payload = {}) => {
            // Backward compatibility: previous callers passed an array of items
            let type = 'people';
            let items = [];
            if (Array.isArray(payload)) { items = payload; }
            else { ({ type = 'people', items = [] } = payload || {}); }

            // Infer type from path when not provided
            try {
                if (!payload || Array.isArray(payload) || !payload.type) {
                    const p = (window.location && window.location.pathname) || '';
                    if (/^\/people$/.test(p)) type = 'people';
                    else if (/^\/businesses$/.test(p)) type = 'businesses';
                    else if (/^\/teams$/.test(p)) type = 'teams';
                }
            } catch (_) {}

            const title = type === 'people' ? 'Pessoas' : type === 'teams' ? 'Equipes' : 'Negócios';
            const icon  = type === 'people' ? 'fas fa-user-friends' : type === 'teams' ? 'fas fa-users' : 'fas fa-briefcase';
            const searchId = `${type}-search`;

            let html = '';
            html += '<div class="col-span-12 clearfix grid grid-cols-12 gap-6 mx-6 mt-28">'
            // Header + search
            html += `
            <div class="col-span-12 mb-2">
                <div class="bg-white p-3 rounded-3xl shadow-lg flex items-center gap-3">
                    <span class="fa-stack text-gray-200"><i class="fas fa-circle fa-stack-2x"></i><i class="${icon} fa-stack-1x text-gray-700"></i></span>
                    <h1 class="text-lg font-bold text-gray-800 flex-none">${title}</h1>
                    <div class="flex-1"></div>
                    <div class="w-full max-w-md">
                        <input id="${searchId}" type="search" class="w-full rounded-3xl bg-gray-100 focus:bg-white focus:outline-none px-4 py-2"
                               placeholder="Pesquisar ${title.toLowerCase()}..." />
                    </div>
                    <span id="list-count" class="ml-3 text-sm text-gray-500 whitespace-nowrap"></span>
                </div>
            </div>`;

            // Grid list            

            html += '<div id="list-grid" class="col-span-12 flex flex-col grid grid-cols-12 gap-6" style="list-style: none;">';

            if (!Array.isArray(items) || items.length === 0) {
                html += `<div class="col-span-12"><div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Nenhum item encontrado.</div></div>`;
            } else {
                items.forEach(item => {
                    const name = (item.tt || '');
                    html += `
                    <div class="list-item sm:col-span-12 md:col-span-6 lg:col-span-4 flex flex-col bg-white p-3 rounded-3xl shadow-lg bg-gray hover:bg-gray-100 cursor-pointer" style="list-style: none;" data-item-id="${item.id}" data-name="${name.toLowerCase()}">
                        <div class="flex items-center gap-3">
                            <img class="w-10 h-10 rounded-full object-cover" src="${resolveImageSrc(item?.im, name, { size: 80 })}" alt="${name}">
                            <span class="font-semibold truncate">${name}</span>
                        </div>
                    </div>`;
                });
            }

            html += '</div>';
            html += '<div id="list-sentinel" class="h-10"></div>';
            html += '</div>';
            return html;
        }
    }

    // Removido: lógica antiga baseada em sidebarHistory/sidebar-back
    templates.entityContent = async ({ data }) => {
        // Fallback for cover image - using a dynamic placeholder
        const coverUrl = data.cover || `https://source.unsplash.com/1600x900/?abstract,${viewType},${data.id}`;

        let statsHtml = '';
        if (viewType === 'profile') {
            statsHtml = `
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.postsCount || 0}</span>
                    <span>Publicações</span>
                </div>
                <div class="flex items-center gap-1">
                    <span id="followers-count" class="font-bold text-gray-800">${data.followersCount || 0}</span>
                    <span>Seguidores</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.peopleCount || 0}</span>
                    <span>Seguindo</span>
                </div>
            `;
        } else if (viewType === 'business') {
            statsHtml = `
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.postsCount || 0}</span>
                    <span>Publicações</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.peopleCount || 0}</span>
                    <span>Membros</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.teamsCount || 0}</span>
                    <span>Equipes</span>
                </div>
            `;
        } else if (viewType === 'team') {
            statsHtml = `
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.postsCount || 0}</span>
                    <span>Publicações</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="font-bold text-gray-800">${data.peopleCount || 0}</span>
                    <span>Membros</span>
                </div>
            `;
        }

        const content = `
            <div class="w-full bg-gray-100 rounded-t-3xl shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1),0_-4px_6px_-2px_rgba(0,0,0,0.05)]" overflow-hidden">                
                <!-- Profile Info -->
                <div class="p-4 sm:p-6">                    
                    <div class="mt-4">
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">${data.tt}</h1>
                        ${data.un ? `<p class="text-sm text-gray-500 mt-1">@${data.un}</p>` : ''}
                    </div>

                    ${data.cf ? `<p class="mt-4 text-gray-700">${data.cf}</p>` : ''}

                    <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600">
                        ${statsHtml}
                    </div>
                </div>
            </div>
        `;

        return content;
    };

    // Mensagem para equipe sem acesso ao negócio correspondente
    templates.teamRestricted = () => `
        <div class="rounded-3xl w-full p-4 bg-white shadow-lg">
            <div class="p-3 text-sm text-gray-700">
                Você não participa do negócio desta equipe. Solicite acesso ao negócio para visualizar o conteúdo.
            </div>
        </div>
    `;

    // Mensagem para paginas publicas que exigem login (page_privacy != 1)
    templates.pageRestricted = () => `
        <div class="rounded-3xl w-full p-4 bg-white shadow-lg">
            <div class="p-3 text-sm text-gray-700">
                Esta pagina esta disponivel apenas para usuarios logados.
            </div>
            <div class="p-3">
                <button data-action="dashboard" class="w-full h-11 rounded-3xl bg-orange-600 text-white font-semibold hover:bg-orange-700 transition-colors">Fazer login</button>
            </div>
        </div>
    `;

    templates.appLibrary = async ({ appsList }) => {
        const resolved = Array.isArray(appsList) ? appsList : (appsList ? [appsList] : []);
        // Store tile (always visible)
        const storeItem = `
            <button data-store="1" data-app-name="loja" title="Workz! Store" class="">
                <div class="w-full aspect-square cursor-pointer rounded-full shadow-md">
                    <img src="/images/apps/store.jpg" alt="Workz! Store"class="app-icon-image rounded-full">
                </div>                
            </button>`;

        const appItems = resolved.map(app => `
            <button data-app-id="${app.id}" data-app-name="${(app.tt || 'App').toLowerCase()}" class="app-item-button">
                <div class="w-full aspect-square cursor-pointer rounded-full shadow-md">
                    <img src="${resolveImageSrc(app?.im, app?.tt, { fallbackUrl: '/images/app-default.png', size: 160 })}" alt="${app.tt || 'App'}" class="app-icon-image rounded-full">
                </div>
                <div class="w-full p-1 text-xs text-white text-shadow-lg truncate text-center">
                    ${app.tt || 'App'}
                </div>
            </button>
        `).join('');

        return `
            <div id="app-grid-container" class="bg-black/20 rounded-[2rem] p-3 backdrop-blur-md">
                <div class="mb-6">
                    <input type="text" id="app-search-input" placeholder="Buscar aplicativos..." class="w-full px-4 py-2 rounded-full border-0 bg-white/15 text-white text-base outline-none transition-all duration-200 ease-in-out placeholder:text-white/70 focus:bg-white/25 focus:shadow-[0_0_0_2px_rgba(251,146,60,0.5)]">
                </div>
                <div id="app-grid" class="grid grid-cols-4 lg:grid-cols-6 gap-3 h-auto overflow-y-auto scroll-snap-y-mandatory [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                    ${appItems}
                </div>
            </div>
            <div id="app-quickbar" class="bg-black/20 rounded-full p-3 backdrop-blur-md">
                <div id="quickbar-track" class="flex items-center justify-center gap-3 overflow-x-auto snap-x snap-mandatory [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden" role="listbox" aria-label="Acesso rápido a apps">
                    <!-- itens do acesso rápido são injetados pelo JS em initAppLibrary() -->
                </div>
            </div>
        `;
    };

    // Classes padronizadas para itens de menu/botões
    const CLASSNAMES = {
        menuItem: 'cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center',
        actionBtn: 'cursor-pointer text-center rounded-3xl text-white transition-colors truncate w-full p-2 mb-1'
    };

    const CONTACT_OPTIONS = [
        { value: '', label: 'Contato', disabled: true, placeholder: true },
        { value: 'email', label: 'E-mail' },
        { value: 'phone', label: 'Telefone' },
        { value: 'site', label: 'Site' },
        { value: 'behance', label: 'Behance' },
        { value: 'discord', label: 'Discord' },
        { value: 'facebook', label: 'Facebook' },
        { value: 'flickr', label: 'Flickr' },
        { value: 'instagram', label: 'Instagram' },
        { value: 'linkedin', label: 'LinkedIn' },
        { value: 'pinterest', label: 'Pinterest' },
        { value: 'reddit', label: 'Reddit' },
        { value: 'snapchat', label: 'Snapchat' },
        { value: 'tiktok', label: 'TikTok' },
        { value: 'tumblr', label: 'Tumblr' },
        { value: 'twitch', label: 'Twitch' },
        { value: 'twitter', label: 'X / Twitter' },
        { value: 'vimeo', label: 'Vimeo' },
        { value: 'wechat', label: 'WeChat' },
        { value: 'whatsapp', label: 'WhatsApp' },
        { value: 'youtube', label: 'YouTube' },
        { value: 'other', label: 'Outro' }
    ];

    const COUNTRY_OPTIONS = [
        { value: '', label: 'Selecione', disabled: true, placeholder: true },
        { value: 'Brasil', label: 'Brasil' }
    ];



    // =====================================================================
    // 3. UI CONSTRUCTION HELPERS
    // =====================================================================

    const UI = {
        renderHeader: ({ backAction = 'page-settings', backLabel, title }) => `
            <div data-sidebar-action="${backAction}" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">
                <i class="fas fa-chevron-left"></i>
                <a>${backLabel ?? 'Voltar'}</a>
            </div>
            <h1 class="text-center text-gray-500 text-xl font-bold">${title}</h1>
            <div id="message" class="w-full absolute"></div>
        `,
        renderCloseHeader: () => `
            <div id="close" data-sidebar-action="settings" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">
                <a>Fechar</a>
                <i class="fas fa-chevron-right"></i>                
            </div>
        `,
        renderHero: ({ tt, im }) => {
            const heroSrc = resolveImageSrc(im, tt, { size: 220 });
            return `
            <div class="col-span-1 justify-center">
                <img id="sidebar-profile-image" data-role="entity-image" class="w-32 h-32 shadow-lg cursor-pointer rounded-full mx-auto object-cover" src="${heroSrc}" alt="${tt ?? 'Imagem'}">
            </div>
        `;
        },
        sectionCard: (content, { roundedTop = true, roundedBottom = true } = {}) => `
            <div class="w-full shadow-md rounded-2xl grid grid-cols-1 overflow-hidden bg-white">
                ${content}
            </div>
        `,
        // Ícone FontAwesome em pilha padronizado
        fa: (icon) => `
            <span class="fa-stack text-gray-200 mr-1">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas ${icon} fa-stack-1x text-gray-700"></i>
            </span>
        `,
        // Item de menu padronizado
        menuItem: ({ action, icon, label }) => `
            <li>
                <button data-action="${action}" class="${CLASSNAMES.menuItem}">
                    ${UI.fa(icon)}
                    <span class="truncate">${label}</span>
                </button>
            </li>
        `,
        // Botão de ação padronizado (para sidebar/actions)
        actionButton: ({ action, label, color = 'blue', extra = '' }) => `
            <button data-action="${action}" class="${CLASSNAMES.actionBtn} bg-${color}-400 hover:bg-${color}-600 ${extra}"><span class="truncate">${label}</span></button>
        `,
        row: (id, label, inputHtml, { top = false, bottom = false } = {}) => `
            <div class="grid grid-cols-4 border-b border-gray-200 ${top ? 'rounded-t-2xl' : ''} ${bottom ? 'rounded-b-2xl' : ''}">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <div class="col-span-3 p-4">
                ${inputHtml}
                </div>
            </div>
        `,
        rowTextarea: (id, label, value = '') => `
            <div class="grid grid-cols-4">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <textarea id="${id}" name="${id}" class="border-0 focus:outline-none col-span-3 p-4 min-h-[120px] rounded-r-2xl">${value ?? ''}</textarea>
            </div>
        `,
        rowSelect: (id, label, optionsHtml, { top = false, bottom = false } = {}) => `
            <div class="grid grid-cols-4 border-b border-gray-200 ${top ? 'rounded-t-2xl' : ''} ${bottom ? 'rounded-b-2xl' : ''}">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <select id="${id}" name="${id}" class="border-0 focus:outline-none col-span-3 p-4">
                ${optionsHtml}
                </select>
            </div>
        `,
        contactOptions: (selected = '') => CONTACT_OPTIONS.map(({ value, label, disabled, placeholder }) => {
            const isSelected = placeholder ? (selected === null || selected === undefined || selected === '') : selected === value;
            const disabledAttr = disabled ? 'disabled' : '';
            const selectedAttr = isSelected ? 'selected' : '';
            const placeholderClass = placeholder ? 'text-gray-500' : '';
            return `<option value="${value}" class="${placeholderClass}" ${disabledAttr} ${selectedAttr}>${label}</option>`;
        }).join(''),
        countryOptions: (selected = '') => {
            let normalizedSelected = (selected ?? '').toString().trim().toLowerCase();
            if (normalizedSelected === 'brazil') normalizedSelected = 'brasil';
            const aliasMap = new Map([
                ['br', 'brasil'],
                ['brasil', 'br']
            ]);
            return COUNTRY_OPTIONS.map(({ value, label, disabled, placeholder }) => {
                const normalizedValue = (value ?? '').toString().trim().toLowerCase();
                const placeholderClass = placeholder ? 'text-gray-500' : '';
                const disabledAttr = disabled ? 'disabled' : '';
                let isSelected;
                if (placeholder) {
                    isSelected = !normalizedSelected;
                } else {
                    isSelected = normalizedSelected && (normalizedSelected === normalizedValue || aliasMap.get(normalizedSelected) === normalizedValue);
                }
                const selectedAttr = isSelected ? 'selected' : '';
                return `<option value="${value}" class="${placeholderClass}" ${disabledAttr} ${selectedAttr}>${label}</option>`;
            }).join('');
        },
        contactBlock: (contacts = null) => {
            let parsed = [];
            let fallbackValue = '';

            if (Array.isArray(contacts)) {
                parsed = normalizeContacts(contacts);
            } else if (typeof contacts === 'string') {
                const trimmed = contacts.trim();
                if (trimmed.startsWith('[')) {
                    parsed = normalizeContacts(trimmed);
                    if (!parsed.length && trimmed && trimmed !== '[]') {
                        fallbackValue = trimmed;
                    }
                } else if (trimmed) {
                    fallbackValue = trimmed;
                }
            } else if (contacts) {
                parsed = normalizeContacts(contacts);
            }

            if (!Array.isArray(parsed) || parsed.length === 0) {
                parsed = fallbackValue ? [{ type: '', value: fallbackValue }] : [{ type: '', value: '' }];
            }

            const rows = parsed.map((contact, index) => {
                const isFirst = index === 0;
                const isLast = index === parsed.length - 1;
                const borderClass = isLast ? '' : 'border-b border-gray-100';
                return `
                    <div title="Link" class="${isFirst ? 'rounded-t-2xl' : ''} bg-white grid grid-cols-6 ${borderClass}" data-input-id="${index}">
                        <input class="${isFirst ? 'rounded-tl-2xl' : ''} border-0 focus:outline-none col-span-2 p-4" type="text" name="url_type" value="${contact.type || ''}">
                        <input class="border-0 focus:outline-none col-span-4 ${isFirst ? 'rounded-tr-2xl' : ''} p-4" type="text" name="url_value" value="${contact.value || ''}">
                    </div>
                `;
            }).join('');

            return `
                <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
                    <div id="input-container" class="rounded-t-2xl w-full">
                        ${rows}
                    </div>
                    <div id="addButtonContainer" class="grid grid-cols-2 rounded-b-2xl border-t border-gray-200 bg-white">
                        <div id="add-input-button" class="col-span-1 p-3 bg-gray-100 hover:bg-gray-200 cursor-pointer text-center rounded-bl-2xl"><i class="fas fa-plus centered"></i></div>
                        <div id="remove-input-button" class="col-span-1 p-3 bg-gray-100 hover:bg-gray-200 cursor-pointer text-center rounded-br-2xl"><i class="fas fa-minus centered"></i></div>
                    </div>
                </div>
            `;
        },
        privacyRowsProfile: ({ page_privacy, feed_privacy }) => {
            const pageOpts = `
            <option value="" ${page_privacy == null ? 'selected' : ''} disabled>Selecione</option>
            <option value="0" ${page_privacy === 0 ? 'selected' : ''}>Usuários logados</option>
            <option value="1" ${page_privacy === 1 ? 'selected' : ''}>Toda a internet</option>
            `;
            const feedOpts = `
            <option value="" ${feed_privacy == null ? 'selected' : ''} disabled>Selecione</option>
            <option value="0" ${feed_privacy === 0 ? 'selected' : ''}>Somente eu</option>
            <option value="1" ${feed_privacy === 1 ? 'selected' : ''}>Seguidores</option>
            <option value="2" ${feed_privacy === 2 ? 'selected' : ''}>Usuários logados</option>
            <option value="3" ${feed_privacy === 3 && page_privacy > 0 ? 'selected' : ''} ${page_privacy < 1 ? 'disabled' : ''}>Toda a internet</option>
            `;
            return UI.sectionCard(
                UI.rowSelect('page_privacy', 'Página', pageOpts, { top: true }) +
                UI.rowSelect('feed_privacy', 'Conteúdo', feedOpts, { bottom: true })
            );
        },
        shortcutItem: (id, icon, label, color = 'gray', { roundedTop = false, roundedBottom = false } = {}) => `
        <div id="${id}" title="${label}" class="${roundedTop ? 'rounded-t-2xl' : ''} ${roundedBottom ? 'rounded-b-2xl' : 'border-b'} bg-${(color !== 'gray') ? color + '-200' : 'white'} text-${color}-700 p-3 cursor-pointer hover:bg-${(color !== 'gray') ? color + '-300' : 'white/50'} transition-all duration-300 ease-in-out">
            <span class="fa-stack">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas ${icon} fa-stack-1x fa-inverse"></i>
            </span>
            ${label}
        </div>
        `,
        shortcutList: (items = []) => `
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            ${items.map((it, i) => UI.shortcutItem(it.id, it.icon, it.label, it.color, {
            roundedTop: i === 0,
            roundedBottom: i === items.length - 1
        })).join('')}
        </div>
        `,
        signature: () => `
        <div class="text-center border-t border-gray-200 grid grid-cols-1 gap-1 py-4">
            <img class="mx-auto" src="https://guilhermesantana.com.br/images/50x50.png" style="height: 40px; width: 40px" alt="meSan"></img>
            <a href="https://guilhermesantana.com.br" target="_blank">Guilherme Santana © 2025</a>
        </div>
        `
    };

    templates.sidebarPageSettings = async ({ view = null, data = null, type = null, origin = null, prevTitle = null, navTitle = null, crop = null }) => {

        const personalizationCard = (d) => {
            const hasBk = d?.bk;
            const changeLabel = hasBk ? 'Substituir' : 'Adicionar';
            let removeButton = '';
            if (hasBk) {
                removeButton = `<button data-action="remove-background" class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-sm flex items-center justify-center gap-2"><i class="fas fa-trash-alt"></i> Remover</button>`;
            }
            const gridCols = hasBk ? 'grid-cols-2' : 'grid-cols-1';
            return `
                <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
                    <div class="p-3 bg-white rounded-t-2xl font-semibold border-b border-gray-200">                            
                        <img src="${hasBk ? hasBk : '#'}" class="h-auto w-full rounded-xl object-cover"></img>
                    </div>
                    <div class="p-3 bg-white rounded-b-2xl grid ${gridCols} gap-2">
                        <button data-action="change-background" class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-sm flex items-center justify-center gap-2"><i class="fas fa-image"></i> ${changeLabel}</button>
                        ${removeButton}
                    </div>
                </div>
            `;
        };

        const sidebarContent = document.querySelector('.sidebar-content');
        if (sidebarContent) sidebarContent.dataset.currentView = view || 'root';
        let html = '';

        // Cabeçalhos unificados
        const titles = {
            'profile': data?.tt ?? '',
            'business': data?.tt ?? '',
            'team': data?.tt ?? '',
            'people': 'Pessoas Seguidas',
            'businesses': 'Negócios Gerenciados',
            'teams': 'Equipes Gerenciadas',
            'employees': 'Colaboradores',
            'testimonials': 'Depoimentos',
            'billing': 'Cobrança e Recebimento',
            'transactions': 'Transações',
            'subscriptions': 'Assinaturas',
            'user-education': 'Formação Acadêmica',
            'user-jobs': 'Experiência Profissional',
            // 'business-shareholding': 'Estrutura Societária' // removido por ora
            'apps': 'Aplicativos',
            'password': 'Alterar Senha'
        };

        const headerBackLabel = (view !== null) ? (([ENTITY.PROFILE, ENTITY.BUSINESS, ENTITY.TEAM].includes(view)) ? 'Ajustes' : (view.startsWith('user-') ? (currentUserData?.tt ?? '') : (data?.tt ?? ''))) : 'Fechar';

        const financeShortcuts = UI.shortcutList([
            { id: 'billing', icon: 'fa-money-bill', label: 'Cobrança e Recebimento' },
            { id: 'transactions', icon: 'fa-receipt', label: 'Transações' },
            { id: 'subscriptions', icon: 'fa-satellite-dish', label: 'Assinaturas' }
        ]);

        // Cabeçalho e navegação com suporte a stack
        const isStack = (origin === 'stack');
        if (view === 'image-crop') {
            const cropData = crop ?? {};
            const previewSrc = cropData.imageDataUrl || resolveImageSrc(data?.im, data?.tt, { size: 320 });
            const fileLabel = cropData.fileName ? `<span class="text-xs text-gray-500 truncate max-w-full">${cropData.fileName}</span>` : '';
            const isBackgroundImage = cropData.entityContext?.imageType === 'bk';
            const cropAspectRatio = Number(cropData.entityContext?.aspectRatio) || 1;
            const cropOutputWidth = Number(cropData.entityContext?.outputWidth) || 600;
            const cropOutputHeight = Number(cropData.entityContext?.outputHeight) || Math.round(cropOutputWidth / cropAspectRatio) || 600;
            const cropContainerStyle = `width: 100%; max-width: 100%; margin-left: auto; margin-right: auto; aspect-ratio: ${cropOutputWidth} / ${cropOutputHeight};`;
            const cropWrapperClass = isBackgroundImage
                ? 'relative w-full rounded-3xl overflow-hidden bg-gray-200 border border-gray-300'
                : 'relative w-full rounded-3xl overflow-hidden bg-gray-200 border border-gray-200';
            html += `
                <div id="message" data-role="message" class="w-full"></div>
                <div class="grid gap-4 w-full" style="max-width: 100%; overflow-x: hidden;">
                    <div class="flex flex-col gap-2 items-center w-full" style="max-width: 100%;">
                        <div class="${cropWrapperClass}" data-role="cropper-box" style="${cropContainerStyle}">
                            <img data-role="cropper-image" class="absolute top-0 left-0 select-none pointer-events-none" src="${previewSrc}" alt="${(data?.tt ?? 'Imagem')}">
                        </div>
                        ${fileLabel}
                    </div>
                    <label class="flex items-center gap-3 w-full" style="max-width: 100%;">
                        <span class="text-sm text-gray-600">Zoom</span>
                        <input type="range" data-role="cropper-zoom" min="1" max="3" step="0.01" value="1" class="flex-1">
                    </label>
                    <div class="text-xs text-gray-500 text-center w-full" style="max-width: 100%;">Arraste a imagem para ajustar o enquadramento.</div>
                    <div class="grid gap-2 w-full" style="max-width: 100%;">
                        <button data-action="crop-save" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
                        <button data-action="crop-cancel" class="shadow-md w-full py-2 px-4 bg-gray-200 text-gray-700 font-semibold rounded-3xl hover:bg-gray-300 transition-colors">Cancelar</button>
                    </div>
                </div>
            `;
            return html;
        }

        if (view === null) {
            html += UI.renderCloseHeader();

            const appearance = UI.shortcutList([
                { id: 'desktop', icon: 'fa-th', label: 'Área de Trabalho' }
            ]);
            const applications = UI.shortcutList([
                { id: 'apps', icon: 'fa-shapes', label: 'Aplicativos' }
            ]);
            const pages = UI.shortcutList([
                { id: 'people', icon: 'fa-user-friends', label: 'Pessoas' },
                { id: 'businesses', icon: 'fa-briefcase', label: 'Negócios' },
                { id: 'teams', icon: 'fa-users', label: 'Equipes' }
            ]);
            const logout = UI.shortcutList([
                { id: 'logout', icon: 'fa-sign-out-alt', label: 'Sair' }
            ]);
            html += `
                <div data-sidebar-type="current-user" data-sidebar-action="page-settings" class="pointer w-full bg-white shadow-md rounded-3xl p-3 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-profile-link">
                    <div data-sidebar-action="page-settings" class="grid grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 items-center gap-3">
                        <div class="flex col-span-1 justify-center">
                            <img id="sidebar-profile-image" data-role="entity-image" class="w-full rounded-full object-cover" src="${resolveImageSrc(data?.im ?? currentUserData?.im, data?.tt ?? currentUserData?.tt, { size: 100 })}" alt="Foto do Utilizador">
                        </div>
                        <div class="flex col-span-3 lg:col-span-4 xl:col-span-5 flex-col gap-1">
                            <p class="truncate font-bold">${data.tt}</p>
                            <p class="truncate">${data.ml}</p>
                            <small class="text-gray-500 truncate" >Perfil Workz!, E-mail, Foto, Endereço</small>
                        </div>
                    </div>
                    <div class="flex justify-end col-span-1">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
                ${appearance}
                ${applications}
                ${pages}
                ${logout}
            `;


        } else if (view === ENTITY.PROFILE) {
            sidebarContent.dataset.sidebarType = (data.id === currentUserData.id) ? 'current-user' : 'profile';

            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += UI.renderHero({ tt: data.tt, im: data.im });

            const card1 = UI.sectionCard(
                UI.row('name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, { top: true }) +
                UI.row('email', 'E-mail*', `<input class="w-full border-0 focus:outline-none" type="email" id="email" name="ml" value="${data.ml}" ${(data.provider ? '' : 'disabled')} required>`, { bottom: true })
            );

            const cardAbout = UI.sectionCard(UI.rowTextarea('cf', 'Sobre', data.cf));

            const cardUserMeta = UI.sectionCard(
                UI.row('username', 'Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, { top: true }) +
                UI.rowSelect('page_privacy', 'Página', `
                <option value="" ${currentUserData.page_privacy == null ? 'selected' : ''} disabled>Selecione</option>
                <option value="0" ${currentUserData.page_privacy === 0 ? 'selected' : ''}>Usuários logados</option>
                <option value="1" ${currentUserData.page_privacy === 1 ? 'selected' : ''}>Toda a internet</option>
                `) +
                UI.rowSelect('feed_privacy', 'Conteúdo', `
                <option value="" ${currentUserData.feed_privacy == null ? 'selected' : ''} disabled>Selecione</option>
                <option value="0" ${currentUserData.feed_privacy === 0 ? 'selected' : ''}>Somente eu</option>
                <option value="1" ${currentUserData.feed_privacy === 1 ? 'selected' : ''}>Seguidores</option>
                <option value="2" ${currentUserData.feed_privacy === 2 ? 'selected' : ''}>Usuários logados</option>
                <option value="3" ${currentUserData.feed_privacy === 3 && currentUserData.page_privacy > 0 ? 'selected' : ''} ${currentUserData.page_privacy < 1 ? 'disabled' : ''}>Toda a internet</option>
                `, { bottom: true })
            );

            const cardPersonal = UI.sectionCard(
                UI.rowSelect('gender', 'Gênero', `
                <option value="" ${(!['male', 'female'].includes(currentUserData.gender)) ? 'selected' : ''} disabled>Selecione</option>
                <option value="male" ${currentUserData.gender === 'male' ? 'selected' : ''}>Masculino</option>
                <option value="female" ${currentUserData.gender === 'female' ? 'selected' : ''}>Feminino</option>
                `, { top: true }) +
                UI.row('birth', 'Nascimento', `<input class="w-full border-0 focus:outline-none" type="date" id="birth" name="birth" value="${(currentUserData.birth) ? new Date(currentUserData.birth).toISOString().split('T')[0] : ''}">`) +
                UI.row('cpf', 'CPF', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="999.999.999-99" id="cpf" name="national_id" value="${currentUserData.national_id ?? ''}">`, { bottom: true })
            );

            const contacts = UI.contactBlock(data?.contacts ?? currentUserData?.contacts ?? '');

            const shortcuts = UI.shortcutList([
                { id: 'user-education', icon: 'fa-graduation-cap', label: 'Formação Acadêmica' },
                { id: 'user-jobs', icon: 'fa-user-tie', label: 'Experiência Profissional' },
                { id: 'testimonials', icon: 'fa-scroll', label: 'Depoimentos' },
            ]);

            const userChoices = UI.shortcutList([
                { id: 'password', icon: 'fa-key', label: 'Alterar Senha' },
                { id: 'delete-account', icon: 'fa-times', label: 'Excluir Conta', color: 'red' }
            ]);

            html += `
                ${personalizationCard(data)}
                <form id="settings-form" data-view="${view}" class="grid grid-cols-1 gap-6">
                    <input type="hidden" name="id" value="${data.id}">
                    ${card1}
                    ${cardAbout}
                    ${cardUserMeta}
                    ${cardPersonal}
                    ${contacts}
                    <button type="submit" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
                </form>                
                <hr>
                ${shortcuts}
                ${financeShortcuts}
                ${userChoices}                
            `;
        } else if (view === 'desktop') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: (navTitle || 'Área de Trabalho') });

            const hideFav = (function(){ try { return localStorage.getItem('workz.apps.hideFavorites') === '1'; } catch (_) { return false; } })();
            const bgUrl = (function(){ try { return localStorage.getItem('workz.desktop.background') || DEFAULT_BING_BG; } catch (_) { return DEFAULT_BING_BG; } })();

            const hideFavCard = UI.sectionCard(
                UI.row('desktop-hide-favorites', 'Ocultar favoritos da biblioteca', `
                    <button id="desktop-hide-favorites" data-action="desktop-toggle-hide-favorites" class="ios-switch" role="switch" aria-checked="${hideFav ? 'true' : 'false'}" aria-label="Ocultar favoritos da biblioteca" tabindex="0">
                        <span class="ios-switch-handle"></span>
                    </button>
                `, { top: true, bottom: true })
            );

            const bgCard = `
                <div class="w-full shadow-md rounded-2xl grid grid-cols-1 overflow-hidden bg-white">
                    <div class="p-3 font-semibold">Plano de Fundo</div>
                    <div class="p-3 grid grid-cols-1 gap-3">
                        <div id="desktop-bg-preview" class="w-full h-36 rounded-xl bg-center bg-cover border" style="background-image: url(${bgUrl});"></div>
                        <div class="grid grid-cols-2 gap-2">
                            <button data-action="desktop-bg-open-picker" class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-sm"><i class="fas fa-image"></i> Escolher imagem</button>
                            <button data-action="desktop-bg-remove" class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl text-sm" ${bgUrl && bgUrl !== DEFAULT_BING_BG ? '' : 'disabled'}><i class="fas fa-trash-alt"></i> Remover</button>
                        </div>
                        <input type="file" id="desktop-bg-file" accept="image/*" class="hidden" />
                        <div class="text-xs text-gray-500">Quando não personalizado, será exibido o plano de fundo do Bing por padrão.</div>
                    </div>
                </div>
            `;

            html += hideFavCard + bgCard;

        } else if (view === 'password') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const userId = data?.id ?? currentUserData?.id ?? '';
            const passwordCard = UI.sectionCard(
                UI.row('current_password', 'Senha Atual', `<input class="w-full border-0 focus:outline-none" type="password" id="current_password" name="current_password" placeholder="********" required>`, { top: true }) +
                UI.row('new_password', 'Nova Senha', `<input class="w-full border-0 focus:outline-none" type="password" id="new_password" name="new_password" placeholder="********" required>`) +
                UI.row('new_password_repeat', 'Confirmar Nova Senha', `<input class="w-full border-0 focus:outline-none" type="password" id="new_password_repeat" name="new_password_repeat" placeholder="********" required>`, { bottom: true })
            );

            html += `
                <div id="message" data-role="message" class="w-full"></div>
                <form id="change-password-form" method="post" novalidate data-view="${view}" class="grid grid-cols-1 gap-6">
                    <input type="hidden" name="id" value="${userId}">
                    ${passwordCard}
                    <button type="submit" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Alterar Senha</button>
                </form>
            `;
        } else if (view === ENTITY.BUSINESS) {
            sidebarContent.id = 'business';

            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += UI.renderHero({ tt: data.tt, im: data.im });

            const basics = UI.sectionCard(
                UI.row('name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, { top: true }) +
                UI.row('cnpj', 'CNPJ', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="99.999.999/9999-99" id="cnpj" name="cnpj" value="${data.national_id ?? ''}">`, { bottom: true })
            );

            const about = UI.sectionCard(UI.rowTextarea('cf', 'Sobre', data.cf));

            const privacy = UI.sectionCard(
                UI.row('username', 'Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, { top: true }) +
                UI.rowSelect('page_privacy', 'Página', `
                <option value="" ${data.page_privacy == null ? 'selected' : ''} disabled>Selecione</option>
                <option value="0" ${data.page_privacy === 0 ? 'selected' : ''}>Usuários logados</option>
                <option value="1" ${data.page_privacy === 1 ? 'selected' : ''}>Toda a internet</option>
                `) +
                UI.rowSelect('feed_privacy', 'Conteúdo', `
                <option value="" ${data.feed_privacy == null ? 'selected' : ''} disabled>Selecione</option>
                <option value="0" ${data.feed_privacy === 0 ? 'selected' : ''}>Moderadores</option>
                <option value="1" ${data.feed_privacy === 1 ? 'selected' : ''}>Usuários membros</option>
                <option value="2" ${data.feed_privacy === 2 ? 'selected' : ''}>Usuários logados</option>
                <option value="3" ${data.feed_privacy === 3 && (data.page_privacy > 0) ? 'selected' : ''} ${data.page_privacy < 1 ? 'disabled' : ''}>Toda a internet</option>
                `, { bottom: true })
            );

            const address = UI.sectionCard(
                UI.row('zip_code', 'CEP', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="99999-999" id="zip_code" name="zip_code" value="${data?.zip_code ?? ''}">`, { top: true }) +
                UI.rowSelect('country', 'País', UI.countryOptions(data?.country ?? '')) +
                UI.row('state', 'Estado', `<input class="w-full border-0 focus:outline-none" type="text" id="state" name="state" value="${data?.state ?? ''}">`) +
                UI.row('city', 'Cidade', `<input class="w-full border-0 focus:outline-none" type="text" id="city" name="city" value="${data?.city ?? ''}">`) +
                UI.row('district', 'Bairro', `<input class="w-full border-0 focus:outline-none" type="text" id="district" name="district" value="${data?.district ?? ''}">`) +
                UI.row('address', 'Endereço', `<input class="w-full border-0 focus:outline-none" type="text" id="address" name="address" value="${data?.address ?? ''}">`) +
                UI.row('complement', 'Complemento', `<input class="w-full border-0 focus:outline-none" type="text" id="complement" name="complement" value="${data?.complement ?? ''}">`, { bottom: true })
            );

            const contacts = UI.contactBlock(data?.contacts ?? data?.url ?? '');

            const shortcuts = UI.shortcutList([
                // { id:'business-shareholding', icon:'fa-sitemap', label:'Estrutura Societária' },
                { id: 'employees', icon: 'fa-id-badge', label: 'Colaboradores' },
                { id: 'testimonials', icon: 'fa-scroll', label: 'Depoimentos' },
            ]);


            html += `
                ${personalizationCard(data)}
                <form id="settings-form" data-view="${view}" class="grid grid-cols-1 gap-6">
                <input type="hidden" name="id" value="${data.id}">
                ${basics}
                ${about}
                ${privacy}
                ${address}
                ${contacts}
                <button type="submit" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
                </form>                
                <hr>
                ${shortcuts}
                ${financeShortcuts}
                <div class="w-full shadow-md rounded-2xl grid grid-cols-1 mt-3">
                    <button data-action="delete-business" data-id="${data.id}" class="p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-2xl"><i class="fas fa-trash"></i> Excluir Negócio</button>
                </div>
            `;
        } else if (view === ENTITY.TEAM) {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += UI.renderHero({ tt: data.tt, im: data.im });
            // exemplo: reuso igual ao business/profile para campos
            // (mantém tua lógica de buscar businesses; só exibindo com os helpers)
            let mappedBusinesses = await Promise.all(userBusinesses.map(async (business) => {
                const b = await fetchByIds(business, 'businesses');
                return `<option value="${business}" ${(data.em === business) ? 'selected' : ''}>${b.tt}</option>`;
            }));

            const basics = UI.sectionCard(
                UI.row('name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, { top: true }) +
                UI.rowSelect('business', 'Negócio', `
                <option value="" ${data.em == null ? 'selected' : ''} disabled>Selecione</option>
                ${mappedBusinesses.join('')}
                `, { bottom: true })
            );

            const about = UI.sectionCard(UI.rowTextarea('cf', 'Sobre', data.cf));

            const feedPrivacy = UI.sectionCard(
                UI.rowSelect('feed_privacy', 'Conteúdo', `
                <option value="" ${data.feed_privacy == null ? 'selected' : ''} disabled>Selecione</option>
                <option value="0" ${data.feed_privacy === 0 ? 'selected' : ''}>Moderadores</option>
                <option value="1" ${data.feed_privacy === 1 ? 'selected' : ''}>Membros da equipe</option>
                <option value="2" ${data.feed_privacy === 2 ? 'selected' : ''}>Todos do negócio</option>
                `, { bottom: true })
            );

            const contacts = UI.contactBlock(data?.contacts ?? data?.url ?? '');

            const shortcuts = UI.shortcutList([
                { id: 'employees', icon: 'fa-id-badge', label: 'Colaboradores' },
            ]);

            // Apenas dono ou moderadores da equipe podem excluir a equipe
            let canDeleteTeam = canManageTeam(data);
            const deleteTeamButton = canDeleteTeam
                ? `
                <div class="w-full shadow-md rounded-2xl grid grid-cols-1 mt-3">
                    <button data-action="delete-team" data-id="${data.id}" data-em="${data.em}" class="p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-2xl"><i class="fas fa-trash"></i> Excluir Equipe</button>
                </div>`
                : '';

            html += `
                ${personalizationCard(data)}
                <form id="settings-form" data-view="${view}" class="grid grid-cols-1 gap-6">
                <input type="hidden" name="id" value="${data.id}">
                ${basics}
                ${about}
                ${UI.sectionCard(UI.row('username', 'Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, { top: true }))}
                ${feedPrivacy}
                ${contacts}
                <button type="submit" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
                </form>                
                ${shortcuts}
                ${deleteTeamButton}
            `;
        } else if (view === 'employees') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const table = (type === 'business') ? 'employees' : 'teams_users';
            const idKey = (type === 'business') ? 'em' : 'cm';
            const conditions = { [idKey]: data.id };
            const employees = await apiClient.post('/search', { db: 'workz_companies', table, columns: ['us', 'nv', 'st'], conditions, exists: [{ db: 'workz_data', table: 'hus', local: 'us', remote: 'id', conditions: { st: 1 } }], fetchAll: true });
            const entries = Array.isArray(employees?.data) ? employees.data : [];
            const allUserIds = entries.map(o => o.us);
            let people = await fetchByIds(allUserIds, 'people');
            people = Array.isArray(people) ? people : (people ? [people] : []);
            const userMap = new Map(people.map(p => [p.id, p]));

            const active = entries.filter(e => Number(e.st) === 1);
            const pending = entries.filter(e => Number(e.st) === 0);

            // Permissões de gestão
            let canManage = (type === 'business') ? isBusinessManager(data.id) : canManageTeam(data);

            const levelSelect = (current) => {
                const options = [
                    { v: 1, t: 'Membro' },
                    { v: 2, t: 'Colaborador' },
                    { v: 3, t: 'Moderador' },
                    { v: 4, t: 'Gestor' }
                ];
                return `<select name="nv" class="border-0 focus:outline-none">${options.map(o => `<option value="${o.v}" ${Number(current) === o.v ? 'selected' : ''}>${o.t}</option>`).join('')}</select>`;
            };

            const activeRows = active.length
                ? active.map(e => {
                    const p = userMap.get(e.us) || { id: e.us, tt: 'Usuário' };
                    if (canManage) {
                        const controls = `
                            <div class="flex gap-2 items-center">
                                ${levelSelect(e.nv ?? 1)}
                                <button data-action="update-member-level" data-user-id="${p.id}" data-scope-type="${type}" data-scope-id="${data.id}" class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-md">Atualizar</button>
                            </div>`;
                        return UI.row(`member-${p.id}`, p.tt, controls);
                    }
                    return UI.row(`member-${p.id}`, p.tt, `<span class="text-gray-500">Nível: ${Number(e.nv ?? 1)}</span>`);
                }).join('')
                : `<div class="p-3 text-sm text-gray-500">Nenhum membro ativo.</div>`;

            const pendingRows = (pending.length && canManage)
                ? pending.map(e => {
                    const p = userMap.get(e.us) || { id: e.us, tt: 'Usuário' };
                    const controls = `
                        <div class="flex gap-2">
                            <button data-action="accept-member" data-user-id="${p.id}" data-scope-type="${type}" data-scope-id="${data.id}" class="p-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-md">Aceitar</button>
                            <button data-action="reject-member" data-user-id="${p.id}" data-scope-type="${type}" data-scope-id="${data.id}" class="p-2 bg-red-100 hover:bg-red-200 text-red-800 rounded-md">Recusar</button>
                        </div>`;
                    return UI.row(`pending-${p.id}`, p.tt, controls);
                }).join('')
                : `<div class="p-3 text-sm text-gray-500">Sem solicitações pendentes.</div>`;

            html += `
                ${UI.sectionCard(`<div class="p-3 font-semibold">Membros Ativos</div>` + activeRows)}
                ${UI.sectionCard(`<div class="p-3 font-semibold">Solicitações Pendentes</div>` + pendingRows)}
            `;
        } else if (view === 'people') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            // Pessoas seguidas pelo usuário logado
            const ids = Array.isArray(userPeople) ? userPeople : [];
            if (!ids.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Você ainda não segue ninguém.</div>`;
            } else {
                let list = await fetchByIds(ids, 'people');
                list = Array.isArray(list) ? list : (list ? [list] : []);
                // Campo de busca
                const searchCard = UI.sectionCard(
                    UI.row('people-search', 'Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="people-search" placeholder="Digite para filtrar">`, { top: true, bottom: true })
                );

                const rows = list.map(u => {
                    const img = resolveImageSrc(u?.im, u?.tt, { size: 100 });
                    const name = (u?.tt || 'Usuário');
                    return `
                    <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" data-id="${u.id}" data-name="${name.toLowerCase()}">
                        <div class="col-span-1 p-3 flex justify-center">
                            <img src="${img}" alt="${u?.tt || 'Usuário'}" class="w-7 h-7 rounded-full" />
                        </div>
                        <div class="col-span-5 p-3 truncate">${name}</div>
                    </div>`;
                }).join('');
                html += searchCard + UI.sectionCard(`<div id="people-list">${rows}</div>`);
            }
        } else if (view === 'businesses') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            // Negócios onde o usuário é membro com nível de moderação/gestão (nv >= 3)
            const managed = Array.isArray(userBusinessesData)
                ? userBusinessesData.filter(r => Number(r?.nv ?? 0) >= 3 && Number(r?.st ?? 1) === 1).map(r => r.em)
                : [];
            const ids = managed;
            if (!ids.length) {
                const createBusinessCard = UI.sectionCard(
                    UI.row('new-business-name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-business-name" placeholder="Digite o nome do negócio" required>`, { top: true }) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-business" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createBusinessCard;
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center mt-3">Você ainda não gerencia nenhum negócio.</div>`;
            } else {
                let list = await fetchByIds(ids, 'businesses');
                list = Array.isArray(list) ? list : (list ? [list] : []);
                const createBusinessCard = UI.sectionCard(
                    UI.row('new-business-name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-business-name" placeholder="Digite o nome do negócio" required>`, { top: true }) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-business" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createBusinessCard;
                const searchCardBiz = UI.sectionCard(
                    UI.row('businesses-search', 'Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="businesses-search" placeholder="Digite para filtrar">`, { top: true, bottom: true })
                );
                html += searchCardBiz;
                const rows = list.map(b => {
                    const img = resolveImageSrc(b?.im, b?.tt, { size: 100 });
                    const name = (b?.tt || 'Negócio');
                    return `
                    <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" data-id="${b.id}" data-name="${name.toLowerCase()}">
                        <div class="col-span-1 p-3 flex justify-center">
                            <img src="${img}" alt="${name}" class="w-7 h-7 rounded-full" />
                        </div>
                        <div class="col-span-5 p-3 truncate">${name}</div>
                    </div>`;
                }).join('');
                html += UI.sectionCard(`<div id="businesses-list">${rows}</div>`);
            }
        } else if (view === 'teams') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            // Equipes em que o usuário é criador (us) ou moderador (usmn contém id), ativas (st=1)
            // e cujo negócio (companies.em) também está ativo (companies.st=1)
            const res = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'teams',
                columns: ['id', 'tt', 'im', 'us', 'usmn', 'st', 'em'],
                conditions: { st: 1 },
                exists: [{ table: 'companies', local: 'em', remote: 'id', conditions: { st: 1 } }],
                fetchAll: true
            });
            const all = Array.isArray(res?.data) ? res.data : [];
            const uid = String(currentUserData.id);
            const managed = all.filter(t => {
                const isOwner = String(t.us) === uid;
                let moderators = [];
                try { moderators = t?.usmn ? JSON.parse(t.usmn) : []; } catch (_) { moderators = []; }
                const isModerator = Array.isArray(moderators) && moderators.map(String).includes(uid);
                return isOwner || isModerator;
            });
            // Filtro: só equipes cujos negócios o usuário participa com aprovação
            const approvedBizSet = new Set((Array.isArray(userBusinessesData) ? userBusinessesData : [])
                .filter(r => Number(r?.st ?? 0) === 1)
                .map(r => String(r.em))
            );
            const visible = managed.filter(t => approvedBizSet.has(String(t.em)));
            if (!visible.length) {
                // Select de negócios gerenciados para criar equipe (em)
                const managedBiz = Array.isArray(userBusinessesData)
                    ? userBusinessesData.filter(r => Number(r?.nv ?? 0) >= 3 && Number(r?.st ?? 1) === 1).map(r => r.em)
                    : [];
                let bizList = await fetchByIds(managedBiz, 'businesses');
                bizList = Array.isArray(bizList) ? bizList : (bizList ? [bizList] : []);
                const options = [`<option value="">Selecione um negócio</option>`]
                    .concat(bizList.map(b => `<option value="${b.id}">${b.tt}</option>`)).join('');
                const createTeamCard = UI.sectionCard(
                    UI.row('new-team-name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-team-name" placeholder="Digite o nome da equipe" required>`, { top: true }) +
                    UI.rowSelect('new-team-business', 'Negócio', options) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-team" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createTeamCard;
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center mt-3">Você ainda não gerencia nenhuma equipe.</div>`;
            } else {
                // Formulário de criação no topo (bloco/padrão depoimentos)
                const managedBiz = Array.isArray(userBusinessesData)
                    ? userBusinessesData.filter(r => Number(r?.nv ?? 0) >= 3 && Number(r?.st ?? 1) === 1).map(r => r.em)
                    : [];
                let bizList = await fetchByIds(managedBiz, 'businesses');
                bizList = Array.isArray(bizList) ? bizList : (bizList ? [bizList] : []);
                const options = [`<option value="">Selecione um negócio</option>`]
                    .concat(bizList.map(b => `<option value="${b.id}">${b.tt}</option>`)).join('');
                const createTeamCard = UI.sectionCard(
                    UI.row('new-team-name', 'Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-team-name" placeholder="Digite o nome da equipe" required>`, { top: true }) +
                    UI.rowSelect('new-team-business', 'Negócio', options) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-team" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createTeamCard;
                const searchCardTeams = UI.sectionCard(
                    UI.row('teams-search', 'Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="teams-search" placeholder="Digite para filtrar">`, { top: true, bottom: true })
                );
                html += searchCardTeams;
                const rows = visible.map(t => {
                    const img = resolveImageSrc(t?.im, t?.tt, { size: 100 });
                    const name = (t?.tt || 'Equipe');
                    return `
                    <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" data-id="${t.id}" data-name="${name.toLowerCase()}">
                        <div class="col-span-1 p-3 flex justify-center">
                            <img src="${img}" alt="${name}" class="w-7 h-7 rounded-full" />
                        </div>
                        <div class="col-span-5 p-3 truncate">${name}</div>
                    </div>`;
                }).join('');
                html += UI.sectionCard(`<div id="teams-list">${rows}</div>`);
            }
        } else if (view === 'apps') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            // Lista de apps instalados do usuário logado
            let userApps = await apiClient.post('/search', {
                db: 'workz_apps',
                table: 'gapp',
                columns: ['ap'],
                conditions: { us: currentUserData.id },
                fetchAll: true
            });
            const appIds = Array.isArray(userApps?.data) ? userApps.data.map(o => o.ap) : [];
            const apps = await fetchByIds(appIds, 'apps');
            const list = Array.isArray(apps) ? apps : (apps ? [apps] : []);
            const searchCardApps = UI.sectionCard(
                UI.row('apps-search', 'Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="apps-search" placeholder="Digite para filtrar">`, { top: true, bottom: true })
            );
            const storeRow = `
                <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" id="open-app-store" data-name="loja">
                    <div class="col-span-1 p-3 flex justify-center">
                        <img src="/images/app-default.png" alt="Loja" class="w-7 h-7 rounded-md" />
                    </div>
                    <div class="col-span-5 p-3 truncate">Loja de Aplicativos</div>
                </div>`;
            const rows = list.map(app => {
                const img = resolveImageSrc(app?.im, app?.tt, { fallbackUrl: '/images/app-default.png', size: 120 });
                const name = (app?.tt || 'App');
                return `
                <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" data-app-id="${app.id}" data-name="${name.toLowerCase()}">
                    <div class="col-span-1 p-3 flex justify-center">
                        <img src="${img}" alt="${name}" class="w-7 h-7 rounded-md" />
                    </div>
                    <div class="col-span-5 p-3 truncate">${name}</div>
                </div>`;
            }).join('');
            html += searchCardApps + UI.sectionCard(`<div id="apps-list">${storeRow}${rows}</div>`);
        } else if (view === 'app-settings') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const app = data || {};
            const appId = app.id || payload?.appId || null;
            const img = resolveImageSrc(app?.im, app?.tt, { fallbackUrl: '/images/app-default.png', size: 120 });
            const notifyKey = `app_notify_${appId}`;
            const enabled = (localStorage.getItem(notifyKey) === '1');
            // Verifica se está nos favoritos (acesso rápido)
            let inQuick = false;
            try {
                const quickRes = await apiClient.post('/search', {
                    db: 'workz_apps', table: 'quickapps', columns: ['ap'],
                    conditions: { us: currentUserData.id, ap: appId }, fetchAll: true
                });
                inQuick = Array.isArray(quickRes?.data) && quickRes.data.length > 0;
            } catch (_) {}
            const header = `
            <div class="w-full bg-white rounded-2xl shadow-md p-4 flex items-center gap-3">
                <img src="${img}" alt="${app?.tt || 'App'}" class="w-9 h-9 rounded-md" />
                <div class="font-semibold truncate">${app?.tt || 'Aplicativo'}</div>
            </div>`;
            const actions = `
            <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
                <div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                    <button data-action="app-toggle-quick" data-app-id="${appId}" class="p-3 bg-amber-100 hover:bg-amber-200 text-amber-800">
                        <i class="fas fa-star"></i> ${inQuick ? 'Remover do Acesso Rápido' : 'Adicionar ao Acesso Rápido'}
                    </button>
                    <button data-action="app-toggle-notifications" data-app-id="${appId}" class="p-3 bg-blue-100 hover:bg-blue-200 text-blue-800">
                        <i class="fas fa-bell"></i> ${enabled ? 'Desativar Notificações' : 'Ativar Notificações'}
                    </button>
                    <button data-action="app-uninstall" data-app-id="${appId}" class="p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-b-2xl">
                        <i class="fas fa-trash"></i> Desinstalar aplicativo
                    </button>
                </div>
            </div>`;
            html += header + actions;
        } else if (view === 'testimonials') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const res = await apiClient.post('/search', { db: 'workz_data', table: 'testimonials', columns: ['*'], conditions: { recipient: data.id, recipient_type: type }, fetchAll: true });
            const list = Array.isArray(res?.data) ? res.data : [];
            if (!list.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Não há depoimentos.</div>`;
            } else {
                const cards = await Promise.all(list.map(async t => {
                    const author = await fetchByIds(t.author, 'people');
                    const avatar = resolveImageSrc(author?.im, author?.tt, { size: 100 });
                    const primaryBtn = (t.status === 0)
                        ? `<button title="Aceitar" data-action="accept-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-bl-2xl"><i class="fas fa-check"></i></button>`
                        : `<button title="Reverter" data-action="revert-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-bl-2xl"><i class="fas fa-undo"></i></button>`;
                    const rejectBtn = `<button title="Rejeitar" data-action="reject-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-br-2xl"><i class="fas fa-ban"></i></button>`;

                    return `
                        <div class="w-full bg-white shadow-md rounded-2xl grid grid-cols-1 gap-y-4">
                        <div class="pt-4 px-4 col-span-4 flex items-center truncate">
                            <img class="w-7 h-7 mr-2 rounded-full pointer" src="${avatar}" />
                            <a class="font-semibold">${author?.tt ?? 'Autor'}</a>
                        </div>
                        <div class="col-span-4 px-4">${t.content ?? ''}</div>
                        <div class="grid grid-cols-2 rounded-b-2xl border-t border-gray-200 bg-white">
                            ${primaryBtn}
                            ${rejectBtn}
                        </div>
                        </div>
                    `;
                }));
                html += cards.join('');
            }
        } else if (view === 'billing') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += `<div class="bg-white rounded-2xl shadow-md p-4">Cobrança e Recebimento</div>`;
        } else if (view === 'transactions') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += `<div class="bg-white rounded-2xl shadow-md p-4">Transações</div>`;
        } else if (view === 'subscriptions') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const conditions = (type === 'business') ? { em: data.id, subscription: 1 } : { us: data.id, subscription: 1 };

            const exists = [{ table: 'apps', local: 'ap', remote: 'id' }];
            const res = await apiClient.post('/search', { db: 'workz_apps', table: 'gapp', columns: ['*'], conditions: conditions, exists: exists, fetchAll: true });
            const list = Array.isArray(res?.data) ? res.data : [];

            if (!list.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Não há assinaturas.</div>`;
            } else {
                const cards = await Promise.all(list.map(async t => {
                    const app = await fetchByIds(t.ap, 'apps');
                    const avatar = resolveImageSrc(app?.im, app?.tt, { fallbackUrl: '/images/app-default.png', size: 90 });
                    const actionBtn = (t.end_date === null)
                        ? `<button title="Cancelar" data-action="reject-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-b-2xl"><i class="fas fa-ban"></i> Cancelar Assinatura</button>`
                        : `<button title="Renovar" data-action="revert-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-b-2xl"><i class="fas fa-undo"></i> Renovar Assinatura</button>`
                        ;
                    return `
                        <div class="w-full bg-white shadow-md rounded-2xl grid grid-cols-1 gap-y-4">
                            <div class="pt-4 px-4 col-span-4 flex items-center truncate">
                                <img class="w-9 h-9 mr-2 rounded-md pointer" src="${avatar}" />
                                <a class="font-semibold">${app?.tt}</a>
                            </div>
                            <div class="grid grid-cols-1 border-t p-4">                                
                                ${(app?.vl > 0) ? app?.vl : 'Gratuito'}
                            </div>
                            <div class="grid grid-cols-1 border-t">
                                ${actionBtn}
                            </div>
                        </div>
                    `;
                }));
                html += cards.join('');
            }

        } else if (view === 'user-education') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            html += `<div class="bg-white rounded-2xl shadow-md p-4">Education content</div>`;
        } else if (view === 'user-jobs') {
            html += UI.renderHeader({ backAction: 'stack-back', backLabel: prevTitle, title: navTitle });
            const userJobs = await apiClient.post('/search', { db: 'workz_companies', table: 'employees', columns: ['*'], conditions: { us: currentUserData.id }, order: { by: 'start_date', dir: 'DESC' }, fetchAll: true });
            const cards = await Promise.all(userJobs?.data?.map(async j => {
                const business = await fetchByIds(j.em, 'businesses');
                return `
                    <div class="w-full bg-white shadow-md rounded-2xl grid grid-cols-1 gap-y-4">
                        <div class="pt-4 px-4 col-span-4 flex items-center truncate">
                            <img class="w-7 h-7 mr-2 rounded-full pointer" src="${resolveImageSrc(business?.im, business?.tt, { size: 100 })}" />
                            <a class="font-semibold">${business?.tt ?? 'Empresa'}</a>
                        </div>
                        <div class="col-span-4 px-4">
                            <p class="font-semibold">${j.job_title}</p>
                            <p class="text-sm text-gray-600">${new Date(j.start_date).toLocaleDateString()} - ${j.end_date ? new Date(j.end_date).toLocaleDateString() : 'Atual'}</p>
                            <p class="text-sm mt-2">${j.description ?? ''}</p>
                        </div>
                        <div class="grid grid-cols-2 rounded-b-2xl border-t border-gray-200 bg-white">
                            <button title="Editar" data-action="edit-job" data-id="${j.id}" class="col-span-1 p-3 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-bl-2xl"><i class="fas fa-edit"></i></button>
                            <button title="Excluir" data-action="delete-job" data-id="${j.id}" class="col-span-1 p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-br-2xl"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    
                `;
            }));
            html += cards.join('');
            html += `<button id="add-job-btn" class="w-full py-2 px-4 bg-green-500 text-white font-semibold rounded-3xl hover:bg-green-700 transition-colors mt-4">Adicionar Experiência Profissional</button>`;
        } else if (view === 'post-editor') {
            
            const postCaption = UI.sectionCard(UI.rowTextarea('postCaption', 'Legenda', ''));
            
            html += UI.renderCloseHeader();
            html += `
            <h1 class="text-center text-gray-500 text-xl font-bold">Criar Publicação</h1>
            <div id="appShell" class="gap-6 flex flex-col">    
                <section class="editor-card">
                    <!-- Toolbar superior minimalista -->
                    <div class="flex items-center gap-6">
                        <label for="bgUpload" class="tool-icon" title="Plano de fundo">
                            <i class="fas fa-image"></i>
                            <input type="file" id="bgUpload" accept="image/*,video/*">
                        </label>
                        <button type="button" id="btnAddText" class="tool-icon" title="Adicionar texto">
                            <i class="fas fa-font"></i>
                        </button>
                        <button type="button" id="btnAddImg" class="tool-icon" title="Adicionar imagem">
                            <i class="fas fa-image"></i>
                        </button>                        
                        <button type="button" id="btnCameraMode" class="tool-icon hidden" title="Usar câmera (requer permissão)">
                            <i class="fas fa-camera"></i>
                        </button>                        
                        <button type="button" id="btnSaveJSON" class="tool-icon" title="Salvar layout">
                            <i class="fas fa-save"></i>
                        </button>
                        <label class="tool-icon" for="loadJSON" title="Carregar layout">
                            <i class="fas fa-folder-open"></i>
                            <input type="file" id="loadJSON" accept="application/json" class="sr-only">
                        </label>
                    </div>
                </section>
                
                <section class="editor-card">
                    <!-- Editor viewport com botão de captura sobreposto -->
                    <div id="editorViewport" class="bg-white rounded-2xl shadow-md">
                        <div id="editor" class="rounded-2xl">
                            <canvas id="gridCanvas" width="900" height="1200"></canvas>
                            <div id="guideX" class="guide guide-x" style="display:none; top:50%"></div>
                            <div id="guideY" class="guide guide-y" style="display:none; left:50%"></div>
                        </div>                    
                        <!-- Botão de captura estilo Instagram Stories -->
                        <div class="capture-overlay">
                        <button type="button" id="captureButton" class="capture-button" title="Toque para foto, segure para vídeo">
                            <div class="capture-inner">
                                <i class="fa-solid fa-camera capture-icon"></i>
                            </div>
                            <div class="capture-hint">
                                <i class="fa-solid fa-hand-pointer"></i>
                            </div>
                        </button>
                        </div>
                    </div>                                    
                </section>
            
                <section id="itemBar" class="editor-card control-panel" style="display:none">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-600">Selecionado</span>
                        <select id="zIndex" class="border border-slate-200 rounded-lg px-2 py-1 text-sm">
                            <option value="front">Trazer p/ frente</option>
                            <option value="back">Enviar p/ trás</option>
                        </select>
                    </div>
                
                    <div id="textControls" class="hidden flex-wrap items-center gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            Fonte
                            <input id="fontSize" type="range" min="12" max="96" value="28" class="accent-slate-500" title="Tamanho da fonte">
                        </label>
                        <input id="fontColor" type="color" value="#111827" class="h-9 w-9 rounded-full border border-slate-200" title="Cor do texto">
                        <select id="fontWeight" class="border border-slate-200 rounded-lg px-2 py-1 text-sm" title="Peso">
                            <option value="400">Regular</option>
                            <option value="600" selected>Semibold</option>
                            <option value="700">Bold</option>
                        </select>
                        <div class="flex items-center gap-2">
                            <button type="button" id="alignLeft" class="toolbar-btn toolbar-btn--ghost" title="Alinhar à esquerda">
                                <i class="fa-solid fa-align-left"></i><span class="sr-only">Alinhar à esquerda</span>
                            </button>
                            <button type="button" id="alignCenter" class="toolbar-btn toolbar-btn--ghost" title="Centralizar texto">
                                <i class="fa-solid fa-align-center"></i><span class="sr-only">Centralizar</span>
                            </button>
                            <button type="button" id="alignRight" class="toolbar-btn toolbar-btn--ghost" title="Alinhar à direita">
                                <i class="fa-solid fa-align-right"></i><span class="sr-only">Alinhar à direita</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                Fundo
                                <input id="bgTextColor" type="color" value="#ffffff" class="h-9 w-9 rounded-full border border-slate-200" title="Cor de fundo do texto">
                            </label>
                            <label class="flex items-center gap-1 text-sm text-slate-600">
                                <input id="bgNone" type="checkbox" class="rounded border-slate-300">
                                Sem fundo
                            </label>
                        </div>
                        <button type="button" id="btnEditText" class="toolbar-btn toolbar-btn--ghost" aria-pressed="false" title="Editar texto selecionado">
                            <i class="fa-solid fa-edit"></i>
                            <span id="btnEditTextLabel">Editar</span>
                        </button>
                    </div>
            
                    <div id="animControls" class="hidden flex-wrap items-center gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                        Animação
                        <select id="animType" class="border border-slate-200 rounded-lg px-2 py-1 text-sm">
                            <option value="none">none</option>
                            <option value="fade-in">fade-in</option>
                            <option value="slide-left">slide-in left</option>
                            <option value="slide-right">slide-in right</option>
                            <option value="slide-top">slide-in top</option>
                            <option value="slide-bottom">slide-in bottom</option>
                        </select>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                        Delay (s)
                        <input id="animDelay" type="number" step="0.1" min="0" value="0" class="w-20 border border-slate-200 rounded px-2 py-1 text-sm">
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                        Duração (s)
                        <input id="animDur" type="number" step="0.1" min="0.1" value="0.8" class="w-20 border border-slate-200 rounded px-2 py-1 text-sm">
                        </label>
                    </div>
            
                    <div class="ml-auto">
                        <button type="button" id="btnDelete" class="toolbar-btn toolbar-btn--danger" title="Excluir item selecionado">
                            <i class="fa-solid fa-trash"></i><span>Excluir</span>
                        </button>
                    </div>
                </section>
                `;

                html += postCaption;

                html += `
                <!-- Galeria (carrossel) - upload múltiplo e publicação -->
                <section class="editor-card">                                                               
                    <div class="flex items-center gap-2">                            
                        <input id="postMediaPicker" class="hidden" type="file" multiple accept="image/*,video/*">                            
                        <button id="publishGalleryBtn" type="button" class="ml-auto px-3 py-2 rounded-2xl bg-indigo-600 text-white text-sm hover:bg-indigo-700">Publicar</button>
                    </div>
                    <div id="postMediaTray" class=""></div>                   
                </section>
            
                <!-- Botão Publicar -->
                <section class="editor-card">
                    <div class="flex flex-col items-center gap-4">
                        <!-- Configurações de exportação (ocultas por padrão) -->
                        <div id="exportSettings" class="export-settings hidden">
                            <div class="flex flex-wrap gap-3 items-center justify-center text-sm">
                                <label class="flex items-center gap-2 text-slate-600">
                                    Duração (s)
                                    <input id="vidDur" type="number" min="1" step="0.5" value="6" class="w-20 border border-slate-200 rounded px-2 py-1 text-sm">
                                </label>
                                <label class="flex items-center gap-2 text-slate-600">
                                    FPS
                                    <input id="vidFPS" type="number" min="10" max="60" step="1" value="30" class="w-20 border border-slate-200 rounded px-2 py-1 text-sm">
                                </label>
                                <label class="flex items-center gap-2 text-slate-600">Fundo <input id="bgColorPicker" type="color" value="#ffffff" class="h-8 w-10 border border-slate-200 rounded"></label> </div><div id="videoExportInfo" class="text-xs text-slate-500 text-center mt-2 hidden">
                                <i class="fa-solid fa-info-circle"></i> 
                                <span id="videoExportInfoText"></span>
                            </div>
                        </div>                                            

                        <button type="button" id="btnEnviar"  title="Enviar conteúdo" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">
                            <div class="enviar-inner">
                                <i class="fas fa-paper-plane enviar-icon"></i>
                                <span class="enviar-text">Publicar</span>
                            </div>
                        </button>
                        
                        
                    </div>
                </section>

                <canvas id="outCanvas" width="900" height="1200" class="hidden"></canvas>
                
                <!-- Elementos ocultos para captura -->
                <video id="hiddenCameraStream" autoplay muted playsinline class="hidden"></video>
                <canvas id="captureCanvas" class="hidden"></canvas>
            </div>
            `;
        }
        html += UI.signature();
        return html;
    };

    async function appendWidget(type = 'people', gridList, count) {

        // people aqui são IDs; resolvemos antes de tudo
        let resolved = await fetchByIds(gridList, type);

        resolved = (!Array.isArray(resolved)) ? [resolved] : resolved;

        count = Number(count) ?? 0;
        const visorCount = count > 0 ? ` (${count})` : '';
        const fontAwesome = type === 'people' ? 'fas fa-user-friends' : type === 'teams' ? 'fas fa-users' : 'fas fa-briefcase';
        const title = type === 'people' ? 'Seguindo' : type === 'teams' ? 'Equipes' : 'Negócios';

        // monta o grid (ou o vazio) sem ternário com várias linhas
        let gridHtml = '';
        if (count > 0) {
            const cards = resolved?.map(p => `
            <div data-id="${p.id}" class="relative rounded-2xl overflow-hidden bg-gray-300 aspect-square cursor-pointer card-item">
                <div class="absolute inset-0 bg-center bg-cover" style="background-image:${resolveBackgroundImage(p?.im, p?.tt, { size: 100 })};"></div>
                <div class="absolute h-full inset-x-0 bottom-0 bg-black/20 hover:bg-black/40 text-white font-medium px-2 py-1 truncate">
                    <div class="absolute bottom-0 left-0 right-0 p-2 text-xs text-shadow-lg truncate text-center">${p.tt || 'Usuário'}</div>
                </div>
            </div>                                
            `).join('');

            gridHtml = `
            <div class="grid grid-cols-3 gap-3 min-w-0">
                ${cards}
            </div>
            `;
        } else {
            gridHtml = `
            <div class="rounded-3xl w-full p-3 truncate flex items-center gap-2" style="background:#F7F8D1;">
                <i class="fas fa-info-circle cm-mg-5-r"></i>
                <span class="truncate">Nenhuma página de usuário.</span>
            </div>
            `;
        }

        const widgetWrapper = document.querySelector('#widget-wrapper');

        // Verificar se o widget já existe e removê-lo
        const existingWidget = widgetWrapper.querySelector(`#widget-${type}`);
        if (existingWidget) {
            existingWidget.remove();
        }

        widgetWrapper.insertAdjacentHTML('beforeend', `    
            <div id="widget-${type}">
                <div class="bg-white p-3 rounded-3xl shadow-lg">
                    <div class="w-full content-center mb-3 mt-1">
                        <span class="fa-stack">
                            <i class="fas fa-circle fa-stack-2x"></i>
                            <i class="fas ${fontAwesome} fa-stack-1x fa-inverse"></i>
                        </span>
                        <a class="font-semibold"> ${title}${visorCount}</a>
                    </div>
                    ${gridHtml}
                </div>
            </div>
        `);
    }

    const HTML_ESCAPE_MAP = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => HTML_ESCAPE_MAP[char] || char);
    }

    function deriveContactHref(rawValue) {
        const value = String(rawValue ?? '').trim();
        if (!value) return null;
        if (/^(https?:\/\/|mailto:|tel:|sms:)/i.test(value)) return value;
        if (/^www\./i.test(value)) return `https://${value}`;
        if (value.includes('@') && !value.includes(' ')) return `mailto:${value}`;
        const digits = value.replace(/[^+\d]/g, '');
        if (digits && digits.length >= 8 && /^\+?\d+$/.test(digits)) return `tel:${digits}`;
        return null;
    }

    function appendContactsWidget(contactsSource) {
        const widgetWrapper = document.querySelector('#widget-wrapper');
        if (!widgetWrapper) return;

        const normalized = extractContactsData(contactsSource);
        if (!Array.isArray(normalized) || !normalized.length) return;

        const existing = widgetWrapper.querySelector('#widget-contacts');
        if (existing) existing.remove();

        const count = normalized.length;
        const countLabel = count > 0 ? ` (${count})` : '';
        const itemsHtml = normalized.map((contact) => {
            const label = escapeHtml(contact?.type || contact?.value || '');
            const href = deriveContactHref(contact?.value);
            if (href) {
                const safeHref = escapeHtml(href);
                return `<a class="block rounded-2xl p-3 bg-gray-100 hover:bg-gray-200 text-gray-700 truncate transition-colors" href="${safeHref}" target="_blank" rel="noopener noreferrer" title="${label}">${label}</a>`;
            }
            return `<div class="block rounded-2xl p-3 bg-gray-100 text-gray-600 truncate" title="${label}">${label}</div>`;
        }).join('');

        widgetWrapper.insertAdjacentHTML('beforeend', `    
            <div id="widget-contacts">
                <div class="bg-white p-3 rounded-3xl shadow-lg">
                    <div class="w-full content-center mb-3 mt-1">
                        <span class="fa-stack">
                            <i class="fas fa-circle fa-stack-2x"></i>
                            <i class="fas fa-link fa-stack-1x fa-inverse"></i>
                        </span>
                        <a class="font-semibold"> Links${countLabel}</a>
                    </div>
                    <div class="grid grid-cols-1 gap-3 font-semibold text-center">
                        ${itemsHtml}
                    </div>
                </div>
            </div>
        `);
    }

    async function pageAction() {
        const actionContainer = document.querySelector('#action-container');

        // Limpar container de ações antes de adicionar novas
        if (actionContainer) {
            actionContainer.innerHTML = '';
        }

        // Visitante (sem login) não exibe ações interativas
        const authed = !!localStorage.getItem('jwt_token') && !!(currentUserData && currentUserData.id != null);
        if (!authed || !actionContainer) return;

        const isManager = memberLevel >= 3;
        if (viewType === ENTITY.PROFILE) {
            const isFollowing = Array.isArray(userPeople)
                ? userPeople.map(String).includes(String(viewId))
                : false;
            if (isFollowing) {
                actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'unfollow-user', label: 'Deixar de Seguir', color: 'red' }));
            } else {
                actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'follow-user', label: 'Seguir', color: 'blue' }));
            }
        } else if (viewType === ENTITY.BUSINESS) {
            const parseIdArray = (val) => { try { const arr = JSON.parse(val); return Array.isArray(arr) ? arr : []; } catch (_) { return []; } };
            const mods = (viewData?.usmn) ? parseIdArray(viewData.usmn) : [];
            const isModerator = mods.map(String).includes(String(currentUserData?.id));

            // Verifica se o usuário não é gestor na empresa ou moderador
            if (!isManager && !isModerator) {
                if (userBusinesses.includes(viewId)) {
                    if (memberStatus === 0) {
                        actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'cancel-request', label: 'Cancelar Pedido', color: 'yellow' }));
                    } else {
                        actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'cancel-access', label: 'Cancelar Acesso', color: 'red' }));
                    }
                } else {
                    actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'request-join', label: 'Solicitar Acesso', color: 'green' }));
                }
            } else {
                actionContainer.insertAdjacentHTML('beforeend', `
                    <li><button data-sidebar-action="page-settings" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-cog fa-stack-1x text-gray-700"></i></span><span class="truncate">Ajustes</a></span></li>
                `);
            }

        } else if (viewType === ENTITY.TEAM) {
            const canManage = canManageTeam(viewData);
            // Acesso de equipe NÃO herda de gestor do negócio.
            // Somente moderadores/dono da equipe ou membros aprovados têm alternativas a pedir acesso.
            if (!canManage) {
                if (userTeams.includes(viewId)) {
                    if (memberStatus === 0) {
                        actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'cancel-request', label: 'Cancelar Pedido', color: 'yellow' }));
                    } else {
                        actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'cancel-access', label: 'Cancelar Acesso', color: 'red' }));
                    }
                } else {
                    actionContainer.insertAdjacentHTML('beforeend', UI.actionButton({ action: 'request-join', label: 'Solicitar Acesso', color: 'green' }));
                }
            } else {
                actionContainer.insertAdjacentHTML('beforeend', `
                    <li><button data-sidebar-action="page-settings" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-cog fa-stack-1x text-gray-700"></i></span><span class="truncate">Ajustes</a></span></li>
                `);
            }
        }
    }

    function customMenu() {
        const customMenu = document.querySelector('#custom-menu');
        const standardMenu = document.querySelector('#standard-menu');

        // Limpar menu customizado antes de adicionar novos itens
        if (customMenu) {
            customMenu.innerHTML = '';
        }

        if (viewType === 'dashboard') {
            customMenu.insertAdjacentHTML('beforeend', UI.menuItem({ action: 'my-profile', icon: 'fa-address-card', label: 'Meu Perfil' }));
        } else {
            if (viewType === ENTITY.PROFILE && currentUserData && currentUserData.id === viewId) {
                customMenu.insertAdjacentHTML('beforeend', `
                    <li><button data-sidebar-action="page-settings" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-cog fa-stack-1x text-gray-700"></i></span><span class="truncate">Ajustes</a></span></li>
                `);
            } else {
                customMenu.insertAdjacentHTML('beforeend', `
                    <li id="action-container"></li>
                `);
                pageAction();
            }
            customMenu.insertAdjacentHTML('beforeend',
                UI.menuItem({ action: 'dashboard', icon: 'fa-home', label: 'Início' }) +
                UI.menuItem({ action: 'share-page', icon: 'fa-share', label: 'Compartilhar' })
            );
        }

        // Delegação global do roteador cobre cliques com data-action
    }

    function normalizeNumericId(value) {
        const num = Number(value);
        return Number.isFinite(num) ? num : null;
    }


    // =====================================================================
    // 7. FEED HELPERS & SOCIAL INTERACTIONS
    // =====================================================================

    function formatFeedTimestamp(input) {
        if (!input) return '';
        try {
            const date = new Date(input);
            if (Number.isNaN(date.getTime())) return '';
            const now = new Date();
            const diffMs = now.getTime() - date.getTime();
            const diffMinutes = Math.floor(diffMs / 60000);
            if (diffMinutes < 1) return 'Agora';
            if (diffMinutes < 60) return diffMinutes + ' min';
            const diffHours = Math.floor(diffMinutes / 60);
            if (diffHours < 24) return diffHours + 'h';
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return diffDays + 'd';
            if (date.getFullYear() === now.getFullYear()) {
                return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short' }).format(date);
            }
            return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
        } catch (error) {
            return '';
        }
    }

    function formatFeedRichText(value) {
        const safe = escapeHtml((value ?? '').toString());
        return safe.replace(/(?:\r\n|\n|\r)/g, '<br>');
    }

    async function ensureFeedUsersLoaded(ids = []) {
        const normalized = Array.from(new Set((ids || []).map(normalizeNumericId).filter((id) => id !== null)));
        if (!normalized.length) return;
        const missing = normalized.filter((id) => !feedUserCache.has(String(id)));
        if (!missing.length) return;
        try {
            const res = await apiClient.post('/search', {
                db: 'workz_data',
                table: 'hus',
                columns: ['id', 'tt', 'im', 'feed_privacy', 'page_privacy'],
                conditions: { id: { op: 'IN', value: missing } },
                fetchAll: true,
            });
            const rows = Array.isArray(res?.data) ? res.data : [];
            rows.forEach((row) => {
                if (row?.id == null) return;
                feedUserCache.set(String(row.id), row); pruneFeedUserCache();
            });
        } catch (error) {
            console.error('Failed to load feed users', error);
        }
    }

    function getFeedUserSummary(id) {
        const key = String(id);
        const user = feedUserCache.get(key);
        const name = user?.tt || 'Usuário';
        return {
            id: user?.id ?? id,
            name,
            avatar: resolveImageSrc(user?.im, name, { size: 160 }),
        };
    }

    async function fetchFeedLikes(postIds = []) {
        const normalized = Array.from(new Set((postIds || []).map(normalizeNumericId).filter((id) => id !== null)));
        const likeMap = new Map();
        if (!normalized.length) return likeMap;
        try {
            const res = await apiClient.post('/search', {
                db: 'workz_data',
                table: 'lke',
                columns: ['pl', 'us'],
                conditions: { pl: { op: 'IN', value: normalized } },
                fetchAll: true,
            });
            const rows = Array.isArray(res?.data) ? res.data : [];
            const currentUserId = currentUserData?.id != null ? String(currentUserData.id) : null;
            rows.forEach((row) => {
                const postId = normalizeNumericId(row?.pl);
                if (postId == null) return;
                const key = String(postId);
                if (!likeMap.has(key)) likeMap.set(key, { total: 0, userLiked: false });
                const entry = likeMap.get(key);
                entry.total += 1;
                if (currentUserId && String(row?.us) === currentUserId) entry.userLiked = true;
            });
        } catch (error) {
            console.error('Failed to fetch feed likes', error);
        }
        return likeMap;
    }

    async function fetchFeedComments(postIds = []) {
        const normalized = Array.from(new Set((postIds || []).map(normalizeNumericId).filter((id) => id !== null)));
        const commentsMap = new Map();
        if (!normalized.length) return commentsMap;
        try {
            const res = await apiClient.post('/search', {
                db: 'workz_data',
                table: 'hpl_comments',
                columns: ['id', 'pl', 'us', 'ds', 'dt'],
                conditions: { pl: { op: 'IN', value: normalized } },
                order: { by: 'dt', dir: 'DESC' },
                fetchAll: true,
            });
            const rows = Array.isArray(res?.data) ? res.data : [];
            rows.forEach((row) => {
                const postId = normalizeNumericId(row?.pl);
                if (postId == null) return;
                const key = String(postId);
                if (!commentsMap.has(key)) commentsMap.set(key, []);
                commentsMap.get(key).push(row);
            });
        } catch (error) {
            console.error('Failed to fetch feed comments', error);
        }
        return commentsMap;
    }

    // ===== Privacy helpers for dashboard feed =====
    function isBusinessMemberApproved(businessId) {
        try { return (userBusinessesData || []).some(r => String(r.em) === String(businessId) && Number(r.st) === 1); } catch(_) { return false; }
    }
    function isBusinessManagerLocal(businessId) {
        try { return (userBusinessesData || []).some(r => String(r.em) === String(businessId) && Number(r.st) === 1 && Number(r.nv) >= 3); } catch(_) { return false; }
    }
    function isTeamMemberApproved(teamId) {
        try { return (userTeamsData || []).some(r => String(r.cm) === String(teamId) && Number(r.st) === 1); } catch(_) { return false; }
    }
    function isTeamModeratorLocal(teamId) {
        try { return (userTeamsData || []).some(r => String(r.cm) === String(teamId) && Number(r.st) === 1 && Number(r.nv) >= 3); } catch(_) { return false; }
    }

    async function fetchCompaniesPrivacy(ids = []) {
        const list = Array.from(new Set((ids || []).map(normalizeNumericId).filter((id) => id !== null)));
        const map = new Map();
        if (!list.length) return map;
        try {
            const res = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'companies',
                columns: ['id', 'feed_privacy'],
                conditions: { id: { op: 'IN', value: list } },
                fetchAll: true,
                limit: list.length
            });
            const rows = Array.isArray(res?.data) ? res.data : [];
            rows.forEach(r => { if (r?.id != null) map.set(String(r.id), r); });
        } catch (_) {}
        return map;
    }

    async function fetchTeamsPrivacy(ids = []) {
        const list = Array.from(new Set((ids || []).map(normalizeNumericId).filter((id) => id !== null)));
        const map = new Map();
        if (!list.length) return map;
        try {
            const res = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'teams',
                columns: ['id', 'em', 'feed_privacy'],
                conditions: { id: { op: 'IN', value: list } },
                fetchAll: true,
                limit: list.length
            });
            const rows = Array.isArray(res?.data) ? res.data : [];
            rows.forEach(r => { if (r?.id != null) map.set(String(r.id), r); });
        } catch (_) {}
        return map;
    }

    async function filterDashboardItems(items = []) {
        if (!Array.isArray(items) || !items.length) return [];
        const currentIdStr = String(currentUserData?.id ?? '');
        // Collect ids
        const userIds = new Set();
        const companyIds = new Set();
        const teamIds = new Set();
        items.forEach(it => {
            const us = normalizeNumericId(it?.us); const em = normalizeNumericId(it?.em); const cm = normalizeNumericId(it?.cm);
            if (em && em > 0) companyIds.add(em);
            else if (cm && cm > 0) teamIds.add(cm);
            else if (us && us > 0) userIds.add(us);
        });
        // Load privacy metadata
        try { await ensureFeedUsersLoaded(Array.from(userIds)); } catch(_) {}
        const companiesMap = await fetchCompaniesPrivacy(Array.from(companyIds));
        const teamsMap = await fetchTeamsPrivacy(Array.from(teamIds));

        const allowed = items.filter((it) => {
            const us = normalizeNumericId(it?.us); const em = normalizeNumericId(it?.em); const cm = normalizeNumericId(it?.cm);
            // Personal post
            if ((!em || em === 0) && (!cm || cm === 0)) {
                if (String(us) === currentIdStr) return true; // owner always sees
                const info = feedUserCache.get(String(us));
                const fp = Number(info?.feed_privacy ?? 2);
                if (fp === 0) return false; // Somente eu
                if (fp === 1) {
                    // Seguidores: garantir que segue
                    return Array.isArray(userPeople) && userPeople.map(String).includes(String(us));
                }
                return true; // 2 ou 3
            }
            // Business post
            if (em && em > 0) {
                const row = companiesMap.get(String(em));
                const fp = Number(row?.feed_privacy ?? 1);
                if (fp === 0) return isBusinessManagerLocal(em);
                if (fp === 1) return isBusinessMemberApproved(em);
                // 2 or 3
                return true;
            }
            // Team post
            if (cm && cm > 0) {
                const row = teamsMap.get(String(cm));
                const fp = Number(row?.feed_privacy ?? 1);
                if (fp === 0) return isTeamModeratorLocal(cm);
                if (fp === 1) return isTeamMemberApproved(cm);
                if (fp === 2) {
                    // Todos do negócio: membro de negócio do time
                    const biz = row?.em != null ? row.em : null;
                    return biz != null ? isBusinessMemberApproved(biz) : false;
                }
                return false;
            }
            return false;
        });
        return allowed;
    }

    async function hydrateFeedItems(items = []) {
        if (!Array.isArray(items) || !items.length) return items;
        const postIds = Array.from(new Set(items.map((item) => normalizeNumericId(item?.id)).filter((id) => id !== null)));
        if (!postIds.length) return items;
        const authorIds = Array.from(new Set(items.map((item) => normalizeNumericId(item?.us)).filter((id) => id !== null)));
        await ensureFeedUsersLoaded(authorIds);

        const [likesMap, commentsMap] = await Promise.all([
            fetchFeedLikes(postIds),
            fetchFeedComments(postIds),
        ]);

        const commentAuthorIds = [];
        commentsMap.forEach((commentRows) => {
            commentRows.forEach((row) => {
                const authorId = normalizeNumericId(row?.us);
                if (authorId != null) commentAuthorIds.push(authorId);
            });
        });
        if (commentAuthorIds.length) await ensureFeedUsersLoaded(commentAuthorIds);

        return items.map((item) => {
            const numericId = normalizeNumericId(item?.id);
            const key = numericId != null ? String(numericId) : String(item?.id ?? '');
            const likeInfo = likesMap.get(key) || { total: 0, userLiked: false };
            const comments = (commentsMap.get(key) || []).map((row) => ({
                ...row,
                author: getFeedUserSummary(row.us),
                formattedDate: formatFeedTimestamp(row.dt),
            }));
            // Preparar mídia do post a partir de ct (JSON)
            let parsedCt = null;
            try { parsedCt = typeof item?.ct === 'string' ? JSON.parse(item.ct) : (item?.ct || null); } catch (_) { parsedCt = null; }
            const mediaList = (parsedCt && Array.isArray(parsedCt.media)) ? parsedCt.media : [];
            const media0 = mediaList[0] || null;
            const mmime = String(media0?.mimeType || '').toLowerCase();
            let feedMediaType = media0?.type || (mmime.startsWith('video') ? 'video' : (mmime.startsWith('image') ? 'image' : null));
            let feedMediaUrl = media0?.url || (media0?.path ? ('/' + String(media0.path).replace(/^\/+/, '')) : null);
            // Define imagem de fundo preferindo a mídia (se for imagem)
            let backgroundImage = null;
            if (feedMediaType === 'image' && feedMediaUrl) {
                backgroundImage = resolveBackgroundImage(feedMediaUrl, item?.tt, { size: 1024 });
            } else {
                backgroundImage = resolveBackgroundImage(item?.im, item?.tt, { size: 1024 });
            }
            return {
                ...item,
                author: getFeedUserSummary(item.us),
                likeInfo,
                comments,
                commentCount: comments.length,
                formattedDate: formatFeedTimestamp(item.dt),
                backgroundImage,
                feedMediaType,
                feedMediaUrl,
                ct: parsedCt || item?.ct,
                media: mediaList,
                hasCarousel: Array.isArray(mediaList) && mediaList.length > 1,
            };
        });
    }

    function renderFeedComment(comment, { hidden = false } = {}) {
        if (!comment) return '';
        const commentAuthor = comment?.author ?? getFeedUserSummary(comment?.us);
        const commentName = escapeHtml(commentAuthor?.name || 'Usuário');
        const commentAvatar =
            commentAuthor?.avatar ||
            resolveImageSrc(commentAuthor?.im ?? null, commentAuthor?.tt ?? commentAuthor?.name ?? 'Usuário', { size: 80 });
        const commentText = formatFeedRichText(comment?.ds || '');
        const commentDate = escapeHtml(comment?.formattedDate || formatFeedTimestamp(comment?.dt) || '');
        const hiddenClasses = hidden ? ' hidden extra-comment' : '';
        const extraAttr = hidden ? ' data-role="extra-comment"' : '';
        const commentId = comment?.id != null ? String(comment.id) : '';
        return `
                <div class="flex items-start gap-2 text-xs text-white/90${hiddenClasses}" data-comment-id="${commentId}"${extraAttr}>
                    <span class="w-7 h-7 rounded-full overflow-hidden border border-white/20 bg-black/40 flex items-center justify-center">
                        <img src="${commentAvatar}" alt="${commentName}" class="w-full h-full object-cover">
                    </span>
                    <div class="flex-1 space-y-1">
                        <p class="font-semibold text-white/95">${commentName}</p>
                        <p class="text-white/90 leading-snug">${commentText}</p>
                        ${commentDate ? `<span class="text-[10px] uppercase tracking-wide text-white/60">${commentDate}</span>` : ''}
                    </div>
                </div>`;
    }

    function appendFeed(items) {
        const timeline = document.querySelector('#timeline');
        if (!timeline || !Array.isArray(items) || !items.length) return;

        // Verificar posts duplicados
        const existingPostIds = new Set();
        timeline.querySelectorAll('[data-post-id]').forEach(post => {
            existingPostIds.add(post.dataset.postId);
        });

        // Filtrar apenas posts que não existem
        const newItems = items.filter(post => !existingPostIds.has(String(post?.id ?? '')));
        if (!newItems.length) return;

        const html = newItems.map((post) => {
            const author = post?.author ?? {};
            const authorName = escapeHtml(author?.name || 'Usuário');
            const avatarSrc = author?.avatar || resolveImageSrc(null, authorName, { size: 100 });
            const title = escapeHtml(post?.tt || '');
            const captionRaw = (typeof post?.ct === 'string') ? post.ct : (post?.ct?.caption || post?.ct?.text || '');
            const caption = formatFeedRichText(captionRaw || '');
            const formattedDate = escapeHtml(post?.formattedDate || '');
            const likeInfo = post?.likeInfo || {};
            const likeTotal = Number.isFinite(Number(likeInfo.total)) ? Number(likeInfo.total) : 0;
            const userLiked = !!likeInfo.userLiked;
            const likeLabel = likeTotal === 1 ? 'curtida' : 'curtidas';
            const postId = String(post?.id ?? '');
            const backgroundStyle = (!post?.hasCarousel && post?.feedMediaType !== 'video' && post?.backgroundImage) ? `background-image: ${post.backgroundImage};` : '';
            let mediaMarkup = '';
            if (post?.hasCarousel && Array.isArray(post.media)) {
                const slides = post.media.map((m, idx) => {
                    const mtype = String(m.type || '').toLowerCase();
                    const murl = m.url || (m.path ? ('/' + String(m.path).replace(/^\/+/, '')) : '');
                    const isVid = (mtype === 'video' || String(m.mimeType||'').toLowerCase().startsWith('video'));
                    const inner = isVid
                        ? `<video src="${murl}" class="absolute inset-0 w-full h-full object-cover" ${idx===0 ? 'autoplay' : ''} muted loop playsinline data-carousel-video></video>`
                        : `<img src="${murl}" class="absolute inset-0 w-full h-full object-cover"/>`;
                    return `<div class="inline-block align-top w-full h-full relative">${inner}</div>`;
                }).join('');
                mediaMarkup = `
                <div class="absolute inset-0 overflow-hidden" data-role="carousel" data-post-id="${postId}" data-index="0">
                    <div class="h-full whitespace-nowrap transition-transform duration-300 ease-out" data-carousel-track style="transform: translateX(0);">
                        ${slides}
                    </div>
                    <div class="absolute inset-x-0 bottom-2 z-30 flex items-center justify-center gap-2 text-xs">
                        <button type="button" class="px-2 py-1 rounded-full bg-black/40 text-white" data-feed-action="prev-slide" data-post-id="${postId}">&#10094;</button>
                        <span class="rounded-full bg-black/40 text-white px-2 py-1" data-role="carousel-indicator" data-post-id="${postId}">1/${post.media.length}</span>
                        <button type="button" class="px-2 py-1 rounded-full bg-black/40 text-white" data-feed-action="next-slide" data-post-id="${postId}">&#10095;</button>
                    </div>
                </div>`;
            } else if (post?.feedMediaType === 'video' && post?.feedMediaUrl) {
                mediaMarkup = `<video class="absolute inset-0 w-full h-full object-cover" src="${post.feedMediaUrl}" muted autoplay loop playsinline></video>`;
            }
            const comments = Array.isArray(post?.comments) ? post.comments : [];
            const commentList = comments.map((comment) => renderFeedComment(comment)).join('');
            const canDelete = (
                currentUserData?.id && String(currentUserData.id) === String(post.us)
            ) || (
                Number(post.cm) > 0 && Array.isArray(userTeamsData) && userTeamsData.some(t => String(t.cm) === String(post.cm) && Number(t.st) === 1 && Number(t.nv) >= 3)
            ) || (
                Number(post.em) > 0 && Array.isArray(userBusinessesData) && userBusinessesData.some(b => String(b.em) === String(post.em) && Number(b.st) === 1 && Number(b.nv) >= 3)
            );

            return `
        <article class="col-span-12 sm:col-span-6 lg:col-span-4" data-post-id="${postId}">
            <div class="relative aspect-[3/4] rounded-3xl overflow-hidden shadow-lg bg-gray-900 text-white">
                <div class="absolute inset-0 bg-cover bg-center" style="${backgroundStyle}"></div>
                ${mediaMarkup}
                <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-black/10 to-black/40"></div>
                <div class="relative z-1 flex flex-col h-full justify-between">
                    <header class="flex items-center gap-2 p-4">
                        <span class="w-9 h-9 rounded-full overflow-hidden border border-white/30 bg-black/40 flex items-center justify-center">
                            <img src="${avatarSrc}" alt="${authorName}" class="w-full h-full object-cover">
                        </span>
                        <div class="flex flex-col">
                            <span class="font-semibold text-s leading-tight">${authorName}</span>
                            ${formattedDate ? `<time class="text-xs text-white/70">${formattedDate}</time>` : ''}
                        </div>
                    </header>
                    <button type="button" class="absolute top-2 right-2 w-9 h-9 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center backdrop-blur-sm" data-feed-action="open-post-menu" data-post-id="${postId}" data-media-url="${post?.feedMediaUrl || ''}" data-media-type="${post?.feedMediaType || ''}">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <div class="absolute top-12 right-2 w-44 bg-black/80 text-white rounded-xl shadow-lg border border-white/10 hidden z-40" data-role="post-menu" data-post-id="${postId}" data-media-url="${post?.feedMediaUrl || ''}" data-media-type="${post?.feedMediaType || ''}" data-can-delete="${canDelete ? '1' : '0'}">
                        <button type="button" class="w-full text-left px-3 py-2 hover:bg-white/10" data-feed-action="download-media" data-post-id="${postId}">
                            <i class="fas fa-download mr-2"></i>${post?.feedMediaType === 'video' ? 'Baixar vídeo' : 'Baixar imagem'}
                        </button>
                        <button type="button" class="w-full text-left px-3 py-2 hover:bg-white/10" data-feed-action="report-post" data-post-id="${postId}">
                            <i class="fas fa-flag mr-2"></i>Denunciar
                        </button>
                        ${canDelete ? `<button type=\"button\" class=\"w-full text-left px-3 py-2 hover:bg-red-500/20 text-red-300\" data-feed-action=\"delete-post\" data-post-id=\"${postId}\"><i class=\\\"fas fa-trash mr-2\\\"></i>Excluir</button>` : ''}
                    </div>
                    <div class="px-4 pb-5 space-y-4">
                        ${title ? `<h3 class="text-lg font-semibold text-white drop-shadow">${title}</h3>` : ''}
                        ${caption ? `<p class="text-sm text-white/90 leading-relaxed">${caption}</p>` : ''}
                        <div class="flex items-center gap-3">
                            <button type="button" class="flex items-center justify-center w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 transition text-lg ${userLiked ? 'text-red-400' : 'text-white'}" data-feed-action="toggle-like" data-post-id="${postId}" data-liked="${userLiked ? '1' : '0'}" aria-pressed="${userLiked}">
                                <i class="${userLiked ? 'fas' : 'far'} fa-heart"></i>
                            </button>
                            <button type="button" class="flex items-center justify-center w-10 h-10 rounded-full bg-black/40 hover:bg-black/60 transition text-lg text-white" data-feed-action="open-comments" data-post-id="${postId}">
                                <i class="far fa-comment"></i>
                            </button>
                            <span class="text-sm text-white/90" data-role="like-count" data-post-id="${postId}" data-count="${likeTotal}">${likeTotal} ${likeLabel}</span>
                        </div>
                        <div class="space-y-3 hidden" data-role="comment-block" data-post-id="${postId}">
                            <div class="space-y-3" data-role="comment-list" data-post-id="${postId}">
                                ${commentList || '<p class="text-xs text-white/60" data-role="empty-comments">Ainda sem comentários.</p>'}
                            </div>
                            <form class="flex items-center gap-2 bg-black/40 rounded-full px-3 py-2" data-feed-action="comment-form" data-post-id="${postId}">
                                <input type="text" name="comment" class="flex-1 bg-transparent border-none text-sm text-white placeholder-white/60 focus:outline-none" placeholder="Adicione um comentário..." autocomplete="off" maxlength="300">
                                <button type="submit" class="text-sm font-semibold text-white hover:text-indigo-200 transition">Publicar</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </article>`;
        }).join('');

        timeline.insertAdjacentHTML('beforeend', html);
        enhanceFeedCards(newItems);
        ensureFeedInteractions();
    }

    function enhanceFeedCards(items) {
        if (!Array.isArray(items) || !items.length) return;
        items.forEach((post) => {
            const postId = String(post?.id ?? '');
            const article = document.querySelector(`[data-post-id="${postId}"]`);
            if (!article) return;
            // Seleciona de forma robusta o contêiner do card (primeiro div dentro do article)
            const card = article.querySelector(':scope > div') || article.querySelector('div');
            const commentBlock = article.querySelector(`[data-role="comment-block"][data-post-id="${postId}"]`);
            if (!card || !commentBlock) return;
            // Cria overlay e move bloco de comentários para dentro
            const overlay = document.createElement('section');
            overlay.className = 'absolute inset-0 bg-black/70 backdrop-blur-sm hidden z-30';
            overlay.setAttribute('data-role','comment-overlay');
            overlay.setAttribute('data-post-id', postId);
            overlay.innerHTML = `
                <div class="relative z-20 flex flex-col h-full p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-white font-semibold">Comentários</h4>
                        <button type="button" class="w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 text-white" data-feed-action="close-comments" data-post-id="${postId}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>`;
            const holder = overlay.querySelector('.flex.flex-col.h-full');
            // Cria containers para lista (scroll) e formulário fixo no rodapé
            const listHolder = document.createElement('div');
            listHolder.className = 'flex-1 overflow-auto pr-1';
            listHolder.setAttribute('data-role','list-holder');
            holder.appendChild(listHolder);

            const formHolder = document.createElement('div');
            formHolder.className = 'pt-2';
            formHolder.setAttribute('data-role','form-holder');
            holder.appendChild(formHolder);

            // Extrai lista e formulário do bloco original e move para a overlay
            const list = commentBlock.querySelector('[data-role="comment-list"]');
            if (list) { list.classList.add('space-y-3'); listHolder.appendChild(list); }
            const form = commentBlock.querySelector('[data-feed-action="comment-form"]');
            if (form) { form.classList.add('bg-white/10'); formHolder.appendChild(form); }
            // Mantém bloco original oculto no card
            commentBlock.classList.add('hidden');
            // Insere overlay dentro do card (necessário para absolute inset-0)
            card.appendChild(overlay);

            // Autoplay do primeiro vídeo em carrossel
            try {
                const carousel = article.querySelector(`[data-role="carousel"][data-post-id="${postId}"]`);
                if (carousel) {
                    const track = carousel.querySelector('[data-carousel-track]');
                    const firstVid = track?.children?.[0]?.querySelector('video');
                    if (firstVid) {
                        firstVid.muted = true; firstVid.playsInline = true; firstVid.autoplay = true;
                        // Forçar reprodução após inserção no DOM
                        setTimeout(() => { try { firstVid.play(); } catch(_) {} }, 50);
                    }
                }
            } catch (_) {}
        });
    }

    function ensureFeedInteractions() {
        const timeline = document.querySelector('#timeline');
        if (!timeline || feedInteractionsAttached) return;
        timeline.addEventListener('click', handleFeedClick);
        timeline.addEventListener('submit', handleFeedSubmit);
        feedInteractionsAttached = true;
    }

    async function handleFeedClick(event) {
        const timeline = event.currentTarget;
        const target = event.target.closest('[data-feed-action]');
        if (!target || !(timeline instanceof Element) || !timeline.contains(target)) return;

        const action = target.dataset.feedAction;
        if (action === 'toggle-like') {
            event.preventDefault();
            const postId = target.dataset.postId;
            if (postId) await togglePostLike(postId, target);
        } else if (action === 'open-comments') {
            event.preventDefault();
            const postId = target.dataset.postId;
            const overlay = timeline.querySelector(`[data-role="comment-overlay"][data-post-id="${postId}"]`);
            if (overlay) overlay.classList.remove('hidden');
        } else if (action === 'close-comments') {
            event.preventDefault();
            const postId = target.dataset.postId;
            const overlay = timeline.querySelector(`[data-role="comment-overlay"][data-post-id="${postId}"]`);
            if (overlay) overlay.classList.add('hidden');
        } else if (action === 'open-post-menu') {
            event.preventDefault();
            const postId = target.dataset.postId;
            timeline.querySelectorAll('[data-role="post-menu"]').forEach(menu => {
                if (menu.dataset.postId !== postId) menu.classList.add('hidden');
            });
            const menu = timeline.querySelector(`[data-role="post-menu"][data-post-id="${postId}"]`);
            if (menu) menu.classList.toggle('hidden');
        } else if (action === 'prev-slide' || action === 'next-slide') {
            event.preventDefault();
            const postId = target.dataset.postId;
            const carousel = timeline.querySelector(`[data-role="carousel"][data-post-id="${postId}"]`);
            if (!carousel) return;
            const track = carousel.querySelector('[data-carousel-track]');
            if (!track) return;
            const total = track.children.length;
            if (!total) return;
            let index = Number(carousel.dataset.index || '0') || 0;
            if (action === 'prev-slide') index = Math.max(0, index - 1);
            else index = Math.min(total - 1, index + 1);
            carousel.dataset.index = String(index);
            track.style.transform = `translateX(${-index * 100}%)`;
            const indicator = timeline.querySelector(`[data-role="carousel-indicator"][data-post-id="${postId}"]`);
            if (indicator) indicator.textContent = `${index + 1}/${total}`;
            const opener = timeline.querySelector(`[data-feed-action="open-post-menu"][data-post-id="${postId}"]`);
            const activeMedia = track.children[index]?.querySelector('img,video');
            if (opener && activeMedia) {
                opener.dataset.mediaUrl = activeMedia.getAttribute('src') || '';
                opener.dataset.mediaType = activeMedia.tagName.toLowerCase() === 'video' ? 'video' : 'image';
                const menu = timeline.querySelector(`[data-role="post-menu"][data-post-id="${postId}"]`);
                if (menu) {
                    menu.dataset.mediaUrl = opener.dataset.mediaUrl;
                    menu.dataset.mediaType = opener.dataset.mediaType;
                }
            }
            // Controlar reprodução dos vídeos no carrossel
            try {
                const vids = track.querySelectorAll('video');
                vids.forEach(v => { try { v.pause(); v.currentTime = 0; } catch(_){} });
                const currentVid = track.children[index]?.querySelector('video');
                if (currentVid) {
                    currentVid.muted = true; currentVid.playsInline = true; currentVid.autoplay = true;
                    currentVid.play().catch(()=>{});
                }
            } catch(_) {}
        } else if (action === 'download-media') {
            event.preventDefault();
            const postId = target.dataset.postId;
            const menu = target.closest('[data-role="post-menu"]');
            const url = menu?.dataset.mediaUrl || '';
            const type = menu?.dataset.mediaType || '';
            if (!url) { if (typeof notifyError === 'function') notifyError('Mídia indisponível.'); return; }
            const a = document.createElement('a');
            const ext = (() => { try { const u = new URL(url, window.location.origin); const p = u.pathname; const m = p.match(/\.([a-zA-Z0-9]+)$/); return m ? m[1] : (type==='video' ? 'webm' : 'jpg'); } catch(_) { return type==='video' ? 'webm' : 'jpg'; } })();
            a.href = url; a.download = `post_${postId}.${ext}`; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove();
            if (menu) menu.classList.add('hidden');
        } else if (action === 'report-post') {
            event.preventDefault();
            const menu = target.closest('[data-role="post-menu"]');
            if (menu) menu.classList.add('hidden');
            if (typeof notifySuccess === 'function') notifySuccess('Denúncia registrada. Obrigado por nos ajudar.');
        } else if (action === 'delete-post') {
            event.preventDefault();
            const postId = Number(target.dataset.postId);
            const menu = target.closest('[data-role="post-menu"]');
            const canDelete = menu?.dataset?.canDelete === '1';
            if (!canDelete) { if (typeof notifyError === 'function') notifyError('Você não tem permissão para excluir.'); return; }
            const confirmed = await confirmDialog('Excluir esta publicação?', { danger: true, title: 'Confirmar exclusão' });
            if (!confirmed) return;
            try {
                // Backend faz cascade delete (post + mídias)
                const json = await apiClient.post('/app/delete_post.php', { id: postId });
                if (json?.error) { throw new Error(json?.error || 'Falha ao excluir'); }
                const article = timeline.querySelector(`[data-post-id="${postId}"]`);
                if (article && article.parentElement) article.parentElement.removeChild(article);
                if (typeof notifySuccess === 'function') notifySuccess('Publicação excluída.');
                // Nada a limpar no cliente; servidor já remove mídias
            } catch (e) {
                console.error('Failed to delete post', e);
                if (typeof notifyError === 'function') notifyError('Não foi possível excluir a publicação.');
            } finally {
                if (menu) menu.classList.add('hidden');
            }
            
        }
    }

    // (remoção de mídia agora é responsabilidade do backend em /app/delete_post.php)

    function adjustLikeButtonAppearance(button, liked) {
        button.dataset.liked = liked ? '1' : '0';
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');
        const icon = button.querySelector('i');
        if (liked) {
            button.classList.add('text-red-400');
            button.classList.remove('text-white');
            if (icon) {
                icon.classList.remove('far');
                icon.classList.add('fas');
            }
        } else {
            button.classList.add('text-white');
            button.classList.remove('text-red-400');
            if (icon) {
                icon.classList.remove('fas');
                icon.classList.add('far');
            }
        }
    }

    function updateLikeCountElement(el, count) {
        if (!el) return;
        const safeCount = Math.max(0, Number(count) || 0);
        el.dataset.count = String(safeCount);
        const label = safeCount === 1 ? 'curtida' : 'curtidas';
        el.textContent = `${safeCount} ${label}`;
    }

    async function togglePostLike(postId, button) {
        if (!currentUserData?.id) {
            if (typeof notifyError === 'function') notifyError('É necessário estar logado para curtir.');
            return;
        }
        if (button.dataset.loading === '1') return;
        const numericPostId = Number(postId);
        if (!Number.isFinite(numericPostId)) return;

        button.dataset.loading = '1';
        const likeCountEl = document.querySelector(`[data-role="like-count"][data-post-id="${postId}"]`);
        const currentCount = Number(likeCountEl?.dataset.count ?? 0) || 0;
        const isCurrentlyLiked = button.dataset.liked === '1';

        try {
            if (isCurrentlyLiked) {
                await apiClient.post('/delete', { db: 'workz_data', table: 'lke', conditions: { pl: numericPostId, us: currentUserData.id } });
                adjustLikeButtonAppearance(button, false);
                updateLikeCountElement(likeCountEl, currentCount - 1);
            } else {
                const now2 = new Date();
                const dtStr2 = `${now2.getFullYear()}-${String(now2.getMonth()+1).padStart(2,'0')}-${String(now2.getDate()).padStart(2,'0')} ${String(now2.getHours()).padStart(2,'0')}:${String(now2.getMinutes()).padStart(2,'0')}:${String(now2.getSeconds()).padStart(2,'0')}`;
                await apiClient.post('/insert', { db: 'workz_data', table: 'lke', data: { pl: numericPostId, us: currentUserData.id, dt: dtStr2 } });
                adjustLikeButtonAppearance(button, true);
                updateLikeCountElement(likeCountEl, currentCount + 1);
            }
        } catch (error) {
            console.error('Failed to toggle like', error);
            if (typeof notifyError === 'function') notifyError('Não foi possível atualizar sua curtida. Tente novamente.');
        } finally {
            delete button.dataset.loading;
        }
    }

    function revealAllComments(button) {
        const block = button.closest('[data-role="comment-block"]');
        if (!block) return;
        const extras = block.querySelectorAll('[data-role="extra-comment"]');
        extras.forEach((node) => {
            node.classList.remove('hidden', 'extra-comment');
            node.removeAttribute('data-role');
        });
        button.remove();
    }

    function handleFeedSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.feedAction !== 'comment-form') return;
        event.preventDefault();
        submitCommentForm(form);
    }

    async function submitCommentForm(form) {
        if (!currentUserData?.id) {
            if (typeof notifyError === 'function') notifyError('É necessário estar logado para comentar.');
            return;
        }
        if (form.dataset.loading === '1') return;
        const input = form.querySelector('input[name="comment"]');
        const message = (input?.value ?? '').trim();
        if (!message) return;

        const postId = form.dataset.postId;
        const numericPostId = Number(postId);
        if (!postId || !Number.isFinite(numericPostId)) return;

        form.dataset.loading = '1';
        const now = new Date();
        const dtStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')} ${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
        const payload = { pl: numericPostId, us: currentUserData.id, ds: message, dt: dtStr };
        try {
            const result = await apiClient.post('/insert', { db: 'workz_data', table: 'hpl_comments', data: payload });
            feedUserCache.set(String(currentUserData.id), { id: currentUserData.id, tt: currentUserData.tt, im: currentUserData.im }); pruneFeedUserCache();
            const commentData = {
                id: result?.id ?? result?.insertId ?? Date.now(),
                us: currentUserData.id,
                ds: message,
                dt: dtStr,
                author: {
                    id: currentUserData.id,
                    name: currentUserData.tt || 'Você',
                    avatar: resolveImageSrc(currentUserData.im, currentUserData.tt, { size: 80 }),
                },
                formattedDate: formatFeedTimestamp(dtStr),
            };
            // Encontrar a lista dentro da overlay da própria publicação
            const overlay = form.closest('[data-role="comment-overlay"]');
            let list = overlay?.querySelector(`[data-role="comment-list"][data-post-id="${postId}"]`);
            if (!list) {
                list = document.querySelector(`[data-role="comment-list"][data-post-id="${postId}"]`);
            }
            if (!list && overlay) {
                const holder = overlay.querySelector('[data-role="list-holder"]') || overlay;
                list = document.createElement('div');
                list.className = 'space-y-3';
                list.setAttribute('data-role','comment-list');
                list.setAttribute('data-post-id', postId);
                holder.appendChild(list);
            }
            if (list) {
                const emptyState = list.querySelector('[data-role="empty-comments"]');
                if (emptyState) emptyState.remove();
                list.insertAdjacentHTML('afterbegin', renderFeedComment(commentData));
                try { list.scrollTop = 0; } catch (_) {}
                // rolar para o fim para mostrar o novo comentário
                try { list.scrollTop = list.scrollHeight; } catch (_) {}
            }
            if (input) input.value = '';
        } catch (error) {
            console.error('Failed to submit comment', error);
            if (typeof notifyError === 'function') notifyError('Não foi possível enviar seu comentário. Tente novamente.');
        } finally {
            delete form.dataset.loading;
        }
    }

    // Exibição de loading (abstrai jQuery e permite trocar no futuro)
    function showLoading() {
        const loader = document.getElementById('loading');
        if (!loader) return;
        const jq = (typeof window !== 'undefined') ? window.jQuery : null;
        if (typeof jq === 'function' && jq.fn && typeof jq.fn.fadeIn === 'function') {
            jq(loader).stop(true, true).fadeIn();
        } else {
            loader.style.display = 'block';
            loader.style.opacity = '1';
        }
    }
    function hideLoading({ delay = 0 } = {}) {
        const loader = document.getElementById('loading');
        if (!loader) return;
        const perform = () => {
            const jq = (typeof window !== 'undefined') ? window.jQuery : null;
            if (typeof jq === 'function' && jq.fn && typeof jq.fn.fadeOut === 'function') {
                jq(loader).stop(true, true).fadeOut();
            } else {
                loader.style.opacity = '0';
                loader.style.display = 'none';
            }
        };
        if (delay > 0) {
            window.setTimeout(perform, delay);
        } else {
            perform();
        }
    }

    // Navegação centralizada (mantém padrão e facilita manutenção)

    // =====================================================================
    // 8. GENERIC HELPERS & INFRASTRUCTURE
    // =====================================================================

    function navigateTo(path) {
        history.pushState({}, '', path);
        showLoading();
        loadPage();
    }

    // Habilita navegação pelo botão Voltar/Avançar do navegador
    window.addEventListener('popstate', () => {
        try { showLoading(); } catch(_) {}
        try { loadPage(); } catch(_) {}
    });

    // Snapshot simples do estado (facilita roteadores e handlers)
    function getState() {
        return {
            user: currentUserData,
            view: { type: viewType, id: viewId, data: viewData },
            memberships: { people: userPeople, businesses: userBusinesses, teams: userTeams, level: memberLevel, status: memberStatus }
        };
    }

    // Notificações simples (disponíveis globalmente)
    function notifySuccess(msg) { try { swal('Pronto', msg, 'success'); } catch (_) { try { alert(msg); } catch (__) { } } }
    function notifyError(msg) { try { swal('Ops', msg, 'error'); } catch (_) { try { alert(msg); } catch (__) { } } }
    
    // Tornar funções disponíveis globalmente para o editor
    window.notifySuccess = notifySuccess;
    window.notifyError = notifyError;

    async function confirmDialog(msg, { title = 'Confirmação', danger = false } = {}) {
        try {
            return await swal({
                title,
                text: msg,
                icon: danger ? 'warning' : 'info',
                buttons: ['Cancelar', 'Confirmar'],
                dangerMode: !!danger,
            });
        } catch (_) {
            try { return confirm(msg); } catch (__) { return false; }
        }
    }

    // UX helpers (debounce e estado de carregamento em botões)
    function debounce(fn, delay = 250) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), delay); };
    }

    function setButtonLoading(button, loading = true, textWhileLoading = 'Aguarde…') {
        if (!button) return;
        if (loading) {
            if (!button.dataset.origHtml) button.dataset.origHtml = button.innerHTML;
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
            button.innerHTML = `<span class="inline-block animate-pulse">${textWhileLoading}</span>`;
        } else {
            button.disabled = false;
            button.classList.remove('opacity-60', 'cursor-not-allowed');
            if (button.dataset.origHtml) { button.innerHTML = button.dataset.origHtml; delete button.dataset.origHtml; }
        }
    }

    // Compartilhar página atual
    async function sharePage() {
        const url = window.location.href;
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title || 'Workz!', url });
                return true;
            }
        } catch (_) { }
        try {
            await navigator.clipboard.writeText(url);
            notifySuccess('Link copiado!');
            return true;
        } catch (_) {
            prompt('Copie o link da página:', url);
            return false;
        }
    }

    // Helpers de mapeamento de condições por tipo de view
    function getPostConditions(type, id) {
        const base = { st: 1 };
        if (type === ENTITY.PROFILE) {
            // Se conteúdo está restrito pela privacidade, não retorna posts
            if (viewRestricted) return { us: -1, em: 0, cm: 0 };
            return { ...base, us: id, em: 0, cm: 0 };
        }
        if (type === ENTITY.BUSINESS) {
            // Se conteúdo está restrito pela privacidade, não retorna posts
            if (viewRestricted) return { em: -1 };
            return { ...base, em: id };
        }
        if (type === ENTITY.TEAM) {
            // Se acesso à equipe está restrito, não retorna posts
            if (viewRestricted) return { cm: -1 };
            return { ...base, cm: id };
        }
        return base;
    }

    function getFollowersConditions(type, id) {
        if (type === ENTITY.PROFILE) return { s1: id };
        return null;
    }

    // Determines if the current viewer can see the entity content
    function canViewEntityContent() {
        try {
            const vt = viewType;
            const data = viewData || {};
            const logged = !!localStorage.getItem('jwt_token');
            if (!vt || !data) return true;

            if (vt === ENTITY.PROFILE) {
                const owner = String(currentUserData?.id ?? '') === String(data?.id ?? '');
                if (owner) return true;
                const fp = Number(data?.feed_privacy ?? 2);
                if (fp === 0) return false; // only me
                if (fp === 1) {
                    const idStr = String(data?.id ?? '');
                    return Array.isArray(userPeople) && userPeople.map(String).includes(idStr);
                }
                if (fp === 2) return logged; // logged users
                return true; // 3: internet
            }

            if (vt === ENTITY.BUSINESS) {
                const fp = Number(data?.feed_privacy ?? 1);
                if (fp === 0) {
                    const isManager = isBusinessManager(data?.id);
                    let isModerator = false;
                    try { const mods = data?.usmn ? JSON.parse(data.usmn) : []; isModerator = Array.isArray(mods) && mods.map(String).includes(String(currentUserData?.id)); } catch(_) {}
                    return isManager || isModerator;
                }
                if (fp === 1) {
                    return Array.isArray(userBusinessesData) && userBusinessesData.some(r => String(r.em) === String(data?.id) && Number(r.st) === 1);
                }
                if (fp === 2) return logged;
                return true;
            }

            if (vt === ENTITY.TEAM) {
                const fp = Number(data?.feed_privacy ?? 1);
                const teamId = data?.id;
                if (fp === 0) { try { return isTeamOwner(data) || isTeamModerator(data); } catch(_) { return false; } }
                if (fp === 1) { return Array.isArray(userTeamsData) && userTeamsData.some(r => String(r.cm) === String(teamId) && Number(r.st) === 1); }
                if (fp === 2) { const bizId = data?.em; return Array.isArray(userBusinessesData) && userBusinessesData.some(r => String(r.em) === String(bizId) && Number(r.st) === 1); }
                return false;
            }
            return true;
        } catch(_) { return true; }
    }

    // Exibe um aviso sobre a restrição de privacidade do CONTEÚDO
    function getContentPrivacyText(vt, fp) {
        const p = Number(fp);
        if (!Number.isFinite(p)) return null;
        if (vt === ENTITY.PROFILE) {
            if (p === 0) return 'Somente você';
            if (p === 1) return 'Seguidores';
            if (p === 2) return 'Usuários logados';
            return null; // 3 = Toda a internet (sem aviso)
        }
        if (vt === ENTITY.BUSINESS) {
            if (p === 0) return 'Moderadores';
            if (p === 1) return 'Usuários membros';
            if (p === 2) return 'Usuários logados';
            return null;
        }
        if (vt === ENTITY.TEAM) {
            if (p === 0) return 'Moderadores da equipe';
            if (p === 1) return 'Membros da equipe';
            if (p === 2) return 'Todos do negócio';
            return null;
        }
        return null;
    }

    function insertContentPrivacyNotice() {
        try {
            const vt = viewType;
            const fp = viewData?.feed_privacy;
            const text = getContentPrivacyText(vt, fp);
            // Só mostra quando há restrição (quando text != null)
            if (!text) return;
            if (canViewEntityContent()) return;
            const anchor = document.querySelector('#main-content > div > div');
            if (!anchor || !anchor.parentElement) return;
            const existing = document.getElementById('content-privacy-notice');
            if (existing) try { existing.remove(); } catch (_) {}
            const html = `
                <div id="content-privacy-notice" class="w-full mt-3">
                    <div class="rounded-3xl w-full p-3 bg-yellow-50 border border-yellow-200 text-yellow-800 shadow-md">
                        <i class="fas fa-lock mr-2"></i>
                        <span>Conteúdo visível apenas para <strong>${text}</strong>.</span>
                    </div>
                </div>`;
            anchor.insertAdjacentHTML('afterend', html);
            try {
                const isOwner = String(currentUserData?.id ?? '') === String(viewData?.id ?? '');
                const isOnlyMe = (vt === ENTITY.PROFILE) && Number(fp) === 0;
                if (!isOwner && isOnlyMe) {
                    const span = document.querySelector('#content-privacy-notice span');
                    if (span) span.textContent = 'Conteudo indisponivel.';
                }
            } catch (_) {}
        } catch (_) { }
    }

    // Roteador de ações centralizado
    // Helper: abre o post-editor e aguarda o Editor ficar pronto
    async function openPostEditorAnd(waitForFn = null) {
        const mockElement = document.createElement('div');
        mockElement.dataset.sidebarAction = 'post-editor';
        try {
            await toggleSidebar(mockElement, true);
        } catch (_) {
            try { toggleSidebar(mockElement, true); } catch (_) {}
        }
        return new Promise((resolve) => {
            const started = Date.now();
            const check = () => {
                const sc = document.querySelector('.sidebar-content');
                const bridgeReady = !!window.EditorBridge;
                const viewport = sc && sc.querySelector('#editorViewport');
                const inited = !!(viewport && viewport.dataset && viewport.dataset.initialized === '1');
                const ok = typeof waitForFn === 'function' ? !!waitForFn(sc) : !!viewport;
                if ((bridgeReady && ok && inited) || (Date.now() - started > 8000)) {
                    resolve(sc || document);
                } else {
                    setTimeout(check, 100);
                }
            };
            check();
        });
    }

    // ===== Desktop background helpers =====
    const DESKTOP_BG_KEY = 'workz.desktop.background';
    const DEFAULT_BING_BG = 'https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US';
    function getDesktopBg() {
        try { return localStorage.getItem(DESKTOP_BG_KEY) || ''; } catch (_) { return ''; }
    }
    function setDesktopBg(val) {
        try {
            if (val) localStorage.setItem(DESKTOP_BG_KEY, val);
            else localStorage.removeItem(DESKTOP_BG_KEY);
        } catch (_) {}
        return applyDesktopBackgroundFromSettings();
    }
    function applyDesktopBackgroundFromSettings() {
        const stored = getDesktopBg();
        const url = stored || DEFAULT_BING_BG;
        try {
            const target = document.querySelector('#main-content > div');
            if (target) {
                target.style.backgroundImage = `url(${url})`;
                target.style.backgroundPosition = 'center';
                target.style.backgroundRepeat = 'no-repeat';
                target.style.backgroundSize = 'cover';
            }
        } catch (_) {}
        return url;
    }
    function refreshDesktopBgPreview(scope = null) {
        const url = getDesktopBg() || DEFAULT_BING_BG;
        const mount = scope || document.querySelector('.sidebar-content');
        try {
            const prev = mount && mount.querySelector('#desktop-bg-preview');
            if (prev) prev.style.backgroundImage = `url(${url})`;
            const rmBtn = mount && mount.querySelector('[data-action="desktop-bg-remove"]');
            if (rmBtn) rmBtn.disabled = !getDesktopBg();
        } catch (_) {}
    }

    // ===== Sidebar actions =====
    const ACTIONS = {
        'dashboard': ({ state }) => navigateTo('/'),
        'my-profile': ({ state }) => navigateTo(`/profile/${state.user?.id}`),
        'share-page': () => sharePage(),
        'list-people': () => navigateTo('/people'),
        'list-businesses': () => navigateTo('/businesses'),
        'list-teams': () => navigateTo('/teams'),
        'logout': () => handleLogout(),

        // Desktop settings actions
        'desktop-toggle-hide-favorites': ({ event, button }) => {
            try {
                const isOn = String(button.getAttribute('aria-checked')) === 'true';
                const newState = !isOn;
                button.setAttribute('aria-checked', newState ? 'true' : 'false');
                localStorage.setItem('workz.apps.hideFavorites', newState ? '1' : '0');
            } catch (_) {}
            try {
                const lib = document.querySelector('#app-library');
                if (lib && typeof lib._applyAppGridFilters === 'function') {
                    lib._applyAppGridFilters();
                }
            } catch (_) {}
        },
        'desktop-bg-open-picker': ({ button }) => {
            const mount = button.closest('.sidebar-content') || document;
            const input = mount.querySelector('#desktop-bg-file');
            if (!input) return;
            try { if (input._bound) input.removeEventListener('change', input._bound); } catch (_) {}
            const onChange = (ev) => {
                try { input.removeEventListener('change', onChange); } catch (_) {}
                const file = ev?.target?.files?.[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = () => { try { setDesktopBg(reader.result); refreshDesktopBgPreview(mount); } catch (_) {} };
                try { reader.readAsDataURL(file); } catch (_) {}
                try { ev.target.value = ''; } catch (_) {}
            };
            input.addEventListener('change', onChange);
            input._bound = onChange;
            try { input.click(); } catch (_) {}
        },
        'desktop-bg-remove': ({ button }) => {
            try { setDesktopBg(''); } catch (_) {}
            try { refreshDesktopBgPreview(button.closest('.sidebar-content') || document); } catch (_) {}
        },
        // Editor: atalhos rápidos
        'editor-quick-text': async () => {
            try {
                const sc = await openPostEditorAnd(sc => sc && sc.querySelector('#btnAddText'));
                const btn = (sc || document).querySelector('#btnAddText');
                if (btn) setTimeout(() => { try { btn.click(); } catch (_) {} }, 60);
            } catch (_) {}
        },
        'editor-quick-media': async () => {
            try {
                const sc = await openPostEditorAnd(sc => sc && (sc.querySelector('#postMediaPicker') || sc.querySelector('#btnAddImg')));
                const picker = (sc || document).querySelector('#postMediaPicker');
                if (picker) {
                    picker.click();
                    return;
                }
                // Fallback: abre o seletor de imagem como item sobreposto (não entra na galeria)
                const btn = (sc || document).querySelector('#btnAddImg');
                if (btn) btn.click();
            } catch (_) {}
        },
        'editor-quick-bg': async () => {
            try {
                const sc = await openPostEditorAnd(sc => sc && (sc.querySelector('#postMediaPicker') || sc.querySelector('#bgUpload')));
                const picker = (sc || document).querySelector('#postMediaPicker');
                if (picker) {
                    picker.click();
                    return;
                }
                // Fallback: usa o input do editor e adiciona à galeria manualmente para exibir thumbnail
                const input = (sc || document).querySelector('#bgUpload');
                if (!input) return;
                const onChange = (ev) => {
                    try { input.removeEventListener('change', onChange); } catch (_) {}
                    const file = ev?.target?.files?.[0];
                    if (!file) return;
                    const isVid = (file.type||'').toLowerCase().startsWith('video');
                    const objUrl = URL.createObjectURL(file);
                    POST_MEDIA_STATE.items = POST_MEDIA_STATE.items || [];
                    POST_MEDIA_STATE.items.push({
                        url: objUrl,
                        path: null,
                        mimeType: file.type || (isVid ? 'video/*' : 'image/*'),
                        type: isVid ? 'video' : 'image',
                        file,
                        fileName: file.name || (isVid ? `post_${Date.now()}.webm` : `post_${Date.now()}.jpg`)
                    });
                    POST_MEDIA_STATE.activeIndex = POST_MEDIA_STATE.items.length - 1;
                    try { initPostEditorGallery(sc || document); } catch (_) {}
                };
                try { input.addEventListener('change', onChange, { once: true }); } catch (_) { input.addEventListener('change', onChange); }
                input.click();
            } catch (_) {}
        },
        // Apps: notificações e desinstalar
        'app-toggle-notifications': ({ button }) => {
            const appId = button?.dataset?.appId;
            if (!appId) return;
            const key = `app_notify_${appId}`;
            const enabled = (localStorage.getItem(key) === '1');
            localStorage.setItem(key, enabled ? '0' : '1');
            const label = enabled ? 'Ativar Notificações' : 'Desativar Notificações';
            if (button) button.innerHTML = `<i class="fas fa-bell"></i> ${label}`;
        },
        'app-uninstall': async ({ button }) => {
            const appId = button?.dataset?.appId;
            if (!appId) return;
            // Remove a associação do app ao usuário e permanece no sidebar
            await apiClient.post('/delete', { db: 'workz_apps', table: 'gapp', conditions: { us: currentUserData.id, ap: appId } });
            if (typeof SidebarNav !== 'undefined') {
                const prev = SidebarNav.prev?.();
                // Se a view anterior é a lista de apps, apenas volte (isso re-renderiza a lista atualizada)
                if (prev && prev.view === 'apps') {
                    SidebarNav.back();
                } else {
                    // Caso contrário, garanta que mostramos a lista de apps sem fechar o sidebar
                    SidebarNav.resetRoot(currentUserData);
                    SidebarNav.push({ view: 'apps', title: 'Aplicativos', payload: { data: currentUserData } });
                }
            }
            notifySuccess('Aplicativo desinstalado.');
        },
        'app-toggle-quick': async ({ button }) => {
            const appId = Number(button?.dataset?.appId);
            if (!Number.isFinite(appId)) return;
            const el = document.querySelector('#app-library');
            try {
                // Verificar estado atual no servidor
                const res = await apiClient.post('/search', {
                    db: 'workz_apps', table: 'quickapps', columns: ['ap'],
                    conditions: { us: currentUserData.id, ap: appId }, fetchAll: true
                });
                const exists = Array.isArray(res?.data) && res.data.length > 0;
                if (exists) {
                    await apiClient.post('/delete', { db: 'workz_apps', table: 'quickapps', conditions: { us: currentUserData.id, ap: appId } });
                    if (button) button.innerHTML = '<i class="fas fa-star"></i> Adicionar ao Acesso Rápido';
                    notifySuccess('Removido do acesso rápido.');
                } else {
                    await apiClient.post('/insert', { db: 'workz_apps', table: 'quickapps', data: { us: currentUserData.id, ap: appId } });
                    if (button) button.innerHTML = '<i class="fas fa-star"></i> Remover do Acesso Rápido';
                    notifySuccess('Adicionado ao acesso rápido.');
                }
            } catch (e) {
                notifyError('Não foi possível atualizar o acesso rápido.');
            }
            try { initAppLibrary(el, window.__appListCache || []); } catch (_) {}
        },
        'delete-business': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            if (!(await confirmDialog('Tem certeza que deseja excluir este negócio? Esta ação não pode ser desfeita.', { danger: true }))) return;
            setButtonLoading(button, true, 'Excluindo…');
            try {
                // Exclui vínculos de employees e o próprio negócio
                await apiClient.post('/delete', { db: 'workz_companies', table: 'employees', conditions: { em: id } });
                await apiClient.post('/delete', { db: 'workz_companies', table: 'companies', conditions: { id } });
                // Atualiza caches locais
                if (Array.isArray(userBusinessesData)) {
                    userBusinessesData = userBusinessesData.filter(r => String(r.em) !== String(id));
                }
                if (Array.isArray(userBusinesses)) {
                    userBusinesses = userBusinesses.filter(em => String(em) !== String(id));
                }
                // Volta para lista de Negócios no sidebar
                if (typeof SidebarNav !== 'undefined') {
                    const prev = SidebarNav.prev?.();
                    if (prev && prev.view === 'businesses') {
                        SidebarNav.back();
                    } else {
                        SidebarNav.resetRoot(currentUserData);
                        SidebarNav.push({ view: 'businesses', title: 'Negócios', payload: { data: currentUserData } });
                    }
                }
                notifySuccess('Negócio excluído.');
            } catch (_) {
                notifyError('Falha ao excluir o negócio.');
            }
            setButtonLoading(button, false);
        },
        'delete-team': async ({ button, state }) => {
            const id = button?.dataset?.id;
            const em = button?.dataset?.em || state?.view?.data?.em;
            if (!id) return;
            // Permissão: somente dono da equipe ou moderadores podem excluir
            let canDelete = false;
            try {
                const teamData = state?.view?.data;
                const uid = String(currentUserData.id);
                const isOwner = String(teamData?.us) === uid;
                let moderators = [];
                try { moderators = teamData?.usmn ? JSON.parse(teamData.usmn) : []; } catch (_) { moderators = []; }
                const isModerator = Array.isArray(moderators) && moderators.map(String).includes(uid);
                canDelete = isOwner || isModerator;
            } catch (_) { canDelete = false; }
            if (!canDelete) { notifyError('Você não tem permissão para excluir esta equipe.'); return; }
            if (!(await confirmDialog('Tem certeza que deseja excluir esta equipe? Esta ação não pode ser desfeita.', { danger: true }))) return;
            // Usa endpoint protegido no backend
            await apiClient.post('/teams/delete', { id: Number(id) });
            notifySuccess('Equipe excluída.');
            // Atualiza caches locais
            if (Array.isArray(userTeamsData)) userTeamsData = userTeamsData.filter(r => String(r.cm) !== String(id));
            if (Array.isArray(userTeams)) userTeams = userTeams.filter(cm => String(cm) !== String(id));
            // Se estamos na página desta equipe, redireciona
            if (state?.view?.type === ENTITY.TEAM && String(state.view.id) === String(id)) {
                if (em) navigateTo(`/business/${em}`); else navigateTo('/');
            }
            // Atualiza a navegação do sidebar para a lista de equipes
            if (typeof SidebarNav !== 'undefined') {
                const prev = SidebarNav.prev?.();
                if (prev && prev.view === 'teams') {
                    SidebarNav.back();
                } else {
                    SidebarNav.resetRoot(currentUserData);
                    SidebarNav.push({ view: 'teams', title: 'Equipes', payload: { data: currentUserData } });
                }
            }
        },
        // Criação (negócio/equipe)
        'create-business': async () => {
            const name = (document.getElementById('new-business-name')?.value || '').trim();
            if (!name) { notifyError('Informe o nome do negócio.'); return; }
            const sc = document.querySelector('.sidebar-content');
            // Cria o negócio
            const res = await apiClient.post('/insert', {
                db: 'workz_companies',
                table: 'companies',
                data: { tt: name, us: currentUserData.id, st: 1 }
            });
            // Descobre o ID recém criado (preferencialmente pelo retorno)
            let newId = res?.id;
            if (!newId) {
                const lookup = await apiClient.post('/search', {
                    db: 'workz_companies',
                    table: 'companies',
                    columns: ['*'],
                    conditions: { tt: name, us: currentUserData.id },
                    order: { by: 'id', dir: 'DESC' },
                    fetchAll: true,
                    limit: 1
                });
                newId = Array.isArray(lookup?.data) && lookup.data[0]?.id;
            }
            if (!newId) { notifyError('Falha ao criar o negócio.'); return; }
            // Garante vínculo do usuário ao novo negócio (funciona com a lista "Negócios Gerenciados")
            try {
                await apiClient.post('/insert', {
                    db: 'workz_companies',
                    table: 'employees',
                    data: { us: currentUserData.id, em: newId, nv: 4, st: 1 }
                });
                // Atualiza caches locais usados nas listas de Negócios
                if (Array.isArray(userBusinessesData)) {
                    userBusinessesData.push({ us: currentUserData.id, em: newId, nv: 4, st: 1 });
                } else {
                    userBusinessesData = [{ us: currentUserData.id, em: newId, nv: 4, st: 1 }];
                }
                if (Array.isArray(userBusinesses)) {
                    if (!userBusinesses.includes(newId)) userBusinesses.push(newId);
                } else {
                    userBusinesses = [newId];
                }
            } catch (_) { /* ignore silent; backend may also auto-create */ }
            // Busca dados completos do negócio e abre diretamente as Configurações dele no sidebar (sem recarregar a página)
            const fetchNew = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'companies',
                columns: ['*'],
                conditions: { id: newId }
            });
            const business = Array.isArray(fetchNew?.data) ? fetchNew.data[0] : fetchNew?.data || null;
            if (!business) { notifyError('Negócio criado, mas não foi possível carregar os dados.'); return; }
            if (typeof SidebarNav !== 'undefined') {
                SidebarNav.push({ view: ENTITY.BUSINESS, title: business.tt || 'Negócio', payload: { data: business, type: 'business' } });
            } else if (sc) {
                // Fallback: render direto
                await renderTemplate(sc, templates.sidebarPageSettings, { view: ENTITY.BUSINESS, data: business, origin: 'settings' });
            }
        },
        'create-team': async () => {
            const name = (document.getElementById('new-team-name')?.value || '').trim();
            const em = (document.getElementById('new-team-business')?.value || '').trim();
            if (!name || !em) { notifyError('Informe o nome da equipe e o negócio.'); return; }
            const sc = document.querySelector('.sidebar-content');
            // Cria a equipe
            const res = await apiClient.post('/insert', {
                db: 'workz_companies',
                table: 'teams',
                data: { tt: name, us: currentUserData.id, em: Number(em), st: 1 }
            });
            // Descobre o ID recém criado
            let newId = res?.id;
            if (!newId) {
                const lookup = await apiClient.post('/search', {
                    db: 'workz_companies',
                    table: 'teams',
                    columns: ['*'],
                    conditions: { tt: name, us: currentUserData.id, em: Number(em) },
                    order: { by: 'id', dir: 'DESC' },
                    fetchAll: true,
                    limit: 1
                });
                newId = Array.isArray(lookup?.data) && lookup.data[0]?.id;
            }
            if (!newId) { notifyError('Falha ao criar a equipe.'); return; }
            // Vínculo do usuário à equipe (teams_users)
            try {
                await apiClient.post('/insert', {
                    db: 'workz_companies',
                    table: 'teams_users',
                    data: { us: currentUserData.id, cm: newId, st: 1 }
                });
                if (Array.isArray(userTeamsData)) {
                    userTeamsData.push({ us: currentUserData.id, cm: newId, st: 1 });
                } else {
                    userTeamsData = [{ us: currentUserData.id, cm: newId, st: 1 }];
                }
                if (Array.isArray(userTeams)) {
                    if (!userTeams.includes(newId)) userTeams.push(newId);
                } else {
                    userTeams = [newId];
                }
            } catch (_) { /* ignore; backend may also auto-create */ }
            // Busca dados completos da equipe e abre diretamente as Configurações da equipe no sidebar
            const fetchNew = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'teams',
                columns: ['*'],
                conditions: { id: newId }
            });
            const team = Array.isArray(fetchNew?.data) ? fetchNew.data[0] : fetchNew?.data || null;
            if (!team) { notifyError('Equipe criada, mas não foi possível carregar os dados.'); return; }
            if (typeof SidebarNav !== 'undefined') {
                SidebarNav.push({ view: ENTITY.TEAM, title: team.tt || 'Equipe', payload: { data: team, type: 'team' } });
            } else if (sc) {
                await renderTemplate(sc, templates.sidebarPageSettings, { view: ENTITY.TEAM, data: team, origin: 'settings' });
            }
        },
        // Ações sociais: seguir/desseguir pessoa (tabela workz_data.usg)
        'follow-user': async ({ state, button }) => { if (button && button.disabled) return; const follower = state.user?.id;
            const followed = state.view?.id;
            if (!follower || !followed) return;
            setButtonLoading(button, true, 'Seguindo…');
            try {
                const res = await apiClient.post('/insert', { db: 'workz_data', table: 'usg', data: { s0: follower, s1: followed } });
                if (res && res.status === 'success') {
                    // Atualiza estado local
                    const fid = String(followed);
                    if (Array.isArray(userPeople)) {
                        if (!userPeople.map(String).includes(fid)) userPeople.push(followed);
                    } else {
                        userPeople = [followed];
                    }
                    // Troca o botão
                    const container = document.querySelector('#action-container');
                    if (container) container.innerHTML = UI.actionButton({ action: 'unfollow-user', label: 'Deixar de Seguir', color: 'red' });
                    // Ajusta contagem de seguidores da página (se existir)
                    const cntEl = document.querySelector('#followers-count');
                    if (cntEl) {
                        const n = parseInt(cntEl.textContent || '0', 10) || 0;
                        cntEl.textContent = String(n + 1);
                    }
                    notifySuccess('Agora você está seguindo.');
                }
            } finally { setButtonLoading(button, false); }
        },
        'unfollow-user': async ({ state, button }) => { if (button && button.disabled) return; const follower = state.user?.id;
            const followed = state.view?.id;
            if (!follower || !followed) return;
            setButtonLoading(button, true, 'Removendo…');
            try {
                const res = await apiClient.post('/delete', { db: 'workz_data', table: 'usg', conditions: { s0: follower, s1: followed } });
                if (res && res.status === 'success') {
                    // Atualiza estado local
                    const fid = String(followed);
                    if (Array.isArray(userPeople)) {
                        userPeople = userPeople.filter(id => String(id) !== fid);
                    } else {
                        userPeople = [];
                    }
                    // Troca o botão
                    const container = document.querySelector('#action-container');
                    if (container) container.innerHTML = UI.actionButton({ action: 'follow-user', label: 'Seguir', color: 'blue' });
                    // Ajusta contagem de seguidores da página (se existir)
                    const cntEl = document.querySelector('#followers-count');
                    if (cntEl) {
                        const n = parseInt(cntEl.textContent || '0', 10) || 0;
                        cntEl.textContent = String(Math.max(0, n - 1));
                    }
                    notifySuccess('Você deixou de seguir.');
                }
            } finally { setButtonLoading(button, false); }
        },
        // Acesso a negócios/equipes (UI otimista)
        'request-join': async ({ state, button }) => {
            const { table, idKey } = getMembershipMeta(state.view?.type);
            if (!table || !idKey) return;
            const payloadKeys = { us: state.user?.id, [idKey]: state.view?.id };
            if (button) setButtonLoading(button, true, 'Enviando…');
            try {
                const exists = await apiClient.post('/search', { db: 'workz_companies', table, columns: ['id', 'st'], conditions: payloadKeys, fetchAll: true, limit: 1 });
                if (Array.isArray(exists?.data) && exists.data.length) {
                    await apiClient.post('/update', { db: 'workz_companies', table, data: { st: 0 }, conditions: payloadKeys });
                } else {
                    await apiClient.post('/insert', { db: 'workz_companies', table, data: { ...payloadKeys, st: 0 } });
                }
                // Estado local
                const idVal = state.view?.id;
                if (state.view?.type === ENTITY.BUSINESS) {
                    if (!Array.isArray(userBusinessesData)) userBusinessesData = [];
                    const found = userBusinessesData.find(r => String(r.em) === String(idVal) && Number(r.us) === Number(state.user?.id));
                    if (found) found.st = 0; else userBusinessesData.push({ us: state.user?.id, em: idVal, st: 0 });
                    if (!Array.isArray(userBusinesses)) userBusinesses = [];
                    if (!userBusinesses.map(String).includes(String(idVal))) userBusinesses.push(idVal);
                    memberStatus = 0;
                } else if (state.view?.type === ENTITY.TEAM) {
                    if (!Array.isArray(userTeamsData)) userTeamsData = [];
                    const found = userTeamsData.find(r => String(r.cm) === String(idVal) && Number(r.us) === Number(state.user?.id));
                    if (found) found.st = 0; else userTeamsData.push({ us: state.user?.id, cm: idVal, st: 0 });
                    if (!Array.isArray(userTeams)) userTeams = [];
                    if (!userTeams.map(String).includes(String(idVal))) userTeams.push(idVal);
                    memberStatus = 0;
                }
                const container = document.querySelector('#action-container');
                if (container) container.innerHTML = UI.actionButton({ action: 'cancel-request', label: 'Cancelar Pedido', color: 'yellow' });
                notifySuccess('Solicitação enviada.');
            } finally { if (button) setButtonLoading(button, false); }
        },
        'cancel-request': async ({ state, button }) => {
            const { table, idKey } = getMembershipMeta(state.view?.type);
            if (!table || !idKey) return;
            const keys = { us: state.user?.id, [idKey]: state.view?.id, st: 0 };
            if (button) setButtonLoading(button, true, 'Cancelando…');
            try {
                await apiClient.post('/delete', { db: 'workz_companies', table, conditions: keys });
                const idVal = state.view?.id;
                if (state.view?.type === ENTITY.BUSINESS) {
                    if (Array.isArray(userBusinessesData)) {
                        userBusinessesData = userBusinessesData.filter(r => !(String(r.em) === String(idVal) && Number(r.st) === 0 && Number(r.us) === Number(state.user?.id)));
                    }
                    // Se não houver vínculo ativo restante, remove do resumo
                    const stillActive = Array.isArray(userBusinessesData) && userBusinessesData.some(r => String(r.em) === String(idVal) && Number(r.st) === 1);
                    if (!stillActive) userBusinesses = (userBusinesses || []).filter(em => String(em) !== String(idVal));
                    memberStatus = stillActive ? 1 : null;
                } else if (state.view?.type === ENTITY.TEAM) {
                    if (Array.isArray(userTeamsData)) {
                        userTeamsData = userTeamsData.filter(r => !(String(r.cm) === String(idVal) && Number(r.st) === 0 && Number(r.us) === Number(state.user?.id)));
                    }
                    const stillActive = Array.isArray(userTeamsData) && userTeamsData.some(r => String(r.cm) === String(idVal) && Number(r.st) === 1);
                    if (!stillActive) userTeams = (userTeams || []).filter(cm => String(cm) !== String(idVal));
                    memberStatus = stillActive ? 1 : null;
                }
                const container = document.querySelector('#action-container');
                if (container) container.innerHTML = UI.actionButton({ action: 'request-join', label: 'Solicitar Acesso', color: 'green' });
                notifySuccess('Solicitação cancelada.');
            } finally { if (button) setButtonLoading(button, false); }
        },
        'cancel-access': async ({ state, button }) => {
            const { table, idKey } = getMembershipMeta(state.view?.type);
            if (!table || !idKey) return;
            const keys = { us: state.user?.id, [idKey]: state.view?.id };
            if (button) setButtonLoading(button, true, 'Atualizando…');
            try {
                await apiClient.post('/update', { db: 'workz_companies', table, data: { st: 0 }, conditions: keys });
                const idVal = state.view?.id;
                if (state.view?.type === ENTITY.BUSINESS) {
                    const rec = Array.isArray(userBusinessesData) && userBusinessesData.find(r => String(r.em) === String(idVal) && Number(r.us) === Number(state.user?.id));
                    if (rec) rec.st = 0;
                    memberStatus = 0;
                } else if (state.view?.type === ENTITY.TEAM) {
                    const rec = Array.isArray(userTeamsData) && userTeamsData.find(r => String(r.cm) === String(idVal) && Number(r.us) === Number(state.user?.id));
                    if (rec) rec.st = 0;
                    memberStatus = 0;
                    // Se estamos vendo a equipe atual, bloqueia o conteúdo imediatamente
                    if (viewType === ENTITY.TEAM && String(viewId) === String(idVal)) {
                        viewRestricted = true;
                        const main = document.querySelector('#main-content');
                        if (main) await renderTemplate(main, templates.teamRestricted);
                    }
                }
                const container = document.querySelector('#action-container');
                if (container) container.innerHTML = UI.actionButton({ action: 'cancel-request', label: 'Cancelar Pedido', color: 'yellow' });
                notifySuccess('Acesso desativado.');
            } finally { if (button) setButtonLoading(button, false); }
        },
        // Gestão de solicitações (membros de equipe/negócio) via sidebar
        'accept-member': async ({ button }) => {
            const uid = button?.dataset?.userId;
            const scopeType = button?.dataset?.scopeType; // 'business' | 'team'
            const scopeId = button?.dataset?.scopeId;
            if (!uid || !scopeType || !scopeId) return;
            setButtonLoading(button, true, 'Aceitando…');
            try {
                if (scopeType === 'business') {
                    await apiClient.post('/companies/members/accept', { companyId: Number(scopeId), userId: Number(uid) });
                } else {
                    await apiClient.post('/teams/members/accept', { teamId: Number(scopeId), userId: Number(uid) });
                    // Se o usuário aceitou a si mesmo na equipe atualmente aberta, liberar conteúdo imediatamente
                    const isSelf = String(uid) === String(currentUserData.id);
                    const isCurrentTeam = (viewType === ENTITY.TEAM) && String(viewId) === String(scopeId);
                    if (isSelf) {
                        // Atualiza caches locais
                        if (!Array.isArray(userTeamsData)) userTeamsData = [];
                        const found = userTeamsData.find(r => String(r.cm) === String(scopeId) && Number(r.us) === Number(currentUserData.id));
                        if (found) found.st = 1; else userTeamsData.push({ us: currentUserData.id, cm: Number(scopeId), st: 1 });
                        if (!Array.isArray(userTeams)) userTeams = [];
                        if (!userTeams.map(String).includes(String(scopeId))) userTeams.push(Number(scopeId));
                        if (isCurrentTeam) {
                            memberStatus = 1;
                            viewRestricted = false;
                            // Re-renderiza a view atual para carregar conteúdo e feed
                            try { await renderView(viewId); } catch (_) { }
                        }
                    }
                }
                if (typeof SidebarNav !== 'undefined') SidebarNav.render();
                notifySuccess('Solicitação aceita.');
            } finally { setButtonLoading(button, false); }
        },
        'reject-member': async ({ button }) => {
            const uid = button?.dataset?.userId;
            const scopeType = button?.dataset?.scopeType;
            const scopeId = button?.dataset?.scopeId;
            if (!uid || !scopeType || !scopeId) return;
            setButtonLoading(button, true, 'Rejeitando…');
            try {
                if (scopeType === 'business') {
                    await apiClient.post('/companies/members/reject', { companyId: Number(scopeId), userId: Number(uid) });
                } else {
                    await apiClient.post('/teams/members/reject', { teamId: Number(scopeId), userId: Number(uid) });
                }
                if (typeof SidebarNav !== 'undefined') SidebarNav.render();
                notifySuccess('Solicitação rejeitada.');
            } finally { setButtonLoading(button, false); }
        },
        'update-member-level': async ({ button }) => {
            const uid = button?.dataset?.userId;
            const scopeType = button?.dataset?.scopeType; // 'business' | 'team'
            const scopeId = button?.dataset?.scopeId;
            if (!uid || !scopeType || !scopeId) return;
            const row = button.closest('.grid');
            const sel = row ? row.querySelector('select[name="nv"]') : null;
            const nv = sel ? Number(sel.value) : null;
            if (nv == null) return;
            button.disabled = true;
            try {
                if (scopeType === 'business') {
                    await apiClient.post('/companies/members/level', { companyId: Number(scopeId), userId: Number(uid), nv });
                } else {
                    await apiClient.post('/teams/members/level', { teamId: Number(scopeId), userId: Number(uid), nv });
                }
                // Atualiza caches locais mínimos
                if (scopeType === 'business' && Array.isArray(userBusinessesData)) {
                    const rec = userBusinessesData.find(r => String(r.em) === String(scopeId) && Number(r.us) === Number(uid));
                    if (rec) rec.nv = nv;
                }
                if (scopeType === 'team' && Array.isArray(userTeamsData)) {
                    const rec = userTeamsData.find(r => String(r.cm) === String(scopeId) && Number(r.us) === Number(uid));
                    if (rec) rec.nv = nv;
                }

                // Se o usuário atual mudou seu próprio nível na página aberta, atualize a UI sem recarregar
                const isSelf = String(uid) === String(currentUserData.id);
                const onCurrentBusiness = (scopeType === 'business' && viewType === ENTITY.BUSINESS && String(viewId) === String(scopeId));
                const onCurrentTeam = (scopeType === 'team' && viewType === ENTITY.TEAM && String(viewId) === String(scopeId));
                if (isSelf && (onCurrentBusiness || onCurrentTeam)) {
                    memberLevel = Number(nv) || 0;
                    const ac = document.querySelector('#action-container');
                    if (ac) { ac.innerHTML = ''; pageAction(); }
                    if (typeof SidebarNav !== 'undefined') {
                        try { await SidebarNav.render(); } catch (_) { }
                    }
                }
                // Feedback simples
                button.textContent = 'Atualizado';
                notifySuccess('Nível atualizado.');
                setTimeout(() => { button.textContent = 'Atualizar'; button.disabled = false; }, 800);
            } catch (_) {
                button.disabled = false;
                notifyError('Falha ao atualizar nível.');
            }
        },
        // Depoimentos (testimonials)
        'accept-testmonial': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            try {
                await apiClient.post('/update', { db: 'workz_data', table: 'testimonials', data: { status: 1 }, conditions: { id } });
                notifySuccess('Depoimento aceito.');
            } catch (_) { notifyError('Falha ao aceitar depoimento.'); }
            loadPage();
        },
        'reject-testmonial': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            try {
                await apiClient.post('/update', { db: 'workz_data', table: 'testimonials', data: { status: 2 }, conditions: { id } });
                notifySuccess('Depoimento rejeitado.');
            } catch (_) { notifyError('Falha ao rejeitar depoimento.'); }
            loadPage();
        },
        'revert-testmonial': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            try {
                await apiClient.post('/update', { db: 'workz_data', table: 'testimonials', data: { status: 0 }, conditions: { id } });
                notifySuccess('Depoimento revertido.');
            } catch (_) { notifyError('Falha ao reverter depoimento.'); }
            loadPage();
        },
        // Jobs (experiências)
        'delete-job': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            showLoading();
            try {
                await apiClient.post('/delete', { db: 'workz_companies', table: 'employees', conditions: { id } });
                notifySuccess('Experiência excluída.');
                loadPage();
            } catch (_) { notifyError('Falha ao excluir experiência.'); }
            finally { hideLoading(); }
        },
        'edit-job': ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            const form = document.querySelector(`.job-form[data-job-id="${id}"]`);
            if (!form) return;
            form.dataset.readonlyMode = '0';
            form.querySelectorAll('input,select,textarea').forEach(el => el.disabled = false);
        }
    };

    // Retorna metadados da tabela de associação conforme o tipo de página
    function getMembershipMeta(type) {
        if (type === ENTITY.BUSINESS) return { table: 'employees', idKey: 'em' };
        if (type === ENTITY.TEAM) return { table: 'teams_users', idKey: 'cm' };
        return { table: null, idKey: null };
    }

    // ================================
    // Helpers de permissão (front)
    // ================================
    function isBusinessManager(companyId) {
        try {
            const rec = (userBusinessesData || []).find(r => String(r.em) === String(companyId) && Number(r.st) === 1);
            const level = Number(rec?.nv ?? 0);
            return level >= 3;
        } catch (_) { return false; }
    }
    function isTeamOwner(teamData) {
        try { return String(teamData?.us) === String(currentUserData.id); } catch (_) { return false; }
    }
    function isTeamModerator(teamData) {
        try {
            const mods = teamData?.usmn ? JSON.parse(teamData.usmn) : [];
            return Array.isArray(mods) && mods.map(String).includes(String(currentUserData.id));
        } catch (_) { return false; }
    }
    function canManageTeam(teamData) { return isTeamOwner(teamData) || isTeamModerator(teamData); }

    // ===================================================================
    // POST EDITOR INTEGRATION
    // ===================================================================

    async function openPostEditor() {
        try {
            // Carregar CSS do editor se ainda não foi carregado
            if (!document.querySelector('link[href*="editor.css"]')) {
                const editorCSS = document.createElement('link');
                editorCSS.rel = 'stylesheet';
                // Use absolute path to work on nested routes like /team/15
                editorCSS.href = '/css/editor.css';
                document.head.appendChild(editorCSS);
            }

            // Limpar e preparar sidebar
            sidebarWrapper.innerHTML = '';

            // Garantir que w-0 seja removida e as classes de largura sejam adicionadas
            if (sidebarWrapper.classList.contains('w-0')) {
                sidebarWrapper.classList.remove('w-0');
            }
            if (!sidebarWrapper.classList.contains('lg:w-1/3')) {
                sidebarWrapper.classList.add('lg:w-1/3');
            }
            if (!sidebarWrapper.classList.contains('sm:w-1/2')) {
                sidebarWrapper.classList.add('sm:w-1/2');
            }
            if (!sidebarWrapper.classList.contains('w-full')) {
                sidebarWrapper.classList.add('w-full');
            }
            if (!sidebarWrapper.classList.contains('shadow-2xl')) {
                sidebarWrapper.classList.add('shadow-2xl');
            }

            // Forçar largura via CSS inline para sobrescrever qualquer CSS conflitante
            sidebarWrapper.style.width = '33.333333%'; // equivalente a w-1/3
            sidebarWrapper.style.minWidth = '300px'; // largura mínima

            // Verificar se a sidebar está visível
            const rect = sidebarWrapper.getBoundingClientRect();

            // Criar conteúdo da sidebar
            sidebarWrapper.innerHTML = `<div class="sidebar-content grid grid-cols-1 gap-6 p-4"></div>`;
            const sidebarContent = document.querySelector('.sidebar-content');

            if (!sidebarContent) {
                console.error('Falha ao criar sidebar-content');
                notifyError('Erro ao preparar interface do editor.');
                return;
            }

            // Usar o sistema de navegação do sidebar para carregar a view post-editor
            SidebarNav.setMount(sidebarContent);
            await renderTemplate(sidebarWrapper, templates.sidebarPageSettings, {
                view: 'post-editor',
                data: currentUserData,
                navTitle: 'Editor de Posts',
                prevTitle: 'Voltar',
                origin: 'stack'
            }, async () => {
                // Callback executado após a renderização completa
                setTimeout(() => {
                    // Inicializa a UI de múltiplas mídias (carrossel)
                    try { initPostEditorGallery(sidebarWrapper); } catch (_) {}
                    try { setupEditorCaptureBridge(sidebarWrapper); } catch (_) {}
                    // Unifica o botão Enviar com a galeria
                    try { wireUnifiedSendFlow(sidebarWrapper); } catch (_) {}
                    // Unifica captura e rótulos para adicionar mídia na galeria
                    try { wireUnifiedMediaAdders(sidebarWrapper); } catch (_) {}
                    // Estratégia local-first: override de picker e captura
                    try { setupLocalFirstGalleryUpload(sidebarWrapper); } catch (_) {}
                    try { setupEditorCaptureBridgeLocal(sidebarWrapper); } catch (_) {}

                    // Buscar elementos diretamente no sidebarWrapper
                    const appShellInSidebar = sidebarWrapper.querySelector('#appShell');
                    const gridCanvasInSidebar = sidebarWrapper.querySelector('#gridCanvas');

                    if (appShellInSidebar && gridCanvasInSidebar) {
                        loadEditorScript(sidebarWrapper);
                    } else {
                        console.error('Elementos não encontrados na sidebar');

                        // Tentar novamente após mais tempo
                        setTimeout(() => {
                            const appShellRetry = sidebarWrapper.querySelector('#appShell');
                            const gridCanvasRetry = sidebarWrapper.querySelector('#gridCanvas');

                            if (appShellRetry && gridCanvasRetry) {
                                loadEditorScript(sidebarWrapper);
                            } else {
                                console.error('Elementos ainda não encontrados na segunda tentativa');
                                notifyError('Interface do editor não foi carregada corretamente.');
                            }
                        }, 500);
                    }
                }, 200);
            });

        } catch (error) {
            console.error('Erro ao abrir post editor:', error);
            notifyError('Não foi possível abrir o editor de posts.');
        }
    }

    function loadEditorScript(sidebarContent = null) {

        if (!sidebarContent) {
            sidebarContent = document.querySelector('.sidebar-content');
        }

        if (!sidebarContent) {
            console.error('Sidebar content não encontrado');
            notifyError('Interface do editor não está pronta.');
            return;
        }

        const requiredElements = ['editorViewport', 'editor', 'gridCanvas'];
        const missingElements = requiredElements.filter(id => !sidebarContent.querySelector(`#${id}`));

        if (missingElements.length > 0) {
            console.error('Elementos necessários não encontrados:', missingElements);
            notifyError('Interface do editor não está pronta.');
            return;
        }

        // Verificar se o script já foi carregado
        if (window._editorReady === true) { try { initEditorInSidebar(sidebarContent); } catch(_) {} return; }
        if (window._editorLoading === true) { setTimeout(() => { if (typeof init === 'function') { try { initEditorInSidebar(sidebarContent); } catch(_) {} } }, 150); return; }
        if (typeof init === 'function') {
            try {
                initEditorInSidebar(sidebarContent);
                return;
            } catch (error) {
                console.error('Erro ao inicializar editor:', error);
                notifyError('Erro ao inicializar o editor.');
                return;
            }
        }

        // Usar fetch para carregar o script diretamente
        const editorUrl = window.location.origin + '/js/editor.js?v=' + Date.now();
        
        window._editorLoading = true;
        fetch(editorUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(content => {
                
                if (content.trim().startsWith('<')) {
                    console.error('editor.js está retornando HTML em vez de JavaScript!');
                    createInlineEditor(sidebarContent);
                    return;
                }
                
                try {
                    // Criar e executar o script
                    const script = document.createElement('script');
                    script.textContent = content;
                    document.head.appendChild(script);
                    window._editorReady = true;
                    window._editorLoading = false;
                    
                    // Aguardar um pouco e tentar inicializar
                    setTimeout(() => {
                        if (typeof init === 'function') {
                            initEditorInSidebar(sidebarContent);
                        } else {
                            console.error('Função init não encontrada após carregar o script');
                            createInlineEditor(sidebarContent);
                        }
                    }, 100);
                    
                } catch (evalError) {
                    console.error('Erro ao executar editor.js:', evalError);
                    window._editorLoading = false;
                    createInlineEditor(sidebarContent);
                }
            })
            .catch(err => {
                console.error('Erro ao fazer fetch do editor.js:', err);
                window._editorLoading = false;
                createInlineEditor(sidebarContent);
            });
    }

    function initEditorInSidebar(sidebarContent) {
        // Criar um contexto para o editor funcionar dentro da sidebar
        const originalGetElementById = document.getElementById;
        document.getElementById = function (id) {
            // Primeiro tentar encontrar na sidebar
            const sidebarElement = sidebarContent.querySelector(`#${id}`);
            if (sidebarElement) {
                return sidebarElement;
            }
            // Se não encontrar na sidebar, usar o método original
            return originalGetElementById.call(document, id);
        };

        try {
            // Inicializar o editor
            init();
        } finally {
            // Restaurar o método original
            setTimeout(() => {
                document.getElementById = originalGetElementById;
            }, 100);
        }
    }

    function createInlineEditor(sidebarContent) {

        try {
            const gridCanvas = sidebarContent.querySelector('#gridCanvas');
            if (!gridCanvas) {
                console.error('Canvas não encontrado para editor inline');
                notifyError('Interface do editor não está pronta.');
                return;
            }

            const ctx = gridCanvas.getContext('2d');
            if (!ctx) {
                console.error('Não foi possível obter contexto do canvas');
                notifyError('Erro ao inicializar canvas.');
                return;
            }

            // Desenhar grade básica
            function drawGrid() {
                ctx.clearRect(0, 0, gridCanvas.width, gridCanvas.height);
                ctx.globalAlpha = 0.15;
                ctx.strokeStyle = '#94a3b8';
                ctx.lineWidth = 1;

                // Linhas verticais
                for (let x = 0; x <= gridCanvas.width; x += 20) {
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, gridCanvas.height);
                    ctx.stroke();
                }

                // Linhas horizontais
                for (let y = 0; y <= gridCanvas.height; y += 20) {
                    ctx.beginPath();
                    ctx.moveTo(0, y);
                    ctx.lineTo(gridCanvas.width, y);
                    ctx.stroke();
                }

                ctx.globalAlpha = 1;
            }

            // Desenhar grade inicial
            drawGrid();

            // Adicionar funcionalidade básica de texto
            const btnAddText = sidebarContent.querySelector('#btnAddText');
            if (btnAddText) {
                // Evitar múltiplos binds se o fallback for chamado mais de uma vez
                if (btnAddText._inlineTextHandler) {
                    btnAddText.removeEventListener('click', btnAddText._inlineTextHandler);
                }
                const inlineHandler = () => {
                    const editor = sidebarContent.querySelector('#editor');
                    if (editor) {
                        const textBox = document.createElement('div');
                        textBox.className = 'item';
                        textBox.style.position = 'absolute';
                        textBox.style.left = '50px';
                        textBox.style.top = '50px';
                        textBox.style.padding = '10px';
                        textBox.style.backgroundColor = 'rgba(255,255,255,0.9)';
                        textBox.style.border = '2px solid #ccc';
                        textBox.style.borderRadius = '4px';
                        textBox.style.cursor = 'move';
                        textBox.style.minWidth = '100px';
                        textBox.style.minHeight = '30px';
                        textBox.contentEditable = true;
                        textBox.innerText = 'Novo texto';
                        textBox.dataset.type = 'text';

                        // Adicionar funcionalidade de arrastar
                        let isDragging = false;
                        let startX, startY, initialLeft, initialTop;

                        textBox.addEventListener('mousedown', (e) => {
                            if (e.target === textBox) {
                                isDragging = true;
                                startX = e.clientX;
                                startY = e.clientY;
                                initialLeft = parseInt(textBox.style.left);
                                initialTop = parseInt(textBox.style.top);
                                e.preventDefault();
                            }
                        });

                        document.addEventListener('mousemove', (e) => {
                            if (isDragging) {
                                const deltaX = e.clientX - startX;
                                const deltaY = e.clientY - startY;
                                textBox.style.left = (initialLeft + deltaX) + 'px';
                                textBox.style.top = (initialTop + deltaY) + 'px';
                            }
                        });

                        document.addEventListener('mouseup', () => {
                            isDragging = false;
                        });

                        editor.appendChild(textBox);
                    }
                };
                btnAddText.addEventListener('click', inlineHandler);
                btnAddText._inlineTextHandler = inlineHandler;
            }
            notifySuccess('Editor básico carregado. Funcionalidade limitada disponível.');

        } catch (error) {
            console.error('Erro ao criar editor inline:', error);
            notifyError('Erro ao criar editor básico.');
        }
    }

    // ===================================================================
    // 😉 ANIMAÇÃO E RENDERIZAÇÃO
    // ===================================================================

    async function fadeTransition(target, updateFunction, duration = 300) {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        if (!element) {
            console.error('fadeTransition: Elemento não encontrado.');
            return;
        }

        // Define classes de transição se ainda não existirem
        element.style.transition = `opacity ${duration}ms ease-in-out`;

        // Fade out
        element.style.opacity = 0;
        element.style.pointerEvents = 'none';

        await new Promise(resolve => setTimeout(resolve, duration));

        // Atualiza o conteúdo enquanto invisível
        await updateFunction();

        // Fade in
        element.style.opacity = 1;
        element.style.pointerEvents = '';
    }


    // =====================================================================
    // 5. TEMPLATE RENDERING & MESSAGING UTILITIES
    // =====================================================================

    async function renderTemplate(container, template, data = null, onRendered = null) {
        await fadeTransition(container, async () => {
            if (typeof template === 'string' && templates[template]) {
                container.innerHTML = templates[template];
            } else if (typeof template === 'function') {
                const payload = data ?? {};
                const result = template(payload);
                container.innerHTML = result instanceof Promise ? await result : result;
            } else {
                console.error('Template inválido.');
                return;
            }
            if (onRendered) await onRendered();
        });
    }

    async function showMessage(container, message, type = 'info', { autoDismiss = true, dismissAfter } = {}) {
        if (!container) {
            console.error('[message] container not found');
            return;
        }

        container.dataset.messageType = type;
        container.dataset.messageText = message;

        await renderTemplate(container, templates.message, {
            message,
            type,
            autoDismiss,
            ...(typeof dismissAfter === 'number' ? { dismissAfter } : {})
        });
    }

    // Helpers de animação (com fallback para quem prefere menos movimento)
    const prefersReduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    function enterRow(row) {
        if (prefersReduced) return;

        row.style.overflow = 'hidden';
        row.style.opacity = '0';
        row.style.transform = 'scale(0.98)';
        row.style.height = '0px';

        // força reflow
        row.getBoundingClientRect();

        row.style.transition = 'height 160ms ease, opacity 160ms ease, transform 160ms ease';
        row.style.height = row.scrollHeight + 'px';
        row.style.opacity = '1';
        row.style.transform = 'scale(1)';

        row.addEventListener('transitionend', () => {
            row.style.transition = '';
            row.style.height = '';
            row.style.overflow = '';
            row.style.transform = '';
            row.style.opacity = '';
        }, { once: true });
    }

    function leaveRow(row, done) {
        if (prefersReduced) { done?.(); return; }

        row.style.overflow = 'hidden';
        row.style.height = row.scrollHeight + 'px';
        row.style.opacity = '1';
        row.style.transform = 'scale(1)';

        // força reflow
        row.getBoundingClientRect();

        row.style.transition = 'height 160ms ease, opacity 160ms ease, transform 160ms ease';
        row.style.height = '0px';
        row.style.opacity = '0';
        row.style.transform = 'scale(0.98)';

        row.addEventListener('transitionend', () => {
            done?.();
        }, { once: true });
    }

    // Opcional: manter os cantos arredondados só na 1ª e última linhas
    function fixRoundings(container) {
        const rows = [...container.children];
        rows.forEach((row, i) => {
            row.classList.remove('rounded-t-2xl', 'rounded-b-2xl', 'border-b', 'border-gray-100');
            const urlType = row.querySelector('input[name="url_type"]');
            const urlValue = row.querySelector('input[name="url_value"]');
            urlType?.classList.remove('rounded-tl-2xl');
            urlValue?.classList.remove('rounded-tr-2xl');

            if (i === 0) {
                row.classList.add('rounded-t-2xl');
                urlType?.classList.add('rounded-tl-2xl');
                urlValue?.classList.add('rounded-tr-2xl');
            }
            if (i === rows.length - 1) {
                row.classList.add('rounded-b-2xl');
            } else {
                row.classList.add('border-b', 'border-gray-100');
            }
        });
    }

    async function toggleSidebar(el = null, toggle = true) {

        if (sidebarWrapper.innerHTML.trim() !== '') {
            sidebarWrapper.innerHTML = '';
        }

        //

        if (toggle === true) {

            // Para o post-editor, garantir que a sidebar fique visível
            if (el && el.dataset.sidebarAction === 'post-editor') {
                // Remover w-0 e adicionar classes de largura
                sidebarWrapper.classList.remove('w-0');
                sidebarWrapper.classList.add('lg:w-1/3', 'sm:w-1/2', 'w-full', 'shadow-2xl');
            } else {
                // Comportamento normal de toggle para outros elementos
                sidebarWrapper.classList.toggle('w-0');
                sidebarWrapper.classList.toggle('lg:w-1/3');
                sidebarWrapper.classList.toggle('sm:w-1/2');
                sidebarWrapper.classList.toggle('w-full');
                sidebarWrapper.classList.toggle('shadow-2xl');
            }
        }

        if (el) {
            sidebarWrapper.innerHTML = `<div class="sidebar-content grid grid-cols-1 gap-6 p-4"></div>`;
            const sidebarContent = document.querySelector('.sidebar-content');
            const action = el.dataset.sidebarAction;
            if (action === 'settings') {
                // Página principal de configurações (atalhos gerais)
                // Use apenas o sistema de navegação do SidebarNav para renderizar e anexar handlers
                SidebarNav.setMount(sidebarContent);
                SidebarNav.resetRoot(currentUserData); // já chama SidebarNav.render() e configura os eventos do root

            } else if (action === 'post-editor') {
                // Editor de posts
                // Carregar CSS do editor se ainda não foi carregado
                if (!document.querySelector('link[href*="editor.css"]')) {
                    const editorCSS = document.createElement('link');
                    editorCSS.rel = 'stylesheet';
                    // Use absolute path to ensure correct load on nested routes
                    editorCSS.href = '/css/editor.css';
                    document.head.appendChild(editorCSS);
                }

                // Usar o sistema de navegação do sidebar
                SidebarNav.setMount(sidebarContent);

                // Renderizar o template do post-editor
                renderTemplate(sidebarContent, templates.sidebarPageSettings, {
                    view: 'post-editor',
                    data: currentUserData,
                    navTitle: 'Editor de Posts',
                    prevTitle: 'Voltar',
                    origin: 'stack'
                }, async () => {
                    // Callback executado após a renderização completa
                    setTimeout(() => {
                        // Inicializa a UI de múltiplas mídias (carrossel)
                        try { initPostEditorGallery(sidebarWrapper); } catch (_) {}
                        try { setupEditorCaptureBridge(sidebarWrapper); } catch (_) {}
                        // Unifica o botão Enviar com a galeria
                        try { wireUnifiedSendFlow(sidebarWrapper); } catch (_) {}
                        // Unifica captura e rótulos para adicionar mídia na galeria
                        try { wireUnifiedMediaAdders(sidebarWrapper); } catch (_) {}
                        // Estratégia local-first: override de picker e captura
                        try { setupLocalFirstGalleryUpload(sidebarWrapper); } catch (_) {}
                        try { setupEditorCaptureBridgeLocal(sidebarWrapper); } catch (_) {}
                        // Buscar elementos diretamente no sidebarWrapper
                        const appShellInSidebar = sidebarWrapper.querySelector('#appShell');
                        const gridCanvasInSidebar = sidebarWrapper.querySelector('#gridCanvas');

                        if (appShellInSidebar && gridCanvasInSidebar) {
                            loadEditorScript(sidebarWrapper);
                        } else {
                            // Tentar novamente após mais tempo
                            setTimeout(() => {
                                const appShellRetry = sidebarWrapper.querySelector('#appShell');
                                const gridCanvasRetry = sidebarWrapper.querySelector('#gridCanvas');

                                if (appShellRetry && gridCanvasRetry) {
                                    loadEditorScript(sidebarWrapper);
                                } else {
                                    notifyError('Interface do editor não foi carregada corretamente.');
                                }
                            }, 500);
                        }
                    }, 200);
                });

            } else if (action === 'page-settings') {
                // Entrou em Ajustes a partir da página atual (fora do stack ou entidade corrente)
                SidebarNav.setMount(sidebarContent);
                SidebarNav.resetRoot(currentUserData);
                const containerEl = el.closest('[data-sidebar-type]') || el.parentNode;
                let pageSettingsView = (containerEl?.dataset?.sidebarType === 'current-user') ? ENTITY.PROFILE : viewType;
                let pageSettingsData = (containerEl?.dataset?.sidebarType === 'current-user') ? currentUserData : viewData;
                SidebarNav.push({ view: pageSettingsView, title: (pageSettingsData?.tt || 'Ajustes'), payload: { data: pageSettingsData, type: pageSettingsView } });
                // listeners complementares (contatos etc.)
                const settingsForm = document.querySelector('#settings-form');
                if (!settingsForm) return;
                //Botões de Adição / Remoção
                settingsForm?.addEventListener('click', (e) => {
                    const addBtn = e.target.closest('#add-input-button');
                    const rmBtn = e.target.closest('#remove-input-button');
                    if (!addBtn && !rmBtn) return;
                    e.preventDefault();
                    const container = settingsForm.querySelector('#input-container');
                    if (!container) return;
                    if (addBtn) {
                        const last = container.lastElementChild;
                        const lastId = last ? parseInt(last.dataset.inputId || '-1', 10) : -1;
                        const newId = Number.isFinite(lastId) ? lastId + 1 : 0;
                        let newRow;
                        if (last) {
                            newRow = last.cloneNode(true);
                            newRow.dataset.inputId = String(newId);
                            newRow.querySelectorAll('input, select, textarea').forEach((el) => {
                                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                                else el.value = '';
                                if (el.id) {
                                    const base = el.id.replace(/-\d+$/, '');
                                    el.id = `${base}-${newId}`;
                                }
                            });
                            newRow.querySelectorAll('label[for]').forEach((lab) => {
                                const base = lab.htmlFor?.replace(/-\d+$/, '') ?? '';
                                if (base) lab.htmlFor = `${base}-${newId}`;
                            });
                        } else {
                            return;
                        }
                        newRow.style.willChange = 'height, opacity, transform';
                        container.appendChild(newRow);
                        enterRow(newRow);
                        fixRoundings(container);
                    }
                    if (rmBtn) {
                        if (container.children.length > 1) {
                            const row = container.lastElementChild;
                            row.style.willChange = 'height, opacity, transform';
                            leaveRow(row, () => {
                                row.remove();
                                fixRoundings(container);
                            });
                        }
                    }
                });
                document?.querySelector("#business-shareholding")?.addEventListener('click', (e) => {
                    renderTemplate(sidebarContent, templates.sidebarPageSettings, {
                        view: 'business-shareholding',
                        data: pageSettingsData
                    }, () => { });
                });
            };
        };
    };

    // Anexa listeners necessários para interações dentro de uma subview de ajustes
    function wireSidebarPageActions(sidebarContent, pageSettingsData, pageSettingsView) {
        const settingsForm = document.querySelector('#settings-form');
        if (settingsForm) {
            if (settingsForm._clickHandler) settingsForm.removeEventListener('click', settingsForm._clickHandler);
            const clickHandler = (e) => {
                const addBtn = e.target.closest('#add-input-button');
                const rmBtn = e.target.closest('#remove-input-button');
                if (!addBtn && !rmBtn) return;
                e.preventDefault();
                const container = settingsForm.querySelector('#input-container');
                if (!container) return;
                if (addBtn) {
                    const last = container.lastElementChild;
                    const lastId = last ? parseInt(last.dataset.inputId || '-1', 10) : -1;
                    const newId = Number.isFinite(lastId) ? lastId + 1 : 0;
                    if (!last) return;
                    const newRow = last.cloneNode(true);
                    newRow.dataset.inputId = String(newId);
                    newRow.querySelectorAll('input, select, textarea').forEach((el) => {
                        if (el.tagName === 'SELECT') el.selectedIndex = 0; else el.value = '';
                        if (el.id) { const base = el.id.replace(/-\d+$/, ''); el.id = `${base}-${newId}`; }
                    });
                    newRow.querySelectorAll('label[for]').forEach((lab) => {
                        const base = lab.htmlFor?.replace(/-\d+$/, '') ?? '';
                        if (base) lab.htmlFor = `${base}-${newId}`;
                    });
                    newRow.style.willChange = 'height, opacity, transform';
                    container.appendChild(newRow);
                    enterRow(newRow);
                    fixRoundings(container);
                }
                if (rmBtn && container.children.length > 1) {
                    const row = container.lastElementChild;
                    row.style.willChange = 'height, opacity, transform';
                    leaveRow(row, () => { row.remove(); fixRoundings(container); });
                }
            };
            settingsForm.addEventListener('click', clickHandler);
            settingsForm._clickHandler = clickHandler;
            setupCepAutofill(settingsForm);
        }

        setupHeroImageUpload(sidebarContent, pageSettingsData, pageSettingsView);
        setupBackgroundImageUpload(sidebarContent, pageSettingsData, pageSettingsView);
        setupRemoveBackgroundImage(sidebarContent, pageSettingsData, pageSettingsView);

        // atalhos da subview (via stack)
        if (sidebarContent._shortcutsHandler) sidebarContent.removeEventListener('click', sidebarContent._shortcutsHandler);
        const shortcutsHandler = (e) => {
            const shortcut = e.target.closest('#employees, #user-jobs, #testimonials, #billing, #transactions, #subscriptions, #password');
            if (!shortcut) return;
            const id = shortcut.id;
            const title = pageSettingsData?.tt || 'Ajustes';
            if (typeof SidebarNav !== 'undefined') {
                SidebarNav.push({ view: id, title, payload: { data: pageSettingsData, type: pageSettingsView } });
            } else {
                renderTemplate(sidebarContent, templates.sidebarPageSettings, { view: id, data: pageSettingsData, type: pageSettingsView, origin: 'page' });
            }
        };
        sidebarContent.addEventListener('click', shortcutsHandler);
        sidebarContent._shortcutsHandler = shortcutsHandler;




        document?.getElementById('settings-form')?.addEventListener('submit', handleUpdate);
        initMasks();
    }

    function setupCepAutofill(form) {
        if (!form) return;
        const zipInput = form.querySelector('input[name="zip_code"]');
        if (!zipInput || zipInput.dataset.cepAutofill === '1') return;

        let lookupTimeout = null;
        let inFlightCep = '';

        const resolveMessageContainer = () => form.querySelector('[data-role="message"]') || document.getElementById('message');

        const scheduleLookup = () => {
            clearTimeout(lookupTimeout);
            lookupTimeout = setTimeout(async () => {
                const sanitized = onlyNumbers(zipInput.value || '');
                if (sanitized.length !== 8) {
                    delete zipInput.dataset.lastCepFetched;
                    inFlightCep = '';
                    return;
                }

                if (sanitized === zipInput.dataset.lastCepFetched || sanitized === inFlightCep) {
                    return;
                }

                inFlightCep = sanitized;
                const result = await fetchCepData(sanitized);
                inFlightCep = '';

                if (!result.ok) {
                    const container = resolveMessageContainer();
                    if (container && result.message) {
                        await showMessage(container, result.message, 'warning', { dismissAfter: 6000 });
                    }
                    return;
                }

                applyCepData(form, result.data || {});
                zipInput.dataset.lastCepFetched = sanitized;
            }, 400);
        };

        zipInput.addEventListener('input', scheduleLookup);
        zipInput.addEventListener('blur', scheduleLookup);
        zipInput.dataset.cepAutofill = '1';
    }

    async function fetchCepData(cep) {
        const sanitized = onlyNumbers(cep || '');
        if (sanitized.length !== 8) {
            return { ok: false, message: 'Informe um CEP com 8 digitos.' };
        }

        const supportsAbort = typeof AbortController !== 'undefined';
        const controller = supportsAbort ? new AbortController() : null;
        let timeoutId = null;

        if (controller) {
            timeoutId = setTimeout(() => controller.abort(), 7000);
        }

        try {
            const options = controller ? { signal: controller.signal } : {};
            const response = await fetch('https://viacep.com.br/ws/' + sanitized + '/json/', options);
            if (!response.ok) {
                return { ok: false, message: 'Nao foi possivel consultar o CEP.' };
            }
            const data = await response.json();
            if (data && data.erro) {
                return { ok: false, message: 'CEP nao encontrado.' };
            }
            return { ok: true, data };
        } catch (error) {
            console.error('[cep] lookup error', error);
            const message = error && error.name === 'AbortError'
                ? 'Tempo excedido ao consultar o CEP.'
                : 'Nao foi possivel consultar o CEP.';
            return { ok: false, message };
        } finally {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        }
    }

    function applyCepData(form, cepData) {
        if (!cepData || !form) return;
        const mapping = [
            ['address', cepData.logradouro || ''],
            ['district', cepData.bairro || ''],
            ['city', cepData.localidade || ''],
            ['state', cepData.uf || ''],
            ['country', 'Brasil'],
            ['complement', cepData.complemento || ''],
        ];

        mapping.forEach(([name, value]) => {
            const field = form.querySelector('[name="' + name + '"]');
            if (!field) return;
            field.value = value || '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // OUTRAS FUNÇÕES

    function renderOutsourcedRow(typeSelectEl, { selected = '', disabled = false } = {}) {
        const rowEl = typeSelectEl.closest('.grid');
        if (!rowEl) return;

        // remove linha antiga se existir
        const existing = rowEl.nextElementSibling;
        if (existing && existing.dataset.role === 'outsourced-extra') {
            existing.remove();
        }

        if (typeSelectEl.value !== 'outsourced') return;

        let html = `
        <div data-role="outsourced-extra" class="grid grid-cols-4 border-b border-gray-200">
            <label class="col-span-1 p-4 truncate text-gray-500">Terceirizada</label>
            <select name="third_party" ${disabled ? 'disabled' : ''} class="border-0 focus:outline-none col-span-3 p-4" required>
            <option value="" disabled ${!selected ? 'selected' : ''}>Selecione</option>
            `;
        const optionsHtml = Object.entries(businessesJobs).sort(([, a], [, b]) => a.localeCompare(b, 'pt-BR')).map(([id, nome]) => `
            <option value="${id}" ${String(selected) === String(id) ? 'selected' : ''}>${nome}</option>
            `).join('');
        html += optionsHtml + `
            </select>
        </div>
        `;

        rowEl.insertAdjacentHTML('afterend', html);
    }

    // 2) Inicializa (útil no carregamento da página/templating)
    function initOutsourcedUI(scope = document) {
        let allTypeSelectors = scope.querySelectorAll('.job-form select[name="type"]');
        allTypeSelectors.forEach(sel => {
            const form = sel.closest('.job-form');
            const disabled = sel.disabled || form?.dataset.readonlyMode === '1';
            const currentExtraValue = form?.querySelector('[name="third_party"]')?.value || form?.dataset.thirdParty || '';
            renderOutsourcedRow(sel, { selected: currentExtraValue, disabled });
        });
    }

    // ===================================================================
    // 🧠 LÓGICA DE INICIALIZAÇÃO
    // ===================================================================

    async function startup() {

        const showNotLoggedIn = () => {
            localStorage.removeItem('jwt_token');
            renderTemplate(mainWrapper, 'notLoggedIn', null, () => {
                let loginWrapper = document.querySelector('#login');
                renderTemplate(loginWrapper, 'init', null, () => {
                    renderLoginUI();
                    viewType = 'public';
                    resetFeed();
                    loadFeed();
                    initFeedInfiniteScroll();
                });
            });
        }

        const urlToken = new URLSearchParams(window.location.search).get('token');
        if (urlToken) {
            localStorage.setItem('jwt_token', urlToken);
            window.history.replaceState({}, '', '/');
        }

        if (!localStorage.getItem('jwt_token')) {
            // Rota pública: permite visualizar perfis e negócios com página pública
            const path = window.location.pathname || '';
            const isPublicEntityRoute = /^(\/profile\/(\d+)|\/business\/(\d+))$/.test(path);
            if (isPublicEntityRoute) {
                // Renderiza layout base para suportar entity view e feed containers
                renderTemplate(mainWrapper, 'dashboard', null, () => {
                    // Oculta o gatilho da sidebar para visitantes
                    const st = document.querySelector('#sidebarTrigger');
                    if (st) st.style.display = 'none';
                    workzContent = document.querySelector('#workz-content');
                    loadPage();
                });
                return;
            }
            // Fallback: landing pública com login e feed básico
            showNotLoggedIn();
            return;
        }

        // Inicia com os dados do usuário logado
        const isInitialized = await initializeCurrentUserData();
        if (!isInitialized) {
            showNotLoggedIn();
            return;
        };

        // Obtem os dados do usuário logado

        // Pessoas
        userPeople = await apiClient.post('/search', {
            db: 'workz_data',
            table: 'usg',
            columns: ['s1'],
            conditions: {
                s0: currentUserData.id
            },
            exists: [{
                table: 'hus',           // tabela a checar
                local: 's1',            // coluna da tabela principal (usg.s1)
                remote: 'id',           // coluna da outra tabela (hus.id)                
                conditions: { st: 1 }   // filtros extras na tabela hus
            }],
            order: { by: 's1', dir: 'DESC' },
            fetchAll: true
        });
        userPeople = Array.isArray(userPeople?.data) ? userPeople.data.map(o => o.s1) : [];

        // Negócios
        // 1) Vínculos por employees (membros/gestores)
        userBusinesses = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'employees',
            columns: ['*'],
            conditions: {
                us: currentUserData.id
            },
            exists: [{
                table: 'companies',     // tabela a checar
                local: 'em',            // coluna da tabela principal (employees.em)
                remote: 'id',           // coluna da outra tabela (companies.id)
                conditions: { st: 1 }   // filtros extras na tabela companies
            }],
            order: { by: 'em', dir: 'DESC' },
            fetchAll: true
        });
        userBusinessesData = Array.isArray(userBusinesses?.data) ? userBusinesses.data.slice() : [];
        userBusinesses = userBusinessesData.map(o => o.em);

        // 2) Inclui também negócios criados pelo usuário (companies.us = currentUserData.id)
        const ownedBiz = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'companies',
            columns: ['id', 'us', 'st', 'tt'],
            conditions: { us: currentUserData.id, st: 1 },
            order: { by: 'id', dir: 'DESC' },
            fetchAll: true
        });
        const ownedList = Array.isArray(ownedBiz?.data) ? ownedBiz.data : [];
        // Mescla: adiciona como se fossem vínculos employees nv=4
        for (const b of ownedList) {
            if (!userBusinesses.includes(b.id)) {
                userBusinesses.push(b.id);
                userBusinessesData.push({ us: currentUserData.id, em: b.id, nv: 4, st: b.st });
            }
        }

        // Equipes
        userTeams = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'teams_users',
            columns: ['*'],
            conditions: {
                us: currentUserData.id
            },
            exists: [{
                table: 'teams',         // tabela a checar
                local: 'cm',            // coluna da tabela principal (user_teams.cm)
                remote: 'id',           // coluna da outra tabela (teams.id)
                conditions: { st: 1 }   // filtros extras na tabela teams
            }],
            order: { by: 'cm', dir: 'DESC' },
            fetchAll: true
        });
        userTeamsData = Array.isArray(userTeams?.data) ? userTeams.data : [];

        // 1) Colete os IDs dos teams (cm) e busque todos de uma vez
        const cmIds = userTeamsData.map(t => t.cm).filter(Boolean);

        const teamRes = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'teams',
            columns: ['id', 'em'],
            conditions: { id: { op: 'IN', value: cmIds } },
            fetchAll: true,
            limit: cmIds.length
        });

        // 2) Mapa id->em para consulta rápida
        const idToEm = new Map((teamRes?.data || []).map(r => [r.id, r.em]));

        // 3) Conjunto de businesses aprovados do usuário (compatível com string/number)
        const userBusinessSet = new Set(
            (Array.isArray(userBusinessesData) ? userBusinessesData : [])
                .filter(r => Number(r?.st ?? 0) === 1)
                .map(r => String(r.em))
        );

        // 4) Filtre os teams que pertencem a businesses do usuário
        const filteredTeams = userTeamsData.filter(t => {
            const em = idToEm.get(t.cm);
            return userBusinessSet.has(String(em));
        });

        // 5) Aplique o resultado (substitui a lista, evita remover “em voo”)
        userTeamsData = filteredTeams;

        userTeams = userTeamsData.map(o => o.cm);
        renderTemplate(mainWrapper, 'dashboard', null, () => {
            workzContent = document.querySelector('#workz-content');
            loadPage();
        });
    }

    function loadPage() {
        // Ao carregar uma nova página, desconecte qualquer observer de lista restante
        try { if (listObserver) { listObserver.disconnect(); listObserver = null; } } catch (_) {}
        // Verifica se a URL deve redirecionar a uma página específica
        const path = window.location.pathname;
        const profileMatch = path.match(/^\/profile\/(\d+)$/);
        const businessMatch = path.match(/^\/business\/(\d+)$/);
        const teamMatch = path.match(/^\/team\/(\d+)$/);
        const peopleListMatch = path.match(/^\/people$/);
        const businessListMatch = path.match(/^\/businesses$/);
        const teamsListMatch = path.match(/^\/teams$/);

        const renderList = async (listType = 'people') => {
            const entityMap = {
                people: { db: 'workz_data', table: 'hus', columns: ['id', 'tt', 'im'], conditions: { st: 1 }, url: 'profile/' },
                teams: { db: 'workz_companies', table: 'teams', columns: ['id', 'tt', 'im', 'em'], conditions: { st: 1 }, url: 'team/' },
                businesses: { db: 'workz_companies', table: 'companies', columns: ['id', 'tt', 'im'], conditions: { st: 1 }, url: 'business/' }
            };
            let list = await apiClient.post('/search', {
                db: entityMap[listType].db,
                table: entityMap[listType].table,
                columns: entityMap[listType].columns,
                conditions: entityMap[listType].conditions,
                order: { by: 'tt', dir: 'ASC' },
                fetchAll: true
            });
            list = Array.isArray(list?.data) ? list.data : [];
            // Filtro global: Equipes só são listadas se o usuário for membro aprovado do negócio dono
            if (listType === 'teams') {
                const approvedBizSet = new Set((Array.isArray(userBusinessesData) ? userBusinessesData : [])
                    .filter(r => Number(r?.st ?? 0) === 1)
                    .map(r => String(r.em))
                );
                list = (list || []).filter(t => approvedBizSet.has(String(t.em)));
            }
            renderTemplate(workzContent, templates.listView, list, async () => {
                // Delegação única para os itens da lista
                const handler = (e) => {
                    const item = e.target.closest('.list-item');
                    if (!item) return;
                    navigateTo(`/${entityMap[listType].url + item.dataset.itemId}`);
                };
                workzContent.addEventListener('click', handler, { once: true });
                // Busca rápida no cliente
                const searchInput = document.getElementById(`${listType}-search`);
                if (searchInput) {
                    const listRoot = workzContent.querySelector('.col-span-12.grid');
                    const doFilter = () => {
                        const q = (searchInput.value || '').toLowerCase().trim();
                        workzContent.querySelectorAll('.list-item').forEach(el => {
                            const name = el.getAttribute('data-name') || '';
                            el.style.display = (!q || name.includes(q)) ? '' : 'none';
                        });
                    };
                    searchInput.addEventListener('input', debounce(doFilter, 150));
                }
                hideLoading();
            });
        };

        // Nova implementação com busca no servidor, paginação e contagem
        const renderListV2 = async (listType = 'people') => {
            const entityMap = {
                people: { db: 'workz_data', table: 'hus', columns: ['id', 'tt', 'im'], url: 'profile/' },
                teams: { db: 'workz_companies', table: 'teams', columns: ['id', 'tt', 'im', 'em'], url: 'team/' },
                businesses: { db: 'workz_companies', table: 'companies', columns: ['id', 'tt', 'im'], url: 'business/' }
            };

            const PAGE_SIZE = 24;
            const state = { q: '', offset: 0, loading: false, finished: false, total: 0 };

            const approvedBizIds = (Array.isArray(userBusinessesData) ? userBusinessesData : [])
                .filter(r => Number(r?.st ?? 0) === 1)
                .map(r => r.em);

            const buildConditions = (q) => {
                const base = { st: 1 };
                if (q) base.tt = { op: 'LIKE', value: `%${q}%` };
                if (listType === 'teams') {
                    if (approvedBizIds.length) base.em = { op: 'IN', value: approvedBizIds };
                    else base.em = { op: 'IN', value: [-1] };
                }
                return base;
            };

            async function fetchCount() {
                const res = await apiClient.post('/count', {
                    db: entityMap[listType].db,
                    table: entityMap[listType].table,
                    conditions: buildConditions(state.q),
                    distinctCol: 'id'
                });
                state.total = Number(res?.count ?? 0) || 0;
            }

            async function fetchPage() {
                if (state.loading || state.finished) return [];
                state.loading = true;
                const res = await apiClient.post('/search', {
                    db: entityMap[listType].db,
                    table: entityMap[listType].table,
                    columns: entityMap[listType].columns,
                    conditions: buildConditions(state.q),
                    order: { by: 'tt', dir: 'ASC' },
                    fetchAll: true,
                    limit: PAGE_SIZE,
                    offset: state.offset
                });
                const items = Array.isArray(res?.data) ? res.data : [];
                if (!items.length) { state.finished = true; }
                else { state.offset += items.length; }
                state.loading = false;
                return items;
            }

            const itemCardHTML = (item) => {
                const name = (item.tt || '');
                const img = resolveImageSrc(item?.im, name, { size: 80 });
                return `
                    <div class="list-item sm:col-span-12 md:col-span-6 lg:col-span-4 flex flex-col bg-white p-3 rounded-3xl shadow-lg bg-gray hover:bg-gray-100 cursor-pointer" style="list-style: none;" data-item-id="${item.id}" data-name="${name.toLowerCase()}">
                        <div class="flex items-center gap-3">
                            <img class="w-10 h-10 rounded-full object-cover" src="${img}" alt="${name}">
                            <span class="font-semibold truncate">${name}</span>
                        </div>
                    </div>`;
            };

            await fetchCount();
            const firstItems = await fetchPage();

            renderTemplate(workzContent, templates.listView, { type: listType, items: firstItems }, async () => {
                const countEl = document.getElementById('list-count');
                const searchInput = document.getElementById(`${listType}-search`);
                const gridEl = document.getElementById('list-grid');
                const sentinel = document.getElementById('list-sentinel');

                const updateCount = (shown = null) => {
                    const n = Number(state.total || 0);
                    const val = n > 0 ? n : (shown != null ? shown : state.offset);
                    if (countEl) countEl.textContent = `${val} resultados`;
                };
                updateCount(firstItems?.length || 0);

                // Navegar ao clicar (delegado no grid)
                const handler = (e) => {
                    const item = e.target.closest('.list-item');
                    if (!item || !gridEl || !gridEl.contains(item)) return;
                    navigateTo(`/${entityMap[listType].url + item.dataset.itemId}`);
                };
                if (gridEl) gridEl.addEventListener('click', handler);

                const reload = async () => {
                    state.offset = 0; state.finished = false;
                    await fetchCount();
                    if (gridEl) gridEl.innerHTML = '';
                    const fresh = await fetchPage();
                    if (gridEl && fresh.length) gridEl.insertAdjacentHTML('beforeend', fresh.map(itemCardHTML).join(''));
                    updateCount(fresh?.length || 0);
                };

                if (searchInput) {
                    const onInput = debounce(async () => {
                        const q = (searchInput.value || '').trim();
                        if (q === state.q) return;
                        state.q = q;
                        await reload();
                    }, 300);
                    searchInput.addEventListener('input', onInput);
                }

                if (sentinel) {
                    try { if (listObserver) { listObserver.disconnect(); listObserver = null; } } catch(_) {}
                    listObserver = new IntersectionObserver(async (entries) => {
                        const [entry] = entries;
                        if (!entry.isIntersecting) return;
                        if (state.loading || state.finished) return;
                        const more = await fetchPage();
                        if (gridEl && more.length) gridEl.insertAdjacentHTML('beforeend', more.map(itemCardHTML).join(''));
                    }, { rootMargin: '200px' });
                    try { listObserver.observe(sentinel); } catch(_) {}
                }

                hideLoading();
            });
        };

        memberLevel = null;
        memberStatus = null;
        viewId = null;
        viewData = null;
        viewType = null;
        pageRestricted = false;

        if (peopleListMatch) {
            renderListV2();
            return;
        } else if (businessListMatch) {
            renderListV2('businesses');
            return;
        } else if (teamsListMatch) {
            renderListV2('teams');
            return;
        } else {
            if (profileMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    applyEntityBackgroundImage(null);
                    viewType = 'profile';
                    viewId = parseInt(profileMatch[1], 10);
                    renderView(viewId);
                });
                return;
            } else if (businessMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    applyEntityBackgroundImage(null);
                    viewType = 'business';
                    viewId = parseInt(businessMatch[1], 10);
                    renderView(viewId);
                });
                return;
            } else if (teamMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    applyEntityBackgroundImage(null);
                    viewType = 'team';
                    viewId = parseInt(teamMatch[1], 10);
                    renderView(viewId);
                });
                return;
            } else {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    applyEntityBackgroundImage(null);
                    viewType = 'dashboard';
                    renderView();
                });
                return;
            }
        }
    }

    async function renderView(entity = currentUserData) {

        // Limpar widgets existentes no início da renderView
        const widgetWrapper = document.querySelector('#widget-wrapper');
        if (widgetWrapper) {
            widgetWrapper.innerHTML = '';
        }

        // Normaliza o ID que vai para a query
        const entityId = typeof entity === 'object' && entity !== null ? entity.id : entity;
        let entityData = [];

        // Always-defines
        let widgetPeople = [];
        let widgetBusinesses = [];
        let widgetTeams = [];
        let widgetPeopleCount = 0;
        let widgetBusinessesCount = 0;
        let widgetTeamsCount = 0;
        let entityImage = '';

        // DASHBOARD: usa caches/globais já carregados
        if (viewType === 'dashboard') {


            const ppl = Array.isArray(userPeople) ? userPeople : [];
            // Somente vínculos aprovados para widgets do dashboard
            const approvedBizIds = (Array.isArray(userBusinessesData) ? userBusinessesData : [])
                .filter(r => Number(r?.st ?? 0) === 1)
                .map(r => r.em);
            const approvedTeamIds = (Array.isArray(userTeamsData) ? userTeamsData : [])
                .filter(r => Number(r?.st ?? 0) === 1)
                .map(r => r.cm);

            widgetPeople = ppl.slice(0, 6);
            widgetBusinesses = approvedBizIds.slice(0, 6);
            widgetTeams = approvedTeamIds.slice(0, 6);
            widgetPeopleCount = ppl.length;
            widgetBusinessesCount = approvedBizIds.length;
            widgetTeamsCount = approvedTeamIds.length;

            entityImage = resolveImageSrc(currentUserData?.im, currentUserData?.tt, { size: 100 });

            // OUTRAS ROTAS: define o que buscar
        } else {

            let entityMap = {};
            let entitiesToFetch = [];
            if (viewType === ENTITY.PROFILE) {
                entityMap = {
                    people: { db: 'workz_data', table: 'usg', target: 's1', conditions: { s0: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    // Somente businesses aprovados (st=1)
                    businesses: { db: 'workz_companies', table: 'employees', target: 'em', conditions: { us: entityId, st: 1 }, mainDb: 'workz_companies', mainTable: 'companies' },
                    teams: { db: 'workz_companies', table: 'teams_users', target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' },
                };
                entitiesToFetch = ['people', 'businesses', 'teams'];
            } else if (viewType === ENTITY.BUSINESS) {
                entityMap = {
                    // Somente membros aprovados (st=1) devem aparecer no widget da página do negócio
                    people: { db: 'workz_companies', table: 'employees', target: 'us', conditions: { em: entityId, st: 1 }, mainDb: 'workz_data', mainTable: 'hus' },
                    teams: { db: 'workz_companies', table: 'teams', target: 'id', conditions: { em: entityId, st: 1 } },
                    businesses: { db: 'workz_companies', table: 'employees', target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                };
                entitiesToFetch = ['people', 'teams'];
            } else if (viewType === ENTITY.TEAM) {
                entityMap = {
                    // Somente membros aprovados (st=1) devem aparecer
                    people: { db: 'workz_companies', table: 'teams_users', target: 'us', conditions: { cm: entityId, st: 1 }, mainDb: 'workz_data', mainTable: 'hus' },
                    teams: { db: 'workz_companies', table: 'teams_users', target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' }
                };
                entitiesToFetch = ['people'];
            }

            // Define o tipo de entidade
            let entityType = (viewType === ENTITY.PROFILE) ? 'people' : (viewType === ENTITY.BUSINESS) ? 'businesses' : 'teams';

            // Busca dados da entidade
            entityData = await apiClient.post('/search', {
                db: entityMap[entityType].db,
                table: entityMap[entityType].mainTable,
                columns: ['*'],
                conditions: { ['id']: entityId }
            });

            // Verifica se entidade existe
            if (!Array.isArray(entityData?.data) || entityData.data.length === 0) {
                hideLoading({ delay: 250 });
                return;
            }

            if (!entitiesToFetch.length) {
                // nada a buscar — evita loop vazio
                // ... render mínimo/placeholder se precisar
                return;
            }

            // Busca tudo em paralelo
            const resultsArray = await Promise.all(
                entitiesToFetch.map(async (key) => {
                    const cfg = entityMap[key];
                    try {
                        const [res] = await Promise.all([

                            // Primeiro: busca os 6 primeiros registros
                            (async () => {
                                const payload = {
                                    db: cfg.db,
                                    table: cfg.table,
                                    columns: [cfg.target],       // queremos o alvo (ex.: s1)
                                    conditions: cfg.conditions,  // filtrando pela conditions (ex.: s0)
                                    distinct: true,
                                    order: { by: cfg.target, dir: 'DESC' },
                                    fetchAll: true
                                };

                                // Só adiciona exists se mainDb e mainTable existirem
                                if (cfg.mainDb && cfg.mainTable) {
                                    payload.exists = [{
                                        db: cfg.mainDb,
                                        table: cfg.mainTable,       // tabela a checar
                                        local: cfg.target,          // coluna da tabela principal (usg.s1)
                                        remote: 'id',               // coluna da outra tabela (hus.id)
                                        conditions: { st: 1 }       // filtros extras na tabela hus
                                    }];
                                }
                                return apiClient.post('/search', payload);
                            })(),
                        ]);

                        const list = Array.isArray(res?.data) ? res.data.map(row => row[cfg.target]) : [];
                        const count = list.length;

                        return [key, list, count];

                    } catch (e) {
                        // Em caso de erro numa entidade, não derruba as demais
                        console.error(`Falha buscando ${key}:`, e);
                        return [key, [], 0];
                    }
                })
            );

            // Monta objeto de saída
            const results = {};
            for (const [key, list, count] of resultsArray) {
                const safeList = Array.isArray(list) ? list : [];
                results[key] = safeList;
                results[`${key}Count`] = count;
            }

            const entityRow = Array.isArray(entityData?.data) ? (entityData.data[0] || null) : (entityData?.data || null);
            if (!entityRow) { hideLoading({ delay: 250 }); return; }
            Object.assign(entityRow, results);

            viewData = entityRow;
            applyEntityBackgroundImage(viewData);

            // Restrição de acesso por privacidade da página e do conteúdo
            // Equipes: somente membros aprovados da equipe
            if (viewType === ENTITY.TEAM) {
                const isTeamMemberApproved = Array.isArray(userTeamsData)
                    ? userTeamsData.some(t => String(t.cm) === String(viewData.id) && Number(t.st) === 1)
                    : false;
                viewRestricted = !isTeamMemberApproved;
                pageRestricted = false;
            } else if (viewType === ENTITY.PROFILE) {
                // Pessoas: feed_privacy
                // Página: pública (=1) permite acesso sem login; caso contrário, exige login
                const hasJwt = !!localStorage.getItem('jwt_token');
                const pp = Number(viewData?.page_privacy ?? 0);
                pageRestricted = !hasJwt && pp !== 1;
                const fp = Number(viewData?.feed_privacy ?? 0);
                const isOwner = String(currentUserData?.id ?? '') === String(viewData?.id ?? '');
                const isFollower = Array.isArray(userPeople)
                    ? userPeople.map(String).includes(String(viewData?.id))
                    : false;
                let restricted = false;
                if (!isOwner) {
                    if (fp === 0) {
                        // Somente eu
                        restricted = true;
                    } else if (fp === 1) {
                        // Seguidores
                        restricted = !isFollower;
                    } else if (fp === 2) {
                        // Usuários logados
                        restricted = !hasJwt;
                    } else {
                        // 3: Toda a internet (OK mesmo sem login, desde que a página seja pública)
                        restricted = false;
                    }
                }
                viewRestricted = pageRestricted ? true : restricted;
            } else if (viewType === ENTITY.BUSINESS) {
                // Negócios: feed_privacy
                const fp = Number(viewData?.feed_privacy ?? 0);
                const parseIdArray = (val) => { try { const arr = JSON.parse(val); return Array.isArray(arr) ? arr : []; } catch (_) { return []; } };
                const mods = viewData?.usmn ? parseIdArray(viewData.usmn) : [];
                const isModerator = Array.isArray(mods) && mods.map(String).includes(String(currentUserData?.id));
                const isManager = isBusinessManager(viewData?.id);
                const isMemberApproved = Array.isArray(userBusinessesData)
                    ? userBusinessesData.some(r => String(r.em) === String(viewData?.id) && Number(r.st) === 1)
                    : false;
                const hasJwt = !!localStorage.getItem('jwt_token');
                const pp = Number(viewData?.page_privacy ?? 0);
                pageRestricted = !hasJwt && pp !== 1;
                let restricted = false;
                if (fp === 0) {
                    // Moderadores
                    restricted = !(isManager || isModerator);
                } else if (fp === 1) {
                    // Usuários membros
                    restricted = !isMemberApproved;
                } else if (fp === 2) {
                    // Usuários logados
                    restricted = !hasJwt;
                } else {
                    // 3: Toda a internet (OK mesmo sem login, desde que a página seja pública)
                    restricted = false;
                }
                viewRestricted = pageRestricted ? true : restricted;
            } else {
                viewRestricted = false;
                pageRestricted = false;
            }

            // Se a página está restrita para visitantes, redireciona para a página inicial
            if (pageRestricted) {
                try { window.location.href = '/'; } catch (_) { try { navigateTo('/'); } catch (__) {} }
                return;
            }

            const postConditions = getPostConditions(viewType, entityId);
            const followersConditions = getFollowersConditions(viewType, entityId);

            // Obtém o número de publicações
            let postsCount = await apiClient.post('/count', {
                db: 'workz_data',
                table: 'hpl',
                conditions: postConditions
            });

            results.postsCount = postsCount.count;

            const needFollowers = !!followersConditions;
            // Só chama a contagem de seguidores se houver condições
            if (needFollowers) {
                const followersCount = await apiClient.post('/count', {
                    db: 'workz_data',
                    table: 'usg',
                    conditions: followersConditions, // Ex.: quem segue este perfil
                    distinct: 's0',               // (mapeie para $distinctCol no backend)
                    exists: [{
                        db: 'workz_data',
                        table: 'hus',
                        local: 's1',
                        remote: 'id',
                        conditions: { st: 1 }
                    }]
                });
                results.followersCount = followersCount.count;
            };

            Object.assign(viewData, results);

            entityImage = resolveImageSrc(viewData.im, viewData.tt, { size: 100 });

            if (viewType === ENTITY.PROFILE && results?.teams) {
                // 1) Colete os IDs dos teams (cm) e busque todos de uma vez
                const cmIds = results?.teams.filter(Boolean);

                const teamRes = await apiClient.post('/search', {
                    db: 'workz_companies',
                    table: 'teams',
                    columns: ['id', 'em'],
                    conditions: { id: { op: 'IN', value: cmIds } },
                    fetchAll: true,
                    limit: cmIds.length
                });

                // 2) Mapa id->em para consulta rápida
                const idToEm = new Map((teamRes?.data || []).map(r => [r.id, r.em]));

                // 3) Conjunto de businesses aprovados do USUÁRIO LOGADO
                // Em todo o projeto, só exibimos equipes de negócios dos quais o usuário logado participa
                const userBusinessSet = new Set(
                    (Array.isArray(userBusinessesData) ? userBusinessesData : [])
                        .filter(r => Number(r?.st ?? 0) === 1)
                        .map(r => String(r.em))
                );

                // 4) Filtre os teams que pertencem a businesses do usuário
                const filteredTeams = results?.teams.filter(t => {
                    const em = idToEm.get(t);
                    return userBusinessSet.has(String(em));
                });


                // 5) Aplique o resultado (substitui a lista, evita remover “em voo”)
                results.teams = filteredTeams;
                results.teamsCount = filteredTeams.length;
            }

            // Negócios: se usuário não participa (aprovado) do negócio, não listar equipes dele
            if (viewType === ENTITY.BUSINESS) {
                const belongs = (Array.isArray(userBusinessesData) ? userBusinessesData : [])
                    .some(r => String(r.em) === String(entityId) && Number(r.st) === 1);
                if (!belongs) {
                    results.teams = [];
                    results.teamsCount = 0;
                }
            }

            // Atribuições com fallback
            widgetPeople = Array.isArray(results?.people) ? results.people.slice(0, 6) : [];
            widgetBusinesses = Array.isArray(results?.businesses) ? results.businesses.slice(0, 6) : [];
            widgetTeams = Array.isArray(results?.teams) ? results.teams.slice(0, 6) : [];
            widgetPeopleCount = results.peopleCount ?? 0;
            widgetBusinessesCount = results.businessesCount ?? 0;
            widgetTeamsCount = results.teamsCount ?? 0;
        }

        // Widgets: para visitantes (sem login), mostrar UI de login no wrapper;
        // usuários logados mantêm widgets, desde que a página não esteja bloqueada
        const isAuthed = !!localStorage.getItem('jwt_token');
        if (!isAuthed) {
            if (widgetWrapper) {
                await renderTemplate(widgetWrapper, 'init', null, () => { try { renderLoginUI(); } catch (_) {} });
            }
        } else if (!pageRestricted) {
            if (widgetPeople.length) await appendWidget('people', widgetPeople, widgetPeopleCount);
            if (widgetBusinesses.length) await appendWidget('businesses', widgetBusinesses, widgetBusinessesCount);
            if (widgetTeams.length) await appendWidget('teams', widgetTeams, widgetTeamsCount);
            if ([ENTITY.PROFILE, ENTITY.BUSINESS, ENTITY.TEAM].includes(viewType) && viewData) {
                appendContactsWidget(viewData);
            }
        }

        // Nível do usuário
        // - TEAM: utiliza nv do vínculo na própria equipe (teams_users)
        // - BUSINESS: utiliza nv do vínculo na empresa (employees)
        memberLevel = (viewType === ENTITY.TEAM)
            ? Number(parseInt(userTeamsData.find(item => item.cm === viewData.id)?.nv ?? 0))
            : (viewType === ENTITY.BUSINESS)
                ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.nv ?? 0))
                : 0;
        memberStatus = (viewType === ENTITY.TEAM) ? Number(parseInt(userTeamsData.find(item => item.cm === viewData.id)?.st ?? 0)) : (viewType === ENTITY.BUSINESS) ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.st ?? 0)) : 0;

        const editorTriggerEl = document.querySelector('#editor-trigger');
        const normalizedViewId = viewId ?? viewData?.id ?? entityId ?? null;
        const shouldShowEditorTrigger =
            viewType === 'dashboard' ||
            (viewType === ENTITY.PROFILE && currentUserData && normalizedViewId !== null && String(normalizedViewId) === String(currentUserData.id)) ||
            (viewType === ENTITY.BUSINESS && Number(memberStatus) === 1) ||
            (viewType === ENTITY.TEAM && Number(memberStatus) === 1 && !viewRestricted);
        // Nunca mostrar gatilho se página bloqueada
        if (pageRestricted) {
            editorTriggerEl.innerHTML = '';
            editorTriggerEl.hidden = true;
        }

        const editorTriggerPromise = (async () => {
            if (!editorTriggerEl) return;
            // Nunca mostrar gatilho se página bloqueada ou visitante
            const isAuthed2 = !!localStorage.getItem('jwt_token');
            if (!shouldShowEditorTrigger || !isAuthed2 || pageRestricted) {
                editorTriggerEl.innerHTML = '';
                editorTriggerEl.hidden = true;
                return;
            }
            editorTriggerEl.hidden = false;
            await renderTemplate(editorTriggerEl, templates['editorTriggerV2'], currentUserData, () => {
                try { if (window._mainActionHandler) { document.removeEventListener('click', window._mainActionHandler); } } catch(_) {}
                const _handler = (e) => {
                    // Verificar se clicou no post-editor
                    const postEditor = e.target.closest('#post-editor');
                    if (postEditor) {
                        e.preventDefault();
                        e.stopPropagation(); // Impedir que o event listener global processe

                        // Criar um elemento mock com data-sidebar-action para usar com toggleSidebar
                        const mockElement = document.createElement('div');
                        mockElement.dataset.sidebarAction = 'post-editor';
                        toggleSidebar(mockElement, true);
                        return;
                    }

                    const btn = e.target.closest('[data-action]');
                    if (!btn) return;
                    const action = btn.dataset.action;
                    const handler = ACTIONS[action];
                    if (!handler) return;
                    e.preventDefault();
                    Promise.resolve(handler({ event: e, button: btn, state: getState() }))
                        .finally(() => {
                            if (action === 'follow-user' || action === 'unfollow-user') {
                                try {
                                    if (viewType === ENTITY.PROFILE && String(viewId) === String(getState()?.view?.id)) {
                                        const fp = Number(viewData?.feed_privacy ?? 0);
                                        const isOwner = String(currentUserData?.id ?? '') === String(viewId ?? '');
                                        if (fp === 1) {
                                            if (action === 'follow-user') viewRestricted = false;
                                            else if (action === 'unfollow-user' && !isOwner) viewRestricted = true;
                                        }
                                        resetFeed();
                                        loadFeed();
                                    }
                                } catch (_) {}
                            }
                        });
                };
                document.addEventListener('click', _handler);
                window._mainActionHandler = _handler;
            });
        })();

        Promise.all([
            // Menu customizado
            customMenu(),
            // Gatilhos de criação de conteúdo
            editorTriggerPromise,

            (viewType === 'dashboard')
                ?
                // Conteúdo principal (Dashboard)
                renderTemplate(document.querySelector('#main-content'), 'mainContent', null, async () => {
                    // Aplicar plano de fundo personalizado da área de trabalho (ou Bing por padrão)
                    try { applyDesktopBackgroundFromSettings(); } catch (_) {}
                    startClock();
                    // Gatilho no topo (próximo ao relógio) para abrir o menu lateral de apps
                    try {
                        const btnApps = document.getElementById('apps-menu-trigger');
                        if (btnApps) {
                            if (btnApps._bound) btnApps.removeEventListener('click', btnApps._bound);
                            const handler = async (ev) => { ev.preventDefault(); await openAppsSidebar(); };
                            btnApps.addEventListener('click', handler);
                            btnApps._bound = handler;
                        }
                        // Fallback: delegação global em caso de re-render
                        if (!document._appsMenuDelegate) {
                            const delegate = async (ev) => {
                                const trg = ev.target && ev.target.closest && ev.target.closest('#apps-menu-trigger');
                                if (!trg) return;
                                ev.preventDefault();
                                await openAppsSidebar();
                            };
                            document.addEventListener('click', delegate);
                            document._appsMenuDelegate = delegate;
                        }
                    } catch (_) {}
                    // Aplicativos
                    let userAppsRaw = await apiClient.post('/search', {
                        db: 'workz_apps',
                        table: 'gapp',
                        columns: ['ap'],
                        conditions: {
                            us: currentUserData.id,
                            st: 1
                        },
                        fetchAll: true
                    });
                    const appIds = Array.isArray(userAppsRaw?.data) ? userAppsRaw.data.map(o => o.ap) : [];
                    const resolvedApps = await fetchByIds(appIds, 'apps'); // Fetch full app data here
                    try { window.__appListCache = Array.isArray(resolvedApps) ? resolvedApps : (resolvedApps ? [resolvedApps] : []); } catch (_) {}

                    await renderTemplate(document.querySelector('#app-library'), templates.appLibrary, { appsList: resolvedApps }, () => {
                        initAppLibrary('#app-library', window.__appListCache); // Pass resolvedApps to initAppLibrary
                    });
                })
                : Promise.resolve(),

            (viewType !== 'dashboard')
                ?
                // Conteúdo principal (Perfil, Negócio ou Equipe)
                renderTemplate(
                    document.querySelector('#main-content'),
                    (viewType === ENTITY.TEAM && viewRestricted) ? templates.teamRestricted : templates['entityContent'],
                    { data: viewData }
                )
                : Promise.resolve(),

            // Imagem da página
            document.querySelector('#profile-image').src = entityImage
        ]).then(() => {
            try { insertContentPrivacyNotice(); } catch (_) {}

            const widgetWrapper = document.querySelector('#widget-wrapper');

            // Remover event listeners existentes para evitar duplicação
            if (widgetWrapper._clickHandler) {
                widgetWrapper.removeEventListener('click', widgetWrapper._clickHandler);
            }
            if (widgetWrapper._keydownHandler) {
                widgetWrapper.removeEventListener('keydown', widgetWrapper._keydownHandler);
            }

            // Criar novos handlers
            const clickHandler = (e) => {
                const card = e.target.closest('.card-item');
                if (!card) return; // não clicou em um card

                // encontra a raiz do widget
                const widgetRoot = card.closest('[id^="widget-"]');
                if (!widgetRoot) return;

                // extrai o tipo do id (people, teams, business)
                const type = widgetRoot.id.replace('widget-', '');

                // pega o ID do card
                const id = card.dataset.id;

                // redireciona de acordo com o tipo
                let baseUrl;
                if (type === 'people') baseUrl = '/profile/';
                else if (type === 'teams') baseUrl = '/team/';
                else baseUrl = '/business/';

                navigateTo(`${baseUrl}${id}`);
            };

            // Acessibilidade: abrir cards com Enter
            const keydownHandler = (e) => {
                if (e.key !== 'Enter') return;
                const card = e.target.closest('.card-item');
                if (!card) return;
                const widgetRoot = card.closest('[id^="widget-"]');
                const type = widgetRoot?.id?.replace('widget-', '') || '';
                const id = card.dataset.id;
                let baseUrl;
                if (type === 'people') baseUrl = '/profile/';
                else if (type === 'teams') baseUrl = '/team/';
                else baseUrl = '/business/';
                navigateTo(`${baseUrl}${id}`);
            };

            // Adicionar os event listeners e armazenar as referências
            try { if (widgetWrapper._clickHandler) widgetWrapper.removeEventListener('click', widgetWrapper._clickHandler); } catch(_) {}
            widgetWrapper.addEventListener('click', clickHandler);
            try { if (widgetWrapper._keydownHandler) widgetWrapper.removeEventListener('keydown', widgetWrapper._keydownHandler); } catch(_) {}
            widgetWrapper.addEventListener('keydown', keydownHandler);
            widgetWrapper._clickHandler = clickHandler;
            widgetWrapper._keydownHandler = keydownHandler;


            const pageThumbs = document.getElementsByClassName('page-thumb');
            for (let i = 0; i < pageThumbs.length; i++) {
                pageThumbs[i].src = resolveImageSrc(currentUserData?.im, currentUserData?.tt, { size: 100 });
            }

            // Reseta o estado do feed
            resetFeed();

            // Finalizações
            loadFeed();
            initFeedInfiniteScroll();
            //topBarScroll();      
            hideLoading();
        });
    }

    // mapeia type -> banco/tabela
    const typeMap = {
        people: { db: 'workz_data', table: 'hus', idCol: 'id' },
        businesses: { db: 'workz_companies', table: 'companies', idCol: 'id' },
        teams: { db: 'workz_companies', table: 'teams', idCol: 'id' },
        apps: { db: 'workz_apps', table: 'apps', idCol: 'id' }
    };

    async function fetchByIds(ids = [], type = 'people') {
        const cfg = typeMap[type] || typeMap.people;

        // Normaliza: se for único valor, transforma em array
        if (!Array.isArray(ids)) {
            ids = [ids];
        }

        const uniqueIds = [...new Set(ids)].filter(Boolean);
        if (uniqueIds.length === 0) return Array.isArray(ids) ? [] : null;

        // 1) Busca em lote usando IN
        const res = await apiClient.post('/search', {
            db: cfg.db,
            table: cfg.table,
            columns: ['id', 'tt', 'im'], // ajuste as colunas necessárias
            conditions: { [cfg.idCol]: { op: 'IN', value: uniqueIds } },
            order: { by: 'tt', dir: 'ASC' },
            fetchAll: true,
            limit: uniqueIds.length
        });

        const list = Array.isArray(res?.data) ? res.data : [];

        // 2) Reordena pra manter a mesma ordem de entrada
        const byId = new Map(list.map(item => [item.id, item]));
        const results = ids.map(id => byId.get(id) || { id, tt: 'Item', im: '/images/default-avatar.jpg' });

        // Se o input original era único, devolve único
        return ids.length === 1 ? results[0] : results;
    }
    // Unificado: abrir app por ID/slug com SSO opcional
    async function launchAppById(appId, opts = {}) {
        try {
            const res = await apiClient.post('/search', { db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { id: appId } });
            const app = Array.isArray(res?.data) ? res.data[0] : res?.data;
            if (!app) return;
            await launchApp(app, opts);
        } catch (_) {}
    }
    async function launchAppBySlug(slug, opts = {}) {
        try {
            const res = await apiClient.post('/search', { db: 'workz_apps', table: 'apps', columns: ['*'], conditions: { slug } });
            const app = Array.isArray(res?.data) ? res.data[0] : res?.data;
            if (!app) return;
            await launchApp(app, opts);
        } catch (_) {}
    }
    async function launchApp(app, opts = {}) {
        let baseUrl = app?.embed_url || app?.src || app?.page_privacy || null;
        if (!app || !baseUrl) { return; }
        // Deriva contexto atual
        let ctx = null;
        if (viewType === ENTITY.BUSINESS && viewId) ctx = { type: 'business', id: viewId };
        else if (viewType === ENTITY.TEAM && viewId) ctx = { type: 'team', id: viewId };
        else if (currentUserData?.id) ctx = { type: 'user', id: currentUserData.id };

        let finalUrl = baseUrl;
        const wantSSO = opts.sso !== false;
        if (wantSSO) {
            try {
                const sso = await apiClient.post('/apps/sso', { app_id: app.id, ctx });
                const token = sso?.token || null;
                if (token) {
                    const sep = (finalUrl.indexOf('?') >= 0) ? '&' : '?';
                    finalUrl = `${finalUrl}${sep}token=${encodeURIComponent(token)}`;
                }
            } catch (_) {}
        }
        newWindow(finalUrl, `app_${app.id}`, app.im, app.tt);
    }


    // Handshake fallback: respond with auth to embedded apps requesting it
    try {
        window.addEventListener('message', (ev) => {
            const data = ev?.data || {};
            if (!data || typeof data !== 'object') return;
            if (data.type === 'workz-sdk:init') {
                const jwt = localStorage.getItem('jwt_token') || null;
                // Deriva contexto atual
                let ctx = null;
                if (viewType === ENTITY.BUSINESS && viewId) ctx = { type: 'business', id: viewId };
                else if (viewType === ENTITY.TEAM && viewId) ctx = { type: 'team', id: viewId };
                else if (currentUserData?.id) ctx = { type: 'user', id: currentUserData.id };
                try {
                    ev.source?.postMessage({ type: 'workz-sdk:auth', jwt, user: currentUserData || null, context: ctx }, '*');
                } catch (_) {}
            }
        }, false);
    } catch (_) {}

    function initAppLibrary(root = '#app-library', appsData = []) {
        const el = typeof root === 'string' ? document.querySelector(root) : root;
        if (!el) return;
        // Mapa rápido id->dados
        const appsById = new Map();
        try {
            (Array.isArray(appsData) ? appsData : []).forEach(a => { if (a && a.id != null) appsById.set(Number(a.id), a); });
        } catch (_) {}

        // Evita handlers duplicados ao reinicializar a biblioteca de apps
        if (el._appClickHandler) {
            try { el.removeEventListener('click', el._appClickHandler); } catch (_) {}
        }

        // Combined click listener for opening apps
        const clickHandler = async (event) => {
            const storeBtn = event.target.closest('[data-store]');
            if (storeBtn) {
                // Abre a loja (sem SSO - usa handshake embed para obter JWT)
                try { await launchAppBySlug('store', { sso: false }); } catch (_) {}
                return;
            }
            // Primeiro, checa clique no botão de favorito para não abrir o app
            // sem gatilhos de favoritos na grade
            const appButton = event.target.closest('[data-app-id]');
            if (appButton) {
                const appId = Number(appButton.dataset.appId);
                if (!appId) return;
                try { await launchAppById(appId); } catch (_) {}
                return;
            }
        };
        el.addEventListener('click', clickHandler);
        el._appClickHandler = clickHandler;

        // Search + Hide favorites filter
        const searchInput = el.querySelector('#app-search-input');
        const appGrid = el.querySelector('#app-grid');

        const HIDE_FAV_KEY = 'workz.apps.hideFavorites';
        const getHideFav = () => { try { return localStorage.getItem(HIDE_FAV_KEY) === '1'; } catch (_) { return false; } };

        async function applyAppGridFilters() {
            if (!appGrid) return;
            const searchTerm = (searchInput?.value || '').toLowerCase().trim();
            const hideFav = getHideFav();
            let favIds = [];
            try { favIds = await fetchQuickFromServer(); } catch (_) { favIds = quickCache || []; }
            const favSet = new Set((favIds || []).map(n => Number(n)));
            const appItems = appGrid.querySelectorAll('.app-item-button');
            appItems.forEach(item => {
                const appName = (item.dataset.appName || '').toLowerCase();
                const id = Number(item.dataset.appId);
                const matchesSearch = appName.includes(searchTerm);
                const isFav = favSet.has(id);
                const shouldHide = !matchesSearch || (hideFav && isFav);
                item.style.display = shouldHide ? 'none' : '';
            });
        }

        if (searchInput && appGrid) {
            if (searchInput._appSearchHandler) {
                try { searchInput.removeEventListener('input', searchInput._appSearchHandler); } catch (_) {}
            }
            const inputHandler = () => { applyAppGridFilters(); };
            searchInput.addEventListener('input', inputHandler);
            searchInput._appSearchHandler = inputHandler;
        }

        // Expor para que outras views possam reaplicar o filtro
        try { el._applyAppGridFilters = applyAppGridFilters; } catch (_) {}

        // ===== Quick access (Favoritos) – sincronizado com backend =====
        const track = el.querySelector('#quickbar-track');
        let quickCache = [];
        let quickFetchCooldownUntil = 0;
        async function fetchQuickFromServer() {
            try {
                if (Date.now() < quickFetchCooldownUntil) return quickCache || [];
                const res = await apiClient.post('/search', {
                    db: 'workz_apps',
                    table: 'quickapps',
                    columns: ['ap'],
                    conditions: { us: currentUserData.id },
                    order: { by: 'sort', dir: 'ASC' },
                    fetchAll: true
                });
                const ids = Array.isArray(res?.data) ? res.data.map(r => Number(r.ap)).filter(n => Number.isFinite(n)) : [];
                quickCache = ids;
            } catch (_) {
                quickCache = quickCache || [];
                // backoff de 30s para evitar flood de erros 500
                quickFetchCooldownUntil = Date.now() + 30000;
            }
            return quickCache;
        }
        async function addQuickOnServer(id) {
            try { await apiClient.post('/insert', { db: 'workz_apps', table: 'quickapps', data: { us: currentUserData.id, ap: id } }); } catch (_) {}
        }
        async function removeQuickOnServer(id) {
            try { await apiClient.post('/delete', { db: 'workz_apps', table: 'quickapps', conditions: { us: currentUserData.id, ap: id } }); } catch (_) {}
        }
        // Fallback localStorage para cenários offline/erro 500
        function getLocalQuick(){ try { return JSON.parse(localStorage.getItem('workz.quickApps')||'[]'); } catch(_){ return []; } }
        function setLocalQuick(arr){ try { localStorage.setItem('workz.quickApps', JSON.stringify(arr||[])); } catch(_){ } }

        async function toggleQuickApp(id) {
            const ids = new Set(await fetchQuickFromServer());
            if (ids.has(id)) {
                ids.delete(id);
                try { await removeQuickOnServer(id); }
                catch (_) { setLocalQuick([...ids]); }
            } else {
                ids.add(id);
                try { await addQuickOnServer(id); }
                catch (_) { setLocalQuick([...ids]); }
            }
            quickCache = [...ids];
            // Atualiza UI dependente (quickbar, estrelas e filtro da grade)
            try { await renderQuickBar(); } catch (_) {}
            try { await refreshQuickStars(); } catch (_) {}
            try { applyAppGridFilters && applyAppGridFilters(); } catch (_) {}
            return quickCache;
        }
        async function renderQuickBar() {
            if (!track) return;
            const ids = await fetchQuickFromServer();
            // Envolve os itens em um contêiner centrado e com largura do conteúdo
            let html = '<div class="flex items-center gap-3 w-max mx-auto">';
            // Loja (sempre presente)
            html += `
                <button data-store="1" class="shrink-0 snap-start" title="Loja de aplicativos">
                    <div class="w-14 h-14 rounded-full overflow-hidden shadow-md">
                        <img src="/images/apps/store.jpg" alt="Loja" class="app-icon-image rounded-full" />
                    </div>
                </button>`;
            ids.forEach(id => {
                const app = appsById.get(Number(id));
                if (!app) return;
                const img = resolveImageSrc(app?.im, app?.tt, { fallbackUrl: '/images/app-default.png', size: 120 });
                const name = app?.tt || 'App';
                html += `
                    <div class="relative shrink-0 snap-start" data-quick-id="${app.id}">
                        <button data-app-id="${app.id}" title="${name}">
                            <div class="w-14 h-14 rounded-full overflow-hidden shadow-md">
                                <img src="${img}" alt="${name}" class="app-icon-image rounded-full" />
                            </div>
                        </button>
                    </div>`;
            });
            html += '</div>';
            track.innerHTML = html;
        }

        async function refreshQuickStars() {
            // Atualiza o estado visual das estrelas nos cards
            const ids = new Set(await fetchQuickFromServer());
            el.querySelectorAll('[data-quick-toggle]').forEach(span => {
                const id = Number(span.getAttribute('data-quick-toggle'));
                const icon = span.querySelector('i');
                if (ids.has(id)) {
                    span.style.opacity = '1';
                    span.title = 'Remover do acesso rápido';
                    if (icon) icon.className = 'fa-solid fa-star text-[12px]';
                } else {
                    span.style.opacity = '';
                    span.title = 'Adicionar aos favoritos';
                    if (icon) icon.className = 'fa-regular fa-star text-[12px]';
                }
            });
        }

        // Sem remoção pela quickbar: manter apenas abertura de apps

        // Inicializa barra e estado das estrelas
        fetchQuickFromServer().then(() => { renderQuickBar(); refreshQuickStars(); try { applyAppGridFilters && applyAppGridFilters(); } catch (_) {} });
    }

    // Abre a sidebar diretamente na view "Aplicativos"
    async function openAppsSidebar() {
        const mock = document.createElement('div');
        mock.dataset.sidebarAction = 'settings';
        await toggleSidebar(mock, true);
        try {
            const mount = document.querySelector('.sidebar-content');
            SidebarNav.setMount(mount);
            SidebarNav.resetRoot(currentUserData);
            setTimeout(() => {
                try { SidebarNav.push({ view: 'apps', title: 'Aplicativos', payload: { data: currentUserData } }); } catch (_) {}
            }, 60);
        } catch (_) {}
    }

    function resetFeed() {
        // Desconectar observer antigo do feed, se houver
        try { if (feedObserver) { feedObserver.disconnect(); feedObserver = null; } } catch (_) {}
        feedOffset = 0;
        feedLoading = false;
        feedFinished = false;
        feedInteractionsAttached = false;

        // Limpar o conteúdo do timeline
        const timeline = document.querySelector('#timeline');
        if (timeline) {
            timeline.innerHTML = '';
        }
    }

    async function loadFeed() {
        if (!viewType) return;

        if (feedLoading || feedFinished) return;
        // Se a view está restrita (por feed_privacy/page_privacy), não carregar feed
        if ((viewType === ENTITY.PROFILE || viewType === ENTITY.BUSINESS || viewType === ENTITY.TEAM) && viewRestricted) {
            feedFinished = true;
            feedLoading = false;
            return;
        }
        feedLoading = true;

        const orBlocks = [];

        if (viewType === 'dashboard') {
            // Copia segura + sem duplicatas
            const basePeople = Array.isArray(userPeople) ? userPeople : [];
            const baseBiz = Array.isArray(userBusinesses) ? userBusinesses : [];
            const baseTeams = Array.isArray(userTeams) ? userTeams : [];

            const followedIds = [...new Set([...basePeople, currentUserData.id])];

            if (followedIds.length) orBlocks.push({ us: { op: 'IN', value: followedIds } });
            if (baseBiz.length) orBlocks.push({ em: { op: 'IN', value: baseBiz } });
            if (baseTeams.length) orBlocks.push({ cm: { op: 'IN', value: baseTeams } });

        } else if (viewType === ENTITY.PROFILE) {
            orBlocks.push({ us: viewId, cm: 0, em: 0 });
        } else if (viewType === ENTITY.BUSINESS) {
            orBlocks.push({ em: viewId });
        } else if (viewType === ENTITY.TEAM) {
            orBlocks.push({ cm: viewId });
        } else if (viewType === 'public') {
            orBlocks.push({ cm: 0, em: 0 });
        } else {
            return;
        }

        if (!orBlocks.length) {
            document.querySelector('#main-feed').innerHTML = `
            <div class="rounded-3xl w-full p-3 flex items-center gap-2" style="background:#F7F8D1;">
                <i class="fas fa-info-circle"></i>
                <span>Você ainda não segue ninguém.</span>
            </div>`;
            feedFinished = true;
            feedLoading = false;
            return;
        }

        const feedPayload = {
            db: 'workz_data',
            table: 'hpl',
            columns: ['id', 'us', 'em', 'cm', 'tt', 'ct', 'dt', 'im'],
            conditions: {
                st: 1,                // AND st = 1
                _or: orBlocks         // AND ( us IN (...) OR em IN (...) OR cm IN (...) )
            },
            order: { by: 'dt', dir: 'DESC' },
            fetchAll: true,
            limit: FEED_PAGE_SIZE,
            offset: feedOffset
        };
        // Visitante (sem login): só exibe posts pessoais de autores com página e conteúdo públicos
        if (viewType === 'public') {
            feedPayload.exists = [{
                table: 'hus',
                local: 'us',
                remote: 'id',
                conditions: { st: 1, feed_privacy: 3, page_privacy: 1 }
            }];
        }
        const res = await apiClient.post('/search', feedPayload);

        let items = res?.data || [];

        // se não veio nada, acabou
        if (!items.length) {
            feedFinished = true;
            feedLoading = false;
            return;
        }

        // Ajusta itens conforme privacidade no dashboard
        if (viewType === 'dashboard') {
            try { items = await filterDashboardItems(items); } catch (_) {}
        }

        // se após filtro não restou nada
        if (!items.length) {
            feedFinished = true;
            feedLoading = false;
            return;
        }

        // renderizar (append)
        let feedItems = items;
        try {
            feedItems = await hydrateFeedItems(items);
        } catch (error) {
            console.error('Failed to prepare feed items', error);
        }
        appendFeed(feedItems);


        // avançar offset
        feedOffset += FEED_PAGE_SIZE;
        feedLoading = false;

    }

    function initFeedInfiniteScroll() {
        const sentinel = document.querySelector('#feed-sentinel');
        if (!sentinel) return;

        // Evita acumular múltiplos observers
        try { if (feedObserver) { feedObserver.disconnect(); feedObserver = null; } } catch (_) {}
        feedObserver = new IntersectionObserver((entries) => {
            const [entry] = entries;
            if (entry.isIntersecting) {
                loadFeed();
            }
        }, { rootMargin: '200px' }); // começa a carregar antes de encostar

        try { feedObserver.observe(sentinel); } catch (_) {}
    }

    async function initializeCurrentUserData() {
        try {
            const userData = await apiClient.get('/me');
            if (userData.error) {
                handleLogout();
                return false;
            }
            currentUserData = userData;
            // Tornar disponível globalmente para o editor
            window.currentUserData = currentUserData;
            return true;
        } catch (error) {
            console.error('Failed to initialize user data:', error);
            handleLogout();
            return false;
        }
    }

    async function handleUpdate(e) {
        e.preventDefault();
        const formEl = e.target;
        const view = formEl.dataset.view;
        const form = new FormData(formEl);
        const rawData = Object.fromEntries(form.entries());
        const messageContainer = document.getElementById('message');

        if (view === ENTITY.PROFILE) {
            await handleProfileUpdate({ formEl, rawData, messageContainer });
            return;
        }

        if (view === ENTITY.BUSINESS) {
            await handleBusinessUpdate({ formEl, rawData, messageContainer });
            return;
        }

        if (view === ENTITY.TEAM) {
            await handleTeamUpdate({ formEl, rawData, messageContainer });
            return;
        }

        if (rawData.phone) rawData.phone = onlyNumbers(rawData.phone);
        if (rawData.national_id) rawData.national_id = onlyNumbers(rawData.national_id);

        const changedData = getChangedFields(rawData, currentUserData);

        if (Object.keys(changedData).length === 0) {
            renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        if (changedData.tt !== undefined && changedData.tt.trim() === '') {
            renderTemplate(messageContainer, templates.message, { message: 'Nome é obrigatório.', type: 'error' });
            return;
        }

        if (changedData.ml !== undefined && changedData.ml.trim() === '') {
            renderTemplate(messageContainer, templates.message, { message: 'E-mail é obrigatório.', type: 'error' });
            return;
        }

        if (changedData.ml !== undefined) {
            const result = await apiClient.post('/change-email', {
                userId: currentUserData.id,
                newEmail: changedData.ml
            });
            if (result) {
                if (result.status === 'success') {
                    renderTemplate(messageContainer, templates.message, { message: 'Um pedido de confirmação foi encaminhado ao novo endereço de e-mail.', type: 'warning' });
                } else {
                    renderTemplate(messageContainer, templates.message, { message: result.message || 'Ocorreu um erro.', type: 'error' });
                }
            } else {
                renderTemplate(messageContainer, templates.message, { message: 'Ocorreu um erro ao alterar o e-mail.', type: 'error' });
            }
            delete changedData.ml;
            if (Object.keys(changedData).length === 0) return;
        }

        const entityType = (view === ENTITY.BUSINESS) ? 'businesses'
            : (view === ENTITY.TEAM) ? 'teams'
                : 'people';

        if (rawData.id) {
            const result = await apiClient.post('/update', {
                db: typeMap[entityType].db,
                table: typeMap[entityType].table,
                data: changedData,
                conditions: { id: rawData.id }
            });
            if (result) {
                await initializeCurrentUserData();
                renderTemplate(messageContainer, templates.message, { message: 'Dados atualizados com sucesso!', type: 'success' });
                if (viewType === view) loadPage();
            } else {
                console.error('Falha na atualização.');
            }
        }
    }

    async function handleProfileUpdate({ formEl, rawData, messageContainer }) {
        const userId = rawData.id || currentUserData.id;
        const baseContacts = normalizeContacts(currentUserData?.contacts);
        const newContacts = collectContactsFromForm(formEl);

        const profileNew = {
            tt: (rawData.tt || '').trim(),
            ml: (rawData.ml || '').trim(),
            cf: rawData.cf ?? '',
            un: (rawData.un || '').trim(),
            page_privacy: normalizeNumber(rawData.page_privacy),
            feed_privacy: normalizeNumber(rawData.feed_privacy),
            gender: normalizeStringOrNull(rawData.gender),
            birth: normalizeStringOrNull(rawData.birth),
            contacts: newContacts.length ? JSON.stringify(newContacts) : null
        };

        const profileBaseline = {
            tt: (currentUserData?.tt || '').trim(),
            ml: (currentUserData?.ml || '').trim(),
            cf: currentUserData?.cf ?? '',
            un: (currentUserData?.un || '').trim(),
            page_privacy: normalizeNumber(currentUserData?.page_privacy),
            feed_privacy: normalizeNumber(currentUserData?.feed_privacy),
            gender: normalizeStringOrNull(currentUserData?.gender),
            birth: normalizeBirth(currentUserData?.birth),
            contacts: baseContacts.length ? JSON.stringify(baseContacts) : null
        };

        const changed = {};
        for (const key of Object.keys(profileNew)) {
            if (profileNew[key] !== profileBaseline[key]) {
                changed[key] = profileNew[key];
            }
        }

        if (Object.keys(changed).length === 0) {
            renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        if (changed.tt !== undefined && changed.tt.trim() === '') {
            renderTemplate(messageContainer, templates.message, { message: 'Nome é obrigatório.', type: 'error' });
            return;
        }

        let emailChange = null;
        if (changed.ml !== undefined) {
            if (changed.ml.trim() === '') {
                renderTemplate(messageContainer, templates.message, { message: 'E-mail é obrigatório.', type: 'error' });
                return;
            }
            emailChange = changed.ml;
            delete changed.ml;
        }

        if (changed.un !== undefined) {
            const available = await ensureNicknameAvailable({
                nickname: profileNew.un,
                entityType: ENTITY.PROFILE,
                entityId: userId,
                messageContainer
            });
            if (!available) return;
        }

        if (emailChange) {
            const result = await apiClient.post('/change-email', {
                userId,
                newEmail: emailChange
            });
            if (result) {
                if (result.status === 'success') {
                    renderTemplate(messageContainer, templates.message, { message: 'Um pedido de confirmação foi encaminhado ao novo endereço de e-mail.', type: 'warning' });
                } else {
                    renderTemplate(messageContainer, templates.message, { message: result.message || 'Ocorreu um erro.', type: 'error' });
                    return;
                }
            } else {
                renderTemplate(messageContainer, templates.message, { message: 'Ocorreu um erro ao alterar o e-mail.', type: 'error' });
                return;
            }
        }

        if (Object.keys(changed).length === 0) {
            await initializeCurrentUserData();
            if (!emailChange) renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        const result = await apiClient.post('/update', {
            db: 'workz_data',
            table: 'hus',
            data: changed,
            conditions: { id: userId }
        });

        if (result) {
            await initializeCurrentUserData();
            renderTemplate(messageContainer, templates.message, { message: 'Dados atualizados com sucesso!', type: 'success' });
            if (viewType === ENTITY.PROFILE) loadPage();
        } else {
            console.error('Falha na atualização do perfil.');
            renderTemplate(messageContainer, templates.message, { message: 'Não foi possível salvar as alterações.', type: 'error' });
        }
    }

    async function handleBusinessUpdate({ formEl, rawData, messageContainer }) {
        const businessId = rawData.id;
        if (!businessId) {
            console.error('Business ID não informado.');
            renderTemplate(messageContainer, templates.message, { message: 'Não foi possível identificar o negócio.', type: 'error' });
            return;
        }

        const contacts = collectContactsFromForm(formEl);
        const baselineData = getEntityBaseline(ENTITY.BUSINESS, businessId) || {};
        const newModel = buildBusinessModel(rawData, contacts);
        const baselineModel = buildBusinessModel(baselineData, extractContactsData(baselineData));

        const changed = diffModels(newModel, baselineModel);

        if (!Object.keys(changed).length) {
            renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        if (!newModel.tt) {
            renderTemplate(messageContainer, templates.message, { message: 'Nome é obrigatório.', type: 'error' });
            return;
        }

        if (changed.un !== undefined) {
            const available = await ensureNicknameAvailable({
                nickname: newModel.un,
                entityType: ENTITY.BUSINESS,
                entityId: businessId,
                messageContainer
            });
            if (!available) return;
        }

        const result = await apiClient.post('/update', {
            db: 'workz_companies',
            table: 'companies',
            data: changed,
            conditions: { id: businessId }
        });

        if (result) {
            const updated = await refreshEntityState(ENTITY.BUSINESS, businessId);
            renderTemplate(messageContainer, templates.message, { message: 'Dados atualizados com sucesso!', type: 'success' });
            if (!updated) return;
        } else {
            console.error('Falha na atualização do negócio.');
            renderTemplate(messageContainer, templates.message, { message: 'Não foi possível salvar as alterações.', type: 'error' });
        }
    }

    async function handleTeamUpdate({ formEl, rawData, messageContainer }) {
        const teamId = rawData.id;
        if (!teamId) {
            console.error('Team ID não informado.');
            renderTemplate(messageContainer, templates.message, { message: 'Não foi possível identificar a equipe.', type: 'error' });
            return;
        }

        const contacts = collectContactsFromForm(formEl);
        const baselineData = getEntityBaseline(ENTITY.TEAM, teamId) || {};
        const newModel = buildTeamModel(rawData, contacts);
        const baselineModel = buildTeamModel(baselineData, extractContactsData(baselineData));

        const changed = diffModels(newModel, baselineModel);

        if (!Object.keys(changed).length) {
            renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        if (!newModel.tt) {
            renderTemplate(messageContainer, templates.message, { message: 'Nome é obrigatório.', type: 'error' });
            return;
        }

        if (changed.un !== undefined) {
            const available = await ensureNicknameAvailable({
                nickname: newModel.un,
                entityType: ENTITY.TEAM,
                entityId: teamId,
                messageContainer
            });
            if (!available) return;
        }

        const result = await apiClient.post('/update', {
            db: 'workz_companies',
            table: 'teams',
            data: changed,
            conditions: { id: teamId }
        });

        if (result) {
            const updated = await refreshEntityState(ENTITY.TEAM, teamId);
            renderTemplate(messageContainer, templates.message, { message: 'Dados atualizados com sucesso!', type: 'success' });
            if (!updated) return;
        } else {
            console.error('Falha na atualização da equipe.');
            renderTemplate(messageContainer, templates.message, { message: 'Não foi possível salvar as alterações.', type: 'error' });
        }
    }

    function normalizeNumber(value) {
        if (value === undefined || value === null || value === '') return null;
        const num = Number(value);
        return Number.isNaN(num) ? null : num;
    }

    function normalizeStringOrNull(value) {
        if (value === undefined || value === null) return null;
        const trimmed = String(value).trim();
        return trimmed === '' ? null : trimmed;
    }

    function normalizeBirth(value) {
        if (!value) return null;
        try {
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return null;
            return date.toISOString().split('T')[0];
        } catch (_) {
            return null;
        }
    }

    function normalizeRequiredString(value) {
        return String(value ?? '').trim();
    }

    function sanitizeDocument(value) {
        const normalized = normalizeStringOrNull(value);
        if (!normalized) return null;
        const digits = onlyNumbers(normalized);
        return digits || null;
    }

    function extractContactsData(source) {
        if (!source) return [];
        if (Array.isArray(source)) return canonicalizeContacts(source);
        if (typeof source === 'string') {
            const trimmed = source.trim();
            if (!trimmed) return [];
            if (trimmed.startsWith('[')) {
                return normalizeContacts(trimmed);
            }
            return canonicalizeContacts([{ type: '', value: trimmed }]);
        }
        if (typeof source === 'object') {
            if (source.contacts !== undefined) return extractContactsData(source.contacts);
            if (source.url !== undefined) return extractContactsData(source.url);
        }
        return [];
    }

    function buildBusinessModel(source = {}, contactsList = []) {
        const normalizedContacts = canonicalizeContacts(contactsList);
        return {
            tt: normalizeRequiredString(source.tt ?? source.name),
            national_id: sanitizeDocument(source.national_id ?? source.cnpj),
            cf: normalizeStringOrNull(source.cf),
            un: normalizeStringOrNull(source.un),
            page_privacy: normalizeNumber(source.page_privacy ?? source.page_privacy),
            feed_privacy: normalizeNumber(source.feed_privacy ?? source.feed_privacy),
            zip_code: normalizeStringOrNull(source.zip_code ?? source.zp),
            country: normalizeStringOrNull(source.country),
            state: normalizeStringOrNull(source.state),
            city: normalizeStringOrNull(source.city),
            district: normalizeStringOrNull(source.district),
            address: normalizeStringOrNull(source.address),
            complement: normalizeStringOrNull(source.complement),
            contacts: normalizedContacts.length ? JSON.stringify(normalizedContacts) : null
        };
    }

    function buildTeamModel(source = {}, contactsList = []) {
        const normalizedContacts = canonicalizeContacts(contactsList);
        return {
            tt: normalizeRequiredString(source.tt ?? source.name),
            cf: normalizeStringOrNull(source.cf),
            un: normalizeStringOrNull(source.un),
            feed_privacy: normalizeNumber(source.feed_privacy ?? source.feed_privacy),
            em: normalizeNumber(source.em ?? source.business),
            contacts: normalizedContacts.length ? JSON.stringify(normalizedContacts) : null
        };
    }

    function diffModels(current = {}, baseline = {}) {
        const diff = {};
        const keys = new Set([...Object.keys(current), ...Object.keys(baseline)]);
        keys.forEach((key) => {
            const currentValue = current[key] ?? null;
            const baselineValue = baseline[key] ?? null;
            if (currentValue !== baselineValue) {
                diff[key] = currentValue;
            }
        });
        return diff;
    }

    function getEntityBaseline(view, entityId) {
        const idStr = String(entityId ?? '');
        if (!idStr) return null;

        if (typeof SidebarNav !== 'undefined' && Array.isArray(SidebarNav.stack)) {
            for (let i = SidebarNav.stack.length - 1; i >= 0; i--) {
                const state = SidebarNav.stack[i];
                const payloadData = state?.payload?.data;
                if (payloadData && String(payloadData.id ?? '') === idStr) {
                    return payloadData;
                }
            }
        }

        if (viewType === view && viewData && String(viewData.id ?? '') === idStr) {
            return viewData;
        }

        if (view === ENTITY.PROFILE && currentUserData && String(currentUserData.id ?? '') === idStr) {
            return currentUserData;
        }

        return null;
    }

    async function refreshEntityState(view, entityId) {
        const idStr = String(entityId ?? '');
        if (!idStr) return null;

        let dbCfg = null;
        if (view === ENTITY.BUSINESS) dbCfg = { db: 'workz_companies', table: 'companies' };
        else if (view === ENTITY.TEAM) dbCfg = { db: 'workz_companies', table: 'teams' };
        else if (view === ENTITY.PROFILE) dbCfg = { db: 'workz_data', table: 'hus' };
        if (!dbCfg) return null;

        try {
            const res = await apiClient.post('/search', {
                db: dbCfg.db,
                table: dbCfg.table,
                columns: ['*'],
                conditions: { id: entityId },
                fetchAll: true,
                limit: 1
            });
            const updated = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
            if (updated) updateSidebarStateData(view, entityId, updated);
            return updated;
        } catch (error) {
            console.error('[refreshEntityState] erro', error);
            return null;
        }
    }

    function updateSidebarStateData(view, entityId, updated) {
        if (!updated) return;
        const idStr = String(entityId ?? updated.id ?? '');
        if (!idStr) return;

        if (typeof SidebarNav !== 'undefined' && Array.isArray(SidebarNav.stack)) {
            SidebarNav.stack.forEach((state) => {
                if (state?.payload?.data && String(state.payload.data.id ?? '') === idStr) {
                    state.payload = { ...(state.payload || {}), data: updated };
                }
            });
        }

        if (viewType === view && viewData && String(viewData.id ?? '') === idStr) {
            viewData = updated;
        }

        if (view === ENTITY.PROFILE && currentUserData && String(currentUserData.id ?? '') === idStr) {
            currentUserData = { ...currentUserData, ...updated };
        }
    }

    function collectContactsFromForm(formEl) {
        const container = formEl.querySelector('#input-container');
        if (!container) return [];
        const rows = [...container.querySelectorAll('[data-input-id]')];
        const entries = rows.map((row) => {
            const type = row.querySelector('input[name="url_type"]')?.value || '';
            const value = row.querySelector('input[name="url_value"]')?.value || '';
            return { type: type.trim(), value: value.trim() };
        }).filter(item => item.type && item.value);
        return canonicalizeContacts(entries);
    }

    function normalizeContacts(raw) {
        if (!raw) return [];
        let source = raw;
        if (typeof raw === 'string') {
            try { source = JSON.parse(raw); }
            catch (_) { return []; }
        }
        if (!Array.isArray(source)) return [];
        return canonicalizeContacts(source);
    }

    function canonicalizeContacts(list) {
        if (!Array.isArray(list)) return [];
        return list
            .map((item) => {
                const type = normalizeStringOrNull(item?.type ?? item?.url_type);
                const value = normalizeStringOrNull(item?.value ?? item?.url_value);
                if (!type || !value) return null;
                return { type, value };
            })
            .filter(Boolean);
    }

    async function ensureNicknameAvailable({ nickname, entityType, entityId, messageContainer }) {
        const value = normalizeStringOrNull(nickname);
        if (!value) return true;

        const available = await isNicknameAvailable(value, entityType, entityId);
        if (!available) {
            renderTemplate(messageContainer, templates.message, { message: 'Esse apelido já está sendo utilizado. Escolha outro.', type: 'error' });
            return false;
        }
        return true;
    }

    async function isNicknameAvailable(nickname, entityType, entityId) {
        const value = normalizeStringOrNull(nickname);
        if (!value) return true;

        const checks = [
            { entity: ENTITY.PROFILE, db: 'workz_data', table: 'hus' },
            { entity: ENTITY.BUSINESS, db: 'workz_companies', table: 'companies' },
            { entity: ENTITY.TEAM, db: 'workz_companies', table: 'teams' }
        ];

        const idStr = String(entityId ?? '');
        const requests = checks.map(({ db, table }) => apiClient.post('/search', {
            db,
            table,
            columns: ['id', 'un'],
            conditions: { un: value },
            fetchAll: true,
            limit: 5
        }).catch(() => null));

        const responses = await Promise.all(requests);

        for (let i = 0; i < checks.length; i++) {
            const { entity } = checks[i];
            const res = responses[i];
            const rows = Array.isArray(res?.data) ? res.data : res?.data ? [res.data] : [];
            if (!rows.length) continue;

            const conflict = rows.find((row) => {
                if (!row) return false;
                const rowId = String(row.id ?? '');
                if (!rowId) return true;
                if (entity === entityType && rowId === idStr) return false;
                return true;
            });

            if (conflict) return false;
        }

        return true;
    }

    function getChangedFields(newData, oldData) {
        const changed = {};
        for (const [key, value] of Object.entries(newData)) {
            const oldValue = oldData[key] ?? null;
            if (value !== oldValue && key !== 'id') {
                changed[key] = value;
            }
        }
        return changed;
    }

    async function handleLogin(event) {
        event.preventDefault();
        const loginForm = event.target;
        const messageContainer = loginForm.previousElementSibling?.id === 'message'
            ? loginForm.previousElementSibling
            : document.getElementById('message');

        const email = (loginForm.email.value || '').trim();
        const password = loginForm.password.value || '';

        if (!email || !password) {
            await showMessage(messageContainer, 'Informe e-mail e senha para continuar.', 'error', { dismissAfter: 6000 });
            if (!email) loginForm.email.focus();
            else loginForm.password.focus();
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            await showMessage(messageContainer, 'Informe um e-mail válido.', 'error', { dismissAfter: 6000 });
            loginForm.email.focus();
            return;
        }

        let data = { email, password };

        const submitBtn = loginForm.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true, 'Entrando...');

        try {
            const result = await apiClient.post('/login', data);
            if (result?.token) {
                localStorage.setItem('jwt_token', result.token);
                showLoading();
                installSidebarClickShield();
    startup();
            } else {
                await showMessage(messageContainer, result?.error || 'Credenciais inválidas. Verifique seus dados.', 'error', { dismissAfter: 6000 });
            }
        } catch (error) {
            console.error('[login] error', error);
            await showMessage(messageContainer, 'Não foi possível fazer login agora. Tente novamente.', 'error', { dismissAfter: 6000 });
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    async function handleRegister(event) {
        event.preventDefault();
        const registerForm = event.target;
        const messageContainer = registerForm.previousElementSibling?.id === 'message'
            ? registerForm.previousElementSibling
            : document.getElementById('message');

        const formValues = {
            name: (registerForm.name.value || '').trim(),
            email: (registerForm.email.value || '').trim(),
            password: registerForm.password.value || '',
            repeat: registerForm['password-repeat'].value || ''
        };

        if (!formValues.name) {
            await showMessage(messageContainer, 'Informe seu nome completo.', 'error', { dismissAfter: 6000 });
            registerForm.name.focus();
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formValues.email)) {
            await showMessage(messageContainer, 'Informe um e-mail válido.', 'error', { dismissAfter: 6000 });
            registerForm.email.focus();
            return;
        }

        if (!passwordMeetsRules(formValues.password)) {
            await showMessage(
                messageContainer,
                'A senha deve ter pelo menos 8 caracteres, incluindo letras maiúsculas, minúsculas, números e um caractere especial.',
                'error',
                { dismissAfter: 7000 }
            );
            registerForm.password.focus();
            return;
        }

        if (formValues.password !== formValues.repeat) {
            await showMessage(messageContainer, 'As senhas não coincidem.', 'error', { dismissAfter: 6000 });
            registerForm['password-repeat'].focus();
            return;
        }

        const submitBtn = registerForm.querySelector('button[type="submit"]');
        setButtonLoading(submitBtn, true, 'Criando conta...');

        try {
            const result = await apiClient.post('/register', {
                name: formValues.name,
                email: formValues.email,
                password: formValues.password,
                'password-repeat': formValues.repeat
            });

            if (result?.token) {
                localStorage.setItem('jwt_token', result.token);
                showLoading();
                installSidebarClickShield();
    startup();
                return;
            }

            const message = result?.error || result?.message || 'Não foi possível criar a conta. Tente novamente.';
            await showMessage(messageContainer, message, 'error', { dismissAfter: 6000 });
        } catch (error) {
            console.error('[register] error', error);
            await showMessage(messageContainer, 'Não foi possível criar a conta no momento. Tente novamente.', 'error', { dismissAfter: 6000 });
        } finally {
            setButtonLoading(submitBtn, false);
        }
    }

    function handleLogout() {
        localStorage.removeItem('jwt_token');
        window.location.href = '/';
    }

    // ===================================================================
    // 🔐 REGISTER / LOGIN
    // ===================================================================

    async function renderRegisterUI() {
        const mainWrapperInit = document.getElementById('main-wrapper-init');
        await renderTemplate(mainWrapperInit, 'register', null, () => {
            // Adiciona os listeners aos elementos de registo
            const registerForm = document.getElementById('register-form');
            if (registerForm) {
                const submitHandler = (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (typeof ev.stopImmediatePropagation === 'function') {
                        ev.stopImmediatePropagation();
                    }
                    handleRegister(ev);
                };
                registerForm.addEventListener('submit', submitHandler, true);
                registerForm.onsubmit = submitHandler;
                registerForm.dataset.jsVersion = '2024-02-15-1';
            }
            document.getElementById('show-login-link').addEventListener('click', (e) => {
                e.preventDefault();
                renderLoginUI();
            });
        });
    }

    async function renderLoginUI() {
        const mainWrapperInit = document.getElementById('main-wrapper-init');
        await renderTemplate(mainWrapperInit, 'login', null, () => {
            // Adiciona os listeners APENAS depois de renderizar a UI de login.
            const loginForm = document.getElementById('login-form');
            if (loginForm) {
                const submitHandler = (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (typeof ev.stopImmediatePropagation === 'function') {
                        ev.stopImmediatePropagation();
                    }
                    handleLogin(ev);
                };
                loginForm.addEventListener('submit', submitHandler, true);
                loginForm.onsubmit = submitHandler;
                loginForm.dataset.jsVersion = '2024-02-15-1';
            }
            document.getElementById('google-login-btn').addEventListener('click', () => window.location.href = '/api/auth/google/redirect');
            document.getElementById('microsoft-login-btn').addEventListener('click', () => window.location.href = '/api/auth/microsoft/redirect');
            document.getElementById('show-register-link').addEventListener('click', (e) => {
                e.preventDefault();
                renderRegisterUI();
            });
            hideLoading({ delay: 250 });
        });
    }

    // ===================================================================
    // 🔄 RENDERIZAÇÃO DA INTERFACE
    // ===================================================================


    // =====================================================================
    // 9. STARTUP FLOW & EVENT BINDINGS
    // =====================================================================

    function startClock() {
        const clock = document.querySelector('#wClock');
        if (!clock) {
            return; // Stop if clock element is not on the page
        }
        const today = new Date();
        let h = today.getHours();
        let m = today.getMinutes();
        h = checkTime(h);
        m = checkTime(m);

        clock.innerHTML = h + ":" + m;

        setTimeout(startClock, 1000);
    }

    function checkTime(i) {
        return (i < 10 ? "0" : "") + i;
    }

    function topBarScroll() {

        const topBar = document.querySelector('#topbar');
        const logo = document.querySelector('.logo-menu');

        mainWrapper.scroll(function () {
            if (mainWrapper.scrollTop() > 17.5) {
                topBar.addClass('shadow-3xl bg-gray-100');
                logo.attr('src', '/images/logos/workz/90x47.png');
                logo.css({
                    'width': '90px',
                    'height': '47px',
                    'transition': 'width 0.5s, height 0.5s'
                });
            } else {
                topBar.removeClass('shadow-3xl bg-gray-100');
                logo.attr('src', '/images/logos/workz/145x76.png');
                logo.css({
                    'width': '145px',
                    'height': '76px',
                    'transition': 'width 0.5s, height 0.5s'
                });

            }
        });
    }

    installSidebarClickShield();
    startup();

    setupSidebarFormConfirmation();



    document.addEventListener('submit', (event) => {
        if (!event.target?.matches?.('#change-password-form')) return;
        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
        handleChangePassword(event);
    }, true);

    document.addEventListener('click', function (event) {
        const target = event.target;
        const activeSwal = document.querySelector('.swal-overlay:not(.swal-hidden)');
        if (activeSwal && activeSwal.contains(target)) {
            return;
        }
        const isSidebarOpen = !sidebarWrapper.classList.contains('w-0');
        const clickedInsideSidebar = !!(sidebarWrapper && sidebarWrapper.contains(target));
        const clickedSidebarTrigger = !!target.closest('#sidebarTrigger');

        const actionBtn = target.closest('[data-sidebar-action]');
        const actionType = actionBtn?.dataset?.sidebarAction;
        const actionInsideSidebar = actionBtn ? sidebarWrapper.contains(actionBtn) : false;
        if (actionType === 'sidebar-back') {
            // Deixa o handler de histórico cuidar; não limpar/trocar o sidebar aqui
            event.preventDefault();
            try { window.history.back(); } catch(_) {}
            return;
        }
        if (actionType === 'stack-back') {
            event.preventDefault();
            if (typeof SidebarNav !== 'undefined') SidebarNav.back();
            return;
        }
        if (actionBtn && !isSidebarOpen) {
            event.preventDefault();
            toggleSidebar(actionBtn);
            return;
        } else if (actionBtn && isSidebarOpen && actionBtn.id !== 'close') {
            // Se o botão pertence ao conteúdo da sidebar, deixe os handlers internos cuidarem
            if (actionInsideSidebar) {
                return;
            }
            event.preventDefault();
            toggleSidebar(actionBtn, false);
            return;
        } else if (actionBtn && isSidebarOpen && actionBtn.id === 'close') {
            toggleSidebar();
        }

        // Clique dentro da sidebar nunca deve fechá-la
        if (clickedInsideSidebar) {
            return;
        }
        if (isSidebarOpen && !clickedSidebarTrigger) {
            toggleSidebar(); // fecha
        }

        // Fechar menus de post ao clicar fora do trigger/menu
        const clickedPostMenu = !!target.closest('[data-role="post-menu"]');
        const clickedPostMenuTrigger = !!target.closest('[data-feed-action="open-post-menu"]');
        if (!clickedPostMenu && !clickedPostMenuTrigger) {
            document.querySelectorAll('[data-role="post-menu"]').forEach(menu => menu.classList.add('hidden'));
        }
    });

    // Fecha menus de post com tecla Escape
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape') {
            document.querySelectorAll('[data-role="post-menu"]').forEach(menu => menu.classList.add('hidden'));
        }
    });

    // Removido: fallback global. A navegação do sidebar é controlada por SidebarNav.

    // Opções das máscaras
    const phoneMaskOptions = {
        // duas máscaras: 8 ou 9 dígitos
        mask: [
            { mask: '(00) 0000-0000' },
            { mask: '(00) 00000-0000' },
            { mask: '+00 (00) 0000-0000' },
            { mask: '+00 (00) 00000-0000' },
        ]
    };

    const cpfMaskOptions = {
        mask: [
            { mask: '000.000.000-00' },
            { mask: '00.000.000/0000-00' }
        ]
    };

    const cepMaskOptions = {
        mask: '00000-000'
    };

    // Função segura para aplicar IMask
    function applyMask(id, options) {
        const el = document.getElementById(id);
        if (!el) {
            return null;
        }
        if (el.dataset.maskInitialized === '1') {
            return el._imaskInstance || null;
        }
        const instance = IMask(el, options);
        el.dataset.maskInitialized = '1';
        el._imaskInstance = instance;
        return instance;
    }

    function initMasks() {
        applyMask('phone', phoneMaskOptions);
        applyMask('cpf', cpfMaskOptions);
        applyMask('zip_code', cepMaskOptions);
    }

    function passwordMeetsRules(password) {
        if (!password) return false;
        return password.length >= 8
            && /[a-z]/.test(password)
            && /[A-Z]/.test(password)
            && /\d/.test(password)
            && /[@$!%*?&.#]/.test(password);
    }

    async function handleChangePassword(event) {
        event.preventDefault();
        event.stopPropagation();

        const form = event.target;
        const getField = (name) => form.querySelector(`[name="${name}"]`);
        const fields = {
            current: getField('current_password'),
            next: getField('new_password'),
            repeat: getField('new_password_repeat'),
            id: getField('id')
        };

        const values = {
            current: (fields.current?.value ?? '').trim(),
            next: (fields.next?.value ?? '').trim(),
            repeat: (fields.repeat?.value ?? '').trim(),
            id: fields.id?.value ?? ''
        };


        let messageContainer = form.previousElementSibling;
        if (!messageContainer?.matches?.('[data-role="message"]')) {
            messageContainer = form.parentElement?.querySelector('[data-role="message"]') ?? null;
        }

        if (!messageContainer) {
            console.error('[password] message container not found for change-password form');
            return;
        }

        const missingField = Object.entries({
            'Senha atual': values.current,
            'Nova senha': values.next,
            'Confirmação da nova senha': values.repeat
        }).find(([, value]) => !value);

        if (missingField) {
            await showMessage(messageContainer, `Preencha o campo "${missingField[0]}" antes de continuar.`, 'error', { dismissAfter: 6000 });
            const key = missingField[0] === 'Senha atual' ? 'current' : missingField[0] === 'Nova senha' ? 'next' : 'repeat';
            fields[key]?.focus?.();
            return;
        }

        if (values.next === values.current) {
            await showMessage(messageContainer, 'A nova senha deve ser diferente da senha atual.', 'error', { dismissAfter: 6000 });
            fields.next?.focus?.();
            return;
        }

        if (values.next !== values.repeat) {
            await showMessage(messageContainer, 'As senhas não coincidem.', 'error', { dismissAfter: 6000 });
            fields.repeat?.focus?.();
            return;
        }

        if (!passwordMeetsRules(values.next)) {
            await showMessage(messageContainer, 'A nova senha deve ter pelo menos 8 caracteres, incluir letras maiúsculas e minúsculas, números e um caractere especial.', 'error', { dismissAfter: 7000 });
            fields.next?.focus?.();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        setButtonLoading(submitButton, true, 'Alterando...');

        try {
            const payload = {
                userId: values.id,
                currentPassword: values.current,
                newPassword: values.next
            };

            const result = await apiClient.post('/change-password', payload);

            if (result?.status === 'success') {
                await showMessage(messageContainer, result.message || 'Senha alterada com sucesso!', 'success', { dismissAfter: 4000 });
                form.reset();
            } else {
                const errorMessage = result?.message || result?.error || 'Falha ao alterar a senha. Verifique os dados informados.';
                await showMessage(messageContainer, errorMessage, 'error', { dismissAfter: 6000 });
            }
        } catch (error) {
            console.error('[password] change error', error);
            await showMessage(messageContainer, 'Não foi possível alterar a senha no momento. Tente novamente.', 'error', { dismissAfter: 6000 });
        } finally {
            setButtonLoading(submitButton, false);
        }
    }

    function setupSidebarFormConfirmation() {
        if (!sidebarWrapper || typeof confirmDialog !== 'function') return;

        const confirmationText = 'Você tem certeza de que deseja continuar?';

        sidebarWrapper.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            if (form.dataset.skipConfirm === '1') {
                delete form.dataset.skipConfirm;
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }

            const confirmed = await confirmDialog(confirmationText, { title: 'Confirmar ação', danger: true });
            if (!confirmed) return;

            form.dataset.skipConfirm = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }, true);
    }

    function onlyNumbers(str) {
        return str.replace(/\D/g, ''); // remove tudo que não for número
    }

});
