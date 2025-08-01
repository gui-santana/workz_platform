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

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];
$or = '';
foreach($teams as $team){
	$or .= " OR cm = '".$team."'";
}

if(isset($_GET['wp'])){
    
    $wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['wp']}' AND us = '{$_SESSION['wz']}'");
    $api_url = 'https://'.$wpConn[0]['ur'].'/wp-json/tarefaswp/v1/listar?colunas=id,pasta,equipe,equipe_usuarios,frequencia,dificuldade,status,titulo,descricao,data_registro,data_reinicio,tempo,habilidades,etapas,prazo_final&campo[]=id&valor[]='.$_GET['vr'];
    
    include('wp_consult_folder.php');
    
    $tskb = $tgtsk;
    $tskr = $tskb[0];
    $tskr['us'] = $_SESSION['wz'];
    $df	= $tskr['dt'];
    
}else{
    $tskb = search('app', 'wa0001_wtk', '', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'");	
    $tskr = $tskb[0];
    $tskd = search('app', 'wa0008_events', 'dt', "el = '".$tskr['id']."' AND ap = '1'");
    $df	= $tskd[0]['dt'];
}


if((count($tskb) > 0)){
			
	?>
	<div id="folder-root" class="row large-10 medium-12 small-12 cm-pad-5-h position-relative centered">
		<?php			
		$creator = search('hnw', 'hus', 'id,tt,un', "id = '".$tskr['us']."'")[0];
		
		$userEntities = getUserAccessibleEntities($_SESSION['wz']);
		$companies = $userEntities['companies'];
		$teams = $userEntities['teams'];
		
		$statuses = [
			0 => 'Pendente',
			1 => 'Em pausa',
			2 => 'Em andamento',
			3 => 'Finalizada',
			5 => 'Finalizada',
			6 => 'Arquivada'
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
		
		$dificuldade = [
			0 => '⚪ Trivial',
			1 => '🟢 Fácil',
			2 => '🟡 Médio',
			3 => '🔴 Difícil',
			4 => '⚫ Extremo'
		];

		$xp = [
			0 => 5,
			1 => 10,
			2 => 25,
			3 => 50,
			4 => 100
		];
		
		if(isset($_GET['wp'])){
		    $tga[0] = [
		        'id' => 0,
		        'us' => $_SESSION['wz'],
		        'dt' => date('Y-m-d H:i:s'),
		        'tt' => $wpConn[0]['ur'],
		        'ds' => 'Conexão via API do Workz! Tarefas para WordPress com '.$wpConn[0]['ur'],
		        'st' => 0
		        ];
		}else{
    		if ($_GET['qt'] == 2 && ($_SESSION['wz'] == $tskr['wz'])) {
    			$tga = search('app', 'wa0001_tgo', '', "us = '".$_SESSION['wz']."' AND st = '0'");
    		} else {
    			$tga = search('app', 'wa0001_tgo', '', "id = '".$tskr['tg']."'");
    		}   
		}
		
		if($tskr['cm'] <> ''){
			$mba_us = search('cmp', 'teams_users', '', "cm = '".$tskr['cm']."'");
		}else{
			$mba_us = '';
		}			
		if ($tskr['init'] > 0) {
			$bfr = new DateTime($tskr['init']);
		} else {
			$bfr = new DateTime();
		}
		function brf(){
			$init = search('app', 'wa0001_wtk', 'init', "id = '".$_GET['vr']."'")[0]['init'];
			$init = new DateTime($init);
			return $init->getTimestamp();
		}
		
		$steps = json_decode($tskr['step'], true);
		
		//NEW RESOURCES
		$level = $tskr['lv'];  // Nível de dificuldade da tarefa
		$competencias = json_decode($tskr['hb'], true); // Array de competências					
		
		if(!is_array($competencias)){
			$competencias = [$competencias];
		}
		
		if(!empty($tskr['hb'])){ $n = count($competencias); }else{ $n = 0; }					
		
		
		$im = 'data:image/jpeg;base64,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';
		// Calcula XP com um bônus baseado na quantidade de competências selecionadas
		if($n == 0){ $xpCalculado = 0; }else{ $xpCalculado = $xp[$level] * (1 + (($n - 1) / 10));
			foreach($competencias as $skill){
				$categories[] = search('app', 'wa0001_skills', 'ct', "id = $skill")[0]['ct'];
			}				
			// Contar a frequência de cada número
			$frequencia = array_count_values($categories);
			// Encontrar o número com a maior frequência
			$categoriaMaisFrequente = array_search(max($frequencia), $frequencia);
			$im = search('app', 'wa0001_categories', 'im', "id = '{$categoriaMaisFrequente}'")[0]['im'];				
		}		
		?>
		<div id="ckb_response" class="display-none"></div>
		
		<div class="clearfix">
			<div class="cm-mg-15-t large-12 medium-12 small-12 display-center-general-container">				
				<div class="float-left fs-g height-100 font-weight-500 text-ellipsis large-8 medium-8 small-5 orange"><?= $tskr['tt'] ?></div>			
				<!-- MENU SUPERIOR DIREITO -->
				<div class="float-left large-4 mediun-4 small-7 text-right">
					<?php
					//Se usuário é o editor da tarefa ou moderador da equipe
					if($tskr['us'] == $_SESSION['wz'] || in_array($tskr['cm'], $userEntities['teams_manager'])){
						
						//Se não estiver em execução
						if($tskr['st'] <> 2){
						?>					
						<span onclick="wAlert('env/<?= $env ?>/backengine/wa0001/folder.php', 'folder-root', 1, '<?= $tskr['tg'].'|'.$tskr['id']; ?>', 'Deseja excluir permanentemente esta tarefa?', 'Tarefa excluída com sucesso.', 'A tarefa não foi excluída.');" class="fa-stack w-color-or-to-bl pointer" title="Excluir permanentemente esta tarefa">
							<i class="fas fa-circle fa-stack-2x"></i>
							<i class="fas fa-trash fa-stack-1x fa-inverse"></i>
						</span>					
						<span onclick="editMode()" class="open-sidebar fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Editar tarefa">
							<i class="fas fa-circle fa-stack-2x"></i>
							<i class="fas fa-pen fa-stack-1x fa-inverse"></i>
						</span>
						<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/logs.php', 'folder-root', 1, '<?= $tskr['id'] ?>');" class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Ver logs">
							<i class="fas fa-circle fa-stack-2x"></i>
							<i class="fas fa-stream fa-stack-1x fa-inverse"></i>
						</span>
						<script>
						(function(){
							'use strict';							
							function editMode(){
								toggleSidebar();							
								goTo('env/<?= $env ?>/backengine/wa0001/menu.php', 'config', 1, '&id=<?= $tskr['id'] ?>');
							}
							window.editMode = editMode;
							
						})();
						</script>
						<?php
						}
					}					
					?>
					<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/folder.php', 'folder-root', 1, '<?= $tskr['tg'] ?>'); resetCounter();" class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Abrir a pasta da tarefa">
						<i class="fas fa-circle fa-stack-2x"></i>
						<i class="fas fa-folder fa-stack-1x fa-inverse"></i>
					</span>
					<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();" class="fa-stack w-color-bl-to-or pointer" style="vertical-align: middle;" title="Dashboard de tarefas">
						<i class="fas fa-circle fa-stack-2x"></i>
						<i class="fas fa-home fa-stack-1x fa-inverse"></i>
					</span>			
				</div>
			</div>
			<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-15-b cm-mg-20-t position-sticky z-index-2 cm-mg-0-h break-word w-rounded-20 w-shadow-1 bkg-0 color-1 clearfix">
			
				<div class="clearfix large-12 medium-12 small-12 z-index-2">
					<div class="float-left large-10 medium-10 small-8">
						<a><?= $dificuldade[$level] ?> (<?= $xpCalculado ?> Pontos)</a>
					</div>
					<div class="clearfix float-left large-2 medium-2 small-4 height-100 text-right cm-pad-5-l text-ellipsis">
						<?php
						if($tskr['st'] == 2 && ($_GET['qt'] == 1 || $_GET['qt'] == 3)){
						?>
						<p class="float-right font-weight-600">
							<span id="counter" style="padding-top: 2px;"></span>
							<input type="hidden" name="timeCounter" id="timeCounter" value=""></input>
						</p>
						<script>
						(function () {								
							'use strict';												
							startCounter('<?= $tskr['time'] ?>', '<?= $bfr->getTimestamp() ?>');
						})();
						</script>
						<?php
						}else{
						// Convertendo o timestamp para o formato H:i:s
						$hours = floor($tskr['time'] / 3600);
						$minutes = floor(($tskr['time'] - ($hours * 3600)) / 60);
						$seconds = $tskr['time'] - ($hours * 3600) - ($minutes * 60);

						$formattedTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
						?>
						<p class="font-weight-600"><span><?= $formattedTime; ?></span></p>
						<?php										
						}
						?>						
					</div>											
				</div>
				<div id="task_progress" class="large-12 medium-12 small-12 display-center-general-container no-break"></div>
			</div>
			
		
			<div class="cm-mg-30-t large-12 medium-12 small-12 w-shadow-1 cm-pad-20 w-rounded-20 break-word bkg-1 color-0 fs-e">
				<?php				
				$holidays = array_column(search('app', 'wa0013_FerDiario', 'dt', ''), 'dt');				
				?>				
				<div class="clearfix large-12 medium-12 small-12">
					<?php 
					//EDITOR 
					?>					
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">					
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-user fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Editor</strong> <a target="_blank" class="pointer color-0" href="https://workz.com.br/<?= $creator['un'] == '' ? '?profile='.$creator['id'] : $creator['un']; ?>"> <?= $creator['tt']; ?></a></p>						
					</div>
					<?php
					//PASTA				
					?>						
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-folder fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Pasta</strong> <a class="color-0"><?= (!empty($tga[0]['cl'])) ? '<i class="fas fa-circle" style="color: '.$tga[0]['cl'].'"></i> ' : '' ?><?= $tga[0]['tt'] ?></a></p>
					</div>
					<?php				
					//EQUIPE							
					if(count($teams) > 0 && !empty($tskr['cm'])){																
					?>
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-users fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Equipe</strong> <a class="color-0"><?= search('cmp', 'teams', 'tt', "id = {$tskr['cm']}")[0]['tt'] ?></a></p>							
					</div>
					<?php				
					//ATRIBUIÇÃO A MEMBROS ESPECÍFICOS DA EQUIPE
					if($tskr['uscm'] <> ''){				
					$uscm = json_decode($tskr['uscm'], true);
					$totalUsers = count($uscm);
					$n = 1;
					?>
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-user-check fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Responsáve<?= ($totalUsers == 1) ? 'l' : 'is' ?></strong> <a class="color-0">
						<?php
						foreach($uscm as $user){
							echo ($user == $_SESSION['wz']) ? 'mim' : search('hnw', 'hus', '', "id = '".$user."'")[0]['tt'];
							echo ($n == $totalUsers) ? '' : (($n == ($totalUsers - 1)) ? ' e ' : ', ');
						$n++;
						}
						?>	
						</a></p>							
					</div>													
					<?php
					}
					}											
					//STATUS 
					?>
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-play fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Status</strong> <a class="color-0"><?= $statuses[$tskr['st']] ?></a></p>
					</div>					
					<?php 
					//FREQUENCIA DA OCORRÊNCIA 
					?>
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-calendar-week fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Frequência</strong> <a class="color-0"><?= $recurrenceOptions[$tskr['pr']] ?></a></p>
					</div>				
					<?php 
					//PRAZO 
					?>
					<div class="cm-pad-5 cm-pad-0-h clearfix float-left large-6 medium-6 small-12 display-center-general-container">
						<span class="fa-stack fs-b cm-mg-5-r">
							<i class="fas fa-circle fa-stack-2x color-2"></i>
							<i class="fas fa-calendar-check fa-stack-1x color-1"></i>					
						</span>																	
						<p class="color-2"><strong class="cm-mg-5-r">Prazo</strong> <a class="color-0"><?= ($df == 0) ? 'Indefinido' : ucfirst(strftime('%A, %d de %B de %Y, às %H:%M', strtotime($df))) ?></a></p>
					</div>						
					<div class="cm-pad-10-t cm-pad-5-b cm-pad-0-h clearfix float-left large-12 medium-12 small-12">						
						<p class="color-2 cm-mg-5-b"><strong class="cm-mg-5-r">Observações</strong></p>
						<p><?= nl2br($tskr['ds']) ?></p>
					</div>										
				</div>									
			</div>
			
			<h3 class="color-1 cm-pad-20-t">Subtarefas</h3>
			<div class="large-12 medium-12 small-12 color-0 clearfix <?= ($key > 0) ? 'cm-mg-10-t' : '' ?>">
			<?php									
			//EXIBIÇÃO DE SUBTAREFAS
			foreach($steps as $key => $step){											
				$step_desc = $step['titulo'];
				$step_stat = $step['status'];
				$step_date = $step['prazo'];
				$isDisabled = ($tskr['st'] <> 2 || $_GET['qt'] >= 3) ? 'disabled' : '';
				?>							
				<div class="checkbox-wrapper-15 cm-mg-15 cm-mg-0-h">
					<input onchange="updateStepStatus(<?= $key ?>, this.checked)" class="inp-cbx display-none" id="stepckb<?= $key; ?>" type="checkbox" <?= ($step_stat == 1) ? 'checked' : '' ?> <?= $isDisabled ?>/>
					<label class="cbx large-12 medium-12 small-12 display-center-general-container" for="stepckb<?= $key; ?>">
						<span class="cm-mg-10-r">
							<svg width="12px" height="9px" viewbox="0 0 12 9">
								<polyline points="1 5 4 8 11 1"></polyline>
							</svg>
						</span>
						<div style="width: calc(100% - 34px)" class="float-right large-12 medium-12 small-12 text-ellipsis-2 bkg-1 cm-pad-15 w-rounded-15"><?= (!empty($step_date)) ? ' <a class="">'.date('d/m/Y H:i', strtotime($step_date)).' - </a>' : '' ?> <?= str_replace('\'', '', $step_desc) ?></div>
					</label>
				</div>
				<?php										
			}									
			?>
			</div>			
		</div>
		<script>
		(function () {
			'use strict';
			
			// 1. Concluir tarefa
			function concluirTarefa(tskr) {
				handleTaskAction(
					tskr,
					'stop',
					'Deseja concluir esta tarefa?',
					'Tarefa concluída com sucesso.',
					'A tarefa não foi concluída.'
				);
			}
			window.concluirTarefa = concluirTarefa;

			// 2. Pausar tarefa
			function pausarTarefa(tskr) {
				handleTaskAction(
					tskr,
					'pause',
					'Deseja interromper esta tarefa?',
					'Tarefa interrompida com sucesso.',
					'A tarefa não foi interrompida.'
				);
			}
			window.pausarTarefa = pausarTarefa;

			// 3. Iniciar tarefa
			function iniciarTarefa(tskr) {
				handleTaskAction(
					tskr,
					'play',
					'Deseja ' + (tskr.st == 1 ? 're' : '') + 'iniciar esta tarefa?',
					'Tarefa ' + (tskr.st == 1 ? 're' : '') + 'iniciada com sucesso.',
					'A tarefa não foi ' + (tskr.st == 1 ? 're' : '') + 'iniciada.'
				);
			}
			window.iniciarTarefa = iniciarTarefa;

			// 4. Arquivar tarefa
			function arquivarTarefa(tskr) {
				handleTaskAction(
					tskr,
					'eject',
					'Deseja arquivar esta tarefa? Ela será mantida na pasta selecionada.',
					'Tarefa arquivada com sucesso.',
					'A tarefa não foi arquivada.'
				);
			}
			window.arquivarTarefa = arquivarTarefa;																		
			
			//Atualiza a barra de status da tarefa
			function updateStepStatus(stepIndex, isChecked) {
				// Define o novo status do passo (1 = Concluído, "" = Pendente)
				let newStatus = isChecked ? "1" : "";
				
				// Envia a atualização via goPost()											
				goPost('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', {
					action: 'step',
					task_id: '<?= $tskr['id'] ?>',  // ID da tarefa
					step_index: stepIndex,         // Índice do passo
					new_status: newStatus          // Novo status
				}, '');
			}
			window.updateStepStatus = updateStepStatus;
			
			goTo('env/<?= $env ?>/backengine/wa0001/task_progress.php', 'task_progress', '0', '<?= $tskr['id'] ?><?= (isset($_GET['wp']) ? '&wp='.$_GET['wp'] : '') ?>');
			goTo('env/<?= $env ?>/backengine/wa0001/task_timeline.php', 'task_timeline', '0', '<?= $tskr['id'] ?><?= (isset($_GET['wp']) ? '&wp='.$_GET['wp'] : '') ?>');					
		})();
		</script>
		
		<h3 class="color-1 cm-pad-20 cm-pad-0-h">Comentários</h3>
		<div class="large-12 medium-12 small-12">
			<div id="callback" class="display-none"></div>
			<?php
			//LINHA DO TEMPO											
			if($tskr['st'] == 2){
			//EDITOR DE TEXTO
			?>
			<div class="large-12 medium-12 small-12 position-relative centered bkg-1 w-shadow-1 w-rounded-20 cm-pad-15 cm-mg-20-b position-sticky z-index-2">					
				<div>
					<form name="compForm" method="post" action="sample.php" onsubmit="if(validateMode()){this.myDoc.value=oDoc.innerHTML;return true;}return false;">									
					<input type="hidden" name="myDoc" />
					<div class="large-12 medium-12 small-12">
						<div id="editMode" class="" style="height: 0; width: 0;">
							<input type="checkbox" name="switchMode" id="switchBox" onchange="setDocMode(this.checked);" style="height: 0; width: 0;"/>											
						</div>																																						
						<img title="Desfazer" onclick="formatDoc('undo');" style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('formatblock', 'blockquote');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M212.333 224.333H12c-6.627 0-12-5.373-12-12V12C0 5.373 5.373 0 12 0h48c6.627 0 12 5.373 12 12v78.112C117.773 39.279 184.26 7.47 258.175 8.007c136.906.994 246.448 111.623 246.157 248.532C504.041 393.258 393.12 504 256.333 504c-64.089 0-122.496-24.313-166.51-64.215-5.099-4.622-5.334-12.554-.467-17.42l33.967-33.967c4.474-4.474 11.662-4.717 16.401-.525C170.76 415.336 211.58 432 256.333 432c97.268 0 176-78.716 176-176 0-97.267-78.716-176-176-176-58.496 0-110.28 28.476-142.274 72.333h98.274c6.627 0 12 5.373 12 12v48c0 6.627-5.373 12-12 12z'/></svg>"></img>
						<img title="Refazer" onclick="formatDoc('redo');" style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('formatblock', 'blockquote');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'><path d='M500.33 0h-47.41a12 12 0 0 0-12 12.57l4 82.76A247.42 247.42 0 0 0 256 8C119.34 8 7.9 119.53 8 256.19 8.1 393.07 119.1 504 256 504a247.1 247.1 0 0 0 166.18-63.91 12 12 0 0 0 .48-17.43l-34-34a12 12 0 0 0-16.38-.55A176 176 0 1 1 402.1 157.8l-101.53-4.87a12 12 0 0 0-12.57 12v47.41a12 12 0 0 0 12 12h200.33a12 12 0 0 0 12-12V12a12 12 0 0 0-12-12z'/></svg>"></img>																																																																
						<img style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('justifyleft');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M12.83 352h262.34A12.82 12.82 0 0 0 288 339.17v-38.34A12.82 12.82 0 0 0 275.17 288H12.83A12.82 12.82 0 0 0 0 300.83v38.34A12.82 12.82 0 0 0 12.83 352zm0-256h262.34A12.82 12.82 0 0 0 288 83.17V44.83A12.82 12.82 0 0 0 275.17 32H12.83A12.82 12.82 0 0 0 0 44.83v38.34A12.82 12.82 0 0 0 12.83 96zM432 160H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0 256H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16z'/></svg>"></img>
						<img style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('justifycenter');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M432 160H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0 256H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zM108.1 96h231.81A12.09 12.09 0 0 0 352 83.9V44.09A12.09 12.09 0 0 0 339.91 32H108.1A12.09 12.09 0 0 0 96 44.09V83.9A12.1 12.1 0 0 0 108.1 96zm231.81 256A12.09 12.09 0 0 0 352 339.9v-39.81A12.09 12.09 0 0 0 339.91 288H108.1A12.09 12.09 0 0 0 96 300.09v39.81a12.1 12.1 0 0 0 12.1 12.1z'/></svg>"></img>
						<img style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('justifyright');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M16 224h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16zm416 192H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm3.17-384H172.83A12.82 12.82 0 0 0 160 44.83v38.34A12.82 12.82 0 0 0 172.83 96h262.34A12.82 12.82 0 0 0 448 83.17V44.83A12.82 12.82 0 0 0 435.17 32zm0 256H172.83A12.82 12.82 0 0 0 160 300.83v38.34A12.82 12.82 0 0 0 172.83 352h262.34A12.82 12.82 0 0 0 448 339.17v-38.34A12.82 12.82 0 0 0 435.17 288z'/></svg>"></img>
						<img style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('justifyfull');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M432 416H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0-128H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0-128H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16zm0-128H16A16 16 0 0 0 0 48v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16V48a16 16 0 0 0-16-16z'/></svg>"></img>																																																																															
						<img title="Negrito" onclick="formatDoc('bold');" style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('formatblock', 'blockquote');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 384 512'><path d='M333.49 238a122 122 0 0 0 27-65.21C367.87 96.49 308 32 233.42 32H34a16 16 0 0 0-16 16v48a16 16 0 0 0 16 16h31.87v288H34a16 16 0 0 0-16 16v48a16 16 0 0 0 16 16h209.32c70.8 0 134.14-51.75 141-122.4 4.74-48.45-16.39-92.06-50.83-119.6zM145.66 112h87.76a48 48 0 0 1 0 96h-87.76zm87.76 288h-87.76V288h87.76a56 56 0 0 1 0 112z'/></svg>"></img>
						<img title="Itálico" onclick="formatDoc('italic');" style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('formatblock', 'blockquote');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'><path d='M320 48v32a16 16 0 0 1-16 16h-62.76l-80 320H208a16 16 0 0 1 16 16v32a16 16 0 0 1-16 16H16a16 16 0 0 1-16-16v-32a16 16 0 0 1 16-16h62.76l80-320H112a16 16 0 0 1-16-16V48a16 16 0 0 1 16-16h192a16 16 0 0 1 16 16z'/></svg>"></img>
						<img title="Sublinhado" onclick="formatDoc('underline');" style="padding: 4.5px; height: 1.6em; width: 1.6em; margin: 2.5px; border-radius: 2.5px" class="w-bkg-tr-gray pointer" onclick="formatDoc('formatblock', 'blockquote');" height="15" width="15" src="data:image/svg+xml;charset=UTF-8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path d='M32 64h32v160c0 88.22 71.78 160 160 160s160-71.78 160-160V64h32a16 16 0 0 0 16-16V16a16 16 0 0 0-16-16H272a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h32v160a80 80 0 0 1-160 0V64h32a16 16 0 0 0 16-16V16a16 16 0 0 0-16-16H32a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16zm400 384H16a16 16 0 0 0-16 16v32a16 16 0 0 0 16 16h416a16 16 0 0 0 16-16v-32a16 16 0 0 0-16-16z'/></svg>"></img>
					</div>
					
					<div id="editContainer" class="clearfix large-12 medium-12 small-12 overflow-auto text-right cm-mg-10-t">
						<div id="textBox" contenteditable="true" class="required background-white float-left large-12 medium-12 small-12 text-left cm-pad-20 border-none w-rounded-15 position-relative"></div>										
					</div>
					
					<span onclick="sendToTimeline()" class="position-absolute abs-b-20 abs-r-20 w-color-wh-to-gr pointer fs-f" title="Enviar">
						<span class="fa-stack">
							<i class="fas fa-circle fa-stack-2x"></i>
							<i class="fas fa-paper-plane fa-stack-1x gray"></i>
						</span>
					</span>
					
					</form>						
					<div id="tml_response" class="display-none"></div>
				</div>										
			</div>
			<script>
			(function(){
				'use strict';
				
				document.getElementById('textBox').addEventListener('paste', function (event) {
					const clipboardData = event.clipboardData || window.clipboardData;
					const items = clipboardData.items;

					let imagePasted = false; // Controle para verificar se uma imagem foi colada

					for (const item of items) {
						// Verificar se é uma imagem
						if (item.type.indexOf("image") !== -1) {
							const file = item.getAsFile();
							const reader = new FileReader();

							reader.onload = function (e) {
								const img = new Image();
								img.src = e.target.result;

								img.onload = function () {
									const canvas = document.createElement("canvas");
									const ctx = canvas.getContext("2d");

									const MAX_WIDTH = 800;
									const MAX_HEIGHT = 600;

									let width = img.width;
									let height = img.height;

									if (width > MAX_WIDTH || height > MAX_HEIGHT) {
										const aspectRatio = width / height;
										if (width > height) {
											width = MAX_WIDTH;
											height = width / aspectRatio;
										} else {
											height = MAX_HEIGHT;
											width = height * aspectRatio;
										}
									}

									canvas.width = width;
									canvas.height = height;
									ctx.drawImage(img, 0, 0, width, height);

									const resizedImage = canvas.toDataURL("image/jpeg", 0.9);

									// Inserir a imagem redimensionada no local do cursor
									const range = window.getSelection().getRangeAt(0);
									const imgElement = document.createElement('img');
									imgElement.src = resizedImage;
									range.deleteContents();
									range.insertNode(imgElement);

									imagePasted = true;
								};
							};

							reader.readAsDataURL(file);
							event.preventDefault();  // Evita que a imagem original seja colada
						}
					}

					// Permite que textos sejam colados normalmente
					if (!imagePasted) {
						setTimeout(() => {
							const plainText = clipboardData.getData('text/plain');
							if (plainText) {
								const range = window.getSelection().getRangeAt(0);
								const textNode = document.createTextNode(plainText);
								range.deleteContents();
								range.insertNode(textNode);
							}
						}, 0);
					}
				});
			})();
			</script>		
			<?php
			}
			//TIMELINE DE COMENTÁRIOS
			?>		
			<div id="task_timeline" class="large-12 medium-12 small-12 w-task-tl-timeline"></div>
		</div>
	</div>
	<?php			
}else{
	echo "Desculpe, mas não conseguimos carregar o calendário desta tarefa. Por favor, entre em contato com o suporte para assistência.";
}
?>