<?php

/**
 * File: Exercises ticket idempotency, tenant access, transitions, escaping, and search safety.
 * Symbols: TicketWorkflowTest and six workflow/security tests.
 * State: refreshed database fixtures and JSON/browser responses; exact locations are in docs/code-index.md.
 */

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\AuditEntry;
use App\Models\OutboxMessage;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** Verify request-key replay creates one ticket, audit entry, and notification. */
    public function test_ticket_creation_is_idempotent_and_transactional(): void
    {
        $actors = $this->createActors();
        $payload = [
            'title' => 'Export delayed', 'description' => 'Expected data has not arrived.',
            'priority' => 'high', 'tags' => ['exports'], 'request_key' => 'request-001',
        ];

        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->postJson('/api/v1/tickets', $payload)->assertCreated();
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->postJson('/api/v1/tickets', $payload)->assertCreated();

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('audit_entries', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        self::assertSame($actors['tenant']->id, Ticket::firstOrFail()->tenant_id);
    }

    /** Verify an idempotency key cannot be reused to hide a different request. */
    public function test_ticket_request_key_reuse_with_changed_content_conflicts(): void
    {
        $actors = $this->createActors();
        $payload = [
            'title' => 'Original request', 'description' => 'Original description.',
            'priority' => 'normal', 'request_key' => 'request-conflict',
        ];
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->postJson('/api/v1/tickets', $payload)->assertCreated();

        $payload['title'] = 'Changed request';
        $this->withHeader('X-Demo-Actor', $actors['requester']->email)->postJson('/api/v1/tickets', $payload)->assertConflict();
        $this->assertDatabaseCount('tickets', 1);
    }

    /** Verify cross-tenant and other-requester ticket identifiers are concealed. */
    public function test_object_authorization_prevents_ticket_disclosure(): void
    {
        $actors = $this->createActors();
        $ticket = app(TicketService::class)->create($actors['requester'], [
            'title' => 'Private request', 'description' => 'Tenant-confidential details.',
            'priority' => 'normal', 'request_key' => 'private-1',
        ]);

        $this->withHeader('X-Demo-Actor', $actors['outsider']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/transition", ['status' => 'open', 'version' => 1])
            ->assertNotFound();
        $this->withHeader('X-Demo-Actor', $actors['outsider']->email)
            ->getJson('/api/v1/tickets')->assertOk()->assertJsonCount(0, 'data');
    }

    /** Verify allowed transitions update the audit/outbox state and stale writes fail. */
    public function test_transition_is_audited_and_optimistically_locked(): void
    {
        $actors = $this->createActors();
        $ticket = app(TicketService::class)->create($actors['requester'], [
            'title' => 'Lifecycle request', 'description' => 'Exercise explicit state edges.',
            'priority' => 'normal', 'request_key' => 'state-1',
        ]);

        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/transition", ['status' => 'open', 'version' => 1])
            ->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.version', 2);
        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/transition", ['status' => 'resolved', 'version' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('version');

        self::assertSame(TicketStatus::Open, $ticket->refresh()->status);
        self::assertSame(2, AuditEntry::query()->where('ticket_id', $ticket->id)->count());
        self::assertSame(2, OutboxMessage::query()->where('tenant_id', $ticket->tenant_id)->count());
    }

    /** Verify requesters cannot invoke agent-only lifecycle mutations. */
    public function test_requester_cannot_transition_ticket(): void
    {
        $actors = $this->createActors();
        $ticket = app(TicketService::class)->create($actors['requester'], [
            'title' => 'Restricted transition', 'description' => 'Requester should have read access only.',
            'priority' => 'low', 'request_key' => 'role-1',
        ]);

        $this->withHeader('X-Demo-Actor', $actors['requester']->email)
            ->postJson("/api/v1/tickets/{$ticket->id}/transition", ['status' => 'open', 'version' => 1])
            ->assertForbidden();
    }

    /** Verify untrusted markup is escaped in the server-rendered dashboard. */
    public function test_dashboard_escapes_ticket_content(): void
    {
        $actors = $this->createActors();
        app(TicketService::class)->create($actors['requester'], [
            'title' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(2)>',
            'priority' => 'normal', 'request_key' => 'xss-1',
        ]);

        $this->withHeader('X-Demo-Actor', $actors['agent']->email)->get('/')
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    /** Verify SQL-like search input remains data and does not broaden results. */
    public function test_search_treats_sql_metacharacters_as_literal_input(): void
    {
        $actors = $this->createActors();
        app(TicketService::class)->create($actors['requester'], [
            'title' => 'Ordinary title', 'description' => 'Nothing unusual.',
            'priority' => 'normal', 'request_key' => 'search-1',
        ]);

        $this->withHeader('X-Demo-Actor', $actors['agent']->email)
            ->get('/?q='.urlencode("%' OR 1=1 --"))->assertOk()->assertSee('No tickets match these filters.');
    }
}
