<?php
// Configurações do seu usuário e senha de aplicação do WordPress
$wordpress_user = 'gsantana';
$application_password = '7WRn VyUp aOiM IOo0 Y78m SP3M';

// URL do endpoint da API
$api_url = 'https://guilhermesantana.com.br/wp-json/tarefaswp/v1/listar';

// Montar autenticação Basic Auth
$auth = base64_encode($wordpress_user . ':' . $application_password);

// Requisição cURL
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $auth,
    'Content-Type: application/json',
]);
// 👇 ESSENCIAL
curl_setopt($ch, CURLOPT_USERAGENT, 'MyApp/1.0');

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Erro na requisição: ' . curl_error($ch);
} else {
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "Código HTTP: $http_code\n\n";

    if ($http_code === 200) {
        $data = json_decode($response, true);
        echo "Dados recebidos:\n";
        print_r($data);
    } else {
        echo "Erro ao acessar a API: " . $response;
    }
}

curl_close($ch);
?>
