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
use Workz\Platform\Controllers\TeamsController;
use Workz\Platform\Controllers\PostsController;
use Workz\Platform\Controllers\CompaniesController;

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

// Rota protegida por middleware (Ok)
$router->add('GET', '/api/me', [UserController::class, 'me'], [AuthMiddleware::class, 'handle']);

// Rotas de autenticação local (Ok)
$router->add('POST', '/api/register', [AuthController::class, 'register']);
$router->add('POST', '/api/login', [AuthController::class, 'login']);

// Rotas de autenticação social com Google
$router->add('GET', '/api/auth/google/redirect', [AuthController::class, 'redirectToGoogle']);
$router->add('GET', '/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Rotas de autenticação social com Microsoft
$router->add('GET', '/api/auth/microsoft/redirect', [AuthController::class, 'redirectToMicrosoft']);
$router->add('GET', '/api/auth/microsoft/callback', [AuthController::class, 'handleMicrosoftCallback']);

// Rotas genéricas de CRUD
$router->add('POST', '/api/insert', [GeneralController::class, 'insert']);
$router->add('POST', '/api/update', [GeneralController::class, 'update']);
$router->add('POST', '/api/search', [GeneralController::class, 'search']);
$router->add('POST', '/api/count', [GeneralController::class, 'count']);
$router->add('POST', '/api/delete', [GeneralController::class, 'delete']);


// Rota para alterar o e-mail
$router->add('POST', '/api/change-email', [GeneralController::class, 'changeEmail']);
// Rota para alterar a senha
$router->add('POST', '/api/change-password', [GeneralController::class, 'changePassword']);
$router->add('POST', '/api/upload-image', [GeneralController::class, 'uploadImage'], [AuthMiddleware::class, 'handle']);

// Rotas de equipes (protegidas)
$router->add('POST', '/api/teams/delete', [TeamsController::class, 'delete'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/teams/members/level', [TeamsController::class, 'updateMemberLevel'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/teams/members/accept', [TeamsController::class, 'acceptMember'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/teams/members/reject', [TeamsController::class, 'rejectMember'], [AuthMiddleware::class, 'handle']);

// Rotas de negócios (protegidas)
$router->add('POST', '/api/companies/members/level', [CompaniesController::class, 'updateMemberLevel'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/companies/members/accept', [CompaniesController::class, 'acceptMember'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/companies/members/reject', [CompaniesController::class, 'rejectMember'], [AuthMiddleware::class, 'handle']);

// Posts (criar e feed)
$router->add('POST', '/api/posts', [PostsController::class, 'create'], [AuthMiddleware::class, 'handle']);
$router->add('POST', '/api/posts/feed', [PostsController::class, 'feed'], [AuthMiddleware::class, 'handle']);
// Upload de mídias para posts (imagens/vídeos, múltiplos arquivos)
$router->add('POST', '/api/posts/media', [PostsController::class, 'uploadMedia'], [AuthMiddleware::class, 'handle']);

// ==================================================
// DESPACHO DA REQUISIÇÃO
// ==================================================

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($uri, $method);



