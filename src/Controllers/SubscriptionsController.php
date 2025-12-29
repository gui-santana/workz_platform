<?php

namespace Workz\Platform\Controllers;

use Workz\Platform\Models\General;
use Workz\Platform\Services\MercadoPagoClient;
use Workz\Platform\Controllers\Traits\AuthorizationTrait;

class SubscriptionsController
{
    use AuthorizationTrait;

    private General $db;

    public function __construct()
    {
        $this->db = new General();
    }

    /**
     * POST /api/subscriptions/plans
     * Body: { app_id, amount, currency?, frequency?, frequency_type? }
     */
    public function createPlan(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $appId = (int)($in['app_id'] ?? 0);
            $amount = (float)($in['amount'] ?? 0);
            $currency = (string)($in['currency'] ?? 'BRL');
            $frequency = (int)($in['frequency'] ?? 1);
            $frequencyType = (string)($in['frequency_type'] ?? 'months');
            if ($appId <= 0 || $amount <= 0) { http_response_code(400); echo json_encode(['error' => 'Parâmetros inválidos']); return; }

            $app = $this->db->search('workz_apps', 'apps', ['id','tt','slug'], ['id' => $appId], false);
            if (!$app) { http_response_code(404); echo json_encode(['error' => 'App não encontrado']); return; }

            // Check existing
            $existing = $this->db->search('workz_apps', 'workz_payments_plans', ['*'], [
                'app_id' => $appId,
                'amount' => $amount,
                'frequency' => $frequency,
                'frequency_type' => $frequencyType,
            ], false);
            if ($existing && !empty($existing['mp_plan_id'])) {
                echo json_encode(['success' => true, 'plan' => $existing]);
                return;
            }

            // Unificado: usar somente MP_ACCESS_TOKEN
            $mp = new MercadoPagoClient($_ENV['MP_ACCESS_TOKEN'] ?? '');
            $reason = ($app['tt'] ?? 'Workz App') . ' - Assinatura';
            $publicBase = rtrim($_ENV['APP_URL'] ?? '', '/');
            $backUrl = $publicBase ? ($publicBase . '/apps') : null;
            $payload = [
                'reason' => $reason,
                'auto_recurring' => [
                    'frequency' => $frequency,
                    'frequency_type' => $frequencyType,
                    'transaction_amount' => $amount,
                    'currency_id' => $currency,
                ],
            ];
            if ($backUrl) { $payload['back_url'] = $backUrl; }

            $planResp = $mp->createPreapprovalPlan($payload);
            $mpPlanId = (string)($planResp['id'] ?? '');
            if ($mpPlanId === '') { http_response_code(500); echo json_encode(['error' => 'Falha ao criar plano']); return; }

            $id = $this->db->insert('workz_apps', 'workz_payments_plans', [
                'app_id' => $appId,
                'reason' => $reason,
                'amount' => $amount,
                'currency' => $currency,
                'frequency' => $frequency,
                'frequency_type' => $frequencyType,
                'mp_plan_id' => $mpPlanId,
                'status' => 'active',
            ]);
            echo json_encode(['success' => true, 'plan' => [ 'id' => $id, 'mp_plan_id' => $mpPlanId ]]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao criar plano', 'details' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/subscriptions/subscribe
     * Body: { app_id, plan_id?, company_id? }
     * Returns { init_point, preapproval_id? }
     */
    public function subscribe(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $appId = (int)($in['app_id'] ?? 0);
            $planId = isset($in['plan_id']) ? (int)$in['plan_id'] : null;
            $companyId = isset($in['company_id']) ? (int)$in['company_id'] : null;
            $cardToken = isset($in['token']) ? (string)$in['token'] : '';
            if ($companyId) {
                $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
            }
            $pmId = isset($in['pm_id']) ? (int)$in['pm_id'] : 0; // método salvo local
            if ($appId <= 0) { http_response_code(400); echo json_encode(['error' => 'app_id inválido']); return; }

            $app = $this->db->search('workz_apps', 'apps', ['id','tt'], ['id' => $appId], false);
            if (!$app) { http_response_code(404); echo json_encode(['error' => 'App não encontrado']); return; }

            // Resolve plan (find for app if not provided)
            if (!$planId) {
                $plan = $this->db->search('workz_apps', 'workz_payments_plans', ['id'], ['app_id' => $appId, 'status' => 'active'], false);
                if ($plan) $planId = (int)$plan['id'];
            }
            if (!$planId) { http_response_code(400); echo json_encode(['error' => 'Plano não encontrado para o app']); return; }

            $plan = $this->db->search('workz_apps', 'workz_payments_plans', ['*'], ['id' => $planId], false);
            if (!$plan || empty($plan['mp_plan_id'])) { http_response_code(404); echo json_encode(['error' => 'Plano inválido']); return; }

            // Create subscription row first
            $subId = $this->db->insert('workz_apps', 'workz_payments_subscriptions', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'app_id' => $appId,
                'plan_id' => $planId,
                'status' => 'pending',
                'start_date' => date('Y-m-d'),
            ]);
            if (!$subId) { http_response_code(500); echo json_encode(['error' => 'Falha ao registrar assinatura']); return; }

            // Create preapproval
            $user = $this->db->search('workz_data', 'hus', ['ml','tt'], ['id' => $userId], false);
            $email = (string)($user['ml'] ?? '');
            if ($email === '') { http_response_code(400); echo json_encode(['error' => 'E-mail do usuário é obrigatório para assinatura.']); return; }
            // Client para todas as chamadas MP neste fluxo (unificado)
            $mp = new MercadoPagoClient($_ENV['MP_ACCESS_TOKEN'] ?? '');
            $reason = $plan['reason'] ?? ($app['tt'] . ' - Assinatura');
            // Priorize token quando presente (ignora pm_id se token existir)
            if ($cardToken !== '') {
                // Fluxo direto com cartão salvo/tokenizado: cria preapproval com token + auto_recurring + customer
                $payload = [
                    'reason' => $reason,
                    'external_reference' => 'sub:' . $subId,
                    'payer_email' => $email,
                    'card_token_id' => $cardToken,
                    'auto_recurring' => [
                        'frequency' => (int)$plan['frequency'],
                        'frequency_type' => (string)$plan['frequency_type'],
                        'transaction_amount' => (float)$plan['amount'],
                        'currency_id' => (string)$plan['currency'],
                    ],
                    'back_url' => (rtrim($_ENV['APP_URL'] ?? '', '/') . '/apps')
                ];
                // Vincular ao customer quando possível aumenta a taxa de sucesso
                // Se tivermos pm_id, use o mp_customer_id do método salvo; caso contrário, busque por email
                if ($pmId > 0) {
                    try {
                        $pm = $this->db->search('workz_data', 'billing_payment_methods', ['mp_customer_id'], ['id' => $pmId], false);
                        if ($pm && !empty($pm['mp_customer_id'])) {
                            $payload['payer'] = [ 'type' => 'customer', 'id' => (string)$pm['mp_customer_id'] ];
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
                if (!isset($payload['payer'])) {
                    try {
                        $cust = $mp->searchCustomerByEmail($email);
                        if ($cust && !empty($cust['id'])) {
                            $payload['payer'] = [ 'type' => 'customer', 'id' => (string)$cust['id'] ];
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
            } elseif ($pmId > 0) {
                // Fluxo com cartão salvo sem token (cart_id)
                $pm = $this->db->search('workz_data', 'billing_payment_methods', ['id','mp_customer_id','mp_card_id'], ['id' => $pmId], false);
                if (!$pm || empty($pm['mp_card_id'])) { http_response_code(400); echo json_encode(['error' => 'Método de pagamento inválido']); return; }
                $custId = (string)($pm['mp_customer_id'] ?? '');
                $cardId = (string)($pm['mp_card_id'] ?? '');

                $payload = [
                    'preapproval_plan_id' => (string)$plan['mp_plan_id'],
                    'reason' => $reason,
                    'external_reference' => 'sub:' . $subId,
                    'payer_email' => $email,
                    'card_id' => $cardId,
                ];
                if ($custId !== '') { $payload['payer'] = [ 'type' => 'customer', 'id' => $custId ]; }
                // NOTA: se a conta exigir token, o MP retornará 400 e o catch final devolverá o detalhe
            } else {
                // Fluxo com redirect/init_point usando o plan_id
                $payload = [
                    'preapproval_plan_id' => (string)$plan['mp_plan_id'],
                    'reason' => $reason,
                    'external_reference' => 'sub:' . $subId,
                    'payer_email' => $email,
                    'back_url' => (rtrim($_ENV['APP_URL'] ?? '', '/') . '/apps')
                ];
            }

            $idemKey = 'workz-sub-' . $subId;
            try {
                $pre = $mp->createPreapproval($payload, $idemKey);
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                // Fallback: se token não for aceito (404/"Card token service not found" ou 400 genérico),
                // tente com card_id quando disponível (pm_id informado)
                if ($cardToken !== '' && $pmId > 0 && (
                        stripos($msg, 'Card token service not found') !== false ||
                        stripos($msg, 'HTTP 404') !== false ||
                        stripos($msg, 'HTTP 400') !== false ||
                        stripos($msg, 'invalid') !== false
                    )) {
                    try {
                        $pm = $this->db->search('workz_data', 'billing_payment_methods', ['id','mp_customer_id','mp_card_id'], ['id' => $pmId], false);
                        if (!$pm || empty($pm['mp_card_id'])) { throw new \RuntimeException('Método inválido para fallback.'); }
                        $custId = (string)($pm['mp_customer_id'] ?? '');
                        $cardId = (string)($pm['mp_card_id'] ?? '');
                        $payloadFallback = [
                            'preapproval_plan_id' => (string)$plan['mp_plan_id'],
                            'reason' => $reason,
                            'external_reference' => 'sub:' . $subId,
                            'payer_email' => $email,
                            'card_id' => $cardId,
                        ];
                        if ($custId !== '') { $payloadFallback['payer'] = [ 'type' => 'customer', 'id' => $custId ]; }
                        $pre = $mp->createPreapproval($payloadFallback, $idemKey . '-fallback-card');
                    } catch (\Throwable $e2) {
                        // Última tentativa: auto_recurring + card_id
                        try {
                            $pm = $pm ?? $this->db->search('workz_data', 'billing_payment_methods', ['id','mp_customer_id','mp_card_id'], ['id' => $pmId], false);
                            $custId = (string)($pm['mp_customer_id'] ?? '');
                            $cardId = (string)($pm['mp_card_id'] ?? '');
                            $payloadAuto = [
                                'reason' => $reason,
                                'external_reference' => 'sub:' . $subId,
                                'payer_email' => $email,
                                'card_id' => $cardId,
                                'auto_recurring' => [
                                    'frequency' => (int)$plan['frequency'],
                                    'frequency_type' => (string)$plan['frequency_type'],
                                    'transaction_amount' => (float)$plan['amount'],
                                    'currency_id' => (string)$plan['currency'],
                                ],
                                'back_url' => (rtrim($_ENV['APP_URL'] ?? '', '/') . '/apps')
                            ];
                            if ($custId !== '') { $payloadAuto['payer'] = [ 'type' => 'customer', 'id' => $custId ]; }
                            $pre = $mp->createPreapproval($payloadAuto, $idemKey . '-fallback-auto');
                        } catch (\Throwable $e3) {
                            http_response_code(500);
                            echo json_encode(['error' => 'Falha ao iniciar assinatura', 'details' => $e3->getMessage()]);
                            return;
                        }
                    }
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Falha ao iniciar assinatura', 'details' => $e->getMessage()]);
                    return;
                }
            }
            $preId = (string)($pre['id'] ?? '');
            $init = $pre['init_point'] ?? null;
            $pStatus = strtolower((string)($pre['status'] ?? ''));

            if ($preId) {
                $statusToSave = $pStatus ?: 'pending';
                $this->db->update('workz_apps', 'workz_payments_subscriptions', [ 'mp_preapproval_id' => $preId, 'status' => $statusToSave ], [ 'id' => $subId ]);

                // Se já veio autorizado (token ou card_id), garantir entitlement imediato
                if ($pStatus === 'authorized') {
                    try {
                        $startDate = date('Y-m-d');
                        $existing = $this->db->search('workz_apps', 'gapp', ['id','us','em','ap','subscription','st','start_date'], [ 'us' => $userId, 'ap' => $appId ], false);
                        $upd = [ 'st' => 1, 'subscription' => 1 ];
                        if (empty($existing['start_date'])) { $upd['start_date'] = $startDate; }
                        if ($companyId) { $upd['em'] = $companyId; }
                        if ($existing && isset($existing['id'])) {
                            $this->db->update('workz_apps', 'gapp', $upd, [ 'id' => (int)$existing['id'] ]);
                        } else {
                            $this->db->insert('workz_apps', 'gapp', [ 'us' => $userId, 'em' => $companyId, 'ap' => $appId, 'st' => 1, 'subscription' => 1, 'start_date' => $startDate ]);
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
            }
            echo json_encode(['success' => true, 'subscription_id' => (int)$subId, 'preapproval_id' => $preId, 'status' => $pStatus, 'init_point' => $init]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao iniciar assinatura', 'details' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/subscriptions
     * Query: company_id?
     */
    public function list(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $conds = [];
            if ($companyId) {
                $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
                $conds = ['company_id' => $companyId];
            } else { $conds = ['user_id' => $userId]; }
            $rows = $this->db->search('workz_apps', 'workz_payments_subscriptions', ['*'], $conds, true, 100, 0, ['by' => 'id', 'dir' => 'DESC']);
            echo json_encode(['success' => true, 'data' => is_array($rows) ? $rows : []]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao listar assinaturas', 'details' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/subscriptions/cancel
     * Body: { app_id, company_id? }
     */
    public function cancel(object $auth): void
    {
        header('Content-Type: application/json');
        try {
            $userId = (int)($auth->sub ?? 0);
            if ($userId <= 0) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); return; }
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $appId = (int)($in['app_id'] ?? 0);
            $companyId = isset($in['company_id']) ? (int)$in['company_id'] : null;
            if ($appId <= 0) { http_response_code(400); echo json_encode(['error' => 'Parâmetros inválidos']); return; }
            if ($companyId) {
                $this->authorize('business.manage_billing', ['em' => $companyId], $auth);
            }

            // Find subscription row (most recent)
            $conds = [ 'user_id' => $userId, 'app_id' => $appId ];
            if ($companyId) { $conds['company_id'] = $companyId; }
            $sub = $this->db->search('workz_apps', 'workz_payments_subscriptions', ['*'], $conds, false, null, null, ['by' => 'id', 'dir' => 'DESC']);
            if (!$sub) { http_response_code(404); echo json_encode(['error' => 'Assinatura não encontrada']); return; }

            $mpId = (string)($sub['mp_preapproval_id'] ?? '');
            try {
                if ($mpId !== '') {
                    $mp = new MercadoPagoClient($_ENV['MP_ACCESS_TOKEN'] ?? '');
                    $mp->updatePreapproval($mpId, [ 'status' => 'cancelled' ]);
                }
            } catch (\Throwable $e) { /* ignore MP error and proceed to mark cancelled locally */ }

            // Update subscription + entitlement
            try { $this->db->update('workz_apps', 'workz_payments_subscriptions', [ 'status' => 'cancelled' ], [ 'id' => (int)$sub['id'] ]); } catch (\Throwable $e) {}
            try { $this->db->update('workz_apps', 'gapp', [ 'st' => 0 ], [ 'us' => $userId, 'ap' => $appId ]); } catch (\Throwable $e) {}

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Falha ao cancelar assinatura', 'details' => $e->getMessage()]);
        }
    }
}
