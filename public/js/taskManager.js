function formatatempo(segs){
	//Formata os segundos como hh:mm:ss
	min = 0;
	hr = 0;												
	while(segs>=60){
		if (segs >=60){
			segs = segs-60;
			min = min+1;
		}
	}
	while(min>=60){
		if (min >=60){
			min = min-60;
			hr = hr+1;
		}
	}
	//Zero à esquerda quando menor que 10
	if(hr < 10){hr = "0"+hr}
	if(min < 10){min = "0"+min}
	if(segs < 10){segs = "0"+segs}
	fin = hr+":"+min+":"+segs
	return fin;
}
function conta(){
	//Exibe o tempo
	segundos++;
	if($('#counter').length > 0){
        document.getElementById("counter").innerHTML = formatatempo(segundos);
		document.getElementById("timeCounter").value = segundos;
    }	
}
function inicia(time, init){
	//time => Tempo total registrado no BD - time (em segundos);
	//init => Tempo do reinicio registrado no BD - init (em segundos)
	var now = new Date().getTime();	
	segundos = parseInt(time) + Math.round(((now / 1000) - init));
	//Executa a função "conta()" a cada 1s;
	interval = setInterval("conta();",1000);
}
function para(){
	clearInterval(interval);
}
function zera(){
	if(typeof interval !== 'undefined'){		
		if($('#counter').length > 0){	
			clearInterval(interval);
			segundos = 0;
			document.getElementById("counter").innerHTML = formatatempo(segundos);
		}
	}	
}
function addTaskStep(){	
	var inputContainer = document.getElementById('inputContainer');
	var inputs = inputContainer.getElementsByTagName('input');	
	var n = inputs.length;	
	var step_input = document.createElement('input');
	step_input.setAttribute('type', 'text');
	step_input.setAttribute('class', 'large-12 medium-12 small-12 w-rounded-5 input-border border-like-input cm-pad-10 cm-mg-10-b required');
	step_input.setAttribute('name', 'tsksp_' + n);
	step_input.setAttribute('id', 'tsk_npt' + n);	
	inputContainer.appendChild(step_input);	
}
function remTaskStep(){
	var inputContainer = document.getElementById('inputContainer');
	var inputs = inputContainer.getElementsByTagName('input');
	var n = inputs.length - 1;
	var step_input = document.getElementById('tsk_npt' + n);
	if(step_input){
		inputContainer.removeChild(step_input);	
	}else{
		
			
		var w = (n / 2) - 1;
		if(w > 0){
			var tsksp = document.getElementsByName('tsksp_' + w)[0];
			var tspst = document.getElementsByName('tspst_' + w)[0];
			inputContainer.removeChild(tsksp);
			inputContainer.removeChild(tspst);
		}
		
	}
}
function rev_action_result(){
	var myNodelist = document.getElementsByName("rev_action[]");												
	for (i = 0; i < myNodelist.length; i++){
		document.getElementById("rev_action_result").value += myNodelist[i].value + ';';
	}  				
}
function updateTaskStep(){
	var inputContainer = document.getElementById('inputContainer');
	var inputs = inputContainer.getElementsByTagName('input');	
	var n = inputs.length;
	var result = '';
	for(i=0; i < n; i++){
		result = result + inputs[i].name + '=' + inputs[i].value + ';';
	}
	return result;
}

function openImage(ob){
	
	//alert(ob.src);
	var modal = document.getElementById("modalTaskImage"); 
	var modalImg = document.getElementById("canva");
	modal.style.display = "block";
	modalImg.src = ob.src;
	var span = document.getElementsByClassName("modalTaskImage-close")[0];
	span.onclick = function(){
		modal.style.display = "none";
	}
	/*

  
 
  // Get the <span> element that closes the modal
  var span = document.getElementsByClassName("close")[0];
  // When the user clicks on <span> (x), close the modal
  span.onclick = function() { 
    modal.style.display = "none";
  }
  */
}