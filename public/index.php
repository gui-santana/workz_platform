<!DOCTYPE HTML>
<html id="html" class="no-js" lang="pt-br">
	<head>
		<meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Workz!</title>
        <script src="https://cdn.tailwindcss.com"></script>						
		<script type='text/javascript' src="js/sweetalert.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
		<link rel="stylesheet" href="/css/main.css">				
		<link rel="stylesheet" href="css/footerParallax.css" />
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous"/>
		
		<!-- InteractiveJS -->
		<script type='text/javascript' src="js/interactive.js"></script>
		<script type='text/javascript' src="js/autosize.js"></script>
		<link href="css/interactive.css" rel="stylesheet"/>
		<script src="https://www.youtube.com/iframe_api"></script>								
	</head>
	<body class="w-full bg-gray-100">
		
		<div id="loading" class="w-full bg-gray-100 ">
			<div class="la-ball-scale-pulse">
				<div class="w-shadow"></div>
			</div>
		</div>
						
		<div id="main-wrapper" class="w-full h-screen overflow-y-auto overflow-x-hidden snap-y">			
			<div class="snap-center relative h-full w-full bg-gray-900">
				<div class="absolute top-0 left-0 right-0 bottom-0 overflow-hidden looping_zoom z-0" style="opacity: .7; background-image: url(https://bing.biturl.top/?resolution=1366&format=image&index=0&mkt=en-US); background-size: cover; background-position: center; background-repeat: no-repeat;"></div>
				<div class="w-full absolute bottom-0">
					<svg class="waves z-2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
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
					<div class="w-full p-8 bg-gray-100 content-center">
						<div class="text-center">
							<a  class=""><a>Workz!</a> © 2025</a><a class="gray"> (Stable 1.0.0)</a>
							<p><small class="" target="_blank">Desenvolvido por <a href="/profile/guisantana" target="_blank" class="font-semibold">Guilherme Santana</a></small></p>
						</div>
					</div>
				</div>
				<div class="absolute h-full w-full m-0 p-0 z-0">
					<div class="h-full max-w-screen-xl mx-auto m-0 p-8 grid grid-rows-12 grid-cols-12">
						<div class="w-full row-span-1 col-span-12 content-center">
							<img title="Workz!" src="/images/icons/workz_wh/145x60.png"></img>
						</div>
						<div id="login" class="px-30 row-span-9 col-span-12 sm:col-span-6 md:col-span-4 content-center  justify-center">
							<?php
							//include('partes/loginZ.php');
							?>														
						</div>
					</div>
				</div>
			</div>
			<div class="relative w-full bg-gray-100 z-3 clear">
				<div class="max-w-screen-xl mx-auto grid grid-cols-12">
					<div class=" px-0 col-span-12 lg:col-span-8 pt-6">
						<div id="timeline" class="w-full px-5">
						</div>
					</div>
				</div>				
				<script>
					/*
				//Ao carregar a janela
				window.onload = function(){
					goTo('backengine/timeline.php', 'timeline', '0', '');					
					timelineScroll('');
					setTimeout(()=>{
						observePosts();
					},500);
				}
					*/
				</script>
			</div>			
		</div>
		<div id="sidebar-wrapper" class="fixed p-0 m-0 top-0 right-0 z-3 w-0 h-full bg-gray-100 overflow-y-auto transition-width duration-500 ease-in-out"></div>
		<div class="sidebar fixed p-0 m-0 top-0 z-3 bg-gray-100 h-full overflow-y-auto" id="sidebar"></div>

		<script type="module" src="js/main.js"></script>

		<!--link rel="stylesheet" href="css/flickity.css">
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
		<script src="https://cdn.jsdelivr.net/npm/@ffmpeg/ffmpeg@0.11.1/dist/ffmpeg.min.js"></script-->
	</body>
</html>