<?php

/**
 * File: Defines normalized ticket priorities and SLA targets.
 * Symbols: Priority enum and slaHours().
 * State: enum cases only; exact declarations are indexed in docs/code-index.md.
 */

namespace App\Enums;

enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    /** Return the response target in hours for this priority. */
    public function slaHours(): int
    {
        return match ($this) {
            self::Low => 72,
            self::Normal => 24,
            self::High => 8,
            self::Critical => 2,
        };
    }
}
