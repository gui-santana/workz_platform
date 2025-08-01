<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL,'pt_BR.UTF8');
mb_internal_encoding('UTF8');
mb_regex_encoding('UTF8');

$now = date('Y-m-d H:i:s');	

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}

// Busca a timeline da tarefa no banco
$tskr = search('app', 'wa0001_wtk', 'id,st,step,time', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'")[0] ?? null;

$steps = json_decode($tskr['step'], true);			

// Contar etapas com status igual a 1
$stepcpl = count(array_filter($steps, function($step) {
	return isset($step['status']) && $step['status'] == 1;
}));

$step_progress = (count($steps) > 0) ? ($stepcpl / count($steps)) * 100 : 0;
?>
<div style="<?= ($tskr['st'] == 3) ? 'width: 100%;' : 'width: calc(100% - 85.33px); margin-right: 10px;' ?>" class="w-shadow-1 fs-c background-black-transparent-25 float-left w-square-rounded w-rounded-10">
	<div class="background-orange text-right white <?= $step_progress == 100 ? 'w-rounded-20' : 'w-rounded-20-l' ?>" style="width: <?= str_replace(',','.',$step_progress) ?>%; max-width: 100%;">
		<a class="cm-mg-10-r cm-mg-10-l white font-weight-500"><?= round($step_progress, 1).'%'; ?></a>
	</div>
</div>
<div class="float-right text-right">
<?php
//BOTÕES DE AÇÃO				
if($tskr['st'] > 1){						
	//EM EXECUÇÃO / FINALIZADO
	if($tskr['st'] == 2){
		//EM EXECUÇÃO
		if($step_progress == 100){
		//CONCLUIR TAREFA
		?> 																										
		<span class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Concluir" onclick="concluirTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})" style="color: #35A853;">
			<i class="fas fa-circle fa-stack-2x"></i>
			<i class="fas fa-check fa-stack-1x fa-inverse"></i>					
		</span>
		<?php
		//PAUSAR TAREFA
		}else{
		?>																																																																								
		<span class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Pausar" onclick="pausarTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})">
			<i class="fas fa-circle fa-stack-2x"></i>
			<i class="fas fa-pause fa-stack-1x fa-inverse"></i>					
		</span>
		<?php
		}
		//ARQUIVAR TAREFA **DESATIVADO**
		?>
		<span class="fa-stack light-gray" style="vertical-align: middle;">
			<i class="fas fa-circle fa-stack-2x"></i>
			<i class="fas fa-eject fa-stack-1x fa-inverse"></i>					
		</span>
		<?php
	}
}else{
	//A FAZER
	if($tskr['st'] == 0 || $tskr['st'] == 1){
	//INICIAR / REINICIAR TAREFA
	?>
	<span class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="<?= $tskr['st'] == 1 ? 'Rei' : 'I' ?>nciar" onclick="iniciarTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})">
		<i class="fas fa-circle fa-stack-2x"></i>
		<i class="fas fa-play fa-stack-1x fa-inverse"></i>					
	</span>
	<?php							
	}
	//ARQUIVAR TAREFA
	?>						
	<span class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Arquivar" onclick="arquivarTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})">
		<i class="fas fa-circle fa-stack-2x"></i>															  
		<i class="fas fa-eject fa-stack-1x fa-inverse"></i>					
	</span>
	<?php
}				
?>
</div>