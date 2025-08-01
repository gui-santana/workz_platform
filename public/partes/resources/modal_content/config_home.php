<?php
// =========================================================================
// Configurações Iniciais
// =========================================================================
setlocale(LC_ALL,'pt_BR.UTF8');
mb_internal_encoding('UTF8'); 
mb_regex_encoding('UTF8');
setlocale(LC_ALL, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
date_default_timezone_set('America/Fortaleza');
session_start();

// Inclusões e requisições
require('../../../functions/search.php');
require('../../../functions/update.php');

// Sanitização de parâmetros GET
if (!empty($_GET['cl'])) {
    $_GET['cl'] = htmlspecialchars($_GET['cl'], ENT_QUOTES, 'UTF-8');    
}

// =========================================================================
// Funções Auxiliares
// =========================================================================
function pluralize($word) {
    $lastChar = strtolower(substr($word, -1));

    if ($lastChar === 'y') {
        return substr($word, 0, -1) . 'ies';
    } elseif ($lastChar === 's' || $lastChar === 'x') {
        return $word . 'es';
    } else {
        return $word . 's';
    }
}

// =========================================================================
// Tratamento de Dados do POST
// =========================================================================
if(!empty($_POST)){
	$post = json_decode($_POST['vr'],true);	
	if(isset($post['tp'])){
		$_GET['vr'] = $post['tp'];
		$_GET['qt'] = '';	
	}elseif($post['tp_id']){
		//Altera a imagem da página de equipe/negócio
		$tp_id = explode('_', $post['tp_id']);
		$_GET['vr'] = $tp_id[0];
		$_GET['qt'] = $tp_id[1];		
		//insere a imagem
		if($_GET['vr'] === 'profile'){
			if(isset($post['im'])){
				$up = update('hnw', 'hus', "im = '".str_replace('data:image/jpeg;base64,','',base64_decode($post['imTxt']))."'", "id = '".$_SESSION['wz']."'");
			}elseif(isset($post['bk'])){
				$up = update('hnw', 'hus', "bk = '".str_replace('data:image/jpeg;base64,','',base64_decode($post['imTxt']))."'", "id = '".$_SESSION['wz']."'");
			}
		}else{
			if(isset($post['im'])){
				$up = update('cmp', pluralize($tp_id[0]), "im = '".str_replace('data:image/jpeg;base64,','',base64_decode($post['imTxt']))."'", "id = '".$_GET['qt']."'");
			}elseif(isset($post['bk'])){
				$up = update('cmp', pluralize($tp_id[0]), "bk = '".str_replace('data:image/jpeg;base64,','',base64_decode($post['imTxt']))."'", "id = '".$_GET['qt']."'");
			}		
		}		
	}	
}

// =========================================================================
// Verificação de Sessão e Dados do Usuário Logado
// =========================================================================
if(isset($_SESSION['wz'])){
	$loggedUser = search('hnw', 'hus', '', "id = '{$_SESSION['wz']}'")[0];
	if(empty($loggedUser['im'])){ $loggedUser['im'] =  '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'; }
	if(empty($loggedUser['tt'])){ $loggedUser['tt'] = $loggedUser['ml']; }
}

// =========================================================================
// HTML - Início da renderização da página
// =========================================================================
?>
<div class="large-12 medium-12 small-12 height-100 overflow-auto background-gray cm-pad-20-b">
<?php
// =========================================================================
// MENU INICIAL
// =========================================================================
if(!isset($_GET['vr'])){
	?>
	<div class="cm-pad-20-h cm-pad-10-b cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="toggleSidebar();" class="display-center-general-container w-color-bl-to-or pointer">				
				<a>Fechar</a>	
				<i class="fas fa-chevron-right fs-f cm-mg-10-l"></i>
			</div>
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-20 text-right">												
		<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '<?php echo $_SESSION['wz'].'&op=0'; ?>', 'profile')" class="clearfix pointer large-12 medium-12 small-12 text-ellipsis w-color-bl-to-or w-bkg-wh-to-gr w-rounded-15 w-shadow cm-pad-15">
			<div class="clearfix float-left large-11 medium-11 small-11 text-ellipsis" style="height: 70px;">
				<div class="w-circle float-left" style="height: 70px; width: 70px; background: url(data:image/jpeg;base64,<?php echo $loggedUser['im']; ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">					
				</div>
				<div class="float-left text-left display-center-general-container dark" style="height: 70px; width: calc(100% - 70px); justify-content: left;">
					<div class="cm-pad-20-l">
						<p class="dark font-weight-500 text-ellipsis"><?php echo $loggedUser['tt']; ?></p>
						<p class="fs-b cm-mg-5-t text-ellipsis"><?php echo $loggedUser['ml']; ?></p>
						<p class="fs-b cm-mg-5-t gray text-ellipsis" >Perfil Workz!, E-mail, Foto, Endereço...</p>
					</div>
				</div>				
			</div>
			<div class="float-right large-1 medium-1 small-1 text-right display-center-general-container" style="height: 70px; justify-content: right;">										
				<i class="fas fa-chevron-right"></i>
			</div>			
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-b">			
		<div class="w-shadow w-rounded-15">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', 'c1');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-th fa-stack-1x fa-inverse dark"></i>					
					</span>		
					Tela de Início
				</div>
			</div>								
		</div>
	</div>	
	<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-10-b">			
		<div class="w-shadow w-rounded-15">								
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', 'c2');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-shapes fa-stack-1x fa-inverse dark"></i>					
					</span>						
					Aplicativos
				</div>										
			</div>
		</div>
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-0-b">								
		<div class="w-shadow w-rounded-15">			
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', 'profile');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-t">
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-user-friends fa-stack-1x fa-inverse dark"></i>					
					</span>						
					Pessoas
				</div>										
			</div>
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', 'company');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h">									
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-briefcase fa-stack-1x fa-inverse dark"></i>					
					</span>						
					Negócios
				</div>										
			</div>
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', 'team');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-b">									
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-users fa-stack-1x fa-inverse dark"></i>					
					</span>						
					Equipes
				</div>										
			</div>						
		</div>
	</div>
	
	<div class="large-12 medium-12 small-12 cm-pad-20">			
		<div class="w-shadow w-rounded-15">		
			<a href="/logout.php/">
				<div class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
					<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
						<span class="fa-stack orange" style="vertical-align: middle;">
							<i class="fas fa-circle fa-stack-2x light-gray"></i>
							<i class="fas fa-sign-out-alt fa-stack-1x fa-inverse dark"></i>												
						</span>						
						Sair
					</div>										
				</div>
			</a>
		</div>
	</div>
	
	<div class="large-12 medium-12 small-12 cm-pad-20 cm-mg-10-t cm-pad-10-h border-t-input fs-c text-center">																
		<img src="https://guilhermesantana.com.br/images/50x50.png" style="height: 35px; width: 35px" alt="Logo de Guilherme Santana"></img><br />
		<a href="https://guilhermesantana.com.br" class="font-weight-5  00 w-color-bl-to-or" target="_blank">Guilherme Santana © <?php echo date('Y'); ?></a>
	</div>		
<?php
// =========================================================================
// OPÇÕES DO USUÁRIO
// =========================================================================
}elseif($_GET['vr'] == 'c0'){
	?>
	<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<? if($_GET['qt'] <> ''){ echo $_GET['vr']; } ?>');" class="display-center-general-container w-color-bl-to-or pointer">
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Ajustes</a>	
			</div>
		</div>		
	</div>
	<iframe class="w-rounded-10-b border-none large-12 medium-12 small-12" style="height: calc(100% - 60px)" src="" onload="" frameborder="0">
	</iframe>	
<?php
// =========================================================================
// OPÇÕES DA TELA INICIAL 
// =========================================================================
}elseif($_GET['vr'] == 'c1'){
	$hd = $loggedUser['hd'];
	?>	
	<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<? if($_GET['qt'] <> ''){ echo $_GET['vr']; } ?>');" class="display-center-general-container w-color-bl-to-or pointer">
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Ajustes</a>	
			</div>
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 text-center gray">
		<h2>Tela de Início</h2>	
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-20">			
		<div class="w-shadow background-white w-rounded-15">
			<div class="large-12 medium-12 small-12 cm-pad-10 cm-pad-5-l display-flex">				
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-circle fa-stack-2x light-gray"></i>
					<i class="fas fa-praying-hands fa-stack-1x fa-inverse dark"></i>
				</span>
				<a class="align-left text-ellipsis cm-pad-5-h">Homilia diária (Canção Nova)</a>
				<div class="onoffswitch align-right">
					<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="option-1" tabindex="0" <?if($hd == 'true'){?> checked <?}?> onclick="
						var el = $(this).attr('id');
						var elementoId = el.split('-')[1];
						var estado = $(this).prop('checked');						
						var label = $('[for=\'option-1\']');
						if(el.split('-')[0] == 'option'){		
							$.ajax({
								url: 'https://workz.com.br/partes/resources/request.php',
								type: 'POST',
								data: {
									fnc: 'hd',
									valor: estado,									
									wz: <? echo $_SESSION['wz']; ?>
								},
								success: function(response){				
									sAlert({
										tt: 'Por favor, atualize a página para visualizar as alterações.',
										warning: false
									});
								}
							});		  
						}
					">					
					<label class="onoffswitch-label" for="option-1"></label>
				</div>
				<div class="clear"></div>				
			</div>								
		</div>
	</div>	
<?php

// =========================================================================
// APLICATIVOS
// =========================================================================
}elseif($_GET['vr'] == 'c2'){
	// =========================================================================
	// OPÇÕES DO APLICATIVO
	// =========================================================================
	if($_GET['qt'] <> ''){
	$app_conf = search('app', 'gapp', '', 'us="'.$_SESSION['wz'].'" AND ap = "'.$_GET['qt'].'"');
	$app_info = search('app', 'apps', 'tt', 'id="'.$_GET['qt'].'"');
	?>			
	<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<? if($_GET['qt'] <> ''){ echo $_GET['vr']; } ?>');" class="display-center-general-container w-color-bl-to-or pointer">
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Ajustes</a>	
			</div>
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 text-center gray">
		<h2><? echo $app_info[0]['tt']; ?></h2>	
	</div>
	<?
	$pdo_params = array(
		'type' => 'delete',
		'db' => 'app',
		'table' => 'gapp',
		'where' => 'us="'.$_SESSION['wz'].'" AND ap = "'.$_GET['qt'].'"',				
	);
	$vr = base64_encode(json_encode($pdo_params));
	?>
	<div class="large-12 medium-12 small-12 cm-pad-20">								
		<div class="w-shadow w-rounded-15">			
			<div onclick="removeApp()" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 center-general-container">						
					<span class="fa-stack orange" style="vertical-align: middle;">
						<i class="fas fa-circle fa-stack-2x light-gray"></i>
						<i class="fas fa-times fa-stack-1x fa-inverse dark"></i>
					</span>
					Desinstalar aplicativo
				</div>										
			</div>						
		</div>
	</div>
	<script>
	(function(){
		'use strict';
		function removeApp(){
			swal({
				title: 'Desinstalar <?= $app_info[0]['tt'] ?>?',
				text: 'A desinstalação removerá o aplicativo da sua Tela de Início, mas não apagará as informações fornecidas ao aplicativo. Se deseja apagar os seus dados, faça isso diretamente pelo aplicativo.',
				icon: 'warning',
				buttons: true,
				dangerMode: true
			}).then((result) => {
				if(result){
					swal(
						'<?= $app_info[0]['tt'] ?> removido.',
						'Você pode reinstalá-lo diretamente na loja de aplicativos Workz.',
						'success'
					)
					goTo('../functions/actions.php', 'callback', '', '<?= $vr ?>');
					setTimeout(() => {
						if(document.getElementById('callback').innerHTML !== ''){
							location.reload();
						} 
					}, 500);
				}
			});
		};
		window.removeApp = removeApp;
	})();
	</script>
	<?
	// =========================================================================
	// LISTA DE APLICATIVOS
	// =========================================================================
	}else{
	?>	
	<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<? if($_GET['qt'] <> ''){ echo $_GET['vr']; } ?>');" class="display-center-general-container w-color-bl-to-or pointer">
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Ajustes</a>	
			</div>
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 text-center gray">
		<h2>Aplicativos Instalados</h2>	
	</div>
	<div class="large-12 medium-12 small-12 cm-pad-20">								
		<div class="w-shadow w-rounded-15">			
		<?php
		// Busca os registros de apps do usuário
		$user_apps = search('app', 'gapp', 'ap', 'us="'.$_SESSION['wz'].'"');

		// Cria um array para armazenar as informações completas dos apps
		$apps_details = [];

		// Para cada app, busca as informações detalhadas (título e imagem)
		foreach ($user_apps as $apps) {
			$app_info = search('app', 'apps', 'tt,im', 'id="'.$apps['ap'].'"');
			// Armazena o id do app, o título (tt) e a imagem (im)
			$apps_details[] = [
				'ap' => $apps['ap'],
				'tt' => $app_info[0]['tt'],
				'im' => $app_info[0]['im']
			];
		}

		// Ordena os apps pelo título (tt)
		usort($apps_details, function($a, $b) {
			return strcmp($a['tt'], $b['tt']);
		});

		// Percorre o array ordenado e renderiza o HTML para cada app
		foreach ($apps_details as $key => $app) {
			?>
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '<?php echo $app['ap']; ?>', '<?php echo $_GET['vr']; ?>');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h <?php if ($key == 0) { echo 'w-rounded-15-t'; } elseif (($key + 1) == count($apps_details)) { echo 'w-rounded-15-b'; } ?>">
				<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 center-general-container">
					<img src="data:image/jpeg;base64,<?php echo $app['im']; ?>" style="height: 28.79px; max-width: 28.79px; width: 28.79px; object-fit: cover; object-position: center;" class="w-circle cm-mg-5-r" />
					<?php echo $app['tt']; ?>
				</div>
			</div>
			<?php	
		}
		?>			
		</div>
	</div>
	<?
	}
	
// =========================================================================
// SOBRE A WORKZ!
// =========================================================================
}elseif($_GET['vr'] == 'c4'){
	?>
	<div class="cm-pad-20 large-12 medium-12 small-12 text-ellipsis background-gray w-rounded-10-t z-index-1">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis">						
			<span onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<? if($_GET['qt'] <> ''){ echo $_GET['vr']; } ?>');" class="fa-stack pointer w-color-bl-to-or pointer" style="vertical-align: middle;" title="Voltar para o Início">
				<i class="fas fa-square fa-stack-2x"></i>
				<i class="fas fa-arrow-left fa-stack-1x fa-inverse"></i>
			</span>	
			<a >Sobre</a>
		</div>		
	</div>	
	<div class="cm-pad-15-h large-12 medium-12 small-12 fs-c">
		<div class="cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis border-b-input">
			Versão do site: Versão para testes (Beta)
		</div>
		<div class="cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis border-b-input">
			Idealizado e desenvolvido por <a class="w-color-bl-to-or pointer" href="https://workz.com.br/guisantana">Guilherme Santana</a>
		</div>			
	</div>
	<?
	
// =========================================================================
// PÁGINAS (PESSOAS, NEGÓCIOS E EQUIPES)
// =========================================================================
}elseif($_GET['vr'] == 'company' || $_GET['vr'] == 'team' || $_GET['vr'] == 'profile'){
	
	require_once('../../../functions/insert.php');	
	
	//NOVAS PÁGINAS (NEGÓCIOS E EQUIPES)
	if(isset($_POST) && !empty($_POST)){
		if($_GET['vr'] == 'company'){
			$db = 'cmp';
			$table = 'companies';
			$group = 'employees';
			$group_column = 'em';
		}elseif($_GET['vr'] == 'team'){
			$db = 'cmp';
			$table = 'teams';
			$group = 'teams_users';
			$group_column = 'cm';
		}elseif($_GET['vr'] == 'profile'){
			$db = 'hnw';
			$table = 'hus';
		}		
		$var = '';
		$con = '';
		$n = 0;
		$columns = array();
		$values = array();
		foreach($post as $key => $column){			
			if($key !== 'im' && $key !== 'tp'){
				if($key == 'imTxt'){
					$columns[] = 'im';
					$values[] = "'".str_replace('data:image/jpeg;base64,','',base64_decode($column))."'";
				}else{
					$columns[] = $key;
					$values[] = "'".$column."'";
				}				
			}							
		}		
		$columns = implode(',', $columns).',us,dt';
		$values = implode(',', $values).",'".$_SESSION['wz']."','".date('Y-m-d H:i:s')."'";			
		$page_id = insert($db, $table, $columns, $values);
		if($_GET['qt'] == '' || $page_id > 0){
			if($_GET['vr'] == 'team'){
				$result = insert($db, $group, $group_column.',us,st,dt', "'".$page_id."','".$_SESSION['wz']."','1','".date('Y-m-d H:i:s')."'");
			}else{
				$result = insert($db, $group, $group_column.',us,st,nv,dt', "'".$page_id."','".$_SESSION['wz']."','1','3','".date('Y-m-d H:i:s')."'");
			}
			echo $result;
		}
	}
	
	//ALTERAÇÕES EM GERAL
	if($_GET['qt'] <> ''){
		
		//UPDATE
		if(isset($_GET['crud']) && $_GET['crud'] == 'update'){
			
			require_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');
			
			$column = array_keys($_GET)[2];
			if(isset($_GET['us']) && isset($_GET['nv'])){
				if($_GET['vr'] == 'company'){
					$up = update('cmp', 'employees', "".$column." = '".$_GET[$column]."'", "em = '".$_GET['qt']."' AND us = '".$_GET['us']."'");
				}elseif($_GET['vr'] == 'team'){
					$up = update('cmp', 'teams_users', "".$column." = '".$_GET[$column]."'", "cm = '".$_GET['qt']."' AND us = '".$_GET['us']."'");
				}				
			}else{
				if($_GET['vr'] !== 'profile'){
					$table = pluralize($_GET['vr']);				
					$up = update('cmp', ''.$table.'', "".$column." = '".$_GET[$column]."'", "id = '".$_GET['qt']."'");							
				}else{					 					
					$up = update('hnw', 'hus', "".$column." = '".$_GET[$column]."'", "id = '".$_SESSION['wz']."'");
				}	
			}					
		}
		
		//AJUSTES DA PÁGINA
		$get_count = 0;
		if($_GET['vr'] == 'company' && $_GET['qt'] !== 'new'){			
			$res = search('cmp', 'companies', '', "id = '".$_GET['qt']."'");
			$get_count = count($res);
			$res = $res[0];
		}elseif($_GET['vr'] == 'team' && $_GET['qt'] !== 'new'){
			$res = search('cmp', 'teams', '', "id = '".$_GET['qt']."'");
			$get_count = count($res);
			$res = $res[0];
		}elseif($_GET['vr'] == 'profile' && $_GET['qt'] !== 'new'){
			$res = search('hnw', 'hus', '', "id = '".$_GET['qt']."'");
			$get_count = count($res);
			$res = $res[0];
			if(empty($res['im'])){ $res['im'] =  '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'; }
			if(empty($res['tt'])){ $res['tt'] = $res['ml']; }
		}		
		if($get_count > 0){
			$cfid = $res['id'];
			$cfpc = $res['pg'];
			$cftt = $res['tt'];	
			if($_GET['vr'] == 'company'){							
				$cfex = $res['ex'];
			}else{				
				$cftt = $res['tt'];				
				$cfpc = $res['pc'];				
			}
			$cfus = array();
			if($_GET['vr'] <> 'profile'){
				$cfex = 0;
				if($_GET['vr'] == 'team' && $res['em'] <> ''){					
					$company = search('cmp', 'companies', 'ex', "id = '".$res['em']."'");								
					if(count($company) > 0){
						$company = $company[0];
						if($company['ex'] == 1){
							$cfex = 1;
						}else{
							$cfex = 0;
						}						
					}else{
						$cfex = 0;						
					}
					$cfus = array_unique(array_column(search('cmp', 'employees', 'us', "em = '".$res['em']."' AND nv > 2"), 'us'));
				}else{
					$cfus = array_unique(array_column(search('cmp', 'employees', 'us', "em = '".$res['id']."' AND nv > 2"), 'us'));
				}
				$cfim = $res['im'];
				$cfbk = $res['bk'];
				$cfcl = $res['cl'];				
				$cfst = $res['st'];
			}else{
				$cfex = 0;
				$cfim = $res['im'];
				$cfbk = $res['bk'];
				$cfcl = $res['cl'];										
				if($_GET['qt'] == $_SESSION['wz']){
					$cfus[] = $_SESSION['wz'];
				}
				$cfst = 0;
			}
			
			$cfin = $res['cf'];			
		}
	}
	
	
	?>	
	<div class="cm-pad-20 cm-pad-30-t large-12 medium-12 small-12 text-ellipsis">
		<div class="float-left large-8 medium-8 small-6 text-ellipsis fs-e">
			<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '', '<?= ($_GET['qt'] <> '') ? $_GET['vr'] : '' ?>');" class="display-center-general-container w-color-bl-to-or pointer">
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Ajustes</a>	
			</div>
		</div>		
	</div>
	<div class="large-12 medium-12 small-12 text-center gray">		
		<h2><?= ($_GET['qt'] <> '' && $get_count > 0) ? $cftt : (($_GET['vr'] == 'company') ? 'Negócios' : (($_GET['vr'] == 'team') ? 'Equipes' : (($_GET['vr'] == 'profile') ? 'Pessoas' : ''))) ?><?= ($_GET['qt'] == 'new') ? ' (Novo)' : '' ?></h2>
	</div>
	<?php
	
	// =========================================================================
	// LISTA DE PÁGINAS DO USUÁRIO LOGADO (NEGÓCIOS, EQUIPES E PESSOAS)
	// =========================================================================
	if($_GET['qt'] == ''){
		
		//LISTA DE PÁGINAS
		$list = array();
		
		//COMPANIES
		if($_GET['vr'] == 'company'){			
			$user_companies = array_column(search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."'"),'em');			
			$companies_user = array_column(search('cmp', 'companies', 'id', "us = '".$_SESSION['wz']."'"),'id');			
			$list = array_unique(array_merge($user_companies, $companies_user));
		
		//TEAMS	
		}elseif($_GET['vr'] == 'team'){				
			$list = array_column(search('cmp', 'teams_users', 'cm', "us = '".$_SESSION['wz']."' AND st > 0"),'cm');		
		
		//USERS / FOLLOWERS
		}elseif($_GET['vr'] == 'profile'){			
			$list = array_column(search('hnw', 'usg', 's1', "s0 = '".$_SESSION['wz']."'"),'s1');
			?>					
			<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-0-b">			
				<div class="w-shadow w-rounded-15">								
					<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_SESSION['wz'].'&op=0'; ?>', 'profile')" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
						<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
							<span class="fa-stack orange" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-address-card fa-stack-1x fa-inverse dark"></i>					
							</span>						
							Minhas informações
						</div>										
					</div>
				</div>
			</div>
			<?php
		}
		?>
		<?php		
		if($_GET['vr'] !== 'profile'){
		?>		
		<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-0-b">					
			<div class="w-shadow w-rounded-15">								
				<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', 'new', '<?= $_GET['vr'] ?>');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
					<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
						<span class="fa-stack orange" style="vertical-align: middle;">
							<i class="fas fa-circle fa-stack-2x light-gray"></i>
							<i class="fas fa-plus fa-stack-1x fa-inverse dark"></i>					
						</span>						
						Novo
					</div>										
				</div>
			</div>
		</div>
		<?php
		}
		?>
		<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-0-b">					
			<div class="w-shadow w-rounded-15">								
				<div onclick="window.location.href = 'https://workz.com.br/?<?= ($_GET['vr'] == 'company') ? 'companies' : (($_GET['vr'] == 'team') ? 'teams' : 'users') ?>';" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
					<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
						<span class="fa-stack orange" style="vertical-align: middle;">
							<i class="fas fa-circle fa-stack-2x light-gray"></i>
							<i class="fas fa-<?= ($_GET['vr'] == 'company') ? 'briefcase' : (($_GET['vr'] == 'team') ? 'users' : 'user-friends') ?> fa-stack-1x fa-inverse dark"></i>					
						</span>						
						Procurar <?= ($_GET['vr'] == 'company') ? 'Negócios' : (($_GET['vr'] == 'team') ? 'Equipes' : 'Pessoas') ?>
					</div>										
				</div>
			</div>
		</div>
		<div class="large-12 medium-12 small-12 cm-pad-20">								
			<div class="w-shadow w-rounded-15">			
			<?php
			$content = array();

			// Pre-processa os itens para recuperar o título e dados de cada página
			$items = [];
			foreach ($list as $result) {
				if ($_GET['vr'] == 'company') {
					$content = search('cmp', 'companies', 'tt,pg', "id = '".$result."'");
				} elseif ($_GET['vr'] == 'team') {
					$content = search('cmp', 'teams', 'tt,pg', "id = '".$result."'");
				} elseif ($_GET['vr'] == 'profile') {
					$content = search('hnw', 'hus', 'tt,pg', "id = '".$result."'");
				}
				
				$n = count($content);
				if ($n > 0) {
					$content = $content[0];
					$title = $content['tt'];
					// Armazena os dados no array auxiliar
					$items[] = [
						'result' => $result,
						'title'  => $title,
						'pg'     => $content['pg']
					];
				}
			}

			// Ordena o array pelo título (campo "tt")
			usort($items, function($a, $b) {
				return strcmp($a['title'], $b['title']);
			});

			// Renderiza a listagem usando o array ordenado
			foreach ($items as $key => $item) {
				$result = $item['result'];
				$title  = $item['title'];
				$pg     = $item['pg'];
				?>
				<div onclick="goTo('partes/resources/modal_content/config_home.php', 'config', '<?= $result . '&op=0' ?>', '<?= $_GET['vr'] ?>');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h <?= ($key == 0) ? 'w-rounded-15-t' : ((($key + 1) == count($items)) ? 'w-rounded-15-b' : '') ?>">
					<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5 center-general-container">			
						<span class="fa-stack orange" style="vertical-align: middle;">
							<i class="fas fa-circle fa-stack-2x light-gray"></i>
							<i class="fas <?= ($_GET['vr'] == 'company') ? 'fa-building' : (($_GET['vr'] == 'team') ? 'fa-users' : (($_GET['vr'] == 'profile') ? 'fa-user' : '')) ?> fa-stack-1x fa-inverse dark"></i>
						</span>
						<?= $title ?>
						<?= ($pg == 0) ? '<a class="orange cm-mg-10-l"><i class="fas fa-eye-slash"></i></a>' : '<a class="green cm-mg-10-l"><i class="fas fa-eye"></i></a>' ?>						
					</div>
				</div>
				<?php	
			}
			?>			
			</div>
		</div>
		<?
		
	// =========================================================================
	// EDIÇÃO DE PÁGINA
	// =========================================================================
	}else{
		
		// =========================================================================
		// EDIÇÃO DE PÁGINA GERENCIADA PELO USUÁRIO
		// =========================================================================
		if($get_count > 0 && in_array($_SESSION['wz'], $cfus)){
			?>			
			<div class="cm-mg-20-t w-square position-relative centered" style="height: 150px; width: 150px;">
				<div id="imgForm" class="w-square-content w-circle w-shadow-1">
					<input type="hidden" id="tp_id" name="tp_id" value="<? echo $_GET['vr'].'_'.$_GET['qt']; ?>"></input>
					<input accept="image/*" type='file' name="im" id="imgInp" class="display-none" onchange="imgPreview(this);
					waitForElm('#imTxt').then((elm) => {						
						console.log(elm.value);
						formValidator2('imgForm', 'partes/resources/modal_content/config_home.php', 'config');
					});
					"/>
					<label for="imgInp" class="w-square-content w-bkg-wh-to-gr w-color-bl-to-or display-center-general-container text-center large-12 medium-12 small-12 height-100 w-rounded-5 pointer" title="Carregar imagem">
						<span class="<?if($cfim <> ''){?>display-none<?}?> fs-g fa-stack pointer">
							<i class="fas fa-upload fa-stack-1x"></i>
						</span>
						<span class="<?if($cfim == ''){?>display-none<?}?> large-12 medium-12 small-12 height-100">
							<img class="w-shadow w-rounded-5 large-12 medium-12 small-12 height-100" src="data:image/png;base64,<? if($cfim <> ''){ echo $cfim; }else{ echo '#'; } ?>" style="object-fit: cover; object-position: center;" />
						</span>								
					</label>						
				</div>
			</div>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
				<div class="w-shadow w-rounded-15">
				<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t <?php if($_GET['vr'] == 'profile'){ echo 'w-rounded-15-t'; }else{ echo 'w-rounded-15'; } ?>">
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
						<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Nome</div>
						<input onchange="
						goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&tt='; ?>' + this.value, '<? echo $_GET['vr']; ?>');
						"class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" id="tt" name="tt" placeholder="" value="<? echo $cftt; ?>"></input>
					</div>										
				</div>
				<?php
				if($_GET['vr'] == 'profile'){
				?>
				<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
						<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">E-mail</div>
						<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="un" placeholder="mail@mail.com" value="<? echo $res['ml']; ?>" disabled></input>
					</div>										
				</div>
				<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
						<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Nascimento</div>
						<input onchange="" type="date" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="dt" value="<? echo ''; ?>"></input>
					</div>										
				</div>
				<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input w-rounded-15-b">
					<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
						<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">CPF</div>
						<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="un" placeholder="XXX.XXX.XXX-XX" value=""></input>
					</div>										
				</div>
				<?php
				}
				?>
				</div>
			</div>
			<?php
			if($_GET['vr'] == 'profile'){
			?>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
				<div class="w-shadow w-rounded-15">
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">CEP</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="CEP" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Endereço</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Endereço" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Número</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Número" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Complemento</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Complemento" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Bairro</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Bairro" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Cidade</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Cidade" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Estado</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="Estado" value="<? echo ''; ?>" disabled></input>
						</div>
					</div>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input w-rounded-15-b">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">País</div>
							<input onchange="" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="cp" placeholder="País" value="<? echo ''; ?>" disabled></input>
						</div>										
					</div>
				</div>
			</div>
			<?php
			}
			if($_GET['vr'] == 'profile'){
				
				
			}
			
			if($res['pg'] > 0){
			
			?>
			
			
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">			
				<div class="w-shadow w-rounded-15">								
												
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">workz.com.br/</div>
							<input onchange="
							goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&un='; ?>' + this.value, '<? echo $_GET['vr']; ?>');
							" class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" name="un" placeholder="nomedapagina" value="<? echo $res['un']; ?>"></input>
						</div>										
					</div>					
					<?
					if($_GET['vr'] == 'team'){
					$companies = search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."' AND nv > 0");
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Negócio</div>							
							<select onchange="
								goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&em='; ?>' + this.value, '<? echo $_GET['vr']; ?>');
							" name="em" id="em" class="float-left border-none large-10 medium-10 small-8 required" disabled style="height: 41.59px">								
								<?
								foreach($companies as $company){
									$company = $company['em'];
									$company_title = search('cmp', 'companies', 'tt', "id = '".$company."'")[0]['tt'];
									?>
									<option <?if($res['em'] == $company){?>selected<?}?> value="<? echo $company; ?>"><? echo $company_title; ?></option>
									<?
								}
								?>
							</select>
						</div>										
					</div>												
					<?
					}
					?>					
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Exib. da Página</div>							
							<select onchange="
								goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&pg='; ?>' + this.value, '<? echo $_GET['vr']; ?>');
							" name="pg" id="pg" class="float-left border-none large-10 medium-10 small-8 required" style="height: 41.59px">
								<option <?if($res['pg'] == 0){?>selected<?}?> value="0">Somente <?if($_GET['vr'] == 'company'){?>autores e moderadores<?}elseif($_GET['vr'] == 'team'){?>autores e moderadores<?}elseif($_GET['vr'] == 'profile'){?>eu<?}?></option>
								<option <?if($res['pg'] == 1){?>selected<?}?> value="1">Usuários logados</option>
								<option <?if($res['pg'] == 2){?>selected<?}?> value="2">Todos</option>
							</select>
						</div>										
					</div>	
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Exib. do Conteúdo</div>							
							<select onchange="
								goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&pc='; ?>' + this.value, '<? echo $_GET['vr']; ?>');
							" name="pc" id="pc" class="float-left border-none large-10 medium-10 small-8 required" style="height: 41.59px">
								<option <?if($res['pc'] == 0){?>selected<?}?> value="0">Somente <?if($_GET['vr'] == 'company'){?>autores e moderadores<?}elseif($_GET['vr'] == 'team'){?>autores e moderadores<?}elseif($_GET['vr'] == 'profile'){?>eu<?}?></option>
								<option <?if($res['pc'] == 1){?>selected<?}?> value="1"><?if($_GET['vr'] == 'company'){?>Colaboradores membros<?}elseif($_GET['vr'] == 'team'){?>Membros da equipe<?}elseif($_GET['vr'] == 'profile'){?>Seguidores<?}?></option>
								<option <?if($res['pc'] == 2){?>selected<?}?> value="2">Usuários logados</option>
								<option <?if($res['pc'] == 3){?>selected<?}?> value="3">Todos</option>
							</select>
						</div>										
					</div>
					
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container background-white">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Cor dos ícones e botões</div>
							<input 
								type="color" 
								onchange="
								const colorValue = encodeURIComponent(this.value); // Codifica o valor da cor
								goTo('partes/resources/modal_content/config_home.php', 'config', '<?= $_GET['qt'] ?>&crud=update&cl=' + colorValue, '<?= $_GET['vr'] ?>');												
								" 
								class="float-left border-none large-10 medium-10 small-8 required" 
								style="height: 41.59px" 
								id="cl" 
								name="cl" 
								value="<?= $cfcl; ?>"
							>
						</div>
					</div>
					
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input w-rounded-15-b">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Sobre</div>																												
							<textarea onchange="goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&cf='; ?>' + encodeURIComponent(this.value), '<? echo $_GET['vr']; ?>');" class="float-left border-none large-10 medium-10 small-8 required cm-pad-10 cm-pad-5-l" style="min-height: 150px; line-height: 1.5em;" id="cf" name="cf" placeholder="Escreva algo..." value=""><?= htmlspecialchars($cfin ?? '') ?></textarea>
						
						
						</div>										
					</div>
				</div>
			</div>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">			
				<div class="w-shadow w-rounded-15">								
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
							<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Imagem de fundo</div>
							<div class="float-left border-none large-10 medium-10 small-8 required cm-pad-10 cm-pad-5-l centered" style="width: 150px;">
								<div id="bkgForm" class="large-12 small-12 medium-12 w-shadow-1">
									<input type="hidden" id="tp_id" name="tp_id" value="<? echo $_GET['vr'].'_'.$_GET['qt']; ?>"></input>
									<input accept="image/*" type='file' name="bk" id="imgBkg" class="display-none" onchange="imgPreview(this);
									waitForElm('#imTxt').then((elm) => {						
										formValidator2('bkgForm', 'partes/resources/modal_content/config_home.php', 'config');
									});
									"/>
									<label for="imgBkg" class="large-12 small-12 medium-12 w-bkg-wh-to-gr w-color-bl-to-or display-center-general-container text-center height-100 w-rounded-5 pointer" title="Carregar imagem">
										<span class="<?if($cfbk <> ''){?>display-none<?}?> fs-g fa-stack pointer">
											<i class="fas fa-upload fa-stack-1x"></i>
										</span>
										<span class="<?if($cfbk == ''){?>display-none<?}?> large-12 medium-12 small-12 height-100">
											<img class="w-shadow w-rounded-5 large-12 medium-12 small-12 height-100" src="data:image/png;base64,<? if($cfbk <> ''){ echo $cfbk; }else{ echo '#'; } ?>" style="object-fit: cover; object-position: center;" />
										</span>								
									</label>						
								</div>
							</div>
						</div>	
					</div>
				</div>
			</div>
			<?
			if($_GET['vr'] !== 'profile'){
			?>
			
			
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
				<div class="w-shadow w-rounded-15">
					<?					
					if($_GET['vr'] == 'company'){
						$users = search('cmp', 'employees', 'id,us,st,nv', "st > 0 AND em = '".$_GET['qt']."'");
						$pgtp = $_GET['qt'];
					}elseif($_GET['vr'] == 'team'){
						$users = search('cmp', 'teams_users', 'id,us,st', "st > 0 AND cm = '".$_GET['qt']."'");
					}					
					$levels = [
						0 => 'Nenhum',
						1 => 'Operação',
						2 => 'Supervisão',
						3 => 'Gestão',
						4 => 'Direção',
						5 => 'Conselho'					
					];
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container font-weight-500">										
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l <?if($_GET['vr'] !== 'company'){?>large-12 medium-12 small-12<?}?>">Usuário</div>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l <?if($_GET['vr'] !== 'company'){?>display-none<?}?>">Nível</div>							
						</div>										
					</div>
					<?					
					foreach($users as $key => $user){										
					if($_GET['vr'] == 'team'){
						$user['nv'] = search('cmp', 'employees', 'id,us,st,nv', "st > 0 AND em = ".$res['em']." AND us = ".$user['us']."")[0]['nv'];
						$pgtp = $res['em'];						
						$pdo_params = array(
							'type' => 'delete',
							'db' => 'cmp',
							'table' => 'teams_users',
							'where' => 'us="'.$user['us'].'" AND cm="'.$_GET['qt'].'"'
						);						
					}elseif($_GET['vr'] == 'company'){
						$pdo_params = array(
							'type' => 'delete',											
							'db' => 'cmp',
							'table' => 'employees',
							'where' => 'us="'.$user['us'].'" AND em="'.$_GET['qt'].'"'
						);						
					}
					$vr = base64_encode(json_encode($pdo_params));
					?>													
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white <?if(($key + 1) == count($users)){?>w-rounded-15-b<?}?>">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container border-t-input">										
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l <?if($_GET['vr'] !== 'company'){?>large-12 medium-12 small-12 cm-pad-20-r<?}?>">
								<? 
								$usname = search('hnw', 'hus', 'tt', "id='".$user['us']."'")[0]['tt']; 								
								echo $usname;
								if (in_array($user['us'], $cfus) && count($cfus) > 1 || !in_array($user['us'], $cfus)) {
								?>
								<i onclick="
								swal({
									title: 'Tem certeza?',
									text: 'Deseja remover <? echo $usname; ?>?',
									icon: 'warning',
									buttons: true,
									dangerMode: true
								}).then((result) => {
									if(result){													
										goTo('functions/actions.php', 'callback', '', '<? echo $vr; ?>');																			
										setTimeout(() => {
											if(document.getElementById('callback').innerHTML !== ''){
												goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&op=0'; ?>', '<? echo $_GET['vr']; ?>');												
											} 
										}, 500);
									}
								});
								" class="fas fa-user-times w-color-or-to-bl float-right pointer" title="Remover <? echo $usname; ?>"></i>
								<?
								}							
								?>							
							</div>
							<?
							if($_GET['vr'] == 'company'){
							?>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-15-l">
								<select onchange="
								goTo('partes/resources/modal_content/config_home.php', 'config', '<? echo $_GET['qt'].'&crud=update&nv='; ?>' + this.value + '<? echo '&us='.$user['us'].''; ?>', 'company');
								" name="pg" id="pg" class="float-left border-none large-12 medium-12 small-12 required" style="height: 41.59px">
									<?
									foreach($levels as $key => $level){
									?>
									<option <?if($user['nv'] == $key){?>selected<?}?> value="<? echo $key; ?>" <?if($_GET['vr'] == 'team' && $key > 3){?>disabled<?}?> ><? echo $level; ?></option>
									<?
									}
									?>
								</select>
							</div>
							<?
							}
							?>
						</div>
					</div>					
					<?
					}									
					?>	
				</div>											
				
			</div>						
			<?
			}
			?>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-5-b">
				<div class="large-12 medium-12 small-12 cm-pad-20-t">			
					<div class="w-shadow w-rounded-15">																
						<div onclick='sAlert({
										fnc: function() {
											goTo(
												"partes/resources/modal_content/config_home.php",
												"config",
												"<? echo $_GET['qt'].'&crud=update&pg=0'; ?>",
												"<? echo $_GET['vr'] ?>"
											);
										},
										tt: "Deseja tornar a página <? echo $cftt; ?> invisível? Após esta ação, não será possível gerenciar as congigurações e a página não será mais vista.",
										ss: "Página está invisível.",
										cl: "Sem alterações. A página permanece visível."
									});' 
							class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
							<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
								<span class="fa-stack orange" style="vertical-align: middle;">
									<i class="fas fa-circle fa-stack-2x light-gray"></i>
									<i class="fas fa-eye-slash fa-stack-1x fa-inverse dark"></i>					
								</span>						
								Tornar página invisível
							</div>										
						</div>
					</div>
				</div>
			</div>
			<?php
			}else{
				?>				
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-5-b">
					<div class="large-12 medium-12 small-12 cm-pad-20-t">			
						<div class="w-shadow w-rounded-15" title="Tornar visível">																
							<div onclick='sAlert({
											fnc: function() {
												goTo(
													"partes/resources/modal_content/config_home.php",
													"config",
													"<? echo $_GET["qt"] . "&crud=update&pg=1"; ?>",
													"<? echo $_GET["vr"]; ?>"
												);
											},
											tt: "Deseja tornar a página <? echo $cftt; ?> visível? Após esta ação, a página ficará acessível conforme as configurações de exibição.",
											ss: "A página está visível.",
											cl: "Sem alterações. A página permanece invisível."
										});'
								class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
								<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
									<span class="fa-stack orange" style="vertical-align: middle;">
										<i class="fas fa-circle fa-stack-2x light-gray"></i>
										<i class="fas fa-eye fa-stack-1x fa-inverse dark"></i>					
									</span>						
									Tornar página visível
								</div>										
							</div>
						</div>
					</div>
				</div>
				<div class="large-12 medium-12 small-12 cm-pad-35-h cm-pad-20-t cm-pad-5-b">
					Ao tornar esta página visível, você estará permitindo que o seu conteúdo seja acessado pelos usuários da plataforma, além de ser indexado por motores de busca da internet, conforme as configurações de exibição.
				</div>				
				<?
			}
			
		// =========================================================================
		// EDIÇÃO DE VISUALIZAÇÃO DE OUTRAS PÁGINAS
		// =========================================================================
		}else{
			
			if($_GET['vr'] == 'profile'){	

			?>
			<div class="large-12 medium-12 small-12 cm-pad-20 cm-pad-0-b">			
				<div class="w-shadow w-rounded-15">								
					<a href="https://workz.com.br/?profile=<?= $_GET['qt'] ?>"><div class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
							<span class="fa-stack orange" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-user fa-stack-1x fa-inverse dark"></i>					
							</span>						
							Visitar <?= $cftt ?>
						</div>										
					</div></a>
					<div onclick="" class="cm-pad-5-t border-t-input cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-b">
						<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
							<span class="fa-stack orange" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-eye-slash fa-stack-1x fa-inverse dark"></i>					
							</span>						
							Deixar de seguir
						</div>										
					</div>
					
					
				</div>
			</div>
			<?php
			}
			?>
			
			<?
			
		}
		
		//NEGÓCIOS
		if($_GET['vr'] == 'company'){
			//tp = 0 > Controle Acionário
			//Controladas (Participações)
			$parts = search('cmp', 'companies_groups', 'emC,fr', "emP = {$_GET['qt']} AND tp = 0");
			//Controladores
			$contr = search('cmp', 'companies_groups', 'emP,fr', "emC = {$_GET['qt']} AND tp = 0");
			//tp = 1 > Relacionamento Comercial
			//Clientes
			$clints = search('cmp', 'companies_groups', 'emC,fr', "emP = {$_GET['qt']} AND tp = 1");
			//Fornecedores
			$suppls = search('cmp', 'companies_groups', 'emP,fr', "emC = {$_GET['qt']} AND tp = 1");	
			if(count($parts) > 0){
			?>		
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b ">
				<div class="w-shadow w-rounded-15">
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container font-weight-500">										
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Controladas</div>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Participação (%)</div>
						</div>										
					</div>					
					<?
					foreach($parts as $key => $part){					
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input <?if(($key + 1) == count($parts)){?>w-rounded-15-b<?}?>">				
						<?					
						$partTitle = search('cmp', 'companies', 'tt', "id = {$part['emC']}")[0]['tt'];
						?>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo $partTitle;
						?>
						</div>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo ($part['fr']*100).'%';
						?>						
						</div>									
					</div>
					<?
					}
					?>					
				</div>
			</div>
			<?
			}
			if(count($contr) > 0){
			?>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
				<div class="w-shadow w-rounded-15">
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container font-weight-500">
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Controladores</div>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Participação (%)</div>
						</div>										
					</div>					
					<?
					foreach($contr as $key => $cont){
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input <?if(($key + 1) == count($contr)){?>w-rounded-15-b<?}?>">
						<?					
						$contrTitle = search('cmp', 'companies', 'tt', "id = {$cont['emP']}")[0]['tt'];
						?>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo $contrTitle;
						?>
						</div>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo ($cont['fr']*100).'%';
						?>						
						</div>					
					</div>
					<?
					}
					?>
				</div>
			</div>			
			<?
			}
			if(count($clints) > 0){
			?>		
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b ">
				<div class="w-shadow w-rounded-15">
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container font-weight-500">										
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Clientes</div>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Contato</div>
						</div>										
					</div>					
					<?
					foreach($clints as $key => $clint){					
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input <?if(($key + 1) == count($clints)){?>w-rounded-15-b<?}?>">
						<?					
						$clintTitle = search('cmp', 'companies', 'tt', "id = {$clint['emC']}")[0]['tt'];
						?>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo $clintTitle;
						?>
						</div>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						
						?>						
						</div>									
					</div>
					<?
					}
					?>					
				</div>
			</div>
			<?
			}
			if(count($suppls) > 0){
			?>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">
				<div class="w-shadow w-rounded-15">
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
						<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-supplainer font-weight-500">
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Fornecedores</div>
							<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">Contato</div>
						</div>										
					</div>					
					<?
					foreach($suppls as $key => $suppl){
					?>
					<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input <?if(($key + 1) == count($suppls)){?>w-rounded-15-b<?}?>">
						<?					
						$supplsTitle = search('cmp', 'companies', 'tt', "id = {$suppl['emP']}")[0]['tt'];
						?>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						echo $supplsTitle;
						?>
						</div>
						<div class="float-left large-6 medium-6 small-6 text-ellipsis cm-pad-10 cm-pad-15-l">
						<?
						
						?>						
						</div>					
					</div>
					<?
					}
					?>
				</div>
			</div>			
			<?
			}
		}
		
		//NOVA PÁGINA
		if($_GET['qt'] == 'new'){
			?>	
			<div id="divForm">
				<div class="cm-mg-20-t w-square position-relative centered" style="height: 150px; width: 150px;">
					<div class="w-square-content w-circle w-shadow-1">
						<input accept="image/*" type='file' name="im" id="imgInp" class="display-none" onchange="imgPreview(this)"/>
						<label for="imgInp" class="w-square-content w-bkg-wh-to-gr w-color-bl-to-or display-center-general-container text-center large-12 medium-12 small-12 height-100 w-rounded-5 pointer" title="Carregar imagem">
							<span class="fs-g fa-stack pointer centered">
								<i class="fas fa-upload fa-stack-1x"></i>
							</span>
							<span class="display-none large-12 medium-12 small-12 height-100">
								<img class="w-shadow w-rounded-5 large-12 medium-12 small-12 height-100" src="#" style="object-fit: cover; object-position: center;" />
							</span>								
						</label>						
					</div>
				</div>
				<input type="hidden" class="" id="tp" name="tp" value="<? echo $_GET['vr']; ?>"></input>
				<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">			
					<div class="w-shadow w-rounded-15">								
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white w-rounded-15-t">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Nome</div>
								<input class="float-left border-none large-10 medium-10 small-8 required cm-pad-5-l" style="height: 41.59px" id="tt" name="tt" placeholder="Nome" value=""></input>
							</div>										
						</div>
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">workz.com.br/</div>														
								<input class="float-left border-none large-10 medium-10 small-8 cm-pad-5-l" style="height: 41.59px" name="un" placeholder="nomedapagina" value=""></input>
							</div>										
						</div>
						<?
						if($_GET['vr'] == 'team'){
						$companies = array_unique(array_column(search('cmp', 'employees', 'em', "us = '".$_SESSION['wz']."' AND nv > 2"), 'em'));
						$blocked_companies = array_unique(array_column(search('cmp', 'companies', 'id', "id IN (".implode(',', $companies).") AND pg = 0"), 'id'));	
						$companies = array_diff($companies, $blocked_companies);
						?>
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Negócio</div>
								<select name="em" id="em" class="float-left border-none large-10 medium-10 small-8 required" style="height: 41.59px">									
									<option disabled selected value="">Selecione</option>
									<?
									foreach($companies as $company){										
										$company_title = search('cmp', 'companies', 'tt', "id = '".$company."'")[0]['tt'];
										?>
										<option value="<? echo $company; ?>"><? echo $company_title; ?></option>
										<?
									}
									?>
								</select>
							</div>										
						</div>												
						<?
						}
						?>
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Exib. da Página</div>							
								<select name="pg" id="pg" class="float-left border-none large-10 medium-10 small-8 required" style="height: 41.59px">
									<option disabled selected value="">Selecione</option>
									<option value="0">Invisível</option>
									<option value="1">Visível para usuários logados</option>
									<option value="2">Visível para todos</option>
								</select>
							</div>										
						</div>	
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Exib. do Conteúdo</div>							
								<select name="pc" id="pc" class="float-left border-none large-10 medium-10 small-8 required" style="height: 41.59px">
									<option disabled selected value="">Selecione</option>
									<option value="0">Somente <?if($_GET['vr'] == 'company'){?>autores e moderadores<?}elseif($_GET['vr'] == 'team'){?>autores e moderadores<?}elseif($_GET['vr'] == 'profile'){?>eu<?}?></option>
									<option value="1"><?if($_GET['vr'] == 'company'){?>Colaboradores<?}elseif($_GET['vr'] == 'team'){?>Membros da equipe<?}elseif($_GET['vr'] == 'profile'){?>Seguidores<?}?></option>
									<option value="2">Usuários logados</option>
									<option value="3">Todos</option>
								</select>
							</div>										
						</div>
						<div class="large-12 medium-12 small-12 position-relative text-ellipsis background-white border-t-input w-rounded-15-b">
							<div class="large-12 medium-12 small-12 text-ellipsis display-center-general-container">										
								<div class="float-left large-2 medium-2 small-4 text-ellipsis cm-pad-15-l">Sobre</div>																												
								<textarea class="float-left border-none large-10 medium-10 small-8 cm-pad-10 cm-pad-5-l" style="min-height: 150px; line-height: 1.5em;" id="cf" name="cf" placeholder="Escreva algo..." value=""></textarea>
							</div>										
						</div>
					</div>
				</div>
			</div>
			<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-20-t cm-pad-5-b">			
				<div class="w-shadow w-rounded-15">								
					<div onclick="formValidator2('divForm', 'partes/resources/modal_content/config_home.php', 'config');" class="cm-pad-5-t cm-pad-5-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15">
						<div class="large-12 medium-12 small-12 text-ellipsis cm-pad-5">
							<span class="fa-stack orange" style="vertical-align: middle;">
								<i class="fas fa-circle fa-stack-2x light-gray"></i>
								<i class="fas fa-plus fa-stack-1x fa-inverse dark"></i>					
							</span>						
							Criar
						</div>										
					</div>
				</div>
			</div>						
			<?
		}		
	}
}
?>
</div>