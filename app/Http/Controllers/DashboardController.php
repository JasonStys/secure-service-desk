<?php

/**
 * File: Builds the server-rendered, tenant-scoped ticket dashboard.
 * Symbols: DashboardController and __invoke().
 * State: validated filters, paginated tickets, and actor context; exact locations are in docs/code-index.md.
 */

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Render tickets visible to the resolved actor with bounded filters. */
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(TicketStatus::cases(), 'value'))],
            'priority' => ['nullable', 'string', 'in:'.implode(',', array_column(Priority::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'owner' => ['nullable', 'in:mine,unassigned'],
            'due' => ['nullable', 'in:overdue,due-soon'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');

        $query = Ticket::query()->forActor($actor)->with(['requester', 'assignee']);
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));
        $query->when($filters['priority'] ?? null, fn ($builder, $priority) => $builder->where('priority', $priority));
        if (($filters['owner'] ?? '') === 'mine') {
            $query->where('assignee_id', $actor->id);
        } elseif (($filters['owner'] ?? '') === 'unassigned') {
            $query->whereNull('assignee_id');
        }
        $capturedAt = now()->toImmutable();
        if (($filters['due'] ?? '') !== '') {
            $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
            if ($filters['due'] === 'overdue') {
                $query->where('sla_due_at', '<=', $capturedAt);
            } else {
                $query->where('sla_due_at', '>', $capturedAt)->where('sla_due_at', '<=', $capturedAt->addHours(24));
            }
        }

        if (($filters['q'] ?? '') !== '') {
            $term = $filters['q'];
            if (DB::getDriverName() === 'pgsql') {
                $query->whereFullText(['title', 'description'], $term, ['language' => 'english']);
            } else {
                // SQLite does not interpret backslash as a LIKE escape without an explicit clause.
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
                $query->where(fn ($nested) => $nested
                    ->whereRaw("title LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", ["%{$escaped}%"]));
            }
        }

        return view('dashboard', [
            'actor' => $actor,
            'tickets' => $query->latest()->orderByDesc('id')->paginate(15)->withQueryString(),
            'statuses' => TicketStatus::cases(),
            'priorities' => Priority::cases(),
            'filters' => $filters,
            'capturedAt' => $capturedAt,
        ]);
    }
}
