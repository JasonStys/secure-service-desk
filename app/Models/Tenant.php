<?php

/**
 * File: Represents an isolated customer workspace.
 * Symbols: Tenant model, users(), and tickets().
 * State: name and slug; exact locations are indexed in docs/code-index.md.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    /** @var list<string> Tenant attributes accepted by trusted services. */
    protected $fillable = ['name', 'slug'];

    /** @return HasMany<User, $this> Tenant members. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Ticket, $this> Tenant tickets. */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
