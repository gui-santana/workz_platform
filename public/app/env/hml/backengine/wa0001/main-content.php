<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

// Verificação de dispositivo móvel
$useragent = $_SERVER['HTTP_USER_AGENT'];
$mobile = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) ? 1 : 0;

$colours[0] = $colors[0];

require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
require_once('../../common/getUserAccessibleEntities.php');

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

setlocale(LC_TIME, 'pt_BR.UTF-8', 'portuguese');
date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');
mb_regex_encoding('UTF-8');

// Função para obter recorrência
function recorrencia($pr) {
    $periods = [
        1 => " Diária",
        2 => " Semanal",
        3 => " Mensal",
        4 => " Bimestral",
        5 => " Trimestral",
        6 => " Semestral",
        7 => " Anual"
    ];
    return $periods[$pr] ?? '';
}

if(isset($_GET['vr'])){
	
	//PASTAS
	if($_GET['vr'] === 'folders'){
		
		// Ações e carregamento de pastas
		if ($_GET['qt'] !== '0') {
			include_once $_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php';
			// Sanitização para evitar injeções SQL e melhorar segurança
			$id = (int) $_GET['qt'];
			$wz = (int) $_SESSION['wz'];
			
			// Lógica de exclusão de pastas e tarefas relacionada a pastas
			// Código similar pode ser encapsulado em uma função externa, se necessário
			$tasks = search('app', 'wa0001_wtk', 'id', "(us = '{$wz}' AND tg = '{$id}')");
			foreach ($tasks as $task) {
				del('app', 'wa0001_wtk', "id = '{$task['id']}'");
			}
			$remainingTasks = search('app', 'wa0001_wtk', 'id', "(us = '{$wz}' AND tg = '{$id}')");
			if (count($remainingTasks) === 0) {
				del('app', 'wa0001_tgo', "id = '{$id}' AND us = '{$wz}'");
			}			
		}
		
		?>
		<div id="folder-root" class="row large-10 medium-10 small-12 position-relative centered cm-pad-5-h">
		<?php		
		include 'folder-root.php';
		?>
		</div>
		<?php		
	
	//REGISTROS
	}elseif ($_GET['vr'] === 'regs'){		
		
		// Exibição de registros
		$result = search('app', 'wa0001_usxp', '', "us = '{$_SESSION['wz']}'");
		$skills = search('app', 'wa0001_skills', '', "");
		$userSkills = array_unique(array_column($result, 'sk'));

		$xpPorSkill = [];

		foreach($userSkills as $skill){
			$registrosFiltrados = array_filter($result, function ($item) use ($skill) {
				return $item['sk'] == $skill;
			});

			$registrosFiltrados = array_values($registrosFiltrados);

			$totalXp = array_reduce($registrosFiltrados, function($carry, $item) {
				return $carry + $item['xp'];
			}, 0);

			$xpPorSkill[$skill] = $totalXp;
		}
		
		$skillsAssoc = [];
		foreach ($skills as $s) {
			$skillsAssoc[$s['id']] = $s['nm'];
		}

		// Ordenar do maior para o menor
		arsort($xpPorSkill);		
		?>
		<div id="folder-root" class="row large-10 medium-10 small-12 position-relative centered cm-pad-5-h">
			<div class="large-12 medium-12 small-12 display-center-general-container cm-mg-15-t">				
				<div class="float-left fs-g height-100 font-weight-500 large-9 medium-8 small-5 orange display-center-general-container">
					<a class="text-ellipsis">Competências de <?= search('hnw', 'hus', 'tt', "id = {$_SESSION['wz']}")[0]['tt'] ?></a>
				</div>				
				<!-- MENU SUPERIOR DIREITO -->
				<div class="float-left large-3 mediun-4 small-7 text-right">														
					<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '');" class="fa-stack w-color-bl-to-or pointer" title="Tarefas">
						<i class="fas fa-circle fa-stack-2x"></i>
						<i class="fas fa-tasks fa-stack-1x fa-inverse"></i>
					</span>		
				</div>
			</div>
			<?php
			$temPontuacao = false;
			$dataLabels = [];
			$dataValues = [];
			$somaGeral = 0;

			// Construção dos dados para o gráfico e exibição textual
			foreach ($xpPorSkill as $skill => $xp) {
				if ($xp > 0) {
					$temPontuacao = true;
					$nomeSkill = isset($skillsAssoc[$skill]) ? $skillsAssoc[$skill] : "Skill $skill";
					$dataLabels[] = $nomeSkill;
					$dataValues[] = $xp;
					$somaGeral += $xp;
				}
			}
			?>

			<div class="cm-mg-20 cm-mg-0-h large-12 medium-12 small-12 w-rounded-15 z-index-2 w-shadow-1 background-white-transparent-75 backdrop-blur cm-pad-15 cm-pad-30-h orange">
				<p class="cm-mg-10-b text-center cm-pad-10">Evolução das suas habilidades ao longo de sua jornada com Workz! Tarefas.</p>

				<?php if ($temPontuacao): ?>
					<canvas id="skillsChart" width="400" height="250"></canvas>
					<script>
					(function(){
						'use strict';

						function loadChart(){
							const ctx = document.getElementById('skillsChart').getContext('2d');
							const skillsChart = new Chart(ctx, {
								type: 'bar',
								data: {
									labels: <?= json_encode($dataLabels) ?>,
									datasets: [{
										label: 'Experiência por habilidade',
										data: <?= json_encode($dataValues) ?>,
										backgroundColor: '<?= $colors[2] ?>',
										borderColor: '<?=  $colors[0]; ?>',
										borderWidth: 1
									}]
								},
								options: {
									responsive: true,
									scales: {
										y: {
											beginAtZero: true
										}
									}
								}
							});
						}

						window.loadChart = loadChart; // Disponibiliza para reuso externo, se necessário
						loadChart(); // Executa no carregamento

					})();
					</script>


					<br>
					<p class="text-center"><strong>Experiência acumulada:</strong> <?= number_format($somaGeral, 2, ',', '.') ?></p>
				<?php else: ?>
					<p>Você ainda não acumulou experiência em nenhuma habilidade.</p>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

//TAREFAS	
}else{
	?>
	<style>
	.vertical-text {
		writing-mode: vertical-rl;  /* Mantém o texto na vertical */
		text-orientation: sideways;  /* Garante que o texto fique legível */
		transform: rotate(180deg);  /* Faz o texto virar para dentro */
	}
	.zoom-container {
		overflow: hidden; /* Evita que a imagem ultrapasse a div */		
		cursor: pointer;		
	}
	.zoom-container .zoom-image {
		transition: transform 0.3s ease-in-out;
	}
	.zoom-container:hover .zoom-image {
		transform: scale(1.2); /* Aumenta 20% ao passar o mouse */
	}	
	</style>
	<?php	
	// Função para exibir tarefas, incluindo a busca e organização de $df (data final) e cores baseadas no prazo
	function exibirTarefas($titulo, $tasks, $cor, $mobile, $env) {
		
		include('user_cmp.php');
	
		$teamsInfos = array();
		foreach($teams as $team){
			$teamsInfos[$team] = search('cmp', 'teams', 'tt,im', "id = {$team}");
		}
		
		if($titulo <> '6' && $titulo <> '5' && $titulo <> '99'){			
					
		$statusMap = [
			0 => 'Pendentes',
			1 => 'Iniciadas',
			2 => 'Em Execução'
		];

		$status = $statusMap[$titulo] ?? 'Status Desconhecido';		
		?>
		<div class="large-12 medium-12 small-12">
			<h3 class="color-1 cm-pad-20 cm-pad-0-h"><?= $status.' ('.count($tasks).')' ?></h3>			
			<div class="position-relative centered" style="color: $cor;">
				<div class="large-12 medium-12 small-12 container-x" id="carousel_<?= $titulo ?>">
				<?php
				
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
			
				foreach ($tasks as $task) {
					
					$competencias = [];
					$categories = [];
					
					// Buscar a data final (df) na tabela wa0008_events					
					$df = !empty($task['dt']) ? $task['dt'] : null;

					// Se não encontrar a data final, atribuir uma data padrão para evitar erros
					if (!$df) {
						$df = date('Y-m-d');  // Ajuste conforme necessário para o caso de tarefas sem data final
					}

					$deadline = date("Y-m-d", strtotime($df));
					$today = date('Y-m-d');
					$daysToDeadline = (strtotime($deadline) - strtotime($today)) / (60 * 60 * 24);

					// Definir a cor de fundo inline com base no prazo
					if ($daysToDeadline < 0) {
						$backgroundColor = 'animate-background-dark-red red'; // Vermelho escuro para atrasado
					} elseif ($daysToDeadline == 0) {
						$backgroundColor = 'animate-background-white gray'; // Vermelho claro para vence hoje
					} elseif ($daysToDeadline <= 7) {
						$backgroundColor = 'background-faded-yellow yellow'; // Amarelo para vence dentro de 7 dias
					} else {
						$backgroundColor = 'background-faded-green green'; // Verde para vence acima de 7 dias
					}

					// Cálculo do progresso das etapas da tarefa
					$steps = json_decode($task['step'], true);
					$stepCount = count($steps);
					$completedSteps = count(array_filter($steps, function ($item) {
						return $item['status'] == 1;
					}));
					$progress = $stepCount > 0 ? round(($completedSteps / $stepCount) * 100, 1) : 0;
					
					// Indicar se a tarefa está em execução ou pausada (apenas para tarefas em andamento)
					$statusLabel = '';
					if ($task['st'] == '1') {
						$statusLabel = ' - Pausada';
					} elseif ($task['st'] == '2') {
						$statusLabel = ' - Em execução';				
					}
					$bfr = new DateTime($task['init']);
					
					$level = $task['lv'];  // Nível de dificuldade da tarefa
					$competencias = json_decode($task['hb'], true); // Array de competências
					
					if(!is_array($competencias)){
						$competencias = [$competencias];
					}
					
					if(!empty($task['hb'])){ $n = count($competencias); }else{ $n = 0; }					
					
					
					$im = '';
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
					
					$folder_color = search('app', 'wa0001_tgo', 'cl', "id = {$task['tg']}");					
					$folder_color = (!empty($folder_color)) ? $folder_color[0]['cl'] : '';
					
					?>
					<div class="tab-x cm-pad-5 large-2 medium-3 small-6 orange">
						<div class="large-12 medium-12 small-12 position-relative proportional-4-3">							
							<div onclick="goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', 1, '<?= $task['id'] ?>');" class="zoom-container position-absolute height-100 abs-t-0 abs-l-0 w-shadow-2 w-rounded-20 pointer font-weight-500 large-10-5 medium-12 small-12 bkg-0">
								<div class="zoom-image w-rounded-20 position-absolute abs-t-0 abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12 opacity-05" style="background: url('<?= $im ?>'); background-size: cover; background-position: center; background-repeat: no-repeat; filter: saturate(25%);"></div>
								<div class="w-rounded-20 position-absolute abs-t-0 abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12" style="background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, rgba(0,0,0,0.35) 100%);">									
									<div class="w-rounded-20-b position-absolute abs-t-0 abs-r-0 large-10 medium-10 small-10 cm-pad-10 cm-pad-5-l" >								
										<div class="large-12 medium-12 small-12">
											<?php
											if($xpCalculado > 0){
											?>
											<div class="background-white display-center-general-container cm-pad-10-h float-left cm-mg-5-l text-left text-ellipsis w-rounded-15 fs-c" style="height: 22.5px">
												<a class="font-weight-500" style="vertical-align: middle;"><i class="fas fa-star yellow"></i> <?= str_replace('.',',',$xpCalculado) ?></a>
											</div>
											<?php
											}
											?>
											<div class="text-right float-right fs-f cm-pad-5-r">
												<i class="fas fa-folder centered" style="color: <?= $folder_color ?>"></i>
											</div>																	
											<div class="clear"></div>
										</div>										
									</div>
									<div class="position-absolute abs-b-10 abs-l-45 abs-r-10 text-ellipsis-3 white line-height-b fs-e"><a><?= $task['tt'] ?></a></div>																
								</div>
							</div>							
							<div class="position-absolute abs-t-0 abs-b-0 large-1-5 medium-1-5 small-2 <?= $backgroundColor ?> display-center-general-container w-rounded-15-l">
								<p class="vertical-text text-center centered font-weight-500"><?= ($deadline == $today) ? "Vencendo hoje" : (($daysToDeadline < 0) ? "Venceu em " . strftime('%d/%b', strtotime($deadline)) : "Vence em " . strftime('%d/%b', strtotime($deadline))) ?></p>
								<div class="bkg-2 height-100 position-absolute abs-r-0" style="right: -5px; width: 5px">
									<div class="bkg-1 large-12 medium-12 small-12 abs-b-0" style="height: calc(100% - <?= $progress ?>%)"></div>
								</div>
							</div>												
						</div> 
					</div>				
					<?php
				}	
				?>
				<div class='clear'></div>
				</div>
				<script>
				(function(){
					'use strict';
					var el = document.getElementById('carousel_<?= $titulo ?>');					
					carousel(el);
				})();
				</script>
			</div>
		</div>
		<?php
		}
	}	
	
	if(isset($_GET['folder']) && $_GET['folder'] == ''){
		unset($_GET['folder']);
	}

	$teamsList = '';
	
	$teamFilters = [];
	foreach ($teams as $team) {
		$teamFilters[] = "cm = '{$team}'";
	}
	$teamsList = implode(' OR ', $teamFilters);

	$where = "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '\"{$_SESSION['wz']}\"', '$')";
	if (!empty($teamsList)) {
		$where .= " OR {$teamsList}";
	}
	$where .= ") AND st < 3";

	if (isset($_GET['folder'])) {
		$where .= " AND tg = '{$_GET['folder']}'";
	}

	$tasks = search('app', 'wa0001_wtk', '', $where);


	$tasksByStatus = [];

	foreach ($tasks as &$task) { // Passa por todas as tarefas por referência (&)
		// Inicializa o prazo como padrão
		$task['dt'] = '9999-12-31 23:59:59';

		// Obtém o prazo mais antigo entre subtarefas pendentes
		$steps = json_decode($task['step'], true);
		$menorPrazo = null;

		if (is_array($steps)) {
			foreach ($steps as $step) {
				if (!empty($step['prazo']) && (!isset($step['status']) || $step['status'] === "" || $step['status'] === "0")) {
					$prazoStep = $step['prazo'];

					// Verifica se o prazoStep é o mais antigo
					if ($menorPrazo === null || strtotime($prazoStep) < strtotime($menorPrazo)) {
						$menorPrazo = $prazoStep;
					}
				}
			}
		}

		// Se encontrou um menor prazo, sobrescreve 'dt'
		if ($menorPrazo !== null) {
			$task['dt'] = date('Y-m-d H:i:s', strtotime($menorPrazo));
		} else {
			// Caso contrário, busca na tabela wa0008_events
			$prazoFinal = search('app', 'wa0008_events', 'dt', "el = '{$task['id']}' AND ap = '1'");
			if (!empty($prazoFinal)) {
				$task['dt'] = $prazoFinal[0]['dt'];
			}
		}

		// Agora que `dt` foi definido corretamente, adicionamos ao array de status
		$tasksByStatus[$task['st']][] = $task;
	}


	?>
	<div class="row large-10 medium-10 small-12 cm-mg-15-t">
		<div class="clearfix large-12 medium-12 small-12">
		<div class="float-left large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white w-rounded-30 w-shadow-1">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Pasta</div>							
			<?php
			$tgtsk_user = array_column(search('app', 'wa0001_tgo', 'id', "us = '{$_SESSION['wz']}'"), 'id');
			$folders = array_unique(array_merge(array_column($tasks, 'tg'), $tgtsk_user));

			// Cria um array associativo para manter nome e valor das pastas
			$folder_names = [];
			foreach ($folders as $folder) {
				$folder_names[$folder] = search('app', 'wa0001_tgo', 'tt', "id = '{$folder}'")[0]['tt'];
			}

			// Ordena alfabeticamente com base nos nomes das pastas
			asort($folder_names);
			?>
			<select onchange="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0&folder=' + this.value, '')" 
					class="float-left border-none large-10 medium-10 small-8" 
					style="height: 45px">
				<option value="" selected>Todas</option>
				<?php foreach ($folder_names as $folder => $name): ?>
				<option <?= (isset($_GET['folder']) && $_GET['folder'] == $folder) ? 'selected' : '' ?> 
						value="<?= $folder ?>"><?= $name ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		</div>
	
	<script>
	(function(){
		'use strict';
		
		function carousel(el){
			const carousel = el;
			let isDown = false;
			let startX;
			let scrollLeft;

			carousel.addEventListener("mousedown", (e) => {
				isDown = true;
				carousel.classList.add("active");
				startX = e.pageX - carousel.offsetLeft;
				scrollLeft = carousel.scrollLeft;
			});

			carousel.addEventListener("mouseleave", () => {
				isDown = false;
				carousel.classList.remove("active");
			});

			carousel.addEventListener("mouseup", () => {
				isDown = false;
				carousel.classList.remove("active");
			});

			carousel.addEventListener("mousemove", (e) => {
				if (!isDown) return;
				e.preventDefault();
				const x = e.pageX - carousel.offsetLeft;
				const walk = (x - startX) * 1.5; // Ajuste a velocidade do arraste
				carousel.scrollLeft = scrollLeft - walk;
			});
		}
		window.carousel = carousel;
		
	})();
	</script>
	
	<?php
	if($rotinas = search('app', 'wa0001_wtk', '', "( tp = 1 AND cm = 0 AND us = {$_SESSION['wz']} ) OR ( tp = 1 AND cm > 0 AND JSON_CONTAINS(uscm, '\"{$_SESSION['wz']}\"') )")){
		if(count($rotinas) > 0){
		?>
		<div class="large-12 medium-12 small-12 container-x cm-mg-20-t" id="routines">
			<?php
			foreach($rotinas as $rotina){
				
			?>
			<div class="tab-x pointer w-circle <?= ($rotina['pr'] == 1) ? 'background-faded-green' : 'background-faded-red' ?> w-shadow large-1 medium-2 small-3 display-center-general-container cm-mg-20-r float-left" style="">
				<div class="centered text-center text-ellipsis-2 cm-pad-10 font-weight-500"><?= $rotina['tt'] ?></div>
			</div>
			<?php
			}
			?>
		</div>
		<script>
		(function(){
			'use strict';
			var el = document.getElementById('routines');					
			carousel(el);
		})();
		</script>
		<?php
		}
	}
	
	// Ordena os status de forma decrescente (maior para menor)
	krsort($tasksByStatus);

	foreach ($tasksByStatus as $key => &$tasks) {    
		// Ordena as tarefas dentro de cada status pelo prazo (mais antigo primeiro)
		usort($tasks, function ($a, $b) {
			return strtotime($a['dt']) - strtotime($b['dt']);
		});

		// Exibir tarefas
		exibirTarefas($key, $tasks, '#FF0000', $mobile, $env);
	}
	?>
	</div>	
	<?php	
}
?>
