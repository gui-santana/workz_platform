<?php
require_once('../../common/getUserAccessibleEntities.php');

$userEntities = getUserAccessibleEntities($_SESSION['wz']);
$teams = $userEntities['teams'];

//SEARCH TEAMS FOLDERS
$search_post = '';
foreach($teams as $team){
	$search_post .= " cm = '".$team."' OR";	
}
$search_post .= "DER BY tt";
$tgtsk_team = array_column(search('app', 'wa0001_wtk', 'tg,cm', $search_post), 'tg');
//SEARCH USER'S FOLDERS
$tgtsk_user = array_column(search('app', 'wa0001_tgo', 'id', "us = '".$_SESSION['wz']."'"), 'id');
$folders = array_unique(array_merge($tgtsk_team, $tgtsk_user));

// Cria um array associativo para manter nome e valor das pastas
$folder_names = [];
foreach ($folders as $folder) {
	$folder_names[$folder] = search('app', 'wa0001_tgo', 'tt', "id = '{$folder}'")[0]['tt'];
}

// Ordena alfabeticamente com base nos nomes das pastas
asort($folder_names);

//Obtém conexões com WordPress
$wpConn = search('app', 'wa0001_wp', '', "us = '{$_SESSION['wz']}'");

?>
<div class="w-community-container cm-pad-20-t">
	<?php
	if(count($folders) > 0){
    	foreach ($folder_names as $folder => $name){
    		$tgtskr = count(search('app', 'wa0001_wtk', 'id', "tg = '".$folder."'"));
    		if($tgo = search('app', 'wa0001_tgo', 'cl', "id = '".$folder."'")){
    		$tgor = $tgo[0];
    		?>
    		<div class="large-1 medium-2 small-4 cm-pad-10 float-left">
    			<div class="pointer w-color-wh-to-or">
    				<div title="<?= $name ?>" class="large-12 medium-12 small-12 position-relative" onclick="goTo('env/<?= $env ?>/backengine/wa0001/folder.php', 'folder-root', 1, '<?= $folder ?>');">
    					<svg class="large-12 medium-12 small-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    						<defs>
    							<!-- Definindo o filtro para a sombra -->
    							<filter id="dropShadow" x="-50%" y="-50%" width="200%" height="200%">
    								<feDropShadow dx="0" dy="0" stdDeviation="0" flood-color="black"/>
    							</filter>
    						</defs>
    						<path fill="<?= $tgor['cl'] ?>" d="M64 480H448c35.3 0 64-28.7 64-64V160c0-35.3-28.7-64-64-64H298.5c-17 0-33.3-6.7-45.3-18.7L226.7 50.7c-12-12-28.3-18.7-45.3-18.7H64C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64z"/>
    					</svg>
    					<a class="white fs-b position-absolute abs-b-15 abs-r-10"><? echo $tgtskr; ?></a>
    				</div>
    				<p class="text-ellipsis pointer text-center"><?= $name ?></p>
    			</div>				
    		</div>
    		<?php
    		}
    	}
	}
	if(count($wpConn) > 0){
	    echo '<hr class="cm-pad-15-t">';
	    foreach ($wpConn as $folder){
    		?>
    		<div class="large-1 medium-2 small-4 cm-pad-10 float-left">
    			<div class="pointer w-color-wh-to-or">
    				<div title="<?= $folder['ur'] ?>" class="large-12 medium-12 small-12 position-relative" onclick="goTo('env/<?= $env ?>/backengine/wa0001/folder.php', 'folder-root', 3, '<?= $folder['id'] ?>');">
    					<svg class="large-12 medium-12 small-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
    						<defs>
    							<!-- Definindo o filtro para a sombra -->
    							<filter id="dropShadow" x="-50%" y="-50%" width="200%" height="200%">
    								<feDropShadow dx="0" dy="0" stdDeviation="0" flood-color="black"/>
    							</filter>
    						</defs>
    						<path fill="#000000" d="M64 480H448c35.3 0 64-28.7 64-64V160c0-35.3-28.7-64-64-64H298.5c-17 0-33.3-6.7-45.3-18.7L226.7 50.7c-12-12-28.3-18.7-45.3-18.7H64C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64z"/>
    					</svg>
    				</div>
    				<p class="text-ellipsis pointer text-center"><?= $folder['ur'] ?></p>
    			</div>
    		</div>
    		<?php
    	}
	}
	
	
	if(count($folders) == 0 && count($wpConn) == 0){
	?>						
	<div class="large-10 medium-12 small-12 position-relative centered cm-mg-20-t" style="color: <? echo $colours[0]; ?>">					
		<div class="cm-mg-20-t large-12 medium-12 small-12 cm-pad-15 text-center">				
			<p><strong>Dica para uso das pastas</strong>:</p>
			<p>Ao entrar em uma pasta, uma lista de tarefas associadas a ela deve ser exibida.</p>
			<p>Clique em qualquer tarefa exibida dentro da pasta para editá-la.</p><br/>
			<p><strong>Dica para o compartilhamento de tarefas</strong>:</p>
			<p>Para compartilhar uma tarefa, basta que os envolvidos façam parte da mesma equipe.</p>
			<p>Compartilhe tarefas ao criá-las ou editá-las. Ambos poderão executá-las.</p>					
		</div>
	</div>
	<?php
	}	
	?>
	<div class="clear"></div>
</div>