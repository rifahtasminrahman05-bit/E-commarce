# Release Preparation Runbook

**Status: not approved for production deployment.** No server, domain, production credentials, live provider evidence, backup restore, or business-policy sign-off was supplied or tested. This runbook separates the current PHP store from the incomplete target platform and records the safe next steps; it does not authorize deployment or data migration.

## Product boundaries

| Workload | Current role | Production decision |
|---|---|---|
| Root PHP/SQLite application | Existing store with customer checkout and admin operations | Only candidate for a separately approved PHP staging release; not production-ready yet |
| `backend/` Laravel API | Target M1 foundation | Not a commerce API; do not expose as the production store |
| `frontend/apps/storefront/` | Target M1 UI foundation | No complete production checkout; do not cut over from PHP |
| `frontend/apps/admin/` | Target admin UI preview | No production authentication/authorization; never publicly deploy it |
| `compose.yaml` | Local development preview | Uses `artisan serve`, Next.js dev servers and debug settings; development only |

The root PHP routes remain in place to preserve existing URLs and SQLite data. Do not move files, replace the active database, or point the target stack at production data as part of routine release preparation.

## PHP/SQLite staging gate

Before any real customer traffic:

1. Provision an isolated staging host with a supported PHP runtime and required extensions (`pdo_sqlite`, `curl`, `intl`, `mbstring`), HTTPS, working error logs, and a tested release/rollback procedure.
2. Configure a public HTTPS `ALLSHOP_APP_URL`, `ALLSHOP_ENV=production`, `ALLSHOP_SECURE_COOKIES=true`, and a fresh random `ALLSHOP_APP_KEY` of at least 32 characters. Do not use any placeholder from `.env.example`.
3. Configure a unique admin bootstrap username and a randomly generated password of at least 20 characters. Store the environment file outside version control with permissions restricted to the application owner. Admin bootstrap credentials only create the initial database account; changing the environment value does not rotate an existing account.
4. Pre-create an application-owned, writable directory outside the web root and set `ALLSHOP_DB_PATH` to an absolute database file path inside it. The application rejects database paths under its source directory and rejects non-writable paths.
5. Serve only through a reviewed Apache configuration that applies the root `.htaccess` restrictions, or add and independently test equivalent Nginx rules. `.htaccess` is not enforced by Nginx. Never use PHP's built-in development server as a public production server.
6. Keep bKash disabled until live merchant credentials are supplied through a secret manager and the official sandbox/payment callback, amount, currency, replay, and failure cases pass. Do not substitute sandbox payment for a production checkout.
7. Test product, stock, coupon, COD, bKash callback, cancellation, admin login lockout, and order-tracking flows on staging with synthetic data. Review database migrations on a copy and retain a tested rollback backup.
8. Configure encrypted automated SQLite backups with WAL-consistent snapshots, off-host retention, monitoring/alerts, an on-call owner, and a successful restore drill. Never place backups under the public application directory.
9. Obtain written approval for delivery zones/rates, COD operations, tax, returns/refunds, privacy/marketing consent, retention, support response time, and expected traffic/SLO/RPO/RTO.
10. Complete every applicable item in [the production readiness checklist](./20-production-readiness-checklist.md) and obtain owner, engineering, security, and operations sign-off.

The PHP startup production guard validates configuration shape and path safety; it cannot prove TLS termination, correct web-server rules, secret-manager security, payment-provider correctness, or backup recoverability.

## Laravel/Next.js target gates

The Compose stack is local preview only. Its API and queue run as development containers, Compose sets debug/local settings, and both Next.js images start development servers. Docker is not installed in the current validation environment, so Compose build/runtime checks have not run here.

Before this target may be considered for staging, implement and validate the approved modules: customer and staff authentication/authorization, catalog administration, guest/registered checkout, inventory reservation, payment adapters, courier workflows, notifications, and operational observability. Then add production runtime images (non-root and production commands), TLS/reverse-proxy topology, production environment/secrets, database migration/cutover/rollback plan, CI-backed MySQL tests, provider sandbox evidence, security assessment, and encrypted backup/restore drills. Keep the existing PHP store as the active system until a separately approved cutover completes.

## Validation commands

Run in isolated local/CI environments, never against a production database:

```sh
# Current PHP application
for file in *.php; do php -l "$file" || exit 1; done
php -l tests/php-production-guard.php
php tests/php-production-guard.php

# Target API
cd backend
composer validate --strict
composer audit --no-interaction
vendor/bin/pint --test
php artisan test

# Target Next.js workspaces
cd ../frontend
npm ci
npm audit --audit-level=moderate
npm run lint
npm run build
```

The PHP app has a production-configuration regression test, but no automated commerce-flow regression suite. Manual staging checks are not a substitute for one. The target's checks only validate its current foundation; green checks do not imply complete commerce functionality or production readiness.
