# Non-Functional Requirements

Targets below are proposed staging/release acceptance values; business owners must approve service-level objectives before production.

| ID | Quality attribute | Requirement / verification |
|---|---|---|
| NFR-01 | Performance | Cached public catalog API p95 ≤ 500 ms; other first-party API p95 ≤ 1 s at 100 concurrent virtual users on agreed staging hardware, excluding external-provider latency. Measure with repeatable load scripts. |
| NFR-02 | Availability | Initial target 99.5% monthly for customer ordering, excluding announced maintenance; alert on API, DB, queue, and payment callback failures. |
| NFR-03 | Recovery | Provisional RPO ≤ 24 hours and RTO ≤ 8 hours; perform a restore exercise before production and at least quarterly. |
| NFR-04 | Security | OWASP ASVS risk-based controls, threat review, dependency/static analysis, authorization tests, secret scanning, and no unresolved critical/high findings. |
| NFR-05 | Integrity | Transactional inventory/order/payment operations; FK constraints; immutable movement and financial event history; provider IDs unique in provider scope. |
| NFR-06 | Accessibility | WCAG 2.2 AA target for core browse, auth, cart, checkout, and admin tasks; keyboard and screen-reader manual checks. |
| NFR-07 | Localization | Bengali and English; UTC persistence; Dhaka display; BDT formatting; Bangladesh phone normalization and validation; translatable validation/errors. |
| NFR-08 | Scalability | Horizontal web/API replicas; stateless application requests; shared MySQL/Redis/object storage; queue-backed integrations. No service decomposition without measured need. |
| NFR-09 | Maintainability | Domain modules, API contract tests, strict TypeScript, Laravel conventions, migration review, 80% backend coverage goal and 90% critical-rule goal. |
| NFR-10 | Observability | Structured JSON logs, correlation IDs, queue/provider latency, audit events, actionable alerts; redact credentials, OTPs, tokens, and payment secrets. |
| NFR-11 | Compatibility | Supported current/previous major desktop and mobile browsers at launch; exact version matrix selected at implementation kickoff. |
| NFR-12 | Privacy | Data minimization, consent-aware outreach, retention schedule, access/export/correction/deletion process subject to financial/legal retention obligations. |

## Performance test profile

Seed realistic catalog, customer, order, and movement volumes in isolated staging. Run browse/search, cart, checkout concurrency, admin reporting, and webhook bursts. Include baseline and peak load, measure p50/p95/p99, error rate, queue lag, DB lock waits, and CPU/memory. Do not use synthetic test payment success against production.

## Dependencies and assumptions

Targets depend on agreed hardware, CDN, production data volume, image delivery, provider SLA, and network quality. Re-baseline after infrastructure and traffic forecasts are known.
