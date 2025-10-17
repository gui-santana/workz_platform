<?php
// src/Middleware/AuthMiddleware.php

namespace Workz\Platform\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    /**
     * Verifica o token JWT da requisição.
     * @return object Retorna o payload do token em caso de sucesso.
     */
    public static function handle(): ?object
    {
        $token = null;

        // 1. Tenta pegar o token do cabeçalho 'Authorization: Bearer ...'
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if ($authHeader && preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
            $token = $matches[1];
        }

        // 2. Se não encontrou no cabeçalho, tenta pegar do parâmetro de query '?token=...'
        if (!$token && isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        // Se não encontrou o token em nenhum lugar, nega o acesso.
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token não fornecido.']);
            exit();
        }
        
        // 3. Decodifica e valida o token
        try {
            $secretKey = $_ENV['JWT_SECRET'];
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Formato de token inválido.', 'details' => $e->getMessage()]);
            exit();
        }
    }
}
