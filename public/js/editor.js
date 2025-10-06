const init = () => {
  // ---------- Polyfill para roundRect se necessário
  if (!CanvasRenderingContext2D.prototype.roundRect) {
    CanvasRenderingContext2D.prototype.roundRect = function(x, y, width, height, radius) {
      if (typeof radius === 'number') {
        radius = {tl: radius, tr: radius, br: radius, bl: radius};
      } else {
        radius = {tl: 0, tr: 0, br: 0, bl: 0, ...radius};
      }
      this.beginPath();
      this.moveTo(x + radius.tl, y);
      this.lineTo(x + width - radius.tr, y);
      this.quadraticCurveTo(x + width, y, x + width, y + radius.tr);
      this.lineTo(x + width, y + height - radius.br);
      this.quadraticCurveTo(x + width, y + height, x + width - radius.br, y + height);
      this.lineTo(x + radius.bl, y + height);
      this.quadraticCurveTo(x, y + height, x, y + height - radius.bl);
      this.lineTo(x, y + radius.tl);
      this.quadraticCurveTo(x, y, x + radius.tl, y);
      this.closePath();
    };
  }

  // ---------- Refs e constantes
    const editorViewport = document.getElementById('editorViewport');
    // Evitar múltiplas inicializações no mesmo DOM (duplica event listeners)
    if (!editorViewport) return;
    if (editorViewport.dataset && editorViewport.dataset.initialized === '1') {
      console.debug('[editor] já inicializado neste container, ignorando init()');
      return;
    }
    editorViewport.dataset.initialized = '1';

    // Helper para vincular listeners sem duplicar
    const addEvtOnce = (el, type, handler, options) => {
      if (!el) return;
      const key = `__bound_${type}`;
      if (el[key]) {
        el.removeEventListener(type, el[key], options);
      }
      el.addEventListener(type, handler, options);
      el[key] = handler;
    };
    const editor = document.getElementById('editor');
    const gridCanvas = document.getElementById('gridCanvas');
    const gctx = gridCanvas.getContext('2d');
    const guideX = document.getElementById('guideX');
    const guideY = document.getElementById('guideY');
  
    const bgUpload = document.getElementById('bgUpload');
    const btnAddText = document.getElementById('btnAddText');
    const btnAddImg = document.getElementById('btnAddImg');
    const btnAddElement = document.getElementById('btnAddElement');
    const itemBar = document.getElementById('itemBar');
    const textControls = document.getElementById('textControls');
    const animControls = document.getElementById('animControls');
    const fontSize = document.getElementById('fontSize');
    const fontColor = document.getElementById('fontColor');
    const fontWeight = document.getElementById('fontWeight');
    const alignLeft = document.getElementById('alignLeft');
    const alignCenter = document.getElementById('alignCenter');
    const alignRight = document.getElementById('alignRight');
    const bgTextColor = document.getElementById('bgTextColor');
    const bgNone = document.getElementById('bgNone');
    const btnEditText = document.getElementById('btnEditText');
    const btnEditTextLabel = document.getElementById('btnEditTextLabel');
  
    const animType = document.getElementById('animType');
    const animDelay = document.getElementById('animDelay');
    const animDur = document.getElementById('animDur');
  
    const zIndexSel = document.getElementById('zIndex');
    const btnDelete = document.getElementById('btnDelete');
  
    const btnSaveJSON = document.getElementById('btnSaveJSON');
    const loadJSON = document.getElementById('loadJSON');
  
    const outCanvas = document.getElementById('outCanvas');
    const octx = outCanvas.getContext('2d');
  
    const btnEnviar = document.getElementById('btnEnviar');
    const exportSettings = document.getElementById('exportSettings');
    const vidDur = document.getElementById('vidDur');
    const vidFPS = document.getElementById('vidFPS');
    
    // Listener para atualizar informações quando duração for alterada manualmente
    vidDur.addEventListener('input', updateVideoExportInfo);

    // Limpar recursos da câmera quando a página for fechada
    window.addEventListener('beforeunload', cleanupCamera);
    window.addEventListener('unload', cleanupCamera);
  
    const SNAP_GRID = 20, SNAP_TOL = 6;
    const BASE_W = 900, BASE_H = 1200;
  
    let bgEl = null, selected = null, editingText = null;
    let currentScale = 1;

    let dragState = null, resizeState = null, rotateState = null;

    // Inicializar estado do botão Enviar
    updateEnviarButton();

    // ---------- Editor Scaling
    function scaleEditor() {
      const { width } = editorViewport.getBoundingClientRect();
      currentScale = width / BASE_W;
      editor.style.setProperty('--editor-scale', currentScale);
    }

    window.addEventListener('resize', scaleEditor);
    scaleEditor();
  
    // ---------- Grid
    drawGrid();
    function drawGrid(){
      console.log('Desenhando grade');
      gctx.clearRect(0,0,gridCanvas.width, gridCanvas.height);
      gctx.globalAlpha = 0.15; 
      gctx.strokeStyle = '#94a3b8'; 
      gctx.lineWidth = 1;
      
      // Desenhar linhas verticais
      for (let x=0; x<=gridCanvas.width; x+=SNAP_GRID){ 
        gctx.beginPath(); 
        gctx.moveTo(x,0); 
        gctx.lineTo(x,gridCanvas.height); 
        gctx.stroke(); 
      }
      
      // Desenhar linhas horizontais
      for (let y=0; y<=gridCanvas.height; y+=SNAP_GRID){ 
        gctx.beginPath(); 
        gctx.moveTo(0,y); 
        gctx.lineTo(gridCanvas.width,y); 
        gctx.stroke(); 
      }
      
      gctx.globalAlpha = 1;
    }
    
    // Função para redesenhar a grade quando necessário
    function refreshGrid() {
      requestAnimationFrame(drawGrid);
    }
  
    // ---------- Fundo
    bgUpload.addEventListener('change', e=>{
      const f = e.target.files?.[0]; if (!f) return;
      const el = document.createElement(f.type.startsWith('image') ? 'img' : 'video');
      el.className = 'bg-media'; el.src = URL.createObjectURL(f);
      if (el.tagName==='VIDEO'){ 
        el.autoplay=true; el.loop=true; el.muted=true; 
        
        // Atualizar duração do vídeo na interface quando os metadados carregarem
        el.addEventListener('loadedmetadata', () => {
          if (el.duration && !isNaN(el.duration)) {
            const limitedDuration = Math.min(60, el.duration);
            vidDur.value = limitedDuration.toFixed(1);
            
            // Mostrar informação para o usuário
            const durationInfo = document.getElementById('videoDurationInfo');
            if (durationInfo) {
              durationInfo.remove();
            }
            
            const info = document.createElement('div');
            info.id = 'videoDurationInfo';
            info.className = 'text-xs text-slate-600 mt-1';
            info.innerHTML = `
              <i class="fa-solid fa-info-circle"></i> 
              Vídeo importado: ${el.duration.toFixed(1)}s 
              ${el.duration > 60 ? `(limitado a 60s para exportação)` : ''}
            `;
            vidDur.parentElement.appendChild(info);
            
            // Atualizar informações de exportação
            updateVideoExportInfo();
          }
        });
      }
      if (bgEl) editor.removeChild(bgEl); bgEl = el;
      editor.insertBefore(bgEl, editor.firstChild);
      
      // Redesenhar a grade para garantir que fique visível
      setTimeout(refreshGrid, 100);
    });
  

    // ---------- Criação: Texto / Imagem / Elementos
    const onAddTextClick = () => createTextBox('Digite seu texto');
    addEvtOnce(btnAddText, 'click', onAddTextClick);
    btnAddImg.addEventListener('click', ()=>{
      const input = document.createElement('input'); input.type='file'; input.accept='image/*';
      input.onchange = e=>{
        const f = e.target.files?.[0]; if(!f) return;
        const url = URL.createObjectURL(f);
        createImageItem(url);
      };
      input.click();
    });
    
    // Menu de elementos adicionais
    btnAddElement.addEventListener('click', ()=>{
      // Por enquanto, criar uma caixa de texto como fallback
      // Futuramente pode abrir um menu com formas, stickers, etc.
      createTextBox('Novo elemento');
    });

    // ---------- Câmera: Captura Direta no Canvas
    const captureButton = document.getElementById('captureButton');
    const hiddenCameraStream = document.getElementById('hiddenCameraStream');
    const captureCanvas = document.getElementById('captureCanvas');

    let currentStream = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let isRecording = false;
    let recordingStartTime = 0;
    let recordingTimer = null;
    let currentFacingMode = 'user'; // 'user' para frontal, 'environment' para traseira
    
    // Variáveis para controle de pressão longa
    let pressTimer = null;
    let isLongPress = false;
    let pressStartTime = 0;
    const LONG_PRESS_DURATION = 500; // 500ms para ativar gravação

    // Detectar se é dispositivo móvel
    function isMobileDevice() {
      return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
             (navigator.maxTouchPoints && navigator.maxTouchPoints > 2) ||
             window.innerWidth <= 768;
    }

    // Configurar comportamento baseado no dispositivo
    if (isMobileDevice()) {
      // Mobile: Captura direta
      captureButton.addEventListener('pointerdown', handleCaptureStart);
      captureButton.addEventListener('pointerup', handleCaptureEnd);
      captureButton.addEventListener('pointerleave', handleCaptureEnd);
      captureButton.addEventListener('contextmenu', e => e.preventDefault());
      
      // Manter dica para mobile
      console.log('Dispositivo móvel detectado - usando captura direta');
    } else {
      // Desktop: Upload de arquivo
      captureButton.addEventListener('click', handleDesktopCapture);
      
      // Atualizar visual para desktop
      const icon = captureButton.querySelector('.capture-icon');
      icon.className = 'fas fa-upload capture-icon';
      
      const hint = document.querySelector('.capture-hint');
      if (hint) {
        hint.innerHTML = '<i class="fas fa-mouse-pointer"></i>';
      }
      
      // Atualizar tooltip e dica
      captureButton.title = 'Clique para fazer upload de foto/vídeo';
      
      const captureHint = document.getElementById('captureHint');
      if (captureHint) {
        captureHint.innerHTML = 'Dica: <strong>Clique</strong> para fazer upload de foto ou vídeo';
      }
      
      console.log('Desktop detectado - usando upload de arquivo');
      
      // Mostrar botão de câmera como opção alternativa
      const btnCameraMode = document.getElementById('btnCameraMode');
      if (btnCameraMode) {
        btnCameraMode.classList.remove('hidden');
        btnCameraMode.addEventListener('click', switchToCameraMode);
      }
    }

    // Função para alternar para modo câmera no desktop
    async function switchToCameraMode() {
      try {
        // Tentar inicializar câmera
        await initializeCamera();
        
        // Se sucesso, alterar comportamento do botão principal
        captureButton.removeEventListener('click', handleDesktopCapture);
        captureButton.addEventListener('pointerdown', handleCaptureStart);
        captureButton.addEventListener('pointerup', handleCaptureEnd);
        captureButton.addEventListener('pointerleave', handleCaptureEnd);
        
        // Atualizar visual
        const icon = captureButton.querySelector('.capture-icon');
        icon.className = 'fas fa-camera capture-icon';
        
        captureButton.title = 'Clique para foto, segure para iniciar vídeo, clique novamente para parar';
        
        const captureHint = document.getElementById('captureHint');
        if (captureHint) {
          captureHint.innerHTML = 'Dica: <strong>Clique</strong> para foto, <strong>segure</strong> para iniciar vídeo, <strong>clique</strong> para parar';
        }
        
        // Esconder botão de câmera
        const btnCameraMode = document.getElementById('btnCameraMode');
        if (btnCameraMode) {
          btnCameraMode.classList.add('hidden');
        }
        
        // Iniciar preview em tempo real
        startCameraPreview();
        
        showCaptureSuccess('Modo câmera ativado!');
        
      } catch (error) {
        console.error('Erro ao ativar câmera:', error);
        showCameraError(error);
      }
    }

    // Função para desktop - upload de arquivo
    function handleDesktopCapture(e) {
      e.preventDefault();
      
      // Criar input de arquivo
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*,video/*';
      input.multiple = false;
      
      input.onchange = (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        
        const url = URL.createObjectURL(file);
        const type = file.type.startsWith('image/') ? 'image' : 'video';
        
        // Aplicar como plano de fundo
        setBackgroundMedia(url, type);
        
        // Mostrar feedback
        showCaptureSuccess(`${type === 'image' ? 'Foto' : 'Vídeo'} adicionado!`);
        
        // Limpar input
        input.remove();
      };
      
      // Abrir seletor de arquivo
      input.click();
    }

    // Variáveis para controle do preview e gravação
    let previewActive = false;
    let previewAnimationId = null;

    // Funções para controle do botão de captura único (mobile)
    async function handleCaptureStart(e) {
      e.preventDefault();
      
      // Se não há stream ativo, inicializar câmera
      if (!currentStream) {
        try {
          await initializeCamera();
          startCameraPreview();
        } catch (error) {
          console.error('Erro ao inicializar câmera:', error);
          showCameraError(error);
          return;
        }
      }
      
      // Se preview não está ativo (foto foi tirada), reativar câmera
      if (!previewActive) {
        if (currentStream) {
          // Stream ainda existe, apenas reativar preview
          startCameraPreview();
        } else {
          // Stream foi desligado, reinicializar câmera
          try {
            await initializeCamera();
            startCameraPreview();
          } catch (error) {
            console.error('Erro ao reinicializar câmera:', error);
            showCameraError(error);
            return;
          }
        }
        return;
      }
      
      // Se já está gravando, parar gravação com clique simples
      if (isRecording) {
        console.log('Clique durante gravação - parando gravação');
        stopVideoRecording();
        return;
      }
      
      // Iniciar lógica de pressão longa para gravação
      pressStartTime = Date.now();
      isLongPress = false;
      
      // Adicionar classe visual para feedback
      captureButton.classList.add('long-press');
      editorViewport.classList.add('capture-mode');
      
      // Alterar ícone para indicar modo de vídeo em potencial
      const icon = captureButton.querySelector('.capture-icon');
      icon.className = 'fas fa-video capture-icon';
      
      // Timer para detectar pressão longa
      pressTimer = setTimeout(() => {
        isLongPress = true;
        // Vibração se disponível
        if (navigator.vibrate) {
          navigator.vibrate(50);
        }
        // Iniciar gravação de vídeo
        console.log('Pressão longa detectada - iniciando gravação');
        startVideoRecording();
      }, LONG_PRESS_DURATION);
    }

    async function handleCaptureEnd(e) {
      e.preventDefault();
      
      // Se está gravando, não fazer nada no handleCaptureEnd
      // A gravação será parada no próximo handleCaptureStart
      if (isRecording) {
        return;
      }
      
      const pressDuration = Date.now() - pressStartTime;
      
      // Limpar timer se ainda estiver ativo
      if (pressTimer) {
        clearTimeout(pressTimer);
        pressTimer = null;
      }
      
      // Restaurar ícone da câmera
      const icon = captureButton.querySelector('.capture-icon');
      icon.className = 'fas fa-camera capture-icon';
      
      // Se não foi pressão longa, tirar foto
      if (!isLongPress && pressDuration < LONG_PRESS_DURATION) {
        console.log('Clique simples - tirando foto');
        takeInstantPhoto();
      }
      
      // Remover classes visuais
      setTimeout(() => {
        captureButton.classList.remove('long-press');
        editorViewport.classList.remove('capture-mode');
      }, 200);
      
      isLongPress = false;
    }

    // Variáveis para o preview
    let previewVideoElement = null;

    // Iniciar preview da câmera em tempo real no canvas
    function startCameraPreview() {
      if (!currentStream || !hiddenCameraStream.videoWidth) {
        console.log('Aguardando câmera estar pronta para preview');
        setTimeout(startCameraPreview, 100);
        return;
      }
      
      previewActive = true;
      console.log('Iniciando preview da câmera no canvas');
      
      // Adicionar classe visual para indicar câmera ativa
      captureButton.classList.remove('camera-inactive');
      captureButton.classList.add('camera-active');
      
      // Restaurar tooltip original
      captureButton.title = 'Clique para foto, segure para iniciar vídeo, clique novamente para parar';
      
      // Restaurar dica de uso
      const captureHint = document.getElementById('captureHint');
      if (captureHint) {
        captureHint.innerHTML = 'Dica: <strong>Clique</strong> para foto, <strong>segure</strong> para iniciar vídeo, <strong>clique</strong> para parar';
      }
      
      // Criar elemento de vídeo para preview (mais eficiente que canvas)
      if (!previewVideoElement) {
        previewVideoElement = document.createElement('video');
        previewVideoElement.className = 'bg-media preview-media';
        previewVideoElement.autoplay = true;
        previewVideoElement.muted = true;
        previewVideoElement.playsInline = true;
        previewVideoElement.srcObject = currentStream;
        
        // Remover elemento de fundo anterior se existir
        if (bgEl) {
          editor.removeChild(bgEl);
          if (bgEl.classList.contains('preview-media')) {
            URL.revokeObjectURL(bgEl.src);
          }
        }
        
        bgEl = previewVideoElement;
        editor.insertBefore(bgEl, editor.firstChild);
        
        console.log('Preview de vídeo criado e adicionado ao editor');
        
        // Redesenhar a grade
        setTimeout(refreshGrid, 100);
      }
    }
    
    // Parar preview da câmera
    function stopCameraPreview() {
      previewActive = false;
      
      // Remover classe visual e adicionar classe inativa
      captureButton.classList.remove('camera-active');
      captureButton.classList.add('camera-inactive');
      
      // Alterar ícone para indicar que pode reativar câmera
      const icon = captureButton.querySelector('.capture-icon');
      icon.className = 'fas fa-camera capture-icon';
      
      // Atualizar tooltip
      captureButton.title = 'Clique para reativar câmera';
      
      // Remover elemento de preview se existir
      if (previewVideoElement && bgEl === previewVideoElement) {
        editor.removeChild(previewVideoElement);
        previewVideoElement = null;
        bgEl = null;
      }
      
      console.log('Preview da câmera parado');
    }

    // Desligar câmera completamente (para após tirar foto)
    function stopCameraCompletely() {
      previewActive = false;
      
      // Parar todas as tracks do stream para desligar a luz da câmera
      if (currentStream) {
        currentStream.getTracks().forEach(track => {
          track.stop();
          console.log('Track parada:', track.kind, track.label);
        });
        currentStream = null;
      }
      
      // Limpar elemento de vídeo oculto
      if (hiddenCameraStream) {
        hiddenCameraStream.srcObject = null;
      }
      
      // Remover classe visual e adicionar classe inativa
      captureButton.classList.remove('camera-active');
      captureButton.classList.add('camera-inactive');
      
      // Alterar ícone para indicar que pode reativar câmera
      const icon = captureButton.querySelector('.capture-icon');
      icon.className = 'fas fa-camera capture-icon';
      
      // Atualizar tooltip
      captureButton.title = 'Clique para reativar câmera';
      
      // Remover elemento de preview se existir
      if (previewVideoElement && bgEl === previewVideoElement) {
        editor.removeChild(previewVideoElement);
        previewVideoElement = null;
        bgEl = null;
      }
      
      console.log('Câmera desligada completamente - luz deve apagar');
    }

    // Limpar todos os recursos da câmera (para quando sair da página)
    function cleanupCamera() {
      stopCameraCompletely();
      
      // Limpar elementos de vídeo
      if (previewVideoElement) {
        previewVideoElement.srcObject = null;
        previewVideoElement = null;
      }
      
      if (hiddenCameraStream) {
        hiddenCameraStream.srcObject = null;
      }
      
      console.log('Recursos da câmera limpos');
    }

    // Inicializar câmera
    async function initializeCamera() {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        throw new Error('Seu navegador não suporta acesso à câmera');
      }

      const constraints = {
        video: {
          facingMode: currentFacingMode,
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: false // Não precisamos de áudio para foto
      };

      try {
        currentStream = await navigator.mediaDevices.getUserMedia(constraints);
        hiddenCameraStream.srcObject = currentStream;
        
        // Aguardar o vídeo estar pronto
        return new Promise((resolve, reject) => {
          hiddenCameraStream.onloadedmetadata = () => {
            console.log('Câmera inicializada com sucesso - Dimensões:', hiddenCameraStream.videoWidth, 'x', hiddenCameraStream.videoHeight);
            
            // Garantir que o vídeo está reproduzindo
            hiddenCameraStream.play().then(() => {
              console.log('Vídeo oculto iniciado com sucesso');
              resolve();
            }).catch((error) => {
              console.error('Erro ao iniciar vídeo oculto:', error);
              resolve(); // Continuar mesmo se play falhar
            });
          };
          
          hiddenCameraStream.onerror = (error) => {
            console.error('Erro no vídeo:', error);
            reject(new Error('Erro ao inicializar vídeo da câmera'));
          };
          
          // Timeout de segurança
          setTimeout(() => {
            if (hiddenCameraStream.videoWidth === 0) {
              reject(new Error('Timeout: Câmera não inicializou'));
            } else {
              resolve();
            }
          }, 5000);
        });
      } catch (error) {
        // Tentar sem especificar câmera
        if (error.name === 'OverconstrainedError' || error.name === 'NotFoundError') {
          const fallbackConstraints = { video: true, audio: false };
          currentStream = await navigator.mediaDevices.getUserMedia(fallbackConstraints);
          hiddenCameraStream.srcObject = currentStream;
          
          return new Promise((resolve, reject) => {
            hiddenCameraStream.onloadedmetadata = () => {
              console.log('Câmera inicializada (fallback) - Dimensões:', hiddenCameraStream.videoWidth, 'x', hiddenCameraStream.videoHeight);
              
              // Garantir que o vídeo está reproduzindo
              hiddenCameraStream.play().then(() => {
                console.log('Vídeo oculto (fallback) iniciado com sucesso');
                resolve();
              }).catch((error) => {
                console.error('Erro ao iniciar vídeo oculto (fallback):', error);
                resolve(); // Continuar mesmo se play falhar
              });
            };
            
            setTimeout(() => {
              if (hiddenCameraStream.videoWidth === 0) {
                reject(new Error('Timeout: Câmera não inicializou (fallback)'));
              } else {
                resolve();
              }
            }, 5000);
          });
        } else {
          throw error;
        }
      }
    }

    // Tirar foto instantânea
    function takeInstantPhoto() {
      if (!currentStream) {
        console.error('Stream da câmera não está disponível');
        return;
      }

      // Usar o elemento de preview se disponível, senão usar o oculto
      const video = previewVideoElement || hiddenCameraStream;
      
      if (!video || !video.videoWidth) {
        console.error('Elemento de vídeo não está pronto');
        return;
      }
      
      console.log('Capturando foto - Dimensões do vídeo:', video.videoWidth, 'x', video.videoHeight);
      console.log('ReadyState do vídeo:', video.readyState);
      
      // Configurar canvas de captura
      const canvas = captureCanvas;
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      
      const ctx = canvas.getContext('2d');
      
      // Limpar canvas antes de desenhar
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      
      // Verificar se o vídeo está reproduzindo
      if (video.readyState >= 2) { // HAVE_CURRENT_DATA
        try {
          // Desenhar o frame atual do vídeo
          ctx.drawImage(video, 0, 0, video.videoWidth, video.videoHeight);
          
          // Debug: verificar se algo foi desenhado
          const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const hasContent = imageData.data.some(pixel => pixel !== 0);
          console.log('Canvas tem conteúdo após drawImage:', hasContent);
          
          // Converter para blob e usar como plano de fundo permanente
          canvas.toBlob((blob) => {
            if (blob && blob.size > 0) {
              console.log('Foto capturada com sucesso, tamanho:', blob.size, 'bytes');
              
              // Debug: criar uma imagem temporária para verificar o conteúdo
              const testImg = new Image();
              testImg.onload = () => {
                console.log('Imagem de teste carregada:', testImg.width, 'x', testImg.height);
                
                // Se chegou até aqui, a imagem está válida
                const url = URL.createObjectURL(blob);
                
                // Desligar câmera completamente e aplicar foto
                stopCameraCompletely();
                setBackgroundMedia(url, 'image', false); // false = permanente
                
                // Feedback visual
                showCaptureSuccess('Foto capturada!');
                
                // Atualizar dica de uso
                const captureHint = document.getElementById('captureHint');
                if (captureHint) {
                  captureHint.innerHTML = 'Dica: <strong>Clique</strong> para reativar câmera ou fazer upload';
                }
              };
              
              testImg.onerror = () => {
                console.error('Imagem de teste falhou ao carregar - blob pode estar corrompido');
                showCameraError(new Error('Imagem capturada está corrompida'));
              };
              
              testImg.src = URL.createObjectURL(blob);
              
            } else {
              console.error('Erro: Blob vazio ou inválido');
              showCameraError(new Error('Falha na captura da foto'));
            }
          }, 'image/jpeg', 0.9);
          
        } catch (error) {
          console.error('Erro ao desenhar no canvas:', error);
          showCameraError(new Error('Erro na captura: ' + error.message));
        }
      } else {
        console.error('Vídeo não está pronto para captura. ReadyState:', video.readyState);
        showCameraError(new Error('Vídeo não está pronto'));
      }
    }

    // Iniciar gravação de vídeo
    function startVideoRecording() {
      if (!currentStream) {
        console.error('Câmera não está disponível');
        return;
      }

      // Manter preview durante gravação, apenas marcar como gravando
      previewActive = false;

      // Adicionar áudio para gravação de vídeo
      navigator.mediaDevices.getUserMedia({
        video: { facingMode: currentFacingMode },
        audio: true
      }).then(stream => {
        recordedChunks = [];
        
        // Verificar formatos suportados
        const supportedTypes = [
          'video/webm;codecs=vp8,opus',
          'video/webm;codecs=vp9,opus',
          'video/webm',
          'video/mp4'
        ];

        let selectedType = null;
        for (const type of supportedTypes) {
          if (MediaRecorder.isTypeSupported(type)) {
            selectedType = type;
            break;
          }
        }

        const options = {};
        if (selectedType) {
          options.mimeType = selectedType;
          if (selectedType.includes('webm')) {
            options.videoBitsPerSecond = 2500000;
            options.audioBitsPerSecond = 128000;
          }
        }

        mediaRecorder = new MediaRecorder(stream, options);
        
        mediaRecorder.ondataavailable = (event) => {
          if (event.data.size > 0) {
            recordedChunks.push(event.data);
          }
        };

        mediaRecorder.onstop = () => {
          console.log('MediaRecorder parado, processando vídeo...');
          
          if (recordedChunks.length === 0) {
            console.error('Nenhum dado foi gravado');
            showCameraError(new Error('Nenhum dado foi gravado'));
            return;
          }
          
          const mimeType = selectedType || 'video/webm';
          const blob = new Blob(recordedChunks, { type: mimeType });
          console.log('Vídeo processado, tamanho:', blob.size, 'bytes');
          
          const url = URL.createObjectURL(blob);
          
          // Parar stream de áudio/vídeo da gravação
          stream.getTracks().forEach(track => {
            track.stop();
            console.log('Track de gravação parada:', track.kind);
          });
          
          // Desligar câmera completamente após gravação
          stopCameraCompletely();
          
          // Aplicar vídeo como fundo
          setBackgroundMedia(url, 'video', false); // false = permanente
          
          // Atualizar duração do vídeo
          const videoElement = document.querySelector('.bg-media');
          if (videoElement && videoElement.tagName === 'VIDEO') {
            videoElement.addEventListener('loadedmetadata', () => {
              if (videoElement.duration && !isNaN(videoElement.duration)) {
                const limitedDuration = Math.min(60, videoElement.duration);
                vidDur.value = limitedDuration.toFixed(1);
                updateVideoExportInfo();
              }
            });
          }
          
          showCaptureSuccess('Vídeo gravado!');
          
          // Atualizar dica de uso
          const captureHint = document.getElementById('captureHint');
          if (captureHint) {
            captureHint.innerHTML = 'Dica: <strong>Clique</strong> para reativar câmera ou fazer upload';
          }
        };

        mediaRecorder.start(100);
        isRecording = true;
        recordingStartTime = Date.now();
        
        // Atualizar UI
        captureButton.classList.add('recording');
        
        console.log('Gravação de vídeo iniciada');
      }).catch(error => {
        console.error('Erro ao iniciar gravação:', error);
        showCameraError(error);
      });
    }

    // Parar gravação de vídeo
    function stopVideoRecording() {
      if (mediaRecorder && isRecording) {
        console.log('Parando gravação de vídeo...');
        
        try {
          mediaRecorder.stop();
        } catch (error) {
          console.error('Erro ao parar MediaRecorder:', error);
        }
        
        isRecording = false;
        captureButton.classList.remove('recording');
        
        // Restaurar ícone da câmera
        const icon = captureButton.querySelector('.capture-icon');
        icon.className = 'fas fa-camera capture-icon';
        
        console.log('Comando de parada enviado ao MediaRecorder');
      } else {
        console.log('Nenhuma gravação ativa para parar');
      }
    }

    // Definir mídia como plano de fundo
    function setBackgroundMedia(url, type, isPreview = false) {
      console.log('Definindo mídia como plano de fundo:', type, url, isPreview ? '(preview)' : '(permanente)');
      
      const element = document.createElement(type === 'image' ? 'img' : 'video');
      element.className = 'bg-media';
      element.src = url;
      
      // Marcar como preview se necessário
      if (isPreview) {
        element.classList.add('preview-media');
      }
      
      if (type === 'image') {
        element.onload = () => {
          console.log('Imagem carregada com sucesso:', element.naturalWidth, 'x', element.naturalHeight);
        };
        
        element.onerror = (error) => {
          console.error('Erro ao carregar imagem:', error);
        };
      } else if (type === 'video') {
        element.autoplay = true;
        element.loop = true;
        element.muted = true;
        
        element.addEventListener('loadedmetadata', () => {
          console.log('Vídeo carregado:', element.videoWidth, 'x', element.videoHeight);
          if (element.duration && !isNaN(element.duration)) {
            const limitedDuration = Math.min(60, element.duration);
            vidDur.value = limitedDuration.toFixed(1);
            updateVideoExportInfo();
          }
        });
      }
      
      // Remover elemento anterior
      if (bgEl) {
        console.log('Removendo elemento de fundo anterior');
        editor.removeChild(bgEl);
        
        // Se o anterior era um preview, limpar adequadamente
        if (bgEl.classList.contains('preview-media')) {
          if (bgEl.src && bgEl.src.startsWith('blob:')) {
            URL.revokeObjectURL(bgEl.src);
          }
          // Se era o preview de vídeo, limpar referência
          if (bgEl === previewVideoElement) {
            previewVideoElement = null;
          }
        }
      }
      
      bgEl = element;
      editor.insertBefore(bgEl, editor.firstChild);
      console.log('Elemento de fundo adicionado ao editor');
      
      // Redesenhar a grade para garantir que fique visível
      setTimeout(refreshGrid, 100);
      
      // Atualizar botão Enviar baseado no novo conteúdo
      setTimeout(updateEnviarButton, 200);
    }

    // Mostrar sucesso da captura
    function showCaptureSuccess(message) {
      // Criar notificação temporária
      const notification = document.createElement('div');
      notification.className = 'capture-notification';
      notification.innerHTML = `<i class="fa-solid fa-check"></i> ${message}`;
      
      editorViewport.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, 2000);
    }

    // Mostrar erro da câmera
    function showCameraError(error) {
      let errorMessage = 'Não foi possível acessar a câmera.';
      
      if (error.name === 'NotAllowedError') {
        errorMessage = 'Permissão negada. Permita o acesso à câmera.';
      } else if (error.name === 'NotFoundError') {
        errorMessage = 'Nenhuma câmera encontrada.';
      } else if (error.name === 'NotReadableError') {
        errorMessage = 'Câmera está sendo usada por outro aplicativo.';
      }
      
      alert(errorMessage);
    }


  
    function createTextBox(text=''){
      const box = document.createElement('div');
      box.className = 'item draggable text-box';
      box.contentEditable = 'false';
      box.dataset.type='text';
      box.dataset.fontSize='28';
      box.dataset.fontWeight='600';
      box.dataset.fontColor='#111827';
      box.dataset.align='left';         // left | center | right
      box.dataset.bgColor='#ffffff';    // cor de fundo
      box.dataset.bgNone='false';       // sem fundo?
      box.dataset.anim='none'; box.dataset.delay='0'; box.dataset.dur='0.8';
      box.addEventListener('blur', handleTextBlur);
      
      // Forçar posicionamento absoluto independente
      box.style.position = 'absolute';
      box.style.left = '60px';
      box.style.top = '60px';
      box.style.width = '360px';
      box.style.zIndex = getMaxZ() + 1;
      
      box.innerText = text || 'Digite seu texto';
      applyTextStyle(box); applyTextBg(box);
      addHandles(box); attachDrag(box);
      editor.appendChild(box); selectItem(box); startEditingText(box);
    }
  
    function createImageItem(src){
      // Criar um wrapper div para a imagem (pois img não pode ter filhos)
      const wrapper = document.createElement('div');
      wrapper.className='item draggable image-item';
      wrapper.dataset.type='image';
      wrapper.dataset.anim='none'; wrapper.dataset.delay='0'; wrapper.dataset.dur='0.8';
      
      // Forçar posicionamento absoluto independente
      wrapper.style.position = 'absolute';
      wrapper.style.left = '100px';
      wrapper.style.top = '100px';
      wrapper.style.zIndex = getMaxZ() + 1;
      
      const img = document.createElement('img');
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '0.85rem';
      
      img.onload = ()=>{
        const w = Math.min(360, img.naturalWidth);
        const h = Math.round(w * (img.naturalHeight/img.naturalWidth));
        wrapper.style.width = w + 'px';
        wrapper.style.height = h + 'px';
        
        addHandles(wrapper);
        attachDrag(wrapper);
        // Forçar seleção após um pequeno delay para garantir que tudo foi renderizado
        setTimeout(() => {
          selectItem(wrapper);
        }, 10);
      };
      
      img.src = src;
      wrapper.appendChild(img);
      editor.appendChild(wrapper);
    }
  
    function placeDefaults(el, left, top, w, h, rot){
      el.style.left = left+'px'; el.style.top=top+'px';
      if (w) el.style.width=w+'px'; if (h) el.style.height=h+'px';
      el.dataset.rot = String(rot || 0); applyRotation(el);
    }
  
    // ---------- Seleção
    editor.addEventListener('pointerdown', e=>{
      const it = e.target.closest('.item');
      if (editingText && (!it || it !== editingText)) {
        stopEditingText();
      }
      selectItem(it || null);
    });
    function selectItem(el){
      if (editingText && editingText !== el){
        stopEditingText();
      }
      if (selected) {
        selected.classList.remove('selected');
        // Esconder handles do item anterior
        selected.querySelectorAll('.handle').forEach(handle => {
          handle.style.setProperty('display', 'none', 'important');
        });
      }
      selected = el;
      if (selected){
        selected.classList.add('selected');
        // Forçar exibição dos handles via JavaScript com !important
        selected.querySelectorAll('.handle').forEach(handle => {
          handle.style.setProperty('display', 'block', 'important');
          handle.style.setProperty('background', 'red', 'important');
          handle.style.setProperty('width', '40px', 'important');
          handle.style.setProperty('height', '40px', 'important');
          handle.style.setProperty('z-index', '9999', 'important');
          console.log('Handle:', handle, 'Computed style:', getComputedStyle(handle));
        });
        itemBar.style.display='';
        const isText = selected.dataset.type==='text';
        textControls.style.display = isText ? 'flex' : 'none';
        btnEditText.style.display = isText ? '' : 'none';
        animControls.style.display = 'flex';
        // Definir o valor correto baseado no z-index atual
        const currentZ = +getComputedStyle(selected).zIndex || 0;
        const maxZ = getMaxZ();
        zIndexSel.value = (currentZ >= maxZ) ? 'front' : 'back';
  
        if (isText){
          fontSize.value = selected.dataset.fontSize||'28';
          fontWeight.value = selected.dataset.fontWeight||'600';
          fontColor.value = selected.dataset.fontColor||'#111827';
          bgTextColor.value = selected.dataset.bgColor || '#ffffff';
          bgNone.checked = selected.dataset.bgNone === 'true';
          // alinhar UI visual
          setTextAlignButtons(selected.dataset.align||'left');
          btnEditText.textContent = editingText === selected ? 'Concluir' : 'Editar';
        }
        animType.value = selected.dataset.anim || 'none';
        animDelay.value = selected.dataset.delay || '0';
        animDur.value   = selected.dataset.dur   || '0.8';
        
        // Atualizar botão Enviar quando animação mudar
        setTimeout(updateEnviarButton, 100);
      } else {
        itemBar.style.display='none';
        textControls.style.display = 'none';
        btnEditText.style.display = 'none';
        btnEditText.textContent = 'Editar';
      }
    }

    // ---------- Z-index / Delete
    zIndexSel.addEventListener('change', ()=>{
      if(!selected) return;
      selected.style.zIndex = (zIndexSel.value==='front') ? (getMaxZ()+1) : '0';
    });
    function getMaxZ(){
      return [...editor.querySelectorAll('.item')].reduce((m,el)=>Math.max(m, +getComputedStyle(el).zIndex||0),0);
    }
    btnDelete.addEventListener('click', ()=>{
      if(!selected) return;
      const toRemove = selected;
      selectItem(null);
      toRemove.remove();
    });
  
    // ---------- Texto: estilo / alinhamento / fundo
    function applyTextStyle(el){
      el.style.fontSize=`${el.dataset.fontSize}px`;
      el.style.fontWeight=el.dataset.fontWeight;
      el.style.color=el.dataset.fontColor;
      // Remover textAlign pois agora usamos flexbox
      el.style.textAlign = '';
      // Aplicar data-align para o CSS flexbox
      el.setAttribute('data-align', el.dataset.align || 'left');
    }
    function applyTextBg(el){
      if (el.dataset.bgNone === 'true'){
        el.style.background = 'transparent';
      } else {
        // usar mesma opacidade da exportação para consistência
        const hex = el.dataset.bgColor || '#ffffff';
        el.style.background = hexToRgba(hex, 0.86);
      }
    }
    function hexToRgba(hex, alpha=1){
      if (!hex || typeof hex !== 'string') return `rgba(255,255,255,${alpha})`;
      const cleanHex = hex.replace('#','');
      if (cleanHex.length !== 6) return `rgba(255,255,255,${alpha})`;
      
      const r = parseInt(cleanHex.substring(0,2), 16) || 0;
      const g = parseInt(cleanHex.substring(2,4), 16) || 0;
      const b = parseInt(cleanHex.substring(4,6), 16) || 0;
      return `rgba(${r},${g},${b},${alpha})`;
    }

    function handleTextBlur(e){
      if (editingText === e.currentTarget){
        stopEditingText();
      }
    }

    function startEditingText(el){
      if (editingText === el) return;
      if (editingText && editingText !== el) {
        stopEditingText();
      }
      editingText = el;
      el.contentEditable = 'true';
      el.style.cursor = 'text';
      el.focus({ preventScroll: true });
      placeCaretAtEnd(el);
      if (btnEditText) {
        btnEditText.textContent = 'Concluir';
      }
    }

    function stopEditingText(){
      if (!editingText) return;
      editingText.contentEditable = 'false';
      if(editingText) editingText.style.removeProperty('cursor');
      if (document.activeElement === editingText) {
        editingText.blur();
      }
      editingText = null;
      if (btnEditText) {
        btnEditText.textContent = 'Editar';
      }
    }

    function placeCaretAtEnd(el){
      const selection = window.getSelection();
      if (!selection) return;
      const range = document.createRange();
      range.selectNodeContents(el);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }

    fontSize.addEventListener('input', ()=>{ if(selected?.dataset.type!=='text')return; selected.dataset.fontSize=fontSize.value; applyTextStyle(selected); });
    fontWeight.addEventListener('change', ()=>{ if(selected?.dataset.type!=='text')return; selected.dataset.fontWeight=fontWeight.value; applyTextStyle(selected); });
    fontColor.addEventListener('input', ()=>{ if(selected?.dataset.type!=='text')return; selected.dataset.fontColor=fontColor.value; applyTextStyle(selected); });
  
    alignLeft.addEventListener('click', ()=> setAlign('left'));
    alignCenter.addEventListener('click', ()=> setAlign('center'));
    alignRight.addEventListener('click', ()=> setAlign('right'));
  
    function setAlign(a){
      if(!selected || selected.dataset.type!=='text') return;
      selected.dataset.align = a; applyTextStyle(selected); setTextAlignButtons(a);
    }
    function setTextAlignButtons(a){
      [alignLeft,alignCenter,alignRight].forEach(b=> b.classList.remove('bg-slate-800','text-white'));
      if (a==='left')   { alignLeft.classList.add('bg-slate-800','text-white'); }
      if (a==='center') { alignCenter.classList.add('bg-slate-800','text-white'); }
      if (a==='right')  { alignRight.classList.add('bg-slate-800','text-white'); }
    }
  
    bgTextColor.addEventListener('input', ()=>{
      if(!selected || selected.dataset.type!=='text') return;
      selected.dataset.bgColor = bgTextColor.value; applyTextBg(selected);
    });
    bgNone.addEventListener('change', ()=>{
      if(!selected || selected.dataset.type!=='text') return;
      selected.dataset.bgNone = bgNone.checked ? 'true' : 'false'; applyTextBg(selected);
    });

    btnEditText.addEventListener('click', () => {
      if (!selected || selected.dataset.type !== 'text') return;
      if (editingText === selected) {
        stopEditingText();
      } else {
        startEditingText(selected);
      }
    });
  
    // ---------- Animações
    animType.addEventListener('change', ()=>{ if(!selected) return; selected.dataset.anim=animType.value; });
    animDelay.addEventListener('change', ()=>{ if(!selected) return; selected.dataset.delay=animDelay.value; });
    animDur.addEventListener('change', ()=>{ if(!selected) return; selected.dataset.dur=animDur.value; });
  
    // ---------- Handles (resize + rotate)
    function addHandles(el){
      // Remover handles existentes primeiro
      el.querySelectorAll('.handle').forEach(h => h.remove());
      
      ['nw','ne','sw','se'].forEach(c=>{
        const h = document.createElement('div');
        h.className = `handle h-${c}`;
        el.appendChild(h);
        h.addEventListener('pointerdown', e=>{
          e.stopPropagation(); e.preventDefault(); h.setPointerCapture(e.pointerId);
          const { width, height } = getElementSize(el);
          const startLeft = parseFloat(el.style.left || '0');
          const startTop = parseFloat(el.style.top || '0');
          document.body.classList.add('is-resizing');
          resizeState = {
            corner: c,
            startWidth: width,
            startHeight: height,
            startLeft,
            startTop,
            startX: e.clientX,
            startY: e.clientY,
            keepRatio: el.dataset.type === 'image' || e.shiftKey
          };
        });
        h.addEventListener('pointermove', e=>{
          if (!resizeState) return;
          const dx = (e.clientX - resizeState.startX) / currentScale;
          const dy = (e.clientY - resizeState.startY) / currentScale;
          let w = resizeState.startWidth;
          let hVal = resizeState.startHeight;
          let left = resizeState.startLeft;
          let top = resizeState.startTop;
          const corner = resizeState.corner;

          if (corner.includes('e')) {
            w = resizeState.startWidth + dx;
          }
          if (corner.includes('s')) {
            hVal = resizeState.startHeight + dy;
          }
          if (corner.includes('w')) {
            w = resizeState.startWidth - dx;
            left = resizeState.startLeft + (resizeState.startWidth - w);
          }
          if (corner.includes('n')) {
            hVal = resizeState.startHeight - dy;
            top = resizeState.startTop + (resizeState.startHeight - hVal);
          }

          if (resizeState.keepRatio) {
            const ratio = resizeState.startWidth / (resizeState.startHeight || 1);
            if (corner.includes('w') || corner.includes('e')) {
              hVal = w / ratio;
              if (corner.includes('n')) {
                top = resizeState.startTop + (resizeState.startHeight - hVal);
              }
            } else {
              w = hVal * ratio;
              if (corner.includes('w')) {
                left = resizeState.startLeft + (resizeState.startWidth - w);
              }
            }
          }

          // Limites para manter elementos dentro do canvas
          w = Math.max(20, Math.min(w, BASE_W));
          hVal = Math.max(20, Math.min(hVal, BASE_H));
          
          // Ajustar posição se o elemento sair dos limites após redimensionamento
          left = Math.max(0, Math.min(left, BASE_W - w));
          top = Math.max(0, Math.min(top, BASE_H - hVal));

          el.style.left = `${left}px`;
          el.style.top = `${top}px`;
          el.style.width = `${w}px`;
          el.style.height = `${hVal}px`;
        });
        h.addEventListener('pointerup', ()=>{ resizeState = null; document.body.classList.remove('is-resizing'); });
      });

      const rot = document.createElement('div');
      rot.className = 'handle h-rot';
      el.appendChild(rot);
      rot.addEventListener('pointerdown', e=>{
        e.stopPropagation(); e.preventDefault(); rot.setPointerCapture(e.pointerId);
        document.body.classList.add('is-rotating');
        const viewportRect = editorViewport.getBoundingClientRect();
        const rect = el.getBoundingClientRect();
        rotateState = {
          cx: rect.left - viewportRect.left + rect.width/2,
          cy: rect.top - viewportRect.top + rect.height/2
        };
      });
      rot.addEventListener('pointermove', e=>{
        if(!rotateState) return;
        const viewportRect = editorViewport.getBoundingClientRect();
        const mx = e.clientX - viewportRect.left;
        const my = e.clientY - viewportRect.top;
        const deg = Math.atan2(my-rotateState.cy, mx-rotateState.cx) * 180/Math.PI + 90;
        
        // Snap para ângulos padrão (a cada 15 graus)
        const snapAngle = 15;
        const snappedDeg = Math.round(deg / snapAngle) * snapAngle;
        const finalDeg = ((snappedDeg % 360) + 360) % 360;
        
        el.dataset.rot = String(finalDeg);
        applyRotation(el);
        selected = el;
      });
      rot.addEventListener('pointerup', ()=>{ rotateState=null; document.body.classList.remove('is-rotating'); });
    }
    function applyRotation(el){ 
      el.style.transform = `rotate(${el.dataset.rot || 0}deg)`;
      el.style.transformOrigin = 'center center';
    }
  
    // ---------- Drag com snap
    function attachDrag(el){
      el.addEventListener('pointerdown', e=>{
        if (e.target.classList.contains('handle')) return;
        if (editingText === el) return;
        if (editingText && editingText !== el) {
          stopEditingText();
        }
        e.preventDefault(); el.setPointerCapture(e.pointerId);
        const pointer = getPointerBase(e);
        const startLeft = parseFloat(el.style.left || '0');
        const startTop = parseFloat(el.style.top || '0');
        dragState = {
          offsetX: pointer.x - startLeft,
          offsetY: pointer.y - startTop,
          element: el
        };
        showGuides(false);
      });
      el.addEventListener('pointermove', e=>{
        if (!dragState) return;
        const pointer = getPointerBase(e);
        let nx = pointer.x - dragState.offsetX;
        let ny = pointer.y - dragState.offsetY;

        const snappedX = Math.round(nx / SNAP_GRID) * SNAP_GRID;
        const snappedY = Math.round(ny / SNAP_GRID) * SNAP_GRID;
        if (Math.abs(snappedX - nx) <= SNAP_TOL) nx = snappedX;
        if (Math.abs(snappedY - ny) <= SNAP_TOL) ny = snappedY;

        // Calcular centro do elemento para as guias centrais
        const elementWidth = parseFloat(dragState.element.style.width) || 200;
        const elementHeight = parseFloat(dragState.element.style.height) || 200;
        
        const cx = nx + elementWidth / 2;
        const cy = ny + elementHeight / 2;
        const ex = BASE_W / 2;
        const ey = BASE_H / 2;
        
        let showY = false, showX = false;
        if (Math.abs(cx - ex) <= SNAP_TOL) { nx += (ex - cx); showY = true; }
        if (Math.abs(cy - ey) <= SNAP_TOL) { ny += (ey - cy); showX = true; }
        showGuides(showX, showY);

        // Aplicar limitações para manter dentro do canvas
        nx = Math.max(0, Math.min(nx, BASE_W - elementWidth));
        ny = Math.max(0, Math.min(ny, BASE_H - elementHeight));

        el.style.left = `${nx}px`;
        el.style.top = `${ny}px`;
      });
      el.addEventListener('pointerup', ()=>{ dragState=null; showGuides(false); });
    }
    function showGuides(x=false,y=false){ 
      console.log('Mostrando guias:', { x, y });
      if (guideX) {
        guideX.style.display = x ? 'block' : 'none';
        if (x) console.log('Guia X ativado');
      }
      if (guideY) {
        guideY.style.display = y ? 'block' : 'none';
        if (y) console.log('Guia Y ativado');
      }
    }
  
    // ---------- Teclado
    document.addEventListener('keydown', e=>{
      if(!selected) return;
      if (editingText === selected){
        if (e.key === 'Escape'){
          e.preventDefault();
          stopEditingText();
        }
        return;
      }
      if ((e.key==='Delete' || e.key==='Backspace') && !(selected.dataset.type==='text' && document.activeElement===selected)){
        e.preventDefault();
        const toRemove = selected;
        selectItem(null);
        toRemove.remove();
        return;
      }
      const step = e.shiftKey? 10:1;
      const left = parseInt(selected.style.left||'0'), top=parseInt(selected.style.top||'0');
      // Movimento com setas respeitando os limites do canvas
      const newLeft = Math.max(0, Math.min(BASE_W - selected.offsetWidth, left + (e.key==='ArrowLeft' ? -step : e.key==='ArrowRight' ? step : 0)));
      const newTop = Math.max(0, Math.min(BASE_H - selected.offsetHeight, top + (e.key==='ArrowUp' ? -step : e.key==='ArrowDown' ? step : 0)));
      
      if(e.key==='ArrowLeft' || e.key==='ArrowRight')  selected.style.left = newLeft+'px';
      if(e.key==='ArrowUp' || e.key==='ArrowDown')    selected.style.top  = newTop+'px';
    });
  
    // ---------- Clipboard (colar imagem ou texto)
    // Ctrl/Cmd+V em qualquer lugar adiciona no centro do editor
    document.addEventListener('paste', async (e)=>{
      // primeiro, tenta imagem
      const items = e.clipboardData?.items || [];
      for (const it of items){
        if (it.type && it.type.indexOf('image') >= 0){
          const blob = it.getAsFile();
          if (blob){
            e.preventDefault();
            const url = URL.createObjectURL(blob);
            const img = createImageItem(url);
            return;
          }
        }
      }
      // se não for imagem, usa texto
      const text = e.clipboardData?.getData('text/plain');
      if (text && text.trim()){
        e.preventDefault();
        createTextBox(text.trim());
      }
    });
  
    // ---------- Botão Enviar Inteligente
    btnEnviar.addEventListener('click', handleEnviar);
    
    async function handleEnviar() {
      // Detectar tipo de conteúdo automaticamente
      const contentType = detectContentType();
      
      if (contentType === 'video') {
        // Mostrar configurações de vídeo se necessário
        if (exportSettings.classList.contains('hidden')) {
          exportSettings.classList.remove('hidden');
          return;
        }
        await exportAndSaveVideo();
      } else {
        // Exportar e salvar como imagem
        await exportAndSaveImage();
      }
    }
    
    function detectContentType() {
      // Verificar se há elemento de vídeo como fundo
      if (bgEl && bgEl.tagName === 'VIDEO') {
        return 'video';
      }
      
      // Verificar se há elementos animados
      const animatedElements = editor.querySelectorAll('.item[data-anim]:not([data-anim="none"])');
      if (animatedElements.length > 0) {
        return 'video';
      }
      
      // Por padrão, exportar como imagem
      return 'image';
    }
    
    function updateEnviarButton() {
      const contentType = detectContentType();
      const icon = btnEnviar.querySelector('.enviar-icon');
      const text = btnEnviar.querySelector('.enviar-text');
      
      if (contentType === 'video') {
        icon.className = 'fas fa-video enviar-icon';
        text.textContent = 'Enviar Vídeo';
        exportSettings.classList.remove('hidden');
      } else {
        icon.className = 'fas fa-image enviar-icon';
        text.textContent = 'Enviar Imagem';
        exportSettings.classList.add('hidden');
      }
    }
    async function exportFrameToPNG(tSec=null){
      await renderFrame(tSec);
      const a = document.createElement('a'); a.download='slide.png'; a.href=outCanvas.toDataURL('image/png'); a.click();
    }

    // ---------- Função para comprimir imagem
    async function compressImage(canvas, quality = 0.7, maxWidth = 1024) {
      return new Promise(resolve => {
        const tempCanvas = document.createElement('canvas');
        const tempCtx = tempCanvas.getContext('2d');
        
        // Calcular novo tamanho mantendo proporção
        const scale = Math.min(maxWidth / canvas.width, maxWidth / canvas.height, 1);
        tempCanvas.width = canvas.width * scale;
        tempCanvas.height = canvas.height * scale;
        
        // Desenhar imagem redimensionada
        tempCtx.drawImage(canvas, 0, 0, tempCanvas.width, tempCanvas.height);
        
        // Converter para blob com qualidade reduzida
        tempCanvas.toBlob(resolve, 'image/jpeg', quality);
      });
    }

    // ---------- Novas funções para salvar no servidor
    async function exportAndSaveImage() {
      try {
        // Desabilitar botão e mostrar progresso
        btnEnviar.disabled = true;
        btnEnviar.classList.add('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-spinner fa-spin enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Salvando...';

        // Renderizar frame
        await renderFrame();
        
        // Converter canvas para blob com compressão otimizada
        let blob = await new Promise(resolve => {
          outCanvas.toBlob(resolve, 'image/jpeg', 0.85);
        });

        // Se o arquivo ainda for muito grande, redimensionar
        if (blob.size > 2 * 1024 * 1024) { // Se maior que 2MB
          console.log(`Imagem muito grande (${(blob.size / 1024 / 1024).toFixed(2)}MB), redimensionando...`);
          blob = await compressImage(outCanvas, 0.7, 1024); // Reduzir para max 1024px
        }

        console.log(`Tamanho final da imagem: ${(blob.size / 1024 / 1024).toFixed(2)}MB`);

        // Obter dados do usuário atual do contexto global
        const userData = window.currentUserData || { id: 1, tt: 'Usuário Teste' };
        
        // Preparar dados para envio
        const formData = new FormData();
        formData.append('file', blob, 'post_image.jpg');
        formData.append('userId', userData.id);
        formData.append('type', 'image');
        formData.append('team', userData.team || '');
        formData.append('business', userData.business || '');

        // Enviar para servidor
        const response = await fetch('app/save_post.php', {
          method: 'POST',
          body: formData
        });

        // Verificar se a resposta é válida
        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const responseText = await response.text();
        console.log('Resposta do servidor (imagem):', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          throw new Error(`Resposta inválida do servidor: ${responseText.substring(0, 200)}...`);
        }

        if (result.success) {
          // Usar função de notificação global se disponível
          if (typeof window.notifySuccess === 'function') {
            window.notifySuccess('Imagem salva com sucesso!');
          } else {
            alert('Imagem salva com sucesso!');
          }
          console.log('Post criado com ID:', result.postId);
        } else {
          throw new Error(result.error || 'Erro desconhecido');
        }

      } catch (error) {
        console.error('Erro ao salvar imagem:', error);
        // Usar função de notificação global se disponível
        if (typeof window.notifyError === 'function') {
          window.notifyError('Erro ao salvar imagem: ' + error.message);
        } else {
          alert('Erro ao salvar imagem: ' + error.message);
        }
      } finally {
        // Restaurar botão
        btnEnviar.disabled = false;
        btnEnviar.classList.remove('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-paper-plane enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Enviar';
      }
    }

    async function exportAndSaveVideo() {
      try {
        // Desabilitar botão e mostrar progresso
        btnEnviar.disabled = true;
        btnEnviar.classList.add('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-spinner fa-spin enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Processando vídeo...';

        // Usar a função de exportação de vídeo existente mas modificada
        const videoBlob = await exportVideoToBlob();

        if (!videoBlob) {
          throw new Error('Falha na geração do vídeo');
        }

        // Obter dados do usuário atual do contexto global
        const userData = window.currentUserData || { id: 1, tt: 'Usuário Teste' };
        
        // Preparar dados para envio
        const formData = new FormData();
        formData.append('file', videoBlob, 'post_video.webm');
        formData.append('userId', userData.id);
        formData.append('type', 'video');
        formData.append('team', userData.team || '');
        formData.append('business', userData.business || '');

        btnEnviar.querySelector('.enviar-text').textContent = 'Salvando vídeo...';

        // Enviar para servidor
        const response = await fetch('app/save_post.php', {
          method: 'POST',
          body: formData
        });

        // Verificar se a resposta é válida
        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const responseText = await response.text();
        console.log('Resposta do servidor (vídeo):', responseText);
        
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          throw new Error(`Resposta inválida do servidor: ${responseText.substring(0, 200)}...`);
        }

        if (result.success) {
          // Usar função de notificação global se disponível
          if (typeof window.notifySuccess === 'function') {
            window.notifySuccess('Vídeo salvo com sucesso!');
          } else {
            alert('Vídeo salvo com sucesso!');
          }
          console.log('Post criado com ID:', result.postId);
        } else {
          throw new Error(result.error || 'Erro desconhecido');
        }

      } catch (error) {
        console.error('Erro ao salvar vídeo:', error);
        // Usar função de notificação global se disponível
        if (typeof window.notifyError === 'function') {
          window.notifyError('Erro ao salvar vídeo: ' + error.message);
        } else {
          alert('Erro ao salvar vídeo: ' + error.message);
        }
      } finally {
        // Restaurar botão
        btnEnviar.disabled = false;
        btnEnviar.classList.remove('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-paper-plane enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Enviar';
      }
    }

    // Função para exportar vídeo como blob (para salvamento no servidor)
    async function exportVideoToBlob() {
      return new Promise(async (resolve, reject) => {
        try {
          const fps = Math.max(10, Math.min(60, Number(vidFPS.value)||30));
          const dur = Math.max(1, Number(vidDur.value)||6);
          const totalFrames = Math.round(fps*dur);
          
          console.log(`Exportando vídeo como blob: ${dur}s × ${fps}fps = ${totalFrames} frames`);
      
          // Para vídeos de fundo, configurar reprodução
          if (bgEl && bgEl.tagName === 'VIDEO') {
            bgEl.currentTime = 0;
            bgEl.playbackRate = 1.0;
            const wasOriginallyMuted = bgEl.muted;
            bgEl.muted = false;
            bgEl.play();
          }
          
          // Capturar stream do canvas
          const canvasStream = outCanvas.captureStream();
          
          // Criar stream combinado com áudio se houver vídeo de fundo
          let finalStream = canvasStream;
          
          if (bgEl && bgEl.tagName === 'VIDEO' && !bgEl.muted) {
            try {
              const audioContext = new (window.AudioContext || window.webkitAudioContext)();
              const source = audioContext.createMediaElementSource(bgEl);
              const destination = audioContext.createMediaStreamDestination();
              source.connect(destination);
              source.connect(audioContext.destination);
              
              const videoTrack = canvasStream.getVideoTracks()[0];
              const audioTrack = destination.stream.getAudioTracks()[0];
              
              finalStream = new MediaStream([videoTrack, audioTrack]);
            } catch (error) {
              console.warn('Erro ao capturar áudio, usando apenas vídeo:', error);
              finalStream = canvasStream;
            }
          }
          
          // Configurar MediaRecorder
          let selectedFormat = null;
          const formats = [
            'video/webm;codecs=vp8,opus',
            'video/webm;codecs=vp9,opus',
            'video/webm;codecs=vp8',
            'video/webm;codecs=vp9', 
            'video/webm',
            'video/mp4'
          ];
          
          for (const format of formats) {
            if (MediaRecorder.isTypeSupported(format)) {
              selectedFormat = format;
              break;
            }
          }
          
          const rec = new MediaRecorder(finalStream, { 
            mimeType: selectedFormat || 'video/webm',
            videoBitsPerSecond: 2500000,
            audioBitsPerSecond: 128000
          });
          
          const chunks = [];
          let recordingStartTime = Date.now();
          
          rec.ondataavailable = e => {
            if(e.data.size) {
              chunks.push(e.data);
            }
          };
          
          rec.onstop = () => {
            const blob = new Blob(chunks, {type:'video/webm'});
            console.log(`Blob de vídeo criado: ${blob.size} bytes`);
            
            // Restaurar estado do vídeo
            if (bgEl && bgEl.tagName === 'VIDEO') {
              bgEl.playbackRate = 1.0;
            }
            
            resolve(blob);
          };
          
          rec.onerror = (error) => {
            console.error('Erro no MediaRecorder:', error);
            reject(error);
          };
          
          rec.start(100);
          
          const frameInterval = 1000 / fps;
          
          for (let i = 0; i < totalFrames; i++) {
            const currentTime = i / fps;
            const progress = Math.round((i / totalFrames) * 100);
            
            if (i % 30 === 0) {
              console.log(`Frame ${i}/${totalFrames} (${currentTime.toFixed(2)}s) - ${progress}%`);
            }
            
            await renderFrame(currentTime, false);
            
            const targetTime = recordingStartTime + (i + 1) * frameInterval;
            const currentRealTime = Date.now();
            const waitTime = Math.max(0, targetTime - currentRealTime);
            
            await new Promise(resolve => {
              setTimeout(() => {
                requestAnimationFrame(resolve);
              }, waitTime);
            });
          }
          
          rec.stop();
          
        } catch (error) {
          console.error('Erro durante exportação do blob:', error);
          if (bgEl && bgEl.tagName === 'VIDEO') {
            bgEl.playbackRate = 1.0;
          }
          reject(error);
        }
      });
    }
  
    // ---------- Função para atualizar informações de exportação
    function updateVideoExportInfo() {
      const videoExportInfo = document.getElementById('videoExportInfo');
      const videoExportInfoText = document.getElementById('videoExportInfoText');
      
      if (bgEl && bgEl.tagName === 'VIDEO' && bgEl.duration && !isNaN(bgEl.duration)) {
        const currentDuration = Number(vidDur.value) || 6;
        const videoDuration = bgEl.duration;
        const maxDuration = Math.min(60, videoDuration);
        
        videoExportInfo.classList.remove('hidden');
        
        if (currentDuration === maxDuration) {
          videoExportInfoText.textContent = `Usando duração do vídeo importado: ${maxDuration.toFixed(1)}s${videoDuration > 60 ? ' (limitado a 60s)' : ''}`;
        } else {
          videoExportInfoText.textContent = `Duração personalizada: ${currentDuration}s (vídeo importado tem ${videoDuration.toFixed(1)}s)`;
        }
      } else {
        videoExportInfo.classList.add('hidden');
      }
    }

    // ---------- Exportação de Vídeo
    async function exportVideo() {
      try {
        const fps = Math.max(10, Math.min(60, Number(vidFPS.value)||30));
        
        // SEMPRE usar a duração definida na interface para vídeos gravados
        // Isso garante que vídeos com duração "Infinity" sejam exportados corretamente
        const dur = Math.max(1, Number(vidDur.value)||6);
        console.log(`Usando duração da interface: ${dur}s (vidDur.value = ${vidDur.value})`);
        
        const totalFrames = Math.round(fps*dur);
        console.log(`Calculando exportação: ${dur}s × ${fps}fps = ${totalFrames} frames`);
    
        // Para vídeos de fundo, configurar reprodução
        if (bgEl && bgEl.tagName === 'VIDEO') {
          bgEl.currentTime = 0;
          bgEl.playbackRate = 1.0; // SEMPRE usar velocidade normal
          
          // Temporariamente desmutar para capturar áudio
          const wasOriginallyMuted = bgEl.muted;
          bgEl.muted = false;
          
          bgEl.play();
          console.log('Vídeo configurado: velocidade normal (1.0x), áudio habilitado para exportação');
          
          // Restaurar estado original após exportação
          setTimeout(() => {
            if (wasOriginallyMuted) {
              bgEl.muted = true;
              console.log('Áudio do vídeo restaurado para mutado');
            }
          }, (dur + 2) * 1000); // Aguardar exportação terminar + margem
        }

        // Desabilitar botão e mostrar progresso
        btnEnviar.disabled = true;
        btnEnviar.classList.add('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-spinner enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Exportando...';

        console.log(`Iniciando exportação: ${totalFrames} frames a ${fps}fps = ${dur}s`);
        
        // Capturar stream do canvas (vídeo)
        const canvasStream = outCanvas.captureStream();
        
        // Criar stream combinado com áudio se houver vídeo de fundo
        let finalStream = canvasStream;
        
        if (bgEl && bgEl.tagName === 'VIDEO' && !bgEl.muted) {
          try {
            // Capturar áudio do vídeo de fundo
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = audioContext.createMediaElementSource(bgEl);
            const destination = audioContext.createMediaStreamDestination();
            source.connect(destination);
            source.connect(audioContext.destination); // Manter áudio na interface
            
            // Combinar streams de vídeo e áudio
            const videoTrack = canvasStream.getVideoTracks()[0];
            const audioTrack = destination.stream.getAudioTracks()[0];
            
            finalStream = new MediaStream([videoTrack, audioTrack]);
            console.log('Stream combinado criado: vídeo + áudio');
          } catch (error) {
            console.warn('Erro ao capturar áudio, usando apenas vídeo:', error);
            finalStream = canvasStream;
          }
        } else {
          console.log('Sem áudio para capturar (vídeo mutado ou não é vídeo)');
        }
        
        // Tentar formatos mais compatíveis para metadados corretos
        let selectedFormat = null;
        const formats = [
          'video/webm;codecs=vp8,opus',
          'video/webm;codecs=vp9,opus',
          'video/webm;codecs=vp8',
          'video/webm;codecs=vp9', 
          'video/webm',
          'video/mp4'
        ];
        
        for (const format of formats) {
          if (MediaRecorder.isTypeSupported(format)) {
            selectedFormat = format;
            console.log('Usando formato de exportação:', format);
            break;
          }
        }
        
        const rec = new MediaRecorder(finalStream, { 
          mimeType: selectedFormat || 'video/webm',
          videoBitsPerSecond: 2500000, // 2.5 Mbps para qualidade consistente
          audioBitsPerSecond: 128000   // 128 kbps para áudio
        });
        const chunks = [];
        let recordingStartTime = Date.now();
        
        rec.ondataavailable = e=>{
          if(e.data.size) {
            chunks.push(e.data);
            console.log(`Chunk recebido: ${e.data.size} bytes`);
          }
        };
        
        rec.start(100); // Capturar dados a cada 100ms
        console.log('MediaRecorder iniciado com controle manual de timing');
    
        const frameInterval = 1000 / fps; // Intervalo entre frames em ms
        
        for (let i=0; i<totalFrames; i++){
          const currentTime = i/fps;
          const progress = Math.round((i / totalFrames) * 100);
          
          // Log a cada 30 frames (1 segundo a 30fps)
          if (i % 30 === 0) {
            console.log(`Frame ${i}/${totalFrames} (${currentTime.toFixed(2)}s) - ${progress}%`);
          }
          
          // Atualizar progresso no botão
          btnEnviar.querySelector('.enviar-text').textContent = `Exportando... ${progress}%`;
          
          await renderFrame(currentTime, false);
          
          // Aguardar o tempo correto para o próximo frame (mais preciso)
          const targetTime = recordingStartTime + (i + 1) * frameInterval;
          const currentRealTime = Date.now();
          const waitTime = Math.max(0, targetTime - currentRealTime);
          
          await new Promise(resolve => {
            setTimeout(() => {
              requestAnimationFrame(resolve);
            }, waitTime);
          });
        }
        
        const actualExportTime = (Date.now() - recordingStartTime) / 1000;
        console.log(`Exportação concluída em ${actualExportTime.toFixed(2)}s reais`);
        
        rec.stop();
        rec.onstop = ()=>{
          console.log(`MediaRecorder parado. Total de chunks: ${chunks.length}`);
          const totalSize = chunks.reduce((sum, chunk) => sum + chunk.size, 0);
          console.log(`Tamanho total do vídeo: ${totalSize} bytes`);
          
          const blob = new Blob(chunks, {type:'video/webm'});
          console.log(`Blob final criado: ${blob.size} bytes`);
          
          // Criar um vídeo temporário para verificar a duração
          const tempVideo = document.createElement('video');
          tempVideo.src = URL.createObjectURL(blob);
          tempVideo.addEventListener('loadedmetadata', () => {
            const videoDuration = tempVideo.duration;
            console.log(`Duração do vídeo exportado: ${videoDuration}s`);
            console.log(`Duração esperada: ${dur}s`);
            
            if (!isFinite(videoDuration) || isNaN(videoDuration)) {
              console.warn(`AVISO: Duração inválida (${videoDuration}). Isso pode ser um problema do formato WebM.`);
              console.log('Sugestão: O vídeo pode reproduzir corretamente mesmo com metadados inválidos.');
            } else if (Math.abs(videoDuration - dur) > 0.5) {
              console.warn(`AVISO: Duração incorreta! Esperado: ${dur}s, Obtido: ${videoDuration}s`);
            } else {
              console.log('✅ Duração do vídeo exportado está correta!');
            }
          });
          
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a'); a.href=url; a.download='slide.webm'; a.click();
          URL.revokeObjectURL(url);
          
          // Nada para limpar - usamos apenas o vídeo original
          // Restaurar velocidade normal do vídeo
          if (bgEl && bgEl.tagName === 'VIDEO') {
            bgEl.playbackRate = 1.0;
          }
          btnEnviar.disabled = false;
          btnEnviar.classList.remove('exporting');
          btnEnviar.querySelector('.enviar-icon').className = 'fas fa-paper-plane enviar-icon';
          btnEnviar.querySelector('.enviar-text').textContent = 'Enviar';
        };
        
      } catch (error) {
        console.error('Erro durante exportação:', error);
        // Restaurar velocidade normal do vídeo em caso de erro
        if (bgEl && bgEl.tagName === 'VIDEO') {
          bgEl.playbackRate = 1.0;
        }
        btnEnviar.disabled = false;
        btnEnviar.classList.remove('exporting');
        btnEnviar.querySelector('.enviar-icon').className = 'fas fa-paper-plane enviar-icon';
        btnEnviar.querySelector('.enviar-text').textContent = 'Enviar';
        alert('Erro durante a exportação do vídeo. Tente novamente.');
      }
    }
  
    // ---------- Renderização de Frame
    async function renderFrame(tSec=null, useExportVideo=false){
      octx.clearRect(0,0,outCanvas.width,outCanvas.height);
      // BG
      if (bgEl){
        if (bgEl.tagName==='IMG') {
          drawCoverImage(bgEl,0,0,outCanvas.width,outCanvas.height);
        } else {
          // Sempre usar o vídeo original - sem seeks, sem complicações
          drawCoverVideo(bgEl, 0, 0, outCanvas.width, outCanvas.height);
        }
      } else { octx.fillStyle='#fff'; octx.fillRect(0,0,outCanvas.width,outCanvas.height); }
  
      const items = [...editor.querySelectorAll('.item')].sort((a,b)=> {
        const aZ = +getComputedStyle(a).zIndex || 0;
        const bZ = +getComputedStyle(b).zIndex || 0;
        
        // Se z-index for igual, manter ordem DOM (elementos criados primeiro ficam atrás)
        if (aZ === bZ) {
          const allItems = [...editor.querySelectorAll('.item')];
          return allItems.indexOf(a) - allItems.indexOf(b);
        }
        
        return aZ - bZ; // Renderizar elementos com z-index menor primeiro (ficam atrás)
      });
      const sx = outCanvas.width / BASE_W;
      const sy = outCanvas.height / BASE_H;
  
      for(const el of items){
        // Usar posições e dimensões CSS definidas diretamente, não getBoundingClientRect
        let x = parseFloat(el.style.left || '0') * sx;
        let y = parseFloat(el.style.top || '0') * sy;
        let w = parseFloat(el.style.width || '200') * sx;
        let h = parseFloat(el.style.height || '100') * sy;
        const rot = (Number(el.dataset.rot||0))*Math.PI/180;
  
        // animação
        let alpha=1, tx=0, ty=0;
        if (tSec!==null){
          const type = el.dataset.anim||'none'; const delay= Number(el.dataset.delay||0); const dur= Number(el.dataset.dur||0.8);
          const t = Math.max(0, tSec - delay), p = Math.min(1, dur>0 ? t/dur : 1);
          const ease = (u)=> 1 - Math.pow(1-u, 3);
          if (type==='fade-in'){ alpha = ease(p); }
          else if (type.startsWith('slide-')){
            const D = 200*sx; const e = ease(p);
            if (type==='slide-left')   tx = -D*(1-e);
            if (type==='slide-right')  tx =  D*(1-e);
            if (type==='slide-top')    ty = -D*(1-e);
            if (type==='slide-bottom') ty =  D*(1-e);
            alpha = e;
          }
        }
  
        octx.save();
        octx.globalAlpha = alpha;
        octx.translate(x+w/2 + tx, y+h/2 + ty);
        octx.rotate(rot);
  
        if (el.dataset.type==='image'){
          // Agora el é o wrapper, precisamos pegar a imagem filha
          const img = el.querySelector('img');
          if (img) {
            octx.drawImage(img, -w/2, -h/2, w, h);
          }
        } else {
          // Replicar exatamente o CSS do editor
          const fs = Number(el.dataset.fontSize||28)*sy;
          const fw = el.dataset.fontWeight||'600';
          const lh = fs*1.35; // Mesmo line-height do CSS
          const paddingV = 12*sy, paddingH = 16*sy;
          
          // Usar a mesma fonte do CSS para consistência
          octx.font=`${fw} ${fs}px Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`;
          octx.textBaseline = 'middle';
  
          // Calcular wrap de texto usando as mesmas dimensões do CSS
          const maxTextW = w - (paddingH * 2);
          const lines = wrapText(el.innerText, fs, fw, maxTextW);
          
          // Usar exatamente as mesmas dimensões CSS do elemento para o fundo
          const totalLines = lines.length;
          const totalTextHeight = totalLines * lh;
          const bgW = w;
          const bgH = h; // Usar a altura CSS real do elemento, não calculada
  
          // fundo - usar opacidade correta baseada no CSS
          if (el.dataset.bgNone !== 'true'){
            const bgColor = el.dataset.bgColor || '#ffffff';
            octx.fillStyle = hexToRgba(bgColor, 0.86);
            // Posicionar o fundo centralizado baseado na altura do texto
            const bgX = -w/2;
            const bgY = -bgH/2;
            // border-radius: 0.85rem = 13.6px
            octx.save();
            octx.beginPath();
            const radius = 13.6*sy;
            octx.roundRect(bgX, bgY, bgW, bgH, radius);
            octx.fill();
            octx.restore();
          }
  
          // Replicar exatamente o comportamento do CSS flexbox
          const align = el.dataset.align || 'left';
          octx.fillStyle = el.dataset.fontColor || '#111827';
          octx.textBaseline = 'middle';
          
          // Centralização vertical precisa (como CSS align-items: center)
          const startY = -(totalTextHeight / 2) + (lh / 2);
          
          for (let i = 0; i < lines.length; i++){
            const ln = lines[i];
            const tw = octx.measureText(ln).width;
            
            // Alinhamento horizontal baseado no CSS flexbox
            let txText;
            if (align === 'left') {
              txText = -w/2 + paddingH; // justify-content: flex-start
            } else if (align === 'center') {
              txText = -tw/2; // justify-content: center
            } else if (align === 'right') {
              txText = w/2 - tw - paddingH; // justify-content: flex-end
            }
            
            const ty = startY + (i * lh);
            octx.fillText(ln, txText, ty);
          }
        }
        octx.restore();
      }
    }
  
    function drawCoverImage(img,x,y,w,h){
      const iw=img.naturalWidth, ih=img.naturalHeight; if(!iw||!ih) return;
      const ir=iw/ih, r=w/h; let sx,sy,sw,sh;
      if (ir>r){ sh=ih; sw=ih*r; sx=(iw-sw)/2; sy=0; } else { sw=iw; sh=iw/r; sx=0; sy=(ih-sh)/2; }
      octx.drawImage(img, sx,sy,sw,sh, x,y,w,h);
    }
    function drawCoverVideo(video,x,y,w,h){
      const iw=video.videoWidth, ih=video.videoHeight; if(!iw||!ih) return;
      const ir=iw/ih, r=w/h; let sx,sy,sw,sh;
      if (ir>r){ sh=ih; sw=ih*r; sx=(iw-sw)/2; sy=0; } else { sw=iw; sh=iw/r; sx=0; sy=(ih-sh)/2; }
      octx.drawImage(video, sx,sy,sw,sh, x,y,w,h);
    }
    function wrapText(text, fs, fw, maxW){
      const ctx = octx; 
      // Usar a mesma fonte do CSS para consistência
      ctx.font=`${fw||600} ${fs}px Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`;
      const words = (text||'').split(/\s+/); const lines=[]; let line='';
      for(const w of words){ const t = line? line+' '+w : w; if (ctx.measureText(t).width<=maxW) line=t; else { lines.push(line); line=w; } }
      if(line) lines.push(line); return lines;
    }
  
    // ---------- Salvar / Carregar JSON (inclui novos campos)
    btnSaveJSON.addEventListener('click', ()=>{
      const data = serializeLayout();
      const blob = new Blob([JSON.stringify(data,null,2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href=url; a.download='layout.json'; a.click();
      URL.revokeObjectURL(url);
    });
    loadJSON.addEventListener('change', e=>{
      const f = e.target.files?.[0]; if(!f) return;
      f.text().then(json=> loadLayout(JSON.parse(json)));
    });
  
    function serializeLayout(){
      const items = [...editor.querySelectorAll('.item')].map(el=>({
        type: el.dataset.type,
        left: parseInt(el.style.left||'0'),
        top:  parseInt(el.style.top||'0'),
        width: parseInt(el.style.width||el.offsetWidth),
        height: parseInt(el.style.height||el.offsetHeight),
        rot: Number(el.dataset.rot||0),
        z: +getComputedStyle(el).zIndex||0,
        anim: el.dataset.anim||'none',
        delay: Number(el.dataset.delay||0),
        dur: Number(el.dataset.dur||0.8),
        text: el.dataset.type==='text' ? el.innerText : null,
        fontSize: el.dataset.type==='text' ? Number(el.dataset.fontSize||28): null,
        fontWeight: el.dataset.type==='text' ? el.dataset.fontWeight||'600': null,
        fontColor: el.dataset.type==='text' ? el.dataset.fontColor||'#111827': null,
        align: el.dataset.type==='text' ? (el.dataset.align||'left') : null,
        bgColor: el.dataset.type==='text' ? (el.dataset.bgColor||'#ffffff') : null,
        bgNone: el.dataset.type==='text' ? (el.dataset.bgNone==='true') : null,
        src: el.dataset.type==='image' ? el.querySelector('img')?.src : null
      }));
      return { items };
    }
  
    function loadLayout(data){
      editor.querySelectorAll('.item').forEach(n=>n.remove());
      for (const it of data.items||[]){
        if (it.type==='text'){
          const box = document.createElement('div');
          box.className='item draggable text-box'; box.contentEditable='false';
          box.dataset.type='text';
          box.dataset.fontSize=String(it.fontSize||28);
          box.dataset.fontWeight=it.fontWeight||'600';
          box.dataset.fontColor=it.fontColor||'#111827';
          box.dataset.align=it.align||'left';
          box.dataset.bgColor=it.bgColor||'#ffffff';
          box.dataset.bgNone=it.bgNone ? 'true' : 'false';
          box.dataset.anim=it.anim||'none'; box.dataset.delay=String(it.delay||0); box.dataset.dur=String(it.dur||0.8);
          box.addEventListener('blur', handleTextBlur);
          placeDefaults(box, it.left, it.top, it.width, it.height, it.rot||0); box.style.zIndex = String(it.z||0);
          box.innerText = it.text||''; addHandles(box); attachDrag(box); editor.appendChild(box);
          applyTextStyle(box); applyTextBg(box);
        } else if (it.type==='image'){
          const wrapper = document.createElement('div');
          wrapper.className='item draggable image-item'; wrapper.dataset.type='image';
          wrapper.dataset.anim=it.anim||'none'; wrapper.dataset.delay=String(it.delay||0); wrapper.dataset.dur=String(it.dur||0.8);
          
          const img = document.createElement('img');
          img.style.width = '100%';
          img.style.height = '100%';
          img.style.objectFit = 'cover';
          img.style.borderRadius = '0.85rem';
          
          img.onload = ()=>{
            placeDefaults(wrapper, it.left, it.top, it.width, it.height, it.rot||0);
            wrapper.style.zIndex=String(it.z||0);
            addHandles(wrapper);
            attachDrag(wrapper);
          };
          img.src = it.src;
          wrapper.appendChild(img);
          editor.appendChild(wrapper);
        }
      }
    }

    function getPointerBase(e) {
      // Calcular coordenadas de forma mais direta e independente
      const editorRect = editor.getBoundingClientRect();
      const viewportRect = editorViewport.getBoundingClientRect();
      
      // Usar coordenadas absolutas e depois converter
      const x = (e.clientX - editorRect.left) / currentScale;
      const y = (e.clientY - editorRect.top) / currentScale;
      
      return { x, y };
    }

    function getElementSize(el) {
      // Sempre usar computedStyle para consistência
      const computedStyle = getComputedStyle(el);
      return {
        width: parseFloat(computedStyle.width) || 200,
        height: parseFloat(computedStyle.height) || 200
      };
    }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
