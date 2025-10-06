<?php
header('Content-Type: application/json');

try {
    // Carregar configuração do banco de dados
    $dbConfig = require_once 'config_db.php';
    
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Testar conexão
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    // Verificar se a tabela hpl existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'hpl'");
    $tableExists = $stmt->fetch() !== false;
    
    // Se a tabela existe, contar registros
    $recordCount = 0;
    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM hpl");
        $recordCount = $stmt->fetch()['count'];
    }
    
    echo json_encode([
        'success' => true,
        'connection' => 'OK',
        'database' => $dbConfig['dbname'],
        'tableExists' => $tableExists,
        'recordCount' => $recordCount,
        'message' => $tableExists ? 
            "Tabela 'hpl' existe com $recordCount registros" : 
            "Tabela 'hpl' não existe - execute create_table.sql"
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro de conexão: ' . $e->getMessage(),
        'code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro geral: ' . $e->getMessage()
    ]);
}
?>