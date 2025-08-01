<?
if($_GET['qt'] == 0){
	$fawesm = 'fas fa-cog';
	$pgname = 'Ajustes';	
	$source = $_SERVER['DOCUMENT_ROOT'].'/partes/resources/modal_content/config_home.php';
}elseif($_GET['qt'] == 1){
	$fawesm = 'far fa-comment';	
	$pgname = 'Comentários';
	$source = $_SERVER['DOCUMENT_ROOT'].'/backengine/timeline_comments.php';
}
?>

<div class="large-2 medium-2 small-3 fs-b float-right text-right">			
	<span onclick="modalSize(this)" class="fa-stack w-color-gr-to-gr pointer" title="Maximizar">
		<i class="fas fa-square fa-stack-2x"></i>
		<i class="far fa-window-maximize fa-stack-1x dark"></i>
	</span>
	<label for="tasks-modal">
		<span class="fa-stack w-color-gr-to-gr pointer" onclick="minimize(this); document.getElementById('wz_tasks_modal').innerHTML = '';" title="Fechar">
			<i class="fas fa-square fa-stack-2x"></i>
			<i class="fas fa-times fa-stack-1x dark"></i>
		</span>
	</label>
</div>
<div class="large-10 medium-10 small-9 cm-mg-10-b fs-c font-weight-600 text-ellipsis uppercase" style="display: inline-block; margin-left: -5px;">
	<span class="fa-stack orange" style="vertical-align: middle;">
		<i class="fas fa-circle fa-stack-2x light-gray"></i>
		<i class="<? echo $fawesm; ?> fa-stack-1x fa-inverse dark"></i>					
	</span>	
	<a class="uppercase font-weight-600" style="vertical-align: middle;"><? echo $pgname; ?></a>
</div>
<div class="w-form" style="height: calc(100% - 45px)">
	<div id="tab-container" class="background-gray position-relative height-100 w-rounded-10 overflow-auto" >
		<? include($source); ?>
	</div>	
</div>
<div class="clear"></div>