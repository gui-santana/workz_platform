<?php 
session_start();
require('../../../functions/search.php');
require('../../../functions/update.php');
date_default_timezone_set('America/Fortaleza');

//MOBILE DEVICES
require_once '../../../includes/Mobile-Detect-2.8.41/Mobile_Detect.php';
$detect = new Mobile_Detect();
$mobile = $detect->isMobile() ? 1 : 0;

//
?>
<div class="large-12 medium-12 small-12 height-100 background-gray overflow-auto position-relative">
<?php 	

	$vr = '';
	if(isset($_POST['vr']) && !empty($_POST['vr'])){
		
		require_once('../../../functions/insert.php');
        $now = date('Y-m-d H:i:s');
		$vr = get_object_vars(json_decode($_POST['vr']));
		
		// Decodifique os dados enviados
		$vr['lg'] = urldecode($vr['lg']); // Decodifica o conteúdo enviado no formato urlencode
		// Converta para UTF-8, se necessário
		if (mb_detect_encoding($vr['lg'], 'UTF-8', true) === false) {
			$vr['lg'] = mb_convert_encoding($vr['lg'], 'UTF-8', 'ISO-8859-1');
		}
		// Verifica caracteres inválidos e os remove
		$vr['lg'] = base64_encode(preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $vr['lg']));
		
		$vr['us'] = $_SESSION['wz'];
		$vr['dt'] = $now;
		$vr['st'] = 1;
		
		$data = json_decode(base64_decode($vr['ct']), true);
		
		if ($data === null) {
			
			echo "Erro ao decodificar ou descompactar a string.";
			
		} else {
			
			$vr['ct'] = base64_encode(bzcompress(json_encode($data)));
			
			foreach ($vr as $chave => $valor) {
				if($chave !== 'id'){
					$campos[] = $chave;
					$valores[] = "'".addslashes($valor)."'"; // proteção contra aspas em valores
				}
			}
			
			$campos_str = implode(',', $campos);
			$valores_str = implode(',', $valores);
			
			if ($success = insert('hnw', 'hpl', $campos_str, $valores_str)){
				echo '<div id="success_'.$vr['id'].'"> Post publicado com sucesso: '.$success.'</div>';			
			}else{
				http_response_code(500);
				echo 'Erro ao publicar.';
			}
			
		}				
		
	}else{
		
		$variables = $_GET;
		$getURL = '';	
		unset($variables['vr']);
		unset($variables['qt']);
		for($i = 0; $i < count($variables); $i++){
			$getURL .= array_keys($variables)[$i].'='.array_values($variables)[$i];
			if($i < (count($variables) - 1)){
				$getURL .= '&';
			}
		}
		$sideline = '';
		if(!empty($getURL)){
			if(strpos($getURL, 'profile=') !== false){
				$sideline = 'profile,'.substr($getURL, strpos($getURL, 'profile=') + 8, 100).',';
			}elseif(strpos($getURL, 'team=') !== false){
				$sideline = 'team,'.substr($getURL, strpos($getURL, 'team=') + 5, 100).',';
			}elseif(strpos($getURL, 'company=') !== false){
				$sideline = 'company,'.substr($getURL, strpos($getURL, 'company=') + 8, 100).',';
			}				
		}
		
		?>					
		<div class="large-12 medium-12 small-12 height-100">			
			<?php 
			if($_GET['qt'] == 0){
			?>
			<div id="main" class="">
				<div class="large-12 medium-12 cm-pad-20 cm-pad-30-b small-12 text-center gray">
					<h2>Opções de publicação</h2>	
				</div>			
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-b">			
					<div class="w-shadow w-rounded-15">								
						<div onclick="goTo('partes/resources/modal_content/editor.php', 'editor', 1, '');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
								<span class="fa-stack orange" style="vertical-align: middle;">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-comment-dots fa-stack-1x fa-inverse dark"></i>					
								</span>						
								Mensagem
							</div>										
						</div>
					</div>
				</div>			
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-10-b">			
					<div class="w-shadow w-rounded-15">								
						<div onclick="goTo('partes/resources/modal_content/editor.php', 'editor', 2, '');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
								<span class="fa-stack orange" style="vertical-align: middle;">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-image fa-stack-1x fa-inverse dark"></i>					
								</span>						
								Imagem
							</div>										
						</div>
					</div>
				</div>	
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-10-b">			
					<div class="w-shadow w-rounded-15">
						<div onclick="goTo('partes/resources/modal_content/editor.php', 'editor', 3, '');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
								<span class="fa-stack orange" style="vertical-align: middle;">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-newspaper fa-stack-1x fa-inverse dark"></i>					
								</span>		
								Link de Notícia
							</div>
						</div>								
					</div>
				</div>
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-10-b">			
					<div class="w-shadow w-rounded-15">
						<div onclick="goTo('partes/resources/modal_content/editor.php', 'editor', 4, '');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
								<span class="fa-stack orange" style="vertical-align: middle;">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fab fa-youtube fa-stack-1x fa-inverse dark"></i>					
								</span>		
								Link do YouTube
							</div>
						</div>								
					</div>
				</div>						
				<p class="gray cm-pad-20-h cm-pad-10-t">
				Para a criação de artigos completos, recomendamos a utilização do aplicativo "Artigos", localizado na sua página inicial. 
				Ele oferece todas as ferramentas necessárias para criar conteúdo impactante e de alta qualidade.
				</p>
				<p class="gray cm-pad-20-h">
				Através do aplicativo "Artigos", você terá acesso a recursos que facilitam a criação de textos envolventes e informativos. 
				Além disso, proporciona uma forma mais acessível de exibir o seu conteúdo por meio de links diretos ou mecanismos de busca.
				</p>
			</div>	
			<?php 
			}else{
			?>
			<div class="cm-pad-20 cm-pad-t-0 large-12 medium-12 small-12 text-ellipsis">
				<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
					<div onclick="toggleSidebar();" class="display-center-general-container w-color-bl-to-or pointer">						
						<a>Fechar</a>	
						<i class="fas fa-chevron-right fs-f cm-mg-10-l"></i>
					</div>
				</div>		
			</div>
			<style>
				video {
					display: none;
				}
				
				.button-container {
					display: flex;
					justify-content: center;
					align-items: center;
					height: auto;		
					left: 50%;
					transform: translateX(-50%);
				}
				/* Estilos do botão redondo */
				.round-button {
					width: 75px; /* Tamanho do botão */
					height: 75px; /* Tamanho do botão */
					background-color: white; /* Cor de fundo */
					border: 7.5px double black; /* Borda ao redor do botão */
					border-radius: 50%; /* Faz o botão ser circular */
					cursor: pointer; /* Mostra o cursor como pointer */
					outline: none;
					opacity: .7;
					position: relative;
				}

				/* Efeito ao passar o mouse */
				.round-button:hover {
					background-color: lightgray; /* Altera a cor ao passar o mouse */
				}
				
				#brush-size {
					/* Transforma o elemento na vertical */
					writing-mode: bt-lr; /* Define a direção vertical */					
					height: 200px; /* Altura do controle */
					width: 20px; /* Largura do controle */
				}
				
				#brush-controls {
					opacity: 0; /* Inicialmente oculto */
					visibility: hidden; /* Remove do fluxo visual */
					transition: opacity 0.3s ease, visibility 0.3s ease; /* Transição suave */
				}

				#brush-controls.show {
					opacity: 1; /* Totalmente visível */
					visibility: visible; /* Tornar visível */
				}
				#progress-bar{
					transition: all .5s ease-in-out;
				}	
			</style>
			<div id="divForm" class="cm-pad-20-h cm-pad-40-b" style="height: calc(100% - 63.75px);">
				<?php 
				//PUBLICAÇÃO
				if($_GET['qt'] == 1){
				?>				
				<div class="story-header large-12 medium-12 small-12 text-center gray cm-mg-20-b">
					<h2>Nova Publicação</h2>
				</div>

				<!-- Container do Editor -->
				<div id="editor-container" class="story-editor large-12 medium-12 small-12 position-relative w-rounded-20 background-dark centered w-shadow" style="max-width: 480px;">

					<!-- Ferramentas do Editor -->
					<div class="editor-tools position-absolute abs-t-0 abs-l-0 large-12 medium-12 small-12 cm-pad-15-h fs-g">
						<span onclick="toggleDraw()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Desenhar">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-paint-brush fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<span onclick="addText()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Adicionar Texto">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-font fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<span onclick="addEmoji()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Adicionar Emoji">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-smile-wink fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<span onclick="downloadCanvas()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Baixar Imagem">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-cloud-download-alt fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<span onclick="undo()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Desfazer">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-undo-alt fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<span onclick="redo()" class="fa-stack pointer cm-pad-0 cm-mg-15-t" title="Refazer">
						<i class="fas fa-circle fa-stack-2x white-transparent cm-pad-0 cm-mg-0 w-shadow"></i>
						<i class="fas fa-redo-alt fa-inverse fa-stack-1x white cm-pad-0 cm-mg-0"></i>
						</span>
						<?php 
						if ($mobile == 0) {
						echo '<button onclick="startCameraAndRecording()" style="background: linear-gradient(to right, #85d0ff, #9a85ff); border: none; padding: 10px 20px; border-radius: 5px; color: white; font-size: 16px; cursor: pointer;" title="Abrir Câmera"><i class="fas fa-camera"></i></button>';
						echo '<button id="stop-recording-btn" onclick="stopRecording()" style="display: none; background: linear-gradient(to right, #ff5c8d, #ffa585); padding: 10px 20px; border: none; border-radius: 5px; color: white; font-size: 16px; cursor: pointer;" title="Parar Gravação"><i class="fas fa-stop"></i></button>';
						}
					?>
					</div>

					<!-- Controles do Pincel -->
					<div id="brush-controls" class="display-none position-absolute abs-t-50 abs-r-20 background-white w-rounded-10 cm-pad-10 w-shadow" style="text-align: center;">
						<input type="color" id="brush-color" value="#000000"><br/>
						<input type="range" class="cm-mg-10-t" id="brush-size" min="1" max="20" value="4">
					</div>

					<!-- Input para Upload de Arquivos -->
					<div id="file-input-container" class="button-container position-absolute abs-b-20">		
						<label for="file-input" class="round-button w-shadow-2"></label>
						<input type="file" class="display-none" id="file-input" accept="image/*,video/*" />
					</div>
				</div>

				<!-- Legenda -->
				<div class="large-12 medium-12 small-12 cm-pad-20-t">
					<div class="w-shadow w-rounded-15">
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Legenda</div>
								<textarea class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 100px" id="lg" name="lg" placeholder="Escreva algo para sua legenda..."></textarea>
							</div>
						</div>
					</div>
				</div>

				<!-- Botão Enviar -->
				<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-0-h">			
				  <div class="w-shadow w-rounded-15">					
					<div onclick="downloadVideoWithEdits()" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
					  <div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
						<span class="fa-stack orange" style="vertical-align: middle;">
						  <i class="fas fa-circle fa-stack-2x light-gray"></i>
						  <i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>					
						</span>
						Enviar
					  </div>										
					</div>
				  </div>
				</div>
				<?php 				
				//Scripts
				include('posts.php');				
				//FOTOS DO ÁLBUM
				}elseif($_GET['qt'] == 2){
					?>
					<div class="large-12 medium-12 small-12 text-center gray">
						<h2>Adicionar Foto</h2>	
					</div>
					<p class="error"></p>					
					<div class="cm-mg-20-t cm-mg-10-b w-square centered overflow-hidden w-rounded-25 " style="height: 300px; width: 300px; border: 2px dashed #ccc; ">										
						<label for="imageUp">
							<div id="drop-area" class="position-relative centered overflow-hidden large-12 medium-12 small-12 height-100 w-bkg-wh-to-gr pointer">
								<div class="preview-container" style="display: flex; overflow-y: hidden; overflow-x: scroll; scroll-snap-type: x mandatory;" >									
								</div>
							</div>					
						</label>
					</div>
					<input id="imageUp" onchange="previewFiles(this.files)" class="display-none" type="file" multiple>
					<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
						<div class="w-shadow w-rounded-15">
							<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
								<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">
									<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Legenda</div>
									<textarea class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 100px" id="lg" name="lg" placeholder="Escreva algo..."></textarea>
								</div>
							</div>
						</div>
					</div>					
					<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">			
						<div class="w-shadow w-rounded-15">					
							<div 
							onclick="sendPost('<?php  echo $_GET['qt']; ?>', '<?php  echo $getURL; ?>', 6, '<?php  echo $sideline; ?>', btoa(encodeURIComponent(document.getElementById('drop-area').innerHTML)))" 
							class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
								<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
									<span class="fa-stack orange" style="vertical-align: middle;">
										<i class="fas fa-circle fa-stack-2x light-gray"></i>
										<i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>					
									</span>						
									Enviar
								</div>										
							</div>
						</div>
					</div>
					<?php 
				//LINK DE NOTÍCIA	
				}elseif ($_GET['qt'] == 3) {
					?>
					<div class="large-12 medium-12 small-12 text-center gray">
						<h2>Novo Link de Notícia</h2>
					</div>
					<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">
						<div class="w-shadow w-rounded-15">
							<div class="large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis background-white w-rounded-15-t">
								<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">
									<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-5-l">Link da Notícia</div>
									<input class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" id="url" name="url" placeholder="https://www.workz.com.br/noticia-exemplo" value="">
								</div>
							</div>
							<div class="pointer large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis w-bkg-wh-to-gr w-rounded-15-b">
								<div onclick="var url = document.getElementById('url').value; goTo('partes/resources/modal_content/editor.php', 'config', 3, url<?php  if($getURL <> "") { ?> + <?php  echo "'&" . $getURL . "'"; } ?>);" class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
									<span class="fa-stack orange" style="vertical-align: middle;">
										<i class="fas fa-circle fa-stack-2x light-gray"></i>
										<i class="fas fa-arrow-up fa-stack-1x fa-inverse dark"></i>
									</span>
									Carregar Notícia
								</div>
							</div>
						</div>
					</div>
					<?php 
					if (isset($_GET['vr']) && $_GET['vr'] !== '') {
						$url = htmlspecialchars($_GET['vr']);
						// Verificação de Resposta HTTP
						$headers = get_headers($url);
						if (strpos($headers[0], '200') === false) {
							echo '<p class="gray text-center cm-pad-15 error-message">Erro ao acessar a URL: ' . htmlspecialchars($headers[0]) . '</p>';
							exit;
						}
						libxml_use_internal_errors(true);
						try {
							$content = @file_get_contents($url);
							if ($content === false) {
								throw new Exception("Erro ao acessar a URL");
							}
							if (empty($content)) {
								echo 'O conteúdo obtido da URL está vazio.';
								exit;
							}
							$dom = new DomDocument();
							$dom->loadHTML($content);
							$xpath = new domxpath($dom);
							$metaTags = [
								'title' => "//meta[@property='og:title' or @name='twitter:title']",
								'image' => "//meta[@property='og:image' or @name='twitter:image']",
								'description' => "//meta[@property='og:description' or @name='twitter:description']",
								'site_name' => "//meta[@property='og:site_name' or @name='twitter:site']",
							];
							$result = [];
							foreach ($metaTags as $key => $query) {
								$nodeList = $xpath->query($query);
								
								if($nodeList->length > 0){
									
									$texto = $nodeList[0]->getAttribute('content');
									
									// Detecta o encoding original
									$encoding = mb_detect_encoding($texto, mb_list_encodings(), true);
									
									// Corrigir o texto para UTF-8
									if ($encoding !== 'UTF-8') {
										$texto = iconv('UTF-8', $encoding.'//IGNORE', $texto);
									}
									
									// Remove caracteres inválidos
									$texto = preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $texto);
									
									// Converte para UTF-8
									$textoCorrigido = mb_convert_encoding($texto, 'UTF-8', $encoding);									
									
									$result[$key] = $textoCorrigido;
								}else{
									$result[$key] = null;
								}
								
							}
							// Obter nome da fonte caso não esteja presente
							if (!$result['site_name']) {
								$result['site_name'] = parse_url($url, PHP_URL_HOST);
							}
							// Tratar imagem
							$imageUrl = $result['image'] ?: 'https://workz.com.br/images/no-image.jpg';
							
							$postData = [
								'type' => 'embed_link',
								'path' => $url,
							];

							// Adiciona os itens do array `$result` ao array `$postData`
							foreach ($result as $key => $content) {
								$postData[$key] = $content;
							}
							
							// Codifica o array em JSON, comprime e converte para base64
							$post = base64_encode(json_encode($postData, JSON_UNESCAPED_UNICODE));							
							?>
							<p class="gray text-center cm-pad-15">Pré-visualização</p>							
							<div class="position-relative centered" style="height: 400px; width: 300px;">
								<div id="content" class="w-square-content position-relative w-rounded-20 w-shadow-1">
									<img class="position-absolute large-12 medium-12 small-12 height-100 w-rounded-20" src="<?php  echo $imageUrl; ?>" style="object-fit: cover; object-position: center;" />
									<div class="w-rounded-20-b position-absolute abs-l-0 abs-b-0 abs-r-0 large-12 medium-12 small-12 w-color-wh-to-or overflow-y-auto" style="top: 75px; background: linear-gradient(to bottom,  rgba(0,0,0,0) 0%,rgba(0,0,0,0.5) 100%);">
										<div class="cm-pad-15 cm-pad-50-b position-absolute abs-b-0">
											<a class="w-color-wh-to-or" href="<?php  echo $url; ?>" target="_blank">
												<small class="text-ellipsis white"><?php  echo htmlspecialchars($result['site_name']); ?></small>
												<h3 class="font-weight-500 text-ellipsis-2 "><?php  echo htmlspecialchars($result['title']); ?></h3>
											</a>
										</div>
									</div>
								</div>
							</div>							
							<div class="cm-mg-20-t large-12 medium-12 small-12 cm-pad-5-b">
								<div class="w-shadow w-rounded-15">
									<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
										<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">
											<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Legenda</div>
											<textarea class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 100px" id="lg" name="lg" placeholder="Escreva algo..."><?php  echo htmlspecialchars($result['description']); ?></textarea>
										</div>
									</div>
								</div>
							</div>
							<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">
								<div class="w-shadow w-rounded-15">
									<div 										
										onclick="sendPost('<?= $_GET['qt'] ?>', '<?= $getURL ?>', 7, '<?= $sideline ?>','<?= $post ?>')" 
										class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
										<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
											<span class="fa-stack orange" style="vertical-align: middle;">
												<i class="fas fa-circle fa-stack-2x light-gray"></i>
												<i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>
											</span>
											Enviar
										</div>
									</div>
								</div>
							</div>
							<?php 
						} catch (Exception $e) {
							echo 'Erro: ' . $e->getMessage();
							exit;
						}
					}
					
				//VÍDEOS INCORPORADOS
				}elseif($_GET['qt'] == 4){
					// Função para preparar URLs de vídeo com parâmetros de controle
					function prepareVideoUrl($inputUrl) {
						$url = '';
						// Remove espaços extras e normaliza a URL
						$inputUrl = trim($inputUrl);
						if (stripos($inputUrl, 'youtube.com/watch?v=') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, 'youtube.com/watch?v=') + 20, 11);
							$url = 'https://www.youtube.com/embed/' . $videoId . '?enablejsapi=1&mute=1&rel=0&showinfo=0';
						} elseif (stripos($inputUrl, 'youtu.be/') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, 'youtu.be/') + 9, 11);
							$url = 'https://www.youtube.com/embed/' . $videoId . '?enablejsapi=1&mute=1&rel=0&showinfo=0';
						} elseif (stripos($inputUrl, 'youtube.com/shorts/') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, 'youtube.com/shorts/') + 19, 11);
							$url = 'https://www.youtube.com/embed/' . $videoId . '?enablejsapi=1&mute=1&rel=0&showinfo=0';
						} elseif (stripos($inputUrl, 'dailymotion.com/') !== false && stripos($inputUrl, '/video/') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, '/video/') + 7, 10);
							$url = 'https://www.dailymotion.com/embed/video/' . $videoId . '?api=postMessage';
						} elseif (stripos($inputUrl, 'dai.ly/') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, 'dai.ly/') + 7, 10);
							$url = 'https://www.dailymotion.com/embed/video/' . $videoId . '?api=postMessage';
						} elseif (stripos($inputUrl, 'vimeo.com/') !== false) {
							$videoId = substr($inputUrl, strpos($inputUrl, 'vimeo.com/') + 10, 9);
							$url = 'https://player.vimeo.com/video/' . $videoId . '?api=1&player_id=vimeo1';
						} elseif (stripos($inputUrl, 'canva.com/design/') !== false) {
							// Detecta URLs do Canva e converte para formato de incorporação
							$designId = substr($inputUrl, strpos($inputUrl, 'design/') + 7, 34); // Captura o ID do design
							$url = 'https://www.canva.com/design/'.$designId.'/watch?embed';
							
							
						} else {
							// URL inválida ou não suportada
							$url = null;
						}						
						return $url;
					}				
					?>
					<div class="large-12 medium-12 small-12 text-center gray">
						<h2>Novo Link de Vídeo</h2>					
					</div>
					<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">			
						<div class="w-shadow w-rounded-15">								
							<div class="large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis background-white w-rounded-15-t">
								<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
									<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-5-l">Link de vídeo</div>
									<input class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-h" style="height: 41.59px" id="url" name="url" placeholder="https://www.youtube.com/watch?v=exemplo" value=""></input>
								</div>										
							</div>
							<div class="pointer large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis w-bkg-wh-to-gr w-rounded-15-b">
								<div onclick="
									var url = document.getElementById('url').value; 
									goTo('partes/resources/modal_content/editor.php', 'config', 4, url<?php  if($getURL <> ""){?> + <?php  echo "'&".$getURL."'";} ?>);																									
									" class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
									<span class="fa-stack orange" style="vertical-align: middle;">
										<i class="fas fa-circle fa-stack-2x light-gray"></i>
										<i class="fas fa-arrow-up fa-stack-1x fa-inverse dark"></i>			
									</span>						
									Carregar vídeo
								</div>																		
							</div>							
						</div>
					</div>
					<p class="gray text-center cm-pad-15">Plataformas suportadas: YouTube, DailyMotion, Vimeo e Canva.</p>
					<a id="videoTitle"></a>
					<?php 					
					//YOUTUBE VIDEO
					if(isset($_GET['vr']) && $_GET['vr'] <> ''){
						// Obtém a URL de entrada
						if (isset($_GET['vr'])) {
							$videoUrl = prepareVideoUrl($_GET['vr']);							
							if ($videoUrl) {								
								$post = base64_encode(json_encode([								
									'type' => 'embed_video',
									'path' => htmlspecialchars($videoUrl) // Caminho público do arquivo
								]));								
								?>
								<p class="gray text-center cm-pad-15 cm-pad-0-t">Pré-visualização</p>
								<div class="large-12 medium-12 small-12 position-relative centered" style="width: 300px; height: 400px">
									<div id="content" class="w-square-content w-shadow-1 w-rounded-20 background-black">																					
										<iframe id="webPlayer" class="video-iframe w-rounded-20 large-12 medium-12 small-12 height-100 border-none position-absolute abs-t-0 abs-l-0 abs-b-0 abs-r-0" src="<?= htmlspecialchars($videoUrl) ?>" frameborder="0" allow="fullscreen; picture-in-picture" ></iframe>									
									</div>
								</div>								
								<div class="cm-mg-20-t large-12 medium-12 small-12 cm-pad-5-b">
									<div class="w-shadow w-rounded-15">								
										<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">																
											<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
												<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
													<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Legenda</div>														
													<textarea class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 100px" id="lg" name="lg" placeholder="Escreva algo..."></textarea>													
												</div>										
											</div>	
										</div>	
									</div>
								</div>								
								<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">			
									<div class="w-shadow w-rounded-15">					
										<div
										onclick="sendPost('<?= $_GET['qt'] ?>', '<?= $getURL ?>', 8, '<?= $sideline ?>','<?= $post ?>')"
										class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
											<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
												<span class="fa-stack orange" style="vertical-align: middle;">
													<i class="fas fa-circle fa-stack-2x light-gray"></i>
													<i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>					
												</span>						
												Enviar
											</div>										
										</div>
									</div>
								</div>								
								<?php 								
							} else {
								echo "URL de vídeo inválida ou não suportada.";
							}
						} else {
							echo "Nenhuma URL fornecida.";
						}					
					}					
				}elseif($_GET['qt'] == 5){
				?>
				<form action="backengine/envia_video.php" method="post" enctype="multipart/form-data">
				  <label for="video_file">Escolha o vídeo para enviar:</label>
				  <input type="file" name="video_file" id="video_file" accept="video/*" required>
				  
				  <input type="submit" value="Enviar Vídeo">
				</form>

				<?php 
				}
			}
			?>
			</div>
			<script>
			(() => {
				'use strict';

				function sendPost(qt, getURL, tp, sideline, post) {
					let tempID = parseInt(Math.random() * 99999, 10) + 1;					
					let lg = document.getElementById('lg') ? encodeURIComponent(document.getElementById('lg').value) : '';
					
					// Objeto postData com chave-valor válido
					const postData = {
						id: tempID,
						tp: tp,
						lg: lg,
						ct: post
						<?= (isset($_GET['company'])) ? ",em: '".addslashes($_GET['company'])."'" : ((isset($_GET['team'])) ? ",cm: '".addslashes($_GET['team'])."'" : '') ?>
					};

					// Verificação se a função goPost está definida
					if (typeof goPost === 'function') {
						
						goPost(
							'partes/resources/modal_content/editor.php?qt=' + encodeURIComponent(qt),
							'config',
							postData
						);
						
						waitForElm('#success_' + tempID).then((elm) => {
							if (elm) {
								
								// Limpa marcador de fim e reseta variáveis
								document.querySelectorAll('#timeline .no-more-content[data-end="1"]').forEach(el => el.remove());
								fimDaTimeline = false;
								uview = 1;

								// Recarrega a timeline
								goTo('backengine/timeline.php', 'timeline', '0', sideline);

								// Espera a timeline reaparecer para retomar o scroll
								waitForElm('#timeline').then(() => {
									timelineScroll(sideline);
								});
								
								toggleSidebar();				
							}
						});						
					} else {
						console.error('A função goPost não está definida.');
					}
				}

				// Adiciona a função ao escopo global
				window.sendPost = sendPost;
			})();

			</script>
		</div>		
		<?php 
	}		
	?>
</div>