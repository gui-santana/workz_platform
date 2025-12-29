# Test Payment Flow with Saved Card

## Changes Made

1. **Forced HTTP/1.1**: Added `CURL_HTTP_VERSION_1_1` to ensure consistent HTTP version
2. **Explicit Content-Length**: Added Content-Length header to match Postman behavior
3. **Debug Logging**: Added verbose cURL logging and payload logging to error_log
4. **Removed Debug Output**: Cleaned up customer_verification debug from tokenize response

## Testing Steps

### Step 1: Generate Fresh Token
```bash
POST https://burke-repeat-offers-paradise.trycloudflare.com/api/billing/mp/tokenize-saved
Headers:
  Authorization: Bearer YOUR_JWT_TOKEN
  Content-Type: application/json

Body:
{
  "pm_id": 5,
  "security_code": "123"
}
```

Expected Response:
```json
{
  "success": true,
  "token": "GENERATED_TOKEN_HERE"
}
```

### Step 2: Make Payment with Token
```bash
POST https://burke-repeat-offers-paradise.trycloudflare.com/api/payments/charge
Headers:
  Authorization: Bearer YOUR_JWT_TOKEN
  Content-Type: application/json

Body:
{
  "app_id": 1,
  "amount": 99.90,
  "token": "GENERATED_TOKEN_FROM_STEP_1",
  "pm_id": 5,
  "currency": "BRL",
  "installments": 1
}
```

Expected Response (Success):
```json
{
  "success": true,
  "transaction_id": 123,
  "mp_payment_id": "MP_PAYMENT_ID",
  "status": "approved",
  "response": { ... }
}
```

### Step 3: Check Error Logs
If the payment fails, check the Docker logs for debug information:
```bash
docker logs workz-php-1 2>&1 | grep "PAYMENT DEBUG" -A 20
```

This will show:
- Exact payload being sent to MP
- Idempotency key (should be null)
- Transaction ID
- Payment Method ID
- Validated Customer ID

### Step 4: Check cURL Verbose Output
If payment fails, the error response will include `_debug` field with:
- URL called
- Method used
- Headers sent
- Payload sent
- Verbose cURL log (shows actual HTTP request/response)

## What Changed in the HTTP Request

### Before:
- HTTP version: Auto-negotiated (could be HTTP/2)
- Content-Length: Auto-calculated by cURL
- No verbose logging

### After:
- HTTP version: Forced to HTTP/1.1
- Content-Length: Explicitly set in headers
- Verbose logging enabled for debugging
- Debug info included in error responses

## Why These Changes Might Fix the Issue

1. **HTTP/1.1 vs HTTP/2**: Some API endpoints behave differently with HTTP/2. Postman typically uses HTTP/1.1 by default.

2. **Content-Length Header**: Explicitly setting this header ensures the server knows exactly how many bytes to expect, preventing potential parsing issues.

3. **Consistent Request Format**: By forcing HTTP/1.1 and explicit headers, we're making the PHP request more similar to how Postman makes requests.

## If It Still Fails

If the payment still fails with "Customer not found", check the verbose log output to see:
1. What HTTP status code is returned
2. What headers are being sent
3. If there are any redirects or connection issues
4. Compare the actual HTTP request with what Postman sends

You can also test the exact same payload directly using curl from command line:
```bash
curl -X POST https://api.mercadopago.com/v1/payments \
  -H "Authorization: Bearer TEST-8200142246106461-111109-80af3688c8216017faf484a1673f14c3-2345626046" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  --http1.1 \
  -d '{
    "transaction_amount": 99.90,
    "description": "Test",
    "token": "YOUR_TOKEN_HERE",
    "installments": 1,
    "payer": {
      "email": "guilhermesantanarp@gmail.com"
    }
  }'
```
