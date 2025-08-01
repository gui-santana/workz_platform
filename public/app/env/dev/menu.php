<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
session_start();
$app = json_decode($_SESSION['app'], true);
if($loggedUser = search('hnw', 'hus', 'im,ml,tt', "id = {$_SESSION['wz']}")){
	$loggedUser = $loggedUser[0];
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
	<div class="clearfix large-12 medium-12 small-12 text-ellipsis w-color-bl-to-or background-white w-rounded-15 w-shadow cm-pad-15">
		<div class="clearfix float-left large-12 medium-12 small-12 text-ellipsis" style="height: 70px;">
			<div class="w-circle float-left" style="height: 70px; width: 70px; background: url(data:image/jpeg;base64,<?= $loggedUser['im'] ?>); background-size: cover; background-position: center; background-repeat: no-repeat;">
			</div>
			<div class="float-left text-left display-center-general-container dark" style="height: 70px; width: calc(100% - 70px); justify-content: left;">
				<div class="cm-pad-20-l">
					<p class="dark font-weight-500 text-ellipsis"><?= $loggedUser['tt'] ?></p>
					<p class="fs-b cm-mg-5-t text-ellipsis"><?= $loggedUser['ml'] ?></p>
					<p class="fs-b cm-mg-5-t gray text-ellipsis" >Você precisa acessar a Workz! para atualizar seus dados pessoais.</p>
				</div>
			</div>			
		</div>	
	</div>		
</div>
<!-- Opções do aplicativo -->
<div id="appOptions" class="display-none large-12 medium-12 small-12 cm-pad-20 cm-pad-0-t">								
</div>
<!-- Opções comuns -->
<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-b">			
	<div class="w-shadow w-rounded-15">
		<div onclick="shareContent(); toggleSidebar()" class="fs-d cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-t">
			<div class="large-12 medium-12 small-12 text-ellipsis">
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-square fa-stack-2x light-gray"></i>
					<i class="fas fa-share fa-stack-1x fa-inverse dark"></i>					
				</span>
				<a class="" style="vertical-align: middle;">Compartilhar app <?= $app['tt'] ?></a>
			</div>
		</div>		
		<a href="mailto:guilherme@guilhermesantana.com.br" target="_blank">
		<div class="fs-d cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-t-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-b">
			<div class="large-12 medium-12 small-12 text-ellipsis center-general-container">											
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-square fa-stack-2x light-gray"></i>
					<i class="fas fa-exclamation-triangle fa-stack-1x fa-inverse dark"></i>					
				</span>
				Reportar Erro
			</div>										
		</div>
		</a>				
	</div>
</div>
<div class="large-12 medium-12 small-12 cm-pad-20-h cm-pad-10-t cm-pad-20-b">								
	<div class="w-shadow w-rounded-15">
	<a href="https://workz.com.br" target="_blank">
		<div class="fs-d cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-t">
			<div class="large-12 medium-12 small-12 text-ellipsis center-general-container">											
				<img class="cm-mg-5-l cm-mg-5-r w-rounded-5 centered" src="https://workz.com.br/images/icons/ios/AppIcon-20@2x.png" style="height: 26px; width: 26px; margin: 0 5.4px 0 5.4px; filter: grayscale(1) brightness(1); opacity: .25"></img>										
				Ir para Workz!
			</div>										
		</div>
	</a>	
	<a href="https://app.workz.com.br/protspot/logout.php/?app=<?= $app['nm'] ?>">
		<div class="fs-d cm-pad-10-t cm-pad-10-b large-12 medium-12 small-12 position-relative text-ellipsis w-color-bl-to-or border-b-input pointer w-bkg-wh-to-gr cm-pad-5-h w-rounded-15-b">
			<div class="large-12 medium-12 small-12 text-ellipsis">
				<span class="fa-stack orange" style="vertical-align: middle;">
					<i class="fas fa-square fa-stack-2x light-gray"></i>
					<i class="fas fa-sign-out-alt fa-stack-1x fa-inverse dark"></i>												
				</span>						
				Sair
			</div>										
		</div>
	</a>
	</div>
</div>
<div class="large-12 medium-12 small-12 cm-pad-20 cm-mg-20-t cm-pad-10-h border-t-input fs-c text-center">	
	<p class="cm-mg-5-b">Workz! © <?= $app['tt'] ?> | v<a class="uppercase"><?= $app['environment'][$app['env']].$app['rl'] ?></a></p>
	<a href="https://guilhermesantana.com.br" class="font-weight-5  00 w-color-bl-to-or" target="_blank">Desenvolvido por <a class="w-color-bl-to-or font-weight-500" href="https://workz.com.br/guisantana">Guilherme Santana</a>
</div>
<?php
}
?>