<link href="core/backengine/wa0026/css/style2.css" rel="stylesheet">	
<?
include('sanitize.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
?>
<div class="container position-absolute abs-t-0 abs-b-0 abs-r-0 abs-l-0 large-12 medium-12 small-12">

	<!-- LEITOR -->
	<style>	
	svg{	
		top: 0;
		left: 0;
		width: 100%;
		height: 100vh;
	}
	#coverImage{
		width: 100px;
	}
	</style>
	<div class="tab height-100 large-12 medium-12 small-12 position-relative">
		<img id="coverImage" class="position-absolute abs-t-20 abs-l-20 w-rounded-10 w-shadow-2 background-white z-index-2" alt="Capa do PDF" />
		<div class="position-absolute abs-t-0 abs-r-0 abs-l-0 abs-b-0">			
			<svg preserveAspectRatio="xMidYMid slice" viewBox="10 10 80 80">
				<defs>
					<style>
						.out-top,
						.in-top,
						.out-bottom,
						.in-bottom {							
							opacity: .15;
							animation: none; /* Animação desativada inicialmente */
						}

						@keyframes rotate {
						0%{
								transform: rotate(0deg);
							}
							100% {
								transform: rotate(360deg);
							}
						}
						.out-top {
							
							transform-origin: 13px 25px;
						}
						.in-top {
							
							transform-origin: 13px 25px;
						}
						.out-bottom {
							
							transform-origin: 84px 93px;
						}
						.in-bottom {
							
							transform-origin: 84px 93px;
						}
					</style>
				</defs>
				<path fill="#fd5f1d" class="out-top w-modal-shadow" d="M37-5C25.1-14.7,5.7-19.1-9.2-10-28.5,1.8-32.7,31.1-19.8,49c15.5,21.5,52.6,22,67.2,2.3C59.4,35,53.7,8.5,37-5Z"/>
				<path fill="#ff7f3d" class="in-top w-shadow-2" d="M20.6,4.1C11.6,1.5-1.9,2.5-8,11.2-16.3,23.1-8.2,45.6,7.4,50S42.1,38.9,41,24.5C40.2,14.1,29.4,6.6,20.6,4.1Z"/>
				<path fill="#e6521a" class="out-bottom w-modal-shadow" d="M105.9,48.6c-12.4-8.2-29.3-4.8-39.4.8-23.4,12.8-37.7,51.9-19.1,74.1s63.9,15.3,76-5.6c7.6-13.3,1.8-31.1-2.3-43.8C117.6,63.3,114.7,54.3,105.9,48.6Z"/>
				<path fill="#d14b18" class="in-bottom w-shadow-2" d="M102,67.1c-9.6-6.1-22-3.1-29.5,2-15.4,10.7-19.6,37.5-7.6,47.8s35.9,3.9,44.5-12.5C115.5,92.6,113.9,74.6,102,67.1Z"/>
			</svg>		
		</div>
		<div class="controls extra-controls position-absolute abs-t-0 abs-r-0 background-white w-rounded-20-l-b w-shadow-2 z-index-1">				
			<div class="togglable closed">
				<div class="zoom border-b-input">
					<a id="spritz_smaller" href="#" title="Smaller" class="smaller entypo-minus"></a>
					<span href="#" title="Smaller" class="entypo-search"></span>
					<a id="spritz_bigger" href="#" title="Bigger" class="bigger entypo-plus"></a>
				</div>				
				<div class="autosave border-b-input text-ellipsis cm-pad-15 cm-pad-30-h">
					<input id="autosave_checkbox" type="checkbox" class="checkbox"/>
					<label id="spritz_autosave" for="autosave_checkbox" class="checkbox-label entypo-cancel display-center-general-container">Autosave</label>
				</div>
				<div class="zoom border-b-input">
					<a id="spritz_save" href="#" title="Save" class="save entypo-save"></a>					
					<a href="#" title="Change Theme" class="lightsp"></a>
				</div>
			</div>
			<a href="#" title="Extra Controls" class="toggle entypo-dot-3"></a>
		</div>
		<div class="cm-pad-30 display-center-general-container" style="height: calc(100% - 94px);">				
			<div id="spritz" class="spritz large-12 medium-12 small-12 w-rounded-30 ">
				<div id="spritz_word" class="spritz-word"></div>									
			</div>				
		</div>			
		<div class="settings position-absolute abs-b-40 abs-l-30 abs-r-30">
			<div class="background-white w-rounded-5 large-12 medium-12 small-12 w-shadow" style="height: 5px;">
				<div id="spritz_progress" class="progress-bar w-rounded-5"></div>
			</div>
			<div class="controls settings-controls">
				<span class="interaction float-left">					
					<a id="spritz_back" href="#" title="Jog Back" class="back entypo-left-open"></a>					
					<a id="spritz_pause" href="#" title="Pause/Play" class="pause entypo-pause"></a>					
					<a id="spritz_forward" href="#" title="Jog Forward" class="forward entypo-right-open"></a>
				</span>
				<span class="speed float-right">
					<a id="spritz_slower" href="#" title="Slow Down" class="slower entypo-fast-backward"></a>
					<input id="spritz_wpm" type="number" value="300" step="50" min="50" class="wpm"/>
					<a id="spritz_faster" href="#" title="Speed Up" class="faster entypo-fast-forward"></a>
				</span>														
			</div>
		</div>
	</div>
	<!-- TEXTO -->
	<div class="tab height-100 large-12 medium-12 small-12 background-gray cm-pad-20">
		
		<form id="pdfForm" enctype="multipart/form-data">
		
			<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">			
				<div class="w-shadow w-rounded-15">								
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Enviar PDF</div>													
							<div class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l">
								<input type="file" id="pdfFile" accept=".pdf" class="float-left border-none large-12 medium-12 small-12 required" required>							
							</div>
						</div>	
					</div>
				</div>
			</div>
			<input type="hidden" id="coverImageData" name="coverImageData">			
		
		
			<div class="large-12 medium-12 small-12 cm-pad-20-t cm-pad-5-b">			
				<div class="w-shadow w-rounded-15">								
					<button type="submit" class="border-none cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
						<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 text-left">
							<span class="fa-stack orange" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-save fa-stack-1x dark"></i>					
							</span>						
							Salvar
						</div>										
					</button>
				</div>
			</div>		
		</form>			
		
		<div class="words display-none">
			<div class="controls words-controls">
				<a id="spritz_select" href="#" title="Select All" class="select entypo-doc-text"></a>
				<a id="spritz_refresh" href="#" title="Refresh Text" class="refresh entypo-cycle"></a>
				<a id="spritz_expand" href="#" title="Text Area Resize" class="expand entypo-resize-full"></a>
			</div>			
			<textarea id="spritz_words" class="demo-words">Leia textos mais rapidamente com o Velox. Nossa ferramenta permite que você leia sem saltar os olhos, garantindo mais foco e rapidez.  Treine e torne-se um verdadeiro velocista da leitura!  Copie o texto a partir de arquivos do seu computador ou de páginas da internet e cole aqui neste campo. Então, clique em play para iniciar a reprodução.</textarea>
		</div>
		<hr>
		<?php
		$documents = search('app', 'wa0026_books', 'id,cp,tt', 'us = "'.$_SESSION['wz'].'"');
		?>
		<p class="fs-f font-weight-500 cm-pad-20 cm-pad-0-b">Sua biblioteca</p>
		<div id="library" class="cm-mg-20 cm-mg-0-h large-12 medium-12 small-12 js-flickity" data-flickity-options='{ "cellAlign": "left", "imagesLoaded": true, "percentPosition": false, "prevNextButtons": false, "pageDots": false, "fullscreen": false }'>    
			<?php
			foreach($documents as $doc){            
			?>        
			<div class="large-1 medium-2 small-4 float-left w-color-bl-to-or cm-pad-20-l cm-pad-5-t doc-item" data-doc-id="<?php echo $doc['id']; ?>">													
				
				<img class="w-rounded-10 w-shadow-2 background-white z-index-2 cm-mg-5-b" src="data:image/jpg;base64,<?php echo $doc['cp']; ?>" />				
				<div class="large-12 medium-12 small-12 text-ellipsis text-center">
					<?php echo htmlspecialchars($doc['tt']); ?>
				</div>
				
			</div>        
			<?php            
			}
			?>        
			<div class="clear"></div>
		</div>

	</div>		
	<div id="alert" class="alert z-index-5 w-shadow-1 w-rounded-20 cm-pad-10 cm-mg-20 position-absolute abs-t-0" style="background: #F7F8D1;"></div>				

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.8.335/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.8.335/pdf.worker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>   
<script>
	$(document).ready(function() {
		$('#pdfForm').submit(function(e) {
			e.preventDefault();
			var file = $('#pdfFile')[0].files[0];
			if (file) {
				var reader = new FileReader();
				reader.onload = function(e) {
					var typedarray = new Uint8Array(e.target.result);
					pdfjsLib.getDocument(typedarray).promise.then(function(pdf) {
						var numPages = pdf.numPages;
						var fullText = '';
						var pagePromises = [];

						// Obter o título do PDF (usaremos o nome do arquivo)
						var pdfTitle = file.name;

						// Obter a primeira página (capa)
						pdf.getPage(1).then(function(page) {
							var viewport = page.getViewport({ scale: 1 });
							var originalWidth = viewport.width;
							var originalHeight = viewport.height;

							// Calcular a escala para ajustar a imagem
							var maxDimension = 250; // Máximo de 250px para largura ou altura
							var scale = 1;

							if (originalWidth > originalHeight) {
								if (originalWidth > maxDimension) {
									scale = maxDimension / originalWidth;
								}
							} else {
								if (originalHeight > maxDimension) {
									scale = maxDimension / originalHeight;
								}
							}

							viewport = page.getViewport({ scale: scale });

							var canvas = document.createElement('canvas');
							var context = canvas.getContext('2d');
							canvas.height = viewport.height;
							canvas.width = viewport.width;
							var renderContext = {
								canvasContext: context,
								viewport: viewport
							};
							page.render(renderContext).promise.then(function() {
								// Ajustar a qualidade da imagem
								var quality = 0.8; // Qualidade entre 0 e 1
								var dataURL = canvas.toDataURL('image/jpeg', quality);
								// Exibir a imagem no elemento <img>
								$('#coverImage').attr('src', dataURL);

								// Após obter a imagem e o texto, enviar os dados ao servidor
								// Precisamos garantir que o texto completo já foi extraído
								// Modificaremos o código para enviar os dados após a extração do texto

								// Extrair texto de todas as páginas
								for (var i = 1; i <= numPages; i++) {
									pagePromises.push(pdf.getPage(i).then(function(page) {
										return page.getTextContent().then(function(textContent) {
											var text = '';
											textContent.items.forEach(function(item) {
												text += item.str + ' ';
											});
											return text;
										});
									}));
								}

								Promise.all(pagePromises).then(function(texts) {
									fullText = texts.join('\n');
									fullText = adjustText(fullText); // Ajustar texto
									$('#spritz_words').val(fullText);
									spritz_refresh();
									// Adicionar um pequeno atraso para garantir que o conteúdo foi renderizado
									setTimeout(function() {
										// Fazer o scroll para o topo
										$('.container').animate({ scrollTop: 0 }, 'slow');
									}, 100);

									// Enviar dados ao servidor
									$.ajax({
										url: 'core/backengine/wa0026/save_book.php', // O script PHP que irá processar os dados
										type: 'POST',
										data: {
											title: pdfTitle,
											coverImage: dataURL,
											contentText: fullText
										},
										success: function(response) {
											// Lidar com a resposta do servidor
											console.log(response);
										},
										error: function(jqXHR, textStatus, errorThrown) {
											console.error('Erro ao salvar os dados: ' + textStatus, errorThrown);
										}
									});
								});
							});
						});
					});
				};
				reader.readAsArrayBuffer(file);
			}
		});
		
		
		// Evento de clique nos itens do documento
		$(document).on('click', '.doc-item', function() {
			var docId = $(this).data('doc-id');

			// Fazer a requisição AJAX para obter o conteúdo e a imagem
			$.ajax({
				url: 'core/backengine/wa0026/get_book.php',
				type: 'POST',
				dataType: 'json',
				data: { id: docId },
				success: function(response) {
					if (response.success) {
						// Atualizar o campo de texto com o conteúdo do documento
						$('#spritz_words').val(response.content);

						// Atualizar a imagem de capa
						$('#coverImage').attr('src', 'data:image/jpeg;base64,' + response.coverImage);

						// Acionar o refresh (supondo que a função 'spritz_refresh' exista)
						spritz_refresh();

						// Adicionar um pequeno atraso para garantir que o conteúdo foi renderizado
						setTimeout(function() {
							// Fazer o scroll para o topo
							$('.container').animate({ scrollTop: 0 }, 'slow');
						}, 100);

					} else {
						alert('Erro ao carregar o documento: ' + response.message);
					}
				},
				error: function(xhr, status, error) {
					alert('Erro na requisição: ' + error);
				}
			});
		});

	});




	
	function adjustText(text) {
		// Dividir o texto em palavras
		var words = text.split(/\s+/);
		var adjustedText = '';
		// Percorrer cada palavra
		for (var i = 0; i < words.length; i++) {
			// Adicionar espaço antes da palavra, exceto na primeira palavra
			if (i > 0) {
				adjustedText += ' ';
			}
			adjustedText += words[i];
		}

		return adjustedText;
	}

	
	var $wpm = $('#spritz_wpm');
	var interval = 60000/$wpm.val();  
	var paused = false;
	var $space = $('#spritz_word');
	var i = 0;
	var night = false;
	var zoom = 1;
	var autosave = false;
	var $words = $('#spritz_words');
	var local_spritz = {};

	function words_load() {
	  if (!localStorage.jqspritz) {
		words_set();
		word_show(0);
		word_update();
		spritz_pause(true);
	  } else {
		local_spritz = JSON.parse(localStorage['jqspritz']);
		$words.val(local_spritz.words);
		i = local_spritz.word;
		if (local_spritz.night) {
		  night = true
		  $('html').addClass('night');
		};
		if (local_spritz.autosave) {
		  autosave = true;
		  $('html').addClass('autosave');
		  $('#autosave_checkbox').prop('checked', true);
		};
		$wpm.val(local_spritz.wpm); 
		interval = 60000/local_spritz.wpm;
		spritz_zoom(0);
		words_set();
		word_show(i);
		word_update();
		spritz_pause(true);
		spritz_alert('loaded');
	  }  
	}
	function words_save() {
	  local_spritz = {
		word: i,
		words: $words.val(),
		wpm: $wpm.val(),
		night: night,
		autosave: autosave,
		zoom: zoom
	  };
	  localStorage['jqspritz'] = JSON.stringify(local_spritz);
	  if (!autosave) {
		spritz_alert('saved');
	  } else {
		button_flash('save', 500);
	  }
	}


	/* TEXT PARSING */
	function words_set() {
	  var wordsValue = $words.val();
	  if (wordsValue) {
		var trimmedWords = wordsValue.trim().replace(/([-—])(\w)/g, '$1 $2').replace(/[\r\n]/g, ' {linebreak} ').replace(/[ \t]{2,}/g, ' ').split(' ');
		for (var j = 1; j < trimmedWords.length; j++) {
		  trimmedWords[j] = trimmedWords[j].replace(/{linebreak}/g, '   ');
		}
		words = trimmedWords;
	  } else {
		words = [];
	  }
	}

	/* ON EACH WORD */
	function word_show(i) {
	  if (words && words.length > 0 && i >= 0 && i < words.length) {
		$('#spritz_progress').width(100 * i / words.length + '%');
		var word = words[i];
		var stop = Math.round((word.length + 1) * 0.4) - 1;
		$space.html('<div>' + word.slice(0, stop) + '</div><div>' + word[stop] + '</div><div>' + word.slice(stop + 1) + '</div>');
	  } else {
		// Trate o caso em que não há palavras para mostrar ou o índice está fora do intervalo
		console.error('Não há palavras para mostrar ou o índice está fora do intervalo.');
	  }
	}


	function word_next() {
	  i++;
	  word_show(i);
	}
	function word_prev() {
	  i--;
	  word_show(i);
	}

	/* ITERATION FUNCTION */
	function word_update() {
	  spritz = setInterval(function() {
		word_next();
		if (i+1 == words.length) {
		  setTimeout(function() {
			$space.html('');
			spritz_pause(true);
			i = 0;
			word_show(0);
		  }, interval);
		  clearInterval(spritz);
		};
	  }, interval);
	} 

	/* PAUSING FUNCTIONS */
	function spritz_play() {
		word_update();
		paused = false;
		$('html').removeClass('paused');
		// Adiciona a classe animate ao container para ativar as animações
		$('.out-top').css('animation', 'rotate 20s linear infinite');
        $('.in-top').css('animation', 'rotate 10s linear infinite');
        $('.out-bottom').css('animation', 'rotate 25s linear infinite');
        $('.in-bottom').css('animation', 'rotate 15s linear infinite');
	}
	function spritz_pause(ns) {
		if (!paused) {
			clearInterval(spritz);
			paused = true;
			$('html').addClass('paused');
			// Remove a classe animate do container para pausar as animações
			$('.out-top, .in-top, .out-bottom, .in-bottom').css('animation', 'none');
			if (autosave && !ns) {
				words_save();
			}
		}
	}	
	function spritz_flip() {
		if (paused) {
			spritz_play();
		} else {
			spritz_pause();
		};
	}

	/* SPEED FUNCTIONS */
	function spritz_speed() {
	  interval = 60000/$('#spritz_wpm').val();
	  if (!paused) {
		clearInterval(spritz);
		word_update();
	  };
	  $('#spritz_save').removeClass('saved loaded');
	}
	function spritz_faster() {
	  $('#spritz_wpm').val(parseInt($('#spritz_wpm').val())+50);
	  spritz_speed();
	}
	function spritz_slower() {
	  if ($('#spritz_wpm').val() >= 100) {
		$('#spritz_wpm').val(parseInt($('#spritz_wpm').val())-50);
	  }
	  spritz_speed();
	}

	/* JOG FUNCTIONS */
	function spritz_back() {
	  spritz_pause();
	  if (i >= 1) {
		word_prev();
	  };
	}
	function spritz_forward() {
	  spritz_pause();
	  if (i < words.length) {
		word_next();
	  };
	}

	/* WORDS FUNCTIONS */
	function spritz_zoom(c) {
	  zoom = zoom+c
	  $('#spritz').css('font-size', zoom+'em');
	}
	function spritz_refresh() {
	  clearInterval(spritz);
	  words_set(); 
	  i = 0;
	  spritz_pause();
	  word_show(0);
	};
	function spritz_select() {
	  $words.select();
	};
	function spritz_expand() {
	  $('html').toggleClass('fullscreen');
	}

	/* AUTOSAVE FUNCTION */
	function spritz_autosave() {
	  $('html').toggleClass('autosave');
	  autosave = !autosave;
	  if (autosave) {
		$('#autosave_checkbox').prop('checked', true);
	  } else {
		$('#autosave_checkbox').prop('checked', false);
	  }
	};

	/* ALERT FUNCTION */
	function spritz_alert(type) {
	  var msg = '';
	  switch (type) {
		case 'loaded':
		  msg = 'Dados carregados do armazenamento local';
		  break;
		case 'saved':
		  msg = 'As palavras, a posição e as configurações foram salvas no armazenamento local para a próxima vez que você utilizar o Spritz';
		  break;
	  }
	  $('#alert').text(msg).fadeIn().delay(5000).fadeOut();
	}



	/* CONTROLS */
	$('#spritz_wpm').on('input', function() {
	  spritz_speed();
	});
	$('.controls').on('click', 'a, label', function() {
	  switch (this.id) {
		case 'spritz_slower':
		  spritz_slower(); break;
		case 'spritz_faster':
		  spritz_faster(); break;
		case 'spritz_save':
		  words_save(); break;
		case 'spritz_pause':
		  spritz_flip(); break;
		case 'spritz_smaller':
		  spritz_zoom(-0.1); break;
		case 'spritz_bigger':
		  spritz_zoom(0.1); break;
		case 'spritz_autosave':
		  spritz_autosave(); break;
		case 'spritz_refresh':
		  spritz_refresh(); break;
		case 'spritz_select':
		  spritz_select(); break;
		case 'spritz_expand':
		  spritz_expand(); break;
	  };
	  return false;
	});
	$('.controls').on('mousedown', 'a', function() {
	  switch (this.id) {
		case 'spritz_back':
		  spritz_jog_back = setInterval(function() {
			spritz_back();
		  }, 100);
		  break;
		case 'spritz_forward':
		  spritz_jog_forward = setInterval(function() {
			spritz_forward();
		  }, 100);
		  break;
	  };
	});
	$('.controls').on('mouseup', 'a', function() {
	  switch (this.id) {
		case 'spritz_back':
		  clearInterval(spritz_jog_back); break;
		case 'spritz_forward':
		  clearInterval(spritz_jog_forward); break;
	  };
	});

	/* KEY EVENTS */
	function button_flash(btn, time) {
	  var $btn = $('.controls a.'+btn);
	  $btn.addClass('active');
	  if (typeof(time) === 'undefined') time = 100;
	  setTimeout(function() {
		$btn.removeClass('active');
	  }, time);
	}
	$(document).on('keyup', function(e) {
	  if (e.target.tagName.toLowerCase() != 'body') {
		return;
	  };
	  switch (e.keyCode) {
		case 32:
		  spritz_flip(); button_flash('pause'); break;
		case 37:
		  spritz_back(); button_flash('back'); break;
		case 38:
		  spritz_faster(); button_flash('faster'); break;
		case 39:
		  spritz_forward(); button_flash('forward'); break;
		case 40:
		  spritz_slower(); button_flash('slower'); break;
	  };
	});
	$(document).on('keydown', function(e) {
	  if (e.target.tagName.toLowerCase() != 'body') {
		return;
	  };
	  switch (e.keyCode) {
		case 37:
		  spritz_back(); button_flash('back'); break;
		case 39:
		  spritz_forward(); button_flash('forward'); break;
	  };
	});



	/* INITIATE */
	words_load();

	/* LIGHT/DARK THEME */
	$('.lightsp').on('click', function() {
		console.log("Change theme");
	  $('html').toggleClass('night');
	  night = !night;
	  return false;
	});

	$('a.toggle').on('click', function() {
	  $(this).siblings('.togglable').slideToggle();
	  return false;
	});
</script>