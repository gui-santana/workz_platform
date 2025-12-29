<?php
// src/Services/StripeClient.php

namespace Workz\Platform\Services;

class StripeClient
{
    private string $secretKey;
    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct(string $secretKey)
    {
        $this->secretKey = trim($secretKey);
        if ($this->secretKey === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY não configurada');
        }
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $url = $this->apiBase . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'GET') {
            if (!empty($payload)) {
                $url .= '?' . http_build_query($payload);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = $errno ? curl_error($ch) : null;
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('Erro cURL: ' . $err);
        }
        $data = json_decode($resp ?: '', true);
        if (!is_array($data)) {
            $data = ['_http_status' => $status, 'raw' => substr((string)$resp, 0, 1000)];
        } else {
            $data['_http_status'] = $status;
        }
        if ($status >= 400) {
            throw new \RuntimeException('Erro Stripe API: HTTP ' . $status . ' - ' . json_encode($data));
        }
        return $data;
    }

    public function createCustomer(string $email, ?string $name = null): array
    {
        $payload = ['email' => $email];
        if ($name) { $payload['name'] = $name; }
        return $this->request('POST', '/customers', $payload);
    }

    public function getCustomer(string $id): array
    {
        return $this->request('GET', '/customers/' . urlencode($id));
    }

    public function createSetupIntent(string $customerId, array $types = ['card']): array
    {
        $payload = [
            'customer' => $customerId,
        ];
        foreach ($types as $t) {
            $payload['payment_method_types[]'] = $t;
        }
        return $this->request('POST', '/setup_intents', $payload);
    }

    public function attachPaymentMethod(string $paymentMethodId, string $customerId): array
    {
        $payload = ['customer' => $customerId];
        return $this->request('POST', '/payment_methods/' . urlencode($paymentMethodId) . '/attach', $payload);
    }

    public function detachPaymentMethod(string $paymentMethodId): array
    {
        return $this->request('POST', '/payment_methods/' . urlencode($paymentMethodId) . '/detach');
    }

    public function retrievePaymentMethod(string $paymentMethodId): array
    {
        return $this->request('GET', '/payment_methods/' . urlencode($paymentMethodId));
    }

    public function updateCustomerDefaultPaymentMethod(string $customerId, string $paymentMethodId): array
    {
        return $this->request('POST', '/customers/' . urlencode($customerId), [
            'invoice_settings[default_payment_method]' => $paymentMethodId,
        ]);
    }

    public function createPaymentIntent(array $payload): array
    {
        // payload already prepared with correct fields (amount in cents)
        return $this->request('POST', '/payment_intents', $payload);
    }

    public function retrievePaymentIntent(string $id): array
    {
        return $this->request('GET', '/payment_intents/' . urlencode($id));
    }
}
