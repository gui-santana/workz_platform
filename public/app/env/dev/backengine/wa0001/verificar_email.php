<?php
// Sanitização de subdomínios
include_once('../../../sanitize.php');

// Sessão e configurações de ambiente
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/search.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/AEScrypt.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/insert.php';  

date_default_timezone_set('America/Sao_Paulo');
$now = date('Y-m-d H:i:s');

if (!isset($_SESSION["wz"])) {
    die(json_encode(["error" => "Usuário não autenticado."]));
}

$result = search('app', 'wa0001_mail', 'ml,pw,im', "us = {$_SESSION["wz"]}");

if (!is_array($result) || count($result) === 0) {
    die(json_encode(["message" => "Nenhum e-mail cadastrado."]));
}

$api_url = "https://workz.space/classify";
$api_key = "1G,B:{Z08E+(R+a?:P77|VmljEY9E$";

$emails_processados = [];

foreach ($result as $email_data) {
    $email = $email_data["ml"];
    $password = decryptData($email_data["pw"]);
    $imap_host = $email_data["im"];    

    // Conectar ao IMAP
    $inbox = imap_open($imap_host, $email, $password);
    if (!$inbox) {
        error_log("Erro ao conectar ao e-mail: $email - " . imap_last_error());
        continue;
    }

    $emails = imap_search($inbox, 'UNSEEN');  // Buscar e-mails não lidos

    if ($emails !== false) {
        foreach ($emails as $email_id) {
            $overview = imap_fetch_overview($inbox, $email_id, 0);
            $message = imap_fetchbody($inbox, $email_id, 1);
            $email_text = strip_tags($message);  // Remover HTML

            // Obter remetente do e-mail
            $remetente = isset($overview[0]->from) ? $overview[0]->from : "Desconhecido";

            // Enviar para a API de IA para classificação
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer $api_key"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["email_text" => $email_text]));

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (!empty($result["isTask"]) && $result["isTask"] === true) {
                // Usar título gerado pela IA
                $titulo_ia = isset($result["title"]) ? $result["title"] : "Tarefa Detectada";

                // Criar JSON com os atributos obtidos do e-mail
                $dsc_data = [
                    "email" => $email,
                    "remetente" => $remetente,
                    "urgencia" => $result["urgency"],
                    "importancia" => $result["importance"]
                ];
                
                $dsc_json = json_encode($dsc_data, JSON_UNESCAPED_UNICODE); // Correção aqui

                // Obter a pasta na qual será armazenada a tarefa
                $target = 0; // Vazio, por enquanto
                
                // Obter etapa padrão
                $step = "'Finalizar'|=0";

                // Inserir no banco de dados
                $task = insert('app', 'wa0001_wtk', 'tg,us,wg,tt,step,dsc', "'{$target}','{$_SESSION['wz']}','{$now}','{$titulo_ia}','{$step}','{$dsc_json}'");
                
                if ($task) {
                    echo json_encode(["message" => "Tarefa adicionada com sucesso!", "status" => "Incluído ao BD"]);
                } else {
                    error_log("Erro ao inserir a tarefa no banco para o e-mail: $email.");
                }
            }
        }
    }

    imap_close($inbox);
    $emails_processados[] = $email;
}

echo json_encode(["message" => "Verificação concluída!", "emails_verificados" => $emails_processados]);
?>
