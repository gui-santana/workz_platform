<?php
// src/Controllers/UserController.php

namespace Workz\Platform\Controllers;

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
        
        echo json_encode(['message' => 'Trying.', 'status' => $data]);        

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
}