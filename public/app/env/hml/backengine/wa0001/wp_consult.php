<?php
//Sanitiza subdomínios de $_SERVER['DOCUMENT_ROOT']
require_once $_SERVER['DOCUMENT_ROOT'] . '/sanitize.php';
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';

$wpNames = [
    'pasta', 'equipe', 'equipe_usuarios', 'frequencia', 'frequencia_pers',
    'dificuldade', 'status', 'titulo', 'descricao', 'data_registro',
    'data_reinicio', 'tempo', 'habilidades', 'etapas', 'historico', 'prazo_final'
];

$wzNames = [
    'tg', 'cm', 'uscm', 'pr', 'prpe', 'lv', 'st', 'tt', 'ds', 'wg',
    'init', 'time', 'hb', 'step', 'tml', 'dt'
];

function renomear_chaves_personalizadas($registro, $originais, $novos) {
    $resultado = [];

    foreach ($originais as $index => $chaveOriginal) {
        $novaChave = $novos[$index];
        $resultado[$novaChave] = $registro[$chaveOriginal] ?? null;
    }

    // Mantém campos adicionais importantes
    $extras = ['id', 'usuario', 'tipo'];
    foreach ($extras as $campo) {
        if (isset($registro[$campo])) {
            $resultado[$campo] = $registro[$campo];
        }
    }

    return $resultado;
}

function sanitizarDominio($url) {
    $host = parse_url(trim($url), PHP_URL_HOST);

    // Remove "www." se existir
    $host = preg_replace('/^www\./', '', $host);

    return $host;
}

function gerarIdTemporario() {
    return intval(microtime(true) * 10000) + random_int(1000, 9999);
}

if($connections = search('app', 'wa0001_wp', '', "us = '{$_SESSION['wz']}'")){
 
    $dataConvertidoTotal = []; // acumulador de tarefas vindas de diferentes conexões
    $temporaryFolders = []; //acumulador de pastas temporárias
    
    foreach($connections as $conn){
        
        $tg = gerarIdTemporario();
        
        $api_url = 'https://'.$conn['ur'].'/wp-json/tarefaswp/v1/listar';
        $auth = $conn['en'];
        
        // Requisição cURL
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json',
        ]);
        
        // 👇 ESSENCIAL
        curl_setopt($ch, CURLOPT_USERAGENT, 'WorkzTarefas/1.0');
        
        $response = curl_exec($ch);
        
        print_r($response);
        
        if (curl_errno($ch)) {
            echo 'Erro na requisição: ' . curl_error($ch);
        } else {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            echo "Código HTTP: $http_code\n\n";
        
            if ($http_code === 200) {
                $data = json_decode($response, true);
                //echo "Dados recebidos:\n";
                
                // Aplicar sobre o array de tarefas
                $dataConvertido = array_map(function($item) use ($wpNames, $wzNames, $tg) {
                    // Se 'pasta' estiver vazia ou nula, define um valor temporário único
                    if (empty($item['pasta'])) {
                        $item['pasta'] = $tg;
                    }
                    return renomear_chaves_personalizadas($item, $wpNames, $wzNames);
                }, $data);
                
                $dataConvertidoTotal = array_merge($dataConvertidoTotal, $dataConvertido);
            } else {
                echo "Erro ao acessar a API: " . $response;
            }
        }
        
        curl_close($ch);
        
        $temporaryFolders[$tg] = $conn['ur']; // atribui o valor ao ID como chave
    }
    
    // Ao final do foreach:
    echo "<pre>";
    print_r($dataConvertidoTotal);
    echo "</pre>";
    print_r($temporaryFolders);
}
?>
