<?php

/**
 * File: Declares the least-privilege roles used by authorization rules.
 * Symbols: Role enum and permission helpers.
 * State: enum cases only; exact declarations are indexed in docs/code-index.md.
 */

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Agent = 'agent';
    case Requester = 'requester';

    /** Determine whether a role may manage tickets for its tenant. */
    public function canManageTickets(): bool
    {
        return $this === self::Admin || $this === self::Agent;
    }
}
