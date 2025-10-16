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
    $type = $_POST['type']; // 'image' | 'video' | 'mixed' | numeric
    // Escopo do post: dashboard (cm=0, em=0) ou página de entidade (team/em)
    $cm = isset($_POST['team']) && is_numeric($_POST['team']) ? (int)$_POST['team'] : 0;
    $em = isset($_POST['business']) && is_numeric($_POST['business']) ? (int)$_POST['business'] : 0;
    if ($cm > 0) { $em = 0; }
    if ($em > 0) { $cm = 0; }

    // Privacidade por publicação (opcional)
    $pp = isset($_POST['post_privacy']) && is_numeric($_POST['post_privacy']) ? max(0, min(3, (int)$_POST['post_privacy'])) : null;

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

    // Criar pasta de uploads padronizada em public/uploads/posts/
    $publicRoot = dirname(__DIR__); // .../public
    $uploadSubdir = 'uploads/posts/';
    $uploadDirAbs = $publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $uploadSubdir);
    if (!is_dir($uploadDirAbs)) {
        mkdir($uploadDirAbs, 0755, true);
    }

    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $filePathAbs = $uploadDirAbs . $fileName;
    $relativePath = $uploadSubdir . $fileName;
    $publicUrl = '/' . $relativePath;

    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $filePathAbs)) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao salvar arquivo']);
        return;
    }

    // Mapear tipo para formato aceito pelo banco (inteiro se necessário)
    $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
    $tpDb = isset($tpMap[$type]) ? $tpMap[$type] : (is_numeric($type) ? (int)$type : 0);

    // Preparar conteúdo JSON com suporte a carrossel (array de mídias)
    $content = [
        'media' => [
            [
                'type' => $type,
                'file' => $fileName,
                'path' => $relativePath,
                'url'  => $publicUrl,
                'originalName' => $file['name'],
                'size' => (int)$file['size'],
                'mimeType' => $fileMimeType
            ]
        ]
    ];

    // Salvar no banco de dados
    try {
        // Define status publicado (st = 1) para aparecer no feed
        $stmt = $pdo->prepare("INSERT INTO hpl (us, tp, dt, cm, em, st, ct, post_privacy) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $tpDb,
            $cm,
            $em,
            1,
            json_encode($content, JSON_UNESCAPED_UNICODE),
            $pp
        ]);

        $postId = $pdo->lastInsertId();

        // Log de sucesso
        error_log("Post salvo com sucesso - ID: $postId, Usuário: $userId, Tipo: $type, Arquivo: $fileName");

        echo json_encode([
            'success' => true,
            'postId' => $postId,
            'fileName' => $fileName,
            'filePath' => $relativePath,
            'url' => $publicUrl,
            'message' => 'Post salvo com sucesso!'
        ]);

    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro ao salvar post no banco: " . $e->getMessage());

        // Remover arquivo se erro no banco
        if (isset($filePathAbs) && file_exists($filePathAbs)) {
            unlink($filePathAbs);
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
    $pp = isset($input['post_privacy']) && is_numeric($input['post_privacy']) ? max(0, min(3, (int)$input['post_privacy'])) : null;
    $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
    $tpDb = isset($tpMap[$type]) ? $tpMap[$type] : (is_numeric($type) ? (int)$type : 0);
    $cm = isset($input['team']) && is_numeric($input['team']) ? (int)$input['team'] : 0;
    $em = isset($input['business']) && is_numeric($input['business']) ? (int)$input['business'] : 0;
    if ($cm > 0) { $em = 0; }
    if ($em > 0) { $cm = 0; }

    try {
        // Define status publicado (st = 1) para aparecer no feed
        $stmt = $pdo->prepare("INSERT INTO hpl (us, tp, dt, cm, em, st, ct, post_privacy) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $tpDb,
            $cm,
            $em,
            1,
            json_encode($content, JSON_UNESCAPED_UNICODE),
            $pp
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
