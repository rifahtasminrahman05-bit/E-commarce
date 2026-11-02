# Contributing to All Shop

## Current milestone

The repository contains the existing PHP/SQLite application, the approved target architecture, and an in-progress Laravel/Next.js M1 foundation. Commerce modules remain unimplemented. Do not migrate data or cut over traffic until those separate gates are explicitly approved in `docs/19-implementation-status.md`.

## Architecture changes

- Update the relevant numbered document in `docs/` when a requirement, API, schema, lifecycle, threat, or operational decision changes.
- Keep `docs/19-implementation-status.md` accurate at each approved milestone.
- Record decisions and rationale; do not silently change the target technology stack or business rules.
- Never invent provider APIs, credentials, test results, or production integrations.

## Implementation standards after approval

- Follow Laravel/PSR conventions, strict TypeScript, modular monolith boundaries, and the approved OpenAPI/schema contracts.
- Validate all input server-side; enforce authorization in policies; use transactions/idempotency for commerce mutations.
- Add meaningful unit/feature/integration/E2E tests for changed behavior and document operational consequences.
- Do not commit credentials, real customer/payment data, or production database copies.
- Do not perform destructive migrations without an explicit verified backup, rehearsal, and rollback approval.

## Review checklist

1. Is the change within approved scope and architecture?
2. Are data integrity, failure/retry, authorization, privacy, and localization addressed?
3. Are tests and documentation updated?
4. Are migration, deployment, and rollback impacts explicit?
5. Are external integrations verified against official provider documentation?
