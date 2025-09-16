// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {    

    const mainWrapper = document.querySelector("#main-wrapper"); //Main Wrapper
    const sidebarWrapper = document.querySelector('#sidebar-wrapper');

    let workzContent = '';

    const apiClient = new ApiClient();    

    // Variáveis Globais do Usuário
    let currentUserData = null;
    let userPeople = [];
    let userBusinesses = []; //Ids de empresa
    let userTeams = [];
    
    let userBusinessesData = [];// Condições do usuário nas empresas
    let userTeamsData = []; // Condições do usuário nas equipes

    let businessesJobs = {};

    let memberStatus = null; // Status do usuário em páginas de negócio e de equipe
    let memberLevel = null; // Nível do usuário em páginas de negócio e de equipe
    let viewRestricted = false; // Restrição de acesso (ex.: equipe fora de negócios do usuário)

    // Variáveis Globais da Página
    let viewType = null;
    let viewId = null;
    let viewData = null;

    // Variáveis Globais do Feed       
    const FEED_PAGE_SIZE = 6;
    let feedOffset = 0;    
    let feedLoading = false;
    let feedFinished = false;

    // Navegação estilo iOS para o sidebar (stack)
    const SidebarNav = {
        stack: [],
        mount: null,
        setMount(el){ this.mount = el; },
        current(){ return this.stack[this.stack.length-1]; },
        prev(){ return this.stack[this.stack.length-2]; },
        resetRoot(data){ this.stack = [{ view: 'root', title: 'Ajustes', payload: { data }, type: 'root' }]; this.render(); },
        push(state){ this.stack.push(state); this.render(); },
        back(){ if (this.stack.length>1){ this.stack.pop(); this.render(); } else { this.resetRoot(currentUserData); } },
        async render(){
            if (!this.mount) return;
            const st = this.current();
            const isRoot = (st.view === 'root');
            const payload = { ...(st.payload||{}), view: (isRoot ? null : st.view), origin: 'stack', navTitle: st.title, prevTitle: (this.prev()?.title || 'Ajustes') };
            this.mount.dataset.navMode = 'stack';
            this.mount.dataset.currentView = st.view || 'root';
            await renderTemplate(this.mount, templates.sidebarPageSettings, payload, () => {
                // Root: handler único para main menu
                if (isRoot) {
                    if (this.mount._rootHandler) this.mount.removeEventListener('click', this.mount._rootHandler);
                    const rootHandler = (e) => {
                        const it = e.target.closest('#people,#businesses,#teams,#desktop,#apps,#logout');
                        if (!it || !this.mount.contains(it)) return;
                        const id = it.id;
                        if (id === 'desktop') { navigateTo('/'); return; }
                        if (id === 'logout') { handleLogout(); return; }
                        const titleMap = { people:'Pessoas', businesses:'Negócios', teams:'Equipes', apps:'Aplicativos' };
                        this.push({ view: id, title: titleMap[id] || 'Ajustes', payload: { data: currentUserData } });
                    };
                    this.mount.addEventListener('click', rootHandler);
                    this.mount._rootHandler = rootHandler;
                }

                // Delegação para abrir itens de listas (negócios/equipes)
                if (this.mount._listHandler) this.mount.removeEventListener('click', this.mount._listHandler);
                const listHandler = async (e) => {
                    // Pessoas/Negócios/Equipes (data-id) e Aplicativos (data-app-id)
                    const appRow = e.target.closest('[data-app-id]');
                    if (appRow && this.mount.contains(appRow) && this.mount.dataset.currentView === 'apps') {
                        const ap = appRow.dataset.appId;
                        const res = await apiClient.post('/search', { db:'workz_apps', table:'apps', columns:['*'], conditions:{ id: ap } });
                        const app = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (app) this.push({ view: 'app-settings', title: app.tt || 'Aplicativo', payload: { data: app, appId: ap } });
                        return;
                    }
                    const row = e.target.closest('[data-id]');
                    if (!row || !this.mount.contains(row)) return;
                    const id = row.dataset.id;
                    if (!id) return;
                    if (this.mount.dataset.currentView === 'businesses') {
                        const res = await apiClient.post('/search', { db:'workz_companies', table:'companies', columns:['*'], conditions:{ id } });
                        const data = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (data) this.push({ view: ENTITY.BUSINESS, title: data.tt || 'Negócio', payload: { data, type:'business' } });
                    } else if (this.mount.dataset.currentView === 'teams') {
                        const res = await apiClient.post('/search', { db:'workz_companies', table:'teams', columns:['*'], conditions:{ id } });
                        const data = Array.isArray(res?.data) ? res.data[0] : res?.data || null;
                        if (data) this.push({ view: ENTITY.TEAM, title: data.tt || 'Equipe', payload: { data, type:'team' } });
                    } else if (this.mount.dataset.currentView === 'people') {
                        // Abrir o perfil do usuário (visualização pública), não ajustes
                        navigateTo(`/profile/${id}`);
                        try { await toggleSidebar(); } catch(_) {}
                    }
                };
                this.mount.addEventListener('click', listHandler);
                this.mount._listHandler = listHandler;

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
            });
        }
    };

    // Constantes de entidade para padronização
    const ENTITY = Object.freeze({
        PROFILE: 'profile',
        BUSINESS: 'business',
        TEAM: 'team'
    });

   // ===================================================================
    // 🏳️ TEMPLATES - Partes do HTML a ser renderizado
    // ===================================================================

    const templates = {

        init: `            
            <div id="main-wrapper-init" class="w-full">
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

        message: (data) => {
            const { message, type = 'success' } = data;
            if (type === 'success') {
                return `<div class="bg-green-100 border border-green-400 rounded-3xl p-3 text-sm text-center">${message}</div>`;
            } else if (type === 'error') {
                return `<div class="bg-red-100 border border-red-400 rounded-3xl p-3 text-sm text-center">${message}</div>`;
            }else if (type === 'warning') {
                return `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">${message}</div>`;
            }
            return '';
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
                <div class="max-w-screen-xl mx-auto p-7 px-3 xl:px-0 flex items-center justify-between">
                    <a href="/">
                        <!--img class="logo-menu" style="width: 145px; height: 76px;" title="Workz!" src="/images/logos/workz/145x76.png"-->
                    </a>
                    <button id="sidebarTrigger" data-sidebar-action="settings"><img class="page-thumb h-11 w-11 shadow-lg object-cover rounded-full" src="/images/no-image.jpg" /></button>
                </div>
            </div>                                         
            <div id="workz-content" class="mt-[132px] max-w-screen-xl px-3 xl:px-0 mx-auto clearfix grid grid-cols-12 gap-6">                               
            </div>        
        `,

        workzContent: `
            <div class="col-span-12 sm:col-span-8 lg:col-span-9 flex flex-col grid grid-cols-12 gap-x-6">
                <!-- Coluna da Esquerda (Menu de Navegação) -->
                <aside class="w-full flex col-span-4 lg:col-span-3 flex flex-col gap-y-6">                        
                    <div class="aspect-square w-full rounded-full shadow-lg overflow-hidden">
                        <img id="profile-image" class="w-full h-full object-cover" src="/images/no-image.jpg" alt="Imagem da página">
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
                <main class="col-span-8 lg:col-span-9 flex-col relative space-y-6">                        
                    <div id="main-content" class="w-full"></div>
                    <div id="editor-trigger" class="shadow-lg w-full bg-white rounded-3xl text-center"></div>
                </main>
                <!-- Feed de Publicações -->
                <div id="timeline" class="col-span-12 flex flex-col grid grid-cols-12 gap-6 pt-6"></div>
                <div id="feed-sentinel" class="h-10"></div>
            </div>
            <aside id="widget-wrapper" class="col-span-12 sm:col-span-4 lg:col-span-3 flex flex-col gap-y-6">                    
            </aside>        
        `,

        mainContent: `
            <div class="w-full grid grid-cols-12 gap-6 rounded-3xl p-6" style="background-image: url(https://bing.biturl.top/?resolution=1366&amp;format=image&amp;index=0&amp;mkt=en-US); background-position: center; background-repeat: no-repeat; background-size: cover;">
                <div class="col-span-12 lg:col-span-8 grid grid-cols-12 gap-4">
                    <div class="col-span-12 text-white font-bold content-center text-shadow-lg flex items-center justify-between">
                        <div id="wClock" class="text-md">00:00</div>
                        <div id="wSearch" class="p-1"><i class="fas fa-search"></i></div>
                    </div>
                    <div id="app-library" class="col-span-12"></div> 
                </div>
                <div class="shadow-lg rounded-3xl hidden lg:block col-span-4 lg:h-full bg-white/50 backdrop-blur p-3">
                    <p class="truncate">Terça-feira, 12 de agosto</p>
                </div>
            </div>
        `,

        editorTrigger: (currentUserData) => `
            <div class="w-full p-3 border-b-2 border-gray-100 flex items-center gap-3">
                <img class="page-thumb w-11 h-11 rounded-full pointer" src="/images/no-image.jpg" />
                <div id="pageConfig" class="flex-1 rounded-3xl h-11 pointer text-gray-500 px-4 text-left bg-gray-100 hover:bg-gray-200 flex items-center overflow-hidden whitespace-nowrap truncate">
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
                <img id="sidebar-profile-image" class="sm:w-1/4 md:w-1/6 lg:w-1/6 shadow-lg cursor-pointer rounded-full mx-auto" src="https://placehold.co/100x100/EFEFEF/333?text=${businessData.name.charAt(0)}" alt="Foto do Utilizador">
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

        listView: (listItems) => {
            if (!Array.isArray(listItems) || listItems.length === 0) {
                return `<div class="col-span-12"><div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Nenhum item encontrado.</div></div>`;
            }
            let html = '<div class="col-span-12 flex flex-col grid grid-cols-12 gap-6">';        
            listItems.forEach(item => {
                const name = (item.tt || '');
                html += `
                <div class="list-item sm:col-span-12 md:col-span-6 lg:col-span-4 flex flex-col bg-white p-3 rounded-3xl shadow-lg bg-gray hover:bg-gray-100 cursor-pointer" data-item-id="${item.id}" data-name="${name.toLowerCase()}">
                    <div class="flex items-center gap-3">
                        <img class="w-10 h-10 rounded-full" src="https://placehold.co/40x40/EFEFEF/333?text=${name.charAt(0)}" alt="${name}">
                        <span class="font-semibold truncate">${name}</span>
                    </div>                    
                </div> 
                `;
            });
            html += '</div>';
            return html;
        }
    }

    // Removido: lógica antiga baseada em sidebarHistory/sidebar-back
    templates.entityContent = async ({ data }) => {        
        const content = `
            <div class="rounded-3xl w-full p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1),0_-4px_6px_-2px_rgba(0,0,0,0.05)]">
                <h2 class="text-2xl font-semibold">${data.tt}</h2>
                <div class="grid grid-cols-3 flex-wrap gap-4 mt-6 mb-6">
                    <div class="col-span-1 flex items-center text-center justify-center">
                        <p><small class="text-gray-500">Publicações</small><br>${data.postsCount}</p>
                    </div>
                    <div class="col-span-1 flex items-center text-center justify-center">                        
				        <p><small class="text-gray-500">Seguidores</small><br><span id="followers-count">${data.followersCount}</span></p>
                    </div>
                    <div class="col-span-1 flex items-center text-center justify-center">
                        <p><small class="text-gray-500">Seguindo</small><br>${data.peopleCount}</p>
                    </div>
                </div>
                ${data.cf}
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

    templates.appLibrary = async ({ appsList }) => {
        const resolved = await fetchByIds(appsList, 'apps'); // cada app: { id, tt, ic } etc.
        const pageSize = 8; // 4 col x 2 linhas

        const pages = [];
        for (let i = 0; i < resolved.length; i += pageSize) {
            pages.push(resolved.slice(i, i + pageSize));
        }

        const pageSlides = pages.map(page => `
            <div class="min-w-full grid grid-cols-3 grid-rows-3 sm:grid-cols-4 sm:grid-rows-2 gap-4 p-4">
            ${page.map(app => `                
                <button data-app-id="${app.id}" class="flex flex-col items-center gap-1">
                    <div class="relative rounded-full overflow-hidden bg-gray-300 aspect-square w-full shadow-lg">
                        <div class="absolute inset-0 bg-center bg-cover" style="background-image:url('${'data:image/png;base64,' + app.im || '/images/app-default.png'}');"></div>
                    </div>
                    <span class="text-xs text-white text-shadow-lg truncate w-full text-center">${app.tt || 'App'}</span>
                </button>
            `).join('')}
            </div>
        `).join('');

        const dots = pages.map((_, i) =>
            `<button data-idx="${i}" class="w-2 h-2 rounded-full ${i===0?'bg-white':'bg-gray-300'}"></button>`
        ).join('');        
        
        // container raiz com track deslizante
        return `
            <div id="app-carousel" class="relative select-none">
                <div class="overflow-hidden rounded-3xl bg-white/50 backdrop-blur">
                    <div class="flex transition-transform duration-300 will-change-transform" data-role="track" style="transform:translateX(0%)">
                    ${pageSlides}
                    </div>
                    <div class="flex justify-center gap-2 3 mb-4" data-role="dots">
                        ${dots}
                    </div>
                </div>            
            </div>
        `;        
    };

    // Classes padronizadas para itens de menu/botões
    const CLASSNAMES = {
        menuItem: 'cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center',
        actionBtn: 'cursor-pointer text-center rounded-3xl text-white transition-colors truncate w-full p-2 mb-1'
    };

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
        renderHero: ({ tt, im }) => `
            <div class="col-span-1 justify-center">
                <img id="sidebar-profile-image" class="w-1/3 shadow-lg cursor-pointer rounded-full mx-auto" src="${im ? `data:image/png;base64,${im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(tt||'?').charAt(0)}`}" alt="${tt ?? 'Imagem'}">
            </div>
        `,
        sectionCard: (content, { roundedTop=true, roundedBottom=true } = {}) => `
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
        actionButton: ({ action, label, color='blue', extra='' }) => `
            <button data-action="${action}" class="${CLASSNAMES.actionBtn} bg-${color}-400 hover:bg-${color}-600 ${extra}"><span class="truncate">${label}</span></button>
        `,
        row: (id, label, inputHtml, { top=false, bottom=false } = {}) => `
            <div class="grid grid-cols-4 border-b border-gray-200 ${top ? 'rounded-t-2xl' : ''} ${bottom ? 'rounded-b-2xl' : ''}">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <div class="col-span-3 p-4">
                ${inputHtml}
                </div>
            </div>
        `,
        rowTextarea: (id, label, value='') => `
            <div class="grid grid-cols-4">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <textarea id="${id}" name="${id}" class="border-0 focus:outline-none col-span-3 p-4 min-h-[120px] rounded-r-2xl">${value ?? ''}</textarea>
            </div>
        `,
        rowSelect: (id, label, optionsHtml, { top=false, bottom=false } = {}) => `
            <div class="grid grid-cols-4 border-b border-gray-200 ${top ? 'rounded-t-2xl' : ''} ${bottom ? 'rounded-b-2xl' : ''}">
                <label for="${id}" class="col-span-1 p-4 truncate text-gray-500">${label}</label>
                <select id="${id}" name="${id}" class="border-0 focus:outline-none col-span-3 p-4">
                ${optionsHtml}
                </select>
            </div>
        `,
        contactOptions: () => `
            <option value="" class="text-gray-500" disabled selected>Contato</option>
            <option value="email">E-mail</option>
            <option value="phone">Telefone</option>
            <option value="site">Site</option>
            <option value="behance">Behance</option>
            <option value="discord">Discord</option>
            <option value="facebook">Facebook</option>
            <option value="flickr">Flickr</option>
            <option value="instagram">Instagram</option>
            <option value="linkedin">LinkedIn</option>
            <option value="pinterest">Pinterest</option>
            <option value="reddit">Reddit</option>
            <option value="snapchat">Snapchat</option>
            <option value="tiktok">TikTok</option>
            <option value="tumblr">Tumblr</option>
            <option value="twitch">Twitch</option>
            <option value="twitter">X / Twitter</option>
            <option value="vimeo">Vimeo</option>
            <option value="wechat">WeChat</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="youtube">YouTube</option>
            <option value="other">Outro</option>
        `,
        contactBlock: (value='') => `
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            <div id="input-container" class="rounded-t-2xl w-full">
                <div title="Contato" class="rounded-t-2xl bg-white grid grid-cols-6" data-input-id="0">
                    <select class="rounded-tl-2xl border-0 focus:outline-none col-span-2 p-4" id="url_type" name="url_type">
                        ${UI.contactOptions()}
                    </select>
                    <input class="border-0 focus:outline-none col-span-4 rounded-tr-2xl p-4" type="text" id="url_value" name="url_value" value="${value ?? ''}">
                </div>
            </div>
            <div id="addButtonContainer" class="grid grid-cols-2 rounded-b-2xl border-t border-gray-200 bg-white">
                <div id="add-input-button" class="col-span-1 p-3 bg-gray-100 hover:bg-gray-200 cursor-pointer text-center rounded-bl-2xl"><i class="fas fa-plus centered"></i></div>
                <div id="remove-input-button" class="col-span-1 p-3 bg-gray-100 hover:bg-gray-200 cursor-pointer text-center rounded-br-2xl"><i class="fas fa-minus centered"></i></div>
            </div>
        </div>
        `,
        privacyRowsProfile: ({ page_privacy, feed_privacy }) => {
            const pageOpts = `
            <option value="" ${page_privacy==null?'selected':''} disabled>Selecione</option>
            <option value="0" ${page_privacy===0?'selected':''}>Usuários logados</option>
            <option value="1" ${page_privacy===1?'selected':''}>Toda a internet</option>
            `;
            const feedOpts = `
            <option value="" ${feed_privacy==null?'selected':''} disabled>Selecione</option>
            <option value="0" ${feed_privacy===0?'selected':''}>Moderadores</option>
            <option value="1" ${feed_privacy===1?'selected':''}>Usuários membros</option>
            <option value="2" ${feed_privacy===2?'selected':''}>Usuários logados</option>
            <option value="3" ${feed_privacy===3?'selected':''}>Toda a internet</option>
            `;
            return UI.sectionCard(
                UI.rowSelect('page_privacy', 'Página', pageOpts, { top:true }) +
                UI.rowSelect('feed_privacy', 'Conteúdo', feedOpts, { bottom:true })
            );
        },
        shortcutItem: (id, icon, label, color = 'gray', { roundedTop=false, roundedBottom=false } = {}) => `
        <div id="${id}" title="${label}" class="${roundedTop?'rounded-t-2xl':''} ${roundedBottom?'rounded-b-2xl':'border-b'} bg-${ (color !== 'gray') ? color + '-200' : 'white' } text-${color}-700 p-3 cursor-pointer hover:bg-${ (color !== 'gray') ? color + '-300' : 'white/50' } transition-all duration-300 ease-in-out">
            <span class="fa-stack">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas ${icon} fa-stack-1x fa-inverse"></i>
            </span>
            ${label}
        </div>
        `,
        shortcutList: (items=[]) => `
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            ${items.map((it, i) => UI.shortcutItem(it.id, it.icon, it.label, it.color, {
                roundedTop: i===0,
                roundedBottom: i===items.length-1                
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

    templates.sidebarPageSettings = async ({ view = null, data = null, type = null, origin = null, prevTitle = null, navTitle = null }) => {
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
            'apps': 'Aplicativos'
        };

        const headerBackLabel = (view !== null) ? (([ENTITY.PROFILE, ENTITY.BUSINESS, ENTITY.TEAM].includes(view)) ? 'Ajustes' : (view.startsWith('user-') ? (currentUserData?.tt ?? '') : (data?.tt ?? ''))) : 'Fechar';

        const financeShortcuts = UI.shortcutList([
            { id: 'billing',      icon: 'fa-money-bill',     label: 'Cobrança e Recebimento' },
            { id: 'transactions', icon: 'fa-receipt',        label: 'Transações' },
            { id: 'subscriptions',icon: 'fa-satellite-dish', label: 'Assinaturas' }
        ]);

        // Cabeçalho e navegação com suporte a stack
        const isStack = (origin === 'stack');
        if ([ENTITY.PROFILE, ENTITY.BUSINESS, ENTITY.TEAM].includes(view)) {
            html += `
                ${UI.renderHeader({ backAction: (isStack ? 'stack-back' : 'settings'), backLabel: (isStack ? (prevTitle || 'Ajustes') : 'Ajustes'), title: (data?.tt ?? '') })}
                ${UI.renderHero({ tt: (data?.tt ?? ''), im: data?.im })}
                <div id="message" class="w-full fixed"></div>
            `;
            // Marcar tipo/identificador da entidade atual no dataset para navegação interna
            sidebarContent.dataset.sidebarType = (view === ENTITY.PROFILE && data?.id === currentUserData?.id) ? 'current-user' : view;
            if (data?.id) {
                sidebarContent.dataset.entityType = view;
                sidebarContent.dataset.entityId = String(data.id);
            }
        } else if (view === null) {
            html += UI.renderCloseHeader();
        } else {
            const topShortcuts = ['people','businesses','teams','apps'];
            const isTopShortcut = topShortcuts.includes(view);
            const backAction = isStack ? 'stack-back' : (isTopShortcut ? 'settings' : 'page-settings');
            const backLabel = isStack ? (prevTitle || 'Ajustes') : (isTopShortcut ? 'Ajustes' : (data?.tt ?? 'Ajustes'));
            html += UI.renderHeader({ backAction, backLabel, title: titles[view] ?? '' });
        }

        // VIEWS
        if (view === null) {
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
                <div data-sidebar-type="current-user" class="pointer w-full bg-white shadow-md rounded-3xl p-3 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-profile-link">
                    <div data-sidebar-action="page-settings" class="grid grid-cols-4 items-center gap-3">
                        <div class="flex col-span-1 justify-center">
                            <img id="sidebar-profile-image" class="w-full rounded-full" src="https://placehold.co/100x100/EFEFEF/333?text=${data.tt.charAt(0)}" alt="Foto do Utilizador">
                        </div>
                        <div class="flex col-span-3 flex-col gap-1">
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

            const card1 = UI.sectionCard(
                UI.row('name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, {top:true}) +
                UI.row('email','E-mail*', `<input class="w-full border-0 focus:outline-none" type="email" id="email" name="ml" value="${data.ml}" ${(data.provider ? '' : 'disabled')} required>`, {bottom:true})
            );

            const cardAbout = UI.sectionCard(UI.rowTextarea('cf', 'Sobre', data.cf));

            const cardUserMeta = UI.sectionCard(
                UI.row('username','Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, {top:true}) +
                UI.rowSelect('page_privacy', 'Página', `
                <option value="" ${currentUserData.page_privacy==null?'selected':''} disabled>Selecione</option>
                <option value="0" ${currentUserData.page_privacy===0?'selected':''}>Usuários logados</option>
                <option value="1" ${currentUserData.page_privacy===1?'selected':''}>Toda a internet</option>
                `) +
                UI.rowSelect('feed_privacy', 'Conteúdo', `
                <option value="" ${currentUserData.feed_privacy==null?'selected':''} disabled>Selecione</option>
                <option value="0" ${currentUserData.feed_privacy===0?'selected':''}>Moderadores</option>
                <option value="1" ${currentUserData.feed_privacy===1?'selected':''}>Usuários membros</option>
                <option value="2" ${currentUserData.feed_privacy===2?'selected':''}>Usuários logados</option>
                <option value="3" ${currentUserData.feed_privacy===3 && currentUserData.page_privacy>0?'selected':''} ${currentUserData.page_privacy<1?'disabled':''}>Toda a internet</option>
                `, {bottom:true})
            );

            const cardPersonal = UI.sectionCard(
                UI.rowSelect('gender','Gênero', `
                <option value="" ${(!['male','female'].includes(currentUserData.gender))?'selected':''} disabled>Selecione</option>
                <option value="male" ${currentUserData.gender==='male'?'selected':''}>Masculino</option>
                <option value="female" ${currentUserData.gender==='female'?'selected':''}>Feminino</option>
                `, {top:true}) +
                UI.row('birth','Nascimento', `<input class="w-full border-0 focus:outline-none" type="date" id="birth" name="birth" value="${(currentUserData.birth)? new Date(currentUserData.birth).toISOString().split('T')[0] : ''}">`) +
                UI.row('cpf','CPF', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="999.999.999-99" id="cpf" name="national_id" value="${currentUserData.national_id ?? ''}">`, {bottom:true})
            );

            const contacts = UI.contactBlock(data.url ?? '');

            const shortcuts = UI.shortcutList([
                { id:'user-education', icon:'fa-graduation-cap', label:'Formação Acadêmica' },
                { id:'user-jobs', icon:'fa-user-tie', label:'Experiência Profissional' },
                { id:'testimonials', icon:'fa-scroll', label:'Depoimentos' },
            ]);

            const userChoices = UI.shortcutList([
                { id: 'password', icon: 'fa-key', label: 'Alterar Senha' },
                { id: 'delete-account', icon: 'fa-times', label: 'Excluir Conta', color: 'red' }
            ]);

            html += `
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
        } else if (view === ENTITY.BUSINESS) {
            sidebarContent.id = 'business';

            const basics = UI.sectionCard(
                UI.row('name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, {top:true}) +
                UI.row('cnpj','CNPJ', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="99.999.999/9999-99" id="cnpj" name="cnpj" value="${data.national_id ?? ''}">`, {bottom:true})
            );

            const about = UI.sectionCard(UI.rowTextarea('cf','Sobre', data.cf));

            const privacy = UI.sectionCard(
                UI.row('username','Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, {top:true}) +
                UI.rowSelect('page_privacy','Página', `
                <option value="" ${data.pg==null?'selected':''} disabled>Selecione</option>
                <option value="0" ${data.pg===0?'selected':''}>Usuários logados</option>
                <option value="1" ${data.pg===1?'selected':''}>Toda a internet</option>
                `) +
                UI.rowSelect('feed_privacy','Conteúdo', `
                <option value="" ${data.pc==null?'selected':''} disabled>Selecione</option>
                <option value="0" ${data.pc===0?'selected':''}>Moderadores</option>
                <option value="1" ${data.pc===1?'selected':''}>Usuários membros</option>
                <option value="2" ${data.pc===2?'selected':''}>Usuários logados</option>
                <option value="3" ${data.pc===3 && (data.pg>0)?'selected':''} ${data.pg<1?'disabled':''}>Toda a internet</option>
                `, {bottom:true})
            );

            const address = UI.sectionCard(
                UI.row('zip_code','CEP', `<input class="w-full border-0 focus:outline-none" type="text" placeholder="99999-999" id="zip_code" name="zip_code" value="${data?.zip_code ?? ''}">`, {top:true}) +
                UI.rowSelect('country','País', `<option value="" disabled ${!data?.country?'selected':''}>Selecione</option>`) +
                UI.row('state','Estado', `<input class="w-full border-0 focus:outline-none" type="text" id="state" name="state" value="${data?.state ?? ''}">`) +
                UI.row('city','Cidade', `<input class="w-full border-0 focus:outline-none" type="text" id="city" name="city" value="${data?.city ?? ''}">`) +
                UI.row('district','Bairro', `<input class="w-full border-0 focus:outline-none" type="text" id="district" name="district" value="${data?.district ?? ''}">`) +
                UI.row('address','Endereço', `<input class="w-full border-0 focus:outline-none" type="text" id="address" name="address" value="${data?.address ?? ''}">`) +
                UI.row('complement','Complemento', `<input class="w-full border-0 focus:outline-none" type="text" id="complement" name="complement" value="${data?.complement ?? ''}">`, {bottom:true})
            );

            const contacts = UI.contactBlock(data.url ?? '');

            const shortcuts = UI.shortcutList([
                // { id:'business-shareholding', icon:'fa-sitemap', label:'Estrutura Societária' },
                { id:'employees', icon:'fa-id-badge', label:'Colaboradores' },
                { id:'testimonials', icon:'fa-scroll', label:'Depoimentos' },
            ]);

            
            html += `
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
            // exemplo: reuso igual ao business/profile para campos
            // (mantém tua lógica de buscar businesses; só exibindo com os helpers)
            let mappedBusinesses = await Promise.all(userBusinesses.map(async (business) => {
                const b = await fetchByIds(business, 'businesses');
                return `<option value="${business}" ${(data.em===business)?'selected':''}>${b.tt}</option>`;
            }));

            const basics = UI.sectionCard(
                UI.row('name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="name" name="tt" value="${data.tt}" required>`, {top:true}) +
                UI.rowSelect('business','Negócio', `
                <option value="" ${data.em==null?'selected':''} disabled>Selecione</option>
                ${mappedBusinesses.join('')}
                `, {bottom:true})
            );

            const about = UI.sectionCard(UI.rowTextarea('cf','Sobre', data.cf));

            const feedPrivacy = UI.sectionCard(
                UI.rowSelect('feed_privacy','Conteúdo', `
                <option value="" ${data.pc==null?'selected':''} disabled>Selecione</option>
                <option value="0" ${data.pc===0?'selected':''}>Moderadores</option>
                <option value="1" ${data.pc===1?'selected':''}>Membros da equipe</option>
                <option value="2" ${data.pc===2?'selected':''}>Todos do negócio</option>
                `, {bottom:true})
            );

            const contacts = UI.contactBlock(data.url ?? '');

            const shortcuts = UI.shortcutList([
                { id:'employees', icon:'fa-id-badge', label:'Colaboradores' },
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
                <form id="settings-form" data-view="${view}" class="grid grid-cols-1 gap-6">
                <input type="hidden" name="id" value="${data.id}">
                ${basics}
                ${about}
                ${UI.sectionCard(UI.row('username','Apelido', `<input class="w-full border-0 focus:outline-none" type="text" id="username" name="un" value="${data.un ?? ''}">`, {top:true}))}
                ${feedPrivacy}
                ${contacts}
                <button type="submit" class="shadow-md w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
                </form>
                ${shortcuts}
                ${deleteTeamButton}
            `;
        } else if (view === 'employees') {
            const table = (type === 'business') ? 'employees' : 'teams_users';
            const idKey = (type === 'business') ? 'em' : 'cm';
            const conditions = { [idKey]: data.id };
            const employees = await apiClient.post('/search', { db: 'workz_companies', table, columns: ['us', 'nv', 'st'], conditions, exists: [{ db: 'workz_data', table: 'hus', local: 'us', remote: 'id', conditions: { st: 1 }}], fetchAll: true});
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
                return `<select name=\"nv\" class=\"border-0 focus:outline-none\">${options.map(o => `<option value=\"${o.v}\" ${Number(current)===o.v?'selected':''}>${o.t}</option>`).join('')}</select>`;
            };

            const activeRows = active.length
                ? active.map(e => {
                    const p = userMap.get(e.us) || { id: e.us, tt: 'Usuário' };
                    if (canManage) {
                        const controls = `
                            <div class=\"flex gap-2 items-center\">
                                ${levelSelect(e.nv ?? 1)}
                                <button data-action=\"update-member-level\" data-user-id=\"${p.id}\" data-scope-type=\"${type}\" data-scope-id=\"${data.id}\" class=\"p-2 bg-blue-100 hover:bg-blue-200 text-blue-800 rounded-md\">Atualizar</button>
                            </div>`;
                        return UI.row(`member-${p.id}`, p.tt, controls);
                    }
                    return UI.row(`member-${p.id}`, p.tt, `<span class=\"text-gray-500\">Nível: ${Number(e.nv ?? 1)}</span>`);
                }).join('')
                : `<div class=\"p-3 text-sm text-gray-500\">Nenhum membro ativo.</div>`;

            const pendingRows = (pending.length && canManage)
                ? pending.map(e => {
                    const p = userMap.get(e.us) || { id: e.us, tt: 'Usuário' };
                    const controls = `
                        <div class=\"flex gap-2\">
                            <button data-action=\"accept-member\" data-user-id=\"${p.id}\" data-scope-type=\"${type}\" data-scope-id=\"${data.id}\" class=\"p-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-md\">Aceitar</button>
                            <button data-action=\"reject-member\" data-user-id=\"${p.id}\" data-scope-type=\"${type}\" data-scope-id=\"${data.id}\" class=\"p-2 bg-red-100 hover:bg-red-200 text-red-800 rounded-md\">Recusar</button>
                        </div>`;
                    return UI.row(`pending-${p.id}`, p.tt, controls);
                }).join('')
                : `<div class=\"p-3 text-sm text-gray-500\">Sem solicitações pendentes.</div>`;

            html += `
                ${UI.sectionCard(`<div class=\"p-3 font-semibold\">Membros Ativos</div>` + activeRows)}
                ${UI.sectionCard(`<div class=\"p-3 font-semibold\">Solicitações Pendentes</div>` + pendingRows)}
            `;
        } else if (view === 'people') {
            // Pessoas seguidas pelo usuário logado
            const ids = Array.isArray(userPeople) ? userPeople : [];
            if (!ids.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Você ainda não segue ninguém.</div>`;
            } else {
                let list = await fetchByIds(ids, 'people');
                list = Array.isArray(list) ? list : (list ? [list] : []);
                // Campo de busca
                const searchCard = UI.sectionCard(
                    UI.row('people-search','Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="people-search" placeholder="Digite para filtrar">`, { top:true, bottom:true })
                );

                const rows = list.map(u => {
                    const img = u?.im ? `data:image/png;base64,${u.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(u?.tt||'?').charAt(0)}`;
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
            // Negócios onde o usuário é membro com nível de moderação/gestão (nv >= 3)
            const managed = Array.isArray(userBusinessesData)
                ? userBusinessesData.filter(r => Number(r?.nv ?? 0) >= 3 && Number(r?.st ?? 1) === 1).map(r => r.em)
                : [];
            const ids = managed;
            if (!ids.length) {
                const createBusinessCard = UI.sectionCard(
                    UI.row('new-business-name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-business-name" placeholder="Digite o nome do negócio" required>`, { top:true }) +
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
                    UI.row('new-business-name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-business-name" placeholder="Digite o nome do negócio" required>`, { top:true }) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-business" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createBusinessCard;
                const searchCardBiz = UI.sectionCard(
                    UI.row('businesses-search','Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="businesses-search" placeholder="Digite para filtrar">`, { top:true, bottom:true })
                );
                html += searchCardBiz;
                const rows = list.map(b => {
                    const img = b?.im ? `data:image/png;base64,${b.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(b?.tt||'?').charAt(0)}`;
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
            // Equipes em que o usuário é criador (us) ou moderador (usmn contém id), ativas (st=1)
            // e cujo negócio (companies.em) também está ativo (companies.st=1)
            const res = await apiClient.post('/search', {
                db: 'workz_companies',
                table: 'teams',
                columns: ['id','tt','im','us','usmn','st','em'],
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
                    UI.row('new-team-name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-team-name" placeholder="Digite o nome da equipe" required>`, { top:true }) +
                    UI.rowSelect('new-team-business','Negócio', options) +
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
                    UI.row('new-team-name','Nome*', `<input class="w-full border-0 focus:outline-none" type="text" id="new-team-name" placeholder="Digite o nome da equipe" required>`, { top:true }) +
                    UI.rowSelect('new-team-business','Negócio', options) +
                    `<div class="grid grid-cols-1 border-t border-gray-200 bg-white">
                        <button data-action="create-team" class="col-span-1 p-3 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-b-2xl"><i class="fas fa-plus"></i> Criar</button>
                    </div>`
                );
                html += createTeamCard;
                const searchCardTeams = UI.sectionCard(
                    UI.row('teams-search','Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="teams-search" placeholder="Digite para filtrar">`, { top:true, bottom:true })
                );
                html += searchCardTeams;
                const rows = visible.map(t => {
                    const img = t?.im ? `data:image/png;base64,${t.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(t?.tt||'?').charAt(0)}`;
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
            if (!list.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Nenhum aplicativo instalado.</div>`;
            } else {
                const searchCardApps = UI.sectionCard(
                    UI.row('apps-search','Pesquisar', `<input class="w-full border-0 focus:outline-none" type="text" id="apps-search" placeholder="Digite para filtrar">`, { top:true, bottom:true })
                );
                const rows = list.map(app => {
                    const img = app?.im ? `data:image/png;base64,${app.im}` : '/images/app-default.png';
                    const name = (app?.tt || 'App');
                    return `
                    <div class="grid grid-cols-6 border-b border-gray-200 items-center hover:bg-gray-50 cursor-pointer" data-app-id="${app.id}" data-name="${name.toLowerCase()}">
                        <div class="col-span-1 p-3 flex justify-center">
                            <img src="${img}" alt="${name}" class="w-7 h-7 rounded-md" />
                        </div>
                        <div class="col-span-5 p-3 truncate">${name}</div>
                    </div>`;
                }).join('');
                html += searchCardApps + UI.sectionCard(`<div id="apps-list">${rows}</div>`);
            }
        } else if (view === 'app-settings') {
            const app = data || {};
            const appId = app.id || payload?.appId || null;
            const img = app?.im ? `data:image/png;base64,${app.im}` : '/images/app-default.png';
            const notifyKey = `app_notify_${appId}`;
            const enabled = (localStorage.getItem(notifyKey) === '1');
            const header = `
            <div class="w-full bg-white rounded-2xl shadow-md p-4 flex items-center gap-3">
                <img src="${img}" alt="${app?.tt || 'App'}" class="w-9 h-9 rounded-md" />
                <div class="font-semibold truncate">${app?.tt || 'Aplicativo'}</div>
            </div>`;
            const actions = `
            <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
                <div class="grid grid-cols-1 border-t border-gray-200 bg-white">
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
            const res = await apiClient.post('/search', { db:'workz_data', table:'testimonials', columns:['*'], conditions: { recipient: data.id, recipient_type: type }, fetchAll:true });
            const list = Array.isArray(res?.data) ? res.data : [];
            if (!list.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Não há depoimentos.</div>`;
            } else {
                const cards = await Promise.all(list.map(async t => {
                    const author = await fetchByIds(t.author, 'people');
                    const avatar = author?.im ? `data:image/png;base64,${author.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(author?.tt||'?').charAt(0)}`;
                    const primaryBtn = (t.status===0)
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
            console.log(data, view, type);            
            html += `<div class="bg-white rounded-2xl shadow-md p-4">Cobrança e Recebimento</div>`;
        } else if (view === 'transactions') {
            html += `<div class="bg-white rounded-2xl shadow-md p-4">Transações</div>`;
        } else if (view === 'subscriptions') {
            const conditions = (type === 'business') ? { em: data.id, subscription: 1 } : { us: data.id, subscription: 1 };

            const exists = [{ table: 'apps', local: 'ap', remote: 'id'}];
            const res = await apiClient.post('/search', { db:'workz_apps', table:'gapp', columns:['*'], conditions: conditions, exists: exists, fetchAll:true });                        
            const list = Array.isArray(res?.data) ? res.data : [];

            if (!list.length) {
                html += `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 text-sm text-center">Não há assinaturas.</div>`;
            } else {
                const cards = await Promise.all(list.map(async t => {                    
                    const app = await fetchByIds(t.ap, 'apps');
                    const avatar = app?.im ? `data:image/png;base64,${app.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(app?.tt||'?').charAt(0)}`;
                    const actionBtn = (t.end_date === null)
                        ? `<button title="Cancelar" data-action="reject-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-red-100 hover:bg-red-200 text-red-800 rounded-b-2xl"><i class="fas fa-ban"></i> Cancelar Assinatura</button>`
                        : `<button title="Renovar" data-action="revert-testmonial" data-id="${t.id}" class="col-span-1 p-3 bg-amber-100 hover:bg-amber-200 text-amber-800 rounded-b-2xl"><i class="fas fa-undo"></i> Renovar Assinatura</button>`
                    ;
                    console.log(app);
                    return `
                        <div class="w-full bg-white shadow-md rounded-2xl grid grid-cols-1 gap-y-4">
                            <div class="pt-4 px-4 col-span-4 flex items-center truncate">
                                <img class="w-9 h-9 mr-2 rounded-md pointer" src="${avatar}" />
                                <a class="font-semibold">${app?.tt}</a>
                            </div>
                            <div class="grid grid-cols-1 border-t p-4">                                
                                ${ (app?.vl > 0) ? app?.vl : 'Gratuito' }
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
        html += `<div class="bg-white rounded-2xl shadow-md p-4">Education content</div>`;
        } else if (view === 'user-jobs') {
            const userJobs = await apiClient.post('/search', { db: 'workz_companies', table: 'employees', columns: ['*'], conditions: { us: currentUserData.id }, order: { by: 'start_date', dir: 'DESC' }, fetchAll: true });
            const cards = await Promise.all(userJobs?.data?.map(async j => {
                const business = await fetchByIds(j.em, 'businesses');
                return `
                    <div class="w-full bg-white shadow-md rounded-2xl grid grid-cols-1 gap-y-4">
                        <div class="pt-4 px-4 col-span-4 flex items-center truncate">
                            <img class="w-7 h-7 mr-2 rounded-full pointer" src="${business?.im ? `data:image/png;base64,${business.im}` : `https://placehold.co/100x100/EFEFEF/333?text=${(business?.tt||'?').charAt(0)}`}" />
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
                <div class="absolute inset-0 bg-center bg-cover" style="background-image: url('${ (p.im) ? 'data:image/png;base64,' + p.im : `https://placehold.co/100x100/EFEFEF/333?text=${p.tt.charAt(0)}` }');"></div>
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

    async function pageAction() {        
        const actionContainer = document.querySelector('#action-container');
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
            const parseIdArray = (val) => { try { const arr = JSON.parse(val); return Array.isArray(arr) ? arr : []; } catch(_) { return []; } };
            const mods = (viewData?.usmn) ? parseIdArray(viewData.usmn) : [];
            const isModerator = mods.map(String).includes(String(currentUserData.id));
            
            // Verifica se o usuário não é gestor na empresa ou moderador
            if(!isManager && !isModerator){                                
                if (userBusinesses.includes(viewId)) {
                    if(memberStatus === 0) {
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
                    if(memberStatus === 0) {
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

    function customMenu () {
        const customMenu = document.querySelector('#custom-menu');
        const standardMenu = document.querySelector('#standard-menu');

        if (viewType === 'dashboard') {
            customMenu.insertAdjacentHTML('beforeend', UI.menuItem({ action: 'my-profile', icon: 'fa-address-card', label: 'Meu Perfil' }));
        } else {
            if (viewType === ENTITY.PROFILE && currentUserData.id === viewId) {
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
                UI.menuItem({ action: 'dashboard',  icon: 'fa-home',  label: 'Início' }) +
                UI.menuItem({ action: 'share-page', icon: 'fa-share', label: 'Compartilhar' })
            );
        } 
        
        // Delegação global do roteador cobre cliques com data-action
    }

    function appendFeed ( items ) {
        const timeline = document.querySelector('#timeline');
        const html = items.map(post => `
        <article class="col-span-12 sm:col-span-6 lg:col-span-4 flex flex-col bg-white p-4 rounded-3xl shadow-lg aspect-[3/4]">
            <header class="text-sm text-gray-500 mb-1">
                <span class="font-medium">${post.us}</span> • <time>${new Date(post.dt).toLocaleString()}</time>
            </header>
            ${post.tt ? `<h3 class="font-semibold mb-1">${post.tt}</h3>` : ''}                        
        </article>
        `).join('');

        timeline.insertAdjacentHTML('beforeend', html);
    }

    // Exibição de loading (abstrai jQuery e permite trocar no futuro)
    function showLoading() { try { $('#loading').fadeIn(); } catch(e) { const el = document.getElementById('loading'); if (el) el.style.display = 'block'; } }
    function hideLoading() { try { $('#loading').fadeOut(); } catch(e) { const el = document.getElementById('loading'); if (el) el.style.display = 'none'; } }

    // Navegação centralizada (mantém padrão e facilita manutenção)
    function navigateTo(path) {
        history.pushState({}, '', path);
        showLoading();
        loadPage();
    }

    // Snapshot simples do estado (facilita roteadores e handlers)
    function getState() {
        return {
            user: currentUserData,
            view: { type: viewType, id: viewId, data: viewData },
            memberships: { people: userPeople, businesses: userBusinesses, teams: userTeams, level: memberLevel, status: memberStatus }
        };
    }

    // Notificações simples
    function notifySuccess(msg) { try { swal('Pronto', msg, 'success'); } catch(_) { try { alert(msg); } catch(__) {} } }
    function notifyError(msg) { try { swal('Ops', msg, 'error'); } catch(_) { try { alert(msg); } catch(__) {} } }
    async function confirmDialog(msg, { title='Confirmação', danger=false } = {}) {
        try {
            return await swal({
                title,
                text: msg,
                icon: danger ? 'warning' : 'info',
                buttons: ['Cancelar', 'Confirmar'],
                dangerMode: !!danger,
            });
        } catch(_) {
            try { return confirm(msg); } catch(__) { return false; }
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
            button.classList.add('opacity-60','cursor-not-allowed');
            button.innerHTML = `<span class="inline-block animate-pulse">${textWhileLoading}</span>`;
        } else {
            button.disabled = false;
            button.classList.remove('opacity-60','cursor-not-allowed');
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
        } catch (_) {}
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
            return { ...base, us: id, em: 0, cm: 0 };
        }
        if (type === ENTITY.BUSINESS) {
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

    // Roteador de ações centralizado
    const ACTIONS = {
        'dashboard': ({ state }) => navigateTo('/'),
        'my-profile': ({ state }) => navigateTo(`/profile/${state.user?.id}`),
        'share-page': () => sharePage(),
        'list-people': () => navigateTo('/people'),
        'list-businesses': () => navigateTo('/businesses'),
        'list-teams': () => navigateTo('/teams'),
        'logout': () => handleLogout(),
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
            } catch(_) {
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
            const em   = (document.getElementById('new-team-business')?.value || '').trim();
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
        'follow-user': async ({ state, button }) => {
            const follower = state.user?.id;
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
        'unfollow-user': async ({ state, button }) => {
            const follower = state.user?.id;
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
                const exists = await apiClient.post('/search', { db: 'workz_companies', table, columns: ['id','st'], conditions: payloadKeys, fetchAll: true, limit: 1 });
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
                            try { await renderView(viewId); } catch(_) {}
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
                        try { await SidebarNav.render(); } catch(_) {}
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
            } catch(_) { notifyError('Falha ao aceitar depoimento.'); }
            loadPage();
        },
        'reject-testmonial': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            try {
                await apiClient.post('/update', { db: 'workz_data', table: 'testimonials', data: { status: 2 }, conditions: { id } });
                notifySuccess('Depoimento rejeitado.');
            } catch(_) { notifyError('Falha ao rejeitar depoimento.'); }
            loadPage();
        },
        'revert-testmonial': async ({ button }) => {
            const id = button?.dataset?.id;
            if (!id) return;
            try {
                await apiClient.post('/update', { db: 'workz_data', table: 'testimonials', data: { status: 0 }, conditions: { id } });
                notifySuccess('Depoimento revertido.');
            } catch(_) { notifyError('Falha ao reverter depoimento.'); }
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
            } catch(_) { notifyError('Falha ao excluir experiência.'); }
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

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const action = btn.dataset.action;
        const handler = ACTIONS[action];
        if (!handler) return;
        e.preventDefault();
        handler({ event: e, button: btn, state: getState() });
    });

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
        } catch(_) { return false; }
    }
    function isTeamOwner(teamData) {
        try { return String(teamData?.us) === String(currentUserData.id); } catch(_) { return false; }
    }
    function isTeamModerator(teamData) {
        try {
            const mods = teamData?.usmn ? JSON.parse(teamData.usmn) : [];
            return Array.isArray(mods) && mods.map(String).includes(String(currentUserData.id));
        } catch(_) { return false; }
    }
    function canManageTeam(teamData) { return isTeamOwner(teamData) || isTeamModerator(teamData); }

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

    async function renderTemplate(container, template, data = null, onRendered = null) {
    await fadeTransition(container, async () => {
        if (typeof template === 'string' && templates[template]) {
        container.innerHTML = templates[template];
        } else if (typeof template === 'function') {
        const result = template.length >= 1 ? template(data) : template(); // chama
        container.innerHTML = result instanceof Promise ? await result : result; // aguarda se for Promise
        } else {
        console.error('Template inválido.');
        return;
        }
        if (onRendered) await onRendered();
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
    [...container.children].forEach((row, i, arr) => {
        row.classList.remove('rounded-t-2xl', 'rounded-b-2xl');
        if (i === 0) row.classList.add('rounded-t-2xl');
        if (i === arr.length - 1) row.classList.add('rounded-b-2xl');
    });
    }

    async function toggleSidebar(el = null, toggle = true) {

        if (sidebarWrapper.innerHTML.trim() !== '') {
            sidebarWrapper.innerHTML = '';
        }

        //

        if (toggle === true) {
            sidebarWrapper.classList.toggle('w-0');
            sidebarWrapper.classList.toggle('lg:w-1/3');
            sidebarWrapper.classList.toggle('sm:w-1/2');
            sidebarWrapper.classList.toggle('w-full');
            sidebarWrapper.classList.toggle('shadow-2xl');
        }

        if (el) {
            sidebarWrapper.innerHTML = `<div class="sidebar-content grid grid-cols-1 gap-6 p-4"></div>`;
            const sidebarContent = document.querySelector('.sidebar-content');
            const action = el.dataset.sidebarAction;            
            if (action === 'settings') {
                // Página principal de configurações (atalhos gerais)
                SidebarNav.setMount(sidebarContent);
                SidebarNav.resetRoot(currentUserData);
                renderTemplate(sidebarContent, templates.sidebarPageSettings, { data: currentUserData, origin: 'stack' }, () => {});

            } else if (action === 'page-settings') {
                // Entrou em Ajustes a partir da página atual (fora do stack ou entidade corrente)
                SidebarNav.setMount(sidebarContent);
                SidebarNav.resetRoot(currentUserData);
                let pageSettingsView = (el.parentNode.dataset.sidebarType === 'current-user') ? ENTITY.PROFILE : viewType;
                let pageSettingsData = (el.parentNode.dataset.sidebarType === 'current-user') ? currentUserData : viewData;
                SidebarNav.push({ view: pageSettingsView, title: (pageSettingsData?.tt || 'Ajustes'), payload: { data: pageSettingsData, type: pageSettingsView } });
                // listeners complementares (contatos etc.)
                const settingsForm = document.querySelector('#settings-form');
                if (!settingsForm) return;
                //Botões de Adição / Remoção
                settingsForm?.addEventListener('click', (e) => {
                    const addBtn = e.target.closest('#add-input-button');
                    const rmBtn  = e.target.closest('#remove-input-button');
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
                    }, () => {});
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
                const rmBtn  = e.target.closest('#remove-input-button');
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
        }

        // atalhos da subview (via stack)
        if (sidebarContent._shortcutsHandler) sidebarContent.removeEventListener('click', sidebarContent._shortcutsHandler);
        const shortcutsHandler = (e) => {
            const shortcut = e.target.closest('#employees, #user-jobs, #testimonials, #billing, #transactions, #subscriptions');
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
            const optionsHtml = Object.entries(businessesJobs).sort(([,a],[,b]) => a.localeCompare(b, 'pt-BR')).map(([id, nome]) => `
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

    async function startup(){        
        
        const showNotLoggedIn = () => {
            localStorage.removeItem('jwt_token');
            renderTemplate(mainWrapper, 'notLoggedIn', null, () => {
                let loginWrapper = document.querySelector('#login');
                renderTemplate(loginWrapper, 'init', null, () => {
                    renderLoginUI();
                    viewType = 'public';
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
        userPeople = userPeople.data.map(o => o.s1);        

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
            columns: ['id','us','st','tt'],
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
        userTeamsData = userTeams.data;

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
                people:     { db: 'workz_data',      table: 'hus',       columns: ['id', 'tt', 'im'],        conditions: { st: 1 }, url: 'profile/' },
                teams:      { db: 'workz_companies', table: 'teams',     columns: ['id', 'tt', 'im', 'em'], conditions: { st: 1 }, url: 'team/' },
                businesses: { db: 'workz_companies', table: 'companies', columns: ['id', 'tt', 'im'],        conditions: { st: 1 }, url: 'business/' }
            };            
            let list = await apiClient.post('/search', {
                db: entityMap[listType].db,
                table: entityMap[listType].table,
                columns: entityMap[listType].columns,
                conditions: entityMap[listType].conditions,                
                order: { by: 'tt', dir: 'ASC' },
                fetchAll: true
            }); 
            list = list.data;
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
                    navigateTo(`/${ entityMap[listType].url + item.dataset.itemId }`);
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

        memberLevel = null;
        memberStatus = null;
        viewId = null;
        viewData = null;
        viewType = null;

        if (peopleListMatch) {
            renderList();
            return;
        } else if (businessListMatch) {
            renderList('businesses');
            return;
        } else if (teamsListMatch) {
            renderList('teams');
            return;
        } else {
            if (profileMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    viewType = 'profile';
                    viewId = parseInt(profileMatch[1], 10);
                    renderView(viewId);
                });
                return;
            } else if (businessMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    viewType = 'business';
                    viewId = parseInt(businessMatch[1], 10);
                    renderView(viewId);
                });
                return;                
            } else if (teamMatch) {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    viewType = 'team';
                    viewId = parseInt(teamMatch[1], 10);                    
                    renderView(viewId);
                });
                return;                
            } else {
                renderTemplate(workzContent, 'workzContent', null, () => {
                    viewType = 'dashboard';                    
                    renderView();
                });
                return;
            }
        }
    }

    async function renderView(entity = currentUserData) {
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

            entityImage = (currentUserData.im) ? 'data:image/png;base64,' + currentUserData.im : `https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.tt.charAt(0)}`;            

        // OUTRAS ROTAS: define o que buscar
        } else {
            
            let entityMap = {};
            let entitiesToFetch = [];
            if (viewType === ENTITY.PROFILE) {
                entityMap = {
                    people:     { db: 'workz_data',      table: 'usg',             target: 's1', conditions: { s0: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    // Somente businesses aprovados (st=1)
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId, st: 1 }, mainDb: 'workz_companies', mainTable: 'companies' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' },
                }; 
                entitiesToFetch = ['people', 'businesses', 'teams'];                
            } else if (viewType === ENTITY.BUSINESS) {
                entityMap = {
                    // Somente membros aprovados (st=1) devem aparecer no widget da página do negócio
                    people:     { db: 'workz_companies', table: 'employees',       target: 'us', conditions: { em: entityId, st: 1 }, mainDb: 'workz_data', mainTable: 'hus' },                    
                    teams:      { db: 'workz_companies', table: 'teams',           target: 'id', conditions: { em: entityId, st: 1 } },
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                }; 
                entitiesToFetch = ['people', 'teams'];
            } else if  (viewType === ENTITY.TEAM) {
                entityMap = {
                    // Somente membros aprovados (st=1) devem aparecer
                    people:     { db: 'workz_companies', table: 'teams_users',     target: 'us', conditions: { cm: entityId, st: 1 }, mainDb: 'workz_data', mainTable: 'hus' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' }
                }; 
                entitiesToFetch = ['people'];
            }

            // Define o tipo de entidade
            let entityType = (viewType === ENTITY.PROFILE) ? 'people' : (viewType === ENTITY.BUSINESS)   ? 'businesses' : 'teams';

            // Busca dados da entidade
            entityData = await apiClient.post('/search', {
                db: entityMap[entityType].db,
                table: entityMap[entityType].mainTable,
                columns: ['*'],
                conditions: { ['id']: entityId }
            }); 

            // Verifica se entidade existe
            if (entityData.data.length === 0) {
                $('#loading').delay(250).fadeOut();                
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

            Object.assign(entityData.data[0], results);

            viewData = entityData.data[0];

            // Restrição de acesso para páginas de Equipe: somente membros aprovados da equipe
            if (viewType === ENTITY.TEAM) {
                const isTeamMemberApproved = Array.isArray(userTeamsData)
                    ? userTeamsData.some(t => String(t.cm) === String(viewData.id) && Number(t.st) === 1)
                    : false;
                viewRestricted = !isTeamMemberApproved;
            } else {
                viewRestricted = false;
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

            Object.assign(entityData.data[0], results);            

            entityImage = (entityData.data[0].im) ? 'data:image/png;base64,' + entityData.data[0].im : `https://placehold.co/100x100/EFEFEF/333?text=${entityData.data[0].tt.charAt(0)}`;

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

                // 3) Conjunto de businesses do usuário (compatível com string/number)
                const userBusinessSet = new Set(
                    (results?.businesses || []).map(b =>
                        String(typeof b === 'object' ? (b.em ?? b.id ?? b) : b)
                    )
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
            widgetPeople     = Array.isArray(results?.people)     ? results.people.slice(0, 6)     : [];
            widgetBusinesses = Array.isArray(results?.businesses) ? results.businesses.slice(0, 6) : [];
            widgetTeams      = Array.isArray(results?.teams)      ? results.teams.slice(0, 6)      : [];
            widgetPeopleCount = results.peopleCount ?? 0;
            widgetBusinessesCount = results.businessesCount ?? 0;
            widgetTeamsCount = results.teamsCount ?? 0;
        }
            
        // Depois os widgets, na ordem desejada
        if (widgetPeople.length)      await appendWidget('people',      widgetPeople,      widgetPeopleCount);
        if (widgetBusinesses.length)  await appendWidget('businesses',  widgetBusinesses,  widgetBusinessesCount);
        if (widgetTeams.length)       await appendWidget('teams',       widgetTeams,       widgetTeamsCount);

        // Nível do usuário
        // - TEAM: utiliza nv do vínculo na própria equipe (teams_users)
        // - BUSINESS: utiliza nv do vínculo na empresa (employees)
        memberLevel = (viewType === ENTITY.TEAM)
            ? Number(parseInt(userTeamsData.find(item => item.cm === viewData.id)?.nv ?? 0))
            : (viewType === ENTITY.BUSINESS)
                ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.nv ?? 0))
                : 0;
        memberStatus = (viewType === ENTITY.TEAM) ? Number(parseInt(userTeamsData.find(item => item.cm === viewData.id)?.st ?? 0)) : (viewType === ENTITY.BUSINESS) ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.st ?? 0)) : 0;

        Promise.all([            
            // Menu customizado
            customMenu(),
            // Gatilhos de criação de conteúdo
            renderTemplate(document.querySelector('#editor-trigger'), templates['editorTrigger'], currentUserData),                    

            (viewType === 'dashboard')
                ? 
                // Conteúdo principal (Dashboard)
                renderTemplate(document.querySelector('#main-content'), 'mainContent', null, async () => {                    
                    // Aplicativos
                    let userApps = await apiClient.post('/search', {
                        db: 'workz_apps',
                        table: 'gapp',
                        columns: ['ap'],
                        conditions: {
                            us: currentUserData.id,
                            st: 1
                        },
                        fetchAll: true
                    });
                    userApps = userApps.data.map(o => o.ap);                
                    await renderTemplate(document.querySelector('#app-library'), templates.appLibrary, { appsList: userApps }, () => {
                        initAppLibrary('#app-library');
                    });
                })
                : Promise.resolve(),              
            
            (viewType !== 'dashboard')
                ?
                // Conteúdo principal (Perfil, Negócio ou Equipe)
                renderTemplate(
                    document.querySelector('#main-content'),
                    (viewType === ENTITY.TEAM && viewRestricted) ? templates.teamRestricted : templates['entityContent'],
                    { data: entityData.data[0] }
                )
                : Promise.resolve(),
            
            // Imagem da página
            document.querySelector('#profile-image').src = entityImage
        ]).then(() => {

            const widgetWrapper = document.querySelector('#widget-wrapper');
            widgetWrapper.addEventListener('click', (e) => {
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
            });

            // Acessibilidade: abrir cards com Enter
            widgetWrapper.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const card = e.target.closest('.card-item');
                if (!card) return;
                const widgetRoot = card.closest('[id^="widget-"]');
                const type = widgetRoot?.id?.replace('widget-','') || '';
                const id = card.dataset.id;
                let baseUrl;
                if (type === 'people') baseUrl = '/profile/';
                else if (type === 'teams') baseUrl = '/team/';
                else baseUrl = '/business/';
                navigateTo(`${baseUrl}${id}`);
            });


            const pageThumbs = document.getElementsByClassName('page-thumb');
            for (let i = 0; i < pageThumbs.length; i++) {
                pageThumbs[i].src = 'data:image/png;base64,' + currentUserData.im;
            }

            // Reseta o estado do feed
            feedOffset = 0;        
            feedLoading = false;
            feedFinished = false;

            // Finalizações
            loadFeed();
            initFeedInfiniteScroll();            
            //topBarScroll();      
            $('#loading').fadeOut();                                          
        });
    }

    // mapeia type -> banco/tabela
    const typeMap = {
        people:      { db: 'workz_data',      table: 'hus',       idCol: 'id' },
        businesses:  { db: 'workz_companies', table: 'companies', idCol: 'id' },
        teams:       { db: 'workz_companies', table: 'teams',     idCol: 'id' },
        apps:        { db: 'workz_apps',      table: 'apps',      idCol: 'id' }
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

        const list = Array.isArray(res?.data) ? res.data : res;

        // 2) Reordena pra manter a mesma ordem de entrada
        const byId = new Map(list.map(item => [item.id, item]));
        const results = ids.map(id => byId.get(id) || { id, tt: 'Item', im: '/images/default-avatar.jpg' });

        // Se o input original era único, devolve único
        return ids.length === 1 ? results[0] : results;
    }


    function initAppLibrary(root = '#app-library') {
        const el = typeof root === 'string' ? document.querySelector(root) : root;
        if (!el) return;

        const track = el.querySelector('[data-role="track"]');
        const dotEls = [...el.querySelectorAll('[data-role="dots"] button')];
        const prev = el.querySelector('[data-role="prev"]');
        const next = el.querySelector('[data-role="next"]');

        let page = 0;
        const total = dotEls.length;

        const go = (idx) => {
            page = Math.max(0, Math.min(idx, total - 1));
            track.style.transform = `translateX(-${page * 100}%)`;
            dotEls.forEach((d, i) => {
            d.classList.toggle('bg-white', i === page);
            d.classList.toggle('bg-gray-300', i !== page);
            });
        };

        // Dots
        dotEls.forEach(d => d.addEventListener('click', () => go(+d.dataset.idx)));

        // Setas
        prev?.addEventListener('click', () => go(page - 1));
        next?.addEventListener('click', () => go(page + 1));

        // Swipe (touch/drag)
        let startX = 0, deltaX = 0, isDown = false;

        const onStart = (x) => { isDown = true; startX = x; deltaX = 0; };
        const onMove = (x) => {
            if (!isDown) return;
            deltaX = x - startX;
            track.style.transitionDuration = '0ms';
            track.style.transform = `translateX(calc(${-page*100}% + ${deltaX}px))`;
        };
        const onEnd = () => {
            if (!isDown) return;
            isDown = false;
            track.style.transitionDuration = '';
            const threshold = 50; // px
            if (deltaX > threshold) go(page - 1);
            else if (deltaX < -threshold) go(page + 1);
            else go(page); // volta
        };

        // Touch
        track.addEventListener('touchstart', e => onStart(e.touches[0].clientX), {passive:true});
        track.addEventListener('touchmove',  e => onMove(e.touches[0].clientX), {passive:true});
        track.addEventListener('touchend', onEnd);

        // Mouse (opcional)
        track.addEventListener('mousedown', e => onStart(e.clientX));
        window.addEventListener('mousemove', e => onMove(e.clientX));
        window.addEventListener('mouseup', onEnd);

        // Teclado
        el.tabIndex = 0;
        el.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') go(page - 1);
            if (e.key === 'ArrowRight') go(page + 1);
        });

        go(0);
    }

    async function loadFeed() {
        if (!viewType) return;

        if (feedLoading || feedFinished) return;
        feedLoading = true;

        const orBlocks = [];

        if (viewType === 'dashboard') {
            // Copia segura + sem duplicatas
            const basePeople   = Array.isArray(userPeople) ? userPeople : [];
            const baseBiz      = Array.isArray(userBusinesses) ? userBusinesses : [];
            const baseTeams    = Array.isArray(userTeams) ? userTeams : [];

            const followedIds  = [...new Set([...basePeople, currentUserData.id])];

            if (followedIds.length) orBlocks.push({ us: { op: 'IN', value: followedIds } });
            if (baseBiz.length)     orBlocks.push({ em: { op: 'IN', value: baseBiz } });
            if (baseTeams.length)   orBlocks.push({ cm: { op: 'IN', value: baseTeams } });

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

        const res = await apiClient.post('/search', {
            db: 'workz_data',
            table: 'hpl',
            columns: ['id','us','em','cm','tt','ct','dt','im'],
            conditions: {
                st: 1,                // AND st = 1
                _or: orBlocks         // AND ( us IN (...) OR em IN (...) OR cm IN (...) )
            },
            order: { by: 'dt', dir: 'DESC' },
            fetchAll: true,
            limit: FEED_PAGE_SIZE,
            offset: feedOffset
        });

        const items = res?.data || [];

        // se não veio nada, acabou
        if (!items.length) {
            feedFinished = true;
            feedLoading = false;
            return;
        }

        // renderizar (append)        
        appendFeed(items);

        // avançar offset
        feedOffset += FEED_PAGE_SIZE;
        feedLoading = false;

    }

    function initFeedInfiniteScroll() {
        const sentinel = document.querySelector('#feed-sentinel');
        if (!sentinel) return;

        const io = new IntersectionObserver((entries) => {
            const [entry] = entries;
            if (entry.isIntersecting) {
            loadFeed();
            }
        }, { rootMargin: '200px' }); // começa a carregar antes de encostar

        io.observe(sentinel);
    }

    async function initializeCurrentUserData() {
        try {
            const userData = await apiClient.get('/me');
            if (userData.error) {
                handleLogout();                
                return false;
            }            
            currentUserData = userData;
            return true;
        } catch (error) {
            console.error('Failed to initialize user data:', error);
            handleLogout();
            return false;
        }
    }

    async function handleUpdate(e) {
        e.preventDefault();
        const view = e.target.dataset.view;
        const form = new FormData(e.target);
        const data = Object.fromEntries(form.entries());
        const messageContainer = document.getElementById('message');

        if (data.phone) {
            data.phone = onlyNumbers(data.phone);
        }
        if (data.national_id) {
            data.national_id = onlyNumbers(data.national_id);
        }

        const changedData = getChangedFields(data, currentUserData);

        if (Object.keys(changedData).length === 0) {
            renderTemplate(messageContainer, templates.message, { message: 'Nenhuma alteração detectada.', type: 'warning' });
            return;
        }

        if (changedData.tt !== undefined && changedData.tt.trim() === '') { renderTemplate(messageContainer, templates.message, { message: 'Nome é obrigatório.', type: 'error' }); return; }
        if (changedData.ml !== undefined && changedData.ml.trim() === '') { 
            renderTemplate(messageContainer, templates.message, { message: 'E-mail é obrigatório.', type: 'error' });
            return; 
        } else if (changedData.ml !== undefined) {
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
            return;
        }

        const entityType = (view === ENTITY.PROFILE) ? 'people' 
                         : (view === ENTITY.BUSINESS) ? 'businesses' 
                         : 'teams';

        if (data.id) {            
            const result = await apiClient.post('/update', {
                db: typeMap[entityType].db,
                table: typeMap[entityType].table,
                data: changedData,
                conditions: {
                    id: data.id
                }
            });
            if (result) {
                await initializeCurrentUserData();                
                renderTemplate(messageContainer, templates.message, { message: 'Dados atualizados com sucesso!', type: 'success' });
                if (viewType === view) {
                    loadPage();
                }                
            } else {
                console.error('Falha na atualização.');
            }
        }                            
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
        const messageContainer = document.getElementById('message');        

        let data = {
            email: loginForm.email.value,
            password: loginForm.password.value
        }
        
        const result = await apiClient.post('/login', data);
        if (result.token) {
            localStorage.setItem('jwt_token', result.token);
            $('#loading').fadeIn();
            startup();            
        } else {
            renderTemplate(messageContainer, templates.message, { message: result.error || 'Ocorreu um erro.', type: 'error' });            
        }        
    }

    async function handleRegister(event) {
        event.preventDefault();
        const registerForm = event.target;
        const messageContainer = document.getElementById('message');

        const result = await apiClient.post('/register', {
            name: registerForm.name.value,
            email: registerForm.email.value,
            password: registerForm.password.value,
            'password-repeat': registerForm['password-repeat'].value
        });
    
        if (result) {
            if (result.token) {
                localStorage.setItem('jwt_token', result.token);
                $('#loading').fadeIn();
                startup();
            } else {
                renderTemplate(messageContainer, templates.message, { message: result.error || 'Ocorreu um erro ao logar após criar a conta.', type: 'error' });            
            }
        } else {
            renderTemplate(messageContainer, templates.message, { message: result.error || 'Ocorreu um erro ao criar a conta.', type: 'error' });
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
            document.getElementById('register-form').addEventListener('submit', handleRegister);
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
            document.getElementById('login-form').addEventListener('submit', handleLogin);
            document.getElementById('google-login-btn').addEventListener('click', () => window.location.href = '/api/auth/google/redirect');
            document.getElementById('microsoft-login-btn').addEventListener('click', () => window.location.href = '/api/auth/microsoft/redirect');
            document.getElementById('show-register-link').addEventListener('click', (e) => {
                e.preventDefault();
                renderRegisterUI();
            });
            $('#loading').delay(250).fadeOut();
        });
    }

    // ===================================================================
    // 🔄 RENDERIZAÇÃO DA INTERFACE
    // ===================================================================

    function startClock() {
        const today = new Date();
        const clock = document.querySelector('#wClock');

		let h = today.getHours();
		let m = today.getMinutes();
		let s = today.getSeconds();
		h = checkTime(h); // <- aqui está a mudança
		m = checkTime(m);
		s = checkTime(s);
		
        clock.innerHTML =  h + ":" + m;

		setTimeout(startClock, 1000);
	}

	function checkTime(i) {
		return (i < 10 ? "0" : "") + i;
	}  

    function topBarScroll(){
        
        const topBar = document.querySelector('#topbar');
        const logo = document.querySelector('.logo-menu');        

        mainWrapper.scroll(function(){            
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

    startup();

    document.addEventListener('click', function(event) {
        const target = event.target;
        
        const isSidebarOpen = !sidebarWrapper.classList.contains('w-0');
        const clickedInsideSidebar = sidebarWrapper.contains(target);
        const clickedSidebarTrigger = !!target.closest('#sidebarTrigger');
        
        const actionBtn = target.closest('[data-sidebar-action]');        
        const actionType = actionBtn?.dataset?.sidebarAction;
        if (actionType === 'sidebar-back') {
            // Deixa o handler de histórico cuidar; não limpar/trocar o sidebar aqui
            event.preventDefault();
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
            event.preventDefault();
            toggleSidebar(actionBtn, false);
            return;
        } else if (actionBtn && isSidebarOpen && actionBtn.id === 'close') { 
            toggleSidebar();
        }

        if (isSidebarOpen && !clickedInsideSidebar && !clickedSidebarTrigger) {
            toggleSidebar(); // fecha
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

    // Função segura para aplicar IMask
    function applyMask(id, options) {
        const el = document.getElementById(id);
        if (!el) {
            console.warn(`[mask] elemento #${id} não encontrado no DOM no momento da aplicação`);
            return null;
        }
        return IMask(el, options);
    }

    // Inicializa quando o DOM está pronto
    function initMasks() {
        applyMask('phone', phoneMaskOptions);
        applyMask('cpf', cpfMaskOptions);
    }

    function onlyNumbers(str) {
        return str.replace(/\D/g, ''); // remove tudo que não for número
    }

});
