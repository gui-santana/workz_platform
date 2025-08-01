<?php
// Permitir qualquer origem
header("Access-Control-Allow-Origin: https://app.workz.com.br");

// Permitir os métodos de solicitação POST
header("Access-Control-Allow-Methods: POST");

// Permitir o cabeçalho Content-Type
header("Access-Control-Allow-Headers: Content-Type");

// Verificar se a solicitação é do tipo OPTIONS (pré-voo)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Finalizar a solicitação sem retornar dados (somente cabeçalhos)
    exit();
}

date_default_timezone_set('America/Sao_Paulo');
require_once($_SERVER['DOCUMENT_ROOT'].'/functions/search.php');

// Função para criptografar a senha
function criptografar($senha, $encryptionKey){
	$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
	$senhaCriptografada = openssl_encrypt($senha, 'aes-256-cbc', $encryptionKey, 0, $iv);
	return base64_encode($iv . $senhaCriptografada);
}

// Função para descriptografar a senha
function descriptografar($senhaCriptografada, $encryptionKey){
	$data = base64_decode($senhaCriptografada);
	$iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
	$senhaCriptografada = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
	return openssl_decrypt($senhaCriptografada, 'aes-256-cbc', $encryptionKey, 0, $iv);
}

//Adicionar registro
if(isset($_POST) && !empty($_POST)){
	$wa0004_keys = search('app', 'wa0004_keys', 'id,ky', "us = '".$_POST['wz']."'")[0];
	$encryptionKey = $wa0004_keys['ky'];
	$id_psw = $wa0004_keys['id'];
	if(!empty($encryptionKey)){		
		if($_POST['fnc'] == 'load'){
			
			header('Content-Type: application/json');	
			//Recuperar registros
			$data = array();
			$user = hash('sha256', $_POST['wz'] . $id_psw);	
			$regs = search('app', 'wa0004_regs', '', "us = '".$user."'");
			foreach($regs as $reg){
				$data[] = array(
					'id' => $reg['id'],
					'descricao' => descriptografar($reg['ds'], $encryptionKey),
					'site' => descriptografar($reg['ur'], $encryptionKey),
					'usuario' => descriptografar($reg['lg'], $encryptionKey),
					'senha' => descriptografar($reg['pw'], $encryptionKey),
					'shared' => descriptografar($reg['sh'], $encryptionKey)
				);
			}		
			echo json_encode($data);
			
		}elseif($_POST['fnc'] == 'insert'){
			require_once($_SERVER['DOCUMENT_ROOT'].'/functions/insert.php');		
			$user = hash('sha256', $_POST['wz'] . $id_psw);	
			$senha = criptografar($_POST['senha'], $encryptionKey);
			$usuario = criptografar($_POST['usuario'], $encryptionKey);
			$site = criptografar($_POST['site'], $encryptionKey);
			$descricao = criptografar($_POST['descricao'], $encryptionKey);						
			$result = insert('app', 'wa0004_regs', 'ds,ur,lg,pw,us,dt', "'".$descricao."', '".$site."', '".$usuario."', '".$senha."', '".$user."', '".date('Y-m-d H:i:s')."'");
			echo $result;		
		}elseif($_POST['fnc'] == 'update'){
			require_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');
			$id = $_POST['id'];
			$senha = criptografar($_POST['senha'], $encryptionKey);
			$usuario = criptografar($_POST['usuario'], $encryptionKey);
			$site = criptografar($_POST['site'], $encryptionKey);
			$descricao = criptografar($_POST['descricao'], $encryptionKey);
			$result = update('app', 'wa0004_regs', "ds='".$descricao."',ur='".$site."',lg='".$usuario."',pw='".$senha."',dt='".date('Y-m-d H:i:s')."'", "id='".$id."'");
			echo $result;
		}elseif($_POST['fnc'] == 'delete'){
			require_once($_SERVER['DOCUMENT_ROOT'].'/functions/delete.php');
			$id = $_POST['id'];
			$result = del('app', 'wa0004_regs', "id='".$id."'");
			echo $result;
		}elseif($_POST['fnc'] == 'options'){
			require_once($_SERVER['DOCUMENT_ROOT'].'/functions/update.php');			
			$usuario = $_POST['wz'];
			$opcao = $_POST['opcao'];
			$valor = $_POST['valor'];
			$result = update('app', 'wa0004_keys', $opcao."='".$valor."'", "us='".$usuario."'");					  						
			echo $result;			
		}		
	}
}
?>
