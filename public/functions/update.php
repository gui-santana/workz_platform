<?
//CMP - UPDATE GENERAL
function update($db, $table, $values, $where){	
	if($db <> '' && $table <> '' && $values <> '' && $where <> ''){	
		include($_SERVER['DOCUMENT_ROOT'].'/config/_'.$db.'.php');		
		$con = $$db->prepare("UPDATE ".$table." SET ".$values." WHERE ".$where."");		
		$result = '';		
		try{
			$con->execute();
			$result = $con->rowCount(PDO::FETCH_ASSOC);
		}catch(Exception $e){
			echo $e->getMessage();
		}		
		$$db = null;
		return $result;
	}
}

//CMP - COMPANIES & TEAMS
function updateCMP($table, $values, $where){
	include($_SERVER['DOCUMENT_ROOT'].'/config/_cmp.php');
	$row = '';
	if($table <> '' && $where <> '' && $values <> ''){	
		$con = $cmp->prepare("UPDATE ".$table." SET ".$values." WHERE ".$where."");		
		try{
			$con->execute();
			$row = $con->rowCount(PDO::FETCH_ASSOC);
		}catch(Exception $e){
			echo $e->getMessage();
		}			
	}
	$cmp = null;
	return $row;	
}
?>