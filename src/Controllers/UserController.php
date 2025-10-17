<?php
// src/Controllers/UserController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;


class UserController
{
    private General $generalModel;
    
    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * Retorna os dados do usuário autenticado.
     * @param object|null $payload O payload do token JWT injetado pelo roteador.
     */
    public function me(?object $payload): void
    {
        header("Content-Type: application/json");
        // O payload contém o ID do usuário em 'sub' (subject)
        $userId = $payload->sub;
        
        $user = $this->generalModel->search('workz_data','hus', ['*'], ['id' => $userId], false);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'Usuário não encontrado.']);
            return;
        }

        // Remove a senha do retorno por segurança
        unset($user['pw']);

        http_response_code(200);
        echo json_encode($user);
    }


    public function register(): void
    {
        header("Content-Type: application/json");
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['username', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $username = $input['username'];
        $email = $input['email'];
        $password = password_hash($input['password'], PASSWORD_DEFAULT); // Hash da senha

        // Verifica se o usuário ou e-mail já existem
        $existingUser = $this->generalModel->search('workz_data', 'hus', ['*'], ['tt' => $username], false);
        if ($existingUser) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Username already exists.', 'status' => 'error']);
            return;
        }

        $existingEmail = $this->generalModel->search('workz_data','hus', ['*'], ['ml' => $email], false);
        if ($existingEmail) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Email already registered.', 'status' => 'error']);
            return;
        }

        $data = [
            'tt' => $username,
            'ml' => $email,
            'pw' => $password,
            'dt' => date('Y-m-d H:i:s')
        ];

        $userId = $this->generalModel->insert('workz_data','hus', $data);

        if ($userId) {
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'User registered successfully!',
                'status' => 'success',
                'user_id' => $userId
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to register user.', 'status' => 'error']);
        }
    }
}
