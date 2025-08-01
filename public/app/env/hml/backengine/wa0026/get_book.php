<?php
include('../../../sanitize.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['wz'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit();
}

// Obter o ID do documento a partir do POST
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do documento não fornecido.']);
    exit();
}

$docId = intval($_POST['id']);

$doc = search('app', 'wa0026_books', 'ct,cp', 'id="'.$docId.'" AND us = "'.$_SESSION['wz'].'"')[0];

if($doc){
	// Verificar se 'cp' é armazenado como binário e, se necessário, codificar em base64
	$coverImageData = $doc['cp'];
	// Se 'cp' for binário, descomente a linha abaixo
	// $coverImageData = base64_encode($doc['cp']);

	// Retornar os dados em formato JSON
	echo json_encode([
		'success' => true,
		'content' => $doc['ct'],
		'coverImage' => $coverImageData
	]);
}else{
	echo json_encode(['success' => false, 'message' => 'Documento não encontrado.']);
}
?>