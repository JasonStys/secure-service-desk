<?php

/**
 * File: Handles CSRF-protected browser mutations and delegates domain rules to TicketService.
 * Symbols: TicketController, store(), comment(), and transition().
 * State: validated request fields only; exact declarations are indexed in docs/code-index.md.
 */

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    /** Inject the ticket domain service. */
    public function __construct(private readonly TicketService $tickets) {}

    /** Validate and create a ticket for the current tenant actor. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:4000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'tags' => ['sometimes', 'array', 'max:5'],
            'tags.*' => ['string', 'max:30', 'regex:/^[a-z0-9-]+$/'],
            'request_key' => ['nullable', 'string', 'max:100'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');
        $ticket = $this->tickets->create($actor, $data);

        return back()->with('status', "Ticket {$ticket->public_id} is ready.");
    }

    /** Validate and append a plain-text comment. */
    public function comment(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');
        $this->tickets->addComment($actor, $ticket, $data['body']);

        return back()->with('status', 'Comment added.');
    }

    /** Validate and apply an optimistic-lock-protected status transition. */
    public function transition(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
            'version' => ['required', 'integer', 'min:1'],
        ]);
        /** @var User $actor */
        $actor = $request->attributes->get('actor');
        $this->tickets->transition($actor, $ticket, TicketStatus::from($data['status']), $data['version']);

        return back()->with('status', 'Ticket status updated.');
    }
}
