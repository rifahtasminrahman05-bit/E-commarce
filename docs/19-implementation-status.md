# Implementation Status

Milestone: M1 — Foundation  
Overall: **M1 — Foundation in progress; target architecture approved**

## Existing baseline (not target-stack implementation)

The current application remains the PHP/SQLite All Shop developed in earlier work. It contains storefront/cart/checkout, COD, bKash integration scaffolding, admin catalog/orders, tracking, coupons, wishlist, verified reviews, and stock/variants. It remains separate from the target M1 workspaces.

## M0 deliverables

| Deliverable | Status |
|---|---|
| Feasibility and project overview | Drafted |
| SRS and functional requirements | Drafted |
| Roles and authorization matrix | Drafted |
| System/module/deployment architecture | Drafted |
| Mermaid system and ER diagrams | Drafted |
| Normalized schema and indexing strategy | Drafted |
| Versioned API inventory/contracts | Drafted |
| Payment/courier adapter designs | Drafted; providers unverified |
| Security threat model | Drafted; security assessment not run |
| UI/UX user journeys | Requirements documented; visual wireframes not produced |
| Testing, deployment, backup, roadmap | Design docs drafted; operational validation deferred to later milestones |
| Business assumptions and open decisions | Recorded; target stack, checkout modes, and provider scope approved; operating policies remain open |

## M1 foundation

Laravel 13 API skeleton, Next.js 16 storefront/admin workspaces, API health/error contract, request IDs, MySQL/Redis development Compose stack, and CI foundations are present. These scaffolds do not implement customer checkout, production admin authentication, payment/courier integrations, or production operations.

## Release preparation verification (2026-11-02)

| Check | Result |
|---|---|
| Root PHP syntax (`php -l` for every PHP file) | Pass |
| PHP production guard regression test (`tests/php-production-guard.php`) | Pass; valid isolated settings accepted and placeholders/in-tree SQLite rejected |
| PHP production guard: example credentials and in-tree SQLite path | Rejected as intended |
| Local HTTP invalid-production response and sensitive-path requests | 503 fail-closed; checked paths return 404 |
| Laravel tests | 4 passed, 19 assertions |
| Laravel Pint | Pass |
| Next.js workspace lint and production builds | Pass for admin and storefront |
| `npm audit --audit-level=moderate` | 0 vulnerabilities reported |
| Docker Compose, MySQL CI migration, Apache/Nginx staging, GitHub Actions | Not run / not available in this environment |
| Production payment, backup restore, migration, load and security assessment | Not performed |

These are foundation and local configuration checks, not a production acceptance test. The PHP app has a production-configuration regression test, but no automated commerce-flow regression suite.

## Approval gate

Owner approval received for the target-stack rewrite and M1 foundation. Further implementation remains gated by the module-specific business rules in [18-known-limitations.md](./18-known-limitations.md). Payment and courier scope selection does not authorize live transactions without provider credentials, official documentation, and sandbox verification.
