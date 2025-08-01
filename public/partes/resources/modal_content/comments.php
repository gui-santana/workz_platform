<?
session_start();
require('../../../functions/search.php');
include('../../../functions/actions.php');
date_default_timezone_set('America/Fortaleza');

?>
<div class="large-12 medium-12 small-12 height-100 background-gray overflow-auto">		
	<div class="cm-pad-20-h cm-pad-30-t cm-pad-10-b cm-pad-t-0 large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="toggleSidebar();" class="display-center-general-container w-color-bl-to-or pointer">						
				<a>Fechar</a>	
				<i class="fas fa-chevron-right fs-f cm-mg-10-l"></i>
			</div>
		</div>		
	</div>
	<?
	$pl = $_GET['vr'];

	if(isset($_GET['act'])){
		if($_GET['act'] == 'ins' && isset($_GET['qt']) && $_GET['qt'] <> ''){
			$pdo_params = array(
				'type' => 'insert',	
				'db' => 'hnw',
				'table' => 'hpl_comments',	
				'values' => '"'.$pl.'","'.$_SESSION['wz'].'","'.$_GET['qt'].'","'.date('Y-m-d H:i:s').'"',
				'columns' => 'pl,us,ds,dt'
			);
			if(isset($_GET['res']) && $_GET['res'] <> ''){
				$pdo_params['values'] .= ',"'.$_GET['res'].'"';
				$pdo_params['columns'] .= ',cm';
				unset($_GET['res']);
			}
			$params = base64_encode(json_encode($pdo_params));
			action($params);
		}elseif($_GET['act'] == 'del' && isset($_GET['cm']) && $_GET['cm'] <> ''){
			//DELETE COMMENT
			$pdo_params = array(
				'type' => 'delete',	
				'db' => 'hnw',
				'table' => 'hpl_comments',	
				'where' => 'id = "'.$_GET['cm'].'"'
			);
			$params = base64_encode(json_encode($pdo_params));
			action($params);
			
			//DELETE COMMENTS OF COMMENT
			$pdo_params = array(
				'type' => 'delete',	
				'db' => 'hnw',
				'table' => 'hpl_comments',	
				'where' => 'cm = "'.$_GET['cm'].'"'
			);
			$params = base64_encode(json_encode($pdo_params));
			action($params);
		}
		sleep(.5);
	}
	?>
	<div class="overflow-auto large-12 medium-12 small-12 cm-pad-30" style="height: calc(100% - 184.02px);">
	<?
	$comments = search('hnw', 'hpl_comments', '', "pl = '".$pl."'");
	if(count($comments) > 0){
		foreach($comments as $comment){			
			$content = search('hnw', 'hus', 'ps,tt,un', '')[0];
			$n = count($content);
			if($n > 0){
				if($content['un'] <> ''){ 
					$title = $content['un'];
				}else{ 
					$title = $content['tt'];					
				}			
				$content['im'] = '';	
			}
			if($comment['cm'] == 0){
			?>
			<div class="cm-pad-15 cm-pad-0-h border-b-input large-12 medium-12 small-12 position-relative">
				<div class="large-12 medium-12 small-12">
					<span style="height: 22.5px; width: 22.5px;" class="cm-mg-5-r" style="vertical-align: middle;">
						<img style="height: 22.5px; width: 22.5px;" class="w-circle" src="data:image/jpeg;base64,<? echo $content['im']; ?>"></img>
					</span>
					<?
					if(isset($_SESSION['wz'])){
					?>
					<div class="position-absolute abs-t-15 abs-r-0">
						<span onclick="goTo('../../../partes/resources/modal_content/comments.php', 'config', '', '<? echo $pl; ?>&res=<? echo $comment['id']; ?>');" class="fa-stack w-color-gr-to-wh pointer" title="Responder">
							<i class="fas fa-square fa-stack-2x"></i>
							<i class="fas fa-reply fa-stack-1x gray"></i>
						</span>
						<?
						if($comment['us'] == $_SESSION['wz']){
						?>
						<span onclick="
							goTo('../../../partes/resources/modal_content/comments.php', 'config', '', '<? echo $pl; ?>&act=del&cm=<? echo $comment['id']; ?>');
							setTimeout(() => {
								goTo('backengine/dynamic_bottom_post.php', 'dynamic_bottom_post_' + <? echo $pl; ?>, '', <? echo $pl; ?>);
							}, 1000);							
							" class="fa-stack w-color-gr-to-wh pointer" title="Excluir">
							<i class="fas fa-square fa-stack-2x"></i>
							<i class="fas fa-trash fa-stack-1x gray"></i>
						</span>
						<?
						}
						?>				
					</div>
					<?
					}
					?>					
					<a href="https://workz.com.br/<? if($content['un'] <> ''){ echo $content['un']; }else{ echo '?profile='.$comment['us']; } ; ?>" class="w-color-bl-to-or pointer font-weight-600" style="vertical-align: middle;" target="_blank"><? echo $title; ?></a>
					
					
					<div class="large-12 medium-12 small-12 cm-mg-15-t">
						<a><? echo $comment['ds']; ?></a>
					</div>
					<?						
					$comment_comments = array_keys(array_column($comments, 'cm'), $comment['id']);
					foreach($comment_comments as $comment_id){
						$content = search('hnw', 'hus', 'ps,tt,un', '')[0];
						$n = count($content);
						if($n > 0){
							if($content['un'] <> ''){ 
								$title = $content['un'];
							}else{ 
								$title = $content['tt'];					
							}	
							$content['im'] = '';	
						}
						?>
						<div class="cm-pad-20-t cm-pad-35-l large-12 medium-12 small-12 position-relative">
							<div class="large-12 medium-12 small-12">
								<span style="height: 22.5px; width: 22.5px;" class="cm-mg-5-r" style="vertical-align: middle;">
									<img style="height: 22.5px; width: 22.5px;" class="w-circle" src="data:image/jpeg;base64,<? echo $content['im']; ?>"></img>
								</span>
								<?
								if(isset($_SESSION['wz'])){
								?>
								<div class="position-absolute abs-t-0 abs-r-0">							
									<?
									if($comments[$comment_id]['us'] == $_SESSION['wz']){
									?>
									<span onclick="goTo('../../../partes/resources/modal_content/comments.php', 'config', '', '<? echo $pl; ?>&act=del&cm=<? echo $comments[$comment_id]	['id']; ?>');" class="fa-stack w-color-gr-to-wh pointer" title="Excluir">
										<i class="fas fa-square fa-stack-2x"></i>
										<i class="fas fa-trash fa-stack-1x gray"></i>
									</span>
									<?
									}
									?>				
								</div>
								<?
								}
								?>								
								<a href="https://workz.com.br/<? if($content['un'] <> ''){ echo $content['un']; }else{ echo '?profile='.$comment['us']; } ; ?>" class="w-color-bl-to-or pointer font-weight-600" style="vertical-align: middle;" target="_blank"><? echo $title; ?></a>
								<div class="large-12 medium-12 small-12 cm-mg-15-t">
									<a><? echo $comments[$comment_id]['ds']; ?></a>
								</div>
							</div>
						</div>				
						<?				
					}			
					?>
				</div>
			</div>
			<?
			}
		}
	}else{
		?>
		<div class="gray">Seja o primeiro a comentar esta publicação.</div>		
		<?
	}

	?>
	</div>
	
	<div class="large-12 medium-12 small-12 cm-pad-15 position-absolute abs-b-0 abs-r-0 cm-pad-30">
		<?
		if(isset($_SESSION['wz'])){
		?>
		<div class="w-circle w-shadow pointer float-left" style="height: 40px; width: 40px; background: url(data:image/jpeg;base64,); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
		</div>	
		<div class="float-left w-rounded-15 background-white cm-pad-10 cm-mg-15-l text-left w-shadow" style="width: calc(100% - 55px)">			
			<textarea id="comment_content" class="float-left large-12 medium-12 small-12 text-left border-none" placeholder="<?if(isset($_GET['res']) && $_GET['res'] <> ''){?>Responder<?}else{?>Comentar<?}?> como <? echo strtok(search('hnw', 'hus', 'tt', "id = '".$_SESSION['wz']."'")[0]['tt'], " ");?>"></textarea>
			<span onclick="
			
			goTo('../../../partes/resources/modal_content/comments.php', 'config', document.getElementById('comment_content').value, '<? echo $pl; ?>&act=ins<?if(isset($_GET['res']) && $_GET['res'] <> ''){?>&res=<? echo $_GET['res']; }?>');
			<?if(!isset($_GET['res'])){?>
			setTimeout(() => {
				goTo('backengine/dynamic_bottom_post.php', 'dynamic_bottom_post_' + <? echo $pl; ?>, '', <? echo $pl; ?>);
			}, 1000);
			<?}?>
			" class="position-absolute abs-r-10 abs-b-10 fa-stack w-color-gr-to-wh pointer" title="Enviar">
				<i class="fas fa-square fa-stack-2x"></i>
				<i class="fas fa-paper-plane fa-stack-1x gray"></i>
			</span>										
			<div class="clear"></div>
		</div>	
		<div class="clear"></div>
		<?
		}else{
		?>
		<a href="https://workz.com.br" target="_blank" class="w-color-bl-to-or font-weight-600">Entre para enviar comentários.</a>
		<?
		}
		?>
	</div>
	
</div>