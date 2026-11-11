# Secure Service Desk

[![CI](https://github.com/JasonStys/secure-service-desk/actions/workflows/ci.yml/badge.svg)](https://github.com/JasonStys/secure-service-desk/actions/workflows/ci.yml)
[![CodeQL](https://github.com/JasonStys/secure-service-desk/actions/workflows/codeql.yml/badge.svg)](https://github.com/JasonStys/secure-service-desk/actions/workflows/codeql.yml)

A portfolio-grade service-desk application built to make correctness visible. It combines a server-rendered Laravel interface, a versioned JSON API, tenant-safe authorization, an explicit ticket state machine, optimistic concurrency control, immutable audit records, SLA escalation, signed idempotent webhooks, and a transactional outbox with bounded retries.

All people, organizations, tickets, and addresses in the seed data are synthetic.

## Native Windows application

The new **Secure Service Desk 1.1.0** portable application bundles its real Laravel/PHP backend,
production assets, current PHP runtime and sandboxed Electron window. Extract the complete package
and launch `SecureServiceDesk.exe`; no PHP, Node, Composer, IDE, Docker or setup commands are needed
by the application user. This branch contains a tested candidate; a published installer/release is
a separate acceptance step, not implied by the source change.

Create a fictional request as **Requester**, then choose **Agent** to assign an eligible owner,
comment and confirm a valid lifecycle transition. Search/owner/SLA filters preserve the selected
actor's scope; stale versions and cross-tenant requests remain refused. Existing work persists in a
durable OS-local application profile across restarts. Prepared Northwind/Contoso records are normal
data initialized inside this same complete app, not a separate demonstration program. New native
reference actors receive fresh randomized inactive passwords, not a shared installed credential.

The native title, persistent help, authored icon and footer identify **Jason Stys** and his
[source/contact profile](https://github.com/JasonStys). The local boundary uses an owned ephemeral
loopback listener, a per-launch private header, exact Host/Origin checks, Laravel CSRF and an isolated
profile/key. It never loads your development `.env` or database. Closing waits for owned PHP work.
Actor selection is a **single-OS-owner reference workflow, not production multiuser authentication**;
use fictional data. Local database/history is plaintext OS-owner data, not an encrypted account vault.

See [native usage, storage and build](docs/native-application.md),
[observed native validation](docs/reports/native-application-2026-11-10.md), and the
[actual package workflow](.github/workflows/native-app.yml). Original web/API development and
PostgreSQL/container workflows remain available below.

## What this project demonstrates

- **Backend engineering:** PHP 8.5, Laravel 13, validation, transactions, Eloquent relationships, API design, scheduling, and PostgreSQL-aware search.
- **Reliability:** database-backed outbox delivery, exponential retry delays, dead-letter state, idempotency keys, webhook replay handling, and SLA escalation.
- **Security:** tenant and object authorization in the domain layer, role checks, CSRF on browser mutations, API throttling, HMAC verification, output escaping, bounded inputs, and no committed secrets.
- **Frontend engineering:** semantic HTML, responsive CSS, keyboard-visible focus, reduced-motion support, and optional vanilla JavaScript that leaves core forms functional without scripts.
- **Delivery discipline:** pinned GitHub Actions, PHP and JavaScript tests, PostgreSQL integration tests, formatting, dependency audits, CodeQL, container builds, generated code indexing, and documented evidence.

## Architecture at a glance

```mermaid
flowchart LR
    UI[Blade + HTML forms] --> C[Web/API controllers]
    API[REST API] --> C
    C --> S[Ticket service]
    S --> DB[(PostgreSQL or SQLite)]
    S --> A[Immutable audit]
    S --> O[Transactional outbox]
    W[Scheduled worker] --> O
    W --> N[Notification adapter]
    E[SLA scheduler] --> DB
```

Controllers validate transport data; `TicketService` owns tenant checks, authorization, lifecycle rules, and transactional writes. The same transaction writes the domain change, audit entry, and outbox message, so a committed ticket update cannot silently lose its notification intent. See [architecture](docs/architecture.md) and [data model](docs/data-model.md).

## Major features

| Feature             | Behavior                                                                                                                               |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Ticket lifecycle    | `new → open → pending/resolved/closed`, reopen from `resolved`, and terminal `closed`; invalid edges fail validation.                  |
| Tenant isolation    | Agents see their tenant; requesters see only their own tickets; cross-tenant identifiers return `404`.                                 |
| Concurrency safety  | Every transition includes an expected version and rejects stale updates.                                                               |
| Ownership workspace | Assign/unassign eligible tenant agents with the same version lock, immutable audit and transactional notification intent.              |
| Operational queue   | Combine status/priority/search with assigned-to-me, unassigned and precise SLA deadline windows.                                       |
| Ticket review       | Open an authorized ticket workspace, page through discussion/audit independently, comment and use the current version for each action. |
| Audit trail         | Creation, comments, transitions, and SLA breaches append structured immutable records.                                                 |
| Reliable delivery   | Outbox messages are deduplicated, processed in bounded batches, retried with backoff, and dead-lettered after three failures.          |
| SLA automation      | Priority determines the response target; a scheduled command marks each overdue ticket once.                                           |
| Integration API     | Versioned JSON endpoints, bounded pagination, request-key idempotency, rate limiting, and signed webhook ingestion.                    |
| Search              | PostgreSQL uses a GIN full-text index; SQLite uses an escaped literal fallback for local development.                                  |
| Progressive UI      | All mutations are standard CSRF-protected forms; JavaScript adds live search and transition confirmation.                              |

## Quick start

Prerequisites: PHP 8.5 with SQLite, Composer 2.10, and Node.js 24.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
npm ci
npm run build
php artisan serve
```

Open `http://localhost:8000`. The local-only demonstration identity adapter recognizes:

- `agent@northwind.example` — may manage tickets in the synthetic Northwind tenant.
- `requester@northwind.example` — may create, view, and comment on only their own tickets.

The adapter is deliberately isolated behind middleware and is **not production authentication**. Replace it with an identity provider before deployment; domain authorization remains reusable.

For a containerized PostgreSQL environment:

```bash
docker compose up --build
```

## Verification

```bash
bash scripts/verify.sh
```

The gate runs dependency audits, Laravel Pint, PHPUnit, Node tests, a production asset build, and repository-specific policy checks. The CI suite additionally uses PostgreSQL and builds the non-root container image. Current evidence is recorded in [test results](docs/reports/test-results.md), [security validation](docs/reports/security-validation.md), and [validation results](docs/reports/validation-results.md).

## API example

```bash
curl -X POST http://localhost:8000/api/v1/tickets \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-Demo-Actor: requester@northwind.example' \
  -d '{"title":"Export delayed","description":"Expected data has not arrived.","priority":"high","request_key":"client-42"}'
```

The complete contract is in [OpenAPI](docs/openapi.yaml) and the human-readable [API guide](docs/api.md).

## Repository map

| Path                                      | Purpose                                                                                                                        |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `app/Enums/`                              | Roles, priorities, SLA targets, states, and transition rules.                                                                  |
| `app/Http/Controllers/`                   | Thin browser/API transport adapters and validation.                                                                            |
| `app/Http/Middleware/`                    | Demonstration actor resolution; replaceable at the application boundary.                                                       |
| `app/Models/`                             | Typed Eloquent entities and tenant-scoped query behavior.                                                                      |
| `app/Services/TicketService.php`          | Central authorization, transactions, audit, and outbox writes.                                                                 |
| `app/Services/OutboxProcessor.php`        | Bounded delivery with retry and dead-letter handling.                                                                          |
| `app/Services/SlaEscalationService.php`   | Idempotent scheduled SLA breach handling.                                                                                      |
| `app/Services/WebhookService.php`         | Replay-safe webhook receipt persistence.                                                                                       |
| `database/migrations/`                    | Relational constraints, operational indexes, and PostgreSQL full-text index.                                                   |
| `database/seeders/`                       | Synthetic multi-tenant demonstration data.                                                                                     |
| `resources/views/`                        | Escaping-by-default semantic HTML dashboard.                                                                                   |
| `resources/views/ticket.blade.php`        | Role-aware ownership/lifecycle forms, bounded discussion/audit pages and workflow help.                                        |
| `resources/css/`                          | Responsive, high-contrast visual system.                                                                                       |
| `resources/js/`                           | Optional live filtering and confirmation behavior.                                                                             |
| `routes/`                                 | Browser, API, worker, and scheduler entry points.                                                                              |
| `tests/`                                  | Lifecycle, authorization, injection, escaping, webhook, outbox, SLA, and frontend tests.                                       |
| `tests/Feature/OwnershipWorkflowTest.php` | Ownership/tenant/version invariants, exact deadline boundaries, bounded history and deterministic interleaved SLA regressions. |
| `playwright.config.js`, `tests/browser/`  | Owned PHP/SQLite/session runtime, CSRF and no-JavaScript desktop/mobile workflows, isolation controls and failure diagnostics. |
| `tools/validate_repo.php`                 | Reproducible documentation, header, action-pin, link, and secret checks.                                                       |
| `docs/code-index.md`                      | Generated exact locations for symbols and variables in every authored code file.                                               |
| `.github/workflows/`                      | CI and CodeQL automation.                                                                                                      |
| `Dockerfile` / `compose.yaml`             | Non-root application image and local PostgreSQL stack.                                                                         |

| `docs/handbook.md` | Organized current usage, architecture, security and validation documentation. |
| `docs/reports/project-guide.docx` | Reviewed Word guide bound to the cited source revision. |
| `docs/reports/publications.json` | Exact Office byte identities, approved visual scopes and immutable evidence baseline. |
| `docs/reports/documentation-refresh-2026-11-09.md` | Dated documentation integration checks and interpretation limits. |

## Documentation

- [Architecture](docs/architecture.md)
- [API](docs/api.md)
- [Complexity and performance](docs/complexity.md)
- [Data model](docs/data-model.md)
- [Operations and recovery](docs/operations.md)
- [Technology research](docs/research.md)
- [Security model](docs/security.md)
- [Testing strategy](docs/testing.md)
- [Ownership and ticket workspace](docs/ticket-workspace.md)
- [Practical workflow validation](docs/reports/practical-workflows-2026-11-09.md)
- [Code index](docs/code-index.md)
- [Dependency and Actions review](docs/reports/actions-review-2026-10-14.md)

## Scope

This repository is a focused engineering case study, not a hosted support product. Email/SMS delivery is represented by a replaceable log adapter; production identity, object storage, observability export, and managed secret storage are documented integration boundaries rather than simulated credentials.

Created by [Jason Stys](https://github.com/JasonStys). The profile and repository issues provide
contact paths for non-sensitive questions; report vulnerabilities privately as described in SECURITY.md.

## License

MIT — see [LICENSE](LICENSE).

## Documentation editions

Start with the [organized handbook](docs/handbook.md) for current usage, design, security and test guidance. Reviewed downloadable editions: [project-guide.docx](docs/reports/project-guide.docx).

[Publication identities and source scope](docs/reports/publications.json) · [Documentation validation record](docs/reports/documentation-refresh-2026-11-09.md). Historical results retain their own tested revision and date.

## Current Windows application guide

The published **1.1.0** app has a [four-page illustrated guide](docs/reports/windows-application-guide-1.1.0.docx) with actual installed screenshots, a worked user journey, important source excerpts and operating limits. Read the [dated validation report](docs/reports/installed-windows-2026-11-11.md) and [byte/page review manifest](docs/reports/windows-application-guide-1.1.0.json) for exact scope. Historical documentation editions remain unchanged.

Added files:

- [docs/reports/windows-application-guide-1.1.0.docx](docs/reports/windows-application-guide-1.1.0.docx): source-specific installed-app workflow, screenshots, code and measured validation.
- [docs/reports/windows-application-guide-1.1.0.json](docs/reports/windows-application-guide-1.1.0.json): document, screenshot, immutable-source and reviewed-page hashes.
- [docs/reports/installed-windows-2026-11-11.md](docs/reports/installed-windows-2026-11-11.md): observed results, installation, data handling and remaining limits.
