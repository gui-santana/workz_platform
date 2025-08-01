<?php
//SEARCH GENERAL
/*
Exemplos:

Exibindo o nome de um (1) usuário:
search('hnw', 'hus', '', "id = '".$_SESSION['wz']."'")[0]['tt'];

Exibindo uma lista (array) de usuários com o mesmo nome:
search('hnw', 'hus', '', "tt = '".$user_name."'");
*/
function search($db, $table, $columns, $where){
	
	// 1) Validação mínima
    if (empty($db) || empty($table)) {
        return [];
    }
    if ($columns === '' || $columns === '*') {
        $columns = '*';
    }
	
	// 2) Inclui o PDO e configurações
    include($_SERVER['DOCUMENT_ROOT']."/config/_{$db}.php");	
	/** @var PDO $pdo */
	$pdo = $$db;
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	
	// 3) Validar e escapar tabela
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
        error_log("search(): tabela inválida — {$table}");
        $$db = null;
        return [];
    }
    $tableSql = "`{$table}`";
	
	// 4) Montar e validar lista de colunas
    if ($columns === '*') {
        $colSql = '*';
    } else {
        $rawCols = array_map('trim', explode(',', $columns));
        $escaped = [];
        foreach ($rawCols as $col) {
            // caso seja só um identificador
            if (preg_match('/^[a-z0-9_]+$/i', $col)) {
                $escaped[] = "`{$col}`";
                continue;
            }
            // caso seja uma função simples, ex: SUM(xp) ou COUNT(*) ou AVG(col) AS avg_col
            if (preg_match('/^[a-z0-9_]+\(\s*(\*|[a-z0-9_]+)\s*\)(\s+AS\s+[a-z0-9_]+)?$/i', $col)) {
                $escaped[] = $col;
                continue;
            }
            error_log("search(): expressão de coluna inválida — {$col}");
            $$db = null;
            return [];
        }
        $colSql = implode(',', $escaped);
    }

	// 5) Monta o WHERE com sanitização básica
    $sql = "SELECT {$colSql} FROM {$tableSql}";
    $w = trim($where);
    if ($w !== '') {
        // bloqueia comentário, ponto-e-vírgula e keywords perigosas no WHERE
        if (preg_match('/(;|--|\/\*|\*\/|\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE)\b)/i', $w)) {
            error_log("search(): possível SQL injection em WHERE — {$w}");
            $$db = null;
            return [];
        }
        $sql .= " WHERE {$w}";
    }
	
	// 6) Executa e devolve FETCH_ASSOC
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("search() erro SQL: " . $e->getMessage());
        $res = [];
    } finally {
        $$db = null;
    }

    return $res;
}

function searchWithJoin($db, $query, $params = []) {     
    if ($db != '' && $query != '') {
        include($_SERVER['DOCUMENT_ROOT'] . '/config/_' . $db . '.php');

        $con = $$db->prepare($query);
        
        // Se houver parâmetros, executa com segurança
        if (!empty($params)) {
            $con->execute($params);
        } else {
            $con->execute();
        }
        
        $result = $con->fetchAll(PDO::FETCH_ASSOC);
        
        $$db = null;
        return $result;
    }
    return [];
}