<?php
session_start();
require('../functions/search.php');
require('../protspot/userGetIdClient.php');

// Verificar se o arquivo foi enviado
if (isset($_FILES['video_file'])) {

    // Verificar se o arquivo enviado é um vídeo
    $allowedVideoTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/mpeg', 'video/webm'];
    $fileType = $_FILES['video_file']['type'];
    
    if (!in_array($fileType, $allowedVideoTypes)) {
        echo "Erro: Apenas arquivos de vídeo são permitidos.";
        exit;
    }
    
    // Caminho temporário do arquivo de vídeo
    $file_path = $_FILES['video_file']['tmp_name'];
    
    // Obter informações do usuário para enviar
    $user = search('hnw', 'hus', 'tt,un', "id = '".$_SESSION['wz']."'")[0];
    
    $user_name = strtok($user['tt'], " ");  // Obter o primeiro nome
    $user_link = 'workz.com.br/' . $user['un'];
    //$user_photo = getByProtSpot($_SESSION['id'], 'picture');  // Foto do perfil em base64
    
    // Configurar o destino da API de processamento na VM
    $url = 'http://204.216.171.33/producer.php'; // Certifique-se de que o IP e o endpoint estão corretos

    // Configurar os dados para envio via cURL
    $cfile = curl_file_create($file_path, $_FILES['video_file']['type'], $_FILES['video_file']['name']);
    $post = array(
        'file' => $cfile,
        'user_name' => $user_name,
        'user_link' => $user_link        
    );

    // Inicializar cURL para enviar o vídeo para a VM
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);  // URL da API de processamento na VM
    curl_setopt($ch, CURLOPT_POST, 1);  // Método POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);  // Dados a serem enviados
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Retornar resposta como string

    // Executar a requisição e capturar a resposta
    $response = curl_exec($ch);
    
    // Verificar se ocorreu algum erro na execução
    if ($response === FALSE) {
        echo 'Erro: ' . curl_error($ch);
    } else {
        echo $response;  // Exibir a resposta da VM
    }

    // Fechar a conexão cURL
    curl_close($ch);
}
?>

