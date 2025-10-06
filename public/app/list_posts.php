<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Carregar configuração do banco de dados
$dbConfig = require_once 'config_db.php';

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    error_log("Erro de conexão com banco: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro de conexão com banco de dados']);
    exit;
}

try {
    // Buscar posts ordenados por data (mais recentes primeiro)
    $stmt = $pdo->prepare("SELECT id, us, tp, dt, cm, em, ct FROM hpl ORDER BY dt DESC LIMIT 50");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    
    // Processar posts para incluir informações decodificadas
    $processedPosts = [];
    foreach ($posts as $post) {
        $content = json_decode($post['ct'], true);
        
        $processedPost = [
            'id' => $post['id'],
            'userId' => $post['us'],
            'type' => $post['tp'],
            'date' => $post['dt'],
            'team' => $post['cm'],
            'business' => $post['em'],
            'content' => $content
        ];
        
        // Adicionar URL completo para arquivos
        if (isset($content['file'])) {
            $processedPost['fileUrl'] = 'uploads/posts/' . $content['file'];
        }
        
        $processedPosts[] = $processedPost;
    }
    
    echo json_encode([
        'success' => true,
        'posts' => $processedPosts,
        'total' => count($processedPosts)
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar posts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar posts']);
}
?>