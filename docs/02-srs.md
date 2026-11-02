# Software Requirements Specification

## 1. System overview

All Shop is a single-vendor e-commerce system for Bangladesh. Customers browse products and place orders through a responsive Bengali/English storefront or a future native client using the same versioned REST API. Authorized staff operate catalog, inventory, orders, payments, delivery, marketing, content, support, and reports through a separate admin application.

## 2. Scope

### In scope

- Public catalog, product variants, search, filters, reviews, SEO, CMS content.
- Guest and registered customer journeys, saved addresses, cart merge, wishlist.
- Server-calculated checkout, configurable shipping, coupons, COD and configured online payment providers.
- Orders, fulfillment, inventory movements/reservations, courier bookings, returns/refunds.
- Admin RBAC/MFA/auditing, customer support, notifications, reports, exports.
- Versioned mobile-ready customer API, deployment/backup procedures and automated verification.

### Out of scope for initial release

- Multi-vendor marketplace, native Android/iOS applications, microservices, multiple legal entities.
- Unapproved provider-specific integrations or financial/tax policy invention.

## 3. Functional requirements

| ID | Requirement |
|---|---|
| FR-01 | A visitor can browse, search, sort, filter, and paginate active products and categories. |
| FR-02 | Product detail exposes SKU, descriptions/specifications, media, variants, current server-owned price, stock availability, reviews, related products, and SEO metadata. |
| FR-03 | Customers can register, authenticate, verify contact channels, recover access, manage profile and revoke sessions/tokens. |
| FR-04 | Guest and authenticated carts support variant-specific lines, quantity changes, persistence, stock validation, server-side price refresh, and safe merge. |
| FR-05 | Checkout validates address, available shipping method, current prices, discounts, stock, payment method, and idempotency on the server. |
| FR-06 | Orders retain item/address/price snapshots and separate order, payment, and fulfillment state histories. |
| FR-07 | Inventory supports locations, reservations, movements, adjustments, returns, low-stock alerts, and race-safe non-negative availability. |
| FR-08 | Payment adapters support COD and approved local gateways; provider state is independently verified and idempotent. |
| FR-09 | Courier adapters support manual dispatch and only provider integrations confirmed by official docs/credentials. |
| FR-10 | Customers can track orders, access their own history, request returns, view refunds, review eligible purchases, and contact support. |
| FR-11 | Staff can administer catalog, stock, orders, payment/courier reconciliation, promotions, content, customers, reports, and settings subject to server-side permissions. |
| FR-12 | Event-driven notifications support preferences, templates, retries, logs, and pluggable email/SMS/push/in-app adapters. |
| FR-13 | Public content supports bilingual SEO metadata, canonical URLs, sitemap, robots rules, structured data, and consent-aware promotions. |
| FR-14 | Every sensitive state change records actor, target, time, request correlation, and before/after details without storing secrets. |

## 4. Non-functional requirements

- Security: OWASP-aligned validation, authorization, secure cookies/tokens, MFA for administrators, callback verification, rate limiting, auditability, encrypted backups.
- Integrity: foreign keys, unique provider references, decimal money, transactional checkout, idempotency, append-only financial and stock histories.
- Availability/recovery targets: provisional RPO ≤ 24 h and RTO ≤ 8 h until business approves stricter objectives.
- Performance: provisional storefront p95 ≤ 500 ms for cached catalog APIs and p95 ≤ 1 s for uncached application APIs at agreed staging load, excluding third-party latency.
- Compatibility: current supported LTS browser versions, mobile-first layouts, accessible keyboard and screen-reader journeys, WCAG 2.2 AA target.
- Maintainability: modular monolith boundaries, strict TypeScript, Laravel conventions, OpenAPI contracts, meaningful automated tests and documented migrations.
- Localization: BDT, `Asia/Dhaka` presentation, UTC database timestamps, Bengali and English, normalized Bangladesh mobile numbers.

## 5. External dependencies

MySQL 8, Redis, supported PHP/Laravel and Node/Next.js versions, S3-compatible storage, SMTP/email, OTP/SMS, chosen payment and courier providers, DNS/TLS, CI runner, and monitoring/alerting services.

## 6. Assumptions

- One primary warehouse is sufficient for the first release, but schema supports multiple warehouses.
- Money is stored as `DECIMAL(12,2)` BDT; currency is explicitly recorded on financial records.
- Admin configuration controls areas, rates, thresholds, and provider enablement.
- Guest checkout remains available unless the owner chooses otherwise.
- No provider is considered configured until sandbox verification evidence exists.

## 7. User stories and acceptance examples

- As a guest, I can select a specific size and buy only available stock; checkout never sells the same last unit twice.
- As a customer, I can see only my own addresses/orders and can revoke a lost mobile token.
- As an admin, I can adjust inventory with a reason; the adjustment is visible in an immutable movement history.
- As an order operator, I can cancel an eligible unshipped unpaid/COD order and see reservations released once.
- As a finance operator, I can reconcile gateway events and issue only validated non-duplicate refunds.
- As a delivery operator, I can create a consignment idempotently and view provider tracking events.
- As a support agent, I can view only permitted customer information and create an auditable ticket.

Detailed functional acceptance criteria are in [03-functional-requirements.md](./03-functional-requirements.md).
