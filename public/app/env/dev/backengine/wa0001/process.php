<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/update.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

date_default_timezone_set('America/Sao_Paulo');
$now = date('Y-m-d H:i:s');

// Função para verificar se o valor é um JSON válido
function isJson($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// Função para verificar se um dia é feriado
function isHoliday($date, $holidays)
{
    return in_array($date->format('Y-m-d'), $holidays);
}

// Função para adicionar dias úteis
function addWorkingDays($startDate, $days, $holidays)
{
    $currentDate = new DateTime($startDate);
    $addedDays = 0;

    while ($addedDays < $days) {
        $currentDate->modify('+1 day');
        if ($currentDate->format('N') < 6 && !isHoliday($currentDate, $holidays)) {
            $addedDays++;
        }
    }

    return $currentDate->format('Y-m-d H:i');
}

// Função para adicionar períodos maiores considerando dias úteis
function addPeriodConsideringWorkingDays($startDate, $interval, $holidays)
{
    $currentDate = new DateTime($startDate);
    $currentDate->modify($interval);

    while ($currentDate->format('N') >= 6 || isHoliday($currentDate, $holidays)) {
        $currentDate->modify('+1 day');
    }

    return $currentDate->format('Y-m-d H:i');
}

// Função para registrar log de alterações na tabela wa0001_logs
function logChange($taskId, $userId, $fieldChanged, $previousValue, $newValue, $details = '') {
    // Converte detalhes para JSON
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);

    // Protege contra SQL Injection (se não usar prepared statements na função insert)
    $taskId = addslashes($taskId);
    $userId = addslashes($userId);
    $fieldChanged = addslashes($fieldChanged);
    $previousValue = addslashes($previousValue);
    $newValue = addslashes($newValue);
    $detailsJson = addslashes($detailsJson);

    // Campos a serem inseridos
    $columns = 'task_id, user_id, change_date, field_changed, previous_value, new_value, details';
    $values = "'$taskId', '$userId', NOW(), '$fieldChanged', '$previousValue', '$newValue', '$detailsJson'";

    // Executa a inserção na tabela de logs
    insert('app', 'wa0001_logs', $columns, $values);
}

// Lógica principal
if ((isset($_POST['vr']) && $_POST['vr'] !== '') || (isset($_GET['vr']) && $_GET['vr'] !== '')) {

    $vr = isset($_POST['vr']) && isJson($_POST['vr']) ? json_decode($_POST['vr'], true) : explode('|', $_POST['vr'] ?? $_GET['vr']);
   
	if (count($vr) > 1) {
		
		if($user = search('hnw', 'hus', 'tt', "id = {$_SESSION['wz']}")[0]){
			$user = $user['tt'];
		}else{
			$user = '';
		}
		
        // AÇÕES ESPECÍFICAS DE STATUS DA TAREFA (INICIAR, PAUSAR, CONCLUIR) ->>> EXISTE A KEY [0] ($vr[0]) E O VALOR É NUMÉRICO
        if (isset($vr[0]) && is_numeric($vr[0])) {
            $taskId = $vr[0];
            switch ($vr[2]) {
				
				//INICIAR
                case 'play':
                    $previousStatus = search('app', 'wa0001_wtk', 'st', "id = '$taskId'")[0]['st'] ?? null;
                    update('app', 'wa0001_wtk', "init = '$now', st = '2'", "id = '$taskId'");
                    //LOG
					logChange($taskId, $_SESSION['wz'], 'status', $previousStatus, '2');
					?>
					<script>					
					document.getElementById('main-content').innerHTML = '';														
					goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', 1, <?= $taskId ?>);					
					</script>
					<?php
                    break;
				
				//PAUSAR
                case 'pause':
                    $task = search('app', 'wa0001_wtk', 'init,time', "id = '$taskId'")[0] ?? null;
                    if ($task) {
                        $time = $task['time'];
                        $init = $task['init'];
                        if ($init > 0) {
                            $init = new DateTime($init);
                            $nowTime = new DateTime();
                            $time += ($nowTime->getTimestamp() - $init->getTimestamp());
                            update('app', 'wa0001_wtk', "init = '0', st = '1', time = '$time'", "id = '$taskId'");
                            //LOG
							logChange($taskId, $_SESSION['wz'], 'status', '2', '1');
							?>
							<script>
							document.getElementById('main-content').innerHTML = '';																
							goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', 1, <?= $taskId ?>);							
							</script>
							<?php
                        }
                    }
                    break;

				//CONCLUIR
                case 'stop':
					$holidays = array_column(search('app', 'wa0013_FerDiario', 'dt', ''), 'dt');					
                    $task = search('app', 'wa0001_wtk', 'tg,time,init,pr,tml,step,st,uscm,lv,hb', "id = '$taskId'")[0] ?? null;
                    if($task){
						$st0 = $task['st'];
						$time = $task['time'];
                        $init = $task['init'];						
						
						if ($init > 0) {
                            $init = new DateTime($init);
                            $nowTime = new DateTime();
                            $time += ($nowTime->getTimestamp() - $init->getTimestamp());
                        }
						
						$xp = [
							0 => 5,
							1 => 10,
							2 => 25,
							3 => 50,
							4 => 100
						];
						
						//Obtemos a data do app Agenda
						$df = search('app', 'wa0008_events', 'dt', "el = '$taskId' AND ap = '1'")[0]['dt'];																			
						
						//RESETAMOS STATUS E PRAZOS DAS SUBTAREFAS							
						$steps = json_decode($task['step'], true);
						foreach ($steps as &$step) {
							$step['status'] = "";   // Resetar status para pendente
							$step['prazo'] = null;  // Resetar prazo
						}
						$step = json_encode($steps, JSON_UNESCAPED_UNICODE);
						
						//INCLUÍMOS UM COMENTÁRIO NA LINHA DO TEMPO
						$newEntry = [
							"timestamp" => date("Y-m-d H:i:s"), // Timestamp atual
							"descrição" => base64_encode('(***!WORKZ!***)<i class="far fa-check-circle"></i> A conclusão desta tarefa, com prazo em <strong>' . date('d/m/Y', strtotime($df)) . '</strong>, foi registrada com sucesso por <a href="https://workz.com.br/?profile='.$_SESSION['wz'].'" target="_blank"><strong>'. $user .'</strong>.</a>') // Descrição codificada em Base64
						];
						$timeline = json_decode($task['tml'], true);
						if (!is_array($timeline)) {
							$timeline = []; // Caso o JSON esteja vazio ou inválido
						}
						$timeline[] = $newEntry;
						$updatedTimelineJson = json_encode($timeline, JSON_UNESCAPED_UNICODE);
						
						if($task['pr'] > 0){							
							
							$intervalos = array(
								1 => '+1 day',
								2 => '+1 week',
								3 => '+1 month',
								4 => '+2 months',
								5 => '+3 months',
								6 => '+6 months',
								7 => '+1 year'
							);
							if(isset($intervalos[$task['pr']])){
								$interval = $intervalos[$task['pr']];
								if ($task['pr'] == 1) {
									// Adiciona 1 dia útil
									$df = addWorkingDays($df, 1, $holidays);
								}else{
									$df = addPeriodConsideringWorkingDays($df, $interval, $holidays);
								}
							}
							$st = 0;							
							//update('app', 'wa0008_events', "dt = '$df'", "el = '$taskId' AND ap = '1'");
						}else{
							$st = 3;
							//update('app', 'wa0001_wtk', "init = '$now', st = '$st', time = '$time'", "id = '$taskId'");
						}
						
						$pts = 0;
												
						if (update('app', 'wa0001_wtk', "init = '0', st = '$st', time = '0', tml = '$updatedTimelineJson', step = '$step'", "id = '$taskId'")) {
							echo json_encode(["success" => "Conclusão da atividade realizada com sucesso"]);

							if ($st == 0) {
								if (update('app', 'wa0008_events', "dt = '$df'", "el = '$taskId' AND ap = '1'")) {
									echo json_encode(["success" => "Reagendamento realizado com sucesso."]);                                    
								} else {
									echo json_encode(["error" => "Erro ao reagendar atividade."]);
									exit;
								}

								// **SALVANDO PONTOS DOS USUÁRIOS ENVOLVIDOS NA ATIVIDADE**
								$level = intval($task['lv']);  // Nível de dificuldade da tarefa
								$competencias = json_decode($task['hb'], true) ?: []; // Garante que sempre seja um array

								if (!is_array($competencias) || empty($competencias)) {
									echo json_encode(["error" => "Nenhuma competência atribuída à tarefa."]);
									exit;
								}

								$numCompetencias = count($competencias);

								// **Definir XP Baseado no Nível**
								$xpBase = $xp[$level] ?? 0; // Se não houver XP definido, assume 0
								if ($xpBase <= 0) {
									echo json_encode(["error" => "XP base inválido para o nível $level."]);
									exit;
								}

								// **Cálculo de XP com bônus por quantidade de competências**
								$xpTotal = $xpBase * (1 + (($numCompetencias - 1) / 10));                                    
								$xpPorCompetencia = $xpTotal / $numCompetencias; // XP distribuído por competência

								// **Definir os usuários que receberão os pontos**
								$users = json_decode($task['uscm'], true);
								if (!is_array($users) || empty($users)) {
									$users = [$_SESSION['wz']]; // Se não houver outros usuários, o criador recebe os pontos
								}

								$numUsers = count($users);
								$xpPorUsuario = $xpPorCompetencia / $numUsers; // XP por usuário e por competência

								// **Registra XP no banco de dados**
								foreach ($users as $user) {
									foreach ($competencias as $skill) {
										insert('app', 'wa0001_usxp', 'us,tk,sk,xp,dt', "'$user','$taskId','$skill','".number_format($xpPorUsuario, 2, '.', '')."','$now'");
									}
								}

								echo json_encode(["success" => "XP distribuído corretamente."]);
							}

						} else {
							echo json_encode(["error" => "Erro ao atualizar os dados da atividade"]);
							exit;
						}


						//LOG
						$details = json_encode([
							"tempo" => $time,
							"recente" => $init,
							"usuario" => $user,
							"pontos" => $pts
						], JSON_UNESCAPED_UNICODE);						
                        logChange($taskId, $_SESSION['wz'], 'status', $st0, $st, $details);
						?>
						<script>
						document.getElementById('main-content').innerHTML = '';								
						goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '');
						goTo('env/<?= $env ?>/backengine/wa0001/task_points.php', 'usxp', '0', '');						
						</script>
						<?php
                    }
                    break;
				
				//ARQUIVAR
                case 'eject':
                    $previousStatus = search('app', 'wa0001_wtk', 'st', "id = '$taskId'")[0]['st'] ?? null;
                    update('app', 'wa0001_wtk', "init = '0', st = '6'", "id = '$taskId'");
                    //LOG
					logChange($taskId, $_SESSION['wz'], 'status', $previousStatus, '6');
					?>
					<script>					
					document.getElementById('main-content').innerHTML = '';								
					goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '');					
					</script>
					<?php
                    break;

				/// DELETAR
				case 'delete':
					// Sanitiza e verifica se o ID é válido
					$taskId = intval($taskId);
					if ($taskId <= 0) {
						echo json_encode(["error" => "ID inválido"]);
						exit;
					}

					// Busca os dados antes da exclusão para log
					$previousData = search('app', 'wa0001_wtk', '*', "id = '$taskId'")[0] ?? null;					
					$folder = $previousData['tg'];
					
					if ($previousData) {
						// Exclui a tarefa
						$deletedTask = del('app', 'wa0001_wtk', "id = '$taskId'");

						// Exclui eventos associados à tarefa
						$deletedEvents = del('app', 'wa0008_events', "el = '$taskId' AND ap = '1'");

						// Registra no log
						logChange($taskId, $_SESSION['wz'], 'delete', json_encode($previousData), 'deleted');

						if ($deletedTask) {
							echo json_encode(["success" => "Tarefa excluída com sucesso"]);
							?>
							<script>
								document.getElementById('main-content').innerHTML = '';											
								goTo('env/<?= $env ?>/backengine/wa0001/folder_content.php', 'folder_content', <?= $folder ?>, '');
							</script>
							<?php
						} else {
							echo json_encode(["error" => "Erro ao excluir a tarefa do banco de dados"]);
						}
					} else {
						echo json_encode(["error" => "Tarefa não encontrada"]);
					}
					break;
					
				//CLONAR
                case 'clone':
                   
				   // Buscar dados da tarefa original
					$task = search('app', 'wa0001_wtk', '*', "id = '$taskId'")[0] ?? null;
					if (!$task) {
						echo json_encode(["error" => "Tarefa não encontrada"]);
						exit;
					}
					
					$df = search('app', 'wa0008_events', 'dt', "us = '{$_SESSION['wz']}' AND ap = '1' AND el = '{$taskId}'")[0]['dt'] ?? null;
					
					// Preparar os dados para a nova tarefa
					$newTaskData = [
						'us' => $_SESSION['wz'], // Criador da nova tarefa
						'tt' => $task['tt'] . " (Cópia)", // Adiciona "(Cópia)" ao título
						'ds' => $task['ds'], // Descrição original
						'tg' => $task['tg'], // Pasta
						'cm' => $task['cm'], // Equipe
						'lv' => $task['lv'], // Nível de dificuldade
						'hb' => $task['hb'], // Competências
						'pr' => $task['pr'], // Recorrência
						'uscm' => $task['uscm'], // Usuários atribuídos
						'prpe' => $task['prpe'], // Personalização de recorrência
						'tp' => $task['tp'], // Tipo (Tarefa, Rotina, etc.)
						'step' => $task['step'], // Subtarefas (mantidas)
						'tml' => json_encode([]), // Zera a linha do tempo
						'st' => '0', // Define como "Pendente"
						'wg' => date('Y-m-d H:i:s') // Data de criação atual						
					];																		
										
					// Inserir a nova tarefa no banco
					$columns = implode(', ', array_keys($newTaskData));
					$values = "'" . implode("', '", array_values($newTaskData)) . "'";
					
					$newTaskId = insert('app', 'wa0001_wtk', $columns, $values);
					
					if ($newTaskId) {
						// Log da duplicação
						logChange($newTaskId, $_SESSION['wz'], 'duplicação', $taskId, $newTaskId);

						// Verifica se é uma tarefa que deve ser registrada na agenda												
						if ($df) {
							// Query de inserção
							$query = "INSERT INTO wa0008_events (us, ap, el, dt) VALUES ('{$_SESSION['wz']}', '1', '{$newTaskId}', '{$df}')";
							var_dump($query); // Verificar se a query está correta
							
							if (insert('app', 'wa0008_events', 'us, ap, el, dt', "'{$_SESSION['wz']}', '1', '{$newTaskId}', '{$df}'")) {
								echo json_encode(["success" => "Tarefa duplicada com sucesso!", "id" => $newTaskId]);
								?>
								<script>
									goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', '1', '<?= $newTaskId ?>');
								</script>
								<?php
							} else {
								echo json_encode(["error" => "Erro ao gravar a data de vencimento no banco de dados.", "df" => $df, "id" => $newTaskId]);
							}
						} else {
							echo json_encode(["error" => "Não foi possível obter a data no banco de dados.", "df" => $df, "id" => $newTaskId]);
						}
						
					} else {
						echo json_encode(["error" => "Erro ao duplicar a tarefa"]);
						exit;
					}				
				   
                    break;	
            }
			
		//AÇÕES DE MANIPULAÇÃO DE REGISTROS (INCLUSÃO, EXCLUSÃO, COMENTÁRIOS, ETC) -> NÃO EXISTE A KEY [0]
        } else { 
			
			$action = $vr['action'];
			$taskId = $vr['task_id'] ?? null;

			// Validação básica
			if (!$taskId) {
				echo json_encode(["error" => "ID da tarefa inválido"]);
				exit;
			}

			switch ($action) {
				
				//ATUALIZAR SUBTAREFA
				case 'step':
					$stepIndex = $vr['step_index'] ?? null;
					$newStatus = $vr['new_status'] ?? "";

					if ($stepIndex === null) {
						echo json_encode(["error" => "Dados inválidos"]);
						break;
					}

					$task = search('app', 'wa0001_wtk', 'step', "id = '{$taskId}'")[0] ?? null;
					if (!$task) {
						echo json_encode(["error" => "Tarefa não encontrada"]);
						break;
					}

					// Atualiza o status do step específico
					$steps = json_decode($task['step'], true);
					if (isset($steps[$stepIndex])) {
						$steps[$stepIndex]['status'] = $newStatus;
					}
					
					$previousData = $task['step'];
					if (update('app', 'wa0001_wtk', "step = '" . json_encode($steps, JSON_UNESCAPED_UNICODE) . "'", "id = {$taskId}")) {
						echo json_encode(["success" => "Subtarefa atualizada com sucesso."]);
						//LOG
						logChange($taskId, $_SESSION['wz'], 'subtask_status', json_encode($previousData), 'changed');
						?>
						<script>
						goTo('env/<?= $env ?>/backengine/wa0001/task_progress.php', 'task_progress', '0', '<?= $taskId ?>');
						</script>
						<?php
					} else {
						echo json_encode(["error" => "Erro ao atualizar subtarefa"]);
					}
					break;

				//ADICIONAR COMENTÁRIO À LINHA DO TEMPO DA TAREFA
				case 'timeline':
					$comment = $vr['comment'] ?? null;
					$user = $_SESSION['wz'] ?? null;

					if (!$comment) {
						echo json_encode(["error" => "Comentário inválido"]);
						break;
					}

					$task = search('app', 'wa0001_wtk', 'tml', "id = '{$taskId}'")[0] ?? null;
					if (!$task) {
						echo json_encode(["error" => "Tarefa não encontrada"]);
						break;
					}

					// Adiciona nova entrada à linha do tempo
					$timeline = json_decode($task['tml'], true) ?: [];
					$timeline[] = [
						"timestamp" => date("Y-m-d H:i:s"),
						"descrição" => base64_encode($comment),
						"user" => $user
					];
					
					if (update('app', 'wa0001_wtk', "tml = '" . json_encode($timeline, JSON_UNESCAPED_UNICODE) . "'", "id = {$taskId}")) {
						echo json_encode(["success" => "Comentário adicionado com sucesso."]);
						?>
						<script>
							goTo('env/<?= $env ?>/backengine/wa0001/task_timeline.php', 'task_timeline', '0', '<?= $taskId ?>');
							document.getElementById('textBox').innerHTML = '';
						</script>
						<?php
					} else {
						echo json_encode(["error" => "Erro ao adicionar comentário"]);
					}
					break;
					
				//REMOVER COMENTÁRIO DA LINHA DO TEMPO DA TAREFA
				case 'timeline_delete':
									
					$timestamp = $vr['timestamp'] ?? null;
				
					if (!$taskId || !$timestamp) {
						echo json_encode(["error" => "Dados inválidos"]);
						break;
					}
					
					// Busca a timeline da tarefa
					$task = search('app', 'wa0001_wtk', 'tml', "id = '{$taskId}'")[0] ?? null;
					if (!$task) {
						echo json_encode(["error" => "Tarefa não encontrada"]);
						break;
					}
					
					// Remove a entrada da linha do tempo pelo timestamp
					$timeline = json_decode($task['tml'], true) ?: [];					
					$newTimeline = array_filter($timeline, function ($entry) use ($timestamp) {
						return $entry['timestamp'] !== $timestamp;
					});									
					
					if (update('app', 'wa0001_wtk', "tml = '" . json_encode(array_values($newTimeline), JSON_UNESCAPED_UNICODE) . "'", "id = {$taskId}")) {
						echo json_encode(["success" => "Comentário excluído com sucesso."]);
						?>
						<script>
							goTo('env/<?= $env ?>/backengine/wa0001/task_timeline.php', 'task_timeline', '0', '<?= $taskId ?>');
							document.getElementById('textBox').innerHTML = '';
						</script>
						<?php
					} else {
						echo json_encode(["error" => "Erro ao adicionar comentário"]);
					}
					break;
			}
		}

			 /*
            // Caso contrário, lidamos com as ações baseadas em variáveis de texto
            $action = $vr[0];
            $taskId = $vr[2] ?? null;	

            switch ($action) {
                case 'ckb': // Atualizar passo de uma tarefa
                    $taskId = $vr[4];
										
					//ETAPAS (PASSOS)
					$steps = explode(';', base64_decode($vr[1]));					
					$stepx = array();
					foreach($steps as $i => $step){
						if($i == $vr[2]){
							$ex = explode('=', $step);
							if($vr[3] === 'true'){
								if($i > 0){$stepx[] = ';'.$ex[0].'=1';}else{$stepx[] = $ex[0].'=1';};
							}elseif($vr[3] === 'false'){
								if($i > 0){$stepx[] = ';'.$ex[0].'=0';}else{$stepx[] = $ex[0].'=0';};					
							}						
						}else{
							if($i > 0){$stepx[] = ';'.$step;}else{$stepx[] = $step;};				
						}						
					}
					$str = str_replace("'","\'", join($stepx));
					
                    $previousValue = str_replace("'","\'", search('app', 'wa0001_wtk', 'step', "id = '$taskId'")[0]['step']) ?? '';									                    
                    if(update('app', 'wa0001_wtk', "step = '".$str."'", "id = '".$vr[4]."'")){
						logChange($taskId, $_SESSION['wz'], 'step', $previousValue, $str);
					}
                    break;					

                case 'tms': // Atualizar data no calendário
                    $previousValue = search('app', 'wa0008_events', 'dt', "el = '$taskId' AND ap = '1'")[0]['dt'] ?? '';
                    if(update('app', 'wa0008_events', "dt = '$vr[1]'", "el = '$taskId' AND ap = '1'")){
						logChange($taskId, $_SESSION['wz'], 'due_date', $previousValue, $vr[1]);
					}                    
                    break;

                case 'prf': // Atualizar recorrência
                    $previousValue = search('app', 'wa0001_wtk', 'pr', "id = '$taskId'")[0]['pr'] ?? '';
                    if(update('app', 'wa0001_wtk', "pr = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'recurrence', $previousValue, $vr[1]);
					}                    
                    break;

                case 'tt': // Atualizar título
                    $previousValue = search('app', 'wa0001_wtk', 'tt', "id = '$taskId'")[0]['tt'] ?? '';
                    if(update('app', 'wa0001_wtk', "tt = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'title', $previousValue, $vr[1]);
					}                    
                    break;

                case 'dsc': // Atualizar descrição
                    $previousValue = search('app', 'wa0001_wtk', 'ds', "id = '$taskId'")[0]['ds'] ?? '';
                    if(update('app', 'wa0001_wtk', "ds = '" . nl2br($vr[1]) . "'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'description', $previousValue, $vr[1]);
					}                    
                    break;

                case 'tml': // Atualizar linha do tempo
                    $tmlData = search('app', 'wa0001_wtk', 'tml', "id = '$taskId'")[0]['tml'] ?? '';
                    $tmlContent = str_replace('<img ', '<img onclick="openImage(this)" ', urldecode(base64_decode($vr[1])));
                    $newTimeline = $tmlData ? "$tmlData|$now;" . base64_encode($tmlContent) : "$now;" . base64_encode($tmlContent);
                    if(update('app', 'wa0001_wtk', "tml = '$newTimeline'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'timeline', $tmlData, $newTimeline);
					}                    
                    break;

                case 'tml_del': // Deletar da linha do tempo
                    $tmlData = search('app', 'wa0001_wtk', 'tml', "id = '$taskId'")[0]['tml'] ?? '';
                    $tmlList = explode('|', $tmlData);
                    $previousValue = $tmlList[$vr[1]] ?? '';
                    unset($tmlList[$vr[1]]);
                    $newValue = implode('|', $tmlList);
					if(update('app', 'wa0001_wtk', "tml = '$newValue'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'timeline', $previousValue, $newValue);
					}                                       
                    break;

                case 'tg': // Atualizar pasta de tarefas
                    $previousValue = search('app', 'wa0001_wtk', 'tg', "id = '$taskId'")[0]['tg'] ?? '';
                    if(update('app', 'wa0001_wtk', "tg = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'folder', $previousValue, $vr[1]);
					}                    
                    break;

                case 'cm': // Atualizar vínculo à comunidade
                    $previousValue = search('app', 'wa0001_wtk', 'cm', "id = '$taskId'")[0]['cm'] ?? '';
                    if(update('app', 'wa0001_wtk', "cm = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'community', $previousValue, $vr[1]);
					}                    
                    break;

                case 'us': // Atualizar membro da comunidade que executa a tarefa
                    $previousValue = search('app', 'wa0001_wtk', 'us', "id = '$taskId'")[0]['us'] ?? '';
                    if(update('app', 'wa0001_wtk', "us = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'assigned_user', $previousValue, $vr[1]);
					}                    
                    break;

                case 'st': // Atualizar status da etapa
                    $previousValue = search('app', 'wa0001_wtk', 'st', "id = '$taskId'")[0]['st'] ?? '';
                    if ($vr[1] == 0) {
                        $step = search('app', 'wa0001_wtk', 'step', "id = '$taskId'")[0]['step'] ?? '';
                        $reset = str_replace("=1", "=0", $step);
                        update('app', 'wa0001_wtk', "step = '$reset'", "id = '$taskId'");
                    }
					if(update('app', 'wa0001_wtk', "st = '$vr[1]'", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'status', $previousValue, $vr[1]);
					}                                       
                    break;
					
				case 'init': // Zera o valor de 'init', usado para calcular o valor total gasto na atividade
                    $previousValue = search('app', 'wa0001_wtk', 'time', "id = '$taskId'")[0]['time'] ?? '';
                    if(update('app', 'wa0001_wtk', "init = 0, time = 0", "id = '$taskId'")){
						logChange($taskId, $_SESSION['wz'], 'clear_all_time', $previousValue, '0');
					}                    
                    break;
            }*/
        
    }
}
?>
