<?php

/**
 * File: Marks overdue active tickets once and emits auditable escalation messages.
 * Symbols: SlaEscalationService and escalateDue().
 * State: SLA breach timestamp, audit entry, and outbox notification.
 */

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\OutboxMessage;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class SlaEscalationService
{
    /** Escalate a bounded batch of active tickets whose response target has elapsed. */
    public function escalateDue(int $limit = 100): int
    {
        $ids = Ticket::query()
            ->whereNull('sla_breached_at')
            ->where('sla_due_at', '<=', now())
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderBy('sla_due_at')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            $count += DB::transaction(function () use ($id): int {
                $ticket = Ticket::query()->lockForUpdate()->find($id);
                if ($ticket === null || $ticket->sla_breached_at !== null || $ticket->status->terminal()) {
                    return 0;
                }

                $ticket->forceFill(['sla_breached_at' => now()])->save();
                $entry = AuditEntry::query()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'ticket_id' => $ticket->id,
                    'actor_id' => null,
                    'event' => 'ticket.sla_breached',
                    'metadata' => ['due_at' => $ticket->sla_due_at->toIso8601String()],
                ]);
                OutboxMessage::query()->create([
                    'tenant_id' => $ticket->tenant_id,
                    'deduplication_key' => "audit:{$entry->id}",
                    'topic' => 'ticket.sla_breached',
                    'payload' => ['ticket' => $ticket->public_id, 'audit_entry' => $entry->id],
                    'available_at' => now(),
                ]);

                return 1;
            });
        }

        return $count;
    }
}
