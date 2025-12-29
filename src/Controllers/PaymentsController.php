<?php
// src/Controllers/PaymentsController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Core\Database;
use Workz\Platform\Services\StripeClient;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;

class PaymentsController
{
    use AuthorizationTrait;

    private General $generalModel;

    public function __construct()
    {
        $this->generalModel = new General();
    }

    /**
     * POST /api/payments/preference
     * Body JSON: { app_id, title?, quantity?, unit_price?, currency?, company_id?, back_urls? }
     * Requires AuthMiddleware
     */
    public function createPreference(object $auth): void
    {
        header('Content-Type: application/json');
        http_response_code(410);
        echo json_encode(['error' => 'Fluxo antigo desativado. Use Stripe via /api/payments/charge.']);
    }

/**
     * POST /api/payments/webhook (Stripe)
     */
    public function stripeWebhook(): void
    {
        header('Content-Type: application/json');
        $payload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if (!$secret) { http_response_code(500); echo json_encode(['error' => 'Webhook secret não configurado']); return; }

        // Verifica assinatura (HMAC-SHA256)
        $timestamp = null; $v1 = null;
        foreach (explode(',', $sigHeader) as $part) {
            [$k,$v] = array_pad(explode('=', $part, 2), 2, null);
            if ($k === 't') { $timestamp = $v; }
            if ($k === 'v1') { $v1 = $v; }
        }
        if (!$timestamp || !$v1) { http_response_code(400); echo json_encode(['error' => 'Assinatura inválida']); return; }
        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        if (!hash_equals($expected, $v1)) {
            http_response_code(400); echo json_encode(['error' => 'Assinatura webhook inválida']); return;
        }

        $event = json_decode($payload, true) ?: [];
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        if ($type === 'payment_intent.succeeded') {
            $this->handleStripePaymentIntent($data, 'succeeded');
        } elseif ($type === 'payment_intent.payment_failed') {
            $this->handleStripePaymentIntent($data, 'failed');
        }

        echo json_encode(['success' => true]);
    }

    /**
     * GET /api/payments/transactions (auth)
     * Optional query: app_id, status, limit
     * Returns transactions of the current user
     */
    public function listTransactions(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $appId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : null;
            $status = isset($_GET['status']) ? (string)$_GET['status'] : null;
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;

            $conditions = [];
            if ($companyId) {
                $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                $conditions['company_id'] = $companyId;
            } else {
                $conditions['user_id'] = $userId;
            }
            if ($appId) { $conditions['app_id'] = $appId; }
            if ($status) { $conditions['status'] = $status; }

            $rows = $this->generalModel->search(
                'workz_apps',
                'workz_payments_transactions',
                ['id','type','status','app_id','user_id','company_id','amount','currency','mp_payment_id','mp_preference_id','created_at','updated_at'],
                $conditions,
                true,
                $limit,
                0,
                ['by' => 'id', 'dir' => 'DESC']
            );

            echo json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao buscar transações', 'details' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/payments/transactions/{id} (auth)
     */
    public function getTransaction(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $tx = $this->generalModel->search(
                'workz_apps',
                'workz_payments_transactions',
                ['id','type','status','app_id','user_id','company_id','amount','currency','mp_payment_id','mp_preference_id','metadata','created_at','updated_at'],
                ['id' => $id],
                false
            );

            if (!$tx) { http_response_code(404); echo json_encode(['error' => 'Transação não encontrada']); return; }

            $ownerId = (int)($tx['user_id'] ?? 0);
            $companyId = isset($tx['company_id']) ? (int)$tx['company_id'] : null;
            if ($ownerId !== $userId) {
                if ($companyId) {
                    $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                } else {
                    http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
                }
            }

            echo json_encode(['success' => true, 'data' => $tx]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao obter transação', 'details' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/payments/status/{id} (auth)
     */
    public function getStatus(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $tx = $this->generalModel->search(
                'workz_apps',
                'workz_payments_transactions',
                ['id','status','user_id','company_id','mp_payment_id','app_id','metadata'],
                ['id' => $id],
                false
            );
            if (!$tx) { http_response_code(404); echo json_encode(['error' => 'Transação não encontrada']); return; }

            $ownerId = (int)($tx['user_id'] ?? 0);
            $companyId = isset($tx['company_id']) ? (int)$tx['company_id'] : null;
            if ($ownerId !== $userId) {
                if ($companyId) {
                    $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                } else {
                    http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
                }
            }

            $status = (string)($tx['status'] ?? '');
            $shouldSync = false;
            if ($status === '' || is_numeric($status)) { $shouldSync = true; }
            if (in_array($status, ['created','pending','in_process'], true) && !empty($tx['mp_payment_id'])) { $shouldSync = true; }

            if ($shouldSync && !empty($tx['mp_payment_id'])) {
                try {
                    $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
                    $pi = $stripe->retrievePaymentIntent((string)$tx['mp_payment_id']);
                    $piStatus = strtolower((string)($pi['status'] ?? ''));
                    if ($piStatus) {
                        $this->generalModel->update('workz_apps', 'workz_payments_transactions', ['status' => $piStatus], ['id' => $id]);
                        $status = $piStatus;
                    }
                } catch (\Throwable $e) { /* ignore sync errors */ }
            }

            // Ensure entitlement on approval
            if ($status === 'approved') {
                try {
                    $appId = (int)($tx['app_id'] ?? 0);
                    $companyId = isset($tx['company_id']) ? (int)$tx['company_id'] : null;
                    $supportDays = $this->resolveSupportDays($tx['metadata'] ?? null, 30);
                    if ($appId > 0) {
                        $this->grantAppService($userId, $appId, $companyId, $supportDays);
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }

            echo json_encode(['success' => true, 'data' => ['id' => (int)$tx['id'], 'status' => $status]]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao obter status', 'details' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/payments/charge (auth)
     * Body: { app_id, amount, currency?, payment_method_id?, pm_id?, company_id?, metadata?, use_pix? }
     * Cria PaymentIntent no Stripe. Se payment_method_id/pm_id for enviado, confirma server-side; caso contrário retorna client_secret para o front confirmar.
     */
    public function charge(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $appId = (int)($input['app_id'] ?? 0);
            $amount = (float)($input['amount'] ?? 0);
            $currency = strtolower($input['currency'] ?? 'brl');
            $companyId = isset($input['company_id']) ? (int)$input['company_id'] : null;
            $pmId = isset($input['pm_id']) ? (int)$input['pm_id'] : null; // saved PM (local)
            $paymentMethodId = isset($input['payment_method_id']) ? trim((string)$input['payment_method_id']) : null; // Stripe PM id
            $supportDays = isset($input['support_days']) ? (int)$input['support_days'] : null;
            if ($supportDays !== null && $supportDays <= 0) { $supportDays = null; }
            $clientMeta = $input['metadata'] ?? [];
            if (!is_array($clientMeta)) { $clientMeta = []; }
            if ($supportDays !== null) { $clientMeta['support_days'] = $supportDays; }
            $usePix = !empty($input['use_pix']);

            if ($appId <= 0 || $amount <= 0) { http_response_code(400); echo json_encode(['error' => 'Parâmetros inválidos']); return; }

            $app = $this->generalModel->search('workz_apps', 'apps', ['id','tt','slug','vl'], ['id' => $appId], false);
            if (!$app) { http_response_code(404); echo json_encode(['error' => 'App não encontrado']); return; }
            $description = $input['description'] ?? ($app['tt'] . ' - Workz App');

            $user = $this->generalModel->search('workz_data', 'hus', ['ml','tt'], ['id' => $userId], false);
            $email = (string)($user['ml'] ?? '');

            $txId = $this->generalModel->insert('workz_apps', 'workz_payments_transactions', [
                'type' => 'one_time',
                'status' => 'created',
                'app_id' => $appId,
                'user_id' => $userId,
                'company_id' => $companyId,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'metadata' => json_encode(array_merge([
                    'flow' => 'direct_charge',
                    'app_slug' => $app['slug'] ?? null,
                    'pm_id' => $pmId,
                ], $clientMeta)),
            ]);
            if (!$txId) { http_response_code(500); echo json_encode(['error' => 'Falha ao registrar transação']); return; }

            $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
            $stripeCustomerId = $this->ensureStripeCustomerId($userId);

            // Se pm_id foi informado, buscar o PM salvo
            if ($pmId > 0) {
                $pm = $this->generalModel->search('workz_data', 'billing_payment_methods', ['id','provider','entity_type','entity_id','status','token_ref','stripe_customer_id'], ['id' => $pmId], false);
                if (!$pm || $pm['provider'] !== 'stripe' || (int)$pm['status'] !== 1) {
                    http_response_code(400); echo json_encode(['error' => 'Método de pagamento inválido']); return;
                }
                $pmEntityType = (string)($pm['entity_type'] ?? 'user');
                $pmEntityId = (int)($pm['entity_id'] ?? 0);
                if ($pmEntityType === 'business') {
                    if (!$companyId || $companyId !== $pmEntityId) {
                        http_response_code(403); echo json_encode(['error' => 'Sem permissão para usar este cartão']); return;
                    }
                    $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                } elseif ($pmEntityId !== $userId) {
                    http_response_code(403); echo json_encode(['error' => 'Sem permissão para usar este cartão']); return;
                }
                $paymentMethodId = (string)($pm['token_ref'] ?? '');
                // Ajusta customer de acordo com a entidade do cartão
                if ($pmEntityType === 'business') {
                    $companyCustomer = $pm['stripe_customer_id'] ?? null;
                    if (!$companyCustomer) {
                        $companyCustomer = $this->ensureStripeCustomerForEntity('business', $pmEntityId, null, null, $userId);
                    }
                    $stripeCustomerId = $companyCustomer;
                }
            }

            $metadata = [
                'workz_transaction_id' => (string)$txId,
                'workz_app_id' => (string)$appId,
                'workz_user_id' => (string)$userId,
                'workz_company_id' => $companyId ? (string)$companyId : null,
                'workz_support_days' => $supportDays,
            ];

            $amountCents = (int)round($amount * 100);
            $payload = [
                'amount' => $amountCents,
                'currency' => $currency,
                'description' => $description,
                'customer' => $stripeCustomerId,
                'metadata' => $metadata,
                'payment_method_types[]' => 'card',
            ];
            if ($usePix) {
                $payload['payment_method_types[]'] = 'pix';
                $payload['payment_method_options[pix][expires_after_seconds]'] = 900;
            }
            $confirmServerSide = false;
            if ($paymentMethodId) {
                $payload['payment_method'] = $paymentMethodId;
                $payload['confirm'] = 'true';
                $payload['off_session'] = 'true';
                $confirmServerSide = true;
            }

            $pi = $stripe->createPaymentIntent($payload);
            $piId = (string)($pi['id'] ?? '');
            $status = strtolower((string)($pi['status'] ?? 'requires_payment_method'));

            $update = [
                'mp_payment_id' => $piId ?: null, // reutilizando coluna existente para ID do PaymentIntent
                'status' => $status,
            ];
            $this->generalModel->update('workz_apps', 'workz_payments_transactions', $update, [ 'id' => $txId ]);

            if ($status === 'succeeded') {
                $this->grantAppService($userId, $appId, $companyId, $supportDays ?? $this->resolveSupportDays($clientMeta, 30));
            }

            echo json_encode([
                'success' => true,
                'transaction_id' => (int)$txId,
                'payment_intent_id' => $piId,
                'status' => $status,
                'client_secret' => $pi['client_secret'] ?? null,
                'response' => $pi,
                'confirmation' => $confirmServerSide ? 'server' : 'client',
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Falha ao processar pagamento. Tente novamente ou entre em contato com o suporte.',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/payments/transactions/{id}/resume
     * Re-obtains init_point for a pending/created transaction, or recreates preference if needed.
     */
        public function resumeTransaction(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        http_response_code(410);
        echo json_encode(['error' => 'Fluxo desativado. Crie nova cobrança via Stripe.']);
    }

    /**
     * POST /api/payments/transactions/{id}/cancel
     * Marks a transaction as cancelled locally.
     */
    public function cancelTransaction(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $tx = $this->generalModel->search('workz_apps', 'workz_payments_transactions', ['id','user_id','company_id','status'], ['id' => $id], false);
            if (!$tx) { http_response_code(404); echo json_encode(['error' => 'Transação não encontrada']); return; }
            $ownerId = (int)($tx['user_id'] ?? 0);
            $companyId = isset($tx['company_id']) ? (int)$tx['company_id'] : null;
            if ($ownerId !== $userId) {
                if ($companyId) {
                    $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                } else {
                    http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
                }
            }

            $this->generalModel->update('workz_apps', 'workz_payments_transactions', ['status' => 'cancelled'], ['id' => $id]);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao cancelar transação', 'details' => $e->getMessage()]);
        }
    }

    public function getStripePublicKey(): void
    {
        header('Content-Type: application/json');
        $pk = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '';
        if (!$pk) { http_response_code(404); echo json_encode(['error' => 'Public key not set']); return; }
        echo json_encode(['publishable_key' => $pk]);
    }

    private function ensureStripeCustomerId(int $userId): string
    {
        $email = null; $name = null; $customerId = null;
        try {
            $user = $this->generalModel->search('workz_data', 'hus', ['ml','tt','stripe_customer_id'], ['id' => $userId], false);
            $email = $user['ml'] ?? '';
            $name = $user['tt'] ?? '';
            if (!empty($user['stripe_customer_id'])) {
                return (string)$user['stripe_customer_id'];
            }
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'stripe_customer_id') === false) {
                throw $e;
            }
            $user = $this->generalModel->search('workz_data', 'hus', ['ml','tt'], ['id' => $userId], false);
            $email = $user['ml'] ?? '';
            $name = $user['tt'] ?? '';
        }
        if (!$email) {
            throw new \RuntimeException('Email do usuário é obrigatório para Stripe');
        }

        $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
        $cust = $stripe->createCustomer((string)$email, (string)$name);
        $cid = (string)($cust['id'] ?? '');
        if ($cid === '') {
            throw new \RuntimeException('Falha ao criar cliente no Stripe (id ausente)');
        }
        try {
            $this->generalModel->update('workz_data', 'hus', ['stripe_customer_id' => $cid, 'ml' => $email], ['id' => $userId]);
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'stripe_customer_id') === false) {
                throw $e;
            }
        }
        return $cid;
    }

    /**
     * Garante customer Stripe para entidade (user/business).
     */
    private function ensureStripeCustomerForEntity(string $entityType, int $entityId, ?int $authUserId = null): string
    {
        if ($entityType === 'business') {
            $email = null; $name = null; $cid = null;
            try {
                $company = $this->generalModel->search('workz_companies', 'companies', ['id','tt','ml','email','contact_email','stripe_customer_id'], ['id' => $entityId], false);
                $email = $company['ml'] ?? ($company['email'] ?? ($company['contact_email'] ?? ''));
                $name = $company['tt'] ?? '';
                if (!empty($company['stripe_customer_id'])) {
                    return (string)$company['stripe_customer_id'];
                }
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'stripe_customer_id') === false) {
                    throw $e;
                }
                $company = $this->generalModel->search('workz_companies', 'companies', ['id','tt','ml','email','contact_email'], ['id' => $entityId], false);
                $email = $company['ml'] ?? ($company['email'] ?? ($company['contact_email'] ?? ''));
                $name = $company['tt'] ?? '';
            }
            if (!$email && $authUserId) {
                try {
                    $owner = $this->generalModel->search('workz_data', 'hus', ['ml'], ['id' => $authUserId], false);
                    $email = $owner['ml'] ?? $email;
                } catch (\Throwable $e) { /* ignore */ }
            }
            if (!$email) { throw new \RuntimeException('Email da empresa é obrigatório para Stripe'); }
            $stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
            $cust = $stripe->createCustomer((string)$email, (string)$name);
            $cid = (string)($cust['id'] ?? '');
            if ($cid === '') { throw new \RuntimeException('Falha ao criar cliente no Stripe (id ausente)'); }
            try { $this->generalModel->update('workz_companies', 'companies', ['stripe_customer_id' => $cid], ['id' => $entityId]); }
            catch (\Throwable $e) { if (stripos($e->getMessage(), 'stripe_customer_id') === false) { throw $e; } }
            return $cid;
        }
        return $this->ensureStripeCustomerId($entityId);
    }

    /**
     * Extract support/activation period in days from a metadata payload.
     */
    private function resolveSupportDays($meta, ?int $default = 30): ?int
    {
        $arr = [];
        if (is_array($meta)) {
            $arr = $meta;
        } elseif (is_string($meta) && $meta !== '') {
            $arr = json_decode($meta, true) ?: [];
        }
        $days = isset($arr['support_days']) ? (int)$arr['support_days'] : null;
        if ($days === null && isset($arr['service_days'])) {
            $days = (int)$arr['service_days'];
        }
        if ($days !== null && $days <= 0) {
            $days = null;
        }
        if ($days === null) {
            return $default;
        }
        return $days;
    }

    /**
     * Ensure an app entitlement is active, updating start/end dates for prepaid periods.
     */
    private function grantAppService(int $userId, int $appId, ?int $companyId, ?int $supportDays = null): void
    {
        $supportDays = ($supportDays !== null) ? max(1, (int)$supportDays) : null;
        $existing = $this->generalModel->search(
            'workz_apps',
            'gapp',
            ['id','us','em','ap','subscription','st','start_date','end_date'],
            [ 'us' => $userId, 'ap' => $appId ],
            false
        );

        $today = new \DateTimeImmutable('today');
        $baseStart = $today;
        if ($supportDays !== null && $existing && !empty($existing['end_date'])) {
            try {
                $currentEnd = new \DateTimeImmutable($existing['end_date']);
                if ($currentEnd >= $today) {
                    // Empilha períodos sem perder tempo já pago
                    $baseStart = $currentEnd->modify('+1 day');
                }
            } catch (\Throwable $e) { /* ignore date parse */ }
        }

        $startDate = $supportDays !== null ? $baseStart->format('Y-m-d') : $today->format('Y-m-d');
        $endDate = null;
        if ($supportDays !== null) {
            $endDate = $baseStart->modify('+' . $supportDays . ' days')->format('Y-m-d');
        }

        $data = [ 'st' => 1, 'subscription' => 1 ];
        if ($supportDays !== null) {
            $data['start_date'] = $startDate;
            $data['end_date'] = $endDate;
        } elseif ($existing && empty($existing['start_date'])) {
            $data['start_date'] = $startDate;
        } elseif (!$existing) {
            $data['start_date'] = $startDate;
        }
        if ($companyId) {
            $data['em'] = $companyId;
        }

        try {
            if ($existing && isset($existing['id'])) {
                $this->generalModel->update('workz_apps', 'gapp', $data, [ 'id' => (int)$existing['id'] ]);
            } else {
                $insertData = array_merge([
                    'us' => $userId,
                    'ap' => $appId,
                ], $data);
                if ($companyId) {
                    $insertData['em'] = $companyId;
                }
                $this->generalModel->insert('workz_apps', 'gapp', $insertData);
            }
        } catch (\Throwable $e) {
            // Fallback para ambientes sem coluna end_date (migration não aplicada ainda)
            $safeData = $data;
            unset($safeData['end_date']);
            try {
                if ($existing && isset($existing['id'])) {
                    $this->generalModel->update('workz_apps', 'gapp', $safeData, [ 'id' => (int)$existing['id'] ]);
                } else {
                    $insertData = array_merge([
                        'us' => $userId,
                        'ap' => $appId,
                    ], $safeData);
                    if ($companyId) {
                        $insertData['em'] = $companyId;
                    }
                    $this->generalModel->insert('workz_apps', 'gapp', $insertData);
                }
            } catch (\Throwable $e2) {
                // ultima tentativa: não deixar 500 quebrar o fluxo de pagamento
            }
        }
    }

    /**
     * Helper to activate entitlement from a transaction id (reads metadata to define support days).
     */
    private function activateFromTransactionId(int $transactionId): void
    {
        $tx = $this->generalModel->search(
            'workz_apps',
            'workz_payments_transactions',
            ['app_id','user_id','company_id','metadata'],
            ['id' => $transactionId],
            false
        );
        if (!$tx) { return; }
        $supportDays = $this->resolveSupportDays($tx['metadata'] ?? null, 30);
        $userId = (int)($tx['user_id'] ?? 0);
        $appId = (int)($tx['app_id'] ?? 0);
        $companyId = isset($tx['company_id']) ? (int)$tx['company_id'] : null;
        if ($userId > 0 && $appId > 0) {
            $this->grantAppService($userId, $appId, $companyId, $supportDays);
        }
    }

    private function handleStripePaymentIntent(array $pi, string $status): void
    {
        $metadata = $pi['metadata'] ?? [];
        $txId = isset($metadata['workz_transaction_id']) ? (int)$metadata['workz_transaction_id'] : 0;
        if ($txId <= 0) { return; }

        $this->generalModel->update('workz_apps', 'workz_payments_transactions', [
            'status' => $status,
            'mp_payment_id' => (string)($pi['id'] ?? null), // reaproveitando coluna
        ], [ 'id' => $txId ]);

        if ($status === 'succeeded') {
            $this->activateFromTransactionId($txId);
        }
    }

    /**
     * Fallback: criar preferência de pagamento (redirect) quando charge direto falhar.
     */
    private function createPaymentPreferenceFallback(array $app, int $txId, float $amount, string $currency, int $userId, ?int $companyId, ?int $supportDays, array $clientMeta): ?array
    {
        return null;
    }

    /**
     * Loga o payload enviado ao MP (token mascarado) para depuração.
     */
    private function logPaymentPayload(string $label, array $payload): void
    {
        try {
            $sanitized = $payload;
            if (isset($sanitized['token'])) {
                $tok = (string)$sanitized['token'];
                $sanitized['token'] = substr($tok, 0, 6) . '...' . substr($tok, -4);
            }
            $msg = '[' . $label . '] ' . json_encode($sanitized);
            error_log($msg);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Controla debug detalhado via env MP_DEBUG_CHARGE (true/1/on).
     */
    private function isChargeDebugEnabled(): bool
    {
        return filter_var($_ENV['MP_DEBUG_CHARGE'] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * Retorna payload sem token em claro.
     */
    private function sanitizePayload(array $payload): array
    {
        $sanitized = $payload;
        if (isset($sanitized['token'])) {
            $tok = (string)$sanitized['token'];
            $sanitized['token'] = substr($tok, 0, 6) . '...' . substr($tok, -4);
        }
        return $sanitized;
    }

    /**
     * Mascarar token/credencial.
     */
    private function maskToken(string $tok): string
    {
        return $tok ? substr($tok, 0, 6) . '...' . substr($tok, -4) : 'empty';
    }
}
