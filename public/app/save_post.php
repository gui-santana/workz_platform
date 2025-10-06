<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir arquivos de configuração
if (file_exists('sanitize.php')) {
    require_once 'sanitize.php';
}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se é upload de arquivo ou dados do post
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (strpos($contentType, 'multipart/form-data') !== false) {
    // Upload de arquivo
    handleFileUpload($pdo);
} else {
    // Dados do post
    handlePostData($pdo);
}

function handleFileUpload($pdo) {
    if (!isset($_FILES['file']) || !isset($_POST['userId']) || !isset($_POST['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados incompletos']);
        return;
    }

    $file = $_FILES['file'];
    $userId = (int)$_POST['userId'];
    $type = $_POST['type']; // 'image' ou 'video'
    $team = $_POST['team'] ?? '';
    $business = $_POST['business'] ?? '';

    // Validar arquivo
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Erro no upload do arquivo']);
        return;
    }

    // Validar tipo de arquivo
    $allowedTypes = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'video' => ['video/mp4', 'video/webm', 'video/avi', 'video/mov']
    ];

    $fileMimeType = mime_content_type($file['tmp_name']);
    if (!in_array($fileMimeType, $allowedTypes[$type])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de arquivo não permitido']);
        return;
    }

    // Criar pasta de uploads se não existir
    $uploadDir = 'uploads/posts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar arquivo']);
        return;
    }

    // Preparar conteúdo JSON
    $content = [
        'type' => $type,
        'file' => $fileName,
        'path' => $filePath,
        'originalName' => $file['name'],
        'size' => $file['size'],
        'mimeType' => $fileMimeType
    ];

    // Salvar no banco de dados
    try {
        $stmt = $pdo->prepare("INSERT INTO hpl (us, tp, dt, cm, em, ct) VALUES (?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([
            $userId,
            $type,
            $team,
            $business,
            json_encode($content)
        ]);

        $postId = $pdo->lastInsertId();

        // Log de sucesso
        error_log("Post salvo com sucesso - ID: $postId, Usuário: $userId, Tipo: $type, Arquivo: $fileName");

        echo json_encode([
            'success' => true,
            'postId' => $postId,
            'fileName' => $fileName,
            'filePath' => $filePath,
            'message' => 'Post salvo com sucesso!'
        ]);

    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro ao salvar post no banco: " . $e->getMessage());
        
        // Remover arquivo se erro no banco
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar no banco de dados: ' . $e->getMessage()]);
    }
}

function handlePostData($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['userId']) || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados incompletos']);
        return;
    }

    $userId = (int)$input['userId'];
    $content = $input['content'];
    $type = $input['type'] ?? 'mixed';
    $team = $input['team'] ?? '';
    $business = $input['business'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO hpl (us, tp, dt, cm, em, ct) VALUES (?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([
            $userId,
            $type,
            $team,
            $business,
            json_encode($content)
        ]);

        $postId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'postId' => $postId
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar no banco de dados']);
    }
}
?>