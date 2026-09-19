# Complexity and performance

Let `n` be tickets visible to one tenant, `b` the requested batch size, and `t` the number of query tokens (capped at 10 in the browser helper).

| Operation | Time | Space | Bound/optimization |
|---|---:|---:|---|
| Lifecycle transition | O(1) | O(1) | Primary-key row lock plus indexed writes. |
| Ticket create/replay | O(log n) | O(1) | Unique `(tenant_id, request_key)` lookup. |
| Tenant queue filter | O(log n + b) | O(b) | Composite tenant/status/priority index; page size 15. |
| PostgreSQL text search | Index-dependent, typically sublinear + results | O(b) | GIN expression index and result pagination. |
| SQLite fallback search | O(n) | O(b) | Intended for local/testing datasets only; wildcard characters escaped. |
| Outbox scan | O(log n + b) | O(b) | Status/availability index; batch clamped to 1–100. |
| SLA scan | O(log n + b) | O(b) | Tenant/SLA index; batch clamped to 1–500. |
| Browser live filter | O(cards × min(t,10) × text) | O(cards) | Query token count capped; only the rendered page is scanned. |

## Performance budgets

- API list maximum: 100 tickets.
- Browser page: 15 tickets.
- Ticket title/description: 160/4,000 characters.
- Comment: 2,000 characters.
- Tags: five values, 30 characters each.
- Webhook payload: 50 top-level fields.
- Outbox error text: 500 characters.
- Outbox attempts: three.

These are load-shedding boundaries as well as validation rules. Production tuning should use statement statistics and realistic tenant distributions; average-only benchmarks can hide tail latency and hot-tenant behavior.

## Query review

The dashboard eager-loads requester and assignee to avoid N+1 queries. All ticket reads begin with a tenant/object scope. Operational scans order by the indexed scheduling field and cap their batch. PostgreSQL CI verifies that migrations and full-text syntax remain valid.
