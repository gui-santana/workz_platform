<?php

namespace Workz\Platform\Controllers;

use DateTime;
use Workz\Platform\Models\General;

class PostsController
{
    private General $db;

    public function __construct()
    {
        $this->db = new General();
    }

    // Cria um post na tabela workz_data.hpl usando o modelo General
    public function create(?object $payload): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        if (!$payload) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        $userId = (int)($payload->sub ?? 0);
        $tp = (string)($input['tp'] ?? '');
        // Escopo: dashboard => cm=0, em=0; entidade => um deles > 0
        $cm = isset($input['cm']) ? (int)$input['cm'] : 0;
        $em = isset($input['em']) ? (int)$input['em'] : 0;
        if ($cm > 0) { $em = 0; }
        if ($em > 0) { $cm = 0; }
        $ct = $input['ct'] ?? null;

        if ($userId <= 0 || $tp === '' || $ct === null) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields (tp, ct).']);
            return;
        }

        $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
        $tpDb = isset($tpMap[$tp]) ? $tpMap[$tp] : (is_numeric($tp) ? (int)$tp : 0);
        if ($tpDb === 0) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Invalid post type.']);
            return;
        }

        // ct pode vir como objeto/array ou string JSON; sempre persistimos como string JSON
        $ctJson = is_string($ct) ? $ct : json_encode($ct, JSON_UNESCAPED_UNICODE);

        $data = [
            'us' => $userId,
            'tp' => $tpDb,
            'dt' => (new DateTime())->format('Y-m-d H:i:s'),
            'cm' => $cm,
            'em' => $em,
            'st' => 1,
            'ct' => $ctJson,
        ];

        $id = $this->db->insert('workz_data', 'hpl', $data);
        if ($id) {
            http_response_code(201);
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create post.']);
        }
    }

    // Lista posts para o feed (padrão: mais recentes)
    public function feed(?object $payload): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];

        $limit = isset($input['limit']) ? max(1, min(100, (int)$input['limit'])) : 20;
        $offset = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;

        $conditions = [];
        if (isset($input['us'])) { $conditions['us'] = (int)$input['us']; }
        if (isset($input['cm'])) { $conditions['cm'] = (string)$input['cm']; }
        if (isset($input['em'])) { $conditions['em'] = (string)$input['em']; }
        if (isset($input['tp'])) {
            $tpIn = $input['tp'];
            $tpMap = ['image' => 1, 'video' => 2, 'mixed' => 3];
            $conditions['tp'] = is_numeric($tpIn) ? (int)$tpIn : ($tpMap[$tpIn] ?? 0);
        }

        $order = ['by' => 'dt', 'dir' => 'DESC'];

        $rows = $this->db->search('workz_data', 'hpl', ['id','us','tp','dt','cm','em','ct'], $conditions, true, $limit, $offset, $order);
        if ($rows === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to load feed.']);
            return;
        }

        // Decodificar ct JSON por conveniência
        foreach ($rows as &$row) {
            if (isset($row['ct']) && is_string($row['ct'])) {
                $decoded = json_decode($row['ct'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['ct'] = $decoded;
                }
            }
        }

        echo json_encode([
            'status' => 'success',
            'items' => $rows,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
