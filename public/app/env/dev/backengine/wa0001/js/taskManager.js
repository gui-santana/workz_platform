// MANIPULAÇÃO DE TEMPO
let interval = null;
let isCounterRunning = false;
let segundos = 0;

function formatTime(secs) {
    const date = new Date(secs * 1000);
    const formattedTime = date.toISOString().substr(11, 8);
    return formattedTime; // Saída: 09:58:51 (UTC)
}

function startCounter(totalTime, initialTime) {
	
	// Se o contador já está rodando, não inicia de novo
	if (isCounterRunning) {
		console.log("O contador já está em execução.");
		return;
	}

	isCounterRunning = true; // marcamos que está rodando
	
    const now = new Date().getTime();
    const difference = Math.round((now / 1000) - initialTime);
    segundos = parseInt(totalTime) + difference;
    
    interval = setInterval(function() {		
        segundos++;
        if ($('#counter').length > 0) {
            $("#counter").html(formatTime(segundos));
            $("#timeCounter").val(segundos);
        }
    }, 1000);
}

function stopCounter() {
	clearInterval(interval);
	interval = null;       // zera a variável de controle
	isCounterRunning = false; // e marca como parado
}

function resetCounter() {
	clearInterval(interval);
	interval = null;
	isCounterRunning = false;
	segundos = 0;
	if ($('#counter').length > 0) {
		$("#counter").html(formatTime(segundos));
	}
}


//EDIÇÃO DE ETAPAS DA TAREFA
function addTaskStep(){	
	var inputContainer = document.getElementById('inputContainer');
	var containers = inputContainer.getElementsByClassName('fieldsContainer');
	var n = containers.length;		
	var step_input = '<div id="taskStep_' + n + '" class="fieldsContainer">'+
						'<input class="large-8 medium-8 small-8 w-rounded-10 input-border border-like-input cm-pad-10 cm-mg-10-b required float-left" id="tsk_npt' + n + '" name="tsksp_' + n + '" type="text" value=""></input>'+
						'<input type="hidden" name="tspst_' + n + '" value="0"></input>'+
						'<div class="large-4 medium-4 small-4 cm-pad-10-l float-right">'+
							'<input onchange="" type="datetime-local" name="tsksp_dt_' + n + '" class="large-12 medium-12 small-12 w-rounded-10 input-border border-like-input cm-pad-10" value=""></input>'+
						'</div>'+
						'<div class="clear"></div>'+
					'</div>';
	inputContainer.innerHTML += step_input;
}
function remTaskStep(){
	var inputContainer = document.getElementById('inputContainer');
	var containers = inputContainer.getElementsByClassName('fieldsContainer');
	var n = containers.length;		
	if(n > 1){
		var elementToRemove = inputContainer.querySelector('#taskStep_' + (n - 1));
		if (elementToRemove) {
			elementToRemove.remove();
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

//VISUALIZAÇÃO DE IMAGENS DA LINHA DO TEMPO
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