<?php
define("ENCRYPTION_KEY", "r^mn,33z?YM-S9cZac4:Tf(y!e}+1f"); // Defina uma chave forte

function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted); // Retorna IV + Dados Criptografados
}

function decryptData($encryptedData) {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
}
?>