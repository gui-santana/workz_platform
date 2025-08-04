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
    public static function handle(): object
    {
        // 1. Pega o cabeçalho de autorização
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Token de autenticação não fornecido.']);
            exit();
        }

        // 2. Extrai o token do formato "Bearer <token>"
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        $tokenParts = explode(' ', $authHeader);

        if (count($tokenParts) !== 2 || $tokenParts[0] !== 'Bearer') {
            http_response_code(401);
            echo json_encode(['error' => 'Formato de token inválido.']);
            exit();
        }

        $jwt = $tokenParts[1];
        $secretKey = $_ENV['JWT_SECRET']; // A mesma chave usada para criar o token

        // 3. Decodifica e valida o token
        try {
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado.', 'details' => $e->getMessage()]);
            exit();
        }
    }
}
