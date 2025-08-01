//PUSH NOTIFICATIONS
function formatar(mascara, valor) {
    valor = valor.replace(/\D/g, ''); // Remove qualquer caractere não numérico
    let formatado = '';
    let j = 0;
    for (let i = 0; i < mascara.length; i++) {
        if (mascara[i] === '#' && j < valor.length) {
            formatado += valor[j++];
        } else if (mascara[i] !== '#') {
            formatado += mascara[i];
        }
    }
    return formatado;
}

function pushNotification(val){
	// Caso window.Notification não exista, quer dizer que o browser não possui suporte a web notifications, então cancela a execução
	 if(!window.Notification){
		return false;
	 }

	// Função utilizada para enviar a notificação para o usuário
	var notificar = function(){
		if(val == ''){
			var tituloMensagem = "Nova Mensagem de Workz!";
			var icone = "https://workz.com.br/logo_sm.png";
			var mensagem = "Workz está em desenvolvimento! \n\n Aproveite as funcionalidades disponíveis!";
			return new Notification(tituloMensagem,{
				icon : icone,
				body : mensagem
			}); 
		}else{
			var tituloMensagem = "Nova Mensagem de Workz!";
			var icone = "https://workz.com.br/logo_sm.png";
			var mensagem = val;
			return new Notification(tituloMensagem,{
				icon : icone,
				body : mensagem
			});
		}					 
	};

	 // Verifica se existe a permissão para exibir a notificação; caso ainda não exista ("default"), então solicita permissão.
	 // Existem três estados para a permissão:
	 // "default" => o usuário ainda não deu nem negou permissão (neste caso deve ser feita a solicitação da permissão)
	 // "denied" => permissão negada (como o usuário não deu permissão, o web notifications não irá funcionar)
	 // "granted" => permissão concedida

	 // A permissão já foi concedida, então pode enviar a notificação
	 if(Notification.permission==="granted"){
		notificar();
	 }else if(Notification.permission==="default"){
		// Solicita a permissão e caso o usuário conceda, envia a notificação
		Notification.requestPermission(function(permission){
			if(permission=="granted"){
				notificar();
			}
		});
	 }
};

//nl2br
function nl2br (str, is_xhtml) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}


//WAIT FOR ELEMENT

let isWaiting = false; // Variável para controlar o estado de espera

function waitForElm(selector, timeout = 5000) { // Defina um tempo limite padrão de 5 segundos (5000 milissegundos)
    // Verificar se a função já está em execução
    if (isWaiting) {
        return Promise.resolve(); // Retorna uma promessa vazia se já estiver esperando
    }

    // Define o estado de espera como verdadeiro
    isWaiting = true;

    return new Promise((resolve, reject) => {
        const timeoutId = setTimeout(() => {
            isWaiting = false; // Define o estado de espera como falso
            reject(new Error('Tempo limite excedido')); // Rejeita a promessa com um erro se o tempo limite for excedido
        }, timeout);

        if (document.querySelector(selector)) {
            // Se o elemento já estiver presente, resolve imediatamente
            clearTimeout(timeoutId); // Limpa o tempo limite
            isWaiting = false; // Define o estado de espera como falso
            return resolve(document.querySelector(selector));
        }

        const observer = new MutationObserver(mutations => {
            if (document.querySelector(selector)) {
                // Se o elemento for encontrado, resolve e desconecta o observador
                clearTimeout(timeoutId); // Limpa o tempo limite
                resolve(document.querySelector(selector));
                observer.disconnect();
                isWaiting = false; // Define o estado de espera como falso
            }
        });

        // Observa alterações no corpo do documento
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
}


//SLEEP

function sleep(ms) {
	return new Promise(resolve => setTimeout(resolve, ms));
}

//IFRAME HEIGHT

function setIframeHeight(iframe){
	if (iframe) {
		var iframeWin = iframe.contentWindow || iframe.contentDocument.parentWindow;
		if (iframeWin.document.body) {
			iframe.height = iframeWin.document.documentElement.scrollHeight || iframeWin.document.body.scrollHeight;
		}
	}
};

//GET URL FROM DOM

function changeURL(url){				
	document.location = url;
}

//LOADING PAGE

$(window).on('load', function () {	
	$('#loading').delay(500).fadeOut();
			
    setIframeHeight(document.getElementById('ps_login'));				

    // Seleciona o elemento de áudio
    const audioPlayer = document.getElementById('audio-player');

    // Tenta tocar o áudio
    if (audioPlayer) {
        const playPromise = audioPlayer.play();

        // Verifica se o navegador bloqueou o autoplay
        if (playPromise !== undefined) {
            playPromise.catch((error) => {
                console.warn("Autoplay bloqueado: o usuário precisa interagir com a página primeiro.");
            });
        }
    }
});

//INSERT EMOJI WHEN CLICK

function insertAtCursor(myField, myValue){				
	var previousValue = myField.innerHTML.replace('<div>', '<br>');				
	myField.innerHTML = previousValue + myValue;							
}

//IMAGE PREVIEW (FORM)

function imgPreview(el) {
    const [file] = el.files;
    var labl = el.parentNode.getElementsByTagName('label')[0];
    var span = labl.getElementsByTagName('span');
    var simg = labl.getElementsByTagName('img')[0];
	var imTxtCreated = document.getElementById('imTxt');

    if (!file) {
        // Quando nenhum arquivo for selecionado
        simg.src = 'https://workz.com.br/#';
        span[0].classList.remove('display-none');
        span[1].classList.add('display-none');
        
        // Remover o campo de texto, se ele existir
        var imTxtCreated = document.getElementById('imTxt');
        if (el.parentNode.contains(imTxtCreated)) {
            imTxtCreated.remove();
        }
        return;
    }

    // Ocultar o ícone de upload e mostrar a imagem
    span[0].classList.add('display-none');
    span[1].classList.remove('display-none');

    // Adicionar uma classe de fade para efeito
    simg.classList.add('fade');

    // Criação do canvas para redimensionar a imagem
    var canvas = document.createElement("canvas");
    var ctx = canvas.getContext('2d');
    var img = new Image();
    var maxW = 500;
    var maxH = 500;

    img.onload = function() {
        var iw = img.width;
        var ih = img.height;
        var scale = Math.min((maxW / iw), (maxH / ih));
        var iwScaled = iw * scale;
        var ihScaled = ih * scale;

        // Redimensionar o canvas com as dimensões escaladas
        canvas.width = iwScaled;
        canvas.height = ihScaled;

        // Desenhar a imagem no canvas
        ctx.drawImage(img, 0, 0, iwScaled, ihScaled);

        // Converter a imagem para base64
        var dataUrl = canvas.toDataURL("image/jpeg", 0.5);
        
        // Atualizar o src da imagem com a versão base64
        simg.src = dataUrl;       

        // Criar ou atualizar o campo de texto oculto para armazenar o base64 da imagem
        var imTxtCreated = document.getElementById('imTxt');
        if (!el.parentNode.contains(imTxtCreated)) {
            var imTxt = document.createElement('input');
            imTxt.classList.add('display-none');
            imTxt.id = 'imTxt';
            imTxt.name = 'imTxt';
            el.parentNode.appendChild(imTxt);
            imTxtCreated = imTxt;
        }
        // Armazenar o valor codificado em base64
        imTxtCreated.value = btoa(dataUrl);

        // Remover a classe de fade após 3 segundos
        setTimeout(() => simg.classList.remove('fade'), 3000);
    };

    // Carregar a imagem do arquivo selecionado
    img.src = URL.createObjectURL(file);
}


function toDataURL(url, callback){
	var xhr = new XMLHttpRequest();
	xhr.onload = function(){
		var reader = new FileReader();
		reader.onloadend = function(){
			callback(reader.result);
		}
		reader.readAsDataURL(xhr.response);
	};
	xhr.open('GET', url);
	xhr.responseType = 'blob';
	xhr.send();
}

//CONTENTEDITABLE PREVENT ADDING <DIV> ON ENTER

function divPrevent(el){
	$(el).keydown(function(e){		
		if (e.keyCode == 13) {			
			document.execCommand('insertHTML', false, '<br><br>');			
			return false;
		}
	});
}

//POSTBOX EDITOR

function pblsnd(el){
	var pblButton = el.parentNode.querySelector('input[type="submit"]');	
	var img = el.getElementsByTagName('img');
	if(img.length > 1){
		img[0].remove();
	}
	if(el.innerHTML == ''){
		pblButton.disabled = true;
	}else{
		pblButton.disabled = false;		
		if(img.length > 0){
			//IF AN IMAGE EXISTS
			var charged = el.getElementsByTagName('div');
			if(charged.length == 0){
				for(i=0; i < img.length; i++){
					img[i].classList.add('cover', 'w-rounded-5', 'background-white');
					//CHANGING POST EDITOR STYLES
					el.classList.add('text-center', 'background-gray');
					el.classList.remove('input-border', 'border-like-input');
					el.contentEditable = false;			
					document.getElementById('photo-comment').classList.remove('display-none');
					document.getElementById('add-hiperlink').classList.add('display-none');				
					//CREATING A FONT AWESOME SPAN FOR CANCEL BUTTON
					var cancelButton = document.createElement('span');		
					cancelButton.classList.add('fa-stack', 'w-color-gr-to-gr', 'float-right', 'pointer');
					cancelButton.title = 'Cancelar Imagem';
					cancelButton.id = 'photo-cancel';					
					cancelButton.onclick = function(){
						restorePostBox(this);
					}					
					document.getElementById('post-menu').appendChild(cancelButton);
					var FA01 = document.createElement('i');
					FA01.classList.add('fas', 'fa-circle', 'fa-stack-2x');
					cancelButton.appendChild(FA01);
					var FA02 = document.createElement('i');
					FA02.classList.add('fas', 'fa-times', 'fa-stack-1x', 'dark');
					cancelButton.appendChild(FA02);
				}
			}
		}else if(el.innerText.slice(0, 8) == 'https://'){
			el.classList.add('text-center', 'background-gray');
			el.classList.remove('input-border', 'border-like-input');
			el.contentEditable = false;
			getMetaFromURL(el.innerText, el);
			el.innerText = 'Carregando...';
			//CREATING A FONT AWESOME SPAN FOR CANCEL BUTTON
			var cancelButton = document.createElement('span');		
			cancelButton.classList.add('fa-stack', 'w-color-gr-to-gr', 'float-right', 'pointer');
			cancelButton.title = 'Cancelar Imagem';
			cancelButton.id = 'photo-cancel';					
			cancelButton.onclick = function(){
				restorePostBox(this);
			}					
			document.getElementById('post-menu').appendChild(cancelButton);
			var FA01 = document.createElement('i');
			FA01.classList.add('fas', 'fa-circle', 'fa-stack-2x');
			cancelButton.appendChild(FA01);
			var FA02 = document.createElement('i');
			FA02.classList.add('fas', 'fa-times', 'fa-stack-1x', 'dark');
			cancelButton.appendChild(FA02);
		}else{
			el.contentEditable = true;
		}
	}
}

function restorePostBox(el){
	var postBox = document.getElementById('postBox');		
	postBox.classList.remove('text-center', 'background-gray');
	postBox.classList.add('input-border', 'border-like-input');
	postBox.innerHTML = '';
	postBox.contentEditable = true;	
	document.getElementById('photo-comment').classList.add('display-none');
	document.getElementById('add-hiperlink').classList.remove('display-none');
	document.getElementById('photo-cancel').remove();
	el.remove();	
}

//POSTADD WORKS ON BOTH (POSTBOX AND TEXTBOX)

function postAdd(el, type, target){
	var target = document.getElementById(target);
	if(type == 'media'){
		var mediaInput = document.createElement('input');
		mediaInput.type = 'file';
		mediaInput.className = 'display-none';		
		el.parentNode.appendChild(mediaInput);
		mediaInput.accept = 'image/*';
		mediaInput.click();
		mediaInput.onchange = function(){
			const [file] = mediaInput.files;
			if(file){
				toDataURL(URL.createObjectURL(file), function(dataUrl){
					var mediaPreview = document.createElement('img');
					mediaPreview.width = 200;
					mediaPreview.src = dataUrl;						
					target.appendChild(mediaPreview);
				})
				mediaInput.remove();				
			}
		}
	}else if(type == 'hlink'){
		var sLnk=prompt('Escreve a sua URL aqui','https:\/\/');
		if(sLnk&&sLnk!=''&&sLnk!='https://'){
			target.focus();
			document.execCommand('createlink', false, sLnk);
		}
	}else if(type == 'photo-comment'){
		var charged = target.getElementsByTagName('div');
		if(charged.length > 0){
			charged[0].remove();
		}else{
			var container = document.createElement('div');;
			container.classList.add('position-absolute', 'large-12', 'medium-12', 'small-12', 'abs-t-0', 'abs-r-0', 'abs-b-0', 'abs-l-0', 'background-black-transparent', 'height-100', 'cm-pad-20', 'w-rounded-5', 'overflow-auto');
			target.appendChild(container);		
			var comment = document.createElement('div');
			comment.classList.add('background-white-transparent', 'cm-pad-10', 'w-shadow', 'w-rounded-5', 'text-left', 'speech-bubble', 'position-absolute', 'abs-t-0');
			comment.contentEditable = true;
			container.appendChild(comment);
			comment.focus();
		}				
	}
}

function getMetaFromURL(url, target){
	$.post('functions/news.php', { url: url }, function(data){
		target.innerHTML = '';
		var data = data.split("|*|");
		var dataImg = document.createElement('img');
		dataImg.src = data[1];
		dataImg.classList.add('cover', 'w-rounded-5', 'background-white');
		target.appendChild(dataImg);
		var container = document.createElement('div');;
		container.classList.add('position-absolute', 'large-12', 'medium-12', 'small-12', 'abs-t-0', 'abs-r-0', 'abs-b-0', 'abs-l-0', 'background-black-transparent', 'height-100', 'cm-pad-20', 'w-rounded-5', 'overflow-auto', 'pointer');
		target.appendChild(container);
		container.addEventListener('click', function() {
			location.href = url;
		}, false);
		var comment = document.createElement('div');
		comment.classList.add('background-white-transparent', 'cm-pad-10', 'w-shadow', 'w-rounded-5', 'text-left', 'speech-bubble', 'position-absolute', 'abs-t-0');
		comment.innerText = data[0];
		container.appendChild(comment);
	});
	
	
	
	/*
	console.log(url);
	var x = new XMLHttpRequest();
	x.open("GET", url, true);
	x.onreadystatechange = function(){
		if (x.readyState == 4 && x.status == 200){
			var doc = x.responseXML;
			target.innerHTML = doc;
		}
	};
	x.send(null);
	
	var txt = "";
	target.innerHTML = txt;  
	$.ajax({
		url,
		error: function() {
			txt = "Unable to retrieve webpage source HTML";
		}, 
		success: function(response){		
			response = $.parseHTML(response);
			$.each(response, function(i, el){
				if(el.nodeName.toString().toLowerCase() == 'meta' && $(el).attr("name") != null && typeof $(el).attr("name") != "undefined"){
					txt += $(el).attr("name") +"="+ ($(el).attr("content")?$(el).attr("content"):($(el).attr("value")?$(el).attr("value"):"")) +"<br>";
					//console.log($(el).attr("name") ,"=", ($(el).attr("content")?$(el).attr("content"):($(el).attr("value")?$(el).attr("value"):"")), el);
				}
			});
		},
		complete: function(){
			target.innerHTML = txt;
		}
	});
	*/
}

//TEXTBOX EDITOR

/*
var oDoc, sDefTxt;
function initDoc(){
	setTimeout(()=>{
		oDoc = document.getElementById("textBox");
		sDefTxt = oDoc.innerHTML;
		if (document.compForm.switchMode.checked) { setDocMode(true); }
	},1000);  
}
function formatDoc(sCmd, sValue) {
	//sCmd = Comando cmd para alterar o texto	
	if(sCmd === 'forecolor'){
		//sCmd é igual a "forecolor"
		document.getElementById('toolsTxtColorSelected').style.color = sValue;
	}else if(sCmd === 'backcolor'){
		//sCmd é igual a "backcolor"
		document.getElementById('toolsTxtFillSelected').style.color = sValue;
	}
	//div não está como HTML. executa o comando sCmd.
	document.execCommand(sCmd, false, sValue);
	oDoc.focus();
}

function validateMode() {
  if (!document.compForm.switchMode.checked) { return true ; }
  alert("Desmarque \"Mostrar Código HTML\".");
  oDoc.focus();
  return false;
}
function setDocMode(bToSource) {
  var oContent;
  if (bToSource) {
    oContent = document.createTextNode(oDoc.innerHTML);
    oDoc.innerHTML = "";
    var oPre = document.createElement("pre");
    oDoc.contentEditable = false;
    oPre.id = "sourceText";
    oPre.contentEditable = true;
    oPre.appendChild(oContent);
    oDoc.appendChild(oPre);
    document.execCommand("defaultParagraphSeparator", false, "div");
  } else {
    if (document.all) {
      oDoc.innerHTML = oDoc.innerText;
    } else {
      oContent = document.createRange();
      oContent.selectNodeContents(oDoc.firstChild);
      oDoc.innerHTML = oContent.toString();
    }
    oDoc.contentEditable = true;
  }
  oDoc.focus();
}

function printDoc() {
    var printWindow = window.open("", "_blank","toolbar=no,location=no");
    printWindow.document.open();
    printWindow.document.write("<html><head><title>Workz! Artigos</title></head><body>");
    
    var printContent = oDoc.cloneNode(true);
    
    // Ajuste o estilo da cópia para garantir que a formatação seja preservada
    var styleElement = document.createElement("style");
    styleElement.innerHTML = `
        body {
            margin: 0;
            font-family: Arial, sans-serif;
			padding: 30mm 20mm 20mm 30mm;
			width: 210mm;
			min-height: 297mm;
			font-size: 14px;
        }
    `;
    
    printWindow.document.head.appendChild(styleElement);
    printWindow.document.body.appendChild(printContent);
    
    printWindow.document.close();
    printWindow.print();
    printWindow.close();
}
function setZoom(zoom,el){      
		transformOrigin = [0.5,0];
	    el = el || instance.getContainer();
	    var p = ["webkit", "moz", "ms", "o"],
            s = "scale(" + zoom + ")",
            oString = (transformOrigin[0] * 100) + "% " + (transformOrigin[1] * 100) + "%";
	    for (var i = 0; i < p.length; i++) {
	        el.style[p[i] + "Transform"] = s;
	        el.style[p[i] + "TransformOrigin"] = oString;
	    }
	    el.style["transform"] = s;
	    el.style["transformOrigin"] = oString;
}

function formMode(vl, el){
	var child = el.getElementsByTagName('div');
	var toolsTxtArticle = document.getElementById('toolsTxtArticle');
	var toolsTxtForm = document.getElementById('toolsTxtForm');	
	if(vl == 0){
		el.innerHTML = '<div id="textBox" contenteditable="true" class="page"></div>';
		toolsTxtArticle.classList.remove('display-none');
		toolsTxtForm.classList.add('display-none');
	}else if(vl == 1){
		el.innerHTML = '<div id="formulario" class="w-community-container overflow-x-hidden cm-pad-20-t"></div>';
		goPost('backengine/editor/form_content.php', 'formulario', '', '1');
		toolsTxtArticle.classList.add('display-none');
		toolsTxtForm.classList.remove('display-none');
	}

}
*/

// TEXTBOX EDITOR

document.addEventListener("DOMContentLoaded", () => {
    let oDoc = null;

    // Observa alterações no DOM para detectar a criação do elemento #textBox
    const observer = new MutationObserver(() => {
        oDoc = document.getElementById("textBox");
        if (oDoc) {
            observer.disconnect(); // Para de observar após encontrar o elemento
            initializeEditor(oDoc);
        }
    });

    // Configura o observer para monitorar mudanças no DOM
    observer.observe(document.body, { childList: true, subtree: true });

    // Inicializa o editor após o elemento #textBox estar disponível
    const initializeEditor = (editorElement) => {
        const switchModeCheckbox = document.getElementById("switchBox");
        const toolsTxtColorSelected = document.getElementById("toolsTxtColorSelected");

        if (switchModeCheckbox && switchModeCheckbox.checked) {
            setDocMode(true);
        }

        // Função para formatar o texto
        const formatDoc = (command, value) => {
            if (command === "forecolor" && toolsTxtColorSelected) {
                toolsTxtColorSelected.style.color = value;
            }
            document.execCommand(command, false, value);
            editorElement.focus();
        };

        // Alterna entre modo HTML e modo visual
        const setDocMode = (toSource) => {
            if (toSource) {
                const sourceText = document.createElement("pre");
                sourceText.id = "sourceText";
                sourceText.contentEditable = true;
                sourceText.textContent = editorElement.innerHTML;

                editorElement.innerHTML = "";
                editorElement.contentEditable = false;
                editorElement.appendChild(sourceText);
            } else {
                const sourceText = editorElement.querySelector("#sourceText");
                if (sourceText) {
                    editorElement.innerHTML = sourceText.textContent;
                    editorElement.contentEditable = true;
                }
            }
            editorElement.focus();
        };

        // Imprime o conteúdo do editor
        const printDoc = () => {
            const printWindow = window.open("", "_blank", "toolbar=no,location=no");
            printWindow.document.write(`
                <html>
                    <head><title>Workz! Artigos</title></head>
                    <body style="margin: 0; font-family: Arial, sans-serif; padding: 30mm 20mm; width: 210mm; min-height: 297mm; font-size: 14px;">
                        ${editorElement.innerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
        };

        // Ajusta o zoom do editor
        const setZoom = (zoom, element = editorElement) => {
            element.style.transform = `scale(${zoom})`;
            element.style.transformOrigin = "50% 0%";
        };

        // Altera o tamanho da fonte
        const changeFontSize = (increment) => {
            const selection = window.getSelection();
            if (!selection.rangeCount) return;

            const range = selection.getRangeAt(0);
            const span = document.createElement("span");

            // Define o novo tamanho da fonte
            const currentSize = window
                .getComputedStyle(range.startContainer.parentElement)
                .fontSize.replace("px", "");
            const newSize = Math.max(8, parseInt(currentSize) + increment) + "px";

            span.style.fontSize = newSize;
            span.appendChild(range.extractContents());
            range.insertNode(span);
        };

        // Alterna entre modos de formulário e artigo
        const formMode = (mode, container) => {
            if (mode === 0) {
                container.innerHTML = '<div id="textBox" contenteditable="true" class="page"></div>';
                document.getElementById("toolsTxtArticle").classList.remove("display-none");
                document.getElementById("toolsTxtForm").classList.add("display-none");
            } else if (mode === 1) {
                container.innerHTML = '<div id="formulario" class="w-community-container overflow-x-hidden cm-pad-20-t"></div>';
                console.warn("Função 'goPost' precisa ser implementada.");
                document.getElementById("toolsTxtArticle").classList.add("display-none");
                document.getElementById("toolsTxtForm").classList.remove("display-none");
            }
        };

        // Exponha funções para uso global se necessário
        window.formatDoc = formatDoc;
        window.setDocMode = setDocMode;
        window.printDoc = printDoc;
        window.setZoom = setZoom;
        window.changeFontSize = changeFontSize;
        window.formMode = formMode;
    };
});

// Função para criar uma enquete
const createPoll = () => {
    const editor = document.getElementById("textBox");
    if (!editor) {
        console.error("Elemento 'textBox' não encontrado.");
        return;
    }

    // Adiciona campo de pergunta
    const questionInput = document.createElement("input");
    questionInput.type = "text";
    questionInput.placeholder = "Digite sua pergunta";
    questionInput.style.margin = "10px 0";
    questionInput.style.display = "block";
    questionInput.className = "poll-question";
    editor.appendChild(questionInput);

    // Container para opções
    const optionsContainer = document.createElement("div");
    optionsContainer.className = "poll-options-container";
    optionsContainer.style.margin = "10px 0";
    editor.appendChild(optionsContainer);

    // Botão para adicionar opções
    const addOptionButton = document.createElement("button");
    addOptionButton.textContent = "Adicionar Opção";
    addOptionButton.style.margin = "10px 5px";
    addOptionButton.style.padding = "5px 10px";
    addOptionButton.style.cursor = "pointer";
    addOptionButton.addEventListener("click", () => {
        const optionInput = document.createElement("input");
        optionInput.type = "text";
        optionInput.placeholder = "Digite uma opção";
        optionInput.style.margin = "5px 0";
        optionInput.style.display = "block";
        optionInput.className = "poll-option";
        optionsContainer.appendChild(optionInput);
    });
    editor.appendChild(addOptionButton);

    // Botão de submissão
    const submitButton = document.createElement("button");
    submitButton.textContent = "Publicar Enquete";
    submitButton.style.margin = "10px 0";
    submitButton.style.padding = "5px 15px";
    submitButton.style.backgroundColor = "#007BFF";
    submitButton.style.color = "#FFF";
    submitButton.style.border = "none";
    submitButton.style.borderRadius = "5px";
    submitButton.style.cursor = "pointer";
    submitButton.className = "poll-submit-btn";

    submitButton.addEventListener("click", () => {
        // Coletar dados da enquete
        const question = editor.querySelector(".poll-question").value;
        const options = Array.from(
            editor.querySelectorAll(".poll-option")
        ).map((input) => input.value);

        if (!question.trim()) {
            alert("A pergunta não pode estar vazia!");
            return;
        }

        if (options.length < 2 || options.some((opt) => !opt.trim())) {
            alert("A enquete precisa de pelo menos duas opções válidas!");
            return;
        }

        const pollData = { question, options };

        // Enviar a enquete via AJAX
        sendPollData(pollData);
    });

    editor.appendChild(submitButton);

    editor.focus();
};

// Função para enviar enquete via AJAX
const sendPollData = (pollData) => {
    console.log("Enviando enquete:", pollData); // Para depuração
    fetch("/submit-poll", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(pollData),
    })
        .then((response) => response.json())
        .then((result) => {
            alert("Enquete publicada com sucesso!");
            console.log("Resposta do servidor:", result);
        })
        .catch((error) => {
            console.error("Erro ao publicar a enquete:", error);
        });
};

// Expondo a função globalmente
window.createPoll = createPoll;




//FORMULÁRIO

function formContent(el){
	var tp = el.value;
	var k = 1;
	var ds = el.parentNode.getElementsByTagName('div')[1];
	if(tp == 0){
		ds.innerHTML = '';
	}else if(tp == 1){
		ds.innerHTML =  '<div class="w-rounded-5 large-12 medium-12 small-12 cm-pad-10 font-weight-600 fs-a uppercase" style="background: #F7F8D1;">'+
							'<i class="fas fa-info-circle cm-mg-5-r"></i>Selecione a resposta correta, caso exista'+
						'</div>'+
						'<div class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative">'+
							'<input style="padding: 0; margin: 0;" class="large-1 medium-1 small-1 position-absolute abs-l-0 abs-t-20" id="" name="card_type__option_selected" type="radio" value="1"></input>'+
							'<input name="card_type__option[]" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="text" class="required cm-pad-10-b float-right border-none background-transparent large-11 medium-11 small-11" placeholder="Opção '+ k +'"></input>'+
							'<div class="clear"></div>'+
						'</div>'+
						'<div id="addOption" class="cm-mg-10-t large-12 medium-12 small-12 text-right cm-mg-10-b uppercase fs-b font-weight-600 w-color-bl-to-or pointer"><a onclick="addOption(this, ' + tp + ',  ' + k + ')">Adicionar opção</a></div>';
	}else if(tp == 2){
		ds.innerHTML =  '<div class="w-rounded-5 large-12 medium-12 small-12 cm-pad-10 font-weight-600 fs-a uppercase" style="background: #F7F8D1;">'+
							'<i class="fas fa-info-circle cm-mg-5-r"></i>Selecione as respostas corretas, caso existam'+
						'</div>'+
						'<div class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative">'+
							'<input style="padding: 0; margin: 0;" class="large-1 medium-1 small-1 position-absolute abs-l-0 abs-t-20" id="" name="card_type__option_selected[]" type="checkbox" value="1"></input>'+
							'<input name="card_type__option[]" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="text" class="required cm-pad-10-b float-right border-none background-transparent large-11 medium-11 small-11" placeholder="Caixa '+ k +'"></input>'+
							'<div class="clear"></div>'+
						'</div>'+
						'<div id="addOption" class="cm-mg-10-t large-12 medium-12 small-12 text-right cm-mg-10-b uppercase fs-b font-weight-600 w-color-bl-to-or pointer"><a onclick="addOption(this, ' + tp + ',  ' + k + ')">Adicionar opção</a></div>';		
	}else if(tp == 3){
		ds.innerHTML =  '<div class="w-rounded-5 large-12 medium-12 small-12 cm-pad-10 font-weight-600 fs-a uppercase" style="background: #F7F8D1;">'+
							'<i class="fas fa-info-circle cm-mg-5-r"></i>Informe a data correta, caso exista'+
						'</div>'+
						'<div class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative">'+														
							'<input name="card_type__option" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="date" class="cm-pad-5-b float-right border-none background-transparent large-12 medium-12 small-12" placeholder="dd/mm/aaaa"></input>'+
							'<div class="clear"></div>'+
						'</div>';
	}
}		
function addOption(el, tp, k){
	var ds = el.parentNode.parentNode;			
	divs = ds.getElementsByTagName('div');			
	for(i = 0; i < divs.length; i++){
		if(divs[i].id == k){
			divs[i].getElementsByTagName('a')[0].remove();
		}
	}			
	k = k + 1;
	if(tp == 1){
		var input = '<div id="' + k + '" class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative input">'+
						'<input style="padding: 0; margin: 0;" class="large-1 medium-1 small-1 position-absolute abs-l-0 abs-t-20" id="" name="card_type__option_selected" type="radio" value="' + k + '"></input>'+
						'<a class="position-absolute abs-r-10 abs-t-10 w-color-bl-to-or pointer" style="z-index: 99;" onclick="remOption(this, ' + tp + ', ' + k + ')" title="Descartar esta opção"><i class="fas fa-times-circle"></i></a>'+
						'<input name="card_type_option[]" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="text" class="required cm-pad-10-b float-right border-none background-transparent large-11 medium-11 small-11" placeholder="Opção '+ k +'"></input>'+
						'<div class="clear"></div>'+
					'</div>'+
					'<div id="addOption" class="cm-mg-10-t large-12 medium-12 small-12 text-right cm-mg-10-b uppercase fs-b font-weight-600 w-color-bl-to-or pointer"><a onclick="addOption(this, ' + tp + ',  ' + k + ')">Adicionar opção</a></div>';
		ds.removeChild(ds.lastElementChild);
		ds.innerHTML += input;
	}else if(tp == 2){
		var input = '<div id="' + k + '" class="border-like-input cm-mg-10-t large-12 medium-12 small-12 background-white w-rounded-5 cm-pad-10 cm-pad-15-t position-relative input">'+
						'<input style="padding: 0; margin: 0;" class="large-1 medium-1 small-1 position-absolute abs-l-0 abs-t-20" id="" name="card_type__option_selected[]" type="checkbox" value="' + k + '"></input>'+
						'<a class="position-absolute abs-r-10 abs-t-10 w-color-bl-to-or pointer" style="z-index: 99;" onclick="remOption(this, ' + tp + ', ' + k + ')" title="Descartar esta opção"><i class="fas fa-times-circle"></i></a>'+
						'<input name="card_type_option[]" style="border-bottom: 1px solid rgba(0,0,0,0.5)" type="text" class="required cm-pad-10-b float-right border-none background-transparent large-11 medium-11 small-11" placeholder="Caixa '+ k +'"></input>'+
						'<div class="clear"></div>'+
					'</div>'+
					'<div id="addOption" class="cm-mg-10-t large-12 medium-12 small-12 text-right cm-mg-10-b uppercase fs-b font-weight-600 w-color-bl-to-or pointer"><a onclick="addOption(this, ' + tp + ',  ' + k + ')">Adicionar opção</a></div>';
		ds.removeChild(ds.lastElementChild);
		ds.innerHTML += input;
	}
	
}		
function remOption(el, tp, k){
	var ds = el.parentNode.parentNode;
	k = k - 1;
	divs = ds.getElementsByTagName('div');			
	for(i = 0; i < divs.length; i++){
		if(divs[i].id == k){
			var remove = '<a class="position-absolute abs-r-10 abs-t-10 w-color-bl-to-or pointer" style="z-index: 99;" onclick="remOption(this, ' + tp + ', ' + k + ')" title="Descartar esta opção"><i class="fas fa-times-circle"></i></a>';
			divs[i].innerHTML += remove;
		}
	}
	ds.removeChild(ds.lastElementChild);
	var input = '<div id="addOption" class="cm-mg-10-t large-12 medium-12 small-12 text-right cm-mg-10-b uppercase fs-b font-weight-600 w-color-bl-to-or pointer"><a onclick="addOption(this, ' + tp + ',  ' + k + ')">Adicionar opção</a></div>';			
	el.parentNode.remove();
	ds.innerHTML += input;
}		

//GIFS

function httpGetAsync(theUrl, callback){
	var xmlHttp = new XMLHttpRequest();
	xmlHttp.onreadystatechange = function(){
		if (xmlHttp.readyState == 4 && xmlHttp.status == 200){
			callback(xmlHttp.responseText);
		}
	}   
	xmlHttp.open("GET", theUrl, true);    
	xmlHttp.send(null);
	return;
}
// callback for the top 8 GIFs of search
function tenorCallback_search(responsetext){
	var response_objects = JSON.parse(responsetext);
	top_10_gifs = response_objects["results"];	
	var elemento_pai = document.getElementById('resultGIF');
	var lenght = 0;
	for(key in top_10_gifs){
		if(top_10_gifs.hasOwnProperty(key)){
			++lenght;			
			var imgSrc = top_10_gifs[key]["media"][0]["nanogif"]["url"];			
			var hyperImg = document.createElement('a');									
			var imgObj = document.createElement('img');	
			imgObj.src = imgSrc;
			imgObj.style = 'margin: 10px; border-radius: 5px; float: left; cursor: pointer';						
			imgObj.onclick = function(){insertAtCursor(document.getElementById(elemento_pai.parentNode.parentNode.childNodes[1].id), '<img class="w-rounded-5" src="' + this.src + '"></img>')};
			hyperImg.appendChild(imgObj);
			elemento_pai.appendChild(hyperImg);
		}
	}
	return;
}
function grab_data(tag){
	// set the apikey and limit
	var apikey = "LIVDSRZULELA";
	var lmt = 8;
	// test search term
	var search_term = "excited";
	// using default locale of en_US
	if(tag === ''){
		document.getElementById('resultGIF').innerHTML = '';
		var search_url = 'https://api.tenor.com/v1/search?tag=trabalho&locale=pt_BR&key=LIVDSRZULELA';
	}else{
		document.getElementById('resultGIF').innerHTML = '';
		var search_url = 'https://api.tenor.com/v1/search?tag=' + tag + '&locale=pt_BR&key=LIVDSRZULELA';
	}				
	httpGetAsync(search_url, tenorCallback_search);
	return;
}		

//TABLE TO EXCEL

var tableToExcel = (function(){
	var uri = 'data:application/vnd.ms-excel;base64,'
	, template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>'
	, base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))) }
	, format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }) }
	return function(table, name) {
		if (!table.nodeType) table = document.getElementById(table)
		var ctx = {worksheet: name || 'Worksheet', table: table.innerHTML}
		window.location.href = uri + base64(format(template, ctx))
	}
})();

//SOCIAL MEDIA

function fbshareCurrentPage(){
	window.open("https://www.facebook.com/sharer/sharer.php?u="+escape(window.location.href)+"&t="+document.title, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=300,width=600');return false;
}
function wtshareCurrentPage(){
	window.open("whatsapp://send?text="+escape(window.location.href)+"");return false;
}
function trshareCurrentPage(){
	window.open("https://twitter.com/share?url="+escape(window.location.href)+"");return false;
}
function lkshareCurrentPage(){
	window.open('https://www.linkedin.com/cws/share?url=' +escape(window.location.href)+ '?name=' +'Workz', 'newwindow', 'width=680, height=450');
}
function pishareCurrentPage(){
	window.open('https://www.pinterest.com/pin/create/button/?url=' +escape(window.location.href), 'newwindow', 'width=680, height=450');
}

// MISC

//SLEEP

//TIMELINE IMAGES DRAG AND DROP


	// Select relevant DOM elements using querySelector
	let dropArea = document.querySelector("#drop-area");
	let previewContainer = document.querySelector(".preview-container");
	let error = document.querySelector(".error");

	// Define an array of allowed image types
	let allowedImageTypes = ["image/jpeg", "image/gif", "image/png"];

function enableDropArea(){

	// Select relevant DOM elements using querySelector
	let dropArea = document.querySelector("#drop-area");
	let previewContainer = document.querySelector(".preview-container");
	let error = document.querySelector(".error");

	// Define an array of allowed image types
	let allowedImageTypes = ["image/jpeg", "image/gif", "image/png"];

	
	dropArea.addEventListener('dragenter', ev => {
	   // Highlighting the drop area's border.
	   dropArea.classList.add("highlight");
		// Preventing the browser's default action.
		ev.preventDefault();
	});

	// Add a dragover event listener to prevent the browser
	// open the file.
	dropArea.addEventListener('dragover', ev => {
	   // Highlighting the drop area's border.
	   dropArea.classList.add("highlight");
		// Preventing the browser's default action.
		ev.preventDefault();
	});


	dropArea.addEventListener('dragleave', ev => {
	   // Highlighting the drop area's border.
	   dropArea.classList.remove("highlight");
		// Preventing the browser's default action.
		ev.preventDefault();
	});

	// Add a drop event listener to handle dropped files
	dropArea.addEventListener('drop', ev => {
		// Remove the border highlight when dropping the files
		dropArea.classList.remove("highlight");

		ev.preventDefault();
		// Using the dataTransfer object to access the files being dragged.
		if(ev.dataTransfer.files){
			// If there are being files dragged and dropped, we store
			// this files in the 'transferredFiles' variable.
			let transferredFiles = ev.dataTransfer.files;
		   
			// Call the previewFiles function with the dropped files
			// to preview them
			previewFiles(transferredFiles);
		}
	});
}


/*
// Function to preview files dropped onto the drop area
// Also this function runs we whe selecting files using the
// input file form element.
function previewFiles(files){
	
	// Select relevant DOM elements using querySelector
	let dropArea = document.querySelector("#drop-area");
	let previewContainer = document.querySelector(".preview-container");
	let error = document.querySelector(".error");

	// Define an array of allowed image types
	let allowedImageTypes = ["image/jpeg", "image/gif", "image/png"];
	
    // Hide any previous error messages
    error.style.display = "none";

    // Check each dropped file against the allowed image types
    for(let file of files){
        if(!allowedImageTypes.includes(file.type)){
            // Display an error message for disallowed file types
            error.style.display = "block";
            error.innerHTML = "Only .jpg, .png, .gif files are allowed";

            // Hide the error message after 5 seconds
            setTimeout( () => {
                error.style.display = "none";
            }, 5000);

            // Return false to stop processing and prevent image preview
            return false;
        }
    }

    // If all files are allowed, proceed to preview each image
    for(let file of files){
        // Create a FileReader to read the file content
        let reader = new FileReader();
        // Read the file as a data URL (base64 encoded)
        reader.readAsDataURL(file);
        
        // When the browser finishes reading the file, the
        // onload event listener will trigger a function.
        reader.onload = function(){
            // Create an image element and set its source to the result of FileReader
            let image = new Image();
            image.src = this.result;
            
            // Create a container for the image
            let imageContainer = document.createElement("div");
            imageContainer.setAttribute('class', 'image-container');
            imageContainer.appendChild(image);
            previewContainer.appendChild(imageContainer);

            // Create a paragraph element to display the file name
            let imageName = document.createElement("p");
            imageName.setAttribute('class', 'info');
            imageName.innerHTML = file.name;
            imageContainer.appendChild(imageName);

            // Create a remove button with an onclick event to remove the image
            let removeButton = document.createElement("button");
            removeButton.setAttribute('class', 'remove-button');
            removeButton.setAttribute('onclick', 'removeImage(this.parentElement)');
            removeButton.innerText = "Remove image";
            imageContainer.appendChild(removeButton);
        }

    }
}
*/

async function previewFiles(files){
	
	// Select relevant DOM elements using querySelector
	let dropArea = document.querySelector("#drop-area");
	let previewContainer = document.querySelector(".preview-container");
	let error = document.querySelector(".error");

	// Define an array of allowed image types
	let allowedImageTypes = ["image/jpeg", "image/gif", "image/png"];
	
    // Hide any previous error messages
    error.style.display = "none";

    // Check each dropped file against the allowed image types
    for(let file of files){
        if(!allowedImageTypes.includes(file.type)){
            // Display an error message for disallowed file types
            error.style.display = "block";
            error.innerHTML = "Only .jpg, .png, .gif files are allowed";

            // Hide the error message after 5 seconds
            setTimeout( () => {
                error.style.display = "none";
            }, 5000);

            // Return false to stop processing and prevent image preview
            return false;
        }
    }

	

    // If all files are allowed, proceed to preview each image
    for(let file of files){
        try {
            // Redimensionar a imagem
            let resizedImage = await resizeImage(file, 500, 500, 0.25);

            // Create an image element and set its source to the resized image
            let image = new Image();
            image.src = resizedImage;         
			
			image.setAttribute("style", "object-fit: cover; object-position: center; height: 100%; flex: auto 0 0; scroll-snap-align: start;");
			const uniqueId = generateUniqueRandomId();
			
			image.setAttribute("id", uniqueId);
            previewContainer.appendChild(image);
						
        } catch (error) {
            console.error("Error resizing image:", error);
            // Handle any errors that occur during image resizing
        }							
    }
	var n_img = previewContainer.querySelectorAll('img').length;
	var n_prevnext = previewContainer.querySelectorAll('.prevnext').length;	
	
	if(n_img > 1 && n_prevnext == 0){
		//Cria os botões				
		var btnEsquerdo = '<span onclick="this.parentElement.scrollBy({left: -scrollWidth, behavior: `smooth`});" class="position-absolute abs-l-10 abs-b-10 fa-stack" style="vertical-align: middle;"><i class="fas fa-chevron-left"></i><i class="fas fa-clock fa-stack-1x fa-inverse dark"></i></span>';
		var btnDireito = '<span onclick="this.parentElement.scrollBy({left: scrollWidth, behavior: `smooth` });" class="position-absolute abs-r-10 abs-b-10 fa-stack" style="vertical-align: middle;"><i class="fas fa-circle fa-stack-2x light-gray"></i><i class="fas fa-chevron-right"></i></span>';
		
		previewContainer.innerHTML += btnEsquerdo;
		previewContainer.innerHTML += btnDireito;
		
	}else if(n_prevnext > 0 && n_img <= 1){
		//Remove os botões
		let botoes = previewContainer.querySelectorAll('.prevnext');
		botoes.forEach(function(botao){
			botao.remove();
		});
	}
	
	let stacks = previewContainer.querySelectorAll('.fa-stack');
	stacks.forEach(function(stack){
		stack.remove();
	});
	
	if(n_img > 1){
		
		stack = '<span class="fa-stack position-absolute abs-t-50 abs-r-10" style="vertical-align: middle;"><i class="fas fa-circle fa-stack-2x light-gray"></i><i class="fas fa-images fa-stack-1x fa-inverse dark"></i></span>';
		previewContainer.innerHTML += stack;
	}
	
}


function resizeImage(file, maxWidth, maxHeight, quality) {
    return new Promise((resolve, reject) => {
        // Criar um novo objeto FileReader
        let reader = new FileReader();
        
        // Lidar com o evento de carregamento do FileReader
        reader.onload = function(event) {
            // Criar uma nova imagem
            let img = new Image();
            img.src = event.target.result;
            
            // Lidar com o carregamento da imagem
            img.onload = function() {
                // Calcular novas dimensões mantendo a proporção original
                let width = img.width;
                let height = img.height;
                let newWidth = width;
                let newHeight = height;

                if (width > height) {
                    if (width > maxWidth) {
                        newHeight *= maxWidth / width;
                        newWidth = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        newWidth *= maxHeight / height;
                        newHeight = maxHeight;
                    }
                }

                // Criar um novo elemento canvas
                let canvas = document.createElement('canvas');
                canvas.width = newWidth;
                canvas.height = newHeight;

                // Desenhar a imagem no canvas com as novas dimensões
                let ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, newWidth, newHeight);

                // Obter a imagem em base64 com a qualidade especificada
                let resizedImage = canvas.toDataURL('image/jpeg', quality);

                // Resolver a promessa com a imagem redimensionada
                resolve(resizedImage);
            };
        };

        // Ler o conteúdo do arquivo como uma URL de dados (base64)
        reader.readAsDataURL(file);
    });
}

// Function to remove an image element from the DOM
// when click on the remove image button
function removeImage(element){
    element.remove();
}


function generateRandomId() {
    // Gera um ID aleatório de 8 caracteres
    const idLength = 8;
    const characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let randomId = '';
    for (let i = 0; i < idLength; i++) {
        randomId += characters.charAt(Math.floor(Math.random() * characters.length));
    }
    return randomId;
}

function isIdUnique(id) {
    // Verifica se o ID gerado é único no documento
    return !document.getElementById(id);
}

function generateUniqueRandomId() {
    let randomId;
    do {
        randomId = generateRandomId();
    } while (!isIdUnique(randomId));
    return randomId;
}