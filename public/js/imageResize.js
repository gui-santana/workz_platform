function imageResize(){
	setTimeout(()=>{
		var toolsImg = document.getElementById('toolsImg');
		var toolsTxt = document.getElementById('toolsTxt');
		var toolsImg_w = document.getElementById('toolsImg_w');
		
		var toolsImg_g = document.getElementById('toolsImg_g');
		var toolsImg_t = document.getElementById('toolsImg_t');
		var toolsImg_r = document.getElementById('toolsImg_r');

		var toolsImg_b = document.getElementById('toolsImg_b');
		var toolsImg_c = document.getElementById('toolsImg_c');			
		
		const textBoxPressed = (e) => {			
			var selected = document.getElementsByClassName('selected');
			//RESET
			toolsImg_t.value = 1;
			toolsImg_g.value = 0;
			toolsImg_r.value = 0;
			toolsImg_b.value = 100;
			toolsImg_c.value = 0;
			
			for(i = 0; i < selected.length; i++){
				selected[i].classList.remove('selected');
			}

			if(e.target.nodeName == 'IMG'){
				
				var range = document.createRange();
				range.selectNodeContents(e.target);
				var sel = window.getSelection();
				sel.removeAllRanges();
				sel.addRange(range);
				
				e.target.classList.add('selected');
				toolsTxt.classList.add('display-none');
				toolsImg.classList.remove('display-none');
				var width = e.target.width;
				toolsImg_w.value = width;
				if(e.target.getAttribute("style")){
					var styles = e.target.getAttribute("style").split(';');
					for(w = 0; w < styles.length; w++){						
						var style = styles[w].split(':')[0];
						var value = styles[w].split(':')[1];						
						if(style.indexOf("filter") != -1){							
							if(value.indexOf("grayscale") != -1){			
								toolsImg_g.value = value.replace(/\D/g, '');
							}else if(value.indexOf("brightness") != -1){			
								toolsImg_b.value = value.replace(/\D/g, '');
							}else if(value.indexOf("contrast") != -1){
								toolsImg_c.value = value.replace(/\D/g, '');
							}
						}else if(style.indexOf("opacity")!= -1){
							toolsImg_t.value = value.replace('opacity: ', '').trim();
						}else if(style.indexOf("transform")!= -1){
							if(value.indexOf("rotate") != -1){
								toolsImg_r.value = value.replace(/\D/g, '');
							}
						}
					}
				}
			}else{				
				if(toolsTxt.classList.contains('display-none')){
					toolsTxt.classList.remove('display-none');
					toolsImg.classList.add('display-none');
				}
			}
		}
		oDoc.addEventListener('click', textBoxPressed);
		
		
		document.addEventListener('keydown', function(event){
			var selected = document.getElementsByClassName('selected');
			if(selected.length > 0){
				var keyPressed = event.keyCode || event.which;
				if (keyPressed === 13){
					event.preventDefault();					
					for(i = 0; i < selected.length; i++){						
						imageEffect(toolsImg_w);
					}
					return false;				
				}
			}
		});
	},1500)
}
function imageEffect(el){
	var selected = document.getElementsByClassName('selected');
	if(el.id == 'toolsImg_w'){
		for(i = 0; i < selected.length; i++){
			selected[i].width = Number(el.value) + 2;			
		}
	}else if(el.id == 'toolsImg_h'){
		for(i = 0; i < selected.length; i++){
			selected[i].height = el.value;
		}
	}else if(el.id == 'toolsImg_g'){
		for(i = 0; i < selected.length; i++){
			selected[i].style.filter = 'grayscale('+ el.value +'%)';
		}
	}else if(el.id == 'toolsImg_t'){
		for(i = 0; i < selected.length; i++){
			selected[i].style.opacity = el.value;
		}		
	}else if(el.id == 'toolsImg_r'){
		for(i = 0; i < selected.length; i++){
			selected[i].style.transform = 'rotate('+ el.value +'deg)';
		}
	}else if(el.id == 'toolsImg_b'){
		for(i = 0; i < selected.length; i++){
			selected[i].style.filter = 'brightness('+ el.value +'%)';
		}
	}else if(el.id == 'toolsImg_c'){
		for(i = 0; i < selected.length; i++){
			selected[i].style.filter = 'contrast('+ el.value +'%)';
		}
	}
}
function replaceTag(el, tagName){
	var newEl = document.createElement(tagName);
    newEl.id = el.id;
    newEl.classList = el.classList;
    newEl.innerHTML = el.innerHTML;
    el.parentNode.replaceChild(newEl, el);
}
