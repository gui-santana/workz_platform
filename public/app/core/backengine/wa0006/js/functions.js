//TEXTBOX EDITOR

var oDoc, sDefTxt;
function initDoc(){
	setTimeout(()=>{
		oDoc = document.getElementById("textBox");
		sDefTxt = oDoc.innerHTML;
		if (document.compForm.switchMode.checked) { setDocMode(true); }
	},1000);  
}
function formatDoc(sCmd, sValue) {
	if(sCmd == 'fontsize'){	
		var unit = 'px';
		var spanString = $('<span/>', {
			'text': document.getSelection()
		}).css('font-size', sValue + unit).prop('outerHTML');
		document.execCommand('insertHTML', false, spanString);			
		oDoc.focus(); 		
	}else{
		document.execCommand(sCmd, false, sValue); oDoc.focus();
		if(sCmd == 'forecolor'){
			document.getElementById('toolsTxtColorSelected').style.color = sValue;
		}
		if(sCmd == 'backcolor'){
			document.getElementById('toolsTxtFillSelected').style.color = sValue;
		}
	} 
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
	var oPrntWin = window.open("","_blank","width=450,height=470,left=400,top=100,menubar=yes,toolbar=no,location=no,scrollbars=yes");
	oPrntWin.document.open();
	oPrntWin.document.write('<!doctype html><html><head><title>Impressão<\/title><link href="RequestReducedStyle.css" rel="Stylesheet" type="text/css" /><\/head><body onload=\"print();\">' + oDoc.innerHTML + '<\/body><\/html>');
	oPrntWin.document.close();
}
function setZoom(zoom,el){      
		transformOrigin = [0,0];
	    el = el || instance.getContainer();
	    var p = ["webkit", "moz", "ms", "o"],
            s = "translateX(" + (zoom/2) + ") scale(" + zoom + ")",
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