<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;

class PublicController
{
    private General $generalModel;

    public function __construct()
    {
        $this->generalModel = new General();
    }

    public function profile(int $id): void
    {
        $this->renderPublicEntity(
            'workz_data',
            'hus',
            $id,
            [
                'id',
                'tt',
                'un',
                'cf',
                'im',
                'bk',
                'page_privacy',
                'feed_privacy',
                'contacts',
                'url',
            ]
        );
    }

    public function business(int $id): void
    {
        $this->renderPublicEntity(
            'workz_companies',
            'companies',
            $id,
            [
                'id',
                'tt',
                'un',
                'cf',
                'im',
                'bk',
                'page_privacy',
                'feed_privacy',
                'contacts',
                'url',
                'usmn',
            ]
        );
    }

    private function renderPublicEntity(string $db, string $table, int $id, array $allowedFields): void
    {
        header("Content-Type: application/json");

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Parâmetro id é obrigatório.']);
            return;
        }

        $row = $this->generalModel->search(
            $db,
            $table,
            ['*'],
            ['id' => $id, 'st' => 1],
            false
        );

        if (!$row || !is_array($row)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Entidade não encontrada.']);
            return;
        }

        $pagePrivacy = (int)($row['page_privacy'] ?? 0);
        if ($pagePrivacy !== 1) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Página não pública.']);
            return;
        }

        $data = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $row)) {
                $data[$field] = $row[$field];
            }
        }
        if (!isset($data['id']) && isset($row['id'])) {
            $data['id'] = $row['id'];
        }

        echo json_encode(['success' => true, 'data' => $data]);
    }
}
