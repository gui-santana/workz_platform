<?php
header('Content-Type: application/json');

// Teste mais simples possível de conexão
try {
    // Tentar configurações mais comuns primeiro
    $configs = [
        ['localhost', 'root', ''],
        ['127.0.0.1', 'root', ''],
        ['localhost', 'root', 'root'],
        ['mysql', 'root', 'password']
    ];
    
    $success = false;
    $workingConfig = null;
    
    foreach ($configs as $config) {
        try {
            $pdo = new PDO("mysql:host={$config[0]};charset=utf8", $config[1], $config[2]);
            $success = true;
            $workingConfig = $config;
            break;
        } catch (Exception $e) {
            continue;
        }
    }
    
    if (!$success) {
        throw new Exception("Nenhuma configuração MySQL funcionou");
    }
    
    // Verificar se banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE 'workz_data'");
    $dbExists = $stmt->fetch() !== false;
    
    echo json_encode([
        'success' => true,
        'mysql_working' => true,
        'working_config' => [
            'host' => $workingConfig[0],
            'username' => $workingConfig[1],
            'password' => $workingConfig[2] ? 'SET' : 'EMPTY'
        ],
        'database_exists' => $dbExists,
        'message' => $dbExists ? 
            'MySQL OK - Banco workz_data existe' : 
            'MySQL OK - Banco workz_data precisa ser criado'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'suggestions' => [
            'Verificar se MySQL está rodando',
            'Verificar credenciais em config_db.php',
            'Tentar configurações em config_db_examples.php'
        ]
    ]);
}
?>