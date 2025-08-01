<?php 
if(isset($_GET['lk'])){
	
	session_start();
	require_once('../functions/search.php');
	require_once('../functions/insert.php');
	require_once('../functions/delete.php');
	
	$post['id'] = $_GET['lk'];
	if($spl = search('hnw', 'lke', 'id', "pl = '{$post['id']}' AND us = '{$_SESSION['wz']}'")){
		if(del('hnw', 'lke', "pl = '{$post['id']}' AND us = '{$_SESSION['wz']}'")){			
		}else{
			echo 'Erro: A curtida não foi deletada.';
		}
	}else{
		if(insert('hnw', 'lke', 'pl,us', "'{$post['id']}','{$_SESSION['wz']}'")){			
		}else{
			echo 'Erro: A curtida não foi incluída.';
		}
	}	
}
?>
<!-- Botão de Comentário -->
<a onclick="
	toggleSidebar();
	var config = $('<div id=config class=height-100></div>'); 
	$('#sidebar').append(config); 
	waitForElm('#config').then((elm) => {
		goTo('../partes/resources/modal_content/comments.php', 'config', '', '<?php  echo $post['id']; ?>');
	});
" class="comment fs-g">
	<i title="Comentar esta publicação" class="far fa-comment pointer w-color-wh-to-or cm-mg-5-r"></i>
</a>
<!-- Botão de Curtida -->
<?php 
if(isset($_SESSION['wz'])){
	$isLiked = search('hnw', 'lke', 'pl', "pl = '{$post['id']}' AND us = '{$_SESSION['wz']}'");
	$likeIcon = count($isLiked) > 0 ? 'fas fa-heart' : 'far fa-heart';
	$likeColor = count($isLiked) > 0 ? 'white' : 'w-color-wh-to-or';
	?>
	<i onclick="goTo('../backengine/like.php','dynamic_bottom_like_<?php  echo $post['id']; ?>','&lk=<?php  echo $post['id']; ?>','')" class="<?php  echo $likeIcon; ?> pointer fs-g <?php  echo $likeColor; ?>"></i>
<?php 
}else{
	?>
	<i class="far fa-heart fs-g white"></i>
	<?php 
}
$likes = count(search('hnw', 'lke', 'pl', "pl = '{$post['id']}'"));
?>
<!-- Contagem de Curtidas -->
<div class="large-12 medium-12 small-12 text-right fs-b white">
	<a class="font-weight-500">
		<?php  echo $likes; ?> curtida<?php  echo $likes !== 1 ? 's' : ''; ?>
	</a>
</div>