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
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');

        $query = Ticket::query()->forActor($actor)->with(['requester', 'assignee']);
        $query->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));
        $query->when($filters['priority'] ?? null, fn ($builder, $priority) => $builder->where('priority', $priority));

        if (($filters['q'] ?? '') !== '') {
            $term = $filters['q'];
            if (DB::getDriverName() === 'pgsql') {
                $query->whereFullText(['title', 'description'], $term, ['language' => 'english']);
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%"));
            }
        }

        return view('dashboard', [
            'actor' => $actor,
            'tickets' => $query->latest()->paginate(15)->withQueryString(),
            'statuses' => TicketStatus::cases(),
            'priorities' => Priority::cases(),
            'filters' => $filters,
        ]);
    }
}
