<?php

/**
 * File: Exposes bounded, tenant-scoped ticket operations through API version 1.
 * Symbols: TicketApiController, index(), store(), and transition().
 * State: validated filters and payload fields; exact locations are in docs/code-index.md.
 */

namespace App\Http\Controllers\Api;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketApiController extends Controller
{
    /** Inject the ticket domain service. */
    public function __construct(private readonly TicketService $tickets) {}

    /** Return a bounded page of tickets visible to the actor. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');
        $tickets = Ticket::query()->forActor($actor)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()->limit($filters['limit'] ?? 25)->get();

        return response()->json(['data' => $tickets]);
    }

    /** Create or replay a request-keyed ticket operation. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'tags' => ['sometimes', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30', 'regex:/^[a-z0-9-]+$/'],
            'request_key' => ['required', 'string', 'max:100'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');

        return response()->json(['data' => $this->tickets->create($actor, $data)], 201);
    }

    /** Apply a finite-state transition with optimistic concurrency control. */
    public function transition(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');
        $updated = $this->tickets->transition(
            $actor,
            $ticket,
            TicketStatus::from($data['status']),
            $data['version'],
        );

        return response()->json(['data' => $updated]);
    }
}
