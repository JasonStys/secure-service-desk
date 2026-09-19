# Test results

Run date: 2026-09-18  
Environment: Windows 11, PHP 8.5.10, Laravel 13.32.0, Node.js 24.18.1, SQLite in memory

## Local results

| Check | Result | Evidence |
|---|---|---|
| PHPUnit | Pass | 19 tests, 52 assertions, 0 failures in 0.756 seconds. |
| Node test runner | Pass | 4 tests, 0 failures in 0.119 seconds. |
| Vite production build | Pass | 3 modules transformed; CSS 4.85 kB and JS 0.66 kB before gzip. |
| Laravel migrations | Pass | Fresh SQLite schema and synthetic seed completed. |
| Route discovery | Pass | 12 routes discovered, including health, browser, and API endpoints. |
| Scheduler discovery | Pass | SLA and outbox commands scheduled every minute without overlap. |

The measured suite covers 23 total PHP/JavaScript test cases. It includes an eight-case lifecycle data provider, transaction/idempotency and conflicting-key assertions, tenant and role isolation, optimistic locking, XSS escaping, injection-like search input, webhook authentication and replay conflict, outbox dead-letter behavior, SLA idempotency, Unicode search, and bounded browser work.

## CI results

The repository must not be treated as release-ready until the current `main` CI and CodeQL runs are green. CI repeats clean installs and adds PostgreSQL integration plus container build checks. The workflow link is available from the README badge after publication.
