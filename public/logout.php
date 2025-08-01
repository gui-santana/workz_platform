<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/getCurrentURL.php');
$url = getCurrentURL(1);
try{
	session_destroy();
	header('Location: '.$url); //Your current page
}catch(Exception $e){	
	echo json_encode(["error" => "Erro ao destruir a sessão deste site.".$e]);
	exit;
}	
?>