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

//Obtém dados da API para WordPress
if(isset($_GET['wp'])){
    
    $wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['wp']}' AND us = '{$_SESSION['wz']}'");
    $api_url = 'https://'.$wpConn[0]['ur'].'/wp-json/tarefaswp/v1/listar?colunas=id,titulo,descricao,prazo_final,frequencia,status';
    include('wp_consult_folder.php');
    
//Obtém dados da base de dados
}else{
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
    $tgtsk = search('app', 'wa0001_wtk', '', "(us = '".$_SESSION['wz']."' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND tg = '".$tgor['id']."'".$sql);
}

$statuses = [
	0 => 'Pendente',
	1 => 'Iniciada',
	2 => 'Em andamento',
	3 => 'Finalizada',
	5 => 'Finalizada',
	6 => 'Arquivada',
	99 => 'Arquivada'
];	

$recurrenceOptions = [
	0 => 'Única',
	1 => 'Diária',
	2 => 'Semanal',
	3 => 'Mensal',
	4 => 'Bimestral',
	5 => 'Trimestral',
	6 => 'Semestral',
	7 => 'Anual'
];

date_default_timezone_set('America/Sao_Paulo');

if(count($tgtsk) > 0){
	?>	
	<style>
	table{
		border-collapse: collapse;
	}		
	</style>
	<div id="ckb_response" class="display-none overflow-x-auto"></div>
	<div style="min-width: 800px" class="clearfix cm-pad-30-h cm-pad-10 cm-pad-15-t large-12 medium-12 small-12 position-relative text-ellipsis border-b-input overflow">	
		<div class="float-left large-4 medium-4 small-4 text-ellipsis font-weight-500">Tarefa</div>
		<div class="float-left large-1 medium-1 small-1 text-ellipsis font-weight-500 text-center">Frequência</div>
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500 text-center">Prazo</div>		
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500 text-center">Status</div>
		<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-500 text-center">Últimos Registros</div>
		<div class="float-left large-1 medium-1 small-1 text-ellipsis font-weight-500 text-center">Ações</div>			
	</div>
	<?php
	$results = array(); // Crie um array para armazenar todos os resultados
	foreach ($tgtsk as $tgtskc) {
        
        $result = array();
        if(isset($tgtskc['dt']) && !empty($tgtskc['dt'])){
            $result['df'] = $tgtskc['dt'];
            $result['us'] = $_SESSION['wz'];
        }else{
    		if($df = search('app', 'wa0008_events', 'dt', "el = '" . $tgtskc['id'] . "' AND ap = '1'")){
    			$result['df'] = $df[0]['dt'];
    			$result['us'] = $tgtskc['us'];
    		}
        }
        $result['id'] = $tgtskc['id'];
        $result['tt'] = $tgtskc['tt'];
        $result['st'] = $tgtskc['st'];
        $result['pr'] = $tgtskc['pr'];
        $result['time'] = $tgtskc['time'];
        $result['init'] = $tgtskc['init'];
        
		$results[] = $result; // Adicione cada resultado ao array de resultados
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
			ORDER BY change_date DESC LIMIT 3"
		);
		$lastCompletion = '<table class="large-12 medium-12 small-12 text-center"><tbody>';
		if (!empty($result)) {
			foreach($result as $completion){
				
					// Cria a instância da data no fuso horário GMT
					$dateTime = new DateTime($completion['change_date'], new DateTimeZone('GMT'));
					// Converte para o fuso horário de São Paulo
					$dateTime->setTimezone(new DateTimeZone('America/Sao_Paulo'));			
					$lastCompletion .= '<tr><td>'.$dateTime->format('d/m/Y H:i:s').'</td></tr>';
				
			}
		} else {
			$lastCompletion .= "<tr><td>Sem registro</td></tr>";
		}
		$lastCompletion .= '</tbody></table>';
		
	?>	
		<div style="min-width: 800px" class="clearfix cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative w-color-bl-to-or w-bkg-tr-gray <?= ($i == $totalResults) ? 'w-rounded-15-b'  : 'border-b-input' ?>">
			<label title="<?= $tgtskc['tt'] ?> - Clique para ver" onclick="goTo(`env/<?= $env ?>/backengine/wa0001/m_task.php`, `main-content`, 1, `<?= $tgtskc['id'] ?><?= (isset($_GET['wp'])) ? '&wp='.$_GET['wp'] : '' ?>`);" class="pointer">
				<div class="float-left large-4 medium-4 small-4 text-ellipsis-2 font-weight-400 cm-pad-20-r break-word <?= ($tgtskc['st'] > 2) ? 'line-through' : '' ?>"><?= $tgtskc['tt'] ?></div>
				<div class="float-left large-1 medium-1 small-1 text-ellipsis text-center"><?= $recurrenceOptions[$tgtskc['pr']] ?></div>
				<div class="float-left large-2 medium-2 small-2 text-ellipsis text-center"><?= date('d/m/Y', strtotime($tgtskc['df'])) ?></div>				
				<div class="float-left large-2 medium-2 small-2 text-ellipsis text-center"><?= $statuses[$tgtskc['st']] ?></div>
				<div class="float-left large-2 medium-2 small-2 text-center"><?= $lastCompletion ?></div>
			</label>
			<div class="float-left large-1 medium-1 small-1 text-ellipsis text-center">
				<?php
				if($tgtskc['us'] == $_SESSION['wz']){	
				?>
				<span onclick="clonarTarefa({id: <?= $tgtskc['id'] ?>, st: <?= $tgtskc['st'] ?>, time: <?= $tgtskc['time'] ?>})" class="fa-stack fs-c w-color-or-to-bl pointer" title="Duplicar">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-clone fa-stack-1x fa-inverse"></i>
				</span>
				<span onclick="excluirTarefa({id: <?= $tgtskc['id'] ?>, st: <?= $tgtskc['st'] ?>, time: <?= $tgtskc['time'] ?>})" class="fa-stack fs-c w-color-or-to-bl pointer" title="Excluir">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-trash fa-stack-1x fa-inverse"></i>
				</span>
				<?php
				}
				?>
			</div>						
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