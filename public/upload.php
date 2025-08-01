<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
date_default_timezone_set('America/Fortaleza');
error_reporting(E_ALL);

$response = []; // Variável para armazenar a resposta final

if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $ociUrl = 'https://workz.space/upload.php';

    // Configuração do cURL
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $ociUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
        ],
    ]);

    $ociResponse = curl_exec($curl);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        http_response_code(500);
        $response = ['message' => 'Erro ao enviar o vídeo para a OCI: ' . $curlError];
    } else {
        $data = json_decode($ociResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            $response = ['message' => 'Resposta inválida recebida da OCI.', 'raw_response' => $ociResponse];
        } else {
            if (isset($data['hls_path'])) {
                session_start();
                require_once('functions/insert.php');
                $now = date('Y-m-d H:i:s');

				// Decodifique os dados enviados
				$lg = isset($_POST['lg']) ? base64_encode($_POST['lg']) : '';				

                $post = base64_encode(bzcompress(json_encode([
                    'type' => 'video',
                    'path' => $data['hls_path'] // Caminho público do arquivo
                ])));

                // Insere no banco de dados
                if ($success = insert('hnw', 'hpl', 'us,tp,dt,st,ct,lg', "'{$_SESSION['wz']}','9','{$now}','1','{$post}','{$lg}'")) {
                    $response = ['message' => 'Vídeo publicado com sucesso!', 'id' => $success];
                } else {
                    http_response_code(500);
                    $response = ['message' => 'Erro ao publicar o vídeo.'];
                }
            } else {
                http_response_code(500);
                $response = ['message' => 'Erro no processamento do vídeo pela OCI.', 'data' => $data];
            }
        }
    }
} else {
    http_response_code(400);
    $response = ['message' => 'Nenhum arquivo enviado.'];
}

// Retorna a resposta final
ob_clean();
echo json_encode($response);
?>
