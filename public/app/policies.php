<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$pattern = '/public_html\/([^\/]+)/';	
preg_match($pattern, $documentRoot, $matches);	
$subdomainFolder = isset($matches[1]) ? $matches[1] : '';	
$sanitizedFolder = preg_replace('/[^a-zA-Z0-9-_]/', '', $subdomainFolder);
$currentUrl = $_SERVER['HTTP_HOST'];
$parts = explode('.', $currentUrl);
$subdomain = $parts[0];						
if ($sanitizedFolder === $subdomain){
	if(strpos($documentRoot, $sanitizedFolder.'/') > 0){
		$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder.'/', '', $documentRoot);					
	}else{
		$_SERVER['DOCUMENT_ROOT'] = str_replace($sanitizedFolder, '', $documentRoot);
	}			
}

require_once('../functions/search.php');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Política de Privacidade do App <?php echo $app[0]['tt']; ?></title>
	<style>
	  body {
		font-family: Arial, sans-serif;
		line-height: 1.6;
		margin: 0 auto;
		max-width: 800px;
		padding: 20px;
	  }
	  h1, h2, h3 {
		color: #333;
	  }
	  h1 {
		font-size: 1.8em;
	  }
	  h2 {
		font-size: 1.4em;
	  }
	  h3 {
		font-size: 1.2em;
	  }
	  p {
		margin-bottom: 20px;
	  }
	</style>
</head>
<body>
<?
if(!empty($_GET['app']) & !empty($_GET['type'])){
	$app = search('app', 'apps', '', "nm = '".$_GET['app']."'");
	if($_GET['type'] === 'privacy'){
	?>	
	<h1>Política de Privacidade do aplicativo <?php echo $app[0]['tt']; ?></h1>

	<p>A sua privacidade é importante para nós. Esta Política de Privacidade descreve como a Workz! trata as informações pessoais que são coletadas quando você utiliza o aplicativo <?php echo $app[0]['tt']; ?>. Leia atentamente esta política para entender como lidamos com seus dados.</p>

	<h2>Informações Coletadas</h2>

	<p>Ao utilizar o aplicativo <?php echo $app[0]['tt']; ?>, podemos coletar as seguintes informações:</p>

	<ol>
		<li><strong>Dados de Cadastro e Login:</strong> Ao utilizar a API ProtSpot para cadastrar-se ou fazer login, alguns dados pessoais serão fornecidos, como nome, endereço de e-mail e senha. Esses dados são armazenados no banco de dados da ProtSpot e são fornecidos à Workz! mediante o seu consentimento a cada tentativa de login.</li>

		<li><strong>Informações de Perfil:</strong> Podemos coletar informações adicionais para completar o seu acesso ao aplicativo <?php echo $app[0]['tt']; ?>, como dados mínimos necessários e/ou outras informações que você escolher compartilhar.</li>

		<li><strong>Conteúdo Publicado:</strong> Quando você cria e compartilha algo através do aplicativo <?php echo $app[0]['tt']; ?>, o conteúdo é armazenado e associado à sua conta de usuário Workz!.</li>
	</ol>

	<h2>Uso das Informações</h2>

	<p>As informações coletadas no aplicativo <?php echo $app[0]['tt']; ?> são utilizadas para os seguintes propósitos:</p>

	<ol>
		<li><strong>Fornecimento de Serviços:</strong> Utilizamos suas informações para fornecer os serviços do aplicativo <?php echo $app[0]['tt']; ?>.</li>

		<li><strong>Personalização:</strong> Podemos utilizar suas informações para personalizar sua experiência no aplicativo, com base em suas preferências.</li>

		<li><strong>Comunicação:</strong> Podemos utilizar seu endereço de e-mail para enviar notificações relacionadas ao uso do aplicativo, como atualizações, notificações de atividade e outras informações relevantes.</li>
	</ol>

	<h2>Compartilhamento de Informações</h2>

	<p>A Workz! não compartilha suas informações pessoais com terceiros sem o seu consentimento, exceto nos seguintes casos:</p>

	<ul>
		<li><strong>Cumprimento de Obrigações Legais:</strong> Podemos compartilhar suas informações em resposta a uma solicitação legal válida ou quando necessário para cumprir com obrigações legais.</li>

		<li><strong>Proteção dos Direitos da Workz!:</strong> Podemos compartilhar informações para proteger os direitos, privacidade, segurança ou propriedade da Workz! ou de seus usuários.</li>
	</ul>

	<h2>Política de Banimento</h2>

	<p>A Workz! reserva-se o direito de banir usuários que descumprirem as regras de uso do aplicativo <?php echo $app[0]['tt']; ?>, incluindo conteúdo que viole a lei, prejudique a imagem de terceiros, faça apologia a atividades ilegais, contenha pornografia ou temas relacionados.</p>

	<h2>Alterações nesta Política</h2>

	<p>Esta Política de Privacidade pode ser atualizada ocasionalmente para refletir mudanças nas práticas da Workz!. Recomendamos que você reveja periodicamente esta política para estar ciente das atualizações.</p>

	<h2>Contato</h2>

	<p>Se tiver alguma dúvida sobre esta Política de Privacidade, entre em contato conosco através do e-mail: <a href="mailto:legal@guilhermesantana.com.br">legal@guilhermesantana.com.br</a>.</p>

	<p>Esta Política de Privacidade foi atualizada em 13 de setembro de 2023.</p>

	<p>Workz!</p>
	<?
	}
}else{
	?>
	<p>Não foi possível obter uma Política de Privacidade válida.</p>
	<?
}
?>
</body>
</html>

