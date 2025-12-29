<?php
// Teste direto de pagamento MP sem usar MercadoPagoClient
header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
if (!$token) {
    echo json_encode(['error' => 'Token não fornecido. Use ?token=SEU_TOKEN']);
    exit;
}

$payload = [
    'transaction_amount' => 99.9,
    'description' => 'Teste direto',
    'token' => $token,
    'installments' => 1,
    'payer' => [
        'email' => 'guilhermesantanarp@gmail.com',
        'identification' => [
            'type' => 'CPF',
            'number' => '19119119100'
        ]
    ]
];

$accessToken = 'TEST-8200142246106461-111109-80af3688c8216017faf484a1673f14c3-2345626046';

$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        'content' => json_encode($payload),
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$result = file_get_contents('https://api.mercadopago.com/v1/payments', false, $context);

$statusLine = $http_response_header[0] ?? '';
preg_match('/\d{3}/', $statusLine, $matches);
$statusCode = $matches[0] ?? 0;

echo json_encode([
    'status_code' => (int)$statusCode,
    'response' => json_decode($result, true),
    'payload_sent' => $payload
]);
