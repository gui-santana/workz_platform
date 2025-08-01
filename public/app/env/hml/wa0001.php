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

/* TASK TIMELINE */

/* The actual w-task-tl-timeline (the vertical ruler) */
.w-task-tl-timeline {
	position: relative;
	margin: 0 auto;
}

/* The actual w-task-tl-timeline (the vertical ruler) */
.w-task-tl-timeline::after {
	content: '';
	position: absolute;
	width: 1px;
	background: <?= $colors[0] ?>;
	top: 0;
	bottom: 0;
	left: 50%;

}

/* w-task-tl-container around w-task-tl-content */
.w-task-tl-container {	
	padding: 10px 0;
	position: relative;
	background-color: inherit;
	width: 50%;
}

/* The circles on the w-task-tl-timeline */
.w-task-tl-container::after {
	content: '';
	position: absolute;
	width: 25px;
	height: 25px;
	right: -12.5px;
	background-color: <?= $colors[1] ?>;
	top: 25px;
	border-radius: 50%;
	z-index: 1;
}

/* Place the w-task-tl-container to the w-task-tl-left */
.w-task-tl:nth-child(even){
	left: 0;
	padding-right: 40px;
}

/* Place the w-task-tl-container to the w-task-tl-right */
.w-task-tl:nth-child(odd){
	left: 50%;
	padding-left: 40px;
}

/* Add arrows to the w-task-tl w-task-tl-container (pointing w-task-tl-right) */
.w-task-tl:nth-child(even)::before {
	content: " ";
	height: 0;
	position: absolute;
	top: 25px;
	width: 0;
	z-index: 1;
	right: 30px;
	border: medium solid white;
	border-width: 12.5px 0 12.5px 12.5px;
	border-color: transparent transparent transparent white;
}

/* Add arrows to the w-task-tl-right w-task-tl-container (pointing w-task-tl) */
.w-task-tl:nth-child(odd)::before {
	content: " ";
	height: 0;
	position: absolute;
	top: 25px;
	width: 0;
	z-index: 1;
	left: 30px;
	border: medium solid white;
	border-width: 12.5px 12.5px 12.5px 0;
	border-color: transparent white transparent transparent;
}

/* Fix the circle for w-task-tl-containers on the w-task-tl-right side */
.w-task-tl:nth-child(odd)::after {
	left: -12.5px;
}

/* The actual w-task-tl-content */
.w-task-tl-content {
	position: relative;
	border-radius: 6px;
}

.w-task-tl-content img{			
	border: .5px solid rgba(0,0,0,0.1);
	border-radius: 5px;
}

.w-task-tl-viewer img{
	cursor: pointer;
	transition: filter .25s ease-in-out;
}

.w-task-tl-viewer img:hover{
	filter: brightness(90%);
}

/* Media queries - Responsive w-task-tl-timeline on screens less than 600px wide */
@media screen and (max-width: 600px) {
	/* Place the timelime to the w-task-tl */
	.w-task-tl-timeline::after {
		left: 12.5px;
	}

	/* Full-width w-task-tl-containers */
	.w-task-tl-container {
		width: 100%;
		/*left: 70px;
		right: 25px;
		*/
	}
  
	/* Make sure that all arrows are pointing w-task-tlwards */
	.w-task-tl-container::before {
		left: 60px;
		border: medium solid white;
		border-width: 10px 10px 10px 0;
		border-color: transparent white transparent transparent;
	}
	
	.w-task-tl:nth-child(even)::before, .w-task-tl:nth-child(odd)::before {
		border-width: 12.5px 12.5px 12.5px 0;
		border-color: transparent white transparent transparent;
		left: 35px;
	}

	/* Make sure all circles are at the same spot */
	.w-task-tl:nth-child(even)::after, .w-task-tl:nth-child(odd)::after {
		left: 0px;
	}
  
	/* Make all w-task-tl-right w-task-tl-containers behave like the w-task-tl ones */
	.w-task-tl:nth-child(odd){
		left: 0%;
		padding-left: 47px;
	}
	.w-task-tl:nth-child(even){
		padding-right: 0;
		padding-left: 47px;
	}
}
<?php
foreach($colors as $key => $color){
?>
.bkg-<?= $key ?>{
	background-color: <?= $color ?>;
}
.color-<?= $key ?>{
	color: <?= $color ?>;
}
<?php
}
?>
.checkbox-wrapper-15 .cbx {
	-webkit-user-select: none;
	user-select: none;
	-webkit-tap-highlight-color: transparent;
	cursor: pointer;
}
.checkbox-wrapper-15 .cbx span {
	display: inline-block;
	vertical-align: middle;
	transform: translate3d(0, 0, 0);
}
.checkbox-wrapper-15 .cbx span:first-child {
	position: relative;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	transform: scale(1);
	vertical-align: middle;
	border: 1px solid <?= $colors[0] ?>;
	transition: all 0.2s ease;
}
.checkbox-wrapper-15 .cbx span:first-child svg {
	position: absolute;
	z-index: 1;
	top: 8px;
	left: 6px;
	fill: none;
	stroke: white;
	stroke-width: 2;
	stroke-linecap: round;
	stroke-linejoin: round;
	stroke-dasharray: 16px;
	stroke-dashoffset: 16px;
	transition: all 0.3s ease;
	transition-delay: 0.1s;
	transform: translate3d(0, 0, 0);
}
.checkbox-wrapper-15 .cbx span:first-child:before {
	content: "";
	width: 100%;
	height: 100%;
	background: <?= $colors[3] ?>;
	display: block;
	transform: scale(0);
	opacity: 1;
	border-radius: 50%;
	transition-delay: 0.2s;
}

.checkbox-wrapper-15 .cbx div:after {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	height: 100%;
	width: 100%;
	background: <?= $colors[3] ?>80;
	transform-origin: 0 0;
	transform: scaleX(0);
}

.checkbox-wrapper-15 .inp-cbx:checked + .cbx div {
	color: <?= $colors[2] ?>80;
	transition: all 0.5s ease;
}
.checkbox-wrapper-15 .inp-cbx:checked + .cbx div:after {
	transform: scaleX(1);
	transition: all 0.5s ease;
}


.checkbox-wrapper-15 .cbx:hover span:first-child {
	border-color: <?= $colors[1] ?>;
}

.checkbox-wrapper-15 .inp-cbx:checked + .cbx span:first-child {
	border-color: <?= $colors[0] ?>;
	background: <?= $colors[0] ?>;
	animation: check-15 0.6s ease;
}
.checkbox-wrapper-15 .inp-cbx:checked + .cbx span:first-child svg {
	stroke-dashoffset: 0;
}
.checkbox-wrapper-15 .inp-cbx:checked + .cbx span:first-child:before {
	transform: scale(2.2);
	opacity: 0;
	transition: all 0.6s ease;
}

@keyframes check-15 {
	50% {
		transform: scale(1.2);
	}
}
.zoom-overlay {
	position: fixed;
	top: 0;
	left: 0;
	width: 100vw;
	height: 100vh;
	background: <?= $colors[0] ?>E6; /* Ex: #000E6 para preto com 90% opacidade */
	display: flex;
	justify-content: center;
	align-items: center;
	z-index: 9999;
	overflow: hidden;
	touch-action: none;
	cursor: grab;
	opacity: 0;
}
.zoom-overlay.active {
	opacity: 1;
}
.zoom-img {
	max-width: none;
	max-height: none;
	will-change: transform;
	transform-origin: center center;  
	user-select: none;
	pointer-events: auto;
	transform: scale(0.95); /* Leve zoom inicial */
}
.zoom-img.active {
	transform: scale(1); /* Cresce levemente ao ativar */
}
</style>							
<div id="main-content" style="height: calc(100% - 217.72px)" class="content large-12 medium-12 small-12 overflow-auto cm-pad-20-b cm-pad-20 view ease-all-2s clear"></div>
<div style="" class="abs-b-0 position-fixed cm-pad-20-h cm-pad-30-b large-12 medium-12 small-12 z-index-2">
	<div class="cm-pad-5 large-10 medium-12 small-12 w-modal-shadow text-center fs-f background-white display-center-general-container centered overflow-x-auto w-rounded-20-35">
		<div class="centered">
			<div class="float-left text-center cm-pad-10 cm-pad-15-h line-height-a w-color-gr-to-gr pointer" onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', ''); resetCounter();">
				<i class="fas fa-tasks fs-g pointer cm-pad-5-t"></i><br/>
				<a class="fs-a">Tarefas</a>
			</div>							
			<div class="float-left text-center cm-pad-10 cm-pad-15-h line-height-a w-color-gr-to-gr pointer" onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', 'folders'); resetCounter();">
				<div class="pointer cm-pad-5-t">
					<svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" class="" viewBox="0 0 576 512"><!--! Font Awesome Free 6.2.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) Copyright 2022 Fonticons, Inc. --><path d="M64 32C64 14.3 49.7 0 32 0S0 14.3 0 32v96V384c0 35.3 28.7 64 64 64H256V384H64V160H256V96H64V32zM288 192c0 17.7 14.3 32 32 32H544c17.7 0 32-14.3 32-32V64c0-17.7-14.3-32-32-32H445.3c-8.5 0-16.6-3.4-22.6-9.4L409.4 9.4c-6-6-14.1-9.4-22.6-9.4H320c-17.7 0-32 14.3-32 32V192zm0 288c0 17.7 14.3 32 32 32H544c17.7 0 32-14.3 32-32V352c0-17.7-14.3-32-32-32H445.3c-8.5 0-16.6-3.4-22.6-9.4l-13.3-13.3c-6-6-14.1-9.4-22.6-9.4H320c-17.7 0-32 14.3-32 32V480z"/></svg>
				</div>
				<a class="fs-a">Pastas</a>
			</div>
			<div class="float-left text-center cm-pad-10 cm-pad-15-h line-height-a w-color-gr-to-gr pointer" onclick="goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', 'regs'); resetCounter();">
				<i class="fas fa-user-graduate fs-g pointer cm-pad-5-t"></i><br/>
				<a class="fs-a">Compet.</a>
			</div>		
		</div>
	</div>
</div>
<script type='text/javascript' src="env/<?= $env ?>/backengine/wa0001/js/taskManager.js"></script>
<script type='text/javascript' src="https://workz.com.br/js/index/wChange.js"></script>
<script type='text/javascript' src="https://workz.com.br/js/index/formValidator2.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
//Adiciona botões de função personalizada ao menu lateral
function appOptions() {
    const optionsHTML = '<div class="w-shadow background-white w-rounded-15">' +
            createOption("env/<?= $env ?>/backengine/wa0001/menu.php", "config", 2, "Nova Pasta", "fa-folder-plus", false, true, false) +
            createOption("env/<?= $env ?>/backengine/wa0001/menu.php", "config", 1, "Nova Tarefa", "fa-plus", true) +
            createOption("env/<?= $env ?>/backengine/wa0001/menu.php", "config", 7, "Conexões WordPress", "fa-plug", true, false, true) +
        '</div>';        
    document.getElementById("appOptions").classList.remove("display-none");
    document.getElementById("appOptions").innerHTML = optionsHTML;
}
function appHeader() {
	const headerHTML = 	'<div id="usxp" class="large-12 medium-12 small-12 float-right display-center-general-container"></div>';
	document.getElementById("appHeader").classList.remove("display-none");
    document.getElementById("appHeader").innerHTML = headerHTML;
}

window.onload = function(){	
	goTo('env/<?= $env ?>/backengine/wa0001/main-content.php', 'main-content', '0', '');
	goTo('env/<?= $env ?>/backengine/wa0001/task_points.php', 'usxp', '0', '');
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
// Função que consolida as ações na mesma estrutura
function handleTaskAction(tskr, action, question, successMsg, failMsg) {
	sAlert(function() {
		switch (action) {
			case 'stop':
				// Concluir tarefa
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '0', tskr.id + '|' + tskr.st + '|stop|');
				resetCounter();										
				break;
				
			case 'pause':
				// Pausar tarefa
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '0', tskr.id + '|' + tskr.st + '|pause|');
				resetCounter();										
				break;

			case 'play':
				// Iniciar ou Re-iniciar tarefa												
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '0', tskr.id + '|' + tskr.st + '|play');
				break;

			case 'eject':
				// Arquivar tarefa
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '', tskr.id + '|' + tskr.st + '|eject');
				break;
				
			case 'delete':
				//Deletar tarefa
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '', tskr.id + '|' + tskr.st + '|delete');
				break;
				
			case 'clone':
				//Clonar tarefa
				goTo('env/<?= $env ?>/backengine/wa0001/process.php', 'ckb_response', '', tskr.id + '|' + tskr.st + '|clone');
				break;

			default:
				console.error("Ação não reconhecida:", action);
				break;
		}
	}, question, successMsg, failMsg);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>


