# Requirements Document

## Introduction

Este documento especifica os requisitos para corrigir o fluxo de pagamento com cartões salvos no Mercado Pago. Atualmente, o sistema está falhando com erro "Customer not found" ao tentar processar pagamentos usando cartões previamente salvos, pois o customer_id referenciado no token não existe ou não está sendo corretamente associado ao pagamento.

## Glossary

- **Workz Platform**: A plataforma de aplicativos que integra pagamentos via Mercado Pago
- **MP**: Mercado Pago, provedor de pagamentos
- **Customer**: Entidade no Mercado Pago que representa um pagador e pode ter cartões salvos
- **Card Token**: Token temporário gerado pelo MP que representa um cartão para uso em um pagamento
- **Saved Card**: Cartão de crédito previamente tokenizado e armazenado no vault do MP
- **Payment Method (PM)**: Método de pagamento salvo localmente na tabela billing_payment_methods
- **CVV**: Código de segurança do cartão (Card Verification Value)

## Requirements

### Requirement 1

**User Story:** Como usuário da plataforma, eu quero pagar por um app usando um cartão salvo, para que eu não precise digitar os dados do cartão novamente

#### Acceptance Criteria

1. WHEN o usuário seleciona um cartão salvo e fornece o CVV THEN o sistema SHALL gerar um card token válido vinculado ao customer e card existentes
2. WHEN o sistema cria um pagamento com cartão salvo THEN o sistema SHALL incluir o customer_id no payload do pagamento
3. WHEN o customer não existe no MP mas existe localmente THEN o sistema SHALL criar o customer no MP antes de processar o pagamento
4. WHEN o pagamento é processado com sucesso THEN o sistema SHALL retornar o status approved e conceder acesso ao app
5. WHEN o token de cartão salvo é gerado THEN o sistema SHALL validar que o customer_id e card_id existem no MP

### Requirement 2

**User Story:** Como desenvolvedor, eu quero que o sistema sincronize automaticamente os customers entre o banco local e o Mercado Pago, para que não ocorram erros de "Customer not found"

#### Acceptance Criteria

1. WHEN um cartão é salvo pela primeira vez THEN o sistema SHALL garantir que o customer existe no MP antes de salvar o cartão
2. WHEN um pagamento é iniciado com pm_id THEN o sistema SHALL verificar se o customer_id associado existe no MP
3. IF o customer_id não existe no MP THEN o sistema SHALL criar o customer usando o email do usuário
4. WHEN o customer é criado no MP THEN o sistema SHALL atualizar o registro local com o mp_customer_id correto
5. WHEN múltiplos cartões são salvos para o mesmo usuário THEN o sistema SHALL reutilizar o mesmo customer_id do MP

### Requirement 3

**User Story:** Como administrador do sistema, eu quero logs detalhados dos erros de pagamento, para que eu possa diagnosticar problemas rapidamente

#### Acceptance Criteria

1. WHEN um erro "Customer not found" ocorre THEN o sistema SHALL registrar o customer_id que causou o erro
2. WHEN um pagamento falha THEN o sistema SHALL incluir detalhes completos da resposta do MP no log de erro
3. WHEN um token é gerado com sucesso THEN o sistema SHALL registrar o token_id e customer_id associados
4. WHEN o sistema tenta criar um customer THEN o sistema SHALL registrar se foi criado novo ou reutilizado existente
5. WHEN qualquer operação com MP falha THEN o sistema SHALL preservar a mensagem de erro original do MP

### Requirement 4

**User Story:** Como usuário, eu quero que o sistema valide meus dados antes de enviar ao Mercado Pago, para que eu receba feedback imediato sobre problemas

#### Acceptance Criteria

1. WHEN o usuário tenta pagar com pm_id THEN o sistema SHALL validar que o payment method existe e está ativo
2. WHEN o payment method não tem mp_customer_id THEN o sistema SHALL retornar erro claro antes de chamar o MP
3. WHEN o payment method não tem mp_card_id THEN o sistema SHALL retornar erro claro antes de chamar o MP
4. WHEN o CVV não é fornecido para cartão salvo THEN o sistema SHALL retornar erro de validação
5. WHEN o usuário não tem email cadastrado THEN o sistema SHALL retornar erro antes de tentar criar customer
