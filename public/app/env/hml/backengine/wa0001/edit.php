<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

date_default_timezone_set('America/Sao_Paulo');
$now = date('Y-m-d H:i:s');

// Função para registrar log de alterações na tabela wa0001_logs
function logChange($taskId, $userId, $fieldChanged, $previousValue, $newValue, $details = '') {
    // Converte detalhes para JSON
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

    // Campos a serem inseridos
    $columns = 'task_id, user_id, field_changed, previous_value, new_value, details';
    $values = "'$taskId', '$userId', '$fieldChanged', '$previousValue', '$newValue', '$detailsJson'";

    // Executa a inserção na tabela de logs
    return insert('app', 'wa0001_logs', $columns, $values);
}

// Função para gerar valores seguros
function getSafeValue($value) {
    if (is_array($value)) {
        return "'" . addslashes(json_encode($value)) . "'";
    }
    return (is_null($value) || $value === '') ? "NULL" : "'" . addslashes($value) . "'";
}

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
		$hb = !empty($_POST['hb']) ? json_encode($_POST['hb'], JSON_UNESCAPED_UNICODE) : null; 
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
			'pr' => $pr,
			'uscm' => $uscm,
			'prpe' => $prpe
		];
		
		// ✅ Inclui 'hb' apenas se ele contiver um valor válido
		if (!is_null($hb)) {
			$dados['hb'] = $hb;
		}
		
		//Se for alteração dos dados
		if($_GET['action'] == 'update'){
			
			$time = $_POST['time'] ?? ""; //Tempo
			$st = $_POST['st'] ?? ""; //Status
			
			$dados += [				
				'time' => $time,
				'st' => $st
			];
		}
		
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
		if ($id = insert('app', $tabela, implode(', ', array_keys($dados)), "'".implode("', '", array_values($dados))."'") ){
			//Registra a data de vencimento no app Agenda
			if ($tipo == 1) {
				if($tsk_dt = insert('app', 'wa0008_events', 'us,ap,el,dt', "'".$_SESSION['wz']."','1','".$id."','".$df."'")){
					echo json_encode(["success" => "Registro salvo com sucesso!", "id" => $id]);
					?>
					<script>					
					goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
					toggleSidebar();
					</script>
					<?php
				}else{
					echo json_encode(["error" => "Erro ao gravar a data de vencimento no banco de dados.", "id" => $id]);
				}
			}else{
				echo json_encode(["success" => "Registro salvo com sucesso!", "id" => $id]);
				?>
				<script>				
				goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '<?= ($tipo == 2) ? 'folders' : '' ?>');
				toggleSidebar();				
				</script>
				<?php
			}			
		} else {
			echo json_encode(["error" => "Erro ao salvar no banco de dados."]);
		}
	
	//UPDATE
	}elseif($_GET['action'] == 'update'){
		
		include_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
		include_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');
		
		//LOG
		$motivo = $_POST['motivo'] ?? ""; //Tempo			
		$details = json_encode([
			"motivo" => $motivo			
		], JSON_UNESCAPED_UNICODE);						                        			

		$id = $_POST['id'] ?? null; // Obtém o ID do registro a ser atualizado

		if (!$id) {
			echo json_encode(["error" => "ID do registro não informado."]);
			exit;
		}

		// Criar string de atualização dinamicamente
		$newValues = [];
		foreach ($dados as $key => $value) {
			$updateValues[] = "$key = '" . $value . "'" ;
			$newValues[$key] = $value;
		}
		
		$newString = json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
		
		$updateString = implode(",", $updateValues);
		$whereClause = "id = '$id'"; // Condição de atualização

		// Registra no log antes de alterar os dados
		if ($result = search('app', $tabela, implode(',', array_keys($dados)), "id = '{$id}'")) {				
			if (!empty($result) && isset($result[0])) {
				$oldConsult = array_change_key_case(array_map('trim', $result[0]));

				$oldValues = [];
				foreach ($oldConsult as $key => $value) {
					$oldValues[$key] = $value;
				}		
				$oldString = json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
				
				if($newLog = logChange($id, $_SESSION['wz'], 'Edição', $oldString, $newString, $details)){
				    echo json_encode(["success" => "Novo log incluído.", "newLog" => $newLog]);
				}else{
				    echo json_encode(["error" => "Erro ao incluir um novo log."]);
				}
			}				
		}else{
		    echo json_encode(["error" => "Não foi possível encontrar a tarefa".$id."."]);
		}

		// Executar o update no banco de dados
		if ($updatedRows = update('app', $tabela, $updateString, $whereClause)) {									
										
			//Registra a data de vencimento no app Agenda
			if ($tipo == 1) {				
				
				if($previousDate = search('app', 'wa0008_events', '', "us = '{$_SESSION['wz']}' AND ap = '1' AND el = '{$id}'")){
					if($df !== $previousDate[0]['dt']){
						if ($tsk_dt = update('app', 'wa0008_events', "dt = '{$df}'", "us = '{$_SESSION['wz']}' AND ap = '1' AND el = '{$id}'")) {
							echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
							?>
							<script>
							goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
							toggleSidebar()
							</script>
							<?php
						} else {
							echo json_encode(["error" => "Erro ao gravar a data de vencimento no banco de dados.", "df" => $df, "id" => $id]);
						}
					}else{
						echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
						?>
						<script>							
						goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $id ?>');
						toggleSidebar()
						</script>
						<?php
					}
				}
				
			}else{
				echo json_encode(["success" => "Registro atualizado com sucesso!", "id" => $id]);
				?>
				<script>
				goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '<?= ($tipo == 2) ? 'folders' : '' ?>');					
				toggleSidebar();
				</script>
				<?php
			}
		} else {
			echo json_encode(["error" => "Erro ao atualizar o registro no banco de dados.", "id" => stripslashes($updateString)]);
		}
	}elseif($tipo == 7){
	    
	    
	    
	   
	    
	}
}
?>