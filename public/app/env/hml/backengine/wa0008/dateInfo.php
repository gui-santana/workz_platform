<?
setlocale(LC_ALL,'pt_BR.UTF8');
mb_internal_encoding('UTF8'); 
mb_regex_encoding('UTF8');
date_default_timezone_set('America/Sao_Paulo');
include($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/functions/crudPDO.php');
function appCalendarResponse($app, $response){
	if($app == 1){
		//TAREFAS
		require_once($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/wa0001/functions/taskStatus.php');
		$res = explode(',', $response);			
		return $res[0].' - Tarefa '.strtolower(taskStatus($res[1]));
	}else{
		//AQUI VÃO AS CONFIGURAÇÕES DE RESPOSTAS DOS DEMAIS APPS
		return 'This is another app';
	}
}
if(isset($_GET['qt']) && $_GET['qt'] <> ''){
	session_start();
	include($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/config.php');
	
	$lfDate = $events->prepare("SELECT * FROM events WHERE us = '".$_SESSION['wz']."' AND DATE(dt) = DATE('".$_GET['qt']."') ORDER BY dt ASC");
	$lfDate->execute();
	$rwDate = $lfDate->rowCount(PDO::FETCH_ASSOC);
	
	$apps = array();
	$date = array();
	while($dates = $lfDate->fetch(PDO::FETCH_ASSOC)){
		$result = array(
			'id' => $dates['id'],
			'ap' => $dates['ap'],
			'el' => $dates['el'],
			'dt' => $dates['dt']
		);
		array_push($date, $result);
		$apps[] = $dates['ap'];
	}	
	$apps = array_unique($apps);
	//APPS CONFIGS
	foreach($apps as $app){
		//include($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/wa'.str_pad($app, 4, '0', STR_PAD_LEFT).'/config.php');
		$$app = appRequest($app);
	}
	?>
	<div class="w-rounded-30 cm-pad-10 large-12 medium-12 small-12 font-weight-600 text-ellipsis uppercase z-index-1 background-white text-ellipsis w-shadow-1 text-center" style="display: inline-block;">	
		<?		
		echo ucfirst(strftime('%A, %e de %B de %Y', strtotime($_GET['qt'])));
		?>
	</div>
	<div class="cm-mg-20-t w-rounded-30 background-white w-shadow-1 large-12 medium-12 small-12">
	<?	
	include($_SERVER['DOCUMENT_ROOT'].'/apps/core/backengine/wa0008/functions/searchHolidays.php');
	$holiday = searchHolidays($_GET['qt'], $events);
	if($holiday['st'] == 1){
		?>
		<div class="large-12 medium-12 small-12 border-t-input cm-pad-10 red">
		<?
		echo $holiday['ds'];
		?>
		</div>
		<?
	}
	$i = 0;
	foreach($date as $event){
		$app = $event['ap'];
		$db_name = $$app['db_name'];
		$values = array(
			'id' => $event['el']
		);		
		?>
		<div class="large-12 medium-12 small-12 border-t-input cm-pad-10">
		<?
		$response = table_select($$db_name, $$app['table'], implode(', ', $$app['columns']), $values);
		echo date('H:i', strtotime($event['dt'])).' | '.appCalendarResponse($app, $response).'<br/>';
		?>
		</div>
		<?
		$i++;
	}
	if($i == 0){
		?>
		<div class="large-12 medium-12 small-12 border-t-input cm-pad-10 green">
			Você não tem tarefas programadas para o dia de hoje.
		</div>
		<?		
	}
	?>
	</div>
	<?
}
?>