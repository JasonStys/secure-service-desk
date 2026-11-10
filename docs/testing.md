# Testing strategy

## Layers

| Layer | Coverage |
|---|---|
| Unit | Every representative lifecycle edge, terminal state, normalization, token matching, Unicode, and client work bounds. |
| Feature | Idempotent create, tenant/object authorization, role authorization, optimistic locking, XSS escaping, injection-like search, signed webhook replay/conflict, retry/dead-letter, and SLA idempotency. |
| Integration | Full migrations and feature suite on PostgreSQL in CI; SQLite locally; asset production build; container image build. |
| Static/policy | Pint, Composer/npm audits, action SHA pins, required docs, source headers, local links, sensitive-token patterns, forbidden-reference scan, and generated line index. |
| Security analysis | CodeQL for JavaScript/TypeScript plus OWASP-mapped behavioral tests. |

## Commands

```bash
vendor/bin/pint --test
php artisan test
npm test
npm run build
composer audit --locked
npm audit --audit-level=high
php tools/validate_repo.php --check
```

Use `bash scripts/verify.sh` to run the complete local gate. CI runs equivalent jobs from a clean checkout and adds PostgreSQL/container checks.

For actual browser acceptance, build with `npm ci && npm run build`, install the exact browser with
`npx playwright install chromium`, then run `npm run check:browser-isolation` and
`npm run test:browser`. Set `SSD_BROWSER_PHP` to a PHP8.5 executable if it is not on PATH;
`SSD_BROWSER_PORT` defaults to the distinct unused loopback port18971. A private temporary
SQLite/database/storage/session/config/runtime is created and only its owned child is stopped.
The real.env file, local HMR server and existing application database are never adopted. CSRF is
active because the fixture is not Laravel's special `testing` environment. Standard no-script
forms, same-origin browser behavior and token-fallback negative controls are exercised separately.

See [this upgrade's completed validation](reports/practical-workflows-2026-11-09.md) for counts and
limits; earlier publication reports remain historical records.

## Test data

Factories and seeders use invented names and reserved `.example` domains. Feature tests refresh the database for isolation. Frozen clocks make SLA and retry behavior deterministic.

Ownership acceptance independently checks exact audit/outbox deltas, same-owner no-op, assign and
unassign, shared-version stale rejection, missing/foreign/ineligible targets, requester/cross-tenant
concealment, resolved/reopen/closed behavior, due-now/exact24h/just-after boundaries, escaped bounded
history and optional blank tags. A connection-local query-event fixture interleaves resolution,
closure or deadline extension after candidate selection, before SLA locking: no sleep or real
service is needed to prove that an invalidated candidate must not escalate.

## Coverage philosophy

Line coverage is collected as a diagnostic artifact, but behavioral risk drives the suite. The critical assertions are authorization decisions, transaction side effects, state-machine edges, concurrency failures, idempotency, and bounded failure behavior. Generated framework/bootstrap files are not treated as authored domain coverage.

## Accessibility checks

The document uses semantic landmarks, labeled controls, a skip link, visible focus, a live status region, no color-only status, responsive layouts, and reduced-motion handling. Automated structure/build checks do not replace manual screen-reader, browser zoom, and keyboard testing; those remain pre-release tasks for a hosted deployment.

## Adding a feature

Add the lowest-cost unit test for pure rules, feature tests for permissions and database effects, a PostgreSQL-aware test for database-specific behavior, an adversarial test for new input, and documentation/report changes. Tests must reproduce the failure before the fix when addressing a defect.
