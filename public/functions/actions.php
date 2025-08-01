<?php
	//GET OR POST
	if(isset($_GET['vr']) || isset($_POST['vr'])){
		if(isset($_GET['vr']) && !empty($_GET['vr'])){
			$pdo_params = json_decode(base64_decode($_GET['vr']), true);
		}elseif(isset($_POST['vr']) && !empty($_POST['vr'])){		
			$pdo_params = json_decode(base64_decode($_POST['vr']), true);
		}			
		if(isset($pdo_params['type'])){
			$type = $pdo_params['type'];			
			if(isset($pdo_params['db'])){ $db = $pdo_params['db']; } 
			if(isset($pdo_params['table'])){ $table = $pdo_params['table']; } 
			if(isset($pdo_params['where'])){ $where = $pdo_params['where']; } 
			if(isset($pdo_params['values'])){ $values = $pdo_params['values'];	 } 
			if(isset($pdo_params['columns'])){ $columns = $pdo_params['columns']; } 			
			//echo "INSERT INTO ".$table." (".$columns.") VALUES (".$values.")".'<br/>';
			//echo "UPDATE ".$table." SET ".$values." WHERE ".$where."".'<br/>';					
			if($type == 'insert'){
				require_once('insert.php');
				//echo insertCMP($table, $columns, $values);					
				echo insert($db, $table, $columns, $values);
			}elseif($type == 'update'){
				require_once('update.php');
				echo updateCMP($table, $values, $where);		
			}elseif($type == 'delete'){
				require_once('delete.php');
				echo del($db, $table, $where);
			}elseif($type == 'search'){
				require_once('search.php');
				echo search($db, $table, $columns, $where);		
			}				
		}
	}	
	//FUNCTION
	function action($params){
		$params = json_decode(base64_decode($params), true);
		if(isset($params['type'])){
			$type = $params['type'];
			if(isset($params['db'])){ $db = $params['db'];}
			if(isset($params['table'])){ $table = $params['table'];}
			if(isset($params['where'])){ $where = $params['where'];}
			if(isset($params['values'])){ $values = $params['values'];}
			if(isset($params['columns'])){ $columns = $params['columns'];}
			if($type == 'insert'){
				require_once('insert.php');				
				return insert($db, $table, $columns, $values);
			}elseif($type == 'update'){
				require_once('update.php');
				return updateCMP($table, $values, $where);		
			}elseif($type == 'delete'){
				require_once('delete.php');
				return del($db, $table, $where);
			}elseif($type == 'search'){
				require_once('search.php');
				return search($db, $table, $columns, $where);		
			}				
		}
	}
?>