<?
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

// Permite acesso de qualquer origem (pode restringir para origens específicas, se necessário)
header("Access-Control-Allow-Origin: *");

// Especifica os métodos HTTP permitidos na solicitação
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Especifica os cabeçalhos permitidos na solicitação
header("Access-Control-Allow-Headers: Content-Type");

// Verifica se é uma solicitação OPTIONS (pré-voo) e, se for, termina a execução para evitar que o código abaixo seja processado para esse tipo de solicitação
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

setlocale(LC_NUMERIC, 'pt_BR');

// Coloque a declaração 'use' fora dos blocos condicionais
require_once($_SERVER['DOCUMENT_ROOT'].'/tools/xlsviewer/vendor/autoload.php');	
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

$st = trim($_GET['index']);
?>
<div class="position-absolute display-block abs-t-20 abs-r-20">		
	<span onclick="tableToExcel('myTable', '<? echo $st; ?>')" class="fa-stack">
		<i class="fas fa-circle fa-stack-2x light-gray"></i>
		<i class="fas fa-file-excel fa-stack-1x dark"></i>
	</span>
	<span onclick='clipboardCopy("select")' class="fa-stack">
		<i class="fas fa-circle fa-stack-2x light-gray"></i>
		<i class="fas fa-link fa-stack-1x dark"></i>
	</span>
	<div class="clear"></div>
</div>	
<div class="large-12 medium-12 small-12 position-relative cm-mg-25-t">
	<div class="table-responsive background-gray w-rounded-10 cm-pad-20 overflow-x-auto display-block">
		<?
		if($st == "IPCAIBGE"){
			?>						
			<table id="myTable" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">
				<thead>
					<tr>
						<th class="cm-pad-15-b large-6 medium-6 small-6">Arquivo</th>
						<th class="cm-pad-15-b large-6 medium-6 small-6">Atualização</th>		
					</tr>
				</thead>
				<tbody>
				<?
				$dir = "../download/";		
				$files = scandir($dir); 
				foreach($files as $file){
					if(is_file($dir.$file)){
					?>
					<tr>				
						<td class="large-6 medium-6 small-6 cm-pad-5 text-ellipsis">
							<a title="Clique para baixar" href="https://workz.com.br/estatisticas/download/<? echo $file; ?>"><? echo $file; ?></a>
						</td>
						<td class="large-6 medium-6 small-6 cm-pad-5">
							<a><? echo date('d/m/Y', filemtime($dir.$file)); ?></a>
						</td>
					</tr>
					<?
					}
				}
				?>
				</tbody>
			</table>			
			<?	
		}elseif($st == "SERIE_IPCA"){
			?>
			<table id="myTable" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">
			<?
			if(file_exists('../download/ipca_'.date('Ym').'SerieHist.xls')){
				$arquivoXLS = '../download/ipca_'.date('Ym').'SerieHist.xls';
			}elseif(file_exists('../download/ipca_'.date('Ym', strtotime('-1 month', strtotime(date('Y-m-01')))).'SerieHist.xls')){
				$arquivoXLS = '../download/ipca_'.date('Ym', strtotime('-1 month', strtotime(date('Y-m-01')))).'SerieHist.xls';
			}elseif(file_exists('../download/ipca_'.date('Ym', strtotime('-2 month', strtotime(date('Y-m-01')))).'SerieHist.xls')){
				$arquivoXLS = '../download/ipca_'.date('Ym', strtotime('-2 month', strtotime(date('Y-m-01')))).'SerieHist.xls';
			}
			try {
				$reader = IOFactory::createReader('Xls');
				$spreadsheet = $reader->load($arquivoXLS);
				// Obtemos todas as abas (sheets) do arquivo
				$abas = $spreadsheet->getAllSheets();
				// Loop para exibir o conteúdo de cada aba
				foreach ($abas as $aba) {
					//echo '<h2>' . $aba->getTitle() . '</h2>';					
					foreach ($aba->getRowIterator() as $linha) {
						echo '<tr>';
						foreach ($linha->getCellIterator() as $celula) {
							echo '<td>' . $celula->getValue() . '</td>';
						}
						echo '</tr>';
					}					
				}
			}catch(Exception $e){
				echo 'Erro ao ler o arquivo: ', $e->getMessage();
			}	
			?>
			</table>
			<?
		}elseif($st == "NTNB"){			
			$arquivoXLS = '../ntnb/NTN-B_'.date('Y').'.xls';
			try {
				$reader = IOFactory::createReader('Xls');
				$spreadsheet = $reader->load($arquivoXLS);
				// Obtemos todas as abas (sheets) do arquivo
				$abas = $spreadsheet->getAllSheets();
				// Loop para exibir o conteúdo de cada aba
				foreach ($abas as $aba) {
					$sheet = str_replace(" ", '', $aba->getTitle());
					
					if((!empty($_GET['sheet']) && $_GET['sheet'] == $sheet) || empty($_GET['sheet'])){
						
						?>
						<div class="large-12 medium-12 small-12 display-center-general-container cm-pad-20 cm-pad-0-h">
							<h3 style="width: calc(100% - 72px);">
							<? echo $aba->getTitle();?>
							</h3>
							<div class="">
								<span onclick="tableToExcel('<? echo str_replace(" ", '', $aba->getTitle()); ?>', '<? echo $st; ?>')" onclick='clipboardCopy("select")' class="fa-stack float-right">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-file-excel fa-stack-1x dark"></i>
								</span>
								<span onclick="copyToClipboard('<? echo 'https://app.workz.com.br/core/backengine/wa0013/estatisticas/async/statistics.php?index=NTNB&sheet='.$sheet; ?>')" class="fa-stack float-right">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-link fa-stack-1x dark"></i>
								</span>
							</div>
							<div class="clear"></div>
						</div>
						<table id="<? echo $sheet; ?>" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">
						<?					
						foreach ($aba->getRowIterator() as $linha) {
							echo '<tr>';						
							foreach ($linha->getCellIterator() as $celula) {
								echo '<td>' . $celula->getValue() . '</td>';
							}
							echo '</tr>';						
						}
						?>
						</table>
						<?
					}
					
					
				}
			}catch(Exception $e){
				echo 'Erro ao ler o arquivo: ', $e->getMessage();
			}						
		}elseif($st == "Feriados"){
			$consult = search('app', 'wa0013_'.$st, '', '');
			if(count($consult) == 0){
					echo "Não há resultados para exibir.";
			}else{
			?>
			<table id="myTable" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">
				<thead>
					<tr>									
						<th class="cm-pad-15-b large-3 medium-3 small-3">Data</th>
						<th class="cm-pad-15-b large-3 medium-3 small-3">Dias mês</th>
						<th class="cm-pad-15-b large-3 medium-3 small-3">1ª quinz.</th>
						<th class="cm-pad-15-b large-3 medium-3 small-3">2ª quinz.</th>
					</tr>
				</thead>
				<tbody>
					<?
					foreach($consult as $result){
					?>
					<tr>				
						<td class="large-3 medium-3 small-3 cm-pad-5 text-ellipsis"><? echo date("m/Y", strtotime($result['cmp'])); ?></td>				
						<td class="large-3 medium-3 small-3 cm-pad-5 text-ellipsis"><? echo $result['dtm']; ?></td>
						<td class="large-3 medium-3 small-3 cm-pad-5 text-ellipsis"><? echo $result['d1q']; ?></td>
						<td class="large-3 medium-3 small-3 cm-pad-5 text-ellipsis"><? echo $result['d2q']; ?></td>				
					</tr>
						<?
					}
					?>					
				</tbody>
			</table>
			<?
			}
		}elseif($st == "FerDiario"){
			$consult = search('app', 'wa0013_'.$st, '', '');
			if(count($consult) == 0){
					echo "Não há resultados para exibir.";
			}else{
			?>
			<table id="myTable" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">
				<thead>
					<tr>									
						<th class="cm-pad-15-b large-4 medium-4 small-4">Data</th>
						<th class="cm-pad-15-b large-4 medium-4 small-4">Dia da semana</th>
						<th class="cm-pad-15-b large-4 medium-4 small-4">Descrição</th>					
					</tr>
				</thead>
				<tbody>
					<?
					foreach($consult as $result){
					?>
					<tr>
						<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo date("d/m/Y", strtotime($result['dt'])); ?></td>
						<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo $result['wk']; ?></td>
						<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo $result['ds']; ?></td>
					</tr>
					<?
					}
					?>					
				</tbody>
			</table>
			<?
			}
		}else{
			$consult = search('app', 'wa0013_'.$st, '', '');
			if(count($consult) == 0){
					echo "Não há resultados para exibir.";
			}else{		
			?>	
			<table id="myTable" class="table large-12 medium-12 small-12 fs-c overflow-x-auto text-center">			
				<thead>
					<tr>					
						<?
						if($st == "CDI"){
						?>
						<th class="cm-pad-15-b large-4 medium-4 small-4">Data</th>
						<th class="cm-pad-15-b large-4 medium-4 small-4">Taxa (% a.a.)</th>
						<th class="cm-pad-15-b large-4 medium-4 small-4">Fator diário</th>
						<?					
						}else{
						?>
						<th class="cm-pad-15-b large-6 medium-6 small-6">Data</th>
						<th class="cm-pad-15-b large-6 medium-6 small-6">Fator</th>
						<?						
						}
						?>						
					</tr>
				</thead>
				<tbody>
					<?
					foreach($consult as $result){
					?>
					<tr>	
					<?									
					if($st == "CDI"){
					?>
					<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo date("d/m/Y", strtotime($result['Data'])); ?></td>
					<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo number_format($result['Media'], 2, ',', ' '); ?></td>
					<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis" title="Fator DI diário: (1 + {[1 + ('<? echo $result['Media']; ?>'/100)]^(1/252) - 1}) - Sem arredondamento"><? echo number_format((1 + (pow((($result['Media'] / 100) + 1), (1 / 252)) - 1)), 14, ',', ' '); ?></td>
					<?						
					}else{
					?>
					<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo $result['Data']; ?></td>
					<td class="large-4 medium-4 small-4 cm-pad-5 text-ellipsis"><? echo $result['Valor']; ?></td>
					<?
					}					
					?>
					</tr>
						<?
					}
					?>					
				</tbody>
			</table>			
			<?
			}
		}
		?>		
	</div>
	<div class="cm-mg-10-b white">
			<?
			/*
			if($st == "CDI"){
				echo '<p>Fonte: CETIP</p>';
			}elseif($st == "IPCA_ANBIMA_2Q"){
				echo '<p>Fonte: ANBIMA</p>';
			}else{
				echo '<p>Fonte: BNDES</p>';
			}
			
			<div class="cm-mg-10-b white">
				<p>Fonte: IBGE</p>
			</div>
			<div class="cm-mg-10-b white">
				<p>Fonte: IBGE</p>
			</div>
			*/
			?>						
	</div>
</div>
<input type="text" class="seed position-absolute abs-0-t" value="https://workz.com.br/app/core/backengine/wa0013/estatisticas/async/statistics.php?index=<? echo $st; ?>" id="select" />