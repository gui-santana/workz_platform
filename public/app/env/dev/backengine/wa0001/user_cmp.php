<?php
if(!isset($_POST['vr'])){
	//USER'S COMPANIES
	$companies = array_unique(array_column(search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."' AND st > 0 AND nv > 0"), 'em'));
	$blocked_companies = array_unique(array_column(search('cmp', 'companies', 'id', "id IN (".implode(',', $companies).") AND pg = 0"), 'id'));	
	$companies = array_diff($companies, $blocked_companies);

	//USER'S TEAMS			
	$teams = search('cmp', 'teams_users', 'cm,st', "us = '".$_SESSION['wz']."'");				
	foreach($teams as $key => $team){				
		$cm = $team['cm'];
		$st = $team['st'];
		$team = search('cmp', 'teams', 'pg,em', "id = '".$cm."'");
		if($cm == 0 || $team[0]['pg'] == 0 || !in_array($team[0]['em'], $companies) || $st == 0){
			unset($teams[$key]);
		}
	}
	$teams = array_values(array_unique(array_column($teams, 'cm')));
}else{	
	if(isset($_GET['func'])){
		//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
		require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
		session_start();
		include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
		
		//Load team tasks
		if($_GET['func'] == '5-1'){
			$teamTasks = search('app', 'wa0001_wtk', 'id,tt', "cm = '{$_POST['vr']}'");			
			foreach($teamTasks as $task){
			?>
			<option class="w-rounded-5 cm-pad-5 cm-mg-5 text-ellipsis" value="<?= $task['id'] ?>"><?= $task['tt'] ?></option>
			<?php
			}
		// Load team users
		}elseif($_GET['func'] == '1-1'){
			$teamUsers = search('cmp', 'teams_users', 'us', "cm = '{$_POST['vr']}' AND st > 0");    
			
			$taskUsers = [];
			if(isset($_GET['task'])){
				$taskUsers = search('app', 'wa0001_wtk', 'uscm', "id = '{$_GET['task']}'")[0]['uscm'];            
				$taskUsers = json_decode($taskUsers, true) ?? []; // Garante que seja um array
			}
			
			foreach ($teamUsers as $user) {                
			?>
				<option class="w-rounded-5 cm-pad-5 cm-mg-5 text-ellipsis" 
					<?= (is_array($taskUsers) && in_array($user['us'], $taskUsers) ? 'selected' : '') ?> 
					value="<?= $user['us'] ?>">
					<?= search('hnw', 'hus', 'tt', "id = '{$user['us']}'")[0]['tt'] ?>
				</option>
			<?php
			}
		}  
	}	
}


?>