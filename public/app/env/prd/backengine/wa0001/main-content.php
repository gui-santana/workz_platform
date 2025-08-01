<?php
// Sanitização de subdomínios
include_once('../../../sanitize.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$colors = explode(';', $app['cl']);


// Verificação de dispositivo móvel
$useragent = $_SERVER['HTTP_USER_AGENT'];
$mobile = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) ? 1 : 0;

$colours[0] = $colors[0];


require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/app/core/backengine/wa0001/functions/taskStatus.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/protspot/userGetIdClient.php';

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
	
	if($_GET['vr'] === 'folders'){
		
		// Ações e carregamento de pastas
		if ($_GET['qt'] !== '0') {
			include_once $_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php';
			// Sanitização para evitar injeções SQL e melhorar segurança
			$id = (int) $_GET['qt'];
			$wz = (int) $_SESSION['wz'];

			// Lógica de exclusão de pastas e tarefas relacionada a pastas
			// Código similar pode ser encapsulado em uma função externa, se necessário
			$tasks = search('app', 'wa0001_wtk', 'id', "(us = '{$wz}' AND tg = '{$id}') OR (wz = '{$wz}' AND tg = '{$id}')");
			foreach ($tasks as $task) {
				del('app', 'wa0001_wtk', "id = '{$task['id']}'");
			}
			$remainingTasks = search('app', 'wa0001_wtk', 'id', "(us = '{$wz}' AND tg = '{$id}') OR (wz = '{$wz}' AND tg = '{$id}')");
			if (count($remainingTasks) === 0) {
				del('app', 'wa0001_tgo', "id = '{$id}' AND us = '{$wz}'");
			}
		}

		echo '<div id="folder-root" class="large-10 medium-12 small-12 position-relative centered">';
		include 'folder-root.php';
		echo '</div>';
	
	}elseif ($_GET['vr'] === 'regs'){
		
		// Exibição de registros
        $tasks = search('app', 'wa0001_wtk', 'id,tt,tg,us,st,time,init', "wz = '{$_SESSION['wz']}'");
        
        if (count($tasks) > 0) {
            echo "<div class=\"large-10 medium-12 small-12 position-relative centered\" style=\"color: {$colours[0]};\">";
            echo '<h3 class="text-ellipsis cm-mg-20-t">Meus registros</h3>';
            echo '<div class="large-12 medium-12 small-12 overflow-x-auto">';
            echo '<div style="min-width: 800px">';
            foreach ($tasks as $task) {
                $folder = search('app', 'wa0001_tgo', '', "id = '{$task['tg']}'")[0]['tt'] ?? 'Não especificada';
                $deadline = search('app', 'wa0008_events', 'dt', "el = '{$task['id']}' AND ap = '1'")[0]['dt'];
                $user = ($task['us'] === $_SESSION['wz']) ? 'mim' : search('hnw', 'hus', 'tt', "id = '{$task['us']}'")[0]['tt'];
                
                echo "<div class=\"cm-pad-10 cm-pad-10-h large-12 medium-12 small-12 position-relative text-ellipsis border-t-input pointer w-color-bl-to-or\" onclick=\"goTo('core/backengine/wa0001/m_task.php', 'main-content', 1, '{$task['id']}');\">";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">{$task['tt']}</div>";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">{$folder}</div>";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">{$user}</div>";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">" . date('d/m/Y', strtotime($deadline)) . "</div>";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">{$task['st']}</div>";
                echo "<div class=\"float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r\">" . gmdate('H:i:s', $task['time']) . "</div>";
                echo '<div class="clear"></div></div>';
            }
            echo '</div></div></div>';
        } else {
            echo "<div class=\"large-10 medium-12 small-12 position-relative centered cm-mg-20-t\" style=\"color: {$colours[0]};\">";
            echo '<div class="cm-mg-20-t large-12 medium-12 small-12 cm-pad-15 text-center">';
            echo '<p><strong>Dica para uso dos Registros</strong>:</p><p>Acompanhe o tempo de execução de suas tarefas.</p>';
            echo '<p>Utilize os dados para gerar seus indicadores de performance.</p></div></div>';
        }
	}
	
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
	function exibirTarefas($titulo, $tasks, $cor, $mobile) {
		
		include('user_cmp.php');
	
		$teamsInfos = array();
		foreach($teams as $team){
			$teamsInfos[$team] = search('cmp', 'teams', 'tt,im', "id = {$team}");
		}
		
		if($titulo <> '6' && $titulo <> '5' && $titulo <> '99'){
		
		// Ordenar as tarefas pelo campo $df em ordem ascendente (do passado para o futuro)
		usort($tasks, function($a, $b) {
			$dfA = search('app', 'wa0008_events', 'dt', "el = '{$a['id']}' AND ap = '1'");
			$dfB = search('app', 'wa0008_events', 'dt', "el = '{$b['id']}' AND ap = '1'");
			return strtotime($dfA[0]['dt'] ?? '9999-12-31') - strtotime($dfB[0]['dt'] ?? '9999-12-31');
		});
		
		$statusMap = [
			0 => 'Pendentes',
			1 => 'Em Pausa',
			2 => 'Em Andamento'
		];

		$status = $statusMap[$titulo] ?? 'Status Desconhecido';		
		?>
		<div class="large-12 medium-12 small-12">
			<div class="text-ellipsis cm-pad-30-t cm-pad-0-b cm-mg-5-l white fs-f font-weight-600"><?= $status.' ('.count($tasks).')' ?></div>
			<div class="position-relative centered cm-mg-10-t" style="color: $cor;">
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
					$eventData = search('app', 'wa0008_events', 'dt', "el = '{$task['id']}' AND ap = '1'");
					$df = !empty($eventData) ? $eventData[0]['dt'] : null;

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
						$backgroundColor = 'animate-background-red red'; // Vermelho claro para vence hoje
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
					<div class="tab-x cm-pad-5 large-2 medium-3 small-6 orange">
						<div class="large-12 medium-12 small-12 position-relative proportional-4-3">												
							<div onclick="goTo('core/backengine/wa0001/m_task.php', 'main-content', 1, '<?= $task['id'] ?>');" class="zoom-container position-absolute height-100 abs-t-0 abs-l-0 w-shadow-2 w-rounded-20 pointer font-weight-500 large-10-5 medium-12 small-12">
								<div class="zoom-image w-rounded-20 position-absolute abs-t-0 abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12" style="background: url('<?= $im ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
								<div class="w-rounded-20 position-absolute abs-t-0 abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12 background-black-transparent-50">	
									<div class="w-rounded-20-b position-absolute abs-t-0 abs-r-0 large-10 medium-10 small-10 cm-pad-10 cm-pad-5-l" >								
										<div class="large-12 medium-12 small-12">
											<div class="large-8 medium-8 small-8 float-left text-left text-ellipsis white">
												<a class="font-weight-500" style="vertical-align: middle;"> <?= $dificuldade[$level] ?></a>
											</div>
											<div class="large-4 medium-4 small-4 text-right float-left">
												<?=(!empty($task['cm'])) ? "<img class='w-shadow-1 w-square w-rounded-5 z-index-1' style='height: 20px; width: 20px;' src='data:image/jpeg;base64,".$teamsInfos[$task['cm']][0]['im']."'></img>" : '' ?>							
											</div>
											<div class="clear"></div>
										</div>
										<div class="text-ellipsis-2 white font-weight-500 fs-f line-height-b cm-pad-5-l cm-mg-10-t"><a><?= $task['tt'] ?></a></div>
									</div>
									<div class="w-rounded-20-b position-absolute abs-b-0 abs-r-0 large-10 medium-10 small-10  cm-pad-10 cm-pad-5-l" >								
										<div class="text-left white fs-b">
											<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-mg-5-b">
												<span class="fa-stack" style="vertical-align: middle;">
													<i class="fas fa-circle fa-stack-2x white"></i>
													<i class="fas fa-star fa-stack-1x fa-inverse orange"></i>					
												</span>																	
												<a class="font-weight-500" style="vertical-align: middle;"> <?= $xpCalculado ?> Pts</a>
											</div>
											<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-mg-5-b">
												<span class="fa-stack" style="vertical-align: middle;">
													<i class="fas fa-circle fa-stack-2x white"></i>
													<i class="fas fa-user-ninja fa-stack-1x fa-inverse orange"></i>					
												</span>																	
												<a class="font-weight-500" style="vertical-align: middle;"> <?= $n ?> Competências</a>
											</div>			
											<div class="large-12 medium-12 small-12 text-ellipsis display-block cm-mg-5-b">
												<span class="fa-stack" style="vertical-align: middle;">
													<i class="fas fa-circle fa-stack-2x white"></i>
													<i class="fas fa-hourglass-half fa-stack-1x fa-inverse orange"></i>					
												</span>																	
												<a class="font-weight-500" style="vertical-align: middle;"> <?= gmdate('H:i:s', $task['time']) ?></a>									
											</div>
											<div class="large-12 medium-12 small-12 text-ellipsis display-block">
												<span class="fa-stack" style="vertical-align: middle;">
													<i class="fas fa-circle fa-stack-2x white"></i>
													<i class="fas fa-spinner fa-stack-1x fa-inverse orange"></i>					
												</span>																	
												<a class="font-weight-500" style="vertical-align: middle;"> <?= $progress ?>%</a>									
											</div>
										</div>								
									</div>
								</div>
							</div>     
							
							<div class="position-absolute abs-t-0 abs-b-0 large-1-5 medium-1-5 small-2 <?= $backgroundColor ?> display-center-general-container w-rounded-15-l">
								<p class="vertical-text text-center centered font-weight-500"><?= ($daysToDeadline < 0) ? "Venceu em " . strftime('%d/%b', strtotime($deadline)) : "Vence em " . strftime('%d/%b', strtotime($deadline)) ?></p>
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

	//Obter todas as tarefas
	if(isset($_GET['folder'])){
		$tasks = search('app', 'wa0001_wtk', '', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')) AND st < 3 AND tg = '{$_GET['folder']}'");	
	}else{
		$tasks = search('app', 'wa0001_wtk', '', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')) AND st < 3");	
	}
	$tasksByStatus = [];
	
	foreach($tasks as $task){
		// Exemplo: se $task['st'] for '0', ele agrupa em $tasksByStatus['0'][]
		$tasksByStatus[$task['st']][] = $task;
	}
	?>
	<div class="row large-10 medium-10 small-12">
		<div class="large-12 medium-10 small-12">
		<div class="float-left large-6 medium-6 small-6 text-ellipsis display-center-general-container text-ellipsis background-white w-rounded-15 w-shadow">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Pasta</div>							
			<?php
			$folders = search('app', 'wa0001_tgo', '', "us = '{$_SESSION['wz']}' AND st = '0'");
			?>
			<select onchange="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0&folder=' + this.value, '')" class="float-left border-none large-10 medium-10 small-8" style="height: 45px">
				<option value="" selected>Todas</option>
				<?php foreach ($folders as $folder): ?>
				<option <?= (isset($_GET['folder']) && $_GET['folder'] == $folder['id']) ? 'selected' : '' ?> value="<?= $folder['id'] ?>"><?= $folder['tt'] ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="float-left large-6 medium-6 small-6 cm-pad-10-l ">
			<input type="text" class="w-rounded-15 cm-pad-10-h w-shadow float-left border-none large-12 medium-12 small-12" placeholder="Pesquisar" style="height: 45px"></input>
		</div>
		<div class="clear"></div>
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
	
	krsort($tasksByStatus);
	foreach($tasksByStatus as $key => $tasks){
		//print_r($key);
		exibirTarefas($key, $tasks, '#FF0000', $mobile);
	}
	?>
	</div>	
	<?php	
}
?>
