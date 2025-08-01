<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

$sql = '';
if(isset($_GET['qt']) && $_GET['qt'] > 1){	
	$tgor['id'] = $_GET['qt'];
	if(array_key_exists('vr', $_GET)){
		$sql .= " AND tt LIKE '%".$_GET['vr']."%'";
	}	
}
$sql .= " ORDER BY tt ASC";

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}
if(!isset($_GET['qt'])){
	$_GET['qt'] = 1;
}

$statuses = [
	0 => 'Pendente',
	1 => 'Em pausa',
	2 => 'Em andamento',
	3 => 'Finalizada',
	5 => 'Finalizada',
	6 => 'Arquivada',
	99 => 'Arquivada'
];	

$recurrenceOptions = [
	0 => 'Não recorrente',
	1 => 'Diária',
	2 => 'Semanal',
	3 => 'Mensal',
	4 => 'Bimestral',
	5 => 'Trimestral',
	6 => 'Semestral',
	7 => 'Anual'
];

date_default_timezone_set('America/Sao_Paulo');
$tgtsk = search('app', 'wa0001_wtk', '', "(us = '".$_SESSION['wz']."' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND tg = '".$tgor['id']."'".$sql);
if(count($tgtsk) > 0){
	?>	
	<style>
	table{
		border-collapse: collapse;
	}		
	</style>
	<div id="ckb_response" class="overflow-x-auto"></div>
	<div style="min-width: 800px" class="cm-pad-30-h cm-pad-10 cm-pad-15-t large-12 medium-12 small-12 position-relative text-ellipsis border-b-input overflow">	
		<div class="float-left large-4 medium-4 small-4 text-ellipsis font-weight-500">Tarefa</div>
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500">Prazo</div>
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500">Recorrência</div>
		<div class="float-left large-1 medium-1 small-1 text-ellipsis font-weight-500">Status</div>
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500">Conclusão</div>
		<div class="float-left large-1 medium-1 small-1 text-ellipsis font-weight-500">Ações</div>	
		<div class="clear"></div>
	</div>
	<?php
	$results = array(); // Crie um array para armazenar todos os resultados
	foreach ($tgtsk as $tgtskc) {
		if($df = search('app', 'wa0008_events', 'dt', "el = '" . $tgtskc['id'] . "' AND ap = '1'")){
			$df = $df[0]['dt'];
			$result = array(
				'id' => $tgtskc['id'],
				'df' => $df,
				'tt' => $tgtskc['tt'],
				'st' => $tgtskc['st'],
				'pr' => $tgtskc['pr'],
				'time' => $tgtskc['time'],
				'init' => $tgtskc['init']
			);
			$results[] = $result; // Adicione cada resultado ao array de resultados
		}
	}
	$dfValues = array_column($results, 'df'); // Extraia os valores da coluna 'df'
	
	$totalResults = count($results);
	$i = 1;
	foreach ($results as $tgtskc) {
		
		$taskId = $tgtskc['id'];
		
		$result = search('app', 'wa0001_logs', 'change_date', 
			"field_changed = 'status' 
			AND previous_value = '2' 
			AND (new_value = '0' OR new_value = '3') 
			AND task_id = '{$taskId}' 
			ORDER BY change_date DESC 
			LIMIT 1"
		);
		
		if (!empty($result)) {
			$lastCompletion = date('d/m/Y H:i', strtotime($result[0]['change_date']));
		} else {
			$lastCompletion = "Sem registro";
		}
	?>
	
		<div style="min-width: 800px" class="cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative w-color-bl-to-or w-bkg-tr-gray border-b-input <?= ($i == $totalResults) ? 'w-rounded-15-b'  : '' ?>">
			<label title="<?= $tgtskc['tt'] ?> - Clique para ver" <?= ($tgtskc['st'] <> 2) ? 'onclick="goTo(`env/'.$env.'/backengine/wa0001/m_task.php`, `main-content`, 1, `'.$tgtskc['id'].'`);"' : '' ?> class="pointer">
				<div class="float-left large-4 medium-4 small-4 text-ellipsis-2 font-weight-400 cm-pad-20-r break-word <?= ($tgtskc['st'] > 2) ? 'line-through' : '' ?>"><?= $tgtskc['tt'] ?></div>
				<div class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r"><?= date('d/m/Y', strtotime($tgtskc['df'])) ?></div>
				<div class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r"><?= $recurrenceOptions[$tgtskc['pr']] ?></div>
				<div class="float-left large-1 medium-1 small-1 text-ellipsis cm-pad-20-r"><?= $statuses[$tgtskc['st']] ?></div>
				<div class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r"><?= $lastCompletion ?></div>
			</label>
			<div class="float-left large-1 medium-1 small-1 text-ellipsis text-right">
				<span onclick="clonarTarefa({id: <?= $tgtskc['id'] ?>, st: <?= $tgtskc['st'] ?>, time: <?= $tgtskc['time'] ?>})" class="fa-stack fs-c w-color-gr-to-gr pointer" title="Duplicar">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-clone fa-stack-1x fa-inverse"></i>
				</span>
				<span onclick="excluirTarefa({id: <?= $tgtskc['id'] ?>, st: <?= $tgtskc['st'] ?>, time: <?= $tgtskc['time'] ?>})" class="fa-stack fs-c w-color-gr-to-gr pointer" title="Excluir">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-trash fa-stack-1x fa-inverse"></i>
				</span>
			</div>			
			<div class="clear"></div>
		</div>		
	<?php 
	$i++;
	} 
	?>	
	<script>
	(function(){
		'use strict';
		
		// 5. Clonar Tarefa
		function clonarTarefa(tskr) {
			handleTaskAction(
				tskr,
				'clone',
				'Deseja clonar esta tarefa? Ela será duplicada na pasta selecionada.',
				'Tarefa clonada com sucesso.',
				'A tarefa não foi clonada.'
			);
		}
		window.clonarTarefa = clonarTarefa;		

		// 6. Excluir Tarefa
		function excluirTarefa(tskr) {
			handleTaskAction(
				tskr,
				'delete',
				'Deseja excluir tarefa? Ela será excluída permanentemente.',
				'Tarefa excluída com sucesso.',
				'A tarefa não foi excluída.'
			);
		}
		window.excluirTarefa = excluirTarefa;		
		
	})();
	</script>
	<?php
}else{
	?>
	<div class="large-12 medium-12 small-12 cm-pad-15 text-center">
		<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma tarefa encontrada		
	</div>
	<?php
}
?>