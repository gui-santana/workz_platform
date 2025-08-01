<?php
if(!empty($_GET['vr']) && !empty($_GET['ini']) && !empty($_GET['fim'])){
	
	$dataInicio = DateTime::createFromFormat('Y-m-d', $_GET['ini']);
	$dataFim = DateTime::createFromFormat('Y-m-d', $_GET['fim']);
	
	//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
	$documentRoot = $_SERVER['DOCUMENT_ROOT'];
	$pattern = '/public_html\/([^\/]+)/';	
	preg_match($pattern, $documentRoot, $matches);	
	$subdomainFolder = isset($matches[1]) ? $matches[1] : '';	
	$sanitizedFolder = preg_replace('/[^a-zA-Z0-9-_]/', '', $subdomainFolder);
	$currentUrl = $_SERVER['HTTP_HOST'];
	$parts = explode('.', $currentUrl);
	$subdomain = $parts[0];						
	if ($sanitizedFolder === $subdomain){
		if(strpos($documentRoot, $sanitizedFolder.'/') > 0){
			$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder.'/', '', $documentRoot);					
		}else{
			$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder, '', $documentRoot);
		}			
	}
	include_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
	
	// Verifica se a data é um dia útil
	function ehDiaUtil($data, $feriados) {
		$diaSemana = $data->format('N'); // 1 para segunda-feira, 7 para domingo
		// Verifica se é sábado (6) ou domingo (7)
		if ($diaSemana == 6 || $diaSemana == 7) {
			return false;
		}
		// Verifica se é feriado
		foreach ($feriados as $feriado) {
			if ($data->format('dmY') == $feriado) {
				return false;
			}
		}
		return true;
	}
	
	$feriados = search('app', 'wa0013_FerDiario', 'dt', '');
	// Se $feriados é o seu array original
	$feriados = array_column($feriados, 'dt');
	
	if($dataInicio && $dataFim){
		
		$data = $dataInicio;

		$diasUteis = [];

		while($data <= $dataFim){
			if(ehDiaUtil($data, $feriados)){
				$diasUteis[] = clone $data;
			}
			$data->add(new DateInterval('P1D'));
		}
		
		// Agora $diasUteis contém todos os dias úteis entre dataInicio e dataFim
		?>
		<div class="row w-rounded-20 background-white w-shadow-1 cm-pad-20 cm-mg-20-t position-relative">			
		<?
		echo '<table class="large-12 medium-12 small-12 text-center" id="result" border="0">';
		echo '<tr><th>Código</th><th>Data</th><th>Taxa Indicativa</th><th>PU Indicativo</th><th>PU PAR</th><th>Duration</th></tr>';
		


        foreach($diasUteis as $data){

            // Status da consulta
			$found = false;					
						
			// Caminho para o arquivo txt
			$caminho_arquivo = 'https://www.anbima.com.br/informacoes/merc-sec-debentures/arqs/db'.$data->format('ymd').'.txt';
			$conteudo = @file_get_contents($caminho_arquivo);
					

			// Verifica se o conteúdo foi obtido
			if($conteudo !== false){
				
				
				echo '<tr>';
				
				
				// Lê o conteúdo do arquivo	
				$conteudo = file_get_contents($caminho_arquivo);

				// Converte a codificação para UTF-8, assumindo que a original é ISO-8859-1
				$conteudo = iconv('ISO-8859-1', 'UTF-8', $conteudo);

				// Explode o conteúdo em linhas
				$linhas = explode("\n", $conteudo);

				// Inicia um array vazio para armazenar os dados
				$dados = [];

				// Processa cada linha
				foreach ($linhas as $linha) {
					// Divide a linha em colunas usando o separador "@"
					$colunas = explode('@', $linha);

					// Ignora linhas vazias ou sem o número correto de colunas
					if (count($colunas) != 15) {
						continue;
					}

					// Cria um array associativo com os dados
					$item = [
						'Código' => $colunas[0],
						'Nome' => $colunas[1],
						'Repac./ Venc.' => $colunas[2],
						'Índice/ Correção' => $colunas[3],
						'Taxa de Compra' => $colunas[4],
						'Taxa de Venda' => $colunas[5],
						'Taxa Indicativa' => $colunas[6],
						'Desvio Padrão' => $colunas[7],
						'Intervalo Indicativo Mínimo' => $colunas[8],
						'Intervalo Indicativo Máximo' => $colunas[9],
						'PU' => $colunas[10],
						'% PU Par' => $colunas[11],
						'Duration' => $colunas[12],
						'% Reune' => $colunas[13],
						'Referência' => $colunas[14]
					];

					// Adiciona o item ao array de dados
					$dados[] = $item;
				}

				// Agora $dados contém os dados em formato de array multidimensional
				$alvo = strtoupper($_GET['vr']); // O código que estamos procurando

				foreach ($dados as $linha) {
					if ($linha['Código'] == $alvo) {
						$found = true;
						// Encontramos a linha com o código desejado
						//print_r($linha);
																	
						// Substitui as vírgulas por pontos nos valores
						$pu = str_replace(',', '.', $linha['PU']);
						$pu_par_percent = str_replace(',', '.', $linha['% PU Par']);
						
						if (is_numeric($pu) && is_numeric($pu_par_percent)) {													
							$pu_par = $pu / ($pu_par_percent / 100);							
							echo '<td>' . $linha['Código'] . '</td>';
							echo '<td>' . $data->format('d/m/Y') . '</td>';							
							echo '<td>' . number_format(floatval(str_replace(',', '.', $linha['Taxa Indicativa'])), 6, ',', '.') . '%</td>';
							echo '<td>' . number_format(floatval($pu), 6, ',', '.') . '</td>';
							echo '<td>' . number_format(floatval($pu_par), 6, ',', '.') . '</td>';
							echo '<td>' . round(str_replace(',', '.', $linha['Duration'])) . '</td>';
						} else {
							echo 'Valores de PU ou % PU Par não são numéricos.';
						}
						
						break; // Encerra o loop, pois já encontramos o que precisamos
					}
				}
				
				if($found == false){
					echo "Não foi possível encontrar um resultado com base no código IF informado.";
				}
				
				echo '</tr>';

			} else {
				// Se não foi possível obter o conteúdo, continua para a próxima iteração
				continue;
			}		
        }
		
		echo '</table>';
		?>
		</div>
		<?		
		
	}	
}else{
	echo "Algo deu errado.";
}



?>