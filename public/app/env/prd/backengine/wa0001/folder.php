<?
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');

include($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
if(isset($_POST['vr'])){
	include('edit.php');
}else{
	session_start();
}
if(strpos($_GET['vr'], '|') > 0){
	include($_SERVER['DOCUMENT_ROOT'].'/functions/delete.php');
	//$_GET['vr'] = 'PASTA|TAREFA'
	$vr = explode('|',$_GET['vr']);	
	del('app', 'wa0001_wtk', "id = '".$vr[1]."'");
	$_GET['vr'] = $vr[0];
}
$now = date('Y-m-d H:i:s');
$tgo = search('app', 'wa0001_tgo', '', "id = '".$_GET['vr']."' AND st = '0'");
if(count($tgo) > 0){
	$tgor = $tgo[0];
	print_r($tgor);
	?>
	<div class="large-12 medium-12 small-12 display-center-general-containter">
		<div class="float-left large-10 medium-10 small-8 display-center-general-containter">
			<span class="w-circle float-left" style="background-color: <?= $tgor['cl'] ?>; height: 45px; width: 45px;"></span>
			<h3 class="text-ellipsis cm-mg-20-t"><? echo $tgor['tt']; ?></h3>
			<p><?= $tgor['ds'] ?></p>
		</div>
		<div class="float-left large-2 medium-2 small-4">
			<?
			if($tgor['us'] == $_SESSION['wz']){				
			?>			
			<span onclick="" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Excluir permanentemente esta pasta e suas respectivas tarefas">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-trash fa-stack-1x fa-inverse"></i>
			</span>
			<span onclick="editMode()" class="open-sidebar fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Editar pasta">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-pen fa-stack-1x fa-inverse"></i>
			</span>
			<script>
			(function(){
				'use strict';
				
				function deleteFolder(){
					wAlert('core/backengine/wa0001/main-content.php', 'main-content', <? echo $tgor['id']; ?>, 'folders', 
					'Deseja excluir permanentemente esta pasta e suas respectivas tarefas?', 
					'A pasta e suas respectivas tarefas foram excluídas com sucesso.', 
					'A pasta de tarefas não foi excluída.'
					);
				}
				
				function editMode(){
					toggleSidebar();							
					goTo('core/backengine/wa0001/menu.php', 'config', 2, '&id=<?= $tgor['id'] ?>');
				}
				window.editMode = editMode;
				
			})();
			</script>
			<?php			
			}
			?>	
			<span onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', 'folders');" class="fa-stack w-color-bl-to-or pointer" style="vertical-align: middle;">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
			</span>	
		</div>
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-25-t cm-pad-15-b">
		<input class="w-rounded-10 w-shadow-1 border-like-input input-border cm-pad-15 large-12 medium-12 small-12" type="text" placeholder="Pesquisar em <? echo $tgor['tt']; ?>" onkeyup="goTo('core/backengine/wa0001/folder_content.php', 'folder_content', <?  echo $tgor['id']; ?>, this.value);"/>		
	</div>	
	<div id="folder_content" class="large-12 medium-12 small-12 position-relative overflow-x-auto">			
	<? include('folder_content.php'); ?>
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