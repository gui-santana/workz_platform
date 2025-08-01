<?php
session_start();

try{
	//Descarta todas as sessões ativas
	session_destroy();
	if(isset($_GET['app']) && !empty($_GET['app'])){
		header("Location: https://app.workz.com.br/".$_GET['app']); //Your index page
	}else{
		header("Location: https://workz.com.br"); //Your index page
	}
	
}catch(Exception $e){
	echo 'Algo deu errado! Entre em contato para obter suporte.<br>'.$e;	
}

?>