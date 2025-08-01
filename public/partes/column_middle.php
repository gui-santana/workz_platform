<?php 
if($mobile == 1){
?>
<style>
/* Ocultar o checkbox */
#toggle {
	display: none;
}

/* Estilo do botão "+" */
.plus-icon {
	display: block;
	width: 50px;
	height: 50px;
	background-color: #FFFFFF;
	color: #f2571c;
	font-size: 2.5em;
	line-height: 50px;
	text-align: center;
	border-radius: 50%;
	cursor: pointer;
	transition: transform 0.3s ease;
}

/* Contêiner do formulário */
.form-container {
	max-height: 0;
	width: 0;
	opacity: 0;
	overflow: hidden;
	transition: max-height 0.3s ease, padding 0.3s ease, width 0.3s ease, opacity 0.6s ease;
}

/* Estilos quando o checkbox está marcado */
#toggle:checked + .plus-icon {
	transform: rotate(45deg);
}

#toggle:checked ~ .form-container {
	max-height: 166.7px; /* Ajuste conforme o tamanho do seu formulário */
	width: calc(100% - 75px);
	opacity: 1;
}

/* Estilos adicionais */
.expandable-button {
	width: 100%;
	transition: width 0.3s ease;
}		

.form-container form {
	margin: 0;
}

.expandable-button {
	/*position: relative;*/
	position: fixed;
	bottom: 40px;
	left: 0px;
	right: 0px;
	margin: 0 auto;
}

.plus-icon {
	position: absolute;
	z-index: 2;
	right: 20px;
	bottom: 0;
}

.form-container {
	position: absolute;
	bottom: 40px;
	right: 60px;			
}
</style>
		
<?php
}
?>
<div class="cm-pad-15-h large-8-5 medium-8 small-12 position-relative">		
	<?php
	$editorLogo = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wgARCAH0AfQDASIAAhEBAxEB/8QAGQABAQEBAQEAAAAAAAAAAAAAAAUEAwEC/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAH/2gAMAwEAAhADEAAAArgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB8n05jo5jo5jo5jo5jo5jo5jo5jo5jo5/Z6AAAA4ZigwazoAAAAAAAAAAAAAAZTPm61okK4ke1hJVhJVhJVhJ8riQriQriQriPS7AKAAT/MEfWvXoJWa9kPrTCugUAAAAAAAAAAAAn0J0dteTXQAAAAAAAAAAACawx96OVgjWJ3AtvjyoluJdj6FAAAAAAAAAAAAJ1GbHfXk10AAAAAAAAAAAmMsLDqSfizJKk3lWI/1z7n3T89oAAAAAAAAAAAABNpTI06suqgAAAAAAAAAExlhYdQKAk5tKFV7QAAAAAAAAAAAAACZTlxq1ZtNAAAAAAAAAJjLCw6gUAlPIVXtAAAAAAAAAAAAAAAJdSXGvTm00AAAAAAAAmMsLDqBQCU8hVe0AAAAAAAAAAAAAAAAl1JUbdGfRQAAAAAACayQsOoFAJTyFV7QAAympg3gAAAAAAAAAAAACVVlRt0cO9AAAAAAJvuOFh1AoBKeQqvaAAAZNeQ40Z1GAoAAAAAAAAAAABKqyo3d+HegAAAAE5jhYdQKHglEKr2gAAAGTXkONGdRgKAAAAAAAAAAAASqsqN3fh3oAAABPYoWHUCh4JL2FV7QAAAADJryHGjOowFAAAAAAAAAAAAJVWVG/tx7UAAAnsULDqBQ8Eh9QqvaAAAAAAZNeQ40Z1GAoAAAAAAAAAAABLqS43duHegAGPZJjNai6iqKHgkefcKr2gAAAAAAGTXkONGdRgKAAAAAAAAAAAAS6kuNvfh3oABCsRYrTrPhLrRepTkPsVXtAAAAAAAAMmvIcaM6jAUAAAAAAAAAAAAmU5kbO+fRQAGPFRkRYz4e5856Wgn1fVAAAAAAAAAMmvIcaM6jAUAAAAAAAAAAAAm0psatGbTQADz0AAAAAAAAAAAAMmvIcaM6jAUAAAAAAAAAAAAnUZ0aNOXVQAAAAAAAAAAAAAADJryHGjOowFAAAAAAAAAAAAJ9DgctkatH2KAAAAAAAAAAAAAAY9UiNNDh3oAAAAAAAAAAAAADJgtIhrghrgh+XRDXBD9tCKtCL7ZEZZEZZEZZEZZEZaEVaERbERbEXdsAUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB//aAAwDAQACAAMAAAAh88888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888888884w8888888888888887OuO+Oe+OOu8887Pva8888888888888uc8888888888883r/sn8888888888888u888888888884XunSz88888888888888uc8888888884H+8o7c88888888888888u0888888884H+84Lc8888888888888886c88888888f+84bc8888888888888888uc8888888n+84bc88808888888888888+08888883+84Dc8888+8888888888888+c88888/8AvPM3PPPPPPvPPPPPPPPPPPPPvPPPPJ/vPM3PPPPPPFvPPPPPPPPPPPPP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';
	//PÁGINA INICIAL
	if(empty($_GET)){
	?>
	<div id="main_page_content" class="large-12 medium-12 small-12 w-shadow-1 w-rounded-20 background-white cm-pad-30 cm-pad-20-t" 
	style="background-image: url(https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US); background-position: center; background-repeat: no-repeat; background-size: cover;">
		<div class="large-12 medium-12 small-12 cm-pad-15-b white font-weight-600 display-center-general-container text-shadow clearfix">
			<div id="wClock" class="float-left fs-f" style="width: calc(100% - 22px)"></div>							
		</div>
		<?php
		include('partes/resources/apps.php');
		?>				
	</div>
	<script>
	//Relógio
	function startTime() {
		const today = new Date();
		let h = today.getHours();
		let m = today.getMinutes();
		let s = today.getSeconds();
		h = checkTime(h); // <- aqui está a mudança
		m = checkTime(m);
		s = checkTime(s);
		document.getElementById('wClock').innerHTML =  h + ":" + m;
		setTimeout(startTime, 1000);
	}

	function checkTime(i) {
		return (i < 10 ? "0" : "") + i;
	}

	</script>
	<?php
	$hd = $loggedUser['hd'];
	if($hd == 'true'){		
		include('functions/getHtml.php');			
		$url = 'https://homilia.cancaonova.com/pb/';			
		try {
			// Tenta carregar o conteúdo HTML da URL
			$htmlContent = loadHTMLFileWithErrors($url);
			// Define a consulta XPath para o primeiro parágrafo dentro da div específica
			$paragraphQuery = "//div[contains(@class, 'entry-content content-homilia')][1]//p[1]";
			// Obtém o texto do primeiro parágrafo
			$firstParagraph = getFirstParagraphFromHTML($htmlContent, $paragraphQuery);
			if ($firstParagraph !== null) {
				// Exibe o conteúdo dentro do link estilizado
				?>
				<a href="<?= htmlspecialchars($url) ?>" target="_blank" class="w-color-bl-to-or">
					<div class="w-shadow-1 italic cm-pad-25 large-12 medium-12 small-12 background-white w-rounded-20 text-center cm-mg-30-t">
						<?= htmlspecialchars($firstParagraph) ?>
					</div>
				</a>
				<?php
			} else {
				throw new Exception("Nenhum parágrafo encontrado no conteúdo da URL.");
			}
		} catch (Exception $e) {
			// Trata os erros e exibe uma mensagem amigável ao usuário
			?>
			<div class="error-message">
				<p>Não foi possível carregar o conteúdo de Canção Nova.</p>
				<p>Erro: <?php echo htmlspecialchars($e->getMessage()); ?></p>
			</div>
			<?php
		}
	}
	}elseif($get_count > 0 && (isset($_GET['team']) || isset($_GET['profile']) || isset($_GET['company']))){
	?>
	<!-- PAGE IMAGE -->
	<div class="cm-mg-25-b hide-for-large hide-for-medium">
		<div class="large-12 medium-12 small-12">
			<div class="large-4 medium-4 small-4 centered">
				<div class="large-12 medium-12 small-12 w-square position-relative">											
					<div class="w-circle w-square-content position-relative border-div-gray" style="background: url(data:image/jpeg;base64,<? if(!empty($_GET)){ echo $pgim; }else{ echo $loggedUser['im']; } ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />					
					</div>																					
				</div>
			</div>
		</div>
		<div id="pageTitle" class="text-ellipsis text-center line-height-a">
			<p class="fs-g font-weight-600"><?= $pgtt ?></p>
			<small class="gray">workz.com.br/<?= $pgun ?> </small>
		</div>			
	</div>						
	<div class="w-rounded-25-t w-shadow-t clearfix" style="background: linear-gradient(to bottom, rgba(255,255,255,1) 0%,rgba(245,245,245,1) 100%);">
		<div class="cm-pad-15 cm-pad-7-5-b">
		<h2 class="hide-for-small-only cm-pad-7-5 cm-pad-0-h"><?php echo $pgtt; ?></h2>		
		<?php
		if(isset($_GET['profile'])){
		?>
		<div class="font-weight-500 fs-e cm-pad-7-5 cm-pad-0-h clearfix">
			<div class="large-4 medium-4 small-4 float-left text-center text-ellipsis">
				<p><small class="gray">Publicações</small><br/><?= $post_count ?></p>
			</div>
			<div class="large-4 medium-4 small-4 float-left text-center text-ellipsis">
				<p><small class="gray">Seguidores</small><br/><?= count($followers) ?></p>
			</div>
			<div class="large-4 medium-4 small-4 float-left text-center text-ellipsis">
				<p><small class="gray">Seguindo</small><br/><?= count($followed) ?></p>
			</div>			
		</div>
		<?php
		}
		?>
		<?php
		if(isset($_GET['team'])){			
			$team_creator = search('hnw','hus','',"id = {$pgus}")[0];
			if(isset($_GET['team'])){
			?>
			<small class="gray"> Equipe de <a class="w-color-or-to-bl pointer" href="https://workz.com.br/?company=<?= $pgem ?>"><?= search('cmp','companies','tt',"id = {$pgem}")[0]['tt'] ?></a></small>
			<?
			}	
		}
		?>
		</div>
		<?php
		if((isset($_GET['company']) || isset($_GET['team']) || (isset($_GET['profile']) && $pgpc > 0))){
			?>
			<div class="large-12 medium-12 small-12 cm-pad-7-5 cm-pad-15-h">			
			<?= nl2br(htmlspecialchars($pgds ?? '')) ?>			
			</div>
			<?
			if($postedImgs = search('hnw', 'hpl', '', "{$pgft} = '{$pgid}' AND tp = '2' ORDER BY dt DESC LIMIT 0,6")){
				echo '<div class="large-12 medium-12 small-12 cm-pad-7-5 clearfix">';
				foreach($postedImgs as $postedImg){
				?>					
				<div class="large-4 medium-4 small-4 cm-pad-7-5 float-left">								
					<div class="large-12 w-square position-relative">
						<div class="w-rounded-20 w-square-content w-shadow position-relative" style="background: url(data:image/jpeg;base64,<?= $postedImg ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" /></div>
					</div>								
				</div>																																							
				<?php
				}
				echo '</div>';
			}
			
			if(!empty($_GET['company'])){									
			$hPosts = search('hnw', 'hpl', '', "em = '".$_GET['company']."' AND tp = '9' AND st = '1' ORDER BY dc");
			?>
			<link rel="stylesheet" href="app/core/backengine/wa0006/suneditor/dist/css/se_viewer.css" />								
			<?php
			foreach($hPosts as $post){
				?>
				<div class="large-12 medium-12 small-12 cm-pad-15 cm-mg-30-b">										
					<div class="large-12 medium-12 small-12 sun-editor-viewer cm-pad-0">
					<?= bzdecompress(base64_decode($post['ct'])) ?>
					</div>
				</div>
				<?php
			}								
			}
				
		}
		?>		
	</div>
	
	<?
	}else{
		echo 'Página indisponível';
	}
	
	if(isset($loggedUser)){
	//POST EDITOR	

	if(isset($pgft)){
		if ($user_level === "") {
			unset($page);
		}elseif($user_level > 0){
			$page = array_keys($_GET)[0].'='.array_values($_GET)[0];
		}
	}else{
		$page = '';
	}
	
	if(isset($page)){	
	if($mobile == 1){?>
	<div class="expandable-button z-index-1">
	<input type="checkbox" id="toggle" />
	<label for="toggle" class="plus-icon w-modal-shadow">+</label>
	<?}?>
	<div class="w-shadow-1 large-12 medium-12 small-12 background-white w-rounded-20 text-center cm-mg-30-t <?= ($mobile == 1) ? 'form-container w-modal-shadow z-index-2' : '' ?>">
		<div class="large-12 medium-12 small-12 cm-pad-15 border-b-input clearfix">				
			<div class="w-circle pointer float-left" style="height: 40px; width: 40px; background: url(data:image/jpeg;base64,<?= $loggedUser['im'] ?>); background-size: cover; background-position: center; background-repeat: no-repeat;" />
			</div>				
			<div id="pageConfig" onclick="
					toggleSidebar();
					var config = $('<div id=config class=height-100></div>');
					$('#sidebar').append(config);
					waitForElm('#config').then((elm) => {
						goTo('partes/resources/modal_content/editor.php', 'config', 1, '&<?= $page ?>');						
					});" class="float-left w-rounded-20 w-bkg-tr-gray cm-pad-10 cm-mg-15-h text-left pointer text-ellipsis" style="width: calc(100% - 110px)">
				<a class="gray pointer" style="vertical-align: middle;">O que você quer publicar, <?= strtok($loggedUser['tt'], " ") ?>?</a>
			</div>
			<?
			$app = search('app', 'apps', 'id,tt,im,nm,us', "id = '6'")[0];
			$url = 'https://workz.com.br/app/index.php?valor='.$app['nm'];
			?>
			<div title="Abrir <?= $app['tt'] ?>" onclick="newWindow('<?= $url ?>', '<?= $app['id'] ?>', btoa(encodeURIComponent('<?= $app['im'] ?>')))" class="w-circle w-shadow-1 pointer" style="background: url(<?= (empty($app['im'])) ? 'https://workz.com.br/images/no-image.jpg' : 'data:image/jpeg;base64,'.$app['im'] ?>); background-size: cover; background-position: center; background-repeat: no-repeat; height: 40px; width: 40px;"></div>
			<?
			?>			
		</div>
		<div class="large-12 medium-12 small-12 cm-pad-15">
			<div class="w-rounded-20 clearfix">				
				<div id="pageConfig" onclick="postEditor(4)" class="float-left large-6 medium-6 small-6 w-bkg-tr-gray pointer cm-pad-10 w-rounded-20-l text-ellipsis w-color-bl-to-or">
					<i class="fas fa-video orange"></i>
					<a>Link de Vídeo</a>
				</div>
				<div id="pageConfig" onclick="postEditor(3)" class="float-left large-6 medium-6 small-6 w-bkg-tr-gray pointer cm-pad-10 w-rounded-20-r text-ellipsis w-color-bl-to-or">
					<i class="fas fa-newspaper orange"></i>					
					<a>Link de Notícia</a>
				</div>				
			</div>
		</div>
	</div>
	<script>
		function postEditor(type){		
			toggleSidebar(); 
			var config = $('<div id=config class=height-100></div>'); 
			$('#sidebar').append(config); 
			waitForElm('#config').then((elm) => {
				goTo('partes/resources/modal_content/editor.php', 'config', type, '&<?= $page ?>');
			});
		}
	</script>
	<?php if($mobile == 1){?>
	</div>
	<?}
	}
	}
	?>			
</div>	