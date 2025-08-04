<?php

// src/Models/General.php

namespace Workz\Platform\Models;

use Workz\Platform\Core\Database;
use PDO;

class General
{
    private PDO $db;

    /*          
    public function executeQuery(string $sql, array $params = []): bool|array
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt->execute($params)) {
            return $stmt->fetchAll(PDO::FETCH_OBJ); // Retorna os resultados como um array de objetos
        }
        return false; // Retorna false em caso de erro
    }
    */

    /**
     * Método genérico para inserir dados em uma tabela.
     */
    public function insert(string $db, string $table, array $data): int|false
    {
        $this->db = Database::getInstance($db);

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
    public function update(string $db, string $table, array $data, array $conditions): bool
    {
        $this->db = Database::getInstance($db);

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
    public function delete(string $db, string $table, array $conditions): bool
    {        
        $this->db = Database::getInstance($db);

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
     * Método genérico para buscar dados em uma tabela.
     */
    public function search(
        string $dbName,
        string $table,
        array $columns = ['*'],
        array $conditions = [],
        bool $fetchAll = true,
        ?int   $limit      = null,
        ?int   $offset     = null
        ): array|false
    {
        try {
            $this->db = Database::getInstance($dbName);
            
            // 2) Validação básica de nome de tabela
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new \InvalidArgumentException("Nome de tabela inválido: $table");
            }
            
            // Validação e escape de colunas
            if (empty($columns)) {
                $columns = ['*'];
            }
            $allowedCols = [];
            foreach ($columns as $col) {
                if ($col === '*') {
                    $allowedCols = ['*'];
                    break;
                }
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    throw new \InvalidArgumentException("Nome de coluna inválido: $col");
                }
                $allowedCols[] = "`$col`";
            }
            $colsList = implode(', ', $allowedCols);

            $sql = "SELECT {$colsList} FROM `$table`";
            $params = [];

            if (!empty($conditions)) {
                $clauses = [];
                foreach ($conditions as $field => $value) {
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                        throw new \InvalidArgumentException("Nome de campo inválido: $field");
                    }
                    $clauses[]       = "`$field` = :$field";
                    $params[":$field"] = $value;
                }
                $sql .= ' WHERE ' . implode(' AND ', $clauses);
            }

            // paginação: LIMIT e OFFSET injetados como inteiros
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int)$limit;
                if ($offset !== null) {
                    $sql .= ' OFFSET ' . (int)$offset;
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if ($fetchAll) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
        
        } catch (\PDOException|\InvalidArgumentException $e) {
            // Aqui você pode registrar no log: $e->getMessage()
            return false;
        }
    }
}