# Security Architecture and Threat Model

## Trust boundaries and controls

| Boundary / asset | Threat | Planned controls | Evidence required |
|---|---|---|---|
| Browser ↔ Next.js/API | session theft, CSRF, XSS, credential stuffing | HTTPS, secure HttpOnly SameSite cookies, CSRF for cookie writes, CSP/security headers, output encoding, rate limits, MFA staff | browser/header tests, auth tests |
| Customer ↔ customer data | IDOR/BOLA, guest order enumeration | owner-scoped queries, opaque public IDs, one-time expiring guest proof, non-disclosing errors | cross-account authorization tests |
| Staff ↔ admin functions | privilege escalation, compromised account | RBAC policies, MFA, least privilege, reauth for sensitive actions, audit trail, session revocation | permission matrix test suite |
| API ↔ MySQL | injection, mass assignment, race/oversell | Form Requests, ORM/parameter binding, allow-listed DTOs, FK/check/unique, transactions, atomic reservation | SAST and concurrency tests |
| API ↔ payment provider | forged/replayed webhook, wrong amount/currency, duplicate refund | signature verification, provider query, exact amount/currency/reference checks, event uniqueness, idempotency, reconciliation | negative webhook/refund tests |
| API ↔ courier | forged state, duplicate booking, data leak | signed provider events, adapter boundary, idempotent booking, minimal PII, retry/DLQ | provider contract/failure tests |
| Media uploads | malware, path traversal, active content, oversized payload | MIME/content validation, size limits, random object keys, private originals, image re-encode, signed access where needed | upload test corpus |
| Secrets and backups | credential exposure, theft, ransomware | secret manager, rotation, least-privilege DB, encrypted backup, isolated restore credentials | secret scan, restore exercise |
| Notification channels | OTP abuse, consent violation, spam | OTP one-time TTL, rate limit, consent preferences, templates, retries and delivery logs without secret body | abuse/consent tests |

## Authentication

Use Laravel-supported secure password hashing, verified email/phone lifecycle, password reset tokens, session rotation on login/privilege change, secure cookies, rate-limited login/OTP, and revocable Sanctum tokens for native clients. Set expiry/abilities and provide customer token revocation. Require MFA for administrators; define recovery flow with audit and out-of-band verification.

## Authorization and privacy

Policies/Gates protect each resource/action. Validate nested parent-child ownership. Keep admin and customer API route groups distinct. Use field minimization for support/analysts and redact personal/payment secrets from logs. Collect only data necessary for delivery, fraud control, support, and legal records. Define retention/deletion policy before launch.

## Secure delivery

Dependency and secret scanning in CI; static analysis; CSP and security headers; CORS allow-list; TLS; input validation; output encoding; upload quarantine; audit log append-only access; encrypted backups; patch policy; incident response and key rotation runbooks.

## Threat-model assumptions

The attacker can control all client input, replay requests, guess public IDs, manipulate browser return URLs, submit malformed files, and trigger concurrency. Provider callbacks and internal queues are untrusted until authenticated and validated. Internal staff accounts may be compromised; permissions and auditing are defense in depth.
