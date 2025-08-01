<div class="cm-mg-100-t column large-12">
	<?php 
	if(isset($_GET['users'])){
		$search_type = 'p';
		$placeholder = 'Encontrar pessoas';
	}elseif(isset($_GET['teams'])){	
		$search_type = 't';
		$placeholder = 'Encontrar equipes';
	}elseif(isset($_GET['companies'])){
		$search_type = 'c';
		$placeholder = 'Encontrar negócios';
	}	
	?>
	<div class="large-12 cm-mg-30 cm-mg-0-h">					
		<input id="search_input" class="w-rounded-30 w-rounded-5 input-border cm-pad-15 w-shadow large-12 medium-12 small-12" type="text" placeholder="<?php  echo $placeholder; ?>"/>		
	</div>			
	<div id="search_result" class="cm-mg-30-b" style="height: 600px">
	</div>
</div>
<script>
<?php 
if(isset($_GET['u'])){
?>
window.onload = function(){
	goTo('backengine/consulta_comunidade.php', 'search_result', 0 + '|<?php  echo $search_type; ?>,u:<?php  echo $_GET['u']; ?>|', '');
}
<?php 	
}else{	
?>
window.onload = function(){
	console.log('ok');
	goTo('backengine/consulta_comunidade.php', 'search_result', 0 + '|<?php  echo $search_type; ?>', '');
}
<?php 
}
?>

	var uview = 1;		
	$('#search_input').keyup(function(){
		uview = 1;
		goTo('backengine/consulta_comunidade.php', 'search_result', 0 + '|<?php  echo $search_type; ?>', $(this).val());
	});
	
	let isLoading = false; // Variável para evitar múltiplas requisições

	$(window).scroll(function () {
		waitForElm('#dynamic_results_' + uview).then((elm) => {
			const scrollSum = Math.trunc($(window).scrollTop() + $(window).height());
			const scrollGot = $(document).height();

			if (scrollSum >= scrollGot - 300 && !isLoading) {
				isLoading = true; // Bloqueia novas requisições até a atual ser concluída
				goTo('backengine/consulta_comunidade.php', 'dynamic_results_' + uview, uview + '|<?php echo $search_type; ?>', $('#search_input').val())
					.then(() => {
						uview++; // Incrementa após carregar mais itens
						isLoading = false; // Libera para novas requisições
					})
					.catch(() => {
						console.error('Erro ao carregar mais itens.');
						isLoading = false; // Libera para novas tentativas em caso de erro
					});
			}
		});
	});


</script>