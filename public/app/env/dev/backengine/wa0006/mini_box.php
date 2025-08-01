<?
if(!empty($_POST['vr'])){
	
	echo '<img class="w-rounded-5 cm-mg-10-b" height="200px" src="'.base64_decode($_POST['vr']).'"/>';
	
	?>	
	<div placeholder="Comente sua publicação" class="background-gray w-rounded-5 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 background-white position-relative"></div>	
	<input type="submit" value="Publicar" class="large-12 medium-12 small-12 cm-pad-10 cm-mg-10-t" disabled></input>
	<?
}else{
	?>
	<div class="large-12 medium-12 small-12 w-rounded-5 position-relative display-flex">
		<div class="large-6 medium-6 small-6 text-ellipsis">
			<a dir="rtl" class="font-weight-600" ><? if($get_count > 0 && (isset($_GET['team']) || isset($_GET['profile']) || isset($_GET['company']))){  echo 'Publicar na linha do tempo de '.$pgtt; }else{ echo 'Publicar em minha linha do tempo'; }?></a>
		</div>
		<div id="post-menu" class="large-6 medium-6 small-6 text-right" title="Página da Web">			
			<span id="add-hiperlink" onclick="var sLnk=prompt('Escreve a sua URL aqui','http:\/\/');if(sLnk&&sLnk!=''&&sLnk!='http://'){goTo('apps/core/backengine/wa0006/mini_box.php', 'postContent', '0', sLnk)}" class="fa-stack w-color-gr-to-gr float-right pointer" title="URL da página da web">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-link fa-stack-1x dark"></i>
			</span>
			<span id="add-hiperlink" onclick="var sLnk=prompt('Escreve a sua URL aqui','http:\/\/');if(sLnk&&sLnk!=''&&sLnk!='http://'){formatDoc('createlink',sLnk)}" class="fa-stack w-color-gr-to-gr float-right pointer" title="URL do vídeo do YouTube">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fab fa-youtube fa-stack-1x dark"></i>
			</span>
			<div onclick="addImage(this)" class="fa-stack w-color-gr-to-gr float-right pointer" title="Imagem">
				<i class="fas fa-circle fa-stack-2x"></i>
				<i class="fas fa-images fa-stack-1x dark"></i>
			</div>			
		</div>
		<div class="clear"></div>								
	</div>
	<?	
}
?>
