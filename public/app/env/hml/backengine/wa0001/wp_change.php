<?php
$wpConn = search('app', 'wa0001_wp', '', "id = '{$_GET['wp']}' AND us = '{$_SESSION['wz']}'");
$url = 'https://'.$wpConn[0]['ur'].'/wp-json/tarefaswp/v1/atualizar/';

$wpNames = [
    'id', 'pasta', 'equipe', 'equipe_usuarios', 'frequencia', 'frequencia_pers',
    'dificuldade', 'status', 'titulo', 'descricao', 'data_registro',
    'data_reinicio', 'tempo', 'habilidades', 'etapas', 'historico', 'prazo_final'
];

$wzNames = [
    'wpid', 'tg', 'cm', 'uscm', 'pr', 'prpe', 'lv', 'st', 'tt', 'ds', 'wg',
    'init', 'time', 'hb', 'step', 'tml', 'dt'
];

if(isset($_POST)){
    print_r($_POST);
    echo '<hr>';
}

// Dados que serão enviados na atualização
$data = [
    'id' => 193,
    'dados' => [
        'tt' => 'Título atualizado via PHP',
        'ds' => 'Descrição alterada pelo cliente PHP',
        'st' => 1
    ]
];

print_r($data);

/*

// Credenciais de acesso (Application Passwords do WordPress ou autenticação básica)
$usuario = 'seu_usuario';
$senha = 'sua_senha_de_aplicativo'; // pode ser uma senha do tipo "Application Password"

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, "$usuario:$senha");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Erro cURL: ' . curl_error($ch);
} else {
    echo "Código HTTP: $http_code\n";
    echo "Resposta da API:\n$response\n";
}

curl_close($ch);
*/
