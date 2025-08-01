<?
function appRequest($app){
	if($app == 1){
		//TAREFAS
		$db_name = 'tsk';
		$db_columns = array(
			'tt',
			'st',
			'wz'
		);
		$db_table = 'wtk';		
		$result = array(
			'db_name' => $db_name,
			'table' => $db_table,
			'columns' => $db_columns			
		);	
		return $result;
	}elseif($app == 8){
		//CALENDARIO
		$db_name = 'events';
		$db_columns = array(
			'us',
			'ap',
			'el',
			'cf',
			'dt'
		);
		$db_table = 'events';		
		$result = array(
			'db_name' => $db_name,
			'table' => $db_table,
			'columns' => $db_columns			
		);	
		return $result;
	}
}
?>