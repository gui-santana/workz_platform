<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/sanitizeFIleName.php');
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

$now = date('Y-m-d H:i:s');
$tgo = search('app', 'wa0001_tgo', '', "id = '".$_GET['vr']."' AND st = '0'");

if($_GET['qt'] == 3){
    $wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['vr']}' AND us = '{$_SESSION['wz']}'");
    $tgo = 
    [
        [
        "id" => '0',
        "us" => $_SESSION['wz'],
        "cl" => '#000000',
        "dt" => $now,
        "tt" => $wpConn[0]['ur'],
        "ds" => 'Conexão via API com '.$wpConn[0]['ur'],
        "st" => 0
        ]
    ];
        
    //include('wp_consult_folder.php');
    
}

if(count($tgo) > 0){
	$tgor = $tgo[0];	
	?>		
	<div class="large-12 medium-12 small-12 display-center-general-container cm-mg-15-t">				
		<div class="float-left fs-g height-100 font-weight-500 large-9 medium-8 small-5 orange display-center-general-container"><?= (!empty($tgor['cl'])) ? '<i class="fas fa-folder fs-g cm-mg-10-r" style="color:' . $tgor['cl'] . '"></i> ' : '' ?><a class="text-ellipsis"><? echo $tgor['tt']; ?></a></div>
		<!-- MENU SUPERIOR DIREITO -->
		<div class="float-left large-3 mediun-4 small-7 text-right">						
			<?php
			if($tgor['us'] == $_SESSION['wz']){				
			?>			
			<span onclick="deleteFolder(<?= $tgor['id'] ?>)" class="fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Excluir permanentemente a pasta <?= $tgor['tt'] ?> e todas as suas tarefas">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-trash fa-stack-1x fa-inverse"></i>
			</span>
			<span onclick="editMode()" class="open-sidebar fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Editar pasta">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-pen fa-stack-1x fa-inverse"></i>
			</span>			
			<script>
			(function(){
				'use strict';
				
				function deleteFolder(tg){
					wAlert('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', tg, 'folders', 						
					'Você realmente deseja excluir a pasta <?= $tgor['tt'] ?> e todas as suas tarefas permanentemente?',						
					'A pasta <?= $tgor['tt'] ?> e todas as suas tarefas foram excluídas com sucesso.', 
					'A pasta <?= $tgor['tt'] ?> não pôde ser excluída.'
					);
				}
				window.deleteFolder = deleteFolder;
				
				function editMode(){
					toggleSidebar();							
					goTo('env/<?= $env ?>/backengine/wa0001/menu.php', 'config', 2, '&id=<?= $tgor['id'] ?>');
				}
				window.editMode = editMode;
				
			})();
			</script>
			<?php			
			}
			?>
			<span onclick="exportToPDF()" class="open-sidebar fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Exportar para PDF">
				<i class="fas fa-square fa-stack-2x"></i>
				<i class="fas fa-file-pdf fa-stack-1x fa-inverse"></i>
			</span>
			<span onclick="exportToExcel()" class="open-sidebar fa-stack w-color-or-to-bl pointer" style="vertical-align: middle;" title="Exportar para Excel">
				<i class="fas fa-square fa-stack-2x"></i>
				<i class="fas fa-file-excel fa-stack-1x fa-inverse"></i>
			</span>
			<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', 'folders');" class="fa-stack w-color-bl-to-or pointer" title="Voltar">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
			</span>		
		</div>
	</div>			
	<div class="large-12 medium-12 small-12 z-index-2 cm-mg-15-t display-center-general-container white">			
		<?= $tgor['ds'] ?>			
	</div>									
	<div class="large-12 medium-12 small-12 cm-pad-15 cm-pad-0-h">
		<input class="w-rounded-30 w-shadow-1 border-like-input input-border cm-pad-15 large-12 medium-12 small-12" type="text" placeholder="Pesquisar em <? echo $tgor['tt']; ?>" onkeyup="goTo('env/<?= $env ?>/backengine/wa0001/folder_content.php', 'folder_content', <?  echo $tgor['id']; ?>, this.value);"/>		
	</div>	
	<div class="cm-mg-15 cm-mg-0-h large-12 medium-12 small-12 w-rounded-15 z-index-2 w-shadow-1 background-white-transparent-75 backdrop-blur">
		<div id="folder_content" class="large-12 medium-12 small-12 fs-c position-relative overflow-x-auto"></div>
		<script>
		(function(){
			'use strict';
			function loadFolder(){
				goTo('env/<?= $env ?>/backengine/wa0001/folder_content.php', 'folder_content', '<?= ($_GET['qt'] !== 3) ?  $_GET['vr'] : '' ?>', '<?= ($_GET['qt'] == 3) ? '&wp='.$_GET['vr'] : ''?>');
			}
			window.loadFolder = loadFolder;
			
			loadFolder();
			
			function exportToPDF(){
				var element = document.getElementById('folder_content');
				
				// Excluir botões e outros elementos desnecessários
				var clone = element.cloneNode(true);
				var buttons = clone.querySelectorAll('button, span');
				buttons.forEach(button => button.remove()); // Remove botões e ícones

				// Usar html2pdf para gerar o PDF
				html2pdf()
					.from(clone)
					.save('Tarefas_<?= sanitizeFileName($tgor['tt']) ?>.pdf');
			}
			window.exportToPDF = exportToPDF;
			
			function exportToExcel(){
				const rows = document.querySelectorAll("#folder_content > div.cm-pad-10");
				const data = [];  // Cabeçalho

				rows.forEach(row => {
					const tarefa = row.querySelector("div:nth-child(1)").innerText.trim();
					const prazo = row.querySelector("div:nth-child(2)").innerText.trim();
					const frequencia = row.querySelector("div:nth-child(3)").innerText.trim();
					const status = row.querySelector("div:nth-child(4)").innerText.trim();
					const conclusao = row.querySelector("div:nth-child(5)").innerText.trim();

					data.push([tarefa, prazo, frequencia, status, conclusao]);
				});

				// Criação da planilha
				const ws = XLSX.utils.aoa_to_sheet(data);
				const wb = XLSX.utils.book_new();
				XLSX.utils.book_append_sheet(wb, ws, "Tarefas");

				// Download do arquivo
				XLSX.writeFile(wb, "Tarefas_<?= sanitizeFileName($tgor['tt']) ?>.xlsx");
			}
			window.exportToExcel = exportToExcel;
		
		})();
		</script>
	</div>
	<?
}else{
	?>
	<div class="large-12 medium-12 small-12 cm-pad-20-h">
		<p>Ocorreu um erro ao carregar a pasta. Por favor, contate o suporte.</p>
	</div>
	<?
}
?>