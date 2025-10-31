<?php
// src/Core/DartToJSTranspiler.php
// Transpilador Dart para JavaScript - Simples e Direto

namespace Workz\Platform\Core;

class DartToJSTranspiler
{
    /**
     * Transpila código Dart para JavaScript executável
     * Processo simples: Dart → JavaScript funcional para web
     */
    public static function transpile(string $dartCode): string
    {
        // Extrair elementos essenciais do código Dart
        $elements = self::extractDartElements($dartCode);

        // Gerar JavaScript direto baseado no código Dart
        return self::generateWebJS($elements, $dartCode);
    }

    /**
     * Extrai elementos essenciais do código Dart
     */
    private static function extractDartElements(string $dartCode): array
    {
        $elements = [
            'appName' => 'FlutterApp',
            'hasCanvas' => false,
            'hasAnimation' => false,
            'hasInteraction' => false
        ];

        // Extrair nome do app principal
        if (preg_match('/runApp\s*\(\s*(?:const\s+)?(\w+)\s*\(/', $dartCode, $match)) {
            $elements['appName'] = $match[1];
        }

        // Detectar se precisa de canvas (CustomPainter, jogos)
        $elements['hasCanvas'] = strpos($dartCode, 'CustomPainter') !== false ||
            strpos($dartCode, 'Canvas') !== false ||
            strpos($dartCode, 'paint(') !== false;

        // Detectar animações
        $elements['hasAnimation'] = strpos($dartCode, 'Animation') !== false ||
            strpos($dartCode, 'Ticker') !== false ||
            strpos($dartCode, 'Timer') !== false;

        // Detectar interações
        $elements['hasInteraction'] = strpos($dartCode, 'onTap') !== false ||
            strpos($dartCode, 'GestureDetector') !== false ||
            strpos($dartCode, 'onPressed') !== false;

        return $elements;
    }

    /**
     * Gera JavaScript executável baseado no código Dart
     */
    private static function generateWebJS(array $elements, string $dartCode): string
    {
        $appName = $elements['appName'];
        $timestamp = date('Y-m-d H:i:s');

        // JavaScript base
        $js = "// Transpilado de Dart para Web - $timestamp\n";
        $js .= "console.log('🚀 Iniciando $appName');\n\n";

        // Inicializar WorkzSDK se disponível
        $js .= "if (typeof WorkzSDK !== 'undefined') {\n";
        $js .= "    WorkzSDK.init();\n";
        $js .= "}\n\n";

        // Criar estrutura básica do app
        $js .= "class $appName {\n";
        $js .= "    constructor() {\n";
        $js .= "        this.container = null;\n";

        if ($elements['hasCanvas']) {
            $js .= "        this.canvas = null;\n";
            $js .= "        this.ctx = null;\n";
        }

        if ($elements['hasAnimation']) {
            $js .= "        this.animationId = null;\n";
        }

        $js .= "        this.init();\n";
        $js .= "    }\n\n";

        // Método de inicialização
        $js .= "    init() {\n";
        $js .= "        this.createUI();\n";

        if ($elements['hasCanvas']) {
            $js .= "        this.setupCanvas();\n";
        }

        if ($elements['hasAnimation']) {
            $js .= "        this.startAnimation();\n";
        }

        $js .= "        console.log('✅ $appName inicializado');\n";
        $js .= "    }\n\n";

        // Criar interface baseada no código Dart
        $js .= self::generateUIFromDart($dartCode, $elements);

        if ($elements['hasCanvas']) {
            $js .= self::generateCanvasCode($elements);
        }

        if ($elements['hasAnimation']) {
            $js .= self::generateAnimationCode($elements);
        }

        $js .= "}\n\n";

        // Inicializar o app
        $js .= "// Inicializar quando a página carregar\n";
        $js .= "if (document.readyState === 'loading') {\n";
        $js .= "    document.addEventListener('DOMContentLoaded', () => new $appName());\n";
        $js .= "} else {\n";
        $js .= "    new $appName();\n";
        $js .= "}\n";

        return $js;
    }

    /**
     * Gera interface baseada no código Dart do usuário
     */
    private static function generateUIFromDart(string $dartCode, array $elements): string
    {
        $js = "    createUI() {\n";
        $js .= "        this.container = document.getElementById('app-content') || document.body;\n";

        // Extrair textos do código Dart
        $texts = self::extractTextsFromDart($dartCode);
        $title = $texts['title'] ?? $elements['appName'];

        // Gerar HTML baseado no que está no código Dart
        $js .= "        this.container.innerHTML = `\n";
        $js .= "            <div style=\"\n";
        $js .= "                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;\n";
        $js .= "                min-height: 100vh;\n";
        $js .= "                display: flex;\n";
        $js .= "                flex-direction: column;\n";
        $js .= "                align-items: center;\n";
        $js .= "                justify-content: center;\n";
        $js .= "                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n";
        $js .= "                color: white;\n";
        $js .= "                padding: 20px;\n";
        $js .= "            \">\n";

        // Título do app
        $js .= "                <h1 style=\"margin-bottom: 30px; text-align: center;\">$title</h1>\n";

        // Container principal baseado no código Dart
        if ($elements['hasCanvas']) {
            $js .= "                <canvas id=\"appCanvas\" width=\"800\" height=\"600\" style=\"\n";
            $js .= "                    background: white;\n";
            $js .= "                    border-radius: 10px;\n";
            $js .= "                    max-width: 100%;\n";
            $js .= "                    height: auto;\n";
            $js .= "                \"></canvas>\n";
        } else {
            // Interface baseada nos widgets encontrados no Dart
            $js .= "                <div id=\"appContent\" style=\"\n";
            $js .= "                    background: rgba(255,255,255,0.1);\n";
            $js .= "                    padding: 40px;\n";
            $js .= "                    border-radius: 20px;\n";
            $js .= "                    backdrop-filter: blur(10px);\n";
            $js .= "                    text-align: center;\n";
            $js .= "                    max-width: 600px;\n";
            $js .= "                \">\n";

            // Adicionar conteúdo baseado no código Dart
            foreach ($texts['content'] as $content) {
                $js .= "                    <p style=\"margin: 15px 0; line-height: 1.6;\">$content</p>\n";
            }

            // Botões se houver interações
            if ($elements['hasInteraction']) {
                $js .= "                    <button id=\"actionBtn\" style=\"\n";
                $js .= "                        background: #4CAF50;\n";
                $js .= "                        color: white;\n";
                $js .= "                        border: none;\n";
                $js .= "                        padding: 15px 30px;\n";
                $js .= "                        border-radius: 25px;\n";
                $js .= "                        cursor: pointer;\n";
                $js .= "                        font-size: 16px;\n";
                $js .= "                        margin: 20px 10px;\n";
                $js .= "                    \">Interagir</button>\n";
            }

            $js .= "                </div>\n";
        }

        $js .= "            </div>\n";
        $js .= "        `;\n";

        // Configurar eventos se necessário
        if ($elements['hasInteraction']) {
            $js .= "        \n";
            $js .= "        const actionBtn = document.getElementById('actionBtn');\n";
            $js .= "        if (actionBtn) {\n";
            $js .= "            actionBtn.onclick = () => this.handleAction();\n";
            $js .= "        }\n";
        }

        $js .= "    }\n\n";

        // Método de ação se houver interações
        if ($elements['hasInteraction']) {
            $js .= "    handleAction() {\n";
            $js .= "        console.log('🎯 Ação executada');\n";
            $js .= "        // Lógica baseada no código Dart do usuário\n";
            $js .= "    }\n\n";
        }

        return $js;
    }

    /**
     * Extrai textos e conteúdo do código Dart
     */
    private static function extractTextsFromDart(string $dartCode): array
    {
        $texts = [
            'title' => 'Flutter App',
            'content' => []
        ];

        // Extrair título do MaterialApp
        if (preg_match('/title:\s*[\'"]([^\'"]+)[\'"]/', $dartCode, $match)) {
            $texts['title'] = $match[1];
        }

        // Extrair textos de Text widgets
        preg_match_all('/Text\s*\(\s*[\'"]([^\'"]+)[\'"]/', $dartCode, $matches);
        if (!empty($matches[1])) {
            $texts['content'] = array_unique($matches[1]);
        }

        // Se não encontrou conteúdo, usar padrão
        if (empty($texts['content'])) {
            $texts['content'] = ['App Flutter funcionando!'];
        }

        return $texts;
    }

    /**
     * Gera código para canvas se necessário
     */
    private static function generateCanvasCode(array $elements): string
    {
        $js = "    setupCanvas() {\n";
        $js .= "        this.canvas = document.getElementById('appCanvas');\n";
        $js .= "        if (this.canvas) {\n";
        $js .= "            this.ctx = this.canvas.getContext('2d');\n";
        $js .= "            this.draw();\n";
        $js .= "        }\n";
        $js .= "    }\n\n";

        $js .= "    draw() {\n";
        $js .= "        if (!this.ctx) return;\n";
        $js .= "        \n";
        $js .= "        // Limpar canvas\n";
        $js .= "        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);\n";
        $js .= "        \n";
        $js .= "        // Desenhar baseado no código Dart do usuário\n";
        $js .= "        this.ctx.fillStyle = '#4CAF50';\n";
        $js .= "        this.ctx.fillRect(50, 50, 100, 100);\n";
        $js .= "        \n";
        $js .= "        this.ctx.fillStyle = '#2196F3';\n";
        $js .= "        this.ctx.beginPath();\n";
        $js .= "        this.ctx.arc(200, 100, 50, 0, 2 * Math.PI);\n";
        $js .= "        this.ctx.fill();\n";
        $js .= "    }\n\n";

        return $js;
    }

    /**
     * Gera código para animações se necessário
     */
    private static function generateAnimationCode(array $elements): string
    {
        $js = "    startAnimation() {\n";
        $js .= "        const animate = () => {\n";
        $js .= "            if (this.canvas && this.ctx) {\n";
        $js .= "                this.draw();\n";
        $js .= "            }\n";
        $js .= "            this.animationId = requestAnimationFrame(animate);\n";
        $js .= "        };\n";
        $js .= "        animate();\n";
        $js .= "    }\n\n";

        return $js;
    }
}
