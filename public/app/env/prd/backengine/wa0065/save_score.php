<?php
// Sanitização de subdomínios
session_start();
include_once($_SERVER['DOCUMENT_ROOT'] . '/sanitize.php');

if(isset($_SESSION['wz'])){
	print_r($_POST);
	
	if($_POST['sc']){
		// Inclui a função de inserção
		require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';	
		$columns = 'us,lv,tm,sc';
		$values = "'{$_SESSION['wz']}','{$_POST['lv']}','{$_POST['tm']}','{$_POST['sc']}'";		
		if(insert('app', 'wa0065_regs', $columns, $values)){
			echo "Registro de pontuação enviado.";
		}else{
			echo "Erro: Não foi possível enviar o registro de pontuação.";
		}
	}
}else{
	echo "É necessário estar logado na Workz! para registrar a sua pontuação.";
}

?>