<?php

// api/index.php

// Carrega o autoloader do Composer.
require_once __DIR__ . '/../vendor/autoload.php';

// Importa todas as classes que vamos usar.
use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\TestController;
use Workz\Platform\Controllers\UserController;

// Define o cabeçalho de resposta padrão para JSON.
header("Content-Type: application/json");

// Cria uma nova instância do nosso roteador.
$router = new Router();

// ==================================================
// REGISTRO DE ROTAS
// ==================================================

// Rota de teste
$router->add('GET', '/api/test', [TestController::class, 'index']);
// Rota de Usuário
$router->add('POST', '/api/register', [UserController::class, 'register']);

// ==================================================
// DESPACHO DA REQUISIÇÃO
// ==================================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($uri, $method);
