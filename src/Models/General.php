<?php

// src/Models/General.php

namespace Workz\Platform\Models;

use Workz\Platform\Core\Database;
use PDO;

class General
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Método genérico para executar uma consulta SQL.
     */
    public function executeQuery(string $sql, array $params = []): bool|array
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->fetchAll(PDO::FETCH_OBJ); // Retorna os resultados como um array de objetos
        }
        return false; // Retorna false em caso de erro
    }

    /**
     * Método genérico para inserir dados em uma tabela.
     */
    public function insert(string $table, array $data): int|false
    {
        // Adiciona backticks para proteger contra nomes de colunas que são palavras-chave do SQL.
        $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($data)) {
            return (int)$this->db->lastInsertId();
        }
        return false;
    }

    /**
     * Método genérico para atualizar dados em uma tabela.
     * @param array $data Os novos dados a serem atualizados (coluna => valor).
     * @param array $conditions As condições para a cláusula WHERE (coluna => valor).
     */
    public function update(string $table, array $data, array $conditions): bool
    {
        if (empty($data) || empty($conditions)) {
            // Impede atualizações vazias ou sem condição (UPDATE sem WHERE).
            return false;
        }

        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            // Usamos um prefixo para os placeholders do SET para evitar colisões com o WHERE.
            $setClauses[] = "`$key` = :set_$key";
            $params["set_$key"] = $value;
        }

        $whereClauses = [];
        foreach ($conditions as $key => $value) {
            $whereClauses[] = "`$key` = :where_$key";
            $params["where_$key"] = $value;
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Método genérico para deletar dados de uma tabela.
     */
    public function delete(string $table, array $conditions): bool
    {
        if (empty($conditions)) {
            // Medida de segurança para evitar DELETE sem WHERE.
            return false;
        }

        $whereClauses = [];
        foreach (array_keys($conditions) as $key) {
            $whereClauses[] = "`$key` = :$key";
        }
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereClauses);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($conditions);
    }

    /**
     * Método genérico para buscar dados de uma tabela.
     * @param bool $fetchAll Se true, retorna todos os resultados. Se false, retorna apenas o primeiro.
     */
    public function search(string $table, array $conditions = [], bool $fetchAll = true): array|object|false
    {
        $sql = "SELECT * FROM `$table`";

        if (empty($conditions)) {
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute()) {
                return false;
            }
            return $fetchAll ? $stmt->fetchAll(PDO::FETCH_OBJ) : $stmt->fetch(PDO::FETCH_OBJ);
        }

        $whereClauses = [];
        foreach (array_keys($conditions) as $key) {
            $whereClauses[] = "`$key` = :$key";
        }
        $sql .= " WHERE " . implode(' AND ', $whereClauses);

        $stmt = $this->db->prepare($sql);
        if (!$stmt->execute($conditions)) {
            return false;
        }

        return $fetchAll ? $stmt->fetchAll(PDO::FETCH_OBJ) : $stmt->fetch(PDO::FETCH_OBJ);
    }     
}