# Secure Service Desk handbook

By Jason Stys.

Manage tenant-scoped support tickets, assignment, shared-version changes, SLA queues and audit/outbox records. Use the documented demonstration identities and local setup; a shared deployment needs a real authenticated identity boundary.

Assign and unassign tenant agents with shared version checks, atomic audit/outbox writes and bounded ticket history; filter owner/SLA queues and recheck stale authorization and SLA candidates.

Turns a ticket list into an auditable support workflow that resists lost updates, partial commits and cross-tenant mistakes.

## Read the documentation

| Edition | Audience and contents |
| --- | --- |
| [project-guide.docx](reports/project-guide.docx) | User and developer guide: purpose, current usage, design, boundaries and source references. |

The editable Markdown remains the maintenance source. The Word files are readable editions. See [publication identities and review](reports/publications.json) and the [documentation refresh record](reports/documentation-refresh-2026-11-09.md).

## Current usage and help

[README quick start](../README.md) is the entry point. Use the project-specific guides below before changing inputs, operating modes or deployment assumptions.

### Usage and operations

- [operations](operations.md)

### Design and contracts

- [api](api.md)
- [architecture](architecture.md)
- [complexity](complexity.md)
- [data model](data-model.md)
- [research](research.md)

### Security and validation

- [security](security.md)
- [testing](testing.md)

## Historical results and reproduction

The reviewed source is [`561f1a428594d3a12fd71c9c969e8e6f6da4efe3`](https://github.com/JasonStys/secure-service-desk/tree/561f1a428594d3a12fd71c9c969e8e6f6da4efe3). The observed functional workflow baseline is `561f1a428594d3a12fd71c9c969e8e6f6da4efe3`; those observations are recorded at `2026-11-10T05:25:40.628667+00:00`. They do not certify an unrun later revision.

- [Detailed recorded validation](reports/practical-workflows-2026-11-09.md)
- [Current Actions](https://github.com/JasonStys/secure-service-desk/actions) records later integration checks separately.
- Preserve dated measurements and follow the existing testing guide to reproduce them; a test-source inventory alone does not establish execution.

## Practical boundaries

- Synthetic local demo; the identity adapter is not production authentication and notifications use a log adapter.
- Bounded offset history can shift under concurrent inserts; it is not a signed snapshot or enterprise scaling claim.
- Browser checks cover desktop/mobile Chromium-family and no-JavaScript paths, not every device or assistive technology.

## Maintaining these editions

Update the editable usage/design/test documents first. Rebuild and visually review every Word page and workbook tab before replacing an edition; verify the exact binary digests and evidence source in publications.json. Keep account data, logs with secrets, installed-app state and private test stores out of documentation. Source and release assets retain their existing security and licensing boundaries.
