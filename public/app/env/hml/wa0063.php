<div class="large-12 height-100 position-relative cm-pad-0 cm-mg-0 overflow-hidden">
	<style>
	/* Centralizando o botão */
	.button-container {
		display: flex;
		justify-content: center;
		align-items: center;
		height: auto;
		position: absolute;
		bottom: 40px;
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
		position: relative;
	}

	/* Efeito ao passar o mouse */
	.round-button:hover {
		background-color: lightgray; /* Altera a cor ao passar o mouse */
	}
	</style>

	<!-- Área do vídeo -->
	<video id="camera" class="tab height-100 large-12 medium-12 small-12 position-relative border-none cm-pad-0" autoplay></video>

	<!-- Botão redondo estilo câmera do iPhone -->
	<div class="button-container position-absolute abs-b-0 large-12">
		<button onclick="tirarFoto()" class="round-button w-shadow-2"></button>
	</div>

	<!-- Selecionar câmeras -->
	<select id="selecionarCamera" class="w-shadow-2 position-absolute abs-t-20 abs-l-20 centered large-6 w-rounded-10 input-border border-like-input cm-pad-10 "></select>

	<!-- Canvas para exibir a foto tirada -->
	<canvas id="canvasFoto" class="position-absolute abs-b-20 abs-l-20 w-rounded-10" style="height: 75px; width: 100px;"></canvas>

	<script>
		const video = document.getElementById('camera');
		const canvas = document.getElementById('canvasFoto');
		const selecionarCamera = document.getElementById('selecionarCamera');
		
		async function configurarCamera() {
			try {
				// Solicita acesso à câmera
				const stream = await navigator.mediaDevices.getUserMedia({ video: true });
				video.srcObject = stream;

				// Obtém as câmeras disponíveis
				const dispositivos = await navigator.mediaDevices.enumerateDevices();
				const cameras = dispositivos.filter(dispositivo => dispositivo.kind === 'videoinput');

				// Popula o dropdown com as câmeras disponíveis
				cameras.forEach(camera => {
					const opcao = document.createElement('option');
					opcao.value = camera.deviceId;
					opcao.text = camera.label || `Câmera ${selecionarCamera.options.length + 1}`;
					selecionarCamera.appendChild(opcao);
				});
			} catch (erro) {
				console.error('Erro ao acessar a câmera: ', erro);
				alert('Não foi possível acessar a câmera. Verifique as permissões.');
			}
		}


		async function tirarFoto() {
			// Captura a imagem do vídeo e desenha no canvas
			const contexto = canvas.getContext('2d');
			contexto.drawImage(video, 0, 0, canvas.width, canvas.height);

			// Converter a imagem para URL base64
			const fotoDataUrl = canvas.toDataURL('image/jpeg');

			// Exibe a imagem capturada na página
			const img = new Image();
			img.src = fotoDataUrl;
			document.body.appendChild(img);
		}

		// Atualiza a câmera quando o usuário seleciona uma nova câmera
		selecionarCamera.addEventListener('change', async () => {
			const deviceId = selecionarCamera.value;
			const stream = await navigator.mediaDevices.getUserMedia({ video: { deviceId } });
			video.srcObject = stream;
		});

		// Configura a câmera ao carregar a página
		window.onload = function() {
			configurarCamera();
		};
	</script>
</div>