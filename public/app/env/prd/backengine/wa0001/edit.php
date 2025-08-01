<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');
session_start();
date_default_timezone_set('America/Sao_Paulo');
$now = date('Y-m-d H:i:s');

// Verifica se o usuário está autenticado
if (!isset($_SESSION["wz"])) {
    die(json_encode(["error" => "Usuário não autenticado."]));
}

$tipo = $_GET['type']; // 1 = Tarefa, 2 = Pasta, 3 = Hábito/Rotina

if(isset($_POST) && isset($_GET['action'])){

	//Nova Pasta / Tarefa / Rotina		
	$_POST = json_decode($_POST['vr'], true);			

	// Dados comuns
	$tt = $_POST['tt'] ?? "";
	$ds = $_POST['ds'] ?? ""; //Observação
	$us = $_SESSION['wz'];

	$dados = [
		'us' => $us,
		'tt' => $tt,
		'ds' => $ds
	];

	if ($tipo == 1 || $tipo == 3) {
					
		$tg = $_POST['tg'] ?? ""; //Pasta
		$cm = $_POST['cm'] ?? ""; //Equipe			
		$lv = $_POST['lv'] ?? ""; //Dificuldade			
		$hb = isset($_POST['hb']) ? json_encode($_POST['hb']) : "[]"; // Convertendo para JSON
		$pr = $_POST['pr'] ?? ""; //Frequência
		$uscm = $_POST['uscm'] ?? ""; //Membros
		$prpe = null;

		// Se for personalizada, armazena JSON
		if ($pr == 8) {
			$prpe = json_encode([
				"intervalo" => (int) $_POST['prCustomValue'], // Número de dias, semanas ou meses
				"unidade" => (int) $_POST['prCustomType'] // 1 = Dias, 2 = Semanas, 3 = Meses
			]);
		}

		$dados += [				
			'tg' => $tg,
			'cm' => $cm,				
			'lv' => $lv,
			'hb' => $hb,
			'pr' => $pr,
			'uscm' => $uscm,
			'prpe' => $prpe
		];
		
		if ($tipo == 1) { // Tarefa específica
			$df = $_POST['df'] ?? null; // Prazo final
			
			if (!$df) {
				echo json_encode(["error" => "A data de vencimento não foi informada."]);
				exit;
			}					
			
			if (!empty($df)) {
				$df = str_replace("T", " ", $df) . ":00"; // Garante que o formato esteja correto
			} else {
				$df = "NULL"; // Define como NULL se não houver data
			}
			
			$step = [];

			foreach ($_POST as $key => $value) {
				// Captura apenas campos que começam com "step_" e não incluem "step_dt_"
				if (strpos($key, 'step_') === 0 && strpos($key, 'step_dt_') === false) {
					$index = str_replace('step_', '', $key);

					// Garantir que título é um texto válido
					$titulo = trim($value, "'\"");
					$prazo = $_POST["step_dt_" . $index] ?? null;

					// Evitar salvar datas como título indevidamente
					if (!empty($prazo) && strtotime($titulo) !== false) {
						$titulo = "Subtarefa sem nome"; // Nome genérico para evitar erro
					}

					$step[] = [
						"titulo" => $titulo,
						"prazo" => $prazo,
						"status" => $_POST["step_status_" . $index] ?? ""
					];
				}
			}

			// Certificar que o JSON está correto
			$dados['step'] = json_encode($step, JSON_UNESCAPED_UNICODE);

			$dados += [
				'tp' => 0, //Tipo 0 - Tarefa				
				'step' => json_encode($step, JSON_UNESCAPED_UNICODE) // Subtarefas salvas em JSON
			];
		}elseif ($tipo == 3) { //Rotina
			$pa = $_POST['pa'] ?? ""; // Impacto (0 - Positivo / 1 - Negativo)			
			
			$dados += [
				'tp' => 1, //Tipo 1 - Rotina
				'pa' => $pa				
			];
		}
		
	}

	if ($tipo == 2) {
		$cl = $_POST['cl'] ?? ""; //Cor da pasta
		
		$dados['cl'] = $cl;
	}

	// Definir a tabela correta e inserir os dados
	$tabela = ($tipo == 2) ? 'wa0001_tgo' : 'wa0001_wtk';
	$dados += ($tipo == 2) ? ['dt' => $now] : ['wg' => $now];			
	
	//INSERT
	if($_GET['action'] == 'insert'){
		include_once($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');
		if ($id = insert('app', $tabela, implode(', ', array_keys($dados)), "'".implode("', '", array_values($dados))."'") ){
			//Registra a data de vencimento no app Agenda
			if ($tipo == 1) {
				if($tsk_dt = insert('app', 'wa0008_events', 'us,ap,el,dt', "'".$_SESSION['wz']."','1','".$id."','".$df."'")){
					echo json_encode(["success" => "Registro salvo com sucesso!", "id" => $id]);
					?>
					<script>
					(function(){
						'use strict';
						goTo('core/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
						toggleSidebar();
					})();
					</script>
					<?php
				}else{
					echo json_encode(["error" => "Erro ao gravar a data de vencimento no banco de dados.", "id" => $id]);
				}
			}else{
				echo json_encode(["success" => "Registro salvo com sucesso!", "id" => $id]);
				?>
				<script>
				(function(){
					'use strict';
					goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', '');
					toggleSidebar();
				})();
				</script>
				<?php
			}			
		} else {
			echo json_encode(["error" => "Erro ao salvar no banco de dados."]);
		}
	
	//UPDATE
	}elseif($_GET['action'] == 'update'){
		include_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');

		$id = $_POST['id'] ?? null; // Obtém o ID do registro a ser atualizado

		if (!$id) {
			echo json_encode(["error" => "ID do registro não informado."]);
			exit;
		}

		// Criar string de atualização dinamicamente
		$updateValues = [];
		foreach ($dados as $key => $value) {
			$updateValues[] = "$key = " . ($value === null ? "NULL" : "'$value'");
		}
		
		$updateString = implode(",", $updateValues);
		$whereClause = "id = '$id'"; // Condição de atualização

		echo $updateString.' - '.$whereClause;

		// Executar o update no banco de dados
		if ($updatedRows = update('app', $tabela, $updateString, $whereClause)) {
			//Registra a data de vencimento no app Agenda
			if ($tipo == 1) {
				include_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
				
				if($previousDate = search('app', 'wa0008_events', '', "us = '{$_SESSION['wz']}' AND ap = '1' AND el = '{$id}'")){
					if($df !== $previousDate[0]['dt']){
						if ($tsk_dt = update('app', 'wa0008_events', "dt = '{$df}'", "us = '{$_SESSION['wz']}' AND ap = '1' AND el = '{$id}'")) {
							echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
							?>
							<script>
							(function(){
								'use strict';
								goTo('core/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
								toggleSidebar()
							})();
							</script>
							<?php
						} else {
							echo json_encode(["error" => "Erro ao gravar a data de vencimento no banco de dados.", "df" => $df, "id" => $id]);
						}
					}else{
						echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
						?>
						<script>
						(function(){
							'use strict';
							goTo('core/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
							toggleSidebar()
						})();
						</script>
						<?php
					}
				}
				
			}else{
				echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
				?>
				<script>
				(function(){
					'use strict';	
					goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', '');					
					toggleSidebar();					
				})();
				</script>
				<?php
			}
		} else {
			echo json_encode(["error" => "Erro ao atualizar o registro no banco de dados.", "id" => $id]);
		}
	}
}

/*
if(isset($_POST['vr'])){
	$vr = json_decode($_POST['vr'],true);
	$ds = '';
	if(array_key_exists('tskid', $vr)){
		//TAREFA JÁ EXISTE - ALTERA
		$tsk_id = $vr['tskid'];
	}else{
		if(array_key_exists('tsktt',$vr)){
			//NOVA TAREFA			
			if (isset($vr['ds'])) $ds = $vr['ds'];
			if (!isset($vr['cm'])) $vr['cm'] = '';
			
			
			$step = array();
			$date = array(); // Adicionamos um array para armazenar as datas
			$n = 0;
			foreach($vr as $key => $value){
				if(preg_match('/tsksp_(\d+)/i', $key, $matches)){ // Modificamos a expressão regular para capturar o número do passo
					$step[$matches[1]] = '\''.$value.'\'';
				}elseif(preg_match('/tsksp_dt_(\d+)/i', $key, $matches)){ // Adicionamos uma verificação para as datas
					$date[$matches[1]] = $value; // Associamos a data ao número do passo correspondente
				}					
				$n++;		
			}
			$result = array();
			foreach($step as $index => $value){			
				$stat_value = 0;
				if(isset($date[$index])){
					$date_value = $date[$index];
				}else{
					$date_value = '';
				}
				$result[] = $value . '|' . $date_value . '=' . $stat_value;
			}			
			
			$step = str_replace("'","\'", implode(';', $result));
			$desc = str_replace("'","\'", $ds);									
			$tsk_id = insert('app', 'wa0001_wtk', 'wz,cm,tg,us,wg,tt,ds,step,pr', "'".$_SESSION['wz']."','".$vr['cm']."','".$vr['tg']."','".$_SESSION['wz']."','".$now."','".$vr['tsktt']."','".$desc."','".$step."','".$vr['pr']."'");			
			if($tsk_id > 0){
				$tsk_dt = insert('app', 'wa0008_events', 'us,ap,el,dt', "'".$_SESSION['wz']."','1','".$tsk_id."','".$vr["df"]."'");
			}			
		}elseif(array_key_exists('tgttt',$vr)){
			//NOVA PASTA
			if(isset($vr['ds'])){
				$ds = $vr['ds'];
			}
			$_GET['vr'] = insert('tsk', 'tgo', 'us,dt,tt,ds', "'".$_SESSION['wz']."','".$now."','".$vr['tgttt']."','".$ds."'");			
		}
	}
}
*/
?>