<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />

		
<style>	
.content {  
	color: black; /* Define a cor do texto */
	-webkit-mask-image: linear-gradient(180deg, rgba(0, 0, 0, 1) 95%, rgba(0, 0, 0, 0) 100%); /* Gradiente de transparência como máscara */
	mask-image: linear-gradient(180deg, rgba(0, 0, 0, 1) 95%, rgba(0, 0, 0, 0) 100%);
}
.bar {
	height: 10px;
	border-radius: 5px;
	background-color: #5A3B6D;
	margin-top: 5px;
	position: relative;
}
.health-bar {
	background-color: #FF5A5A;
	width: 100%; /* 50/50 */
	height: 100%;
	border-radius: 5px;
}

/* SELECT 2 */
.select2-container--default.select2-container--focus .select2-selection--multiple{
	border: none;
	outline: none;
}
.select2-container--default .select2-selection--multiple{
	border: none !important;
	outline: none !important;
	min-height: 45px !important;
	display: flex!important;
	align-items: center;
	padding: 5px 5px 5px 0;
}
.select2-container--default .select2-selection--multiple .select2-selection__rendered{
    align-items: center;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice{
	margin: 2.5px !important;
}
.select2-container--default .select2-selection--multiple .select2-selection__clear{
	margin: 0px !important;
}
.select2-container .select2-search--inline{
	margin: 2.5px !important;
}
.select2-container .select2-search--inline input{
	margin: 0px !important;
	padding-top: 3px !important;
}
</style>							
<div id="main-content" style="height: calc(100% - 227.72px)" class="content large-12 medium-12 small-12 overflow-auto cm-pad-20-b cm-pad-20 view ease-all-2s clear">
</div>
<div class="clear"></div>

<div style="" class="abs-b-0 position-fixed cm-pad-20-h cm-pad-30-b large-12 medium-12 small-12 z-index-2">
	<div class="w-rounded-20 cm-pad-5 large-10 medium-12 small-12 w-modal-shadow text-center fs-f background-white display-center-general-container centered overflow-x-auto">
		<div class="centered large-6 medium-6 small-12 js-flickity" data-flickity-options='{ "groupCells": true, "cellAlign": "center", "imagesLoaded": true, "prevNextButtons": false, "pageDots": false, "fullscreen": false }'>
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
				<i class="fas fa-tasks fs-g pointer cm-pad-5-t" onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();"></i><br/>
				<a class="fs-a">Tarefas</a>
			</div>							
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">					
				<div onclick=" goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', 'folders'); resetCounter();" class="pointer cm-pad-5-t">
					<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" class="" viewBox="0 0 576 512"><!--! Font Awesome Free 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2022 Fonticons, Inc. --><path d="M64 32C64 14.3 49.7 0 32 0S0 14.3 0 32v96V384c0 35.3 28.7 64 64 64H256V384H64V160H256V96H64V32zM288 192c0 17.7 14.3 32 32 32H544c17.7 0 32-14.3 32-32V64c0-17.7-14.3-32-32-32H445.3c-8.5 0-16.6-3.4-22.6-9.4L409.4 9.4c-6-6-14.1-9.4-22.6-9.4H320c-17.7 0-32 14.3-32 32V192zm0 288c0 17.7 14.3 32 32 32H544c17.7 0 32-14.3 32-32V352c0-17.7-14.3-32-32-32H445.3c-8.5 0-16.6-3.4-22.6-9.4l-13.3-13.3c-6-6-14.1-9.4-22.6-9.4H320c-17.7 0-32 14.3-32 32V480z"/></svg>
				</div>
				<a class="fs-a">Pastas</a>
			</div>
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
				<i class="fas fa-gift fs-g pointer cm-pad-5-t" onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();"></i><br/>
				<a class="fs-a">Recompensas</a>
			</div>
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
				<i class="fas fa-star fs-g pointer cm-pad-5-t" onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();"></i><br/>
				<a class="fs-a">Desafios</a>
			</div>
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
				<i class="fas fa-award fs-g pointer cm-pad-5-t" onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();"></i><br/>
				<a class="fs-a">Ranking</a>
			</div>
			<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
				<i class="fas fa-database fs-g pointer cm-pad-5-t" onclick="goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', 'regs'); resetCounter();"></i><br/>
				<a class="fs-a">Registros</a>
			</div>		
		</div>
	</div>
</div>
<script type='text/javascript' src="core/backengine/wa0001/js/taskManager.js"></script>
<script type='text/javascript' src="https://workz.com.br/js/functions.js"></script>
<script type='text/javascript' src="https://workz.com.br/js/index/wChange.js"></script>
<script type='text/javascript' src="https://workz.com.br/js/index/formValidator2.js"></script>
<script>
//Adiciona botões de função personalizada ao menu lateral
//document.addEventListener("DOMContentLoaded", appOptions);
function appOptions() {
    const optionsHTML = '<div class="w-shadow background-white w-rounded-15">' +
            createOption("core/backengine/wa0001/menu.php", "config", 2, "Nova Pasta", "fa-folder-plus", false, true, false) +
            createOption("core/backengine/wa0001/menu.php", "config", 1, "Nova Tarefa", "fa-plus", true) +
			createOption("core/backengine/wa0001/menu.php", "config", 3, "Nova Rotina", "fa-plus", true) +
            createOption("core/backengine/wa0001/menu.php", "config", 4, "Nova Recompensa", "fa-gift", true) +
			createOption("core/backengine/wa0001/menu.php", "config", 5, "Novo Desafio", "fa-star", true) +
            createOption("core/backengine/wa0001/menu.php", "config", 6, "Carregar E-mails", "fa-envelope-open", true, false, true) +
        '</div>';        
    document.getElementById("appOptions").classList.remove("display-none");
    document.getElementById("appOptions").innerHTML = optionsHTML;
}
function appHeader() {
	const headerHTML = 	'<div id="usxp" class="large-6 medium-8 small-12 float-right display-center-general-container"></div>';
	document.getElementById("appHeader").classList.remove("display-none");
    document.getElementById("appHeader").innerHTML = headerHTML;
}

window.onload = function(){	
	goTo('core/backengine/wa0001/main-content.php', 'main-content', '0', '');
	goTo('core/backengine/wa0001/task_points.php', 'usxp', '0', '');
}
function taskMenu(el){	
	var hide = el.parentNode.getElementsByClassName('display-none');
	var vsbl = el.parentNode.getElementsByClassName('display-block');	
	if(hide.length > 0){
		hide[0].classList.add('display-block');
		hide[0].classList.remove('display-none');		
	}else if(vsbl.length > 0){		
		vsbl[0].classList.add('display-none');
		vsbl[0].classList.remove('display-block');
	}	
}
async function waitForElementToLoad(selector) {
    return new Promise((resolve) => {
        const interval = setInterval(() => {
            const element = $(selector);
            if (element.length > 0) {
                clearInterval(interval);
                resolve();
            }
        }, 50); // Verifica a cada 100ms
    });
}
</script>
<!-- MODAL TASK IMAGE - SEND TO TASK APP -->
<div id="modalTaskImage" class="modalTaskImage background-white-transparent-50">
	<span class="modalTaskImage-close">&times;</span>
	<img class="modalTaskImage-content w-rounded-20 w-modal-shadow" id="canva">
	<div id="caption"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
