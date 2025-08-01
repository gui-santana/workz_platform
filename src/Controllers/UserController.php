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

    public function register(): void
    {
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
        $existingUser = $this->generalModel->search('users', ['username' => $username], false);
        if ($existingUser) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Username already exists.', 'status' => 'error']);
            return;
        }

        $existingEmail = $this->generalModel->search('users', ['email' => $email], false);
        if ($existingEmail) {
            http_response_code(409); // Conflict
            echo json_encode(['message' => 'Email already registered.', 'status' => 'error']);
            return;
        }

        $data = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $userId = $this->generalModel->insert('users', $data);

        if ($userId) {
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'User registered successfully!',
                'status' => 'success',
                'user_id' => $userId
            ]);
        } else {            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to register user.', 'status' => 'error']);
        }
    }
}
