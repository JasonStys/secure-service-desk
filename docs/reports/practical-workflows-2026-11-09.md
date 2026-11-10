# Practical support workflows — 2026-11-09

Author: Jason Stys. Synthetic local service desk; no private customer records, credentials or
existing application database were used. This report distinguishes completed local checks from
separate hosted PostgreSQL/container validation.

## Purpose and implementation

An agent needs to own a support request, investigate its history, coordinate with another agent and
avoid losing updates. The new ticket workspace connects assignment/unassignment, current version,
discussion, audit and lifecycle controls. Dashboard owner/SLA filters complement status, priority
and text search. Requester and other-tenant access stays constrained by the shared domain service.

Changes are transactional: a real ownership change increments the same version used by lifecycle
writes, records exact prior/new ownership and appends notification intent. Stale versions and
invalid targets do not write; a same-owner current-version save is a no-op. Assignment preserves
the deadline, state, priority and content. Resolved/closed work is guarded explicitly.

Discussion and audit use independent20-entry newest-first pages. The first200 eligible agents are
shown with truncation disclosed; any current owner outside that window remains selected. Metadata
and comments render as text. Queue/history pages are live offset reads, not a signed snapshot or
claim that concurrent inserts cannot shift pagination. The new additive migration changes indexes
only. Complexity documentation now acknowledges counts, offsets, residual filtering and contention.

## Genuine defects corrected

- A newly created ticket returned an unrefreshed model without database defaults, including the
  initial version. The first API response now exactly matches persisted defaults and its replay.
- An optional blank browser tag field failed validation. It is now omitted consistently, without
  accepting malformed nonempty tags; the HTML pattern uses a valid literal hyphen.
- SLA candidates were not fully rechecked under their write lock. Interleaved resolution, closure
  or deadline extension now prevents a stale escalation and its audit/outbox writes.
- Mutation authorization could rely on an old route-bound ticket. Assignment, transition and
  comment writes now reauthorize the locked current row before changing anything.
- A narrow history navigator could overflow; wrapping preserves visible, reachable links.
- SQLite literal search escaped metacharacters without declaring an SQL escape character. Bound
  patterns now use explicit `ESCAPE '!'`; independent percent/underscore/exclamation/backslash
  cases run against a separate fresh SQLite connection even in the PostgreSQL CI suite.
- The locked Composer graph had seven advisories across four Symfony components. Minimal compatible
  patches selected HTTP Foundation/Kernel/Routing8.1.11 and MIME8.1.10. Full Composer and npm audit
  results were then clear; no advisory suppression or test-gate reduction was introduced.

## Completed local verification

| Gate | Result |
|---|---|
| PHP8.5.10 / SQLite / PHPUnit12.5.40 |34tests,166assertions,0failures; strict warnings gate passed |
| Independent invariants |Exact state/audit/outbox deltas; no-op; unassign; stale lifecycle/assignment; missing/foreign/ineligible IDs; role/tenant concealment |
| Failure controls |Injected outbox exception rolls back ownership/audit; connection-local query-event interleaving invalidates SLA selection; stale persisted access denies all three mutation types |
| Boundaries/usability |Due-now/exact24h/just-after window, resolved exclusions,23comments/two history pages, escaped hostile content,201-agent selector and retained current admin |
| API contract |First-create response equals persisted record and exact replay, including version1 and explicit null owner |
| JavaScript |4existing normalization/matching/work-bound cases passed |
| Assets |Actual production Vite build passed; browser fixtures use built assets |
| Actual browser application |22desktop/mobile Chromium journeys passed40.3s against owned PHP8.5/SQLite/session processes and rebuilt assets; no API/HTML mocks |
| CSRF/user workflows |Real fallback missing/wrong token419 and valid302; no-JavaScript create/comment/assign; requester/agent/other requester/other tenant; stale/noop/foreign assignment; legal resolve/reopen/unassign |
| Browser evidence/usability |21comments20+1 and22audit20+2 windows; unchanged state/deadlines for refused/canceled operations; compiled live filter; canceled confirmation; Axe/reflow passed |
| Isolation controls |Random APP_KEY, new temporary database/storage/session/config paths; separate environment path prevents reading real.env; no HMR reuse; invalid/occupied ports refuse and preserve listener |
| Dependency audits |Composer locked graph and complete npm graph reported0known findings after the patches |

Laravel Pint, browser fixture formatting, generated declaration/variable index,
source-header/link/action-pin/secret checks and diff checks passed before source publication.
Hosted PostgreSQL, container and CodeQL results are coordinated separately and must pass before
merge. Browser coverage is Chromium-family desktop/mobile plus no-JavaScript, not a universal
browser or device certification. Existing gates remain; the new actual-browser job depends on
backend/frontend/repository checks and the complete npm audit now fails at moderate severity.

## Boundaries

The demo identity adapter is not production authentication. Notifications are local log delivery,
not email/SMS guarantees. Private hosting needs a verified identity provider, operational backups,
retention and deployment review. Tests demonstrate the documented cases, not every possible
workload, browser, accessibility need or threat. No local Docker repair or unrelated data access
was performed. See [workspace guide](../ticket-workspace.md), [API](../api.md),
[testing](../testing.md) and [official security references](../research.md).
