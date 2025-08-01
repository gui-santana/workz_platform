<?php
session_start();

if(isset($_GET['p'])){
	
	//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
	include('../../../sanitize.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
	
	$access = 0;
	if($p = search('hnw', 'hpl', ''. "id = '{$_GET['p']}'")[0]){
		
		if($p['cm'] !== 0){
			if($team = search('cmp', 'teams_users', '', "cm = '{$p['cm']}' AND us = '{$_SESSION['wz']}' AND st = 1")){
				$access = 1;
			}
		}elseif($p['em'] !== 0){
			if($company = search('cmp', 'employees', '', "em = '{$p['em']}' AND us = '{$_SESSION['wz']}' AND st = 1")){
				$access = 1;
			}
		}elseif($p['us'] == $_SESSION['wz']){
			$access = 1;			
		}
	
		if($access == 1){			
			$data = json_decode(urldecode($p['ct']), true);
			if (!$data) {
				die(json_encode(["message" => "Erro ao decodificar JSON"]));
			}
			$htmlContent = htmlspecialchars($data["html"] ?? '');
			$cssContent = htmlspecialchars($data["css"] ?? ''); 			
		?>
		<!DOCTYPE html>
		<html lang="pt-br">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>GrapesJS Demo</title>

			<!-- Stylesheets -->
			<link rel="stylesheet" href="https://unpkg.com/grapesjs@0.22.2/dist/css/grapes.min.css">
			<link rel="stylesheet" href="https://unpkg.com/tui-color-picker/dist/tui-color-picker.min.css">
			<link rel="stylesheet" href="https://unpkg.com/tui-image-editor/dist/tui-image-editor.min.css">
			<style>
				body{
					padding: 0;
					margin: 0;
				}
				.editor-container{
					padding: 0;
					margin: 0;
					color: white;
				}
			</style>
		</head>
		<body>
			<div class="editor-container">
				<button id="saveData">Salvar</button>
				<button id="">Publicar</button>
				<div id="gjs-response"></div>
				<div id="gjs" style="height:100vh;"></div>
			</div>
			

			<!-- Scripts -->
			<script src="https://uicdn.toast.com/tui-code-snippet/v1.5.2/tui-code-snippet.min.js"></script>
			<script src="https://uicdn.toast.com/tui-color-picker/v2.2.7/tui-color-picker.min.js"></script>
			<script src="https://uicdn.toast.com/tui-image-editor/v3.15.2/tui-image-editor.min.js"></script>
			<script src="https://unpkg.com/grapesjs"></script>
			<script src="https://unpkg.com/grapesjs-preset-webpage"></script>
			<script src="https://unpkg.com/grapesjs-blocks-basic"></script>
			<script src="https://unpkg.com/grapesjs-plugin-forms"></script>
			<script src="https://unpkg.com/grapesjs-plugin-export"></script>
			<script src="https://unpkg.com/grapesjs-tabs"></script>
			<script src="https://unpkg.com/grapesjs-custom-code"></script>
			<script src="https://unpkg.com/grapesjs-tooltip"></script>
			<script src="https://unpkg.com/grapesjs-typed"></script>
			<script src="https://unpkg.com/grapesjs-style-bg"></script>
			<script src="https://unpkg.com/grapesjs-tui-image-editor"></script>

			<script>
				document.addEventListener("DOMContentLoaded", () => {
					const editor = grapesjs.init({
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
							'grapesjs-tui-image-editor',
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
					editor.setComponents(`<?= $htmlContent ?>`);
					editor.setStyle(`<?= $cssContent ?>`);
					
					// Botão para salvar o HTML e CSS no banco de dados
					document.getElementById('saveData').addEventListener('click', function () {
						const html = editor.getHtml();
						const css = editor.getCss();
						const data = encodeURIComponent(JSON.stringify({ html: html, css: css }));
						
						console.log(data);
						
						goPost('backengine/wa0003/funcs.php', 'gjs-response', data, 0);
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
				});
			</script>
			<script src="https://workz.com.br/js/index/goPost.js"></script>
		</body>
		</html>	
		<?php	
		}
	}
}
?>

