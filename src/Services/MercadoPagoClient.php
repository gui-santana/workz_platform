<?php
// src/Services/MercadoPagoClient.php

namespace Workz\Platform\Services;

class MercadoPagoClient
{
    private string $accessToken;

    public function __construct(?string $accessToken = null)
    {
        $this->accessToken = $accessToken ?: ($_ENV['MP_ACCESS_TOKEN'] ?? '');
        if (!$this->accessToken) {
            throw new \RuntimeException('MP_ACCESS_TOKEN não configurado');
        }
    }

    private function request(string $method, string $url, ?array $payload = null, array $headers = []): array
    {
        $ch = curl_init($url);
        
        $defaultHeaders = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Enable verbose output for debugging
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        // Set method and payload
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                $jsonPayload = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            }
        } elseif (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
        }
        
        // SSL settings
        if (!empty($_ENV['MP_INSECURE_SKIP_VERIFY'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            $caBundle = '/etc/ssl/certs/ca-certificates.crt';
            if (file_exists($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }
        }
        
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $errno ? curl_error($ch) : null;
        
        // Get verbose output for debugging
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);
        
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('Erro cURL: ' . $err);
        }
        $data = json_decode($resp ?: '', true);
        if (!is_array($data)) {
            $data = ['_http_status' => $status, 'raw' => substr((string)$resp, 0, 1000)];
        } else {
            // Preserve provider "status" field; store HTTP status separately
            $data['_http_status'] = $status;
        }
        if ($status >= 400) {
            throw new \RuntimeException('Erro MercadoPago API: HTTP ' . $status . ' - ' . json_encode($data));
        }
        return $data;
    }

    public function createPreference(array $preference): array
    {
        $url = 'https://api.mercadopago.com/checkout/preferences';
        return $this->request('POST', $url, $preference);
    }

    public function getPreference(string $preferenceId): array
    {
        $url = 'https://api.mercadopago.com/checkout/preferences/' . urlencode($preferenceId);
        return $this->request('GET', $url);
    }

    // -------- Preapproval / Plans --------
    public function createPreapprovalPlan(array $payload): array
    {
        $url = 'https://api.mercadopago.com/preapproval_plan';
        return $this->request('POST', $url, $payload);
    }

    public function getPreapprovalPlan(string $planId): array
    {
        $url = 'https://api.mercadopago.com/preapproval_plan/' . urlencode($planId);
        return $this->request('GET', $url);
    }

    public function createPreapproval(array $payload, ?string $idempotencyKey = null): array
    {
        $url = 'https://api.mercadopago.com/preapproval';
        // Mercado Pago requires X-Idempotency-Key header (cannot be null)
        // Generate one if not provided
        if (!$idempotencyKey) {
            $idempotencyKey = uniqid('workz-preapproval-', true);
        }
        $headers = ['X-Idempotency-Key: ' . $idempotencyKey];
        return $this->request('POST', $url, $payload, $headers);
    }

    public function getPreapproval(string $id): array
    {
        $url = 'https://api.mercadopago.com/preapproval/' . urlencode($id);
        return $this->request('GET', $url);
    }

    public function updatePreapproval(string $id, array $payload): array
    {
        $url = 'https://api.mercadopago.com/preapproval/' . urlencode($id);
        return $this->request('PUT', $url, $payload);
    }

    public function getPayment(string $paymentId): array
    {
        $url = 'https://api.mercadopago.com/v1/payments/' . urlencode($paymentId);
        return $this->request('GET', $url);
    }

    public function createPayment(array $payload, ?string $idempotencyKey = null): array
    {
        $url = 'https://api.mercadopago.com/v1/payments';
        // Mercado Pago requires X-Idempotency-Key header (cannot be null)
        // Generate one if not provided
        if (!$idempotencyKey) {
            $idempotencyKey = uniqid('workz-payment-', true);
        }
        $headers = ['X-Idempotency-Key: ' . $idempotencyKey];
        return $this->request('POST', $url, $payload, $headers);
    }

    public function createCardToken(array $payload): array
    {
        $url = 'https://api.mercadopago.com/v1/card_tokens';
        return $this->request('POST', $url, $payload);
    }

    // -------- Customers --------
    public function getCustomer(string $customerId): ?array
    {
        try {
            $url = 'https://api.mercadopago.com/v1/customers/' . urlencode($customerId);
            return $this->request('GET', $url);
        } catch (\RuntimeException $e) {
            // Handle 404 gracefully - customer not found
            if (strpos($e->getMessage(), 'HTTP 404') !== false) {
                return null;
            }
            // Re-throw other errors
            throw $e;
        }
    }

    public function searchCustomerByEmail(string $email): ?array
    {
        $url = 'https://api.mercadopago.com/v1/customers/search?email=' . urlencode($email);
        $r = $this->request('GET', $url);
        $results = $r['results'] ?? [];
        if (is_array($results) && count($results) > 0) { return $results[0]; }
        return null;
    }

    public function createCustomer(array $payload): array
    {
        $url = 'https://api.mercadopago.com/v1/customers';
        return $this->request('POST', $url, $payload);
    }

    // -------- Cards --------
    public function createCustomerCard(string $customerId, array $payload): array
    {
        $url = 'https://api.mercadopago.com/v1/customers/' . urlencode($customerId) . '/cards';
        return $this->request('POST', $url, $payload);
    }

    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        $url = 'https://api.mercadopago.com/v1/customers/' . urlencode($customerId) . '/cards/' . urlencode($cardId);
        return $this->request('DELETE', $url);
    }
}
