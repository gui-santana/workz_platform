<?php
// CONFIGURAÇÃO PARA LOCALHOST:9090
// Copie este conteúdo para config_db.php se o automático não funcionar

return [
    'host' => 'localhost',
    'dbname' => 'workz_data',
    'username' => 'root',
    'password' => '', // Deixe vazio para XAMPP/WAMP
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
?>