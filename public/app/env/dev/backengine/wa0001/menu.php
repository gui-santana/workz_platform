<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
date_default_timezone_set('America/Sao_Paulo');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

if($_GET['qt'] == 1 || $_GET['qt'] == 2 || $_GET['qt'] == 3){

$tipos = [
    1 => "Tarefa",
    2 => "Pasta",
    3 => "Rotina"
];

$tipo = $_GET['qt'] ?? null; // Evita erro se 'qt' não for passado
$id = $_GET['id'] ?? null;
$modoEdicao = !empty($id);

?>
<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
	<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
		<div onclick="<?= ($modoEdicao) ? 'toggleSidebar()' : 'loadMainMenu()' ?>" class="display-center-general-container w-color-bl-to-or pointer">
			<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
			<a>Voltar</a>
		</div>
	</div>		
</div>
<?php
//Nova Pasta


$opcoes = [
    1 => ["arquivo" => "m_task.php", "name" => "tsktt"],
    2 => ["arquivo" => "folder.php", "name" => "tgttt"],
    3 => ["arquivo" => "m_task.php", "name" => "tsktt"]
];

//$formAction = isset($opcoes[$tipo]) ? $opcoes[$tipo]["arquivo"] : "";
$formAction = 'edit.php';

$inputName = isset($opcoes[$tipo]) ? $opcoes[$tipo]["name"] : "";

if($tipo == 1 || $tipo == 3){
	$dados = $modoEdicao ? (search('app', 'wa0001_wtk', 'id,tp,us,wz,tg,cm,pr,prpe,lv,st,tt,ds,wg,init,time,hb,step', "id = '$id'")[0] ?? []) : [];
}elseif($tipo == 2){
	$dados = $modoEdicao ? (search('app', 'wa0001_tgo', '', "id = '$id'")[0] ?? []) : [];
}
// Se for edição, busca os dados da tarefa/pasta/rotina

$action = $modoEdicao ? 'update' : 'insert';
?>
<div class="large-12 medium-12 small-12 text-center gray">
	<h2><?= $modoEdicao ? "Editar" : "Nova" ?> <?= $tipos[$tipo] ?></h2>
</div>
<div id="divForm" class="large-12 medium-12 small-12 cm-pad-20 centered">
	<?php
	if($modoEdicao){ ?> <input type="hidden" name="id" value="<?= $dados['id'] ?>" ></input> <?php }
	?>
	<div class="w-shadow w-rounded-15">
        <div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white w-rounded-15-t">
            <div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Título</div>
            <input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required"
                style="height: 45px" id="tt" name="tt" placeholder="Adicionar o título da <?= $tipos[$tipo] ?>" value="<?= $modoEdicao ? htmlspecialchars($dados['tt'] ?? '') : '' ?>">
        </div>
		<?php
		if($modoEdicao && ($tipo == 1 || $tipo == 3)){
			$statuses = [
				0 => 'Pendente',
				1 => 'Em pausa',
				2 => 'Em andamento',
				3 => 'Finalizada',
				5 => 'Finalizada',
				6 => 'Arquivada'
			];	
			
			// Convertendo o timestamp para o formato H:i:s
			$hours = floor($dados['time'] / 3600);
			$minutes = floor(($dados['time'] - ($hours * 3600)) / 60);
			$seconds = $dados['time'] - ($hours * 3600) - ($minutes * 60);

			$formattedTime = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
		?>		
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Tempo</div>
			<input type="hidden" name="init" value="0000-00-00 00:00:00"></input>
			<input type="hidden" name="time" id="time" value="<?= $dados['time'] ?>"></input>
			<input type="time" onchange="changeTime(this.value)" class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l"
				style="min-height: 45px;" step="2" value="<?= $formattedTime ?>"></input>				
		</div>
		<script>
			(function(){
				'use strict';
				
				function changeTime(time){
					if(!time){
						console.log('Insira um valor válido.');
						return;
					}					
					const [hours, minutes, seconds = 0] = time.split(':').map(Number);										
					const totalSeconds = (hours * 3600) + (minutes * 60) + seconds;								
					document.getElementById('time').value = totalSeconds;
				}
				window.changeTime = changeTime;
				
			})();
		</script>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Status</div>			
			<select name="st" id="st" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">				
				<?php foreach($statuses as $key => $status){ ?>
				<option value="<?= $key ?>" <?= ($dados['st'] == $key) ? 'selected' : '' ?>><?= $status ?></option>
				<?php } ?>
			</select>
		</div>
		<?php
		}
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Observações</div>
			<textarea id="ds" name="ds" class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l"
				style="min-height: 150px; line-height: 1.5em;" placeholder="Adicionar notas"><?= $modoEdicao ? htmlspecialchars($dados['ds'] ?? '') : '' ?></textarea>
		</div>
		<?php
		if ($tipo == 1 || $tipo == 3){
		//CAMPOS EXCLUSIVOS PARA TAREFAS E HÁBITOS
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">										
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Pasta</div>							
			<?php
			$folders = search('app', 'wa0001_tgo', '', "us = '{$_SESSION['wz']}' AND st = '0'");
			?>
			<select name="tg" id="tg" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">												
				<option value="" <?= ($modoEdicao) ? '' : 'selected' ?> disabled>Selecione</option>
				<?php foreach ($folders as $folder): ?>
				<option value="<?= $folder['id'] ?>" <?= ($modoEdicao && $dados['tg'] == $folder['id']) ? 'selected' : '' ?>><?= $folder['tt'] ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Equipe</div>							
			<?php
			include('user_cmp.php');
			?>
			<select name="cm" id="cm" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px" onchange="selectUsers(this.value)" onchange="">
				<option value="0">Somente eu</option>
				<?
				foreach($teams as $team){
					$teamInfo = search('cmp', 'teams', 'tt', "id = '".$team."'")[0];
				?>
				<option value="<?= $team ?>" <?= ($modoEdicao && $dados['cm'] == $team) ? 'selected' : '' ?>><?= $teamInfo['tt'] ?></option>
				<?	
				}
				?>
			</select>
		</div>
		<div id="teamUsersContainer" class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white"></div>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">										
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Dificuldade</div>										
			<select name="lv" id="lv" class="float-left border-none large-10 medium-10 small-8" style="height: 45px">
				<option value="0" <?= ($modoEdicao && $dados['lv'] == 0) ? 'selected' : '' ?>>⚪ Trivial</option>
				<option value="1" <?= ($modoEdicao && $dados['lv'] == 1) ? 'selected' : '' ?>>🟢 Fácil</option>
				<option value="2" <?= ($modoEdicao && $dados['lv'] == 2) ? 'selected' : '' ?>>🟡 Médio</option>
				<option value="3" <?= ($modoEdicao && $dados['lv'] == 3) ? 'selected' : '' ?>>🔴 Difícil</option>
				<option value="4" <?= ($modoEdicao && $dados['lv'] == 4) ? 'selected' : '' ?>>⚫ Extremo</option>
			</select>
		</div>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Frequência</div>			
			<select onchange="toggleCustomFrequency(this.value)" name="pr" id="pr" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">
				<option value="0" <?= ($modoEdicao && $dados['pr'] == 0) ? 'selected' : '' ?>>Única (Não recorrente)</option>
				<option value="1" <?= ($modoEdicao && $dados['pr'] == 1) ? 'selected' : '' ?>>Diária</option>
				<option value="2" <?= ($modoEdicao && $dados['pr'] == 2) ? 'selected' : '' ?>>Semanal</option>
				<option value="3" <?= ($modoEdicao && $dados['pr'] == 3) ? 'selected' : '' ?>>Mensal</option>
				<option value="4" <?= ($modoEdicao && $dados['pr'] == 4) ? 'selected' : '' ?>>Bimestral</option>
				<option value="5" <?= ($modoEdicao && $dados['pr'] == 5) ? 'selected' : '' ?>>Trimestral</option>
				<option value="6" <?= ($modoEdicao && $dados['pr'] == 6) ? 'selected' : '' ?>>Semestral</option>
				<option value="7" <?= ($modoEdicao && $dados['pr'] == 7) ? 'selected' : '' ?>>Anual</option>
				<option value="8" <?= ($modoEdicao && $dados['pr'] == 8) ? 'selected' : '' ?>>🔄 Personalizado</option>
			</select>
		</div>
		<!-- FREQUÊNCIA PERSONALIZADA -->
		<div id="customFrequencyContainer"></div>
		<script>					
		(function() {
			'use strict';
			
			<?php if($modoEdicao && !empty($dados['cm'])){ ?>	selectUsers(<?= $dados['cm'] ?>); <?php	} ?>
			
			function selectUsers(team){
				const teamUsersContainer = document.getElementById('teamUsersContainer');				
				teamUsersContainer.classList.add('border-t-input');
				if(team > 0){
					let teamUsers = '<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Atribuído a</div>'+
									'<select id="teamUsers" class="large-10 medium-10 small-8 float-left border-none" style="height: 45px;" multiple></select>'+
									'<input type="hidden" name="uscm" id="uscm">';
					teamUsersContainer.innerHTML = teamUsers;
					waitForElm('#teamUsers').then((elm) => {
						goPost('env/<?= $env ?>/backengine/wa0001/user_cmp.php?func=1-1<?= ($modoEdicao) ? '&task='.$dados['id'] : '' ?>', 'teamUsers', team, '');
						$('#teamUsers').select2({
							placeholder: "Selecione os membros",
							allowClear: true
						});
						// Atualiza o input hidden com os valores do select2
						$('#teamUsers').on('change', function() {
							$('#uscm').val(JSON.stringify($(this).val()));
						});

					});								
				}else{
					teamUsersContainer.innerHTML = "";
					teamUsersContainer.classList.remove('border-t-input');
				}
			}
			window.selectUsers = selectUsers;

			function toggleCustomFrequency(value) {
				const customContainer = document.getElementById('customFrequencyContainer');
				const isCustom = value === "8"; // Verifica se o valor selecionado é "8" (Personalizado)

				if (isCustom) {
					let customInputs = 
							'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">'+		
								'<div class="float-left large-4 medium-4 small-4 text-ellipsis cm-pad-15-l">Repetir a cada</div>'+
								'<input type="number" class="float-left border-none large-4 medium-4 small-4 cm-pad-5-l required" id="prCustomValue" '+
								'style="height: 45px" name="prCustomValue" min="1" placeholder="Nº">'+
								'<select class="float-left border-none large-4 medium-4 small-4 cm-pad-5-l required" id="prCustomType" '+
								'name="prCustomType" style="height: 45px">'+
									'<option value="1">Dias</option>'+
									'<option value="2">Semanas</option>'+
									'<option value="3">Meses</option>'+
								'</select>'+
							'</div>';
					customContainer.innerHTML = customInputs;
				} else {
					// Remove os inputs se a opção personalizada não estiver selecionada
					customContainer.innerHTML = "";
				}
			}
			window.toggleCustomFrequency = toggleCustomFrequency;

			// Verifica a seleção ao carregar a página (para edição)
			document.addEventListener("DOMContentLoaded", function() {
				const prSelect = document.getElementById('pr');
				if (prSelect) {
					toggleCustomFrequency(prSelect.value);
				}
			});
			
		})();
		</script>
		<?php
		if($tipo == 1){
		// CAMPOS EXCLUSIVOS PARA TAREFAS		
		if($modoEdicao){
			$tskd = search('app', 'wa0008_events', 'dt', "el = '".$dados['id']."' AND ap = '1'");
			if(count($tskd) > 0){
				$df	= $tskd[0]['dt'];
			}					
		}		
		?>            
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Prazo final</div>			
			<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" style="height: 45px" id="df" name="df" type="datetime-local" onchange="atualizarLimites()" value="<?= $modoEdicao ? $df : '0' ?>"></input>
		</div>		
		<div id="inputContainer" class="large-12 medium-12 small-12 w-rounded-15-b">			
			<?php			
			if($modoEdicao){
				$steps = json_decode($dados['step'], true);
				foreach($steps as $key => $step){
				?>
				<div id="taskStep_<?= $key ?>" class="fieldsContainer large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
					<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Tarefa <?= $key + 1 ?></div>			
					<input name="step_<?= $key ?>" type="text" class="float-left border-none large-7 medium-7 small-5 cm-pad-5-l required" style="height: 45px" value="<?= $step['titulo'] ?>"></input>
					<input name="step_dt_<?= $key ?>" type="datetime-local" class="float-left border-none large-3 medium-3 small-3 cm-pad-5-l border-l-input" style="height: 45px" onchange="checkDate(this)" value="<?= $step['prazo'] ?>"></input>
				</div>
				<?php
				}
			}else{
			?>
			<div id="taskStep_0" class="fieldsContainer large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
				<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Tarefa 1</div>			
				<input name="step_0" type="text" class="float-left border-none large-7 medium-7 small-5 cm-pad-5-l required" style="height: 45px"></input>
				<input name="step_dt_0" type="datetime-local" class="float-left border-none large-3 medium-3 small-3 cm-pad-5-l border-l-input" style="height: 45px" onchange="checkDate(this)"></input>
			</div>
			<?php 
			} 
			?>
		</div>
		<div id="addButtonContainer" class="large-12 medium-12 small-12 text-ellipsis background-white border-t-input w-rounded-15-b" style="height: 45px">
			<div onclick="addStep();" class="float-left large-6 medium-6 small-6 height-100 w-bkg-tr-gray pointer display-center-general-container text-center"><i class="fas fa-plus centered"></i></div>
			<div onclick="remStep();" class="float-left large-6 medium-6 small-6 height-100 w-bkg-tr-gray pointer display-center-general-container text-center border-l-input"><i class="fas fa-minus centered"></i></div>
			<div class="clear"></div>			
		</div>
		<script>
		(function () {						
			'use strict';
			
			function atualizarLimites() {
				let prazoFinal = document.getElementById("df").value;
				let tarefasIntermediarias = document.querySelectorAll(".task-date");

				tarefasIntermediarias.forEach(function(input) {
					input.setAttribute("max", prazoFinal);
				});
			}
			window.atualizarLimites = atualizarLimites;
			
			function checkDate(input) {
				let prazoFinal = document.getElementById("df").value;

				if (input.value > prazoFinal) {
					alert("A data da tarefa intermediária deve ser menor ou igual ao prazo final!");
					input.value = prazoFinal; // Ajusta automaticamente para o prazo final
				}
			}
			window.checkDate = checkDate;		
			
			function addStep() {
				var inputContainer = document.getElementById('inputContainer');
				var containers = inputContainer.getElementsByClassName('fieldsContainer');
				var n = containers.length;

				// Criando o container da nova tarefa
				var newStep = document.createElement("div");
				newStep.id = "taskStep_" + n;
				newStep.className = "fieldsContainer large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input";
				
				// Criando o label da nova tarefa
				var label = document.createElement("div");
				label.className = "float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l";
				label.innerText = "Tarefa " + (n + 1);
				
				// Criando o input de texto
				var textInput = document.createElement("input");
				textInput.name = "step_" + n;
				textInput.type = "text";
				textInput.className = "float-left border-none large-7 medium-7 small-5 cm-pad-5-l required";
				textInput.style.height = "45px";
				
				// Criando o input de data/hora
				var dateInput = document.createElement("input");
				dateInput.name = "step_dt_" + n;
				dateInput.type = "datetime-local";
				dateInput.className = "float-left border-none large-3 medium-3 small-3 cm-pad-5-l border-l-input";
				dateInput.style.height = "45px";
				dateInput.setAttribute("onchange", "checkDate(this)");

				// Adicionando os elementos ao novo step
				newStep.appendChild(label);
				newStep.appendChild(textInput);
				newStep.appendChild(dateInput);

				// Adicionando o novo step ao container sem apagar os anteriores
				inputContainer.appendChild(newStep);
			}

			window.addStep = addStep;
			
			function remStep(){
				var inputContainer = document.getElementById('inputContainer');
				var containers = inputContainer.getElementsByClassName('fieldsContainer');
				var n = containers.length;		
				if(n > 1){
					var elementToRemove = inputContainer.querySelector('#taskStep_' + (n - 1));
					if (elementToRemove) {
						elementToRemove.remove();
					}
				}
			}
			window.remStep = remStep;
		})();
		</script>
		<?php 
		};		
		if($tipo == 3){
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input  w-rounded-15-b">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Impacto</div>
			<select name="pa" id="pa" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px">
				<option value="positivo">✅ Positivo</option>
				<option value="negativo">❌ Negativo</option>
			</select>
		</div>
		<?php
		}		
		?>
	
		<?php
		}
		if($tipo == 2){
		//CAMPOS EXCLUSIVOS PARA PASTAS
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input w-rounded-15-b">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Cor de identificação</div>			
			<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l background-white required" style="height: 45px" id="cl" name="cl" type="color" value="<?= ($modoEdicao) ? $dados['cl'] : '' ?>"></input>
		</div>			
		<?php 
		}
		?>		
	</div>
	<?php 
	if ($tipo == 1 || $tipo == 3){
	 $selectedSkills = [];

    if ($modoEdicao) {
        $selectedSkills = isset($dados['hb']) && !empty($dados['hb']) ? json_decode($dados['hb'], true) : [];
        
        // Garantir que seja um array
        if (!is_array($selectedSkills)) {
            $selectedSkills = [];
        }
    }
	?>
	<div class="w-shadow w-rounded-15 cm-mg-20 cm-mg-0-h">		
		<div id="skillsContainer" class="large-12 medium-12 small-12 cm-pad-15 cm-pad-0-t text-ellipsis background-white w-rounded-15 ease-all-5s" style="height: 45px">
		<div class="float-left large-12 medium-12 small-12 text-ellipsis" style="height: 45px; padding-top: 12px">Competências			
			<span onclick="skillsToggle()" class="float-right pointer">
				<i class="fas fa-bars"></i>					
			</span>
		</div>										
		<?php			
		if($categories = search('app', 'wa0001_categories', '', '')){
			foreach($categories as $category){						
				echo '<div class="large-12 medium-12 small-12 cm-pad-10 cm-pad-0-h border-t-input text-ellipsis"><h4 class="cm-mg-5-b text-ellipsis">'.$category['nm'].'</h4>';
				if($skills = search('app', 'wa0001_skills', '', "ct = {$category['id']}")){
					foreach($skills as $skill){
						?>
						<div class="large-12 medium-12 small-15 cm-pad-5 cm-pad-0-l">
							<input id="<?= $category['nm'].'_'.$skill['id'] ?>" name="hb" value="<?= $skill['id'] ?>" <?= ($modoEdicao && in_array($skill['id'], $selectedSkills)) ? 'checked' : '' ?> type="checkbox"></input>
							<label class="cm-mg-5-l" for="<?= $category['nm'].'_'.$skill['id'] ?>"><?= $skill['nm'] ?></label>
						</div>
						<?php
						echo '';
					}
				}
				echo '</div>';
			}
		}
		?>			
		</div>
	</div>
	<script>
		(function(){
			'use strict';
			
			function skillsToggle(){
				const skillsContainer = document.getElementById('skillsContainer');
				
				if (skillsContainer.style.height == '45px') {										
					skillsContainer.style.height = 'auto';
				}else{
					skillsContainer.style.height = '45px'
				}				
			}
			window.skillsToggle = skillsToggle;
			
		})();
	</script>
	<?php 
	}
	?>	
	<?php
	if($modoEdicao && ($tipo == 1 || $tipo == 3)){
	?>
	<div class="w-shadow w-rounded-15 cm-mg-20 cm-mg-0-h">		
		<div class="w-rounded-15 large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Motivo da Alteração</div>
			<textarea id="motivo" name="motivo" class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l required"
				style="min-height: 150px; line-height: 1.5em;" placeholder="Adicione o motivo da alteração"></textarea>
		</div>
	</div>
	<?php
	}
	?>
	<div onclick="formValidator2('divForm', 'env/<?= $env ?>/backengine/wa0001/<?= $formAction ?>?action=<?= $action ?>&type=<?= $tipo ?>', 'main-content');" class="text-ellipsis cm-pad-10 large-12 medium-12 small-12 w-color-bl-to-or pointer w-bkg-wh-to-gr w-shadow w-rounded-15 cm-mg-20-t">
		<span class="fa-stack orange" style="vertical-align: middle;">
			<i class="fas fa-circle fa-stack-2x light-gray"></i>
			<i class="fas fa-save fa-stack-1x fa-inverse dark"></i>					
		</span>						
		Salvar											
	</div>	
</div>

<?php
}elseif($_GET['qt'] == 4 || $_GET['qt'] == 5){

$tipos = [
	4 => "a Recompensa",
	5 => "o Desafio"
];
$tipo = $_GET['qt'] ?? null; // Evita erro se 'qt' não for passado
?>
<div class="large-12 medium-12 small-12 text-center gray">	
	<h2>Nov<?= isset($tipos[$_GET['qt']]) ? $tipos[$_GET['qt']] : ""; ?></h2>	
</div>
<div id="new" class="large-12 medium-12 small-12 cm-pad-20 centered">  
	<div id="divForm" class="w-shadow w-rounded-15">		
        <div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white w-rounded-15-t">
            <div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Título</div>
            <input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" style="height: 45px" id="tt" name="Título" placeholder="Adicionar o título d<?= $tipos[$tipo] ?>"></input>
        </div>
		<?php
		if($tipo == 4){
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input w-rounded-15-b">
            <div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Valor</div>
			<input type="number" min="0.01" step="0.01" class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" id="rcValue" style="height: 45px" name="rcValue" placeholder="Valor em WZD" />
        </div>
		<?php
		}elseif($tipo == 5){
		?>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Anúncio</div>
			<textarea id="ds" name="ds" class="float-left border-none large-10 medium-10 small-8 required cm-pad-10 cm-pad-5-l"
				style="min-height: 150px; line-height: 1.5em;" placeholder="Anuncie o desafio informando o objetivo, o motivo pelo qual as pessoas de sua equipe deveriam participar e demais informações relevantes."></textarea>
		</div>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input">										
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Equipe</div>
			<?php
			include('user_cmp.php');
			?>
			<select name="cm" id="cm" class="float-left border-none large-10 medium-10 small-8 required" style="height: 45px" onchange="goPost('env/<?= $env ?>/backengine/wa0001/user_cmp.php?func=5-1', 'teamTasks', this.value, '')">
				<option value="" disabled selected>Selecione</option>
				<?
				foreach($teams as $team){
				?>
				<option value="<?= $team ?>"><?= search('cmp', 'teams', 'tt', "id = '".$team."'")[0]['tt'] ?></option>
				<?	
				}
				?>				
			</select>
		</div>
		
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container text-ellipsis background-white border-t-input" style="min-height: 45px">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Tarefa(s)</div>						
			<select name="cm" id="teamTasks" class="float-left border-none large-10 medium-10 small-8 required" multiple>				
			</select>
		</div>
		<script>
		(function(){
			'use strict';
			$('#teamTasks').select2({
				placeholder: "Selecione opções",
				allowClear: true
			});
			
		})();
		</script>
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Início</div>			
			<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" style="height: 45px" id="df" name="df" type="datetime-local"></input>
		</div>	
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input">
			<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Fim</div>			
			<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" style="height: 45px" id="df" name="df" type="datetime-local"></input>
		</div>	
		<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input w-rounded-15-b">
            <div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Valor da aposta</div>
			<input type="number" min="0.01" step="0.01" class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" id="rcValue" style="height: 45px" name="rcValue" placeholder="Valor da aposta em WZD" />
        </div>
		<?php
		}
		?>
	</div>
	<div onclick="formValidator2('divForm', 'env/<?= $env ?>/backengine/wa0001/folder.php?action=include&type=<?= $tipo ?>', 'main-content');" class="text-ellipsis cm-pad-10 large-12 medium-12 small-12 w-color-bl-to-or pointer w-bkg-wh-to-gr w-shadow w-rounded-15 cm-mg-20-t">
		<span class="fa-stack orange" style="vertical-align: middle;">
			<i class="fas fa-circle fa-stack-2x light-gray"></i>
			<i class="fas fa-save fa-stack-1x fa-inverse dark"></i>					
		</span>						
		Salvar											
	</div>
</div>
<?php
//E-mails
}elseif($_GET['qt'] == 6){
?>
<div class="large-12 medium-12 small-12 text-center gray">
	<h2>Carregar E-mails</h2>
	
	<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white w-rounded-15-t">
		<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Título</div>
		<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l required" style="height: 45px" id="tt" name="" placeholder="Endereço de e-mail"></input>
	</div>
	
<button onclick="classifyEmail('Prezados, boa noite! @Guilherme Santana - IBH Serviços recebemos esta NF. Conforme conversamos, o envio para pagamento de todas as NF’s relacionados ao RI ficam sob sua responsabilidade, correto? Precisamos alinhar este fluxo para não ocorrer riscos de pendências ou duplicidades nos pagamentos. Ficamos no aguardo. Caso dúvidas, estou a disposição. Atenciosamente');">Teste</button>
<script>
(function () {								
	'use strict';																																		
	//Zera o valor de init registrado no BD
	
	async function classifyEmail(emailText) {
		const response = await fetch("https://workz.space/classify", {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
				"Authorization": "Bearer 1G,B:{Z08E+(R+a?:P77|VmljEY9E$"
			},
			body: JSON.stringify({ email_text: emailText })
		});

		const result = await response.json();
		console.log(result);
	}
	
	window.classifyEmail = classifyEmail;											
})();
</script>
</div>
<?php
}
?>