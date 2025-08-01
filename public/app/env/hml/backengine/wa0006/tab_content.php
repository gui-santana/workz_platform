<?php
// Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
include('../../../sanitize.php');

// Inicia sessão, caso ainda não esteja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    // Funções
    include($_SERVER['DOCUMENT_ROOT'] . '/functions/search.php');
}

//Obtém informações do app
$app = json_decode($_SESSION['app'], true);
$colours = $app['cl'];

/**
 * Se existirem parâmetros via GET, renderiza a listagem de páginas do usuário (INÍCIO).
 */
if (isset($_GET) && count($_GET) > 0) {
	
	// INÍCIO - LISTA DE ARTIGOS (caso 'qt=0' ou exista 'w' na URL)
	if ((isset($_GET['qt']) && $_GET['qt'] === '0') || isset($_GET['w'])) {
		
		// Ações (se houver 'vr' com '|')
		if (isset($_GET['vr']) && $_GET['vr'] !== '') {
			$vr = explode('|', $_GET['vr']);
			if($vr[0] == 'del'){
				include($_SERVER['DOCUMENT_ROOT'] . '/functions/delete.php');
				del('hnw', 'hpl', "id = '" . $vr[1] . "'");
			}elseif($vr[0] == 'snd'){
				include($_SERVER['DOCUMENT_ROOT'] . '/functions/update.php');
				$id = $vr[1];
				echo $id;
				if($st = search('hnw', 'hpl', 'st', "id = {$id}")[0]['st']){
					if(update('hnw', 'hpl', "st = '{$st}', dt = '{$now}'", "id = '{$id}'")){
					?>
					<script>
					(function(){
						'use strict';
						console.log(<?= $id ?>);
					})();
					</script>
					<?php
					}
				}			
			}			
		}

		//Empresas e equipes cujo usuário é moderador
		include('../../common/getUserAccessibleEntities.php');
		$userData = getUserAccessibleEntities($_SESSION['wz']);
		$companies_manager = $userData['companies_manager'];
		$teams_manager = $userData['teams_manager'];

		// Monta string de busca para artigos do usuário ou das páginas em que é moderador
		$search_post = "(us = '" . $_SESSION['wz'] . "'";
		foreach ($teams_manager as $team) {
			$search_post .= " OR cm = '" . $team . "'";
		}
		foreach ($companies_manager as $company) {
			$search_post .= " OR em = '" . $company . "'";
		}
		// Filtra apenas tipos de publicação editáveis (Documentos - 1 e Páginas da Web - 5)
		$search_post .= ") AND (tp = '1' OR tp = '5') ORDER BY dc DESC";

		// Busca pelas publicações		
		if ($myPosts = search('hnw', 'hpl', 'tp,tt,dt,dc,id,st,us', $search_post)) {
		?>
		<div class="row w-shadow-1 overflow-x-auto cm-mg-30-t background-gray cm-pad-30-t w-rounded-15">					
			<div class="large-12 medium-12 small-12 gray cm-pad-30-h">
				<h2>Minhas Páginas</h2>	
			</div>
			<div class="cm-mg-15-t cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative text-ellipsis border-b-input overflow">
				<div class="float-left large-4 medium-4 small-4 text-ellipsis font-weight-600 cm-pad-20-r">Nome</div>
				<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-600 cm-pad-20-r">Última modificação</div>
				<div class="float-left large-3 medium-3 small-3 text-ellipsis font-weight-600 cm-pad-20-r">Tipo</div>
				<div class="float-left large-2 medium-2 small-2 text-ellipsis font-weight-600 cm-pad-20-r">Data de publicação</div>
				<div class="clear"></div>
			</div>
			<?php
			$tipos = [
				1 => "Documento",
				5 => "Página da Web"    
			];
			foreach ($myPosts as $myPostsResult) {						
			?>
			<label title="<?= $myPostsResult['tt']; ?> - Clique para Editar" onclick="<?php if($myPostsResult['tp'] == 1){ ?> goPost('core/backengine/wa0006/tab_content.php', 'tab', <?= $myPostsResult['id'] ?>, '') <?php }else{ ?> goPost('core/backengine/wa0006/tab_content.php', 'tab', '<?= $myPostsResult['id']; ?>', '') <?php } ?>" class="pointer">
				<div class="cm-pad-10 cm-pad-30-h large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or w-bkg-tr-gray border-b-input">
					<div class="float-left large-4 medium-4 small-4 text-ellipsis font-weight-400 cm-pad-20-r">
						<?= $myPostsResult['tt']; ?><?= ($myPostsResult['us'] !== $_SESSION['wz']) ? '<i class="fas fa-people-arrows cm-mg-10-l"></i>' : '' ?>
					</div>
					<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r">
						<?= (!empty($myPostsResult['dc'])) ? date('d/m/Y', strtotime($myPostsResult['dc'])) : 'Data indefinida' ?>						
					</div>
					<div class="float-left large-3 medium-3 small-3 text-ellipsis cm-pad-20-r">
						<?= $tipos[$myPostsResult['tp']] ?>
					</div>
					<div class="float-left large-2 medium-2 small-2 text-ellipsis cm-pad-20-r">
						<?= ($myPostsResult['dt'] != 0 && $myPostsResult['st'] > 0) ? date('d/m/Y', strtotime($myPostsResult['dt'])) : 'Não publicado' ?>						
					</div>
					<div class="clear"></div>
				</div>
			</label>
			<?php							
			}
			?>			
		</div>                   
		<?php 
		} else {
		?>
		<div class="border-t-input large-12 medium-12 small-12 text-center cm-pad-30 cm-mg-10-t">
			<img src="https://workz.com.br/images/sad.png" /><br />
			<p class="font-weight-600 cm-mg-20-t">
				Oh não! Você ainda não criou nenhum artigo.
			</p>
		</div>
		<?php 
		}				
	}
	
/**
 * Se existirem parâmetros via POST, renderiza o editor de criação/edição de páginas (EDITOR).
 */
} elseif (isset($_POST['vr'])) {
	
    // Parâmetros comuns a todos os editores
	
    $postExists = 0;
    // Verifica se 'vr' é ID numérico ou JSON com dados
    if (is_numeric($_POST['vr']) == 1) {
        $myPost = search('hnw', 'hpl', '', "id = '" . $_POST['vr'] . "'")[0];
        $id = $myPost['id'];
        $tt = $myPost['tt'];
        $st = $myPost['st'];
        $dt = $myPost['dt'];
        $dc = $myPost['dc'];
        $ct = $myPost['ct'];
		$tp = $myPost['tp'];
        $postExists = 1;
    } else {
        // via JSON
        $vr = json_decode($_POST['vr'], true);
        if (array_key_exists('id', $vr)) {
            include($_SERVER['DOCUMENT_ROOT'] . '/functions/update.php');
            $myPost = search('hnw', 'hpl', '', "id = '" . $vr['id'] . "'")[0];
            $id = $myPost['id'];
            $tt = $myPost['tt'];
            $st = $myPost['st'];
            $dt = $myPost['dt'];
            $dc = $myPost['dc'];
            $ct = $myPost['ct'];
			$tp = $myPost['tp'];
            $postExists = 1;
        }
    }	
	setlocale(LC_TIME, 'pt_BR.utf-8');
	?>		
	<div id="result"></div>
	<script>
	(function(){
		
		const customHeader = document.getElementById("appHeader");
		
		function appHeader() {
			const headerHTML = 	'<div class="height-100 display-center-general-container large-12 medium-12 small-12">'+
									'<div class="line-height-a cm-pad-15-r large-6 medium-6 small-6 text-ellipsis"><p class="cm-mg-minus-5-b cm-pad-0"><?= $tt ?></p><small class="cm-mg-0 cm-pad-0"><?= ($dt > 0 && $st == 1) ? 'Publicado em ' .mb_convert_case(strftime('%e de %B de %Y, às %H:%M', strtotime($dt)), MB_CASE_LOWER, 'UTF-8') : '' ?><?= ($dc > 0) ? 'Atualizado em <span id="refreshDate">' .mb_convert_case(strftime('%e de %B de %Y, às %H:%M', strtotime($dc)), MB_CASE_LOWER, 'UTF-8') : '' ?></small></div>'+
									'<div class="large-6 medium-6 small-6">'+
										'<div class="float-right">'+
										'<a href="https://workz.com.br/?p=<?= $id; ?>" target="_blank">'+
											'<span onclick="" class="fa-stack pointer w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Ver publicação">'+
												'<i class="fas fa-circle fa-stack-2x">'+'</i>'+
												'<i class="fas fa-external-link-alt fa-stack-1x dark">'+'</i>'+
											'</span>'+
										'</a>'+
										'<span onclick="delPage()" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Excluir">'+
											'<i class="fas fa-circle fa-stack-2x">'+'</i>'+
											'<i class="fas fa-trash fa-stack-1x dark">'+'</i>'+
										'</span>'+
										'<span onclick="publishPage()" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="<?= ($postExists == 1 && $st == 1) ? 'Despublicar' : 'Publicar' ?>">'+
											'<i class="fas fa-circle fa-stack-2x">'+'</i>'+
											'<i id="sendIcon" class="fas <?= ($postExists == 1 && $st == 1) ? 'fa-eye-slash' : 'fa-eye' ?> fa-stack-1x dark">'+'</i>'+
										'</span>'+
										'<span onclick="toggleSidebar(); editPage(`<?= $id; ?>`,`<?= $tp ?>`)" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Editar informações do Artigo">'+
											'<i class="fas fa-circle fa-stack-2x">'+'</i>'+
											'<i class="fas fa-info fa-stack-1x dark">'+'</i>'+
										'</span>'+
										'<span onclick="mainMenu()" class="fa-stack w-color-gr-to-gr pointer" style="vertical-align: middle;" title="Início">'+
											'<i class="fas fa-circle fa-stack-2x">'+'</i>'+
											'<i class="fas fa-arrow-left fa-stack-1x dark">'+'</i>'+
										'</span>'+
										'</div>'+
										'<div class="clear"></div>'+
									'</div>'+
								'</div>';
			
			customHeader.classList.remove("display-none");
			customHeader.innerHTML = headerHTML;					
		}
		window.appHeader = appHeader;
		
		function delPage(){
			sAlert(function(){
				closeInstance();
				goTo('core/backengine/wa0006/tab_content.php', 'tab', '0', 'del|<?= $id;?>');
				customHeader.innerHTML = '';
				customHeader.classList.add("display-none");
			}, "Esta ação excluirá permanentemente esta página.", "Página excluída com sucesso!", "Ação cancelada.");				
		}
		window.delPage = delPage;
		
		function editPage(id,tp){			
			goTo('core/backengine/wa0006/config.php', 'config', tp, id);			
		}
		window.editPage = editPage;
		
		function publishPage() {			
			var st = '<?= $st ?>';
			var vr = {};
			vr['id'] = '<?= $id ?>';
			if(st == '0'){				
				vr['st'] = '1';
			} else {
				vr['st'] = '0';
			}

			// Corrige a interpolação de strings
			var action = (st == '1') ? 'des' : '';
			var successMessage = "Página " + action + "publicada com sucesso!";
			var confirmationMessage = "Esta ação " + action + "publicará esta página.";

			sAlert(function() {				
				goPost('core/backengine/wa0006/save.php', 'result', vr, '');							
			}, confirmationMessage, successMessage, "Ação cancelada.");					
		}
		window.publishPage = publishPage;
		
		appHeader();
		
	})();
	</script>
	<?php
	
	// Parâmetros para páginas do tipo "Documento"
	
	if($tp == 1){
		//SUNEDITOR
		?>			
		<link href="core/backengine/wa0006/suneditor/dist/css/suneditor.min.css" rel="stylesheet">		
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.49.0/lib/codemirror.min.css">		
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.11.1/dist/katex.min.css">
		<link rel="stylesheet" href="app/core/backengine/wa0006/suneditor/dist/css/se_viewer.css" />
				
        <div style="top: 0px" class="position-absolute abs-b-0 abs-l-0 abs-r-0 large-12 medium-12 small-12 z-index-0">
            <textarea id="textBox">
                <?php
                if ($postExists == 1) {
                    echo bzdecompress(base64_decode($ct));
                }
                ?>
            </textarea>
        </div>
		<script>
		(function() {
			'use strict';					
			
			const customHeader = document.getElementById("appHeader");
			let editorInstance = null;
			let editorLoaded = false;
			
			function mainMenu(){
				closeInstance(); 
				goTo('core/backengine/wa0006/tab_content.php', 'tab', '0', '');
				customHeader.innerHTML = '';
				customHeader.classList.add("display-none");
			}
			window.mainMenu = mainMenu;
						
			var st = '<?= $st ?>';
			var id = '<?= $id ?>';
			
			function loadEditor(id, st){
				closeInstance();

				editorInstance = SUNEDITOR.create('textBox', {
					display: 'block',
					width: '100%',		
					height: 'auto',
					popupDisplay: 'full',
					charCounter: true,
					charCounterLabel: 'Caracteres :',			
					buttonList: [
						// default
						['undo', 'redo'],
						['font', 'fontSize', 'formatBlock'],
						['paragraphStyle', 'blockquote'],
						['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
						['fontColor', 'hiliteColor', 'textStyle'],
						['removeFormat'],
						['outdent', 'indent'],
						['align', 'horizontalRule', 'list', 'lineHeight'],
						['table', 'link', 'image', 'video', 'audio', 'math'],				
						['showBlocks', 'codeView'],
						['preview', 'print'],
						['save', 'template'],
						
						// (min-width: 1546)
						['%1546', [
							['undo', 'redo'],
							['font', 'fontSize', 'formatBlock'],
							['paragraphStyle', 'blockquote'],
							['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
							['fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							['table', 'link', 'image', 'video', 'audio', 'math'],					
							['showBlocks', 'codeView'],
							[':i-More Misc-default.more_vertical', 'preview', 'print', 'save', 'template']
						]],
						// (min-width: 1455)
						['%1455', [
							['undo', 'redo'],
							['font', 'fontSize', 'formatBlock'],
							['paragraphStyle', 'blockquote'],
							['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
							['fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							['table', 'link', 'image', 'video', 'audio', 'math'],					
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template']
						]],
						// (min-width: 1326)
						['%1326', [
							['undo', 'redo'],
							['font', 'fontSize', 'formatBlock'],
							['paragraphStyle', 'blockquote'],
							['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
							['fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math']
						]],
						// (min-width: 1123)
						['%1123', [
							['undo', 'redo'],
							[':p-More Paragraph-default.more_paragraph', 'font', 'fontSize', 'formatBlock', 'paragraphStyle', 'blockquote'],
							['bold', 'underline', 'italic', 'strike', 'subscript', 'superscript'],
							['fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math']
						]],
						// (min-width: 817)
						['%817', [
							['undo', 'redo'],
							[':p-More Paragraph-default.more_paragraph', 'font', 'fontSize', 'formatBlock', 'paragraphStyle', 'blockquote'],
							['bold', 'underline', 'italic', 'strike'],
							[':t-More Text-default.more_text', 'subscript', 'superscript', 'fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math']
						]],
						// (min-width: 673)
						['%673', [
							['undo', 'redo'],
							[':p-More Paragraph-default.more_paragraph', 'font', 'fontSize', 'formatBlock', 'paragraphStyle', 'blockquote'],
							[':t-More Text-default.more_text', 'bold', 'underline', 'italic', 'strike', 'subscript', 'superscript', 'fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							['align', 'horizontalRule', 'list', 'lineHeight'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template']
						]],
						// (min-width: 525)
						['%525', [
							['undo', 'redo'],
							[':p-More Paragraph-default.more_paragraph', 'font', 'fontSize', 'formatBlock', 'paragraphStyle', 'blockquote'],
							[':t-More Text-default.more_text', 'bold', 'underline', 'italic', 'strike', 'subscript', 'superscript', 'fontColor', 'hiliteColor', 'textStyle'],
							['removeFormat'],
							['outdent', 'indent'],
							[':e-More Line-default.more_horizontal', 'align', 'horizontalRule', 'list', 'lineHeight'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template']
						]],
						// (min-width: 420)
						['%420', [
							['undo', 'redo'],
							[':p-More Paragraph-default.more_paragraph', 'font', 'fontSize', 'formatBlock', 'paragraphStyle', 'blockquote'],
							[':t-More Text-default.more_text', 'bold', 'underline', 'italic', 'strike', 'subscript', 'superscript', 'fontColor', 'hiliteColor', 'textStyle', 'removeFormat'],
							[':e-More Line-default.more_horizontal', 'outdent', 'indent', 'align', 'horizontalRule', 'list', 'lineHeight'],
							[':r-More Rich-default.more_plus', 'table', 'link', 'image', 'video', 'audio', 'math'],
							[':i-More Misc-default.more_vertical', 'showBlocks', 'codeView', 'preview', 'print', 'save', 'template']
						]]
						
					],
					//placeholder: 'Comece escrevendo algo surpreendente...',
					lang: SUNEDITOR_LANG.pt_br,
					
					//addTagsWhitelist: '*',		
					printTemplate: "<div class='cm-pad-20'>{{contents}}</div>",
					previewTemplate: "<div class='cm-pad-20'>{{contents}}</div>",
					
					textTags: { bold: 'n', underline: 's', italic: 'i' },                  
					
					templates: [
						{
							name: 'Template-1',
							html: '<p>HTML source1</p>'
						},
						{
							name: 'Template-2',
							html: '<p>HTML source2</p>'
						}
					],
					codeMirror: CodeMirror,
					katex: katex
				});
				
				// Verifique se a função saveContent já foi chamada antes de adicionar o evento ao botão 'Salvar'
				if (!editorLoaded) {
					// Encontre o botão 'Salvar' pelo atributo data-command
					const saveButton = document.querySelector('[data-command="save"]');
					// Adiciona um ouvinte de evento ao botão 'Salvar' para chamar a função de salvar com o ID
					if (saveButton) {
						saveButton.addEventListener('click', () => {					
							saveContent(id);
						});
					} else {
						console.error("Botão 'Salvar' não encontrado.");
					}
					editorLoaded = true; // Define a flag para indicar que o editor foi carregado e os eventos foram configurados
				}
				
				// Função para salvar o conteúdo
				function saveContent(id){
					const content = editorInstance.getContents(); // Obtém o conteúdo do editor			
					var vr = {}; 
					vr['id'] = id; // Agora, o ID está disponível
					vr['ct'] = content;
					
					// Configuração da requisição Ajax
					fetch('core/backengine/wa0006/save.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams(vr).toString(),
					})
					.then(response => response.text())
					.then(data => {				
						// Obtenha a data atual
						const currentDate = new Date();
						// Opções de formatação para o Intl.DateTimeFormat
						const options = { day: 'numeric', month: 'long', year: 'numeric', hour: 'numeric', minute: 'numeric' };
						// Crie uma instância de Intl.DateTimeFormat com as opções
						const dateFormatter = new Intl.DateTimeFormat('pt-BR', options);
						// Formate a data
						const formattedDate = dateFormatter.format(currentDate);
						document.getElementById('refreshDate').innerText = formattedDate;
					})
					.catch(error => {
						console.error('Erro ao enviar a requisição:', error);
					});
				}							
			}
			window.loadEditor = loadEditor;

			function loadSunEditor(id,st){				
				waitForElm('#textBox').then((elm) => {
					loadEditor(id,st);
				});
			}
			window.loadSunEditor = loadSunEditor;
				
			// Função para fechar a instância do editor
			function closeInstance() {
				if (editorInstance) {
					editorInstance.destroy(); // Fecha o editor corretamente
					editorInstance = null;
					editorLoaded = false;
				}
			}
			window.closeInstance = closeInstance;
			
			loadSunEditor(id,st);
			
		})();
		</script>
		<?php
		
	// Parâmetros para páginas do tipo "Página da Web"
	
	}elseif($tp == 5){
		
		$data = json_decode(urldecode($ct), true);
		if (!empty($data) && !$data) {
			die(json_encode(["message" => "Erro ao decodificar JSON"]));
		}
		
		$htmlContent = '';
		$cssContent = '';
		if(!empty($data)){
			$htmlContent = htmlspecialchars_decode($data["html"], ENT_QUOTES);
			$cssContent = htmlspecialchars_decode($data["css"], ENT_QUOTES);
		}		
		?>
		<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.22.2/dist/css/grapes.min.css">
		<style>
		:root{
			--gjs-primary-color: #d1d5da !important;
			--gjs-secondary-color: #444 !important;
			--gjs-main-font: Ubuntu, sans-serif;
			--gjs-font-size: 0.9em;
			--gjs-quaternary-color: #103f91;
			--gjs-color-warn: #f2571b;
		}
		.gjs-pn-panel{
			z-index: 1 !important;
		}
		.gjs-pn-views{
			z-index: 2 !important;
		}
		.gjs-pn-btn{
			border-radius: 4px;			
		}
		.gjs-pn-btn:hover{
			background-color: #d1d1d1;
		}
		</style>
		<div id="gjs-response" class=""></div>
		<div id="gjs" class="height-100"></div>		
		<script>
		(function () {
			
			'use strict';
			
			let editor = null;
			
			editor = grapesjs.init({
				container: '#gjs',
				height: '100vh',
				fromElement: true,
				storageManager: { autoload: false },
				plugins: [
					'grapesjs-preset-webpage',
					'gjs-blocks-basic',
					'grapesjs-plugin-forms',
					'grapesjs-plugin-export',
					'grapesjs-tabs',
					'grapesjs-custom-code',
					'grapesjs-tooltip',
					'grapesjs-typed',
					'grapesjs-style-bg',
				],
				pluginsOpts: {
					'grapesjs-style-bg': {
						defaultBgColor: '#FFFFFF',
						defaultTextColor: '#000000',
					},
					'grapesjs-tui-image-editor': {
						config: {
							includeUI: {
								loadImage: {
									path: '',
									name: 'SampleImage',
								},
								menuBarPosition: 'bottom',
							},
							cssMaxWidth: 700,
							cssMaxHeight: 500,
							usageStatistics: false,
						},
						onSave: (imageData) => {
							console.log('Imagem salva:', imageData);
						},
					},
				},
				noticeOnUnload: false, // Desativa mensagens de telemetria
			});
			
			// Carrega o conteúdo salvo
			editor.setComponents(`<?= addslashes($htmlContent) ?>`);
			editor.setStyle(`<?= addslashes($cssContent) ?>`);
			
			//Criar um Bloco de Botão "Salvar"
			editor.BlockManager.add('save-button', {
			  label: 'Salvar',
			  category: 'Básico',
			  content: {
				tagName: 'button',
				attributes: { class: 'btn-save' },
				content: 'Salvar',
				draggable: true
			  }
			});
			
			//Adicionar a Lógica de Salvamento
			editor.on('load', () => {
			  const saveButton = document.createElement('button');
			  saveButton.innerHTML = 'Salvar';
			  saveButton.className = 'gjs-btn-save';
			  
			  // Adicionar ao painel superior
			  const panel = editor.Panels.getPanel('options');
			  if (panel) {
				panel.get('buttons').add({
				  id: 'save',
				  className: 'btn-save',
				  label: '<div style="height: 22px; width: 22px; padding: 2px"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 15.74 15.74"><g><path d="M18.53,19.5l.2-.05A1.78,1.78,0,0,0,20.13,18l0-.09V7.14a2,2,0,0,0-.28-.64A3.18,3.18,0,0,0,19.43,6c-.5-.52-1-1-1.55-1.54A2.59,2.59,0,0,0,17.37,4a1.83,1.83,0,0,0-.61-.25H6l-.21,0a1.78,1.78,0,0,0-1.4,1.49l0,.1V17.87a2.49,2.49,0,0,0,.09.37,1.79,1.79,0,0,0,1.44,1.23l.09,0Zm-6.25-.6H6.92a.61.61,0,0,1-.68-.48.78.78,0,0,1,0-.22V12.3a.62.62,0,0,1,.69-.68H17.64a.62.62,0,0,1,.69.69V18.2a.64.64,0,0,1-.71.69H12.28ZM12,9.81H8.15a.63.63,0,0,1-.72-.71v-4a.64.64,0,0,1,.72-.72h7.66a.64.64,0,0,1,.72.72v4a.65.65,0,0,1-.74.72ZM13.5,5V9.18h1.78V5Z" transform="translate(-4.41 -3.76)"></path></g></svg></div>',
				  command: 'save-data',
				  attributes: { title: 'Salvar Projeto' }
				});
			  }
			});

			// Criar comando de salvamento
			editor.Commands.add('save-data', {
			  run(editor) {				  
				const html = editor.getHtml();
				const css = editor.getCss();
				const data = encodeURIComponent(JSON.stringify({ html: html, css: css }));																				
				var vr = {};
				vr['id'] = '<?= $id ?>';
				vr['ct'] = data;				
				goPost('core/backengine/wa0006/save.php', 'result', vr, '');	
			  }
			});
			
			// Tradução para português
			editor.I18n.addMessages({
				'pt-br': {
					assetManager: {
						addButton: 'Adicionar imagem',
						inputPlh: 'URL da imagem',
						modalTitle: 'Selecionar Imagem',
						uploadTitle: 'Arraste arquivos ou clique para enviar'
					},
					blockManager: {
						labels: {
							section: 'Seção',
							text: 'Texto',
							image: 'Imagem',
							video: 'Vídeo',
							link: 'Link',
							button: 'Botão'
						}
					},
					selectorManager: {
						label: 'Classes',
						selected: 'Selecionado',
						state: 'Estado',
						states: {
							hover: 'Ao passar o mouse',
							active: 'Quando clicado',
							'nth-of-type': 'Elemento específico'
						}
					},
					styleManager: {
						empty: 'Selecione um elemento para editar o estilo',
						properties: {
							'background-color': 'Cor de fundo',
							width: 'Largura',
							height: 'Altura',
							'font-size': 'Tamanho da fonte',
							'font-family': 'Fonte',
							'color': 'Cor do texto',
							'margin-top': 'Margem superior',
							'margin-bottom': 'Margem inferior',
							'margin-left': 'Margem esquerda',
							'margin-right': 'Margem direita'
						}
					},
					traitManager: {
						label: 'Configurações',
						empty: 'Selecione um elemento para modificar',
					},
					componentManager: {
						remove: 'Remover',
						copy: 'Copiar',
						move: 'Mover'
					},
					panelManager: {
						buttons: {
							undo: 'Desfazer',
							redo: 'Refazer',
							export: 'Exportar Código',
							preview: 'Visualizar',
							fullscreen: 'Tela cheia',
							close: 'Fechar'
						}
					},
					commands: {
						preview: 'Visualização',
						save: 'Salvar',
						clear: 'Limpar Página',
						'sw-visibility': 'Alternar visibilidade',
						'fullscreen': 'Tela Cheia'
					},
					deviceManager: {
						devices: {
							desktop: 'Desktop',
							tablet: 'Tablet',
							mobileLandscape: 'Celular (Paisagem)',
							mobilePortrait: 'Celular (Retrato)'
						}
					},
					modal: {
						titleExport: 'Exportar Código',
						labelHtml: 'Código HTML',
						labelCss: 'Código CSS',
						btnDownload: 'Baixar Código',
						btnClose: 'Fechar'
					}
				}
			});

			// Ativa o idioma português
			editor.I18n.setLocale('pt-br');

			// Mensagens de sucesso e erro
			editor.on('load', () => {
				console.log("Editor carregado com sucesso!");
			});

			editor.on('error', (err) => {
				console.error("Erro no editor:", err);
			});
			
			const customHeader = document.getElementById("appHeader");
			
			function mainMenu(){
				closeInstance(); 
				goTo('core/backengine/wa0006/tab_content.php', 'tab', '0', '');
				customHeader.innerHTML = '';
				customHeader.classList.add("display-none");
			}
			window.mainMenu = mainMenu;
			
			// Função para fechar a instância do editor
			function closeInstance() {
				if (editor) {
					editor.destroy(); // Fecha o editor corretamente
					editor = null;
					editor = false;
				}
			}
			window.closeInstance = closeInstance;
				
		})();			
		</script>
		<?php
	}
	?>
	<div class="clear"></div>	
	<?php
}
?>
