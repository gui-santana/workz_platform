<?php
include('user_cmp.php');

//SEARCH TEAMS FOLDERS
$search_post = '';
foreach($teams as $team){
	$search_post .= " cm = '".$team."' OR";									
}
$search_post .= "DER BY id";
$tgtsk_team = array_column(search('app', 'wa0001_wtk', 'tg,cm', $search_post), 'tg');
//SEARCH USER'S FOLDERS
$tgtsk_user = array_column(search('app', 'wa0001_tgo', 'id', "us = '".$_SESSION['wz']."'"), 'id');
$folders = array_unique(array_merge($tgtsk_team, $tgtsk_user));
?>
<div class="w-community-container cm-pad-20-t">
	<?php
	if(count($folders) > 0){
		foreach($folders as $folder){				
			$tgtskr = count(search('app', 'wa0001_wtk', 'id', "tg = '".$folder."'"));		
			$tgo = search('app', 'wa0001_tgo', 'id,tt', "id = '".$folder."'");				
			if(count($tgo) > 0){
				$tgor = $tgo[0];
				?>
				<div class="large-1 medium-2 small-4 cm-pad-10 float-left">
					<div class="pointer w-color-bl-to-or">
						<div title="<? echo $tgor['tt']; ?>" class="large-12 medium-12 small-12 position-relative" onclick="goTo('core/backengine/wa0001/folder.php', 'folder-root', 1, '<? echo $tgor['id']; ?>');">
							<svg class="large-12 medium-12 small-12" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Free 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2022 Fonticons, Inc. --><path d="M64 480H448c35.3 0 64-28.7 64-64V160c0-35.3-28.7-64-64-64H298.5c-17 0-33.3-6.7-45.3-18.7L226.7 50.7c-12-12-28.3-18.7-45.3-18.7H64C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64z"/></svg>
							<a class="white fs-b position-absolute abs-b-20 abs-r-15"><? echo $tgtskr; ?></a>
						</div>
						<p class="text-ellipsis pointer text-center"><? echo $tgor['tt']; ?></p>
					</div>				
				</div>
				<?			
			}				
		}
	}else{
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