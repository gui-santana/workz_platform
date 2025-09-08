// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {    

    const mainWrapper = document.querySelector("#main-wrapper"); //Main Wrapper
    const sidebarWrapper = document.querySelector('#sidebar-wrapper');

    let workzContent = '';

    const apiClient = new ApiClient();    

    // Variáveis Globais do Usuário
    let currentUserData = null;
    let userPeople = null;
    let userBusinesses = null; //Ids de empresa
    let userTeams = null;
    
    let userBusinessesData = null; // Condições do usuário nas empresas
    let userTeamsData = null; // Condições do usuário nas equipes

    let businessesJobs = {};

    let memberStatus = null; // Status do usuário em páginas de negócio e de equipe
    let memberLevel = null; // Nível do usuário em páginas de negócio e de equipe

    // Variáveis Globais da Página
    let viewType = null;
    let viewId = null;
    let viewData = null;

    // Variáveis Globais do Feed       
    const FEED_PAGE_SIZE = 6;
    let feedOffset = 0;    
    let feedLoading = false;
    let feedFinished = false;

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
                                <li><button href="#people" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-user-friends fa-stack-1x text-gray-700"></i></span><a class="truncate">Pessoas</a></button></li>
                                <li><button href="#businesses" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-briefcase fa-stack-1x text-gray-700"></i></span><a class="truncate">Negócios</a></button></li>
                                <li><button href="#teams" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-users fa-stack-1x text-gray-700"></i></span><a class="truncate">Equipes</a></button></li>
                                <li><button href="#" id="logout-btn-sidebar" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-sign-out-alt fa-stack-1x text-gray-700"></i></span><a class="truncate">Sair</a></button></li>
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
            let html = '<div class="col-span-12 flex flex-col grid grid-cols-12 gap-6">';        
            listItems.forEach(item => {
                html += `
                <div class="list-item sm:col-span-12 md:col-span-6 lg:col-span-4 flex flex-col bg-white p-3 rounded-3xl shadow-lg bg-gray hover:bg-gray-100 cursor-pointer" data-item-id="${item.id}">
                    <div class="flex items-center gap-3">
                        <img class="w-10 h-10 rounded-full" src="https://placehold.co/40x40/EFEFEF/333?text=${item.tt.charAt(0)}" alt="${item.tt}">
                        <span class="font-semibold">${item.tt}</span>
                    </div>                    
                </div> 
                `;
            });
            html += '</div>';
            return html;
        }
    }

    templates.entityContent = async ({ data }) => {        
        const content = `
            <div class="rounded-3xl w-full p-4 shadow-[0_-10px_15px_-3px_rgba(0,0,0,0.1),0_-4px_6px_-2px_rgba(0,0,0,0.05)]">
                <h2 class="text-2xl font-semibold">${data.tt}</h2>
                <div class="grid grid-cols-3 flex-wrap gap-4 mt-6 mb-6">
                    <div class="col-span-1 flex items-center text-center justify-center">
                        <p><small class="text-gray-500">Publicações</small><br>${data.postsCount}</p>
                    </div>
                    <div class="col-span-1 flex items-center text-center justify-center">                        
				        <p><small class="text-gray-500">Seguidores</small><br>${data.followersCount}</p>
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

    templates.sidebarMain = async ({ data }) => {
        return `
        <div id="close" data-sidebar-action="settings" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">
            <a>Fechar</a>
            <i class="fas fa-chevron-right"></i>                
        </div>
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
        <div class="bg-white w-full shadow-md rounded-2xl p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
            <span class="fa-stack gray-500">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas fa-th fa-stack-1x fa-inverse"></i>					
            </span>
            Tela de Início
        </div>
        <div class="bg-white w-full shadow-md rounded-2xl p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
            <span class="fa-stack gray-500">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas fa-shapes fa-stack-1x fa-inverse"></i>					
            </span>
            Aplicativos
        </div>
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            <div class="rounded-t-2xl border-b-2 border-black-500 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-user-friends fa-stack-1x fa-inverse"></i>					
                </span>
                Pessoas
            </div>
            <div class="border-b-2 border-black-500 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-briefcase fa-stack-1x fa-inverse"></i>					
                </span>
                Negócios
            </div>
            <div class="rounded-b-2xl bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-users fa-stack-1x fa-inverse"></i>					
                </span>
                Equipes
            </div>
        </div>
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            <div class="rounded-t-2xl border-b-2 border-black-500 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-money-bill fa-stack-1x fa-inverse"></i>					
                </span>
                Cobrança e Recebimento
            </div>
            <div class="border-b-2 border-black-500 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-receipt fa-stack-1x fa-inverse"></i>					
                </span>
                Transações
            </div>
            <div class="rounded-b-2xl border-b-2 border-black-500 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-satellite-dish fa-stack-1x fa-inverse"></i>					
                </span>
                Assinaturas
            </div>            
        </div>
        <div class="bg-white w-full shadow-md rounded-2xl p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
            <span class="fa-stack gray-500">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas fa-sign-out-alt fa-stack-1x fa-inverse"></i>					
            </span>
            Sair
        </div>
        <div class="text-center border-t border-gray-200 grid grid-cols-1 gap-1 pt-4">
            <img class="mx-auto" src="https://guilhermesantana.com.br/images/50x50.png" style="height: 40px; width: 40px" alt="Logo de uFicial"></img>
            <a href="https://uficial.com" target="_blank">uFicial Technologies © 2025</a>
        </div>
        `
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

        shortcutItem: (id, icon, label, { roundedTop=false, roundedBottom=false } = {}) => `
        <div id="${id}" class="${roundedTop?'rounded-t-2xl':''} ${roundedBottom?'rounded-b-2xl':''} border-b border-gray-200 bg-white p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out">
            <span class="fa-stack gray-500">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas ${icon} fa-stack-1x fa-inverse"></i>
            </span>
            ${label}
        </div>
        `,

        shortcutList: (items=[]) => `
        <div class="w-full shadow-md rounded-2xl grid grid-cols-1">
            ${items.map((it, i) => UI.shortcutItem(it.id, it.icon, it.label, {
            roundedTop: i===0,
            roundedBottom: i===items.length-1
            })).join('')}
        </div>
        `
    };

    templates.sidebarPageSettings = async ({ view, data, type = null }) => {
        const sidebarContent = document.querySelector('.sidebar-content');
        let html = '';

        // Cabeçalhos unificados
        const titles = {
        'profile': data.tt,
        'business': data.tt,
        'team': data.tt,
        'user-education': 'Formação Acadêmica',
        'user-jobs': 'Experiência Profissional',
        'user-testmonials': 'Depoimentos',
        'business-shareholding': 'Estrutura Societária',
        'employees': 'Colaboradores',
        'testmonials': 'Depoimentos',
        };

        const headerBackLabel = (['profile','business','team'].includes(view)) ? 'Ajustes' : (view.startsWith('user-') ? currentUserData.tt : data.tt);

        // Hero quando for página “principal”
        if (['profile','business','team'].includes(view)) {
            html += `
                ${UI.renderHeader({ backAction: 'settings', backLabel: 'Ajustes', title: data.tt })}
                ${UI.renderHero({ tt: data.tt, im: data?.im })}
                <div id="message" class="w-full fixed"></div>
            `;
        } else {
            html += UI.renderHeader({ backAction: 'page-settings', backLabel: headerBackLabel, title: titles[view] ?? '' });
        }

        // VIEWS
        if (view === 'profile') {
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
            { id:'testmonials', icon:'fa-scroll', label:'Depoimentos' },
        ]) + `
            <div class="bg-white w-full shadow-md rounded-2xl p-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out">
            <span class="fa-stack gray-500">
                <i class="fas fa-circle fa-stack-2x"></i>
                <i class="fas fa-key fa-stack-1x fa-inverse"></i>
            </span>
            Alterar Senha
            </div>
        `;

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
        `;
        } else if (view === 'business') {
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
            { id:'business-shareholding', icon:'fa-sitemap', label:'Estrutura Societária' },
            { id:'employees', icon:'fa-id-badge', label:'Colaboradores' },
            { id:'testmonials', icon:'fa-scroll', label:'Depoimentos' },
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
        `;
        } else if (view === 'team') {
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
        `;
        } else if (view === 'employees') {
            const table = (type === 'business') ? 'employees' : 'teams_users';
            const conditions = (type === 'business') ? { em: data.id } : { cm: data.id };
            const employees = await apiClient.post('/search', { db:'workz_companies', table, columns:['us','nv','st'], conditions, fetchAll:true });
            const people = await fetchByIds(employees?.data?.map(o => o.us), 'people');

            html += UI.sectionCard(
                (people||[]).map(p => UI.row(`employee-${p.id}`, p.tt, `<input class="w-full border-0 focus:outline-none" name="employee" id="employee-${p.id}">`)).join('')
            );
        } else if (view === 'business-shareholding') {
        html += `
            <div class="w-full shadow-md rounded-2xl">
            <div id="tree" class="bg-white rounded-t-2xl divide-y divide-gray-100"></div>
            <button id="add-root" class="w-full p-4 rounded-b-2xl bg-gray-100 hover:bg-gray-200 text-center">
                <i class="fas fa-plus centered"></i> Adicionar Acionista
            </button>
            </div>
            <button id="submit" class="px-3 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">Gerar JSON</button>
            <div class="mt-6">
            <label class="block text-sm font-medium text-gray-600 mb-2">JSON gerado</label>
            <textarea id="output" rows="10" class="w-full p-3 rounded-xl border border-gray-200 bg-white font-mono text-sm" readonly></textarea>
            </div>
        `;
        } else if (view === 'testmonials') {
            const res = await apiClient.post('/search', { db:'workz_data', table:'testmonials', columns:['*'], conditions: { recipient: data.id, recipient_type: type }, fetchAll:true });
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
        return html;
    };



    async function appendWidget(type = 'people', gridList, count) {
        
        // people aqui são IDs; resolvemos antes de tudo
        const resolved = await fetchByIds(gridList, type);            

        count = Number(count) ?? 0;
        const visorCount = count > 0 ? ` (${count})` : '';
        const fontAwesome = type === 'people' ? 'fas fa-user-friends' : type === 'teams' ? 'fas fa-users' : 'fas fa-briefcase';
        const title = type === 'people' ? 'Seguindo' : type === 'teams' ? 'Equipes' : 'Negócios';        

        // monta o grid (ou o vazio) sem ternário com várias linhas
        let gridHtml = '';
        if (count > 0) {            
            const cards = resolved.map(p => `
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
        if (viewType === 'profile') {
            if (userPeople.includes(viewId)) {
                actionContainer.insertAdjacentHTML('beforeend', `
                    <button data-action="unfollow-user" class="cursor-pointer text-center rounded-3xl bg-red-400 hover:bg-red-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Deixar de Seguir</a></button>
                `);
            } else {
                actionContainer.insertAdjacentHTML('beforeend', `
                    <button data-action="follow-user" class="cursor-pointer text-center rounded-3xl bg-blue-400 hover:bg-blue-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Seguir</a></button>
                `);
            }
        } else if (viewType === 'business') {
            const isModerator = (viewData.usmn !== '') ? JSON.parse(viewData.usmn).map(String).includes(String(currentUserData.id)) : '';
            
            // Verifica se o usuário não é gestor na empresa ou moderador
            if(!isManager && !isModerator){                                
                if (userBusinesses.includes(viewId)) {
                    if(memberStatus === 0) {
                        actionContainer.insertAdjacentHTML('beforeend', `
                            <button data-action="cancel-request" class="cursor-pointer text-center rounded-3xl bg-yellow-400 hover:bg-yellow-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Cancelar Pedido</a></button>
                        `);
                    } else {
                        actionContainer.insertAdjacentHTML('beforeend', `
                            <button data-action="cancel-access" class="cursor-pointer text-center rounded-3xl bg-red-400 hover:bg-red-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Cancelar Acesso</a></button>
                        `);
                    }                    
                } else {
                    actionContainer.insertAdjacentHTML('beforeend', `
                        <button data-action="request-join" class="cursor-pointer text-center rounded-3xl bg-green-400 hover:bg-green-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Solicitar Acesso</a></button>
                    `);
                }                
            } else {
                actionContainer.insertAdjacentHTML('beforeend', `
                    <li><button data-sidebar-action="page-settings" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-cog fa-stack-1x text-gray-700"></i></span><span class="truncate">Ajustes</a></span></li>
                `);
            }
                        
        } else if (viewType === 'team') {                                    
            const isModerator = (viewData.usmn !== '') ? JSON.parse(viewData.usmn).map(String).includes(String(currentUserData.id)) : '';

            // Verifica se o usuário não é gestor na empresa ou moderador
            if (!isManager && !isModerator) {
                if (userTeams.includes(viewId)) {
                    if(memberStatus === 0) {
                        actionContainer.insertAdjacentHTML('beforeend', `
                            <button data-action="cancel-request" class="cursor-pointer text-center rounded-3xl bg-yellow-400 hover:bg-yellow-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Cancelar Pedido</a></button>
                        `);
                    } else {
                        actionContainer.insertAdjacentHTML('beforeend', `
                            <button data-action="cancel-access" class="cursor-pointer text-center rounded-3xl bg-red-400 hover:bg-red-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Cancelar Acesso</a></button>
                        `);
                    }
                } else {
                    actionContainer.insertAdjacentHTML('beforeend', `
                        <button data-action="request-join" class="cursor-pointer text-center rounded-3xl bg-green-400 hover:bg-grenn-600 text-white transition-colors truncate w-full p-2 mb-1"><a class="truncate">Solicitar Acesso</a></button>
                    `);
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
            customMenu.insertAdjacentHTML('beforeend', `<li><button data-action="my-profile" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-address-card fa-stack-1x text-gray-700"></i></span><a class="truncate">Meu Perfil</a></button></li>`);
        } else {
            if (viewType === 'profile' && currentUserData.id === viewId) {
                customMenu.insertAdjacentHTML('beforeend', `
                    <li><button data-sidebar-action="page-settings" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-cog fa-stack-1x text-gray-700"></i></span><span class="truncate">Ajustes</a></span></li>
                `);
            } else {
                customMenu.insertAdjacentHTML('beforeend', `
                    <li id="action-container"></li>
                `);
                pageAction();
            }
            customMenu.insertAdjacentHTML('beforeend', `
                <li><button data-action="dashboard" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-home fa-stack-1x text-gray-700"></i></span><a class="truncate">Início</a></button></li>                
                <li><button data-action="share-page" class="cursor-pointer text-left rounded-3xl hover:bg-gray-200 transition-colors truncate w-full pt-1 pb-1 pr-2 flex items-center"><span class="fa-stack text-gray-200 mr-1"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-share fa-stack-1x text-gray-700"></i></span><a class="truncate">Compartilhar</a></button></li>                
            `);
        } 
        
        standardMenu.parentNode.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('button');
            // Ações da navegação principal (fora do #custom-menu)
            if (target.matches('button[href="#people"]')) {
                history.pushState({}, '', '/people');
                $('#loading').fadeIn();
                loadPage();
            } else if (target.matches('button[href="#businesses"]')) {
                history.pushState({}, '', '/businesses');
                $('#loading').fadeIn();
                loadPage();
            } else if (target.matches('button[href="#teams"]')) {
                history.pushState({}, '', '/teams');
                $('#loading').fadeIn();
                loadPage();
            }    
        });

        customMenu.parentNode.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('button');

            // Ações do menu customizável (dentro do #custom-menu)
            const action = target.dataset.action;
            if (!action) return;

            const contextId = (viewType === 'dashboard') ? currentUserData.id : viewId;
            const contextType = viewType;

            switch (action) {
                case 'dashboard':
                    history.pushState({}, '', '/');
                    $('#loading').fadeIn();
                    loadPage();
                    break;                
                case 'my-profile':
                    history.pushState({}, '', `/profile/${contextId}`);
                    $('#loading').fadeIn(); 
                    loadPage();
                    break;
            }

        });
         
        // Gatilhos de botões
        document.getElementById('logout-btn-sidebar').addEventListener('click', handleLogout);
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

    function toggleSidebar(el = null, toggle = true) {

        if (sidebarWrapper.innerHTML.trim() !== '') {
            sidebarWrapper.innerHTML = '';
        }

        console.log(el);

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
                renderTemplate(sidebarContent, templates.sidebarMain, { data: currentUserData });
            } else if (action === 'page-settings') {
                const pageSettingsView = (el.parentNode.dataset.sidebarType === 'current-user') ? 'profile' : viewType;
                const pageSettingsData = (el.parentNode.dataset.sidebarType === 'current-user') ? currentUserData : viewData;

                renderTemplate(sidebarContent, templates.sidebarPageSettings, {                    
                    view: pageSettingsView,
                    data: pageSettingsData
                }, () => {
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
                        }, () => {
                             // ---------- Estado ----------
                            let nextId = 1;
                            /** Estrutura: [{ id, cnpj, children: [...] }] */
                            let roots = [];

                            // ---------- Helpers ----------
                            const onlyDigits = (s) => (s || '').replace(/\D/g, '');
                            const formatCNPJ = (digits) => {
                            const d = onlyDigits(digits).slice(0,14);
                            if (!d) return '';
                            const p = [
                                d.slice(0,2),
                                d.slice(2,5),
                                d.slice(5,8),
                                d.slice(8,12),
                                d.slice(12,14)
                            ];
                            let out = '';
                            if (p[0]) out = p[0];
                            if (p[1]) out += '.' + p[1];
                            if (p[2]) out += '.' + p[2];
                            if (p[3]) out += '/' + p[3];
                            if (p[4]) out += '-' + p[4];
                            return out;
                            };
                            const isValidCNPJLen = (digits) => onlyDigits(digits).length === 14;

                            const newNode = (cnpj='') => ({ id: nextId++, cnpj, children: [] });

                            function findParentAndIndex(id, nodes=roots, parent=null) {
                                for (let i=0;i<nodes.length;i++){
                                    const n = nodes[i];
                                    if (n.id === id) return { parent, nodes, index:i, node:n };
                                    const deep = findParentAndIndex(id, n.children, n);
                                    if (deep) return deep;
                                }
                                return null;
                            }
                            function addChild(id){
                                const info = findParentAndIndex(id);
                                if (!info) return;
                                info.node.children.push(newNode(''));
                                render();
                            }
                            function removeNode(id){
                                const info = findParentAndIndex(id);
                                if (!info) return;
                                info.nodes.splice(info.index, 1);
                                render();
                            }
                            function updateCNPJ(id, value){
                                const info = findParentAndIndex(id);
                                if (!info) return;
                                info.node.cnpj = formatCNPJ(value);                                
                            }
            
                            const treeEl = document.getElementById('tree');

                            function render(){
                                treeEl.innerHTML = '';
                                roots.forEach(node => {
                                    treeEl.appendChild(renderNode(node, 0));
                                });
                            }

                            function renderNode(node, depth){
                                const wrapper = document.createElement('div');
                                wrapper.className = (depth === 0) ? "ml-0" : "ml-3";
                                const row = document.createElement('div');
                                row.className = "flex items-center gap-2";
                                const input = document.createElement('input');
                                input.type = "text";
                                input.value = node.cnpj || '';
                                input.placeholder = "00.000.000/0000-00";
                                input.dataset.id = node.id;
                                input.className = "flex-1 rounded-2xl p-4 focus:outline-none";
                                input.addEventListener('input', e => {
                                    updateCNPJ(node.id, e.target.value);
                                    e.target.value = formatCNPJ(e.target.value);
                                });
                                const addBtn = document.createElement('button');
                                addBtn.textContent = '+';
                                addBtn.className = "w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 text-2xl font-bold";
                                addBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    addChild(node.id)
                                });
                                const rmBtn = document.createElement('button');
                                rmBtn.textContent = '-';
                                rmBtn.className = "w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 text-2xl font-bold";
                                rmBtn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    removeNode(node.id)
                                });
                                row.appendChild(input);
                                row.appendChild(rmBtn);
                                row.appendChild(addBtn);
                                row.appendChild(document.createElement('div')); // spacer
                                wrapper.appendChild(row);
                                if(node.children?.length){
                                    const childrenWrap = document.createElement('div');
                                    childrenWrap.className = "ml-3 border-l border-gray-200";
                                    node.children.forEach(ch=>{
                                    childrenWrap.appendChild(renderNode(ch, depth+1));
                                    });
                                    wrapper.appendChild(childrenWrap);
                                }
                                return wrapper;
                            }


                            // ---------- Inicialização ----------
                            // Exemplo inicial (opcional): uma raiz vazia para começar
                            roots.push(newNode(''));
                            render();

                            // ---------- Controles globais ----------
                            document.getElementById('add-root').addEventListener('click', ()=>{
                                roots.push(newNode(''));
                                render();
                            });

                            document.getElementById('submit').addEventListener('click', ()=>{
                                // Validação básica (opcional): todos preenchidos e 14 dígitos
                                const allNodes = [];
                                (function collect(nodes){
                                    nodes.forEach(n=>{
                                    allNodes.push(n);
                                    collect(n.children);
                                    });
                                })(roots);

                                const invalid = allNodes.filter(n=> !isValidCNPJLen(n.cnpj));
                                if (invalid.length){
                                    alert('Há CNPJ(s) incompletos. Preencha com 14 dígitos (ex.: 12.345.678/0001-90).');
                                    return;
                                }

                                // Serializa removendo ids internos
                                const serialize = (nodes)=> nodes.map(n=> ({
                                    cnpj: onlyDigits(n.cnpj).replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5"),
                                    children: serialize(n.children)
                                }));

                                const json = JSON.stringify(serialize(roots), null, 2);
                                document.getElementById('output').value = json;
                                // Scroll suave até a saída
                                document.getElementById('output').scrollIntoView({ behavior: 'smooth', block: 'start' });
                            });
                        });
                    });
                    document?.querySelector("#employees")?.addEventListener('click', (e) => {
                        renderTemplate(sidebarContent, templates.sidebarPageSettings, {
                            view: 'employees',
                            data: pageSettingsData,
                            type: pageSettingsView
                        }, () => {

                        });
                    });
                    document?.querySelector("#user-jobs")?.addEventListener('click', (e) => {
                        renderTemplate(sidebarContent, templates.sidebarPageSettings, {
                            view: 'user-jobs',
                            data: pageSettingsData
                        }, () => {
                        
                            sidebarContent.addEventListener('change', (e) => {
                                e.preventDefault();
                                const sel = e.target.closest('select[name="type"]');
                                if (!sel) return;
                                const form = sel.closest('.job-form');
                                const disabled = sel.disabled || form?.dataset.readonlyMode === '1';
                                const currentExtraValue = form?.querySelector('[name="third_party"]')?.value || form?.dataset.thirdParty || '';
                                renderOutsourcedRow(sel, { selected: currentExtraValue, disabled });
                            });                            

                            sidebarContent.addEventListener('submit', async (e) => {
                                if (e.target.classList.contains('job-form')) {
                                    e.preventDefault();
                                    const messageContainer = document.getElementById('message');
                                    const form = new FormData(e.target);
                                    const data = Object.fromEntries(form.entries());                                      
                                    data.visibility = e.target.querySelector(`[name="visibility"]`).checked ? 1 : 0;
                                    data.st = e.target.querySelector(`[name="st"]`).checked ? 1 : 0;                                
                                    if (data) {
                                        const result = await apiClient.post('/update', {
                                            db: 'workz_companies',
                                            table: 'employees',
                                            data: data,
                                            conditions: {
                                                id: e.target.dataset.jobId
                                            }
                                        });
                                        if (result) {
                                            renderTemplate(messageContainer, templates.message, { message: 'Experiência profissional atualizada com sucesso!', type: 'success' });
                                        } else {
                                            renderTemplate(messageContainer, templates.message, { message: 'Falha na atualização', type: 'error' });                                            
                                        }
                                    }                                    
                                }
                            });

                            initOutsourcedUI(document.querySelector('.sidebar-content'));
                        });
                    });
                    document?.querySelector("#testmonials")?.addEventListener('click', (e) => {
                        renderTemplate(sidebarContent, templates.sidebarPageSettings, {
                            view: 'testmonials',                            
                            data: pageSettingsData,
                            type: pageSettingsView
                        }, () => {                          
                        });
                    });
                    document?.getElementById('settings-form')?.addEventListener('submit', handleUpdate);
                    initMasks();
                });
            }
        }
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
    // BUSCAS BÁSICAS DE DADOS
    // ===================================================================

    const toInputDate = (d) => {
        if (!d) return '';
        const dt = new Date(d);
        if (Number.isNaN(dt.getTime())) return '';
        const y = dt.getFullYear();
        const m = String(dt.getMonth() + 1).padStart(2, '0');
        const day = String(dt.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };
    
    async function getBusinessName(em = null) {
        if (em == null) return '';
        const res = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'companies',
            columns: ['tt'],
            conditions: { id: em },
            fetchAll: false
        });

        // Se o seu backend retorna { data: { tt: '...' } }:
        return res?.data?.tt ?? '';

        // Se retorna { data: [ { tt: '...' } ] }:
        //return Array.isArray(res?.data) ? (res.data[0]?.tt ?? '') : (res?.data?.tt ?? '');
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
        userBusinessesData = userBusinesses.data;
        userBusinesses = userBusinessesData.map(o => o.em);        

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

        // 3) Conjunto de businesses do usuário (compatível com string/number)
        const userBusinessSet = new Set(
            (userBusinesses || []).map(b =>
                String(typeof b === 'object' ? (b.em ?? b.id ?? b) : b)
            )
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
                people:     { db: 'workz_data', table: 'hus', columns: ['id', 'tt', 'im'], conditions: { st: 1 }, url: 'profile/' },
                teams:      { db: 'workz_companies', table: 'teams', columns: ['id', 'tt', 'im'], conditions: { st: 1 }, url: 'team/' },
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
            list = list.data;
            renderTemplate(workzContent, templates.listView, list, async () => {
                workzContent.querySelectorAll('.list-item').forEach(item => {
                    item.addEventListener('click', async () => {
                        history.pushState({}, '', `/${ entityMap[listType].url + item.dataset.itemId }`);
                        $('#loading').fadeIn();
                        loadPage();                        
                    });
                });
                $('#loading').fadeOut();
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
            const biz = Array.isArray(userBusinesses) ? userBusinesses : [];
            const teams = Array.isArray(userTeams) ? userTeams : [];

            widgetPeople = ppl.slice(0, 6);
            widgetBusinesses = biz.slice(0, 6);
            widgetTeams = teams.slice(0, 6);
            widgetPeopleCount = ppl.length;
            widgetBusinessesCount = biz.length;
            widgetTeamsCount = teams.length;

            entityImage = (currentUserData.im) ? 'data:image/png;base64,' + currentUserData.im : `https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.tt.charAt(0)}`;            

        // OUTRAS ROTAS: define o que buscar
        } else {
            
            let entityMap = {};
            let entitiesToFetch = [];
            if (viewType === 'profile') {
                entityMap = {
                    people:     { db: 'workz_data',      table: 'usg',             target: 's1', conditions: { s0: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' },
                }; 
                entitiesToFetch = ['people', 'businesses', 'teams'];                
            } else if (viewType === 'business') {
                entityMap = {
                    people:     { db: 'workz_companies', table: 'employees',       target: 'us', conditions: { em: entityId }, mainDb: 'workz_data', mainTable: 'hus' },                    
                    teams:      { db: 'workz_companies', table: 'teams',           target: 'id', conditions: { em: entityId, st: 1 } },
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                }; 
                entitiesToFetch = ['people', 'teams'];
            } else if  (viewType === 'team') {
                entityMap = {
                    people:     { db: 'workz_companies', table: 'teams_users',     target: 'us', conditions: { cm: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' }
                }; 
                entitiesToFetch = ['people'];
            }

            // Define o tipo de entidade
            let entityType = (viewType === 'profile') ? 'people' : (viewType === 'business')   ? 'businesses' : 'teams';

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

            let postConditions = { st: 1 };
            let followersConditions = {};
            if (viewType === 'profile') {
                postConditions.us = entityId;
                postConditions.em = 0;
                postConditions.cm = 0;
                followersConditions.s1 = entityId;
            } else if (viewType === 'business') {
                postConditions.em = entityId;
            } else if (viewType === 'team') {
                postConditions.cm = entityId;
            }

            // Obtém o número de publicações
            let postsCount = await apiClient.post('/count', {
                db: 'workz_data',
                table: 'hpl',
                conditions: postConditions                
            });
           
            results.postsCount = postsCount.count;

            const needFollowers = Object.keys(followersConditions).length > 0;
            // Só chama a contagem de seguidores se houver condições
            if (needFollowers) {
                const followersCount = await apiClient.post('/count', {
                    db: 'workz_data',
                    table: 'usg',
                    conditions: { s1: entityId }, // Ex.: quem segue este perfil
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

            if (viewType === 'profile' && results?.teams) {
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

        memberLevel = (viewType === 'team') ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.em)?.nv ?? 0)) : (viewType === 'business') ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.nv ?? 0)) : 0;
        memberStatus = (viewType === 'team') ? Number(parseInt(userTeamsData.find(item => item.cm === viewData.id)?.st ?? 0)) : (viewType === 'business') ? Number(parseInt(userBusinessesData.find(item => item.em === viewData.id)?.st ?? 0)) : 0;

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
                renderTemplate(document.querySelector('#main-content'), templates['entityContent'], { data: entityData.data[0] } )
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

                history.pushState({}, '', `${baseUrl}${id}`);
                $('#loading').fadeIn();
                loadPage();
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

        } else if (viewType === 'profile') {
            orBlocks.push({ us: viewId, cm: 0, em: 0 });
        } else if (viewType === 'business') {
            orBlocks.push({ em: viewId });
        } else if (viewType === 'team') {
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

    async function handleMultipleUpdates(e) {
        e.preventDefault();
        const messageContainer = document.getElementById('message');

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

        let enitityType = (view === 'profile') ? 'people' 
                        : (view === 'business ') ? 'businesses' 
                        : 'teams';

        if (data.id) {            
            const result = await apiClient.post('/update', {
                db: typeMap[enitityType].db,
                table: typeMap[enitityType].table,
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
