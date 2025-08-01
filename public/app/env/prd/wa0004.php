<?php
// Inclui o autoloader do Composer
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Importa as classes do PHPMailer no escopo global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');
$app = search('app', 'apps', '', "id = '4'")[0];
$user_pass = search('app', 'wa0004_keys', '', "us = '".$_SESSION['wz']."'");

if($user = search('hnw', 'hus', '', "id = {$_SESSION['wz']}")[0]){

if(count($user_pass) == 0){
	require_once($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');
	require_once($_SERVER['DOCUMENT_ROOT'].'/functions/randomPassword.php');
	$senha = randomPassword(11);
	$result = insert('app', 'wa0004_keys', 'us,ky', "'".$_SESSION['wz']."', '".$senha."'");
	$user_pass = search('app', 'wa0004_keys', '', "us = '".$_SESSION['wz']."'");
}
?>

<div class="z-index-0 large-12 medium-12 small-12 height-100">	
	<?
	if($user_pass[0]['s1'] == 'true'){
		if(!isset($_POST['authCode'])){
			
			$codigoExistente = explode('|', $user_pass[0]['ty'] ?? '');
			$ultimoEnvio = isset($codigoExistente[0]) && is_numeric($codigoExistente[0]) ? (int)$codigoExistente[0] : 0;

			// Limite de 5 minutos
			$limiteTempo = 5 * 60;

			if (time() - $ultimoEnvio < $limiteTempo) {
				// Não envie o e-mail novamente
				
				$tempoRestante = $limiteTempo - (time() - $ultimoEnvio);
				$minutos = floor($tempoRestante / 60);
				$segundos = $tempoRestante % 60;

				$msg = "Enviamos um código recentemente. Verifique seu e-mail ou aguarde $minutos minutos e $segundos segundos para solicitar novamente.";
			}else{
				$access = false;
				// Gerar um código de verificação
				$codigoVerificacao = mt_rand(100000, 999999);		
				// Obter o timestamp do momento do login
				$timeCode = time().'|'.$codigoVerificacao; // Isso obtém o timestamp atual em segundos
				// Você precisará adaptar isso de acordo com a estrutura do seu banco de dados		
				update('app', 'wa0004_keys', "ty = '".$timeCode."'", "us = '".$_SESSION['wz']."'");		
				// Enviar o código de verificação para o e-mail do usuário usando PHPMailer		
				$mail = new PHPMailer(true);
				$mail->CharSet = 'UTF-8';
				$mail->isSMTP();
				$mail->Host = 'smtp.hostinger.com';
				$mail->SMTPAuth = true;
				$mail->Username = 'noreply@workz.com.br';
				$mail->Password = 'Y5PegXgmlL]9';
				$mail->SMTPSecure = 'ssl'; // ou 'ssl' se aplicável
				$mail->Port = 465; // ou a porta do seu servidor SMTP
				$mail->setFrom('noreply@workz.com.br', 'Workz!');
				$mail->addAddress($user['ml']); // Endereço de e-mail do usuário
				$mail->Subject = 'Código de Verificação de Acesso ao Workz! Senhas.';
				$mail->Body = 'Seu código de verificação é: ' . $codigoVerificacao;
				if ($mail->send()) {
					$msg = 'Código enviado para ' . $user['ml'] . ' às ' . date('Y-m-d H:i:s');
				} else {
					error_log('Erro ao enviar o e-mail para ' . $user['ml'] . ': ' . $mail->ErrorInfo);
				}
			}
			?>
			<div class="height-100 large-12 medium-12 small-12 overflow-y-auto overflow-x-hidden content position-relative cm-pad-30-b">
				<div class="white display-center-general-container cm-pad-20 cm-pad-30-b">
					<div style="height: 70px; width: 70px; color: <? echo $colours[0]; ?>; " class="text-center position-relative display-center-general-container fs-f float-left display-block">
						<div class="position-absolute abs-t-0 abs-l-0 w-rounded-20 height-100 large-12 medium-12 small-12 background-white w-shadow z-index-0" style="transform: rotate(20deg);"></div>
						<i class="fas fa-lock fs-g pointer cm-pad-5-t z-index-1 centered"></i>
					</div>
					<h3 class="display-block cm-mg-15-l">Verificação de acesso</h3>
				</div>
				<div class="cm-mg-30-t position-relative centered large-10 medium-12 small-12 fs-e cm-pad-15 background-white w-rounded-30 w-shadow-1 cm-mg-20-t cm-pad-30" style="color: <? echo $colours[0]; ?>">				
					<form method="post" action="">			
					<div class="large-12 medium-12 small-12 cm-mg-30-b">
						<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
							<label><?php echo $msg; ?> Por favor, insira o código aqui para entrar:</label>										
						</div>
						<div class="large-9 medium-9 small-12 float-right">
							<input name="authCode" id="authCode" class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" value=""/>
						</div>
						<div class="clear"></div>
					</div>
					<div class="large-6 medium-6 small-12 float-right">						
						<button type="submit" style="color: <? echo $colours[0]; ?>;" class="font-weight-500 border-none w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">
							<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">
								<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
								<i class="fas fa-key pointer z-index-1 fs-c centered"></i>
							</div>
							<a class="font-weight-600 cm-pad-10-l">Entrar</a>					
						</button>
					</div>
					<div class="clear"></div>				
					</form>
				</div>
			</div>
			<?
			exit;
		}elseif(!empty($user_pass[0]['ty']) && isset($_POST['authCode'])){
			// Obtém o timestamp atual
			$timestampAuth = explode('|', $user_pass[0]['ty'])[0];
			$timestampAtual = time();
			// Calcula o limite inferior e superior do intervalo de 3 minutos
			$limiteInferior = $timestampAuth;
			$limiteSuperior = $timestampAuth + (3 * 60);
			// Verifica se o $timestampLogin está dentro do intervalo
			if($timestampAtual >= $limiteInferior && $timestampAtual <= $limiteSuperior){
				// O $timestampLogin está dentro do intervalo de 3 minutos
				if(explode('|', $user_pass[0]['ty'])[1] == $_POST['authCode']){
					$access = true;
				}else{
					$access = false;
					update('app', 'wa0004_keys', "ty = ''", "us = '".$_SESSION['wz']."'");
				}			
			}else{
				// O $timestampLogin não está dentro do intervalo de 3 minutos			
				$access = false;
				update('app', 'wa0004_keys', "ty = ''", "us = '".$_SESSION['wz']."'");
			}
		}			
	}elseif($user_pass[0]['s1'] == 'false' || empty($user_pass[0]['s1'])){
		$access = true;		
	}
	if($access == true){
	update('app', 'wa0004_keys', "ty = ''", "us = '".$_SESSION['wz']."'");
	?>
	<div style="height: calc(100% - 121.82px)" class="large-12 medium-12 small-12 overflow-y-auto overflow-x-hidden content position-relative cm-pad-30-b">
		<div name="opt1" class="view ease-all-2s clear">
			<div class="display-center-general-container cm-pad-20 cm-pad-30-b">
				<div style="height: 70px; width: 70px; color: <? echo $colours[0]; ?>; " class="text-center position-relative display-center-general-container fs-f float-left display-block">
					<div class="position-absolute abs-t-0 abs-l-0 w-rounded-20 height-100 large-12 medium-12 small-12 background-white w-shadow z-index-0" style="transform: rotate(20deg);"></div>
					<i class="fas fa-lock fs-g pointer cm-pad-5-t z-index-1 centered"></i>
				</div>
				<h3 class="display-block cm-mg-15-l" style="color: <? echo $colours[0]; ?>;">Senhas</h3>
			</div>
			<div class="large-10 medium-12 small-12 centered clear" id="tabelaSenhas">
			</div>
		</div>			
		<div name="opt2" class="cm-pad-10 cm-pad-30-b display-none ease-all-2s opacity-0">
			<div class="display-center-general-container cm-pad-10 cm-pad-20-b">
				<div style="height: 70px; width: 70px; color: <? echo $colours[0]; ?>; " class="text-center position-relative display-center-general-container fs-f float-left display-block">
					<div class="position-absolute abs-t-0 abs-l-0 w-rounded-20 height-100 large-12 medium-12 small-12 background-white w-shadow z-index-0" style="transform: rotate(20deg);"></div>
					<i class="fas fa-plus fs-g pointer cm-pad-5-t z-index-1 centered"></i>
				</div>
				<h3 class="display-block cm-mg-15-l" style="color: <? echo $colours[0]; ?>;">Adicionar</h3>
			</div>			
			<div class="cm-mg-30-t position-relative centered large-10 medium-12 small-12 fs-e cm-pad-15 background-white w-rounded-30 w-shadow-1 cm-mg-20-t cm-pad-30" style="color: <? echo $colours[0]; ?>">
				<div style="height: 40px; width: 40px; top: -15px" class="text-center position-absolute abs-l-30 display-center-general-container fs-f display-block white"><div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white w-shadow-2 z-index-0" style="transform: rotate(20deg); background: <? echo $colours[0]; ?>;"></div><i class="fas fa-key pointer z-index-1 centered"></i></div>
				<form id="formSenha " method="POST" class="cm-mg-25-t">
					<div class="large-12 medium-12 small-12 cm-mg-20-b">
						<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
							<label>Nome/Descrição</label>										
						</div>
						<div class="large-9 medium-9 small-12 float-right">
							<textarea class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="text" name="descricao" id="descricao" required></textarea>
						</div>
						<div class="clear"></div>
					</div>
					<div class="large-12 medium-12 small-12 cm-mg-20-b">
						<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
							<label>Site (URL)</label>										
						</div>
						<div class="large-9 medium-9 small-12 float-right">
							<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="text" name="site" id="site" >
						</div>
						<div class="clear"></div>
					</div>
					<div class="large-12 medium-12 small-12 cm-mg-20-b">
						<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
							<label>Login</label>										
						</div>
						<div class="large-9 medium-9 small-12 float-right">
							<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="text" name="usuario" id="usuario" required>
						</div>
						<div class="clear"></div>
					</div>
					<div class="large-12 medium-12 small-12 cm-mg-30-b">
						<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
							<label>Senha</label>										
						</div>
						<div class="large-9 medium-9 small-12 float-right">
							<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="password" name="senha" id="senha" required>
						</div>
						<div class="clear"></div>
					</div>							
					<div class="large-6 medium-6 small-12 float-right">						
						<div onclick="submitForm(this)" style="color: <? echo $colours[0]; ?>;" class="w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">
								<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">		
								
								<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
								<i class="fas fa-plus pointer z-index-1 fs-c centered"></i>
							</div>
							<a class="font-weight-500 cm-pad-10-l">Adicionar</a>					
						</div>
					</div>						
					<div class="clear"></div>
				</form>
			</div>
		</div>
		<div name="opt3" class="cm-pad-10 cm-pad-30-b display-none ease-all-2s opacity-0">		
			<div class="display-center-general-container cm-pad-10 cm-pad-30-b">
				<div style="height: 70px; width: 70px; color: <? echo $colours[0]; ?>; " class="text-center position-relative display-center-general-container fs-f float-left display-block">
					<div class="position-absolute abs-t-0 abs-l-0 w-rounded-20 height-100 large-12 medium-12 small-12 background-white w-shadow z-index-0" style="transform: rotate(20deg);"></div>
					<i class="fas fa-magic fs-g pointer cm-pad-5-t z-index-1 centered"></i>
				</div>
				<h3 class="display-block cm-mg-15-l" style="color: <? echo $colours[0]; ?>;">Gerar Senha</h3>
			</div>
			<div class="cm-mg-30-t position-relative centered large-10 medium-12 small-12 fs-e cm-pad-15 background-white w-rounded-30 w-shadow-1 cm-mg-20-t cm-pad-30" style="color: <? echo $colours[0]; ?>">
				<div style="height: 40px; width: 40px; top: -15px" class="text-center position-absolute abs-l-30 display-center-general-container fs-f display-block white"><div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white w-shadow-2 z-index-0" style="transform: rotate(20deg); background: <? echo $colours[0]; ?>;"></div><i class="fas fa-key pointer z-index-1 centered"></i></div>
				<div class="large-12 medium-12 small-12 cm-mg-20-b cm-mg-25-t">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
						<label>Nº de caracteres</label>										
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required" type="number" id="password_length" value="12" min="6" max="30"/><br>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-12 medium-12 small-12 cm-mg-30-b">
					<div class="large-3 medium-3 small-12 float-left cm-pad-20-r fs-c">			
						<label>Senha gerada:</label>										
					</div>
					<div class="large-9 medium-9 small-12 float-right">
						<input id="password_response" onclick="copiarParaClipboard(this)" class="w-rounded-10 input-border border-like-input large-12 medium-12 small-12 cm-pad-10 required"/><br>
					</div>
					<div class="clear"></div>
				</div>
				<div class="large-6 medium-6 small-12 float-right">						
					<div onclick="gerarSenhaAleatoria(document.getElementById('password_length').value)" style="color: <? echo $colours[0]; ?>;" class="w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">
						<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">
							<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>
							<i class="fas fa-magic pointer z-index-1 fs-c centered"></i>
						</div>
						<a class="font-weight-500 cm-pad-10-l">Gerar Senha</a>					
					</div>
				</div>
				<div class="clear"></div>
			</div>						
		</div>
	</div>		
	<div style="" class="abs-b-0 position-fixed cm-pad-20-h cm-pad-30-b large-12 medium-12 small-12">
		<div class="w-rounded-30 cm-pad-5 large-12 medium-12 small-12 w-shadow text-center fs-f background-white display-center-general-container">
			<div class="centered clear">
				<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
					<i class="fas fa-lock fs-g pointer cm-pad-5-t" id="opt1" onclick="option(this)"></i><br/>
					<a class="fs-a">Senhas</a>
				</div>
				<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
					<i class="fas fa-plus-circle fs-g pointer cm-pad-5-t" id="opt2" onclick="option(this)"></i><br/>
					<a class="fs-a">Adicionar</a>
				</div>
				<div class="float-left text-center cm-pad-15 line-height-a w-color-gr-to-gr">
					<i class="fas fa-magic fs-g pointer cm-pad-5-t" id="opt3" onclick="option(this)"></i><br/>
					<a class="fs-a">Gerar</a>
				</div>
				<div class="clear"></div>
			</div>
		</div>
	</div>
	<script>	
		$(document).on('change', '.onoffswitch-checkbox', function () {
			var el = $(this).attr('id');
			var elementoId = el.split('-')[1];
			var estado = $(this).prop('checked');

			if(el.split('-')[0] == 'option'){		
				$.ajax({
				url: 'https://workz.com.br/app/core/backengine/wa0004/senhas.php',
				type: 'POST',
				dataType: 'json',
				data: {
					fnc: 'options',
					valor: estado,
					opcao: 's' + elementoId,
					wz: <? echo $_SESSION['wz']; ?>
				},
				success: function(response){				
					sAlert('', 'O efeito da alteração será refletido após o próximo processo de autenticação: ' + response, '', '');
				}
			  });		  
			}else if(el.split('-')[0] == 'switch'){
				
				var div = document.querySelector('[id="' + elementoId + '"]');		
				if (estado == true) {
					var inputs = div.querySelectorAll("input[onclick='']");
					for (var i = 0; i < inputs.length; i++) {
						var novoValorOnclick = 'copiarParaClipboard(this)';
						inputs[i].setAttribute("onclick", novoValorOnclick);
					}
				} else {
					var inputs = div.querySelectorAll("input[onclick='copiarParaClipboard(this)']");
					for (var i = 0; i < inputs.length; i++) {
						var novoValorOnclick = '';
						inputs[i].setAttribute("onclick", novoValorOnclick);
					}
				}
			}					
		});
		function option(el){
			var div = document.getElementsByName(el.id)[0];
			var view = document.getElementsByClassName('view')[0]

			if(div.classList.contains('display-none')){
				view.classList.add('opacity-0');				
				div.classList.remove('display-none');
				setTimeout(() => {
					view.classList.add('display-none');
					view.classList.remove('view');
					div.classList.remove('opacity-0');
					div.classList.add('view');
				}, 200);
			}
		}
		function gerarSenhaAleatoria(comprimento) {
			var response = document.getElementById('password_response');
			var caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-={}[]|:;"<>,.?/';
			var senha = '';
			for (var i = 0; i < comprimento; i++) {
				var indice = Math.floor(Math.random() * caracteres.length);
				senha += caracteres.charAt(indice);
			}
			response.value = senha;
		}
		function carregarSenhas() {
			$.ajax({
			url: 'https://workz.com.br/app/core/backengine/wa0004/senhas.php',
			type: 'POST',
			dataType: 'json',
			data: {
				fnc: 'load',
				wz: <? echo $_SESSION['wz']; ?>
			},
			success: function(data){
				var tabela = $('#tabelaSenhas');
				tabela.html('');			
				if(data.length == 0){					
					var divA = $('<div class="large-12 medium-12 small-12 text-center white cm-pad-15 cm-pad-30-b" style="color: <? echo $colours[0]; ?>;"><h3>Bem-vindo ao SenhaSegura!</h3><h3>Proteja e organize suas senhas com tranquilidade e praticidade.</h3></div>');
					var buttonA = $('<div class="large-6 medium-6 small-12 centered cm-mg-30-t">'+
										'<div onclick="document.getElementById(`opt2`).click();" style="color: <? echo $colours[0]; ?>;" class="w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">'+
											'<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">'+
												'<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);">'+'</div>'+
												'<i class="fas fa-play pointer z-index-1 fs-c centered">'+'</i>'+
											'</div>'+
											'<a class="font-weight-500 cm-pad-10-l font-weight-500">Comece agora!</a>'+
										'</div>'+
									'</div>');
					tabela.append(divA.append(buttonA));
				}else{
					for(const property in data){
						var line = data[property];				
						var divA = $('<div class="position-relative large-4 medium-6 small-12 float-left cm-pad-15">');
						var divB = $('<div id="' + property + '" class="cm-pad-15 cm-mg-15-t w-rounded-30 background-white w-shadow fs-e position-relative">');
						var divC = $('<div id="' + property + '_main" class="cm-pad-5">');
						var icon = $('<div style="height: 40px; width: 40px; top: -15px" class="text-center position-absolute abs-l-30 display-center-general-container fs-f display-block white"><div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white w-shadow-2 z-index-0" style="transform: rotate(20deg); background: <? echo $colours[0]; ?>;"></div><i class="fas fa-key pointer z-index-1 centered"></i></div>');
						divB.append(icon);
						for(var reg in line){
							
							if(`${reg}` == 'descricao'){
								var inputA = $('<input style="color: #1A4C71" id="' + property + '_descricao" class="cm-mg-25-t font-weight-500 w-rounded-10 input-border border-none large-12 medium-12 small-12 cm-pad-10">').attr('name', `${reg}`).val(`${line[reg]}`);
								divB.append(inputA);											
							}else if (`${reg}` == 'site'){
								var inputB = $('<div class="large-12 medium-12 small-12 cm-pad-5 clear centered-v fs-c">'+
											'<label for="' + property + '_site" style="height: 25px; width: 25px; background: #8AC8E5;" class="white w-circle text-center centered-v float-left">'+
											'<i class="fas fa-globe-americas"></i>'+
											'</label>'+
											'<input onclick="copiarParaClipboard(this)" id="' + property + '_site" style="color: #1A4C71; width: calc(100% - 30px)" class="cm-mg-5-l w-rounded-10 input-border border-none large-12 medium-12 small-12 cm-pad-10 cm-pad-5-h" value="' + line[reg] + '"/>'+
											'</div>');
								divC.append(inputB);
							}else if (`${reg}` == 'usuario') {
								var inputC = $('<div class="large-12 medium-12 small-12 cm-pad-5 clear centered-v fs-c">'+
											'<label for="' + property + '_usuario" style="height: 25px; width: 25px; background: #8AC8E5;" class="white w-circle text-center centered-v float-left">'+
											'<i class="fas fa-user"></i>'+
											'</label>'+
											'<input onclick="copiarParaClipboard(this)" id="' + property + '_usuario" style="color: #1A4C71; width: calc(100% - 30px)" class="cm-mg-5-l w-rounded-10 input-border border-none large-12 medium-12 small-12 cm-pad-10 cm-pad-5-h" value="' + line[reg] + '"/>'+
											'</div>');
								divC.append(inputC);
							}else if (`${reg}` == 'senha'){
								var inputD = $('<div class="large-12 medium-12 small-12 cm-pad-5 clear centered-v fs-c">'+
											'<label for="' + property + '_senha" style="height: 25px; width: 25px; background: #8AC8E5;" class="white w-circle text-center centered-v float-left">'+
											'<i class="fas fa-ellipsis-h"></i>'+
											'</label>'+
											'<input onclick="copiarParaClipboard(this)" id="' + property + '_senha" type="password" style="color: #1A4C71; width: calc(100% - 60px)" class="cm-mg-5-l cm-mg-5-r w-rounded-10 input-border border-none cm-pad-10 cm-pad-10-h"/>'+
											'<a style="height: 25px; width: 25px;" class="fas fa-eye cm-pad-5 w-all-bl-to-or pointer w-circle floar-right" onclick="showPassword(this)"></a>'+
											'</div>');
								divC.append(inputD);
								inputD.find('input[type="password"]').val(line[reg]);
							}else if (`${reg}` == 'id'){
								var inputE = $('<input id="' + property + '_id" type="hidden" value="' + line[reg] + '">');
								divC.append(inputE);
							}
							
							var divE = $('<div id="' + property + '_shared" class="display-none cm-pad-5">'+
								'<a onclick="sharePassword('+ property +')">Voltar</a>'+								
								'<input onkeyup="searchUser(this.value)" id="' + property + '_sharedInput" type="text" style="color: #1A4C71;" class="large-12 medium-12 small-12 background-gray w-rounded-10 input-border border-none cm-pad-10 cm-pad-10-h" placeholder="Buscar usuário"/>'+
								'<div id="' + property + '_response"></div>'+
							'</div>');												
							
						}
						var divD = $('<div class="cm-pad-10-t cm-pad-15-b large-12 medium-12 small-12 clear">');
						var buttonA = $('<div class="float-left large-6 medium-6 small-6 cm-pad-5">'+
										'<div onclick="confirmarSalvamento(this)" style="color: <? echo $colours[0]; ?>;" class="w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">'+
										'<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block white">'+
										'<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>'+
										'<i class="fas fa-pen pointer z-index-1 fs-c centered"></i>'+
										'</div>'+
										'<a class="font-weight-500 cm-pad-10-l display-block ">Salvar</a>'+
										'</div>'+
										'</div>');
						var buttonB = $('<div class="float-left large-6 medium-6 small-6 cm-pad-5">'+
										'<div onclick="confirmarExclusao(this)" style="background: <? echo $colours[0]; ?>;" class="white w-bkg-tr-gray display-center-general-container cm-pad-10 large-12 medium-12 small-12 text-center w-rounded-30 w-shadow-1 pointer fs-c">'+
										'<div style="height: 30px; width: 30px; color: <? echo $colours[0]; ?>;" class="text-center display-center-general-container fs-b float-left display-block">'+
										'<div class="position-absolute abs-t-0 abs-l-0 w-rounded-10 height-100 large-12 medium-12 small-12 background-white z-index-0 w-shadow" style="transform: rotate(20deg);"></div>'+
										'<i class="fas fa-trash pointer z-index-1 fs-c centered"></i>'+
										'</div>'+
										'<a class="font-weight-500 cm-pad-10-l">Excluir</a>'+
										'<div class="clear"></div>'+
										'</div>'+
										'</div>');
						var clear = $('<div class="clear">');
						divD.append(buttonA, buttonB, clear);
						var sectionA = $('<div class="large-12 medium-12 small-12 cm-pad-10-h cm-pad-10-t border-t-input fs-c display-flex ">'+
										'<a class="align-left">Copiar ao clicar</a>'+
										'<div class="onoffswitch align-right">'+
										'<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="switch-' + property + '" tabindex="0" checked>'+
										'<label class="onoffswitch-label" for="switch-' + property + '">'+'</label>'+
										'</div>'+
										'<div class="clear"></div>'+										
										'</div>'+
										'<?if($_SESSION['wz'] == 1){?><div class="large-12 medium-12 small-12 cm-pad-10 fs-c display-flex"><a onclick="sharePassword('+ property +')" class="w-color-or-to-bl pointer">Compartilhar</a></div><?}?>'
										);
						divC.append(divD, sectionA);
						divB.append(divC);
						divB.append(divE);
						divA.append(divB);
						tabela.append(divA);				
					}
				}
				
			},
			error: function(jqXHR, textStatus, errorThrown) {
				console.log("Erro na requisição AJAX:", textStatus, errorThrown);
				console.log("Resposta do servidor:", jqXHR.responseText); // Aqui está a resposta completa do servidor
			}
			});
		}
		function sharePassword(id){											
						
			// Declare um array com os dois elementos
			const elementos = [id + '_shared', id + '_main'];

			// Itere sobre os elementos do array
			elementos.forEach(elemento => {
				
				elemento = document.getElementById(elemento);
				
				
				// Verifica se a classe "display-none" está definida
				if (elemento.classList.contains('display-none')) {
					// Se estiver, remove a classe para tornar o elemento visível
					elemento.classList.remove('display-none');
				} else {
					// Caso contrário, adiciona a classe para ocultar o elemento
					elemento.classList.add('display-none');
				}
				
			});			
		}
		function showPassword(el){
			var parent = el.parentNode.parentNode.parentNode;			
			var password = document.getElementById(parent.id + '_senha');		
			if(password.type === 'password'){
				el.classList.remove('fa-eye');
				el.classList.add('fa-eye-slash');							
				password.setAttribute('type', 'text');
			}else{
				el.classList.add('fa-eye');
				el.classList.remove('fa-eye-slash');				
				password.setAttribute('type', 'password');
			}		
		}
		function saveChanges(el){
			var parent = el.parentNode.parentNode.parentNode.parentNode;	
			$.ajax({
			url: 'https://workz.com.br/app/core/backengine/wa0004/senhas.php',
			type: 'POST',
			data: {
				fnc: 'update',
				descricao: document.getElementById(parent.id + '_descricao').value,
				site: document.getElementById(parent.id + '_site').value,
				usuario: document.getElementById(parent.id + '_usuario').value,
				senha: document.getElementById(parent.id + '_senha').value,
				id: document.getElementById(parent.id + '_id').value,
				wz: <? echo $_SESSION['wz']; ?>
			},
			success: function(response){
				carregarSenhas();
			}
			});	  
		}
		function del(el){
			var parent = el.parentNode.parentNode.parentNode.parentNode;			
			$.ajax({
			url: 'https://workz.com.br/app/core/backengine/wa0004/senhas.php',
			type: 'POST',
			data: {
				fnc: 'delete',
				id: document.getElementById(parent.id + '_id').value
			},
			success: function(response){			
				carregarSenhas();
			}
			});
		}
		function confirmarExclusao(el) {
			// Mensagem de confirmação antes da exclusão
			sAlert(function() { del(el) }, "Tem certeza que deseja excluir o registro?", "Registro excluído com sucesso!", "Exclusão cancelada.");
		}
		function confirmarSalvamento(el) {
			// Mensagem de confirmação antes de salvar
			sAlert(function() { saveChanges(el) }, "Deseja salvar as alterações?", "Registro salvo com sucesso!", "As alterações não foram salvas.");
		}
		function submitForm(el){
			var form = $(el).closest('form');		
			// Verifica a validade dos campos do formulário
			if (form[0].checkValidity()) {
				var descricao = form.find('#descricao').val();
				var site = form.find('#site').val();
				var usuario = form.find('#usuario').val();
				var senha = form.find('#senha').val();       
				$.ajax({
					url: 'https://workz.com.br/app/core/backengine/wa0004/senhas.php',
					type: 'POST',
					data: {
						fnc: 'insert',
						descricao: descricao,
						site: site,
						usuario: usuario,
						senha: senha,
						wz: <? echo $_SESSION['wz']; ?>,
						<?php echo session_name(); ?>: '<?php echo session_id(); ?>' // Adicione esta linha
					},
					success: function (response) {
						form.trigger('reset');
						carregarSenhas();
						document.getElementById("opt1").click();
					}
				});
			} else {
				// Caso os campos não sejam válidos, exiba uma mensagem de erro ou tome outra ação aqui			
				sAlert('', 'Por favor, preencha todos os campos obrigatórios.', '', '');
			}
		}
		$(document).ready(function () {
			carregarSenhas();
		});
		function addToHomeScreen() {
			if (window.navigator.standalone === true) {
				// O site já está na tela inicial
				return;
			}

			var addToHomeConfig = {
				autostart: false,
				startDelay: 0,
				lifespan: 0,
				touchIcon: true,
				message: 'Toque em "Compartilhar" e selecione "Adicionar à Tela de Início".'
			};

			addToHomescreen(addToHomeConfig);
		}		
		function copiarParaClipboard(el){
			// Verifica se o input é do tipo texto
			if (el.type === 'text') {
				// Seleciona o texto dentro do input
				el.select();
				try {
					// Copia o texto selecionado para o clipboard
					document.execCommand('copy');
					//alert('Copiado para o clipboard!');
					sAlert('', 'Copiado para área de transferência', '', '');
				} catch (err) {
					console.error('Erro ao copiar o texto:', err);				
					sAlert('', 'Ocorreu um erro ao copiar o texto para  área de transferência.', '', '');
				}
			}
		}
		<?
		if($user_pass[0]['s2'] == 'true'){
		?>
		var inactivityTimeout;
		function resetInactivityTimer(){
			clearTimeout(inactivityTimeout);
			inactivityTimeout = setTimeout(handleInactivity, 10000); // 5000 milissegundos = 5 segundos (ajuste o valor conforme necessário)
		}
		function handleInactivity() {
			// Coloque aqui o código a ser executado quando o usuário ficar inativo
			window.location.href = "https://app.workz.com.br/protspot/logout.php/?app=senhas";			
		}
		// Registrar eventos de interação do usuário
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				resetInactivityTimer();
			} else {
				handleInactivity();
			}
		});
		<?	
		}
		?>
		document.addEventListener("DOMContentLoaded", appOptions);
		function appOptions() {
			$('#appOptions').removeClass('display-none').html(
			'<div class="w-shadow background-white w-rounded-15">'+
				'<div class="large-12 medium-12 small-12 cm-pad-10 cm-pad-5-l display-flex">'+
					'<span class="fa-stack orange" style="vertical-align: middle;">'+
						'<i class="fas fa-square fa-stack-2x light-gray"></i>'+
						'<i class="fas fa-lock fa-stack-1x fa-inverse dark"></i>'+
					'</span>'+
					'<a class="align-left text-ellipsis cm-pad-5-h">Autenticação em 2 fatores (e-mail)</a>'+
					'<div class="onoffswitch align-right">'+
						'<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="option-1" tabindex="0" <?if($user_pass[0]['s1'] == 'true'){?> checked <?}?>>'+
						'<label class="onoffswitch-label" for="option-1"></label>'+
					'</div>'+
					'<div class="clear"></div>'+
				'</div>'+
				'<div class="large-12 medium-12 small-12 cm-pad-10 cm-pad-5-l border-t-input display-flex ">'+
					'<span class="fa-stack orange" style="vertical-align: middle;">'+
						'<i class="fas fa-square fa-stack-2x light-gray"></i>'+
						'<i class="far fa-clock fa-stack-1x fa-inverse dark"></i>'+
					'</span>'+
					'<a class="align-left text-ellipsis cm-pad-5-h">Logout automático ao perder o foco</a>'+
					'<div class="onoffswitch align-right">'+
						'<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="option-2" tabindex="0" <?if($user_pass[0]['s2'] == 'true'){?> checked <?}?>>'+
						'<label class="onoffswitch-label" for="option-2"></label>'+
					'</div>'+
					'<div class="clear">'+'</div>'+
				'</div>'+
			'</div>');
		}
	</script>
	<?
	}else{
		echo 'Não foi possível acessar o aplicativo.';
		header("Location: ".$url);
	}
}else{
	echo 'Usuário não encontrado.';
}
	?>
</div>