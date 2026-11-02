# Backup and Recovery Plan

## Data classes

MySQL transactional data, uploaded media/object metadata, encrypted provider settings, deployment configuration (not plaintext secrets), OpenAPI/specification assets, and audit/operational logs. Backups must be encrypted, access-controlled, geographically separated where policy permits, and monitored.

## Provisional policy (approval required)

- MySQL: daily full backup plus binary-log point-in-time recovery; retain daily 30 days and monthly 12 months, subject to legal/business retention.
- Object storage: versioning plus lifecycle policy and periodic inventory.
- Secrets: do not back up secret-manager plaintext; maintain documented recovery/rotation procedure.
- Targets initially RPO ≤ 24 h, RTO ≤ 8 h; tighten after business impact analysis.

## Procedure

1. Verify backup completion, encryption, integrity checksum, size, and alert delivery.
2. Keep credentials and encryption keys separate from backup storage.
3. Document restore order: provision isolated network/services → restore MySQL to isolated host → apply compatible schema → restore object versions → configure rotated secrets → run integrity checks → run read-only smoke tests → approve traffic.
4. Never restore over production during a drill. Record operator, timestamps, backup identifier, errors, and measured RPO/RTO.
5. Test a full restore at least quarterly and after backup/engine changes; run a point-in-time recovery test at least twice yearly.
6. After incident, rotate exposed secrets, preserve incident evidence, validate financial/order/stock invariants, and communicate approved recovery status.

## Verification checks

- Foreign-key checks and key table counts.
- Order totals reconcile to immutable order line/address snapshots.
- Payment/refund references and captured/refunded sums reconcile.
- Inventory balances reconcile to stock movement ledger and active reservations.
- Recent media objects exist and are private as configured.
- Audit history is present; no credentials/OTP secrets appear in restored logs.

No backup or restore has been executed for the target stack yet.
