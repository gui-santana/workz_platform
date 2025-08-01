<?php
// Configurando o cabeçalho para permitir a exibição em frames de outro domínio
header("Access-Control-Allow-Origin: http://localhost:9090/");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // Inicia o buffer de saída

session_start();

//CHECK SERVER
$url = $_SERVER['REQUEST_URI'];
// Obter a parte do caminho do URL
$path = parse_url($url, PHP_URL_PATH);
// Dividir o caminho em segmentos separados pela barra ("/")
if(empty($_GET['valor'])){
	$segmentos = explode('/', $path);
	// Obter o valor após a barra
	$valor = $segmentos[1];
	// Atribuir o valor como uma variável GET
	$_GET['valor'] = $valor;
}
if(!empty($_GET['valor'])){
	if(isset($_GET['valor']) && !empty($_GET['valor'])){
		//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
		$documentRoot = $_SERVER['DOCUMENT_ROOT'];
		$pattern = '/public_html\/([^\/]+)/';	
		preg_match($pattern, $documentRoot, $matches);	
		$subdomainFolder = isset($matches[1]) ? $matches[1] : '';	
		$sanitizedFolder = preg_replace('/[^a-zA-Z0-9-_]/', '', $subdomainFolder);
		$currentUrl = $_SERVER['HTTP_HOST'];
		$parts = explode('.', $currentUrl);
		$subdomain = $parts[0];						
		if ($sanitizedFolder === $subdomain){
			if(strpos($documentRoot, $sanitizedFolder.'/') > 0){
				$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder.'/', '', $documentRoot);					
			}else{
				$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder, '', $documentRoot);
			}			
		}		
		require_once('../functions/search.php');
		require_once('../functions/optionsClass.php');
		$app = search('app', 'apps', '', "nm = '".$_GET['valor']."'");		
		if(count($app) > 0){
			
			
			
			// No app externo
			/*
            if (isset($_GET['auth'])) {
                
                $email = base64_decode($_GET['auth']);
                
                // Valide no seu banco de dados
                if($user = search('hnw', 'hus', 'id', "ml = '".$email."'")[0]['id']){
                    if ($user) {
                        $_SESSION['wz'] = $user;
                        ?>
                        <script>
                            console.log('Usuário encontrado. id = ' + <?= $_SESSION['wz'] ?>);
                        </script>
                        <?php
                    } else {
                        die("Usuário não autorizado.");
                        ?>
                        <script>
                            console.log('Usuário não autorizado');
                        </script>
                        <?php
                    }
                }else{
                    die("Usuário não encontrado.");
                    ?>
                    <script>
                        console.log('Usuário não encontrado');
                    </script>
                    <?php
                }
            }
			*/
			
			//Ambiente
			$environment = [
				0 => 'dev', //Desenvolvimento
				1 => 'hml', //Homologação
				2 => 'prd'	//Produção
			];
			
			$appInfo = [
				"id" => $app[0]["id"], //ID do app
				"nm" => $app[0]["nm"], //Nome abreviado
				"tt" => $app[0]["tt"], //Título do app
				"cl" => $app[0]["cl"], //Cores do app
				"ml" => $app[0]["ml"],  //E-mail do produtor
				"rl" => $app[0]["rl"],  //E-mail do produtor
				"env" => $app[0]["env"],  //Ambiente de execução
				"environment" => $environment
			];
			
			$colors = explode(';', $appInfo['cl']);
			
			$_SESSION['app'] = json_encode($appInfo, JSON_UNESCAPED_UNICODE); // Correção aqui
			date_default_timezone_set('America/Fortaleza');
			setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');															
				if($app[0]['cl'] == ''){
					$colours = ['#E9E9E9', '#FFFFFF'];
				}else{
					$colours = explode(';', $app[0]['cl']);
				}				
				$app_tt = $app[0]['tt'];
				$app_rl = $app[0]['rl'];
				?>
				<!DOCTYPE HTML>
				<html>
				<head>																				
					<meta name="HandheldFriendly" content="True">
					<meta name="MobileOptimized" content="320">
					<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, user-scalable=no, minimal-ui">
					<meta name="mobile-web-app-capable" content="yes" />
					<meta name="mobile-web-app-status-bar-style" content="<?= $appInfo['cl'] ?>"/>										
					<meta name="theme-color" content="<?= $appInfo['cl'] ?>" />
					<meta name="msapplication-navbutton-color" content="<?= $appInfo['cl'] ?>" />					
					<link rel="manifest" href="manifest.php?app=<?= $app[0]['nm'] ?>">
					<link rel="icon" href="https://app.workz.com.br/imgLogo.php?app=<?= $app[0]['nm'] ?>" type="image/x-icon" />
					<link rel="shortcut icon" href="https://app.workz.com.br/imgLogo.php?app=<?= $app[0]['nm'] ?>" type="image/x-icon" />
					<link rel="apple-touch-icon-precomposed" href="https://app.workz.com.br/imgLogo.php?app=<?= $app[0]['nm'] ?>" />
					<title><?= $app[0]['tt'] ?></title>
					<!-- Google OAuth for ProtSpot Apps -->					
					<meta name="google-signin-client_id" content="142357823160-7ppge23d63vp2urdkcofqn8fbshbhom9.apps.googleusercontent.com">
					<script src="https://apis.google.com/js/platform.js" async defer></script>												
					<!-- CSS -->
					<link href="https://workz.com.br/RequestReducedStyle.css" rel="Stylesheet" type="text/css" />
					<link href="https://workz.com.br/cm-pad.css" rel="Stylesheet" type="text/css" />
					<link href="https://workz.com.br/sizes.css" rel="Stylesheet" type="text/css" />
					<!-- FONT AWESOME -->	
					<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous"/>
					<!-- SWEET ALERT -->
					<script src="https://workz.com.br/js/sweetalert.min.js"></script>
					<!-- JQUERY -->		
					<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>					
					<script>
					$(window).load(function(){
						$('#loading').delay(500).fadeOut();
					});
					function shareContent() {
					  // Verifica se a API de compartilhamento é suportada pelo navegador
					  if (navigator.share) {
						// Dados para compartilhamento
						var shareData = {
							title: '<?= $app[0]['tt'] ?>',
							text: '<?= json_encode($app[0]['ab'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>',
							url: 'https://app.workz.com.br<?= $url ?>',
							icon: 'https://app.workz.com.br/imgLogo.php?app=<?= $app[0]['nm'] ?>'
						};					
						// Aciona o compartilhamento nativo
						navigator.share(shareData)
						  .then(() => console.log('Aplicativo compartilhado com sucesso!'))
						  .catch((error) => console.error('Erro ao compartilhar:', error));
					  } else {
						// Caso a API não seja suportada, exibe uma mensagem de erro ou oferece uma experiência alternativa de compartilhamento
						alert('o compartilhamento não é suportado neste dispositivo ou navegador.');
					  }
					}
					</script>					
					<script src="https://unpkg.com/@ungap/custom-elements-builtin"></script>					
					<style>				
						.fadeIn {
							-webkit-animation: fadeIn .75s ease-in-out;
							-moz-animation: fadeIn .75s ease-in-out;
							-o-animation: fadeIn .75s ease-in-out;
							animation: fadeIn .75s ease-in-out;
						}					
						.circle {
							width: 100%;
							height: 100%;
							background-color: <?= $colors[1] ?>;
							position: absolute;
							border-radius: 20%;
							opacity: 0;
							animation: ripple-animation 2s infinite ease-out;					
						}
						.circle1 {
							animation-delay: 0s;
						}
						.circle2 {
							animation-delay: 1s;
						}
						.circle3 {
							animation-delay: 2s;
						}																			
						.area{
							height:100vh;													 
						}																																					
						.icon-background{
							position: relative;
							overflow: hidden;							
						}
						.icon-background::before {
							content: "";
							position: absolute;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;							
							background-image: url(<?= ($app[0]['im'] == '') ? 'https://workz.com.br/images/no-image.jpg' : 'data:image/jpeg;base64,'.$app[0]['im'] ?>);
							background-size: cover;
							background-position: center;
							background-repeat: no-repeat;							
							z-index: 0; /* Coloque o background atrás do conteúdo */
						}						
											
						.background-orange{
							background: <?= $colors[0] ?>;
						}
						.orange{
							color: <?= $colors[0] ?>;
						}						
						.w-color-wh-to-or:hover{
							color: <?= $colors[0] ?>;
						}
						.w-color-or-to-wh{
							color: <?= $colors[0] ?>;
							transition: color .25s ease-in-out;
						}						
						.w-color-bl-to-or:hover{
							color: <?= $colors[0] ?>;
							fill: <?= $colors[0] ?>;
						}
						.w-color-or-to-bl{
							color: <?= $colors[0] ?>;
							transition: color .25s ease-in-out;
						}						
						.w-all-or-to-bl{
							background-color: <?= $colors[0] ?>;
							color: #FFF;
							transition: background-color 0.25s ease-in-out, color 0.25s ease-in-out;		
						}						
						.w-all-bl-to-or:hover{
							background-color: <?= $colors[0] ?>;
						}						
						.w-all-wh-to-or:hover{
							color: #FFF;
							background-color: <?= $colors[0] ?>;
						}						
						.line-through{
							text-decoration: line-through;
						}						
						.content {  
							color: black; /* Define a cor do texto */
							-webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 1) 95%, rgba(0, 0, 0, 0) 100%); /* Gradiente de transparência como máscara */
							mask-image: linear-gradient(180deg, rgba(0, 0, 0, 1) 95%, rgba(0, 0, 0, 0) 100%);
						}
						.onoffswitch-checkbox:checked + .onoffswitch-label {
							background-color: <?= $colors[0] ?>;
						}
						.onoffswitch-checkbox:checked + .onoffswitch-label, .onoffswitch-checkbox:checked + .onoffswitch-label:before {
						   border-color: <?= $colors[0] ?>;
						}
						
						@keyframes borderAnimation{
						  0%   { border: .5px solid rgba(0,0,0,0.1); }
						  50%  { border: .5px solid <?= $colors[0] ?>; }
						  100% { border: .5px solid rgba(0,0,0,0.1); }
						}
						@-o-keyframes borderAnimation{
						  0%   { border: .5px solid rgba(0,0,0,0.1); }
						  50%  { border: .5px solid <?= $colors[0] ?>; }
						  100% { border: .5px solid rgba(0,0,0,0.1); }
						}
						@-moz-keyframes borderAnimation{
						  0%   { border: .5px solid rgba(0,0,0,0.1); }
						  50%  { border: .5px solid <?= $colors[0] ?>; }
						  100% { border: .5px solid rgba(0,0,0,0.1); }
						}
						@-webkit-keyframes borderAnimation{
						  0%   { border: .5px solid rgba(0,0,0,0.1); }
						  50%  { border: .5px solid <?= $colors[0] ?>; }
						  100% { border: .5px solid rgba(0,0,0,0.1); }
						}
						@keyframes colorAnimation{
						  0%   { color:#FFF; }
						  50%  { color:<?= $colors[0] ?>; }
						  100% { color:#FFF; }
						}
						@-o-keyframes colorAnimation{
						  0%   { color:#FFF; }
						  50%  { color:<?= $colors[0] ?>; }
						  100% { color:#FFF; }
						}
						@-moz-keyframes colorAnimation{
						  0%   { color:#FFF; }
						  50%  { color:<?= $colors[0] ?>; }
						  100% { color:#FFF; }
						}
						@-webkit-keyframes colorAnimation{
						  0%   { color:#FFF; }
						  50%  { color:<?= $colors[0] ?>; }
						  100% { color:#FFF; }
						}
						@keyframes ripple-animation {
							0% {
								transform: scale(0.1);
								opacity: 1;
							}
							100% {
								transform: scale(1);
								opacity: 0;
							}
						}	
						@-webkit-keyframes fadeIn {
							0% { opacity: 0; }
							100% { opacity: 1; } 
						}
						@-moz-keyframes fadeIn {
							0% { opacity: 0;}
							100% { opacity: 1; }
						}
						@-o-keyframes fadeIn {
							0% { opacity: 0; }
							100% { opacity: 1; }
						}
						@keyframes fadeIn {
							0% { opacity: 0; }
							100% { opacity: 1; }
						}	
						@keyframes animate {
							0%{
								transform: translateY(0) rotate(0deg);
								opacity: 1;
								border-radius: 10%;
							}
							100%{
								transform: translateY(-1000px) rotate(720deg);
								opacity: 0;
								border-radius: 50%;
							}
						}
						@keyframes anm-bl-3-move {
							0%, 80%, 100% {
								transform: scale(0);
							}
							40% {
								transform: scale(1);
							}
						}	
					</style>
				</head>
				<body style="background-color: <?= $colors[1] ?>;">
					<script>
					  if (typeof navigator.serviceWorker !== 'undefined') {
						navigator.serviceWorker.register('sw.js')
					  }
					</script>
					<!-- LOADING -->
					<div id="loading" class="clearfix">
						<div id="loading-image" class="large-12 medium-12 small-12 height-100 display-center-general-container" style="background-color: <?= $appInfo['cl'] ?>">
							<div class="w-square position-relative display-center-general-container fadeIn" style="height: 300px; width: 300px; margin: 0 auto;">
								<div class="circle circle1"></div>
								<div class="circle circle2"></div>
								<div class="circle circle3"></div>
								<div class="w-square w-rounded-30 position-relative" style="height: 150px; width: 150px; margin: 0 auto;">
									<div class="w-square-content w-modal-shadow icon-background w-rounded-30"></div>
								</div>
							</div>
						</div>
						Carregando...						
					</div>															
					<!-- APP CONTENT -->
					<div class="clearfix position-absolute abs-t-0 abs-r-0 abs-b-0 abs-l-0">
						<?php
						//ANIMAÇÃO DE CÍRCULOS
						if($app[0]['ct'] == 0){
						?>
						<style>
						.circles{
							position: absolute;
							top: 0;
							left: 0;
							width: 100%;
							height: 100%;
							overflow: hidden;
						}
						.circles li{
							position: absolute;
							display: block;
							list-style: none;
							width: 20px;
							height: 20px;
							background:  <?= $colours[1] ?>40;
							animation: animate 25s linear infinite;
							bottom: -150px;
							
						}
						.circles li:nth-child(1){
							left: 25%;
							width: 80px;
							height: 80px;
							animation-delay: 0s;
						}
						.circles li:nth-child(2){
							left: 10%;
							width: 20px;
							height: 20px;
							animation-delay: 2s;
							animation-duration: 12s;
						}
						.circles li:nth-child(3){
							left: 70%;
							width: 20px;
							height: 20px;
							animation-delay: 4s;
						}
						.circles li:nth-child(4){
							left: 40%;
							width: 60px;
							height: 60px;
							animation-delay: 0s;
							animation-duration: 18s;
						}
						.circles li:nth-child(5){
							left: 65%;
							width: 20px;
							height: 20px;
							animation-delay: 0s;
						}
						.circles li:nth-child(6){
							left: 75%;
							width: 110px;
							height: 110px;
							animation-delay: 3s;
						}
						.circles li:nth-child(7){
							left: 35%;
							width: 150px;
							height: 150px;
							animation-delay: 7s;
						}
						.circles li:nth-child(8){
							left: 50%;
							width: 25px;
							height: 25px;
							animation-delay: 15s;
							animation-duration: 45s;
						}
						.circles li:nth-child(9){
							left: 20%;
							width: 15px;
							height: 15px;
							animation-delay: 2s;
							animation-duration: 35s;
						}
						.circles li:nth-child(10){
							left: 85%;
							width: 150px;
							height: 150px;
							animation-delay: 0s;
							animation-duration: 11s;
						}	
						</style>
						<ul class="circles"><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li><li></li></ul>
						<?php
						}
						
						//Carrega os dados do usuário						
						include($_SERVER['DOCUMENT_ROOT'].'/backengine/load_user.php');
						
						
						//Usuário logado e app habilitado?
						if(isset($_SESSION['wz']) || $app[0]['lg'] == 1){
							
							//AMBIENTE DO APP (ENV) 0 DEV / 1 HML / 2 PRD
							$env = ($app[0]['env'] == 0) ? 'dev' : (($app[0]['env'] == 1) ? 'hml' : 'prd');
							
							//APP INCORPORADO
							if($app[0]['cd'] == 1){
							?>
							<iframe id="embed" sandbox="allow-same-origin allow-top-navigation allow-scripts allow-popups allow-forms allow-downloads allow-pointer-lock allow-orientation-lock allow-popups-to-escape-sandbox allow-storage-access-by-user-activation allow-presentation" class="large-12 medium-12 small-12 height-100 position-absolute" src="<?= $app[0]['pg'] ?>" frameborder="0"></iframe>
							<?php
							
							//REDIRECIONAMENTO
							}elseif($app[0]['cd'] == 2){							
								header("Location: ".$app[0]['pg'], true, 301);
								exit();
															
							//APP NATIVO
							}elseif(file_exists('env/'.$env.'/wa'.str_pad($app[0]['id'], 4, '0', STR_PAD_LEFT).'.php')){

								//CONTEÚDO DO APP NATIVO
								?>								
								<div id="appContent" class="large-12 medium-12 small-12 height-100" style="background: linear-gradient(to bottom, <?= $colours[1] ?> 0%, <?= $colours[0] ?> 100%);">
								<?php
								
								//APP POSSUI CABEÇALHO
								if($app[0]['ct'] == 0){
								
								//BARRA LATERAL
								if(isset($_SESSION['wz'])){				
								?>								
								<div class="sidebar position-fixed cm-pad-0 cm-mg-0 abs-t-0 z-index-3 w-shadow-1 background-gray height-100 overflow-y-auto" id="sidebar">
									<div id="config" class="height-100">											
									</div>
								</div>
								<?php
								}								
								//CABEÇALHO
								?>								
								<div class="clearfix large-12 medium-12 small-12 w-rounded-30-l-b white cm-pad-30 z-index-2 w-modal-shadow position-sticky display-center-general-container" style="background-color: <?= $appInfo['cl'] ?>">
									<div class="float-left large-4 medium-5 small-4">
										<a href="<?= $url ?>">
										<div class="float-left text-left white display-inline" style="margin: 0; padding: 0;">
											<img style="height: 18.75px;" class="text-right display-block" src="https://workz.com.br/logo_white.png"></img>
											<h1 class="cm-pad-15-h display-block fs-g" style="margin-top: -5px"><?= $app[0]['tt'] ?></h1>
										</div>
										</a>
									</div>
									<?php
									//GATILHO DA BARRA LATERAL
									if(isset($_SESSION['wz'])){
									?>
									<div class="clearfix float-right large-8 medium-7 small-8">
										<div id="sidebarToggle" onclick="toggleSidebar(); loadMainMenu()" class="pointer w-circle background-gray w-shadow float-right" style="height: 40px; width: 40px; background: url(data:image/jpeg;base64,<? echo $loggedUser['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
										<div id="appHeader" class="float-right display-none display-center-general-container cm-pad-15-r" style="width: calc(100% - 40px); height: 40px;"></div>										
									</div>
									<?php
									}						
									?>									
								</div>								
								<?php
								}
								
								//APP LIGADO À CONTAS DE NEGÓCIO
								if($app[0]['tp'] == 3){
									?>
									<div id="main-content" style="height: calc(100% - 106.16px)" class="large-12 medium-12 small-12 overflow-auto cm-pad-20-b cm-pad-20 view ease-all-2s clear">
									<?php									
									//Escolha entre negócios do usuário que estão habilitados
									$companies_user = array_column(search('cmp', 'companies', 'id', "us = '".$_SESSION['wz']."'"),'id');	
									$pass_companies = array();
									foreach($companies_user as $company){
										$pass_companies[] = array_column(search('app', 'gapp', 'em', "em = {$company} AND ap = {$app[0]['id']} AND st > 0"),'em');
									}
									$pass_companies = array_merge(...array_values($pass_companies));
									if(count($pass_companies) > 0){											
									?>
									<div class="large-12 medium-12 small-12 text-center gray cm-mg-30-t">
										<h2>Selecione uma Conta</h2>	
									</div>
									<div class="large-12 medium-12 small-12 cm-pad-20">								
										<div class="row w-shadow w-rounded-15">			
										<?php
										$content = array();
										$itemCount = count($pass_companies);
										foreach($pass_companies as $key => $result){												
											$content = search('cmp', 'companies', 'tt,pg', "id = '".$result."'");																																								
											if(count($content) > 0){
											$content = $content[0];
											$title = $content['tt'];
											$cssClass = getCssClass($key, $itemCount);												
											?>
											<div onclick="goPost('env/<?= $env ?>/<?= 'wa' . str_pad($app[0]['id'], 4, '0', STR_PAD_LEFT) . '.php' ?>', 'main-content', '<?= $result ?>', '0');" class="<?= $cssClass ?>">
												<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 center-general-container">			
													<span class="fa-stack orange" style="vertical-align: middle;">
														<i class="fas fa-circle fa-stack-2x light-gray"></i>
														<i class="fas fa-building fa-stack-1x fa-inverse dark"></i>
													</span>														
													<?= $title ?>
												</div>										
											</div>		
											<?php
											}
										}											
										?>
										</div>
									</div>										
									<?php
									echo '<hr>';
									}
									//Habilitação de novos negócios ao APP (criador/moderador)
									$other_companies = array_values(array_diff($companies_user, $pass_companies));
									?>
									<div class="large-12 medium-12 small-12 text-center gray">
										<h2>Nova Conta</h2>	
									</div>
									<div class="large-12 medium-12 small-12 cm-pad-20">								
										<div class="row w-shadow w-rounded-15">			
										<?php
										$content = array();
										$itemCount = count($other_companies);
										foreach($other_companies as $key => $result){
											$content = search('cmp', 'companies', 'tt,pg', "id = '".$result."'");																																								
											if(count($content) > 0){
											$content = $content[0];
											$title = $content['tt'];
											$cssClass = getCssClass($key, $itemCount);
											?>
											<div onclick="" class="<?= $cssClass ?>">
												<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 center-general-container">			
													<span class="fa-stack orange" style="vertical-align: middle;">
														<i class="fas fa-circle fa-stack-2x light-gray"></i>
														<i class="fas fa-building fa-stack-1x fa-inverse dark"></i>
													</span>														
													<?= $title ?>
												</div>										
											</div>		
											<?php
											}
										}											
										?>
										</div>
									</div>
									</div>
									<?php
									
								//APP NATIVO COMUM
								}else{
									include('env/'.$env.'/wa' . str_pad($app[0]['id'], 4, '0', STR_PAD_LEFT) . '.php');
								}								
								?>
								<script>
									
									if (typeof appHeader === "function") {
										appHeader();
									}
									
									//MENU LATERAL DE APP NATIVO
									
									//Carrega o menu principal
									function loadMainMenu() {								
										goTo('env/<?= $env ?>/menu.php', 'config', 0, '');
										waitForElm('#appOptions').then((elm) => {
											//A
											if (typeof appOptions === "function") {
												appOptions();
											}else{
												console.log('O app não possui menu personalizado');
											}																																					
										});
									}
									
									//Adiciona botões de ação personalizados ao menu lateral
									function createOption(link, target, param, text, icon, border = false, roundedTop = false, roundedBottom = false) {
										let roundedClass = "w-rounded";
										if (roundedTop && roundedBottom) {
											roundedClass = "w-rounded-15";
										} else if (roundedTop) {
											roundedClass = "w-rounded-15-t";
										} else if (roundedBottom) {
											roundedClass = "w-rounded-15-b";
										}									

										return '<div onclick="goTo(\'' + link + '\', \'' + target + '\', ' + param + ', \'\')" ' +
													'class="' + (border ? 'border-t-input ' : '') + 'fs-d cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis ' +
													'w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h ' + roundedClass + '">' +
													'<div class="large-12 medium-12 small-12 text-ellipsis center-general-container">' +
														'<span class="fa-stack orange cm-mg-5-r" style="vertical-align: middle;">' +
															'<i class="fas fa-square fa-stack-2x light-gray"></i>' +
															'<i class="fas ' + icon + ' fa-stack-1x fa-inverse dark"></i>' +
														'</span>' +
														text +
													'</div>' +
												'</div>';
									}
									
									// Fecha o menu lateral ao clicar fora dele
									document.addEventListener('click', function(event) {
										var sidebar = document.getElementById('sidebar');

										// Verifica se o elemento existe antes de prosseguir
										if (!sidebar) {
											return; // Se não existir, sai da função sem fazer nada
										}
										
										var targetElement = event.target;

										// Certifique-se de que a barra lateral não interfira com os vídeos ou outros elementos
										var isInsideSidebar = sidebar.contains(targetElement);
										var isSidebarToggle = targetElement.id === 'sidebarToggle';
										var isSweetAlertModal = targetElement.closest('.swal-overlay');
										var isSweetAlertButton = targetElement.closest('.swal-button');
										var isSidebarOpener = targetElement.closest('.open-sidebar');
										var isSidebarItem = targetElement.closest('.sidebar-item');
										var isAppHeader = targetElement.closest('#appHeader');
										var isTeamUserRemove = targetElement.closest('.select2-selection__choice__remove');

										if (
											!isInsideSidebar &&
											!isSidebarToggle &&
											!isSweetAlertModal &&
											!isSweetAlertButton &&
											!isSidebarOpener &&
											!isSidebarItem &&
											!isAppHeader &&
											!isTeamUserRemove
										) {
											var config = document.getElementById('config');
											if (config) {
												config.innerHTML = '';
											}
											sidebar.classList.remove('open');
										}
									});
								
									//Fecha o menu lateral
									function toggleSidebar() {
										var sidebar = document.getElementById('sidebar');
										// Verifica se a classe 'open' existe antes de alternar
										if (sidebar.classList.contains('open')) {										
											document.getElementById('config').innerHTML = '';
										}
										sidebar.classList.toggle('open');
									}
								</script>
								</div>
								<?php
								
							//APP NÃO ESPECIFICADO
							}else{
								echo 'Erro na matrix: env/'.$env.'/wa'.str_pad($app[0]['id'], 4, '0', STR_PAD_LEFT).'.php';
							}
							
						//PÁGINA INCIAL (LOGIN)
						}else{
						?>
						<!-- LOGIN -->
						<div style="" class="position-absolute abs-t-0 abs-b-0 abs-r-0 abs-l-0 z-index-1 clearfix overflow-y-auto">
							<div class="centered position-relative large-12 medium-12 small-12 height-100" style="min-height: 600px">
								<div class="area large-12 medium-12 small-12 height-100 position-absolute opacity-075" style="background: url(<?= $app[0]['bk'] ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">
								</div>
								<div class="position-absolute abs-t-0 clearfix text-center cm-pad-60 cm-pad-0-h w-rounded-50-l-b large-12 medium-12 small-12">
									<img style="height: 120px;" class="w-rounded-20 w-shadow-2" src="data:image/jpeg;base64,<?= $app[0]['im'] ?>"></img>
								</div>									
								<div class="w-modal-shadow w-rounded-35-l-t position-absolute abs-b-0 abs-r-0 large-4 medium-12 small-12 cm-pad-30 cm-pad-20-b background-gray">									
									<div id="login" class="">
										<?php
										include('../partes/loginZ.php');
										?>
									</div>
									<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-10-h fs-c text-center">									
										<img src="https://guilhermesantana.com.br/images/50x50.png" style="height: 35px; width: 35px" alt="Logo de Guilherme Santana"></img><br />
										<a class="font-weight-500" target="_blank">Guilherme Santana © <?= date('Y') ?></a>
									</div>
								</div>
							</div>
						</div>
						<?php
						}
						?>						
					</div>								
					<script src="https://workz.com.br/js/cpfCnpj.js"></script>
					<script src="https://workz.com.br/js/formatarMoeda.js"></script>
					<script src="https://workz.com.br/js/index/goTo.js"></script>
					<script src="https://workz.com.br/js/index/goPost.js"></script>
					<script src="https://workz.com.br/js/index/formValidator2.js"></script>
					<script src="https://workz.com.br/js/index/textEditor.js"></script>															
					<script type='text/javascript' src="https://workz.com.br/js/functions.js"></script>
					<script>
					$(function(){
						$("#filtro").keyup(function(){
							var texto = $(this).val();
							$(".bloco").each(function(){
								var resultado = $(this).text().toUpperCase().indexOf(' '+texto.toUpperCase());						  
								if(resultado < 0) {
									$(this).fadeOut();
								}else {
									$(this).fadeIn();
								}
							});

						});
					});
										
					//SWEER ALERT
					function sAlert(fnc, tt = '', ss = '', cl = '') {
						// Validação básica dos parâmetros
						const isFunction = (fnc) => typeof fnc === "function";
						
						// Função para exibir o SweetAlert
						const showAlert = (options) => {
							if (typeof options === 'string') {
								return swal(options);  // Retorna a promessa
							} else {
								return swal(options);  // Retorna a promessa
							}
						};

						// Exibe apenas a mensagem, sem função ou botões adicionais
						if (!ss && !cl) {
							showAlert(tt);
						} else {
							// Exibe a confirmação com botões (quando ss ou cl são fornecidos)
							const alertOptions = {
								title: "Tem certeza?", // Se 'tt' estiver vazio, usa uma mensagem padrão
								text: tt,
								icon: "warning",
								buttons: true,
								dangerMode: true
							};
							showAlert(alertOptions).then((result) => {
								if (result) {
									if (isFunction(fnc)) fnc();  // Executa a função se existir
									showAlert(ss);  // Exibe a mensagem de sucesso
								} else {
									showAlert(cl);  // Exibe a mensagem de cancelamento
								}
							});
						}
					}			
					</script>
					<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
					<script>
					function tableToExcel(tableId, fileName) {
						const table = document.getElementById(tableId); // Corrigido para usar 'tableId'
						if (!table) {
							console.error(`Tabela com ID "${tableId}" não encontrada.`);
							return;
						}
						const workbook = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
						XLSX.writeFile(workbook, fileName + '.xlsx');
					}
					</script>
				</body>
				</html>				
				<?php				
			
		}else{
			echo "Aplicativo não encontrado.";
		}		
	}else{
		echo 'É preciso indicar o nome do aplicativo após "/" na barra de endereços.';
	}	
}else{
	header('Location: https://workz.com.br/');
}