<?php

/**
 * File: Stores a plain-text comment attached to a tenant-owned ticket.
 * Symbols: TicketComment model, ticket(), and author().
 * State: tenant, ticket, author, and body fields; exact locations are in docs/code-index.md.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    /** @var list<string> Comment fields written by the ticket service. */
    protected $fillable = ['tenant_id', 'ticket_id', 'author_id', 'body'];

    /** @return BelongsTo<Ticket, $this> Parent ticket. */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> Comment author. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
