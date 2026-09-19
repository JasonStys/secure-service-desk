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

Final `main` verification completed on 2026-09-18 PDT (2026-09-19 UTC):

| Check | Result | Evidence |
|---|---|---|
| PostgreSQL 18.6 + PHPUnit 12.5.35 | Pass | Strict native runner reported `OK (19 tests, 52 assertions)` with coverage and `--fail-on-warning`. |
| Composer audit | Pass | No known vulnerability advisories. |
| Laravel Pint | Pass | 49 PHP files checked. |
| JavaScript | Pass | Clean install, zero high-severity audit findings, 4/4 tests, and production Vite build. |
| Repository policy | Pass | 43 authored source files; generated line index current. |
| Container | Pass | Multi-stage, non-root PHP 8.5 image built successfully. |
| CodeQL | Pass | JavaScript security-and-quality analysis completed successfully. |

- [Final CI run](https://github.com/JasonStys/secure-service-desk/actions/runs/35411279107)
- [Final CodeQL run](https://github.com/JasonStys/secure-service-desk/actions/runs/35411279104)

The PHP job publishes its Clover coverage file as a 14-day workflow artifact. CI deliberately calls PHPUnit directly with `--display-warnings --fail-on-warning`; a warning cannot be hidden inside an otherwise successful compact report.
