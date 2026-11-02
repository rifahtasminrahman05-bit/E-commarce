# Deployment Architecture and Guide (Target)

## Environments

Development uses Docker Compose with isolated MySQL/Redis and disposable seed data. Staging mirrors production topology with sandbox providers. Production uses Linux, Nginx/TLS, PHP-FPM Laravel API, Next.js storefront/admin runtimes, MySQL 8, Redis, worker processes, scheduler, S3-compatible private media, centralized logs/metrics, backups, and alerting.

## Release pipeline

1. Pull request: formatting, static analysis, typecheck, unit/feature/contract tests, OpenAPI validation, dependency/secret scans, frontend build.
2. Build immutable versioned images/artifacts; generate SBOM and provenance where available.
3. Deploy staging; apply backward-compatible migrations; run smoke, integration, and provider sandbox tests.
4. Review release evidence, migration plan, backups, and rollback decision; obtain production approval.
5. Deploy canary/rolling release; monitor errors, latency, queue lag, payment/courier failures, and database health.
6. Complete post-deploy smoke check; retain previous artifact and migration compatibility window.

## Configuration

Keep separate environment values for app URL/environment, DB/Redis, session/cookie domains, mail, OTP, S3, payment adapters, courier adapters, CORS, rate limits, monitoring, and log retention. `.env.example` contains placeholders only. Production secrets come from an approved secret manager and must be rotated.

## Migration and rollback

- Take and verify an encrypted pre-migration backup.
- Prefer additive expand/contract migrations; never drop/rename a live column in the same release that stops writing it.
- Test migrations on a copy of the current SQLite-era data and representative production-size MySQL data.
- For the rewrite, use a repeatable, checksummed ETL into a new MySQL schema; compare counts, sums, orders, coupon/review/stock invariants.
- Keep old PHP app read-only or available for rollback during an approved cutover window. Define whether writes are frozen or dual-written; do not dual-write without an idempotent reconciliation design.
- Roll back application artifacts first. Restore database only under incident procedure; do not assume destructive migrations are automatically reversible.

## Runtime requirements

Separate web/API, worker, scheduler, and database identities; run PHP-FPM and Node as non-root; restrict storage and internal services; health checks must not reveal secrets. Configure TLS renewal, log rotation, queue retry/DLQ monitoring, database connection limits, and alert ownership.

## Deployment status

No Dockerfile, Compose file, Nginx, CI/CD, or server configuration has been implemented in this architecture-only milestone.
