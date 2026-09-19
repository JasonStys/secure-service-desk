# Security validation

Run date: 2026-09-18

| Category | Validation | Result |
|---|---|---|
| Authorization | Cross-tenant transition/list and requester transition tests | Pass |
| Injection | Parameterized query plus SQL-metacharacter search regression | Pass |
| Output encoding | Stored script/image payloads remain escaped in Blade | Pass |
| Request integrity | Browser routes use CSRF middleware/tokens; API separated | Pass by route/template inspection |
| Webhooks | Exact-body HMAC, safe replay, changed-content conflict | Pass |
| Concurrency | Expected version and row lock reject stale write | Pass |
| Reliable delivery | Atomic outbox record; bounded retry; 500-character error; dead letter | Pass |
| Secret hygiene | Ignored `.env`, placeholder examples, token/key pattern scan | Pass |
| Dependency exposure | Composer and npm audits are CI quality gates | Pending clean CI run |
| Static analysis | JavaScript/TypeScript CodeQL workflow | Pending clean CI run |

## OWASP-oriented review

- Broken access control: tenant and requester rules are centralized and tested.
- Cryptographic failures: no secrets are stored in the repository; HMAC comparison is timing-safe.
- Injection: ORM bindings and escaped wildcard fallback avoid string-built SQL.
- Insecure design: transition matrix, idempotency, audit, and failure states are explicit.
- Security misconfiguration: production checklist requires debug off, HTTPS, secure cookies, managed secrets, and least privilege.
- Vulnerable components: lockfiles, automated proposals, and audit gates are present.
- Identification/authentication failures: the demo adapter is clearly excluded from production and replaceable.
- Integrity failures: signed webhooks, pinned actions, lockfiles, and build validation reduce tampering risk.
- Logging/monitoring failures: operational signals and privacy-safe logging guidance are documented.
- Server-side request forgery: the application performs no user-controlled outbound fetch.

## Limitations

This report is evidence for a portfolio build, not a penetration-test attestation. No public deployment, enterprise identity provider, attachment scanning pipeline, or external notification provider was in scope.
