// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {


    const mainWrapper = document.querySelector("#main-wrapper"); //Main Wrapper

    const apiClient = new ApiClient();    

    let currentUserData = null;
    let userPeople = null;
    let userBusinesses = null;
    let userTeams = null;

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
                return `<div class="bg-green-100 border border-green-400 rounded-3xl p-3 mb-4 text-sm">${message}</div>`;
            } else if (type === 'error') {
                return `<div class="bg-red-100 border border-red-400 rounded-3xl p-3 mb-4 text-sm">${message}</div>`;
            }else if (type === 'warning') {
                return `<div class="bg-yellow-100 border border-yellow-400 rounded-3xl p-3 mb-4 text-sm">${message}</div>`;
            }
            return '';
        },

        dashboard: `            
            <div id="topbar" class="fixed w-full z-1 content-center">
                <div class="max-w-screen-xl mx-auto p-7 px-3 xl:px-0 flex items-center justify-between">
                    <a href="/">
                        <!--img class="logo-menu" style="width: 145px; height: 76px;" title="Workz!" src="/images/logos/workz/145x76.png"-->
                    </a>
                    <img class="page-thumb h-11 w-11 shadow-lg pointer object-cover rounded-full pointer" src="/images/no-image.jpg" />
                </div>
            </div>                  
            <div id="workz-content" class="mt-[132px] max-w-screen-xl px-3 xl:px-0 mx-auto clearfix grid grid-cols-12 gap-6">
                <div class="col-span-12 md:col-span-9 flex flex-col grid grid-cols-12 gap-x-6">
                    <!-- Coluna da Esquerda (Menu de Navegação) -->
                    <aside class="w-full flex col-span-4 md:col-span-3 flex flex-col gap-y-6">                        
                        <div class="aspect-square w-full rounded-full shadow-lg overflow-hidden">
                            <img id="profile-image" class="w-full h-full object-cover" src="/images/no-image.jpg" alt="Imagem da página">
                        </div>
                        <div class="bg-white p-3 rounded-3xl font-semibold shadow-lg grow">
                            <nav class="mt-1">
                                <ul id="custom-menu" class="space-y-3"></ul>
                            </nav>
                            <hr class="mt-3 mb-3">
                            <nav class="mb-1">
                                <ul class="space-y-3">
                                    <li><div href="#pessoas" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100 truncate"><span class="fa-stack"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-user-friends fa-stack-1x fa-inverse"></i></span><a class="truncate">Pessoas</a></div></li>
                                    <li><div href="#businesses" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100"><span class="fa-stack"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-briefcase fa-stack-1x fa-inverse"></i></span><a class="truncate">Negócios</a></div></li>
                                    <li><div href="#" class="rounded-3xl flex items-center gap-2 text-gray-400 cursor-not-allowed truncate"><span class="fa-stack"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-users fa-stack-1x fa-inverse"></i></span><a class="truncate">Equipes</a></div></li>
                                    <li><div href="#" id="logout-btn-sidebar" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100 truncate"><span class="fa-stack"><i class="fas fa-circle fa-stack-2x"></i><i class="fas fa-sign-out-alt fa-stack-1x fa-inverse"></i></span><a class="truncate">Sair</a></div></li>
                                </ul>
                            </nav>
                        </div>
                    </aside>
                    <!-- Coluna do Meio (Conteúdo Principal) -->
                    <main class="col-span-8 md:col-span-9 flex-col relative space-y-6">                        
                        <div id="main-content" class="w-full"></div>
                        <div id="editor-trigger" class="shadow-lg w-full bg-white rounded-3xl text-center"></div>
                    </main>
                    <!-- Feed de Publicações -->
                    <div id="timeline" class="col-span-12 flex flex-col grid grid-cols-12 gap-6 pt-6"></div>
                    <div id="feed-sentinel" class="h-10"></div>
                </div>
                <aside class="col-span-12 md:col-span-3 flex flex-col gap-y-6">                    
                    <div id="widget-people"></div>
                    <div id="widget-businesses"></div>
                    <div id="widget-teams"></div>                    
                </aside>                
            </div>            										
        </div>
        `,

        customMenu: `
            <li>
                <a href="/profile/" ><div class="rounded-3xl flex items-center gap-2 hover:bg-gray-100 truncate">
                    <span class="fa-stack">
                        <i class="fas fa-circle fa-stack-2x"></i>
                        <i class="fas fa-address-card fa-stack-1x fa-inverse"></i>					
                    </span>
                    <a class="truncate">Meu Perfil</a>
                </div></a>
            </li>
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

        sidebarMain: (currentUserData) => `
            <div id="fechar-barra-lateral" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">
                <a>Fechar</a>
                <i class="fas fa-chevron-right"></i>                
            </div>
            <div id="settings-view" class="pointer w-full bg-white shadow-lg rounded-3xl p-4 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-profile-link">
                <div class="grid grid-cols-4 items-center gap-3">
                    <div class="flex col-span-1 justify-center">
                        <img id="sidebar-profile-image" class="w-full rounded-full" src="https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.name.charAt(0)}" alt="Foto do Utilizador">
                    </div>
                    <div class="flex col-span-3 flex-col gap-1">
                        <p class="truncate font-bold">${currentUserData.name}</p>
                        <p class="truncate">${currentUserData.email}</p>
                        <small class="text-gray-500 truncate" >Perfil Workz!, E-mail, Foto, Endereço</small>
                    </div>
                </div>
                <div class="flex justify-end col-span-1">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </div>                            
            <div class="bg-white w-full shadow-lg rounded-2xl p-4 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-th fa-stack-1x fa-inverse"></i>					
                </span>
                Tela de Início
            </div>
            <div class="bg-white w-full shadow-lg rounded-2xl p-4 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-shapes fa-stack-1x fa-inverse"></i>					
                </span>
                Aplicativos
            </div>
            <div class="w-full shadow-lg rounded-2xl grid grid-cols-1">
                <div class="rounded-t-2xl border-b-2 border-black-500 bg-white p-4 border-b-1 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                    <span class="fa-stack gray-500">
                        <i class="fas fa-circle fa-stack-2x"></i>
                        <i class="fas fa-user-friends fa-stack-1x fa-inverse"></i>					
                    </span>
                    Pessoas
                </div>
                <div class="border-b-2 border-black-500 bg-white p-4 border-b-1 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                    <span class="fa-stack gray-500">
                        <i class="fas fa-circle fa-stack-2x"></i>
                        <i class="fas fa-briefcase fa-stack-1x fa-inverse"></i>					
                    </span>
                    Negócios
                </div>
                <div class="rounded-b-2xl bg-white p-4 border-b-1 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">
                    <span class="fa-stack gray-500">
                        <i class="fas fa-circle fa-stack-2x"></i>
                        <i class="fas fa-users fa-stack-1x fa-inverse"></i>					
                    </span>
                    Equipes
                </div>
            </div>
            <div class="bg-white w-full shadow-lg rounded-2xl p-4 flex items-center gap-3 cursor-pointer hover:bg-white/50 transition-all duration-300 ease-in-out" id="sidebar-dashboard-link">                            
                <span class="fa-stack gray-500">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-sign-out-alt fa-stack-1x fa-inverse"></i>					
                </span>
                Sair
            </div>
            <div class="text-center border-t border-gray-200 grid grid-cols-1 gap-1 pt-4">
                <img class="mx-auto" src="https://guilhermesantana.com.br/images/50x50.png" style="height: 40px; width: 40px" alt="Logo de Guilherme Santana"></img>
                <a href="https://guilhermesantana.com.br" target="_blank">Guilherme Santana © 2025</a>
            </div>
        
        `,

        sidebarUserSettings: (currentUserData) => `
            <div id="voltar-main-menu" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">                
                <i class="fas fa-chevron-left"></i>
                <a>Ajustes</a>
            </div>
            <h1 class="text-center text-gray-500 text-xl font-bold">${currentUserData.name}</h1>
            <div class="col-span-1 justify-center">
                <img id="sidebar-profile-image" class="w-1/2 rounded-full mx-auto" src="https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.name.charAt(0)}" alt="Foto do Utilizador">
            </div>
            <form id="settings-form">
                <div class="w-full shadow-lg rounded-2xl grid grid-cols-1">
                    <div class="rounded-t-2xl border-b-2 border-black-500 bg-white border-b-1 grid grid-cols-4">
                        <label for="name" class="col-span-1 p-4 truncate text-gray-500">Nome*</label>
                        <input class="border-none focus:outline-none flex col-span-3 rounded-tr-2xl p-4" type="text" id="name" name="name" value="${currentUserData.name}" required>
                    </div>
                    <div class="border-b-2 border-black-500 bg-white border-b-1 grid grid-cols-4">
                        <label for="email" class="col-span-1 p-4 truncate text-gray-500">E-mail*</label>
                        <input class="border-none focus:outline-none flex col-span-3 p-4" type="email" id="email" name="email" value="${currentUserData.email}" required>
                    </div>
                    <div class="border-b-2 border-black-500 bg-white border-b-1 grid grid-cols-4">
                        <label for="birth" class="col-span-1 p-4 truncate text-gray-500">Nascimento</label>
                        <input class="border-none focus:outline-none flex col-span-3 w-full p-4" type="date" id="birth" name="birth" value="">
                    </div>
                    <div class="rounded-b-2xl bg-white border-b-1 grid grid-cols-4">
                        <label for="cpf" class="col-span-1 p-4 truncate text-gray-500">CPF</label>
                        <input class="border-none focus:outline-none flex col-span-3 rounded-br-2xl p-4" type="number" id="cpf" name="cpf" value="${currentUserData.name}">
                    </div>                
                </div>
                <button type="submit" class="mt-6 w-full py-2 px-4 bg-orange-600 text-white font-semibold rounded-3xl hover:bg-orange-700 transition-colors">Salvar</button>
            </form>                
        `,

        sidebarBusinessSettings: (businessData) => `
            <div id="voltar-main-menu" class="mt-1 text-lg items-center gap-2 cursor-pointer text-gray-600 hover:text-orange flex-row justify-between">                
                <i class="fas fa-chevron-left"></i>
                <a>Ajustes</a>
            </div>
            <h1 class="text-center text-gray-500 text-xl font-bold">${businessData.name}</h1>
            <div class="col-span-1 justify-center">
                <img id="sidebar-profile-image" class="sm:w-1/3 md:w-1/4 lg:w-1/5 shadow-lg cursor-pointer rounded-full mx-auto" src="https://placehold.co/100x100/EFEFEF/333?text=${businessData.name.charAt(0)}" alt="Foto do Utilizador">
            </div>
            <form id="settings-form">
                <div class="w-full shadow-lg rounded-2xl grid grid-cols-1">
                    <div class="rounded-t-2xl border-b-2 border-black-500 bg-white border-b-1 grid grid-cols-4">
                        <label for="name" class="col-span-1 p-4 truncate text-gray-500">Nome*</label>
                        <input class="border-none focus:outline-none flex col-span-3 rounded-tr-2xl p-4" type="text" id="name" name="name" value="${businessData.name}" required>
                    </div>
                    <div class="rounded-b-2xl border-b-2 border-black-500 bg-white border-b-1 grid grid-cols-4">
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
            let html = '<div class="grid grid-cols-12 gap-6">';            
            listItems.forEach(item => {
                html += `
                <div class="list-item sm:col-span-12 md:col-span-6 lg:col-span-4 flex flex-col bg-white p-3 rounded-3xl shadow-lg bg-gray hover:bg-gray-100 cursor-pointer" data-item-id="${item.id}">
                    <div class="flex items-center gap-3">
                        <img class="w-10 h-10 rounded-full" src="https://placehold.co/40x40/EFEFEF/333?text=${item.name.charAt(0)}" alt="${item.name}">
                        <span class="font-semibold">${item.name}</span>
                    </div>                    
                </div> 
                `;
            });
            html += '</div>';
            return html;
        }
    }

    templates.entityContent = async ({ data }) => {
        console.log(data);
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
                <button class="flex flex-col items-center gap-1">
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
            <div id="app-library" class="relative select-none">
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

    templates.widgetGrid = async ({ type = 'people', gridList, count }) => {
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
            <div class="relative rounded-2xl overflow-hidden bg-gray-300 aspect-square">
                <div class="absolute inset-0 bg-center bg-cover" style="background-image: url('${'data:image/png;base64,' + p.im || '/images/default-avatar.jpg'}');"></div>
                <div class="absolute h-full inset-x-0 bottom-0 bg-black/20 text-white font-medium px-2 py-1 truncate">
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

        return `
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
        `;
    };

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

    
    // ===================================================================
    // 🧠 LÓGICA DE INICIALIZAÇÃO
    // ===================================================================

    async function startup(){        
        const loginWrapper = document.querySelector('#login');
        
        const urlToken = new URLSearchParams(window.location.search).get('token');
        if (urlToken) {
            localStorage.setItem('jwt_token', urlToken);
            window.history.replaceState({}, '', '/');
            
        }        
        if (!localStorage.getItem('jwt_token')) {
            renderTemplate(loginWrapper, 'init', null, () => {
                renderLoginUI();
            });
            return;
        }

        // Inicia com os dados do usuário logado
        const isInitialized = await initializeCurrentUserData();
        if (!isInitialized) return;

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
            columns: ['em'],
            conditions: {
                us: currentUserData.id,
                st: 1
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
        userBusinesses = userBusinesses.data.map(o => o.em);        

        // Equipes
        userTeams = await apiClient.post('/search', {
            db: 'workz_companies',
            table: 'teams_users',
            columns: ['cm'],
            conditions: {
                us: currentUserData.id,
                st: 1
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
        userTeams = userTeams.data.map(o => o.cm);        
        
        // Verifica se a URL deve redirecionar a uma página específica
        const path = window.location.pathname;
        const profileMatch = path.match(/^\/profile\/(\d+)$/);
        const businessMatch = path.match(/^\/business\/(\d+)$/);
        const teamMatch = path.match(/^\/team\/(\d+)$/);
        const peopleListMatch = path.match(/^\/people$/);
        const businessListMatch = path.match(/^\/businesses$/);                

        if (peopleListMatch) {
            return;
        } else if (businessListMatch) {
            return;
        } else {
            if (profileMatch) {                
                renderTemplate(mainWrapper, 'dashboard', null, () => {
                    renderView('profile', parseInt(profileMatch[1], 10));
                });  
                return;
            } else if (businessMatch) {
                renderTemplate(mainWrapper, 'dashboard', null, () => {
                    renderView('business', parseInt(businessMatch[1], 10));
                });  
                return;                
            } else if (teamMatch) {
                renderTemplate(mainWrapper, 'dashboard', null, () => {
                    renderView('team', parseInt(teamMatch[1], 10));
                });  
                return;                
            } else {
                renderTemplate(mainWrapper, 'dashboard', null, () => {                    
                    renderView();
                });                
            }
        }        
    }    

    // mapeia type -> banco/tabela    
    
    function uniqueArray(arr) {
        return Array.isArray(arr) ? [...new Set(arr)] : [];
    }

    async function renderView(type = 'dashboard', entity = currentUserData) {
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
        if (type === 'dashboard') {            

            const ppl = Array.isArray(userPeople) ? userPeople : [];
            const biz = Array.isArray(userBusinesses) ? userBusinesses : [];
            const teams = Array.isArray(userTeams) ? userTeams : [];

            widgetPeople = ppl.slice(0, 6);
            widgetBusinesses = biz.slice(0, 6);
            widgetTeams = teams.slice(0, 6);
            widgetPeopleCount = ppl.length;
            widgetBusinessesCount = biz.length;
            widgetTeamsCount = teams.length;

            entityImage = 'data:image/png;base64,' + currentUserData.im;

        // OUTRAS ROTAS: define o que buscar
        } else {
            
            let entityMap = {};
            let entitiesToFetch = [];
            if (type === 'profile') {
                entityMap = {
                    people:     { db: 'workz_data',      table: 'usg',             target: 's1', conditions: { s0: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' },
                }; 
                entitiesToFetch = ['people', 'businesses', 'teams'];                
            } else if (type === 'business') {
                entityMap = {
                    people:     { db: 'workz_companies', table: 'employees',       target: 'us', conditions: { em: entityId }, mainDb: 'workz_data', mainTable: 'hus' },                    
                    teams:      { db: 'workz_companies', table: 'teams',           target: 'id', conditions: { em: entityId, st: 1 } },
                    businesses: { db: 'workz_companies', table: 'employees',       target: 'em', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'companies' },
                }; 
                entitiesToFetch = ['people', 'teams'];
            } else if  (type === 'team') {
                entityMap = {
                    people:     { db: 'workz_companies', table: 'teams_users',     target: 'us', conditions: { cm: entityId }, mainDb: 'workz_data', mainTable: 'hus' },
                    teams:      { db: 'workz_companies', table: 'teams_users',     target: 'cm', conditions: { us: entityId }, mainDb: 'workz_companies', mainTable: 'teams' }
                }; 
                entitiesToFetch = ['people'];
            }

            // Define o tipo de entidade
            let entityType = (type === 'profile') ? 'people' : (type === 'business')   ? 'businesses' : 'teams';

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
                        const [res, num] = await Promise.all([
                            
                            // Primeiro: busca os 6 primeiros registros
                            (async () => {
                                const payload = {
                                    db: cfg.db,
                                    table: cfg.table,
                                    columns: [cfg.target],       // queremos o alvo (ex.: s1)
                                    conditions: cfg.conditions,  // filtrando pela conditions (ex.: s0)
                                    distinct: true,
                                    order: { by: cfg.target, dir: 'DESC' },
                                    fetchAll: true,
                                    limit: 6
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

                            // Segundo: conta todos
                            (async () => {
                                const countPayload = {
                                    db: cfg.db,
                                    table: cfg.table,
                                    conditions: cfg.conditions
                                };

                                if (cfg.mainDb && cfg.mainTable) {
                                    countPayload.exists = [{
                                        db: cfg.mainDb,
                                        table: cfg.mainTable,
                                        local: cfg.target,
                                        remote: 'id',
                                        conditions: { st: 1 }
                                    }];
                                }

                                return apiClient.post('/count', countPayload);
                            })()
                        ]);

                        const list = Array.isArray(res?.data) ? res.data.map(row => row[cfg.target]) : [];
                        const count = num?.data?.count ?? num?.count ?? 0;
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

            let postConditions = { st: 1 };
            let followersConditions = {};
            if (type === 'profile') {
                postConditions.us = entityId;
                postConditions.em = 0;
                postConditions.cm = 0;
                followersConditions.s1 = entityId;
            } else if (type === 'business') {
                postConditions.em = entityId;
            } else if (type === 'team') {
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
           
            entityImage = 'data:image/png;base64,' + entityData.data[0].im ?? '/images/no-image.jpg';

            // Atribuições com fallback
            widgetPeople = results.people ?? [];
            widgetBusinesses = results.businesses ?? [];
            widgetTeams = results.teams ?? [];
            widgetPeopleCount = results.peopleCount ?? 0;
            widgetBusinessesCount = results.businessesCount ?? 0;
            widgetTeamsCount = results.teamsCount ?? 0;        }
        
        Promise.all([            
            // Menu customizado
            renderTemplate(document.querySelector('#custom-menu'), 'customMenu', null),
            // Gatilhos de criação de conteúdo
            renderTemplate(document.querySelector('#editor-trigger'), templates['editorTrigger'], currentUserData),
            
            // Widgets
            widgetPeople.length
                ? renderTemplate(document.querySelector('#widget-people'), templates['widgetGrid'], { gridList: widgetPeople, count: widgetPeopleCount })
                : Promise.resolve(),
            widgetBusinesses.length
                ? renderTemplate(document.querySelector('#widget-businesses'), templates['widgetGrid'], { type: 'businesses', gridList: widgetBusinesses, count: widgetBusinessesCount })
                : Promise.resolve(),
            widgetTeams.length
                ? renderTemplate(document.querySelector('#widget-teams'), templates['widgetGrid'], { type: 'teams', gridList: widgetTeams, count: widgetTeamsCount })
                : Promise.resolve(),

            (type === 'dashboard')
                ? 
                // Conteúdo principal (Dashboard)
                renderTemplate(document.querySelector('#main-content'), 'mainContent', null, async () => {
                    startClock();
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
                    await renderTemplate(document.querySelector('#app-library'), templates.appLibrary, { appsList: userApps }, () => initAppLibrary('#app-library'));
                })
                : Promise.resolve(),
            
            (type !== 'dashboard')
                ?
                // Conteúdo principal (Perfil, Negócio ou Equipe)
                renderTemplate(document.querySelector('#main-content'), templates['entityContent'], { data: entityData.data[0] } )
                : Promise.resolve(),

            // Gatilhos de botões
            document.getElementById('logout-btn-sidebar').addEventListener('click', handleLogout),
            // Imagem da página
            document.querySelector('#profile-image').src = entityImage
        ]).then(() => {                        
            const pageThumbs = document.getElementsByClassName('page-thumb');
            for (let i = 0; i < pageThumbs.length; i++) {
                pageThumbs[i].src = 'data:image/png;base64,' + currentUserData.im;
            }

            // Finalizações
            loadFeed(type, entity);
            initFeedInfiniteScroll(type, entity);
            //topBarScroll();      
            $('#loading').delay(250).fadeOut();                                          
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
        const uniqueIds = [...new Set(ids)].filter(Boolean);
        if (uniqueIds.length === 0) return [];

        // 1) Busca em lote usando IN
        const res = await apiClient.post('/search', {
            db: cfg.db,
            table: cfg.table,
            columns: ['id', 'tt', 'im'],          // ajuste as colunas necessárias
            conditions: { [cfg.idCol]: { op: 'IN', value: uniqueIds } },
            order: { by: 'tt', dir: 'ASC' },
            fetchAll: true,
            limit: uniqueIds.length
        });

        // supondo que a API devolva { data: [...] }
        const list = Array.isArray(res?.data) ? res.data : res;

        // 2) Reordena pra manter a mesma ordem de entrada
        const byId = new Map(list.map(item => [item.id, item]));
        return ids.map(id => byId.get(id) || { id, tt: 'Item', im: '/images/default-avatar.jpg' });
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

    // Estado do feed
    let feedOffset = 0;
    const FEED_PAGE_SIZE = 6;
    let feedLoading = false;
    let feedFinished = false;

    async function loadFeed(entityType = 'dashboard', entityId = null) {

        if (feedLoading || feedFinished) return;
        feedLoading = true;
        
        const orBlocks = [];
        if (entityType === 'dashboard') {
            const followedIds = userPeople;
            if (!followedIds.includes(currentUserData.id)) followedIds.push(currentUserData.id);            
            if (followedIds.length)    orBlocks.push({ us: { op: 'IN', value: followedIds } });
            if (userBusinesses.length) orBlocks.push({ em: { op: 'IN', value: userBusinesses } });
            if (userTeams.length)      orBlocks.push({ cm: { op: 'IN', value: userTeams } });            
        } else if(entityType === 'profile') {            
            orBlocks.push({ us: entityId, cm: 0, em: 0 })
        } else if(entityType === 'business') {            
            orBlocks.push({ em: entityId })
        } else if(entityType === 'team') {            
            orBlocks.push({ cm: entityId })
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

    function initFeedInfiniteScroll(entityType = 'dashboard', entityId = null) {
        const sentinel = document.querySelector('#feed-sentinel');
        if (!sentinel) return;

        const io = new IntersectionObserver((entries) => {
            const [entry] = entries;
            if (entry.isIntersecting) {
            loadFeed(entityType, entityId);
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
    
    async function renderDashboardUI() {
        const loginWrapper = document.querySelector('#login');
        await renderTemplate(loginWrapper, templates.dashboard, null, async () => {
            const mainContent = document.getElementById('main-content');
            const customMenu = document.getElementById('custom-menu');
            const profileImage = document.getElementById('profile-image');
            const sidebarProfileImage = document.getElementById('sidebar-profile-image');
            const logoutBtnSidebar = document.getElementById('logout-btn-sidebar');

            // Renderiza o conteúdo principal do dashboard
            await renderTemplate(mainContent, templates.dashboardMain, currentUserData);

            // Atualiza a imagem de perfil
            if (profileImage) {
                profileImage.src = currentUserData.im || `https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.name.charAt(0)}`;
            }

            // Atualiza a imagem de perfil do sidebar
            if (sidebarProfileImage) {
                sidebarProfileImage.src = currentUserData.im || `https://placehold.co/100x100/EFEFEF/333?text=${currentUserData.name.charAt(0)}`;
            }

            // Adiciona listeners para o menu lateral
            if (logoutBtnSidebar) {
                logoutBtnSidebar.addEventListener('click', handleLogout);
            }
            
            // Renderiza os widgets
            await renderFollowingWidget();
            await renderBusinessesWidget();
        });
           
    }


    startup();


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
    
    /*
    async function searchUserById(userId) {
        try {
            const userData = await apiClient.post('/search', { 
                db: 'workz_data',
                table: 'hus',
                columns: ['id', 'tt', 'ml'],
                conditions: {
                    id: userId
                },
                fetchAll: false
            });
            console.log(userData);
            return userData;
        } catch (error) {
            console.error('Failed to fetch user data by id:', error);
            return null;
        }
    }

    initializeCurrentUserData();
    */
});

//LOADING PAGE
/*
$(window).on('load', function () {	
	$('#loading').delay(250).fadeOut();
});
*/