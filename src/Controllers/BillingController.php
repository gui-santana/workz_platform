<?php
// src/Controllers/BillingController.php

namespace Workz\Platform\Controllers;

use Workz\Platform\Controllers\Traits\AuthorizationTrait;
use Workz\Platform\Models\General;
use Workz\Platform\Services\StripeClient;

class BillingController
{
    use AuthorizationTrait;

    private General $db;
    private ?StripeClient $stripe = null;

    public function __construct()
    {
        $this->db = new General();
        try {
            $this->stripe = new StripeClient($_ENV['STRIPE_SECRET_KEY'] ?? '');
        } catch (\Throwable $e) { $this->stripe = null; }
    }

    // ---------------- Payment Methods (Stripe) -----------------
    public function listStripePaymentMethods(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $entity = $_GET['entity'] ?? 'user';
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if ($entity === 'business') {
                if ($id <= 0) { http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return; }
                $this->authorize('business.manage_billing', ['em' => $id], $auth);
            } else {
                $entity = 'user';
                $id = $userId;
            }

            $rows = $this->db->search('workz_data', 'billing_payment_methods',
                ['id','entity_type','entity_id','provider','pm_type','status','label','brand','last4','exp_month','exp_year','token_ref','is_default','created_at','updated_at'],
                ['entity_type' => $entity, 'entity_id' => $id, 'provider' => 'stripe', 'status' => 1],
                true,
                100,
                0,
                ['by' => 'is_default', 'dir' => 'DESC']
            );
            echo json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao listar métodos', 'details' => $e->getMessage()]);
        }
    }

    public function createStripeSetupIntent(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            if (!$this->stripe) { http_response_code(500); echo json_encode(['error' => 'Stripe não configurado']); return; }
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $emailOverride = isset($in['email']) ? trim((string)$in['email']) : null;
            $nameOverride = isset($in['name']) ? trim((string)$in['name']) : null;
            $entityType = $in['entity_type'] ?? $in['entityType'] ?? 'user';
            $entityId = isset($in['entity_id']) ? (int)$in['entity_id'] : (isset($in['entityId']) ? (int)$in['entityId'] : $userId);
            if ($entityType === 'business') {
                if ($entityId <= 0) { http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return; }
                $this->authorize('business.manage_billing', ['em' => $entityId], $auth);
            } else { $entityType = 'user'; $entityId = $userId; }

            $customerId = $this->ensureStripeCustomerForEntity($entityType, $entityId, $emailOverride, $nameOverride, $userId);
            $si = $this->stripe->createSetupIntent($customerId, ['card']);
            echo json_encode([
                'success' => true,
                'client_secret' => $si['client_secret'] ?? null,
                'customer_id' => $customerId,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao criar SetupIntent', 'details' => $e->getMessage()]);
        }
    }

    public function createStripePaymentMethod(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            if (!$this->stripe) { http_response_code(500); echo json_encode(['error' => 'Stripe não configurado']); return; }
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $paymentMethodId = trim((string)($in['payment_method_id'] ?? ''));
            $setDefault = !empty($in['is_default']);
            $emailOverride = isset($in['email']) ? trim((string)$in['email']) : null;
            $nameOverride = isset($in['name']) ? trim((string)$in['name']) : null;
            $customerIdOverride = isset($in['customer_id']) ? trim((string)$in['customer_id']) : null;
            $entityType = $in['entity_type'] ?? $in['entityType'] ?? 'user';
            $entityId = isset($in['entity_id']) ? (int)$in['entity_id'] : (isset($in['entityId']) ? (int)$in['entityId'] : $userId);

            if ($entityType === 'business') {
                if ($entityId <= 0) {
                    http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
                }
                $this->authorize('business.manage_billing', ['em' => $entityId], $auth);
            } else {
                $entityType = 'user';
                $entityId = $userId;
            }

            if ($paymentMethodId === '') { http_response_code(400); echo json_encode(['error' => 'payment_method_id é obrigatório']); return; }

            $customerId = $customerIdOverride ?: $this->ensureStripeCustomerForEntity($entityType, $entityId, $emailOverride, $nameOverride, $userId);

            // Obter dados do payment_method para saber se já está anexado
            $pm = $this->stripe->retrievePaymentMethod($paymentMethodId);
            $attachedTo = $pm['customer'] ?? null;
            if ($attachedTo) {
                if ((string)$attachedTo !== (string)$customerId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Este cartão já está vinculado a outro cliente. Gere um novo cartão/PaymentMethod.']);
                    return;
                }
                // já anexado ao nosso customer, segue
            } else {
                $pm = $this->stripe->attachPaymentMethod($paymentMethodId, $customerId);
            }

            if ($setDefault) {
                try { $this->stripe->updateCustomerDefaultPaymentMethod($customerId, $paymentMethodId); } catch (\Throwable $e) { /* ignore */ }
            }

            $details = $pm ?: $this->stripe->retrievePaymentMethod($paymentMethodId);
            $brand = (string)($details['card']['brand'] ?? '');
            $last4 = (string)($details['card']['last4'] ?? '');
            $expMonth = isset($details['card']['exp_month']) ? (int)$details['card']['exp_month'] : null;
            $expYear  = isset($details['card']['exp_year']) ? (int)$details['card']['exp_year'] : null;

            // Idempotência local: se já existir token_ref para o usuário, reativa
                $existing = $this->db->search('workz_data', 'billing_payment_methods', ['id'], [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'provider' => 'stripe',
                    'token_ref' => $paymentMethodId
                ], false);

                if ($existing && isset($existing['id'])) {
                    $id = (int)$existing['id'];
                    $dataUpdate = [
                        'status' => 1,
                    'label' => ($brand && $last4) ? ($brand . ' •••• ' . $last4) : ($brand ?: null),
                    'brand' => $brand ?: null,
                    'last4' => $last4 ?: null,
                    'exp_month' => $expMonth,
                    'exp_year' => $expYear,
                    'stripe_customer_id' => $customerId,
                    'is_default' => $setDefault ? 1 : 0,
                ];
                try {
                    $this->db->update('workz_data', 'billing_payment_methods', $dataUpdate, ['id' => $id]);
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'stripe_customer_id') !== false) {
                        unset($dataUpdate['stripe_customer_id']);
                        $this->db->update('workz_data', 'billing_payment_methods', $dataUpdate, ['id' => $id]);
                    } else {
                        throw $e;
                    }
                }
            } else {
                $dataInsert = [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'provider' => 'stripe',
                    'pm_type' => 'card',
                    'status' => 1,
                    'label' => ($brand && $last4) ? ($brand . ' •••• ' . $last4) : ($brand ?: null),
                    'brand' => $brand ?: null,
                    'last4' => $last4 ?: null,
                    'exp_month' => $expMonth,
                    'exp_year' => $expYear,
                    'token_ref' => $paymentMethodId,
                    'stripe_customer_id' => $customerId,
                    'is_default' => $setDefault ? 1 : 0,
                ];
                try {
                    $id = $this->db->insert('workz_data', 'billing_payment_methods', $dataInsert);
                } catch (\Throwable $e) {
                    if (stripos($e->getMessage(), 'stripe_customer_id') !== false) {
                        unset($dataInsert['stripe_customer_id']);
                        $id = $this->db->insert('workz_data', 'billing_payment_methods', $dataInsert);
                    } else {
                        throw $e;
                    }
                }
            }

            if ($id && $setDefault) {
                $this->db->update('workz_data', 'billing_payment_methods', ['is_default' => 0], [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'provider' => 'stripe'
                ]);
                $this->db->update('workz_data', 'billing_payment_methods', ['is_default' => 1], ['id' => (int)$id]);
            }

            echo json_encode(['success' => true, 'id' => (int)$id, 'stripe_payment_method_id' => $paymentMethodId, 'stripe_customer_id' => $customerId]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao salvar método', 'details' => $e->getMessage()]);
        }
    }

    public function deleteStripePaymentMethod(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        try {
            if (!$this->stripe) { http_response_code(500); echo json_encode(['error' => 'Stripe não configurado']); return; }
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $pm = $this->db->search('workz_data', 'billing_payment_methods', ['id','entity_type','entity_id','provider','token_ref'], ['id' => $id], false);
            if (!$pm) { http_response_code(404); echo json_encode(['error' => 'Método não encontrado']); return; }
            if ($pm['entity_type'] === 'business') {
                $this->authorize('business.manage_billing', ['em' => (int)$pm['entity_id']], $auth);
            } elseif ((int)$pm['entity_id'] !== $userId) {
                http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
            }
            if ($pm['provider'] !== 'stripe') { http_response_code(400); echo json_encode(['error' => 'Método não é do Stripe']); return; }

            $pmId = (string)($pm['token_ref'] ?? '');
            if ($pmId !== '') {
                try { $this->stripe->detachPaymentMethod($pmId); } catch (\Throwable $e) { /* ignore */ }
            }
            $this->db->update('workz_data', 'billing_payment_methods', ['status' => 0, 'is_default' => 0], ['id' => $id]);
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao remover método', 'details' => $e->getMessage()]);
        }
    }

    public function updatePaymentMethod(object $auth, int $id): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }

            $pm = $this->db->search('workz_data', 'billing_payment_methods', ['id','entity_type','entity_id','provider','token_ref','stripe_customer_id'], ['id' => $id], false);
            if (!$pm) { http_response_code(404); echo json_encode(['error' => 'Método não encontrado']); return; }

            $entityType = (string)($pm['entity_type'] ?? 'user');
            $entityId = (int)($pm['entity_id'] ?? 0);
            if ($entityType === 'business') {
                if ($entityId <= 0) { http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return; }
                $this->authorize('business.manage_billing', ['em' => $entityId], $auth);
            } elseif ($entityId !== $userId) {
                http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return;
            }

            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $setDefault = !empty($body['isDefault']) || !empty($body['is_default']);

            // Apenas suporte a definir padrão por enquanto
            if ($setDefault) {
                // Zera outros padrões da mesma entidade/provedor
                $this->db->update('workz_data', 'billing_payment_methods', ['is_default' => 0], [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'provider' => $pm['provider'] ?? 'stripe'
                ]);
                $this->db->update('workz_data', 'billing_payment_methods', ['is_default' => 1], ['id' => $id]);

                // Se for Stripe e tivermos customer e pm id, ajusta default lá também
                if (($pm['provider'] ?? '') === 'stripe' && $this->stripe && !empty($pm['stripe_customer_id']) && !empty($pm['token_ref'])) {
                    try {
                        $this->stripe->updateCustomerDefaultPaymentMethod((string)$pm['stripe_customer_id'], (string)$pm['token_ref']);
                    } catch (\Throwable $e) { /* ignore Stripe fallback */ }
                }
            }

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao atualizar método', 'details' => $e->getMessage()]);
        }
    }

    // ---------------- Bank Accounts (business only) -----------------
    public function listBankAccounts(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $businessId = isset($_GET['business_id']) ? (int)$_GET['business_id'] : 0;
            if ($businessId <= 0) { http_response_code(403); echo json_encode(['error' => 'Sem permissão']); return; }
            $this->authorize('business.manage_billing', ['em' => $businessId], $auth);

            $rows = $this->db->search('workz_data', 'billing_bank_accounts',
                ['id','business_id','status','is_default','holder_name','document','bank_code','bank_name','branch','account_number','account_type','pix_key_type','pix_key','created_at','updated_at'],
                ['business_id' => $businessId, 'status' => ['op' => 'IN', 'value' => [1,0]]],
                true,
                100,
                0,
                ['by' => 'is_default', 'dir' => 'DESC']
            );
            echo json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao listar contas', 'details' => $e->getMessage()]);
        }
    }

    
    /**
     * Garante um customer do Stripe para o usuário e retorna o id.
     */
    private function ensureStripeCustomerId(int $userId, ?string $emailOverride = null, ?string $nameOverride = null): string
    {
        if (!$this->stripe) { throw new \RuntimeException('Stripe não configurado'); }
        $user = null;
        $email = null;
        $name = null;
        try {
            $user = $this->db->search('workz_data', 'hus', ['ml','tt','stripe_customer_id'], ['id' => $userId], false);
            $email = $emailOverride ?: ($user['ml'] ?? '');
            $name = $nameOverride ?: ($user['tt'] ?? '');
            if (!empty($user['stripe_customer_id'])) {
                return (string)$user['stripe_customer_id'];
            }
        } catch (\Throwable $e) {
            // Se coluna stripe_customer_id não existir, tentamos novamente sem ela
            if (stripos($e->getMessage(), 'stripe_customer_id') === false) {
                throw $e;
            }
            $user = $this->db->search('workz_data', 'hus', ['ml','tt'], ['id' => $userId], false);
            $email = $emailOverride ?: ($user['ml'] ?? '');
            $name = $nameOverride ?: ($user['tt'] ?? '');
        }

        if (!$user && !$email) {
            throw new \RuntimeException('Email do usuário é obrigatório para criar o cliente Stripe');
        }
        if ($email === '') {
            throw new \RuntimeException('Email do usuário é obrigatório para criar o cliente Stripe');
        }
        $cust = $this->stripe->createCustomer((string)$email, (string)$name);
        $cid = (string)($cust['id'] ?? '');
        if ($cid === '') {
            throw new \RuntimeException('Falha ao criar cliente no Stripe (id ausente)');
        }
        try {
            $this->db->update('workz_data', 'hus', ['stripe_customer_id' => $cid, 'ml' => $email], ['id' => $userId]);
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
    private function ensureStripeCustomerForEntity(string $entityType, int $entityId, ?string $emailOverride = null, ?string $nameOverride = null, ?int $authUserId = null): string
    {
        if ($entityType === 'business') {
            // Busca empresa
            $company = null;
            $email = $emailOverride;
            $name = $nameOverride;
            try {
                $company = $this->db->search('workz_companies', 'companies', ['id','tt','email','contact_email','stripe_customer_id'], ['id' => $entityId], false);
                $email = $email ?: ($company['ml'] ?? ($company['email'] ?? ($company['contact_email'] ?? '')));
                $name = $name ?: ($company['tt'] ?? '');
                if (!empty($company['stripe_customer_id'])) {
                    return (string)$company['stripe_customer_id'];
                }
            } catch (\Throwable $e) {
                if (!$email && stripos($e->getMessage(), 'stripe_customer_id') === false) {
                    throw $e;
                }
            }
            if (!$email && $authUserId) {
                try {
                    $owner = $this->db->search('workz_data', 'hus', ['ml'], ['id' => $authUserId], false);
                    $email = $owner['ml'] ?? $email;
                } catch (\Throwable $e) { /* ignore */ }
            }
            if (!$email) {
                throw new \RuntimeException('Email da empresa é obrigatório para Stripe');
            }
            $cust = $this->stripe->createCustomer((string)$email, (string)$name);
            $cid = (string)($cust['id'] ?? '');
            if ($cid === '') { throw new \RuntimeException('Falha ao criar cliente no Stripe (id ausente)'); }
            try {
                $this->db->update('workz_companies', 'companies', ['stripe_customer_id' => $cid], ['id' => $entityId]);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'stripe_customer_id') === false) { throw $e; }
            }
            return $cid;
        }
        return $this->ensureStripeCustomerId($entityId, $emailOverride, $nameOverride);
    }
}
