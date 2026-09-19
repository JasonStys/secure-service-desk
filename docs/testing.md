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

## Test data

Factories and seeders use invented names and reserved `.example` domains. Feature tests refresh the database for isolation. Frozen clocks make SLA and retry behavior deterministic.

## Coverage philosophy

Line coverage is collected as a diagnostic artifact, but behavioral risk drives the suite. The critical assertions are authorization decisions, transaction side effects, state-machine edges, concurrency failures, idempotency, and bounded failure behavior. Generated framework/bootstrap files are not treated as authored domain coverage.

## Accessibility checks

The document uses semantic landmarks, labeled controls, a skip link, visible focus, a live status region, no color-only status, responsive layouts, and reduced-motion handling. Automated structure/build checks do not replace manual screen-reader, browser zoom, and keyboard testing; those remain pre-release tasks for a hosted deployment.

## Adding a feature

Add the lowest-cost unit test for pure rules, feature tests for permissions and database effects, a PostgreSQL-aware test for database-specific behavior, an adversarial test for new input, and documentation/report changes. Tests must reproduce the failure before the fix when addressing a defect.
