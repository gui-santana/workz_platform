<?php
if(isset($_SESSION)){
	session_start();
	require_once($_SERVER['DOCUMENT_ROOT'].'functions/getCurrentURL.php');
	$url = getCurrentURL(3);
	
	echo 'Location: https://app.workz.com.br/'.$url;
	try{
		session_destroy();
		//header('Location: https://app.workz.com.br/'.$url); //Your current page
	}catch(Exception $e){	
		echo json_encode(["error" => "Erro ao destruir a sessão deste site.".$e]);
		exit;
	}	
}

?>