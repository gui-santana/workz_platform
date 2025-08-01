<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
require_once('../../common/getUserAccessibleEntities.php');
session_start();

date_default_timezone_set('America/Sao_Paulo');

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];
$or = '';
foreach($teams as $team){
    $or .= " OR cm = '".$team."'";
}

if(isset($_GET['vr']) && ($tskb = search('app', 'wa0001_wtk', 'tt', "(us = '{$_SESSION['wz']}' OR JSON_CONTAINS(uscm, '{$_SESSION['wz']}', '$')".$or.") AND id = '{$_GET['vr']}'"))){
    $tskr = $tskb[0];   
	?>
	<div class="large-12 medium-12 small-12 display-center-general-container cm-mg-15-t">				
		<div class="float-left fs-g height-100 font-weight-500 text-ellipsis large-9 medium-8 small-5 orange">Registros - <a class="gray"><?= $tskr['tt'] ?></a></div>
		<!-- MENU SUPERIOR DIREITO -->
		<div class="float-left large-3 mediun-4 small-7 text-right">				
			<span onclick="exportToPDF()" class="open-sidebar fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Exportar para PDF">
				<i class="fas fa-square fa-stack-2x"></i>
				<i class="fas fa-file-pdf fa-stack-1x fa-inverse"></i>
			</span>			
			<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/m_task.php', 'main-content', 1, '<?= $_GET['vr'] ?>');" class="fa-stack w-color-bl-to-or pointer" title="Voltar">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
			</span>		
		</div>
	</div>
	<div class="cm-mg-15-b cm-mg-30-t cm-mg-0-h large-12 medium-12 small-12 w-rounded-15 z-index-2 w-shadow-1 background-white-transparent-75 backdrop-blur">
		<div id="logContent" class="large-12 medium-12 small-12 fs-c position-relative overflow-x-auto">
		<?php
		if($logs = search('app', 'wa0001_logs', '', "task_id = {$_GET['vr']}")){

			require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/isBase64.php');
			require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/sanitizeFIleName.php');

			$statuses = [
				0 => 'Pendente',
				1 => 'Em pausa',
				2 => 'Em andamento',
				3 => 'Finalizada',
				5 => 'Finalizada',
				6 => 'Arquivada'
			];
			
			$statusColors = [
				0 => 'red',
				1 => 'orange',
				2 => 'blue',
				3 => 'green',
				5 => 'green',
				6 => 'gray'
			];
			
			$camposBD = [
				'tt' => 'Título',
				'uscm' => 'Atribuição',
				'tg' => 'Pasta',
				'cm' => 'Equipe',
				'lv' => 'Dificuldade',
				'st' => 'Status',
				'ds' => 'Observações',
				'time' => 'Tempo',
				'hb' => 'Competências',
				'step' => 'Subtarefas'			
			];

			function formatStatus($status, $statuses, $statusColors) {
				$icons = [
					0 => '🔴',
					1 => '🟠',
					2 => '🟦',
					3 => '✅',
					5 => '✅',
					6 => '📁'
				];

				return isset($statuses[$status])
					? "<span style='color: {$statusColors[$status]}; font-weight: bold;'>{$icons[$status]} {$statuses[$status]}</span>"
					: "<span style='color: gray;'>Desconhecido</span>";
			}

			function formatSubtaskStatus($jsonData) {
				$data = json_decode($jsonData, true);
				if (!$data) return $jsonData;

				$output = "<div style='max-height: 150px; overflow-y: auto; padding: 5px; background: #f0f0f0; border: 1px solid #ddd;'>";
				foreach ($data as $task) {
					$statusIcon = $task['status'] == '1' ? '✅' : '❌';
					$output .= "<div>{$statusIcon} <b>{$task['titulo']}</b> - Prazo: " . ($task['prazo'] ?? 'Sem prazo') . "</div>";
				}
				$output .= "</div>";

				return $output;
			}

			function formatDetails($jsonValue){
				
				// Remover espaços, caracteres invisíveis e ajustar a codificação
				$cleanedValue = mb_convert_encoding(trim($jsonValue), 'UTF-8', 'UTF-8');
				// Corrigir aspas escapadas ou JSON em string
				$firstDecode = json_decode($cleanedValue, true);
				$decodedDetails = is_string($firstDecode) ? json_decode($firstDecode, true) : $firstDecode;

				if (is_array($decodedDetails)) {                
					$output = '';
					foreach ($decodedDetails as $key => $value) {
						$output .= "<p><b>" . ucfirst($key) . ":</b> " . htmlspecialchars($value) . "</p>";
					}                
					return $output;
				}

				return htmlspecialchars($details);
			}		

			function decodeBase64($string) {
				$decoded = base64_decode($string, true);
				$jsonDecoded = json_decode($decoded, true);

				return $jsonDecoded ? formatDetails(json_encode($jsonDecoded)) : ($decoded ?: $string);
			}
			
			// Função para limpar caracteres indesejados e formatação incorreta
			function cleanValue($value) {
				return trim(stripslashes(str_replace(["\n", "\r"], '', $value)));
			}

			// Função para comparar datas e ignorar pequenas diferenças (ex.: segundos)
			function compareDates($date1, $date2) {
				$time1 = strtotime($date1);
				$time2 = strtotime($date2);

				// Se a diferença for menor que 5 minutos, considerar como não alterado
				return abs($time1 - $time2) > 300; // 300 segundos = 5 minutos
			}
			
			function formatSubTask($jsonValue) {
				// Remover espaços, caracteres invisíveis e ajustar a codificação
				$cleanedValue = cleanJsonString($jsonValue);

				// Corrigir aspas escapadas ou JSON em string
				$firstDecode = json_decode($cleanedValue, true);
				$decodedValue = is_string($firstDecode) ? json_decode($firstDecode, true) : $firstDecode;

				// Verificar se a decodificação foi bem-sucedida e se é um array
				if (json_last_error() === JSON_ERROR_NONE && is_array($decodedValue)) {								
					$formattedValue = '<table class="table large-12 medium-12 small-12">';
					foreach ($decodedValue as $value) {
						$titulo = $value['titulo'] ?? '-';
						$prazo = !empty($value['prazo']) ? date('d/m/Y H:i', strtotime($value['prazo'])) : 'Sem prazo';
						$status = !empty($value['status']) ? 'Concluído' : 'Em aberto';

						$formattedValue .= '<tr>
												<td class="text-ellipsis">' . htmlspecialchars($titulo) . '</td>
												<td class="text-ellipsis">' . htmlspecialchars($prazo) . '</td>
												<td class="text-ellipsis">' . htmlspecialchars($status) . '</td>
											</tr>';
					}
					$formattedValue .= '</table>';
					return $formattedValue;
				} else {
					return "Erro ao decodificar JSON: " . json_last_error_msg();
				}
			}
			
			function cleanJsonString($value) {
                $value = trim(stripslashes($value));
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }

			
			?>			
			<div class="clearfix cm-pad-10-h cm-pad-10 cm-pad-15-t large-12 medium-12 small-12 position-relative text-ellipsis overflow font-weight-600">
				<div class="float-left large-1 medium-1 small-1 text-ellipsis">Data</div>
				<div class="float-left large-1 medium-1 small-1 text-ellipsis">Tipo</div>
				<div class="float-left large-5 medium-5 small-5 text-ellipsis">Valor Anterior</div>
				<div class="float-left large-5 medium-5 small-5 text-ellipsis">Valor Atual</div>					
			</div>			
			<?php
			foreach ($logs as $log) {
				
				$previousValue = $log['previous_value'];
				$newValue = $log['new_value'];			
				
				switch ($log['field_changed']) {
					
					//INICIAR
					case 'Status':

						$previousValue = $log['field_changed'] === 'Status'
							? formatStatus($log['previous_value'], $statuses, $statusColors)
							: ($log['field_changed'] === 'subtask_status'
								? formatSubtaskStatus($log['previous_value'])
								: $log['previous_value']);

						$newValue = $log['field_changed'] === 'Status'
							? formatStatus($log['new_value'], $statuses, $statusColors)
							: ($log['field_changed'] === 'subtask_status'
								? formatSubtaskStatus($log['new_value'])
								: $log['new_value']);	
						
					break;
					
					case 'Exclusão':
					
					break;
					
					case 'Cópia':
					
					break;
					
					case 'Subtarefa':
					
						$previousValue = formatSubTask($log['previous_value']);
						$newValue = formatSubTask($log['new_value']);
							
					break;
					
					case 'Linha do tempo':
						
					break;
					
					case 'Edição':

						// Converter as strings para arrays										
						$arrayAntigo = json_decode($previousValue, true);
						$arrayNovo = json_decode($newValue, true);											
						
						// Comparar os arrays e exibir apenas as diferenças
						$alteracoes = [];
						
						foreach ($arrayNovo as $chave => $valorNovo) {
							$valorAntigo = $arrayAntigo[$chave] ?? null;
							
							 // Tratamento especial para datas
							if ($chave === 'wg' && !compareDates($valorAntigo, $valorNovo)) {
								continue; // Ignorar se for diferença mínima na data
							}							
							if ($valorNovo !== $valorAntigo) {
								$alteracoes[$chave] = [
									'anterior' => $valorAntigo,
									'atual' => $valorNovo
								];
							}
						}
						
						// Exibição das alterações
						if (!empty($alteracoes)) {
							$previousValue = '';
							foreach ($alteracoes as $campo => $valores) {
								
								if (!isset($camposBD[$campo])) {
                                    continue; // ignora campos desconhecidos
                                }
                                
                                $previousValue .= "<p class='font-weight-600'>{$camposBD[$campo]}:</p>";

								if($campo == 'step'){									
									$previousValue .= formatSubTask(str_replace("'", '"', $valores['anterior']));									
								}elseif($campo == 'cm'){
									$searchResult = search('cmp', 'teams', 'tt', "id = {$valores['anterior']}");
									$previousValue .= ((int) trim($valores['atual'], "'") > 0 && isset($searchResult[0]['tt'])) ? $searchResult[0]['tt'] : 'Nenhuma equipe';
								}elseif($campo == 'tg'){
									$searchResult = search('app', 'wa0001_tgo', 'tt', "id = {$valores['anterior']}");
									$previousValue .= ((int) trim($valores['atual'], "'") > 0 && isset($searchResult[0]['tt'])) ? $searchResult[0]['tt'] : 'Nenhuma pasta';
								}elseif($campo == 'uscm'){
									$users = json_decode($valores['anterior'], true);
									if(count($users) > 0){
										foreach($users as $user){
											$searchResult = search('hnw', 'hus', 'tt', "id = {$valores['anterior']}");
											$previousValue .= "<p>'{$searchResult[0]['tt']}'</p>";
										}
									}else{
										$previousValue .= 'Todos';
									}									
								}else{
									$previousValue .= "<p>{$valores['anterior']}</p>";
								}
								$previousValue .= "<hr>";
							}							
							$newValue = '';
							foreach ($alteracoes as $campo => $valores) {
							    
							    if (!isset($camposBD[$campo])) {
                                    continue; // ignora campos desconhecidos
                                }
							    
								$newValue .= "<p class='font-weight-600'>{$camposBD[$campo]}:</p>";
								
								if($campo == 'step'){									
									$newValue .= formatSubTask(str_replace("'", '"', $valores['atual']));									
								}elseif($campo == 'cm'){
									$searchResult = search('cmp', 'teams', 'tt', "id = {$valores['atual']}");
									$newValue .= ((int) trim($valores['atual'], "'") > 0 && isset($searchResult[0]['tt'])) ? $searchResult[0]['tt'] : 'Nenhuma equipe';
								}elseif($campo == 'tg'){									
									$searchResult = search('app', 'wa0001_tgo', 'tt', "id = {$valores['atual']}");
									$newValue .= ((int) trim($valores['atual'], "'") > 0 && isset($searchResult[0]['tt'])) ? $searchResult[0]['tt'] : 'Nenhuma pasta';
								}else{
									$newValue .= "<p>{$valores['atual']}</p>";
								}		
								$newValue .= "<hr>";
							}														
						}
					break;
				}
				$date = new DateTime($log['change_date'], new DateTimeZone('GMT'));
				$date->setTimezone(new DateTimeZone('America/Sao_Paulo'));
				?>                
				<div class="clearfix large-12 medium-12 small-12 cm-pad-5 border-t-input display-center-general-container">
					<div class="float-left large-1 medium-1 small-1 cm-pad-5"><?= $date->format('d/m/Y, \à\s H:i') ?></div>
					<div class="float-left large-1 medium-1 small-1 cm-pad-5"><?= htmlspecialchars($log['field_changed']) ?></div>
					<div class="float-left large-5 medium-5 small-5 cm-pad-5"><?= isBase64($previousValue) ? base64_decode($previousValue) : $previousValue ?></div>
					<div class="float-left large-5 medium-5 small-5 cm-pad-5"><?= isBase64($newValue) ? base64_decode($newValue) : $newValue ?></div>						
				</div>
			<?php 
			}
		}
		?>
		</div>
	</div>
	<script>
	(function(){
		'use strict';
	
		function exportToPDF(){
			var element = document.getElementById('logContent');
			
			// Excluir botões e outros elementos desnecessários
			var clone = element.cloneNode(true);
			var buttons = clone.querySelectorAll('button, span');
			buttons.forEach(button => button.remove()); // Remove botões e ícones

			// Usar html2pdf para gerar o PDF
			html2pdf()
				.from(clone)
				.save('Log_<?= sanitizeFileName($tskr['tt']) ?>.pdf');
		}
		window.exportToPDF = exportToPDF;
	
	})();
	</script>	
	<?php
}
