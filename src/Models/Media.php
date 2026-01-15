<?php

namespace Workz\Platform\Models;

use Workz\Platform\Core\Database;
use PDO;

class Media
{
    private PDO $db;
    private string $dbName;

    public function __construct(?string $dbName = null)
    {
        $this->dbName = $dbName ?? ($_ENV['MEDIA_DB_NAME'] ?? 'workz_data');
        $this->db = Database::getInstance($this->dbName);
    }

    public function insert(array $data): int|false
    {
        if (empty($data)) {
            return false;
        }

        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `media` ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($data)) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    public function update(int $id, array $data): bool
    {
        if ($id <= 0 || empty($data)) {
            return false;
        }

        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $setClauses[] = "`$key` = :set_$key";
            $params["set_$key"] = $value;
        }
        $params['id'] = $id;

        $sql = "UPDATE `media` SET " . implode(', ', $setClauses) . " WHERE `id` = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM `media` WHERE `id` = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    public function fetchPending(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $sql = "SELECT * FROM `media` WHERE `status` IN ('processing', 'queued') ORDER BY `created_at` ASC LIMIT {$limit}";
        $stmt = $this->db->query($sql);
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
