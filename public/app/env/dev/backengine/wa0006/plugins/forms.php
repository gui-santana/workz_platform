<?

if(isset($_POST['vr']) && !empty($_POST['vr'])){		
	$vr = json_decode($_POST['vr'], true);			
	?>
	<style>
	/* Corpo do Formulário */


	/* Inputs */
	.input-box input[type="radio"],
	.input-box input[type="checkbox"] {
		margin-right: 8px;
		accent-color: #ff5c8d; /* Cor do checkbox e radio */
		cursor: pointer;
	}

	.input-box {
		text-align: left;
		font-size: 16px;
		line-height: 1.6;
		color: #555;
		margin: 10px 0;
	}

	/* Botões de Navegação */
	#paginationControls {
		text-align: center;
		margin-top: 20px;
	}

	#paginationControls button {
		background: linear-gradient(to right, #ff5c8d, #ff9966);
		border: none;
		color: white;
		padding: 12px 20px;
		font-size: 16px;
		font-weight: 500;
		border-radius: 25px;
		cursor: pointer;
		margin: 5px;
		box-shadow: 0px 4px 8px rgba(255, 92, 141, 0.4);
		transition: all 0.3s ease;
	}

	#paginationControls button:hover {
		transform: translateY(-3px);
		box-shadow: 0px 6px 12px rgba(255, 92, 141, 0.5);
	}

	#paginationControls button:disabled {
		background: #ccc;
		cursor: not-allowed;
		box-shadow: none;
	}

	/* Efeito de Fade */
	#formContainer.fade {
		opacity: 0.6;
		pointer-events: none;
		transition: opacity 0.3s ease;
	}
	</style>		
	<div id="content" class="w-square-content position-relative w-rounded-20 w-shadow-1 cm-pad-50-t overflow-auto">								
	<div class="w-rounded-20-b large-12 medium-12 small-12 overflow-y-auto" style="">																														
	<div id="formContainer" class="cm-pad-15 text-center">				
	<?php
	for ($i = 0; $i < $vr['quantidadeCampos']; $i++) {
		echo '<div class="question-page large-12 medium-12 small-12 cm-pad-10 w-rounded-15 w-shadow background-white-transparent" id="question'.$i.'">';
		
		// Garante que 'card_name' seja um array
		$cardName = isset($vr['card_name']) ? (array) $vr['card_name'] : [];
		echo isset($cardName[$i]) ? '<h3 class="cm-mg-10-b">'.$cardName[$i].'</h3>' : 'Sem nome';

		// Garante que 'card_type' seja um array
		$cardType = isset($vr['card_type']) ? (array) $vr['card_type'] : [];
		
		
		if($cardType[$i] == 1 || $cardType[$i] == 2){
			
			// Garante que 'card_1_option' seja um array
			$options = isset($vr['card_' . ($i + 1) . '_option']) ? (array) $vr['card_' . ($i + 1) . '_option'] : [];
			$cardTypo = $cardType[$i] == 1 ? 'radio' : 'checkbox';
			
			foreach ($options as $key => $opt) {
				
				// Garante que 'card_1_option_selected' seja um array
				$selected = isset($vr['card_' . ($i + 1) . '_option_selected']) ? (array) $vr['card_' . ($i + 1) . '_option_selected'] : [];

				if (in_array(($key + 1), $selected)) {
					echo ' - selected.';
				}
				
				echo '<p><input type="'.$cardTypo.'" id="q'.$i.'_'.$key.'" name="q'.$i.'[]" value="'.$opt.'" class="required"> <label for="q'.$i.'_'.$key.'">'.$opt.'</label></p>';				
				
			}	
			
		}
			
		
		
		echo '</div>';
	}
	?>
		<!-- Botões de navegação -->
		<div id="paginationControls">
			<button id="prevButton" onclick="prevQuestion()" style="display: none;">Anterior</button>
			<button id="nextButton" onclick="nextQuestion()">Próximo</button>
			<button id="submitButton" onclick="submitForm()" style="display: none;">Enviar</button>
		</div>

	</div>
	<script>
		(function() {
			let currentQuestion = 0;

			function showQuestion(index) {
				const pages = document.querySelectorAll('.question-page');
				const prevButton = document.getElementById('prevButton');
				const nextButton = document.getElementById('nextButton');
				const submitButton = document.getElementById('submitButton');

				// Esconde todas as perguntas
				pages.forEach((page, i) => {
					page.style.display = i === index ? 'block' : 'none';
				});

				// Atualiza botões de navegação
				prevButton.style.display = index === 0 ? 'none' : 'inline-block';
				nextButton.style.display = index === pages.length - 1 ? 'none' : 'inline-block';
				submitButton.style.display = index === pages.length - 1 ? 'inline-block' : 'none';
			}
			
			// Avançar para a próxima pergunta
			window.nextQuestion = function() {				
				const pages = document.querySelectorAll('.question-page');

				// Verifica se a pergunta atual foi preenchida
				const currentInputs = pages[currentQuestion].querySelectorAll('.required');
				if (!validateInputs(currentInputs)) {
					alert('Por favor, selecione uma opção antes de continuar.');
					return;
				}

				if (currentQuestion < pages.length - 1) {
					currentQuestion++;
					showQuestion(currentQuestion);
				}
			}

			// Retornar para a pergunta anterior
			window.prevQuestion = function() {				
				if (currentQuestion > 0) {
					currentQuestion--;
					showQuestion(currentQuestion);
				}
			}

			// Valida entradas (checkbox e radio)
			function validateInputs(inputs) {
				if (!inputs || inputs.length === 0) return true; // Sem inputs para validar
				if (inputs[0].type === 'radio') {
					return Array.from(inputs).some(input => input.checked); // Pelo menos um selecionado
				} else if (inputs[0].type === 'checkbox') {
					return Array.from(inputs).some(input => input.checked); // Pelo menos um selecionado
				}
				return true;
			}

			// Submeter o formulário
			function submitForm() {
				const formData = {};
				const inputs = document.querySelectorAll('.question-page input');

				// Coleta os dados
				inputs.forEach(input => {
					if ((input.type === 'radio' || input.type === 'checkbox') && !input.checked) {
						return; // Ignora inputs não marcados
					}

					if (input.type === 'checkbox') {
						// Coleta múltiplos valores para checkbox
						if (!formData[input.name]) {
							formData[input.name] = [];
						}
						formData[input.name].push(input.value);
					} else {
						formData[input.name] = input.value;
					}
				});

				// Enviar os dados (por exemplo, via fetch)
				console.log('Dados enviados:', formData);
				alert('Formulário enviado com sucesso!');
			}

			// Mostra a primeira pergunta ao carregar a página
			showQuestion(currentQuestion);
			
			
			
		})();
	</script>
	</div>
	</div>		
<?
}elseif($_GET['qt'] == 1){
?>
	<!-- Etapa 4: Conteúdo -->
	<div class="large-12 medium-12 small-12 text-center gray">
		<h2>Nova publicação</h2>
	</div>

	<div id="formTestResponse" class="large-12 medium-12 small-12"></div>
	<div id="formulario" class="cm-pad-10-h">
		<input type="hidden" id="quantidadeCampos" name="quantidadeCampos" value="0">				
	</div>
	<div class="cm-pad-10 cm-pad-20-h">
		<div class="pointer large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis w-bkg-wh-to-gr w-rounded-15 w-shadow">
			<div id="adicionarCampo" class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-plus fa-stack-1x fa-inverse dark"></i>
				</span>
				Adicionar Pergunta
			</div>
		</div>				
	</div>
	<div id="formOpt" class="cm-pad-10 cm-pad-20-h">
	</div>

	<script>
	//alert("Script Inline Carregado!");
	(function() {
		let questionIndex = 0;

		const quantidadeCampos = document.getElementById('quantidadeCampos');
		// Verifica se o elemento existe antes de adicionar evento
		const addButton = document.getElementById('adicionarCampo');
		if (addButton) {
			addButton.addEventListener('click', () => {
				questionIndex++;
				const container = document.getElementById('formulario');
				const newQuestion = createQuestionCard(questionIndex);						
				container.appendChild(newQuestion);
				document.getElementById('quantidadeCampos').value = questionIndex;
				createSubmitButton();
			});
		} else {
			console.warn('Elemento #adicionarCampo não encontrado.');
		}
		
		

		// Delegação de evento para múltiplas ações
		document.getElementById('formulario').addEventListener('click', function(event) {
			if (event.target && event.target.matches('button.remove')) {
				event.stopPropagation();
				removeQuestion(event.target);
			}
		});

		// Cria nova Pergunta com select para escolher tipo
		function createQuestionCard(index) {
			const div = document.createElement('div');
			div.classList.add('question-card');
			div.innerHTML =																			
				'<div class="cm-pad-10 large-12 medium-12 small-12">'+																								
					'<div class="w-shadow w-rounded-15">'+
						'<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">'+
							'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">'+
								'<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Pergunta</div>'+
								'<textarea name="card_name" placeholder="Escreva o enunciado" class="float-left border-none large-10 medium-10 small-8 required cm-pad-10 cm-pad-5-l" style="min-height: 83.18px; line-height: 1.5em;"></textarea>'+
							'</div>'+
						'</div>'+
						'<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">'+
							'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">'+
								'<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Tipo</div>'+
								'<select name="card_type" onchange="formContent(' + index + ', this.value)" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px">' +
									'<option value="" disabled selected>Escolha o tipo</option>' +
									'<option value="0">Dissertativa</option>' +
									'<option value="1">Múltipla Escolha</option>' +
									'<option value="2">Caixas de Seleção</option>' +
									'<option value="3">Data</option>' +
								'</select>'+
							'</div>'+
						'</div>'+																			
						'<div id="content' + index + '" class="large-12 medium-12 small-12"></div>' +							
						'<div onclick="removeQuestion(this)" class="pointer large-12 medium-12 small-12 cm-pad-5 border-t-input position-relative text-ellipsis w-bkg-wh-to-gr w-rounded-15-b">'+
							'<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">'+
								'<span class="fa-stack orange fs-b cm-mg-5-r" style="vertical-align: middle;">'+
									'<i class="fas fa-circle fa-stack-2x light-gray"></i>'+
									'<i class="fas fa-minus fa-stack-1x fa-inverse dark"></i>'+
								'</span>Remover Pergunta</a>'+
							'</div>'+
						'</div>'+
					'</div>'+							
				'</div>';
			return div;
		}
		
		function createSubmitButton(){
			event.stopPropagation();  // Evita que o clique feche menus laterais
			const quantidadeCampos = document.getElementById('quantidadeCampos');															
			if (quantidadeCampos.value > 0) {
				if (!document.querySelector('#submitButton')) {							
					let container = document.getElementById('formOpt');
					container.innerHTML +=
					'<div id="submitButton" class="pointer large-12 medium-12 small-12 cm-pad-5 position-relative text-ellipsis w-bkg-wh-to-gr w-rounded-15 w-shadow">'+
						'<div onclick="formValidator2(`formulario`, `partes/resources/modal_content/editor.php?qt=1`, `displayContent`);" class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">'+
							'<span class="fa-stack orange" style="vertical-align: middle;">'+
								'<i class="fas fa-circle fa-stack-2x light-gray"></i>'+
								'<i class="fas fa-paper-plane fa-stack-1x fa-inverse dark"></i>'+
							'</span>'+
							'Enviar questionário'+
						'</div>'+
					'</div>';
				}						
			} else {						
				if (document.querySelector('#submitButton')) {
					document.getElementById('formOpt').removeChild(document.getElementById('submitButton'));
				}
			}					
		}
		
		// Remove Pergunta
		window.removeQuestion = function(el) {
			event.stopPropagation();  // Evita que o clique feche menus laterais
			el.parentElement.parentElement.parentElement.remove();
			questionIndex--;
			document.getElementById('quantidadeCampos').value = questionIndex;	
			createSubmitButton();					
		}							

		// Função para manipular conteúdo da Pergunta (caixas de seleção, dissertativas, etc.)
		window.formContent = function(n, tp) {
			let k = 1;
			let ds = document.getElementById('content' + n);
			if (tp == 0) {
				ds.innerHTML = '';
			} else if (tp == 1) {
				ds.innerHTML =  
					'<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white">'+
						'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container border-t-input">'+
							'<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Opção ' + k + '</div>'+																		
							'<input class="float-left border-none large-9 medium-9 small-7 cm-pad-5-l required" name="card_' + n + '_option"  placeholder="Opção ' + k + '" style="height: 41.59px" />' +
							'<input class="float-left border-none large-1 medium-1 small-1 cm-mg-0" name="card_' + n + '_option_selected" type="radio" value="' + k + '" />' +
							'<div class="clear"></div>'+
						'</div>'+								
					'</div>'+
					'<div id="' + n + 'addOption" class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input" style="height: 41.59px">' +								
						'<div class="large-6 medium-6 small-6 text-center centered-v border-r-input float-left pointer w-bkg-wh-to-gr" onclick="addOption(event, ' + n + ', ' + tp + ', ' + k + ')" style="height: 41.59px">Adicionar ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
						'<div class="large-6 medium-6 small-6 text-center centered-v float-left gray"  style="height: 41.59px">Remover ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
					'</div>';
			} else if (tp == 2) {
				ds.innerHTML =  
					'<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white">'+
						'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container border-t-input">'+
							'<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Caixa ' + k + '</div>'+																		
							'<input class="float-left border-none large-9 medium-9 small-7 cm-pad-5-l required" name="card_' + n + '_option"  placeholder="Caixa ' + k + '" style="height: 41.59px" />' +
							'<input class="float-left border-none large-1 medium-1 small-1 cm-mg-0" name="card_' + n + '_option_selected" type="checkbox" value="' + k + '" />' +
							'<div class="clear"></div>'+
						'</div>'+								
					'</div>'+
					'<div id="' + n + 'addOption" class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input" style="height: 41.59px">' +
						'<div class="large-6 medium-6 small-6 text-center centered-v border-r-input float-left pointer w-bkg-wh-to-gr" onclick="addOption(event, ' + n + ', ' + tp + ', ' + k + ')" style="height: 41.59px">Adicionar ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
						'<div class="large-6 medium-6 small-6 text-center centered-v float-left gray"  style="height: 41.59px">Remover ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
					'</div>';							
			} else if (tp == 3) {
				ds.innerHTML =  
					'<div class="w-rounded-5 large-12 medium-12 small-12 cm-pad-10 font-weight-600 fs-a uppercase" style="background: #F7F8D1;">'+
						'<i class="fas fa-info-circle cm-mg-5-r"></i>Informe a data correta, caso exista'+
					'</div>'+
					'<div class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative">'+														
						'<input name="card_' + n + '_option" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="date" class="cm-pad-5-b float-right border-none background-transparent large-12 medium-12 small-12" placeholder="dd/mm/aaaa"></input>'+
						'<div class="clear"></div>'+
					'</div>';
			}
		};

		// Adiciona opções dinamicamente
		window.addOption = function(event, n, tp, k) {
			event.stopPropagation();  // Evita que o clique feche menus laterais
			k++;
			let input = 
				'<div id="' + n + '_' + k + '" class="large-12 medium-12 small-12 position-relative text-ellipsis background-white">'+
					'<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container border-t-input">'+
						'<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">' + (tp == 1 ? 'Opção ' : 'Caixa ') + k + '</div>'+																		
						'<input class="float-left border-none large-9 medium-9 small-7 cm-pad-5-l required" name="card_' + n + '_option"  placeholder="' + (tp == 1 ? 'Opção ' : 'Caixa ') + k + '" style="height: 41.59px" />' +
						'<input class="float-left border-none large-1 medium-1 small-1 cm-mg-0" name="card_' + n + '_option_selected" type="' + (tp == 1 ? 'radio' : 'checkbox') + '" value="' + k + '" />' +
						'<div class="clear"></div>'+								
					'</div>'+								
				'</div>'+
				'<div id="' + n + 'addOption" class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input" style="height: 41.59px">' +							
					'<div class="large-6 medium-6 small-6 text-center centered-v border-r-input float-left '+ (k < 5 ? 'pointer w-bkg-wh-to-gr' : 'gray') + '" '+ (k < 5 ? 'onclick="addOption(event, ' + n + ', ' + tp + ', ' + k + ')"' : '') + '  style="height: 41.59px">Adicionar ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
					'<div class="large-6 medium-6 small-6 text-center centered-v float-left pointer w-bkg-wh-to-gr" onclick="remOption(event, ' + n + ', \'' + n + '_' + k + '\', ' + tp + ', ' + k + ')" style="height: 41.59px">Remover ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +							
				'</div>';					
				document.getElementById('content' + n).removeChild(document.getElementById(n + 'addOption'));
				document.getElementById('content' + n).innerHTML += input;
		};

		// Remove uma opção
		window.remOption = function(event, n, element, tp, k) {
			k--;
			event.stopPropagation();  // Evita que o clique feche o menu
			document.getElementById(element).remove();														
			let addOption = 
			'<div id="' + n + 'addOption" class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white border-t-input" style="height: 41.59px">' +					
				'<div class="large-6 medium-6 small-6 text-center centered-v border-r-input float-left pointer w-bkg-wh-to-gr" onclick="addOption(event, ' + n + ', ' + tp + ', ' + k + ')" style="height: 41.59px">Adicionar ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +
				'<div class="large-6 medium-6 small-6 text-center centered-v border-r-input float-left '+ (k > 1 ? 'pointer w-bkg-wh-to-gr' : 'gray') + '" '+ (k > 1 ? 'onclick="remOption(event, ' + n + ', \'' + n + '_' + k + '\', ' + tp + ', ' + k + ')"' : '') + '  style="height: 41.59px">Remover ' + (tp == 1 ? 'Opção ' : 'Caixa ') + '</div>' +																
			'</div>';					
			document.getElementById('content' + n).removeChild(document.getElementById(n + 'addOption'));
			document.getElementById('content' + n).innerHTML += addOption;
		};

	})();						
	</script>