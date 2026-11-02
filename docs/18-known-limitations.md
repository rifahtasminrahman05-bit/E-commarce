# Known Limitations, Assumptions, and Open Decisions

## Current-system limitations relevant to migration

- Current All Shop is a PHP server-rendered app using SQLite; target specification requires Laravel API + Next.js + MySQL/Redis. There is no existing target-stack scaffold.
- The active PHP/SQLite storefront has no confirmed production domain, host, TLS termination, backup/restore drill, monitoring, or deployment evidence. Production configuration checks do not replace infrastructure review.
- Customer accounts, automated support email, courier integrations, returns/refunds, tax calculations, and approved privacy/retention policies are not implemented in the PHP release.
- PHP makes schema changes at application startup. Production release and rollback procedures need a tested database backup and migration rehearsal before an existing store is updated.
- Existing feature flows do not automatically satisfy the target's customer accounts, full RBAC/MFA, provider adapter contracts, warehouse ledger, returns/refunds, courier integration, CMS, OpenAPI, or operational requirements.
- Existing SQLite data model is not a direct replacement for the normalized target schema. Migration needs mapping, validation, backup, rehearsal, and rollback planning.
- Existing bKash scaffolding does not prove a live merchant integration. No gateway/courier sandbox credentials or provider access are supplied.
- No production hosting capacity, traffic forecast, legal/tax policy, delivery zones/rates, or retention policy is confirmed.

## Explicit assumptions for architecture review

- Single vendor and Bangladesh-only initial operations.
- BDT with two decimal places; timestamps stored UTC, displayed `Asia/Dhaka`.
- Bengali is default locale; English is supported.
- BIGINT internal PKs and opaque ULID public IDs; confirm before migrations.
- One primary warehouse at launch; model supports multiple.
- Guest and registered checkout are both approved in the target scope.
- Manual courier is available as an operational fallback; no provider is enabled by default.
- Initial security/access model uses granular RBAC plus administrator MFA.
- No tax/VAT is computed until owner/accounting policy is supplied.

## Owner decisions required

1. **Approved:** target Laravel/Next.js/MySQL/Redis rewrite; retain the PHP/SQLite app during staged migration.
2. Confirm brand/domain and storefront/admin domain topology.
3. **Scope selected:** COD, bKash, Nagad, Rocket, SSLCOMMERZ. Confirm transaction/refund policy and provide sandbox account/docs for each provider before enabling integrations.
4. **Scope selected:** Manual, Pathao, Steadfast, RedX. Confirm service areas, commercial rate cards, COD/RTO rules, and provide provider docs/sandbox access.
5. Approve Dhaka/outside-Dhaka delivery rates, free threshold, dimensional/weight policy, and delivery SLA.
6. Approve COD eligibility, COD fee, cash collection and reconciliation owner.
7. **Guest and registered checkout approved.** Choose OTP channel/provider and email provider, verification requirements, and account merge policy.
8. Approve reservation timing/expiry separately for COD and prepaid, and return-to-stock policy for returns/damaged goods.
9. Approve VAT/tax inclusion, discount stacking, rounding, invoice requirements, and refund policy.
10. Approve privacy notice, marketing consent, data retention/deletion, and export/access process.
11. Approve traffic/SLO/RPO/RTO and budget for hosting, Redis, MySQL, object storage, monitoring, OTP, SMS, and provider charges.
12. Confirm expected catalog/order volumes, warehouse count, bulk import format, and legacy data retention/cutover strategy.

## Unverified items

No payment/courier integration, deployment, load test, security test, backup restore, or migration has been performed for the target architecture. Provider scope selection is not evidence of credentials, sandbox access, or verification. The security report is a plan, not an assessment.
