<?php
header('Content-Type: application/json');

try {
    // Carregar configuração
    $dbConfig = require_once 'config_db.php';
    
    // Tentar conectar sem especificar o banco primeiro
    $dsn = "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    $results = [];
    
    // 1. Verificar se o banco existe
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbConfig['dbname']}'");
    $dbExists = $stmt->fetch() !== false;
    
    if (!$dbExists) {
        // Criar banco se não existir
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbConfig['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $results[] = "Banco de dados '{$dbConfig['dbname']}' criado";
    } else {
        $results[] = "Banco de dados '{$dbConfig['dbname']}' já existe";
    }
    
    // 2. Conectar ao banco específico
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // 3. Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'hpl'");
    $tableExists = $stmt->fetch() !== false;
    
    if (!$tableExists) {
        // Criar tabela
        $createTableSQL = "
        CREATE TABLE `hpl` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `us` int(11) NOT NULL COMMENT 'ID do usuário',
          `tp` varchar(50) NOT NULL COMMENT 'Tipo do post (image, video, mixed)',
          `dt` datetime NOT NULL COMMENT 'Data de criação',
          `cm` varchar(255) DEFAULT NULL COMMENT 'Equipe',
          `em` varchar(255) DEFAULT NULL COMMENT 'Negócio',
          `ct` longtext NOT NULL COMMENT 'Conteúdo em JSON',
          `post_privacy` tinyint(1) NULL DEFAULT NULL COMMENT '0=me;1=followers/moderators;2=logged/members;3=public',
          PRIMARY KEY (`id`),
          KEY `idx_user` (`us`),
          KEY `idx_type` (`tp`),
          KEY `idx_date` (`dt`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de posts do editor'
        ";
        
        $pdo->exec($createTableSQL);
        $results[] = "Tabela 'hpl' criada com sucesso";
    } else {
        $results[] = "Tabela 'hpl' já existe";
    }
    
    // 4a. Garantir coluna post_privacy em tabelas existentes
    try {
        $pdo->exec("ALTER TABLE `hpl` ADD COLUMN IF NOT EXISTS `post_privacy` tinyint(1) NULL DEFAULT NULL COMMENT '0=me;1=followers/moderators;2=logged/members;3=public'");
        $results[] = "Coluna 'post_privacy' garantida";
    } catch (Exception $e) {
        // Ignorar erros de versões antigas do MySQL sem IF NOT EXISTS; tentar detecção manual
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `hpl` LIKE 'post_privacy'")->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE `hpl` ADD COLUMN `post_privacy` tinyint(1) NULL DEFAULT NULL COMMENT '0=me;1=followers/moderators;2=logged/members;3=public'");
                $results[] = "Coluna 'post_privacy' adicionada";
            }
        } catch (Exception $ie) { /* noop */ }
    }

    // 4. Testar inserção
    $testData = [
        'us' => 1,
        'tp' => 'test',
        'dt' => date('Y-m-d H:i:s'),
        'cm' => 'Setup Test',
        'em' => 'Database Setup',
        'ct' => json_encode(['type' => 'setup_test', 'timestamp' => time()])
    ];
    
    $stmt = $pdo->prepare("INSERT INTO hpl (us, tp, dt, cm, em, ct) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(array_values($testData));
    $testId = $pdo->lastInsertId();
    
    $results[] = "Teste de inserção realizado - ID: {$testId}";
    
    // 5. Limpar teste
    $pdo->prepare("DELETE FROM hpl WHERE id = ?")->execute([$testId]);
    $results[] = "Registro de teste removido";
    
    // 5b. Criar tabela de comentários, se necessário
    $stmt = $pdo->query("SHOW TABLES LIKE 'hpl_comments'");
    $commentsTableExists = $stmt->fetch() !== false;
    if (!$commentsTableExists) {
        $createCommentsSQL = "
        CREATE TABLE `hpl_comments` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `pl` int(11) NOT NULL COMMENT 'ID do post (hpl.id)',
          `us` int(11) NOT NULL COMMENT 'ID do usuário',
          `ds` text NOT NULL COMMENT 'Conteúdo do comentário',
          `dt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do comentário',
          PRIMARY KEY (`id`),
          KEY `idx_post` (`pl`),
          KEY `idx_user` (`us`),
          KEY `idx_dt` (`dt`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comentários de posts do editor';
        ";
        $pdo->exec($createCommentsSQL);
        $results[] = "Tabela 'hpl_comments' criada com sucesso";
    } else {
        $results[] = "Tabela 'hpl_comments' já existe";
    }

    // 6. Contar registros existentes
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hpl");
    $count = $stmt->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Banco de dados configurado com sucesso!',
        'results' => $results,
        'existing_posts' => $count,
        'database' => $dbConfig['dbname'],
        'host' => $dbConfig['host']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro de banco de dados: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'suggestion' => 'Verifique as credenciais em config_db.php'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro geral: ' . $e->getMessage()
    ]);
}
?>
