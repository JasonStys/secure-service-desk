# Complexity and performance

Let `n` be tickets visible to one tenant, `b` the requested batch size, and `t` the number of query tokens (capped at 10 in the browser helper).

| Operation | Time | Space | Bound/optimization |
|---|---:|---:|---|
| Lifecycle/ownership change | O(log n) indexed lookup/write | O(1) application data | Primary-key row lock, version check, constant-count audit/outbox writes; database contention can dominate. |
| Ticket create/replay | O(log n) | O(1) | Unique `(tenant_id, request_key)` lookup. |
| Tenant queue filter | Plan/filter/offset dependent | O(b) | Page size15 and total-count query; large offsets/counts can scan many rows despite supporting indexes. |
| PostgreSQL text search | Index-dependent, typically sublinear + results | O(b) | GIN expression index and result pagination. |
| SQLite fallback search | O(n) | O(b) | Intended for local/testing datasets only; wildcard characters escaped. |
| Outbox scan | O(log n + b) | O(b) | Status/availability index; batch clamped to 1–100. |
| SLA scan | Plan/selectivity dependent; O(n) worst-case scan | O(b) | Global breach/deadline/id index, active-status residual filter; batch clamped to1–500. |
| Browser live filter | O(cards × min(t,10) × text) | O(cards) | Query token count capped; only the rendered page is scanned. |

## Performance budgets

- API list maximum: 100 tickets.
- Browser page: 15 tickets.
- Ticket discussion/audit:20 entries each, independent bounded offset pages; eligible-agent UI
  retains201 rows to signal truncation and displays200 plus any current owner. API validation
  still accepts other eligible same-tenant IDs. Query/memory costs remain database-plan dependent.
- Ticket title/description: 160/4,000 characters.
- Comment: 2,000 characters.
- Tags: five values, 30 characters each.
- Webhook payload: 50 top-level fields.
- Outbox error text: 500 characters.
- Outbox attempts: three.

These are load-shedding boundaries as well as validation rules. Production tuning should use statement statistics and realistic tenant distributions; average-only benchmarks can hide tail latency and hot-tenant behavior.

## Query review

The dashboard eager-loads requester and assignee to avoid N+1 queries. All ticket reads begin with a tenant/object scope. Operational scans order by the indexed scheduling field and cap their batch. PostgreSQL CI verifies that migrations and full-text syntax remain valid.

The additive workspace migration adds tenant/owner/created/id and ticket/id history indexes.
No data is rewritten. Ownership performs constant-count queries and does not enumerate all tickets.
SLA filters capture one request time; their total-count and ordered result queries are separate live
reads. This is an operator review, not a load-tested enterprise ticket platform.
