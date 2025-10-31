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
        // Detect platform and initialize SDK with appropriate mode
        const isIframe = window !== window.top;
        const mode = isIframe ? 'embed' : 'standalone';
        
        console.log('Initializing WorkzSDK v2 in', mode, 'mode');
        
        // Initialize the unified SDK
        const success = await window.WorkzSDK.init({ mode });
        
        if (!success) {
            throw new Error('SDK initialization failed');
        }
        
        // Log platform information
        const platform = window.WorkzSDK.getPlatform();
        console.log('Platform detected:', platform.type, platform);
        
        // Wait for SDK to be fully ready
        if (!window.WorkzSDK.isReady()) {
            await new Promise(resolve => {
                window.WorkzSDK.on('sdk:ready', resolve);
            });
        }
        
        console.log('WorkzSDK ready, starting application...');
        
        // Start the application based on platform
        if (platform.type === 'flutter-web') {
            // Flutter web apps will handle their own initialization
            // The SDK is now available globally for Flutter interop
            console.log('Flutter web app detected, SDK ready for interop');
        } else {
            // JavaScript apps - legacy bootstrap support
            if (window.StoreApp && typeof window.StoreApp.bootstrap === 'function') {
                window.StoreApp.bootstrap();
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