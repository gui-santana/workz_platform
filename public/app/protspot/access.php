<?php
session_start();
if(isset($_POST['user_id'])){	
	$_SESSION['id'] = $_POST['user_id']; //Sessão com a ProtSpot ID do seu usuário	
	$_SESSION['geolocation'] = $_POST['geolocation']; //Array com as Coordenadas de Localização do seu usuário
}
header("Location: ".$_POST['app_url']); //Endereço da sua aplicação
?>