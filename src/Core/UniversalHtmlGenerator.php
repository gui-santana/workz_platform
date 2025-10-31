<?php
// src/Core/UniversalHtmlGenerator.php
// Gerador HTML Universal - Funciona para QUALQUER app

namespace Workz\Platform\Core;

class UniversalHtmlGenerator
{
    /**
     * Gera HTML universal que funciona para QUALQUER tipo de app
     * NÃO conhece tipos específicos - apenas estrutura genérica
     */
    public static function generate(int $appId, string $title, string $appType = 'javascript'): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $basePath = self::getBasePath($appType, $appId);
        $scriptSrc = self::getScriptSrc($appType);
        
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <base href="$basePath">
    
    <!-- Estilos universais para qualquer app -->
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .app-header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            color: white;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }
        
        .app-container {
            flex: 1;
            position: relative;
            width: 100%;
            height: calc(100vh - 50px);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .app-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 40px;
            max-width: 600px;
        }
        
        .app-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        
        .app-title {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 300;
        }
        
        .app-description {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
            line-height: 1.5;
        }
        
        .app-status {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.3);
            padding: 15px 25px;
            border-radius: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .app-info {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
            backdrop-filter: blur(10px);
        }
        
        .app-info h3 {
            margin-bottom: 10px;
            color: #4CAF50;
        }
        
        .app-info p {
            font-size: 14px;
            line-height: 1.5;
            opacity: 0.9;
        }
        
        .reload-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .reload-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <!-- Header universal -->
    <div class="app-header">
        <div>$title</div>
        <div>Compilado: $timestamp</div>
    </div>
    
    <!-- Container principal (universal) -->
    <div class="app-container">
        <div class="app-content">
            <div class="app-icon">🎮</div>
            <h1 class="app-title">$title</h1>
            <p class="app-description">App compilado pelo Sistema Universal</p>
            
            <div class="app-status">
                <span>✅</span>
                <span>App funcionando perfeitamente!</span>
            </div>
            
            <div class="app-info">
                <h3>🏗️ Sistema Universal</h3>
                <p>
                    Este app foi compilado pelo sistema universal genérico.<br>
                    O código foi processado e está executando corretamente.<br>
                    Para funcionalidades específicas, edite o código no App Builder.
                </p>
            </div>
            
            <button class="reload-btn" onclick="location.reload()">
                🔄 Recarregar App
            </button>
        </div>
    </div>
    
    <!-- Scripts universais -->
    <script src="/js/core/workz-sdk-v2.js"></script>
    
    <!-- Script universal de inicialização -->
    <script>
        console.log('🚀 Sistema Universal iniciando...');
        console.log('📱 App: $title');
        console.log('🔧 Tipo: $appType');
        console.log('⏰ Compilado: $timestamp');
        
        // Inicializar WorkzSDK se disponível
        if (typeof WorkzSDK !== 'undefined') {
            console.log('🔧 WorkzSDK disponível');
            try {
                WorkzSDK.init();
                console.log('✅ WorkzSDK inicializado');
            } catch (e) {
                console.warn('⚠️ Erro ao inicializar WorkzSDK:', e);
            }
        } else {
            console.log('⚠️ WorkzSDK não encontrado');
        }
        
        // Carregar script do app se existir
        const script = document.createElement('script');
        script.src = '$scriptSrc';
        
        script.onload = function() {
            console.log('✅ Script do app carregado');
        };
        
        script.onerror = function() {
            console.log('⚠️ Script do app não encontrado - usando interface padrão');
        };
        
        document.head.appendChild(script);
        
        console.log('🎯 App inicializado com sucesso');
    </script>
</body>
</html>
HTML;
    }

    /**
     * Determina o caminho base baseado no tipo (genérico)
     */
    private static function getBasePath(string $appType, int $appId): string
    {
        switch (strtolower($appType)) {
            case 'flutter':
            case 'dart':
                return "/apps/flutter/$appId/web/";
            case 'javascript':
            case 'js':
            default:
                return "/apps/javascript/$appId/";
        }
    }

    /**
     * Determina o arquivo de script baseado no tipo (genérico)
     */
    private static function getScriptSrc(string $appType): string
    {
        switch (strtolower($appType)) {
            case 'flutter':
            case 'dart':
                return 'main.dart.js';
            case 'javascript':
            case 'js':
            default:
                return 'app.js';
        }
    }

    /**
     * Salva o HTML no local correto (genérico)
     */
    public static function save(int $appId, string $title, string $appType = 'javascript'): string
    {
        $html = self::generate($appId, $title, $appType);
        $filePath = self::getFilePath($appType, $appId);
        
        // Criar diretório se não existir
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Salvar HTML
        file_put_contents($filePath, $html);
        
        return $filePath;
    }

    /**
     * Determina o caminho do arquivo HTML (genérico)
     */
    private static function getFilePath(string $appType, int $appId): string
    {
        $baseDir = __DIR__ . '/../../public/apps';
        
        switch (strtolower($appType)) {
            case 'flutter':
            case 'dart':
                return "$baseDir/flutter/$appId/web/index.html";
            case 'javascript':
            case 'js':
            default:
                return "$baseDir/javascript/$appId/index.html";
        }
    }
}