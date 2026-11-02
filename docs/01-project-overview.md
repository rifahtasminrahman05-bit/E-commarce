# All Shop — Project Overview

## 1. Purpose

Plan the evolution of the existing Bangladesh-focused single-vendor All Shop store into a maintainable, production-oriented modular monolith with an API-first backend and separate customer storefront and administration experiences.

## 2. Feasibility and baseline

The requested target stack is technically feasible: Laravel/PHP REST API, Next.js/TypeScript storefront and admin, MySQL 8, Redis, Docker Compose, and a Linux/Nginx deployment. It is not a small extension of the current application. The current baseline is a server-rendered PHP application with SQLite, session cart/wishlist, COD and bKash scaffolding, admin workflows, coupons, verified reviews, and recently added stock/variants. The target is a substantial rewrite and data migration.

Keep the current application and database intact as the migration source and rollback reference. Do not copy its current database into a new schema by destructive conversion. Build the new system alongside it, validate a staged data migration, and switch traffic only after approved acceptance checks.

## 3. Product boundary

- One business owns catalog, inventory, customer experience, orders, payments, shipping, and administration.
- No seller onboarding, seller payouts, or marketplace tenancy.
- Bangladeshi launch market; BDT, `Asia/Dhaka`, Bengali and English.
- One modular monolith with versioned customer and admin APIs; no premature microservices.
- Mobile apps are out of scope; the REST API must support future native clients.

## 4. Target architecture

- Backend: Laravel modular monolith, REST API at `/api/v1`, MySQL 8, Redis.
- Frontend: Next.js App Router, strict TypeScript, Tailwind; logically separate storefront and admin apps.
- Runtime: Docker Compose for development; Linux, Nginx, PHP-FPM, queue workers, scheduler, object storage, monitoring, and CI/CD for deployment.
- Integrations: adapter boundaries for payment and courier providers; real credentials and provider documentation required before live activation.

## 5. Delivery constraints and approval

The attached specification required Phase 1 discovery and Phase 2 architecture before code. The owner approved the target stack and M1 foundation. Laravel/Next.js scaffolds and development infrastructure are now in progress; this does not authorize destructive migration, production cutover, or unverified provider integrations. Preserve the existing application and SQLite database.

## 6. Success measures

Use the measurable acceptance targets in [04-non-functional-requirements.md](./04-non-functional-requirements.md) and [16-testing-strategy.md](./16-testing-strategy.md). Production readiness requires verified payment and courier integrations, tested restore, passing automated tests, security evidence, and no unresolved critical/high defects; starting the app is not sufficient.

## 7. Decisions required before implementation

1. **Approved:** target-stack rewrite; retain the PHP/SQLite application as the migration source until a separately approved cutover.
2. Select launch payment providers and provide authorized sandbox access.
3. Select launch courier providers and provide authorized sandbox/API access.
4. Confirm Dhaka/outside-Dhaka delivery rates, free-delivery rules, COD eligibility, and service areas.
5. Confirm OTP/email providers and whether guest checkout remains available.
6. Confirm stock reservation timeout and COD reservation policy.
7. Confirm tax/VAT, return/refund, privacy, retention, and customer-consent policies.

See [18-known-limitations.md](./18-known-limitations.md) for all assumptions and open questions.
