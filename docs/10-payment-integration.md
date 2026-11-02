# Payment Integration Design

## Provider boundary

`PaymentService` selects a configured adapter implementing:

```text
initiate(order, idempotency_key) -> provider action / pending result
verify(provider_reference) -> normalized verified transaction
handleWebhook(headers, body) -> authenticated normalized event
refund(payment, amount, idempotency_key) -> provider refund result
query/reconcile(provider_reference) -> normalized state
```

Adapters planned: bKash, Nagad, Rocket, SSLCOMMERZ, COD. COD is an internal collection workflow, not an online gateway. Keep provider-specific payloads and authentication inside adapters.

## State and verification

1. Create pending payment attempt and idempotency record transactionally against a server-calculated order total/currency.
2. Initiate provider checkout asynchronously or synchronously per verified official provider contract; store provider reference with unique constraint.
3. A browser redirect only displays a pending result. It never marks paid.
4. Verify callback signature/authentication and fetch authoritative provider transaction state when supported. Match payment/order reference, exact amount, BDT currency, provider, and allowed state transition.
5. Deduplicate provider event ID and payload hash; reject replay or contradictory event. Record verified event and state transition atomically.
6. Queue reconciliation for timeouts/ambiguous results. Keep order payment pending and stock reserved according to approved expiry policy until authoritative resolution.
7. Refunds have independent records; lock payment, ensure cumulative successful/pending refund does not exceed captured amount, reserve refundable balance, call provider idempotently, reconcile final provider result.

## Security and operations

- Use provider sandbox first; use official docs and merchant-specific allow-listed callback URLs.
- Store credentials only in secret manager/environment, encrypted at rest; admin UI masks values. Redact payload secrets, credentials, PAN/OTP, and tokens from logs.
- TLS only; validate callback signature, timing/replay protection, amount/currency/order references, and event uniqueness.
- Separate provider request timeouts, retries with exponential backoff+jitter, circuit breaker/alerting, and manual reconciliation queue.
- Never fabricate live success or claim unconfigured adapters are verified.

## COD

COD payment state begins pending/unpaid, becomes paid only when a permitted operator records collected cash according to order policy. Courier COD remittance must be reconciled separately against shipment and order. Cancellation after payment/collection requires refund/return workflow.

## Required tests and dependencies

Contract tests per adapter; invalid signature, amount/currency mismatch, duplicate/out-of-order webhook, timeout, query reconciliation, partial/full refund, duplicate refund, COD collection, and cancellation/reservation behavior. Sandbox credentials, merchant agreement, current official API docs, and provider test accounts are launch blockers.

No undocumented endpoints, secrets, or successful provider results are assumed in this design.
