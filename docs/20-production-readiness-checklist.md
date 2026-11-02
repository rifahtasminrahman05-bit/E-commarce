# Production Readiness Checklist

No item is marked complete solely because a document or package exists.

## Product and data

- [ ] Business rules, zones/rates, tax, COD, return/refund, consent, retention, and service levels approved.
- [ ] Legacy data mapping and migration rehearsal signed off; count/value/state parity verified.
- [ ] Catalog, order, payment, stock, and shipment state transitions approved.

## Application

- [ ] Storefront, admin, and versioned API complete and integrated.
- [ ] All roles/actions enforced server-side; MFA and session/token revocation verified.
- [ ] OpenAPI contract published and CI validated.
- [ ] Accessibility, localization, SEO, error/empty/loading states reviewed.

## Commerce and integrations

- [ ] Stock concurrency, reservation expiry, cancellation and returns pass tests.
- [ ] COD and every enabled online payment provider pass official sandbox evidence, signature/replay/amount/currency/idempotency and refund checks.
- [ ] Every enabled courier passes sandbox booking/tracking/failure/retry/COD/RTO reconciliation.
- [ ] No unconfigured provider is exposed as an available successful method.

## Security and quality

- [ ] Unit/feature/integration/contract/E2E suites pass on the release artifact.
- [ ] Critical-business coverage target 90% and backend target 80% reviewed.
- [ ] SAST, dependency/container/secret scans complete; no unresolved critical/high findings.
- [ ] IDOR/BOLA, privilege, payment, upload, rate-limit, and webhook tests documented.
- [ ] Security test report contains real target/version/evidence and findings.

## Operations

- [ ] Production secrets managed/rotatable; TLS, headers, CORS, least-privilege access verified.
- [ ] Monitoring, alerting, structured/redacted logs, queue/DLQ, scheduler, and on-call ownership verified.
- [ ] Encrypted backup and point-in-time recovery configured; restore drill meets approved RPO/RTO.
- [ ] Deployment, migration, rollback, incident, key-rotation, and recovery procedures rehearsed.
- [ ] Load test meets approved SLO at expected peak.

## Release approval

- [ ] Staging acceptance and business sign-off complete.
- [ ] Cutover method and rollback data-consistency plan approved.
- [ ] All known limitations and remaining risks accepted by named owner.
- [ ] Production readiness approved by business, engineering, security, and operations.

## Current PHP/SQLite release-specific gates

- [ ] Production guard rejects sample credentials, checks the resolved SQLite path is outside the source tree, and requires writable pre-created storage. Local fail-closed/path checks are not a substitute for staging review.
- [ ] Production environment guard passes with a public HTTPS URL, secure cookies, a strong application key/admin bootstrap secret, and an absolute SQLite path outside the web root.
- [ ] Production sandbox payment methods and incomplete live payment configurations are not exposed as customer checkout options.
- [ ] Production web-server document root is isolated; `.env`, PHP source/config/docs, `storage`, SQLite database/WAL/SHM, and backups are not directly reachable.
- [ ] TLS termination, HTTP-to-HTTPS redirect, HSTS, PHP error display/logging, file ownership, and supported PHP/extensions are verified on staging.
- [ ] Admin lockout/session-expiry behavior, payment sandbox callbacks, stock/coupon/order flows, and customer-support inbox pass staging acceptance.
- [ ] Encrypted automated SQLite backups include consistent WAL state and a restore drill is successful.
- [ ] Delivery rates/zones, COD, cancellation/return/refund, tax, privacy/marketing consent, data-retention, and customer-response policies have named owner approval.
- [ ] Live bKash merchant configuration and sandbox evidence are approved before enabling live checkout. Courier, customer accounts, automated email, returns/refunds, and tax workflows are not implemented in the PHP release.
- [ ] Domain, hosting, operational owner, monitoring/alerts, traffic expectations, and release/rollback procedure are confirmed.

Current status (2026-11-02): **not production-ready**. PHP production configuration and local fail-closed/path checks have been strengthened and exercised, but no real staging host/web-server configuration has been checked. Deployment infrastructure, live provider verification, business policies, backup/restore, security assessment, migration, monitoring, and the remaining gates above are unverified or incomplete. The Laravel/Next.js target remains a foundation and is not the active commerce release.
