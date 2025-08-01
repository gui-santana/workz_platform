<?php
//DELETE GENERAL
function del($db, $table, $where){
	if($db <> '' && $table <> '' && $where <> ''){
		include($_SERVER['DOCUMENT_ROOT'].'/config/_'.$db.'.php');
		$success = '';		
		$con = $$db->prepare("DELETE FROM ".$table." WHERE ".$where."");		
		try{
			$con->execute();
			$success = 'success';
		}catch(Exception $e){
			echo $e->getMessage();
		}		
		$$db = null;
		return $success;
	}
}
?>