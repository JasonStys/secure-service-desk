<?php

/**
 * File: Owns tenant-safe ticket creation, comments, and finite-state transitions.
 * Symbols: TicketService and its create(), addComment(), transition(), and visibleOrFail() operations.
 * State: transaction-scoped ticket, audit, and outbox records; exact locations are in docs/code-index.md.
 */

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\AuditEntry;
use App\Models\OutboxMessage;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TicketService
{
    /** Create or safely replay a ticket request keyed within one tenant. */
    public function create(User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($actor, $data): Ticket {
            $requestKey = $data['request_key'] ?? null;
            $priority = Priority::from($data['priority']);
            $tags = array_values(array_unique($data['tags'] ?? []));
            if ($requestKey !== null) {
                $existing = Ticket::query()
                    ->where('tenant_id', $actor->tenant_id)
                    ->where('request_key', $requestKey)
                    ->first();
                if ($existing !== null) {
                    $this->assertSameCreateIntent($existing, $actor, $data, $priority, $tags);

                    return $existing;
                }
            }

            $values = [
                'requester_id' => $actor->id,
                'public_id' => (string) Str::uuid(),
                'title' => $data['title'],
                'description' => $data['description'],
                'status' => TicketStatus::New,
                'priority' => $priority,
                'tags' => $tags,
                'sla_due_at' => now()->addHours($priority->slaHours()),
            ];
            $ticket = $requestKey === null
                ? Ticket::query()->create(['tenant_id' => $actor->tenant_id, 'request_key' => null] + $values)
                : Ticket::query()->createOrFirst(
                    ['tenant_id' => $actor->tenant_id, 'request_key' => $requestKey],
                    $values,
                );

            if (! $ticket->wasRecentlyCreated) {
                $this->assertSameCreateIntent($ticket, $actor, $data, $priority, $tags);

                return $ticket;
            }

            $this->record($ticket, $actor, 'ticket.created', ['priority' => $priority->value]);

            return $ticket;
        });
    }

    /** Reject replay keys whose stored ticket differs from the caller's current intent. */
    private function assertSameCreateIntent(
        Ticket $ticket,
        User $actor,
        array $data,
        Priority $priority,
        array $tags,
    ): void {
        $sameIntent = $ticket->requester_id === $actor->id
            && $ticket->title === $data['title']
            && $ticket->description === $data['description']
            && $ticket->priority === $priority
            && $ticket->tags === $tags;
        if (! $sameIntent) {
            throw new ConflictHttpException('The request key was already used for a different ticket.');
        }
    }

    /** Append a comment after checking object- and tenant-level access. */
    public function addComment(User $actor, Ticket $ticket, string $body): TicketComment
    {
        $ticket = $this->visibleOrFail($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $body): TicketComment {
            $comment = TicketComment::query()->create([
                'tenant_id' => $ticket->tenant_id,
                'ticket_id' => $ticket->id,
                'author_id' => $actor->id,
                'body' => $body,
            ]);
            $this->record($ticket, $actor, 'ticket.comment_added', ['comment_id' => $comment->id]);

            return $comment;
        });
    }

    /** Perform an authorized, optimistic-lock-protected lifecycle transition. */
    public function transition(User $actor, Ticket $ticket, TicketStatus $next, int $expectedVersion): Ticket
    {
        if (! $actor->role->canManageTickets()) {
            throw new AuthorizationException('Only service agents may change ticket status.');
        }

        $this->visibleOrFail($actor, $ticket);

        return DB::transaction(function () use ($actor, $ticket, $next, $expectedVersion): Ticket {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($current->version !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'The ticket changed; refresh before retrying.']);
            }
            if (! $current->status->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => "Transition from {$current->status->value} to {$next->value} is not allowed.",
                ]);
            }

            $previous = $current->status;
            $current->forceFill([
                'status' => $next,
                'version' => $current->version + 1,
                'resolved_at' => $next === TicketStatus::Resolved ? now() : null,
            ])->save();

            $this->record($current, $actor, 'ticket.transitioned', [
                'from' => $previous->value,
                'to' => $next->value,
                'version' => $current->version,
            ]);

            return $current->refresh();
        });
    }

    /** Resolve a ticket only when the actor is allowed to observe it. */
    public function visibleOrFail(User $actor, Ticket $ticket): Ticket
    {
        $visible = $ticket->tenant_id === $actor->tenant_id
            && ($actor->role->canManageTickets() || $ticket->requester_id === $actor->id);

        if (! $visible) {
            throw (new ModelNotFoundException)->setModel(Ticket::class, [$ticket->getKey()]);
        }

        return $ticket;
    }

    /** Append an audit row and a deduplicated notification in the caller's transaction. */
    private function record(Ticket $ticket, User $actor, string $event, array $metadata): void
    {
        $entry = AuditEntry::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'metadata' => $metadata,
        ]);

        OutboxMessage::query()->create([
            'tenant_id' => $ticket->tenant_id,
            'deduplication_key' => "audit:{$entry->id}",
            'topic' => $event,
            'payload' => ['ticket' => $ticket->public_id, 'audit_entry' => $entry->id],
            'available_at' => now(),
        ]);
    }
}
