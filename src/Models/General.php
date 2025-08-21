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
    public function insert(
        string $db, 
        string $table, 
        array $data    
    ): int|false
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

        // Remove "id" do SET (use o id no WHERE!)
        unset($data['id']);

        //Pré-tratamento dos dados vazios
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        // Impede updates vazios / sem WHERE
        if (empty($data) || empty($conditions)) {
            return false;
        }

        // Validação básica de identificadores (tabela/colunas)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \InvalidArgumentException("Nome de tabela inválido: $table");
        }

        $setClauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException("Nome de coluna inválido no SET: $key");
            }
            $setClauses[] = "`$key` = :set_$key";
            $params["set_$key"] = $value; // em execute() a chave não leva dois-pontos
        }

        if (empty($setClauses)) {
            // Depois do filtro, não sobrou nada para atualizar
            return false;
        }

        $whereClauses = [];
        foreach ($conditions as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException("Nome de coluna inválido no WHERE: $key");
            }
            $whereClauses[] = "`$key` = :where_$key";
            $params["where_$key"] = $value;
        }

        $sql = "UPDATE `$table` SET " . implode(', ', $setClauses)
            . " WHERE " . implode(' AND ', $whereClauses);

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }


    /**
     * Conta registros com suporte a DISTINCT e filtros em tabelas relacionadas via EXISTS.
     *
     * @param string      $db          Nome do banco para a tabela principal (conexão).
     * @param string      $table       Tabela principal.
     * @param array       $conditions  Condições da tabela principal (coluna => valor).
     * @param string|null $distinctCol Coluna para DISTINCT (opcional).
     * @param array       $exists      Lista de sub-filtros em outras tabelas, no formato:
     *                                 [
     *                                   [
     *                                     'db' => 'nome_db',
     *                                     'table' => 'tabela_remota',
     *                                     'local' => 'coluna_local',   // na tabela principal
     *                                     'remote' => 'coluna_remota', // na tabela remota
     *                                     'conditions' => [ 'st' => 1, ... ] // opcional
     *                                   ],
     *                                   ...
     *                                 ]
     * @return int
     */
    public function count(
        string $db,
        string $table,
        array $conditions = [],
        ?string $distinctCol = null,
        array $exists = []
    ): int
    {
        $this->db = Database::getInstance($db);

        // --- Helpers de validação/quote de identificadores ---
        $quoteIdent = function (string $ident): string {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $ident)) {
                throw new \InvalidArgumentException("Identificador inválido: {$ident}");
            }
            return "`{$ident}`";
        };
        $quoteDbTable = function (?string $dbName, string $tbl) use ($quoteIdent): string {
            $t = $quoteIdent($tbl);
            if ($dbName !== null && $dbName !== '') {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
                    throw new \InvalidArgumentException("Nome de banco inválido: {$dbName}");
                }
                return "`{$dbName}`.{$t}";
            }
            return $t;
        };

        // --- Valida tabela principal ---
        $mainFrom = $quoteDbTable(null, $table); // assume que a conexão já está no DB correto

        // --- WHERE principal ---
        $whereParts = [];
        $params = [];
        foreach ($conditions as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException("Nome de coluna inválido: {$key}");
            }
            $paramName = ":c_{$key}";
            $whereParts[] = "{$quoteIdent($key)} = {$paramName}";
            $params[$paramName] = $value;
        }

        // --- EXISTS remotos ---
        foreach ($exists as $i => $ex) {
            // Campos obrigatórios
            $exDb      = $ex['db']      ?? null;   // pode ser null/'' se estiver no mesmo DB da conexão
            $exTable   = $ex['table']   ?? null;
            $exLocal   = $ex['local']   ?? null;   // coluna da tabela principal
            $exRemote  = $ex['remote']  ?? null;   // coluna da tabela remota
            $exConds   = $ex['conditions'] ?? [];

            if (!$exTable || !$exLocal || !$exRemote) {
                throw new \InvalidArgumentException("Bloco EXISTS #{$i} incompleto: 'table', 'local' e 'remote' são obrigatórios.");
            }

            // Monta subquery EXISTS
            $remoteFrom = $quoteDbTable($exDb, $exTable);
            $localCol   = $quoteIdent($exLocal);
            $remoteCol  = $quoteIdent($exRemote);

            $exWhere = ["{$remoteCol} = {$localCol}"]; // condição de junção remota.local = principal.local

            foreach ($exConds as $k => $v) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
                    throw new \InvalidArgumentException("Nome de coluna inválido em EXISTS #{$i}: {$k}");
                }
                $p = ":e{$i}_{$k}";
                $exWhere[] = "{$quoteIdent($k)} = {$p}";
                $params[$p] = $v;
            }

            $existsSql = "EXISTS (SELECT 1 FROM {$remoteFrom} WHERE " . implode(' AND ', $exWhere) . ")";
            $whereParts[] = $existsSql;
        }

        // --- SELECT COUNT ---
        $sql = "SELECT COUNT(" . ($distinctCol ? ("DISTINCT " . $quoteIdent($distinctCol)) : "*") . ") AS count
                FROM {$mainFrom}";
        if (!empty($whereParts)) {
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
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
     * Método genérico para buscar dados em uma tabela (retrocompatível + operadores).
     */
    public function search(
        string $dbName,
        string $table,
        array  $columns   = ['*'],
        array  $conditions = [],
        bool   $fetchAll   = true,
        ?int   $limit      = null,
        ?int   $offset     = null,
        ?array $order      = null,
        $distinct          = null,
        array  $exists     = []
    ): array|false {
        try {
            $this->db = Database::getInstance($dbName);

            // valida tabela
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                throw new \InvalidArgumentException("Nome de tabela inválido: $table");
            }

            // colunas
            if (empty($columns)) { $columns = ['*']; }
            $allowedCols = [];
            foreach ($columns as $col) {
                if ($col === '*') { $allowedCols = ['*']; break; }
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    throw new \InvalidArgumentException("Nome de coluna inválido: $col");
                }
                $allowedCols[] = "`$col`";
            }
            $colsList = ($allowedCols === ['*']) ? '*' : implode(', ', $allowedCols);

            // DISTINCT
            $selectPrefix = 'SELECT ';
            if ($distinct === true) {
                $selectPrefix = 'SELECT DISTINCT ';
            } elseif (is_string($distinct)) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $distinct)) {
                    throw new \InvalidArgumentException("Coluna DISTINCT inválida: $distinct");
                }
                $colsList = "`$distinct`";
                $selectPrefix = 'SELECT DISTINCT ';
            }

            $sql    = $selectPrefix . "{$colsList} FROM `$table`";
            $params = [];

            // WHERE (conditions) – montado uma única vez
            $where = !empty($conditions) ? $this->buildWhereClause($conditions, $params) : '';

            // EXISTS (NOVO)
            $existsClauses = [];
            foreach ($exists as $i => $ex) {
                $exDb    = $ex['db']    ?? $dbName; // se não vier, usa o db principal
                $exTable = $ex['table'] ?? '';
                $local   = $ex['local'] ?? '';
                $remote  = $ex['remote'] ?? '';
                $exConds = $ex['conditions'] ?? [];

                // valida nomes
                foreach ([$exTable, $local, $remote] as $name) {
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                        throw new \InvalidArgumentException("Nome inválido em EXISTS");
                    }
                }

                // monta condições internas do EXISTS
                $subParams = [];
                $subWhere  = '';
                if (!empty($exConds)) {
                    $subWhere = $this->buildWhereClause($exConds, $subParams);
                }

                // renomeia placeholders para evitar colisão
                $renamed = [];
                foreach ($subParams as $k => $v) {
                    $nk = ":ex{$i}_" . ltrim($k, ':');
                    $renamed[$nk] = $v;
                    $subWhere = str_replace($k, $nk, $subWhere);
                }
                $params = array_merge($params, $renamed);

                // adiciona o nome do banco
                $existsClauses[] =
                    "EXISTS (SELECT 1 FROM `{$exDb}`.`{$exTable}` " .
                    "WHERE `{$exDb}`.`{$exTable}`.`{$remote}` = `{$table}`.`{$local}`" .
                    ($subWhere ? " AND {$subWhere}" : "") .
                    ")";
            }

            // WHERE final (conditions + exists)
            if ($where || $existsClauses) {
                $sql .= ' WHERE ' . implode(' AND ', array_filter([$where, ...$existsClauses]));
            }

            // ORDER BY
            if (!empty($order['by']) && preg_match('/^[a-zA-Z0-9_]+$/', $order['by'])) {
                $dir = strtoupper($order['dir'] ?? 'ASC');
                if (!in_array($dir, ['ASC','DESC'], true)) { $dir = 'ASC'; }
                $sql .= " ORDER BY `{$order['by']}` $dir";
            }

            // paginação
            if ($limit !== null) {
                $sql .= ' LIMIT ' . (int)$limit;
                if ($offset !== null) {
                    $sql .= ' OFFSET ' . (int)$offset;
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $result = $fetchAll
                ? $stmt->fetchAll(PDO::FETCH_ASSOC)
                : ($stmt->fetch(PDO::FETCH_ASSOC) ?: false);

            // 🔹 Remove a coluna 'pw' se existir
            if ($result) {
                if ($fetchAll) {
                    foreach ($result as &$row) {
                        unset($row['pw']);
                    }
                } else {
                    unset($result['pw']);
                }
            }
            
            return $result;

        } catch (\PDOException|\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Monta WHERE recursivamente com suporte a _or e _and.
     * Aceita:
     *  - campo => valor (igualdade)
     *  - campo => [op=>..., value=>...] (operadores)
     *  - campo => [v1, v2, ...] (fallback IN)
     *  - _or / _and com arrays de sub-condições
     */
    private function buildWhereClause(array $conditions, array &$params): string
    {
        $parts = [];

        foreach ($conditions as $key => $value) {
            // grupos lógicos
            if ($key === '_or' || $key === '_and') {
                if (!is_array($value) || empty($value)) { continue; }
                $subParts = [];
                foreach ($value as $sub) {
                    if (!is_array($sub)) { continue; }
                    $clause = $this->buildWhereClause($sub, $params);
                    if ($clause !== '') { $subParts[] = $clause; }
                }
                if ($subParts) {
                    $glue = ($key === '_or') ? ' OR ' : ' AND ';
                    $parts[] = '(' . implode($glue, $subParts) . ')';
                }
                continue;
            }

            // campo simples
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                throw new \InvalidArgumentException("Nome de campo inválido: $key");
            }

            $parts[] = $this->buildFieldClause($key, $value, $params);
        }

        return implode(' AND ', array_filter($parts, fn($s) => $s !== ''));
    }

    /**
     * Cláusula para um campo, com operadores e fallback IN.
     */
    private function buildFieldClause(string $field, $value, array &$params): string
    {
        // IS NULL
        if ($value === null) {
            return "`$field` IS NULL";
        }

        // Operador explícito
        if (is_array($value) && array_key_exists('op', $value)) {
            $op  = strtoupper(trim((string)$value['op']));
            $val = $value['value'] ?? null;

            switch ($op) {
                case 'IN':
                case 'NOT IN':
                    if (!is_array($val) || empty($val)) {
                        return '0=1'; // IN vazio -> impossível
                    }
                    $phs = [];
                    foreach (array_values($val) as $i => $v) {
                        $ph = ":{$field}_".($op === 'IN' ? 'in' : 'nin')."_$i";
                        $phs[] = $ph;
                        $params[$ph] = $v;
                    }
                    return "`$field` $op (" . implode(',', $phs) . ")";

                case 'LIKE':
                    $ph = ":{$field}_like";
                    $params[$ph] = $val;
                    return "`$field` LIKE $ph";

                case 'BETWEEN':
                    if (!is_array($val) || count($val) !== 2) {
                        throw new \InvalidArgumentException("Valor inválido para BETWEEN em $field");
                    }
                    $ph1 = ":{$field}_from";
                    $ph2 = ":{$field}_to";
                    $params[$ph1] = $val[0];
                    $params[$ph2] = $val[1];
                    return "`$field` BETWEEN $ph1 AND $ph2";

                case '>':
                case '>=':
                case '<':
                case '<=':
                case '<>':
                case '!=':
                case '=':
                    $ph = ":{$field}_cmp";
                    $params[$ph] = $val;
                    return "`$field` $op $ph";

                case 'NOT NULL':
                case 'IS NOT NULL':
                    return "`$field` IS NOT NULL";

                default:
                    throw new \InvalidArgumentException("Operador não suportado: $op");
            }
        }

        // Fallback: array sem 'op' => IN
        if (is_array($value)) {
            if (empty($value)) { return '0=1'; }
            $phs = [];
            foreach (array_values($value) as $i => $v) {
                $ph = ":{$field}_in_$i";
                $phs[] = $ph;
                $params[$ph] = $v;
            }
            return "`$field` IN (" . implode(',', $phs) . ")";
        }

        // Igualdade simples
        $ph = ":$field";
        $params[$ph] = $value;
        return "`$field` = $ph";
    }




}