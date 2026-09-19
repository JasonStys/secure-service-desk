<?php

/**
 * File: Represents a tenant-scoped service-desk actor.
 * Symbols: User model, tenant(), requestedTickets(), and assignedTickets().
 * State: fillable identity, tenant, role, and credential fields; exact locations are in docs/code-index.md.
 */

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> Attributes accepted during trusted model creation. */
    protected $fillable = ['tenant_id', 'name', 'email', 'password', 'role'];

    /** @var list<string> Attributes excluded from serialized responses. */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> Runtime type conversions for persisted values. */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /** @return BelongsTo<Tenant, $this> Owning tenant relationship. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Ticket, $this> Tickets submitted by this user. */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /** @return HasMany<Ticket, $this> Tickets assigned to this user. */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }
}
