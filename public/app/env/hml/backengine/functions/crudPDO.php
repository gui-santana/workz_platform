<?
function table_select($db, $table, $columns, $values){
	
	//$db => PDO object | Target database
	//$columns => string (separeted by comma) | Columns to be returned
	//$table => string | Target table
	/*	
	$values => 	
	array (indexes are the columns) | Values to be searched 
	string (custom column = id)	
	Array must be like this:
	$values = array(
		'id' => $var,
		'st' => "'1'"
	);
	*/
	
	if(is_array($values) == true){
		$merging = array();
		foreach($values as $key => $value){
			$merging[] = $key." = '".$value."'";
		}
		$values = implode(' AND ', $merging);
	}else{
		$values = "id = '".$values."'";
	}
	
	
	$consult = $db->prepare("SELECT ".$columns." FROM ".$table." WHERE ".$values);
	$consult->execute();	
	$rowCount = $consult->rowCount(PDO::FETCH_ASSOC);	
	if($rowCount > 0){		
		$result = $consult->fetch(PDO::FETCH_ASSOC);
		$indexes = array();
		foreach(explode(',', $columns) as $index){
			$index = trim($index);
			$indexes[$index] = $result[$index];
		}
		return implode(',', $indexes);
	}
	
	return "SELECT ".$columns." FROM ".$table." WHERE ".$values;	
}

function table_insert($db, $table, $columns, $values){
	$insert = $db->prepare("INSERT INTO ".$table." (".$columns.") VALUES (".$values.")");
	try{
		$insert->execute();
		$id = $db->lastInsertId();
		return $id;
	}catch(Exception $e){
		echo $e->getMessage();
	}	
}

function table_update($db, $table, $columns, $values){
	
	//Columns
	if(is_array($columns) == true){
		$merging = array();
		foreach($columns as $key => $value){
			$merging[] = $key." = '".$value."'";
		}
		$columns = implode(', ', $merging);
	}else{
		$columns = "id = '".$columns."'";
	}
	
	//Values
	if(is_array($values) == true){
		$merging = array();
		foreach($values as $key => $value){
			$merging[] = $key.' = '.$value;
		}
		$values = implode(' AND ', $merging);
	}else{
		$values = "id = '".$values."'";
	}
	
	$update = $db->prepare("UPDATE ".$table." SET ".$columns." WHERE ".$values);
	try{
		$update->execute();
		return true;
	}catch(Exception $e){
		echo $e->getMessage();
	}
}
?>