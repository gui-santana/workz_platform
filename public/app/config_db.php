<?php
// Configuração do banco de dados (compatível com Docker e .env)

// Tenta carregar variáveis do .env se disponível
$projectRoot = dirname(__DIR__, 2);
$autoload = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists('Dotenv\\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
            $dotenv->safeLoad();
        } catch (Throwable $e) {
            // Silencia erro de dotenv; seguimos com getenv/defaults
        }
    }
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'mysql';
$dbname = $_ENV['DB_NAME'] ?? $_ENV['DB_DATABASE'] ?? getenv('DB_NAME') ?? getenv('DB_DATABASE') ?? 'workz_data';
$username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? 'root_password';
$charset = 'utf8mb4';

return [
    'host' => $host,
    'dbname' => $dbname,
    'username' => $username,
    'password' => $password,
    'charset' => $charset,
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
?>
