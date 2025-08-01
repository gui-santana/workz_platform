<?php 	
// Inclui o autoloader do Composer
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Importa as classes do PHPMailer no escopo global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Inicia a sessão
session_start();

// Inclui funções personalizadas
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';

// Define a data e hora atual
date_default_timezone_set('America/Fortaleza');
$now = date('Y-m-d H:i:s');

	if(isset($resetMl)){		
				
		if(filter_var($resetMl, FILTER_VALIDATE_EMAIL) && $user = search('hnw','hus','id,tt',"ml = '".addslashes($resetMl)."'")){
			
			// Gerar um código de verificação
			$codigoVerificacao = mt_rand(100000, 999999);			
			$time = time();
			
			// Incluir solicitação no Banco de Dados
			if(insert('hnw', 'hud', 'us,ty,tp,t0', "{$user[0]['id']},{$codigoVerificacao},0,{$time}")){
				$bd =
				'
				<html>
					<head>
						<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0" />
						<link href="https://fonts.googleapis.com/css?family=Ubuntu&display=swap" rel="stylesheet">
						<style>
							@import url("https://fonts.googleapis.com/css?family=Muli&display=swap");
							body{
								margin: 0;
								font-size: 0.9em;
								padding: 0;
								margin: 0;
								font-family: "Muli", sans-serif;
								font-weight: normal;
								line-height: 1.5;
								color: #2c2c2c;
								background: #fefefe;
								-webkit-font-smoothing: antialiased;
								-moz-osx-font-smoothing: grayscale;
								-webkit-tap-highlight-color: rgba(0, 0, 0, 0);
								-webkit-tap-highlight-color: transparent
							}
							a:link{
								color: #fd5f1e;
							}
							a, h3, small{				
								font-family: "Ubuntu", sans-serif;
							}
							.background-gray{
								background: #F5F5F5;
							}
							.w-rounded-5{
								border-radius: 5px 5px 5px 5px;
							}
							.w-rounded-10{
								border-radius: 20px 20px 20px 20px;
							}
						</style>
					</head>
					<body>
						<div style="width: calc(100% - 40px); padding: 20px;">
							<div style="max-width: 600px; width: calc(100% - 40px); margin: 0 auto; text-align: center; padding: 20px;" class="background-gray w-rounded-10">								
								<img src="https://workz.com.br/images/logos/workz/90x47.png"></img>
								<div style="background-color: #FFF; padding: 20px; margin-top: 20px;" class="w-rounded-10">
									<a>Olá, '.htmlspecialchars($user[0]['tt'], ENT_QUOTES, 'UTF-8').'!</a>								
									<p>Para prosseguir com a alteração de senha solicitada em '.date('d/m/Y H:i').', insira o código abaixo no campo "Código" exibido na tela da Workz!</p>
									<br/>
									<h2><strong>'.$codigoVerificacao.'</strong></h2>									
									<small>Se você não realizou esta solicitação, entre em contato com a equipe de suporte imediatamente. O código é válido por apenas 5 minutos.</small>
									<br/>
									<div style="width: 100%; text-align: center;">
										<small>Workz! © '.date('Y').'</small>
									</div>
								</div>
							</div>
						</div>
					</body>
				</html>
				';//Conteúdo
				
				// Enviar o código de verificação para o e-mail do usuário usando PHPMailer
				$mail = new PHPMailer(true);
				
				//Server settings
				//$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      	 // Enable verbose debug output				
				$mail->CharSet = 'UTF-8';
				$mail->isSMTP();                                           		 // Send using SMTP
				$mail->Host       = 'smtp.hostinger.com';                        // Set the SMTP server to send through
				$mail->SMTPAuth   = true;                                  		 // Enable SMTP authentication
				$mail->Username   = 'noreply@workz.com.br';                   	 // SMTP username
				$mail->Password   = 'Y5PegXgmlL]9';                              // SMTP password
				$mail->SMTPSecure = 'ssl'; // ou 'ssl' se aplicável
				$mail->Port       = 465;                                    	 // TCP port to connect to

				//Recipients
				$mail->setFrom('noreply@workz.com.br', 'Workz!');
				$mail->addAddress($resetMl);     						// Add a recipient

				// Content
				$mail->isHTML(true);                                  // Set email format to HTML
				$mail->Subject = 'Alteração de senha Workz!';
				$mail->Body    = $bd;
				$mail->AltBody = "Olá, {$user[0]['tt']}! Para prosseguir com a alteração de senha, insira o código {$codigoVerificacao} na tela da Workz!.";
				
				$mail->send();
			}else{
				echo 'Erro ao criar o código';
			}			
		
		}else{
			echo 'E-mail não encontrado.';
		}
	
	}else{
		echo 'Endereço de e-mail necessário.';
	}	
?>