# API guide

Base path: `/api/v1`. Send `Accept: application/json` and `X-Demo-Actor` for the local demonstration adapter. Production deployments must replace that adapter with verified authentication.

## Endpoints

| Method/path | Purpose | Important constraints |
|---|---|---|
| `GET /tickets` | List actor-visible tickets. | `status` optional; `limit` 1–100, default 25. |
| `POST /tickets` | Create or replay a ticket. | `request_key` required and unique per tenant. |
| `POST /tickets/{id}/transition` | Apply a state-machine edge. | Agent role, same tenant, expected `version`. |
| `POST /tickets/{id}/assignment` | Assign/unassign ownership. | Agent/admin role, eligible same-tenant target or null, expected `version`. |
| `POST /webhooks/ticket-events` | Record an external event receipt. | Valid HMAC, provider/event id, and ≤50 payload fields. |

All routes are rate limited to 60 requests per minute per resolved actor. Validation failures use Laravel's `422` JSON format. Unauthorized identity returns `401`; insufficient role returns `403`; invisible objects return `404`; changed webhook content under a used key returns `409`.

## Idempotent creation

Clients choose a stable `request_key` for one logical operation. A retry with the same tenant/key returns the existing ticket and does not append duplicate audit/outbox records. Clients should reuse a key only for an exact logical retry.

```json
{
  "title": "Export delayed",
  "description": "Expected data has not arrived.",
  "priority": "high",
  "tags": ["exports"],
  "request_key": "mobile-7f38"
}
```

## Optimistic transitions

Read `version` from the ticket representation and submit it with the next state. A `422` on `version` means another writer won; refetch, review the current state, and decide whether to retry.

```json
{"status":"open","version":1}
```

## Signed webhooks

Compute a lowercase hexadecimal HMAC-SHA256 over the exact HTTP request body using `WEBHOOK_SECRET` and send it as `X-Webhook-Signature`. The same tenant/provider/event id and same body is a safe replay. Reusing the key with different content is a conflict.

The machine-readable schema and examples are in [openapi.yaml](openapi.yaml).

## Ownership and browser review

Send `{"assignee_id":42,"version":3}` or `{"assignee_id":null,"version":3}`. The field is required
even when null. Missing, foreign-tenant, requester-role or unknown targets fail validation without
disclosing target details. Invisible tickets return 404; requesters cannot assign their own work.
Ownership and lifecycle use one version, so either operation invalidates the other writer's old
version. A same-owner no-op with the current version creates no new audit/outbox entry. Resolved
work must be reopened first; closed work cannot be assigned. SLA timestamps and status are preserved.

Browser routes: `GET /tickets/{id}`; CSRF-protected `POST /tickets/{id}/assignment`; existing comment
and lifecycle forms. Discussion/audit use independent `comments_page` and `audit_page`, each
20 newest-first entries. Page bounds are1–100000. These live offset pages can move under concurrent
inserts and do not claim a signed or frozen timeline. The dashboard adds `owner=mine|unassigned`
and `due=overdue|due-soon`; due-now is overdue, next24hours is exclusive of now/inclusive of24h,
and resolved/closed tickets are excluded from SLA filters. All filters preserve actor access.
