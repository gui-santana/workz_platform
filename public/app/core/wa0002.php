<div class="row">
<?php
if(isset($_POST['vr'])){
	
	?>
	<div class="cm-pad-10 position-sticky">
		<a class="pointer w-color-bl-to-or cm-mg-10-r" onclick="goTo('core/backengine/wa0002/wtebd.php', 'wteba', '<? echo $_POST['vr']?>|1', '');">Resumo</a>
		<a class="pointer w-color-bl-to-or cm-mg-10-r" onclick="goTo('core/backengine/wa0002/wteba.php', 'wteba', '&pg=1&sc&v', '<? echo $_POST['vr']?>');">Subcréditos</a>
		<a class="pointer w-color-bl-to-or cm-mg-10-r" onclick="goTo('core/backengine/wa0002/wtebb.php', 'wteba', '&pg=1&sc&PRAP&v&l', '<? echo $_POST['vr']?>');">Mov. Financeira</a>
		<a class="pointer w-color-bl-to-or cm-mg-10-r" onclick="goTo('core/backengine/wa0002/wtebc.php', 'wteba', '&pg=1', '<? echo $_POST['vr']?>');">Registros</a>	
		<div id="wteba" class="w-rounded-10 background-white cm-pad-10 large-12 w-shadow cm-mg-30-b cm-pad-20 cm-mg-10-t">
		<?
		include('backengine/wa0002/wtebd.php');
		?>
		</div>
	</div>
<?	
}else{
	echo 'Não foi possível carregar a aplicação.';
}
?>
</div>