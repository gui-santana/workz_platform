<?php

// api/index.php

// Ativa a exibição de todos os erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

// Carrega as variáveis do ficheiro .env para $_ENV e $_SERVER
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Importa todas as classes que vamos usar.
use Workz\Platform\Core\Router;
use Workz\Platform\Controllers\AuthController;
use Workz\Platform\Controllers\TestController;
use Workz\Platform\Controllers\UserController;
use Workz\Platform\Controllers\GeneralController;

use Workz\Platform\Middleware\AuthMiddleware;

// Define o cabeçalho de resposta padrão para JSON.
header("Content-Type: application/json");

// --- INÍCIO DO TRATADOR DE ERROS GLOBAL ---
// Tratador de Erros Global
set_exception_handler(function($exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Ocorreu um erro fatal no servidor.',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ]);
    exit();
});
// --- FIM DO TRATADOR DE ERROS GLOBAL ---

// Cria uma nova instância do nosso roteador.
$router = new Router();

// ==================================================
// REGISTRO DE ROTAS
// ==================================================

// Rota não protegida que requer autenticação
$router->add('GET', '/api/me', [UserController::class, 'me']);

// Rotas de autenticação local
$router->add('POST', '/api/register', [AuthController::class, 'register']);
$router->add('POST', '/api/login', [AuthController::class, 'login']);

// Rotas de autenticação social com Google
$router->add('GET', '/api/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
$router->add('GET', '/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Rotas de autenticação social com Microsoft
$router->add('GET', '/api/auth/microsoft/redirect', [AuthController::class, 'redirectToMicrosoft']);
$router->add('GET', '/api/auth/microsoft/callback', [AuthController::class, 'handleMicrosoftCallback']);

// Rota de teste
$router->add('GET', '/api/test', [TestController::class, 'index']);
// Rota de Usuário
$router->add('POST', '/api/register', [UserController::class, 'register']);

// Rotas genéricas de CRUD
$router->add('POST', '/api/insert', [GeneralController::class, 'insert']);
$router->add('POST', '/api/update', [GeneralController::class, 'update']);
$router->add('POST', '/api/search', [GeneralController::class, 'search']);
$router->add('POST', '/api/delete', [GeneralController::class, 'delete']);

// ==================================================
// DESPACHO DA REQUISIÇÃO
// ==================================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($uri, $method);
