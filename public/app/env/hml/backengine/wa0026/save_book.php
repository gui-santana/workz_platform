<?php
include('../../../sanitize.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['wz'])) {
    // Redirecionar ou retornar um erro
    http_response_code(401);
    echo 'Usuário não autenticado.';
    exit();
}

// Obter os dados enviados via POST
$title = isset($_POST['title']) ? $_POST['title'] : '';
$coverImage = isset($_POST['coverImage']) ? $_POST['coverImage'] : '';
$contentText = isset($_POST['contentText']) ? $_POST['contentText'] : '';

if(count(search('app', 'wa0026_books', 'id', 'tt="'.$title.'" AND us = "'.$_SESSION['wz'].'"')) == 0){

	// Validar os dados (pode adicionar mais validações conforme necessário)
	if (empty($title) || empty($coverImage) || empty($contentText)) {
		http_response_code(400);
		echo 'Dados incompletos.';
		exit();
	}

	// Remover o prefixo 'data:image/jpeg;base64,' da imagem
	if (strpos($coverImage, 'data:image/') === 0) {
		$coverImage = substr($coverImage, strpos($coverImage, ',') + 1);
	}

	// Decodificar a imagem base64
	$coverImageData = base64_decode($coverImage);

	$document = insert('app', 'wa0026_books', 'us,tt,cp,ct', "'".$_SESSION['wz']."','".$title."','".$coverImage."','".$contentText."'");

	if($document){
		echo 'Dados salvos com sucesso.';
	}else{
		http_response_code(500);
		echo 'Erro ao salvar os dados: ' . $e->getMessage();
	}
}else{
	echo 'Este documento já existe na sua biblioteca.';
}
?>