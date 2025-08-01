<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
session_start();

$app = json_decode($_SESSION['app'], true);
$env = $app['environment'][$app['env']];
$colors = explode(';', $app['cl']);

$now = date('Y-m-d H:i:s');
$tgo = search('app', 'wa0001_tgo', '', "id = '".$_GET['vr']."' AND st = '0'");
if(count($tgo) > 0){
	$tgor = $tgo[0];	
	?>	
	
		
		<div class="large-12 medium-12 small-12 display-center-general-container cm-mg-15-t">				
			<div class="float-left fs-g height-100 font-weight-500 text-ellipsis large-10 medium-8 small-6 orange"><?= (!empty($tgor['cl'])) ? '<i class="fas fa-bookmark" style="color:' . $tgor['cl'] . '"></i> ' : '' ?><? echo $tgor['tt']; ?></div>
			<!-- MENU SUPERIOR DIREITO -->
			<div class="float-left large-2 mediun-4 small-6 text-right">						
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
				<span onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', 'folders');" class="fa-stack w-color-bl-to-or pointer" title="Voltar">
					<i class="fas fa-circle fa-stack-2x"></i>
					<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
				</span>		
			</div>
		</div>			
		<div class="large-12 medium-12 small-12 z-index-2 cm-mg-15-t display-center-general-container">			
			<?= $tgor['ds'] ?>			
		</div>									
		<div class="large-12 medium-12 small-12 cm-pad-15 cm-pad-0-h">
			<input class="w-rounded-30 w-shadow-1 border-like-input input-border cm-pad-15 large-12 medium-12 small-12" type="text" placeholder="Pesquisar em <? echo $tgor['tt']; ?>" onkeyup="goTo('env/<?= $env ?>/backengine/wa0001/folder_content.php', 'folder_content', <?  echo $tgor['id']; ?>, this.value);"/>		
		</div>	
	<div class="cm-mg-15 cm-mg-0-h large-12 medium-12 small-12 w-rounded-15 z-index-2 w-shadow-1 background-white-transparent-75 backdrop-blur">
		<div id="folder_content" class="large-12 medium-12 small-12 position-relative overflow-x-auto"></div>
		<script>
		(function(){
			'use strict';
			function loadFolder(){
				goTo('env/<?= $env ?>/backengine/wa0001/folder_content.php', 'folder_content', <?= $_GET['vr'] ?>, '');
			}
			window.loadFolder = loadFolder;
			
			loadFolder();
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