# Workz Payments (Mercado Pago) — Fase 1

Este guia descreve a configuração da fase 1 (compras avulsas via Checkout Pro).

## Variáveis de ambiente

Adicione ao `.env`:

- `MP_ACCESS_TOKEN` (obrigatório): Access Token da sua conta do Mercado Pago (produção ou sandbox)
- `MP_PUBLIC_KEY` (opcional): usada quando integrarmos Bricks/Wallet (não necessário para Checkout Pro via init_point)
- `MP_WEBHOOK_SECRET` (recomendado): segredo compartilhado para validar o webhook via query string
- `APP_URL` (recomendado): base URL pública (ex.: https://workz.example.com)

## Rotas

- POST `/api/payments/preference` (protegida): cria uma preferência
  - Body JSON: `{ app_id, title?, quantity?, unit_price?, currency?, company_id?, back_urls? }`
  - Resposta: `{ success, transaction_id, preference_id, init_point }`

- POST `/api/payments/webhook?secret=...` (pública): recebe eventos do Mercado Pago
  - Configure no painel do MP apontando para `APP_URL/api/payments/webhook?secret=MP_WEBHOOK_SECRET`

## Tabelas

Execute a migration: `database/migrations/2025_11_11_create_workz_payments_tables.sql`

Tabela principal: `workz_payments_transactions`

## Fluxo de compra avulsa

1. Frontend chama SDK: `WorkzSDK.payments.createPurchase({ appId, ... })`
2. Backend cria transação local e preference no MP; retorna `init_point`
3. App abre `init_point` (redireção/iframe)
4. Ao aprovar pagamento, o MP chama o webhook; backend verifica o pagamento e atualiza a transação
5. Se aprovado, o backend concede entitlement em `workz_apps.gapp` (libera o app para o usuário)

## SDK (v2)

Disponível após `WorkzSDK.init(...)`:

```
const { success, init_point } = await WorkzSDK.payments.createPurchase({
  appId: 1234,
  title: 'Meu App',
  unitPrice: 19.9,
  quantity: 1,
  currency: 'BRL',
  backUrls: {
    success: 'https://seuapp/sucesso',
    failure: 'https://seuapp/erro',
    pending: 'https://seuapp/pendente'
  }
});
if (success && init_point) {
  window.location.href = init_point; // ou abrir em modal/iframe
}
```

## Observações

- O webhook é idempotente via `external_reference` (txn:<id>) e usa GET ao MP para verificar o status.
- Entitlements: em aprovação, insere (se necessário) registro em `workz_apps.gapp` com `st=1`.
- Em ambientes com PHP < 8.0, o código evita funções exclusivas de PHP 8.
