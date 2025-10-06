<?php
// Exemplos de configuração de banco de dados
// Copie uma das configurações abaixo para config_db.php

// XAMPP/WAMP (Windows) - Configuração mais comum
$xampp_config = [
    'host' => 'localhost',
    'dbname' => 'workz_data',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// MAMP (Mac)
$mamp_config = [
    'host' => 'localhost',
    'dbname' => 'workz_data',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Docker
$docker_config = [
    'host' => 'mysql', // ou 'db' dependendo do docker-compose
    'dbname' => 'workz_data',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Servidor de produção (exemplo)
$production_config = [
    'host' => 'localhost',
    'dbname' => 'workz_data',
    'username' => 'workz_user',
    'password' => 'senha_segura_aqui',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Para usar uma das configurações acima:
// 1. Copie a configuração desejada
// 2. Cole no arquivo config_db.php substituindo o return [...]
// 3. Ajuste os valores conforme necessário

// Exemplo de como deve ficar o config_db.php:
/*
<?php
return [
    'host' => 'localhost',
    'dbname' => 'workz_data',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
?>
*/
?>