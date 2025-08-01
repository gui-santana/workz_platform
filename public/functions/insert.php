<?php
//INSERT GENERAL
function insert($db, $table, $columns, $values){
    if ($db === '' || $table === '' || $columns === '' || $values === '') {
        return false;
    }

    // 1. Carrega conexão
    include($_SERVER['DOCUMENT_ROOT']."/config/_{$db}.php");
    /** @var PDO $pdo */
    $pdo = $$db;
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // 2. Validar nomes de tabela e colunas: apenas letras, números e underscores
    if (!preg_match('/^[a-z0-9_]+$/i', $table)) {
        error_log("Tabela inválida em insert(): {$table}");
        return false;
    }
    $cols = array_map('trim', explode(',', $columns));
    foreach ($cols as $col) {
        if (!preg_match('/^[a-z0-9_]+$/i', $col)) {
            error_log("Coluna inválida em insert(): {$col}");
            return false;
        }
    }

    // 3. Gerar placeholders e preparar a query
    $placeholders = array_fill(0, count($cols), '?');
    $sql = sprintf(
        "INSERT INTO `%s` (`%s`) VALUES (%s)",
        $table,
        implode('`,`', $cols),
        implode(',', $placeholders)
    );
    $stmt = $pdo->prepare($sql);

    // 4. Extrair os valores: dividir em vírgula respeitando possíveis vírgulas em strings
    $raw = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $values);
    $params = [];
    foreach ($raw as $val) {
        $v = trim($val);
        // remover aspas externas simples, e un-escape de '' para '
        if (preg_match("/^'(.*)'$/s", $v, $m)) {
            $v = str_replace("''", "'", $m[1]);
        }
        $params[] = $v;
    }

    // 5. Executar e tratar erros
    try {
        $stmt->execute($params);
        $lastId = $pdo->lastInsertId();
    } catch (\PDOException $e) {
        //  Não exibir ao usuário — apenas logar
        error_log("SQL Insert Error: " . $e->getMessage());
        return false;
    } finally {
        $$db = null;
    }

    return $lastId;
}

//CMP - COMPANIES & TEAMS
function insertCMP($table, $columns, $values){
	include($_SERVER['DOCUMENT_ROOT'].'/config/_cmp.php');
	$id = '';
	if($table <> '' && $columns <> '' && $values <> ''){	
		$con = $cmp->prepare("INSERT INTO ".$table." (".$columns.") VALUES (".$values.")");		
		try{
			$con->execute();
			$id = $cmp->lastInsertId();		
		}catch(Exception $e){
			echo $e->getMessage();
		}			
	}
	$cmp = null;
	return $id;	
}
?>