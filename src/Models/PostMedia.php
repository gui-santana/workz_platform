<?php

namespace Workz\Platform\Models;

use Workz\Platform\Core\Database;
use PDO;

class PostMedia
{
    private PDO $db;
    private string $dbName;
    private static ?bool $hasStorageDriver = null;
    private static bool $storageDriverLogged = false;

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

        $this->stripStorageDriverIfMissing($data);
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

        $this->stripStorageDriverIfMissing($data);
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

    public function findByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM `media` WHERE `id` IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['id']] = $row;
        }
        return $indexed;
    }

    private function stripStorageDriverIfMissing(array &$data): void
    {
        if (!array_key_exists('storage_driver', $data)) {
            return;
        }
        if ($this->hasStorageDriverColumn()) {
            return;
        }
        unset($data['storage_driver']);
        if ($this->shouldLogStorageDriver()) {
            error_log('[PostMedia] storage_driver column missing; using local fallback.');
        }
    }

    private function hasStorageDriverColumn(): bool
    {
        if (self::$hasStorageDriver !== null) {
            return self::$hasStorageDriver;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col'
            );
            $stmt->execute([
                'db' => $this->dbName,
                'tbl' => 'media',
                'col' => 'storage_driver',
            ]);
            self::$hasStorageDriver = ((int)$stmt->fetchColumn() > 0);
        } catch (\Throwable $e) {
            self::$hasStorageDriver = false;
        }

        if ($this->shouldLogStorageDriver()) {
            $msg = self::$hasStorageDriver ? 'storage_driver column detected.' : 'storage_driver column not found.';
            error_log('[PostMedia] ' . $msg);
        }
        return self::$hasStorageDriver;
    }

    private function shouldLogStorageDriver(): bool
    {
        if (self::$storageDriverLogged) {
            return false;
        }
        $debug = $_ENV['DEBUG'] ?? '';
        $enabled = ($debug === '1' || strtolower((string)$debug) === 'true');
        if ($enabled) {
            self::$storageDriverLogged = true;
        }
        return $enabled;
    }
}
