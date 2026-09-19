<?php

/**
 * File: Represents an append-only security and lifecycle audit record.
 * Symbols: AuditEntry model and ticket relationship.
 * State: tenant, actor, event, ticket, and structured metadata; see docs/code-index.md.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> Audit fields written inside domain transactions. */
    protected $fillable = ['tenant_id', 'ticket_id', 'actor_id', 'event', 'metadata'];

    /** @return array<string, string> Runtime type conversions for audit metadata. */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Ticket, $this> Related ticket. */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
