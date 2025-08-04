<?php
header("Cross-Origin-Opener-Policy: same-origin");
//CROSS SITE COOKIE ENABLED
setcookie('cross-site-cookie', 'name', ['samesite' => 'None', 'secure' => true]);
$date = new DateTime();
//MOBILE VARIABLE
$useragent=$_SERVER['HTTP_USER_AGENT'];
//PAGE CONFIG
session_start();

require_once('functions/search.php');
require_once('functions/insert.php');
require_once 'includes/Mobile-Detect-2.8.41/Mobile_Detect.php';
date_default_timezone_set('America/Fortaleza');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');

//IF IT HAS AN USERNAME IN URL > CONVERT IT TO GET VARIABLE
require_once('functions/resolveUsernameFromUrl.php');
resolveUsernameFromUrl();

//MOBILE DEVICES
$detect = new Mobile_Detect();
$mobile = $detect->isMobile() ? 1 : 0;

//PAGE PRESETS
$pgid = '';
$pg_access = '';
$js_page_type = '';
$pg_tasks = 'off';
$config = explode("|", "|||");

//LOAD USER DATA
if(isset($_SESSION['wz'])){
	include('backengine/load_user.php');
}

//LOAD PAGE CONTENT
if(isset($_GET)){
	include('backengine/load_content.php');
}

// Construção do link atual
$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$url = $actual_link;

// Inicialização de variáveis padrão
$title = 'Workz!';
$description = '';
$image = '/images/icons/ios/AppIcon~ipad.png';
$keywords = '';
$post_id = $_GET['post'] ?? $_GET['p'] ?? null;

// Verificação de post existente
if ($post_id && !empty($postData)) {
    $title = str_replace('"', '', $post['tt']) . ' | Por ' . $postUser . ' | Workz!';
    $keywords = $post['kw'];
    $description = $post['tt'];
    $image = str_replace('"', '', base64_decode($post['im']));
} elseif (!empty($_GET)) {
    if (!empty($pgtt) && !empty($pgim)) {
        $title = "{$pgtt} | Workz!";
        $image = "data:image/jpeg;base64,{$pgim}";
    } else {
        $title = '';
        $image = '';
    }

    if (!empty($config[0])) {
        $description = $config[0];
    }
}
?>
<!DOCTYPE HTML>
<html id="html" class="no-js" lang="pt-br">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
		<meta name="theme-color" content="#FD5F1E" />
		<meta name="msapplication-navbutton-color" content="#FD5F1E" />
		<meta name="mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
		<meta name="description" content='<?php echo $description; ?>'>
		<meta name="keywords" content="<?php if(isset($keywords)){ echo $keywords; }?>">		
		<meta name="twitter:site" content="<?php echo $url; ?>" />	
		<meta name="twitter:title" content="<?php echo $title; ?>">
		<meta name="twitter:description" content="<?php echo $description; ?>">
		<meta name="twitter:card" content="<?php echo $image; ?>">
		<meta property="og:url" content="<?php echo $url; ?>" />
		<meta property="og:type" content="website" />
		<meta property="og:title" content='<?php echo $title; ?>' />
		<meta property="og:description" content='<?php echo $description; ?>' />
		<meta property="og:image" content='<?php echo $image; ?>' />
		<meta property="og:image:width" content="200">
		<meta property="og:image:height" content="200">
		<meta property="fb:app_id" content="377640206404323" />
		<meta property="fb:admins" content="1152984661440076"/>
		<title><?php echo $title; ?></title>
		<!-- Google OAuth for ProtSpot Apps -->
		<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4004809821575180" crossorigin="anonymous"></script>
		<meta name="google-signin-client_id" content="142357823160-7ppge23d63vp2urdkcofqn8fbshbhom9.apps.googleusercontent.com">
		<script src="https://apis.google.com/js/platform.js" async defer></script>		
		<!-- WORKZ ICON -->
		<link rel="icon" href="/images/icons/web/favicon.ico" type="image/x-icon" />
		<link rel="shortcut icon" href="/images/icons/web/favicon.ico" type="image/x-icon" />
		<link rel="apple-touch-icon-precomposed" href="/images/icons/ios/AppIcon-20@2x.png" />
		<link rel="apple-touch-icon-precomposed" sizes="76x76" href="/images/icons/ios/AppIcon~ipad.png" />
		<link rel="apple-touch-icon-precomposed" sizes="120x120" href="/images/icons/ios/AppIcon-40@3x.png" />
		<link rel="apple-touch-icon-precomposed" sizes="167x167" href="/images/icons/ios/AppIcon-83.5@2x~ipad.png" />
		<!-- SWEET ALERT -->
		<script type='text/javascript' src="js/sweetalert.min.js"></script>
		<!-- FONT AWESOME -->						
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous"/>
		<!-- JQUERY -->
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
		<!-- STYLESHEETS -->
		<link href="css/RequestReducedStyle.css" rel="Stylesheet" type="text/css" />
		<link href="css/cm-pad.css" rel="Stylesheet" type="text/css" />
		<link href="css/sizes.css" rel="Stylesheet" type="text/css" />		
		<!-- InteractiveJS -->
		<script type='text/javascript' src="js/interactive.js"></script>
		<script type='text/javascript' src="js/autosize.js"></script>
		<link href="css/interactive.css" rel="stylesheet"/>
		<script src="https://www.youtube.com/iframe_api"></script>
		<script>
		function shareContent(){
			if (navigator.share){			
				var shareData = {
					title: '<? echo $title; ?>',
					text: '<? echo json_encode($description, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>',
					url: '<? echo $url; ?>',
					icon: '<? echo $image; ?>'
				};								
				navigator.share(shareData)
				.then(() => console.log('Aplicativo compartilhado com sucesso!'))
				.catch((error) => console.error('Erro ao compartilhar:', error));
			}else{
				alert('A funcionalidade de compartilhamento não é suportada neste dispositivo/navegador.');
			}
		}
		</script>				
		<script src="js/whammy.js"></script>		

		<?php
		if(isset($pgcl) && !empty($pgcl)){
		?>
		<style>
			.background-orange{
				background: <?php echo $pgcl; ?>;
			}
			.orange{
				color: <?php echo $pgcl; ?>;
			}						
			.w-color-wh-to-or:hover{
				color: <?php echo $pgcl; ?>;
			}
			.w-color-or-to-wh{
				color: <?php echo $pgcl; ?>;
				transition: color .25s ease-in-out;
			}						
			.w-color-bl-to-or:hover{
				color: <?php echo $pgcl; ?>;
				fill: <?php echo $pgcl; ?>;
			}
			.w-color-or-to-bl{
				color: <?php echo $pgcl; ?>;
				transition: color .25s ease-in-out;
			}						
			.w-all-or-to-bl{
				background-color: <?php echo $pgcl; ?>;
				color: #FFF;
				transition: background-color 0.25s ease-in-out, color 0.25s ease-in-out;		
			}						
			.w-all-bl-to-or:hover{
				background-color: <?php echo $pgcl; ?>;
			}						
			.w-all-wh-to-or:hover{
				color: #FFF;
				background-color: <?php echo $pgcl; ?>;
			}
			
			
			.video-iframe {
				width: 100%; /* Ocupe 100% da largura disponível */
				height: 100%; /* Ocupe 100% da altura disponível */
				object-fit: cover; /* Corta para ajustar */
				object-position: center; /* Centraliza o conteúdo cortado */
				border: none; /* Remove borda */
				display: block;
				aspect-ratio: 16 / 9; /* Define o padrão inicial para 16:9 */
			}

			/* Ajustes para vídeos verticais (Shorts ou formato semelhante) */
			.vertical-video {
				aspect-ratio: 9 / 16; /* Ajusta o aspect-ratio para vertical */
			}

		</style>
		<?php
		}
		?>
		<script src="https://cdn.tailwindcss.com"></script>		
	</head>
	<body data-us="<?= (isset($_SESSION['wz']) && $_SESSION['wz'] != '') ? $_SESSION['wz'] : '' ?>" class="background-gray position-absolute abs-t-0 abs-b-0 abs-r-0 abs-l-0" style="max-height: 100%;">
		
		<div id="loading" class="background-gray">
			<div class="la-ball-scale-pulse">
				<div class="w-shadow"></div>
			</div>
		</div>				
		
		<?php		
		if((isset($_SESSION) && !empty($_SESSION)) || (isset($_GET) && !empty($_GET))){
		?>
		
		<div id="callback" class="display-none"></div>		
		<div id="desktop" class=" abs-b-0 large-12 medium-12 small-12 position-fixed z-index-3 ease-all-15s display-grid desktop-area"></div>		

		<div id="dashboard" class="container position-absolute abs-t-0 abs-r-0 abs-b-0 abs-l-0 overflow-y-auto">
			<?php
			if(isset($pgbk) && (!empty($pgbk))){
				//w-page-bkg
			?>
			<div class="row position-absolute z-index-0 abs-t-5 abs-r-5 abs-l-5"	style="border-radius: 10px 10px 25px 25px; height: 220px; background-image: url('data:image/jpeg;base64,<?php echo $pgbk; ?>');
				background-size: cover;
				background-repeat: no-repeat; ">
				<div class="height-100 large-12 medium-12 small-12" style="border-radius: 10px 10px 25px 25px; background: linear-gradient(to bottom, rgba(0,0,0,0.25) 0%,rgba(245,245,245,0) 100%);"></div>
			</div>
			<?php
			}
			?>
			<div class="sidebar position-fixed cm-pad-0 cm-mg-0 abs-t-0 z-index-3 w-shadow-1 background-gray height-100 overflow-y-auto" id="sidebar">
			</div>
			<?php
			//TOP BAR			
			if(!$post_id || (!empty($postData) && $postData[0]['tp'] <> 5)){
			?>			
			<div class="position-fixed large-12 medium-12 small-12 z-index-1">	
				<div id="topbar" class="ease-all-5s cm-pad-30-t cm-pad-30-b">
					<div class="row <?php if($mobile == 0){ echo 'cm-pad-30-h'; }else{ echo 'cm-pad-15-h'; } ?> display-center-general-container large-12 medium-12 small-12 position-relative <?php if($mobile == 1){ ?>text-center<?php } ?>">
						<div class="text-left text-ellipsis" style="width: calc(100% - 40px);">						
							<a href="/"><img class="logo-menu" style="width: 145px; height: 76px;" title="Workz!" src="/images/logos/workz/<?php if(isset($pgbk) && (!empty($pgbk))){ echo '145x76_W.png'; }else{ echo '145x76.png'; } ?>"></img></a>
						</div>
						<?php						
						if(isset($_SESSION['wz'])){
						?>																			
						<div id="sidebarToggle" onclick="toggleSidebar(); var config = $('<div id=config class=height-100></div>'); $('#sidebar').append(config); goTo('partes/resources/modal_content/config_home.php', 'config', 0, '');"	class="pointer w-circle background-gray w-shadow border-gray-sm float-right" style="height: 40px; width: 40px; background: url(data:image/jpeg;base64,<?php echo $loggedUser['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">
						</div>							
						<?php	
						}
						?>						
					</div>
				</div>						
			</div>
			<script>			
			$(document).ready(function() {
				$('#dashboard').scroll(function() {
					var topbar = $('#topbar');
					if ($('#dashboard').scrollTop() > 17.5) {
						topbar.addClass('w-modal-shadow background-gray');
						
						$('.logo-menu').attr('src', '/images/logos/workz/90x47.png');
						$('.logo-menu').css({
							'width': '90px',
							'height': '47px',
							'transition': 'width 0.5s, height 0.5s'
						});
												
					} else {
						topbar.removeClass('w-modal-shadow background-gray');
						
						$('.logo-menu').attr('src', '/images/logos/workz/<?php if(isset($pgbk) && (!empty($pgbk))){ echo '145x76_W.png'; }else{ echo '145x76.png'; } ?>');
						$('.logo-menu').css({
							'width': '145px',
							'height': '76px',
							'transition': 'width 0.5s, height 0.5s'
						});
											
					}
				});
			});	
			</script>
			<?php
			}			
			//CONTENT
			?>			
			<div id="workzContent" class="tab-top row <?php if($mobile == 0){ echo 'cm-pad-15-h'; } ?> clearfix">
				<?php
				// Definição inicial
				$content_lock = 0;
				$post_id = $_GET['post'] ?? $_GET['p'] ?? null;

				// Verificação principal para páginas
				if ((!empty($_GET['company']) || !empty($_GET['team']) || !empty($_GET['profile'])) && 
					($get_count > 0 && (($pgpc > 0 && !empty($_SESSION)) || ($pgpc > 1 && empty($_SESSION)))) || 
					empty($_GET)) {
					
					if (empty($_GET)) {
						$user_level = 1;
						$get_count = 1;
					}

					$content_lock = 1;

					if ($get_count > 0) {
					?>
					<div class="column cm-pad-0-h large-8 medium-8 small-12">
						<div class="large-12 medium-12 small-12 flex" style="margin-top: 135px;">
							<?php
							include('partes/column_left.php');
							include('partes/column_middle.php');
							?>
						</div>
						<div class="cm-pad-30-b cm-pad-30-t">
						<?php
						// Verificação de permissão de conteúdo
						if (($user_level > 0 && empty($_GET)) || 
							($user_level > 0 && !empty($_GET) && $ctpc > 0) || 
							(isset($_SESSION['wz']) && $user_level > 2 && !empty($_GET) && $ctpc == 0) || 
							(isset($_SESSION['wz']) && !empty($_GET) && $user_level == '' && $ctpc > 1) || 
							(!isset($_SESSION['wz']) && !empty($_GET) && $user_level == '' && $ctpc > 2)) {
						?>
							<div id="timeline" class="large-12 medium-12 small-12 position-relative tab cm-pad-5-h"></div>
							<?php
							$sideline = '';

							if (!empty($_GET['profile'])) {
								$sideline = $js_page_type . ',' . $_GET['profile'] . ',';
							} elseif (!empty($_GET['team'])) {
								$sideline = $js_page_type . ',' . $_GET['team'] . ',';
							} elseif (!empty($_GET['company'])) {
								$sideline = $js_page_type . ',' . $_GET['company'] . ',';
							}

							if ($sideline) {
							?>
							<script>
								window.onload = function(){
									goTo('backengine/timeline.php', 'timeline', '0', '<?php echo $sideline; ?>');
									timelineScroll('<?php echo $sideline; ?>');
									setTimeout(() => {
										observePosts();
									}, 500);
								};
							</script>
							<?php
							}
						} else {
							?>
							<div class="large-12 cm-pad-15-h">
								<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
									<i class="fas fa-info-circle cm-mg-5-r"></i> Conteúdo indisponível.
								</div>
							</div>
						<?php
						}
						?>
						</div>
					</div>
					<script type="module" src="js/main.js"></script>
					<?php
					} else {
						?>
						<div class="column <?php echo !isset($_SESSION['wz']) ? 'large-8' : 'large-12'; ?>">
							<div class="row cm-pad-10-h">
								<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
									<i class="fas fa-info-circle cm-mg-5-r"></i> Página inexistente ou bloqueada.
								</div>
							</div>
						</div>
						<?php
					}

					// Exibição da coluna lateral se não for um dispositivo móvel
					if ($mobile == 0) {
					?>
						<div class="column cm-pad-0-h large-4 medium-4 small-12" style="padding-top: 135px;">
							<?php include('partes/column_right.php'); ?>
						</div>
					<?php
					}
				} elseif ($post_id) { 
					// FULL PAGE POST
					if (empty($postData)) {
					?>
						<div class="cm-pad-10-b cm-pad-15-h" style="padding-top: 135px;">
							<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
								<i class="fas fa-info-circle cm-mg-5-r"></i> Publicação inexistente.
							</div>
						</div>
					<?php
					} else {
						include('partes/publicacao.php');
					}
				} elseif (isset($_GET['teams']) || isset($_GET['users']) || isset($_GET['companies'])) { 
					// SEARCH TEAMS, USERS OR COMPANIES
					include('partes/search.php');
				} else { 
					// Página não encontrada
				?>
					<div class="cm-pad-10-b cm-pad-15-h" style="padding-top: 135px;">
						<div class="w-shadow-1 w-rounded-30 large-12 medium-12 small-12 cm-pad-10" style="background: #F7F8D1;">
							<i class="fas fa-info-circle cm-mg-5-r"></i> Página não encontrada.
						</div>
					</div>
				<?php
				}
				?>					
			</div>			
		</div>				

		<script type='text/javascript'>							
			<?php			
			if(empty($_GET) && isset($_SESSION['wz'])){
			?>
			//Ao carregar a janela
			window.onload = function(){
				goTo('backengine/timeline.php', 'timeline', '0', '');
				startTime();
				timelineScroll('');				
			}										
			<?php
			}		
			?>				
		</script>

		<?php
		}else{
		//PÁGINA INCIAL		
		?>
		<style>		
		.flex { /*Flexbox for containers*/
			display: flex;	
			flex-direction: column;	
			height: 100vh;
			scroll-snap-type: y mandatory;			
		}		
		.inner-header {
			height:70vh;
			width:100%;
			margin: 0;
			padding: 0;
			flex-shrink: 0;
		}	
		.waves {
			position:relative;
			width: 100%;
			height:12vh;
			margin-bottom:-7px; /*Fix for safari gap*/
			min-height:100px;
			max-height:150px;
		}
		.content {
			position:relative;
			height:18.5vh;
			justify-content: center;
			align-items: center;
			text-align: center;
		}
		/* Animation */
		.parallax > use {
			animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite;
		}
		.parallax > use:nth-child(1) {
			animation-delay: -2s;
			animation-duration: 10s;
		}
		.parallax > use:nth-child(2) {
			animation-delay: -3s;
			animation-duration: 15s;
		}
		.parallax > use:nth-child(3) {
			animation-delay: -4s;
			animation-duration: 20s;
		}
		.parallax > use:nth-child(4) {
			animation-delay: -5s;
			animation-duration: 25s;
		}
		@keyframes move-forever {
			0% {
				transform: translate3d(-90px,0,0);
			}
			100% { 
				transform: translate3d(85px,0,0);
			}
		}		
		/*Shrinking for mobile*/		
		@media (max-width: 768px) {			
			.inner-header {
				height:75vh;
			}
			.waves{							
				height:10vh;
				min-height:40px;
			}
			.content {
				height:16vh;
			}
			h1 {
				font-size:24px;
			}
		}
		</style>
		<?php
		$url = "https://bing.biturl.top/";
		// Obtendo o conteúdo da URL
		$json = file_get_contents($url);
		// Decodificando o JSON
		$data = json_decode($json, true);
		?>
		<div id="dashboard" class="container position-absolute abs-t-0 abs-r-0 abs-b-0 abs-l-0 overflow-y-auto">
			<div class="sidebar position-fixed cm-pad-0 cm-mg-0 abs-t-0 z-index-3 w-shadow-1 background-gray height-100 overflow-y-auto" id="sidebar">
			</div>
			
			<div class="tab height-100 large-12 medium-12 small-12 z-index-1 position-relative overflow-hidden background-dark">
				<div class="z-index-0 position-absolute abs-t-0 abs-b-0 abs-r-0 abs-l-0 looping_zoom" style="opacity: .7; background-image: url(https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>					
				<div class="inner-header large-12 medium-12 small-12 float-left cm-mg-minus-10-b z-index-1">
					<div class="row large-12 cm-pad-30 cm-pad-60-t medium-12 small-12 display-center-general-container z-index-1">
						<img class="logo-menu" title="Workz!" src="/images/icons/workz_wh/145x60.png"></img>
					</div>					
					<div class="row white cm-pad-30-h display-center-general-container clearfix" style="height: calc(100% - 150px);">
						<div id="login" class="float-right large-4 medium-4 small-12 white abs-r-0" style="margin-left: auto;">
							<?php
							include('partes/loginZ.php');
							?>
						</div>						
					</div>				
				</div>										
				<svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
					<defs>
					<path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
					</defs>
					<g class="parallax">
					<use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(245,245,245,0.7" />
					<use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(245,245,245,0.5)" />
					<use xlink:href="#gentle-wave" x="48" y="4" fill="rgba(245,245,245,0.3)" />
					<use xlink:href="#gentle-wave" x="48" y="4" fill="#F5F5F5" />
					</g>
				</svg>								
							
				<div class="content background-gray display-center-general-container">		
					<div class="text-center">
						<a  class="font-weight-500 dark"><a title="Marca em processo de registro no INPI — Pedido vinculado ao CNPJ 53.334.289/0001-41">Workz!</a> © 2017 - 2025</a><a class="gray"> (Versão Alfa 0.1.2)</a>
						<p><small class="font-weight-500 dark" target="_blank">Desenvolvido por <a href="/guisantana" target="_blank" class="w-color-or-to-bl">Guilherme Santana</a></small></p>
						
						
					</div>
				</div>
			</div>
			
			<div class="row large-12 medium-12 small-12 height-100 z-index-0">				
				<div class="column cm-pad-0-h large-8 medium-12 small-12 cm-pad-30-t">					
					<div id="timeline" class="large-12 medium-12 small-12 cm-pad-5-h">
					</div>
				</div>
				<script>
				//Ao carregar a janela
				window.onload = function(){
					goTo('backengine/timeline.php', 'timeline', '0', '');					
					timelineScroll('');
					setTimeout(()=>{
						observePosts();
					},500);
				}
				</script>
			</div>			
		</div>
		<?php
		}
		?>			
		<link rel="stylesheet" href="css/flickity.css">
		<link rel="stylesheet" href="/css/fullscreen.css">
		
		<link rel='stylesheet' href='https://res.cloudinary.com/lenadi07/raw/upload/v1468859092/forCodePen/owlPlugin/owl.theme.css'>
		<link rel='stylesheet' href='https://res.cloudinary.com/lenadi07/raw/upload/v1468859078/forCodePen/owlPlugin/owl.carousel.css'>
		
		<script src='js/owl.carousel.min.js'></script>
		
		<script type='text/javascript' src="js/functions.js"></script>
		<script type='text/javascript' src="js/index/like.js"></script>
		<script type='text/javascript' src="js/index/goTo.js"></script>		
		<script type='text/javascript' src="js/index/goPost.js"></script>
		<script type='text/javascript' src="js/index/wChange.js"></script>
		<script type='text/javascript' src="js/index/textEditor.js"></script>
		<script type='text/javascript' src="js/index/formValidator.js"></script>
		<script type='text/javascript' src="js/index/formValidator2.js"></script>
		<script type='text/javascript' src="js/flickity.pkgd.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
		<script>
		const currentUser = document.body.dataset.us;
		const sideline = '<?= (isset($sideline) && $sideline != '') ? $sideline : 'null' ?>';
		
		// SIDEBAR: Toggle e Fechamento			
		function toggleSidebar() {
			var sidebar = document.getElementById('sidebar');
			if (sidebar.innerHTML.trim() !== '') {
				sidebar.innerHTML = ''; // Limpa o conteúdo apenas se necessário
			}
			sidebar.classList.toggle('open');
		}

		document.addEventListener('click', function(event) {
			var sidebar = document.getElementById('sidebar');
			var targetElement = event.target;

			// Certifique-se de que a barra lateral não interfira com os vídeos ou outros elementos
			var isInsideSidebar = sidebar.contains(targetElement);
			var isSidebarToggle = targetElement.id === 'sidebarToggle';
			var isSweetAlertModal = targetElement.closest('.swal-overlay');
			var isSweetAlertButton = targetElement.closest('.swal-button');
			var isPageConfigButton = targetElement.closest('#pageConfig');
			var isPageCommentButton = targetElement.closest('.comment');
			var isVideoIframe = targetElement.closest('.video-iframe');

			if (
				!isInsideSidebar &&
				!isSidebarToggle &&
				!isSweetAlertModal &&
				!isSweetAlertButton &&
				!isPageConfigButton &&
				!isPageCommentButton &&
				!isVideoIframe // Garante que vídeos não sejam afetados
			) {
				sidebar.classList.remove('open');
			}
		});
		
		// SWEET ALERT - Função Genérica
		function sAlert({ 
			fnc = null,         // Função a ser executada
			tt = "",            // Mensagem exibida
			ss = "",            // Mensagem de sucesso
			cl = "",            // Mensagem de cancelamento
			warning = true,     // Mostra o ícone de aviso
			dangerMode = true   // Ativa o modo perigoso para ações críticas
		} = {}) {
			// Configuração inicial do SweetAlert
			const swalConfig = {
				title: warning ? "Tem certeza?" : tt, // Exibe título padrão para alertas
				text: !warning ? tt : "",             // Exibe texto somente para mensagens simples
				icon: warning ? "warning" : "info",  // Define ícone (warning ou info)
				buttons: warning,                    // Botões de confirmação para alertas
				dangerMode: dangerMode               // Ativa modo perigoso
			};

			// Se for um alerta simples
			if (!warning || (!ss && !cl && !fnc)) {
				swal(swalConfig).then(() => {
					if (typeof fnc === "function") {
						fnc(); // Executa função simples, se fornecida
					}
				});
				return;
			}

			// Alerta com confirmação
			swal(swalConfig).then((result) => {
				if (result) {
					if (typeof fnc === "function") {
						fnc(); // Executa a função externa, se fornecida
					}
					if (ss) {
						swal("Êxito!", ss, "success"); // Mensagem de sucesso
					}
				} else {
					if (cl) {
						swal("Cancelado", cl, "info"); // Mensagem de cancelamento
					}
				}
			});
		}
		
		let userInteracted = false;

		// Função de debounce para evitar execuções excessivas
		function debounce(func, wait) {
			let timeout;
			return function (...args) {
				clearTimeout(timeout);
				timeout = setTimeout(() => func.apply(this, args), wait);
			};
		}
		
		function postComments(id){
			toggleSidebar();
			var config = $('<div id=config class=height-100></div>'); 
			$('#sidebar').append(config); 
			waitForElm('#config').then((elm) => {
				goTo('partes/resources/modal_content/comments.php', 'config', '', id);
			});
		}		
		
		async function postDelete(id) {
			if (!id || !currentUser) return;

			const pdo_params = {
				type: 'delete',
				db: 'hnw',
				table: 'hpl',
				where: `id="${encodeURIComponent(id)}" AND us="${encodeURIComponent(currentUser)}"`
			};

			sAlert({
				fnc: async () => {
					// Envia a requisição de exclusão com os dados base64url-encoded
					goTo('functions/actions.php', 'callback', '', btoa(JSON.stringify(pdo_params)));

					const callback = document.getElementById('callback');
					if (!callback) return;

					setTimeout(async () => {
						if (callback.innerHTML.trim() !== '') {
							document.querySelectorAll('#timeline .no-more-content[data-end="1"]')
								.forEach(el => el.remove());

							fimDaTimeline = false;
							uview = 1;

							// Recarrega a timeline e scrolla
							goTo('backengine/timeline.php', 'timeline', '0', sideline);
							callback.innerHTML = '';

							await waitForElm('#timeline');
							timelineScroll(sideline);
						}
					}, 750);
				},
				tt: 'Esta publicação será excluída permanentemente.',
				ss: 'Publicação excluída com sucesso!',
				cl: 'Ação cancelada.'
			});
		}

		function getUview() {
			let maior = 0;
			document.querySelectorAll('[id^="dynamic_timeline_"]').forEach(el => {
				const match = el.id.match(/dynamic_timeline_(\d+)/);
				if (match) {
					const num = parseInt(match[1], 10);
					if (num > maior) maior = num;
				}
			});
			return maior + 1;
		}

		let uview = 1;							
		let fimDaTimeline = false;
		let carregandoTimeline = false;

		// Linha do tempo
		function timelineScroll(sideline) {
			if ($('#timeline').length) {				
				
				// Função para manipular o evento de rolagem
				function handleScroll() {
					if (fimDaTimeline || carregandoTimeline) return;
					
					const dashboard = $('#dashboard');

					// Verifica se o elemento #dashboard existe
					if (dashboard.length) {
						const scrollSum = Math.trunc(dashboard.scrollTop() + dashboard.height());
						const scrollGot = dashboard[0].scrollHeight;												
						
						if (scrollSum >= (scrollGot - 500) && scrollSum <= scrollGot) {							
						
							const uview = getUview();
							const elementoId = 'dynamic_timeline_' + uview;

							if (!document.getElementById(elementoId)) {
								//console.log('Carregando:', elementoId);

								// Bloqueia scroll até resposta
								carregandoTimeline = true;

								const newDiv = document.createElement('div');
								newDiv.id = elementoId;
								newDiv.className = 'timeline large-12 medium-12 small-12';
								document.getElementById('timeline').appendChild(newDiv);

								waitForElm('#' + elementoId).then((elm) => {
									goTo('backengine/timeline.php', elementoId, uview, sideline);

									setTimeout(() => {
										const marcadorFim = document.querySelector(`#${elementoId} .no-more-content[data-end="1"]`);
										if (marcadorFim) {
											fimDaTimeline = true;
											//console.log("🔚 Fim da timeline detectado.");
										}
										carregandoTimeline = false; // Libera scroll
									}, 600);
								}).catch(() => {
									console.warn(`Timeout esperando #${elementoId}`);
									carregandoTimeline = false;
								});
							}
						}

						
						setTimeout(observePosts, 300);
					} else {
						console.warn("Elemento #dashboard não encontrado.");
					}
				}

				// Debounce para otimizar rolagem
				const debouncedHandleScroll = debounce(handleScroll, 100);

				// Adiciona o evento de rolagem
				$('#dashboard').on('scroll', debouncedHandleScroll);

				// Executa a função inicialmente para carregar o primeiro conjunto de dados
				handleScroll();
			} else {
				console.warn("Elemento #timeline não encontrado.");
			}
		}

		function carrousel(){
			if($("#owl-demo")){
				$("#owl-demo").owlCarousel({
					navigation : false, // Show next and prev buttons
					slideSpeed : 300,
					paginationSpeed : 400,
					singleItem: true
				});
				console.log($("#owl-demo"));
			}
			
		}
		
		function toggleLg(el){			
			el.classList.toggle('text-ellipsis-2'); 			
		}

		$(document).on('click touchstart scroll', () => {
			userInteracted = true;			
		});
		
		let startX, startY;

		document.addEventListener('touchstart', (event) => {
			const touch = event.touches[0];
			startX = touch.clientX;
			startY = touch.clientY;
		});

		document.addEventListener('touchend', (event) => {
			const touch = event.changedTouches[0];
			const deltaX = touch.clientX - startX;
			const deltaY = touch.clientY - startY;

			// Define o limite mínimo para considerar um "deslizar"
			const swipeThreshold = 50;

			if (Math.abs(deltaX) > swipeThreshold || Math.abs(deltaY) > swipeThreshold) {
				userInteracted = true; // Marca como interação válida
				console.log('Deslizar detectado. Interação registrada.');
			}
		});
		
		// Funções para controle de vídeos
		function playVideo(videoElement) {
			const dataSrc = videoElement.getAttribute('data-src');
			if (!videoElement.src && dataSrc) {
				videoElement.src = dataSrc; // Define o src dinamicamente
			}
			const src = videoElement.src || dataSrc;
			if (src.includes('youtube.com')) {
				videoElement.contentWindow.postMessage(
					JSON.stringify({ event: 'command', func: 'playVideo' }),
					'*'
				);
			} else if (src.includes('vimeo.com')) {
				videoElement.contentWindow.postMessage({ method: 'play' }, '*');
			} else if (src.includes('dailymotion.com')) {
				videoElement.contentWindow.postMessage({ command: 'play' }, '*');
			} else if (src.includes('workz.space')) {				
				videoElement.contentWindow.postMessage({ action: 'play' }, '*');	
				if(userInteracted === true){					
					//videoElement.contentWindow.postMessage({ action: 'unmute' }, '*');
				}		
			} else if (src.includes('canva.com')) {
				videoElement.focus();
				videoElement.click();
				const button = document.querySelector("#root > div > main > div > div > div:nth-child(1) > button");
				if (button) {
					button.click();
				}
			}
		}

		function pauseVideo(videoElement) {
			const src = videoElement.src || videoElement.getAttribute('data-src');
			if (src.includes('youtube.com')) {
				videoElement.contentWindow.postMessage(
					JSON.stringify({ event: 'command', func: 'pauseVideo' }),
					'*'
				);
			} else if (src.includes('vimeo.com')) {
				videoElement.contentWindow.postMessage({ method: 'pause' }, '*');
			} else if (src.includes('dailymotion.com')) {
				videoElement.contentWindow.postMessage({ command: 'pause' }, '*');
			} else if (src.includes('workz.space')) {
				videoElement.contentWindow.postMessage({ action: 'pause' }, '*');				
				videoElement.contentWindow.postMessage({ action: 'mute' }, '*');
			} else if (src.includes('canva.com')) {
				//videoElement.contentWindow.postMessage({ action: 'pause' }, '*');
			}
		}		
			
		function muteUnmute(videoElement){
			if (!videoElement.muted) {
				videoElement.contentWindow.postMessage({ action: 'mute' }, '*');
			} else {
				videoElement.contentWindow.postMessage({ action: 'unmute' }, '*');
			}
		}

		// Configuração do IntersectionObserver
		const observer = new IntersectionObserver((entries) => {
			entries.forEach((entry) => {
				const videoElement = entry.target.querySelector('video, iframe');
				if (!videoElement) return;

				if (entry.isIntersecting) {
					playVideo(videoElement);					
				} else {
					pauseVideo(videoElement);
				}
				
			});
		}, {
			threshold: [1], // Detecta quando o vídeo está 100% visível
		});

		// Observar os vídeos
		function observePosts() {
			const posts = document.querySelectorAll('.tab');
			posts.forEach((post) => observer.observe(post));
		}
		
		// Inicializa a observação
		document.addEventListener('DOMContentLoaded', () => {
			observePosts(); // Observa publicações carregadas inicialmente
		});		
		</script>
		<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
		<script src="https://cdn.jsdelivr.net/npm/@ffmpeg/ffmpeg@0.11.1/dist/ffmpeg.min.js"></script>
	</body>
</html>