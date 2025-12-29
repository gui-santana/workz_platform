# Implementation Plan

- [x] 1. Add customer validation method to MercadoPagoClient





  - Add `getCustomer()` method to fetch customer by ID from Mercado Pago
  - Handle 404 responses gracefully (return null instead of throwing)
  - _Requirements: 1.5, 2.2_

- [x] 2. Implement ensureCustomerExists helper in PaymentsController





  - Create private method `ensureCustomerExists(array $pm, int $userId): string`
  - Check if mp_customer_id exists in payment method
  - If exists, validate customer exists in MP via getCustomer()
  - If customer not found in MP, search by email using searchCustomerByEmail()
  - If no customer found by email, create new customer with createCustomer()
  - Return validated/created customer_id
  - _Requirements: 1.3, 2.1, 2.3, 2.4_

- [ ]* 2.1 Write property test for ensureCustomerExists
  - **Property 4: Customer existence is guaranteed before card save**
  - **Validates: Requirements 2.1**

- [x] 3. Add payment method validation in PaymentsController




  - Create private method `validatePaymentMethod(array $pm, int $userId): void`
  - Check PM exists and status = 1
  - Check PM belongs to user (entity_type = 'user' and entity_id = userId)
  - Check mp_customer_id and mp_card_id are not empty
  - Throw appropriate exceptions with clear messages
  - _Requirements: 4.1, 4.2, 4.3_

- [ ]* 3.1 Write property test for payment method validation
  - **Property 8: Payment method validation before processing**
  - **Validates: Requirements 4.1**

- [x] 4. Modify PaymentsController::charge() to handle saved cards




  - When pm_id is provided, fetch payment method from database
  - Call validatePaymentMethod() to ensure PM is valid
  - Call ensureCustomerExists() to validate/create customer
  - If customer_id changed, update billing_payment_methods table
  - Include customer_id in payment payload: `$payload['payer']['customer_id'] = $customerId`
  - _Requirements: 1.2, 2.2, 2.4_

- [ ]* 4.1 Write property test for payment payload with customer_id
  - **Property 2: Payment payload includes customer_id for saved cards**
  - **Validates: Requirements 1.2**

- [ ]* 4.2 Write property test for approved payments granting access
  - **Property 3: Approved payments grant app access**
  - **Validates: Requirements 1.4**

- [x] 5. Modify BillingController::tokenizeSavedCard() to validate customer





  - Before calling createCardToken(), call ensureCustomerExists()
  - If customer_id changed, update billing_payment_methods table
  - Use validated customer_id in token creation payload
  - _Requirements: 1.1, 1.5, 2.2_

- [ ]* 5.1 Write property test for token generation with valid customer
  - **Property 1: Token generation includes valid customer**
  - **Validates: Requirements 1.1, 1.5**

- [x] 6. Modify BillingController::addMpCard() to ensure customer consistency




  - After creating customer, check if other cards exist for same user
  - If other cards exist with different mp_customer_id, update them to use same customer
  - This ensures all cards for a user share the same customer_id
  - _Requirements: 2.5_

- [ ]* 6.1 Write property test for multiple cards sharing customer_id
  - **Property 7: Multiple cards share customer_id**
  - **Validates: Requirements 2.5**

- [x] 7. Improve error messages and validation




  - Add validation for user email existence before customer operations
  - Add validation for CVV when tokenizing saved card
  - Update error responses to include clear, actionable messages
  - Preserve original Mercado Pago error messages in details field
  - _Requirements: 3.5, 4.4, 4.5_

- [x] 8. Add helper method to update payment method customer_id





  - Create private method `updatePaymentMethodCustomerId(int $pmId, string $customerId): void`
  - Update billing_payment_methods table with new mp_customer_id
  - Used by multiple methods to keep customer_id in sync
  - _Requirements: 2.4_

- [x] 9. Checkpoint - Ensure all tests pass




  - Ensure all tests pass, ask the user if questions arise.
