// public/js/core/app-runner.js

/**
 * Este script é o ponto de entrada para todos os aplicativos embutidos.
 * Ele garante que o WorkzSDK seja inicializado antes que o código do aplicativo seja executado.
 */
(async function() {
    if (typeof window.WorkzSDK === 'undefined') {
        console.error('WorkzSDK não foi encontrado. O aplicativo não pode ser iniciado.');
        return;
    }
    
    try {
        // Inicializa o SDK. Como estamos em um iframe com token na URL, o modo 'standalone' funciona.
        await window.WorkzSDK.init({ mode: 'standalone' });
        
        // Agora que o SDK está pronto, podemos iniciar o aplicativo.
        if (window.StoreApp && typeof window.StoreApp.bootstrap === 'function') {
            window.StoreApp.bootstrap();
        }
    } catch (error) {
        console.error('Falha ao inicializar o WorkzSDK ou o aplicativo:', error);
    }
})();