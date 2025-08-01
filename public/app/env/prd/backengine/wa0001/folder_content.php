<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');

$sql = '';
if(isset($_GET['qt']) && $_GET['qt'] > 1){
	include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
	session_start();	
	$tgor['id'] = $_GET['qt'];
	if(array_key_exists('vr', $_GET)){
		$sql .= " AND tt LIKE '%".$_GET['vr']."%'";
	}	
}
$sql .= " ORDER BY tt ASC";

include('user_cmp.php');
$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}
if(!isset($_GET['qt'])){
	$_GET['qt'] = 1;
}

require_once($_SERVER['DOCUMENT_ROOT'].'/app/core/backengine/wa0001/functions/taskStatus.php');
date_default_timezone_set('America/Sao_Paulo');
$tgtsk = search('app', 'wa0001_wtk', '', "(us = '".$_SESSION['wz']."' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND tg = '".$tgor['id']."'".$sql);
if(count($tgtsk) > 0){
	?>	
	<style>
	table{
		border-collapse: collapse;
	}
		
	
	</style>
	
	
		<table class="large-12 medium-12 small-12" style="min-width: 800px">
			<thead>
				<tr class="text-left orange">
					<th class="cm-pad-10 large-6 medium-6 small-6">Tarefa</th>
					<th class="cm-pad-10 large-2 medium-2 small-2">Prazo</th>
					<th class="cm-pad-10 large-2 medium-2 small-2">Recorrência</th>
					<th class="cm-pad-10 large-2 medium-2 small-2">Status</th>
				</tr>
			</thead>
			<tbody>
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
			//array_multisort($dfValues, SORT_DESC, $results);
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
			foreach ($results as $tgtskc) {
			?>
				<tr class="large-12 medium-12 small-12 <?php if ($tgtskc['st'] <> 2) { ?>pointer w-color-bl-to-or<?php } else { ?>gray<?php } ?> border-t-input" <?php if ($tgtskc['st'] <> 2) { ?>onclick="goTo('core/backengine/wa0001/m_task.php', 'folder-root', 2, '<?php echo $tgtskc['id']; ?>');"<?php } ?>>
					<td class="large-6 cm-pad-10 medium-6 small-6 font-weight-500 clear <?php if ($tgtskc['st'] > 2) { ?>line-through<?php } ?>"><?php echo $tgtskc['tt']; ?></td>
					<td class="large-2 cm-pad-10 medium-2 small-2"><?php echo date('d/m/Y', strtotime($tgtskc['df'])); ?></td>
					<td class="large-2 cm-pad-10 medium-2 small-2"><?php echo $recurrenceOptions[$tgtskc['pr']]; ?></td>
					<td class="large-2 cm-pad-10 medium-2 small-2"><?php echo taskStatus($tgtskc['st']); ?></td>
				</tr>
				
			<?php } ?>
			</tbody>
		</table>		
	
	<?
}else{
	?>
	<div class="large-12 medium-12 small-12 cm-pad-15 text-center">
		<i class="fas fa-info-circle cm-mg-5-r"></i> Nenhuma tarefa encontrada		
	</div>
	<?
}
?>