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

if(isset($_GET['wp'])){
    //Busca as etapas da tarefa via API
    $wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['wp']}' AND us = '{$_SESSION['wz']}'");
    $api_url = 'https://'.$wpConn[0]['ur'].'/wp-json/tarefaswp/v1/listar?colunas=id,status,etapas,tempo&campo[]=id&valor[]='.$_GET['vr'];
    
    include('wp_consult_folder.php');
    $tskr = $tgtsk[0];
    
}else{
    // Busca as etapas da tarefa no banco
    $tskr = search('app', 'wa0001_wtk', 'id,st,step,time', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'")[0] ?? null;
}

$steps = json_decode($tskr['step'], true);			

// Contar etapas com status igual a 1
$stepcpl = count(array_filter($steps, function($step) {
	return isset($step['status']) && $step['status'] == 1;
}));

$step_progress = (count($steps) > 0) ? ($stepcpl / count($steps)) * 100 : 0;
?>
<div style="<?= ($tskr['st'] >= 3) ? 'width: 100%;' : 'width: calc(100% - 36px); margin-right: 10px;' ?>" class="fs-c background-white-transparent-25 float-left w-square-rounded w-rounded-10">
	<div class="bkg-1 text-right white <?= $step_progress == 100 ? 'w-rounded-20' : 'w-rounded-20-l' ?>" style="width: <?= str_replace(',','.',$step_progress) ?>%; max-width: 100%;">
		<a class="cm-mg-10-r cm-mg-10-l color-0 font-weight-500"><?= round($step_progress, 1).'%'; ?></a>
	</div>
</div>
<div class="cm-pad-5 cm-pad-0-h">
<?php
//BOTÕES DE AÇÃO				
if($tskr['st'] > 1){						
	//EM EXECUÇÃO / FINALIZADO
	if($tskr['st'] == 2){
		//EM EXECUÇÃO
		if($step_progress == 100){
		//CONCLUIR TAREFA
		?> 																										
		<span class="fa-stack pointer" title="Concluir" onclick="concluirTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})" style="color: #35A853;">
			<i class="fas fa-circle fa-stack-2x"></i>
			<i class="fas fa-check fa-stack-1x fa-inverse"></i>					
		</span>
		<?php
		//PAUSAR TAREFA
		}else{
		?>																																																																								
		<span class="fa-stack color-2 pointer" title="Pausar" onclick="pausarTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?>})">
			<i class="fas fa-circle fa-stack-2x"></i>
			<i class="fas fa-pause fa-stack-1x fa-inverse"></i>					
		</span>
		<?php
		}
	}
}else{
	//A FAZER
	if($tskr['st'] == 0 || $tskr['st'] == 1){
	//INICIAR / REINICIAR TAREFA
	?>
	<span class="fa-stack color-2 pointer" title="<?= $tskr['st'] == 1 ? 'Rei' : 'I' ?>nciar" onclick="iniciarTarefa({id: <?= $tskr['id'] ?>, st: <?= $tskr['st'] ?>, time: <?= $tskr['time'] ?><?= isset($_GET['wp']) ? ' ,wp: '.$_GET['wp'] : '' ?>})">
		<i class="fas fa-circle fa-stack-2x"></i>
		<i class="fas fa-play fa-stack-1x fa-inverse"></i>					
	</span>
	<?php							
	}
}				
?>
</div>