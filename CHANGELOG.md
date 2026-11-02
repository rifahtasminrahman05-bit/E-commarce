# Changelog

All notable changes to All Shop are recorded here.

## Unreleased

### Architecture

- Added Phase 1–2 architecture and requirements package for the proposed Laravel/Next.js/MySQL/Redis modular-monolith target.
- Recorded owner approval for the target-stack rewrite and M1 foundation; migration/cutover still require separate approval.
- Recorded migration, payment, courier, delivery-rate, stock lifecycle, privacy, and operations decisions that remain open.

### Platform foundation

- Added Laravel API and separate Next.js storefront/admin foundations, request IDs, versioned health/error responses, and a development Compose stack.
- Added Composer/npm lockfiles and CI checks; the new storefront/admin are explicitly previews without commerce or authentication workflows.

### Existing application

- Existing PHP/SQLite application and its SQLite database remain unchanged.
