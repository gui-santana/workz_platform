// public/js/main.js

import { ApiClient } from "./core/ApiClient.js";

document.addEventListener('DOMContentLoaded', () => {


    const mainWrapper = document.querySelector("#main-wrapper"); //Main Wrapper

    const apiClient = new ApiClient();    

    let currentUserData = null;

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
                        <img class="logo-menu" style="width: 145px; height: 76px;" title="Workz!" src="/images/logos/workz/145x76.png">
                    </a>
                    <img class="page-thumb h-11 w-11 shadow-lg pointer object-cover rounded-full pointer" src="/images/no-image.jpg" />
                </div>
            </div>                  
            <div id="workz-content" class="mt-[132px] max-w-screen-xl px-3 xl:px-0 mx-auto clearfix grid grid-cols-12 gap-6">
                <div class="col-span-12 md:col-span-8 flex-col grid grid-cols-12 gap-x-6">
                    <!-- Coluna da Esquerda (Menu de Navegação) -->
                    <aside class="w-full flex col-span-4 md:col-span-3 flex flex-col gap-y-6">
                        <img id="profile-image" class="w-full flex rounded-full shadow-lg" src="/images/no-image.jpg" alt="Foto do Utilizador">                        
                        <div class="mt-3 bg-white p-3 rounded-3xl shadow-lg grow font-semibold">
                            <nav class="mt-1">
                                <ul id="custom-menu" class="space-y-3"></ul>
                            </nav>
                            <hr class="mt-3 mb-3">
                            <nav class="mb-1">
                                <ul class="space-y-3">
                                    <li>
                                        <div href="#pessoas" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100 truncate">
                                            <span class="fa-stack">
                                                <i class="fas fa-circle fa-stack-2x"></i>
                                                <i class="fas fa-user-friends fa-stack-1x fa-inverse"></i>					
                                            </span>
                                            <a class="truncate">Pessoas</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div href="#businesses" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100">
                                            <span class="fa-stack">
                                                <i class="fas fa-circle fa-stack-2x"></i>
                                                <i class="fas fa-briefcase fa-stack-1x fa-inverse"></i>					
                                            </span>
                                            <a class="truncate">Negócios</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div href="#" class="rounded-3xl flex items-center gap-2 text-gray-400 cursor-not-allowed truncate">
                                            <span class="fa-stack">
                                                <i class="fas fa-circle fa-stack-2x"></i>
                                                <i class="fas fa-users fa-stack-1x fa-inverse"></i>					
                                            </span>
                                            <a class="truncate">Equipes</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div href="#" id="logout-btn-sidebar" class="rounded-3xl flex items-center gap-2 hover:bg-gray-100 truncate">
                                            <span class="fa-stack">
                                                <i class="fas fa-circle fa-stack-2x"></i>
                                                <i class="fas fa-sign-out-alt fa-stack-1x fa-inverse"></i>	
                                            </span>
                                            <a class="truncate">Sair</a>
                                        </div>
                                    </li>
                                </ul>
                            </nav>                                                                                                    
                        </div>
                    </aside>
                    <!-- Coluna do Meio (Conteúdo Principal) -->
                    <main class="col-span-8 md:col-span-9 flex-col relative space-y-6">                        
                        <div id="main-content" class="w-full shadow-xl rounded-3xl bg-white"></div>
                        <div id="editor-trigger" class="shadow-lg w-full bg-white rounded-3xl text-center"></div>
                    </main>
                    <!-- Feed de Publicações -->
                    <div id="timeline" class="col-span-12 flex flex-col grid grid-cols-12 gap-6"></div>
                </div>
                <aside class="col-span-4 flex flex-col  gap-y-6">                    
                    <div id="widget-people" class="bg-white p-3 rounded-3xl shadow-lg"></div>
                    <div id="widget-teams" class="bg-white p-3 rounded-3xl shadow-lg"></div>
                    <div id="widget-businesses" class="bg-white p-3 rounded-3xl shadow-lg"></div>                    						
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
            <div class="w-full rounded-3xl p-3" style="background-image: url(https://bing.biturl.top/?resolution=1366&amp;format=image&amp;index=0&amp;mkt=en-US); background-position: center; background-repeat: no-repeat; background-size: cover;">
                <div class="w-full pb-3 text-white font-bold content-center text-shadow-lg"><div id="wClock" class="text-lg">00:00</div></div>
                <div id="appContainer" class="rounded-3xl p-3 px-0 bg-white/50 w-full shadow-lg backdrop-blur-sm"></div>
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

        widgetPeople: `        
            <div class="w-full content-center mb-3 mt-1">
                <span class="fa-stack">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-user-friends fa-stack-1x fa-inverse"></i>					
                </span>
                <a class="font-semibold"> Seguindo</a>
            </div>
            <div class="rounded-3xl w-full p-3 truncate flex items-center gap-2" style="background: #F7F8D1;">
                <i class="fas fa-info-circle cm-mg-5-r"></i><a class="truncate">Nenhuma página de usuário.</a>
            </div>                                                            
        `,

        widgetTeams: `
            <div class="w-full content-center mb-3 mt-1">                            
                <span class="fa-stack">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-users fa-stack-1x fa-inverse"></i>
                </span>
                <a class="font-semibold"> Equipes</a>
            </div>                    
            <div class="rounded-3xl w-full p-3 truncate flex items-center gap-2" style="background: #F7F8D1;">
                <i class="fas fa-info-circle cm-mg-5-r"></i><a class="truncate">Nenhuma página de equipe.</a>
            </div>        
        `,

        widgetBusinesses: `
            <div class="w-full content-center mb-3 mt-1">                            
                <span class="fa-stack">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-briefcase fa-stack-1x fa-inverse"></i>
                </span>
                <a class="font-semibold"> Negócios</a>
            </div>                    
            <div class="rounded-3xl w-full p-3 truncate flex items-center gap-2" style="background: #F7F8D1;">
                <i class="fas fa-info-circle cm-mg-5-r"></i><a class="truncate">Nenhuma página de negócio.</a>
            </div>        
        `,

        dashboardMain: (currentUserData) => `
            <div class="bg-white p-4 rounded-3xl shadow-lg mb-6">                
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
                container.innerHTML = template(data);
            } else {
                console.error('Template inválido.');
                return; // Sai se o template for inválido
            }
            // Executa o callback DEPOIS que o HTML foi inserido no DOM
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
        console.log(localStorage);
        if (!localStorage.getItem('jwt_token')) {
            renderTemplate(loginWrapper, 'init', null, () => {
                renderLoginUI();
            });
            return;
        }

        // Inicia com os dados do usuário logado
        const isInitialized = await initializeCurrentUserData();
        if (!isInitialized) return;

        // Verifica se a URL deve redirecionar a uma página específica
        const path = window.location.pathname;
        const profileMatch = path.match(/^\/profile\/(\d+)$/);
        const businessMatch = path.match(/^\/business\/(\d+)$/);
        const peopleListMatch = path.match(/^\/people$/);
        const businessListMatch = path.match(/^\/businesses$/);
        
        console.log(currentUserData);

        if (peopleListMatch) {
            return;
        } else if (businessListMatch) {
            return;
        } else {            
            if (profileMatch) {
                return;
            } else if (businessMatch) {                    
                return;
            }else{
                renderTemplate(mainWrapper, 'dashboard', null, () => {
                    $('#loading').delay(250).fadeOut();
                    // Render widgets em paralelo
                    Promise.all([
                        renderTemplate(document.querySelector('#custom-menu'), 'customMenu', null),
                        renderTemplate(document.querySelector('#main-content'), 'mainContent', null, () => {
                            startClock();
                        }),
                        renderTemplate(document.querySelector('#editor-trigger'), templates['editorTrigger'], currentUserData),
                        renderTemplate(document.querySelector('#widget-people'), 'widgetPeople', null),
                        renderTemplate(document.querySelector('#widget-teams'), 'widgetTeams', null),
                        renderTemplate(document.querySelector('#widget-businesses'), 'widgetBusinesses', null)
                    ]).then(() => {
                        // Eventos e interações
                        document.getElementById('logout-btn-sidebar').addEventListener('click', handleLogout);
                        

                        document.querySelector('#profile-image').src = 'data:image/png;base64,' + currentUserData.im;

                        // Só agora altera a imagem dos thumbs
                        const pageThumbs = document.getElementsByClassName('page-thumb');
                        for (let i = 0; i < pageThumbs.length; i++) {
                            pageThumbs[i].src = 'data:image/png;base64,' + currentUserData.im;
                        }

                        // Finalizações                        
                        topBarScroll();
                        
                    });             
                });                
            }         
        }

        

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