<div id="tab" class="large-12 medium-12 small-12 overflow-x-hidden position-absolute abs-r-0 abs-b-0 abs-l-0" style="height: calc(100% - 106.15px)"></div>

<!-- GRAPESJS -->
<script src="https://unpkg.com/grapesjs"></script>
<script src="https://unpkg.com/grapesjs-preset-webpage"></script>
<script src="https://unpkg.com/grapesjs-blocks-basic"></script>
<script src="https://unpkg.com/grapesjs-plugin-forms"></script>
<script src="https://unpkg.com/grapesjs-plugin-export"></script>
<script src="https://unpkg.com/grapesjs-tabs"></script>
<script src="https://unpkg.com/grapesjs-custom-code"></script>
<script src="https://unpkg.com/grapesjs-tooltip"></script>
<script src="https://unpkg.com/grapesjs-typed"></script>
<script src="https://unpkg.com/grapesjs-style-bg"></script>
<!-- SUNEDITOR -->
<script src="core/backengine/wa0006/suneditor/js/common.js"></script>
<script src="core/backengine/wa0006/suneditor/dist/suneditor.min.js"></script>
<script src="core/backengine/wa0006/suneditor/dist/pt_br.js"></script>
<!-- CODEMIRROS -->
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.49.0/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.49.0/mode/htmlmixed/htmlmixed.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.49.0/mode/xml/xml.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.49.0/mode/css/css.js"></script>
<!-- KATEX -->
<script src="https://cdn.jsdelivr.net/npm/katex@0.11.1/dist/katex.min.js"></script>
<!-- GLOBALS -->
<script type='text/javascript' src="https://workz.com.br/js/functions.js"></script>
<script>
//Adiciona botões personalizados ao menu lateral
function appOptions() {		
    const optionsHTML = '<div class="w-shadow background-white w-rounded-15">' +
            createOption("core/backengine/wa0006/config.php", "config", 1, "Novo Documento", "fa-newspaper", false, true, false) +
            createOption("core/backengine/wa0006/config.php", "config", 5, "Nova Página da Web", "fa-globe", true, false, true) +			
        '</div>';        
    document.getElementById("appOptions").classList.remove("display-none");
    document.getElementById("appOptions").innerHTML = optionsHTML;
}
window.onload = function(){			
	goTo('core/backengine/wa0006/tab_content.php', 'tab', '0', '');
}
</script>