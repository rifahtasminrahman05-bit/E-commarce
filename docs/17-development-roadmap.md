# Development Roadmap

Each milestone has a review gate. Scope, providers, rates, retention, and lifecycle policy are approved before implementing dependent modules.

| Milestone | Work | Exit evidence |
|---|---|---|
| M0 — Discovery & architecture | SRS, role matrix, modular architecture, ER/schema, API contracts, security/deployment plans, decision register | Owner approves target stack and architecture baseline; module-specific decisions are recorded and closed before dependent milestones |
| M1 — Foundation | Laravel/Next workspace, Docker Compose, MySQL/Redis, CI, logging, API errors/OpenAPI, migration baseline | clean setup, CI green, health and migration tests |
| M2 — Identity/RBAC | customer auth, verification, reset, Sanctum, staff roles/MFA/audit | auth, revocation, privilege and IDOR tests |
| M3 — Customer/catalog | profile/address, categories/brands/products/media/search/SEO | catalog APIs and storefront E2E |
| M4 — Inventory | warehouses, movement ledger, reservations, transfer/adjustment, alerts | concurrency and ledger reconciliation tests |
| M5 — Cart/checkout/orders | guest/auth carts, merge, quote, shipping rules, coupons, snapshots, idempotency | full COD E2E and duplicate/oversell tests |
| M6 — Payments | COD collection and approved online adapters, webhooks, refund/reconciliation | sandbox evidence, callbacks and refund tests per provider |
| M7 — Shipping | zones/rates, manual courier, approved courier adapters, tracking/COD/RTO | sandbox booking/retry and reconciliation evidence |
| M8 — Account/service | order history, reviews, returns, support, notifications | ownership, state, consent and E2E tests |
| M9 — Admin/CMS/reports | dashboards, catalog/inventory/order/finance/CMS/marketing/RBAC/report export | permission and report reconciliation |
| M10 — Hardening | security, load, accessibility, backup restore, data migration, operational runbooks | no critical/high findings; measured SLO and successful restore |
| M11 — Staged migration/cutover | ETL rehearsal, parity checks, freeze/dual-write decision, canary and rollback | owner sign-off and rollback evidence |

## Proposed implementation order

Follow backend dependency order from identity/RBAC to reports; frontend design system and public catalog can proceed against approved OpenAPI mocks, but production views must connect to real APIs. Integrations are gated by official docs and credentials. M0 approval for the foundation has been granted; module implementation remains gated by its required business decisions.

## Current status

The owner approved the target-stack rewrite and M1 foundation. Selected launch method scope: COD, bKash, Nagad, Rocket, and SSLCOMMERZ; selected courier scope: Manual, Pathao, Steadfast, and RedX; guest and registered checkout are both in scope. Provider credentials/docs and delivery/business policies remain launch blockers. M1 foundation is in progress; no provider integration or commerce workflow is claimed as implemented.
