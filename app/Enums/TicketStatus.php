<?php

/**
 * File: Defines ticket lifecycle states and explicit transition rules.
 * Symbols: TicketStatus enum, canTransitionTo(), and terminal().
 * State: enum cases only; exact declarations are indexed in docs/code-index.md.
 */

namespace App\Enums;

enum TicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /** Check the finite-state-machine edge before a mutation is attempted. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::New => [self::Open, self::Closed],
            self::Open => [self::Pending, self::Resolved, self::Closed],
            self::Pending => [self::Open, self::Resolved, self::Closed],
            self::Resolved => [self::Open, self::Closed],
            self::Closed => [],
        }, true);
    }

    /** Report whether no further lifecycle transition is permitted. */
    public function terminal(): bool
    {
        return $this === self::Closed;
    }
}
