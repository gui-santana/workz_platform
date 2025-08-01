<script>
(() => {
  'use strict';
  
  /*** Variáveis e Inicialização ***/
  let canvas, ctx, media, isVideo, drawing, elements, draggingElement, resizingElement, offsetX, offsetY, isDrawing, drawingPath;
  let videoElement;
  let brushColor = '#000000';
  let brushSize = 4;
  
  let undoStack = [];
  let redoStack = [];
  
  let selectedElement = null;
  
  // Atualiza os controles do pincel
  document.getElementById('brush-color').addEventListener('input', (e) => {
    brushColor = e.target.value;
  });
  document.getElementById('brush-size').addEventListener('input', (e) => {
    brushSize = parseInt(e.target.value, 10);
  });
  
  /*** Gerenciamento do Histórico (Undo/Redo) ***/
  function addToHistory() {
    undoStack.push({
      elements: JSON.parse(JSON.stringify(elements)),
      drawingPath: JSON.parse(JSON.stringify(drawingPath))
    });
    redoStack = [];
  }
  window.addToHistory = addToHistory;
  
  function undo() {
    if (undoStack.length === 0) return;
    const currentState = {
      elements: JSON.parse(JSON.stringify(elements)),
      drawingPath: JSON.parse(JSON.stringify(drawingPath))
    };
    redoStack.push(currentState);
    const previousState = undoStack.pop();
    elements = JSON.parse(JSON.stringify(previousState.elements));
    drawingPath = JSON.parse(JSON.stringify(previousState.drawingPath));
    drawCanvas();
  }
  window.undo = undo;
  
  function redo() {
    if (redoStack.length === 0) return;
    const currentState = {
      elements: JSON.parse(JSON.stringify(elements)),
      drawingPath: JSON.parse(JSON.stringify(drawingPath))
    };
    undoStack.push(currentState);
    const nextState = redoStack.pop();
    elements = JSON.parse(JSON.stringify(nextState.elements));
    drawingPath = JSON.parse(JSON.stringify(nextState.drawingPath));
    drawCanvas();
  }
  window.redo = redo;
  
	/*** Inicialização do Editor ***/
	function initializeEditor() {
	
		const dpr = window.devicePixelRatio || 1;
	  
		// Cria e configura o canvas
		canvas = document.createElement('canvas');
		canvas.id = 'editorCanvas';
		canvas.width = 480;
		canvas.height = 640;
		canvas.style.width = "100%";
		canvas.style.height = "auto";
		canvas.style.borderRadius = "20px";
		document.getElementById('editor-container').appendChild(canvas);
  
		ctx = canvas.getContext('2d');
		media = null;
		isVideo = false;
		drawing = false;
		isDrawing = false;
		drawingPath = [];
		elements = [];
		draggingElement = null;
		resizingElement = null;
		offsetX = 0;
		offsetY = 0;
  
		// Configura o elemento de vídeo
		videoElement = document.createElement('video');
		videoElement.loop = true;
		videoElement.muted = true;
		videoElement.playsInline = true;
		videoElement.style.display = 'none';
		document.getElementById('editor-container').appendChild(videoElement);
  
		setupCanvasEvents();
		document.getElementById('file-input').addEventListener('change', handleFile);
  }
  window.initializeEditor = initializeEditor;
  
  /*** Eventos do Canvas ***/
  function setupCanvasEvents() {
    canvas.addEventListener('mousedown', startInteraction);
    canvas.addEventListener('mousemove', moveInteraction);
    canvas.addEventListener('mouseup', endInteraction);
  
    canvas.addEventListener('touchstart', (e) => startInteraction(e.touches[0]));
    canvas.addEventListener('touchmove', (e) => {
      e.preventDefault();
      moveInteraction(e.touches[0]);
    });
    canvas.addEventListener('touchend', endInteraction);
    canvas.addEventListener('dblclick', handleDoubleClick);
  }
  window.setupCanvasEvents = setupCanvasEvents;
  
  /*** Funções de Interação ***/
	function startInteraction(e) {
	  const rect = canvas.getBoundingClientRect();
	  const scaleX = canvas.width / rect.width;
	  const scaleY = canvas.height / rect.height;
	  const x = (e.clientX - rect.left) * scaleX;
	  const y = (e.clientY - rect.top) * scaleY;

	  // Verifica se clicou no "handle" de redimensionamento
	  resizingElement = elements.find((el) => isResizingHandle(x, y, el));
	  if (resizingElement) {
		return;
	  }

	  // Procura pelo elemento clicado
	  const clickedElement = elements.find((el) => {
		const textWidth = ctx.measureText(el.text).width;
		const textHeight = el.size;
		return (
		  x >= el.x - textWidth / 2 &&
		  x <= el.x + textWidth / 2 &&
		  y >= el.y - textHeight / 2 &&
		  y <= el.y + textHeight / 2
		);
	  });
	  
	  if (clickedElement) {
		// Seleciona o elemento clicado para permitir redimensionamento
		selectedElement = clickedElement;
		draggingElement = clickedElement;
		offsetX = x - clickedElement.x;
		offsetY = y - clickedElement.y;
	  } else {
		// Se clicar fora de qualquer elemento, deseleciona
		selectedElement = null;
		draggingElement = null;
	  }
	  drawCanvas();

	  // Modo desenho: se estiver ativo, inicia o traço
	  if (drawing) {
		addToHistory();
		isDrawing = true;
		drawingPath.push([]);
		drawingPath[drawingPath.length - 1].push({ x, y });
	  }
	}
	window.startInteraction = startInteraction;
  
	function moveInteraction(e) {
		const rect = canvas.getBoundingClientRect();
		const scaleX = canvas.width / rect.width;
		const scaleY = canvas.height / rect.height;
		const x = (e.clientX - rect.left) * scaleX;
		const y = (e.clientY - rect.top) * scaleY;

		if (resizingElement) {
			const dx = x - resizingElement.x;
			const dy = y - resizingElement.y;
			resizingElement.size = Math.max(10, Math.sqrt(dx * dx + dy * dy));
			drawCanvas();
			return;
		}
		if (draggingElement) {
			draggingElement.x = x - offsetX;
			draggingElement.y = y - offsetY;
			drawCanvas();
			return;
		}
		if (drawing && isDrawing) {
			drawingPath[drawingPath.length - 1].push({ x, y });
			drawCanvas();
		}
	}
	window.moveInteraction = moveInteraction;
  
	function endInteraction() {
		isDrawing = false;
		draggingElement = null;
		resizingElement = null;
	}
	window.endInteraction = endInteraction;
  
	function isResizingHandle(x, y, el) {
		const textWidth = ctx.measureText(el.text).width;
		const handleX = el.x + textWidth / 2 + 10;
		const handleY = el.y + el.size / 2 + 10;
		const handleSize = 10;
		return (
			x >= handleX - handleSize &&
			x <= handleX + handleSize &&
			y >= handleY - handleSize &&
			y <= handleY + handleSize
		);
	}
	window.isResizingHandle = isResizingHandle;
  
	/*** Funções de Câmera e Gravação (PC) ***/
	let mediaRecorder;
	let recordedChunks = [];
	let cameraStream;
  
	<?php
	if ($mobile == 0) {
	?>
	function startCameraAndRecording() {
		navigator.mediaDevices.getUserMedia({ video: true, audio: true })
		.then((stream) => {
			cameraStream = stream;
			videoElement.srcObject = stream;
			videoElement.style.display = 'none';
			videoElement.play();

			isVideo = true;
			media = videoElement;
			requestAnimationFrame(drawCanvas);

			mediaRecorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
			mediaRecorder.ondataavailable = (event) => {
				if (event.data.size > 0) recordedChunks.push(event.data);
			};

			mediaRecorder.onstop = () => {
				const blob = new Blob(recordedChunks, { type: 'video/webm' });
				const recordedURL = URL.createObjectURL(blob);
				videoElement.srcObject = null;
				videoElement.src = recordedURL;
				videoElement.loop = true;
				videoElement.play();
				recordedChunks = [];
			};

			mediaRecorder.start();
			console.log('🎥 Gravação iniciada');
			document.getElementById('stop-recording-btn').style.display = 'inline-block';

			setTimeout(() => {
				if (mediaRecorder && mediaRecorder.state === 'recording') stopRecording();
			}, 60000);
		})
		.catch((error) => {
		console.error('Erro ao acessar a câmera: ', error);
		alert('Não foi possível acessar a câmera. Verifique as permissões do navegador.');
		});
	}
	window.startCameraAndRecording = startCameraAndRecording;
  
	function stopRecording() {
		if (mediaRecorder && mediaRecorder.state === 'recording') {
			mediaRecorder.stop();
			console.log('⏹️ Gravação finalizada');
			if (cameraStream) cameraStream.getTracks().forEach((track) => track.stop());
			document.getElementById('stop-recording-btn').style.display = 'none';
		}
	}
	window.stopRecording = stopRecording;
	<?php
	}
	?>
  
	/*** Manipulação de Arquivos ***/
	function handleFile(event) {
		const file = event.target.files[0];
		if (!file) return;
		const fileType = file.type;
		if (fileType.startsWith('image/')) {
			isVideo = false;
			videoElement.style.display = 'none';
			const img = new Image();
			img.src = URL.createObjectURL(file);
			img.onload = () => { media = img; drawCanvas(); };
		} else if (fileType.startsWith('video/')) {
			isVideo = true;
			videoElement.src = URL.createObjectURL(file);
			videoElement.onloadeddata = () => { media = videoElement; requestAnimationFrame(drawCanvas); };
			videoElement.play();
			videoElement.muted = false;
		}
	}
	window.handleFile = handleFile;
  
/*** Funções de Desenho no Canvas Otimizadas ***/
let lastFrameTime = 0;
const targetFPS = 24;
const frameInterval = 1000 / targetFPS;
let fpsCounter = 0;
let lastVideoFrame = 0;

setInterval(() => { fpsCounter = 0; }, 1000);

function drawCanvas(timestamp) {
  const elapsed = timestamp - lastFrameTime;
  if (elapsed < frameInterval) {
    requestAnimationFrame(drawCanvas);
    return;
  }

  lastFrameTime = timestamp;
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  if (isVideo && media) {
    if (media.currentTime === lastVideoFrame && !media.paused) {
      requestAnimationFrame(drawCanvas);
      return;
    }
    lastVideoFrame = media.currentTime;

	

    if (!media.renderCache) {
      //const videoAspectRatio = media.videoWidth / media.videoHeight;
		const canvasAspectRatio = canvas.width / canvas.height;
	  
	  const videoAspectRatio = 3 / 4;
      //const canvasAspectRatio = 3 / 4;
      if (videoAspectRatio > canvasAspectRatio) {
        const drawWidth = (media.videoHeight * canvas.width) / canvas.height;
        const drawHeight = media.videoHeight;
        const offsetX = (media.videoWidth - drawWidth) / 2;
        media.renderCache = { drawWidth, drawHeight, offsetX, offsetY: 0 };
      } else {
        const drawWidth = media.videoWidth;
        const drawHeight = (media.videoWidth * canvas.height) / canvas.width;
        const offsetY = (media.videoHeight - drawHeight) / 2;
        media.renderCache = { drawWidth, drawHeight, offsetX: 0, offsetY };
      }
    }

    const { drawWidth, drawHeight, offsetX, offsetY } = media.renderCache;

    if (!media.paused) {
      ctx.drawImage(media, offsetX, offsetY, drawWidth, drawHeight, 0, 0, canvas.width, canvas.height);
    }

    if (media.currentTime >= 60) {
      media.pause();
      console.log('⏰ Duração máxima atingida.');
    }
  } else if (media) {
    drawImageCover(media, ctx, 0, 0, canvas.width, canvas.height);
  }

  ctx.strokeStyle = brushColor;
  ctx.lineWidth = brushSize;
  drawingPath.forEach((path) => {
    ctx.beginPath();
    path.forEach((point, index) => {
      if (index === 0) ctx.moveTo(point.x, point.y);
      else ctx.lineTo(point.x, point.y);
    });
    ctx.stroke();
  });

  elements.forEach((el) => {
    const font = `${el.size}px Ubuntu`;
    if (el.font !== font || el.text !== el.cachedText) {
      el.font = font;
      el.cachedText = el.text;
      ctx.font = el.font;
      el.cachedWidth = ctx.measureText(el.text).width;
    } else {
      ctx.font = el.font;
    }

    ctx.save();
    ctx.translate(el.x, el.y);

    const textWidth = el.cachedWidth;
    const textHeight = el.size;
    const padding = 10;
    const borderRadius = 15;

    if (el.hasBackground) {
      ctx.fillStyle = 'black';
      ctx.strokeStyle = 'black';
      ctx.lineWidth = 2;
      drawRoundedRect(
        ctx,
        -textWidth / 2 - padding,
        -textHeight / 2 - padding / 2,
        textWidth + padding * 2,
        textHeight + padding,
        borderRadius
      );
    }

    ctx.fillStyle = el.color || 'black';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(el.text, 0, 0);

    if (el === draggingElement || el === resizingElement) {
      ctx.strokeStyle = 'red';
      ctx.lineWidth = 2;
      ctx.strokeRect(
        -textWidth / 2 - padding,
        -el.size / 2 - padding / 2,
        textWidth + padding * 2,
        el.size + padding
      );
    }
    ctx.restore();

    if (selectedElement === el) {
      drawResizeHandle(el);
    }
  });

  if (media?.requestVideoFrameCallback) {
    media.requestVideoFrameCallback(() => drawCanvas(performance.now()));
  } else {
    requestAnimationFrame(drawCanvas);
  }
}

window.drawCanvas = drawCanvas;

  
	// Ajusta a imagem para cobrir o canvas
	function drawImageCover(img, ctx, x, y, width, height) {
		const imgAspectRatio = img.width / img.height;
		const canvasAspectRatio = width / height;
		let drawWidth, drawHeight, offsetX, offsetY;
		if (imgAspectRatio > canvasAspectRatio) {
			drawWidth = img.height * canvasAspectRatio;
			drawHeight = img.height;
			offsetX = (img.width - drawWidth) / 2;
			offsetY = 0;
		} else {
			drawWidth = img.width;
			drawHeight = img.width / canvasAspectRatio;
			offsetX = 0;
			offsetY = (img.height - drawHeight) / 2;
		}
		ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight, x, y, width, height);
	}
	window.drawImageCover = drawImageCover;
  
	// Desenha retângulo com cantos arredondados
	function drawRoundedRect(ctx, x, y, width, height, radius) {
		ctx.beginPath();
		ctx.moveTo(x + radius, y);
		ctx.lineTo(x + width - radius, y);
		ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
		ctx.lineTo(x + width, y + height - radius);
		ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
		ctx.lineTo(x + radius, y + height);
		ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
		ctx.lineTo(x, y + radius);
		ctx.quadraticCurveTo(x, y, x + radius, y);
		ctx.closePath();
		ctx.fill();
		ctx.stroke();
	}
	window.drawRoundedRect = drawRoundedRect;
  
	// Desenha o "handle" para redimensionamento
	function drawResizeHandle(el) {
		ctx.font = `${el.size}px Arial`;
		const textWidth = ctx.measureText(el.text).width;
		const handleX = el.x + textWidth / 2 + 10;
		const handleY = el.y + el.size / 2 + 10;
		const handleSize = 10;
		ctx.beginPath();
		ctx.arc(handleX, handleY, handleSize, 0, Math.PI * 2);
		ctx.fillStyle = '#ff5c8d';
		ctx.fill();
		ctx.closePath();
	}
	window.drawResizeHandle = drawResizeHandle;

  
	/*** Adicionar Texto e Emoji ***/
	function addText() {
		const text = prompt('Digite o texto a ser adicionado:');
		if (text) {
			addToHistory();
			const hasBackground = confirm('Deseja adicionar fundo preto ao texto?');
			const color = prompt('Escolha a cor do texto (ex: #ff5c8d):', '#ff5c8d') || '#ff5c8d';
			elements.push({
				type: 'text',
				text,
				x: canvas.width / 2,
				y: canvas.height / 2,
				size: 24,
				color: color,
				hasBackground: hasBackground,
			});
			drawCanvas();
		}
	}
	window.addText = addText;
  
	function addEmoji() {
		const emoji = prompt('Digite o emoji a ser adicionado:');
		if (emoji) {
			addToHistory();
			elements.push({
				type: 'emoji',
				text: emoji,
				x: canvas.width / 2,
				y: canvas.height / 2,
				size: 64,
			});
			drawCanvas();
		}
	}
	window.addEmoji = addEmoji;
  
	function handleDoubleClick(e) {
		const rect = canvas.getBoundingClientRect();
		const x = (e.clientX - rect.left) * (canvas.width / rect.width);
		const y = (e.clientY - rect.top) * (canvas.height / rect.height);

		const clickedElement = elements.find((el) => {
			const textWidth = ctx.measureText(el.text).width;
			const textHeight = el.size;
			return (
				x >= el.x - textWidth / 2 &&
				x <= el.x + textWidth / 2 &&
				y >= el.y - textHeight / 2 &&
				y <= el.y + textHeight / 2
			);
		});

		if (clickedElement) {
			// Seleciona o elemento para que o botão rosa apareça
			selectedElement = clickedElement;
			// Prompt para editar o texto (valor padrão com o texto atual)
			const newText = prompt('Edite o texto:', clickedElement.text);
			if (newText !== null) {
				clickedElement.text = newText;
				addToHistory();
				drawCanvas();
			}
		}
	}
	window.handleDoubleClick = handleDoubleClick;

	function toggleDraw() {
		const brushControls = document.getElementById('brush-controls');
		drawing = !drawing;
		canvas.style.cursor = drawing ? 'crosshair' : 'default';
		if (drawing) {
			brushControls.classList.add('show');
			alert('Modo de desenho ativado! 🖌️');
		} else {
			brushControls.classList.remove('show');
			alert('Modo de desenho desativado! ✍️');
		}
	}
	window.toggleDraw = toggleDraw;
  
  /*** Funções de Download e Exportação ***/
  /*
  function downloadCanvas() {
    const tempCanvas = document.createElement('canvas');
    const tempCtx = tempCanvas.getContext('2d');
    tempCanvas.width = canvas.width;
    tempCanvas.height = canvas.height;
  
    if (media && !isVideo) {
      tempCtx.drawImage(media, 0, 0, tempCanvas.width, tempCanvas.height);
    }
    drawingPath.forEach((path) => {
      tempCtx.beginPath();
      path.forEach((point, index) => {
        if (index === 0) tempCtx.moveTo(point.x, point.y);
        else tempCtx.lineTo(point.x, point.y);
      });
      tempCtx.stroke();
    });
    elements.forEach((el) => {
      tempCtx.save();
      tempCtx.translate(el.x, el.y);
      if (el.hasBackground) {
        tempCtx.font = `${el.size}px Arial`;
        const textWidth = tempCtx.measureText(el.text).width;
        const padding = 10;
        const borderRadius = 15;
        tempCtx.fillStyle = 'black';
        tempCtx.beginPath();
        tempCtx.moveTo(-textWidth / 2 - padding + borderRadius, -el.size / 2 - padding/2);
        tempCtx.arcTo(-textWidth / 2 - padding + textWidth + 2 * padding, -el.size / 2 - padding/2, -textWidth / 2 - padding + textWidth + 2 * padding, -el.size / 2 - padding/2 + el.size + padding, borderRadius);
        tempCtx.arcTo(-textWidth / 2 - padding + textWidth + 2 * padding, -el.size / 2 - padding/2 + el.size + padding, -textWidth / 2 - padding, -el.size / 2 - padding/2 + el.size + padding, borderRadius);
        tempCtx.arcTo(-textWidth / 2 - padding, -el.size / 2 - padding/2 + el.size + padding, -textWidth / 2 - padding, -el.size / 2 - padding/2, borderRadius);
        tempCtx.arcTo(-textWidth / 2 - padding, -el.size / 2 - padding/2, -textWidth / 2 - padding + textWidth + 2 * padding, -el.size / 2 - padding/2, borderRadius);
        tempCtx.closePath();
        tempCtx.fill();
      }
      tempCtx.font = `${el.size}px Arial`;
      tempCtx.fillStyle = el.color || 'white';
      tempCtx.textAlign = 'center';
      tempCtx.textBaseline = 'middle';
      tempCtx.fillText(el.text, 0, 0);
      tempCtx.restore();
    });
  
    const link = document.createElement('a');
    link.download = 'resultado.png';
    link.href = tempCanvas.toDataURL();
    link.click();
  }
  window.downloadCanvas = downloadCanvas;
  
  function downloadVideo() {
    if (!isVideo || !media) {
      alert('Nenhum vídeo para baixar! 😕');
      return;
    }
    const link = document.createElement('a');
    link.download = 'video.mp4';
    link.href = media.src;
    link.click();
  }
  window.downloadVideo = downloadVideo;
  */
  
	/*** Frases Engraçadas ***/
	function startFunnyPhrases(div) {
		const progressText = document.createElement('p');
		progressText.id = 'progress-text';
		progressText.style.color = '#FFF';
		progressText.style.textAlign = 'center';
		progressText.style.padding = '15px';
		progressText.style.opacity = '0';
		div.appendChild(progressText);

		const funnyPhrases = [
			"😴 Acordando o servidor...",
			"🤓 Contando emojis...",
			"🍪 Empacotando bits...",
			"🤝 Negociando espaço em disco...",
			"🐹 Alimentando hamsters digitais...",
			"🧑‍💻 Aplicando filtros...",
			"☕ Preparando café virtual...",
			"✨ Polindo os pixels...",
			"🐣 Inserindo easter eggs...",
			"💪 Girando a manivela..."
		];

		let phraseIndex = 0;

		const fadeIn = (element, duration = 500) => {
			let opacity = 0;
			const step = 50 / duration;
			const interval = setInterval(() => {
				opacity += step;
				if (opacity >= 1) {
					opacity = 1;
					clearInterval(interval);
				}
				element.style.opacity = opacity;
			}, 50);
		};

		const fadeOut = (element, duration = 500) => {
			let opacity = 1;
			const step = 50 / duration;
			const interval = setInterval(() => {
				opacity -= step;
				if (opacity <= 0) {
					opacity = 0;
					clearInterval(interval);
				}
				element.style.opacity = opacity;
			}, 50);
		};

		const interval = setInterval(() => {
			fadeOut(progressText, 500);
			setTimeout(() => {
				progressText.textContent = funnyPhrases[phraseIndex];
				phraseIndex = (phraseIndex + 1) % funnyPhrases.length;
				fadeIn(progressText, 500);
			}, 500);
		}, 3000);

		return () => {
			clearInterval(interval);
			div.removeChild(progressText);
		};
	}
	
	const sideline = '<?= (isset($sideline) && $sideline !== '') ? $sideline : '' ?>';
  
	/*** Upload do Vídeo no Servidor ***/
	function saveVideoToServer(blob) {
		const progressBarInner = document.getElementById('progress-bar-inner');
		const formData = new FormData();
		const uniqueFileName = `video_<?= $_SESSION['wz'] ?>_${Date.now()}.webm`;
		const stopFunnyPhrases = startFunnyPhrases(progressBarInner);
		const lgElement = document.getElementById('lg');
		const lgValue = lgElement ? lgElement.value : '';
		console.log('Legenda:', lgValue);
		formData.append('file', blob, uniqueFileName);
		formData.append('lg', lgValue);

		fetch('/upload.php', {
			method: 'POST',
			body: formData,
		})
		.then(response => response.json())
		.then(data => {
			stopFunnyPhrases();
			toggleSidebar();
			let uview = 1;
			goTo('backengine/timeline.php', 'timeline', '0', sideline);
			timelineScroll(sideline);
		})
		.catch(error => {
			stopFunnyPhrases();
			alert('Erro ao salvar o vídeo no servidor. 😕');
			console.error('Erro:', error);
		});
	}
	window.saveVideoToServer = saveVideoToServer;
  
	function isIOSDevice() {
		const userAgent = navigator.userAgent || navigator.vendor || window.opera;
		return /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
	}
	window.isIOSDevice = isIOSDevice;
  
	async function captureAudioForIOS(media) {
		try {
			console.log('Capturando áudio para iOS via Web Audio API.');
			const audioContext = new AudioContext();
			const mediaSource = audioContext.createMediaElementSource(media);
			const destination = audioContext.createMediaStreamDestination();
			mediaSource.connect(destination);
			mediaSource.connect(audioContext.destination);
			console.log('Áudio capturado com sucesso.');
			return destination.stream;
		} catch (error) {
			console.error('Erro ao capturar áudio em iOS:', error);
			return null;
		}
	}
	window.captureAudioForIOS = captureAudioForIOS;
  
	async function createCombinedStream(canvasStream, media) {
		let combinedStream;
		if (isIOSDevice()) {
			const audioStream = await captureAudioForIOS(media);
			if (audioStream) {
			combinedStream = new MediaStream([
			...canvasStream.getTracks(),
			...audioStream.getAudioTracks(),
			]);
			console.log('Stream combinado com áudio para iOS:', combinedStream);
			} else {
			console.warn('Áudio não disponível para iOS. Usando apenas canvas stream.');
			combinedStream = canvasStream;
			}
		} else {
			try {
				console.log('Dispositivo não iOS: capturando áudio e vídeo.');
				const audioTracks = media.captureStream().getAudioTracks();
				combinedStream = new MediaStream([
				...canvasStream.getTracks(),
				...audioTracks,
				]);
				console.log('Stream combinado:', combinedStream);
			} catch (error) {
				console.error('Erro na captura em dispositivos não iOS:', error);
				combinedStream = canvasStream;
			}
		}
		return combinedStream;
	}
	window.createCombinedStream = createCombinedStream;
  
// Modularização da função downloadVideoWithEdits

function createProgressBar(containerId = 'editor-container') {
  let progressBar = document.getElementById('progress-bar');
  if (!progressBar) {
    progressBar = document.createElement('div');
    progressBar.id = 'progress-bar';
    progressBar.style.position = 'absolute';
    progressBar.style.bottom = '30px';
    progressBar.style.width = '100%';
    progressBar.style.height = '50px';
    progressBar.style.backgroundColor = 'rgba(245, 245, 245, 0.5)';
    progressBar.style.overflow = 'hidden';
    progressBar.style.display = 'none';

    const progressBarInner = document.createElement('div');
    progressBarInner.id = 'progress-bar-inner';
    progressBarInner.style.width = '0%';
    progressBarInner.style.height = '100%';
    progressBarInner.style.textAlign = 'center';
    progressBarInner.style.backgroundColor = '#4caf50';
    progressBarInner.style.transition = 'all 0.5s ease';
    progressBar.appendChild(progressBarInner);
    document.getElementById(containerId).appendChild(progressBar);
  }
  progressBar.style.display = 'block';
  return progressBar.querySelector('#progress-bar-inner');
}
window.createProgressBar = createProgressBar;

async function waitForVideoReady(videoElement) {
  if (videoElement.readyState < 2) {
    await new Promise(resolve => videoElement.addEventListener('loadeddata', resolve, { once: true }));
  }
  return new Promise(resolve => {
    videoElement.pause();
    videoElement.currentTime = 0;
    const onSeeked = () => {
      videoElement.removeEventListener('seeked', onSeeked);
      resolve();
    };
    videoElement.addEventListener('seeked', onSeeked);
  });
}
window.waitForVideoReady = waitForVideoReady;

function setupMediaRecorder(stream, mimeType, onData, onStop, onError) {
  const recorder = new MediaRecorder(stream, { mimeType, videoBitsPerSecond: 600000 });
  const chunks = [];
  recorder.ondataavailable = e => { if (e.data.size > 0) chunks.push(e.data); };
  recorder.onstop = () => onStop(new Blob(chunks, { type: mimeType }));
  recorder.onerror = e => onError(e);
  return recorder;
}
window.setupMediaRecorder = setupMediaRecorder;

async function downloadVideoWithEdits() {
  if (!isVideo || !media) {
    alert('Nenhum vídeo para editar e salvar! 😕');
    return;
  }

  document.querySelectorAll('button').forEach(button => button.disabled = true);
  const progressBarInner = createProgressBar();

  const canvasStream = canvas.captureStream(targetFPS);
  const combinedStream = await createCombinedStream(canvasStream, media);

  const options = isIOSDevice()
    ? { mimeType: 'video/mp4; codecs="avc1.42E01E,mp4a.40.2"' }
    : { mimeType: 'video/webm; codecs=vp8,opus' };

  if (!MediaRecorder.isTypeSupported(options.mimeType)) {
    alert('Seu navegador não suporta gravação no formato especificado.');
    return;
  }

  await waitForVideoReady(videoElement);

  const recorder = setupMediaRecorder(
    combinedStream,
    options.mimeType,
    null,
    blob => {
      saveVideoToServer(blob);
      videoElement.pause();
      document.querySelectorAll('button').forEach(button => button.disabled = false);
    },
    err => {
      console.error('Erro na gravação:', err);
      alert('Erro ao gravar vídeo.');
    }
  );

  videoElement.play();
  recorder.start();

  const updateProgress = () => {
    const progress = Math.min((videoElement.currentTime / Math.min(media.duration, 60)) * 100, 100);
    progressBarInner.style.width = `${progress}%`;
    if (progress < 100) requestAnimationFrame(updateProgress);
  };
  updateProgress();

  setTimeout(() => {
    if (recorder.state === 'recording') recorder.stop();
  }, Math.min(media.duration, 60) * 1000);
}

window.downloadVideoWithEdits = downloadVideoWithEdits;

  
	/*** Inicializa o Editor ***/
	initializeEditor();
})();
</script>