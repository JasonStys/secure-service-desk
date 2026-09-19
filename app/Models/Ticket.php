<?php

/**
 * File: Persists a tenant-owned support request and its lifecycle state.
 * Symbols: Ticket model plus tenant, requester, assignee, comments, and audit relationships.
 * State: identifiers, content, priority/status, SLA timestamps, tags, and lock version; see docs/code-index.md.
 */

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    /** @var list<string> Attributes written by the ticket service. */
    protected $fillable = [
        'tenant_id', 'requester_id', 'assignee_id', 'public_id', 'request_key',
        'title', 'description', 'status', 'priority', 'tags', 'sla_due_at',
        'sla_breached_at', 'resolved_at', 'version',
    ];

    /** @return array<string, string> Runtime type conversions for persisted values. */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => Priority::class,
            'tags' => 'array',
            'sla_due_at' => 'immutable_datetime',
            'sla_breached_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }

    /** @param Builder<Ticket> $query @return Builder<Ticket> Tenant-isolated query. */
    public function scopeForActor(Builder $query, User $actor): Builder
    {
        $query->where('tenant_id', $actor->tenant_id);

        return $actor->role->canManageTickets()
            ? $query
            : $query->where('requester_id', $actor->id);
    }

    /** @return BelongsTo<Tenant, $this> Owning tenant. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> User who submitted the request. */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> Assigned service agent, if any. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<TicketComment, $this> Chronological ticket discussion. */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    /** @return HasMany<AuditEntry, $this> Immutable mutation history. */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditEntry::class)->oldest();
    }
}
