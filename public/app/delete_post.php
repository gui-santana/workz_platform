<?php
// public/app/delete_post.php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Método não permitido']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'ID inválido']);
  exit;
}

// DB
$dbConfig = require_once __DIR__ . '/config_db.php';
try {
  $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
  $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Falha de conexão com o banco']);
  exit;
}

// Buscar post
$stmt = $pdo->prepare('SELECT id, ct FROM hpl WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
  http_response_code(404);
  echo json_encode(['error' => 'Post não encontrado']);
  exit;
}

// Coletar caminhos de mídia do JSON ct
$paths = [];
try {
  $ct = $row['ct'];
  $data = is_string($ct) ? json_decode($ct, true) : (is_array($ct) ? $ct : []);
  if (isset($data['media']) && is_array($data['media'])) {
    foreach ($data['media'] as $m) {
      $rel = '';
      if (is_array($m)) {
        if (!empty($m['path'])) { $rel = (string)$m['path']; }
        elseif (!empty($m['url'])) { $rel = (string)$m['url']; }
      }
      if ($rel === '') continue;
      // Se for URL absoluta, extrair pathname
      if (preg_match('#^https?://#i', $rel)) {
        $parts = parse_url($rel);
        $rel = isset($parts['path']) ? $parts['path'] : '';
      }
      $rel = ltrim($rel, '/\\');
      if ($rel !== '' && stripos($rel, 'uploads/') === 0) {
        $paths[] = $rel;
      }
    }
  }
} catch (Throwable $e) {
  // Ignora erro de parse
}

$publicRoot = dirname(__DIR__); // .../public
$uploadsRoot = $publicRoot . DIRECTORY_SEPARATOR . 'uploads';
$uploadsReal = realpath($uploadsRoot) ?: $uploadsRoot;

$deleted = [];
$errors = [];
foreach (array_unique($paths) as $rel) {
  $abs = $publicRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
  $real = realpath($abs) ?: $abs;
  if (strpos($real, $uploadsReal) !== 0) { $errors[$rel] = 'Caminho não permitido'; continue; }
  if (is_file($real)) {
    if (@unlink($real)) { $deleted[] = $rel; }
    else { $errors[$rel] = 'Falha ao remover'; }
  }
}

// Excluir post
$del = $pdo->prepare('DELETE FROM hpl WHERE id = ?');
$del->execute([$id]);

echo json_encode(['ok' => true, 'id' => $id, 'deletedFiles' => $deleted, 'fileErrors' => $errors]);
exit;

