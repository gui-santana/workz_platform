<?php
// src/Controllers/UserController.php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Models\General;

class GeneralController
{
    private General $generalModel;
    
    public function __construct()
    {
        $this->generalModel = new General();
    }

    public function insert(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'data'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $data = $input['data'];

        $id = $this->generalModel->insert($db, $table, $data);

        if ($id) {
            http_response_code(201); // Created
            echo json_encode([
                'message' => 'Record inserted successfully!',
                'status' => 'success',
                'id' => $id
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to insert record.', 'status' => 'error']);
        }        
    }

    public function update(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'data', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $data = $input['data'];
        $conditions = $input['conditions'];                

        $success = $this->generalModel->update($db, $table, $data, $conditions);

        if ($success) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record updated successfully!',
                'status' => 'success'
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to update record.', 'status' => 'error']);
        }        
    }

    public function search(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }        
        
        $requiredFields = ['db', 'table'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $columns    = $input['columns']    ?? ['*'];
        $conditions = $input['conditions'] ?? [];
        $fetchAll = $input['fetchAll'] ?? true;
        // coleta limit/offset, se existirem
        $limit  = isset($input['limit'])  ? (int)$input['limit']  : null;
        $offset = isset($input['offset']) ? (int)$input['offset'] : null;
        $order  = isset($input['order'])  ? $input['order']  : null;
        $distinct = isset($input['distinct']) ? $input['distinct'] : null;
        $exists = isset($input['exists']) ? $input['exists'] : [];

        $results = $this->generalModel->search($db, $table, $columns, $conditions, $fetchAll, $limit, $offset, $order, $distinct, $exists);

        if ($results !== false) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Records retrieved successfully!',
                'status' => 'success',
                'data' => $results,
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset
                ]
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to retrieve records.', 'status' => 'error']);
        }

        exit();
    }

    public function count(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $conditions = $input['conditions'];
        $distinctCol = isset($input['distinctCol']) ? $input['distinctCol'] : null;
        $exists = isset($input['exists']) ? $input['exists'] : [];

        $count = $this->generalModel->count($db, $table, $conditions, $distinctCol, $exists);

        if ($count !== false) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record count retrieved successfully!',
                'status' => 'success',
                'count' => $count
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to retrieve record count.', 'status' => 'error']);
        }        
    }
    

    public function delete(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            return;
        }

        $requiredFields = ['db', 'table', 'conditions'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['message' => ucfirst($field) . ' is required.', 'status' => 'error']);
                return;
            }
        }

        $db = $input['db'];
        $table = $input['table'];
        $conditions = $input['conditions'];

        $success = $this->generalModel->delete($db, $table, $conditions);

        if ($success) {
            http_response_code(200); // OK
            echo json_encode([
                'message' => 'Record deleted successfully!',
                'status' => 'success'
            ]);
        } else {            
            http_response_code(500); // Internal Server Error
            echo json_encode(['message' => 'Failed to delete record.', 'status' => 'error']);
        }        
    }

    public function uploadImage(): void
    {
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = $_POST['entity_id'] ?? '';
        $imageType = $_POST['image_type'] ?? 'im';

        $allowedTypes = ['people', 'businesses', 'teams'];
        if (!in_array($entityType, $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de entidade inválido.']);
            return;
        }

        $entityId = filter_var($entityId, FILTER_VALIDATE_INT);
        if (!$entityId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador inválido.']);
            return;
        }

        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo de imagem não enviado.']);
            return;
        }

        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao receber o arquivo de imagem.']);
            return;
        }

        $maxSize = 6 * 1024 * 1024; // 6MB
        if ($file['size'] > $maxSize) {
            http_response_code(413);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo excede o tamanho máximo permitido (6MB).']);
            return;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            http_response_code(415);
            echo json_encode(['status' => 'error', 'message' => 'Arquivo enviado não é uma imagem válida.']);
            return;
        }

        $mime = $imageInfo['mime'] ?? '';
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimes[$mime])) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.']);
            return;
        }

        $subDirs = [
            'people' => 'users',
            'businesses' => 'businesses',
            'teams' => 'teams'
        ];

        $rootDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'images';
        $targetDir = $rootDir . DIRECTORY_SEPARATOR . $subDirs[$entityType];

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível preparar o diretório de imagens.']);
            return;
        }

        $extension = $allowedMimes[$mime];
        $filename = uniqid($entityType . '_', true) . '.' . $extension;
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Falha ao salvar a imagem no servidor.']);
            return;
        }

        $relativePath = '/images/' . $subDirs[$entityType] . '/' . $filename;

        $mapping = [
            'people' => ['db' => 'workz_data', 'table' => 'hus'],
            'businesses' => ['db' => 'workz_companies', 'table' => 'companies'],
            'teams' => ['db' => 'workz_companies', 'table' => 'teams']
        ];

        $columnToUpdate = ($imageType === 'bk') ? 'bk' : 'im';

        $config = $mapping[$entityType];
        $updated = $this->generalModel->update($config['db'], $config['table'], [$columnToUpdate => $relativePath], ['id' => $entityId]);

        if (!$updated) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Não foi possível atualizar os dados da entidade.']);
            return;
        }

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Imagem atualizada com sucesso.',
            'imageUrl' => $relativePath
        ]);
    }

    public function changeEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            http_response_code(400);            
            return;
        }

        $userId = $input['userId'];
        $newEmail = $input['newEmail'];

        if (empty($userId) || empty($newEmail)) {
            echo json_encode(['message' => 'Missing required fields.', 'status' => 'error']);
            http_response_code(400);
            return;
        }
        
        $user = $this->generalModel->search('workz_data', 'hus', ['*'], ['id' => $userId], false);

        if (!$user) {
            echo json_encode(['message' => 'User not found.', 'status' => 'error']);
            http_response_code(404);
            return;
        }
        
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['message' => 'Invalid email address.', 'status' => 'error']);
            http_response_code(422);            
            return;
        }

        $emailExists = $this->generalModel->search('workz_data','hus', ['id'], ['ml' => $newEmail], false);

        if ($emailExists) {
            echo json_encode(['message' => 'Email already exists.', 'status' => 'error']);
            http_response_code(409);
            return;
        }

        
        if (!empty($user['provider'])) {
            echo json_encode(['message' => 'User has a provider account: '.$user['provider'], 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new DateTime('+2 hours'))->format('Y-m-d H:i:s');

        $repo = $this->generalModel->update('workz_data','hus', [
            'pending_email' => $newEmail,
            'email_change_token' => $tokenHash,
            'email_change_expires_at' => $expiresAt
        ], ['id' => $userId]);

        if ($repo) {
            // In a real application, you would send an email with a link containing $token
            // For this example, we'll just return the token
            echo json_encode([
                'message' => 'Email change request initiated. Please check your new email for verification.',
                'status' => 'success',
                'verification_token' => $token // For testing/demonstration purposes
            ]);
            http_response_code(200);
        } else {
            echo json_encode(['message' => 'Failed to initiate email change request.', 'status' => 'error']);
            http_response_code(500);
        }
    }


    public function changePassword(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['message' => 'Invalid JSON input.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $userId = $input['userId'] ?? null;
        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';

        if (empty($userId) || $currentPassword === '' || $newPassword === '') {
            echo json_encode(['message' => 'Missing required fields.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        $user = $this->generalModel->search('workz_data', 'hus', ['id', 'pw', 'provider'], ['id' => $userId], false);
        if (!$user) {
            echo json_encode(['message' => 'User not found.', 'status' => 'error']);
            http_response_code(404);
            return;
        }

        if (!empty($user['provider']) && $user['provider'] !== 'local') {
            echo json_encode(['message' => 'Password changes are not allowed for social logins.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        if (empty($user['pw'])) {
            echo json_encode(['message' => 'Password not set for this account.', 'status' => 'error']);
            http_response_code(400);
            return;
        }

        if (!password_verify($currentPassword, $user['pw'])) {
            echo json_encode(['message' => 'Current password is incorrect.', 'status' => 'error']);
            http_response_code(401);
            return;
        }

        if (password_verify($newPassword, $user['pw'])) {
            echo json_encode(['message' => 'The new password must be different from the current password.', 'status' => 'error']);
            http_response_code(422);
            return;
        }

        if (!$this->isValidPassword($newPassword)) {
            echo json_encode(['message' => 'The new password does not meet the security requirements.', 'status' => 'error']);
            http_response_code(422);
            return;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = $this->generalModel->update('workz_data', 'hus', ['pw' => $hashedPassword], ['id' => $userId]);

        if ($updated) {
            echo json_encode(['message' => 'Password updated successfully!', 'status' => 'success']);
            http_response_code(200);
        } else {
            echo json_encode(['message' => 'Failed to update password.', 'status' => 'error']);
            http_response_code(500);
        }
    }

    private function isValidPassword(string $password): bool
    {
        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[@$!%*?&.#])[A-Za-z\\d@$!%*?&.#]{8,}$/';
        return preg_match($regex, $password) === 1;
    }
}






