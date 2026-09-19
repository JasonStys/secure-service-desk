<?php

/**
 * File: Verifies every permitted and rejected ticket lifecycle edge.
 * Symbols: TicketStatusTest, transitionProvider(), and test_transition_matrix().
 * State: status pairs and expected decisions; exact locations are in docs/code-index.md.
 */

namespace Tests\Unit;

use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    /** @return array<string, array{TicketStatus, TicketStatus, bool}> Lifecycle examples. */
    public static function transitionProvider(): array
    {
        return [
            'new to open' => [TicketStatus::New, TicketStatus::Open, true],
            'new to resolved rejected' => [TicketStatus::New, TicketStatus::Resolved, false],
            'open to pending' => [TicketStatus::Open, TicketStatus::Pending, true],
            'pending to open' => [TicketStatus::Pending, TicketStatus::Open, true],
            'open to resolved' => [TicketStatus::Open, TicketStatus::Resolved, true],
            'resolved can reopen' => [TicketStatus::Resolved, TicketStatus::Open, true],
            'closed is terminal' => [TicketStatus::Closed, TicketStatus::Open, false],
            'same state rejected' => [TicketStatus::Open, TicketStatus::Open, false],
        ];
    }

    /** Assert the finite-state-machine decision for one edge. */
    #[DataProvider('transitionProvider')]
    public function test_transition_matrix(TicketStatus $from, TicketStatus $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }
}
