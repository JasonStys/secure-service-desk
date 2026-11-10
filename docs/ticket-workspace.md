# Ownership and ticket workspace

The workspace turns a queue of cards into an actionable support workflow. It is designed for a
local synthetic help desk, with explicit deployment limits instead of a production identity claim.

1. As a requester, create an ordinary incident; the tag field can remain blank. Open the title to
   view your request, comment and inspect its recorded history. Another requester's or tenant's
   identifier never grants access.
2. As an agent/admin, filter the queue by status, priority, text, owner and SLA window. Typing is a
   preview of the current rendered page; Apply searches all actor-visible tickets. Due-now is
   overdue; next24hours excludes now and includes its upper endpoint. Resolved/closed work is
   excluded from SLA windows. These are original deadline filters, not predicted resolution times.
3. Open the ticket, review its owner/status/deadline/version, choose an eligible agent or
   Unassigned, and save. Assignment changes one ticket plus its audit/outbox intent atomically.
   It does not reset SLA, change priority or silently open/reopen/close the ticket.
4. A stale page is rejected after another agent changes assignment or lifecycle. Refresh and
   review the new version before retrying. Saving the current owner again with the current version
   is a no-op. Resolved tickets need an explicit reopen; closed tickets cannot be reassigned.
5. Discussion and audit history show20 newest-first entries each, with independent newer/older
   navigation. They are bounded live pages, not a historical reconstruction or immutable export.
   Text and metadata are escaped. New concurrent inserts can shift offset pages.

The selector displays the first200 eligible agents alphabetically and preserves any current owner
outside that window; truncation is explicitly labelled. The validated API can address other
eligible IDs. A former agent may still be shown as current owner, but eligibility is rechecked on
save. No background account sync, auto-dispatch, email delivery guarantee or SLA reset is implied.

The `X-Demo-Actor`/actor query adapter lets users choose synthetic identities. It is not a secure
login system and must be replaced before hosting private information. Domain tenant/role checks
remain enforced independently. Creator/contact: [Jason Stys](https://github.com/JasonStys).
