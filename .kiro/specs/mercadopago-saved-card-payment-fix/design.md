# Design Document

## Overview

Este documento descreve a solução para corrigir o fluxo de pagamento com cartões salvos no Mercado Pago. O problema atual é que o sistema gera tokens de cartão salvo referenciando um `customer_id` que não existe ou está desatualizado no Mercado Pago, resultando em erro "Customer not found" (HTTP 400, código 2002).

A solução envolve:
1. Garantir que o customer existe no MP antes de tokenizar cartões salvos
2. Validar e sincronizar customer_id antes de processar pagamentos
3. Implementar recuperação automática quando customer não é encontrado
4. Melhorar validações e mensagens de erro

## Architecture

### Fluxo Atual (Com Problema)

```
1. Usuário seleciona cartão salvo (pm_id) + fornece CVV
2. Sistema busca PM no banco → obtém mp_customer_id e mp_card_id
3. Sistema chama MP para tokenizar: POST /v1/card_tokens { customer_id, card_id, security_code }
4. MP retorna token (sucesso)
5. Sistema chama MP para pagamento: POST /v1/payments { token, payer: { email } }
6. MP retorna erro 400: "Customer not found" ❌
```

**Problema**: O token é gerado com sucesso, mas quando o pagamento é criado, o MP não consegue encontrar o customer referenciado no token.

### Fluxo Corrigido

```
1. Usuário seleciona cartão salvo (pm_id) + fornece CVV
2. Sistema busca PM no banco → obtém mp_customer_id e mp_card_id
3. Sistema VALIDA se customer existe no MP:
   a. Tenta buscar customer no MP
   b. Se não existe, cria novo customer com email do usuário
   c. Atualiza PM local com customer_id correto
4. Sistema tokeniza cartão: POST /v1/card_tokens { customer_id, card_id, security_code }
5. Sistema cria pagamento: POST /v1/payments { token, payer: { email } }
   NOTA: customer_id NÃO é incluído no payload de pagamento
   O token já contém a referência ao customer (embedded no token)
6. MP processa pagamento com sucesso ✓
```

## Components and Interfaces

### 1. PaymentsController::charge()

**Modificações necessárias**:
- Adicionar validação de PM quando `pm_id` é fornecido
- Garantir que customer existe antes de tokenizar
- **IMPORTANTE**: NÃO incluir `customer_id` no payload do pagamento
  - O customer_id já está embedded no token gerado
  - Incluir customer_id no payload causa erro 400 do Mercado Pago

**Nova lógica**:
```php
if ($pmId > 0) {
    // 1. Buscar PM e validar
    $pm = buscarPaymentMethod($pmId, $userId);
    validarPaymentMethod($pm);
    
    // 2. Garantir que customer existe
    $customerId = garantirCustomerExiste($pm, $userId);
    
    // 3. Atualizar PM se customer_id mudou
    if ($customerId !== $pm['mp_customer_id']) {
        atualizarCustomerIdNoPaymentMethod($pmId, $customerId);
    }
    
    // 4. Incluir customer_id no payload
    $payload['payer']['customer_id'] = $customerId;
}
```

### 2. BillingController::tokenizeSavedCard()

**Modificações necessárias**:
- Validar que customer existe antes de tokenizar
- Criar customer se não existir
- Atualizar PM local com customer_id correto

**Nova lógica**:
```php
// Antes de tokenizar
$customerId = garantirCustomerExiste($pm, $userId);

// Atualizar PM se necessário
if ($customerId !== $pm['mp_customer_id']) {
    atualizarPaymentMethod($pmId, ['mp_customer_id' => $customerId]);
}

// Tokenizar com customer_id validado
$token = $mp->createCardToken([
    'customer_id' => $customerId,
    'card_id' => $cardId,
    'security_code' => $cvv
]);
```

### 3. Nova Função: ensureCustomerExists()

**Responsabilidade**: Garantir que um customer existe no MP, criando se necessário

**Assinatura**:
```php
private function ensureCustomerExists(
    array $paymentMethod, 
    int $userId
): string
```

**Lógica**:
```php
1. Obter mp_customer_id do PM
2. Se não tem customer_id:
   a. Buscar email do usuário
   b. Buscar customer no MP por email
   c. Se não existe, criar novo
   d. Retornar customer_id
3. Se tem customer_id:
   a. Tentar buscar customer no MP
   b. Se não encontrado (404):
      - Buscar por email
      - Se não existe, criar novo
      - Retornar customer_id
   c. Se encontrado, retornar customer_id existente
```

### 4. MercadoPagoClient

**Novos métodos necessários**:

```php
public function getCustomer(string $customerId): ?array
{
    try {
        $url = 'https://api.mercadopago.com/v1/customers/' . urlencode($customerId);
        return $this->request('GET', $url);
    } catch (\Throwable $e) {
        // Se 404, retornar null
        if (strpos($e->getMessage(), '404') !== false) {
            return null;
        }
        throw $e;
    }
}
```

## Data Models

### billing_payment_methods

Campos relevantes:
- `mp_customer_id`: ID do customer no Mercado Pago (pode estar desatualizado)
- `mp_card_id`: ID do cartão no vault do MP
- `entity_type`: 'user' ou 'business'
- `entity_id`: ID do usuário ou empresa
- `status`: 1 = ativo, 0 = inativo

### Relacionamento com hus (usuários)

```
billing_payment_methods.entity_id → hus.id (quando entity_type = 'user')
hus.ml → email do usuário (necessário para criar/buscar customer)
```

## Error Handling

### Validações Pré-MP

1. **PM não encontrado**:
   - HTTP 404
   - `{ "error": "Método de pagamento não encontrado" }`

2. **PM inativo**:
   - HTTP 400
   - `{ "error": "Método de pagamento inativo" }`

3. **PM sem mp_customer_id ou mp_card_id**:
   - HTTP 400
   - `{ "error": "Método de pagamento incompleto. Por favor, adicione o cartão novamente." }`

4. **Usuário sem email**:
   - HTTP 400
   - `{ "error": "Email do usuário não encontrado. Atualize seu perfil." }`

5. **PM não pertence ao usuário**:
   - HTTP 403
   - `{ "error": "Sem permissão para usar este método de pagamento" }`

### Erros do Mercado Pago

1. **Customer not found (após tentativa de recuperação)**:
   - HTTP 500
   - `{ "error": "Falha ao validar customer no Mercado Pago", "details": "..." }`

2. **Card not found**:
   - HTTP 400
   - `{ "error": "Cartão não encontrado no Mercado Pago. Por favor, adicione o cartão novamente." }`

3. **Token inválido**:
   - HTTP 400
   - `{ "error": "Token de cartão inválido", "details": "..." }`

### Recuperação Automática

Quando `customer_id` não é encontrado:
1. Buscar customer por email
2. Se encontrado, atualizar PM local e continuar
3. Se não encontrado, criar novo customer
4. Atualizar PM local com novo customer_id
5. Continuar com o pagamento

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Token generation includes valid customer

*For any* saved card payment method with valid mp_customer_id and mp_card_id, when a card token is generated with CVV, the resulting token should reference a customer that exists in Mercado Pago
**Validates: Requirements 1.1, 1.5**

### Property 2: Payment payload includes customer_id for saved cards

*For any* payment request that includes a pm_id (saved card), the payment payload sent to Mercado Pago should include the customer_id in the payer object
**Validates: Requirements 1.2**

### Property 3: Approved payments grant app access

*For any* payment transaction that receives status "approved" from Mercado Pago, the system should update the gapp table to grant access (st = 1) for the corresponding user and app
**Validates: Requirements 1.4**

### Property 4: Customer existence is guaranteed before card save

*For any* new card being saved to the billing_payment_methods table, a valid customer must exist in Mercado Pago before the card is associated with that customer_id
**Validates: Requirements 2.1**

### Property 5: Customer validation occurs before payment

*For any* payment initiated with a pm_id, the system should verify that the associated mp_customer_id exists in Mercado Pago before creating the payment
**Validates: Requirements 2.2**

### Property 6: Customer creation updates local record

*For any* customer created in Mercado Pago, the corresponding billing_payment_methods record should be updated with the correct mp_customer_id
**Validates: Requirements 2.4**

### Property 7: Multiple cards share customer_id

*For any* user with multiple saved cards, all cards should reference the same mp_customer_id in Mercado Pago
**Validates: Requirements 2.5**

### Property 8: Payment method validation before processing

*For any* payment request with pm_id, the system should validate that the payment method exists, is active (status = 1), and belongs to the requesting user before attempting to process the payment
**Validates: Requirements 4.1**

## Testing Strategy

### Unit Tests

1. **Test: ensureCustomerExists com customer válido**
   - Given: PM com mp_customer_id válido
   - When: ensureCustomerExists é chamado
   - Then: Retorna o mesmo customer_id sem criar novo

2. **Test: ensureCustomerExists com customer inválido**
   - Given: PM com mp_customer_id que não existe no MP
   - When: ensureCustomerExists é chamado
   - Then: Busca por email, encontra customer, retorna ID correto

3. **Test: ensureCustomerExists sem customer**
   - Given: PM sem mp_customer_id
   - When: ensureCustomerExists é chamado
   - Then: Busca por email, cria se necessário, retorna customer_id

4. **Test: charge com pm_id válido**
   - Given: pm_id de cartão salvo ativo
   - When: charge é chamado com token
   - Then: Pagamento inclui customer_id no payload

5. **Test: tokenizeSavedCard com customer inválido**
   - Given: PM com customer_id inválido
   - When: tokenizeSavedCard é chamado
   - Then: Customer é validado/criado antes de tokenizar

### Integration Tests

1. **Test: Fluxo completo de pagamento com cartão salvo**
   - Adicionar cartão → Tokenizar com CVV → Pagar → Verificar aprovação

2. **Test: Recuperação de customer não encontrado**
   - Simular customer_id inválido → Verificar criação de novo customer → Pagamento bem-sucedido

3. **Test: Múltiplos cartões para mesmo usuário**
   - Adicionar 2 cartões → Verificar que usam mesmo customer_id

## Implementation Notes

### Descoberta Importante: customer_id no Payload de Pagamento

**CRÍTICO**: O campo `payer.customer_id` NÃO deve ser incluído no payload de pagamento (`POST /v1/payments`).

**Razão**: 
- Quando um token é gerado usando `customer_id` + `card_id` + `security_code`, o token já contém a referência ao customer
- O Mercado Pago rejeita requisições que incluem `payer.customer_id` com erro 400: "The name of the following parameters is wrong : [payer.customer_id]"
- O customer_id é usado apenas na geração do token, não no pagamento

**Fluxo correto**:
1. Validar que customer existe no MP
2. Gerar token com: `{ customer_id, card_id, security_code }`
3. Criar pagamento com: `{ token, payer: { email } }` (SEM customer_id)

### Ordem de Implementação

1. Adicionar método `getCustomer()` em `MercadoPagoClient`
2. Implementar `ensureCustomerExists()` em `PaymentsController`
3. Modificar `tokenizeSavedCard()` para validar customer
4. Modificar `charge()` para incluir customer_id quando usar pm_id
5. Adicionar validações de PM
6. Melhorar mensagens de erro
7. Adicionar testes

### Considerações de Performance

- Cache de customer lookups pode ser implementado futuramente
- Validação de customer adiciona 1 request extra ao MP, mas previne falhas
- Considerar batch validation se múltiplos PMs forem usados simultaneamente

### Backward Compatibility

- PMs existentes sem mp_customer_id serão automaticamente corrigidos
- Fluxo sem pm_id (novo cartão) permanece inalterado
- Nenhuma migration de banco necessária
