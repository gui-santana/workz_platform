<?
if(isset($_POST['fnc'])){
	if($_POST['fnc'] == 'hd'){
		require_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');			
		$usuario = $_POST['wz'];		
		$valor = $_POST['valor'];		
		$result = update('hnw', 'hus', "hd='".$valor."'", "id='".$usuario."'");		
		echo $result;
	}
}
?>