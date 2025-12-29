<?php
// Teste: gerar token SEM customer_id
header('Content-Type: application/json');

$cardId = '1764775653244'; // Do banco de dados
$cvv = '123';

$payload = [
    'card_id' => $cardId,
    'security_code' => $cvv
];

$accessToken = 'TEST-8200142246106461-111109-80af3688c8216017faf484a1673f14c3-2345626046';

$ch = curl_init('https://api.mercadopago.com/v1/card_tokens');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $accessToken,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'status_code' => $status,
    'response' => json_decode($resp, true),
    'payload_sent' => $payload
]);
