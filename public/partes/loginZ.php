<?php
$errors = []; // Array para armazenar mensagens de erro
$newMl = null;

if(isset($_POST['vr'])) {	
	if(!empty($_POST['vr'])){
		 $decoded = json_decode($_POST['vr'], true);
		// Verifica se a decodificação foi bem-sucedida
		if (json_last_error() === JSON_ERROR_NONE) {								

			session_set_cookie_params([
				'lifetime' => 0,  // Sessão dura até o navegador ser fechado
				'path' => '/',
				'domain' => '.workz.com.br', // Inclui todos os subdomínios
				'secure' => true,  // Requer HTTPS
				'httponly' => true,  // Impede acesso via JavaScript
				'samesite' => 'None'  // Permite compartilhamento entre subdomínios
			]);

			session_start();
			
			require_once('../functions/search.php');
			require_once('../functions/insert.php');
			require_once('../functions/update.php');
			require_once('../functions/strongPassword.php');

			// Sanitiza e valida o e-mail
			$ml = filter_var($decoded['user_mail'], FILTER_SANITIZE_EMAIL);
			if (!filter_var($ml, FILTER_VALIDATE_EMAIL)) {
				$errors[] = "E-mail inválido.";
			} else {								
				$pw = $decoded['user_pass'];
				$renew = $decoded['renew'] ?? null;
				$pass_repeat = $decoded['pass_repeat'] ?? null;	
				
				$hus = search('hnw', 'hus', 'id,pw', "ml = '{$ml}'");
				
				// Login de usuário existente
				if ($hus && is_array($hus) && !$renew) {
					if (password_verify($pw, $hus[0]['pw'])) {                    						
						$_SESSION['wz'] = $hus[0]['id'];
						$_SESSION['id'] = ''; // Sessão com a ProtSpot ID do seu usuário
						$_SESSION['geolocation'] = $decoded['geolocation'] ?? '';   
						// Indica sucesso com um elemento HTML e também entra nos apps
						$session_data = json_encode([
							"status" => isset($_SESSION['wz']) ? "success" : "error",
							"user_id" => $_SESSION['wz'] ?? null,
							"geolocation" => $_SESSION['geolocation']
						]);
						?><script>goPost('http://localhost:9090/app/workauth.php', 'login', <?= $session_data ?>, '');</script><div id="login-success"></div><?php
						exit();
					} else {
						$errors[] = "Senha inválida.";
					}
				} else {
					$newMl = $ml;
				}

				// Redefinição ou criação
				if (($renew || $pass_repeat) && empty($errors)) {
					// Se não houver erros, processa a redefinição ou criação
					
					$hashed_password = password_hash($pw, PASSWORD_DEFAULT);
					$geo = $decoded['geolocation'] ?? '';

					if ($renew) { // Redefinição de senha
						if ($cdu = search('hnw', 'hud', '', "us = {$hus[0]['id']} AND tp = 0 ORDER BY t0 DESC LIMIT 1")) {
							if ($renew === $cdu[0]['ty']) { // Código confere
								if (update('hnw', 'hus', "pw = '{$hashed_password}'", "ml = '{$ml}'")) {                                									
									$_SESSION['wz'] = $hus[0]['id'];
									$_SESSION['id'] = ''; // Sessão com a ProtSpot ID do seu usuário
									$_SESSION['geolocation'] = $geo;
									// Indica sucesso com um elemento HTML e também entra nos apps
									$session_data = json_encode([
										"status" => isset($_SESSION['wz']) ? "success" : "error",
										"user_id" => $_SESSION['wz'] ?? null,
										"geolocation" => $_SESSION['geolocation']
									]);
									?><script>goPost('http://localhost:9090/app/workauth.php', 'login', <?= $session_data ?>, '');</script><div id="login-success"></div><?php
									exit();
								} else {
									$errors[] = "Erro ao alterar a senha.";
								}
							} else {
								$errors[] = "Erro ao conferir o código durante a revalidação.";
							}
						} else {
							$errors[] = "Erro ao revalidar o código.";
						}
					} else { // Criação de novo usuário
						if ($hus = insert('hnw', 'hus', 'ml,pw', "'{$ml}','{$hashed_password}'")) {							
							$_SESSION['wz'] = $hus;
							$_SESSION['id'] = ''; // Sessão com a ProtSpot ID do seu usuário
							$_SESSION['geolocation'] = $geo;
							// Indica sucesso com um elemento HTML e também entra nos apps
							$session_data = json_encode([
								"status" => isset($_SESSION['wz']) ? "success" : "error",
								"user_id" => $_SESSION['wz'] ?? null,
								"geolocation" => $_SESSION['geolocation']
							]);
							?><script>goPost('http://localhost:9090/app/workauth.php', 'login', <?= $session_data ?>, '');</script><div id="login-success"></div><?php
							exit();
						} else {
							$errors[] = "Erro ao cadastrar usuário.";
						}
					}
					
				}
			}
		} else {
			$errors[] = "Erro ao decodificar JSON: " . json_last_error_msg();
		}	
	}else{
		$newMl = '';
	}
}elseif(isset($_GET['vr'])){
	
	if(!empty($_GET['vr'])){
		$resetMl = $_GET['vr'];
		include($_SERVER['DOCUMENT_ROOT'] . '/renew_password.php');
	}else{
		$resetMl = '';
	}
		
}elseif(isset($_GET['cf'])){
	if(!empty($_GET['cf'])){		
		if(isset($_GET['ml']) && !empty($_GET['ml'])){
			require_once('../functions/search.php');			
			$ml = filter_var($_GET['ml'], FILTER_SANITIZE_EMAIL);
			if (!filter_var($ml, FILTER_VALIDATE_EMAIL)) {
				$errors[] = "E-mail inválido.";
			} else {
				if ($hus = search('hnw', 'hus', 'id', "ml = '{$ml}'")) {
					if($cdu = search('hnw','hud','',"us = {$hus[0]['id']} AND tp = 0 ORDER BY t0 DESC LIMIT 1")){					
						$providedTimestamp = $cdu[0]['t0']; // Timestamp fornecido
						$currentTimestamp = time(); // Timestamp atual
						$fiveMinutesAgo = $currentTimestamp - (5 * 60); // Timestamp de 5 minutos atrás																		
						if ($providedTimestamp > $fiveMinutesAgo) {
							if($_GET['cf'] === $cdu[0]['ty']){
								$renew = $cdu[0]['ty'];
								$newMl = $ml;
							}else{
								$errors[] = "Os códigos não conferem. Por favor, tente novamente.";
							}	
						} else {						
							$errors[] = "O código utilizado excedeu o limite de uso de 5 minutos. Por favor, tente novamente.";
						}																	
					}else{
						$errors[] = "Erro ao buscar o registro de código enviado.";
					}										
				}else{
					$errors[] = "Erro ao buscar o usuário.";
				}
			}
		}else{			
			echo 'Erro, por favor entre em contato com o nosso suporte.';
		}		
	}else{
		echo 'Insira o código enviado por e-mail no campo indicado.';
	}
	
}elseif(isset($_GET['bk'])){
	if($_GET['bk'] == 'init'){
		echo '<div id="login-success"></div>';
	}
}

// Exibe as mensagens de erro, se houver
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "<p class='error-message'>{$error}</p>";
    }
}
?>

<div id="loginZ" class="form-group">
	<?php
	if(isset($newMl)){
		?>				
		<div class="large-12 medium-12 small-12 text-ellipsis fs-e cm-pad-15-b">
			<div onclick="goTo('http://localhost:9090/partes/loginZ.php', 'login', '&bk=init', '');" class="display-center-general-container w-color-wh-to-or pointer">				
				<i class="fas fa-chevron-left fs-f cm-mg-10-r"></i>
				<a>Voltar</a>				
			</div>
		</div>		
		<?php
	}
	?>
	<label for="user_mail">E-mail</label>
	<?php
	$emailValue = isset($resetMl) ? $resetMl : (isset($newMl) ? $newMl : '');
	$isDisabled = (isset($newMl) && !empty($newMl)) || (isset($resetMl) && !empty($resetMl));
	?>
	<input
		class="cm-mg-5-t w-rounded-30 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-none required background-white w-shadow-1"
		type="email"
		name="user_mail"
		id="user_mail"
		placeholder="E-mail"
		value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
		<?= $isDisabled ? 'disabled' : '' ?>
	>
	
	<?php if(isset($resetMl) || (!isset($resetMl) && !empty($resetMl))){ ?>
	<label for="user_mail">Insira o código enviado para o seu e-mail</label>
	<input class="cm-mg-5-t w-rounded-30 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-none required background-white w-shadow-1" type="number" name="reset_code" id="reset_code" placeholder="Código">	
	<input onclick="codeCheck()" type="submit" class="w-rounded-30 cm-pad-15 large-12 medium-12 small-12 font-weight-500 pointer w-all-or-to-bl w-shadow border-none w-shadow-1 fs-e" value="Validar código" title="Validar código"></input>
	<?php	
	}
	if(!isset($resetMl)){ ?>
	<label for="user_pass">Senha</label>								
	<input class="cm-mg-5-t w-rounded-30 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-none required background-white w-shadow-1" type="password" style="width: 100%;" name="user_pass" id="user_pass" placeholder="Senha">
	<?php if(isset($newMl)){?> 
	<label for="pass_repeat">Repita a Senha</label>
	<input class="cm-mg-5-t w-rounded-30 cm-pad-15-h cm-pad-15-t cm-pad-15-b large-12 medium-12 small-12 cm-mg-20-b border-none required background-white w-shadow-1" type="password" style="width: 100%;" name="pass_repeat" id="pass_repeat" placeholder="Repita a Senha">
	<?php 
	if(isset($renew)){		
	?>
	<input id="renew" name="renew" type="hidden" value="<?php echo $renew; ?>"></input>
	<?php
	}	
	}; ?>
	<input type="hidden" id="geolocation" name="geolocation"></input>		
	<script>
	if (navigator.geolocation){
		navigator.geolocation.getCurrentPosition(function(position){
		var pos = {
			lat: position.coords.latitude,
			lng: position.coords.longitude
		};
		document.getElementById("geolocation").value = pos.lat + ";" + pos.lng;					
	  });
	}
	</script>
	<p class="text-right cm-mg-5-b"><?php if(!isset($newMl)){?><a class="pointer" onclick="resetPassword()">Esqueci minha senha</a><?php }else{ echo '<p class="text-left fs-c cm-mg-10-b">Ao se inscrever, você concorda com os Termos de Serviço e a Política de Privacidade, incluindo o Uso de Cookies.</p>'; }; ?></p>		
	<div class="large-12 medium-12 small-12 clearfix display-center-general-container">
		<!-- BOTÃO ENTRAR -->
		<div style="height: 40px; width: 100%;" onclick="loginSubmit()" type="submit" class="w-rounded-30 w-shadow-1 font-weight-500 pointer w-all-or-to-bl border-none fs-e input-shadow display-center-general-container">
			<a class="centered"><?= (isset($newMl)) ? ( (isset($renew)) ? 'Atualizar senha' : 'Criar conta' ) : 'Entrar / Criar conta' ?></a>
		</div>
	</div>
	<?php } ?>
</div>
<?php
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsSHA/2.4.2/sha256.js"></script>
<script>
    function hashPassword(password) {
        let shaObj = new jsSHA("SHA-256", "TEXT");
        shaObj.update(password);
        return shaObj.getHash("HEX");
    }
	function codeCheck(){
		const codeField = document.getElementById("reset_code");
		const mailField = document.getElementById("user_mail");
		
		if(codeField.value !== '' && mailField.value !== ''){
			goTo('http://localhost:9090/partes/loginZ.php', 'login', '&cf=' + codeField.value + '&ml=' + mailField.value, '');
		}else{
			alert('Por favor, preencha o código antes de prosseguir.');
		}
	}
	
	function isStrongPassword(password) {
		const minLength = 8;
		const containsUppercase = /[A-Z]/.test(password);
		const containsLowercase = /[a-z]/.test(password);
		const containsNumber = /[0-9]/.test(password);
		const containsSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password); // Caractere especial
		const containsSpace = /\s/.test(password);

		if (password.length < minLength) {
			return "A senha deve ter pelo menos 8 caracteres.";
		}
		if (!containsUppercase) {
			return "A senha deve conter pelo menos uma letra maiúscula.";
		}
		if (!containsLowercase) {
			return "A senha deve conter pelo menos uma letra minúscula.";
		}
		if (!containsNumber) {
			return "A senha deve conter pelo menos um número.";
		}
		if (!containsSpecial) {
			return "A senha deve conter pelo menos um caractere especial.";
		}
		if (containsSpace) {
			return "A senha não deve conter espaços.";
		}
		return true; // A senha é forte
	}

	
	  function loginSubmit() {
		const passwordField = document.getElementById("user_pass");
		const repeatPasswordField = document.getElementById("pass_repeat");
		const mailField = document.getElementById("user_mail");

		// Verifica se o e-mail ou senha estão vazios
		if (mailField.value === '' && passwordField.value === '') {			
			goPost('http://localhost:9090/partes/loginZ.php', 'login', '', '');
			return;
		}		
		
		// Valida a força da senha
		const validationResult = isStrongPassword(passwordField.value);
		if (validationResult !== true) {
			alert(validationResult); // Mostra o erro específico
			return;
		}

		// Se o campo pass_repeat existir, verifica se as senhas coincidem
		if (repeatPasswordField && passwordField.value !== repeatPasswordField.value) {
			alert("As senhas não coincidem.");
			return;
		}

		// Aplica hash na senha principal
		const hashedPassword = hashPassword(passwordField.value);
		passwordField.value = hashedPassword;

		// Se o campo pass_repeat existir, aplica o hash também
		if (repeatPasswordField) {
			const hashedRepeatPassword = hashPassword(repeatPasswordField.value);
			repeatPasswordField.value = hashedRepeatPassword;
		}

		// Envia os dados usando a função formValidator2
		formValidator2('loginZ', 'http://localhost:9090/partes/loginZ.php', 'login');
	}

	
	function resetPassword(){
		const mailField = document.getElementById("user_mail");		
		goTo('http://localhost:9090/partes/loginZ.php', 'login', '', mailField.value);	
	}
	
	// Função para monitorar o DOM
	function monitorLoginSuccess() {
		const observer = new MutationObserver((mutationsList) => {
			for (const mutation of mutationsList) {
				if (mutation.type === 'childList') {
					// Verifica se o elemento de sucesso foi adicionado
					if (document.getElementById('login-success')) {
						// Realiza transição e recarrega a página
						document.body.style.transition = "opacity 0.7s ease";
						document.body.style.opacity = "0";
						setTimeout(() => {
							window.location.reload();
						}, 700);

						// Para o observer após encontrar o elemento
						observer.disconnect();
						break;
					}
				}
			}
		});

		// Configura o observer para observar alterações no body
		observer.observe(document.body, { childList: true, subtree: true });
	}

	// Chama a função de monitoramento quando a página é carregada
	document.addEventListener('DOMContentLoaded', monitorLoginSuccess);
</script>
<style>
    body {
        transition: opacity 0.7s ease;
    }
</style>
