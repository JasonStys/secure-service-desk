# Operations and recovery

## Processes

- **Web:** serves browser and `/api/v1` routes; health endpoint is `/up`.
- **Scheduler:** run `php artisan schedule:work` or invoke `schedule:run` each minute.
- **Outbox worker:** scheduled `outbox:process`; scale separately when delivery volume grows.
- **Database:** PostgreSQL in production, SQLite for local development/tests.

## Routine commands

```bash
php artisan migrate --force
php artisan tickets:escalate --limit=100
php artisan outbox:process --limit=25
php artisan schedule:list
php artisan route:list
```

Deploy migrations before switching traffic to code that requires them. Backward-incompatible changes use expand/migrate/contract releases rather than a single destructive migration.

## Observability

Track HTTP rate/error/duration, database pool saturation and slow statements, outbox pending age/count, dead-letter count, SLA breach count/age, webhook authentication failures, and scheduler last-success time. Correlate by request id, ticket public id, audit id, and outbox deduplication key; do not log ticket bodies or secrets.

## Backup and restore drill

Backup (example; credentials come from the environment):

```bash
pg_dump --format=custom --no-owner --file=service-desk.dump "$DATABASE_URL"
sha256sum service-desk.dump > service-desk.dump.sha256
```

Restore into an isolated empty database:

```bash
sha256sum --check service-desk.dump.sha256
createdb service_desk_restore_test
pg_restore --exit-on-error --no-owner --dbname=service_desk_restore_test service-desk.dump
```

Then point a disposable application instance at the restored database, run `php artisan migrate:status`, compare row counts by tenant, sample ticket/audit relationships, and process no external deliveries. Record elapsed time and any repair steps. A backup is not considered verified until this restore drill succeeds.

## Dead-letter runbook

1. Pause or isolate the failing delivery adapter if failures are ongoing.
2. Inspect message topic, deduplication key, attempts, timestamps, and sanitized error; never paste full payloads into chat/tickets.
3. Confirm downstream availability and whether delivery may already have succeeded.
4. Fix configuration/code and deploy through CI.
5. Reset only explicitly reviewed messages to `pending` with `available_at=now`; preserve attempts/audit context.
6. Confirm delivery and close the incident with cause and prevention.

## Rollback

Prefer forward fixes for additive schema changes. For an application rollback, keep the database at a version understood by both releases, stop new workers, deploy the prior immutable image, verify `/up` and one read-only tenant query, then resume workers. Never roll back by deleting the database or resetting a production branch.
