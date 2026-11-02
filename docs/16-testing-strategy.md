# Testing Strategy and Acceptance Plan

## Test pyramid

1. Unit: price/coupon eligibility, Bangladesh phone normalization, order/payment/fulfillment transition rules, stock reservation/release, refund cap, provider status mapping.
2. Laravel feature: auth/verification, policies, catalog filters, cart merge, quote/checkout, orders, admin workflows, APIs and error format.
3. Integration: MySQL constraints/transactions, Redis queue/cache/rate limit, object storage, mail/OTP, provider sandbox adapters.
4. Contract: OpenAPI schema and consumer/provider adapter contracts; webhook fixtures must be documented and sanitized.
5. Frontend: component/form validation, accessibility and localization, API error/loading/empty states, permission-aware navigation.
6. Playwright E2E: guest/customer purchase, login/cart merge, admin stock/order/courier flows, mobile viewport and failure paths.
7. Security, load and recovery: see [12-security-architecture.md](./12-security-architecture.md), [04-non-functional-requirements.md](./04-non-functional-requirements.md), [15-backup-recovery.md](./15-backup-recovery.md).

## Mandatory scenario matrix

| Scenario | Expected assertion |
|---|---|
| Registration/login and verification | valid lifecycle; rate-limits and invalid credentials do not expose secrets |
| Unauthorized customer access | cross-user orders/addresses/tickets are denied |
| Browse/search/filter/pagination | deterministic filters, allow-listed sort, stable contract |
| Variant/cart merge | correct variant uniqueness, availability and conflict report |
| Coupon/checkout calculation | server prices/tax/shipping/discount and final amount match rules |
| Duplicate checkout | same idempotency key returns same outcome, never duplicate order |
| Concurrent last-unit order | exactly one reservation/order succeeds; stock never becomes negative |
| COD | unpaid order and later verified collection transition |
| Online payment | verified provider amount/currency/reference required |
| Invalid/duplicate webhook | reject invalid signature; duplicate event changes state once |
| Refund | partial/full caps, no duplicate; provider failure remains pending/failed visibly |
| Courier failure/retry | timeout does not duplicate shipment; retry/reconciliation visible |
| Cancellation/return | state policy enforced and reservations/movements consistent |
| Admin RBAC | each route/action denied unless permission granted |
| Notification | preference/consent checked; retry and delivery log recorded |
| Backup restore | restore drill and financial/stock invariants pass |

## Coverage and quality gates

Critical business logic target ≥90% branch/meaningful assertion coverage; overall backend target ≥80%. Coverage does not replace business assertions. CI blocks on failing tests, typecheck/lint, migration checks, OpenAPI drift, or critical dependency/secret findings. E2E flakiness is tracked and cannot be hidden by retry-only green status.

## Test-data policy

Use deterministic synthetic users/orders and provider fixtures only in isolated test/demo environments. Never use real customer data or send real OTP/payment/courier requests from automated tests. Production dashboards must never use fabricated activity.
