# Courier Integration Design

## Provider boundary

Define `CourierService` methods for serviceability/quote, booking, cancellation where supported, tracking, event verification, COD reconciliation, and return-to-origin. Planned adapters: Pathao, Steadfast, RedX, and Manual. Manual is a real operational workflow, not a fake external integration.

## Booking and tracking workflow

1. Order reaches an approved packed/ready state; validate address, service area, COD amount and shipping fee.
2. Create shipment and idempotency record before provider call; enqueue booking with a stable key.
3. Adapter maps normalized shipment request to provider contract sourced from current official documentation.
4. Persist provider consignment ID and response with sensitive fields redacted. A timeout remains `booking_pending/unknown`, not booked or shipped.
5. Retry only with provider-supported idempotency or a lookup/reconciliation step; do not blindly create a duplicate consignment.
6. Accept only authenticated webhook events or poll through a queue; deduplicate provider event ID and append immutable shipment event.
7. Map provider status to normalized fulfillment state through explicit transition rules; retain raw status mapping for audit.
8. Reconcile delivered COD collection, delivery fee, failed delivery, and return-to-origin. Record differences for finance review.

## Configuration and resilience

Courier enablement, credentials, service mappings, timeout, retry/backoff, callback signing, COD limits, and service areas are environment/secret-manager or masked admin settings. No courier prices or zones are hardcoded. Use bounded retries, rate limits, circuit breaker/alerts, DLQ/manual review, and operator-visible failure states.

## Dependencies and tests

Provider accounts, current API documentation, sandbox keys, webhook registration, serviceable-area data, and commercial rate cards are required. Test duplicate booking request, timeout after provider success, invalid/replayed event, out-of-order scans, unavailable service, cancellation, delivery failure, COD reconciliation mismatch, and RTO.

Until verified credentials and sandbox tests exist, Pathao/Steadfast/RedX remain unconfigured. Never invent endpoint paths or claim a live booking.
