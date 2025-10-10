<?php
// public/apps/tasks/api/tasks.php

header('Content-Type: application/json');

// Resolve project root and autoload
$root = realpath(__DIR__ . '/../../../..') ?: dirname(__DIR__, 4);
if (!is_dir($root)) { http_response_code(500); echo json_encode(['error' => 'Root path not found']); exit; }

$autoload = $root . '/vendor/autoload.php';
if (file_exists($autoload)) { require_once $autoload; }

// Load env (safe)
if (class_exists('Dotenv\\Dotenv')) {
    try { Dotenv\Dotenv::createImmutable($root)->safeLoad(); } catch (Throwable $e) {}
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function get_bearer_token(): ?string {
    // Authorization: Bearer <token>
    $headers = [];
    if (function_exists('getallheaders')) { $headers = getallheaders(); }
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) { return trim($m[1]); }
    // fallback via query/body
    if (!empty($_GET['token'])) return (string)$_GET['token'];
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json['token'])) return (string)$json['token'];
    }
    return null;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Verify SSO token (HS256 for now)
$secret = $_ENV['JWT_SECRET'] ?? '';
if (!$secret) { http_response_code(500); echo json_encode(['error' => 'JWT not configured']); exit; }

$token = get_bearer_token();
if (!$token) { http_response_code(401); echo json_encode(['error' => 'Missing token']); exit; }

try {
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
} catch (Throwable $e) {
    http_response_code(401); echo json_encode(['error' => 'Invalid token']); exit;
}

$now = time();
if (!empty($decoded->exp) && $decoded->exp < $now) {
    http_response_code(401); echo json_encode(['error' => 'Token expired']); exit;
}

// Basic audience check (accept any app:* audience; optional tighten later)
$aud = isset($decoded->aud) ? (string)$decoded->aud : '';
if (strpos($aud, 'app:') !== 0) {
    http_response_code(403); echo json_encode(['error' => 'Invalid audience']); exit;
}

$userId = isset($decoded->sub) ? (int)$decoded->sub : 0;
$ctx = isset($decoded->ctx) && is_object($decoded->ctx) ? $decoded->ctx : null;
if (!$ctx && isset($decoded->ctx) && is_array($decoded->ctx)) { $ctx = (object)$decoded->ctx; }
if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

// Determine scope filters from context
$col = null; $ctxId = null;
if ($ctx && isset($ctx->type) && isset($ctx->id)) {
    if ($ctx->type === 'user') { $col = 'us'; $ctxId = (int)$ctx->id; }
    elseif ($ctx->type === 'business') { $col = 'em'; $ctxId = (int)$ctx->id; }
    elseif ($ctx->type === 'team') { $col = 'cm'; $ctxId = (int)$ctx->id; }
}
if (!$col || !$ctxId) { $col = 'us'; $ctxId = $userId; }

// Init SQLite storage
$appStorageDir = $root . '/storage/apps/tasks';
if (!is_dir($appStorageDir)) { @mkdir($appStorageDir, 0775, true); }
$dbPath = $appStorageDir . '/tasks.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA busy_timeout=5000;');

    // Create tables if not exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        us INTEGER,
        em INTEGER,
        cm INTEGER,
        title TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'open',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_scope ON tasks(us, em, cm);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks(status);");
} catch (Throwable $e) {
    http_response_code(500); echo json_encode(['error' => 'Storage error']); exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? null;
if ($method === 'POST') {
    $body = json_input();
    if (isset($body['action'])) $action = $body['action'];
}

try {
    if ($method === 'GET' && $action === 'list') {
        $stmt = $pdo->prepare("SELECT id, title, status, created_at FROM tasks WHERE $col = :id ORDER BY id DESC LIMIT 200");
        $stmt->execute([':id' => $ctxId]);
        $rows = $stmt->fetchAll();
        echo json_encode(['data' => $rows]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $body = json_input();
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') { http_response_code(400); echo json_encode(['error' => 'title is required']); exit; }
        $columns = ['title' => $title, 'status' => 'open', $col => $ctxId];
        $sql = "INSERT INTO tasks (title, status, $col) VALUES (:title, :status, :ctx)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':title' => $columns['title'], ':status' => $columns['status'], ':ctx' => $columns[$col]]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['data' => ['id' => $id, 'title' => $title, 'status' => 'open']]);
        exit;
    }

    if ($method === 'POST' && $action === 'toggle') {
        $body = json_input();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id is required']); exit; }
        // Ensure scope ownership
        $stmt = $pdo->prepare("SELECT id, status FROM tasks WHERE id = :id AND $col = :ctx LIMIT 1");
        $stmt->execute([':id' => $id, ':ctx' => $ctxId]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error' => 'Task not found']); exit; }
        $new = ($row['status'] === 'open') ? 'done' : 'open';
        $up = $pdo->prepare("UPDATE tasks SET status = :st WHERE id = :id");
        $up->execute([':st' => $new, ':id' => $id]);
        echo json_encode(['data' => ['id' => $id, 'status' => $new]]);
        exit;
    }

    if ($method === 'POST' && $action === 'delete') {
        $body = json_input();
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'id is required']); exit; }
        $del = $pdo->prepare("DELETE FROM tasks WHERE id = :id AND $col = :ctx");
        $del->execute([':id' => $id, ':ctx' => $ctxId]);
        echo json_encode(['data' => true]);
        exit;
    }

    // default
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error']);
}

