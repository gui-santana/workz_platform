<?php
header('Content-Type: application/json');

// Teste simples
echo json_encode([
    'success' => true,
    'message' => 'PHP funcionando',
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>