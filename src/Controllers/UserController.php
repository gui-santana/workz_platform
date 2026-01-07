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

        // Buscar empresas do usuário
        try {
            $userCompanies = $this->generalModel->search(
                'workz_companies',
                'employees',
                ['em', 'nv', 'st'],
                ['us' => $userId, 'st' => 1], // Apenas vínculos ativos
                true
            );

            $companies = [];
            $companyMap = [];
            if ($userCompanies) {
                foreach ($userCompanies as $employeeRecord) {
                    // Buscar dados da empresa
                    $company = $this->generalModel->search(
                        'workz_companies',
                        'companies',
                        ['id', 'tt', 'national_id'], // tt = nome, national_id = CNPJ
                        ['id' => $employeeRecord['em']],
                        false
                    );
                    
                    if ($company) {
                        $companyId = (int)($company['id'] ?? 0);
                        if ($companyId > 0) {
                            $companyMap[$companyId] = [
                                'id' => $companyId,
                                'name' => $company['tt'], // tt é o nome da empresa
                                'cnpj' => $company['national_id'] ?? '', // national_id é o CNPJ
                                'nv' => $employeeRecord['nv'], // Nível do usuário na empresa
                                'st' => $employeeRecord['st']  // Status do vínculo
                            ];
                        }
                    }
                }
            }

            // Incluir empresas onde o usuário é proprietário (mesmo sem vínculo em employees)
            $ownedCompanies = $this->generalModel->search(
                'workz_companies',
                'companies',
                ['id', 'tt', 'national_id', 'st'],
                ['us' => $userId, 'st' => 1],
                true
            );

            if ($ownedCompanies) {
                foreach ($ownedCompanies as $company) {
                    $companyId = (int)($company['id'] ?? 0);
                    if ($companyId <= 0) {
                        continue;
                    }
                    if (!isset($companyMap[$companyId])) {
                        $companyMap[$companyId] = [
                            'id' => $companyId,
                            'name' => $company['tt'],
                            'cnpj' => $company['national_id'] ?? '',
                            'nv' => 4, // Proprietário
                            'st' => 1
                        ];
                    } else {
                        $companyMap[$companyId]['nv'] = 4;
                    }
                }
            }

            $companies = array_values($companyMap);
            $user['companies'] = $companies;
        } catch (\Throwable $e) {
            // Em caso de erro, continua sem as empresas
            error_log("Erro ao buscar empresas do usuário {$userId}: " . $e->getMessage());
            $user['companies'] = [];
        }

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
